<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);
ini_set('max_execution_time', 600);
session_start();
date_default_timezone_set('America/Los_Angeles');

echo "Current directory: " . __DIR__ . "<br>";

echo "Working directory: " . getcwd() . "<br>";


// === DB CONFIG ===
$mysqli = new mysqli("localhost", "u298641722_dbims_user", "?cIk=|zRk3T", "u298641722_dbims");

if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error . "<br>");
}


// =====================================
// MAIN ENTRY POINT (Call the Cron Flow)
// =====================================
fetchOrdersCron();



// === UTILITY REPLACEMENTS ===
function now()
{
    return date('Y-m-d H:i:s');
}

function env($key, $default = null)
{
    return getenv($key) ?: $default;
}

function db_query($query, $bind = [])
{
    global $mysqli;
    $stmt = $mysqli->prepare($query);
    if ($bind) {
        $types = str_repeat("s", count($bind));
        $stmt->bind_param($types, ...$bind);
    }
    $stmt->execute();
    return $stmt;
}

function db_fetch_assoc($query, $bind = [])
{
    $stmt = db_query($query, $bind);
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function db_fetch_all($query, $bind = [])
{
    $stmt = db_query($query, $bind);
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetchOrdersCron()
{
    $serverconfig = env('EBAY_SERVER_CONFIG', 'LIVE');
    $pageNumber = 1;

    $credentials = EbayCredentials();
    if (!$credentials || empty($credentials['access_token'])) {
        echo "❌ Failed to retrieve a valid access token.<br>";
        return;
    }

    $accessToken = $credentials['access_token'];

    try {
        $response = sendEbayRequest($accessToken, $pageNumber);

        if (!$response) {
            echo "⚠️ Raw eBay API Response: Successful Ebay Request!<br>";
            // print_r($response);
            echo "<br>❌ Failed to retrieve orders.<br>";
            return;
        }

        if (!empty($response['Errors'])) {
            handleEbayErrors($response['Errors'], $serverconfig, $credentials);
            return;
        }

        $processedOrders = processOrders($response, $accessToken);
        insertOrUpdate($processedOrders);

        echo "✅ Orders fetched and processed successfully.<br>";
        echo "<pre>";
        print_r($processedOrders);
        echo "</pre>";

    } catch (Exception $e) {
        echo "❌ Exception in fetchOrders: " . $e->getMessage() . "<br>";
    }
}

// === MAIN AFUNCTION ===
function sendEbayRequest($accessToken, $pageNumber)
{
    $createTimeFrom = (new DateTime('-10 days', new DateTimeZone('UTC')))->format(DATE_ATOM);
    $createTimeTo = (new DateTime('now', new DateTimeZone('UTC')))->format(DATE_ATOM);

    $requestBody = '<?xml version="1.0" encoding="utf-8"?>
    <GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <RequesterCredentials>
            <eBayAuthToken>' . $accessToken . '</eBayAuthToken>
        </RequesterCredentials>
        <CreateTimeFrom>' . $createTimeFrom . '</CreateTimeFrom>
        <CreateTimeTo>' . $createTimeTo . '</CreateTimeTo>
        <OrderRole>Buyer</OrderRole>
        <DetailLevel>ReturnAll</DetailLevel>
        <Pagination>
            <EntriesPerPage>100</EntriesPerPage>
            <PageNumber>' . $pageNumber . '</PageNumber>
        </Pagination>
        <OutputSelector>OrderArray.Order.OrderID</OutputSelector>
        <OutputSelector>OrderArray.Order.OrderStatus</OutputSelector>
        <OutputSelector>OrderArray.Order.PaidTime</OutputSelector>
        <OutputSelector>OrderArray.Order.AmountPaid</OutputSelector>
        <OutputSelector>OrderArray.Order.CreatedTime</OutputSelector>
        <OutputSelector>OrderArray.Order.ShippingServiceSelected.ShippingServiceCost</OutputSelector>
        <OutputSelector>OrderArray.Order.Subtotal</OutputSelector>
        <OutputSelector>OrderArray.Order.Total</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.Taxes.TaxDetails.TaxAmount</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.TransactionID</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.Item.ItemID</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.Item.Title</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.QuantityPurchased</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.SellerDiscounts.SellerDiscount.ItemDiscountAmount</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.TransactionPrice</OutputSelector>
        <OutputSelector>OrderArray.Order.ShippedTime</OutputSelector>
        <OutputSelector>OrderArray.Order.SellerUserID</OutputSelector>
        <OutputSelector>OrderArray.Order.SellerEmail</OutputSelector>
        <OutputSelector>OrderArray.Order.Seller.RegistrationAddress</OutputSelector>
        <OutputSelector>OrderArray.Order.ShippingAddress</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.ShippingDetails.ShipmentTrackingDetails.ShipmentTrackingNumber</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.ShippingDetails.ShipmentTrackingDetails.ShippingCarrierUsed</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.Item.ConditionDisplayName</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.ShippingServiceSelected.ShippingPackageInfo.EstimatedDeliveryTimeMax</OutputSelector>
        <OutputSelector>OrderArray.Order.CheckoutStatus.PaymentMethod</OutputSelector>
    </GetOrdersRequest>';

    return sendRequest($requestBody, 'GetOrders');
}

function handleEbayErrors($errors, $serverconfig, $credentials)
{
    foreach ($errors as $error) {
        if (!is_array($error)) {
            echo "⚠️ Unexpected error format:<br>";
            print_r($error);
            echo "<br>";
            continue;
        }

        if (isset($error['ErrorCode']) && $error['ErrorCode'] == '931') {
            echo "❌ Invalid eBay auth token.<br>";

            if ($serverconfig === 'LIVE') {
                echo "🔄 Refreshing token...<br>";
                $newAccessToken = refreshEbayAccessToken($credentials);

                if (!$newAccessToken) {
                    echo "❌ Refresh failed.<br>";
                    return;
                }

                echo "✅ Token refreshed. Retrying fetchOrdersCron...<br>";
                fetchOrdersCron(); // retry
                return;
            }

            echo "⚠️ Invalid token (not LIVE mode).<br>";
            return;
        }

        if (isset($error['ErrorCode']) && $error['ErrorCode'] == '932') {
            echo "❌ Auth token hard expired. Please reauthorize.<br>";
            return;
        }
    }
}

function sendRequest($requestBody, $apiCallName)
{
    $apiEndpoint = 'https://api.ebay.com/ws/api.dll';

    $apiHeaders = [
        'X-EBAY-API-SITEID: 0',
        'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
        'X-EBAY-API-CALL-NAME: ' . $apiCallName,
        'Content-Type: text/xml',
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $apiEndpoint);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $apiHeaders);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) {
        echo 'cURL Error: ' . $error . '<br>';
        return null;
    }

    echo "📦 Raw XML Response for API Call: $apiCallName<br>";

    $xml = simplexml_load_string($response);
    if (!$xml) {
        echo '❌ Invalid XML Response from eBay<br>';
        return null;
    }

    return json_decode(json_encode($xml), true);
}

function processOrders($response, $accessToken)
{
    if (empty($response['OrderArray']['Order'])) {
        echo "ℹ️ No orders found in response.<br>";
        return [];
    }

    $orders = $response['OrderArray']['Order'];
    echo "<br>📝 Order Primary<br><pre>";
    print_r($response['OrderArray']);
    echo "</pre>";

    $processedOrders = [];
    $exchangeRates = fetchExchangeRates('f5d29ab775a644eca3f13e4c'); // define this constant globally

    foreach ($orders as $order) {
        $currency = $order['AmountPaid']['@currencyID'] ?? 'USD';
        $ebayorderid = $order['OrderID'] ?? null;
        $amountPaid = $order['AmountPaid'] ?? 0;
        $amountPaidInUSD = convertToUSD($amountPaid, $currency, $exchangeRates);

        $preshippingServiceCost = $order['ShippingServiceSelected']['ShippingServiceCost'] ?? 0;
        $deliveredDate = $order['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMax'] ?? 0;
        $shipping_currency = $currency;
        $shippingServiceCost = convertToUSD($preshippingServiceCost, $shipping_currency, $exchangeRates);

        $trackingNumber1 = $trackingNumber2 = $trackingNumber3 = $trackingNumber4 = $trackingNumber5 = '';
        $shippingCarrier = '';
        $items = [];

        // Tracking Details
        if (isset($order['TransactionArray']['Transaction']['ShippingDetails']['ShipmentTrackingDetails'])) {
            $trackingDetails = $order['TransactionArray']['Transaction']['ShippingDetails']['ShipmentTrackingDetails'];
            $isArray = isset($trackingDetails[0]);

            for ($i = 0; $i <= 4; $i++) {
                $numField = 'trackingNumber' . ($i + 1);
                if ($isArray && isset($trackingDetails[$i]['ShipmentTrackingNumber'])) {
                    ${$numField} = $trackingDetails[$i]['ShipmentTrackingNumber'];
                } elseif (!$isArray && $i === 0 && isset($trackingDetails['ShipmentTrackingNumber'])) {
                    $trackingNumber1 = $trackingDetails['ShipmentTrackingNumber'];
                }
            }

            if ($isArray && isset($trackingDetails[0]['ShippingCarrierUsed'])) {
                $shippingCarrier = $trackingDetails[0]['ShippingCarrierUsed'];
            } elseif (!$isArray && isset($trackingDetails['ShippingCarrierUsed'])) {
                $shippingCarrier = $trackingDetails['ShippingCarrierUsed'];
            }
        }

        // Transactions
        if (!empty($order['TransactionArray']['Transaction'])) {
            $transactions = $order['TransactionArray']['Transaction'];
            if (!isset($transactions[0]))
                $transactions = [$transactions];

            foreach ($transactions as $transaction) {
                if (!is_array($transaction) || !isset($transaction['Item'])) {
                    echo "⚠️ Invalid transaction structure.<br>";
                    continue;
                }

                $itemId = $transaction['Item']['ItemID'] ?? null;
                if (!$itemId) {
                    echo "⚠️ Missing ItemID in Transaction.<br>";
                    continue;
                }

                $itemDetails = fetchItemDetails($itemId, $accessToken);
                $locationDetails = getItemLocation($itemId, $accessToken);

                echo "<br>🛍️ Item Info of " . $order['OrderID'] . "<br><pre>";
                print_r($itemDetails);
                echo "</pre>";

                echo "<br>📍 Location Details<br><pre>";
                print_r($locationDetails);
                echo "</pre>";

                $items[] = [
                    'transaction_id' => $transaction['TransactionID'] ?? null,
                    'item_id' => $itemId,
                    'title' => $transaction['Item']['Title'] ?? null,
                    'quantity_purchased' => $transaction['QuantityPurchased'] ?? null,
                    'item_details' => $itemDetails
                ];
            }
        }

        $processedOrder = [
            'order_id' => $order['OrderID'] ?? null,
            'order_status' => $order['OrderStatus'] ?? null,
            'paid_time' => $order['PaidTime'] ?? null,
            'amount_paid' => $amountPaidInUSD,
            'created_time' => $order['CreatedTime'] ?? null,
            'shipping_cost' => $shippingServiceCost,
            'subtotal' => $order['Subtotal'] ?? null,
            'total' => $order['Total'] ?? null,
            'seller_user_id' => $order['SellerUserID'] ?? null,
            'seller_email' => $order['SellerEmail'] ?? null,
            'shipped_time' => $order['ShippedTime'] ?? null,
            'shipping_address' => isset($order['ShippingAddress']) ? json_encode($order['ShippingAddress']) : null,
            'tracking_number1' => $trackingNumber1,
            'tracking_number2' => $trackingNumber2,
            'tracking_number3' => $trackingNumber3,
            'tracking_number4' => $trackingNumber4,
            'tracking_number5' => $trackingNumber5,
            'shipping_carrier' => $shippingCarrier,
            'items' => $items,
            'locationdetails' => $locationDetails,
            'estimatedDeliveryTime' => $deliveredDate,
        ];

        echo "<br>📦 Processed Order:<br><pre>";
        print_r($processedOrder);
        echo "</pre>";

        $processedOrders[] = $processedOrder;
    }

    echo "<br>📊 All Processed Orders:<br><pre>";
    print_r($processedOrders);
    echo "</pre>";

    return $processedOrders;
}

function insertOrUpdate($processedOrders)
{
    global $mysqli;

    foreach ($processedOrders as $order) {
        if ($order['order_status'] !== 'Completed')
            continue;

        $orderID = $order['order_id'];
        $createdTime = $order['created_time'] ? date('Y-m-d H:i:s', strtotime($order['created_time'])) : null;
        $shippedTime = $order['shipped_time'] ? date('Y-m-d H:i:s', strtotime($order['shipped_time'])) : null;
        $paymentDate = $order['paid_time'] ? date('Y-m-d H:i:s', strtotime($order['paid_time'])) : null;
        $DeliverDate = isset($order['estimatedDeliveryTime']) ? date('Y-m-d H:i:s', strtotime($order['estimatedDeliveryTime'])) : null;
        $total = $order['total'] ?? 0.00;
        $sellerName = $order['seller_user_id'];
        $moduleLoc = 'Orders';
        $fetchStatus = 'Pending';

        foreach ($order['items'] as $item) {
            $itemID = $item['item_id'];
            $originalTitle = $item['title'];
            $title = cleanTitle($originalTitle);

            $quantityPurchased = $item['quantity_purchased'];
            $transactionPrice = $item['item_details']['Item']['SellingStatus']['CurrentPrice'] ?? 0.00;
            $conditionDisplay = $item['item_details']['Item']['ConditionDisplayName'] ?? 'Unknown';
            $materialType = 'Default';
            $trackingNumber1 = $order['tracking_number1'] ?? null;
            $trackingNumber2 = $order['tracking_number2'] ?? null;
            $trackingNumber3 = $order['tracking_number3'] ?? null;
            $trackingNumber4 = $order['tracking_number4'] ?? null;
            $shippingCarrierUsed = $order['shipping_carrier'] ?? null;
            $PaymentMethod = $order['payment_method'] ?? 'eBay';
            $itemStatus = $order['item_status'] ?? null;
            $appliedCondition = $order['condition_status_applied'] ?? 'Applied';
            $tax = 0.00;
            $DiscountedPrice = 0.00;
            $shippingPrice = $order['shipping_cost'] ?? 0.00;
            $sellerNotes = '';
            $locationdetails = $order['locationdetails'];

            $itemDescription = '';
            if (!empty($item['item_details']['Item']['Description'])) {
                $itemDescription = strip_tags($item['item_details']['Item']['Description']);
                $itemDescription = str_replace(["'", '"', "\n", "\r"], "", $itemDescription);
                $itemDescription = trim($itemDescription);
            }

            if (isset($item['item_details']['Item']['ConditionDescription'])) {
                $sellerNotes = str_replace(["'", '"'], "", $item['item_details']['Item']['ConditionDescription']);
            }

            // Check keywords
            $keywords = db_fetch_all("SELECT descriptionStatus FROM tblItemstatus");
            $descWords = strtolower($title . ' ' . substr($itemDescription, 0, (int) (strlen($itemDescription) * 0.8)));
            foreach ($keywords as $row) {
                $keyword = strtolower($row['descriptionStatus']);
                if (strpos($descWords, $keyword) !== false) {
                    $itemStatus = 'Not Working';
                    $appliedCondition = (strlen($itemDescription) >= 150) ? '80% applied' : '80% not applied';
                    break;
                }
            }

            // Check existing
            $check = db_fetch_assoc("SELECT ProductID FROM tblproduct WHERE rtid=? AND itemnumber=? AND ProductModuleLoc=?", [$orderID, $itemID, $moduleLoc]);

            if ($check) {
                $productID = $check['ProductID'];
                db_query(
                    "UPDATE tblproduct SET ProductTitle=?, orderdate=?, trackingnumber=?, trackingnumber2=?, trackingnumber3=?, trackingnumber4=?, carrier=?, listedcondition=?, seller=?, shipdate=?, paymentdate=?, paymentmethod=?, itemstatus=?, conditionStatusApplied=?, datedelivered=?, total=?, quantity=?, price=?, Discount=?, priceshipping=?, tax=?, Ebay_seller_location=? WHERE ProductID=?",
                    [$title, $createdTime, $trackingNumber1, $trackingNumber2, $trackingNumber3, $trackingNumber4, $shippingCarrierUsed, $conditionDisplay, $sellerName, $shippedTime, $paymentDate, $PaymentMethod, $itemStatus, $appliedCondition, $DeliverDate, $total, $quantityPurchased, $transactionPrice, $DiscountedPrice, $shippingPrice, $tax, $locationdetails, $productID]
                );

                echo "🔁 Updated Order ID: $orderID (Item ID: $itemID) - ProductID: $productID<br>";
            } else {
                $rtcounter = fetchRtCounter();
                db_query("INSERT INTO tblproduct (rtid, itemnumber, ProductTitle, orderdate, total, quantity, price, Discount, priceshipping, tax, trackingnumber, trackingnumber2, trackingnumber3, trackingnumber4, carrier, listedcondition, seller, shipdate, paymentdate, rtcounter, description, notes, paymentmethod, datedelivered, itemstatus, conditionStatusApplied, fetchStatus, ProductModuleLoc, materialtype, validation, Ebay_seller_location)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?)",
                    [$orderID, $itemID, $title, $createdTime, $total, $quantityPurchased, $transactionPrice, $DiscountedPrice, $shippingPrice, $tax, $trackingNumber1, $trackingNumber2, $trackingNumber3, $trackingNumber4, $shippingCarrierUsed, $conditionDisplay, $sellerName, $shippedTime, $paymentDate, $rtcounter, $itemDescription, $sellerNotes, $PaymentMethod, $DeliverDate, $itemStatus, $appliedCondition, $fetchStatus, $moduleLoc, $materialType, $locationdetails]
                );

                $productID = $mysqli->insert_id;
                echo "✅ Inserted Order ID: $orderID (Item ID: $itemID) - ProductID: $productID<br>";
            }

            if (isset($item['item_details']['Item']['PictureDetails']['PictureURL'])) {
                saveEbayImages($productID, $item['item_details']['Item']['PictureDetails']['PictureURL']);
            }
        }
    }
}

function saveEbayImages($productID, $imageUrls)
{
    global $mysqli;

    if (!is_array($imageUrls)) {
        $imageUrls = [$imageUrls];
    }

    $imageUrls = array_slice($imageUrls, 0, 5); // Limit to 5

    $imageDir = '/home/u298641722/domains/tecniquality.com/public_html/laravel_ims/public/images/thumbnails';
    if (!file_exists($imageDir)) {
        mkdir($imageDir, 0755, true);
    }

    foreach ($imageUrls as $index => $imageUrl) {
        $imageName = $productID . ($index > 0 ? "_$index" : "") . ".jpg";
        $imagePath = $imageDir . '/' . $imageName;

        $context = stream_context_create(['http' => ['timeout' => 10]]);
        $imageData = @file_get_contents($imageUrl, false, $context);

        if ($imageData && file_put_contents($imagePath, $imageData)) {
            $imgField = "img" . ($index + 1);
            $stmt = $mysqli->prepare("UPDATE tblproduct SET $imgField = ? WHERE ProductID = ?");
            $stmt->bind_param("si", $imageName, $productID);
            $stmt->execute();
            $stmt->close();

            echo "📷 Saved image $imageName for ProductID: $productID<br>";
        } else {
            echo "⚠️ Failed to save image from: $imageUrl<br>";
        }
    }
}

function fetchItemDetails($itemId, $accessToken)
{
    if (!$itemId) {
        echo "❌ fetchItemDetails: Item ID is missing.<br>";
        return null;
    }

    $requestBody = '<?xml version="1.0" encoding="utf-8"?>
    <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <RequesterCredentials>
            <eBayAuthToken>' . $accessToken . '</eBayAuthToken>
        </RequesterCredentials>
        <ItemID>' . $itemId . '</ItemID>
        <DetailLevel>ReturnAll</DetailLevel>
    </GetItemRequest>';

    $response = sendRequest($requestBody, 'GetItem');

    if (!$response) {
        echo "❌ No response from eBay for Item ID: $itemId<br>";
        return null;
    }

    return $response;
}

function getItemLocation($itemId, $accessToken)
{
    if (!$itemId) {
        echo "❌ getItemLocation: Item ID is missing.<br>";
        return "N/A";
    }

    $requestBody = '<?xml version="1.0" encoding="utf-8"?>
    <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <RequesterCredentials>
            <eBayAuthToken>' . $accessToken . '</eBayAuthToken>
        </RequesterCredentials>
        <ItemID>' . $itemId . '</ItemID>
        <DetailLevel>ReturnAll</DetailLevel>
    </GetItemRequest>';

    $response = sendRequest($requestBody, 'GetItem');

    if (!$response || !isset($response['Item']['Location'])) {
        echo "⚠️ getItemLocation: Could not retrieve location for item ID: $itemId<br>";
        return "N/A";
    }

    $itemLocation = $response['Item']['Location'] ?? '';
    $itemCountry = $response['Item']['Country'] ?? '';

    return $itemCountry && stripos($itemLocation, $itemCountry) === false
        ? "$itemLocation, $itemCountry"
        : $itemLocation;
}

function fetchExchangeRates($apiKey)
{
    $url = "https://v6.exchangerate-api.com/v6/$apiKey/latest/USD";
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if ($data && isset($data['conversion_rates'])) {
        return $data['conversion_rates'];
    } else {
        echo "❌ Error fetching exchange rates.<br>";
        return [];
    }
}

function convertToUSD($amount, $currency, $exchangeRates)
{
    if ($currency === 'USD') {
        return number_format($amount, 2, '.', '');
    }

    if (isset($exchangeRates[$currency])) {
        return number_format($amount / $exchangeRates[$currency], 2, '.', '');
    }

    echo "⚠️ Missing exchange rate for $currency<br>";
    return $amount;
}

function cleanTitle($text)
{
    $pattern = '/[\x{1F600}-\x{1F64F}|\x{1F300}-\x{1F5FF}|\x{1F680}-\x{1F6FF}|\x{1F700}-\x{1F77F}|\x{1F780}-\x{1F7FF}|\x{1F800}-\x{1F8FF}|\x{1F900}-\x{1F9FF}|\x{1FA00}-\x{1FA6F}|\x{1FA70}-\x{1FAFF}|\x{2600}-\x{26FF}|\x{2700}-\x{27BF}]/u';
    $cleanText = preg_replace($pattern, '', $text);
    $cleanText = preg_replace('/[⭐🔥!]/u', '', $cleanText);
    $cleanText = preg_replace('/\s+/', ' ', $cleanText);
    return trim($cleanText);
}

function fetchRtCounter()
{
    $row = db_fetch_assoc("SELECT MAX(rtcounter) as maxval FROM tblproduct");
    return $row && $row['maxval'] ? $row['maxval'] + 1 : 1;
}




//// === Supporting Functions ===

function EbayCredentials()
{
    global $mysqli;

    $id = 3;
    $stmt = $mysqli->prepare("SELECT client_id, client_secret, access_token, refresh_token, expires_in FROM tblapis WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        echo "❌ No eBay credentials found for ID: $id<br>";
        return [];
    }

    $credentials = $result->fetch_assoc();
    $stmt->close();
    return $credentials;
}

function getAccessToken($authorizationCode)
{
    $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';
    $redirectUri = 'https://test.tecniquality.com/apis/ebay-callback';

    $credentials = EbayCredentials();
    if (!$credentials) {
        echo "❌ Failed to retrieve credentials for token request.<br>";
        return null;
    }

    $authHeader = base64_encode("{$credentials['client_id']}:{$credentials['client_secret']}");
    $data = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $authorizationCode,
        'redirect_uri' => $redirectUri,
    ]);

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Basic $authHeader\r\nContent-Type: application/x-www-form-urlencoded",
            'content' => $data,
            'timeout' => 10,
        ]
    ];

    $context = stream_context_create($opts);
    $response = file_get_contents($tokenUrl, false, $context);

    if ($response === false) {
        echo "❌ Error during token request.<br>";
        return null;
    }

    $results = json_decode($response, true);
    if (isset($results['access_token'], $results['refresh_token'])) {
        saveTokens($results);
        return $results['access_token'];
    } else {
        echo "❌ Failed to obtain token:<br>";
        print_r($results);
        echo "<br>";
        return null;
    }
}

function saveTokens(array $tokens)
{
    global $mysqli;

    $stmt = $mysqli->prepare("UPDATE tblapis SET access_token=?, refresh_token=?, expires_in=?, updated_at=? WHERE id=3");
    $now = date('Y-m-d H:i:s');
    $stmt->bind_param("ssis", $tokens['access_token'], $tokens['refresh_token'], $tokens['expires_in'], $now);

    if ($stmt->execute()) {
        echo "✅ Tokens saved to DB.<br>";
    } else {
        echo "❌ Failed to save tokens: " . $stmt->error . "<br>";
    }

    $stmt->close();
}

function refreshEbayAccessToken($credentials)
{
    global $mysqli;

    $stmt = $mysqli->prepare("SELECT refresh_token FROM tblapis WHERE api_name = 'EBAY'");
    $stmt->execute();
    $result = $stmt->get_result();
    $apiRecord = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$apiRecord || !$apiRecord['refresh_token']) {
        echo "❌ No refresh token found for EBAY.<br>";
        return null;
    }

    $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';
    $authHeader = base64_encode("{$credentials['client_id']}:{$credentials['client_secret']}");

    $data = http_build_query([
        'grant_type' => 'refresh_token',
        'refresh_token' => $apiRecord['refresh_token'],
        'scope' => implode(' ', [
            'https://api.ebay.com/oauth/api_scope',
            'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.account.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly',
        ])
    ]);

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Basic $authHeader\r\nContent-Type: application/x-www-form-urlencoded",
            'content' => $data,
            'timeout' => 10,
        ]
    ];

    $context = stream_context_create($opts);
    $response = file_get_contents($tokenUrl, false, $context);

    if ($response === false) {
        echo "❌ Failed to contact eBay token server.<br>";
        return null;
    }

    $results = json_decode($response, true);
    if (isset($results['access_token'])) {
        $newAccessToken = $results['access_token'];
        $expiresIn = $results['expires_in'] ?? 3600;
        $refreshTokenExpiresIn = $results['refresh_token_expires_in'] ?? '';

        $stmt = $mysqli->prepare("UPDATE tblapis SET access_token = ?, updated_at = ? WHERE api_name = 'EBAY'");
        $now = date('Y-m-d H:i:s');
        $stmt->bind_param("ss", $newAccessToken, $now);
        $stmt->execute();
        $stmt->close();

        $filePath = "/home/u298641722/public_html/ims/Admin/modules/orders/tokens.json";
        $jsonData = json_encode([
            'access_token' => $newAccessToken,
            'expires_in' => $expiresIn,
            'refresh_token' => $apiRecord['refresh_token'],
            'refresh_token_expires_in' => $refreshTokenExpiresIn,
            'token_type' => 'User Access Token',
            'expiration_time' => time() + $expiresIn,
        ], JSON_PRETTY_PRINT);

        if (file_put_contents($filePath, $jsonData) !== false) {
            echo "✅ Tokens saved to file.<br>";
        } else {
            echo "❌ Failed to write tokens.json<br>";
        }

        return $newAccessToken;
    } else {
        echo "❌ Token refresh failed:<br>";
        print_r($results);
        echo "<br>";
        return null;
    }
}