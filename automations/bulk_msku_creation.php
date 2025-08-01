<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);
ini_set('max_execution_time', 600);
$Connect = new mysqli("localhost", "u298641722_dbims_user", "?cIk=|zRk3T", "u298641722_dbims");
/*

$authEndpoint = 'https://api.amazon.com/auth/o2/token';



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
        'condition' => $condition,eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee
        'storename' => $row['storename'],
    ];
}

$conditions = array_unique($conditions);

if (empty($mskus)) exit("No MSKUs found for ASIN: $asin<br>");


// step 3 all about checking the item if eligible for listing
// Step 3a: Fetch listing restrictions
$producttype = fetch_listing_product_type($filterstore, $filterasin);

// step 3b: check restriction for the condition
$listing_restrict = fetch_listing_retrict($filterstore, $filterasin);

echo "Product Type";
echo "<pre>";
print_r($producttype);
echo "</pre>";


// step 3c: now check current condition to amzn listing condition 
//   if the condition is restricted 
//     it will execute notification, and skip current item
if ($listing_restrict['status'] == '200') {
    echo "executing sheesh";
    $restrictions = $listing_restrict['data']['restrictions'] ?? [];

    foreach ($restrictions as $r) {
        if ($r['conditionType'] === $amzncondition) {
            $reason = $r['reasons'][0]['reasonCode'] ?? null;

            if ($reason === 'NOT_ELIGIBLE') {
                // 🚫 Blocked condition
                echo "ASIN $filterasin is NOT ELIGIBLE for condition $amzncondition<br>";

                // 🔔 Insert notification if needed
                create_notification([
                    'module' => 'listing',
                    'title' => "Blocked: $filterasin",
                    'subtitle' => $amzncondition,
                    'content' => $r['reasons'][0]['message'] ?? 'Blocked by Amazon',
                    'severity' => 'action_required'
                ]);

                // 🛑 Mark it as blocked
                $Connect->query("UPDATE tblfnsku SET amazon_status = 'Blocked' WHERE ASIN = '$filterasin' AND storename = '$filterstore' AND grading = '$filtercondition'");

                exit("Skipping upload for restricted ASIN.<br>");
            }
        }
    }

    // build the json data to be sent to feeds api
    foreach ($mskus as $item) {
        $feedItems[] = [
            "sku" => $item['sku'],
            "productType" => "generic",
            "attributes" => [
                'condition_type' => [
                    [
                        'value' => $amzncondition,
                        'marketplace_id' => $marketplace,
                    ]
                ],
                'fulfillment_availability' => [
                    [
                        'fulfillment_channel_code' => $fulfillmentChannel,
                        'marketplace_id' => $marketplace
                    ]
                ],
                "merchant_suggested_asin" => [
                    [
                        "value" => $item['asin'],
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]
                ],
                "list_price" => [
                    [
                        "currency" => $currency,
                        "value" => $price,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]
                ]
            ]
        ];
    }

    $createdocumentid_data = Create_feed_document_passing_json($filterstore, null);
    echo "Data from Create Feed Document";
    echo "<pre>";
    print_r($createdocumentid_data);
    echo "</pre>";
    $feeddocumentid = $createdocumentid_data['data']['feedDocumentId'];

    // Convert to JSON
    $feedDataJson = json_encode($feedItems, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    $data = upload_feed_to_amazon_s3($createdocumentid_data['data']['url'], $feedDataJson);

    // result of upload
    echo "Result of Upload";
    echo "<pre>";
    print_r($data);
    echo "</pre>";

    $feedId = create_feed_from_document($filterstore, $createdocumentid_data['data']['feedDocumentId']);

    if ($feedId) {
        insert_created_feed(
            $feedId,
            'JSON_LISTINGS_FEED',
            $createdocumentid_data['data']['feedDocumentId'],
            $filterstore
        );
    }
}

*/

// echo "<pre>";
// print_r($listing_restrict);
// echo "</pre>";

/*
echo "<pre>";
print_r($feedItems);
echo "</pre>";
*/

// $feedData = json_encode($feedItems, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

function fetch_listing_product_type($store, $searchedAsin, $destinationMarketplace = 'ATVPDKIKX0DER', $nextToken = null)
{
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
    $path = '/definitions/2020-09-01/productTypes/';

    $companydetails = sheesh_fetchCompanyDetails();
    if (!$companydetails) {
        echo json_encode(['error' => 'Company not found']) . "<br>";
        return;
    }

    $tblstore = sheesh_fetchtblstores($store);
    if (!$tblstore) {
        echo json_encode(['error' => "Store config not found for $store"]) . "<br>";
        return;
    }

    $customParams = [
        'marketplaceIds' => $destinationMarketplace,
        'sellerId' => $tblstore['MerchantID'],
        'asin' => $searchedAsin,
    ];

    $credentials = aws_sheesh_credentials($store);
    if (!$credentials) {
        echo json_encode(['error' => "No credentials for store $store"]) . "<br>";
        return;
    }

    $accessToken = fetch_sheesh_AccessToken($credentials, false);
    if (!$accessToken) {
        echo json_encode(['error' => "Access token fetch failed"]) . "<br>";
        return;
    }

    $jsonData = JsonCreation(null, null, null, null);

    $headers = sheesh_buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
    $headers['Content-Type'] = 'application/json';
    $headers['accept'] = 'application/json';

    $queryString = buildQueryString_Sheesh($nextToken, $customParams);
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

    $companydetails = sheesh_fetchCompanyDetails();
    if (!$companydetails) {
        echo json_encode(['error' => 'Company not found']) . "<br>";
        return;
    }

    $tblstore = sheesh_fetchtblstores($store);
    if (!$tblstore) {
        echo json_encode(['error' => "Store config not found for $store"]) . "<br>";
        return;
    }

    $customParams = [
        'marketplaceIds' => $destinationMarketplace,
        'sellerId' => $tblstore['MerchantID'],
        'asin' => $searchedAsin,
    ];

    $credentials = aws_sheesh_credentials($store);
    if (!$credentials) {
        echo json_encode(['error' => "No credentials for store $store"]) . "<br>";
        return;
    }

    $accessToken = fetch_sheesh_AccessToken($credentials, false);
    if (!$accessToken) {
        echo json_encode(['error' => "Access token fetch failed"]) . "<br>";
        return;
    }

    $jsonData = JsonCreation(null, null, null, null);

    $headers = sheesh_buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
    $headers['Content-Type'] = 'application/json';
    $headers['accept'] = 'application/json';

    $queryString = buildQueryString_Sheesh($nextToken, $customParams);
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

function Create_feed_document_passing_json($store, $searchedAsin, $destinationMarketplace = 'ATVPDKIKX0DER', $nextToken = null)
{
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
    $path = '/feeds/2021-06-30/documents';

    $companydetails = sheesh_fetchCompanyDetails();
    if (!$companydetails) {
        echo json_encode(['error' => 'Company not found']) . "<br>";
        return;
    }

    $tblstore = sheesh_fetchtblstores($store);
    if (!$tblstore) {
        echo json_encode(['error' => "Store config not found for $store"]) . "<br>";
        return;
    }

    $customParams = [
        // 'marketplaceIds' => $destinationMarketplace,
        // 'sellerId' => $tblstore['MerchantID'],
        // 'asin' => $searchedAsin,
    ];

    $credentials = aws_sheesh_credentials($store);
    if (!$credentials) {
        echo json_encode(['error' => "No credentials for store $store"]) . "<br>";
        return;
    }

    $accessToken = fetch_sheesh_AccessToken($credentials, false);
    if (!$accessToken) {
        echo json_encode(['error' => "Access token fetch failed"]) . "<br>";
        return;
    }

    $jsonData = JsonCreation('createDocumentID', null, null, null);

    $headers = sheesh_buildHeaders($credentials, $accessToken, 'POST', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
    $headers['Content-Type'] = 'application/json';
    $headers['accept'] = 'application/json';

    $queryString = buildQueryString_Sheesh($nextToken, $customParams);
    $url = "{$endpoint}{$path}?{$queryString}";

    // Convert headers array to format required by cURL
    $curlHeaders = array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers);



    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

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

function upload_feed_to_amazon_s3($url, $feedDataJson)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $feedDataJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($feedDataJson)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $stats = curl_getinfo($ch);
    curl_close($ch);

    echo "<b>PUT Upload Result:</b><br>";
    echo "Status Code: $httpCode<br>";
    echo "<pre>" . print_r($stats, true) . "</pre>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";

    return $httpCode === 200 || $httpCode === 201;
}

function create_feed_from_document($store, $feedDocumentId, $payload)
{
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $path = '/feeds/2021-06-30/feeds';
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";

    $credentials = aws_sheesh_credentials($store);
    $accessToken = fetch_sheesh_AccessToken($credentials, false);


    $jsonData = json_encode($payload, JSON_UNESCAPED_SLASHES);

    $headers = sheesh_buildHeaders($credentials, $accessToken, 'POST', 'execute-api', 'us-east-1', $path, null, [], $endpoint, $canonicalHeaders);
    $headers['Content-Type'] = 'application/json';
    $headers['accept'] = 'application/json';
    $curlHeaders = array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers);

    $url = "{$endpoint}{$path}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($statusCode === 202 && isset($decoded['feedId'])) {
        echo "✅ Feed submitted! Feed ID: " . $decoded['feedId'] . "<br>";
        return $decoded['feedId'];
    } else {
        echo "❌ Failed to create feed<br>";
        print_r($decoded);
        return null;
    }
}

// ______ Utility Functions ______
function aws_sheesh_credentials($store)
{
    $row = DB::table('tblstores')
        ->where('storename', $store)
        ->first();

    return $row ? (array) $row : null;
}

function fetch_sheesh_AccessToken($credentials, $returnRaw = false)
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
/*
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
    */

function sheesh_fetchCompanyDetails()
{
    $row = DB::table('tblcompanydetails')->where('id', 1)->first();
    return $row ? (array) $row : null;
}

function sheesh_fetchtblstores($storename)
{
    $row = DB::table('tblstores')
        ->where('storename', $storename)
        ->first();
    return $row ? (array) $row : null;
}

function fetchallstores()
{
    return DB::table('tblstores')->get()->map(fn($row) => (array) $row)->toArray();
}

function buildQueryString_Sheesh($nextToken = null, $customParams = [])
{
    return http_build_query($customParams, '', '&', PHP_QUERY_RFC3986);
}



function sheesh_buildHeaders($credentials, $accessToken, $method, $service, $region, $path, $nextToken, $customParams, $endpoint, $canonicalHeaders)
{
    $amzDate = gmdate('Ymd\THis\Z');
    $signatureDetails = sheesh_calculateSignature($credentials, $amzDate, $method, $service, $region, $path, $nextToken, $customParams, $canonicalHeaders);

    $authorizationHeader = "{$signatureDetails['algorithm']} Credential={$credentials['client_id']}/{$signatureDetails['dateStamp']}/{$signatureDetails['region']}/{$signatureDetails['service']}/aws4_request, SignedHeaders={$signatureDetails['signedHeaders']}, Signature={$signatureDetails['signature']}";

    return [
        "x-amz-date" => $amzDate,
        "x-amz-access-token" => $accessToken,
        "Authorization" => $authorizationHeader,
    ];
}

function sheesh_calculateSignature($credentials, $amzDate, $method, $service, $region, $path, $nextToken, $customParams, $canonicalHeaders)
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

    $signatureKey = sheesh_getSignatureKey($credentials['client_secret'], $dateStamp, $region, $service);
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

function sheesh_getSignatureKey($key, $dateStamp, $regionName, $serviceName)
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
    if ($action == 'createDocumentID') {
        $final_json_construct = [
            "contentType" => "application/json"
        ];
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

function create_notification(array $data)
{
    // 1. Insert into tblnotifications and get the ID
    $notificationId = DB::table('tblnotifications')->insertGetId([
        'module'     => $data['module'],
        'title'      => $data['title'],
        'subtitle'   => $data['subtitle'] ?? null,
        'content'    => $data['content'] ?? null,
        'severity'   => $data['severity'] ?? 'info',
        'link_data'  => isset($data['link_data']) ? json_encode($data['link_data']) : null,
        'created_at' => now(),
    ]);

    // 2. Determine user IDs
    $userIds = $data['user_ids'] ?? [];

    // If no user_ids provided and an authenticated user exists, assign to that user
    if (empty($userIds) && Auth::check()) {
        $userIds = [Auth::id()];
    }

    // 3. Insert into tblnotificationsuser
    foreach ($userIds as $userId) {
        DB::table('tblnotificationsuser')->insert([
            'notif_id'    => $notificationId,
            'userid'      => $userId,
            'read_status' => 'unread',
            'created_at'  => now(),
        ]);
    }

    return $notificationId;
}


function insert_created_feed($feedId, $feedType, $feedDocumentId, $store, $userId = null)
{
    // Determine user ID
    if (!$userId) {
        // Prefer session('userid'), fallback to Auth::id()
        $userId = session('userid') ?? (Auth::check() ? Auth::id() : null);
    }

    DB::table('tblamazon_feeds')->insert([
        'feed_id'          => $feedId,
        'type'             => $feedType,
        'store'            => $store,
        'created_by'       => $userId,
        'status'           => 'IN_PROGRESS',
        'input_document_id'=> $feedDocumentId,
        'submitted_at'     => now(),
    ]);

    echo "✅ Feed $feedId inserted into tblamazon_feeds.<br>";
}