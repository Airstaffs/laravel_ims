<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ---- DEBUG HIT LOG (temporary) ----
$logFile = __DIR__ . '/eb_hits.log';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$raw = file_get_contents('php://input');

file_put_contents($logFile, date('c') . " HIT\n", FILE_APPEND);
file_put_contents($logFile, "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n", FILE_APPEND);
file_put_contents($logFile, "UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n", FILE_APPEND);
file_put_contents($logFile, "Headers: " . json_encode($headers) . "\n", FILE_APPEND);
file_put_contents($logFile, "BodyLen: " . strlen($raw) . "\n", FILE_APPEND);
file_put_contents($logFile, "BodyHead: " . substr($raw, 0, 500) . "\n\n", FILE_APPEND);

$Connect = new mysqli("localhost", "imsv2_dbims_user", "Imsv2_dbims_user", "imsv2_dbims");
// 1) Verify secret header
$expectedSecret = 'eb_4f9c2a7d8e6b41a9b0d3c5e7f2a8d6c1b9e4a7f0d3c8e5b2a6f9c1d7e4';
$receivedSecret = $_SERVER['HTTP_SECRET_IMS_KEY_V2'] ?? '';

if (!hash_equals($expectedSecret, $receivedSecret)) {
    http_response_code(401);
    exit('Unauthorized');
}

// 2) Read JSON body
if (!$raw) {
    http_response_code(400);
    exit('No body');
}

$event = json_decode($raw, true);
if (!is_array($event)) {
    http_response_code(400);
    exit('Bad JSON');
}

// 3) Extract top-level
$version     = $event['version'] ?? null;
$eventId     = $event['id'] ?? null;
$detailType  = $event['detail-type'] ?? null;
$source      = $event['source'] ?? null;
$account     = $event['account'] ?? null;
$region      = $event['region'] ?? null;
$eventTimeIso = $event['time'] ?? null;

/// 4) Extract detail block (support Amazon LISTINGS_ITEM_STATUS_CHANGE format)
$detail = $event['detail'] ?? [];

// Amazon format: detail.Payload has the useful fields
$payload = $detail['Payload'] ?? [];

// Helper to read either camelCase OR PascalCase keys
function pick($arr, ...$keys) {
    foreach ($keys as $k) {
        if (is_array($arr) && array_key_exists($k, $arr)) return $arr[$k];
    }
    return null;
}

// From detail (top-level)
$notificationVersion = pick($detail, 'notificationVersion', 'NotificationVersion');
$notificationType    = pick($detail, 'notificationType', 'NotificationType');
$payloadVersion      = pick($detail, 'payloadVersion', 'PayloadVersion');
$eventDetailTimeIso  = pick($detail, 'eventTime', 'EventTime');

// From detail.Payload (listing fields)
$sellerId      = pick($payload, 'sellerId', 'SellerId');
$marketplaceId = pick($payload, 'marketplaceId', 'MarketplaceId');
$asin          = pick($payload, 'asin', 'Asin');
$sku           = pick($payload, 'sku', 'Sku', 'sellerSku', 'SellerSku', 'sellerSKU', 'SellerSKU');
$createdDateIso= pick($payload, 'createdDate', 'CreatedDate');

$statusVal = pick($payload, 'status', 'Status');
$statusJson = null;
if (is_array($statusVal)) {
    $statusJson = json_encode($statusVal, JSON_UNESCAPED_SLASHES);
} elseif ($statusVal !== null) {
    $statusJson = json_encode([$statusVal], JSON_UNESCAPED_SLASHES);
}

// Metadata (Amazon: detail.NotificationMetadata)
$meta = $detail['NotificationMetadata'] ?? ($detail['notificationMetadata'] ?? []);

$notificationId = pick($meta, 'notificationId', 'NotificationId');
$subscriptionId = pick($meta, 'subscriptionId', 'SubscriptionId');
$applicationId  = pick($meta, 'applicationId', 'ApplicationId');
$publishTimeIso = pick($meta, 'publishTime', 'PublishTime');

// Helper: ISO -> MySQL datetime
function isoToMysqlDatetime($iso) {
    if (!$iso) return null;
    $ts = strtotime($iso);
    if ($ts === false) return null;
    return gmdate('Y-m-d H:i:s', $ts);
}

$eventTime      = isoToMysqlDatetime($eventTimeIso);
$eventDetailTime= isoToMysqlDatetime($eventDetailTimeIso);
$createdDate    = isoToMysqlDatetime($createdDateIso);
$publishTime    = isoToMysqlDatetime($publishTimeIso);

// 5) Insert quickly (dedupe by event_id if you add UNIQUE uq_event_id)
$sql = "INSERT INTO tblnewlycreatedamznitems
(version, event_id, detail_type, source, account, event_time, region,
 notification_version, notification_type, payload_version, event_detail_time,
 seller_id, marketplace_id, asin, sku, created_date, status,
 notification_id, subscription_id, application_id, publish_time, cron_insert_status)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'FALSE')
ON DUPLICATE KEY UPDATE
  publish_time = VALUES(publish_time),
  event_time = VALUES(event_time),
  cron_insert_status = cron_insert_status";

$stmt = $Connect->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    exit("Prepare failed: " . $Connect->error);
}

$stmt->bind_param(
    "sssssssssssssssssssss",
    $version, $eventId, $detailType, $source, $account, $eventTime, $region,
    $notificationVersion, $notificationType, $payloadVersion, $eventDetailTime,
    $sellerId, $marketplaceId, $asin, $sku, $createdDate, $statusJson,
    $notificationId, $subscriptionId, $applicationId, $publishTime
);

$ok = $stmt->execute();
$stmt->close();

// 6) Respond 200 no matter what if insert ok; otherwise 500 to trigger retries
if (!$ok) {
    http_response_code(500);
    exit("Insert failed");
}

http_response_code(200);
echo "OK";
