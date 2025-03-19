<?php

namespace App\Http\Controllers\Amzn;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;


require base_path('app/Helpers/aws_helpers.php');

class FBAShipmentController extends Controller
{
    public function step1_createShipment(Request $request)
    {
        $request->validate([
            'store' => 'nullable|string',
            'destinationMarketplace' => 'nullable|string',
            'nextToken' => 'nullable|string',
            'shipmentID' => 'nullable|string'
        ]);

        $store = $request->input('store', 'Renovar Tech');
        $nextToken = $request->input('nextToken', null);
        $destinationmarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $shipmentID = $request->input('shipmentID', 'FBA17YTXZSKB');

        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/inbound/fba/2024-03-20/inboundPlans';
        $customParams = [];

        $companydetails = $this->fetchCompanyDetails();

        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        // Generate JSON payload
        $jsonData = $this->JsonCreation('step1', $companydetails, 'ATVPDKIKX0DER', $shipmentID);

        // Check if JSON encoding failed
        if ($jsonData === false) {
            Log::error('JSON Encoding Failed:', ['error' => json_last_error_msg()]);
            return response()->json(['success' => false, 'message' => 'JSON encoding error'], 500);
        }


        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return response()->json([
                'success' => false,
                'message' => 'No credentials found for the given store.',
            ], 500);
        }

        $accessToken = fetchAccessToken($credentials, $returnRaw = false);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 500);
        }

        try {
            // Build headers using the helper function
            $headers = buildHeaders($credentials, $accessToken, 'POST', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
            // Ensure Content-Type is set
            $headers['Content-Type'] = 'application/json';
            $headers['accept'] = 'application/json';

            // Log the headers
            Log::info('Request headers:', $headers);

            // Build query string using the helper function
            $queryString = buildQueryString($nextToken, $customParams);

            // Construct the full URL
            $url = "{$endpoint}{$path}{$queryString}";

            // Log the request details (headers, body, etc.) for debugging
            Log::info('Request details:', [
                'url' => $url,
                'headers' => $headers,
                'queryString' => $queryString,
                'body' => $jsonData
            ]);

            // Make the HTTP request (change GET to POST)
            $response = Http::timeout(50)
                ->withHeaders($headers)
                ->withBody($jsonData, 'application/json') // Ensure JSON is properly sent
                ->post($url);

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
                'message' => 'Successfully sent but error.',
                'headers' => $headers,
                'error' => $response->json(),
                'body-payload' => json_decode($jsonData, true), // Decode JSON before returning
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


    public function step2a_generate_packing(Request $request)
    {
        $request->validate([
            'store' => 'nullable|string',
            'destinationMarketplace' => 'nullable|string',
            'nextToken' => 'nullable|string',
            'shipmentID' => 'nullable|string'
        ]);

        $store = $request->input('store', 'Renovar Tech');
        $nextToken = $request->input('nextToken', null);
        $destinationmarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $shipmentID = $request->input('shipmentID', 'FBA17YTXZSKB');
        $inboundplanid = $request->input('inboundplanid', 'wfb8b1d7e2-4629-4f3d-ac6e-bffdb59216d4');


        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/inbound/fba/2024-03-20/inboundPlans/' . $inboundplanid . '/packingOptions';
        $customParams = [];

        $companydetails = $this->fetchCompanyDetails();

        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        // Generate JSON payload
        $jsonData = $this->JsonCreation('step2a', $companydetails, 'ATVPDKIKX0DER', $shipmentID);

        // Check if JSON encoding failed
        if ($jsonData === false) {
            Log::error('JSON Encoding Failed:', ['error' => json_last_error_msg()]);
            return response()->json(['success' => false, 'message' => 'JSON encoding error'], 500);
        }


        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return response()->json([
                'success' => false,
                'message' => 'No credentials found for the given store.',
            ], 500);
        }

        $accessToken = fetchAccessToken($credentials, $returnRaw = false);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 500);
        }

        try {
            // Build headers using the helper function
            $headers = buildHeaders($credentials, $accessToken, 'POST', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
            // Ensure Content-Type is set
            $headers['Content-Type'] = 'application/json';
            $headers['accept'] = 'application/json';

            // Log the headers
            Log::info('Request headers:', $headers);

            // Build query string using the helper function
            $queryString = buildQueryString($nextToken, $customParams);

            // Construct the full URL
            $url = "{$endpoint}{$path}{$queryString}";

            // Log the request details (headers, body, etc.) for debugging
            Log::info('Request details:', [
                'url' => $url,
                'headers' => $headers,
                'queryString' => $queryString,
                'body' => json_decode($jsonData, true) // Decode before logging
            ]);

            // Make the HTTP request (POST)
            $response = Http::timeout(50)
                ->withHeaders($headers)
                ->withBody($jsonData, 'application/json') // Ensure JSON is properly sent
                ->post($url);

            // Log the cURL information (response details)
            $curlInfo = $response->handlerStats();
            Log::info('Curl Info:', $curlInfo);

            // Check if request was successful
            if ($response->successful()) {
                $data = $response->json(); // Parse JSON response

                // Extract operationId
                $operationId = $data['operationId'] ?? null;

                // If operationId exists, call getOperationStatus()
                if ($operationId) {
                    Log::info("Tracking operation: {$operationId}");

                    // Call the operation status function
                    $operationStatusResponse = $this->getOperationStatus($store, $destinationmarketplace, $operationId);

                    // Return the operation response
                    return response()->json([
                        'success' => true,
                        'operationId' => $operationId,
                        'operationStatus' => $operationStatusResponse->getData(true), // Get operation tracking response
                        'logs' => $curlInfo,
                    ]);
                }

                // If no operationId, return success response but indicate missing operation tracking
                return response()->json([
                    'success' => true,
                    'message' => 'Operation initiated but no operationId returned.',
                    'data' => $data,
                    'logs' => $curlInfo,
                ]);
            }

            // If request failed
            return response()->json([
                'success' => false,
                'message' => 'Successfully sent but API returned an error.',
                'headers' => $headers,
                'error' => $response->json(),
                'body-payload' => json_decode($jsonData, true), // Decode JSON before returning
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

    public function step2b_list_packing_options(Request $request)
    {
        $request->validate([
            'store' => 'nullable|string',
            'destinationMarketplace' => 'nullable|string',
            'nextToken' => 'nullable|string',
            'shipmentID' => 'nullable|string'
        ]);

        $store = $request->input('store', 'Renovar Tech');
        $nextToken = $request->input('nextToken', null);
        $destinationmarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $shipmentID = $request->input('shipmentID', 'FBA17YTXZSKB');
        $inboundplanid = $request->input('inboundplanid', 'wffacd6859-1abf-499e-82ff-76497a17ae63');


        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/inbound/fba/2024-03-20/inboundPlans/' . $inboundplanid . '/packingOptions';
        $customParams = [];

        $companydetails = $this->fetchCompanyDetails();

        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        // Generate JSON payload
        $jsonData = $this->JsonCreation('step2a', $companydetails, 'ATVPDKIKX0DER', $shipmentID);

        // Check if JSON encoding failed
        if ($jsonData === false) {
            Log::error('JSON Encoding Failed:', ['error' => json_last_error_msg()]);
            return response()->json(['success' => false, 'message' => 'JSON encoding error'], 500);
        }


        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return response()->json([
                'success' => false,
                'message' => 'No credentials found for the given store.',
            ], 500);
        }

        $accessToken = fetchAccessToken($credentials, $returnRaw = false);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 500);
        }

        try {
            // Build headers using the helper function
            $headers = buildHeaders($credentials, $accessToken, 'POST', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
            // Ensure Content-Type is set
            $headers['Content-Type'] = 'application/json';
            $headers['accept'] = 'application/json';

            // Log the headers
            Log::info('Request headers:', $headers);

            // Build query string using the helper function
            $queryString = buildQueryString($nextToken, $customParams);

            // Construct the full URL
            $url = "{$endpoint}{$path}{$queryString}";

            // Log the request details (headers, body, etc.) for debugging
            Log::info('Request details:', [
                'url' => $url,
                'headers' => $headers,
                'queryString' => $queryString,
                // 'body' => json_decode($jsonData, true) // Decode before logging
            ]);

            // Make the HTTP request (POST)
            $response = Http::timeout(50)
                ->withHeaders($headers)
                // ->withBody($jsonData, 'application/json') // Ensure JSON is properly sent
                ->get($url);

            // Log the cURL information (response details)
            $curlInfo = $response->handlerStats();
            Log::info('Curl Info:', $curlInfo);

            // Check if request was successful
            if ($response->successful()) {
                $data = $response->json(); // Parse JSON response

                // Extract operationId
                $operationId = $data['operationId'] ?? null;

                // If operationId exists, call getOperationStatus()
                if ($operationId) {
                    Log::info("Tracking operation: {$operationId}");

                    // Call the operation status function
                    $operationStatusResponse = $this->getOperationStatus($store, $destinationmarketplace, $operationId);

                    // Return the operation response
                    return response()->json([
                        'success' => true,
                        'operationId' => $operationId,
                        'operationStatus' => $operationStatusResponse->getData(true), // Get operation tracking response
                        'logs' => $curlInfo,
                    ]);
                }

                // If no operationId, return success response but indicate missing operation tracking
                return response()->json([
                    'success' => true,
                    'message' => 'Operation Step 2b Success.',
                    'data' => $data,
                    'logs' => $curlInfo,
                ]);
            }

            // If request failed
            return response()->json([
                'success' => false,
                'message' => 'Successfully sent but API returned an error.',
                'headers' => $headers,
                'error' => $response->json(),
                'body-payload' => json_decode($jsonData, true), // Decode JSON before returning
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

    public function step2c_list_items_by_packing_options(Request $request)
    {
        $request->validate([
            'store' => 'nullable|string',
            'destinationMarketplace' => 'nullable|string',
            'nextToken' => 'nullable|string',
            'shipmentID' => 'nullable|string'
        ]);

        $store = $request->input('store', 'Renovar Tech');
        $nextToken = $request->input('nextToken', null);
        $destinationmarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $shipmentID = $request->input('shipmentID', 'FBA17YTXZSKB');
        $inboundplanid = $request->input('inboundplanid', 'wffacd6859-1abf-499e-82ff-76497a17ae63');
        $packingGroupId = $request->input('packingGroupId', 'pgfadeaafb-3918-48d2-8f32-13a48dc9f69e');


        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/inbound/fba/2024-03-20/inboundPlans/' . $inboundplanid . '/packingGroups/' . $packingGroupId . '/items';
        $customParams = [];

        $companydetails = $this->fetchCompanyDetails();

        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        // Generate JSON payload
        $jsonData = $this->JsonCreation('step2a', $companydetails, 'ATVPDKIKX0DER', $shipmentID);

        // Check if JSON encoding failed
        if ($jsonData === false) {
            Log::error('JSON Encoding Failed:', ['error' => json_last_error_msg()]);
            return response()->json(['success' => false, 'message' => 'JSON encoding error'], 500);
        }


        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return response()->json([
                'success' => false,
                'message' => 'No credentials found for the given store.',
            ], 500);
        }

        $accessToken = fetchAccessToken($credentials, $returnRaw = false);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 500);
        }

        try {
            // Build headers using the helper function
            $headers = buildHeaders($credentials, $accessToken, 'POST', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
            // Ensure Content-Type is set
            $headers['Content-Type'] = 'application/json';
            $headers['accept'] = 'application/json';

            // Log the headers
            Log::info('Request headers:', $headers);

            // Build query string using the helper function
            $queryString = buildQueryString($nextToken, $customParams);

            // Construct the full URL
            $url = "{$endpoint}{$path}{$queryString}";

            // Log the request details (headers, body, etc.) for debugging
            Log::info('Request details:', [
                'url' => $url,
                'headers' => $headers,
                'queryString' => $queryString,
                // 'body' => json_decode($jsonData, true) // Decode before logging
            ]);

            // Make the HTTP request (POST)
            $response = Http::timeout(50)
                ->withHeaders($headers)
                // ->withBody($jsonData, 'application/json') // Ensure JSON is properly sent
                ->get($url);

            // Log the cURL information (response details)
            $curlInfo = $response->handlerStats();
            Log::info('Curl Info:', $curlInfo);

            // Check if request was successful
            if ($response->successful()) {
                $data = $response->json(); // Parse JSON response

                // Extract operationId
                $operationId = $data['operationId'] ?? null;

                // If operationId exists, call getOperationStatus()
                if ($operationId) {
                    Log::info("Tracking operation: {$operationId}");

                    // Call the operation status function
                    $operationStatusResponse = $this->getOperationStatus($store, $destinationmarketplace, $operationId);

                    // Return the operation response
                    return response()->json([
                        'success' => true,
                        'operationId' => $operationId,
                        'operationStatus' => $operationStatusResponse->getData(true), // Get operation tracking response
                        'logs' => $curlInfo,
                    ]);
                }

                // If no operationId, return success response but indicate missing operation tracking
                return response()->json([
                    'success' => true,
                    'message' => 'Operation Step 2c Success.',
                    'data' => $data,
                    'logs' => $curlInfo,
                ]);
            }

            // If request failed
            return response()->json([
                'success' => false,
                'message' => 'Successfully sent but API returned an error.',
                'headers' => $headers,
                'error' => $response->json(),
                'body-payload' => json_decode($jsonData, true), // Decode JSON before returning
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

    public function step2d_confirm_packing_option(Request $request)
    {
        $request->validate([
            'store' => 'nullable|string',
            'destinationMarketplace' => 'nullable|string',
            'nextToken' => 'nullable|string',
            'shipmentID' => 'nullable|string'
        ]);

        $store = $request->input('store', 'Renovar Tech');
        $nextToken = $request->input('nextToken', null);
        $destinationmarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $shipmentID = $request->input('shipmentID', 'FBA17YTXZSKB');
        $inboundplanid = $request->input('inboundplanid', 'wffacd6859-1abf-499e-82ff-76497a17ae63');// from process 1
        $packingGroupId = $request->input('packingGroupId', 'pgfadeaafb-3918-48d2-8f32-13a48dc9f69e');// from process 2b
        $packingOptionId = $request->input('packingOptionId', 'poc7a60e6f-3e37-4899-bb42-35f6161273f2'); // from process 2b


        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/inbound/fba/2024-03-20/inboundPlans/' . $inboundplanid . '/packingOptions/' . $packingOptionId . '/confirmation';
        $customParams = [];

        $companydetails = $this->fetchCompanyDetails();

        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        // Generate JSON payload
        $jsonData = $this->JsonCreation('step2a', $companydetails, 'ATVPDKIKX0DER', $shipmentID);

        // Check if JSON encoding failed
        if ($jsonData === false) {
            Log::error('JSON Encoding Failed:', ['error' => json_last_error_msg()]);
            return response()->json(['success' => false, 'message' => 'JSON encoding error'], 500);
        }


        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return response()->json([
                'success' => false,
                'message' => 'No credentials found for the given store.',
            ], 500);
        }

        $accessToken = fetchAccessToken($credentials, $returnRaw = false);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 500);
        }

        try {
            // Build headers using the helper function
            $headers = buildHeaders($credentials, $accessToken, 'POST', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
            // Ensure Content-Type is set
            $headers['Content-Type'] = 'application/json';
            $headers['accept'] = 'application/json';

            // Log the headers
            Log::info('Request headers:', $headers);

            // Build query string using the helper function
            $queryString = buildQueryString($nextToken, $customParams);

            // Construct the full URL
            $url = "{$endpoint}{$path}{$queryString}";

            // Log the request details (headers, body, etc.) for debugging
            Log::info('Request details:', [
                'url' => $url,
                'headers' => $headers,
                'queryString' => $queryString,
                // 'body' => json_decode($jsonData, true) // Decode before logging
            ]);

            // Make the HTTP request (POST)
            $response = Http::timeout(50)
                ->withHeaders($headers)
                // ->withBody($jsonData, 'application/json') // Ensure JSON is properly sent
                ->post($url);

            // Log the cURL information (response details)
            $curlInfo = $response->handlerStats();
            Log::info('Curl Info:', $curlInfo);

            // Check if request was successful
            if ($response->successful()) {
                $data = $response->json(); // Parse JSON response

                // Extract operationId
                $operationId = $data['operationId'] ?? null;

                // If operationId exists, call getOperationStatus()
                if ($operationId) {
                    Log::info("Tracking operation: {$operationId}");

                    // Call the operation status function
                    $operationStatusResponse = $this->getOperationStatus($store, $destinationmarketplace, $operationId);

                    // Return the operation response
                    return response()->json([
                        'success' => true,
                        'operationId' => $operationId,
                        'operationStatus' => $operationStatusResponse->getData(true), // Get operation tracking response
                        'logs' => $curlInfo,
                    ]);
                }

                // If no operationId, return success response but indicate missing operation tracking
                return response()->json([
                    'success' => true,
                    'message' => 'Operation Step 2d Success.',
                    'data' => $data,
                    'logs' => $curlInfo,
                ]);
            }

            // If request failed
            return response()->json([
                'success' => false,
                'message' => 'Successfully sent but API returned an error.',
                'headers' => $headers,
                'error' => $response->json(),
                'body-payload' => json_decode($jsonData, true), // Decode JSON before returning
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

    public function step3a_confirm_packing_option(Request $request)
    {
        $request->validate([
            'store' => 'nullable|string',
            'destinationMarketplace' => 'nullable|string',
            'nextToken' => 'nullable|string',
            'shipmentID' => 'nullable|string'
        ]);

        $store = $request->input('store', 'Renovar Tech');
        $nextToken = $request->input('nextToken', null);
        $destinationmarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $shipmentID = $request->input('shipmentID', 'FBA17YTXZSKB');
        $inboundplanid = $request->input('inboundplanid', 'wffacd6859-1abf-499e-82ff-76497a17ae63');// from process 1
        $packingGroupId = $request->input('packingGroupId', 'pgfadeaafb-3918-48d2-8f32-13a48dc9f69e');// from process 2b
        $packingOptionId = $request->input('packingOptionId', 'pgfadeaafb-3918-48d2-8f32-13a48dc9f69e'); // from process 2b


        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/inbound/fba/2024-03-20/inboundPlans/' . $inboundplanid . '/packingInformation/';
        $customParams = [];

        $companydetails = $this->fetchCompanyDetails();

        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        // Generate JSON payload
        $jsonData = $this->JsonCreation('step3a', $companydetails, 'ATVPDKIKX0DER', $shipmentID);

        // Check if JSON encoding failed
        if ($jsonData === false) {
            Log::error('JSON Encoding Failed:', ['error' => json_last_error_msg()]);
            return response()->json(['success' => false, 'message' => 'JSON encoding error'], 500);
        }


        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return response()->json([
                'success' => false,
                'message' => 'No credentials found for the given store.',
            ], 500);
        }

        $accessToken = fetchAccessToken($credentials, $returnRaw = false);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 500);
        }

        try {
            // Build headers using the helper function
            $headers = buildHeaders($credentials, $accessToken, 'POST', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
            // Ensure Content-Type is set
            $headers['Content-Type'] = 'application/json';
            $headers['accept'] = 'application/json';

            // Log the headers
            Log::info('Request headers:', $headers);

            // Build query string using the helper function
            $queryString = buildQueryString($nextToken, $customParams);

            // Construct the full URL
            $url = "{$endpoint}{$path}{$queryString}";

            // Log the request details (headers, body, etc.) for debugging
            Log::info('Request details:', [
                'url' => $url,
                'headers' => $headers,
                'queryString' => $queryString,
                // 'body' => json_decode($jsonData, true) // Decode before logging
            ]);

            // Make the HTTP request (POST)
            $response = Http::timeout(50)
                ->withHeaders($headers)
                // ->withBody($jsonData, 'application/json') // Ensure JSON is properly sent
                ->get($url);

            // Log the cURL information (response details)
            $curlInfo = $response->handlerStats();
            Log::info('Curl Info:', $curlInfo);

            // Check if request was successful
            if ($response->successful()) {
                $data = $response->json(); // Parse JSON response

                // Extract operationId
                $operationId = $data['operationId'] ?? null;

                // If operationId exists, call getOperationStatus()
                if ($operationId) {
                    Log::info("Tracking operation: {$operationId}");

                    // Call the operation status function
                    $operationStatusResponse = $this->getOperationStatus($store, $destinationmarketplace, $operationId);

                    // Return the operation response
                    return response()->json([
                        'success' => true,
                        'operationId' => $operationId,
                        'operationStatus' => $operationStatusResponse->getData(true), // Get operation tracking response
                        'logs' => $curlInfo,
                    ]);
                }

                // If no operationId, return success response but indicate missing operation tracking
                return response()->json([
                    'success' => true,
                    'message' => 'Operation Step 3a Success.',
                    'data' => $data,
                    'logs' => $curlInfo,
                ]);
            }

            // If request failed
            return response()->json([
                'success' => false,
                'message' => 'Successfully sent but API returned an error.',
                'headers' => $headers,
                'error' => $response->json(),
                'body-payload' => json_decode($jsonData, true), // Decode JSON before returning
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

    protected function fetchCompanyDetails()
    {
        return DB::table('tblcompanydetails')->where('id', 1)->first();
    }

    protected function JsonCreation($action, $companydetails, $marketplaceID, $shipmentID)
    {
        $systemconfig = 'test';
        $final_json_construct = [];

        if ($action == 'step1') {
            // Convert object to array for safety
            $companydetails = (array) $companydetails;


            // 🔍 **Query Database for Shipment Items**
            $shipmentItems = DB::table('tblfbashipmenthistory')
                ->where('shipmentID', $shipmentID)
                ->get(); // Fetch all matching records

            // 🔹 **Check if there are items before proceeding**
            if ($shipmentItems->isEmpty()) {
                return json_encode(["error" => "No items found for Shipment ID: " . $shipmentID], JSON_UNESCAPED_SLASHES);
            }

            // 🔹 **Convert Database Results to Expected JSON Structure**
            $itemsArray = $shipmentItems->map(function ($item) {
                return [
                    "labelOwner" => "SELLER",
                    "msku" => $item->MSKU ?? "Unknown",
                    "prepOwner" => "AMAZON",
                    "quantity" => $item->quantity ?? 1
                ];
            })->toArray();

            // 🔹 **Build Final JSON**
            $final_json_construct = [
                "name" => $companydetails['Name'] ?? 'Unknown',
                "sourceAddress" => [
                    "name" => $companydetails['Name'] ?? '',
                    "companyName" => $companydetails['CompanyName'] ?? '',
                    "addressLine1" => $companydetails['StreetAddress'] ?? '',
                    "addressLine2" => '',
                    "city" => $companydetails['City'] ?? '',
                    "countryCode" => $companydetails['CountryCode'] ?? '',
                    "stateOrProvinceCode" => $companydetails['State'] ?? '',
                    "postalCode" => $companydetails['ZIPCode'] ?? '',
                    "phoneNumber" => $companydetails['Contact'] ?? ''
                ],
                "destinationMarketplaces" => [
                    $marketplaceID
                ],
                "items" => $itemsArray
            ];

        } elseif ($action == 'step2a') {
            $final_json_construct = [];
        } else if ($action == '3a') {
            $final_json_construct = [
                "packageGroupings" => [
                    [
                        "packingGroupId" => "string",
                        "boxes" => [
                            [
                                "weight" => [
                                    "unit" => "LB",
                                    "value" => 0
                                ],
                                "dimensions" => [
                                    "unitOfMeasurement" => "IN",
                                    "length" => 0,
                                    "width" => 0,
                                    "height" => 0
                                ],
                                "quantity" => 1,
                                "boxId" => "string",
                                "items" => [
                                    [
                                        "msku" => "string",
                                        "quantity" => 1,
                                        "expiration" => "string",
                                        "prepOwner" => "AMAZON",
                                        "labelOwner" => "AMAZON",
                                        "manufacturingLotCode" => "string"
                                    ]
                                ],
                                "contentInformationSource" => "BOX_CONTENT_PROVIDED"
                            ]
                        ]
                    ]
                ]
            ];
        }

        // Ensure JSON encoding before returning
        return json_encode($final_json_construct, JSON_UNESCAPED_SLASHES);
    }
    private function getOperationStatus($store, $destinationmarketplace, $operationid)
    {
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/inbound/fba/2024-03-20/operations/' . $operationid;
        $nextToken = '';
        $customParams = [];
        $maxRetries = 20; // Maximum number of retries before stopping
        $retryInterval = 5; // Time in seconds between each retry

        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return response()->json([
                'success' => false,
                'message' => 'No credentials found for the given store.',
            ], 500);
        }

        $accessToken = fetchAccessToken($credentials, $returnRaw = false);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 500);
        }

        try {
            $headers = buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
            $headers['Content-Type'] = 'application/json';
            $headers['accept'] = 'application/json';

            $queryString = buildQueryString($nextToken, $customParams);
            $url = "{$endpoint}{$path}{$queryString}";

            $attempt = 0;
            do {
                // Log the request details
                Log::info("Attempt {$attempt}: Checking operation status", ['url' => $url, 'headers' => $headers]);

                // Make the HTTP request
                $response = Http::timeout(50)->withHeaders($headers)->get($url);
                $curlInfo = $response->handlerStats();

                // Log response
                Log::info("Attempt {$attempt}: Response received", [
                    'status' => $response->status(),
                    'body' => $response->json(),
                    'logs' => $curlInfo
                ]);

                // If the request was successful
                if ($response->successful()) {
                    $data = $response->json();
                    $status = $data['operationStatus'] ?? 'UNKNOWN';

                    // Return if status is SUCCESS or FAILED
                    if ($status === 'SUCCESS' || $status === 'FAILED') {
                        return response()->json([
                            'success' => true,
                            'status' => $status,
                            'data' => $data,
                            'logs' => $curlInfo
                        ]);
                    }
                } else {
                    // If there's an error response, log it and break
                    Log::error("Attempt {$attempt}: API Error", ['error' => $response->json()]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Error fetching operation status.',
                        'error' => $response->json(),
                        'logs' => $curlInfo
                    ], $response->status());
                }

                // Wait before retrying
                sleep($retryInterval);
                $attempt++;

            } while ($attempt < $maxRetries);

            // If max retries exceeded
            return response()->json([
                'success' => false,
                'message' => 'Maximum retries reached. Operation still in progress.',
            ], 408); // HTTP 408: Request Timeout

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during the API request.',
                'error' => $e->getMessage(),
                'logs' => $curlInfo ?? null,
            ], 500);
        }
    }
}

