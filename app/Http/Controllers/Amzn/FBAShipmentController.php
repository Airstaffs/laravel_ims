<?php

namespace App\Http\Controllers\Amzn;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;


require base_path('Helpers/aws_helpers.php');

class FBAShipmentController extends Controller
{
    public function step1_createShipment(Request $request)
    {
        $request->validate([
            'store' => 'nullable|string',
            'destinationMarketplace' => 'nullable|string',
            'nextToken' => 'nullable|string'
        ]);

        $store = $request->input('store', 'RT');
        $nextToken = $request->input('nextToken', null);
        $destinationmarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $endpoint = 'https://sandbox.sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sandbox.sellingpartnerapi-na.amazon.com";
        $path = '/fba/inventory/v1/summaries';
        // custom params is an associate array
        $customParams = [];

        // Fetch company details
        $companydetails = $this->fetchCompanyDetails();

        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        // Generate JSON payload for step1
        $jsonData = $this->JsonCreation('step1', $companydetails, 'ATVPDKIKX0DER');

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

    public function step2a_generate_packing(Request $request)
    {
        // Fetch company details again
        $companydetails = $this->fetchCompanyDetails();

        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        // Generate JSON payload for step2 (not implemented yet)
        $jsonData = $this->JsonCreation('step2', $companydetails, 'ATVPDKIKX0DER');

        return response()->json($jsonData);
    }

    protected function fetchCompanyDetails()
    {
        return DB::table('tblcompanydetails')->where('id', 1)->first();
    }

    protected function JsonCreation($action, $companydetails, $marketplaceID)
    {
        $systemconfig = 'test';

        if ($action == 'step1') {
            // Convert object to array for safety
            $companydetails = (array) $companydetails;

            if ($systemconfig == 'test') {

                $json = [
                    "destinationMarketplaces" => ["ATVPDKIKX0DER"],
                    "items" => [
                        [
                            "expiration" => "2024-01-01",
                            "labelOwner" => "AMAZON",
                            "manufacturingLotCode" => "manufacturingLotCode",
                            "msku" => "Sunglasses",
                            "prepOwner" => "AMAZON",
                            "quantity" => 10
                        ]
                    ],
                    "name" => "My inbound plan",
                    "sourceAddress" => [
                        "addressLine1" => "123 example street",
                        "addressLine2" => "Floor 19",
                        "city" => "Toronto",
                        "companyName" => "Acme",
                        "countryCode" => "CA",
                        "email" => "email@email.com",
                        "name" => "name",
                        "phoneNumber" => "1234567890",
                        "postalCode" => "M1M1M1",
                        "stateOrProvinceCode" => "ON"
                    ]
                ];

            } else {

                // Build JSON payload with default values to prevent errors
                $json = [
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
                    "contactInformation" => [
                        "phoneNumber" => $companydetails['Contact'] ?? '',
                        "email" => $companydetails['ContactEmail'] ?? '',
                        "name" => "Julius Sanchez"
                    ],
                    "destinationMarketplaces" => [
                        $marketplaceID
                    ],
                    "items" => [
                        [
                            "expiration" => "2024-01-01",
                            "labelOwner" => "AMAZON",
                            "manufacturingLotCode" => "manufacturingLotCode",
                            "msku" => "Sunglasses",
                            "prepOwner" => "AMAZON",
                            "quantity" => 10
                        ]
                    ]
                ];
            }

            return $json;
        } elseif ($action == 'step2') {
            return [
                "message" => "Step 2 JSON not implemented yet."
            ];
        }
    }
}