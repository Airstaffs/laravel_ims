<?php 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

//$credentials = getUSPSCredentials($Connect);

if (!function_exists('AWSCredentials')) {
    /**
     * Retrieve AWS credentials for a given store.
     *
     * @param string $store The store identifier.
     * @return object|null Credentials object or null if not found.
     */
    function AWSCredentials($store)
    {
        try {
            // retrieve USPS credentials
            $id = 1;

            $credentials = (array) DB::table('tblapis')->where('id', $id)->first();

            if (!$credentials) {
                Log::error("No keys found for the given client ID: {$id}");
                return null;
            }

            return $credentials;
        } catch (\Exception $e) {
            Log::error("Error retrieving credentials: " . $e->getMessage());
            return null;
        }
    }
}

function USPS_fetchTrackDetails($trackingNumber, $credentials, $Connect)
{
    $alldata = [];

    // Your USPS Web Tools API user ID and optional password
    $userId = $credentials['client_id'];
    $password = $credentials['client_secret']; // Optional password

    // Prepare XML request
    $revision = "1";
    $clientIp = "92.112.196.9";
    $sourceId = "XYZ Corp";

    $xmlRequest = "<TrackFieldRequest USERID=\"$userId\" PASSWORD=\"$password\">"
        . "<Revision>$revision</Revision>"
        . "<ClientIp>$clientIp</ClientIp>"
        . "<SourceId>$sourceId</SourceId>"
        . "<TrackID ID=\"$trackingNumber\"></TrackID>"
        . "</TrackFieldRequest>";

    // USPS API URL
    $url = 'https://secure.shippingapis.com/ShippingAPI.dll';

    // Make the HTTP request using Laravel's HTTP client
    $response = Http::withHeaders([
        'Content-Type' => 'application/xml',
    ])->get($url, [
        'API' => 'TrackV2',
        'XML' => $xmlRequest,
    ]);

    // Check HTTP status code
    if ($response->failed()) {
        $httpCode = $response->status();

    }

    // Parse the XML response
    $xml = simplexml_load_string($response->body());

    // Check for errors in the USPS response
    if (isset($xml->Error)) {
        echo "Error: " . $xml->Error->Description;
    } else {
        // Process tracking details
        foreach ($xml->TrackInfo as $trackInfo) {
            $alldata = [
                "TrackingNumber" => (string)$trackInfo['ID'],
                "Status" => (string)$trackInfo->Status,
                "StatusCategory" => (string)$trackInfo->StatusCategory,
                "StatusSummary" => (string)$trackInfo->StatusSummary,
                "TrackSummary" => [
                    "EventTime" => (string)$trackInfo->TrackSummary->EventTime,
                    "EventDate" => (string)$trackInfo->TrackSummary->EventDate,
                    "Event" => (string)$trackInfo->TrackSummary->Event,
                    "EventCity" => (string)$trackInfo->TrackSummary->EventCity,
                    "EventState" => (string)$trackInfo->TrackSummary->EventState,
                    "EventZIPCode" => (string)$trackInfo->TrackSummary->EventZIPCode,
                    "EventCountry" => (string)$trackInfo->TrackSummary->EventCountry,
                ],
            ];
        }
    }

    return $alldata;
}