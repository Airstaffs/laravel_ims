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

function upsRefresher($bypassTimeCheck = false) // Add a parameter for bypassing time validation
{
    // ID for UPS
    $id = 2;

    try {
        // Fetch the API details from the database
        $apiDetails = DB::table('tblapis')->where('id', $id)->first();

        if ($apiDetails) {
            $currentTime = time();
            $expiration = $apiDetails->expires_in - 1800; // Subtract 30 minutes

            // Log current time and expiration check
            Log::info("UPS Refresher: Current time: {$currentTime}, Expiration time: {$expiration}");

            // Check if bypass is enabled or token is expired
            if ($bypassTimeCheck || $currentTime > $expiration) {
                if ($bypassTimeCheck) {
                    Log::info('UPS Refresher: Time validation bypassed for testing.');
                } else {
                    Log::info('UPS Refresher: Token is expired or about to expire. Refreshing...');
                }

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

                // Log the response status and body
                Log::info('UPS Refresher: Response status: ' . $response->status());
                Log::debug('UPS Refresher: Response body: ', $response->json());

                if ($response->successful() && $response->json('access_token')) {
                    // Update the database with the new tokens and expiration time
                    DB::table('tblapis')->where('id', $id)->update([
                        'access_token' => $response->json('access_token'),
                        'refresh_token' => $response->json('refresh_token'),
                        'expires_in' => time() + $response->json('expires_in'),
                    ]);

                    Log::info('UPS Refresher: Tokens refreshed and updated in the database successfully.');
                } else {
                    Log::error('UPS Refresher: Failed to refresh tokens. Response: ', $response->json());
                }
            } else {
                Log::info('UPS Refresher: Token is still valid. No action needed.');
            }
        } else {
            Log::warning('UPS Refresher: No API details found for the given ID.');
        }
    } catch (\Exception $e) {
        // Log any unexpected errors
        Log::error('UPS Refresher: An error occurred: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
    }
}


