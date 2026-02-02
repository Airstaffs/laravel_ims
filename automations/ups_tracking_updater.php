<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

/**
 * CRON-SAFE UPS TRACKER (cPanel/WHM)
 * - Writes logs to: __DIR__/logs/ups_tracker.log
 * - No echo/print_r (cron friendly)
 * - Handles queue + UPS errors with logging
 * - Adds basic log rotation
 */

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
        // Keep logs compact
        $message .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES);
    }

    $line = "[{$ts}] {$message}\n";

    // Ensure directory exists
    $dir = dirname(CRON_LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    @file_put_contents(CRON_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/* =========================
   Main
========================= */

cron_log("UPS tracker started", [
    'php' => PHP_VERSION,
    'cwd' => getcwd(),
    'script' => __FILE__,
]);

$imsv2_connect = dbDatabase("laravel_ims");

// Fetch UPS credentials from main app
$credentials = fetchUpsCredentialsFromMainApp();

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

// Toggle test queue
$USE_TEST_QUEUE = false;

$queue = $USE_TEST_QUEUE
    ? [
        [
            'trackingnumber' => '1ZK5083X0318006920',
            'outboundorderitemid' => 999001,
            'productid' => 888001,
            'fnskuviewer' => 'X00ABC12345', // put a real fnsku for testing
        ],
        [
            'trackingnumber' => '1Z12345E0205271688',
            'outboundorderitemid' => 999002,
            'productid' => 888002,
            'fnskuviewer' => 'X00DEF67890',
        ],
    ]
    : getUpsTrackingQueueLatestDispense($imsv2_connect);

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
        continue;
    }

    $processed++;

    cron_log("Processing row", [
        'trackingnumber' => $trackingNumber,
        'outboundorderitemid' => $outboundId,
        'productid' => $productId,
    ]);

    // 1) Fetch UPS details
    $resp = UPS_fetchDetails($trackingNumber, $credentials);

    // If token expired/invalid, refetch from main app and retry once
    if (!empty($resp['http_code']) && (int) $resp['http_code'] === 401) {
        cron_log("UPS returned 401, refetching credentials and retrying once", [
            'trackingnumber' => $trackingNumber,
        ]);

        $credentials2 = fetchUpsCredentialsFromMainApp();
        if (empty($credentials2['error']) && !empty($credentials2['access_token'])) {
            $credentials = $credentials2;
            $resp = UPS_fetchDetails($trackingNumber, $credentials);
        } else {
            cron_log("Credential refetch failed after 401", [
                'trackingnumber' => $trackingNumber,
                'message' => $credentials2['message'] ?? 'Unknown',
            ]);
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
        continue;
    }

    cron_log("UPS status extracted", [
        'trackingnumber' => $trackingNumber,
        'status' => $package_status,
    ]);

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

/* =========================
   Status extraction (OLD-first)
========================= */

function ups_extract_package_status_like_old(array $resp): string
{
    $ups = $resp['ups'] ?? null;
    if (!is_array($ups)) {
        return '';
    }

    // OLD script main path
    $status =
        $ups['trackResponse']['shipment'][0]['package'][0]['currentStatus']['description']
        ?? null;

    // OLD script fallback warning path
    if (!$status) {
        $status =
            $ups['trackResponse']['Shipment'][0]['warnings'][0]['message']
            ?? null;
    }

    // Other common shapes (backup)
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
            'raw' => substr((string) $raw, 0, 5000), // cap
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

function getUpsTrackingQueueLatestDispense(mysqli $db): array
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

    $res = $db->query($sql);
    if (!$res) {
        return ['error' => true, 'message' => 'Query failed: ' . $db->error];
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

    return $items;
}


function handleDeliveredTransaction(
    mysqli $db,
    int $outboundId,
    int $productId,
    string $fnskuviewer,
    string $mskuviewer,
    string $asinviewer
): array {
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
            throw new Exception("Outbound item already processed or not Shipped");
        }
        $stmt1->close();

        // 2) Move ONLY dispensed product to SoldList
        $stmt2 = $db->prepare("
            UPDATE tblproduct
            SET ProductModuleLoc = 'SoldList'
            WHERE ProductID = ?
        ");
        if (!$stmt2)
            throw new Exception($db->error);

        $stmt2->bind_param("i", $productId);
        $stmt2->execute();
        if ($stmt2->affected_rows <= 0) {
            throw new Exception("Product not moved to SoldList");
        }
        $stmt2->close();

        // 3) Load product info (to detect pack)
        $stmt = $db->prepare("
            SELECT mergeId, rtcounter, FNSKUviewer, MSKUviewer, ASINviewer
            FROM tblproduct
            WHERE ProductID = ?
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

        // Prefer DB values (row values are fine, but DB is source of truth)
        $fnskuviewer = trim((string) ($prod['FNSKUviewer'] ?? $fnskuviewer));
        $mskuviewer = trim((string) ($prod['MSKUviewer'] ?? $mskuviewer));
        $asinviewer = trim((string) ($prod['ASINviewer'] ?? $asinviewer));

        // This will hold unique “identifier tuples” => qty
        // Keyed by json string for easy dedupe
        $idCounts = [];

        // CASE A: Single item (no mergeId)
        if (empty($prod['mergeId'])) {
            $tuple = normalizeIdentifierTuple($fnskuviewer, $mskuviewer, $asinviewer);
            $key = json_encode($tuple, JSON_UNESCAPED_SLASHES);
            $idCounts[$key] = ($idCounts[$key] ?? 0) + 1;
        } else {
            // CASE B: Pack parent → expand children
            $rtcounter = (int) $prod['rtcounter'];

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

        // 4) Apply increments to tblfnsku by matching FNSKU OR MSKU OR ASIN
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
                'qty' => $qty,
                'fnsku' => $tuple['fnsku'],
                'msku' => $tuple['msku'],
                'asin' => $tuple['asin'],
                'rowsAffected' => $r['rowsAffected'] ?? null,
            ];
        }

        $db->commit();

        return [
            'ok' => true,
            'fnsku_updates' => $updates,
        ];

    } catch (Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function extractBaseFnsku(string $fnsku): string
{
    $fnsku = strtoupper(trim($fnsku));
    if ($fnsku === '') {
        return $fnsku;
    }

    // Strip exactly 1 letter + 1 digit prefix if remainder is FNSKU
    if (preg_match('/^[A-Z][0-9](X[0-9A-Z]+)$/', $fnsku, $m)) {
        return $m[1];
    }

    return $fnsku;
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

    // Keep same keys your script expects
    return [
        'access_token' => $token,
        'expires_in' => $exp, // absolute unix (as you implemented)
    ];
}

function normalizeIdentifierTuple(string $fnskuviewer, string $mskuviewer, string $asinviewer): array
{
    $fnsku = normalizeFnskuViewer($fnskuviewer);
    $msku  = strtoupper(trim($mskuviewer));
    $asin  = strtoupper(trim($asinviewer));

    return [
        'fnsku' => $fnsku,
        'msku'  => $msku,
        'asin'  => $asin,
    ];
}

/**
 * Normalize FNSKUviewer -> base FNSKU used in tblfnsku.FNSKU
 * Handles:
 *  - No prefix:          X00ABC12345
 *  - 2-letter prefix:    AAX00ABC12345   (example)
 *  - Old pattern:        A1X00ABC12345   (your previous logic)
 */
function normalizeFnskuViewer(string $fnsku): string
{
    $s = strtoupper(trim($fnsku));
    if ($s === '') return '';

    // If it contains X... somewhere, keep from first X onward (common safest rule)
    // e.g. "AAX00ABC" -> "X00ABC"
    $pos = strpos($s, 'X');
    if ($pos !== false) {
        $candidate = substr($s, $pos);
        // basic sanity: starts with X and has letters/numbers only
        if (preg_match('/^X[0-9A-Z]+$/', $candidate)) {
            return $candidate;
        }
    }

    // Legacy exact strip rules (kept as fallback)
    if (preg_match('/^[A-Z][0-9](X[0-9A-Z]+)$/', $s, $m)) return $m[1];
    if (preg_match('/^[A-Z]{2}(X[0-9A-Z]+)$/', $s, $m)) return $m[1];

    return $s;
}

/**
 * Increment tblfnsku.units by matching ANY of:
 *  - tblfnsku.FNSKU = normalized fnsku
 *  - tblfnsku.MSKU  = msku
 *  - tblfnsku.ASIN  = asin
 *
 * Only includes non-empty identifiers in the WHERE clause.
 */
function increment_tblfnsku_units_by_any_identifier(
    mysqli $db,
    int $qty,
    string $fnsku,
    string $msku,
    string $asin
): array {
    if ($qty <= 0) return ['ok' => false, 'error' => 'qty must be > 0'];

    $conds = [];
    $types = "i";
    $params = [$qty];

    if ($fnsku !== '') { $conds[] = "FNSKU = ?"; $types .= "s"; $params[] = $fnsku; }
    if ($msku  !== '') { $conds[] = "MSKU  = ?"; $types .= "s"; $params[] = $msku; }
    if ($asin  !== '') { $conds[] = "ASIN  = ?"; $types .= "s"; $params[] = $asin; }

    if (count($conds) === 0) {
        return ['ok' => false, 'error' => 'No identifiers (fnsku/msku/asin) provided'];
    }

    $sql = "
        UPDATE tblfnsku
        SET units = COALESCE(units, 0) + ?
        WHERE " . implode(" OR ", $conds) . "
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) return ['ok' => false, 'error' => $db->error];

    // bind_param requires references
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

    if ($err) return ['ok' => false, 'error' => $err];

    // NOTE: affected_rows can be 0 if record exists but value unchanged (rare) or no match
    if ($affected <= 0) {
        return [
            'ok' => false,
            'error' => "No tblfnsku rows matched for fnsku={$fnsku} msku={$msku} asin={$asin}"
        ];
    }

    return ['ok' => true, 'rowsAffected' => $affected];
}