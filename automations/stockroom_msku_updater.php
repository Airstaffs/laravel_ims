<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database credentials
$servertype = "vps-automation";



function connectDatabase($servertype)
{
    // IMS is the Official Web Server
    if ($servertype === "ims") {
        $hostname = 'localhost';
        $username = 'user';
        $password = 'root';
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
    } else if ($servertype === "vps") {
        $hostname = 'localhost';
        $username = 'ims_ims';
        $password = 'Imspassword2025';
        $database = 'ims_ims';
        $dsn = "mysql:host=$hostname;dbname=$database";
    } else if ($servertype === "vps-automation") {
        $hostname = 'localhost';
        $username = 'imsv2_dbims_user';
        $password = 'Imsv2_dbims_user';
        $database = 'imsv2_dbims';
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

// calls the connect function!
$Connect = connectDatabase($servertype);

$row = array();

// Retrieve the credentials
$credentials = getAWSCredentials($Connect);

// Fetch the access token using the credentials
$accessToken = fetchAccessToken($credentials);

if ($accessToken) {
    $_SESSION['access_token'] = $accessToken;
    // echo "Access Token: " . $accessToken . "\n";
} else {
    echo "Access token not found in the response.\n";
}
// Print the decoded response (optional - you can remove this if you don't want to print it)
// print_r(fetchAccessToken($credentials, true));

// Functions
function getAWSCredentials($Connect)
{
    $id = 1; // The id you want to retrieve
    $sql = "SELECT storename, client_id, client_secret, refresh_token FROM tblstores";
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
        'refresh_token' => $credentials['refresh_token']
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


// Global configuration
$endpoint = 'https://sellingpartnerapi-na.amazon.com';
$path = '/fba/inventory/v1/summaries';
$service = 'execute-api';
$region = 'us-east-1';
/*
    This Code is connected to the FBA (fullfillment by Amazon) inventory API
*/
// Ensure necessary keys exist
if (!isset($credentials['client_id']) || !isset($credentials['client_secret'])) {
    die("Invalid keys in database.");
}

function fetchDataFromAPI($credentials, $accessToken, $nextToken = null)
{
    global $endpoint, $path, $Connect;
    $allData = []; // Store all aggregated data here
    $allSkus = []; // store all

    $sql = "SELECT 
    p.ProductID,
    f.MSKU,
    f.FNSKU,
    a.ASIN
    FROM tblproduct AS p
    LEFT JOIN tblfnsku AS f ON p.FNSKUviewer = f.FNSKU
    LEFT JOIN tblasin AS a ON f.ASIN = a.ASIN
    WHERE p.ProductModuleLoc IN ('Stockroom', 'SoldList', 'setFNSKU')
    AND f.FNSKU IS NOT NULL AND f.FNSKU <> ''
    AND f.MSKU IS NOT NULL  AND f.MSKU <> ''
    AND a.ASIN IS NOT NULL  AND a.ASIN <> ''";

    $resultproduct = mysqli_query($Connect, $sql);

    // Initialize the array to hold unique MSKUs
    $allSkus = [];
    $uniqueMSKUs = []; // Array to track globally unique MSKUs

    while ($row = mysqli_fetch_assoc($resultproduct)) {
        $productID = $row['ProductID'];
        $mskuviewer = $row['MSKU'];

        // Skip if MSKU is null or empty
        if (is_null($mskuviewer) || $mskuviewer === '') {
            continue;
        }

        // Ensure the array for this productID is initialized
        if (!isset($allSkus[$productID])) {
            $allSkus[$productID] = [];
        }

        // Check if this MSKU is globally unique
        if (!in_array($mskuviewer, $uniqueMSKUs)) {
            // Append the MSKU to the productID's array
            $allSkus[$productID][] = $mskuviewer;
            // Mark this MSKU as added globally
            $uniqueMSKUs[] = $mskuviewer;
        }
    }

    // Now filter out any productID that ended up with no unique MSKUs
    $filteredSkus = array_filter($allSkus, function ($mskus) {
        return count($mskus) > 0; // Keep only arrays with MSKUs
    });

    // Split the filtered results into chunks of 30 unique MSKUs
    $chunkSize = 30;
    $skuChunks = array_chunk($filteredSkus, $chunkSize, true);

    // Iterate over each chunk of MSKUs
    foreach ($skuChunks as $chunk) {
        // Initialize an array to collect MSKU values
        $mskuValues = [];

        foreach ($chunk as $productID => $mskus) {
            foreach ($mskus as $msku) {
                $mskuValues[] = $msku;
            }
        }
        // handles the loop of the Inventory API
        do {
            // handles the loop for errors!
            do {
                $headers = buildHeaders($credentials, $accessToken);

                // Construct the URL for API call
                $url = "{$endpoint}{$path}?" . buildQueryString($mskuValues);

                // echo $url;

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $result = curl_exec($ch);

                $httpcode1 = curl_getinfo($ch);
                print_r($httpcode1);

                // echo "<pre>";
                // print_r($result);
                // echo "</pre>";



                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                print_r($httpcode);

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

            echo "<pre>";
            print_r($data);
            echo "</pre>";

            $aggregatedData = [];

            foreach ($data['payload']['inventorySummaries'] as $item) {
                $fnSku = $item['fnSku'];
                $MSKU = $item['sellerSku'];

                // Fetch matching FNSKUs from tblproduct
                $fnSkuQuery = "SELECT FNSKUviewer FROM tblfnsku WHERE MSKUviewer = ? AND fulfilledby = 'FBA' AND ProductModuleLoc = 'Stockroom'";
                $fnSkuStmt = $Connect->prepare($fnSkuQuery);
                $fnSkuStmt->bind_param('s', $MSKU);
                $fnSkuStmt->execute();
                $matchingFnSkus = $fnSkuStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $matchingFnSkus = array_column($matchingFnSkus, 'FNSKUviewer'); // Extract FNSKU values into an array

                // Initialize remaining quantities for this specific item
                $remainingfulfillable = intval($item['inventoryDetails']['fulfillableQuantity']);
                $remainingInboundWorking = intval($item['inventoryDetails']['inboundWorkingQuantity']);
                $remainingInboundShipped = intval($item['inventoryDetails']['inboundShippedQuantity']);
                $remainingInboundReceiving = intval($item['inventoryDetails']['inboundReceivingQuantity']);
                $remainingFcprocess = intval($item['inventoryDetails']['reservedQuantity']['fcProcessingQuantity']);
                $remainingPendingCus = intval($item['inventoryDetails']['reservedQuantity']['pendingCustomerOrderQuantity']);
                $remainingPendingTrans = intval($item['inventoryDetails']['reservedQuantity']['pendingTransshipmentQuantity']);
                $remainingUnfulfillable = intval($item['inventoryDetails']['unfulfillableQuantity']['totalUnfulfillableQuantity']);

                foreach ($matchingFnSkus as $matchingFnSku) {
                    if (!isset($aggregatedData[$matchingFnSku])) {
                        // Initialize status and quantities
                        $AvailableQty = 0;
                        $status = 'None';
                        $returnedstatus = 'None';
                        $inboundWorkingQty = 0;
                        $inboundShippedQty = 0;
                        $inboundReceivingQty = 0;
                        $fcprocessQty = 0;
                        $pendingCusQty = 0;
                        $pendingTransQty = 0;
                        $unfulfillableQty = 0;

                        // Determine inbound status
                        if ($remainingInboundWorking > 0) {
                            $status = 'Working';
                            $inboundWorkingQty = 1;
                            $remainingInboundWorking--;
                        } elseif ($remainingInboundShipped > 0) {
                            $status = 'Shipped';
                            $inboundShippedQty = 1;
                            $remainingInboundShipped--;
                        } elseif ($remainingInboundReceiving > 0) {
                            $status = 'Receiving';
                            $inboundReceivingQty = 1;
                            $remainingInboundReceiving--;
                        }

                        // If no inbound status was assigned, determine reserved status
                        if ($status === 'None') {
                            if ($remainingFcprocess > 0) {
                                $returnedstatus = 'For Process';
                                $fcprocessQty = 1;
                                $remainingFcprocess--;
                            } elseif ($remainingPendingCus > 0) {
                                $returnedstatus = 'Pending Customer';
                                $pendingCusQty = 1;
                                $remainingPendingCus--;
                            } elseif ($remainingPendingTrans > 0) {
                                $returnedstatus = 'Pending Transshipping';
                                $pendingTransQty = 1;
                                $remainingPendingTrans--;
                            } elseif ($remainingUnfulfillable > 0) {
                                $unfulfillableQty = 1;
                                $remainingUnfulfillable--;
                            } elseif ($remainingfulfillable > 0) {
                                $AvailableQty = 1;
                                $remainingfulfillable--;
                            }
                        }

                        // Store the status and quantities for this FNSKU
                        $aggregatedData[$matchingFnSku] = [
                            'fulfillableQuantity' => $AvailableQty,
                            'inboundWorking' => $inboundWorkingQty,
                            'inboundShipped' => $inboundShippedQty,
                            'inboundReceiving' => $inboundReceivingQty,
                            'fcprocess' => $fcprocessQty,
                            'pendingCus' => $pendingCusQty,
                            'pendingTrans' => $pendingTransQty,
                            'InboundStatus' => $status,
                            'ReservedStatus' => $returnedstatus,
                            'UnfilfillableQuantity' => $unfulfillableQty
                        ];
                    }
                }
            }

            // Update the tblproduct table with the aggregated data
            foreach ($aggregatedData as $fnSku => $data) {
                $inboundquantity = 0;

                if ($data['inboundWorking'] == 1 || $data['inboundShipped'] == 1 || $data['inboundReceiving'] == 1) {
                    $inboundquantity = 1;
                }
                $Connect->begin_transaction();

                try {
                    // 1) Update FNSKU-level fields now stored in tblfnsku
                    $sqlFnsku = "
                        UPDATE tblfnsku
                        SET 
                            Unfulfillable = ?, 
                            Inbound = ?, 
                            InboundStatus = ?, 
                            reservedstatus = ?, 
                            -- If there's any inbound, clear Outbound; else keep current
                            Outbound = IF(? >= 1, 0, Outbound)
                            -- Optional: if you also track numeric reserved units in tblfnsku
                            -- , Reserved = ?
                     WHERE FNSKU = ?
                    ";

                    $stmtFnsku = $Connect->prepare($sqlFnsku);
                    $stmtFnsku->bind_param(
                        'iissi s',   // ints: Unfulfillable, Inbound, (inbound used twice); strings: InboundStatus, reservedstatus, FNSKU
                        $data['unfulfillableQuantity'],
                        $inboundquantity,
                        $data['InboundStatus'],
                        $data['ReservedStatus'],
                        $inboundquantity,
                        $fnSku
                    );
                    // If you also want to set Reserved (int), use this instead:
                    // $stmtFnsku->bind_param('iissiis', $data['unfulfillableQuantity'], $inboundquantity, $data['InboundStatus'], $data['ReservedStatus'], $inboundquantity, $data['reservedQuantity'], $fnSku);

                    if (!$stmtFnsku->execute()) {
                        throw new Exception("tblfnsku update failed for $fnSku: " . $Connect->error);
                    }

                    // 2) (Optional) Update product-level FbaAvailable only (the rest moved to tblfnsku)
                    $sqlProduct = "
                        UPDATE tblproduct
                        SET FbaAvailable = ?
                        WHERE FNSKUviewer = ?
                        AND ProductModuleLoc = 'Stockroom'
                        AND Fulfilledby = 'FBA'
                    ";

                    $stmtProduct = $Connect->prepare($sqlProduct);
                    $stmtProduct->bind_param(
                        'is',
                        $data['fulfillableQuantity'],
                        $fnSku
                    );

                    if (!$stmtProduct->execute()) {
                        throw new Exception("tblproduct update failed for $fnSku: " . $Connect->error);
                    }

                    $Connect->commit();

                    echo "<br>Updated FNSKU: $fnSku";
                    echo "<br>InboundStatus: " . $data['InboundStatus'];
                    echo "<br>Reserved Status: " . $data['ReservedStatus'] . "<br>";
                    echo "<pre>";
                    print_r($data);
                    echo "</pre>";

                } catch (Exception $e) {
                    $Connect->rollback();
                    echo "Error updating FNSKU: $fnSku<br>";
                    echo $e->getMessage() . "<br>";
                }
            }

            if (isset($data['payload']['inventorySummaries'])) {
                $allData = array_merge($allData, $data['payload']['inventorySummaries']);
            }
            curl_close($ch);

        } while ($nextToken);

    }


    return ['payload' => ['inventorySummaries' => $allData]]; // Return the aggregated results


}

function buildHeaders($credentials, $accessToken)
{
    $amzDate = gmdate('Ymd\THis\Z');
    $signatureDetails = calculateSignature($credentials, $amzDate);

    return [
        "x-amz-date: {$amzDate}",
        "x-amz-access-token: {$accessToken}",
        "Authorization: {$signatureDetails['algorithm']} Credential={$credentials['client_id']}/{$signatureDetails['dateStamp']}/{$signatureDetails['region']}/{$signatureDetails['service']}/aws4_request, SignedHeaders={$signatureDetails['signedHeaders']}, Signature={$signatureDetails['signature']}"
    ];
}


function buildQueryString($mskuValues = [], $nextToken = null)
{
    if (!is_array($mskuValues) || empty($mskuValues)) {
        return "error=Invalid MSKU Values";
    }

    $granularityId = "ATVPDKIKX0DER";
    $marketplaceIds = "ATVPDKIKX0DER";

    echo "<pre>";
    print_r($mskuValues);
    echo "</pre>";

    // Encode MSKU values and build query string
    $encodedMSKUValues = implode(',', array_map('urlencode', $mskuValues));
    $query = "details=true&granularityType=Marketplace&granularityId={$granularityId}&sellerSkus={$encodedMSKUValues}&marketplaceIds={$marketplaceIds}";

    if ($nextToken) {
        $query .= "&nextToken=" . urlencode($nextToken);
    }

    return $query;
}

function calculateSignature($credentials, $amzDate)
{
    global $service, $region, $path;

    // Step 1: Create Canonical Request
    $method = 'GET';
    $canonicalUri = $path;
    $canonicalQueryString = buildQueryString();
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

// Fetch data
$data = fetchDataFromAPI($credentials, $accessToken);





/*
echo "<pre>";
print_r($data);
echo "</pre>";
*/

// Functions


?>