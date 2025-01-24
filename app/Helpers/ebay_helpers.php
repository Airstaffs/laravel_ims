<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

if (!function_exists('EbayCredentials')) {
    /**
     * Retrieve eBay credentials as an array from the database.
     *
     * @return array|null Credentials array or null if not found.
     */
    function EbayCredentials()
    {
        try {
            $id = 3;

            // Fetch eBay credentials from the database
            $credentials = DB::table('tblapis')
                ->where('id', $id)
                ->select(['client_id', 'client_secret', 'access_token', 'refresh_token', 'expires_in'])
                ->first();

            if (!$credentials) {
                Log::error("No keys found for the given client ID: {$id}");
                return null;
            }

            return (array) $credentials;
        } catch (\Exception $e) {
            Log::error("Error retrieving credentials: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Retrieve an access token using the authorization code.
 *
 * @param string $authorizationCode
 * @return string|null Access token or null if an error occurs.
 */
function getAccessToken($authorizationCode)
{
    // Hardcoded URLs
    $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';
    $redirectUri = 'https://test.tecniquality.com/apis/ebay-callback';

    // Retrieve credentials
    $credentials = EbayCredentials();

    if (!$credentials) {
        Log::error('Failed to retrieve credentials for token request.');
        return null;
    }

    // Prepare request data
    $data = [
        'grant_type' => 'authorization_code',
        'code' => $authorizationCode,
        'redirect_uri' => $redirectUri,
    ];

    // Generate Basic Auth header
    $authHeader = base64_encode("{$credentials['client_id']}:{$credentials['client_secret']}");

    try {
        // Send the POST request to obtain the access token
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $authHeader,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($tokenUrl, $data);

        $results = $response->json();

        if ($response->successful() && isset($results['access_token'], $results['refresh_token'])) {
            // Save tokens to the database
            saveTokens($results);
            return $results['access_token'];
        } else {
            Log::error("Error obtaining access token: " . $response->body());
            return null;
        }
    } catch (\Exception $e) {
        Log::error("Error during token request: " . $e->getMessage());
        return null;
    }
}

/**
 * Save the access and refresh tokens to the database.
 *
 * @param array $tokens
 * @return void
 */
function saveTokens(array $tokens)
{
    try {
        DB::table('tblapis')
            ->where('id', 3)
            ->update([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_in' => $tokens['expires_in'],
                'updated_at' => now(),
            ]);
    } catch (\Exception $e) {
        Log::error("Error saving tokens: " . $e->getMessage());
    }
}
