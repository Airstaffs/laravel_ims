<?php
/**
 * tblfnsku_updater.php
 *
 * - Manual mode: pass ?msku=XXXX&store=RT
 * - Auto mode: leave blank; it will pull next rows from tblfnsku where FNSKU/grading missing
 * - Resume via tblautomationshandler.additional_config (cron_automation = 'tblfnsku_updater')
 *
 */

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);

$system = "Live"; // "Live" or "Test" (controls endpoint host)
$maxPerRun = 100;  // how many rows to process per run (auto mode)

// ---- Manual inputs (also supports query string) ----
$manualMsku = isset($_GET['msku']) ? trim($_GET['msku']) : '';
$manualStore = isset($_GET['store']) ? trim($_GET['store']) : ''; // RT or AR

// ---- DB ----
$Connect = dbDatabase();

// ---- Load cache from tblautomationshandler ----
$cronAutomation = 'tblfnsku_updater';
$cache = getAutomationCache($Connect, $cronAutomation);
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
    $items = fetchNextMissingFnskuRows($Connect, $lastId, $maxPerRun);
}

// ---- Process items ----
foreach ($items as $row) {
    $rowId = (int) ($row['id'] ?? 0);
    $msku = trim((string) ($row['msku'] ?? ''));
    $store = trim((string) ($row['storename'] ?? ''));

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

        $endpointHost = ($system === "Test")
            ? 'sandbox.sellingpartnerapi-na.amazon.com'
            : 'sellingpartnerapi-na.amazon.com';

        $SID = $credentials['MerchantID']; // SellerId
        if (!$SID) {
            throw new Exception("SID not found for store={$store}");
        }

        // Call Listings Items API for this MSKU
        $resp = fetchListingsItem($credentials, $accessToken, $SID, $msku, $endpointHost);

        if (!empty($resp['curl_err'])) {
            throw new Exception("cURL error: " . $resp['curl_err']);
        }

        $th = isThrottleResponse($resp['httpcode'], $resp['body']);

        if ($resp['httpcode'] === 401) {
            // your existing refresh token retry path if you still want it
            throw new Exception("401 Unauthorized (access token invalid/expired)");
        }

        if ($th['throttled']) {
            // optional: read Retry-After header if present
            $retryAfter = 0;
            if (!empty($resp['headers']['retry-after'])) {
                $retryAfter = (int) $resp['headers']['retry-after'];
            }

            // mark cache so you can see why it stopped
            $cache['stopped_reason'] = 'THROTTLED';
            $cache['throttle_code'] = $th['code']; // e.g. QuotaExceeded
            $cache['throttle_retry_after_sec'] = $retryAfter;
            $cache['throttled_at'] = date('Y-m-d H:i:s');
            updateAutomationCache($Connect, $cronAutomation, $cache);

            // add stats line
            $stats['items'][] = [
                'id' => $rowId,
                'msku' => $msku,
                'store' => $store,
                'status' => 'stopped',
                'reason' => 'Amazon throttled (quota exceeded)',
                'httpcode' => $resp['httpcode'],
                'error_code' => $th['code'],
                'retry_after' => $retryAfter,
            ];

            // IMPORTANT: stop the whole run immediately
            break;
        }

        $api = json_decode($resp['body'], true);
        if (!is_array($api)) {
            throw new Exception("Invalid JSON from Amazon for msku={$msku} (http={$resp['httpcode']})");
        }

        // Extract FNSKU + grading (best-effort)
        $amazonFnsku = findFirstValueByKeyInsensitive($api, ['fnsku', 'fnSku', 'fNSKU', 'fn_sku']);
        $grading = findFirstValueByKeyInsensitive($api, ['grading', 'conditionType', 'condition_type', 'itemCondition', 'condition']);

        $amazonFnsku = is_string($amazonFnsku) ? trim($amazonFnsku) : '';
        $grading = normalizeGrading($grading);

        // If neither exists, do not update
        if ($amazonFnsku === '' && $grading === '') {
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

        // ============================================================
        // 0) HARD BLOCK: if fnsku_update_conflict is already 1, STOP.
        //    (User must clear it. We do NOT auto-clear it.)
        // ============================================================
        if (isFnskuUpdateBlocked($Connect, $rowId, $msku, $store)) {

            if (!alreadyNotifiedToday($Connect, $rowId, $msku, $store)) {
                $title = "FNSKU update is BLOCKED (needs all-clear). MSKU: {$msku}";
                $subtitle = "fnsku_update_conflict = 1";
                $content = "This MSKU is blocked from syncing because fnsku_update_conflict=1. "
                    . "After relabel/verification, clear the conflict flag to allow updates.";

                $linkData = [
                    "type" => "modal",
                    "modal_id" => "fnskuMismatchResolver",
                    "actions" => [
                        [
                            "id" => "view_items",
                            "label" => "View mismatch items",
                            "type" => "redirect",
                            "method" => "GET",
                            "url" => "/stockroom/fnsku-mismatch?msku={$msku}",
                        ],
                        [
                            "id" => "resolve",
                            "label" => "Clear block (after relabel)",
                            "type" => "api",
                            "method" => "POST",
                            "url" => "/api/fnsku/resolve-mismatch",
                            "payload" => ["msku" => $msku],
                        ],
                        [
                            "id" => "override",
                            "label" => "Override (force sync)",
                            "type" => "api",
                            "method" => "POST",
                            "url" => "/api/fnsku/override-sync",
                            "payload" => ["msku" => $msku, "store" => $store],
                        ],
                    ],
                    "context" => [
                        "msku" => $msku,
                        "amazon_fnsku" => $amazonFnsku,
                        "note" => "Blocked until user clears fnsku_update_conflict.",
                    ],
                ];

                $userIds = getAllUserIdsToNotify($Connect);
                createNotification($Connect, "All", $title, $subtitle, $content, "warning", $linkData, $userIds);

                markNotifiedToday($Connect, $rowId, $msku, $store);
            }

            $stats['skipped']++;
            $stats['items'][] = [
                'id' => $rowId,
                'msku' => $msku,
                'store' => $store,
                'status' => 'skipped',
                'reason' => 'fnsku_update_conflict=1 (blocked until user clears)',
                'amazon_fnsku' => $amazonFnsku,
            ];
            continue;
        }

        // ============================================================
        // 1) CONFLICT CHECK FIRST (before any DB updates)
        //    Compare:
        //      - Amazon FNSKU (base)
        //      - tblproduct.FNSKUviewer (base, ignoring prefix)
        // ============================================================
        if ($amazonFnsku !== '') {
            $chk = checkFnskuConflictTblproduct($Connect, $msku, $amazonFnsku);

            if ($chk['conflict']) {
                // Set conflict flag (blocks updates)
                setFnskuConflictFlag($Connect, $rowId, $msku, $store, true);
                $conflictReason = "Amazon FNSKU does not match tblproduct.FNSKUviewer base value, or multiple distinct base FNSKUs exist.";
                $conflictLogId = upsertFnskuConflictRecord(
                    $Connect,
                    $rowId,
                    $msku,
                    $store,
                    $amazonFnsku,
                    $grading,
                    $conflictReason
                );

                // Notify once/day
                if (!alreadyNotifiedToday($Connect, $rowId, $msku, $store)) {
                    $title = "FNSKU update conflict! MSKU: {$msku}";
                    $subtitle = "Relabel required before syncing FNSKU";
                    $content = "FNSKU mismatch detected (Amazon vs tblproduct.FNSKUviewer). "
                        . "Please relabel the following items based on MSKU ({$msku}).";

                    $linkData = [
                        "type" => "actions",
                        "actions" => [
                            [
                                "id" => "approve_apply",
                                "label" => "Apply New FNSKU",
                                "type" => "api",
                                "method" => "POST",
                                "url" => "/fnsku-conflicts/apply",
                                "payload" => [
                                    "conflict_id" => $conflictLogId,
                                    "msku" => $msku,
                                    "store" => $store,
                                ],
                            ],
                            [
                                "id" => "override_clear",
                                "label" => "Keep Current FNSKU",
                                "type" => "api",
                                "method" => "POST",
                                "url" => "/fnsku-conflicts/override",
                                "payload" => [
                                    "conflict_id" => $conflictLogId,
                                    "msku" => $msku,
                                    "store" => $store,
                                ],
                            ],
                        ],
                        "context" => [
                            "conflict_id" => $conflictLogId,
                            "msku" => $msku,
                            "store" => $store,
                            "old_fnsku" => $chk['distinct_base_fnskus'][0] ?? '',
                            "new_fnsku" => $amazonFnsku,
                            "note" => "Pending FNSKU conflict logged. User action required.",
                        ],
                    ];

                    $userIds = getAllUserIdsToNotify($Connect);
                    createNotification($Connect, "All", $title, $subtitle, $content, "warning", $linkData, $userIds);

                    markNotifiedToday($Connect, $rowId, $msku, $store);
                }

                // IMPORTANT: block ALL updates when conflict exists
                $stats['skipped']++;
                $stats['items'][] = [
                    'id' => $rowId,
                    'msku' => $msku,
                    'store' => $store,
                    'status' => 'skipped',
                    'reason' => 'Conflict detected: updates blocked until resolved',
                    'amazon_fnsku' => $amazonFnsku,
                    'mismatch_count' => $chk['mismatch_count'],
                    'distinct_fnsku_count' => $chk['distinct_fnsku_count'],
                ];
                continue;
            }
        }

        // ============================================================
        // 2) SAFE ZONE (conflict-free + not blocked)
        //    - Update tblfnsku only if empty fields
        //    - Update tblproduct.FNSKUviewer by MSKUviewer (preserve prefix)
        // ============================================================

        $didUpdateFnsku = updateTblFnskuIfEmpty($Connect, $rowId, $msku, $store, $amazonFnsku, $grading);

        $updatedProducts = 0;
        if ($amazonFnsku !== '') {
            $updatedProducts = updateTblproductFnskuViewerByMskuPreservePrefix($Connect, $msku, $amazonFnsku);
        }

        if ($didUpdateFnsku || $updatedProducts > 0) {
            $stats['updated']++;
            $stats['items'][] = [
                'id' => $rowId,
                'msku' => $msku,
                'store' => $store,
                'status' => 'updated',
                'fnsku' => $amazonFnsku,
                'grading' => $grading,
                'tblproduct_updated' => $updatedProducts,
            ];
        } else {
            $stats['skipped']++;
            $stats['items'][] = [
                'id' => $rowId,
                'msku' => $msku,
                'store' => $store,
                'status' => 'skipped',
                'reason' => 'Nothing to update (already filled / already correct)',
                'fnsku' => $amazonFnsku,
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
    $sql = "
        SELECT FNSKUID as id, MSKU as msku, storename
        FROM tblfnsku
        WHERE FNSKUID > ?
          AND (FNSKU IS NULL OR FNSKU = '' OR grading IS NULL OR grading = '')
          AND (MSKU IS NOT NULL AND MSKU <> '')
          AND (storename IS NOT NULL AND storename <> '')
          AND (amazon_status IS NULL OR amazon_status <> 'Deleted')
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
    // NOTE: this function ONLY updates empty fields; never overwrites existing
    if ($rowId > 0) {
        $sql = "
            UPDATE tblfnsku
            SET
                FNSKU = CASE WHEN (FNSKU IS NULL OR FNSKU='') AND ? <> '' THEN ? ELSE FNSKU END,
                grading = CASE WHEN (grading IS NULL OR grading='') AND ? <> '' THEN ? ELSE grading END
            WHERE FNSKUID = ?
              AND (fnsku_update_conflict IS NULL OR fnsku_update_conflict = 0)
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
              AND (fnsku_update_conflict IS NULL OR fnsku_update_conflict = 0)
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

function fetchListingsItem(array $credentials, string $accessToken, string $SID, string $SKU, string $endpointHost): array
{
    $endpoint = "https://{$endpointHost}";
    $region = 'us-east-1';
    $service = 'execute-api';

    $path = "/listings/2021-08-01/items/{$SID}/" . rawurlencode($SKU);
    $query = buildQueryString();
    $url = "{$endpoint}{$path}?{$query}";

    $amzDate = gmdate('Ymd\THis\Z');
    $headers = buildHeaders($credentials, $accessToken, $path, $query, $region, $service, $endpointHost, $amzDate);

    $respHeaders = [];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

    // capture headers
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$respHeaders) {
        $len = strlen($headerLine);
        $headerLine = trim($headerLine);
        if ($headerLine === '' || strpos($headerLine, ':') === false)
            return $len;
        [$k, $v] = explode(':', $headerLine, 2);
        $respHeaders[strtolower(trim($k))] = trim($v);
        return $len;
    });

    $body = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    return [
        'httpcode' => (int) $httpcode,
        'headers' => $respHeaders,
        'body' => $body ?: '',
        'curl_err' => $curlErr ?: '',
    ];
}

function isThrottleResponse(int $httpcode, string $body): array
{
    if ($httpcode !== 429) {
        return ['throttled' => false, 'code' => '', 'retry_after' => 0];
    }

    $retryAfter = 0;
    // Retry-After is not guaranteed in SP-API, but handle if present
    // (we’ll set it from headers later if you want)

    $decoded = json_decode($body, true);
    $errCode = '';

    if (is_array($decoded) && isset($decoded['errors'][0]['code'])) {
        $errCode = (string) $decoded['errors'][0]['code'];
    }

    // Typical: QuotaExceeded (and sometimes TooManyRequests* depending on API)
    $is = in_array($errCode, ['QuotaExceeded', 'TooManyRequests', 'TooManyRequestsException'], true);

    return ['throttled' => $is, 'code' => $errCode, 'retry_after' => $retryAfter];
}

function buildQueryString(): string
{
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

    $canonicalHeaders = "host:{$host}\n" . "x-amz-date:{$amzDate}\n";
    $signedHeaders = 'host;x-amz-date';

    $payloadHash = hash('sha256', "");
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

function dbDatabase()
{
    $servertype = "laravel_ims";

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
    if (!$skucondition)
        return '';

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
    return $map[$key] ?? $skucondition;
}

/**
 * Extract base FNSKU from a possibly-prefixed value in tblproduct.FNSKUviewer.
 * Example: C1X123... -> X123...
 *          X123...   -> X123...
 */
function extractBaseFnsku(string $fnsku): string
{
    $fnsku = trim($fnsku);
    if ($fnsku === '')
        return '';

    if (preg_match('/^([C-W]|[Y-Z])(\d+)(X.+)$/', $fnsku, $m)) {
        return $m[3];
    }
    return $fnsku;
}

/**
 * Conflict check:
 * - Reads tblproduct.FNSKUviewer for MSKUviewer = $msku
 * - Compares BASE values (ignoring prefix) vs Amazon base fnsku
 * - conflict if:
 *   a) any mismatch exists, OR
 *   b) more than 1 distinct base fnsku exists in tblproduct for that MSKU
 */
function checkFnskuConflictTblproduct(mysqli $db, string $msku, string $amazonFnsku): array
{
    $amazonFnsku = trim($amazonFnsku);

    $sql = "
        SELECT DISTINCT FNSKUviewer
        FROM tblproduct
        WHERE MSKUviewer = ?
          AND FNSKUviewer IS NOT NULL
          AND FNSKUviewer <> ''
          AND (ProductModuleLoc IS NULL OR ProductModuleLoc <> 'SoldList')
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $msku);
    $stmt->execute();
    $res = $stmt->get_result();

    $distinctBase = [];
    $mismatchCount = 0;

    while ($row = $res->fetch_assoc()) {
        $raw = trim((string) ($row['FNSKUviewer'] ?? ''));
        if ($raw === '')
            continue;

        $base = extractBaseFnsku($raw);
        if ($base === '')
            continue;

        $distinctBase[$base] = true;

        if ($amazonFnsku !== '' && $base !== $amazonFnsku) {
            $mismatchCount++;
        }
    }

    $stmt->close();

    $distinctCount = count($distinctBase);

    return [
        'conflict' => ($mismatchCount > 0 || $distinctCount > 1),
        'mismatch_count' => $mismatchCount,
        'distinct_fnsku_count' => $distinctCount,
        'distinct_base_fnskus' => array_keys($distinctBase),
    ];
}

function updateTblproductFnskuViewerByMskuPreservePrefix(mysqli $db, string $msku, string $amazonFnsku): int
{
    $amazonFnsku = trim($amazonFnsku);
    if ($amazonFnsku === '')
        return 0;

    $sql = "
        SELECT ProductID, FNSKUviewer
        FROM tblproduct
        WHERE MSKUviewer = ?
          AND (ProductModuleLoc IS NULL OR ProductModuleLoc <> 'SoldList')
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $msku);
    $stmt->execute();
    $res = $stmt->get_result();

    $updates = 0;

    while ($row = $res->fetch_assoc()) {
        $pid = (int) ($row['ProductID'] ?? 0);
        if ($pid <= 0)
            continue;

        $current = trim((string) ($row['FNSKUviewer'] ?? ''));

        // Preserve prefix if present (C1/B1/G3/etc)
        if ($current !== '' && preg_match('/^([C-W]|[Y-Z])(\d+)(X.+)$/', $current, $m)) {
            $prefix = $m[1] . $m[2];           // e.g. C1
            $newVal = $prefix . $amazonFnsku;  // e.g. C1X...
        } else {
            $newVal = $amazonFnsku;
        }

        // If base already matches Amazon, skip
        $baseNow = extractBaseFnsku($current);
        if ($baseNow === $amazonFnsku && $current !== '') {
            continue;
        }

        $u = $db->prepare("UPDATE tblproduct SET FNSKUviewer = ? WHERE ProductID = ?");
        $u->bind_param("si", $newVal, $pid);
        $u->execute();
        if ($u->affected_rows > 0)
            $updates++;
        $u->close();
    }

    $stmt->close();
    return $updates;
}

function setFnskuConflictFlag(mysqli $db, int $rowId, string $msku, string $store, bool $isConflict): void
{
    $flag = $isConflict ? 1 : 0;

    if ($rowId > 0) {
        $sql = "
            UPDATE tblfnsku
            SET fnsku_update_conflict = ?,
                fnsku_conflict_detected_at = CASE WHEN ?=1 THEN NOW() ELSE fnsku_conflict_detected_at END
            WHERE FNSKUID = ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("iii", $flag, $flag, $rowId);
    } else {
        $sql = "
            UPDATE tblfnsku
            SET fnsku_update_conflict = ?,
                fnsku_conflict_detected_at = CASE WHEN ?=1 THEN NOW() ELSE fnsku_conflict_detected_at END
            WHERE MSKU = ?
              AND storename = ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("iiss", $flag, $flag, $msku, $store);
    }

    $stmt->execute();
    $stmt->close();
}

function isFnskuUpdateBlocked(mysqli $db, int $rowId, string $msku, string $store): bool
{
    if ($rowId > 0) {
        $sql = "SELECT fnsku_update_conflict AS f FROM tblfnsku WHERE FNSKUID = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $rowId);
    } else {
        $sql = "SELECT fnsku_update_conflict AS f FROM tblfnsku WHERE MSKU = ? AND storename = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ss", $msku, $store);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    return ((int) ($row['f'] ?? 0) === 1);
}

function alreadyNotifiedToday(mysqli $db, int $rowId, string $msku, string $store): bool
{
    if ($rowId > 0) {
        $sql = "SELECT fnsku_conflict_last_notified_at AS d FROM tblfnsku WHERE FNSKUID = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $rowId);
    } else {
        $sql = "SELECT fnsku_conflict_last_notified_at AS d FROM tblfnsku WHERE MSKU = ? AND storename = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ss", $msku, $store);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    $d = (string) ($row['d'] ?? '');
    return ($d === date('Y-m-d'));
}

function markNotifiedToday(mysqli $db, int $rowId, string $msku, string $store): void
{
    $today = date('Y-m-d');
    if ($rowId > 0) {
        $sql = "UPDATE tblfnsku SET fnsku_conflict_last_notified_at = ? WHERE FNSKUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("si", $today, $rowId);
    } else {
        $sql = "UPDATE tblfnsku SET fnsku_conflict_last_notified_at = ? WHERE MSKU = ? AND storename = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sss", $today, $msku, $store);
    }
    $stmt->execute();
    $stmt->close();
}

function getAllUserIdsToNotify(mysqli $db): array
{
    $ids = [1, 4, 14, 15, 16, 17, 18, 19, 20, 21, 22, 26];

    return $ids;
}

function createNotification(mysqli $db, string $module, string $title, string $subtitle, string $content, string $severity, array $linkData, array $userIds): void
{
    $linkJson = json_encode($linkData, JSON_UNESCAPED_SLASHES);

    $sql = "
        INSERT INTO tblnotifications (module, title, subtitle, content, severity, created_at, link_data)
        VALUES (?, ?, ?, ?, ?, NOW(), ?)
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ssssss", $module, $title, $subtitle, $content, $severity, $linkJson);
    $stmt->execute();
    $notifId = (int) $stmt->insert_id;
    $stmt->close();

    if ($notifId <= 0)
        return;

    $sql2 = "INSERT INTO tblnotificationsuser (notif_id, userid, read_status, created_at) VALUES (?, ?, 'unread', NOW())";
    $stmt2 = $db->prepare($sql2);

    foreach ($userIds as $uid) {
        $uid = (int) $uid;
        $stmt2->bind_param("ii", $notifId, $uid);
        $stmt2->execute();
    }
    $stmt2->close();
}

function upsertFnskuConflictRecord(
    mysqli $db,
    int $rowId,
    string $msku,
    string $store,
    string $newFnsku,
    string $newGrading,
    string $reason = ''
): int {
    $asin = '';
    $oldFnsku = '';
    $oldGrading = '';

    // Get tblfnsku row data first
    if ($rowId > 0) {
        $stmt = $db->prepare("
            SELECT ASIN, FNSKU, grading
            FROM tblfnsku
            WHERE FNSKUID = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $rowId);
    } else {
        $stmt = $db->prepare("
            SELECT ASIN, FNSKU, grading
            FROM tblfnsku
            WHERE MSKU = ? AND storename = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $msku, $store);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if ($row) {
        $asin = trim((string) ($row['ASIN'] ?? ''));
        $oldGrading = trim((string) ($row['grading'] ?? ''));
        $oldFnskuRaw = trim((string) ($row['FNSKU'] ?? ''));
        $oldFnsku = extractBaseFnsku($oldFnskuRaw);
    }

    // If tblfnsku old FNSKU is empty, fallback to tblproduct
    if ($oldFnsku === '') {
        $stmt2 = $db->prepare("
            SELECT FNSKUviewer
            FROM tblproduct
            WHERE MSKUviewer = ?
              AND FNSKUviewer IS NOT NULL
              AND FNSKUviewer <> ''
              AND (ProductModuleLoc IS NULL OR ProductModuleLoc <> 'SoldList')
            LIMIT 1
        ");
        $stmt2->bind_param("s", $msku);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $row2 = $res2->fetch_assoc();
        $stmt2->close();

        if ($row2) {
            $oldFnsku = extractBaseFnsku((string) $row2['FNSKUviewer']);
        }
    }

    // Prevent duplicate pending rows for same MSKU/store/newfnsku
    $chk = $db->prepare("
        SELECT id
        FROM tblfnskuconflicts
        WHERE MSKU = ?
          AND storename = ?
          AND newfnsku = ?
          AND status = 'pending'
        ORDER BY id DESC
        LIMIT 1
    ");
    $chk->bind_param("sss", $msku, $store, $newFnsku);
    $chk->execute();
    $resChk = $chk->get_result();
    $existing = $resChk->fetch_assoc();
    $chk->close();

    if ($existing) {
        $conflictId = (int) $existing['id'];

        $upd = $db->prepare("
            UPDATE tblfnskuconflicts
            SET
                FNSKUID = ?,
                ASIN = ?,
                oldfnsku = ?,
                oldgrading = ?,
                newgrading = ?,
                conflict_reason = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $upd->bind_param(
            "isssssi",
            $rowId,
            $asin,
            $oldFnsku,
            $oldGrading,
            $newGrading,
            $reason,
            $conflictId
        );
        $upd->execute();
        $upd->close();

        return $conflictId;
    }

    $ins = $db->prepare("
        INSERT INTO tblfnskuconflicts
        (
            FNSKUID, MSKU, ASIN, storename,
            oldfnsku, newfnsku,
            oldgrading, newgrading,
            status, conflict_reason,
            created_at, updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())
    ");
    $ins->bind_param(
        "issssssss",
        $rowId,
        $msku,
        $asin,
        $store,
        $oldFnsku,
        $newFnsku,
        $oldGrading,
        $newGrading,
        $reason
    );
    $ins->execute();
    $newId = (int) $ins->insert_id;
    $ins->close();

    return $newId;
}