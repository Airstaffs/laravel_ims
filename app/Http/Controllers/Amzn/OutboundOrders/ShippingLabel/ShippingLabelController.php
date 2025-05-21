<?php

namespace App\Http\Controllers\Amzn\OutboundOrders\ShippingLabel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

require base_path('app/Helpers/aws_helpers.php');

class ShippingLabelController extends Controller
{

    public function get_rates(Request $request)
    {
        $request->validate([
            'store' => 'nullable|string',
            'destinationMarketplace' => 'nullable|string',
            'nextToken' => 'nullable|string',
            'shipmentID' => 'nullable|string'
        ]);
        $data_additionale = []; // data that is to be passed to jsonCreation
        $store = $request->input('store', 'Renovar Tech');
        $nextToken = $request->input('nextToken', null);
        $destinationmarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');

        $data_additionale['AmazonOrderId'] = $request->input('AmazonOrderId', '');
        $data_additionale['orderitems'] = $request->input('orderitems', []);
        $data_additionale['package_dimensions_length'] = $request->input('package_dimensions_length', '');
        $data_additionale['package_dimensions_height'] = $request->input('package_dimensions_height', '');
        $data_additionale['package_dimensions_width'] = $request->input('package_dimensions_width', '');
        $data_additionale['package_dimensions_unit'] = $request->input('package_dimensions_unit', '');

        $data_additionale['package_weight_unit'] = $request->input('package_weight_unit', '');
        $data_additionale['package_weight_value'] = $request->input('package_weight_value', '');

        $data_additionale['Shipping_DeliveryExperience'] = $request->input('Shipping_DeliveryExperience', '');
        $data_additionale['Shipping_CarrierPickUpOption'] = $request->input('Shipping_CarrierPickUpOption', '');

        $data_additionale['Shipping_valueCurrencyCode'] = $request->input('Shipping_valueCurrencyCode', '');
        $data_additionale['Shipping_valueAmount'] = $request->input('Shipping_valueAmount', '');
        $data_additionale['Shipping_DeliveryExperience'] = $request->input('Shipping_DeliveryExperience', '');
        $data_additionale['Shipby_Datetime'] = $request->input('Shipby_Datetime', '');
        $data_additionale['Delivered_Datetime'] = $request->input('Delivered_Datetime', '');


        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/mfn/v0/eligibleShippingServices';
        $customParams = [];

        $companydetails = $this->fetchCompanyDetails();

        if (!$companydetails) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        // Generate JSON payload
        $jsonData = $this->JsonCreation('get_rates', $companydetails, 'ATVPDKIKX0DER', $data_additionale);

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

            if ($response->successful()) {
                $data = $response->json(); // Parse JSON response

                // If no operationId, return success response but indicate missing operation tracking
                return response()->json([
                    'success' => true,
                    'message' => 'Get Rates returned.',
                    'data' => $data,
                    'logs' => $curlInfo,
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

        // prodduces inboundplanid
    }

    public function create_shipment(Request $request)
    {
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
            $shipmentData = [

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
                        "CarrierWillPickUp" => $data_additionale['carrierPickUp'],
                        "CarrierWillPickUpOption" => $data_additionale['carrierPickUpOption'],
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
        }

        // Ensure JSON encoding before returning
        return json_encode($final_json_construct, JSON_UNESCAPED_SLASHES);
    }

    protected function tetrister($AmazonOrderId, $orderitems, $platform, $store)
    {
        $results = [];

        foreach ($orderitems as $item) {
            $orderitemid = $item['orderitemid'] ?? null;

            if (!$orderitemid) {
                continue; // skip if orderitemid is missing
            }

            $result = DB::table('tbloutboundordersitem as i')
                ->join('tbloutboundorders as o', function ($join) use ($platform) {
                    $join->on('i.platform_order_id', '=', 'o.platform_order_id')
                        ->where('i.platform', '=', $platform)
                        ->where('o.platform', '=', $platform); // assuming o.platform exists too
                })
                ->select('o.*', 'i.*')
                ->where('i.platform_order_id', $AmazonOrderId)
                ->where('i.platform_order_item_id', $orderitemid)
                ->where('o.store', $store)
                ->first();

            if ($result) {
                $results[] = $result;
            }
        }

        return $results;
    }
}
