<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);
ini_set('max_execution_time', 600);
session_start();
date_default_timezone_set('America/Los_Angeles');

$endpoint = 'https://sellingpartnerapi-na.amazon.com';
$service = 'execute-api';
$region = 'us-east-1';
$user = "CRON_AUTOMATION";
$value = true;
// initialize values for calculating later
$insertCount = 0;
$updateCount = 0;
$duplicateCount = 0;
$errorCount = 0;
$errorUncatchable = 0;
$nullCount = 0;
$number = 0;
$skipCount = 0;
$newasinCount = 0;
$errorasinCount = 0;
$newASIN = [];
$tblname = "";
$servertype = "hostinger";

$Connect = connectDatabase($servertype);

// Check connection
if ($Connect->connect_error) {
    die("Connection failed: " . $Connect->connect_error);
}
$successful = false;

// Generate a timestamp for the current date and time
$timestamp = date("Y-m-d_H-i-s");

$stores = []; // Initialize before use

$sqlstore = "SELECT storename FROM tblstores";
$resultproduct_store = mysqli_query($Connect, $sqlstore);

while ($row_stores = mysqli_fetch_assoc($resultproduct_store)) {
    $storename = $row_stores['storename'];
    if (!in_array($storename, $stores)) {
        $stores[] = $storename;
    }
}
$allSkus = [];

foreach ($stores as $store) {
    $tblname = "tblfnsku";

    $sid = sidfetcherino($Connect, $store);

    $allSkus = getallnewitems($Connect, $sid);

    // Get credentials for this store
    $credentials = $storeCredentials[$store];

    if (!isset($credentials['client_id']) || !isset($credentials['client_secret'])) {
        die("Invalid keys in database.");
    }

    $accessToken = fetchAccessToken($credentials, $returnRaw = false);

    foreach ($allSkus as $skuperitem) {
        echo "Processing yawa MSKU: $skuperitem<br>";

        $path = "/listings/2021-08-01/items/{$sid}/{$skuperitem}";
        $service = 'execute-api';
        $region = 'us-east-1';

        $results = fetchAmazonData($credentials, $accessToken, $skuperitem, $sid);
        echo "actual fucking array";
        echo "<pre>";
        print_r($results);
        echo "</pre>";

        // Ensure `$results` is an array and contains `summaries`
        if (is_array($results) && isset($results['summaries']) && is_array($results['summaries'])) {
            foreach ($results['summaries'] as $summary) {
                echo "<pre>";
                print_r($summary);
                echo "</pre>";

                $date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
                $currentDateTime = $date->format('Y-m-d H:i:s');

                // Retrieve data safely
                $FNSKU = strtoupper(trim($summary['fnSku'] ?? ''));
                $MSKU = strtoupper(trim($results['sku'] ?? ''));
                $skucondition = trim($summary['conditionType'] ?? '');
                $ASIN = strtoupper(trim($summary['asin'] ?? ''));
                $PRODUCT_NAME = trim($summary['itemName'] ?? '');
                $asin_statssus = null;

                if (empty($FNSKU)) {
                    $FNSKU = $ASIN;
                }

                if ($store == 'AR') {
                    $asin_status = (stripos($PRODUCT_NAME, 'renewed') !== false) ? 'Renewed' : null;
                }
                $test = $PRODUCT_NAME;

                // define the values to be removed in the title name!
                $discontinuedPatterns = array("(Discontinued)", "(Discontinued by Manufacturer)", "(Discontinued by manufacturer)", "(discontinued by manufacturer)", "***Discontinued by Manufacturer***", "Discontinued by Manufacturer", "(Discontinued by Manufacturer");

                // Remove the specified patterns from the product name
                $cleanedProductName = str_replace($discontinuedPatterns, "", $test);

                // Outputs the correct productname
                $PRODUCT_NAME = $cleanedProductName;

                if (empty($asin_status)) {
                    $asin_status = null;
                }

                // Check if FNSKU is empty, and if so, skip this row
                if (empty($FNSKU) && !empty($MSKU)) {
                    $FNSKU = 'X00' . str_replace('-', '', $MSKU);
                } else if (empty($FNSKU)) {
                    $FNSKU = null;
                }

                if (empty($MSKU)) {
                    $MSKU = null;
                }

                if (isset($skucondition) && $skucondition == 'new_new') {
                    $skucondition = "New";
                } else if (isset($skucondition) && $skucondition == 'used_like_new') {
                    $skucondition = "UsedLikeNew";
                } else if (isset($skucondition) && $skucondition == 'used_very_good') {
                    $skucondition = "UsedVeryGood";
                } else if (isset($skucondition) && $skucondition == 'used_good') {
                    $skucondition = "UsedGood";
                } else if (isset($skucondition) && $skucondition == 'new_oem') {
                    $skucondition = "NewOem";
                } else if (isset($skucondition) && $skucondition == 'new_open_box') {
                    $skucondition = "NewOpenBox";
                } else if (isset($skucondition) && $skucondition == 'refurbished_refurbished') {
                    $skucondition = "Refurbished";
                } else {
                    $skucondition = $skucondition;
                }

                if (empty($ASIN)) {
                    $ASIN = null;
                }

                if (empty($PRODUCT_NAME)) {
                    $PRODUCT_NAME = null;
                }

                // If FNSKU contains 'X0', keep everything from 'X0' onwards
                if (!empty($FNSKU)) {
                    if (($pos = strpos($FNSKU, 'X')) !== false) {
                        $FNSKU = substr($FNSKU, $pos);
                    }
                }

                // Assuming you have retrieved data from your database into $row
                $words = explode(' ', $MSKU);

                if (count($words) > 1) {
                    $MSKU = $words[0]; // Set $MSKU to the first word
                } else {

                }

                if ($MSKU === null && $ASIN === null) {
                    $logMessage = "All Values are null skipping row! for FNSKU: " . $FNSKU;
                    $nullCount++;

                } else {

                    $sqlSelect = "SELECT * FROM tblasin WHERE ASIN = ?";
                    $stmt50 = $Connect->prepare($sqlSelect);
                    $stmt50->bind_param('s', $ASIN);

                    $stmt50->execute();
                    $result50 = $stmt50->get_result();

                    if ($result50->num_rows === 0) {
                        $PRODUCT_NAME = $PRODUCT_NAME;
                    } else {
                        $row = $result50->fetch_assoc();
                        if (!empty($row['internal'])) {
                            $PRODUCT_NAME = $row['internal'];
                        } else {
                            $PRODUCT_NAME = $PRODUCTNAME;
                        }
                    }

                    echo "<br>Amzn Details: $store - $ASIN - $FNSKU - $MSKU<br>";


                    // Check if the row already exists in the database with the same FNSKU, ASIN, and PRODUCT_NAME
                    $checkQuery = "SELECT * FROM $tblname 
                                                    WHERE (FNSKU = ? OR (FNSKU IS NULL AND ? IS NULL)) 
                                                      AND (ASIN = ? OR (ASIN IS NULL AND ? IS NULL)) 
                                                      AND (MSKU = ? OR (MSKU IS NULL AND ? IS NULL))";

                    $stmtCheck = $Connect->prepare($checkQuery);
                    $stmtCheck->bind_param("ssssss", $FNSKU, $FNSKU, $ASIN, $ASIN, $MSKU, $MSKU);
                    $stmtCheck->execute();
                    $resultCheck = $stmtCheck->get_result();

                    if ($resultCheck->num_rows == 0) {
                        $hehe = "Available";

                        // Inserts into the Connect!
                        $insertQuery = "INSERT INTO $tblname (FNSKU, MSKU, grading, ASIN, insert_date, `Status`, addedby) VALUES ( ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $Connect->prepare($insertQuery);
                        $stmt->bind_param("sssssss", $FNSKU, $MSKU, $skucondition, $ASIN, $currentDateTime, $hehe, $user);

                        if ($stmt->execute()) {
                            $logMessage = "Record inserted successfully for FNSKU: $FNSKU MSKU: $MSKU";
                            // logs for uploads!
                            // uploading_Logs($Connect, $log_message = $logMessage, $reference_id = $ref, $upload_name = $uploader);
                            $insertCount++;
                            updateCronInsertStatus($Connect, $MSKU, $ASIN, $sid);



                        } else {
                            $logMessage = "Error inserting record for FNSKU: $FNSKU - Error: " . $stmt->error;
                            // logs for uploads!
                            // uploading_Logs($Connect, $log_message = $logMessage, $reference_id = $ref, $upload_name = $uploader);

                            $errorCount++;
                        }
                    } else {
                        // Row already exists in the database
                        $existingRow = $resultCheck->fetch_assoc();
                        /*
                        // if all data is equivalent then skip the duplicate!
                        if ($existingRow['astitle'] != $PRODUCT_NAME) { // Update the PRODUCT_NAME if it's different (and not NULL)
                            $updateQuery = "UPDATE $tblname SET astitle = ?, insert_date = ? WHERE FNSKUID = ?";
                            $stmtUpdate = $Connect->prepare($updateQuery);
                            $stmtUpdate->bind_param("sss", $PRODUCT_NAME, $currentDateTime, $existingRow['FNSKUID']);

                            if ($stmtUpdate->execute()) {
                                $logMessage = "PRODUCT_NAME updated successfully for FNSKU: $FNSKU $PRODUCT_NAME";
                                // uploading_Logs($Connect, $log_message = $logMessage, $reference_id = $ref, $upload_name = $uploader);

                                $updateCount++;
                            } else {
                                $logMessage = "Error updating PROUCT_NAME for FNSKU: $FNSKU - Error: " . $stmtUpdate->error;
                                // uploading_Logs($Connect, $log_message = $logMessage, $reference_id = $ref, $upload_name = $uploader);
                                $errorCount++;
                            }
                        } else */
                        if (
                            ($FNSKU == $existingRow['FNSKU'] || ($FNSKU === null && $existingRow['FNSKU'] === null)) &&
                            ($MSKU == $existingRow['MSKU'] || ($MSKU === null && $existingRow['MSKU'] === null)) &&
                            ($ASIN == $existingRow['ASIN'] || ($ASIN === null && $existingRow['ASIN'] === null))
                            //&& ($PRODUCT_NAME == $existingRow['astitle'] || ($PRODUCT_NAME === null && $existingRow['astitle'] === null))
                        ) {
                            $logMessage = "All data is identical for FNSKU: $FNSKU MSKU $MSKU - Skipping insertion as it's a duplicate.";
                            // uploading_Logs($Connect, $log_message = $logMessage, $reference_id = $ref, $upload_name = $uploader);
                            updateCronInsertStatus($Connect, $MSKU, $ASIN, $sid);
                            $duplicateCount++;
                        } else if ($MSKU === null || $ASIN === null || $PRODUCT_NAME === null) {

                            $logMessage = "Values Incomplete: FNSKU: " . $FNSKU . "MSKU: $MSKU ASIN: $ASIN PRODUCT NAME: $PRODUCT_NAME";
                            // uploading_Logs($Connect, $logMessage, $ref, $uploader);
                            $nullCount++;

                        } else {
                            // Row already exists, and PRODUCT_NAME is the same (or both are NULL)
                            $logMessage = "Uncatchable: FNSKU: " . $FNSKU . " MSKU: " . $MSKU . "ASIN: " . $ASIN . " PRODUCT NAME: $PRODUCT_NAME";
                            // logs for uploads!
                            // uploading_Logs($Connect, $log_message = $logMessage, $reference_id = $ref, $upload_name = $uploader);

                            $errorUncatchable++;
                        }
                    }


                    if (isset($ASIN) && !empty($ASIN)) {
                        // updates tblasin asin status
                        $AsinStatus = $Connect->prepare("UPDATE tblasin SET asinStatus = ? WHERE ASIN = ?");
                        $AsinStatus->bind_param('ss', $asin_status, $ASIN);
                        $AsinStatus->execute();
                    }

                }

                $sqlSelect = "SELECT * FROM tblasin WHERE ASIN = ?";
                $stmt50 = $Connect->prepare($sqlSelect);
                $stmt50->bind_param('s', $ASIN);
                $stmt50->execute();
                $result50 = $stmt50->get_result();

                if ($result50->num_rows === 0) {
                    // Prepare the insert statement once, outside the loop
                    if (!isset($stmtInsert)) {
                        $stmtInsert = $Connect->prepare("INSERT INTO tblasin (ASIN, internal, dateupload, amazon_title) VALUES (?, ?, ?, ?)");
                    }
                    $stmtInsert->bind_param('ssss', $ASIN, $PRODUCT_NAME, $currentDateTime, $PRODUCT_NAME);

                    if ($stmtInsert->execute()) {
                        echo "New ASIN inserted to tblasin: " . $ASIN . "<br>";
                        $newasinCount++;
                    } else {
                        echo "Error: " . $stmtInsert->error . "<br>";
                        $errorasinCount++;
                    }
                }

                // Prepare the update statement
                $updateStmt_rawr_101 = $Connect->prepare("UPDATE tblasin SET amazon_title = ? WHERE ASIN = ?");
                $updateStmt_rawr_101->bind_param("ss", $PRODUCT_NAME, $asin);

                if ($updateStmt_rawr_101->execute()) {
                    echo "Updated ASIN $asin with title: $Amazon_title<br>";
                } else {
                    echo "Failed to update ASIN $asin: " . $updateStmt_rawr_101->error . "<br>";
                }

                $number++;
            }
        } else {
            echo "<br>No summaries available for SKU: " . ($pta['sku'] ?? 'Unknown SKU') . "<br>";
        }


    }




}

$logMessage = "File uploaded and processed successfully.";

// logs for uploads!
// uploading_Logs($Connect, $log_message = $logMessage, $reference_id = $ref, $upload_name = $uploader);

$totalRowsProcessed = $insertCount + $updateCount + $duplicateCount + $errorCount + $errorUncatchable + $skipCount + $nullCount;

$logMessage = "Total Rows Processed: " . $totalRowsProcessed . "<br>
                                                insertCount: " . $insertCount . "<br>
                                                updateCount: " . $updateCount . "<br>
                                                duplicateCount: " . $duplicateCount . "<br>
                                                errorCount: " . $errorCount . "<br>
                                                errorUncatchable: " . $errorUncatchable . "<br>
                                                Skip Count " . $skipCount . "<br>
                                                Row Uncomplete Count: " . $nullCount . "<br>
                                                New Asin Count: " . $newasinCount . "<br>
                                                Error Asin Count: " . $errorasinCount;

echo $logMessage;
echo " <br> Successfully uploaded the File!";

echo "<br><br>";
/*
if (!empty($newASIN)) {
    echo "Please Insert the Title for the new ASIN!";
    echo '<form method="post" action="newASIN_process.php">';

    foreach ($newASIN as $index => $item) {
        echo "FNSKU: " . $item['FNSKU'] . "<br>";
        echo "MSKU: " . $item['MSKU'] . "<br>";
        echo "ASIN: " . $item['ASIN'] . "<br>";
        echo "Condition: " . $item['Condition'] . "<br>";
        echo 'Title: <input type="text" name="titles[' . $index . ']" value=""><br><br>';

        // Hidden input fields for FNSKU, MSKU, ASIN, and Condition
        echo '<input type="hidden" name="fnsku[' . $index . ']" value="' . $item['FNSKU'] . '">';
        echo '<input type="hidden" name="msku[' . $index . ']" value="' . $item['MSKU'] . '">';
        echo '<input type="hidden" name="asin[' . $index . ']" value="' . $item['ASIN'] . '">';
        echo '<input type="hidden" name="condition[' . $index . ']" value="' . $item['Condition'] . '">';
    }

    echo '<input type="hidden" name="tblname" value="' . $tblname . '">';
    echo '<input type="submit" name="submit" value="Submit">';
    echo '</form>';
}
*/
$successful = true;





// Functions
function getallnewitems($Connect, $sid)
{
    $sql = "SELECT sku FROM tblnewlycreatedamznitems WHERE cron_insert_status = 'FALSE' AND seller_id = '$sid'";
    $resultproduct = mysqli_query($Connect, $sql);
    $allSkus = [];

    // Fetch all unique SKUs
    while ($row = mysqli_fetch_assoc($resultproduct)) {
        $msku = $row['sku'];
        if (!in_array($msku, $allSkus)) {
            $allSkus[] = $msku;
        }
    }

    echo "<pre>";
    print_r($allSkus);
    echo "</pre>";

    return $allSkus;
}

function fetchAmazonData($credentials, $accessToken, $sku, $sid, $nextToken = null)
{

    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $path = "/listings/2021-08-01/items/{$sid}/{$sku}";
    $service = 'execute-api';
    $region = 'us-east-1';
    $allData = [];

    // handles the loop of the Inventory API
    do {
        // handles the loop for errors!
        do {
            $headers = buildHeaders($credentials, $accessToken, $path);

            // Construct the URL for API call
            $url = "{$endpoint}{$path}?" . query_params($nextToken);

            // echo $url;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($ch);

            $httpcode1 = curl_getinfo($ch);
            //print_r($httpcode1);
/*
            echo "<pre>";
            print_r($httpcode1);
            echo "</pre>";
*/


            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // print_r($httpcode);

            // handles the error code 429
            if ($httpcode == 429) {
                echo "Rate limit exceeded, retrying in 60 seconds...\n";
                sleep(60); // Sleep for 60 seconds
                // Don't forget to close the cURL session before retrying
                curl_close($ch);


            } else if ($httpcode == 401) {
                echo "Unauthorized Access Retrying!\n";

                $accessToken = fetchAccessToken($credentials);

                if ($accessToken) {
                    $_SESSION['access_token'] = $accessToken;
                    // echo "Access Token: " . $accessToken . "\n";
                } else {
                    echo "Access token not found in the response.\n";
                }

                curl_close($ch);
            } else {
                // If the response code is not 429, break out of the loop.
                break;
            }


        } while ($httpcode == 429 || $httpcode == 401);
        $data = json_decode($result, true);

        curl_close($ch);

    } while ($nextToken);

    // }
    return $data;
}

function buildHeaders($credentials, $accessToken, $path)
{
    $amzDate = gmdate('Ymd\THis\Z');
    $signatureDetails = calculateSignature($credentials, $amzDate, $path);

    return [
        "x-amz-date: {$amzDate}",
        "x-amz-access-token: {$accessToken}",
        "Authorization: {$signatureDetails['algorithm']} Credential={$credentials['client_id']}/{$signatureDetails['dateStamp']}/{$signatureDetails['region']}/{$signatureDetails['service']}/aws4_request, SignedHeaders={$signatureDetails['signedHeaders']}, Signature={$signatureDetails['signature']}"
    ];
}

function query_params($nextToken = null)
{
    $query = 'marketplaceIds=ATVPDKIKX0DER&issueLocale=en_US&includedData=summaries';

    return $query;
}

function calculateSignature($credentials, $amzDate, $path)
{
    global $service, $region;

    // Step 1: Create Canonical Request
    $method = 'GET';
    $canonicalUri = $path;
    $canonicalQueryString = query_params();
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com\nx-amz-date:{$amzDate}\n";
    $signedHeaders = 'host;x-amz-date';
    $payloadHash = hash('sha256', ''); // Empty payload for GET request
    $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

    // Step 2: Create String to Sign
    $algorithm = 'AWS4-HMAC-SHA256';
    $dateStamp = substr($amzDate, 0, 8);
    $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
    $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

    // Step 3: Calculate Signature
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

function sidfetcherino($Connect, $store)
{
    if ($store == 'Renovar Tech') {
        $id = 1; // The id you want to retrieve
    } else if ($store == 'All Renewed') {
        $id = 3;
    }

    $sql = "SELECT SID FROM tblcompanydetails WHERE id = $id";
    $result = mysqli_query($Connect, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['SID'];
    } else {
        return null;
    }
}

function updateCronInsertStatus($Connect, $MSKU, $ASIN, $sid)
{
    $sql = "UPDATE tblnewlycreatedamznitems 
            SET cron_insert_status = 'DONE' 
            WHERE cron_insert_status = 'FALSE' AND sku = ? AND asin = ? AND seller_id = ?";

    $stmt = mysqli_prepare($Connect, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $MSKU, $ASIN, $sid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function connectDatabase($servertype)
{

    if ($servertype === "ims") {
        $hostname = 'localhost';
        $username = 'root';
        $password = '';
        $database = 'ims';
        $port = null;
    } else if ($servertype === "hostinger") {
        $hostname = 'localhost';
        $username = 'u298641722_web_ims';
        $password = 'ImsHosting!11923';
        $database = 'u298641722_ims';
        $port = null;
    } else if ($servertype === "test") {
        $hostname = 'localhost';
        $username = 'u298641722_testing_user';
        $password = 'Watdahek1234!';
        $database = 'u298641722_test';
        $port = null;
    } else if ($servertype === "4/19 reference") {
        $hostname = 'localhost';
        $username = 'u298641722_sheeshables';
        $password = '>KXF*LTaWd&2';
        $database = 'u298641722_web_ims_refere';
        $port = null;
    } else if ($servertype === "jundelllocaldb") {
        $hostname = 'localhost';
        $username = 'root';
        $password = 'Helloworld';
        $database = 'ims';
        $port = 9500;
    } else {
        $messageError = "Input Server type! In server file line 46.";
        exit($messageError);
    }

    // Create a database connection
    $db = new mysqli($hostname, $username, $password, $database, $port);

    if ($db->connect_error) {
        echo "Database Connection error: " . $db->connect_error;
    } else {

    }

    // Check the connection
    if (!$db) {
        die("Connection failed: " . mysqli_connect_error());
    }

    return $db; // Return the database connection
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

function fetchRDT($credentials, $accessToken, $jsonbody, $nextToken = null)
{
    // Global configuration
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $path = "/tokens/2021-03-01/restrictedDataToken";
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
            print_r($http);
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

        $nextToken = $data['pagination']['nextToken'] ?? null;
    } while ($nextToken);
    $data['httpcode'] = $httpcode;
    return $data;
}

function getMerchantIDorSID($Connect, $store)
{
    if ($store == 'RT') {
        $id = 1; // The id you want to retrieve
    } else if ($store == 'AR') {
        $id = 3;
    }

    $sql = "SELECT SID FROM tblcompanydetails WHERE id = $id";
    $result = mysqli_query($Connect, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['SID'];
    } else {
        return null;
    }
}

function fetchGrantlessAccessToken($credentials, $scope)
{
    $url = "https://api.amazon.com/auth/o2/token";
    $data = [
        'grant_type' => 'client_credentials',
        'client_id' => $credentials['client_id'],
        'client_secret' => $credentials['client_secret'],
        'scope' => $scope // Notifications API scope
    ];

    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        die("Error fetching access token");
    }

    $tokenData = json_decode($response, true);
    return $tokenData['access_token'];
}