<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('America/Los_Angeles');

/**
 * =========================================================
 *  ONE-FILE MANUAL AMAZON ORDER IMPORTER (SP-API)
 *  - Select store: AllRenewed / RenovarTech
 *  - Input AmazonOrderId(s)
 *  - Fetch /orders/v0/orders/{id}
 *  - Fetch /orders/v0/orders/{id}/orderItems
 *  - Upsert tbloutboundorders + tbloutboundordersitem
 * =========================================================
 */

/* =========================
   UI (GET)
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!doctype html>
    <html>

    <head>
        <meta charset="utf-8">
        <title>Manual Amazon Order Import</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                padding: 18px;
            }

            label {
                font-weight: 600;
                display: block;
                margin-top: 12px;
            }

            select,
            textarea,
            button {
                width: 560px;
                max-width: 100%;
            }

            textarea {
                height: 160px;
                padding: 10px;
            }

            button {
                margin-top: 12px;
                padding: 10px;
                cursor: pointer;
            }

            .hint {
                color: #666;
                font-size: 13px;
                margin-top: 6px;
            }

            .card {
                border: 1px solid #ddd;
                padding: 12px;
                border-radius: 8px;
                max-width: 720px;
            }
        </style>
    </head>

    <body>
        <div class="card">
            <h2 style="margin:0 0 8px 0;">Manual Amazon Order Import</h2>
            <div class="hint">One file. No NextToken. Just paste order IDs.</div>

            <form method="POST">
                <label>Store</label>
                <select name="store" required>
                    <option value="AllRenewed">AllRenewed</option>
                    <option value="RenovarTech">RenovarTech</option>
                </select>

                <label>Amazon Order ID(s)</label>
                <textarea name="order_ids" placeholder="Example:
112-1234567-1234567
112-7654321-7654321" required></textarea>
                <div class="hint">Accepts one per line, comma-separated, or space-separated.</div>

                <button type="submit">Import</button>
            </form>
        </div>
    </body>

    </html>
    <?php
    exit;
}

/* =========================
   POST handler
========================= */

$store = $_POST['store'] ?? '';
$orderIdsRaw = $_POST['order_ids'] ?? '';

if (!in_array($store, ['AllRenewed', 'RenovarTech'], true)) {
    die("Invalid store selected.");
}

// Parse order IDs: newline / comma / spaces
$orderIds = preg_split('/[\r\n,\s]+/', $orderIdsRaw);
$orderIds = array_values(array_filter(array_map('trim', $orderIds)));

if (empty($orderIds)) {
    die("No Amazon Order IDs provided.");
}

echo "<h3>Manual Import Running...</h3>";
echo "<b>Store:</b> {$store}<br>";
echo "<b>Count:</b> " . count($orderIds) . "<br><hr>";

/* =========================
   DB
========================= */
$db = dbDatabase();
if (!$db || $db->connect_error) {
    die("DB connection failed: " . ($db->connect_error ?? 'unknown'));
}

$platform = "Amazon";

/* =========================
   Auth
========================= */
$credentials = getAWSCredentials($db, $store);
$accessToken = fetchAccessToken($credentials);

// OPTIONAL RDT (only if you need buyerInfo/shippingAddress in some cases)
$rdt = null;
$rdtResponse = getRestrictedDataToken($credentials, 'us-east-1', $accessToken);
if (!empty($rdtResponse['restrictedDataToken'])) {
    $rdt = $rdtResponse['restrictedDataToken'];
}

foreach ($orderIds as $amazonOrderIdInput) {
    echo "<hr><h4>Importing: {$amazonOrderIdInput}</h4>";

    // Fetch order by ID
    $orderData = spapiGet($credentials, ($rdt ?: $accessToken), "/orders/v0/orders/" . rawurlencode($amazonOrderIdInput));

    if (empty($orderData['payload'])) {
        $msg = $orderData['errors'][0]['message'] ?? 'No payload returned';
        echo "❌ Order fetch failed: {$msg}<br>";
        continue;
    }

    $order = $orderData['payload'];

    // Map order fields (same as your current script)
    $AmazonOrderId = $db->real_escape_string($order['AmazonOrderId'] ?? '');
    $purchaseDate = isoToMysqlDatetime($order['PurchaseDate'] ?? '');
    $lastUpdateDate = isoToMysqlDatetime($order['LastUpdateDate'] ?? '');
    $orderStatus = $db->real_escape_string($order['OrderStatus'] ?? '');

    $fulfillmentChannel = $db->real_escape_string($order['FulfillmentChannel'] ?? '');
    // Normalize to your vocab
    if ($fulfillmentChannel === 'MFN')
        $fulfillmentChannel = 'FBM';
    else if ($fulfillmentChannel === 'AFN')
        $fulfillmentChannel = 'FBA';

    $itemsShipped = $db->real_escape_string($order['NumberOfItemsShipped'] ?? '');
    $itemsUnshipped = $db->real_escape_string($order['NumberOfItemsUnshipped'] ?? '');

    $AddressLine1 = $db->real_escape_string($order['ShippingAddress']['AddressLine1'] ?? '');
    $state = $db->real_escape_string($order['ShippingAddress']['StateOrRegion'] ?? '');
    $postalcode = $db->real_escape_string($order['ShippingAddress']['PostalCode'] ?? '');
    $city = $db->real_escape_string($order['ShippingAddress']['City'] ?? '');
    $countrycode = $db->real_escape_string($order['ShippingAddress']['CountryCode'] ?? '');

    $paymentMethod = $db->real_escape_string($order['PaymentMethod'] ?? '');
    $BuyerName = $db->real_escape_string($order['BuyerInfo']['BuyerName'] ?? '');
    $buyerEmail = $db->real_escape_string($order['BuyerInfo']['BuyerEmail'] ?? '');

    $earliestShipDate = isoToMysqlDatetime($order['EarliestShipDate'] ?? '');
    $latestShipDate = isoToMysqlDatetime($order['LatestShipDate'] ?? '');
    $earliestDeliveryDate = isoToMysqlDatetime($order['EarliestDeliveryDate'] ?? '');
    $latestDeliveryDate = isoToMysqlDatetime($order['LatestDeliveryDate'] ?? '');

    $ship_to_name = $db->real_escape_string($order['ShippingAddress']['Name'] ?? '');
    $shipmentservice = $db->real_escape_string($order['ShipmentServiceLevelCategory'] ?? '');
    $replacementOrder = $db->real_escape_string($order['IsReplacementOrder'] ?? '');
    $ordertype = $db->real_escape_string($order['OrderType'] ?? '');

    // Upsert tbloutboundorders
    upsertOutboundOrder(
        $db,
        $platform,
        $store,
        $AmazonOrderId,
        $AddressLine1,
        $state,
        $postalcode,
        $city,
        $countrycode,
        $paymentMethod,
        $BuyerName,
        $buyerEmail,
        $purchaseDate,
        $earliestShipDate,
        $latestShipDate,
        $earliestDeliveryDate,
        $latestDeliveryDate,
        $shipmentservice,
        $ordertype,
        $replacementOrder,
        $fulfillmentChannel,
        $itemsShipped,
        $itemsUnshipped,
        $ship_to_name
    );

    // Fetch order items
    $itemsData = spapiGet($credentials, $accessToken, "/orders/v0/orders/" . rawurlencode($AmazonOrderId) . "/orderItems");
    if (empty($itemsData['payload']['OrderItems'])) {
        $msg = $itemsData['errors'][0]['message'] ?? 'No OrderItems returned';
        echo "⚠️ OrderItems fetch issue: {$msg}<br>";
        continue;
    }

    // Upsert tbloutboundordersitem rows
    foreach ($itemsData['payload']['OrderItems'] as $item) {
        $sellerSKU = $db->real_escape_string($item['SellerSKU'] ?? '');
        $asin = $db->real_escape_string($item['ASIN'] ?? '');
        $title = $db->real_escape_string($item['Title'] ?? '');
        $conditionSubtypeId = $db->real_escape_string($item['ConditionSubtypeId'] ?? '');
        $conditionId = $db->real_escape_string($item['ConditionId'] ?? '');

        $QuantityOrdered = (int) ($item['QuantityOrdered'] ?? 0);
        $QuantityShipped = (int) ($item['QuantityShipped'] ?? 0);

        $itemprice = (float) ($item['ItemPrice']['Amount'] ?? 0);
        $itemtax = (float) ($item['ItemTax']['Amount'] ?? 0);
        $ShippingPrice = (float) ($item['ShippingPrice']['Amount'] ?? 0);

        $IsBuyerRequestedCancel = $db->real_escape_string($item['BuyerRequestedCancel']['IsBuyerRequestedCancel'] ?? '');
        $BuyerCancelReason = $db->real_escape_string($item['BuyerRequestedCancel']['BuyerCancelReason'] ?? '');
        $orderItemId = $db->real_escape_string($item['OrderItemId'] ?? '');

        upsertOutboundOrderItem(
            $db,
            $store,
            $platform,
            $AmazonOrderId,
            $orderItemId,
            $sellerSKU,
            $asin,
            $title,
            $conditionSubtypeId,
            $conditionId,
            $fulfillmentChannel,
            $orderStatus,
            $QuantityOrdered,
            $QuantityShipped,
            $itemprice,
            $itemtax,
            $ShippingPrice,
            $IsBuyerRequestedCancel,
            $BuyerCancelReason
        );

        // OPTIONAL: hook your existing tblshiphistory logic here
        // If you want, paste your InsertORUpdate_tblhistory() and call it here.
        // InsertORUpdate_tblhistory($edited);

    }

    echo "✅ Imported: {$AmazonOrderId} (items: " . count($itemsData['payload']['OrderItems']) . ")<br>";
}

echo "<hr><b>Done.</b>";

/* =========================================================
   FUNCTIONS
========================================================= */

function dbDatabase()
{
    $servertype = "laravel_ims";

    switch ($servertype) {
        case "laravel_ims":
            $hostname = 'localhost';
            $username = 'imsv2_dbims_user';
            $password = 'Imsv2_dbims_user';
            $database = 'imsv2_dbims';
            break;
        default:
            die("❌ Invalid server type.");
    }

    return new mysqli($hostname, $username, $password, $database);
}

function fetchAccessToken($credentials, $returnRaw = false)
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
    if ($response === FALSE)
        die('cURL Error: ' . curl_error($ch));
    curl_close($ch);

    $decoded = json_decode($response, true);
    return $returnRaw ? $decoded : ($decoded['access_token'] ?? false);
}

function getAWSCredentials($db, $store)
{
    if ($store === 'RenovarTech')
        $id = 6;
    else if ($store === 'AllRenewed')
        $id = 10;
    else
        die("Invalid store.");

    $sql = "SELECT client_id, client_secret, refresh_token FROM tblstores WHERE store_id = $id";
    $result = $db->query($sql);
    $row = $result ? $result->fetch_assoc() : null;

    if (!$row)
        die("No keys found for the selected store.");
    return $row;
}

/**
 * RDT is optional — keep your current function.
 * (Unchanged, copy from your script)
 */
function getRestrictedDataToken($credentials, $region, $accessToken)
{
    $endpoint = "https://sellingpartnerapi-na.amazon.com/tokens/2021-03-01/restrictedDataToken";
    $host = "sellingpartnerapi-na.amazon.com";
    $payload = json_encode([
        "restrictedResources" => [
            [
                "method" => "GET",
                "path" => "/orders/v0/orders",
                "dataElements" => ["buyerInfo", "shippingAddress"]
            ]
        ]
    ]);

    $amzDate = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');

    $canonicalUri = '/tokens/2021-03-01/restrictedDataToken';
    $canonicalHeaders = "content-type:application/json\nhost:$host\nx-amz-access-token:$accessToken\nx-amz-date:$amzDate\n";
    $signedHeaders = 'content-type;host;x-amz-access-token;x-amz-date';
    $payloadHash = hash('sha256', $payload);

    $canonicalRequest = "POST\n$canonicalUri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

    $algorithm = 'AWS4-HMAC-SHA256';
    $credentialScope = "$date/$region/execute-api/aws4_request";
    $stringToSign = "$algorithm\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

    $kSecret = 'AWS4' . $credentials['client_secret'];
    $kDate = hash_hmac('sha256', $date, $kSecret, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', 'execute-api', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $authorizationHeader = "$algorithm Credential={$credentials['client_id']}/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

    $headers = [
        "Content-Type: application/json",
        "Host: $host",
        "x-amz-access-token: $accessToken",
        "x-amz-date: $amzDate",
        "Authorization: $authorizationHeader"
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function spapiGet($credentials, $accessToken, $path, $queryString = '')
{
    $endpoint = "https://sellingpartnerapi-na.amazon.com";
    $url = $endpoint . $path . ($queryString ? ("?" . $queryString) : "");

    // Build signature for *this exact path/query*
    $headers = buildHeadersForPath($credentials, $accessToken, $path, $queryString);

    do {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $http_data = curl_getinfo($ch);
        curl_close($ch);

        echo "<pre>";
        print_r($http_data);
        echo "</pre>";

        if ($http == 429) {
            echo "<br>Rate limit hit (429). Sleeping 60 seconds...<br>";
            sleep(60);
        } else if ($http == 401) {
            echo "<br>401 Unauthorized. Refreshing token...<br>";
            $accessToken = fetchAccessToken($credentials);
            $headers = buildHeadersForPath($credentials, $accessToken, $path, $queryString);
        } else {
            break;
        }
    } while (true);

    $data = json_decode($result, true);

    echo "<pre>";
    print_r($data);
    echo "</pre>";
    return is_array($data) ? $data : [];
}

function buildHeadersForPath($credentials, $accessToken, $path, $queryString = '')
{
    $amzDate = gmdate('Ymd\THis\Z');

    $service = 'execute-api';
    $region = 'us-east-1';
    $method = 'GET';

    $canonicalUri = $path;
    $canonicalQueryString = $queryString;

    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com\nx-amz-date:{$amzDate}\n";
    $signedHeaders = 'host;x-amz-date';
    $payloadHash = hash('sha256', '');

    $canonicalRequest =
        "{$method}\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

    $algorithm = 'AWS4-HMAC-SHA256';
    $dateStamp = substr($amzDate, 0, 8);
    $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
    $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

    $signatureKey = getSignatureKey($credentials['client_secret'], $dateStamp, $region, $service);
    $signature = hash_hmac('sha256', $stringToSign, $signatureKey);

    $auth =
        "{$algorithm} Credential={$credentials['client_id']}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

    return [
        "x-amz-date: {$amzDate}",
        "x-amz-access-token: {$accessToken}",
        "Authorization: {$auth}"
    ];
}

function getSignatureKey($key, $dateStamp, $regionName, $serviceName)
{
    $kSecret = 'AWS4' . $key;
    $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
    $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
    $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    return $kSigning;
}

function isoToMysqlDatetime($isoString)
{
    if (empty($isoString))
        return null;
    $date = new DateTime($isoString, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('America/Los_Angeles'));
    return $date->format('Y-m-d H:i:s');
}

/* =========================
   DB UPSERTS
========================= */

function upsertOutboundOrder(
    $db,
    $platform,
    $store,
    $AmazonOrderId,
    $AddressLine1,
    $state,
    $postalcode,
    $city,
    $countrycode,
    $paymentMethod,
    $BuyerName,
    $buyerEmail,
    $purchaseDate,
    $earliestShipDate,
    $latestShipDate,
    $earliestDeliveryDate,
    $latestDeliveryDate,
    $shipmentservice,
    $ordertype,
    $replacementOrder,
    $fulfillmentChannel,
    $itemsShipped,
    $itemsUnshipped,
    $ship_to_name
) {
    $stmt = $db->prepare("SELECT platform_order_id FROM tbloutboundorders WHERE platform_order_id = ? AND platform = ? AND storename = ?");
    $stmt->bind_param("sss", $AmazonOrderId, $platform, $store);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $u = $db->prepare("UPDATE tbloutboundorders SET
            address_line1 = ?, StateOrRegion = ?, postal_code = ?, city = ?, CountryCode = ?,
            PaymentMethod = ?, BuyerName = ?, BuyerEmail = ?, PurchaseDate = ?, EarliestShipDate = ?,
            LatestShipDate = ?, EarliestDeliveryDate = ?, LatestDeliveryDate = ?, ShipmentServiceLevelCategory = ?,
            OrderType = ?, IsReplacementOrder = ?, FulfillmentChannel = ?, ShiptoName = ?,
            NumberOfItemsUnshipped = ?, NumberOfItemsShipped = ?
            WHERE platform_order_id = ? AND platform = ? AND storename = ?");

        $vals = [
            $AddressLine1,
            $state,
            $postalcode,
            $city,
            $countrycode,
            $paymentMethod,
            $BuyerName,
            $buyerEmail,
            $purchaseDate,
            $earliestShipDate,
            $latestShipDate,
            $earliestDeliveryDate,
            $latestDeliveryDate,
            $shipmentservice,
            $ordertype,
            $replacementOrder,
            $fulfillmentChannel,
            $ship_to_name,
            $itemsUnshipped,
            $itemsShipped,
            $AmazonOrderId,
            $platform,
            $store
        ];
        $u->bind_param('ssssssssssssssssssiisss', ...$vals);
        $u->execute();
        $u->close();
        echo "✅ tbloutboundorders updated<br>";
    } else {
        $i = $db->prepare("INSERT INTO tbloutboundorders (
            platform, storename, platform_order_id, address_line1, StateOrRegion, postal_code, city, CountryCode,
            PaymentMethod, BuyerName, BuyerEmail, PurchaseDate, EarliestShipDate, LatestShipDate,
            EarliestDeliveryDate, LatestDeliveryDate, ShipmentServiceLevelCategory, OrderType, IsReplacementOrder,
            FulfillmentChannel, NumberOfItemsShipped, NumberOfItemsUnshipped, ShiptoName
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $vals = [
            $platform,
            $store,
            $AmazonOrderId,
            $AddressLine1,
            $state,
            $postalcode,
            $city,
            $countrycode,
            $paymentMethod,
            $BuyerName,
            $buyerEmail,
            $purchaseDate,
            $earliestShipDate,
            $latestShipDate,
            $earliestDeliveryDate,
            $latestDeliveryDate,
            $shipmentservice,
            $ordertype,
            $replacementOrder,
            $fulfillmentChannel,
            $itemsShipped,
            $itemsUnshipped,
            $ship_to_name
        ];
        $i->bind_param(str_repeat("s", count($vals)), ...$vals);
        $i->execute();
        $i->close();
        echo "✅ tbloutboundorders inserted<br>";
    }

    $stmt->close();
}

function upsertOutboundOrderItem(
    $db,
    $store,
    $platform,
    $AmazonOrderId,
    $orderItemId,
    $sellerSKU,
    $asin,
    $title,
    $conditionSubtypeId,
    $conditionId,
    $fulfillmentChannel,
    $orderStatus,
    $QuantityOrdered,
    $QuantityShipped,
    $itemprice,
    $itemtax,
    $ShippingPrice,
    $IsBuyerRequestedCancel,
    $BuyerCancelReason
) {
    $chk = $db->prepare("SELECT platform_order_id FROM tbloutboundordersitem WHERE platform_order_id = ? AND platform_order_item_id = ? AND platform = ?");
    $chk->bind_param("sss", $AmazonOrderId, $orderItemId, $platform);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows > 0) {
        $u = $db->prepare("UPDATE tbloutboundordersitem SET
            storename = ?,
            platform_sku = ?, platform_asin = ?, platform_title = ?,
            ConditionSubtypeId = ?, ConditionId = ?,
            FulfillmentChannel = ?, order_status = ?,
            QuantityOrdered = ?, QuantityShipped = ?,
            unit_price = ?, unit_tax = ?, ShippingPrice = ?,
            IsBuyerRequestedCancel = ?, BuyerCancelReason = ?
        WHERE platform_order_id = ? AND platform_order_item_id = ? AND platform = ?");

        $vals = [
            $store,
            $sellerSKU,
            $asin,
            $title,
            $conditionSubtypeId,
            $conditionId,
            $fulfillmentChannel,
            $orderStatus,
            (int) $QuantityOrdered,
            (int) $QuantityShipped,
            (float) $itemprice,
            (float) $itemtax,
            (float) $ShippingPrice,
            $IsBuyerRequestedCancel,
            $BuyerCancelReason,
            $AmazonOrderId,
            $orderItemId,
            $platform
        ];

        // types: ssssssssii ddd sss sss (match vals count)
        $u->bind_param('ssssssssiidddssssss', ...$vals);
        $u->execute();
        $u->close();
        echo "✅ tbloutboundordersitem updated: {$orderItemId}<br>";
    } else {
        $i = $db->prepare("INSERT INTO tbloutboundordersitem (
            storename, platform, platform_order_id, platform_order_item_id,
            platform_sku, platform_asin, platform_title,
            ConditionSubtypeId, ConditionId,
            FulfillmentChannel, order_status,
            QuantityOrdered, QuantityShipped,
            unit_price, unit_tax, shippingPrice,
            IsBuyerRequestedCancel, BuyerCancelReason
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $vals = [
            $store,
            $platform,
            $AmazonOrderId,
            $orderItemId,
            $sellerSKU,
            $asin,
            $title,
            $conditionSubtypeId,
            $conditionId,
            $fulfillmentChannel,
            $orderStatus,
            (int) $QuantityOrdered,
            (int) $QuantityShipped,
            (float) $itemprice,
            (float) $itemtax,
            (float) $ShippingPrice,
            $IsBuyerRequestedCancel,
            $BuyerCancelReason
        ];

        $i->bind_param('sssssssssssiidddss', ...$vals);
        $i->execute();
        $i->close();
        echo "✅ tbloutboundordersitem inserted: {$orderItemId}<br>";
    }

    $chk->close();
}
