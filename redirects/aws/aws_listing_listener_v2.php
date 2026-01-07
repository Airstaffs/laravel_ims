<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$Connect = new mysqli("localhost", "u298641722_dbims_user", "?cIk=|zRk3T", "u298641722_dbims");

// 1) Verify secret header
$expectedSecret = 'eb_4f9c2a7d8e6b41a9b0d3c5e7f2a8d6c1b9e4a7f0d3c8e5b2a6f9c1d7e4';
$receivedSecret = $_SERVER['HTTP_SECRET_IMS_KEY_V2'] ?? '';

if (!hash_equals($expectedSecret, $receivedSecret)) {
    http_response_code(401);
    exit('Unauthorized');
}

// 2) Read JSON body
$raw = file_get_contents('php://input');
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

// 4) Extract detail block
$detail = $event['detail'] ?? [];

$sellerId       = $detail['sellerId'] ?? null;
$marketplaceId  = $detail['marketplaceId'] ?? null;
$asin           = $detail['asin'] ?? null;
$sku            = $detail['sku'] ?? null;

// These vary by notification/payload version, so keep them optional:
$notificationVersion = $detail['notificationVersion'] ?? ($detail['notificationMetadata']['notificationVersion'] ?? null);
$notificationType    = $detail['notificationType'] ?? null;
$payloadVersion      = $detail['payloadVersion'] ?? null;

$eventDetailTimeIso  = $detail['eventTime'] ?? ($detail['time'] ?? null);
$createdDateIso      = $detail['createdDate'] ?? null;

// Status can be string/array depending on payload; store as JSON text if array
$statusVal = $detail['status'] ?? null;
$statusJson = null;
if (is_array($statusVal)) {
    $statusJson = json_encode($statusVal, JSON_UNESCAPED_SLASHES);
} elseif ($statusVal !== null) {
    $statusJson = json_encode([$statusVal], JSON_UNESCAPED_SLASHES);
}

// Metadata (optional)
$notificationId = $detail['notificationId'] ?? null;
$subscriptionId = $detail['subscriptionId'] ?? null;
$applicationId  = $detail['applicationId'] ?? null;
$publishTimeIso = $detail['publishTime'] ?? null;

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
