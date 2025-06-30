<?php

namespace App\Http\Controllers\Amzn\Listing\HelpAmazonFunctionTemplates;

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

class HelpAmazonFunctionTemplates extends Controller
{

    // this function gets the data of the ASIN
    public function get_asin_catalog(Request $request)
    {

        $destinationMarketplace = $request->input('destinationMarketplace', 'ATVPDKIKX0DER');
        $nextToken = $request->input('nextToken', null);
        $store = $request->input('store', null);
        $searchedAsin = $request->input('searchedAsin', null);

        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/catalog/2022-04-01/items/' . $searchedAsin;

        // Static query parameters
        $customParams = [
            // 'details' => "true",
            // 'granularityType' => "Marketplace",
            'marketplaceIds' => $destinationMarketplace,
            'includedData' => 'attributes,dimensions,identifiers,images,productTypes,salesRanks,summaries,relationships',
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

        $jsonData = $this->JsonCreation('action', null, null, null);
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
            $url = "{$endpoint}{$path}{$queryString}";

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

        if ($action == 'action') {
            $final_json_construct = [];
        }

        // Ensure JSON encoding before returning
        return json_encode($final_json_construct, JSON_UNESCAPED_SLASHES);
    }
}