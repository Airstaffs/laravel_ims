<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* =========================================================
   CONFIG
========================================================= */
$logFile = __DIR__ . '/eb_hits.log';
$expectedSecret = 'eb_4f9c2a7d8e6b41a9b0d3c5e7f2a8d6c1b9e4a7f0d3c8e5b2a6f9c1d7e4';
$receiverUrl = 'https://ims.tecniquality.com/Admin/include/conversion/receiver.php';
$receiverSecret = 'eb_4f9c2a7d8e6b41a9b0d3c5e7f2a8d6c1b9e4a7f0d3c8e5b2a6f9c1d7e4';

/* =========================================================
   LOG ROTATION (10MB)
========================================================= */
if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
    rename($logFile, __DIR__ . '/eb_hits_' . date('Ymd_His') . '.log');
}

/* =========================================================
   LOG HELPERS
========================================================= */
function logLine($msg) {
    global $logFile;
    file_put_contents($logFile, $msg . "\n", FILE_APPEND);
}

function logBlock($title, $data) {
    global $logFile;
    $out = is_string($data)
        ? $data
        : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    file_put_contents($logFile, "== {$title} ==\n{$out}\n", FILE_APPEND);
}

function fail($httpCode, $msg, $extra = null) {
    if ($extra !== null) logBlock("FAIL EXTRA", $extra);
    logLine("RESULT: FAIL {$httpCode} {$msg}");
    logLine(str_repeat("-", 70));
    http_response_code($httpCode);
    exit($msg);
}

function ok($msg = "OK", $extra = null) {
    if ($extra !== null) logBlock("OK EXTRA", $extra);
    logLine("RESULT: OK 200 {$msg}");
    logLine(str_repeat("-", 70));
    http_response_code(200);
    echo $msg;
    exit;
}

function sendToIMSReceiver($receiverUrl, $receiverSecret, array $payload)
{
    $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init($receiverUrl);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'SECRET-IMS-KEY-V2: ' . $receiverSecret
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'success' => $curlError === '' && $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $curlError
    ];
}

/* =========================================================
   HIT LOG
========================================================= */
$headers = function_exists('getallheaders') ? getallheaders() : [];
$raw = file_get_contents('php://input');

logLine(date('c') . " HIT");
logLine("IP: " . ($_SERVER['REMOTE_ADDR'] ?? ''));
logLine("UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
logBlock("Headers", $headers);
logLine("BodyLen: " . strlen($raw));
logLine("BodyHead: " . substr($raw, 0, 800));

/* =========================================================
   AUTH
========================================================= */
$receivedSecret = $_SERVER['HTTP_SECRET_IMS_KEY_V2'] ?? '';
if (!hash_equals($expectedSecret, $receivedSecret)) {
    fail(401, "Unauthorized", ["header_present" => $receivedSecret ? "yes" : "no"]);
}
logLine("AUTH: ok");

/* =========================================================
   JSON
========================================================= */
if (!$raw) {
    fail(400, "No body");
}

$event = json_decode($raw, true);
if (!is_array($event)) {
    fail(400, "Bad JSON", [
        "json_error" => json_last_error(),
        "json_error_msg" => json_last_error_msg()
    ]);
}
logLine("JSON: ok");

/* =========================================================
   TOP-LEVEL FIELDS
========================================================= */
$version       = $event['version'] ?? null;
$eventId       = $event['id'] ?? null;
$detailType    = $event['detail-type'] ?? null;
$source        = $event['source'] ?? null;
$account       = $event['account'] ?? null;
$region        = $event['region'] ?? null;
$eventTimeIso  = $event['time'] ?? null;

/* =========================================================
   DETAIL / PAYLOAD PARSING (Amazon-compatible)
========================================================= */
$detail  = $event['detail'] ?? [];
$payload = $detail['Payload'] ?? ($detail['payload'] ?? []);

function pick($arr, ...$keys) {
    foreach ($keys as $k) {
        if (is_array($arr) && array_key_exists($k, $arr)) return $arr[$k];
    }
    return null;
}

// Detail
$notificationVersion = pick($detail, 'NotificationVersion', 'notificationVersion');
$notificationType    = pick($detail, 'NotificationType', 'notificationType');
$payloadVersion      = pick($detail, 'PayloadVersion', 'payloadVersion');
$eventDetailTimeIso  = pick($detail, 'EventTime', 'eventTime');

// Payload
$sellerId       = pick($payload, 'SellerId', 'sellerId');
$marketplaceId  = pick($payload, 'MarketplaceId', 'marketplaceId');
$asin           = pick($payload, 'Asin', 'asin');
$sku            = pick($payload, 'Sku', 'sku', 'sellerSku', 'SellerSku', 'sellerSKU', 'SellerSKU');
$createdDateIso = pick($payload, 'CreatedDate', 'createdDate');

$statusVal = pick($payload, 'Status', 'status');
$statusJson = null;
if (is_array($statusVal)) {
    $statusJson = json_encode($statusVal, JSON_UNESCAPED_SLASHES);
} elseif ($statusVal !== null) {
    $statusJson = json_encode([$statusVal], JSON_UNESCAPED_SLASHES);
}

// Metadata
$meta = $detail['NotificationMetadata'] ?? ($detail['notificationMetadata'] ?? []);
$notificationId = pick($meta, 'NotificationId', 'notificationId');
$subscriptionId = pick($meta, 'SubscriptionId', 'subscriptionId');
$applicationId  = pick($meta, 'ApplicationId', 'applicationId');
$publishTimeIso = pick($meta, 'PublishTime', 'publishTime');

/* =========================================================
   DATE HELPERS
========================================================= */
function isoToMysql($iso) {
    if (!$iso) return null;
    $ts = strtotime($iso);
    return $ts ? gmdate('Y-m-d H:i:s', $ts) : null;
}

$eventTime       = isoToMysql($eventTimeIso);
$eventDetailTime = isoToMysql($eventDetailTimeIso);
$createdDate     = isoToMysql($createdDateIso);
$publishTime     = isoToMysql($publishTimeIso);

/* =========================================================
   LOG EXTRACTED VALUES
========================================================= */
logBlock("Extracted", [
    "event_id" => $eventId,
    "detail_type" => $detailType,
    "seller_id" => $sellerId,
    "marketplace_id" => $marketplaceId,
    "asin" => $asin,
    "sku" => $sku,
    "event_time" => $eventTime,
    "publish_time" => $publishTime
]);

/* =========================================================
   DB CONNECT
========================================================= */
$Connect = new mysqli("localhost", "imsv2_dbims_user", "Imsv2_dbims_user", "imsv2_dbims");
if ($Connect->connect_error) {
    fail(500, "DB connect failed", $Connect->connect_error);
}
logLine("DB: connected");

/* =========================================================
   INSERT
========================================================= */
$sql = "
INSERT INTO tblnewlycreatedamznitems
(version, event_id, detail_type, source, account, event_time, region,
 notification_version, notification_type, payload_version, event_detail_time,
 seller_id, marketplace_id, asin, sku, created_date, status,
 notification_id, subscription_id, application_id, publish_time, cron_insert_status)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'FALSE')
ON DUPLICATE KEY UPDATE
  publish_time = VALUES(publish_time),
  event_time = VALUES(event_time),
  cron_insert_status = cron_insert_status
";

$stmt = $Connect->prepare($sql);
if (!$stmt) {
    fail(500, "Prepare failed", $Connect->error);
}

$stmt->bind_param(
    "sssssssssssssssssssss",
    $version, $eventId, $detailType, $source, $account, $eventTime, $region,
    $notificationVersion, $notificationType, $payloadVersion, $eventDetailTime,
    $sellerId, $marketplaceId, $asin, $sku, $createdDate, $statusJson,
    $notificationId, $subscriptionId, $applicationId, $publishTime
);

if (!$stmt->execute()) {
    fail(500, "Insert failed", $stmt->error);
}

$insertId = $stmt->insert_id;
$stmt->close();

/* =========================================================
   SEND SAME DATA TO TECNIQUALITY IMS RECEIVER
========================================================= */

$receiverPayload = [
    // Security
    "secret" => $receiverSecret,

    // Top-level event fields
    "version" => $version,
    "event_id" => $eventId,
    "detail_type" => $detailType,
    "source" => $source,
    "account" => $account,
    "event_time" => $eventTime,
    "region" => $region,

    // Notification detail fields
    "notification_version" => $notificationVersion,
    "notification_type" => $notificationType,
    "payload_version" => $payloadVersion,
    "event_detail_time" => $eventDetailTime,

    // Amazon item fields
    "seller_id" => $sellerId,
    "marketplace_id" => $marketplaceId,
    "asin" => $asin,
    "sku" => $sku,
    "created_date" => $createdDate,
    "status" => $statusJson,

    // Metadata fields
    "notification_id" => $notificationId,
    "subscription_id" => $subscriptionId,
    "application_id" => $applicationId,
    "publish_time" => $publishTime,
];

$receiverResult = sendToIMSReceiver($receiverUrl, $receiverSecret, $receiverPayload);

logBlock("TECNIQUALITY RECEIVER RESULT", $receiverResult);

if (!$receiverResult['success']) {
    fail(500, "Local insert OK, but Tecniquality receiver failed", $receiverResult);
}

/* =========================================================
   SUCCESS
========================================================= */
ok("OK", [
    "local_insert_id" => $insertId,
    "receiver_result" => $receiverResult
]);
