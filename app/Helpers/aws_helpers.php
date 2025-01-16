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

if (!function_exists('fetchAccessToken')) {
    /**
     * Fetch an access token from Amazon's OAuth2 service.
     *
     * @param object $credentials Credentials object containing client_id, client_secret, and refresh_token.
     * @param bool $returnRaw Whether to return the raw response.
     * @return mixed The access token string, the full response, or false on failure.
     */
    function fetchAccessToken($credentials, $returnRaw = false)
    {
        try {
            // Prepare the POST fields
            $postfields = [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $credentials['refresh_token'],
            ];

            // Send the POST request with Content-Type header
            $response = Http::asForm()
                ->post(
                    env('AWS_AUTH_ENDPOINT', 'https://api.amazon.com/auth/o2/token'),
                    $postfields
                );

            // Check for response errors
            if ($response->failed()) {
                Log::error('Failed to fetch access token.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $decodedResponse = $response->json();

            return $returnRaw ? $decodedResponse : ($decodedResponse['access_token'] ?? false);
        } catch (\Exception $e) {
            Log::error("Error fetching access token: " . $e->getMessage());
            return false;
        }
    }
}
/*
if (!function_exists('fetchRDT')) {
    function fetchRDT($credentials, $accessToken, $jsonbody, $nextToken = null)
    {
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $path = "/tokens/2021-03-01/restrictedDataToken";
        $service = 'execute-api';
        $region = 'us-east-1';
        $method = 'POST';

        do {
            $headers = buildHeaders($credentials, $accessToken, $Method, $service, $region, $path);
            $url = "{$endpoint}{$path}" . buildQueryString($nextToken);

            $response = Http::withHeaders($headers)->post($url, $jsonbody);

            if ($response->failed()) {
                $httpcode = $response->status();
                if ($httpcode == 429) {
                    sleep(60);
                } elseif ($httpcode == 401) {
                    $accessToken = fetchRefreshToken($credentials);
                }
            }

            $data = $response->json();
            $nextToken = $data['pagination']['nextToken'] ?? null;
        } while ($nextToken);

        return $data;
    }
}
*/
if (!function_exists('getMerchantIDorSID')) {
    function getMerchantIDorSID($store)
    {
        $id = ($store == 'RT') ? 1 : 3;

        $result = DB::table('tblcompanydetails')->where('id', $id)->first();

        return $result ? $result->SID : null;
    }
}

if (!function_exists('fetchGrantlessAccessToken')) {
    function fetchGrantlessAccessToken($credentials, $scope)
    {
        $url = "https://api.amazon.com/auth/o2/token";
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'scope' => $scope
        ];

        $response = Http::asForm()->post($url, $data);

        if ($response->failed()) {
            die("Error fetching access token");
        }

        $tokenData = $response->json();
        return $tokenData['access_token'];
    }
}

if (!function_exists('buildQueryString')) {
    function buildQueryString($nextToken = null, $customParams = [])
    {
        // Build the base query string with RFC3986 encoding
        $query = http_build_query($customParams, '', '&', PHP_QUERY_RFC3986);

        // Add the nextToken if provided
        if (!empty($nextToken)) {
            $query .= ($query ? '&' : '') . 'nextToken=' . rawurlencode($nextToken);
        }

        return $query;
    }
}

if (!function_exists('buildHeaders')) {
    function buildHeaders($credentials, $accessToken, $method, $service, $region, $path, $nextToken, $customParams)
    {
        $amzDate = gmdate('Ymd\THis\Z');
        $signatureDetails = calculateSignature($credentials, $amzDate, $method, $service, $region, $path, $nextToken, $customParams);

        $authorizationHeader = "{$signatureDetails['algorithm']} Credential={$credentials['client_id']}/{$signatureDetails['dateStamp']}/{$signatureDetails['region']}/{$signatureDetails['service']}/aws4_request, SignedHeaders={$signatureDetails['signedHeaders']}, Signature={$signatureDetails['signature']}";

        return [
            "x-amz-date: {$amzDate}",
            "x-amz-access-token: {$accessToken}",
            "Authorization: {$authorizationHeader}"
        ];
    }
}

if (!function_exists('calculateSignature')) {
    function calculateSignature($credentials, $amzDate, $method, $service, $region, $path, $nextToken, $customParams)
    {
        $canonicalUri = $path;
        $canonicalQueryString = "";//buildQueryString($nextToken, $customParams); // Adjust as needed
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com\nx-amz-date:{$amzDate}\n";
        $signedHeaders = 'host;x-amz-date';
        $payloadHash = hash('sha256', ''); // Empty payload for GET request
        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $algorithm = 'AWS4-HMAC-SHA256';
        $dateStamp = substr($amzDate, 0, 8);
        $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request"; // Ensure proper format
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
}

if (!function_exists('getSignatureKey')) {
    function getSignatureKey($key, $dateStamp, $regionName, $serviceName)
    {
        $kSecret = 'AWS4' . $key;
        $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        return $kSigning;
    }
}


