<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Log;

class StockroomController extends BasetablesController
{
    
    /**
 * Display a listing of products in stockroom with joins to tblasin and tblfnsku.
 * Groups by ASIN and aggregates data.
 */
public function index(Request $request)
{
    try {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $store = $request->input('store', ''); // Add store filter parameter
        
        // Get all ASINs with their details, even those without stockroom items
        $asinQuery = DB::table($this->asinTable)
            ->select([
                $this->asinTable.'.ASIN',
                $this->fnskuTable.'.MSKU as MSKUviewer',
                $this->asinTable.'.internal as AStitle',
                $this->fnskuTable.'.storename',  // Added store name
                DB::raw('MIN('.$this->fnskuTable.'.grading) as grading'),
                DB::raw('SUM(CASE WHEN '.$this->productTable.'.ProductModuleLoc = "Stockroom" THEN '.$this->productTable.'.FBMAvailable ELSE 0 END) as FBMAvailable'),
                DB::raw('SUM(CASE WHEN '.$this->productTable.'.ProductModuleLoc = "Stockroom" THEN '.$this->productTable.'.FbaAvailable ELSE 0 END) as FbaAvailable'),
                DB::raw('SUM(CASE WHEN '.$this->productTable.'.ProductModuleLoc = "Stockroom" THEN '.$this->productTable.'.Outbound ELSE 0 END) as Outbound'),
                DB::raw('SUM(CASE WHEN '.$this->productTable.'.ProductModuleLoc = "Stockroom" THEN '.$this->productTable.'.Inbound ELSE 0 END) as Inbound'),
                DB::raw('SUM(CASE WHEN '.$this->productTable.'.ProductModuleLoc = "Stockroom" THEN '.$this->productTable.'.Unfulfillable ELSE 0 END) as Unfulfillable'),
                DB::raw('SUM(CASE WHEN '.$this->productTable.'.ProductModuleLoc = "Stockroom" THEN '.$this->productTable.'.Reserved ELSE 0 END) as Reserved'),
                DB::raw('COUNT(CASE WHEN '.$this->productTable.'.ProductModuleLoc = "Stockroom" THEN '.$this->productTable.'.ProductID ELSE NULL END) as item_count')
            ])
            ->leftJoin($this->fnskuTable, $this->asinTable.'.ASIN', '=', $this->fnskuTable.'.ASIN')
            ->leftJoin($this->productTable, function ($join) {
                $join->on($this->fnskuTable.'.FNSKU', '=', $this->productTable.'.FNSKUviewer');
            })
            ->when($search, function ($query) use ($search) {
                return $query->where(function ($q) use ($search) {
                    $q->where($this->asinTable.'.internal', 'like', "%{$search}%")
                      ->orWhere($this->fnskuTable.'.FNSKU', 'like', "%{$search}%")
                      ->orWhere($this->productTable.'.serialnumber', 'like', "%{$search}%")
                      ->orWhere($this->asinTable.'.metakeyword', 'like', "%{$search}%")
                      ->orWhere($this->asinTable.'.ASIN', 'like', "%{$search}%");
                      
                });
            })
            ->when($store, function ($query) use ($store) {
                return $query->where($this->fnskuTable.'.storename', $store);
            })
            ->groupBy($this->asinTable.'.ASIN', $this->fnskuTable.'.MSKU', $this->asinTable.'.internal', $this->fnskuTable.'.storename');
        
        // Execute the paginated query
        $asins = $asinQuery->paginate($perPage);
        
        // For each ASIN, get all the FNSKUs and serial numbers with warehouselocation
        $results = $asins->getCollection()->map(function($item) {
            // Get all FNSKUs for this ASIN
            $fnskus = DB::table($this->fnskuTable)
                ->select($this->fnskuTable.'.FNSKU', $this->fnskuTable.'.storename')
                ->where($this->fnskuTable.'.ASIN', $item->ASIN)
                ->when(isset($item->storename), function($query) use ($item) {
                    return $query->where($this->fnskuTable.'.storename', $item->storename);
                })
                ->get()
                ->toArray();
            
            // Get all serial numbers for this ASIN including warehouselocation
            $serials = DB::table($this->productTable)
                ->select(
                    $this->productTable.'.serialnumber', 
                    $this->productTable.'.ProductID', 
                    $this->productTable.'.rtcounter', 
                    $this->productTable.'.warehouselocation'
                )
                ->join($this->fnskuTable, $this->productTable.'.FNSKUviewer', '=', $this->fnskuTable.'.FNSKU')
                ->where($this->fnskuTable.'.ASIN', $item->ASIN)
                ->when(isset($item->storename), function($query) use ($item) {
                    return $query->where($this->fnskuTable.'.storename', $item->storename);
                })
                ->where($this->productTable.'.ProductModuleLoc', 'Stockroom')
                ->get()
                ->toArray();
            
            // Add the FNSKUs and serial numbers to the item
            $item->fnskus = $fnskus;
            $item->serials = $serials;
            
            return $item;
        });
        
        // Replace the collection in the paginator
        $asins->setCollection($results);
        
        return response()->json($asins);
    } catch (\Exception $e) {
        Log::error('Error in StockroomController@index: ' . $e->getMessage());
        Log::error($e->getTraceAsString());
        
        return response()->json([
            'error' => 'An error occurred while retrieving stockroom data',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
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

    //check FNSKU
    public function checkFnsku(Request $request)
    {
        $fnsku = $request->input('fnsku');

        if (empty($fnsku)) {
            return response()->json([
                'exists' => false,
                'status' => 'invalid',
                'message' => 'FNSKU is required'
            ]);
        }

        try {
            // Check in tblfnsku table with company suffix
            $result = DB::table($this->fnskuTable)
                ->where('FNSKU', $fnsku)
                ->first();

            if ($result) {
                // Found the FNSKU, now check its status
                $isAvailable = strtolower($result->fnsku_status) === 'available';

                return response()->json([
                    'exists' => true,
                    'status' => $isAvailable ? 'available' : 'unavailable',
                    'message' => $isAvailable ? 'FNSKU is available' : 'FNSKU exists but is not available'
                ]);
            } else {
                // FNSKU not found
                return response()->json([
                    'exists' => false,
                    'status' => 'not_found',
                    'message' => 'FNSKU not found in the database'
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('Error checking FNSKU', $e, ['fnsku' => $fnsku]);

            return response()->json([
                'exists' => false,
                'status' => 'error',
                'message' => 'Error checking FNSKU status'
            ], 500);
        }
    }

    /**
     * Process scanner data
     */
    /**
     * Process scanner data
     */
    /**
 * Process scanner data
 */
public function processScan(Request $request)
{
    // Start the database transaction at the very beginning
    DB::beginTransaction();
    
    try {
        // Validate input with robust error handling
        try {
            $validatedData = $request->validate([
                'SerialNumber' => 'required_without:FNSKU|nullable|string',
                'FNSKU' => 'required_without:SerialNumber|nullable|string',
                'Location' => 'required|string',
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
        $location = trim($request->input('Location', ''));
        $FNSKU = trim($request->input('FNSKU', ''));

        // Check for empty serial and FNSKU
        if (empty($serial) && empty($FNSKU)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Either Serial Number or FNSKU must be provided',
                'reason' => 'missing_identifiers'
            ], 422);
        }

        // Basic configuration
        $Module = "Stockroom";
        $Action = "Scanned and insert to Stockroom";

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

        // Validate serial number if it exists
        if (!empty($serial)) {
            if (!preg_match('/^[a-zA-Z0-9]+$/', $serial) || strpos($serial, 'X00') !== false) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Serial Number',
                    'reason' => 'invalid_serial'
                ]);
            }
        }

        // Validate FNSKU if it exists (check if it's not a location code)
        if (!empty($FNSKU) && preg_match('/^L\d{3}[A-G]$/i', $FNSKU)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid FNSKU - appears to be a location code',
                'reason' => 'invalid_fnsku'
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

        // Determine the target module location based on the scanned location
        $modulelocation = (substr($location, 0, 4) === 'L800') ? 'Production Area' : 'Stockroom';

        // Check if the serial exists in the stockroom or production area
        $existingItem = DB::table($this->productTable)
            ->where(function ($query) use ($serial) {
                $query->where('serialnumber', $serial)
                    ->orWhere('serialnumberb', $serial);
            })
            ->where(function ($query) {
                $query->where('ProductModuleLoc', 'Stockroom')
                    ->orWhere('ProductModuleLoc', 'Production Area');
            })
            ->first();

        if ($existingItem) {
            $id = $existingItem->ProductID;
            $rt = $existingItem->rtcounter;
            $needReprint = false;

            // Case: Item is in Production Area
            if ($existingItem->ProductModuleLoc === 'Production Area') {
                // Don't allow moving item from Production to Production
                if ($modulelocation === 'Production Area') {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Data Already in Production Area',
                        'reason' => 'Duplicate Data not allowed'
                    ]);
                } 
                // Moving from Production to Stockroom
                else {
                    // Find FNSKU in main fnsku table
                    $fnsku_data = DB::table($this->fnskuTable)
                        ->where('FNSKU', $FNSKU)
                        ->first();
                        
                    if (!$fnsku_data) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'FNSKU not found in database',
                            'reason' => 'fnsku_not_found'
                        ]);
                    }
                    
                    $Status = $fnsku_data->fnsku_status;
                    $getCondition = $fnsku_data->grading;
                    $Unitavailable = $fnsku_data->Units;
                    $getASIN = $fnsku_data->ASIN;

                    $asinData = DB::table($this->asinTable)
                        ->where('ASIN', $getASIN)
                        ->first();

                    if (!$asinData) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'ASIN of this FNSKU not found in database',
                            'reason' => 'ASIN_not_found'
                        ]);
                    }

                    // Update item to move from Production to Stockroom
                    DB::table($this->productTable)
                        ->where('ProductID', $id)
                        ->update([
                            'ProductModuleLoc' => $modulelocation,
                            'warehouselocation' => $location,
                            'stockroom_insert_date' => $curentDatetimeString
                        ]);
                    
                    // Insert history record
                    DB::table($this->itemProcessHistoryTable)->insert([
                        'rtcounter' => $rt,
                        'employeeName' => $User,
                        'editDate' => $curentDatetimeString,
                        'Module' => "Scanned and insert to {$modulelocation}",
                        'Action' => 'Return Item'
                    ]);

                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => "Scanned and insert to {$modulelocation}"
                    ]);
                }
            } 
            // Case: Duplicate serial in stockroom
            else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate Data in Stockroom',
                    'reason' => 'duplicate_data'
                ]);
            }
        }
        // Serial not found in main stockroom tables
        else {
            // Check for item in validation module that's ready for stockroom
            $existingInValidation = DB::table($this->productTable)
                ->where(function ($query) use ($serial) {
                    $query->where('serialnumber', $serial)
                        ->orWhere('serialnumberb', $serial);
                })
                ->where('returnstatus', 'Not Returned')
           //     ->where('validation_status', 'validated')
                ->where('ProductModuleLoc', 'Validation')
                ->first();

            if ($existingInValidation) {
                $id = $existingInValidation->ProductID;
                $rtnumberofitem = $existingInValidation->rtcounter;
                $checkFNSKUviewer = $existingInValidation->FNSKUviewer;
                $needReprint = false;
                $validationSTATUS = $existingInValidation->validation_status;
              if($validationSTATUS === 'validated'){
                // Handle validation to stockroom transfer
                // Check and process FNSKU
                $fnsku_data = DB::table($this->fnskuTable)
                    ->where('FNSKU', $FNSKU)
                    ->first();
                    
                if (!$fnsku_data) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'FNSKU not found in database',
                        'reason' => 'fnsku_not_found'
                    ]);
                }
                
                $checkFNSKUstatus = $fnsku_data->fnsku_status;
                $getFNSKU = $fnsku_data->FNSKU;
                $getCondition = $fnsku_data->grading;
                $Unitavailable = $fnsku_data->Units;
                $getASIN = $fnsku_data->ASIN;

                // Check if FNSKU requires a reprint (if it's different from the current one)
                if (!empty($checkFNSKUviewer) && (trim($FNSKU) != trim($checkFNSKUviewer))) {
                    $needReprint = true;
                }

                // Process based on FNSKU availability
                if ($checkFNSKUstatus == "available") {
                    // Update product to Stockroom with the provided FNSKU
                    DB::table($this->productTable)
                        ->where('ProductID', $id)
                        ->update([
                            'ProductModuleLoc' => $modulelocation,
                            'warehouselocation' => $location,
                            'FNSKUviewer' => $FNSKU,
                            'stockroom_insert_date' => $curentDatetimeString
                        ]);
                    
                    // Update FNSKU units
                    $UdpatedUnitavailable = $Unitavailable - 1;
                    DB::table($this->fnskuTable)
                        ->where('FNSKU', $getFNSKU)
                        ->where('ASIN', $getASIN)
                        ->update([
                            'fnsku_status' => 'available',
                            'Units' => $UdpatedUnitavailable
                        ]);
                } 
                else {
                    // Look for an alternative available FNSKU
                    $FindavailableFnsku = DB::table($this->fnskuTable)
                        ->where('fnsku_status', 'Available')
                        ->where('ASIN', $getASIN)
                        ->where('grading', $getCondition)
                        ->first();
                    
                    if (!$FindavailableFnsku) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "No Available FNSKU found for this ASIN and condition",
                            'reason' => 'no_available_fnsku'
                        ]);
                    }
                    
                    $getAvailableFNSKU = $FindavailableFnsku->FNSKU;
                    $Unitavailable = $FindavailableFnsku->Units;
                    
                    // Update product with the available FNSKU
                    DB::table($this->productTable)
                        ->where('ProductID', $id)
                        ->update([
                            'ProductModuleLoc' => $modulelocation,
                            'warehouselocation' => $location,
                            'FNSKUviewer' => $getAvailableFNSKU,
                            'stockroom_insert_date' => $curentDatetimeString
                        ]);
                    
                    // Update FNSKU units
                    $UdpatedUnitavailable = $Unitavailable - 1;
                    DB::table($this->fnskuTable)
                        ->where('FNSKU', $getAvailableFNSKU)
                        ->where('ASIN', $getASIN)
                        ->update([
                            'fnsku_status' => 'available',
                            'Units' => $UdpatedUnitavailable
                        ]);
                }
                
                // Insert history record
                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $rtnumberofitem,
                    'employeeName' => $User,
                    'editDate' => $curentDatetimeString,
                    'Module' => "Scanned and insert to {$modulelocation}",
                    'Action' => 'Return Item'
                ]);
                
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => "Scanned and Forwarded to {$modulelocation} Successfully",
                    'needReprint' => $needReprint,
                    'productId' => $needReprint ? $id : null
                ]);
            }else{
                return response()->json([
                    'success' => false,
                    'message' => "Data is in Validation Module but not validated", 
                    'reason' => "Data not validated", 
                ]);

            }
         } 
            // No existing record, create new entry
            else {
                // Find FNSKU in main fnsku table
                $fnsku_data = DB::table($this->fnskuTable)
                    ->where('FNSKU', $FNSKU)
                    ->first();
                    
                if (!$fnsku_data) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'FNSKU not found in database',
                        'reason' => 'fnsku_not_found'
                    ]);
                }
                
                $checkFNSKUstatus = $fnsku_data->fnsku_status;
                $getFNSKU = $fnsku_data->FNSKU;
                $getCondition = $fnsku_data->grading;
                $Unitavailable = $fnsku_data->Units;
                $getASIN = $fnsku_data->ASIN;

                // Process based on FNSKU availability
                if ($checkFNSKUstatus == "available") {
                    // Get next RT counter
                    $maxxrt = DB::table($this->productTable)->max('rtcounter');
                    $newrt = $maxxrt + 1;

                    // Insert new item
                    $newItemId = DB::table($this->productTable)->insertGetId([
                        'rtcounter' => $newrt,
                        'serialnumber' => $serial,
                        'ProductModuleLoc' => $modulelocation,
                        'warehouselocation' => $location,
                        'FNSKUviewer' => $getFNSKU,
                        'FbmAvailable' => 1,
                        'Fulfilledby' => 'FBM',
                        'quantity' => 1,
                        'stockroom_insert_date' => $curentDatetimeString,
                    ]);

                    // Update FNSKU units
                    $UdpatedUnitavailable = $Unitavailable - 1;
                    DB::table($this->fnskuTable)
                        ->where('FNSKU', $getFNSKU)
                        ->where('ASIN', $getASIN)
                        ->update([
                            'fnsku_status' => 'available',
                            'Units' => $UdpatedUnitavailable
                        ]);
                    
                    // Insert history record
                    DB::table($this->itemProcessHistoryTable)->insert([
                        'rtcounter' => $newrt,
                        'employeeName' => $User,
                        'editDate' => $curentDatetimeString,
                        'Module' => $Module,
                        'Action' => $Action
                    ]);
                } 
                else {
                    // Look for an alternative available FNSKU
                    $FindavailableFnsku = DB::table($this->fnskuTable)
                        ->where('fnsku_status', 'Available')
                        ->where('ASIN', $getASIN)
                        ->where('grading', $getCondition)
                        ->first();
                    
                    if (!$FindavailableFnsku) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "No Available FNSKU found for this ASIN and condition",
                            'reason' => 'no_available_fnsku'
                        ]);
                    }
                    
                    $getAvailableFNSKU = $FindavailableFnsku->FNSKU;
                    $Unitavailable = $FindavailableFnsku->Units;
                    
                    // Get next RT counter
                    $maxxrt = DB::table($this->productTable)->max('rtcounter');
                    $newrt = $maxxrt + 1;

                    // Insert new item
                    $newItemId = DB::table($this->productTable)->insertGetId([
                        'rtcounter' => $newrt,
                        'serialnumber' => $serial,
                        'ProductModuleLoc' => $modulelocation,
                        'warehouselocation' => $location,
                        'FNSKUviewer' => $getAvailableFNSKU,
                        'FbmAvailable' => 1,
                        'Fulfilledby' => 'FBM',
                        'quantity' => 1,
                        'stockroom_insert_date' => $curentDatetimeString,
                    ]);

                    // Update FNSKU units
                    $UdpatedUnitavailable = $Unitavailable - 1;
                    DB::table($this->fnskuTable)
                        ->where('FNSKU', $getAvailableFNSKU)
                        ->where('ASIN', $getASIN)
                        ->update([
                            'fnsku_status' => 'available',
                            'Units' => $UdpatedUnitavailable
                        ]);
                    
                    // Insert history record
                    DB::table($this->itemProcessHistoryTable)->insert([
                        'rtcounter' => $newrt,
                        'employeeName' => $User,
                        'editDate' => $curentDatetimeString,
                        'Module' => $Module,
                        'Action' => $Action
                    ]);
                }
                
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => "Scanned and Inserted Successfully"
                ]);
            }
        }
    } catch (\Exception $e) {
        // Roll back transaction on any error
        DB::rollBack();
        $this->logError('Unhandled error in processScan', $e);

        return response()->json([
            'success' => false,
            'message' => 'Error processing scan: ' . $e->getMessage(),
            'reason' => 'server_error'
        ], 500);
    }
}

    /**
     * Print a label for a product
     */
 /*   public function printLabel(Request $request)
    {
        // Validate the request
        $validatedData = $request->validate([
            'productId' => 'required|integer'
        ]);
    
        $productId = $request->productId;
        $username = Auth::id() ?? $request->session()->get('user_name', 'Unknown');
    
        // Use the PrintLabelService to handle the printing
        $printLabelService = app(PrintLabelService::class);
        $result = $printLabelService->printLabel($productId, $username);
    
        return response()->json($result);
    }*/
}
