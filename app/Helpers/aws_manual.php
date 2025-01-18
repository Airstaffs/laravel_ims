<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

if (!function_exists('AWSCredentials')) {
    /**
     * Retrieve AWS credentials for a given store.
     *
     * @param string $store The store identifier.
     * @return object|null Credentials object or null if not found.
     */
    function AWSCredentials($store)
    {
        try {
            $id = ($store === 'Renovar Tech') ? 1 : 2;

            $credentials = (array) DB::table('tblstores')->where('store_id', $id)->first();

            if (!$credentials) {
                Log::error("No keys found for the given client ID: {$id}");
                return null;
            }

            return $credentials;
        } catch (\Exception $e) {
            Log::error("Error retrieving credentials: " . $e->getMessage());
            return null;
        }
    }
}

function fetchrawrAccessToken($credentials, $returnRaw = false)
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

function buildHeadersrawr($credentials, $accessToken)
    {
        $amzDate = gmdate('Ymd\THis\Z');
        $signatureDetails = calculateSignaturerawr($credentials, $amzDate);

        return [
            "x-amz-date: {$amzDate}",
            "x-amz-access-token: {$accessToken}",
            "Authorization: {$signatureDetails['algorithm']} Credential={$credentials['client_id']}/{$signatureDetails['dateStamp']}/{$signatureDetails['region']}/{$signatureDetails['service']}/aws4_request, SignedHeaders={$signatureDetails['signedHeaders']}, Signature={$signatureDetails['signature']}"
        ];
    }

    function buildQueryStringrawr($nextToken = null)
    {

        $details = true;
        $granularityType = "Marketplace";
        $granularityId = "ATVPDKIKX0DER";
        $marketplaceIds = "ATVPDKIKX0DER";


        // $startdate = date("Y-m-d\TH:i:s\Z", strtotime("-1 days"));

        //$query = "details=true";
        $query = "&granularityType={$granularityType}&granularityId={$granularityId}&marketplaceIds={$marketplaceIds}";

        //$query .= "&startDateTime={$lastUpdatedTime}";

        if (!empty($nextToken)) {
            $query .= "&nextToken=" . urlencode($nextToken);
        }

        return $query;
    }

    function calculateSignaturerawr($credentials, $amzDate)
    {
        $path = '/fba/inventory/v1/summaries';
        $service = 'execute-api';
        $region = 'us-east-1';

        // Step 1: Create Canonical Request
        $method = 'GET';
        $canonicalUri = $path;
        $canonicalQueryString = buildQueryStringrawr();
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
        $signatureKey = getSignatureKeyrawr($credentials['client_secret'], $dateStamp, $region, $service);
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

    function getSignatureKeyrawr($key, $dateStamp, $regionName, $serviceName)
    {
        $kSecret = 'AWS4' . $key;
        $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        return $kSigning;
    }

    function fetchDataFromAPIrawr($credentials, $accessToken, $nextToken = null)
    {

        // echo $nextToken;
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $path = '/fba/inventory/v1/summaries';
        $service = 'execute-api';
        $region = 'us-east-1';
        $allData = [];
        $countValue = 0;
        $Counter = 0;

        do {
            $headers = buildHeadersrawr($credentials, $accessToken);
            $url = "{$endpoint}{$path}?" . buildQueryString($nextToken);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($ch);

            $data = json_decode($result, true);

            $http = curl_getinfo($ch);
            // print_r($http);

            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            //echo "HTTP Code: " . $httpcode . "<br>";

            if ($httpcode !== 200) {
                //echo "Httpcode $httpcode <br>";
            }

            // handles the error code 429
            if ($httpcode == 429) {
                //echo "Rate limit exceeded, retrying in 60 seconds...\n";
                sleep(30);
                // Don't forget to close the cURL session before retrying
                curl_close($ch);
            } else if ($httpcode == 401) {
                //echo "Unauthorized Access Retrying!\n";

                $accessToken = fetchAccessToken($credentials);

                if ($accessToken) {
                    $_SESSION['access_token'] = $accessToken;
                    // echo "Access Token: " . $accessToken . "\n";
                } else {
                    //echo "Access token not found in the response.\n";
                }

                curl_close($ch);
            } else {
                // If the response code is not 429, break out of the loop.
                break;
            }
        } while ($httpcode == 429 || $httpcode == 401);



        $nextToken = $data['pagination']['nextToken'] ?? null;


        // echo $nextToken;
        return $data;
    }