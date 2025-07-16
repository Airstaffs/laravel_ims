<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);
ini_set('max_execution_time', 600);

$authEndpoint = 'https://api.amazon.com/auth/o2/token';

$Connect = new mysqli("localhost", "u298641722_dbims_user", "?cIk=|zRk3T", "u298641722_dbims");

// Step 1: Get the oldest ASIN to process
$asinResult = $Connect->query("SELECT ASIN, storename, grading FROM tblfnsku WHERE amazon_status = 'Not Existed' ORDER BY insert_date ASC LIMIT 1");
if ($asinResult->num_rows == 0)
    exit("No ASINs to process.<br>");
$row = $asinResult->fetch_assoc();
$filterasin = $row['ASIN'];
$filterstore = $row['storename'];
$filtercondition = $row['grading'];
$amzncondition = normalize_db_condition($filtercondition);

echo "<pre>";
print_r($row);
echo "</pre>";

// Step 2: Get all MSKUs for that ASIN
$mskuResult = $Connect->query("SELECT * FROM tblfnsku WHERE amazon_status = 'Not Existed' AND ASIN = '$filterasin' AND storename = '$filterstore' AND grading = '$filtercondition'");
$mskus = [];
$conditions = [];

while ($row = $mskuResult->fetch_assoc()) {
    $condition = strtolower(str_replace(' ', '_', $row['Condition'] ?? 'new_new'));
    $conditions[] = $condition;
    $mskus[] = [
        'sku' => $row['MSKU'],
        'asin' => $filterasin,
        'condition' => $condition,
        'storename' => $row['storename'],
    ];
}

$conditions = array_unique($conditions);
if (empty($mskus))
    exit("No MSKUs found for ASIN: $asin<br>");


// step 3 all about checking the item if eligible for listing
// Step 3a: Fetch listing restrictions
$producttype = fetch_listing_product_type($filterstore, $filterasin);

// step 3b: check restriction for the condition
$listing_restrict = fetch_listing_retrict($filterstore, $filterasin);

// step 3c: now check current condition to amzn listing condition 
//   if the condition is restricted 
//     it will execute notification, and skip current item
if ($listing_restrict['status'] === '200') {
    $checking = $
}

echo "<pre>";
print_r($listing_restrict);
echo "</pre>";

foreach ($restrictedResult['restrictions'] as $r) {
    if (!$r['success']) {
        echo "ASIN $asin is restricted under condition: {$r['conditionType']}. Reason: {$r['message']}<br>";
        exit;
    }
}

// Step 4: Build JSON_LISTINGS_FEED
$feedItems = [];
foreach ($mskus as $item) {
    $feedItems[] = [
        "sku" => $item['sku'],
        "productType" => "generic",
        "attributes" => [
            "standard_product_id" => [
                [
                    "value" => [
                        "type" => "ASIN",
                        "value" => $item['asin']
                    ]
                ]
            ],
            "condition_type" => [["value" => $item['condition']]],
            "merchant_shipping_group_name" => [["value" => "Standard FBM"]]
        ]
    ];
}
/*
echo "<pre>";
print_r($feedItems);
echo "</pre>";
*/

$feedData = json_encode($feedItems, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

/*

// Step 5: Create feed document
$createDocRes = amazon_api_post('/feeds/2021-06-30/documents', [
    'contentType' => 'application/json'
]);
$uploadUrl = $createDocRes['url'];
$feedDocumentId = $createDocRes['feedDocumentId'];

// Step 6: Upload JSON
$upload = file_put_contents_stream($uploadUrl, $feedData);
if (!$upload) exit("Upload to S3 failed.<br>");

// Step 7: Submit feed
$feedRes = amazon_api_post('/feeds/2021-06-30/feeds', [
    "feedType" => "JSON_LISTINGS_FEED",
    "marketplaceIds" => [$marketplace_id],
    "inputFeedDocumentId" => $feedDocumentId
]);
$feedId = $feedRes['feedId'] ?? null;
if (!$feedId) exit("Feed submission failed.<br>");

// Step 8: Update DB
$skuList = implode("','", array_map(fn($i) => $Connect->real_escape_string($i['sku']), $mskus));
$Connect->query("UPDATE tblfnsku SET amazon_status='Submitted', feedId='$feedId' WHERE SKU IN ('$skuList')");
echo "Feed submitted for ASIN $asin: $feedId<br>";

*/
// gets product type of the ASIN

function fetch_listing_product_type($store, $searchedAsin, $destinationMarketplace = 'ATVPDKIKX0DER', $nextToken = null)
{
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
    $path = '/definitions/2020-09-01/productTypes/';

    $companydetails = fetchCompanyDetails();
    if (!$companydetails) {
        echo json_encode(['error' => 'Company not found']) . "<br>";
        return;
    }

    $tblstore = fetchtblstores($store);
    if (!$tblstore) {
        echo json_encode(['error' => "Store config not found for $store"]) . "<br>";
        return;
    }

    $customParams = [
        'marketplaceIds' => $destinationMarketplace,
        'sellerId' => $tblstore['MerchantID'],
        'asin' => $searchedAsin,
    ];

    $credentials = AWSCredentials($store);
    if (!$credentials) {
        echo json_encode(['error' => "No credentials for store $store"]) . "<br>";
        return;
    }

    $accessToken = fetchAccessToken($credentials, false);
    if (!$accessToken) {
        echo json_encode(['error' => "Access token fetch failed"]) . "<br>";
        return;
    }

    $jsonData = JsonCreation(null, null, null, null);

    $headers = buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
    $headers['Content-Type'] = 'application/json';
    $headers['accept'] = 'application/json';

    $queryString = buildQueryString($nextToken, $customParams);
    $url = "{$endpoint}{$path}?{$queryString}";

    // Convert headers array to format required by cURL
    $curlHeaders = array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $stats = curl_getinfo($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($statusCode === 200) {
        echo "<b>Success get product type<br><br>";
        return [
            'status' => $statusCode,
            'data' => $decoded,
            'logs' => $stats
        ];
    } else {
        echo "<b>Error [$statusCode]</b><br>";
        return [
            'data' => $decoded,
            'status' => $statusCode,
            'logs' => $stats
        ];
    }
}

function fetch_listing_retrict($store, $searchedAsin, $destinationMarketplace = 'ATVPDKIKX0DER', $nextToken = null)
{
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
    $path = '/listings/2021-08-01/restrictions';

    $companydetails = fetchCompanyDetails();
    if (!$companydetails) {
        echo json_encode(['error' => 'Company not found']) . "<br>";
        return;
    }

    $tblstore = fetchtblstores($store);
    if (!$tblstore) {
        echo json_encode(['error' => "Store config not found for $store"]) . "<br>";
        return;
    }

    $customParams = [
        'marketplaceIds' => $destinationMarketplace,
        'sellerId' => $tblstore['MerchantID'],
        'asin' => $searchedAsin,
    ];

    $credentials = AWSCredentials($store);
    if (!$credentials) {
        echo json_encode(['error' => "No credentials for store $store"]) . "<br>";
        return;
    }

    $accessToken = fetchAccessToken($credentials, false);
    if (!$accessToken) {
        echo json_encode(['error' => "Access token fetch failed"]) . "<br>";
        return;
    }

    $jsonData = JsonCreation(null, null, null, null);

    $headers = buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
    $headers['Content-Type'] = 'application/json';
    $headers['accept'] = 'application/json';

    $queryString = buildQueryString($nextToken, $customParams);
    $url = "{$endpoint}{$path}?{$queryString}";

    // Convert headers array to format required by cURL
    $curlHeaders = array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $stats = curl_getinfo($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($statusCode === 200) {
        echo "<b>Success listing restrict<br><br>";
        return [
            'status' => $statusCode,
            'data' => $decoded,
            'logs' => $stats
        ];
    } else {
        echo "<b>Error [$statusCode]</b><br>";
        return [
            'data' => $decoded,
            'status' => $statusCode,
            'logs' => $stats
        ];
    }
}

function AWSCredentials($store)
{
    global $Connect;
    $stmt = $Connect->prepare("SELECT * FROM tblstores WHERE storename = ? LIMIT 1");
    $stmt->bind_param("s", $store);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc() ?: null;
}

function fetchAccessToken($credentials, $returnRaw = false)
{
    $url = 'https://api.amazon.com/auth/o2/token';
    $postfields = http_build_query([
        'grant_type' => 'refresh_token',
        'client_id' => $credentials['client_id'],
        'client_secret' => $credentials['client_secret'],
        'refresh_token' => $credentials['refresh_token']
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($response, true);

    return $decoded['access_token'] ?? false;
}

function fetchGrantlessAccessToken($credentials, $scope)
{
    $url = "https://api.amazon.com/auth/o2/token";
    $data = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $credentials['client_id'],
        'client_secret' => $credentials['client_secret'],
        'scope' => $scope
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($response, true);

    return $decoded['access_token'] ?? false;
}

function getMerchantIDorSID($store)
{
    global $Connect;
    $id = ($store == 'RT') ? 1 : 3;
    $stmt = $Connect->prepare("SELECT SID FROM tblcompanydetails WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['SID'] : null;
}

function fetchCompanyDetails()
{
    global $Connect;
    $stmt = $Connect->query("SELECT * FROM tblcompanydetails WHERE id = 1 LIMIT 1");
    return $stmt->fetch_assoc() ?: null;
}

function fetchtblstores($storename)
{
    global $Connect;
    $stmt = $Connect->prepare("SELECT * FROM tblstores WHERE storename = ? LIMIT 1");
    $stmt->bind_param("s", $storename);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc() ?: null;
}

function fetchallstores()
{
    global $Connect;
    $stmt = $Connect->prepare("SELECT * FROM tblstores");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC); // returns array of all rows
}

function buildQueryString($nextToken = null, $customParams = [])
{
    return http_build_query($customParams, '', '&', PHP_QUERY_RFC3986);
}

function buildHeaders($credentials, $accessToken, $method, $service, $region, $path, $nextToken, $customParams, $endpoint, $canonicalHeaders)
{
    $amzDate = gmdate('Ymd\THis\Z');
    $signatureDetails = calculateSignature($credentials, $amzDate, $method, $service, $region, $path, $nextToken, $customParams, $canonicalHeaders);

    $authorizationHeader = "{$signatureDetails['algorithm']} Credential={$credentials['client_id']}/{$signatureDetails['dateStamp']}/{$signatureDetails['region']}/{$signatureDetails['service']}/aws4_request, SignedHeaders={$signatureDetails['signedHeaders']}, Signature={$signatureDetails['signature']}";

    return [
        "x-amz-date" => $amzDate,
        "x-amz-access-token" => $accessToken,
        "Authorization" => $authorizationHeader,
    ];
}

function calculateSignature($credentials, $amzDate, $method, $service, $region, $path, $nextToken, $customParams, $canonicalHeaders)
{
    $canonicalUri = $path;
    $canonicalQueryString = ""; // Adjust if needed
    $canonicalHeadersString = "$canonicalHeaders\nx-amz-date:$amzDate\n";
    $signedHeaders = 'host;x-amz-date';
    $payloadHash = hash('sha256', '');
    $canonicalRequest = "$method\n$canonicalUri\n$canonicalQueryString\n$canonicalHeadersString\n$signedHeaders\n$payloadHash";

    $algorithm = 'AWS4-HMAC-SHA256';
    $dateStamp = substr($amzDate, 0, 8);
    $credentialScope = "$dateStamp/$region/$service/aws4_request";
    $stringToSign = "$algorithm\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

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


function JsonCreation($action, $companydetails, $marketplaceID, $data_additionale)
{
    $final_json_construct = [];
    $companydetails = (array) $companydetails;
    if ($action == 'fetch_listing_restrict') {
        $final_json_construct = [];
    }
    return json_encode($final_json_construct, JSON_UNESCAPED_SLASHES);
}

function process_restrictions($data, $conditions)
{
    $finalArray = ['restrictions' => []];
    $foundConditions = [];
    if (isset($data['restrictions']) && is_array($data['restrictions'])) {
        foreach ($data['restrictions'] as $restriction) {
            $conditionType = $restriction['conditionType'] ?? null;
            $reason = $restriction['reasons'][0] ?? null;
            if ($reason && in_array($reason['reasonCode'], ['APPROVAL_REQUIRED', 'NOT_ELIGIBLE'])) {
                $finalArray['restrictions'][] = [
                    'conditionType' => $conditionType,
                    'message' => $reason['message'],
                    'approvalLink' => $reason['links'][0]['resource'] ?? null,
                    'success' => false,
                ];
                $foundConditions[] = $conditionType;
            }
        }
    } else {
        $finalArray['success'] = false;
    }
    foreach ($conditions as $condition) {
        if (!in_array($condition, $foundConditions)) {
            $finalArray['restrictions'][] = [
                'conditionType' => $condition,
                'success' => true,
                'message' => 'No probs',
                'approvalLink' => ''
            ];
        }
    }
    return $finalArray;
}

function fetch_metaSchema($url, $method, $expectedChecksum)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    }
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'cURL error: ' . curl_error($ch);
        return null;
    }
    curl_close($ch);
    $computedChecksum = base64_encode(md5($response, true));
    if ($computedChecksum !== $expectedChecksum) {
        echo "Checksum mismatch. Data may be corrupted.\n";
        return null;
    }
    return json_decode($response, true);
}

function normalize_db_condition($condition)
{
    $map = [
        'New' => 'new_new',
        'UsedLikeNew' => 'used_like_new',
        'UsedVeryGood' => 'used_very_good',
        'UsedGood' => 'used_good',
        'UsedAcceptable' => 'used_acceptable',
        'CollectibleLikeNew' => 'collectible_like_new',
        'CollectibleVeryGood' => 'collectible_very_good',
        'CollectibleGood' => 'collectible_good',
        'CollectibleAcceptable' => 'collectible_acceptable',
        'RefurbishedRefurbished' => 'refurbished_refurbished',
        'Club' => 'club_club'
    ];

    // Remove spaces and capitalize to normalize inputs
    $key = preg_replace('/[^A-Za-z]/', '', $condition);

    return $map[$key] ?? strtolower(str_replace(' ', '_', $condition));
}