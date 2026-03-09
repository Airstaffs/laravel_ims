<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Database credentials
$servertype = "vps";

$Connect = connectDatabase($servertype);

$conn = $Connect;

$credentials = getAWSCredentials($Connect);

// Fetch the access token using the credentials
$accessToken = fetchAccessToken($credentials);

if (!isset($credentials['client_id']) || !isset($credentials['client_secret'])) {
    die("Invalid keys in database.");
}
// fetch company details!
$inputDate = $_POST['date'] ?? "2024-05-01";

$formattedDate = formatDateToISO8601($inputDate);

// Get the current date and time
$currentDate = date('Y-m-d H:i:s');

// Generate a random number of days between 5 and 15
$randomDays = rand(5, 40);
$random = rand(0, 1);

// Subtract 20 days from the current date
$previousDate = date('Y-m-d H:i:s', strtotime("-$randomDays days", strtotime($currentDate)));

// Format the previous date in ISO 8601 format
$iso8601Date = date('Y-m-d\TH:i:s\Z', strtotime($previousDate));


// Subtract 30 minutes from the current date
$end_sheesh = date('Y-m-d H:i:s', strtotime($currentDate));

$endDate = date('Y-m-d\TH:i:s\Z', strtotime($end_sheesh));

$json = [
    "reportType" => "GET_FBA_FULFILLMENT_CUSTOMER_RETURNS_DATA",
    "dataStartTime" => $iso8601Date,
    "dataEndTime" => $endDate,
    "marketplaceIds" => [
        "ATVPDKIKX0DER"
    ]
];

echo "<pre>";
print_r($json);
echo "</pre>";

$path = "/reports/2021-06-30/reports";
$jsonbody = json_encode($json);
$data_id = fetch_id($credentials, $accessToken, $jsonbody, $nextToken = null, $path);
echo "<br>step 1<br>";
echo "<pre>";
print_r($data_id);
echo "</pre>";

$httpcode = $data_id['httpcode'];
ErrorChecker($Connect, $data_id);

do {
    $reportId = $data_id['reportId'];
    $path = "/reports/2021-06-30/reports/{$reportId}";

    $status = fetchdetailsID($credentials, $accessToken, $nextToken = null, $path);

    echo "<br>step 2<br>";
    echo "<pre>";
    print_r($status);
    echo "</pre>";

    sleep(3);

} while ($status['processingStatus'] == 'IN_QUEUE' || $status['processingStatus'] == 'IN_PROGRESS');

if ($status['processingStatus'] == 'DONE') {
    echo "<br><br>Status<br><br>";
    echo "<pre>";
    print_r($status);
    echo "</pre>";

    $jsonbody = "";

    if ($status['reportDocumentId'] && !empty($status['reportDocumentId'])) {
        $documentid = $status['reportDocumentId'];
        $path2 = "/reports/2021-06-30/documents/{$documentid}";
        echo $documentid;
    }

    $data1 = fetchSuccessDetails($credentials, $accessToken, $jsonbody, $nextToken = null, $path2);

    echo "<br>step fetch success<br>";
    echo "<pre>";
    print_r($data1);
    echo "</pre>";

    // $sheesh = json_encode($data1);
    // echo "SHEEEEEEEEEEEEEEEEESH" . $sheesh;
    $compressionAlgorithm = $data1['compressionAlgorithm'] ?? " ";

    $url = $data1['url'];

    $retrievedData = download($url, $compressionAlgorithm);

    $response = processRetrievedData($Connect, $retrievedData);

    insertToDb($Connect, $response);


} else if ($status['processingStatus'] == 'CANCELLED') {
    echo "<br> CANCELLED!";
} else if ($status['processingStatus'] == 'FATAL') {
    echo "<br> FATAL!";
}


//________________________________________________________________________________________________________________________________________________________________________________________________
// function

function ErrorChecker($Connect, $data)
{
    if (isset($data['errors'])) {
        $httpcode = $data['httpcode'];
        $errorMessage = $data['errors'][0]['message'];
        $errorCode = $data['errors'][0]['code'];

        $data_id = $Connect->query("INSERT INTO cron_alerts SET cron_description = 'AWS_REPORTS_RETURN_CRON', status = '$httpcode', message = '$errorMessage', $errorCode");
        die("Eror Execution!");
    }
}
function formatDateToISO8601($date)
{
    // Check if the date is valid
    $dateTime = DateTime::createFromFormat('Y-m-d', $date);
    if ($dateTime === false) {
        // Handle invalid date format
        die("Invalid date format. Please use 'YYYY-MM-DD'.");
    }

    // Format date to ISO 8601
    return $dateTime->format('Y-m-d\TH:i:s\Z');
}

function fetch_id($credentials, $accessToken, $jsonbody, $nextToken = null, $path)
{
    // Global configuration
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $path = $path;
    $service = 'execute-api';
    $region = 'us-east-1';
    $method = 'POST';
    $allData = [];
    global $additionalurl;

    if (isset($additionalurl)) {
        $path .= $additionalurl;
    }

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
            $data = json_decode($result, true);

            $http = curl_getinfo($ch);
            //print_r($http);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $data['http_code'] = $httpcode;
            $data['messaging'] = "Sheesh";
            // echo "HTTP Code: " . $httpcode;


            // handles the error code 429
            if ($httpcode == 429) {
                // echo "Rate limit exceeded, retrying in 60 seconds...\n";
                sleep(60);
                curl_close($ch);
            } else if ($httpcode == 401) {
                // echo "Unauthorized Access Retrying!\n";

                $accessToken = fetchRefreshToken($credentials);

                if ($accessToken) {
                    $_SESSION['access_token'] = $accessToken;
                    // echo "Access Token: " . $accessToken . "\n";
                } else {
                    // echo "Access token not found in the response.\n";
                }

                curl_close($ch);
            }
        } while ($httpcode == 429 || $httpcode == 401);

        curl_close($ch);

        // $data .= $result;
        $data = json_decode($result, true);

        $nextToken = $data['pagination']['nextToken'] ?? null;
    } while ($nextToken);
    $data['httpcode'] = $httpcode;
    return $data;
}


function buildQueryString($nextToken = null)
{
    $query = '';

    if (!empty($nextToken) && $nextToken !== null) {
        $query .= $nextToken;
    }

    return $query;
}

function buildHeaders($credentials, $accessToken, $path, $region, $service, $method)
{
    $amzDate = gmdate('Ymd\THis\Z');
    $signatureDetails = calculateSignature($credentials, $amzDate, $path, $region, $service, $method);

    return [
        "Content-Type: application/json",
        "x-amz-date: {$amzDate}",
        "x-amz-access-token: {$accessToken}",
        "Authorization: {$signatureDetails['algorithm']} Credential={$credentials['client_id']}/{$signatureDetails['dateStamp']}/{$signatureDetails['region']}/{$signatureDetails['service']}/aws4_request, SignedHeaders={$signatureDetails['signedHeaders']}, Signature={$signatureDetails['signature']}"
    ];
}

function calculateSignature($credentials, $amzDate, $path, $region, $service, $method)
{
    global $jsonbody;

    if (is_string($jsonbody)) {
        $jsonbody = json_decode($jsonbody);
    }

    $canonicalUri = $path;
    $canonicalQueryString = buildQueryString();
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com\nx-amz-date:{$amzDate}\n";
    $signedHeaders = 'host;x-amz-date';

    // if the method is post must include the request data in payload hash else if GET put '' or null

    $payloadHash = ($method === 'POST') ? hash('sha256', json_encode($jsonbody)) : '';


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
        'region' => $region,
        'service' => $service
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


function fetchdetailsID($credentials, $accessToken, $nextToken = null, $path)
{
    $jsonbody = "";
    // Global configuration
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $path = $path;
    $service = 'execute-api';
    $region = 'us-east-1';
    $method = 'GET';
    $allData = [];
    global $additionalurl;

    if (isset($additionalurl)) {
        $path .= $additionalurl;
    }
    do {
        do {
            $headers = buildHeaders($credentials, $accessToken, $path, $region, $service, $method);
            $url = "{$endpoint}{$path}" . buildQueryString($nextToken);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);  // Set the request method to GET

            $result = curl_exec($ch);
            $data = json_decode($result, true);

            $http = curl_getinfo($ch);
            //print_r($http);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $data['http_code'] = $httpcode;
            // echo "HTTP Code: " . $httpcode;


            // handles the error code 429
            if ($httpcode == 429) {
                // echo "Rate limit exceeded, retrying in 60 seconds...\n";
                sleep(60);
                curl_close($ch);
            } else if ($httpcode == 401) {
                // echo "Unauthorized Access Retrying!\n";

                $accessToken = fetchRefreshToken($credentials);

                if ($accessToken) {
                    $_SESSION['access_token'] = $accessToken;
                    // echo "Access Token: " . $accessToken . "\n";
                } else {
                    // echo "Access token not found in the response.\n";
                }

                curl_close($ch);
            }
        } while ($httpcode == 429 || $httpcode == 401);

        curl_close($ch);

        // $data .= $result;
        $data = json_decode($result, true);

        $nextToken = $data['pagination']['nextToken'] ?? null;
    } while ($nextToken);
    $data['httpcode'] = $httpcode;
    return $data;
}

function fetchSuccessDetails($credentials, $accessToken, $jsonbody, $nextToken = null, $path)
{
    // Global configuration
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $path = $path;
    $service = 'execute-api';
    $region = 'us-east-1';
    $method = 'GET';
    $allData = [];
    global $additionalurl;

    if (isset($additionalurl)) {
        $path .= $additionalurl;
    }

    do {
        do {
            $headers = buildHeaders($credentials, $accessToken, $path, $region, $service, $method);
            $url = "{$endpoint}{$path}" . buildQueryString($nextToken);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);  // Set the request method to GET

            $result = curl_exec($ch);
            $data = json_decode($result, true);

            $http = curl_getinfo($ch);
            //print_r($http);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $data['http_code'] = $httpcode;
            // echo "HTTP Code: " . $httpcode;


            // handles the error code 429
            if ($httpcode == 429) {
                // echo "Rate limit exceeded, retrying in 60 seconds...\n";
                sleep(60);
                curl_close($ch);
            } else if ($httpcode == 401) {
                // echo "Unauthorized Access Retrying!\n";

                $accessToken = fetchRefreshToken($credentials);

                if ($accessToken) {
                    $_SESSION['access_token'] = $accessToken;
                    // echo "Access Token: " . $accessToken . "\n";
                } else {
                    // echo "Access token not found in the response.\n";
                }

                curl_close($ch);
            }
        } while ($httpcode == 429 || $httpcode == 401);

        curl_close($ch);

        // $data .= $result;
        $data = json_decode($result, true);

        $nextToken = $data['pagination']['nextToken'] ?? null;
    } while ($nextToken);
    $data['httpcode'] = $httpcode;
    return $data;
}

function download($url, $compressionAlgorithm)
{
    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Execute the request
    $response = curl_exec($ch);

    echo "download response";
    echo "<pre>";
    print_r($response);
    echo "</pre>";

    // Check for cURL errors
    if ($response === false) {
        return "Error: " . curl_error($ch);
    }

    // Get HTTP status code
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Check if request was successful
    if ($statusCode != 200) {
        return "Call to download content was unsuccessful with response code: $statusCode";
    }

    // Decompress if compression algorithm is GZIP
    if ($compressionAlgorithm == 'gzip') {
        $retrievedData = gzdecode($response);
    } elseif ($compressionAlgorithm == 'bzip2') {
        $retrievedData = bzdecompress($response);
    } else {
        // If no compression algorithm specified, return raw response
        $retrievedData = $response;
    }

    // Close cURL session
    curl_close($ch);

    return $retrievedData;
}

function processRetrievedData($Connect, $retrievedData)
{
    // Split the result into lines
    $lines = explode("\n", trim($retrievedData));

    // Define the expected headers
    $expectedHeaders = [
        'return-date',
        'order-id',
        'sku',
        'asin',
        'fnsku',
        'product-name',
        'quantity',
        'fulfillment-center-id',
        'detailed-disposition',
        'reason',
        'status',
        'license-plate-number',
        'customer-comments'
    ];

    // Initialize an array to hold the processed data
    $data = [];
    $skipFirst = true;

    // Process each line
    foreach ($lines as $line) {
        if ($skipFirst) {
            $skipFirst = false;
            continue;
        }
        $fields = explode("\t", $line);

        // Skip empty lines
        if (count($fields) < count($expectedHeaders)) {
            continue;
        }

        // Combine headers with their respective fields
        $data[] = array_combine($expectedHeaders, $fields);
    }

    // Return the processed data as an array
    return $data;
}

function convertToLosAngelesTime($dateString) {
    if ($dateString) {
        $dt = new DateTime($dateString, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
        return $dt->format('Y-m-d H:i:s'); // Format as needed
    }
    return NULL;
}

function insertToDb($Connect, $response)
{
    foreach ($response as $details) {
                                    //
        $amazon_rma_id = $details['license-plate-number'] ?? NULL;

        $amazonOrderId = trim($details['order-id'] ?? '');  
        $ASIN = trim($details['asin'] ?? '');                                      
        $MSKU = trim($details['sku'] ?? '');                   
        $FNSKU = trim($details['fnsku'] ?? '');    
        
        // Convert empty strings to NULL if needed
        $amazonOrderId = $amazonOrderId === '' ? NULL : $amazonOrderId;
        $ASIN = $ASIN === '' ? NULL : $ASIN;
        $MSKU = $MSKU === '' ? NULL : $MSKU;
        $FNSKU = $FNSKU === '' ? NULL : $FNSKU;             
        $order_date = NULL;                                                                 //
        $item_name = $details['product-name'] ?? NULL;                                      //
        $return_type = $details['return_type'] ?? NULL;                                     //
        $tracking_id = $details['label_details']['tracking_id'] ?? NULL;
        $return_request_date = $details['return-date'] ?? NULL;
        $return_request_status = $details['return_request_status'] ?? NULL;
        $return_reason_code = $details['detailed-disposition'] ?? NULL;                     // detailed-disposition
        $item_status = $details['status'] ?? NULL;                                          //
        $quantity = $details['quantity'] ?? NULL;
        $fulfillment_center_id = $details['fulfillment_center_id'] ?? NULL;
        $reason = $details['reason'] ?? NULL;
        $status = $details['status'] ?? NULL;
        $amazon_rma_id = $details['license-plate-number'] ?? NULL;
        $customer_comments = $details['customer-comments'] ?? NULL;
        $return_system = "NEW";
        $store_name = "Allrenewed";

        echo "<br> data license plate - $amazon_rma_id comments: $customer_comments<br>";

        $return_request_date_la = convertToLosAngelesTime($return_request_date);

        if ($amazonOrderId == '114-5801855-8439460' && $MSKU == '2F-6RVM-Y6M6' && $ASIN == 'B00EWCUK1Q' && $FNSKU == 'X003TISNNH') {
            continue;
        }

        if ($amazonOrderId == '112-1170392-1378664' && $MSKU == 'WP-ABVA-Y01B' && $ASIN == 'B07K1V13GZ' && $FNSKU == 'X004E6WCU3') {
            continue;
        }

        if ($amazonOrderId == '112-1170392-1378664' && $MSKU == 'WP-ABVA-Y01B' && $ASIN == 'B07K1V13GZ' && $FNSKU == 'X004EFFZM1') {
            continue;
        }

        if ($amazonOrderId == '112-1170392-1378664' && $MSKU == 'WP-ABVA-Y01B' && $ASIN == 'B07K1V13GZ' && $FNSKU == 'X004E6MU37') {
            continue;
        }


        // Prepare the SQL query to count existing records
        $sql = "SELECT COUNT(*) as count FROM tblfbareturns WHERE order_id = ? AND asin = ? AND sku = ? AND fnsku = ? ";
        $stmt = $Connect->prepare($sql);
        $stmt->bind_param("ssss", $amazonOrderId, $ASIN, $MSKU, $FNSKU);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            $row = $result->fetch_assoc();
            $count = $row['count'];
        } else {
            echo "<br>Error: " . $Connect->error;
            continue; // Skip to the next iteration if there is an error
        }
        $stmt->close();

        if ($count > 0) {
            // Record exists, perform update
            $updateQuery = "UPDATE tblfbareturns SET 
                return_date = ?, 
                order_id = ?, 
                sku = ?, 
                asin = ?, 
                fnsku = ?, 
                product_name = ?, 
                quantity = ?, 
                fulfillment_center_id = ?,
                detailed_disposition = ?,
                reason = ?,
                status = ?,
                license_plate_number = ?,
                customer_comments = ? 
                WHERE order_id = ? AND asin = ? AND sku = ?";
            $stmt = $Connect->prepare($updateQuery);

            $stmt->bind_param(
                "ssssssisssssssss",
                $return_request_date_la,
                $amazonOrderId,
                $MSKU,
                $ASIN,
                $FNSKU,
                $item_name,
                $quantity,
                $fulfillment_center_id,
                $return_reason_code,
                $reason,
                $status,
                $amazon_rma_id,
                $customer_comments,
                $amazonOrderId,
                $ASIN,
                $MSKU
            );
            $stmt->execute();
            $stmt->close();
            echo "<br>Record updated successfully: Order ID $amazonOrderId, ASIN $ASIN, MSKU $MSKU\n";
        } else {
            // Record does not exist, perform insert
            $insertQuery = "INSERT INTO tblfbareturns (
                return_date, 
                order_id, 
                sku, 
                asin, 
                fnsku, 
                product_name, 
                quantity, 
                fulfillment_center_id,
                detailed_disposition,
                reason,
                status,
                license_plate_number,
                customer_comments,
                notif_status,
                store_name
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $Connect->prepare($insertQuery);

            // Use `NULL` explicitly if the value is not provided
            $stmt->bind_param(
                "ssssssissssssss",
                $return_request_date_la,
                $amazonOrderId,
                $MSKU,
                $ASIN,
                $FNSKU,
                $item_name,
                $quantity,
                $fulfillment_center_id,
                $return_reason_code,
                $reason,
                $status,
                $amazon_rma_id,
                $customer_comments,
                $return_system,
                $store_name
            );
            $stmt->execute();
            $stmt->close();
            echo "<br>Record inserted successfully: Order ID $amazonOrderId, ASIN $ASIN, MSKU $MSKU\n";
        }
    }
}


function connectDatabase($servertype)
{
    // IMS is the Official Web Server
    if ($servertype === "ims") {
        $hostname = 'localhost';
        $username = 'root';
        $password = '';
        $database = 'ims';
    } else if ($servertype === "hostinger") {
        $hostname = 'localhost';
        $username = 'u298641722_web_ims';
        $password = 'ImsHosting!11923';
        $database = 'u298641722_ims';
    } else if ($servertype === "test") {
        $hostname = 'localhost';
        $username = 'u298641722_testing_user';
        $password = 'Watdahek1234!';
        $database = 'u298641722_test';
    } else if ($servertype === "4/19 reference") {
        $hostname = 'localhost';
        $username = 'u298641722_sheeshables';
        $password = '>KXF*LTaWd&2';
        $database = 'u298641722_web_ims_refere';
    } else if ($servertype === "vps") {
        $hostname = 'localhost';
        $username = 'imsv2_dbims_user';
        $password = 'imsv2_dbims_user';
        $database = 'imsv2_dbims';
        $dsn = "mysql:host=$hostname;dbname=$database";
    } else if ($servertype === "vps-automation") {
        $hostname = 'localhost';
        $username = 'ims_automation';
        $password = 'Imsautomation2025';
        $database = 'ims_ims';
        $dsn = "mysql:host=$hostname;dbname=$database";
    } else {
        $messageError = "Input Server type! In server file line 46.";
        exit($messageError);
    }

    // Create a database connection
    if ($db = new mysqli($hostname, $username, $password, $database)) {

    } else {
        echo "Database Connection error";
    }

    // Check the connection
    if (!$db) {
        die("Connection failed: " . mysqli_connect_error());
    }

    return $db; // Return the database connection
}

// Functions
function getAWSCredentials($Connect, $storename)
{
    // The id you want to retrieve
    $sql = "SELECT client_id, client_secret, refresh_token, MerchantID FROM tblstores WHERE storename = $storename";
    $result = $Connect->query($sql);
    $row = $result->fetch_assoc();

    if (!$row) {
        die("No keys found for the given client ID.");
    }

    return $row;
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

    if ($response === FALSE) {
        die('cURL Error: ' . curl_error($ch));
    }

    curl_close($ch);

    $decodedResponse = json_decode($response, true);

    if ($returnRaw) {
        return $decodedResponse;
    }

    return $decodedResponse['access_token'] ?? false;
}
