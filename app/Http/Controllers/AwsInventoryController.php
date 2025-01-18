<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
// require app_path('Helpers/aws_helpers.php');
require app_path('Helpers/aws_manual.php');



class AwsInventoryController extends Controller
{

    public function fetchInventorySummary(Request $request)
    {

        /*
        
        $request->validate([
            'store' => 'required|string',
        ]);

        $store = $request->input('store');

        $nextToken = null;

        $credentials = AWSCredentials($store);

        $accessToken = fetchrawrAccessToken($credentials, $returnRaw = false);
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $path = '/fba/inventory/v1/summaries';
        $service = 'execute-api';
        $region = 'us-east-1';

        $datas = fetchDataFromAPIrawr($credentials, $accessToken, $nextToken);

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch inventory summary.',
            'headers' => $accessToken,
            'error' => $datas,
        ]);
        

        */
        // Validate request input
        $request->validate([
            'store' => 'required|string',
        ]);

        $store = $request->input('store');

        // Static query parameters
        $customParams = [
            'details' => "true",
            'granularityType' => "Marketplace",
            'granularityId' => "ATVPDKIKX0DER",
            'marketplaceIds' => "ATVPDKIKX0DER"
        ];

        $nextToken = $request->input('nextToken', null);

        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $path = '/fba/inventory/v1/summaries';

        // Fetch AWS credentials for the store
        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return response()->json([
                'success' => false,
                'message' => 'No credentials found for the given store.',
            ], 500);
        }

        // Fetch access token
        $accessToken = fetchAccessToken($credentials, $returnRaw = false);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 500);
        }

        try {
            // Build headers using the helper function
            $headers = buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint);

            // Log the headers
            Log::info('Request headers:', $headers);

            // Build query string using the helper function
            $queryString = buildQueryString($nextToken, $customParams);

            // Construct the full URL
            $url = "{$endpoint}{$path}?{$queryString}";

            // Log the request details (headers, body, etc.) for debugging
            Log::info('Request details:', [
                'url' => $url,
                'headers' => $headers,
                'queryString' => $queryString
            ]);

            // Make the HTTP request
            $response = Http::timeout(50)->withHeaders($headers)->get($url);

            // Log the curl information (response details)
            $curlInfo = $response->handlerStats(); // This will give you cURL-like information

            Log::info('Curl Info:', $curlInfo);

            // Return the response with logs included
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                    'logs' => $curlInfo, // Add the log details here
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch inventory summary.',
                'headers' => $headers,
                'error' => $response->json(),
                'logs' => $curlInfo,
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during the API request.',
                'error' => $e->getMessage(),
                'logs' => $curlInfo ?? null, // If logs exist, return them
            ], 500);
        }




    }

    /*
        public function fetchInventorySummary(Request $request)
    {
        $store = $request->input('store');
        $customParams = [
            'details' => "true",
            'granularityType' => "Marketplace",
            'granularityId' => "ATVPDKIKX0DER",
            'marketplaceIds' => "ATVPDKIKX0DER",
        ];

        $nextToken = $request->input('nextToken', null);
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $path = '/fba/inventory/v1/summaries';

        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return response()->json(['success' => false, 'message' => 'No credentials found for the given store.'], 500);
        }

        $accessToken = fetchAccessToken($credentials);
        if (!$accessToken) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch access token.'], 500);
        }

        try {
            // Build headers using the helper function
            $headers = buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, $nextToken, $customParams);

            // Build query string using the helper function
            $queryString = buildQueryString($nextToken, $customParams);

            // Construct the full URL
            $url = "{$endpoint}{$path}?{$queryString}";

            // Log the cURL command (for debugging)
            $curlCommand = "curl -X GET '$url' \\
                -H 'Authorization: {$headers['Authorization']}' \\
                -H 'x-amz-date: {$headers['x-amz-date']}' \\
                -H 'x-amz-access-token: {$headers['x-amz-access-token']}'";

            Log::info('Generated cURL command:', ['curl_command' => $curlCommand]);

            // Make the HTTP request
            $response = Http::timeout(50)->withHeaders($headers)->get($url);

            // Return the response with the cURL command included
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                    'curl_command' => $curlCommand,  // Return the cURL command
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch inventory summary.',
                'error' => $response->json(),
                'curl_command' => $curlCommand,  // Return the cURL command
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during the API request.',
                'error' => $e->getMessage(),
                'curl_command' => $curlCommand ?? null,  // If curl command exists, return it
            ], 500);
        }
    }
    */
}



