<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use DateTime;
use DateTimeZone;

class UnreceivedController extends Controller
{
    use ItemImageHandler; // Reuse the same trait
    
    /**
     * Process scanner data for unreceived items
     */
    public function processScan(Request $request)
    {
        try {
            // Adjust validation for the fields your module needs
            $request->validate([
                'TrackingNumber' => 'required', // Example different field
                'PurchaseOrder' => 'required', // Example different field
                'Location' => 'required',
                'Images' => 'nullable|array',
            ]);

            // Get data from request
            $User = Auth::id() ?? session('user_name', 'Unknown');
            $tracking = trim($request->TrackingNumber);
            $po = trim($request->PurchaseOrder);
            $location = $request->Location;
            $images = $request->Images ?? [];
            $Module = "Unreceived";
            
            // Process and save images if any are provided
            $savedImagePaths = [];
            if (!empty($images)) {
                // Pass different identifier (PO instead of FNSKU)
                $savedImagePaths = $this->saveItemImages($images, $tracking, $po, 'Unreceived');
            }
            
            // Your module-specific logic here...
            
            // Return success response
            return response()->json([
                'success' => true,
                'message' => "Successfully processed unreceived item",
                'item' => "Item description", // Replace with actual description
                'imagePaths' => $savedImagePaths
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in unreceived processScan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing scan: ' . $e->getMessage(),
                'reason' => 'server_error'
            ], 500);
        }
    }
    
    // Other unreceived-specific methods...
}