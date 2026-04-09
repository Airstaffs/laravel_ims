<?php
ini_set('max_execution_time', 600);
ini_set('memory_limit', '512M');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Database credentials
$servertype = "laravel_ims";

// For tblstores credential lookup
$store = 'AllRenewed';

// For tblfnsku matching
$fnskuStoreName = 'Allrenewed';

$Connect = connectDatabase($servertype);
$conn = $Connect;

$credentials = getAWSCredentials($Connect, $store);
$accessToken = fetchAccessToken($credentials);

if (!isset($credentials['client_id']) || !isset($credentials['client_secret'])) {
    die("Invalid keys in database.");
}

$inputDate = $_POST['date'] ?? "2024-05-01";
$formattedDate = formatDateToISO8601($inputDate);

$currentDate = date('Y-m-d H:i:s');
$randomDays = rand(5, 20);
$random = rand(0, 4);

$previousDate = date('Y-m-d H:i:s', strtotime("-$randomDays days", strtotime($currentDate)));
$iso8601Date = date('Y-m-d\TH:i:s\Z', strtotime($previousDate));

$end_sheesh = date('Y-m-d H:i:s', strtotime("-$random days", strtotime($currentDate)));
$endDate = date('Y-m-d\TH:i:s\Z', strtotime($end_sheesh));

$json = [
    "reportType" => "GET_FBA_MYI_ALL_INVENTORY_DATA",
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

$httpcode = $data_id['httpcode'] ?? null;
ErrorChecker($Connect, $data_id);

do {
    $reportId = $data_id['reportId'] ?? null;

    if (empty($reportId)) {
        die("No reportId returned from Amazon.");
    }

    $path = "/reports/2021-06-30/reports/{$reportId}";
    $status = fetchdetailsID($credentials, $accessToken, $nextToken = null, $path);

    echo "<br>step 2<br>";
    echo "<pre>";
    print_r($status);
    echo "</pre>";

    sleep(4);

} while (
    isset($status['processingStatus']) &&
    (
        $status['processingStatus'] == 'IN_QUEUE' ||
        $status['processingStatus'] == 'IN_PROGRESS'
    )
);

if (($status['processingStatus'] ?? '') == 'DONE') {
    echo "<br><br>Status<br><br>";
    echo "<pre>";
    print_r($status);
    echo "</pre>";

    $jsonbody = "";

    if (!empty($status['reportDocumentId'])) {
        $documentid = $status['reportDocumentId'];
        $path2 = "/reports/2021-06-30/documents/{$documentid}";
        echo $documentid;
    } else {
        die("Report finished but no reportDocumentId was returned.");
    }

    $data1 = fetchSuccessDetails($credentials, $accessToken, $jsonbody, $nextToken = null, $path2);

    echo "<br>step fetch success<br>";
    echo "<pre>";
    print_r($data1);
    echo "</pre>";

    $compressionAlgorithm = $data1['compressionAlgorithm'] ?? "";
    $url = $data1['url'] ?? '';

    if (empty($url)) {
        die("No download URL returned for report document.");
    }

    $retrievedData = download($url, $compressionAlgorithm);

    echo "<pre>";
    echo htmlentities($retrievedData);
    echo "</pre>";

    $rows = processRetrievedData($Connect, $retrievedData, false);
    $mskuTotals = aggregatePerMsku($rows, false);
    updateMskuTotals($Connect, $mskuTotals, $fnskuStoreName);

} else if (($status['processingStatus'] ?? '') == 'CANCELLED') {
    echo "<br> CANCELLED!";
} else if (($status['processingStatus'] ?? '') == 'FATAL') {
    echo "<br><br>FATAL!<br><br>";
    echo "<pre>";
    print_r($status);
    echo "</pre>";

    $jsonbody = "";

    if (!empty($status['reportDocumentId'])) {
        $documentid = $status['reportDocumentId'];
        $path2 = "/reports/2021-06-30/documents/{$documentid}";
        echo $documentid;

        $data1 = fetchSuccessDetails($credentials, $accessToken, $jsonbody, $nextToken = null, $path2);

        echo "<br>step fetch success<br>";
        echo "<pre>";
        print_r($data1);
        echo "</pre>";

        $compressionAlgorithm = $data1['compressionAlgorithm'] ?? "";
        $fatalUrl = $data1['url'] ?? '';

        if (!empty($fatalUrl)) {
            $retrievedData = download($fatalUrl, $compressionAlgorithm);
            echo "<pre>";
            echo htmlentities($retrievedData);
            echo "</pre>";
        }
    }
}

// =====================================================================================================================
// FUNCTIONS
// =====================================================================================================================

function ErrorChecker($Connect, $data)
{
    if (isset($data['errors']) && !empty($data['errors'])) {
        $httpcode = $data['httpcode'] ?? '';
        $errorMessage = $Connect->real_escape_string($data['errors'][0]['message'] ?? 'Unknown error');
        $errorCode = $Connect->real_escape_string($data['errors'][0]['code'] ?? 'UNKNOWN');

        $sql = "INSERT INTO cron_alerts
                SET cron_description = 'AWS_REPORTS_RETURN_CRON',
                    status = '{$httpcode}',
                    message = '{$errorMessage} | Code: {$errorCode}'";

        $Connect->query($sql);
        die("Error Execution!");
    }
}

function formatDateToISO8601($date)
{
    $dateTime = DateTime::createFromFormat('Y-m-d', $date);

    if ($dateTime === false) {
        die("Invalid date format. Please use 'YYYY-MM-DD'.");
    }

    return $dateTime->format('Y-m-d\TH:i:s\Z');
}

function fetch_id($credentials, $accessToken, $jsonbody, $nextToken = null, $path)
{
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $service = 'execute-api';
    $region = 'us-east-1';
    $method = 'POST';
    global $additionalurl;

    if (isset($additionalurl)) {
        $path .= $additionalurl;
    }

    do {
        do {
            $headers = buildHeaders($credentials, $accessToken, $path, $region, $service, $method, $jsonbody);
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

            if ($httpcode == 429) {
                sleep(60);
                curl_close($ch);
            } else if ($httpcode == 401) {
                $accessToken = fetchRefreshToken($credentials);

                if ($accessToken) {
                    $_SESSION['access_token'] = $accessToken;
                }

                curl_close($ch);
            }
        } while ($httpcode == 429 || $httpcode == 401);

        curl_close($ch);

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

function buildHeaders($credentials, $accessToken, $path, $region, $service, $method, $jsonbody = '')
{
    $amzDate = gmdate('Ymd\THis\Z');
    $signatureDetails = calculateSignature($credentials, $amzDate, $path, $region, $service, $method, $jsonbody);

    return [
        "Content-Type: application/json",
        "x-amz-date: {$amzDate}",
        "x-amz-access-token: {$accessToken}",
        "Authorization: {$signatureDetails['algorithm']} Credential={$credentials['client_id']}/{$signatureDetails['dateStamp']}/{$signatureDetails['region']}/{$signatureDetails['service']}/aws4_request, SignedHeaders={$signatureDetails['signedHeaders']}, Signature={$signatureDetails['signature']}"
    ];
}

function calculateSignature($credentials, $amzDate, $path, $region, $service, $method, $jsonbody = '')
{
    $canonicalUri = $path;
    $canonicalQueryString = buildQueryString();
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com\nx-amz-date:{$amzDate}\n";
    $signedHeaders = 'host;x-amz-date';

    if ($method === 'POST') {
        if (!is_string($jsonbody)) {
            $jsonbody = json_encode($jsonbody);
        }
        $payloadHash = hash('sha256', $jsonbody);
    } else {
        $payloadHash = hash('sha256', '');
    }

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
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $service = 'execute-api';
    $region = 'us-east-1';
    $method = 'GET';
    global $additionalurl;

    if (isset($additionalurl)) {
        $path .= $additionalurl;
    }

    do {
        do {
            $headers = buildHeaders($credentials, $accessToken, $path, $region, $service, $method, '');
            $url = "{$endpoint}{$path}" . buildQueryString($nextToken);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);

            $result = curl_exec($ch);
            $data = json_decode($result, true);

            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $data['http_code'] = $httpcode;

            if ($httpcode == 429) {
                sleep(60);
                curl_close($ch);
            } else if ($httpcode == 401) {
                $accessToken = fetchRefreshToken($credentials);

                if ($accessToken) {
                    $_SESSION['access_token'] = $accessToken;
                }

                curl_close($ch);
            }
        } while ($httpcode == 429 || $httpcode == 401);

        curl_close($ch);

        $data = json_decode($result, true);
        $nextToken = $data['pagination']['nextToken'] ?? null;

    } while ($nextToken);

    $data['httpcode'] = $httpcode;
    return $data;
}

function fetchSuccessDetails($credentials, $accessToken, $jsonbody, $nextToken = null, $path)
{
    $endpoint = 'https://sellingpartnerapi-na.amazon.com';
    $service = 'execute-api';
    $region = 'us-east-1';
    $method = 'GET';
    global $additionalurl;

    if (isset($additionalurl)) {
        $path .= $additionalurl;
    }

    do {
        do {
            $headers = buildHeaders($credentials, $accessToken, $path, $region, $service, $method, '');
            $url = "{$endpoint}{$path}" . buildQueryString($nextToken);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);

            $result = curl_exec($ch);
            $data = json_decode($result, true);

            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $data['http_code'] = $httpcode;

            if ($httpcode == 429) {
                sleep(60);
                curl_close($ch);
            } else if ($httpcode == 401) {
                $accessToken = fetchRefreshToken($credentials);

                if ($accessToken) {
                    $_SESSION['access_token'] = $accessToken;
                }

                curl_close($ch);
            }
        } while ($httpcode == 429 || $httpcode == 401);

        curl_close($ch);

        $data = json_decode($result, true);
        $nextToken = $data['pagination']['nextToken'] ?? null;

    } while ($nextToken);

    $data['httpcode'] = $httpcode;
    return $data;
}

function download($url, $compressionAlgorithm)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return "Error: " . $err;
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode != 200) {
        return "Call to download content was unsuccessful with response code: $statusCode";
    }

    $algo = strtolower(trim((string) $compressionAlgorithm));

    if ($algo === 'gzip' || $algo === '' || $algo === null) {
        $decoded = @gzdecode($response);
        if ($decoded !== false) {
            return $decoded;
        }
        return $response;
    }

    if ($algo === 'bzip2' || $algo === 'bzip') {
        $decoded = @bzdecompress($response);
        if ($decoded !== false) {
            return $decoded;
        }
        return $response;
    }

    return $response;
}

function processRetrievedData($Connect, $retrievedData, $debug = false)
{
    if (substr($retrievedData, 0, 3) === "\xEF\xBB\xBF") {
        $retrievedData = substr($retrievedData, 3);
    }

    $retrievedData = trim($retrievedData);

    if ($retrievedData === '') {
        if ($debug) {
            echo "<p>No content in report file.</p>";
        }
        return [];
    }

    $lines = preg_split("/\r\n|\n|\r/", $retrievedData);

    if (empty($lines)) {
        if ($debug) {
            echo "<p>No lines found in report file.</p>";
        }
        return [];
    }

    $headerLine = null;
    $headerIndex = null;

    foreach ($lines as $i => $line) {
        $lineTrimmed = trim($line);
        if ($lineTrimmed !== '') {
            $headerLine = $lineTrimmed;
            $headerIndex = $i;
            break;
        }
    }

    if ($headerLine === null) {
        if ($debug) {
            echo "<p>No header line found in report.</p>";
        }
        return [];
    }

    $headers = explode("\t", $headerLine);
    $headers = array_map('trim', $headers);

    $headerCount = count($headers);
    $data = [];

    if ($debug) {
        echo "<h3>Report Headers</h3><pre>";
        print_r($headers);
        echo "</pre>";
    }

    for ($i = $headerIndex + 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);

        if ($line === '') {
            continue;
        }

        $fields = explode("\t", $line);

        if (count($fields) < $headerCount) {
            $fields = array_pad($fields, $headerCount, '');
        }

        if (count($fields) > $headerCount) {
            $fields = array_slice($fields, 0, $headerCount);
        }

        $fields = array_map('trim', $fields);

        if (count($fields) !== $headerCount) {
            if ($debug) {
                echo "<p>Field mismatch on line $i: got " . count($fields) . " expected $headerCount</p>";
                echo "<pre>" . htmlentities($line) . "</pre>";
            }
            continue;
        }

        $row = array_combine($headers, $fields);
        if ($row === false) {
            if ($debug) {
                echo "<p>array_combine failed on line $i</p>";
            }
            continue;
        }

        $data[] = $row;
    }

    if ($debug) {
        echo "<p>Parsed FBA Manage Inventory Report Rows<br>Total rows: " . count($data) . "</p>";
    }

    return $data;
}

function findRowValueInsensitive($row, $targetKey)
{
    foreach ($row as $key => $value) {
        $normalizedKey = strtolower(trim($key));
        $normalizedKey = ltrim($normalizedKey, "\xEF\xBB\xBF");
        if ($normalizedKey === strtolower($targetKey)) {
            return trim((string)$value);
        }
    }

    return '';
}

function aggregatePerMsku($rows, $debug = false)
{
    $mskuTotals = [];

    if ($debug) {
        echo "<h3>Aggregate Per MSKU Debug</h3>";
        echo "<p>Total rows received: " . count($rows) . "</p>";
        if (!empty($rows)) {
            echo "<pre>First row keys:\n";
            print_r(array_keys($rows[0]));
            echo "</pre>";
        }
    }

    foreach ($rows as $idx => $row) {
        $msku = trim($row['seller-sku'] ?? '');
        if ($msku === '') {
            $msku = findRowValueInsensitive($row, 'seller-sku');
        }

        if ($msku === '') {
            if ($debug && $idx < 10) {
                echo "<p>Row {$idx} has no seller-sku, skipping.</p>";
            }
            continue;
        }

        $fulfillable = (int)(findRowValueInsensitive($row, 'afn-fulfillable-quantity') ?: 0);
        $inboundWorking = (int)(findRowValueInsensitive($row, 'afn-inbound-working-quantity') ?: 0);
        $inboundShipped = (int)(findRowValueInsensitive($row, 'afn-inbound-shipped-quantity') ?: 0);
        $inboundReceiving = (int)(findRowValueInsensitive($row, 'afn-inbound-receiving-quantity') ?: 0);
        $reserved = (int)(findRowValueInsensitive($row, 'afn-reserved-quantity') ?: 0);

        if (!isset($mskuTotals[$msku])) {
            $mskuTotals[$msku] = [
                'fba_fulfillable_quantity' => 0,
                'fba_inbound_working_quantity' => 0,
                'fba_inbound_shipped_quantity' => 0,
                'fba_inbound_receiving_quantity' => 0,
                'fba_reserved_quantity' => 0,
                'fba_total_quantity' => 0,
            ];
        }

        $mskuTotals[$msku]['fba_fulfillable_quantity'] += $fulfillable;
        $mskuTotals[$msku]['fba_inbound_working_quantity'] += $inboundWorking;
        $mskuTotals[$msku]['fba_inbound_shipped_quantity'] += $inboundShipped;
        $mskuTotals[$msku]['fba_inbound_receiving_quantity'] += $inboundReceiving;
        $mskuTotals[$msku]['fba_reserved_quantity'] += $reserved;
        $mskuTotals[$msku]['fba_total_quantity'] += ($fulfillable + $inboundWorking + $inboundShipped + $inboundReceiving + $reserved);

        if ($debug && $idx < 10) {
            echo "<p>Row {$idx} MSKU {$msku} aggregated.</p>";
        }
    }

    if ($debug) {
        echo "<p>Unique MSKUs aggregated: " . count($mskuTotals) . "</p>";
        echo "<pre>";
        print_r(array_slice($mskuTotals, 0, 10, true));
        echo "</pre>";
    }

    return $mskuTotals;
}

function updateMskuTotals($Connect, $mskuTotals, $storeName)
{
    if (empty($mskuTotals)) {
        echo "<p>No MSKU totals to update.</p>";
        return;
    }

    $sql = "UPDATE tblfnsku
            SET
                fba_fulfillable_quantity = ?,
                fba_inbound_working_quantity = ?,
                fba_inbound_shipped_quantity = ?,
                fba_inbound_receiving_quantity = ?,
                fba_reserved_quantity = ?,
                fba_total_quantity = ?,
                fba_quantity_updated_at = NOW()
            WHERE MSKU = ?
              AND storename = ?";

    $stmt = $Connect->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $Connect->error);
    }

    $Connect->begin_transaction();

    $processed = 0;
    $notMatched = [];

    foreach ($mskuTotals as $msku => $qty) {
        $fulfillable = (int)$qty['fba_fulfillable_quantity'];
        $inboundWorking = (int)$qty['fba_inbound_working_quantity'];
        $inboundShipped = (int)$qty['fba_inbound_shipped_quantity'];
        $inboundReceiving = (int)$qty['fba_inbound_receiving_quantity'];
        $reserved = (int)$qty['fba_reserved_quantity'];
        $total = (int)$qty['fba_total_quantity'];

        $stmt->bind_param(
            'iiiiiiss',
            $fulfillable,
            $inboundWorking,
            $inboundShipped,
            $inboundReceiving,
            $reserved,
            $total,
            $msku,
            $storeName
        );

        if (!$stmt->execute()) {
            echo "<p>Error updating MSKU {$msku}: " . htmlspecialchars($stmt->error) . "</p>";
            continue;
        }

        if ($stmt->affected_rows > 0) {
            $processed++;
        } else {
            $notMatched[] = $msku;
        }
    }

    $Connect->commit();
    $stmt->close();

    echo "<p>Updated {$processed} tblfnsku rows for store {$storeName}.</p>";

    if (!empty($notMatched)) {
        echo "<p>MSKUs not matched in tblfnsku (" . count($notMatched) . "):</p>";
        echo "<pre>";
        print_r($notMatched);
        echo "</pre>";
    }
}

function connectDatabase($servertype)
{
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
        $username = 'ims_ims';
        $password = 'Imspassword2025';
        $database = 'ims_ims';
    } else if ($servertype === "vps-automation") {
        $hostname = 'localhost';
        $username = 'ims_automation';
        $password = 'Imsautomation2025';
        $database = 'ims_ims';
    } else if ($servertype === "laravel_ims") {
        $hostname = 'localhost';
        $username = 'imsv2_dbims_user';
        $password = 'Imsv2_dbims_user';
        $database = 'imsv2_dbims';
    } else {
        exit("Input Server type! In server file line 46.");
    }

    $db = new mysqli($hostname, $username, $password, $database);

    if ($db->connect_error) {
        die("Connection failed: " . $db->connect_error);
    }

    return $db;
}

function getAWSCredentials($db, $store)
{
    if ($store == 'RenovarTech') {
        $id = 6;
    } else if ($store == 'AllRenewed') {
        $id = 10;
    } else {
        die("Unknown store: " . htmlspecialchars($store));
    }

    $sql = "SELECT client_id, client_secret, refresh_token FROM tblstores WHERE store_id = $id";
    $result = $db->query($sql);
    $row = $result ? $result->fetch_assoc() : null;

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

    if ($response === false) {
        die('cURL Error: ' . curl_error($ch));
    }

    curl_close($ch);

    $decodedResponse = json_decode($response, true);

    if ($returnRaw) {
        return $decodedResponse;
    }

    return $decodedResponse['access_token'] ?? false;
}

function fetchRefreshToken($credentials)
{
    return fetchAccessToken($credentials);
}