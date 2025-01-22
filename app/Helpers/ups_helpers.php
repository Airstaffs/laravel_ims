<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

//$credentials = getUPSCredentials($Connect);

if (!function_exists('UPSCredentials')) {
    /**
     * Retrieve AWS credentials for a given store.
     *
     * @param string $store The store identifier.
     * @return object|null Credentials object or null if not found.
     */
    function UPSCredentials()
    {
        try {
            // retrieve UPS credentials
            $id = 2;

            $credentials = (array) DB::table('tblapis')->where('id', $id)->first();

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

function upsRefresher()
{
    // ID for UPS
    $id = 2;

    // Fetch the API details from the database
    $apiDetails = DB::table('tblpais')->where('id', $id)->first();

    if ($apiDetails) {
        $currentTime = time();
        $expiration = $apiDetails->expires_in - 1800; // Subtract 30 minutes

        // Execute only if the current time is greater than the expiration time
        if ($currentTime > $expiration) {
            $payload = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $apiDetails->refresh_token,
            ];

            $credentials = base64_encode("{$apiDetails->client_id}:{$apiDetails->client_secret}");

            // Send the POST request to refresh the token
            $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => "Basic $credentials",
            ])->asForm()->post('https://onlinetools.ups.com/security/v1/oauth/refresh', $payload);

            if ($response->successful() && $response->json('access_token')) {
                // Update the database with the new tokens and expiration time
                DB::table('tblpais')->where('id', $id)->update([
                    'access_token' => $response->json('access_token'),
                    'refresh_token' => $response->json('refresh_token'),
                    'expires_in' => time() + $response->json('expires_in'),
                ]);
            }
        }
    }
}

