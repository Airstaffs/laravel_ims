<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Log;

class ReturnScannerController extends BasetablesController
{
    /**
     * Display a listing of products in return list with joins to tblasin and tblfnsku.
     * Groups by ASIN and aggregates data.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $location = $request->input('location', 'Returnlist');
        
        $products = DB::table($this->productTable)
            ->where('ProductModuleLoc', $location)
            ->when($search, function($query) use ($search) {
                return $query->where(function($q) use ($search) {
                    $q->where('AStitle', 'like', "%{$search}%")
                      ->orWhere('serialnumber', 'like', "%{$search}%")
                      ->orWhere('FNSKUviewer', 'like', "%{$search}%")
                      ->orWhere('MSKUviewer', 'like', "%{$search}%")
                      ->orWhere('ASINviewer', 'like', "%{$search}%")
                      ->orWhere('rtcounter', 'like', "%{$search}%");
                });
            })
            ->paginate($perPage);
        
        return response()->json($products);
    }
    /**
     * Get list of store names for the dropdown
     */
    public function getStores()
    {
        try {
            $stores = DB::table($this->fnskuTable)
                ->select('storename')
                ->distinct()
                ->orderBy('storename')
                ->get()
                ->pluck('storename');
                
            return response()->json($stores);
        } catch (\Exception $e) {
            Log::error('Error getting stores: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'An error occurred while retrieving store list',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a serial number belongs to a dual-serial product
     */
    public function checkSerial(Request $request)
    {
        $serial = $request->get('serial');
        
        if (!$serial) {
            return response()->json([
                'success' => false,
                'message' => 'No serial number provided'
            ]);
        }
        
        try {
            // First, check if this serial exists in the database
            $product = DB::table($this->productTable)
                ->where(function ($query) use ($serial) {
                    $query->where('serialnumber', $serial)
                        ->orWhere('serialnumberb', $serial);
                })
                ->first();
            
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Serial number not found'
                ]);
            }
            
            // Check if serialnumberb is not null (indicating a dual-serial product)
            $isDualSerial = !empty($product->serialnumberb);
            $secondSerial = null;
            
            // Determine which is the second serial based on which one was scanned
            if ($serial === $product->serialnumber && !empty($product->serialnumberb)) {
                $secondSerial = $product->serialnumberb;
            } else if ($serial === $product->serialnumberb && !empty($product->serialnumber)) {
                $secondSerial = $product->serialnumber;
            }
            
            return response()->json([
                'success' => true,
                'isDualSerial' => $isDualSerial,
                'secondSerial' => $secondSerial,
                'secondSerialLabel' => 'Second Serial', // You can customize this label if needed
                'productId' => $product->ProductID
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking serial: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Process a return scan
     */
    public function processScan(Request $request)
    {
        // Start the database transaction at the very beginning
        DB::beginTransaction();
        
        try {
            // Validate input with robust error handling
            try {
                $validatedData = $request->validate([
                    'SerialNumber' => 'required|string',
                    'SecondSerial' => 'nullable|string',
                    'Location' => 'required|string',
                    'ReturnId' => 'nullable|string'
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Return a clean validation error response
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . implode(', ', $e->errors()),
                    'reason' => 'validation_error'
                ], 422);
            }

            // Get data from request with defensive coding
            $User = Auth::id() ?? $request->session()->get('user_name', 'Unknown');
            $serial = trim($request->input('SerialNumber', ''));
            $secondSerial = trim($request->input('SecondSerial', ''));
            $location = trim($request->input('Location', ''));
            $returnId = trim($request->input('ReturnId', ''));

            // Check for empty serial
            if (empty($serial)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Serial Number must be provided',
                    'reason' => 'missing_identifiers'
                ], 422);
            }

            // Time handling with error checking
            try {
                $california_timezone = new DateTimeZone('America/Los_Angeles');
                $currentDatetime = new DateTime('now', $california_timezone);
                $formatted_datetime = $currentDatetime->format('Y-m-d h:i A');
                $currentDate = date('Y-m-d', strtotime($formatted_datetime));
                $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // Use fallback timezone if there's an issue
                Log::warning('Error with timezone, using default', ['error' => $e->getMessage()]);
                $currentDatetime = new DateTime();
                $formatted_datetime = $currentDatetime->format('Y-m-d h:i A');
                $currentDate = date('Y-m-d');
                $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
            }

            // Validate serial number
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $serial)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Serial Number format',
                    'reason' => 'invalid_serial'
                ]);
            }

            // Validate second serial if provided
            if (!empty($secondSerial) && !preg_match('/^[a-zA-Z0-9-]+$/', $secondSerial)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Second Serial Number format',
                    'reason' => 'invalid_second_serial'
                ]);
            }

            // Validate location format
            if (!preg_match('/^L\d{3}[A-G]$/i', $location) && $location !== 'Floor' && $location !== 'L800G') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Location Format',
                    'reason' => 'invalid_location'
                ]);
            }

            // Check if the serial exists in the database
            $existingItem = DB::table($this->productTable)
                ->where(function ($query) use ($serial, $secondSerial) {
                    $query->where('serialnumber', $serial)
                        ->orWhere('serialnumberb', $serial);
                    
                    if (!empty($secondSerial)) {
                        $query->orWhere('serialnumber', $secondSerial)
                            ->orWhere('serialnumberb', $secondSerial);
                    }
                })
                ->first();

            if ($existingItem) {
                // Handle dual-serial validation if applicable
                if (!empty($existingItem->serialnumberb)) {
                    // This is a dual-serial product
                    // Make sure both serials are provided and match what's in the database
                    $dbSerial1 = $existingItem->serialnumber;
                    $dbSerial2 = $existingItem->serialnumberb;
                    
                    // If second serial is not provided but required
                    if (empty($secondSerial)) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'This is a dual-serial product. Second serial number is required.',
                            'reason' => 'missing_second_serial',
                            'secondSerialLabel' => 'Second Serial',
                            'isDualSerial' => true
                        ]);
                    }
                    
                    // Check if the serials match what's in the database (in either order)
                    $serialsMatch = 
                        ($serial === $dbSerial1 && $secondSerial === $dbSerial2) ||
                        ($serial === $dbSerial2 && $secondSerial === $dbSerial1);
                    
                    if (!$serialsMatch) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'The provided serial numbers do not match the product record.',
                            'reason' => 'serial_mismatch'
                        ]);
                    }
                }

                // Update item with return information
                DB::table($this->productTable)
                    ->where('ProductID', $existingItem->ProductID)
                    ->update([
                        'ProductModuleLoc' => 'Returnlist',
                        'returnstatus' => 'returned',
                        'warehouselocation' => $location,
                        'return_id' => $returnId ?: null,
                        'return_date' => $curentDatetimeString
                    ]);
                
                // Insert history record
                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $existingItem->rtcounter,
                    'employeeName' => $User,
                    'editDate' => $curentDatetimeString,
                    'Module' => 'Returnlist',
                    'Action' => 'Item returned and added to Return List'
                ]);

                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => "Item processed as a return and added to Return List",
                    'item' => [
                        'serial_number' => $serial,
                        'second_serial' => $secondSerial,
                        'location' => $location,
                        'return_id' => $returnId,
                        'status' => 'returned'
                    ]
                ]);
            } else {
                // Item doesn't exist in the database
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Serial number not found in database',
                    'reason' => 'serial_not_found'
                ]);
            }
            
        } catch (\Exception $e) {
            // Roll back transaction on any error
            DB::rollBack();
            Log::error('Unhandled error in processScan', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing scan: ' . $e->getMessage(),
                'reason' => 'server_error'
            ], 500);
        }
    }
}