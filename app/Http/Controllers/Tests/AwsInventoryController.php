<?php

namespace App\Http\Controllers\Tests;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
require_once app_path('Helpers/aws_helpers.php');

class AwsInventoryController extends Controller
{
    public function fetchInventorySummary(Request $request)
    {
        // Validate request input
        $request->validate([
            'store' => 'required|string',
        ]);

        $store = $request->input('store');

        // Static query parameters
        $customParams = [
            'details' => true,
            'granularityType' => "Marketplace",
            'granularityId' => "ATVPDKIKX0DER",
            'marketplaceIds' => "ATVPDKIKX0DER",
        ];

        $nextToken = $request->input('nextToken', null);

        $endpoint = env('AWS_API_ENDPOINT', 'https://sellingpartnerapi-na.amazon.com');
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
        $accessToken = fetchAccessToken($credentials);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 500);
        }

        try {
            // Build headers using the helper function
            $headers = buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, $nextToken, $customParams);

            // Build query string using the helper function
            $queryString = buildQueryString($nextToken, $customParams);

            // Construct the full URL
            $url = "{$endpoint}{$path}?{$queryString}";

            // Make the HTTP request
            $response = Http::timeout(10)->withHeaders($headers)->get($url);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch inventory summary.',
                'headers' => $headers,
                'error' => $response->json(),
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during the API request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

