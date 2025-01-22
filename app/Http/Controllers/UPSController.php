<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

require app_path('Helpers/ups_helpers.php');

class UPSController extends Controller
{
    public function UPSfetchTrackDetails(Request $request)
    {
        // Validate request input
        $validated = $request->validate([
            'trackingnumber' => 'required|string',
        ]);

        $trackingNumber = $validated['trackingnumber'];

        $credentials = UPSCredentials();

        $query = [
            "locale" => "en_US",
            "returnSignature" => "false",
            "returnMilestones" => "false",
        ];

        try {
            // Send GET request to UPS API
            $response = Http::withHeaders([
                "Authorization" => "Bearer " . $credentials['access_token'],
                "transId" => "asjfdklasdjfaslkjsdfasslkdjfas",
                "transactionSrc" => "CustomerServicePortal",
            ])->get("https://onlinetools.ups.com/api/track/v1/details/{$trackingNumber}", $query);

            if ($response->successful()) {
                // Return successful response
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            } else {
                // Log and throw exception for non-successful responses
                Log::error("UPS API Request failed: " . $response->body());
                return response()->json([
                    'success' => false,
                    'error' => "API Request failed with status: " . $response->status(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            // Log and handle general exceptions
            Log::error("UPS API Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}