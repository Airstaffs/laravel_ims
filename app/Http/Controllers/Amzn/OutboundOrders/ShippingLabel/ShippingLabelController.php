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

class ShippingLabelController extends Controller
{


    public function shipmentLabelHistory(Request $request)
    {
        $page = max((int) $request->input('page', 1), 1);
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $keyword = trim((string) $request->input('keyword', ''));

        // ✅ sort: asc|desc (default asc)
        $sort = strtolower((string) $request->input('sort', 'asc'));
        $sort = in_array($sort, ['asc', 'desc'], true) ? $sort : 'asc';

        $base = DB::table('tbllabelhistory as lh')
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($qq) use ($keyword) {
                    $qq->where('lh.AmazonOrderId', 'like', "%{$keyword}%")
                        ->orWhere('lh.trackingid', 'like', "%{$keyword}%")
                        ->orWhere('lh.user', 'like', "%{$keyword}%")
                        ->orWhere('lh.ShippingServiceId', 'like', "%{$keyword}%");
                });
            });

        // total count (labelhistory rows)
        $total = (clone $base)->count('lh.id');

        $historyRows = (clone $base)
            // ✅ apply requested direction (id is safest for stable paging)
            ->orderBy('lh.id', $sort)
            ->forPage($page, $perPage)
            ->get([
                'lh.id',
                'lh.shipmentid',
                'lh.AmazonOrderId',
                'lh.status',
                'lh.trackingid',
                'lh.createdDate',
                'lh.updatedDate',
                'lh.ShippingServiceId',
                'lh.ShippingServiceOfferId',
                'lh.labelprice',
                'lh.user',
                'lh.invoicenumberid',
                'lh.scanned_status',
                'lh.insert_log',
                'lh.trackingid_status',
                'lh.ShipDate',
            ]);

        $ids = $historyRows->pluck('id')->filter()->values()->all();

        $itemsByHistoryId = [];
        if (!empty($ids)) {
            $items = DB::table('tbllabelhistoryitems as li')
                ->whereIn('li.labelhistory_id', $ids)
                // (optional) keep items stable inside expansion
                ->orderBy('li.id', 'asc')
                ->get([
                    'li.id',
                    'li.labelhistory_id',
                    'li.shipmentid',
                    'li.AmazonOrderId',
                    'li.orderitemid',
                    'li.trackingid',
                    'li.shipDate',
                    'li.EarliestEstimatedDeliveryDate',
                    'li.LatestEstimatedDeliveryDate',
                    'li.labelprice',
                    'li.DeliveryExperience',
                ]);

            foreach ($items as $it) {
                $itemsByHistoryId[$it->labelhistory_id][] = $it;
            }
        }

        $data = $historyRows->map(function ($row) use ($itemsByHistoryId) {
            $row->items = $itemsByHistoryId[$row->id] ?? [];
            return $row;
        });

        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max($lastPage, 1),
                'sort' => $sort, // ✅ helpful for frontend
            ],
        ]);
    }

    public function get_rates(Request $request)
    {
        $orders = $request->input('orders', []);
        $forms = $request->input('forms', []);

        if (empty($orders) || empty($forms)) {
            return response()->json(['error' => 'Missing orders or form data'], 400);
        }

        $destinationMarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $nextToken = $request->input('nextToken', null);

        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/mfn/v0/eligibleShippingServices';
        $customParams = [];



        $allRates = [];

        foreach ($orders as $order) {
            $platformOrderId = $order['platform_order_id'] ?? null;
            $rawStore = $order['storename'] ?? '';

            $storeKey = strtolower(
                preg_replace('/\s+/', '', trim($rawStore))
            );

            $storeMap = [
                'allrenewed' => 'Allrenewed',
                'renovartech' => 'Renovartech',
            ];

            $store = $storeMap[$storeKey] ?? ucfirst($storeKey);

            $companydetails = $this->fetchCompanyDetails($store);
            if (!$companydetails) {
                return response()->json(['error' => 'Company not found'], 404);
            }

            $form = $forms[$platformOrderId] ?? null;

            if (!$platformOrderId || !$form)
                continue;

            $credentials = AWSCredentials($store);
            if (!$credentials) {
                $allRates[] = [
                    'platform_order_id' => $platformOrderId,
                    'error' => 'No credentials found for store: ' . $store
                ];
                continue;
            }

            $accessToken = fetchAccessToken($credentials, false);
            if (!$accessToken) {
                $allRates[] = [
                    'platform_order_id' => $platformOrderId,
                    'error' => 'Failed to fetch access token.',
                    'credentials' => $credentials
                ];
                continue;
            }

            // Normalize weight input
            $originalWeightValue = (float) $form['weight'];
            $originalWeightUnit = strtolower($form['weightUnit']);

            // Convert to grams or ounces
            if ($originalWeightUnit === 'pound') {
                $normalizedWeightUnit = 'grams';
                $convertedWeightValue = $originalWeightValue * 453.592;
            } elseif ($originalWeightUnit === 'kilogram') {
                $normalizedWeightUnit = 'grams';
                $convertedWeightValue = $originalWeightValue * 1000;
            } else {
                // Assume user already entered ounces or grams properly
                $normalizedWeightUnit = $originalWeightUnit;
                $convertedWeightValue = $originalWeightValue;
            }

            // Build item list with per-item weights
            $itemList = collect($order['items'] ?? [])->map(function ($item) use ($convertedWeightValue, $normalizedWeightUnit) {
                return [
                    'OrderItemId' => $item['platform_order_item_id'],
                    'Quantity' => $item['QuantityOrdered'] ?? 1,
                    'ItemWeight' => [
                        'Value' => $convertedWeightValue,
                        'Unit' => $normalizedWeightUnit
                    ]
                ];
            })->values()->all();

            // Calculate total weight
            $totalWeightValue = array_reduce($itemList, function ($carry, $item) {
                return $carry + ($item['Quantity'] * $item['ItemWeight']['Value']);
            }, 0);

            // Final payload
            $data_additionale = [
                'AmazonOrderId' => $platformOrderId,
                'orderitems' => $itemList,

                // Package dimensions
                'package_dimensions_length' => $form['length'],
                'package_dimensions_width' => $form['width'],
                'package_dimensions_height' => $form['height'],
                'package_dimensions_unit' => $form['dimensionUnit'],

                // Total package weight
                'package_weight_value' => $totalWeightValue,
                'package_weight_unit' => $normalizedWeightUnit,

                // Shipping options
                'deliveryExperience' => $form['deliveryExperience'],
                'Shipping_valueCurrencyCode' => $form['currency'] ?? 'USD',

                // Dates
                'Shipby_Datetime' => $form['shipBy'],
                // 'Delivered_Datetime' => $form['deliverBy'],
            ];

            $jsonData = $this->JsonCreation('get_rates', $companydetails, $destinationMarketplace, $data_additionale);
            if ($jsonData === false) {
                Log::error('JSON Encoding Failed for order: ' . $platformOrderId, ['error' => json_last_error_msg()]);
                continue;
            }

            try {
                $headers = buildHeaders($credentials, $accessToken, 'POST', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
                $headers['Content-Type'] = 'application/json';
                $headers['accept'] = 'application/json';

                $queryString = buildQueryString($nextToken, $customParams);
                $url = "{$endpoint}{$path}{$queryString}";

                $response = Http::timeout(50)
                    ->withHeaders($headers)
                    ->withBody($jsonData, 'application/json')
                    ->post($url);

                $curlInfo = $response->handlerStats();

                if ($response->successful()) {
                    $allRates[] = [
                        'platform_order_id' => $platformOrderId,
                        'rates' => $response->json(),
                        'logs' => $curlInfo
                    ];
                } else {
                    $allRates[] = [
                        'platform_order_id' => $platformOrderId,
                        'error' => $response->json(),
                        'status' => $response->status(),
                        'logs' => $curlInfo
                    ];
                }
            } catch (\Exception $e) {
                $allRates[] = [
                    'platform_order_id' => $platformOrderId,
                    'exception' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $allRates
        ]);
    }

    public function create_shipment(Request $request)
    {
        $orders = $request->input('orders', []);
        $forms = $request->input('forms', []);



        if (empty($orders) || empty($forms)) {
            return response()->json(['error' => 'Missing orders or form data'], 400);
        }

        $destinationMarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $nextToken = $request->input('nextToken', null);

        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/mfn/v0/shipments';
        $customParams = [];



        $Results = [];

        foreach ($orders as $order) {
            $platformOrderId = $order['platform_order_id'] ?? null;
            $rawStore = $order['storename'] ?? '';
            $storeKey = strtolower(preg_replace('/\s+/', '', trim($rawStore)));

            $storeMap = [
                'allrenewed' => 'Allrenewed',
                'renovartech' => 'Renovartech',
            ];

            $store = $storeMap[$storeKey] ?? ucfirst($storeKey);

            $companydetails = $this->fetchCompanyDetails($store);
            if (!$companydetails) {
                return response()->json(['error' => 'Company not found'], 404);
            }

            $form = $forms[$platformOrderId] ?? null;

            $shippingService = $order['selectedCarrier'] ?? null;

            if (!$shippingService) {
                $Results[] = [
                    'platform_order_id' => $platformOrderId,
                    'error' => 'Missing selectedCarrier for this order.'
                ];
                continue;
            }

            $ShippingServiceId = $shippingService['ShippingServiceId'] ?? null;
            $ShippingServiceOfferId = $shippingService['ShippingServiceOfferId'] ?? null;

            if (!$ShippingServiceId || !$ShippingServiceOfferId) {
                $Results[] = [
                    'platform_order_id' => $platformOrderId,
                    'error' => 'Selected carrier missing ShippingServiceId or ShippingServiceOfferId.',
                    'selectedCarrier' => $shippingService
                ];
                continue;
            }

            if (!$platformOrderId || !$form)
                continue;

            $credentials = AWSCredentials($store);
            if (!$credentials) {
                $Results[] = [
                    'platform_order_id' => $platformOrderId,
                    'error' => 'No credentials found for store: ' . $store
                ];
                continue;
            }

            $accessToken = fetchAccessToken($credentials, false);
            if (!$accessToken) {
                $Results[] = [
                    'platform_order_id' => $platformOrderId,
                    'error' => 'Failed to fetch access token.',
                    'credentials' => $credentials
                ];
                continue;
            }

            // Normalize weight input
            $originalWeightValue = (float) $form['weight'];
            $originalWeightUnit = strtolower($form['weightUnit']);

            // Convert to grams or ounces
            if ($originalWeightUnit === 'pound') {
                $normalizedWeightUnit = 'grams';
                $convertedWeightValue = $originalWeightValue * 453.592;
            } elseif ($originalWeightUnit === 'kilogram') {
                $normalizedWeightUnit = 'grams';
                $convertedWeightValue = $originalWeightValue * 1000;
            } else {
                // Assume user already entered ounces or grams properly
                $normalizedWeightUnit = $originalWeightUnit;
                $convertedWeightValue = $originalWeightValue;
            }

            // Build item list with per-item weights
            $itemList = collect($order['items'] ?? [])->map(function ($item) use ($convertedWeightValue, $normalizedWeightUnit) {
                return [
                    'OrderItemId' => $item['platform_order_item_id'],
                    'Quantity' => $item['QuantityOrdered'] ?? 1,
                    'ItemWeight' => [
                        'Value' => $convertedWeightValue,
                        'Unit' => $normalizedWeightUnit
                    ]
                ];
            })->values()->all();

            // Calculate total weight
            $totalWeightValue = array_reduce($itemList, function ($carry, $item) {
                return $carry + ($item['Quantity'] * $item['ItemWeight']['Value']);
            }, 0);

            // Final payload
            $data_additionale = [
                'AmazonOrderId' => $platformOrderId,
                'orderitems' => $itemList,

                // Package dimensions
                'package_dimensions_length' => $form['length'],
                'package_dimensions_width' => $form['width'],
                'package_dimensions_height' => $form['height'],
                'package_dimensions_unit' => $form['dimensionUnit'],

                // Total package weight
                'package_weight_value' => $totalWeightValue,
                'package_weight_unit' => $normalizedWeightUnit,

                // Shipping options
                'deliveryExperience' => $form['deliveryExperience'],
                'Shipping_valueCurrencyCode' => $form['currency'] ?? 'USD',

                // Dates
                'Shipby_Datetime' => $form['shipBy'],
                // 'Delivered_Datetime' => $form['deliverBy'],

                // Carrier Data
                'ShippingServiceId' => $ShippingServiceId,
                'ShippingServiceOfferId' => $ShippingServiceOfferId,
            ];

            $ShippingServiceId = $shippingService['ShippingServiceId'] ?? null;
            $ShippingServiceOfferId = $shippingService['ShippingServiceOfferId'] ?? null;


            $jsonData = $this->JsonCreation('create_shipment', $companydetails, $destinationMarketplace, $data_additionale);
            if ($jsonData === false) {
                Log::error('JSON Encoding Failed for order: ' . $platformOrderId, ['error' => json_last_error_msg()]);
                continue;
            }

            try {

                $headers = buildHeaders($credentials, $accessToken, 'POST', 'execute-api', 'us-east-1', $path, $nextToken, $customParams, $endpoint, $canonicalHeaders);
                $headers['Content-Type'] = 'application/json';
                $headers['accept'] = 'application/json';

                $queryString = buildQueryString($nextToken, $customParams);
                $url = "{$endpoint}{$path}{$queryString}";

                $response = Http::timeout(50)
                    ->withHeaders($headers)
                    ->withBody($jsonData, 'application/json')
                    ->post($url);

                $curlInfo = $response->handlerStats();

                if ($response->successful()) {
                    $dbLog = $this->insertShipmentData($order, $response->json(), $form, $shippingService);

                    $Results[] = [
                        'platform_order_id' => $platformOrderId,
                        'rates' => $response->json(),
                        'logs' => $curlInfo,
                        'db' => $dbLog, // ✅ include DB step-by-step results
                    ];
                } else {
                    $Results[] = [
                        'platform_order_id' => $platformOrderId,
                        'error' => $response->json(),
                        'status' => $response->status(),
                        'logs' => $curlInfo
                    ];
                }
            } catch (\Exception $e) {
                $Results[] = [
                    'platform_order_id' => $platformOrderId,
                    'exception' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $Results
        ]);
    }

    public function cancelShipmentLabel(Request $request)
    {
        $labelHistoryId = (int) $request->input('id', 0);

        if ($labelHistoryId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Missing or invalid label history id.',
            ], 422);
        }

        // 1) Load label history row
        $lh = DB::table('tbllabelhistory')
            ->where('id', $labelHistoryId)
            ->first();

        if (!$lh) {
            return response()->json([
                'success' => false,
                'message' => "Label history row not found for id {$labelHistoryId}.",
            ], 404);
        }

        if (strtolower((string) $lh->status) === 'cancelled') {
            return response()->json([
                'success' => true,
                'message' => 'Already cancelled.',
            ]);
        }

        $shipmentId = trim((string) $lh->shipmentid);
        $amazonOrderId = trim((string) $lh->AmazonOrderId);

        if ($shipmentId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing shipmentid on tbllabelhistory row.',
            ], 422);
        }

        // 2) Determine store (Renovartech / Allrenewed) from outbound tables
        //    Adjust platform value if yours differs.
        $store = DB::table('tbloutboundorders')
            ->where('platform_order_id', $amazonOrderId)
            ->value('storename');

        if (!$store) {
            // fallback: some setups can infer via outbound items
            $store = DB::table('tbloutboundordersitem')
                ->where('platform_order_id', $amazonOrderId)
                ->value('storename');
        }

        // Normalize store name to match AWSCredentials(storename)
        $rawStore = strtolower(preg_replace('/\s+/', '', trim((string) $store)));
        $storeMap = [
            'allrenewed' => 'Allrenewed',
            'renovartech' => 'Renovartech',
            'ar' => 'Allrenewed',
            'rt' => 'Renovartech',
        ];
        $storeName = $storeMap[$rawStore] ?? (ucfirst($rawStore) ?: 'Renovartech');

        // 3) Amazon DELETE /mfn/v0/shipments/{shipmentId}
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $service = 'execute-api';
        $region = 'us-east-1';
        $method = 'DELETE';
        $path = "/mfn/v0/shipments/{$shipmentId}";
        $nextToken = null;
        $customParams = [];

        $credentials = AWSCredentials($storeName);
        if (!$credentials) {
            return response()->json([
                'success' => false,
                'message' => "No AWS credentials found for store: {$storeName}",
            ], 400);
        }

        $accessToken = fetchAccessToken($credentials, false);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 400);
        }

        try {
            $headers = buildHeaders(
                $credentials,
                $accessToken,
                $method,
                $service,
                $region,
                $path,
                $nextToken,
                $customParams,
                $endpoint,
                $canonicalHeaders
            );

            $headers['accept'] = 'application/json';

            $url = "{$endpoint}{$path}";

            $resp = Http::timeout(50)
                ->withHeaders($headers)
                ->delete($url);

            $status = $resp->status();
            $body = $resp->json();

            // Acceptable “success-ish” statuses:
            // - 200/204: success
            // If Amazon returns 404/400 for already-cancelled or not found, you can decide:
            // I’m treating ONLY 200/204 as true success to be safe.
            if (!in_array($status, [200, 204], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amazon cancel failed.',
                    'amazon_status' => $status,
                    'amazon_body' => $body,
                    'Data' => $shipmentId,
                ], 400);
            }

        } catch (\Throwable $e) {
            Log::error('[cancelShipmentLabel] Amazon exception', [
                'shipmentid' => $shipmentId,
                'AmazonOrderId' => $amazonOrderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Amazon cancel exception: ' . $e->getMessage(),
            ], 500);
        }

        // 4) DB updates: tbllabelhistory + return products Shipment->Stockroom
        try {
            DB::beginTransaction();

            DB::table('tbllabelhistory')
                ->where('id', $labelHistoryId)
                ->update([
                    'status' => 'Cancelled',
                    'updatedDate' => now(),
                ]);

            // Pull label items for this shipment
            $labelItems = DB::table('tbllabelhistoryitems')
                ->where('labelhistory_id', $labelHistoryId)
                ->get(['AmazonOrderId', 'orderitemid']);

            $productIds = [];

            if ($labelItems->count()) {
                $latestOi = DB::table('tbloutboundordersitem')
                    ->selectRaw('platform_order_id, platform_order_item_id, MAX(outboundorderitemid) as outboundorderitemid')
                    ->groupBy('platform_order_id', 'platform_order_item_id');

                $productIds = DB::table('tbllabelhistoryitems as li')
                    ->joinSub($latestOi, 'oi', function ($join) {
                        $join->on('oi.platform_order_id', '=', 'li.AmazonOrderId')
                            ->on('oi.platform_order_item_id', '=', 'li.orderitemid');
                    })
                    ->join('tblorderitemdispense as d', function ($join) {
                        $join->on('d.orderitemid', '=', 'oi.outboundorderitemid');
                    })
                    ->where('li.labelhistory_id', $labelHistoryId)
                    ->pluck('d.ProductID')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }

            // Fallback if dispense table didn’t match
            if (empty($productIds) && $labelItems->count()) {
                $pairs = $labelItems->map(fn($x) => [$x->AmazonOrderId, $x->orderitemid])->values();

                foreach ($pairs as $pair) {
                    [$ao, $oi] = $pair;

                    $pid = DB::table('tbloutboundordersitem')
                        ->where('platform_order_id', $ao)
                        ->where('platform_order_item_id', $oi)
                        ->orderByDesc('outboundorderitemid')
                        ->value('ProductID');

                    if ($pid)
                        $productIds[] = $pid;
                }

                $productIds = array_values(array_unique(array_filter($productIds)));
            }

            if (!empty($productIds)) {
                DB::table('tblproduct')
                    ->whereIn('ProductID', $productIds)
                    ->where('ProductModuleLoc', 'Shipment')
                    ->update([
                        'ProductModuleLoc' => 'Stockroom'
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cancelled on Amazon + moved products back to Stockroom.',
                'shipmentid' => $shipmentId,
                'AmazonOrderId' => $amazonOrderId,
                'productIdsCount' => count($productIds),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('[cancelShipmentLabel] DB exception', [
                'labelHistoryId' => $labelHistoryId,
                'shipmentid' => $shipmentId,
                'AmazonOrderId' => $amazonOrderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'DB update failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function manual_shipment(Request $request)
    {
    }

    protected function fetchCompanyDetails($store)
    {
        if ($store === 'Allrenewed') {
            return DB::table('tblcompanydetails')->where('id', 3)->first();
        }

        if ($store === 'Renovartech') {
            return DB::table('tblcompanydetails')->where('id', 1)->first();
        }

        return null;
    }

    protected function JsonCreation($action, $companydetails, $marketplaceID, $data_additionale)
    {
        $final_json_construct = [];

        $companydetails = (array) $companydetails;

        if ($action == 'get_rates') {
            $final_json_construct = [

                "ShipmentRequestDetails" => [
                    "AmazonOrderId" => $data_additionale['AmazonOrderId'],
                    "ItemList" => $data_additionale['orderitems'],
                    "ShipFromAddress" => [
                        "Name" => $companydetails['CompanyName'],
                        "AddressLine1" => $companydetails['StreetAddress'],
                        "Email" => $companydetails['Email'],
                        "City" => $companydetails['City'],
                        "StateOrProvinceCode" => $companydetails['State'],
                        "PostalCode" => $companydetails['ZIPCode'],
                        "CountryCode" => $companydetails['CountryCode'],
                        "Phone" => $companydetails['Contact']
                    ],
                    "PackageDimensions" => [
                        "Length" => $data_additionale['package_dimensions_length'],
                        "Width" => $data_additionale['package_dimensions_width'],
                        "Height" => $data_additionale['package_dimensions_height'],
                        "Unit" => $data_additionale['package_dimensions_unit']
                    ],
                    "Weight" => [
                        "Value" => $data_additionale['package_weight_value'],
                        "Unit" => $data_additionale['package_weight_unit']
                    ],
                    "ShippingServiceOptions" => [
                        "DeliveryExperience" => $data_additionale['deliveryExperience'],
                        "CarrierWillPickUp" => false,
                        // "CarrierWillPickUpOption" => $data_additionale['carrierPickUpOption'],
                        "LabelFormat" => "PDF"
                    ],
                    "LabelCustomization" => [
                        "AmazonOrderId" => $data_additionale['AmazonOrderId']
                    ],
                    "ShipDate" => $data_additionale['Shipby_Datetime'],
                ],
                "ShippingOfferingFilter" => [
                    "IncludeComplexShippingOptions" => 'true'
                ]

            ];
        } else if ($action == 'create_shipment') {
            $final_json_construct = [
                "ShipmentRequestDetails" => [
                    "AmazonOrderId" => $data_additionale['AmazonOrderId'],
                    "ItemList" => $data_additionale['orderitems'],
                    "ShipFromAddress" => [
                        "Name" => $companydetails['CompanyName'],
                        "AddressLine1" => $companydetails['StreetAddress'],
                        "Email" => $companydetails['Email'],
                        "City" => $companydetails['City'],
                        "StateOrProvinceCode" => $companydetails['State'],
                        "PostalCode" => $companydetails['ZIPCode'],
                        "CountryCode" => $companydetails['CountryCode'],
                        "Phone" => $companydetails['Contact']
                    ],
                    "PackageDimensions" => [
                        "Length" => $data_additionale['package_dimensions_length'],
                        "Width" => $data_additionale['package_dimensions_width'],
                        "Height" => $data_additionale['package_dimensions_height'],
                        "Unit" => $data_additionale['package_dimensions_unit']
                    ],
                    "Weight" => [
                        "Value" => $data_additionale['package_weight_value'],
                        "Unit" => $data_additionale['package_weight_unit']
                    ],
                    "ShippingServiceOptions" => [
                        "DeliveryExperience" => $data_additionale['deliveryExperience'],
                        "CarrierWillPickUp" => false,
                        // "CarrierWillPickUpOption" => $data_additionale['carrierPickUpOption'],
                        "LabelFormat" => "PDF"
                    ],
                    "LabelCustomization" => [
                        "AmazonOrderId" => $data_additionale['AmazonOrderId']
                    ],
                    "ShipDate" => $data_additionale['Shipby_Datetime'],
                ],
                "ShippingServiceId" => $data_additionale['ShippingServiceId'],
                "ShippingServiceOfferId" => $data_additionale['ShippingServiceOfferId']
            ];
        }

        // Ensure JSON encoding before returning
        return json_encode($final_json_construct, JSON_UNESCAPED_SLASHES);
    }

    protected function tetristerGroupFit($AmazonOrderId, $orderitems, $platform, $store)
    {
        $items = [];

        foreach ($orderitems as $item) {
            $orderitemid = $item['orderitemid'] ?? null;
            if (!$orderitemid)
                continue;

            // Get outbound item + order
            $orderData = DB::table('tbloutboundordersitem as i')
                ->join('tbloutboundorders as o', function ($join) use ($platform) {
                    $join->on('i.platform_order_id', '=', 'o.platform_order_id')
                        ->where('i.platform', '=', $platform)
                        ->where('o.platform', '=', $platform);
                })
                ->select('o.*', 'i.*')
                ->where('i.platform_order_id', $AmazonOrderId)
                ->where('i.platform_order_item_id', $orderitemid)
                ->where('o.store', $store)
                ->first();

            if (!$orderData || !$orderData->platform_asin)
                continue;

            // Get item dimensions
            $itemDetails = DB::table('tblasin')
                ->where('ASIN', $orderData->platform_asin)
                ->select('dimension_length', 'dimension_width', 'dimension_height')
                ->first();

            if (!$itemDetails)
                continue;

            // Use only clean floats
            $length = (float) $itemDetails->dimension_length;
            $width = (float) $itemDetails->dimension_width;
            $height = (float) $itemDetails->dimension_height;

            $items[] = compact('length', 'width', 'height');
        }

        if (empty($items)) {
            return ['status' => 'no_items_found'];
        }

        // Estimate total bounding box (simplified 3D stacking)
        $totalVolume = 0;
        $maxLength = 0;
        $maxWidth = 0;
        $totalHeight = 0;

        foreach ($items as $item) {
            $totalVolume += $item['length'] * $item['width'] * $item['height'];
            $maxLength = max($maxLength, $item['length']);
            $maxWidth = max($maxWidth, $item['width']);
            $totalHeight += $item['height']; // stacked height
        }

        // Try to find a box that can fit the stack (L × W × totalH)
        $boxes = DB::table('tblpackagedimensions')->get();

        $fits = $boxes->filter(function ($box) use ($maxLength, $maxWidth, $totalHeight, $totalVolume) {
            $orientations = [
                [$box->length, $box->width, $box->height],
                [$box->length, $box->height, $box->width],
                [$box->width, $box->length, $box->height],
                [$box->width, $box->height, $box->length],
                [$box->height, $box->length, $box->width],
                [$box->height, $box->width, $box->length],
            ];

            foreach ($orientations as [$bl, $bw, $bh]) {
                if (
                    $maxLength <= $bl &&
                    $maxWidth <= $bw &&
                    $totalHeight <= $bh
                ) {
                    return true;
                }
            }

            return false;
        });

        // Pick smallest volume fitting box
        $bestBox = $fits->sortBy(function ($b) {
            return $b->length * $b->width * $b->height;
        })->first();

        return [
            'status' => $bestBox ? 'box_found' : 'no_box_found',
            'total_items' => count($items),
            'stack_dimensions' => [
                'length' => $maxLength,
                'width' => $maxWidth,
                'height' => $totalHeight,
                'volume' => $totalVolume
            ],
            'selected_box' => $bestBox ? [
                'id' => $bestBox->id,
                'description' => $bestBox->description,
                'length' => $bestBox->length,
                'width' => $bestBox->width,
                'height' => $bestBox->height,
                'volume' => $bestBox->length * $bestBox->width * $bestBox->height
            ] : null
        ];
    }

    private function insertShipmentData(array $order, array $apiData, array $form, array $selectedCarrier): array
    {
        $log = [
            'amazonOrderId' => $order['platform_order_id'] ?? null,
            'steps' => [],
            'ok' => false,
        ];

        $user = session('user_name', 'Unknown');
        $amazonOrderId = $order['platform_order_id'] ?? null;

        if (!$amazonOrderId) {
            $log['steps'][] = ['step' => 'validate', 'ok' => false, 'error' => 'Missing platform_order_id'];
            return $log;
        }

        $payload = $apiData['payload'] ?? $apiData;

        $shipmentId = data_get($payload, 'ShipmentId');
        $status = data_get($payload, 'Status');
        $trackingId = data_get($payload, 'TrackingId');

        $shippingServiceId =
            data_get($payload, 'ShippingService.ShippingServiceId')
            ?? ($selectedCarrier['ShippingServiceId'] ?? null);

        $shippingServiceOfferId =
            data_get($payload, 'ShippingService.ShippingServiceOfferId')
            ?? ($selectedCarrier['ShippingServiceOfferId'] ?? null);

        $shipDateRaw = data_get($payload, 'ShippingService.ShipDate');
        $shipDate = $shipDateRaw ? Carbon::parse($shipDateRaw)->setTimezone('UTC')->format('Y-m-d H:i:s') : null;

        $earliestRaw = data_get($payload, 'ShippingService.EarliestEstimatedDeliveryDate');
        $earliest = $earliestRaw ? Carbon::parse($earliestRaw)->setTimezone('UTC')->format('Y-m-d H:i:s') : null;

        $latestRaw = data_get($payload, 'ShippingService.LatestEstimatedDeliveryDate');
        $latest = $latestRaw ? Carbon::parse($latestRaw)->setTimezone('UTC')->format('Y-m-d H:i:s') : null;

        $rateAmount =
            $form['rate']
            ?? data_get($payload, 'ShippingService.Rate.Amount')
            ?? ($selectedCarrier['Rate']['Amount'] ?? 0.00);

        try {
            return DB::transaction(function () use (&$log, $amazonOrderId, $shipmentId, $status, $trackingId, $shippingServiceId, $shippingServiceOfferId, $rateAmount, $user, $shipDate, $earliest, $latest, $payload, $form) {

                // invoice number logic unchanged...
                $existingInvoice = DB::table('tbllabelhistory')
                    ->where('AmazonOrderId', $amazonOrderId)
                    ->value('invoicenumberid');

                $invoiceNumber = $existingInvoice ?: ((DB::table('tbllabelhistory')->max('invoicenumberid') ?? 0) + 1);

                // ✅ tbllabelhistory insert
                $labelId = DB::table('tbllabelhistory')->insertGetId([
                    'shipmentid' => $shipmentId,
                    'AmazonOrderId' => $amazonOrderId,
                    'status' => $status,
                    'trackingid' => $trackingId,
                    'updatedDate' => now(),
                    'ShippingServiceId' => $shippingServiceId,
                    'ShippingServiceOfferId' => $shippingServiceOfferId,
                    'labelprice' => $rateAmount,
                    'user' => $user,
                    'invoicenumberid' => $invoiceNumber,
                    'ShipDate' => $shipDate,
                ]);

                $log['steps'][] = [
                    'step' => 'insert_tbllabelhistory',
                    'ok' => true,
                    'labelId' => $labelId,
                    'invoiceNumber' => $invoiceNumber,
                ];

                $items = (array) data_get($payload, 'ItemList', []);
                if (!count($items)) {
                    $log['steps'][] = [
                        'step' => 'validate_itemlist',
                        'ok' => false,
                        'error' => 'Payload ItemList empty',
                    ];
                }

                foreach ($items as $item) {
                    $orderItemId = data_get($item, 'OrderItemId');
                    if (!$orderItemId) {
                        $log['steps'][] = [
                            'step' => 'skip_item',
                            'ok' => false,
                            'error' => 'Missing OrderItemId in payload item',
                            'item' => $item,
                        ];
                        continue;
                    }

                    // ✅ tbllabelhistoryitems insert
                    DB::table('tbllabelhistoryitems')->insert([
                        'shipmentid' => $shipmentId,
                        'AmazonOrderId' => $amazonOrderId,
                        'orderitemid' => $orderItemId,
                        'trackingid' => $trackingId,
                        'shipDate' => $shipDate,
                        'EarliestEstimatedDeliveryDate' => $earliest, // ✅ fixed (you had data_get() on a string before)
                        'LatestEstimatedDeliveryDate' => $latest,     // ✅ fixed
                        'labelhistory_id' => $labelId,
                        'PDFLabel' => data_get($payload, 'Label.FileContents.Contents'),
                        'DeliveryExperience' => $form['deliveryExperience'] ?? null,
                    ]);

                    $log['steps'][] = [
                        'step' => 'insert_tbllabelhistoryitems',
                        'ok' => true,
                        'orderItemId' => $orderItemId,
                    ];

                    // ✅ tbloutboundordersitem update (may affect multiple rows)
                    $rowsOutbound = DB::table('tbloutboundordersitem')
                        ->where('platform_order_id', $amazonOrderId)
                        ->where('platform_order_item_id', $orderItemId)
                        ->update([
                            'trackingnumber' => $trackingId,
                            'carrier' => data_get($payload, 'ShippingService.CarrierName') ?? ($selectedCarrier['CarrierName'] ?? null),
                            'carrier_description' => data_get($payload, 'ShippingService.ShippingServiceName') ?? ($selectedCarrier['ShippingServiceName'] ?? null),
                        ]);

                    $log['steps'][] = [
                        'step' => 'update_tbloutboundordersitem',
                        'ok' => $rowsOutbound > 0,
                        'orderItemId' => $orderItemId,
                        'rowsAffected' => $rowsOutbound,
                    ];

                    // ✅ newest outbound row wins (protect vs old duplicates)
                    $tbloutboundorderitemidid = DB::table('tbloutboundordersitem')
                        ->where('platform_order_id', $amazonOrderId)
                        ->where('platform_order_item_id', $orderItemId)
                        ->orderByDesc('outboundorderitemid')
                        ->value('outboundorderitemid');

                    // ✅ newest outbound row wins (protect vs old duplicates)
                    $productId = DB::table('tblorderitemdispense')
                        ->where('orderitemid', $tbloutboundorderitemidid)
                        ->orderByDesc('id')
                        ->value('productid');

                    if (!$productId) {
                        $log['steps'][] = [
                            'step' => 'resolve_ProductID',
                            'ok' => false,
                            'orderItemId' => $orderItemId,
                            'error' => 'ProductID not found in tbloutboundordersitem (latest row)',
                        ];
                        continue;
                    }

                    $rowsProduct = DB::table('tblproduct')
                        ->where('ProductID', $productId)
                        ->update(['ProductModuleLoc' => 'Shipment']);

                    $log['steps'][] = [
                        'step' => 'update_tblproduct_ProductModuleLoc',
                        'ok' => $rowsProduct > 0,
                        'orderItemId' => $orderItemId,
                        'ProductID' => $productId,
                        'rowsAffected' => $rowsProduct,
                    ];
                }

                $log['ok'] = true;
                return $log;
            });
        } catch (\Throwable $e) {
            $log['ok'] = false;
            $log['steps'][] = [
                'step' => 'exception',
                'ok' => false,
                'error' => $e->getMessage(),
            ];
            return $log;
        }
    }

}
