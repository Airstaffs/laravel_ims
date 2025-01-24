<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

require app_path('Helpers/usps_helpers.php');

class USPSController extends Controller
{
    public function USPSfetchTrackDetails(Request $request)
    {
        // Validate request input
        $validated = $request->validate([
            'trackingnumber' => 'required|string',
        ]);

        $trackingNumber = $validated['trackingnumber'];

        // Load USPS credentials
        $credentials = USPSCredentials();
        Log::info('New DATA');
        Log::info('Credentials', ['Credentials' => $credentials]);

        $userId = $credentials['client_id'];
        $password = $credentials['client_secret']; // Optional password

        // Prepare XML request
        $revision = "1";
        $clientIp = "210.1.108.110";
        $sourceId = "XYZ Corp";

        //$xmlRequest = "<TrackFieldRequest USERID=\"$userId\" PASSWORD=\"$password\">"
        $xmlRequest = "<TrackFieldRequest USERID=\"$userId\">"
            . "<Revision>$revision</Revision>"
            . "<ClientIp>$clientIp</ClientIp>"
            . "<SourceId>$sourceId</SourceId>"
            . "<TrackID ID=\"$trackingNumber\"></TrackID>"
            . "</TrackFieldRequest>";

        // Encode the XML payload for the URL
        $encodedXml = urlencode($xmlRequest);

        // Construct the full URL with the encoded XML
        $url = "https://secure.shippingapis.com/ShippingAPI.dll?API=TrackV2&XML=$encodedXml";

        try {
            Log::info('Sending request to USPS API', ['url' => $url]);

            $response = Http::get($url);

            Log::info('USPS API Response Received', ['status' => $response->status(), 'body' => $response->body()]);

            if ($response->failed()) {
                Log::error('USPS API Request Failed', ['status' => $response->status(), 'body' => $response->body()]);
                return response()->json(['error' => 'Failed to fetch tracking details from USPS.'], $response->status());
            }

            Log::info('XML Request', ['xmlRequest' => $xmlRequest]);
            Log::info('USPS Response', ['response' => $response->body()]);

            $xml = simplexml_load_string($response->body());

            if ($xml === false) {
                Log::error('Failed to parse USPS API response', ['errors' => libxml_get_errors()]);
                return response()->json(['error' => 'Failed to parse USPS API response.'], 500);
            }

            if (!isset($xml->TrackInfo)) {
                Log::warning('TrackInfo not found in USPS API response', ['response' => $xml]);
                return response()->json(['error' => 'Tracking information not available.'], 404);
            }

            $alldata = [];
            foreach ($xml->TrackInfo as $trackInfo) {
                Log::info('Processing TrackInfo', ['trackInfo' => $trackInfo]);
                $alldata = [
                    "TrackingNumber" => (string) $trackInfo['ID'],
                    "Status" => (string) $trackInfo->Status,
                    "StatusCategory" => (string) $trackInfo->StatusCategory,
                    "StatusSummary" => (string) $trackInfo->StatusSummary,
                    "TrackSummary" => [
                        "EventTime" => (string) $trackInfo->TrackSummary->EventTime,
                        "EventDate" => (string) $trackInfo->TrackSummary->EventDate,
                        "Event" => (string) $trackInfo->TrackSummary->Event,
                        "EventCity" => (string) $trackInfo->TrackSummary->EventCity,
                        "EventState" => (string) $trackInfo->TrackSummary->EventState,
                        "EventZIPCode" => (string) $trackInfo->TrackSummary->EventZIPCode,
                        "EventCountry" => (string) $trackInfo->TrackSummary->EventCountry,
                    ],
                ];
            }

            return response()->json($alldata, 200);

        } catch (\Exception $e) {
            Log::error('USPS Tracking Error', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'An unexpected error occurred. Please try again later.'], 500);
        }

    }
}
