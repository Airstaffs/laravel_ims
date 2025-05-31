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

        $companydetails = $this->fetchCompanyDetails();
        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $allRates = [];

        foreach ($orders as $order) {
            $platformOrderId = $order['platform_order_id'] ?? null;
            $store = $order['storename'] ?? '';
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
                'Delivered_Datetime' => $form['deliverBy'],
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

        $companydetails = $this->fetchCompanyDetails();
        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $Results = [];

        foreach ($orders as $order) {
            $platformOrderId = $order['platform_order_id'] ?? null;
            $store = $order['storename'] ?? '';
            $form = $forms[$platformOrderId] ?? null;

            $shippingService = $order['selectedCarrier'] ?? null;

            // You can now access the offer ID if it exists
            $ShippingServiceId = $shippingService['ShippingServiceId'] ?? null;
            $ShippingServiceOfferId = $shippingService['ShippingServiceOfferId'] ?? null;

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
                'Delivered_Datetime' => $form['deliverBy'],

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
                    $Results[] = [
                        'platform_order_id' => $platformOrderId,
                        'rates' => $response->json(),
                        'logs' => $curlInfo
                    ];

                    $this->insertShipmentData($order, $response->json(), $form);
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

    public function manual_shipment(Request $request)
    {
    }

    protected function fetchCompanyDetails()
    {
        return DB::table('tblcompanydetails')->where('id', 1)->first();
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
                        "Name" => $companydetails['Name'],
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
                        "Width" => $data_additionale['package_dimensions_height'],
                        "Height" => $data_additionale['package_dimensions_width'],
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
                    ]
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
                        "Name" => $companydetails['Name'],
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
                        "Width" => $data_additionale['package_dimensions_height'],
                        "Height" => $data_additionale['package_dimensions_width'],
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
                    ]
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

    private function insertShipmentData(array $orders)
    {
        $user = session('user_name', 'Unknown');

        foreach ($orders as $order) {
            $amazonOrderId = $order['platform_order_id'] ?? null;
            $form = $order['form'] ?? [];
            $apiData = $order['apiData'] ?? [];
            $selectedCarrier = $order['selectedCarrier'] ?? [];
            $rateAmount = $form['rate'] ?? 0.00;

            if (!$amazonOrderId)
                continue;

            // Step 1: Find or Generate Invoice Number
            $existingInvoice = DB::table('tbllabelhistory')
                ->where('AmazonOrderId', $amazonOrderId)
                ->value('invoicenumberid');

            if ($existingInvoice) {
                $invoiceNumber = $existingInvoice;
            } else {
                $max = DB::table('tbllabelhistory')->max('invoicenumberid');
                $invoiceNumber = $max ? $max + 1 : 1;
            }

            // Step 2: Insert into tbllabelhistory
            $labelId = DB::table('tbllabelhistory')->insertGetId([
                'shipmentid' => $apiData['ShipmentId'] ?? null,
                'AmazonOrderId' => $amazonOrderId,
                'status' => $apiData['Status'] ?? null,
                'trackingid' => $apiData['TrackingId'] ?? null,
                'updatedDate' => now(),
                'ShippingServiceId' => $apiData['ShippingService']['ShippingServiceId'] ?? null,
                'ShippingServiceOfferId' => $apiData['ShippingService']['ShippingServiceOfferId'] ?? null,
                'labelprice' => $rateAmount,
                'user' => $user,
                'invoicenumberid' => $invoiceNumber,
                'ShipDate' => $apiData['ShippingService']['ShipDate'] ?? null
            ]);

            // Step 3: Insert into tbllabelhistoryitems and update outbound item
            foreach ($apiData['ItemList'] ?? [] as $item) {
                $orderItemId = $item['OrderItemId'] ?? null;

                DB::table('tbllabelhistoryitems')->insert([
                    'shipmentid' => $apiData['ShipmentId'] ?? null,
                    'AmazonOrderId' => $amazonOrderId,
                    'orderitemid' => $orderItemId,
                    'trackingid' => $apiData['TrackingId'] ?? null,
                    'shipDate' => $apiData['ShippingService']['ShipDate'] ?? null,
                    'EarliestEstimatedDeliveryDate' => $apiData['ShippingService']['EarliestEstimatedDeliveryDate'] ?? null,
                    'LatestEstimatedDeliveryDate' => $apiData['ShippingService']['LatestEstimatedDeliveryDate'] ?? null,
                    'labelhistory_id' => $labelId,
                    'PDFLabel' => $apiData['Label']['FileContents']['Contents'] ?? null,
                    'DeliveryExperience' => $form['deliveryExperience'] ?? null
                ]);

                DB::table('tbloutboundordersitem')
                    ->where('platform_order_id', $amazonOrderId)
                    ->where('platform_order_item_id', $orderItemId)
                    ->update([
                        'trackingnumber' => $apiData['TrackingId'] ?? null,
                        'carrier' => $selectedCarrier['CarrierName'] ?? null,
                        'carrier_description' => $selectedCarrier['ShippingServiceName'] ?? null,
                    ]);
            }
        }
    }
}
