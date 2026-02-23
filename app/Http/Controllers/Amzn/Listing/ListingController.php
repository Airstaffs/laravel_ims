<?php

namespace App\Http\Controllers\Amzn\Listing;

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

    public function searchListings(Request $request)
    {
        $data = $request->validate([
            'store' => ['required', 'string'], // Renovartech | Allrenewed
            'marketplaceIds' => ['nullable', 'array'],
            'marketplaceIds.*' => ['string'],

            'includedData' => ['nullable', 'array'],
            'includedData.*' => ['string'],

            'identifiersType' => ['nullable', 'string'], // SKU, ASIN, etc
            'identifiers' => ['nullable', 'array'],
            'identifiers.*' => ['string'],

            'variationParentSku' => ['nullable', 'string'],

            'sortBy' => ['nullable', 'in:sku,createdDate,lastUpdatedDate'],
            'sortOrder' => ['nullable', 'in:ASC,DESC'],

            'pageSize' => ['nullable', 'integer', 'min:1', 'max:20'],
            'pageToken' => ['nullable', 'string'],
        ]);

        $store = $data['store'];
        $marketplaceIds = $data['marketplaceIds'] ?? ['ATVPDKIKX0DER'];

        $includedData = $data['includedData'] ?? [
            'summaries',
            'attributes',
            'issues',
            'offers',
            'fulfillmentAvailability',
            'procurement',
            'relationships',
            'productTypes'
        ];

        $sortBy = $data['sortBy'] ?? 'lastUpdatedDate';
        $sortOrder = $data['sortOrder'] ?? 'DESC';
        $pageSize = $data['pageSize'] ?? 10;
        $pageToken = $data['pageToken'] ?? null;

        $identifiersType = $data['identifiersType'] ?? null;
        $identifiers = $data['identifiers'] ?? [];
        $variationParentSku = $data['variationParentSku'] ?? null;

        // ✅ Amazon rule enforcement
        if ($variationParentSku && (!empty($identifiers) || $identifiersType)) {
            return response()->json([
                'ok' => false,
                'error' => 'variationParentSku cannot be used with identifiers.'
            ], 422);
        }

        if (!empty($identifiers) && !$identifiersType) {
            return response()->json([
                'ok' => false,
                'error' => 'identifiersType is required when identifiers is provided.'
            ], 422);
        }

        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return response()->json(['ok' => false, 'error' => 'Credentials not found'], 404);
        }

        $sellerId = $credentials['MerchantID'] ?? null;

        if (!$sellerId) {
            return response()->json(['ok' => false, 'error' => 'MerchantID missing in credentials'], 500);
        }

        $accessToken = fetchAccessToken($credentials, false);
        if (!$accessToken) {
            return response()->json(['ok' => false, 'error' => 'Failed to fetch access token'], 500);
        }

        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $path = "/listings/2021-08-01/items/{$sellerId}";
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";

        // Build query parameters
        $query = [
            'marketplaceIds' => implode(',', $marketplaceIds),
            'includedData' => implode(',', $includedData),
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'pageSize' => $pageSize,
        ];

        if ($pageToken) {
            $query['pageToken'] = $pageToken;
        }

        if ($variationParentSku) {
            $query['variationParentSku'] = $variationParentSku;
        } elseif (!empty($identifiers)) {
            $query['identifiersType'] = $identifiersType;
            $query['identifiers'] = implode(',', $identifiers);
        }

        $headers = buildHeaders(
            $credentials,
            $accessToken,
            'GET',
            'execute-api',
            'us-east-1',
            $path,
            null,
            $query,
            $endpoint,
            $canonicalHeaders
        );

        $headers['accept'] = 'application/json';

        $url = $endpoint . $path . '?' . http_build_query($query);

        try {
            $response = Http::timeout(50)->withHeaders($headers)->get($url);
            $curlInfo = $response->handlerStats();

            if ($response->successful()) {
                return response()->json([
                    'ok' => true,
                    'data' => $response->json(),
                    'logs' => $curlInfo,
                ]);
            }

            return response()->json([
                'ok' => false,
                'status' => $response->status(),
                'error' => $response->json(),
                'logs' => $curlInfo,
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'exception' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateOne(Request $request)
    {
        $data = $request->validate([
            'store' => ['required', 'string'],
            'marketplaceIds' => ['required', 'array', 'min:1'],
            'marketplaceIds.*' => ['string'],

            'sku' => ['required', 'string'],

            // optional but useful
            'asin' => ['nullable', 'string'],
            'productType' => ['nullable', 'string'],

            // qty
            'quantity' => ['nullable'],              // number|null
            'quantityCleared' => ['nullable', 'boolean'],

            // price
            'price' => ['nullable'],                 // number|null
            'priceCleared' => ['nullable', 'boolean'],
            'currency' => ['nullable', 'string'],
        ]);

        $store = $data['store'];
        $marketplaceId = $data['marketplaceIds'][0]; // your UI uses single marketplace anyway



        $sku = $data['sku'];

        // Credentials + Access token
        $credentials = AWSCredentials($store);

        // Adjust this key to whatever you actually store as sellerId
        $sellerId = $credentials['MerchantID'] ?? null;

        // IMPORTANT: for patchListingsItem the sellerId is part of the URL.
        // If you already store it in company details, pull it from there.
        if (!$sellerId) {
            return response()->json(['message' => "Company not found for store: {$store}"], 404);
        }

        if (!$sellerId) {
            return response()->json([
                'message' => 'Missing sellerId in company details. Add SellerId/seller_id for this store.',
                'store' => $store,
            ], 422);
        }
        if (!$credentials) {
            return response()->json(['message' => "No credentials found for store: {$store}"], 422);
        }

        $accessToken = fetchAccessToken($credentials, false);
        if (!$accessToken) {
            return response()->json(['message' => "Failed to fetch access token for store: {$store}"], 422);
        }

        // Build patches based on what the Vue said was "touched"
        $patches = [];

        // ---------- QTY PATCH ----------
        $qtyTouched = $request->has('quantity') || $request->has('quantityCleared');
        if ($qtyTouched) {
            $qtyCleared = (bool) $request->input('quantityCleared', false);

            if ($qtyCleared) {
                // only delete if you actually intend to clear
                $patches[] = ['op' => 'delete', 'path' => '/attributes/fulfillment_availability'];
            } else {
                $qtyVal = $request->input('quantity', null);
                if ($qtyVal !== null) {
                    $patches[] = [
                        'op' => 'replace',
                        'path' => '/attributes/fulfillment_availability',
                        'value' => [
                            [
                                'fulfillment_channel_code' => 'DEFAULT',
                                'quantity' => (int) $qtyVal,
                            ]
                        ],
                    ];
                }
            }
        }

        // ---------- PRICE PATCH ----------
        $priceTouched = array_key_exists('price', $data) || array_key_exists('priceCleared', $data);
        if ($priceTouched) {
            $priceCleared = (bool) ($data['priceCleared'] ?? false);

            if ($priceCleared) {
                $patches[] = [
                    'op' => 'delete',
                    'path' => '/attributes/purchasable_offer',
                ];
            } else {
                $currency = $data['currency'] ?? 'USD';
                $priceVal = (float) $data['price'];

                // Example format taken from patchListingsItem example that updates purchasable_offer
                $patches[] = [
                    'op' => 'replace',
                    'path' => '/attributes/purchasable_offer',
                    'value' => [
                        [
                            'currency' => $currency,
                            'audience' => 'ALL',
                            'our_price' => [
                                [
                                    'schedule' => [
                                        [
                                            'value_with_tax' => $priceVal,
                                        ]
                                    ],
                                ]
                            ],
                            'marketplace_id' => $marketplaceId,
                        ]
                    ],
                ];
            }
        }

        if (empty($patches)) {
            return response()->json(['message' => 'Nothing to patch (no qty/price fields provided).'], 422);
        }

        // productType required by patchListingsItem
        // Best: pass it from search results (your includedData already requests productTypes)
        $productType = $data['productType'] ?? 'PRODUCT';

        $body = [
            'productType' => $productType,
            'patches' => $patches,
        ];

        // Call SP-API
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $path = "/listings/2021-08-01/items/{$sellerId}/" . rawurlencode($sku);

        // marketplaceIds must be in querystring
        $queryString = '?marketplaceIds=' . rawurlencode($marketplaceId);

        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $customParams = []; // not used here
        $nextToken = null;  // not used here

        try {
            $headers = buildHeaders(
                $credentials,
                $accessToken,
                'PATCH',
                'execute-api',
                'us-east-1',
                $path,
                $nextToken,
                $customParams,
                $endpoint,
                $canonicalHeaders
            );

            // PATCH content type: Amazon may require json-patch media type
            $headers['Content-Type'] = 'application/json-patch+json';
            $headers['accept'] = 'application/json';

            $url = "{$endpoint}{$path}{$queryString}";

            $response = Http::timeout(50)
                ->withHeaders($headers)
                ->withBody(json_encode($body), 'application/json-patch+json')
                ->send('PATCH', $url);

            $curlInfo = method_exists($response, 'handlerStats') ? $response->handlerStats() : null;

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'sku' => $sku,
                    'store' => $store,
                    'marketplaceId' => $marketplaceId,
                    'request' => $body,
                    'response' => $response->json(),
                    'logs' => $curlInfo,
                ]);
            }

            return response()->json([
                'success' => false,
                'sku' => $sku,
                'store' => $store,
                'marketplaceId' => $marketplaceId,
                'request' => $body,
                'status' => $response->status(),
                'error' => $response->json(),
                'logs' => $curlInfo,
            ], 400);

        } catch (\Throwable $e) {
            Log::error('patchListingsItem exception', [
                'sku' => $sku,
                'store' => $store,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'sku' => $sku,
                'store' => $store,
            ], 500);
        }
    }

    // Supporting Functions
    protected function JsonCreation($action, $companydetails, $marketplaceID, $data_additionale)
    {
        $final_json_construct = [];

        $companydetails = (array) $companydetails;

        if ($action == 'fetch_listing_restrict') {
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
            // Handle cases where 'restrictions' key is missing or not an arrays
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