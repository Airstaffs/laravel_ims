<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

$servertype = "vps";
$Connect = connectDatabase($servertype);
$conn = $Connect;
$credentials = getAWSCredentials($Connect);
$accessToken = fetchAccessToken($credentials);

if (!isset($credentials['client_id']) || !isset($credentials['client_secret'])) {
    die("Invalid keys in database.");
}

$inputDate = $_POST['date'] ?? "2024-05-01";
$formattedDate = formatDateToISO8601($inputDate);

$currentDate = date('Y-m-d H:i:s');
$randomDays = rand(5, 20);
$previousDate = date('Y-m-d H:i:s', strtotime("-$randomDays days", strtotime($currentDate)));
$iso8601Date = date('c', strtotime($previousDate));
$end_sheesh = date('Y-m-d H:i:s', strtotime($currentDate));
$endDate = date('Y-m-d\TH:i:s\Z', strtotime($end_sheesh));

$json = [
    "reportType" => "GET_XML_RETURNS_DATA_BY_RETURN_DATE",
    "dataStartTime" => $iso8601Date,
    "dataEndTime" => $endDate,
    "marketplaceIds" => ["ATVPDKIKX0DER"]
];

echo "<br>DATA REQUEST<br><pre>";
print_r($json);
echo "</pre>";

$path = "/reports/2021-06-30/reports";
$jsonbody = json_encode($json);
$data_id = fetch_id($credentials, $accessToken, $jsonbody, $nextToken = null, $path);

echo "<pre>";
print_r($data_id);
echo "</pre>";
$httpcode = $data_id['httpcode'];
ErrorChecker($Connect, $data_id);

do {
    $reportId = $data_id['reportId'];
    $path = "/reports/2021-06-30/reports/{$reportId}";
    echo "Path: " . $path . "<br>";
    $status = fetchdetailsID($credentials, $accessToken, $nextToken = null, $path);
    echo "<pre>";
    print_r($status);
    echo "</pre>";
} while ($status['processingStatus'] == 'IN_QUEUE' || $status['processingStatus'] == 'IN_PROGRESS');

if ($status['processingStatus'] == 'DONE') {
    echo "<br><br>Status<br><br><pre>";
    print_r($status);
    echo "</pre>";

    $jsonbody = "";

    if ($status['reportDocumentId'] && !empty($status['reportDocumentId'])) {
        $documentid = $status['reportDocumentId'];
        $path = "/reports/2021-06-30/documents/{$documentid}";

        $restrictedResources = [['method' => 'GET', 'path' => $path]];

        $rdtResponse = fetchRestrictedDataToken($accessToken, $restrictedResources);
        echo "<br>RDT RESPONSE<br>";
        print_r($rdtResponse);
    }

    $data1 = fetchSuccessDetails($credentials, $rdtResponse, $accessToken, $jsonbody, $nextToken = null, $path);
    $compressionAlgorithm = $data1['compressionAlgorithm'] ?? "";
    $url = $data1['url'];
    $retrievedData = download($url, $compressionAlgorithm);
    $response = processRetrievedData($Connect, $retrievedData);

    // Pre-fetch ONE RDT token covering all order return paths
    $orderIds = array_column($response['return_details'], 'order_id');
    $restrictedResources = array_map(fn($id) => [
        'method' => 'GET',
        'path'   => "/orders/v0/orders/{$id}/returns"
    ], $orderIds);

    $rdtBulk = fetchRestrictedDataToken($accessToken, $restrictedResources);
    $rdtBulkToken = $rdtBulk['restrictedDataToken'] ?? null;

    if (!$rdtBulkToken) {
        echo "<br>Bulk RDT failed: " . ($rdtBulk['errors'][0]['message'] ?? 'unknown') . "<br>";
        echo "<br>Make sure your SP-API app has Orders > Buyer Info permission enabled in Amazon Developer Console.<br>";
    }

    insertToDb($Connect, $response, $credentials, $accessToken, $rdtBulkToken);

} else if ($status['processingStatus'] == 'CANCELLED') {
    echo "<br> CANCELLED!";
} else if ($status['processingStatus'] == 'FATAL') {
    echo "<br> FATAL!";
}


// ================================================================
// FUNCTIONS
// ================================================================

function fetchBuyerComment($credentials, $accessToken, $amazonOrderId, $rdtToken = null) {
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $path = "/orders/v0/orders/{$amazonOrderId}/returns";
    $service = 'execute-api';
    $region = 'us-east-1';
    $method = 'GET';

    // If no shared RDT token passed, try to get one
    if (!$rdtToken) {
        $rdtResponse = fetchRestrictedDataToken($accessToken, [['method' => 'GET', 'path' => $path]]);
        if (isset($rdtResponse['errors'])) {
            echo "<br>RDT Error for $amazonOrderId: " . $rdtResponse['errors'][0]['message'] . "<br>";
            return NULL;
        }
        $rdtToken = $rdtResponse['restrictedDataToken'];
    }

    do {
        $headers = buildHeaders($credentials, $rdtToken, $path, $region, $service, $method);
        $ch = curl_init("{$endpoint}{$path}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode == 429) sleep(60);
        elseif ($httpcode == 401) $accessToken = fetchAccessToken($credentials);

    } while ($httpcode == 429 || $httpcode == 401);

    echo "<br>fetchBuyerComment RAW response for $amazonOrderId: <pre>" . $result . "</pre>";

    $data = json_decode($result, true);

    $comment = NULL;
    if (isset($data['payload']['returnsItems'])) {
        foreach ($data['payload']['returnsItems'] as $item) {
            if (!empty($item['buyerComments'])) {
                $comment = $item['buyerComments'];
                break;
            }
        }
    }

    return $comment;
}

function insertToDb($Connect, $response, $credentials, $accessToken, $rdtBulkToken = null) {
    echo "Sheesh<br><pre>";
    print_r($response);
    echo "</pre>";

    foreach ($response['return_details'] as $details) {
        $amazonOrderId         = $details['order_id'] ?? NULL;
        $amazon_rma_id         = $details['amazon_rma_id'] ?? NULL;
        $ASIN                  = $details['item_details']['asin'] ?? NULL;
        $MSKU                  = $details['item_details']['merchant_sku'] ?? NULL;
        $order_date            = $details['order_date'] ?? NULL;
        $item_name             = $details['item_details']['item_name'] ?? NULL;
        $return_type           = $details['return_type'] ?? NULL;
        $tracking_id           = $details['label_details']['tracking_id'] ?? NULL;
        $return_request_date   = $details['return_request_date'] ?? NULL;
        $return_request_status = $details['return_request_status'] ?? NULL;
        $return_reason_code    = $details['item_details']['return_reason_code'] ?? NULL;
        $return_system         = "NEW";
        $store_name            = "Allrenewed";

        $order_date_la           = convertToLosAngelesTime($order_date);
        $return_request_date_la  = convertToLosAngelesTime($return_request_date);

        // Fetch buyer comment — pass the shared RDT token
        $customer_comment = fetchBuyerComment($credentials, $accessToken, $amazonOrderId, $rdtBulkToken);
        echo "<br>Buyer Comment for $amazonOrderId: " . ($customer_comment ?? 'NULL') . "<br>";

        // Check if record exists
        $sql = "SELECT COUNT(*) as count FROM tblfbmreturns WHERE amazonOrderId = ? AND ASIN = ? AND MSKU = ?";
        $stmt = $Connect->prepare($sql);
        $stmt->bind_param("sss", $amazonOrderId, $ASIN, $MSKU);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            $row = $result->fetch_assoc();
            $count = $row['count'];
        } else {
            echo "<br>Error: " . $Connect->error . "<br>";
            continue;
        }
        $stmt->close();

        if ($count > 0) {
            // UPDATE
            $updateQuery = "UPDATE tblfbmreturns SET 
                order_date = ?, 
                amazon_rma_id = ?, 
                item_name = ?, 
                return_type = ?, 
                tracking_id = ?, 
                return_request_date = ?, 
                return_request_status = ?, 
                return_reason_code = ?,
                customer_comment = ?
                WHERE amazonOrderId = ? AND ASIN = ? AND MSKU = ?";
            $stmt = $Connect->prepare($updateQuery);
            $stmt->bind_param("ssssssssssss",
                $order_date_la,
                $amazon_rma_id,
                $item_name,
                $return_type,
                $tracking_id,
                $return_request_date_la,
                $return_request_status,
                $return_reason_code,
                $customer_comment,
                $amazonOrderId,
                $ASIN,
                $MSKU);
            $stmt->execute();
            $stmt->close();
            echo "<br>Record updated: Order ID $amazonOrderId, ASIN $ASIN, MSKU $MSKU<br>";
        } else {
            // INSERT
            $insertQuery = "INSERT INTO tblfbmreturns (
                amazonOrderId, order_date, amazon_rma_id, ASIN, MSKU, 
                item_name, return_type, tracking_id, return_request_date, 
                return_request_status, return_reason_code, notif_status, 
                store_name, customer_comment
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $Connect->prepare($insertQuery);
            $stmt->bind_param("ssssssssssssss",
                $amazonOrderId,
                $order_date_la,
                $amazon_rma_id,
                $ASIN,
                $MSKU,
                $item_name,
                $return_type,
                $tracking_id,
                $return_request_date_la,
                $return_request_status,
                $return_reason_code,
                $return_system,
                $store_name,
                $customer_comment);
            $stmt->execute();
            $stmt->close();
            echo "<br>Record inserted: Order ID $amazonOrderId, ASIN $ASIN, MSKU $MSKU<br>";
        }
    }
}

function fetchRestrictedDataToken($accessToken, $restrictedResources) {
    $postfields = json_encode(['restrictedResources' => $restrictedResources]);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://sellingpartnerapi-na.amazon.com/tokens/2021-03-01/restrictedDataToken');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
        'x-amz-access-token: ' . $accessToken
    ]);
    $response = curl_exec($ch);
    if ($response === FALSE) die('cURL Error: ' . curl_error($ch));
    curl_close($ch);
    return json_decode($response, true);
}

function ErrorChecker($Connect, $data) {
    if (isset($data['errors'])) {
        $httpcode = $data['httpcode'];
        $errorMessage = $data['errors'][0]['message'];
        $errorCode = $data['errors'][0]['code'];
        $Connect->query("INSERT INTO cron_alerts SET cron_description = 'AWS_REPORTS_RETURN_CRON', status = '$httpcode', message = '$errorMessage', $errorCode");
        die("Error Execution!");
    }
}

function formatDateToISO8601($date) {
    $dateTime = DateTime::createFromFormat('Y-m-d', $date);
    if ($dateTime === false) die("Invalid date format. Please use 'YYYY-MM-DD'.");
    return $dateTime->format('Y-m-d\TH:i:s\Z');
}

function fetch_id($credentials, $accessToken, $jsonbody, $nextToken = null, $path) {
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $service = 'execute-api';
    $region = 'us-east-1';
    $method = 'POST';
    global $additionalurl;
    if (isset($additionalurl)) $path .= $additionalurl;

    do {
        do {
            $headers = buildHeaders($credentials, $accessToken, $path, $region, $service, $method);
            $url = "{$endpoint}{$path}" . buildQueryString($nextToken);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonbody);
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpcode == 429) { sleep(60); curl_close($ch); }
            elseif ($httpcode == 401) { $accessToken = fetchAccessToken($credentials); curl_close($ch); }
        } while ($httpcode == 429 || $httpcode == 401);
        curl_close($ch);
        $data = json_decode($result, true);
        $nextToken = $data['pagination']['nextToken'] ?? null;
    } while ($nextToken);
    $data['httpcode'] = $httpcode;
    return $data;
}

function buildQueryString($nextToken = null) {
    return (!empty($nextToken) && $nextToken !== null) ? $nextToken : '';
}

function buildHeaders($credentials, $accessToken, $path, $region, $service, $method) {
    $amzDate = gmdate('Ymd\THis\Z');
    $signatureDetails = calculateSignature($credentials, $amzDate, $path, $region, $service, $method);
    return [
        "Content-Type: application/json",
        "x-amz-date: {$amzDate}",
        "x-amz-access-token: {$accessToken}",
        "Authorization: {$signatureDetails['algorithm']} Credential={$credentials['client_id']}/{$signatureDetails['dateStamp']}/{$signatureDetails['region']}/{$signatureDetails['service']}/aws4_request, SignedHeaders={$signatureDetails['signedHeaders']}, Signature={$signatureDetails['signature']}"
    ];
}

function calculateSignature($credentials, $amzDate, $path, $region, $service, $method) {
    global $jsonbody;
    if (is_string($jsonbody)) $jsonbody = json_decode($jsonbody);
    $canonicalQueryString = buildQueryString();
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com\nx-amz-date:{$amzDate}\n";
    $signedHeaders = 'host;x-amz-date';
    $payloadHash = ($method === 'POST') ? hash('sha256', json_encode($jsonbody)) : '';
    $canonicalRequest = "{$method}\n{$path}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
    $algorithm = 'AWS4-HMAC-SHA256';
    $dateStamp = substr($amzDate, 0, 8);
    $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
    $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
    $signatureKey = getSignatureKey($credentials['client_secret'], $dateStamp, $region, $service);
    $signature = hash_hmac('sha256', $stringToSign, $signatureKey);
    return ['algorithm' => $algorithm, 'dateStamp' => $dateStamp, 'signedHeaders' => $signedHeaders, 'signature' => $signature, 'region' => $region, 'service' => $service];
}

function getSignatureKey($key, $dateStamp, $regionName, $serviceName) {
    $kSecret  = 'AWS4' . $key;
    $kDate    = hash_hmac('sha256', $dateStamp, $kSecret, true);
    $kRegion  = hash_hmac('sha256', $regionName, $kDate, true);
    $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    return $kSigning;
}

function fetchdetailsID($credentials, $accessToken, $nextToken = null, $path) {
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $service = 'execute-api';
    $region = 'us-east-1';
    $method = 'GET';
    global $additionalurl;
    if (isset($additionalurl)) $path .= $additionalurl;

    do {
        do {
            $headers = buildHeaders($credentials, $accessToken, $path, $region, $service, $method);
            $ch = curl_init("{$endpoint}{$path}" . buildQueryString($nextToken));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpcode == 429) { sleep(60); curl_close($ch); }
            elseif ($httpcode == 401) { $accessToken = fetchAccessToken($credentials); curl_close($ch); }
        } while ($httpcode == 429 || $httpcode == 401);
        curl_close($ch);
        $data = json_decode($result, true);
        $nextToken = $data['pagination']['nextToken'] ?? null;
    } while ($nextToken);
    $data['httpcode'] = $httpcode;
    return $data;
}

function fetchSuccessDetails($credentials, $rdtResponse, $accessToken, $jsonbody, $nextToken = null, $path) {
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $service = 'execute-api';
    $region = 'us-east-1';
    $method = 'GET';
    global $additionalurl;
    if (isset($additionalurl)) $path .= $additionalurl;

    do {
        do {
            $headers = buildHeaders($credentials, $accessToken, $path, $region, $service, $method);
            $ch = curl_init("{$endpoint}{$path}" . buildQueryString($nextToken));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpcode == 429) { sleep(60); curl_close($ch); }
            elseif ($httpcode == 401) { $accessToken = fetchAccessToken($credentials); curl_close($ch); }
        } while ($httpcode == 429 || $httpcode == 401);
        curl_close($ch);
        $data = json_decode($result, true);
        $nextToken = $data['pagination']['nextToken'] ?? null;
    } while ($nextToken);
    $data['httpcode'] = $httpcode;
    return $data;
}

function download($url, $compressionAlgorithm) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    echo "download response<pre>";
    print_r($response);
    echo "</pre>";
    if ($response === false) return "Error: " . curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($statusCode != 200) return "Call to download content was unsuccessful with response code: $statusCode";
    if ($compressionAlgorithm == 'gzip') $retrievedData = gzdecode($response);
    elseif ($compressionAlgorithm == 'bzip2') $retrievedData = bzdecompress($response);
    else $retrievedData = $response;
    curl_close($ch);
    return $retrievedData;
}

function processRetrievedData($Connect, $retrievedData) {
    $xml = simplexml_load_string($retrievedData) or die("Error: Cannot create object");
    $messageType = (string) $xml->MessageType;
    $returnDetailsArray = [];

    if (isset($xml->Message->return_details) && !empty($xml->Message->return_details)) {
        foreach ($xml->Message->return_details as $returnDetail) {
            $returnDetailsArray[] = [
                'item_details' => [
                    'item_name'          => (string) $returnDetail->item_details->item_name,
                    'asin'               => (string) $returnDetail->item_details->asin,
                    'return_reason_code' => (string) $returnDetail->item_details->return_reason_code,
                    'merchant_sku'       => (string) $returnDetail->item_details->merchant_sku,
                    'in_policy'          => (string) $returnDetail->item_details->in_policy,
                    'return_quantity'    => (string) $returnDetail->item_details->return_quantity,
                    'resolution'         => (string) $returnDetail->item_details->resolution,
                    'refund_amount'      => (string) $returnDetail->item_details->refund_amount
                ],
                'order_id'              => (string) $returnDetail->order_id,
                'order_date'            => date('Y-m-d H:i:s', strtotime((string) $returnDetail->order_date)),
                'amazon_rma_id'         => (string) $returnDetail->amazon_rma_id,
                'return_request_date'   => date('Y-m-d H:i:s', strtotime((string) $returnDetail->return_request_date)),
                'return_request_status' => (string) $returnDetail->return_request_status,
                'a_to_z_claim'          => (string) $returnDetail->a_to_z_claim,
                'is_prime'              => (string) $returnDetail->is_prime,
                'label_details' => [
                    'tracking_id'    => (string) $returnDetail->label_details->tracking_id,
                    'return_carrier' => (string) $returnDetail->label_details->return_carrier,
                    'currency_code'  => (string) $returnDetail->label_details->currency_code,
                    'label_cost'     => (string) $returnDetail->label_details->label_cost,
                    'label_type'     => (string) $returnDetail->label_details->label_type
                ],
                'label_to_be_paid_by' => (string) $returnDetail->label_to_be_paid_by,
                'return_type'         => (string) $returnDetail->return_type,
                'order_amount'        => (string) $returnDetail->order_amount,
                'order_quantity'      => (string) $returnDetail->order_quantity
            ];
        }
    } else {
        echo "<br>No return details found in the XML data.<br>";
    }

    return ['message_type' => $messageType, 'return_details' => $returnDetailsArray];
}

function convertToLosAngelesTime($dateString) {
    if ($dateString) {
        $dt = new DateTime($dateString, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
        return $dt->format('Y-m-d H:i:s');
    }
    return NULL;
}

function connectDatabase($servertype) {
    if ($servertype === "ims") {
        $hostname = 'localhost'; $username = 'root'; $password = ''; $database = 'ims';
    } else if ($servertype === "hostinger") {
        $hostname = 'localhost'; $username = 'u298641722_web_ims'; $password = 'ImsHosting!11923'; $database = 'u298641722_ims';
    } else if ($servertype === "test") {
        $hostname = 'localhost'; $username = 'u298641722_testing_user'; $password = 'Watdahek1234!'; $database = 'u298641722_test';
    } else if ($servertype === "vps") {
        $hostname = 'localhost'; $username = 'imsv2_dbims_user'; $password = 'Imsv2_dbims_user'; $database = 'imsv2_dbims';
    } else if ($servertype === "vps-automation") {
        $hostname = 'localhost'; $username = 'ims_automation'; $password = 'Imsautomation2025'; $database = 'ims_ims';
    } else {
        exit("Input Server type! In server file line 46.");
    }
    $db = new mysqli($hostname, $username, $password, $database);
    if (!$db) die("Connection failed: " . mysqli_connect_error());
    return $db;
}

function getAWSCredentials($Connect) {
    $storename = 'Allrenewed';
    $sql = "SELECT client_id, client_secret, refresh_token, MerchantID FROM tblstores WHERE storename = '$storename'";
    $result = $Connect->query($sql);
    $row = $result->fetch_assoc();
    if (!$row) die("No keys found for the given client ID.");
    return $row;
}

function fetchAccessToken($credentials, $returnRaw = false) {
    $postfields = [
        'grant_type'    => 'refresh_token',
        'client_id'     => $credentials['client_id'],
        'client_secret' => $credentials['client_secret'],
        'refresh_token' => $credentials['refresh_token'],
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.amazon.com/auth/o2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded;charset=UTF-8']);
    $response = curl_exec($ch);
    if ($response === FALSE) die('cURL Error: ' . curl_error($ch));
    curl_close($ch);
    $decodedResponse = json_decode($response, true);
    if ($returnRaw) return $decodedResponse;
    return $decodedResponse['access_token'] ?? false;
}