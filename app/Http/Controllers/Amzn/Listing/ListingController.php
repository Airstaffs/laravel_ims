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

    public function get_asin_catalog(Request $request)
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
        $path = '/catalog/2022-04-01/items/';
        $customParams = [];

        $companydetails = fetchCompanyDetails();

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
}