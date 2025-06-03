<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

//$credentials = getUSPSCredentials($Connect);

if (!function_exists('USPSCredentials')) {
    /**
     * Retrieve AWS credentials for a given store.
     *
     * @param string $store The store identifier.
     * @return object|null Credentials object or null if not found.
     */
    function USPSCredentials()
    {
        try {
            $credentials = (array) DB::table('tblapis')->where('api_name', 'USPS')->first();

            if (!$credentials) {
                Log::error("No keys found for the given automation.}");
                return null;
            }

            return $credentials;
        } catch (\Exception $e) {
            Log::error("Error retrieving credentials: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getUSPSTrackingInfo')) {
    function getUSPSAccessToken($clientId, $clientSecret)
    {
        $url = 'https://api.usps.com/oauth2/v3/token';

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ];

        $postFields = http_build_query([
            'grant_type' => 'client_credentials',
            'scope' => 'tracking',
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERPWD, "$clientId:$clientSecret"); // Basic Auth
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($status === 200 && isset($data['access_token'])) {
            return $data['access_token'];
        } else {
            error_log("Token Error [$status]: $response");
            return false;
        }
    }
}