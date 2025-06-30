<?php

namespace App\Http\Controllers\Amzn\OutboundOrders\ShippingLabel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

require base_path('app/Helpers/aws_helpers.php');

class ListingController extends Controller
{

    public function get_product_fetch_listing_main(Request $request)
    {
        $destinationMarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $nextToken = $request->input('nextToken', null);
        $store = $request->input('store', null);
        $searchedAsin = $request->input('searchedAsin', null);
        $producttype = $request->input('producttype', null);

        $conditions = [
            "new_new",
            "new_open_box",
            "new_oem",
            "refurbished_refurbished",
            "used_like_new",
            "used_very_good",
            "used_good",
            "used_acceptable",
            "collectible_like_new",
            "collectible_very_good",
            "collectible_good",
            "collectible_acceptable",
            "club_club"
        ];

        $result = app()->call([$this, 'get_product_type'], ['request' => $request]);
        $result = app()->call([$this, 'fetch_listing_restrict'], ['request' => $request]);

        $arrays['restrictions'] = $this->process_restrictions($result, $conditions);

        $url = $arrays['ProductType']['metaSchema']['link']['resource'];
        $method = $arrays['ProductType']['metaSchema']['link']['verb'];
        $expectedChecksum = $arrays['ProductType']['metaSchema']['checksum'];
        $arrays['metaSchema'] = $this->fetch_metaSchema($url, $method, $expectedChecksum);

        $url = $arrays['ProductType']['schema']['link']['resource'];
        $method = $arrays['ProductType']['schema']['link']['verb'];
        $expectedChecksum = $arrays['ProductType']['schema']['checksum'];
        $arrays['schema'] = $this->fetch_metaSchema($url, $method, $expectedChecksum);
    }
    
    public function get_product_type(Request $request)
    {

        $destinationMarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $nextToken = $request->input('nextToken', null);
        $store = $request->input('store', null);
        $searchedAsin = $request->input('searchedAsin', null);
        $producttype = $request->input('producttype', null);

        $ProductType = urlencode($producttype);
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/definitions/2020-09-01/productTypes/' . $producttype;

        // Static query parameters
        $customParams = [
            // 'details' => "true",
            // 'granularityType' => "Marketplace",
            'marketplaceIds' => $destinationMarketplace,
            'locale' => 'en_US',
            'requirementsEnforced' => 'NOT_ENFORCED',
            'requirements' => 'LISTING_OFFER_ONLY',
        ];

        $companydetails = fetchCompanyDetails();

        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $returndata = [];

        $credentials = AWSCredentials($store);
        if (!$credentials) {
            $returndata[] = [
                'error' => 'No credentials found for store: ' . $store
            ];
        }

        $accessToken = fetchAccessToken($credentials, false);
        if (!$accessToken) {
            $returndata[] = [
                'error' => 'Failed to fetch access token.',
                'credentials' => $credentials
            ];
        }

        // Final payload to be sent
        $data_additionale = [];

        $jsonData = $this->JsonCreation(null, null, null, null);
        if ($jsonData === false) {
            $returndata[] = [
                'error' => 'Failed to construct Json Creation.',
                'jsonData' => $jsonData
            ];
        }

        try {
            $headers = buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
            $headers['Content-Type'] = 'application/json';
            $headers['accept'] = 'application/json';

            $queryString = buildQueryString($nextToken, $customParams);
            $url = "{$endpoint}{$path}?{$queryString}";

            $response = Http::timeout(50)
                ->withHeaders($headers)
                // ->withBody($jsonData, 'application/json')
                ->get($url);

            $curlInfo = $response->handlerStats();

            if ($response->successful()) {
                $returndata[] = [
                    'rates' => $response->json(),
                    'logs' => $curlInfo
                ];
            } else {
                $returndata[] = [

                    'error' => $response->json(),
                    'status' => $response->status(),
                    'logs' => $curlInfo
                ];
            }
        } catch (\Exception $e) {
            $returndata[] = [
                'exception' => $e->getMessage()
            ];
        }


        return response()->json([
            'success' => true,
            'results' => $returndata
        ]);
    }

    public function fetch_listing_restrict(Request $request)
    {

        $destinationMarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $nextToken = $request->input('nextToken', null);
        $store = $request->input('store', null);
        $searchedAsin = $request->input('searchedAsin', null);

        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/definitions/2020-09-01/productTypes/';

        $companydetails = fetchCompanyDetails();

        $tblstore = fetchtblstores($store);

        // Static query parameters
        $customParams = [
            // 'details' => "true",
            // 'granularityType' => "Marketplace",
            'marketplaceIds' => $destinationMarketplace,
            'sellerId' => $tblstore->MerchantID,
            'asin' => $searchedAsin,
        ];

        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $returndata = [];

        $credentials = AWSCredentials($store);
        if (!$credentials) {
            $returndata[] = [
                'error' => 'No credentials found for store: ' . $store
            ];
        }

        $accessToken = fetchAccessToken($credentials, false);
        if (!$accessToken) {
            $returndata[] = [
                'error' => 'Failed to fetch access token.',
                'credentials' => $credentials
            ];
        }

        // Final payload to be sent
        $data_additionale = [];

        $jsonData = $this->JsonCreation(null, null, null, null);
        if ($jsonData === false) {
            $returndata[] = [
                'error' => 'Failed to construct Json Creation.',
                'jsonData' => $jsonData
            ];
        }

        try {
            $headers = buildHeaders($credentials, $accessToken, 'GET', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
            $headers['Content-Type'] = 'application/json';
            $headers['accept'] = 'application/json';

            $queryString = buildQueryString($nextToken, $customParams);
            $url = "{$endpoint}{$path}?{$queryString}";

            $response = Http::timeout(50)
                ->withHeaders($headers)
                // ->withBody($jsonData, 'application/json')
                ->get($url);

            $curlInfo = $response->handlerStats();

            if ($response->successful()) {
                $returndata[] = [
                    'rates' => $response->json(),
                    'logs' => $curlInfo
                ];
            } else {
                $returndata[] = [

                    'error' => $response->json(),
                    'status' => $response->status(),
                    'logs' => $curlInfo
                ];
            }
        } catch (\Exception $e) {
            $returndata[] = [
                'exception' => $e->getMessage()
            ];
        }


        return response()->json([
            'success' => true,
            'results' => $returndata
        ]);
    }

    protected function JsonCreation($action, $companydetails, $marketplaceID, $data_additionale)
    {
        $final_json_construct = [];

        $companydetails = (array) $companydetails;

        if ($action == 'get_rates') {
            $final_json_construct = [];
        }

        // Ensure JSON encoding before returning
        return json_encode($final_json_construct, JSON_UNESCAPED_SLASHES);
    }

    protected function process_restrictions($data, $conditions)
    {
        // Initialize the final result array
        $finalArray = [
            'restrictions' => [],
        ];

        $foundConditions = [];

        // Check if 'restrictions' key exists in the result
        if (isset($data['restrictions']) && is_array($data['restrictions'])) {
            foreach ($data['restrictions'] as $restriction) {
                $conditionType = $restriction['conditionType'];
                $reason = $restriction['reasons'][0] ?? null; // Assuming only one reason per condition

                // Check for both 'APPROVAL_REQUIRED' and 'NOT_ELIGIBLE'
                if ($reason && ($reason['reasonCode'] == 'APPROVAL_REQUIRED' || $reason['reasonCode'] == 'NOT_ELIGIBLE')) {

                    // Add restriction details to the final array
                    $finalArray['restrictions'][] = [
                        'conditionType' => $conditionType,
                        'message' => $reason['message'],
                        'approvalLink' => $reason['links'][0]['resource'] ?? null,
                        'success' => false,
                    ];

                    // Track the found condition
                    $foundConditions[] = $conditionType;
                }
            }
        } else {
            // Handle cases where 'restrictions' key is missing or not an array
            $finalArray['success'] = false;
        }

        // Check if conditions are not found in the restrictions
        foreach ($conditions as $condition) {
            if (!in_array($condition, $foundConditions)) {
                $finalArray['restrictions'][] = [
                    'conditionType' => $condition,
                    'success' => true,
                    'message' => 'No probs',
                    'approvalLink' => '' // Empty approval link
                ];
            }
        }

        return $finalArray;
    }

    protected function fetch_metaSchema($url, $method, $expectedChecksum)
    {
        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);        // URL to send the request to
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string

        // Set the request method (in this case, GET)
        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        }

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check if there was an error during execution
        if (curl_errno($ch)) {
            echo 'cURL error: ' . curl_error($ch);
            return null;
        }

        // Close the cURL session
        curl_close($ch);

        // Calculate the checksum of the response
        $computedChecksum = base64_encode(md5($response, true));

        // Verify checksum
        if ($computedChecksum === $expectedChecksum) {
            // echo "Checksum matches. Data integrity verified.\n";
        } else {
            echo "Checksum mismatch. Data may be corrupted.\n";
            return null;
        }

        $result = json_decode($response, true);

        // Return the response if checksum matches
        return $result;
    }
}