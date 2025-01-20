<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

require app_path('Helpers/usps_helpers.php');
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