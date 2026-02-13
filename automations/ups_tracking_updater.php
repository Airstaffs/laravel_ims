<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

/**
 * CRON-SAFE UPS TRACKER (cPanel/WHM) + WEB TEST MODE
 *
 * CRON (CLI):
 * - No echo output
 * - Full queue (no LIMIT)
 *
 * WEB (Browser):
 * - Uses real DB values, but defaults to LIMIT 1 for safe testing
 * - Supports:
 *    ?key=YOUR_SECRET          (required)
 *    ?debug=1                  (echo output)
 *    ?limit=1                  (DB query limit, default=1)
 *    ?run_once=1               (alias for limit=1)
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
        if (is_string($data)) {
            echo $data . "\n";
        } else {
            print_r($data);
            echo "\n";
        }
    }
}

/* =========================
   Cron file logger + rotation
========================= */

define('CRON_LOG_FILE', __DIR__ . '/logs/ups_tracker.log');
define('CRON_LOG_MAX_BYTES', 10 * 1024 * 1024); // 10MB

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

/* =========================
   Main
========================= */

out("FLAGS", [
    'DEBUG' => $DEBUG,
    'LIMIT' => $LIMIT,
    'RUN_ONCE' => $RUN_ONCE,
    'SAPI' => php_sapi_name(),
]);

cron_log("UPS tracker started", [
    'php' => PHP_VERSION,
    'cwd' => getcwd(),
    'script' => __FILE__,
]);

$imsv2_connect = dbDatabase("laravel_ims");

// Fetch UPS credentials from main app
$credentials = fetchUpsCredentialsFromMainApp();

out("UPS credentials response", $credentials);

if (!empty($credentials['error'])) {
    cron_log("UPS credential fetch failed", [
        'message' => $credentials['message'] ?? 'Unknown',
        'http_code' => $credentials['http']['http_code'] ?? ($credentials['http_code'] ?? null),
    ]);
    cron_log("UPS tracker finished (credentials error)");
    exit;
}
if (!$credentials) {
    cron_log("UPS API credentials not found (empty response)");
    cron_log("UPS tracker finished (credentials missing)");
    exit;
}

// Always use REAL DB queue.
// CRON: LIMIT=0 means full queue.
// WEB:  LIMIT defaults to 1 (safe testing).
$queue = getUpsTrackingQueueLatestDispense($imsv2_connect, $LIMIT);

out("Queue raw", $queue);

// Handle queue error shape
if (isset($queue['error']) && $queue['error'] === true) {
    cron_log("Queue error", [
        'message' => $queue['message'] ?? 'Unknown',
    ]);
    cron_log("UPS tracker finished (queue error)");
    exit;
}

if (!is_array($queue) || count($queue) === 0) {
    cron_log("Queue empty (nothing to process)");
    cron_log("UPS tracker finished (no work)");
    exit;
}

cron_log("Queue loaded", ['count' => count($queue)]);

$processed = 0;
$skipped = 0;
$upsErrors = 0;
$statusErrors = 0;
$deliveredCount = 0;
$updatedCount = 0;

foreach ($queue as $row) {

    // Skip incomplete rows
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

    cron_log("Processing row", [
        'trackingnumber' => $trackingNumber,
        'outboundorderitemid' => $outboundId,
        'productid' => $productId,
    ]);

    out("PROCESSING", [
        'trackingnumber' => $trackingNumber,
        'outboundorderitemid' => $outboundId,
        'productid' => $productId,
        'fnskuviewer' => $row['fnskuviewer'] ?? '',
        'mskuviewer' => $row['mskuviewer'] ?? '',
        'asinviewer' => $row['asinviewer'] ?? '',
    ]);

    // 1) Fetch UPS details
    $resp = UPS_fetchDetails($trackingNumber, $credentials);
    out("UPS_fetchDetails response", $resp);

    // If token expired/invalid, refetch from main app and retry once
    if (!empty($resp['http_code']) && (int) $resp['http_code'] === 401) {
        cron_log("UPS returned 401, refetching credentials and retrying once", [
            'trackingnumber' => $trackingNumber,
        ]);
        out("UPS returned 401 -> refetching credentials", $trackingNumber);

        $credentials2 = fetchUpsCredentialsFromMainApp();
        out("Refetched credentials", $credentials2);

        if (empty($credentials2['error']) && !empty($credentials2['access_token'])) {
            $credentials = $credentials2;
            $resp = UPS_fetchDetails($trackingNumber, $credentials);
            out("UPS_fetchDetails retry response", $resp);
        } else {
            cron_log("Credential refetch failed after 401", [
                'trackingnumber' => $trackingNumber,
                'message' => $credentials2['message'] ?? 'Unknown',
            ]);
            out("Credential refetch failed after 401", $credentials2);
        }
    }

    if (!empty($resp['error'])) {
        $upsErrors++;
        cron_log("UPS fetch error", [
            'trackingnumber' => $trackingNumber,
            'outboundorderitemid' => $outboundId,
            'productid' => $productId,
            'http_code' => $resp['http_code'] ?? null,
            'message' => $resp['message'] ?? 'Unknown error',
        ]);
        out("UPS FETCH ERROR", $resp);
        continue;
    }

    // 2) Extract package status (use old logic first)
    $package_status = ups_extract_package_status_like_old($resp);

    if ($package_status === '') {
        $statusErrors++;
        cron_log("Could not extract UPS package status", [
            'trackingnumber' => $trackingNumber,
            'outboundorderitemid' => $outboundId,
            'productid' => $productId,
        ]);
        out("STATUS EXTRACT FAILED", $resp);
        continue;
    }

    cron_log("UPS status extracted", [
        'trackingnumber' => $trackingNumber,
        'status' => $package_status,
    ]);

    out("UPS STATUS", $package_status);

    // 3) Delivered-ish statuses
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

    // 4) Simple update only (tbloutboundordersitem.trackingstatus)
    $upd = update_outbound_trackingstatus_by_outboundorderitemid(
        $imsv2_connect,
        $outboundId,
        $package_status
    );

    if (!empty($upd['ok'])) {
        $updatedCount++;
    }

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

    // optional: avoid rate limits
    // sleep(1);
}

cron_log("UPS tracker finished", [
    'processed' => $processed,
    'skipped' => $skipped,
    'upsErrors' => $upsErrors,
    'statusErrors' => $statusErrors,
    'deliveredCount' => $deliveredCount,
    'updatedCount' => $updatedCount,
]);

out("SUMMARY", [
    'processed' => $processed,
    'skipped' => $skipped,
    'upsErrors' => $upsErrors,
    'statusErrors' => $statusErrors,
    'deliveredCount' => $deliveredCount,
    'updatedCount' => $updatedCount,
]);

/* =========================
   Status extraction (OLD-first)
========================= */

function ups_extract_package_status_like_old(array $resp): string
{
    $ups = $resp['ups'] ?? null;
    if (!is_array($ups)) {
        return '';
    }

    $status =
        $ups['trackResponse']['shipment'][0]['package'][0]['currentStatus']['description']
        ?? null;

    if (!$status) {
        $status =
            $ups['trackResponse']['Shipment'][0]['warnings'][0]['message']
            ?? null;
    }

    if (!$status) {
        $status =
            $ups['trackResponse']['shipment'][0]['package'][0]['activity'][0]['status']['description']
            ?? null;
    }

    if (!$status) {
        $status =
            $ups['TrackResponse']['Shipment'][0]['Package'][0]['Activity'][0]['Status']['StatusType']['Description']
            ?? null;
    }

    return trim((string) $status);
}

/* =========================
   DB update
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
   Existing functions you already have
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

function UPS_fetchDetails($trackingnumber, array $credentials)
{
    $inquiry = trim((string) $trackingnumber);

    $query = [
        "locale" => "en_US",
        "returnSignature" => "false",
        "returnMilestones" => "false"
    ];

    $token = (string) ($credentials['access_token'] ?? '');
    if ($token === '') {
        return ['error' => true, 'message' => 'Missing UPS access_token.'];
    }

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://onlinetools.ups.com/api/track/v1/details/" . rawurlencode($inquiry) . "?" . http_build_query($query),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
            "transId: " . uniqid("ims_", true),
            "transactionSrc: CustomerServicePortal",
            "Accept: application/json",
        ],
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
            'message' => 'Invalid JSON response from UPS Track API.',
            'http_code' => $httpCode,
            'raw' => substr((string) $raw, 0, 5000),
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'error' => true,
            'message' => 'UPS Track API returned non-2xx.',
            'http_code' => $httpCode,
            'ups' => $data,
        ];
    }

    return [
        'http_code' => $httpCode,
        'ups' => $data,
    ];
}

function getUpsTrackingQueueLatestDispense(mysqli $db, int $limit = 0): array
{
    $sql = "
        SELECT
            oi.trackingnumber,
            oi.outboundorderitemid,
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
        WHERE oi.carrier = 'UPS'
          AND oi.trackingnumber IS NOT NULL
          AND oi.trackingnumber <> ''
          AND oi.order_status = 'Shipped'
          AND d2.productid IS NOT NULL
        ORDER BY oi.outboundorderitemid DESC
    ";

    // Apply SQL LIMIT if requested
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
        if (!$res) {
            return ['error' => true, 'message' => 'Query failed: ' . $db->error];
        }
    }

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'trackingnumber' => (string) $row['trackingnumber'],
            'outboundorderitemid' => (int) $row['outboundorderitemid'],
            'productid' => (int) $row['productid'],
            'fnskuviewer' => (string) ($row['fnskuviewer'] ?? ''),
            'mskuviewer' => (string) ($row['mskuviewer'] ?? ''),
            'asinviewer' => (string) ($row['asinviewer'] ?? ''),
        ];
    }

    if (!empty($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    return $items;
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

function fetchUpsCredentialsFromMainApp(): array
{
    $url = 'https://ims.tecniquality.com/dbserver/ups/ups_credentials.php';

    // MUST match EXPECTED_KEY on the main app endpoint
    $secret = 'REPLACE_WITH_LONG_RANDOM_SECRET';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-IMS-KEY: ' . $secret,
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'caller' => 'ups_tracker_second_app',
        ]),
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($err) {
        return ['error' => true, 'message' => "cURL error: {$err}", 'http' => $info];
    }

    $data = json_decode($raw, true);

    if (!is_array($data) || empty($data['ok'])) {
        return [
            'error' => true,
            'message' => 'Invalid response from main app',
            'http' => $info,
            'raw' => substr((string) $raw, 0, 2000),
        ];
    }

    $token = (string) ($data['access_token'] ?? '');
    $exp = (int) ($data['expires_unix'] ?? 0);

    if ($token === '' || $exp <= 0) {
        return ['error' => true, 'message' => 'Main app returned missing token/expiry', 'raw' => $data];
    }

    return [
        'access_token' => $token,
        'expires_in' => $exp,
    ];
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
