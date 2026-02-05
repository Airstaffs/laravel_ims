<?php
/**
 * tblfnsku_updater.php
 *
 * - Manual mode: pass ?msku=XXXX&store=RT
 * - Auto mode: leave blank; it will pull next rows from tblfnsku where FNSKU/grading missing
 * - Resume via tblautomationshandler.additional_config (cron_automation = 'tblfnsku_updater')
 */

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);

$system = "Live"; // "Live" or "Test" (controls endpoint host)
$maxPerRun = 30;  // how many rows to process per run (auto mode)

// ---- Manual inputs (also supports query string) ----
$manualMsku = isset($_GET['msku']) ? trim($_GET['msku']) : '';
$manualStore = isset($_GET['store']) ? trim($_GET['store']) : ''; // RT or AR (recommended)

// ---- DB ----
$Connect = dbDatabase();

// ---- Load cache from tblautomationshandler ----
$cronAutomation = 'tblfnsku_updater';
$cache = getAutomationCache($Connect, $cronAutomation);
// cache example: ["last_id" => 123, "last_msku"=>"X", "last_store"=>"RT", "updated"=>5, "skipped"=>2, "errors"=>1]
if (!is_array($cache))
    $cache = [];
$lastId = isset($cache['last_id']) ? (int) $cache['last_id'] : 0;

$stats = [
    'mode' => '',
    'start_last_id' => $lastId,
    'processed' => 0,
    'updated' => 0,
    'skipped' => 0,
    'errors' => 0,
    'items' => [],
];

// ---- Decide mode ----
$manualMode = ($manualMsku !== '' || $manualStore !== '');

if ($manualMode) {
    // Validate manual inputs
    if ($manualMsku === '' || $manualStore === '') {
        jsonOut([
            'ok' => false,
            'error' => 'Manual mode requires BOTH msku and store. Example: ?msku=ABC123&store=RT',
        ]);
    }
    $stats['mode'] = 'manual';

    $items = [
        [
            'id' => 0,
            'msku' => $manualMsku,
            'storename' => $manualStore,
        ]
    ];
} else {
    $stats['mode'] = 'auto';

    // Pull next rows after last processed id, only valid rows (msku & storename not empty)
    $items = fetchNextMissingFnskuRows($Connect, $lastId, $maxPerRun);
}

// ---- Process items ----
foreach ($items as $row) {
    $rowId = (int) ($row['id'] ?? 0);
    $msku = trim((string) ($row['msku'] ?? ''));
    $store = trim((string) ($row['storename'] ?? ''));

    // Validate required fields
    if ($msku === '' || $store === '') {
        $stats['skipped']++;
        $stats['items'][] = [
            'id' => $rowId,
            'msku' => $msku,
            'store' => $store,
            'status' => 'skipped',
            'reason' => 'msku or storename missing',
        ];
        continue;
    }

    // In auto mode, advance cache BEFORE processing so failures are skipped next run
    if (!$manualMode && $rowId > 0) {
        $cache['last_id'] = $rowId;
        $cache['last_msku'] = $msku;
        $cache['last_store'] = $store;
        $cache['last_touched_at'] = date('Y-m-d H:i:s');
        updateAutomationCache($Connect, $cronAutomation, $cache);
        $lastId = $rowId;
    }

    $stats['processed']++;

    try {
        $credentials = AWSCredentials($Connect, $store);
        if (empty($credentials['client_id']) || empty($credentials['client_secret']) || empty($credentials['refresh_token'])) {
            throw new Exception("Invalid Amazon credentials for store={$store}");
        }

        $accessToken = fetchAccessToken($credentials, false);
        if (!$accessToken) {
            throw new Exception("Failed to fetch access token for store={$store}");
        }

        // Determine selling partner endpoint host
        $endpointHost = ($system === "Test")
            ? 'sandbox.sellingpartnerapi-na.amazon.com'
            : 'sellingpartnerapi-na.amazon.com';

        // Get SellerId (SID) based on store
        $SID = $credentials['MerchantID']; // SellerId directly
        if (!$SID) {
            throw new Exception("SID not found for store={$store}");
        }

        // Call Listings Items API for this MSKU
        $apiRaw = fetchListingsItem($credentials, $accessToken, $SID, $msku, $endpointHost);

        $api = json_decode($apiRaw, true);
        if (!is_array($api)) {
            throw new Exception("Invalid JSON from Amazon for msku={$msku}");
        }

        // Extract FNSKU + grading (best-effort)
        $fnsku = findFirstValueByKeyInsensitive($api, ['fnsku', 'fnSku', 'fNSKU', 'fn_sku']);
        $grading = findFirstValueByKeyInsensitive($api, ['grading', 'conditionType', 'condition_type', 'itemCondition', 'condition']);

        $fnsku = is_string($fnsku) ? trim($fnsku) : '';
        $grading = normalizeGrading($grading);

        // If neither exists, do not update
        if ($fnsku === '' && $grading === '') {
            $stats['skipped']++;
            $stats['items'][] = [
                'id' => $rowId,
                'msku' => $msku,
                'store' => $store,
                'status' => 'skipped',
                'reason' => 'No fnsku/grading found in response',
            ];
            continue;
        }

        // Update tblfnsku only if values present; do NOT overwrite non-empty fields
        $didUpdate = updateTblFnskuIfEmpty($Connect, $rowId, $msku, $store, $fnsku, $grading);

        if ($didUpdate) {
            $stats['updated']++;
            $stats['items'][] = [
                'id' => $rowId,
                'msku' => $msku,
                'store' => $store,
                'status' => 'updated',
                'fnsku' => $fnsku,
                'grading' => $grading,
            ];
        } else {
            $stats['skipped']++;
            $stats['items'][] = [
                'id' => $rowId,
                'msku' => $msku,
                'store' => $store,
                'status' => 'skipped',
                'reason' => 'Row already had values or nothing to update',
                'fnsku' => $fnsku,
                'grading' => $grading,
            ];
        }

    } catch (Throwable $e) {
        $stats['errors']++;
        $stats['items'][] = [
            'id' => $rowId,
            'msku' => $msku,
            'store' => $store,
            'status' => 'error',
            'error' => $e->getMessage(),
        ];
        // continue to next item (skip-on-fail)
        continue;
    }
}

// Final cache update (stats)
$cache['updated_total_last_run'] = $stats['updated'];
$cache['skipped_total_last_run'] = $stats['skipped'];
$cache['errors_total_last_run'] = $stats['errors'];
$cache['last_run_at'] = date('Y-m-d H:i:s');
updateAutomationCache($Connect, $cronAutomation, $cache);

jsonOut([
    'ok' => true,
    'stats' => $stats,
    'cache' => $cache,
]);

// -------------------- Helpers --------------------

function jsonOut($arr)
{
    if (function_exists('ob_clean'))
        @ob_clean();
    header('Content-Type: application/json');
    echo json_encode($arr, JSON_PRETTY_PRINT);
    exit;
}

function fetchNextMissingFnskuRows(mysqli $db, int $lastId, int $limit): array
{
    // Adjust column names if yours differ
    $sql = "
    SELECT FNSKUID as id, MSKU as msku, storename
    FROM tblfnsku
    WHERE FNSKUID > ?
      AND (FNSKU IS NULL OR FNSKU = '' OR grading IS NULL OR grading = '')
      AND (MSKU IS NOT NULL AND MSKU <> '')
      AND (storename IS NOT NULL AND storename <> '')
      AND amazon_status != 'Deleted'
    ORDER BY FNSKUID ASC
    LIMIT ?
";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ii", $lastId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc())
        $rows[] = $r;

    $stmt->close();
    return $rows;
}

function updateTblFnskuIfEmpty(mysqli $db, int $rowId, string $msku, string $store, string $fnsku, string $grading): bool
{
    // If rowId=0 (manual mode), update by msku+storename instead
    if ($rowId > 0) {
        $sql = "
            UPDATE tblfnsku
            SET
                FNSKU = CASE WHEN (FNSKU IS NULL OR FNSKU='') AND ? <> '' THEN ? ELSE FNSKU END,
                grading = CASE WHEN (grading IS NULL OR grading='') AND ? <> '' THEN ? ELSE grading END
            WHERE FNSKUID = ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ssssi", $fnsku, $fnsku, $grading, $grading, $rowId);
    } else {
        $sql = "
            UPDATE tblfnsku
            SET
                FNSKU = CASE WHEN (FNSKU IS NULL OR FNSKU='') AND ? <> '' THEN ? ELSE FNSKU END,
                grading = CASE WHEN (grading IS NULL OR grading='') AND ? <> '' THEN ? ELSE grading END
            WHERE MSKU = ?
              AND storename = ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sssss", $fnsku, $fnsku, $grading, $grading, $msku, $store);
    }

    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    return ($affected > 0);
}

function getAutomationCache(mysqli $db, string $cronAutomation): array
{
    $sql = "SELECT additional_config FROM tblautomationshandler WHERE cron_automation = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $cronAutomation);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row)
        return [];

    $raw = (string) ($row['additional_config'] ?? '');
    if (trim($raw) === '')
        return [];

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function updateAutomationCache(mysqli $db, string $cronAutomation, array $cache): void
{
    $json = json_encode($cache, JSON_UNESCAPED_SLASHES);

    // Ensure row exists; if not, create it
    $exists = false;
    $chk = $db->prepare("SELECT id FROM tblautomationshandler WHERE cron_automation = ? LIMIT 1");
    $chk->bind_param("s", $cronAutomation);
    $chk->execute();
    $r = $chk->get_result();
    $exists = (bool) $r->fetch_assoc();
    $chk->close();

    if ($exists) {
        $sql = "UPDATE tblautomationshandler SET additional_config = ?, time_last_triggered = NOW(), status = 'IDLE' WHERE cron_automation = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ss", $json, $cronAutomation);
        $stmt->execute();
        $stmt->close();
    } else {
        $sql = "INSERT INTO tblautomationshandler (cron_automation, time_last_triggered, status, additional_config) VALUES (?, NOW(), 'IDLE', ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ss", $cronAutomation, $json);
        $stmt->execute();
        $stmt->close();
    }
}

function fetchListingsItem(array $credentials, string $accessToken, string $SID, string $SKU, string $endpointHost): string
{
    $endpoint = "https://{$endpointHost}";
    $region = 'us-east-1';
    $service = 'execute-api';

    $path = "/listings/2021-08-01/items/{$SID}/" . rawurlencode($SKU);

    $query = buildQueryString();
    $url = "{$endpoint}{$path}?{$query}";

    $tries = 0;
    $maxTries = 6;

    do {
        $tries++;

        $amzDate = gmdate('Ymd\THis\Z');
        $headers = buildHeaders($credentials, $accessToken, $path, $query, $region, $service, $endpointHost, $amzDate);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode == 429) {
            sleep(10); // smaller backoff; you can raise if needed
            continue;
        }

        if ($httpcode == 401) {
            // token expired
            $accessToken = fetchAccessToken($credentials, false);
            if (!$accessToken)
                break;
            continue;
        }

        // Return whatever Amazon said (even if 4xx) so caller can decide
        return $result ?: '';
    } while ($tries < $maxTries);

    return $result ?: '';
}

function buildQueryString(): string
{
    // Keep it exactly as Amazon expects; order matters for signature if you sign the query string.
    // If you edit, ensure signature uses the same exact string.
    return 'marketplaceIds=ATVPDKIKX0DER&issueLocale=en_US&includedData=issues,attributes,summaries,offers,fulfillmentAvailability,procurement';
}

function buildHeaders(array $credentials, string $accessToken, string $path, string $canonicalQueryString, string $region, string $service, string $host, string $amzDate): array
{
    $sig = calculateSignature($credentials, $amzDate, $host, $path, $canonicalQueryString, $region, $service);

    return [
        "Content-Type: application/json",
        "host: {$host}",
        "x-amz-date: {$amzDate}",
        "x-amz-access-token: {$accessToken}",
        "Authorization: {$sig['algorithm']} Credential={$credentials['client_id']}/{$sig['dateStamp']}/{$region}/{$service}/aws4_request, SignedHeaders={$sig['signedHeaders']}, Signature={$sig['signature']}",
    ];
}

function calculateSignature(array $credentials, string $amzDate, string $host, string $path, string $canonicalQueryString, string $region, string $service): array
{
    $method = 'GET';
    $canonicalUri = $path;

    // Canonical headers MUST match the real host used
    $canonicalHeaders = "host:{$host}\n" . "x-amz-date:{$amzDate}\n";
    $signedHeaders = 'host;x-amz-date';

    $payloadHash = hash('sha256', ""); // GET payload is empty

    $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

    $algorithm = 'AWS4-HMAC-SHA256';
    $dateStamp = substr($amzDate, 0, 8);
    $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
    $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

    $signatureKey = getSignatureKey($credentials['client_secret'], $dateStamp, $region, $service);
    $signature = hash_hmac('sha256', $stringToSign, $signatureKey);

    return [
        'algorithm' => $algorithm,
        'dateStamp' => $dateStamp,
        'signedHeaders' => $signedHeaders,
        'signature' => $signature,
    ];
}

function getSignatureKey(string $key, string $dateStamp, string $regionName, string $serviceName)
{
    $kSecret = 'AWS4' . $key;
    $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
    $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
    $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    return $kSigning;
}

function findFirstValueByKeyInsensitive($data, array $keys)
{
    if (!is_array($data))
        return null;

    $keySet = [];
    foreach ($keys as $k)
        $keySet[strtolower($k)] = true;

    $stack = [$data];
    while ($stack) {
        $cur = array_pop($stack);
        if (!is_array($cur))
            continue;

        foreach ($cur as $k => $v) {
            if (is_string($k) && isset($keySet[strtolower($k)])) {
                if (is_scalar($v))
                    return (string) $v;
            }
            if (is_array($v))
                $stack[] = $v;
        }
    }

    return null;
}

function getSID(mysqli $db, string $store)
{
    // Map store code -> tblcompanydetails id
    // RT => 2, AR => 3 (based on your comment)
    $store = strtoupper(trim($store));
    $companyId = null;
    if ($store === 'RT' || $store === 'Renovartech')
        $companyId = 2;
    if ($store === 'AR' || $store === 'Allrenewed')
        $companyId = 3;

    if (!$companyId)
        return null;

    $stmt = $db->prepare("SELECT SID FROM tblcompanydetails WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    return $row['SID'] ?? null;
}

function dbDatabase()
{
    // Define server mode here
    $servertype = "local laravel";

    switch ($servertype) {
        case "ims":
            $hostname = 'localhost';
            $username = 'user';
            $password = 'root';
            $database = 'dbims';
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

        case "local laravel":
            $hostname = '127.0.0.1';
            $username = 'user';
            $password = 'root';
            $database = 'dbims';
            break;

        default:
            die("❌ Invalid server type: Set \$servertype properly.");
    }

    $db = new mysqli($hostname, $username, $password, $database);
    if ($db->connect_error) {
        die("DB connect error: " . $db->connect_error);
    }
    return $db;
}

function AWSCredentials(mysqli $db, string $store)
{
    $sql = "
        SELECT client_id, client_secret, refresh_token, MerchantID
        FROM tblstores
        WHERE storename = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $store);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new Exception("Store '{$store}' not found in tblstores");
    }

    return $row;
}

function fetchAccessToken(array $credentials, bool $returnRaw = false)
{
    $postfields = [
        'grant_type' => 'refresh_token',
        'client_id' => $credentials['client_id'],
        'client_secret' => $credentials['client_secret'],
        'refresh_token' => $credentials['refresh_token'],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.amazon.com/auth/o2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
    ]);

    $response = curl_exec($ch);

    if ($response === FALSE) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $decoded = json_decode($response, true);
    if (!is_array($decoded))
        return false;

    if ($returnRaw)
        return $decoded;

    return $decoded['access_token'] ?? false;
}

function normalizeGrading(?string $skucondition): string
{
    if (!$skucondition) return '';

    $map = [
        'new_new' => 'New',
        'new_oem' => 'NewOem',
        'new_open_box' => 'NewOpenBox',

        'used_like_new' => 'UsedLikeNew',
        'used_very_good' => 'UsedVeryGood',
        'used_good' => 'UsedGood',
        'used_acceptable' => 'UsedAcceptable',

        'refurbished_refurbished' => 'Refurbished',
    ];

    $key = strtolower(trim($skucondition));

    return $map[$key] ?? $skucondition; // fallback if already clean
}