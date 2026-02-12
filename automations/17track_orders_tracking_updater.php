<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

/**
 * CRON-SAFE 17TRACK TRACKER (cPanel/WHM) + WEB TEST MODE
 *
 * AUTH:
 * - We ONLY read the 17TRACK access key from DB:
 *   SELECT access_token FROM tblapis WHERE api_name='17Track' LIMIT 1;
 *
 * 17TRACK DOCS NOTES:
 * - Header: 17token: <your_access_key>
 * - Endpoint base: https://api.17track.net/track/v1
 * - Important: numbers should be registered first via POST /register
 * - Rate limit mentioned in docs: ~3 req/s (be gentle)
 */

/* =========================
   Web-only flags + gate
========================= */

$DEBUG = false;
$LIMIT = 0;         // 0 = no limit (cron)
$RUN_ONCE = false;  // web-only convenience

// CHANGE THIS TO A LONG RANDOM STRING
define('WEB_RUN_KEY', 'Rawr');

if (php_sapi_name() !== 'cli') {
    // Gate the endpoint
    $key = (string) ($_GET['key'] ?? '');
    if ($key !== WEB_RUN_KEY) {
        http_response_code(403);
        exit('Forbidden');
    }

    $DEBUG = !empty($_GET['debug']);
    $RUN_ONCE = !empty($_GET['run_once']);

    // Default web testing is LIMIT 1
    $LIMIT = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 1;

    if ($RUN_ONCE)
        $LIMIT = 1;

    if ($DEBUG) {
        header('Content-Type: text/plain; charset=utf-8');
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
    }
}


/* =========================
   Cron file logger + rotation
========================= */

define('CRON_LOG_FILE', __DIR__ . '/logs/17track_tracker.log');
define('CRON_LOG_MAX_BYTES', 10 * 1024 * 1024); // 10MB



/* =========================
   Main
========================= */

out("FLAGS", [
    'DEBUG' => $DEBUG,
    'LIMIT' => $LIMIT,
    'RUN_ONCE' => $RUN_ONCE,
    'SAPI' => php_sapi_name(),
]);

cron_log("17TRACK tracker started", [
    'php' => PHP_VERSION,
    'cwd' => getcwd(),
    'script' => __FILE__,
]);

$imsv2_connect = dbDatabase("laravel_ims");

// 1) Fetch 17TRACK access key from tblapis (api_name='17Track')
$token = fetch17TrackAccessToken($imsv2_connect);

out("17TRACK token", $token ? 'OK (hidden)' : 'MISSING');

if ($token === '') {
    cron_log("17TRACK token missing", ['message' => "No access_token found in tblapis where api_name='17Track'"]);
    cron_log("17TRACK tracker finished (credentials missing)");
    exit;
}

// 2) Load queue (same queue logic; still filtered by carrier='UPS' like before)
$queue = getTrackingQueueLatestDispense_AllCarriers($imsv2_connect, $LIMIT);

out("Queue raw", $queue);

if (isset($queue['error']) && $queue['error'] === true) {
    cron_log("Queue error", ['message' => $queue['message'] ?? 'Unknown']);
    cron_log("17TRACK tracker finished (queue error)");
    exit;
}

if (!is_array($queue) || count($queue) === 0) {
    cron_log("Queue empty (nothing to process)");
    cron_log("17TRACK tracker finished (no work)");
    exit;
}

cron_log("Queue loaded", ['count' => count($queue)]);

$processed = 0;
$skipped = 0;
$apiErrors = 0;
$statusErrors = 0;
$deliveredCount = 0;
$updatedCount = 0;

// Optional: reduce risk of 17TRACK throttling (docs mention ~3 req/s)
$SLEEP_MICROSECONDS = 350000; // 0.35s between calls (tweak if needed)

// --- 17TRACK v2.4 batching (max 40 per request) ---
// assumes you already have:
// $token, $SLEEP_MICROSECONDS, $imsv2_connect,
// $processed, $skipped, $apiErrors, $statusErrors, $deliveredCount, $updatedCount

// helper: chunk into max 40
// Max 40 per request (per docs)
$batches = array_chunk($queue, 40);

foreach ($batches as $batchIndex => $batchRows) {

    // ---------- Build payload + lookup ----------
    $payload = [];
    $rowByNumber = [];

    foreach ($batchRows as $row) {

        $trackingNumber = trim((string) ($row['trackingnumber'] ?? ''));
        $outboundId = (int) ($row['outboundorderitemid'] ?? 0);
        $productId = (int) ($row['productid'] ?? 0);

        if ($trackingNumber === '' || $outboundId <= 0 || $productId <= 0) {
            $skipped++;
            cron_log("Skipped row (missing required fields)", [
                'trackingnumber' => $trackingNumber,
                'outboundorderitemid' => $outboundId,
                'productid' => $productId,
            ]);
            out("SKIPPED ROW (missing required fields)", $row);
            continue;
        }

        $processed++;

        $carrierKey = mapCarrierTo17TrackKey($row['carrier'] ?? null);



        $item = [
            "number" => $trackingNumber,
        ];

        // only include carrier when known
        if ($carrierKey !== null) {
            $item["carrier"] = $carrierKey;
        }

        $rowByNumber[$trackingNumber] = $row;

        $payload[] = $item;

        out("BATCH QUEUED", [
            'batch' => $batchIndex,
            'trackingnumber' => $trackingNumber,
            'outboundorderitemid' => $outboundId,
            'productid' => $productId,
        ]);
    }

    if (count($payload) === 0) {
        cron_log("Batch has no valid rows", ['batch' => $batchIndex]);
        continue;
    }

    // ---------- 1) Call gettrackinfo ----------
    $resp = TRACK17_postJson_v24("https://api.17track.net/track/v2.4/gettrackinfo", $payload, $token);
    out("17TRACK gettrackinfo (batch) response", $resp);

    if (empty($resp['ok'])) {
        $apiErrors += count($payload);
        cron_log("17TRACK gettrackinfo batch error", [
            'batch' => $batchIndex,
            'http_code' => $resp['http_code'] ?? null,
            'message' => $resp['message'] ?? 'Unknown',
            'raw' => $resp['raw'] ?? null,
        ]);
        continue;
    }

    $root = $resp['track17'] ?? [];
    $code = (int) ($root['code'] ?? -999);

    if ($code !== 0) {
        $apiErrors += count($payload);
        cron_log("17TRACK gettrackinfo returned non-zero code", [
            'batch' => $batchIndex,
            'code' => $code,
            'root' => $root,
        ]);
        continue;
    }

    $accepted = $root['data']['accepted'] ?? [];
    $rejected = $root['data']['rejected'] ?? [];

    // number => acceptedRecord
    $accByNumber = [];
    if (is_array($accepted)) {
        foreach ($accepted as $rec) {
            $num = trim((string) ($rec['number'] ?? ''));
            if ($num !== '')
                $accByNumber[$num] = $rec;
        }
    }

    // number => rejectedRecord
    $rejByNumber = [];
    if (is_array($rejected)) {
        foreach ($rejected as $rec) {
            $num = trim((string) ($rec['number'] ?? ''));
            if ($num !== '')
                $rejByNumber[$num] = $rec;
        }
    }

    // ---------- 2) Lazy-register those requiring it (code -18019902), then retry ----------
    $needsRegister = [];
    foreach ($payload as $item) {
        $num = (string) ($item['number'] ?? '');
        if ($num === '')
            continue;

        if (!isset($accByNumber[$num]) && isset($rejByNumber[$num])) {
            $errCode = (int) ($rejByNumber[$num]['error']['code'] ?? 0);
            if ($errCode === -18019902) {
                $needsRegister[] = ["number" => $num];
            }
        }
    }

    if (!empty($needsRegister)) {

        cron_log("17TRACK register-first required; registering + retrying", [
            'batch' => $batchIndex,
            'count' => count($needsRegister),
        ]);
        out("REGISTER REQUIRED", $needsRegister);

        // register (batch)
        $regResp = TRACK17_register_v24_batch($needsRegister, $token);
        out("17TRACK register response", $regResp);

        if (!empty($regResp['error'])) {
            // if register fails, we’ll just proceed with what we already have
            $apiErrors += count($needsRegister);
            cron_log("17TRACK register batch failed", [
                'batch' => $batchIndex,
                'http_code' => $regResp['http_code'] ?? null,
                'message' => $regResp['message'] ?? 'Unknown',
                'raw' => $regResp['raw'] ?? null,
            ]);
        } else {
            // retry gettrackinfo ONLY for those numbers
            $retryPayload = [];
            foreach ($needsRegister as $x) {
                $retryPayload[] = ["number" => (string) $x['number']];
            }

            if (!empty($retryPayload)) {
                if ($SLEEP_MICROSECONDS > 0)
                    usleep($SLEEP_MICROSECONDS);

                $retry = TRACK17_postJson_v24("https://api.17track.net/track/v2.4/gettrackinfo", $retryPayload, $token);
                out("17TRACK gettrackinfo RETRY response", $retry);

                if (!empty($retry['ok']) && (int) ($retry['track17']['code'] ?? -999) === 0) {
                    $acc2 = $retry['track17']['data']['accepted'] ?? [];
                    if (is_array($acc2)) {
                        foreach ($acc2 as $rec) {
                            $num = trim((string) ($rec['number'] ?? ''));
                            if ($num !== '')
                                $accByNumber[$num] = $rec; // merge in
                        }
                    }
                } else {
                    $apiErrors += count($retryPayload);
                    cron_log("17TRACK retry gettrackinfo failed", [
                        'batch' => $batchIndex,
                        'http_code' => $retry['http_code'] ?? null,
                        'message' => $retry['message'] ?? 'Unknown',
                        'raw' => $retry['raw'] ?? null,
                    ]);
                }
            }
        }
    }

    // ---------- 3) Process each item in batch ----------
    foreach ($payload as $item) {

        $trackingNumber = (string) ($item['number'] ?? '');
        $row = $rowByNumber[$trackingNumber] ?? null;

        if (!$row) {
            $statusErrors++;
            cron_log("Row lookup missing for trackingNumber", [
                'batch' => $batchIndex,
                'trackingnumber' => $trackingNumber,
            ]);
            continue;
        }

        $outboundId = (int) ($row['outboundorderitemid'] ?? 0);
        $productId = (int) ($row['productid'] ?? 0);

        if (!isset($accByNumber[$trackingNumber])) {
            $statusErrors++;

            $rej = $rejByNumber[$trackingNumber] ?? null;
            cron_log("No accepted record after retry", [
                'batch' => $batchIndex,
                'trackingnumber' => $trackingNumber,
                'outboundorderitemid' => $outboundId,
                'productid' => $productId,
                'rejected' => $rej,
            ]);
            out("NO ACCEPTED RECORD", ['trackingnumber' => $trackingNumber, 'rejected' => $rej]);
            continue;
        }

        $acceptedRec = $accByNumber[$trackingNumber];

        // Extract status using v2.4 shape: track_info.latest_status.status
        $package_status = track17_status_from_accepted_v24($acceptedRec);

        if ($package_status === '') {
            $statusErrors++;
            cron_log("Could not extract 17TRACK package status", [
                'trackingnumber' => $trackingNumber,
                'outboundorderitemid' => $outboundId,
                'productid' => $productId,
            ]);
            out("STATUS EXTRACT FAILED", $acceptedRec);
            continue;
        }

        cron_log("17TRACK status extracted", [
            'trackingnumber' => $trackingNumber,
            'status' => $package_status,
        ]);
        out("17TRACK STATUS", $package_status);

        // Delivered-ish
        if ($package_status === 'Delivered' || $package_status === 'Delivered by Local Post Office') {

            $fnskuviewer = trim((string) ($row['fnskuviewer'] ?? ''));
            $mskuviewer = trim((string) ($row['mskuviewer'] ?? ''));
            $asinviewer = trim((string) ($row['asinviewer'] ?? ''));

            $done = handleDeliveredTransaction(
                $imsv2_connect,
                $outboundId,
                $productId,
                $fnskuviewer,
                $mskuviewer,
                $asinviewer
            );

            $deliveredCount++;

            cron_log("Delivered transaction result", [
                'trackingnumber' => $trackingNumber,
                'outboundorderitemid' => $outboundId,
                'productid' => $productId,
                'ok' => $done['ok'] ?? false,
                'error' => $done['error'] ?? null,
                'fnsku_updates' => $done['fnsku_updates'] ?? null,
            ]);

            out("DELIVERED TRANSACTION RESULT", $done);
            continue;
        }

        // Update trackingstatus only
        $upd = update_outbound_trackingstatus_by_outboundorderitemid(
            $imsv2_connect,
            $outboundId,
            $package_status
        );

        if (!empty($upd['ok']))
            $updatedCount++;

        cron_log("Tracking status update result", [
            'trackingnumber' => $trackingNumber,
            'outboundorderitemid' => $outboundId,
            'productid' => $productId,
            'status' => $package_status,
            'ok' => $upd['ok'] ?? false,
            'rowsAffected' => $upd['rowsAffected'] ?? null,
            'error' => $upd['error'] ?? null,
        ]);

        out("TRACKING STATUS UPDATE RESULT", $upd);
    }

    // Optional pacing between batches
    if (!empty($SLEEP_MICROSECONDS) && $SLEEP_MICROSECONDS > 0) {
        usleep($SLEEP_MICROSECONDS);
    }
}

cron_log("17TRACK tracker finished", [
    'processed' => $processed,
    'skipped' => $skipped,
    'apiErrors' => $apiErrors,
    'statusErrors' => $statusErrors,
    'deliveredCount' => $deliveredCount,
    'updatedCount' => $updatedCount,
]);

out("SUMMARY", [
    'processed' => $processed,
    'skipped' => $skipped,
    'apiErrors' => $apiErrors,
    'statusErrors' => $statusErrors,
    'deliveredCount' => $deliveredCount,
    'updatedCount' => $updatedCount,
]);

/* =========================
   17TRACK functions
========================= */

function fetch17TrackAccessToken(mysqli $db): string
{
    // You said: only need access_token from tblapis where api_name='17Track'
    $sql = "SELECT access_token FROM tblapis WHERE api_name = '17Track' LIMIT 1";
    $res = $db->query($sql);
    if (!$res) {
        cron_log("fetch17TrackAccessToken query failed", ['error' => $db->error]);
        return '';
    }
    $row = $res->fetch_assoc();
    $token = trim((string) ($row['access_token'] ?? ''));
    return $token;
}

function TRACK17_postJson(string $url, array $payload, string $token): array
{
    if ($token === '') {
        return ['error' => true, 'message' => 'Missing 17TRACK access token (17token header).'];
    }

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            "17token: {$token}",
            "Content-Type: application/json",
            "Accept: application/json",
        ],
        CURLOPT_POSTFIELDS => $json,
    ]);

    $raw = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);

    if ($err) {
        return ['error' => true, 'message' => "cURL error: {$err}", 'http' => $info];
    }

    $data = json_decode($raw, true);
    $httpCode = (int) ($info['http_code'] ?? 0);

    if (!is_array($data)) {
        return [
            'error' => true,
            'message' => 'Invalid JSON response from 17TRACK API.',
            'http_code' => $httpCode,
            'raw' => substr((string) $raw, 0, 5000),
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'error' => true,
            'message' => '17TRACK API returned non-2xx.',
            'http_code' => $httpCode,
            'raw' => $data,
        ];
    }

    return [
        'http_code' => $httpCode,
        'data' => $data,
    ];
}

/* =========================
   DB update (unchanged)
========================= */

function update_outbound_trackingstatus_by_outboundorderitemid(mysqli $db, int $outboundorderitemid, string $trackingstatus): array
{
    $sql = "UPDATE tbloutboundordersitem
            SET trackingstatus = ?
            WHERE outboundorderitemid = ?";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => $db->error];
    }

    $stmt->bind_param("si", $trackingstatus, $outboundorderitemid);
    $stmt->execute();

    $affected = $stmt->affected_rows;
    $err = $stmt->error;
    $stmt->close();

    if ($err) {
        return ['ok' => false, 'error' => $err];
    }

    return ['ok' => true, 'rowsAffected' => $affected];
}

/* =========================
   Existing functions you already have (UNCHANGED BELOW)
========================= */

function dbDatabase($servertype)
{
    switch ($servertype) {
        case "ims":
            $hostname = 'localhost';
            $username = 'root';
            $password = '';
            $database = 'ims';
            break;

        case "hostinger":
            $hostname = 'localhost';
            $username = 'u298641722_web_ims';
            $password = 'ImsHosting!11923';
            $database = 'u298641722_ims';
            break;

        case "test":
            $hostname = 'localhost';
            $username = 'u298641722_testing_user';
            $password = 'Watdahek1234!';
            $database = 'u298641722_test';
            break;

        case "laravel_ims":
            $hostname = 'localhost';
            $username = 'imsv2_dbims_user';
            $password = 'Imsv2_dbims_user';
            $database = 'imsv2_dbims';
            break;

        default:
            cron_log("Invalid server type", ['servertype' => $servertype]);
            exit;
    }

    $db = new mysqli($hostname, $username, $password, $database);
    if ($db->connect_errno) {
        cron_log("DB connect failed", [
            'servertype' => $servertype,
            'error' => $db->connect_error,
        ]);
        exit;
    }

    $db->set_charset('utf8mb4');
    return $db;
}

function getTrackingQueueLatestDispense_AllCarriers(mysqli $db, int $limit = 0): array
{
    $sql = "
        SELECT
            oi.trackingnumber,
            oi.outboundorderitemid,
            oi.carrier,
            d2.productid,
            p.FNSKUviewer AS fnskuviewer,
            p.MSKUviewer  AS mskuviewer,
            p.ASINviewer  AS asinviewer
        FROM tbloutboundordersitem oi
        INNER JOIN (
            SELECT d.orderitemid, d.productid
            FROM tblorderitemdispense d
            INNER JOIN (
                SELECT orderitemid, MAX(id) AS max_id
                FROM tblorderitemdispense
                GROUP BY orderitemid
            ) x ON x.orderitemid = d.orderitemid AND x.max_id = d.id
        ) d2 ON d2.orderitemid = oi.outboundorderitemid
        INNER JOIN tblproduct p ON p.ProductID = d2.productid
        WHERE oi.trackingnumber IS NOT NULL
          AND oi.trackingnumber <> ''
          AND oi.carrier IS NOT NULL
          AND oi.carrier <> ''
          AND oi.order_status = 'Shipped'
          AND d2.productid IS NOT NULL
        ORDER BY oi.outboundorderitemid DESC
    ";

    if ($limit > 0) {
        $sql .= " LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt)
            return ['error' => true, 'message' => 'Prepare failed: ' . $db->error];

        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $db->query($sql);
        if (!$res)
            return ['error' => true, 'message' => 'Query failed: ' . $db->error];
    }

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'trackingnumber' => (string) $row['trackingnumber'],
            'outboundorderitemid' => (int) $row['outboundorderitemid'],
            'carrier' => (string) ($row['carrier'] ?? ''),
            'productid' => (int) $row['productid'],
            'fnskuviewer' => (string) ($row['fnskuviewer'] ?? ''),
            'mskuviewer' => (string) ($row['mskuviewer'] ?? ''),
            'asinviewer' => (string) ($row['asinviewer'] ?? ''),
        ];
    }

    if (!empty($stmt) && $stmt instanceof mysqli_stmt)
        $stmt->close();
    return $items;
}

/* =========================
   Output helper (debug only)
========================= */

function out(string $label, $data = null): void
{
    global $DEBUG;
    if (!$DEBUG)
        return;

    $ts = date('Y-m-d H:i:s');
    echo "\n==================== {$ts} :: {$label} ====================\n";

    if ($data !== null) {
        if (is_string($data))
            echo $data . "\n";
        else {
            print_r($data);
            echo "\n";
        }
    }
}

function cron_log_rotate_if_needed(): void
{
    if (file_exists(CRON_LOG_FILE) && filesize(CRON_LOG_FILE) > CRON_LOG_MAX_BYTES) {
        $rotated = CRON_LOG_FILE . '.' . date('Ymd_His');
        @rename(CRON_LOG_FILE, $rotated);
    }
}

function cron_log(string $message, array $context = []): void
{
    cron_log_rotate_if_needed();

    $ts = date('Y-m-d H:i:s');

    if (!empty($context)) {
        $message .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES);
    }

    $line = "[{$ts}] {$message}\n";

    // Ensure directory exists
    $dir = dirname(CRON_LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    @file_put_contents(CRON_LOG_FILE, $line, FILE_APPEND | LOCK_EX);

    // Mirror logs to screen only in debug mode (web)
    global $DEBUG;
    if (!empty($DEBUG)) {
        echo $line;
    }
}

function handleDeliveredTransaction(mysqli $db, int $outboundId, int $productId, string $fnskuviewer, string $mskuviewer, string $asinviewer): array
{
    if ($outboundId <= 0 || $productId <= 0) {
        return ['ok' => false, 'error' => 'Missing outboundId/productId'];
    }

    try {
        $db->begin_transaction();

        // 1) Update outbound item (Delivered)
        $stmt1 = $db->prepare("
            UPDATE tbloutboundordersitem
            SET order_status = 'Delivered'
            WHERE outboundorderitemid = ? AND order_status = 'Shipped'
        ");
        if (!$stmt1)
            throw new Exception($db->error);

        $stmt1->bind_param("i", $outboundId);
        $stmt1->execute();
        if ($stmt1->affected_rows <= 0) {
            $stmt1->close();
            throw new Exception("Outbound item already processed or not Shipped");
        }
        $stmt1->close();

        // 2) Load product info FIRST (needed to detect pack + rtcounter)
        $stmt = $db->prepare("
            SELECT mergeId, rtcounter, FNSKUviewer, MSKUviewer, ASINviewer
            FROM tblproduct
            WHERE ProductID = ?
            LIMIT 1
        ");
        if (!$stmt)
            throw new Exception($db->error);

        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$prod) {
            throw new Exception("Product not found for ProductID={$productId}");
        }

        $isPackParent = !empty($prod['mergeId']);
        $rtcounter = (int) ($prod['rtcounter'] ?? 0);

        // Prefer DB values (DB is source of truth)
        $fnskuviewer = trim((string) ($prod['FNSKUviewer'] ?? $fnskuviewer));
        $mskuviewer = trim((string) ($prod['MSKUviewer'] ?? $mskuviewer));
        $asinviewer = trim((string) ($prod['ASINviewer'] ?? $asinviewer));

        // 3) Move to SoldList (Option A: parent always; children if pack)
        $movedRows = moveProductParentAndChildrenToSoldList($db, $productId, $rtcounter, $isPackParent);
        if ($movedRows <= 0) {
            throw new Exception("No tblproduct rows moved to Soldlist");
        }

        // 4) Build idCounts for tblfnsku increments
        $idCounts = [];

        if (!$isPackParent) {
            // Single item
            $tuple = normalizeIdentifierTuple($fnskuviewer, $mskuviewer, $asinviewer);
            $key = json_encode($tuple, JSON_UNESCAPED_SLASHES);
            $idCounts[$key] = ($idCounts[$key] ?? 0) + 1;

        } else {
            // Pack parent: count parent as 1 (so 4-pack => 5 total if 4 children exist)
            $tupleParent = normalizeIdentifierTuple($fnskuviewer, $mskuviewer, $asinviewer);
            $keyParent = json_encode($tupleParent, JSON_UNESCAPED_SLASHES);
            $idCounts[$keyParent] = ($idCounts[$keyParent] ?? 0) + 1;

            if ($rtcounter <= 0) {
                throw new Exception("Pack parent missing rtcounter for ProductID={$productId}");
            }

            // Add children
            $stmt = $db->prepare("
                SELECT FNSKUviewer, MSKUviewer, ASINviewer
                FROM tblproduct
                WHERE mergeTO = ?
            ");
            if (!$stmt)
                throw new Exception($db->error);

            $stmt->bind_param("i", $rtcounter);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 0) {
                $stmt->close();
                throw new Exception("Pack parent found but no children for rtcounter={$rtcounter}");
            }

            while ($row = $res->fetch_assoc()) {
                $tuple = normalizeIdentifierTuple(
                    (string) ($row['FNSKUviewer'] ?? ''),
                    (string) ($row['MSKUviewer'] ?? ''),
                    (string) ($row['ASINviewer'] ?? '')
                );
                $key = json_encode($tuple, JSON_UNESCAPED_SLASHES);
                $idCounts[$key] = ($idCounts[$key] ?? 0) + 1;
            }

            $stmt->close();
        }

        // 5) Apply increments to tblfnsku by matching FNSKU OR MSKU OR ASIN
        $updates = [];
        foreach ($idCounts as $key => $qty) {
            $tuple = json_decode($key, true);

            $r = increment_tblfnsku_units_by_any_identifier(
                $db,
                (int) $qty,
                (string) ($tuple['fnsku'] ?? ''),
                (string) ($tuple['msku'] ?? ''),
                (string) ($tuple['asin'] ?? '')
            );

            if (empty($r['ok'])) {
                throw new Exception($r['error'] ?? "Failed updating tblfnsku for key={$key}");
            }

            $updates[] = [
                'qty' => (int) $qty,
                'fnsku' => (string) ($tuple['fnsku'] ?? ''),
                'msku' => (string) ($tuple['msku'] ?? ''),
                'asin' => (string) ($tuple['asin'] ?? ''),
                'rowsAffected' => $r['rowsAffected'] ?? null,
            ];
        }

        $db->commit();

        return [
            'ok' => true,
            'movedRows' => $movedRows,
            'fnsku_updates' => $updates,
        ];

    } catch (Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function normalizeIdentifierTuple(string $fnskuviewer, string $mskuviewer, string $asinviewer): array
{
    $fnsku = normalizeFnskuViewer($fnskuviewer);
    $msku = strtoupper(trim($mskuviewer));
    $asin = strtoupper(trim($asinviewer));

    return [
        'fnsku' => $fnsku,
        'msku' => $msku,
        'asin' => $asin,
    ];
}


function normalizeFnskuViewer(string $fnsku): string
{
    $s = strtoupper(trim($fnsku));
    if ($s === '')
        return '';

    $pos = strpos($s, 'X');
    if ($pos !== false) {
        $candidate = substr($s, $pos);
        if (preg_match('/^X[0-9A-Z]+$/', $candidate)) {
            return $candidate;
        }
    }

    if (preg_match('/^[A-Z][0-9](X[0-9A-Z]+)$/', $s, $m))
        return $m[1];
    if (preg_match('/^[A-Z]{2}(X[0-9A-Z]+)$/', $s, $m))
        return $m[1];

    return $s;
}

function increment_tblfnsku_units_by_any_identifier(
    mysqli $db,
    int $qty,
    string $fnsku,
    string $msku,
    string $asin
): array {
    if ($qty <= 0)
        return ['ok' => false, 'error' => 'qty must be > 0'];

    $conds = [];
    $types = "i";
    $params = [$qty];

    if ($fnsku !== '') {
        $conds[] = "FNSKU = ?";
        $types .= "s";
        $params[] = $fnsku;
    }
    if ($msku !== '') {
        $conds[] = "MSKU  = ?";
        $types .= "s";
        $params[] = $msku;
    }
    if ($asin !== '') {
        $conds[] = "ASIN  = ?";
        $types .= "s";
        $params[] = $asin;
    }

    if (count($conds) === 0) {
        return ['ok' => false, 'error' => 'No identifiers (fnsku/msku/asin) provided'];
    }

    $sql = "
        UPDATE tblfnsku
        SET units = COALESCE(units, 0) + ?
        WHERE " . implode(" OR ", $conds) . "
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt)
        return ['ok' => false, 'error' => $db->error];

    $bind = [];
    $bind[] = $types;
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind);

    $stmt->execute();
    $affected = $stmt->affected_rows;
    $err = $stmt->error;
    $stmt->close();

    if ($err)
        return ['ok' => false, 'error' => $err];

    if ($affected <= 0) {
        return [
            'ok' => false,
            'error' => "No tblfnsku rows matched for fnsku={$fnsku} msku={$msku} asin={$asin}"
        ];
    }

    return ['ok' => true, 'rowsAffected' => $affected];
}

function moveProductParentAndChildrenToSoldList(mysqli $db, int $productId, int $rtcounter, bool $isPackParent): int
{
    $moved = 0;

    // 1) Move parent
    $stmtP = $db->prepare("
        UPDATE tblproduct
        SET ProductModuleLoc = 'Soldlist'
        WHERE ProductID = ?
    ");
    if (!$stmtP)
        throw new Exception($db->error);

    $stmtP->bind_param("i", $productId);
    $stmtP->execute();
    $moved += (int) $stmtP->affected_rows;
    $err = $stmtP->error;
    $stmtP->close();

    if ($err)
        throw new Exception("Move parent failed: {$err}");

    // 2) Move children (only if pack parent)
    if ($isPackParent) {

        if ($rtcounter <= 0) {
            throw new Exception("Pack parent missing rtcounter (cannot move children)");
        }

        $stmtC = $db->prepare("
            UPDATE tblproduct
            SET ProductModuleLoc = 'Soldlist'
            WHERE mergeTO = ?
        ");
        if (!$stmtC)
            throw new Exception($db->error);

        $stmtC->bind_param("i", $rtcounter);
        $stmtC->execute();
        $moved += (int) $stmtC->affected_rows;
        $err2 = $stmtC->error;
        $stmtC->close();

        if ($err2)
            throw new Exception("Move children failed: {$err2}");
    }

    return $moved;
}

function TRACK17_getTrackInfo_v24_batch(array $rows, string $token): array
{
    // rows: each is ['trackingnumber'=>..., ...]
    $endpoint = "https://api.17track.net/track/v2.4/gettrackinfo";

    $payload = [];
    foreach ($rows as $r) {
        $payload[] = [
            "number" => (string) $r['trackingnumber'],
            // "carrier" => 100003, // OPTIONAL, omit to auto-match
        ];
    }

    return TRACK17_postJson_v24($endpoint, $payload, $token);
}

function TRACK17_postJson_v24(string $url, array $payload, string $token): array
{
    if ($token === '')
        return ['error' => true, 'message' => 'Missing 17TRACK token (17token header).'];

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            "17token: {$token}",
            "Content-Type: application/json",
            "Accept: application/json",
        ],
        CURLOPT_POSTFIELDS => $json,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($err)
        return ['error' => true, 'message' => "cURL error: {$err}", 'http' => $info];

    $data = json_decode($raw, true);
    $httpCode = (int) ($info['http_code'] ?? 0);

    if (!is_array($data)) {
        return ['error' => true, 'message' => 'Invalid JSON from 17TRACK', 'http_code' => $httpCode, 'raw' => substr((string) $raw, 0, 5000)];
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['error' => true, 'message' => '17TRACK non-2xx', 'http_code' => $httpCode, 'raw' => $data];
    }

    return ['ok' => true, 'http_code' => $httpCode, 'track17' => $data];
}

function chunk_rows(array $rows, int $size): array
{
    $out = [];
    $chunk = [];
    foreach ($rows as $r) {
        $chunk[] = $r;
        if (count($chunk) >= $size) {
            $out[] = $chunk;
            $chunk = [];
        }
    }
    if (!empty($chunk))
        $out[] = $chunk;
    return $out;
}

function track17_extract_package_status_from_accepted_record(array $rec): string
{
    $track = $rec['track'] ?? null;
    if (!is_array($track))
        return '';

    // status code
    $e = isset($track['e']) ? (int) $track['e'] : null;

    // Map (common 17track meanings)
    $map = [
        0 => 'Not found',
        10 => 'In transit',
        20 => 'Expired',
        30 => 'Pick up',
        35 => 'Undelivered',
        40 => 'Delivered',
        50 => 'Alert',
    ];

    $status = $map[$e] ?? '';

    // latest event description (optional)
    $z0 = $track['z0'] ?? [];
    $latestDesc = is_array($z0) ? (string) ($z0['z'] ?? '') : '';
    $latestDescLower = strtolower($latestDesc);

    // Keep your special old-string
    if ($e === 40) {
        if ($latestDescLower !== '' && strpos($latestDescLower, 'local post office') !== false) {
            return 'Delivered by Local Post Office';
        }
        return 'Delivered';
    }

    // fallback: return mapped status
    if ($status !== '')
        return $status;

    // last fallback: use latest description if present (short)
    if (trim($latestDesc) !== '')
        return trim($latestDesc);

    return '';
}

function TRACK17_register_v24_batch(array $payloadNumbers, string $token): array
{
    // payloadNumbers: [ ["number"=>"RR..."], ["number"=>"..."] ]
    return TRACK17_postJson_v24("https://api.17track.net/track/v2.4/register", $payloadNumbers, $token);
}

function track17_status_from_accepted_v24(array $acceptedRec): string
{
    $trackInfo = $acceptedRec['track_info'] ?? null;
    if (!is_array($trackInfo))
        return '';

    $latestStatus = $trackInfo['latest_status'] ?? [];
    $status = trim((string) ($latestStatus['status'] ?? ''));

    // Your DB uses these “friendly” values, so we map here:
    // (you can add more mappings later)
    $map = [
        'Delivered' => 'Delivered',
        'OutForDelivery' => 'Out for delivery',
        'AvailableForPickup' => 'Available for pickup',
        'Arrival' => 'Arrival',
        'Departure' => 'Departure',
        'PickedUp' => 'Picked up',
        'InfoReceived' => 'InfoReceived',
        'Exception' => 'Alert',
        'Undelivered' => 'Undelivered',
        'Expired' => 'Expired',
    ];

    if ($status !== '' && isset($map[$status])) {
        return $map[$status];
    }

    // Special case: delivered by local post office (check latest_event.description)
    if ($status === 'Delivered') {
        $desc = (string) ($trackInfo['latest_event']['description'] ?? '');
        if ($desc !== '' && stripos($desc, 'local post office') !== false) {
            return 'Delivered by Local Post Office';
        }
        return 'Delivered';
    }

    // Fallback: try latest_event.stage or description
    $stage = trim((string) ($trackInfo['latest_event']['stage'] ?? ''));
    if ($stage !== '' && isset($map[$stage]))
        return $map[$stage];

    $desc2 = trim((string) ($trackInfo['latest_event']['description_translation']['description'] ?? ''));
    if ($desc2 === '')
        $desc2 = trim((string) ($trackInfo['latest_event']['description'] ?? ''));

    if ($desc2 !== '') {
        // keep it short-ish
        return mb_substr($desc2, 0, 120);
    }

    // Last fallback: if status exists but unmapped, return raw status
    return $status;
}

function mapCarrierTo17TrackKey(?string $carrier): ?int
{
    if (!$carrier)
        return null;

    $carrier = strtoupper(trim($carrier));

    if ($carrier === 'FEDEX')
        $carrier = 'FEDEX';
    if ($carrier === 'FED EX')
        $carrier = 'FEDEX';

    $map = [
        'USPS' => 21051,
        'UPS' => 100002,
        'FEDEX' => 100003,
        'DHL' => 7047,   // common DHL Express
        'SKYPOSTAL' => 100149,
    ];

    return $map[$carrier] ?? null;
}