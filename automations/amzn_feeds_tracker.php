<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);
ini_set('max_execution_time', 600);

$authEndpoint = 'https://api.amazon.com/auth/o2/token';

$Connect = new mysqli("localhost", "u298641722_dbims_user", "?cIk=|zRk3T", "u298641722_dbims");

if ($Connect->connect_error) {
    die("Connection failed: " . $Connect->connect_error);
}

echo "hello";

$feeds = get_pending_feeds();

foreach ($feeds as $feed) {
    echo "Feed Data";
    echo "<pre>";
    print_r($feed);
    echo "</pre>";
    $statusCheck = get_feed_status($feed['store'], $feed['feed_id']);

    echo "<pre>";
    print_r($statusCheck);
    echo "</pre>";
    $status = $statusCheck['processingStatus'] ?? 'UNKNOWN';

    if ($status === 'DONE') {
        $resultDocId = $statusCheck['resultFeedDocumentId'] ?? null;
        update_feed_status_done($feed['feed_id'], $resultDocId);
        handle_feed_result($feed['store'], $resultDocId, $feed['feed_id']);
    } elseif ($status === 'CANCELLED' || $status === 'FAILED') {
        update_feed_status_failed($feed['feed_id']);
    }
}

function get_pending_feeds()
{
    global $Connect;
    $feeds = [];
    $result = $Connect->query("SELECT * FROM tblamazon_feeds WHERE status NOT IN ('DONE', 'FAILED')");
    while ($row = $result->fetch_assoc()) {
        $feeds[] = $row;
    }
    return $feeds;
}

function update_feed_status_done($feedId, $resultDocId)
{
    global $Connect;
    $stmt = $Connect->prepare("UPDATE tblamazon_feeds SET status = 'DONE', result_doc_id = ?, completed_at = NOW() WHERE feed_id = ?");
    $stmt->bind_param("ss", $resultDocId, $feedId);
    $stmt->execute();
}

function update_feed_status_failed($feedId)
{
    global $Connect;
    $stmt = $Connect->prepare("UPDATE tblamazon_feeds SET status = 'FAILED' WHERE feed_id = ?");
    $stmt->bind_param("s", $feedId);
    $stmt->execute();
}

function update_msku_status($sku, $status)
{
    global $Connect;
    $stmt = $Connect->prepare("UPDATE tblfnsku SET amazon_status = ? WHERE MSKU = ?");
    $stmt->bind_param("ss", $status, $sku);
    $stmt->execute();
}

function create_notification($data)
{
    global $Connect;
    $stmt = $Connect->prepare("INSERT INTO tblnotifications (module, title, subtitle, content, severity, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss", $data['module'], $data['title'], $data['subtitle'], $data['content'], $data['severity']);
    $stmt->execute();
}

function handle_feed_result($store, $docId, $feedId)
{
    $report = fetch_feed_processing_report($store, $docId);

    foreach ($report as $row) {
        $sku = $row['sku'];
        $status = $row['processingStatus'];
        $msg = $row['resultDescription'] ?? 'No message';

        create_notification([
            'module' => 'listing',
            'title' => "$status: MSKU $sku",
            'subtitle' => "Feed $feedId",
            'content' => $msg,
            'severity' => $status === 'SUCCESS' ? 'success' : 'error'
        ]);

        $finalStatus = $status === 'SUCCESS' ? 'Existed' : 'Failed';
        update_msku_status($sku, $finalStatus);
    }
}

function fetch_feed_processing_report($store, $docId)
{
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $path = "/feeds/2021-06-30/documents/{$docId}";
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";

    $credentials = AWSCredentials($store);
    $accessToken = fetchAccessToken($credentials, false);

    $headers = buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, null, [], $endpoint, $canonicalHeaders);
    $headers['accept'] = 'application/json';
    $curlHeaders = array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers);

    $url = "{$endpoint}{$path}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);
    echo "Fetch feed";
    echo "<pre>";
    print_r($decoded);
    echo "</pre>";
    $downloadUrl = $decoded['url'];

    // Download and parse the actual report content (usually TSV or JSON)
    $compressed = file_get_contents($downloadUrl);
    $reportContent = gzdecode($compressed); // decompress

    if (!$reportContent) {
        echo "❌ Failed to decode GZIP feed content.\n";
        return [];
    }

    // Try to detect if content is JSON or TSV
    $decodedJson = json_decode($reportContent, true);
    if (is_array($decodedJson)) {
        echo "<pre>📄 Feed Returned JSON (not TSV):\n";
        print_r($decodedJson);
        echo "</pre>";

        // Optional: Handle JSON format results differently
        if (isset($decodedJson['issues'])) {
            foreach ($decodedJson['issues'] as $issue) {
                create_notification([
                    'module' => 'listing',
                    'title' => "{$issue['severity']}: {$issue['code']}",
                    'subtitle' => "Feed {$decodedJson['header']['feedId']}",
                    'content' => $issue['message'] ?? 'Unknown issue',
                    'severity' => strtolower($issue['severity']),
                ]);
            }
        }

        return []; // Skip TSV parsing
    }

    // If not JSON, fallback to TSV parsing
    return parse_feed_report($reportContent);
}

function parse_feed_report($content)
{
    $lines = explode("\n", trim($content));
    $headers = str_getcsv(array_shift($lines), "\t");

    $results = [];
    foreach ($lines as $line) {
        $row = array_combine($headers, str_getcsv($line, "\t"));
        if ($row)
            $results[] = $row;
    }
    return $results;
}


// ______ Utility Functions ______
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

function get_feed_status($store, $feedId)
{
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $path = "/feeds/2021-06-30/feeds/{$feedId}";
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";

    $credentials = AWSCredentials($store);
    $accessToken = fetchAccessToken($credentials, false);

    $headers = buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, null, [], $endpoint, $canonicalHeaders);
    $headers['accept'] = 'application/json';
    $curlHeaders = array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers);

    $url = "{$endpoint}{$path}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
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