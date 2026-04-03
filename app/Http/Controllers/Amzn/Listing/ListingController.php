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

            if (!$response->successful()) {
                return response()->json([
                    'ok' => false,
                    'status' => $response->status(),
                    'error' => $response->json(),
                    'logs' => $curlInfo,
                ], 400);
            }

            $payload = $response->json();
            $items = $payload['items'] ?? [];

            // ----------------------------
            // STRICT IMS MATCHING: MSKU + ASIN (AND)
            // If no strict match -> ims.count = 0
            // ----------------------------

            // Build strict pairs from Amazon items (must have BOTH sku and asin)
            $pairs = [];
            foreach ($items as $it) {
                $sku = $it['sku'] ?? ($it['summaries'][0]['sku'] ?? null);
                $asin = $it['asin'] ?? ($it['summaries'][0]['asin'] ?? null);

                if ($sku && $asin) {
                    $pairs[] = ['sku' => $sku, 'asin' => $asin];
                }
            }

            // De-dupe pairs
            $seen = [];
            $pairs = array_values(array_filter($pairs, function ($p) use (&$seen) {
                $k = $p['sku'] . '|' . $p['asin'];
                if (isset($seen[$k]))
                    return false;
                $seen[$k] = true;
                return true;
            }));

            $rows = collect();
            if (!empty($pairs)) {
                $rows = DB::table('tblproduct')
                    ->where('ProductModuleLoc', 'Stockroom')
                    ->where(function ($q) use ($pairs) {
                        foreach ($pairs as $p) {
                            $q->orWhere(function ($qq) use ($p) {
                                $qq->where('MSKUviewer', $p['sku'])
                                    ->where('ASINviewer', $p['asin']);
                            });
                        }
                    })
                    ->selectRaw('MSKUviewer, ASINviewer, COUNT(*) as imsCount')
                    ->groupBy('MSKUviewer', 'ASINviewer')
                    ->get();
            }

            // Build fast map by "sku|asin"
            $byPair = [];
            foreach ($rows as $r) {
                $k = ($r->MSKUviewer ?? '') . '|' . ($r->ASINviewer ?? '');
                $byPair[$k] = (int) $r->imsCount;
            }

            // Attach ims per item (strict match only)
            foreach ($payload['items'] as $i => $it) {
                $sku = $it['sku'] ?? ($it['summaries'][0]['sku'] ?? null);
                $asin = $it['asin'] ?? ($it['summaries'][0]['asin'] ?? null);

                $count = 0;
                $matchedBy = null;

                if ($sku && $asin) {
                    $k = $sku . '|' . $asin;
                    if (isset($byPair[$k])) {
                        $count = (int) $byPair[$k];
                        $matchedBy = 'MSKUviewer+ASINviewer';
                    }
                }

                $payload['items'][$i]['ims'] = [
                    'module' => 'Stockroom',
                    'count' => $count,          // ✅ default 0
                    'matchedBy' => $matchedBy,  // null when not matched
                ];
            }

            return response()->json([
                'ok' => true,
                'data' => $payload,
                'logs' => $curlInfo,
            ]);

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
            'quantity' => ['nullable'],
            'quantityCleared' => ['nullable', 'boolean'],

            // price
            'price' => ['nullable'],
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
        $priceVal = null;

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
        $customParams = [];
        $nextToken = null;

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

            $headers['Content-Type'] = 'application/json-patch+json';
            $headers['accept'] = 'application/json';

            $url = "{$endpoint}{$path}{$queryString}";

            $response = Http::timeout(50)
                ->withHeaders($headers)
                ->withBody(json_encode($body), 'application/json-patch+json')
                ->send('PATCH', $url);

            $curlInfo = method_exists($response, 'handlerStats') ? $response->handlerStats() : null;

            if ($response->successful()) {
                // update local cached amazon price only if price was part of this request and not cleared
                if ($priceTouched) {
                    $priceCleared = (bool) ($data['priceCleared'] ?? false);

                    try {
                        $updateData = [
                            'updated_at' => now(),
                        ];

                        if ($priceCleared) {
                            $updateData['amzn_item_price'] = null;
                            $updateData['amzn_item_price_updated_at'] = now();
                        } elseif ($priceVal !== null && $priceVal > 0) {
                            $updateData['amzn_item_price'] = $priceVal;
                            $updateData['amzn_item_price_updated_at'] = now();
                        }

                        DB::table('tblfnsku')
                            ->where('MSKU', $sku)
                            ->where('storename', $store)
                            ->update($updateData);

                    } catch (\Throwable $dbEx) {
                        Log::warning('tblfnsku amzn_item_price update failed after successful Amazon patch', [
                            'sku' => $sku,
                            'store' => $store,
                            'message' => $dbEx->getMessage(),
                        ]);
                    }
                }

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

    public function fnskuSearch(Request $request)
    {
        $data = $request->validate([
            'store' => ['required', 'string'],
            'q' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $store = $data['store'];
        $q = trim($data['q'] ?? '');
        $page = (int) ($data['page'] ?? 1);
        $pageSize = (int) ($data['pageSize'] ?? 20);

        $query = DB::table('tblfnsku')
            ->select('FNSKUID', 'FNSKU', 'MSKU', 'ASIN', 'Units', 'grading', 'storename', 'fnsku_status')
            ->where('storename', $store);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('MSKU', 'like', "%{$q}%")
                    ->orWhere('FNSKU', 'like', "%{$q}%")
                    ->orWhere('ASIN', 'like', "%{$q}%");
            });
        }

        // pagination (simple)
        $offset = ($page - 1) * $pageSize;

        $rows = $query
            ->orderByDesc('FNSKUID')
            ->offset($offset)
            ->limit($pageSize + 1)
            ->get();

        $hasMore = $rows->count() > $pageSize;
        if ($hasMore) {
            $rows = $rows->slice(0, $pageSize)->values();
        }

        return response()->json([
            'ok' => true,
            'rows' => $rows,
            'hasMore' => $hasMore,
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'store' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'minPrice' => ['nullable', 'numeric', 'min:0'],
            'maxPrice' => ['nullable', 'numeric', 'min:0'],
            'runEveryMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'isActive' => ['required', 'in:0,1'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.FNSKUID' => ['nullable', 'integer'],
            'items.*.MSKU' => ['nullable', 'string', 'max:50'],
            'items.*.FNSKU' => ['nullable', 'string', 'max:50'],
            'items.*.ASIN' => ['nullable', 'string', 'max:20'],
            'items.*.storename' => ['nullable', 'string', 'max:20'],
        ]);

        // NOTE: this assumes you create the 2 tables below.
        // If you already have your own tables, tell me the names/columns and I’ll adapt.

        return DB::transaction(function () use ($data) {
            $automationId = DB::table('tbl_amzn_pricing_automation')->insertGetId([
                'storename' => $data['store'],
                'name' => $data['name'],
                'min_price' => $data['minPrice'],
                'max_price' => $data['maxPrice'],
                'run_every_minutes' => $data['runEveryMinutes'],
                'is_active' => (int) $data['isActive'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $items = [];
            foreach ($data['items'] as $it) {
                $items[] = [
                    'automation_id' => $automationId,
                    'storename' => $data['store'],
                    'FNSKUID' => $it['FNSKUID'] ?? null,
                    'MSKU' => $it['MSKU'] ?? null,
                    'FNSKU' => $it['FNSKU'] ?? null,
                    'ASIN' => $it['ASIN'] ?? null,
                    'created_at' => now(),
                ];
            }

            DB::table('tbl_amzn_pricing_automation_items')->insert($items);

            return response()->json([
                'ok' => true,
                'automationId' => $automationId,
                'itemsInserted' => count($items),
            ]);
        });
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

    public function submitPriceFeedSync(Request $request)
    {
        $data = $request->validate([
            'store' => ['required', 'string'],
            'marketplaceIds' => ['required', 'array', 'min:1'],
            'marketplaceIds.*' => ['required', 'string'],
            'currency' => ['nullable', 'string'],
            'updates' => ['required', 'array', 'min:1', 'max:25000'],
            'updates.*.sku' => ['required', 'string'],
            'updates.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $store = $data['store'];
        $marketplaceIds = array_values($data['marketplaceIds']);
        $marketplaceId = $marketplaceIds[0];
        $currency = $data['currency'] ?? 'USD';
        $updates = array_values($data['updates']);

        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return response()->json([
                'success' => false,
                'message' => "No credentials found for store: {$store}",
            ], 422);
        }

        $sellerId = $credentials['MerchantID'] ?? null;
        if (!$sellerId) {
            return response()->json([
                'success' => false,
                'message' => "Missing MerchantID / sellerId for store: {$store}",
            ], 422);
        }

        $accessToken = fetchAccessToken($credentials, false);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => "Failed to fetch access token for store: {$store}",
            ], 422);
        }

        $feedBody = $this->buildJsonListingsPriceFeedBody(
            sellerId: $sellerId,
            marketplaceId: $marketplaceId,
            currency: $currency,
            updates: $updates
        );

        try {
            $document = $this->spCreateFeedDocument(
                credentials: $credentials,
                accessToken: $accessToken,
                contentType: 'application/json; charset=UTF-8'
            );

            $feedDocumentId = $document['feedDocumentId'] ?? null;
            $uploadUrl = $document['url'] ?? null;

            if (!$feedDocumentId || !$uploadUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amazon did not return feedDocumentId/url.',
                    'documentResponse' => $document,
                ], 500);
            }

            $uploadResult = $this->uploadFeedDocumentToAmazon(
                uploadUrl: $uploadUrl,
                jsonBody: json_encode($feedBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            if (!$uploadResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed uploading feed document to Amazon.',
                    'upload' => $uploadResult,
                ], 500);
            }

            $feedCreate = $this->spCreateFeed(
                credentials: $credentials,
                accessToken: $accessToken,
                feedType: 'JSON_LISTINGS_FEED',
                marketplaceIds: $marketplaceIds,
                inputFeedDocumentId: $feedDocumentId
            );

            $feedId = $feedCreate['feedId'] ?? null;
            if (!$feedId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amazon did not return feedId.',
                    'createFeedResponse' => $feedCreate,
                ], 500);
            }

            $poll = $this->spPollFeedUntilTerminal(
                credentials: $credentials,
                accessToken: $accessToken,
                feedId: $feedId,
                maxAttempts: 60,
                sleepSeconds: 10
            );

            $processingStatus = strtoupper((string) ($poll['processingStatus'] ?? 'UNKNOWN'));

            // Optional: update local cached prices only after Amazon reports terminal success
            if ($processingStatus === 'DONE') {
                $now = now();

                foreach ($updates as $row) {
                    DB::table('tblfnsku')
                        ->where('MSKU', $row['sku'])
                        ->where('storename', $store)
                        ->update([
                            'amzn_item_price' => round((float) $row['price'], 2),
                            'amzn_item_price_updated_at' => $now,
                        ]);
                }
            }

            return response()->json([
                'success' => $processingStatus === 'DONE',
                'store' => $store,
                'feedType' => 'JSON_LISTINGS_FEED',
                'feedDocumentId' => $feedDocumentId,
                'feedId' => $feedId,
                'submittedCount' => count($updates),
                'processingStatus' => $processingStatus,
                'poll' => $poll,
                'requestPreview' => [
                    'marketplaceIds' => $marketplaceIds,
                    'currency' => $currency,
                    'updatesCount' => count($updates),
                ],
            ], $processingStatus === 'DONE' ? 200 : 422);

        } catch (\Throwable $e) {
            Log::error('submitPriceFeedSync exception', [
                'store' => $store,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'store' => $store,
            ], 500);
        }
    }

    private function buildJsonListingsPriceFeedBody(string $sellerId, string $marketplaceId, string $currency, array $updates): array
    {
        $messages = [];
        $messageId = 1;

        foreach ($updates as $row) {
            $sku = (string) $row['sku'];
            $price = round((float) $row['price'], 2);

            $messages[] = [
                'messageId' => $messageId++,
                'sku' => $sku,
                'operationType' => 'PATCH',
                'productType' => 'PRODUCT',
                'patches' => [
                    [
                        'op' => 'replace',
                        'path' => '/attributes/purchasable_offer',
                        'value' => [
                            [
                                'marketplace_id' => $marketplaceId,
                                'currency' => $currency,
                                'audience' => 'ALL',
                                'our_price' => [
                                    [
                                        'schedule' => [
                                            [
                                                'value_with_tax' => $price,
                                            ]
                                        ],
                                    ]
                                ],
                            ]
                        ],
                    ],
                ],
            ];
        }

        return [
            'header' => [
                'sellerId' => $sellerId,
                'version' => '2.0',
                'issueLocale' => 'en_US',
            ],
            'messages' => $messages,
        ];
    }

    private function spCreateFeedDocument(array $credentials, string $accessToken, string $contentType = 'application/json; charset=UTF-8'): array
    {
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $path = '/feeds/2021-06-30/documents';
        $body = [
            'contentType' => $contentType,
        ];

        $headers = buildHeaders(
            $credentials,
            $accessToken,
            'POST',
            'execute-api',
            'us-east-1',
            $path,
            null,
            [],
            $endpoint,
            'host:sellingpartnerapi-na.amazon.com'
        );

        $headers['Content-Type'] = 'application/json';
        $headers['accept'] = 'application/json';

        $url = $endpoint . $path;

        $response = Http::timeout(60)
            ->withHeaders($headers)
            ->post($url, $body);

        if (!$response->successful()) {
            throw new \Exception('createFeedDocument failed: HTTP ' . $response->status() . ' ' . $response->body());
        }

        return $response->json() ?? [];
    }

    private function uploadFeedDocumentToAmazon(string $uploadUrl, string $jsonBody): array
    {
        $response = Http::timeout(120)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
            ])
            ->withBody($jsonBody, 'application/json; charset=UTF-8')
            ->put($uploadUrl);

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->body(),
        ];
    }

    private function spCreateFeed(array $credentials, string $accessToken, string $feedType, array $marketplaceIds, string $inputFeedDocumentId): array
    {
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $path = '/feeds/2021-06-30/feeds';

        $body = [
            'feedType' => $feedType,
            'marketplaceIds' => array_values($marketplaceIds),
            'inputFeedDocumentId' => $inputFeedDocumentId,
        ];

        $headers = buildHeaders(
            $credentials,
            $accessToken,
            'POST',
            'execute-api',
            'us-east-1',
            $path,
            null,
            [],
            $endpoint,
            'host:sellingpartnerapi-na.amazon.com'
        );

        $headers['Content-Type'] = 'application/json';
        $headers['accept'] = 'application/json';

        $url = $endpoint . $path;

        $response = Http::timeout(60)
            ->withHeaders($headers)
            ->post($url, $body);

        if (!$response->successful()) {
            throw new \Exception('createFeed failed: HTTP ' . $response->status() . ' ' . $response->body());
        }

        return $response->json() ?? [];
    }

    private function spGetFeed(array $credentials, string $accessToken, string $feedId): array
    {
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $path = '/feeds/2021-06-30/feeds/' . rawurlencode($feedId);

        $headers = buildHeaders(
            $credentials,
            $accessToken,
            'GET',
            'execute-api',
            'us-east-1',
            $path,
            null,
            [],
            $endpoint,
            'host:sellingpartnerapi-na.amazon.com'
        );

        $headers['accept'] = 'application/json';

        $url = $endpoint . $path;

        $response = Http::timeout(60)
            ->withHeaders($headers)
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception('getFeed failed: HTTP ' . $response->status() . ' ' . $response->body());
        }

        return $response->json() ?? [];
    }

    private function spPollFeedUntilTerminal(array $credentials, string $accessToken, string $feedId, int $maxAttempts = 60, int $sleepSeconds = 10): array
    {
        $terminal = ['DONE', 'FATAL', 'CANCELLED'];

        for ($i = 1; $i <= $maxAttempts; $i++) {
            $feed = $this->spGetFeed($credentials, $accessToken, $feedId);
            $status = strtoupper((string) ($feed['processingStatus'] ?? ''));

            if (in_array($status, $terminal, true)) {
                $feed['_poll_attempts'] = $i;
                return $feed;
            }

            sleep($sleepSeconds);
        }

        $feed = $this->spGetFeed($credentials, $accessToken, $feedId);
        $feed['_poll_attempts'] = $maxAttempts;
        return $feed;
    }

    public function getFeedStatus(Request $request)
    {
        $request->validate([
            'store' => 'required|string',
            'feedId' => 'required|string',
        ]);

        $store = $request->store;
        $feedId = $request->feedId;

        try {
            $feed = $this->getAmazonFeed($store, $feedId);

            $processingStatus = $feed['processingStatus'] ?? null;
            $documentId =
                $feed['resultFeedDocumentId']
                ?? $feed['resultDocumentId']
                ?? null;

            $documentMeta = null;
            $report = null;
            $summary = null;
            $results = [];

            if ($documentId) {
                $documentMeta = $this->getAmazonFeedDocument($store, $documentId);

                $url = $documentMeta['url'] ?? null;
                $compression = $documentMeta['compressionAlgorithm'] ?? null;

                if ($url) {
                    $raw = $this->downloadFeedDocument($url);

                    if (strtoupper((string) $compression) === 'GZIP') {
                        $decoded = gzdecode($raw);
                        if ($decoded !== false) {
                            $raw = $decoded;
                        }
                    }

                    $report = json_decode($raw, true);

                    $summary = $report['processingSummary'] ?? null;
                    $results = $report['results'] ?? [];
                }
            }

            return response()->json([
                'success' => true,
                'feedId' => $feedId,
                'processingStatus' => $processingStatus,
                'documentId' => $documentId,
                'summary' => $summary,
                'results' => $results,
                'rawFeed' => $feed,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function getAmazonFeed(string $store, string $feedId): array
    {
        $path = "/feeds/2021-06-30/feeds/{$feedId}";
        $headers = $this->buildHeaders($store, $path, 'GET');

        $ch = curl_init("https://sellingpartnerapi-na.amazon.com{$path}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \Exception("getFeed curl error: {$err}");
        }

        $json = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            $msg = $json['message'] ?? $raw;
            throw new \Exception("getFeed failed HTTP {$code}: {$msg}");
        }

        return $json;
    }

    protected function getAmazonFeedDocument(string $store, string $documentId): array
    {
        $path = "/feeds/2021-06-30/documents/{$documentId}";
        $headers = $this->buildHeaders($store, $path, 'GET');

        $ch = curl_init("https://sellingpartnerapi-na.amazon.com{$path}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \Exception("getFeedDocument curl error: {$err}");
        }

        $json = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            $msg = $json['message'] ?? $raw;
            throw new \Exception("getFeedDocument failed HTTP {$code}: {$msg}");
        }

        return $json;
    }

    protected function downloadFeedDocument(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \Exception("downloadFeedDocument curl error: {$err}");
        }

        if ($code < 200 || $code >= 300) {
            throw new \Exception("downloadFeedDocument failed HTTP {$code}");
        }

        return $raw;
    }
}