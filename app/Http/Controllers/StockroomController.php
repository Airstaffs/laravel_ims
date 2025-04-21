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
                      ->orWhere($this->fnskuTable.'.MSKU', 'like', "%{$search}%")
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
    public function processScan(Request $request)
    {
        try {
            // Validate input with more robust error handling
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

            // Check for empty serial and FNSKU (shouldn't happen due to validation, but just in case)
            if (empty($serial) && empty($FNSKU)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either Serial Number or FNSKU must be provided',
                    'reason' => 'missing_identifiers'
                ], 422);
            }

            // Continue with the rest of your method...
            $store = '';
            $Module = "Stockroom";

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

            $Action = "Scanned and insert to Stockroom";

            // Only validate serial number if it exists
            if (!empty($serial)) {
                if (!preg_match('/^[a-zA-Z0-9]+$/', $serial) || strpos($serial, 'X00') !== false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid Serial Number',
                        'reason' => 'invalid_serial'
                    ]);
                }
            }

            // Only validate FNSKU if it exists
            if (!empty($FNSKU) && preg_match('/^L\d{3}[A-G]$/i', $FNSKU)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid FNSKU - appears to be a location code',
                    'reason' => 'invalid_fnsku'
                ]);
            }

            if (!preg_match('/^L\d{3}[A-G]$/i', $location) && $location !== 'Floor' && $location !== 'L800G') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Location Format',
                    'reason' => 'invalid_location'
                ]);
            }
            // Check if the serial exists in the stockroom list - using dynamic table name
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

                // Case: Item is in SoldList and Fulfilledby is FBM or FBA, or ProductModuleLoc is Shipment
                if ($existingItem->ProductModuleLoc === 'Production Area') {
                    if (substr($location, 0, 4) === 'L800') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Data Already in Production Area',
                            'reason' => 'Duplicate Data not allowed'
                        ]);
                    } else {
                        $modulelocation = 'Stockroom';
                        $insertedDate = $curentDatetimeString;

                        // Find FNSKU in main fnsku table - using dynamic table name
                        $fnsku_data = DB::table($this->fnskuTable)
                            ->where('FNSKU', $FNSKU)
                            ->first();
                        if ($fnsku_data) {
                            $Status = $fnsku_data->fnsku_status;
                            $getCondition = $fnsku_data->grading;
                            $Unitavailable = $fnsku_data->Units;
                            $getASIN = $fnsku_data->ASIN;

                            $asinData = DB::table($this->asinTable)
                                ->where('ASIN', $getASIN)
                                ->first();

                            if ($asinData) {
                                $getSister = $asinData->ParentAsin;
                            } else {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'ASIN of this FNSKU not found in database',
                                    'reason' => 'ASIN_not_found'
                                ]);
                            }
                        } else {
                            return response()->json([
                                'success' => false,
                                'message' => 'FNSKU not found in database',
                                'reason' => 'fnsku_not_found'
                            ]);
                        }
                        // Process based on Status or module
                        if ($Status === 'available') {
                            DB::beginTransaction();
                            DB::table($this->productTable)
                                ->where('ProductID', $id)
                                ->update([
                                    'ProductModuleLoc' => $modulelocation,
                                    'warehouselocation' => $location,
                                    'stockroom_insert_date' => $curentDatetimeString
                                ]);
                            $UdpatedUnitavailable = $Unitavailable - 1;
                            if ($UdpatedUnitavailable === 0) {
                                // Update FNSKU status
                                DB::table($this->fnskuTable)
                                    ->where('FNSKU', $FNSKU)
                                    ->where('ASIN', $getASIN)
                                    ->update([
                                        'fnsku_status' => 'Unavailable',
                                        'Units' => $UdpatedUnitavailable
                                    ]);
                            } else {
                                DB::table($this->fnskuTable)
                                    ->where('FNSKU', $FNSKU)
                                    ->where('ASIN', $getASIN)
                                    ->update([
                                        'fnsku_status' => 'Available',
                                        'Units' => $UdpatedUnitavailable
                                    ]);
                            }
                            // Insert history
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
                        } else {
                            // Try to find available FNSKU with same ASIN and grading
                            $FindavailableFnsku = DB::table($this->fnskuTable)
                                ->where('fnsku_status', 'Available')
                                ->where('ASIN', $getASIN)
                                ->where('grading', $getCondition)
                                ->first();
                            if ($FindavailableFnsku) {
                                $getAvailableFNSKU = $FindavailableFnsku->FNSKU;
                                $Unitavailable = $FindavailableFnsku->Units;
                                try {
                                    DB::beginTransaction();
                                    DB::table($this->productTable)
                                        ->where('ProductID', $id)
                                        ->update([
                                            'ProductModuleLoc' => $modulelocation,
                                            'warehouselocation' => $location,
                                            'stockroom_insert_date' => $curentDatetimeString
                                        ]);

                                    // Update FNSKU status
                                    $UdpatedUnitavailable = $Unitavailable - 1;
                                    if ($UdpatedUnitavailable === 0) {
                                        // Update FNSKU status
                                        DB::table($this->fnskuTable)
                                            ->where('FNSKU', $getAvailableFNSKU)
                                            ->where('ASIN', $getASIN)
                                            ->update([
                                                'Status' => 'available',
                                                'Units' => $UdpatedUnitavailable
                                            ]);
                                    } else {
                                        DB::table($this->fnskuTable)
                                            ->where('FNSKU', $getAvailableFNSKU)
                                            ->where('ASIN', $getASIN)
                                            ->update([
                                                'Status' => 'available',
                                                'Units' => $UdpatedUnitavailable
                                            ]);
                                    }

                                    // Insert history for new item
                                    DB::table($this->itemProcessHistoryTable)->insert([
                                        'rtcounter' => $rt,
                                        'employeeName' => $User,
                                        'editDate' => $curentDatetimeString,
                                        'Module' => 'Scan Add Module',
                                        'Action' => "Scanned and insert to {$modulelocation}"
                                    ]);
                                    DB::commit();

                                    return response()->json([
                                        'success' => true,
                                        'message' => "Scanned and Updated. Moved to \"{$modulelocation}\"",
                                        'item' => $getAvailableFNSKU
                                    ]);
                                } catch (\Exception $e) {
                                    DB::rollback();
                                    $this->logError('Error in processScan - available FNSKU', $e);

                                    return response()->json([
                                        'success' => false,
                                        'message' => 'Error processing scan: ' . $e->getMessage(),
                                        'reason' => 'database_error'
                                    ], 500);
                                }
                            } else {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'No Available FNSKU for this item'
                                ]);
                            }
                        }
                    }
                } else {
                    // Case: Duplicate serial in stockroom   
                    return response()->json([
                        'success' => false,
                        'message' => 'Duplicate Data in Stockroom',
                        'reason' => 'duplicate_data'
                    ]);
                }
            }
            // Serial not found in main stockroom tables
            else {
                // Check for item with different FNSKU
                $existingInValidation = DB::table($this->productTable)
                    ->where(function ($query) use ($serial) {
                        $query->where('serialnumber', $serial)
                            ->orWhere('serialnumberb', $serial);
                    })
                    ->where('returnstatus', 'Not Returned')
                    ->where('validation_status', 'validated')
                    ->where('ProductModuleLoc', 'Validation')
                    ->first();

                if ($existingInValidation) {
                    $id = $existingInValidation->ProductID;
                    $rtnumberofitem = $existingInValidation->rtcounter;
                    $checkFNSKUviewer = $existingInValidation->FNSKUviewer;
                    $needReprint = false;

                    if (!empty($checkFNSKUviewer)) {
                        $fetchFNSKU = trim($checkFNSKUviewer);
                        $mainFNSKU = trim($FNSKU);

                        if (trim($mainFNSKU) != trim($fetchFNSKU)) {
                            
                            $needReprint = true;
                            return response()->json([
                                'success' => false,
                                'message' => 'Error processing scan: ' . $e->getMessage(),
                                'reason' => 'database_error'
                            ], 500);

                        }

                        try {
                            // Find FNSKU in main fnsku table - using dynamic table name
                            $fnsku_data = DB::table($this->fnskuTable)
                                ->where('FNSKU', $FNSKU)
                                ->first();
                        
                            // Update product to Stockroom
                            DB::table($this->productTable)
                                ->where('ProductID', $id)
                                ->update([
                                    'ProductModuleLoc' => 'Stockroom',
                                    'warehouselocation' => $location,
                                    'stockroom_insert_date' => $curentDatetimeString
                                ]);

                            // Insert history
                            DB::table($this->itemProcessHistoryTable)->insert([
                                'rtcounter' => $rtnumberofitem,
                                'employeeName' => $User,
                                'editDate' => $curentDatetimeString,
                                'Module' => $Module,
                                'Action' => $Action
                            ]);

                            return response()->json([
                                'success' => true,
                                'message' => "Scanned and Forwarded to Stockroom Successfully",
                                'item' => $existingInValidation->AStitle,
                                'needReprint' => $needReprint,
                                'productId' => $needReprint ? $id : null
                            ]);
                        } catch (\Exception $e) {
                            $this->logError('Error in processScan - existing with different FNSKU', $e);

                            return response()->json([
                                'success' => false,
                                'message' => 'Error processing scan: ' . $e->getMessage(),
                                'reason' => 'database_error'
                            ], 500);
                        }
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot Proceed to Move item - FNSKU is Blank",
                            'reason' => 'blank_fnsku'
                        ]);
                    }
                }
                // Check for new FNSKU entry
                else {
                    $fnsku_data = DB::table($this->fnskuTable)
                        ->where('FNSKU', $FNSKU)
                        ->first();
                    if ($fnsku_data) {
                        $checkFNSKUstatus = $fnsku_data->fnsku_status;
                        $getFNSKU = $fnsku_data->FNSKU;
                        $getCondition = $fnsku_data->grading;
                        $Unitavailable = $fnsku_data->Units;
                        $getASIN = $fnsku_data->ASIN;

                        if ($checkFNSKUstatus == "available") {
                            try {
                                // Get next RT counter
                                $maxxrt = DB::table($this->productTable)->max('rtcounter');
                                $newrt = $maxxrt + 1;

                                // Insert new item
                                $newItemId = DB::table($this->productTable)->insertGetId([
                                    'rtcounter' => $newrt,
                                    'serialnumber' => $serial,
                                    'ProductModuleLoc' => $Module,
                                    'warehouselocation' => $location,
                                    'FNSKUviewer' => $getFNSKU,
                                    'FbmAvailable' => 1,
                                    'Fulfilledby' => 'FBM',
                                    'quantity' => 1,
                                    'stockroom_insert_date' => $curentDatetimeString,
                                ]);

                                // Insert history
                                DB::table($this->itemProcessHistoryTable)->insert([
                                    'rtcounter' => $newrt,
                                    'employeeName' => $User,
                                    'editDate' => $curentDatetimeString,
                                    'Module' => $Module,
                                    'Action' => $Action
                                ]);

                                //UPDATE FNSKU

                                $UdpatedUnitavailable = $Unitavailable - 1;
                                if ($UdpatedUnitavailable === 0) {
                                    // Update FNSKU status
                                    DB::table($this->fnskuTable)
                                        ->where('FNSKU', $getFNSKU)
                                        ->where('ASIN', $getASIN)
                                        ->update([
                                            'fnsku_status' => 'available',
                                            'Units' => $UdpatedUnitavailable
                                        ]);
                                } else {
                                    DB::table($this->fnskuTable)
                                        ->where('FNSKU', $getFNSKU)
                                        ->where('ASIN', $getASIN)
                                        ->update([
                                            'fnsku_status' => 'available',
                                            'Units' => $UdpatedUnitavailable
                                        ]);
                                }


                                return response()->json([
                                    'success' => true,
                                    'message' => "Scanned and Inserted Successfully",
                                    //  'item' => $getTitle
                                ]);
                            } catch (\Exception $e) {
                                $this->logError('Error in processScan - new FNSKU insert', $e);

                                return response()->json([
                                    'success' => false,
                                    'message' => 'Error processing scan: ' . $e->getMessage(),
                                    'reason' => 'database_error'
                                ], 500);
                            }
                        } else {
                            $FindavailableFnsku = DB::table($this->fnskuTable)
                                ->where('fnsku_status', 'Available')
                                ->where('ASIN', $getASIN)
                                ->where('grading', $getCondition)
                                ->first();
                            
                            if ($FindavailableFnsku) {
                                
                                $getAvailableFNSKU = $FindavailableFnsku->FNSKU;
                                $Unitavailable = $FindavailableFnsku->Units;
                                // Get next RT counter
                                $maxxrt = DB::table($this->productTable)->max('rtcounter');
                                $newrt = $maxxrt + 1;

                                // Get current date in different format
                                $curentDatet2 = $currentDatetime->format('Y-m-d');

                                DB::beginTransaction();

                                // Insert new item
                                $newItemId = DB::table($this->productTable)->insertGetId([
                                    'rtcounter' => $newrt,
                                    'serialnumber' => $serial,
                                    'ProductModuleLoc' => $Module,
                                    'warehouselocation' => $location,
                                    'FNSKUviewer' => $FNSKU,
                                    'FbmAvailable' => 1,
                                    'Fulfilledby' => 'FBM',
                                    'quantity' => 1,
                                    'stockroom_insert_date' => $curentDatetimeString
                                ]);


                                   //UPDATE FNSKU

                                   $UdpatedUnitavailable = $Unitavailable - 1;
                                   if ($UdpatedUnitavailable === 0) {
                                       // Update FNSKU status
                                       DB::table($this->fnskuTable)
                                           ->where('FNSKU', $getAvailableFNSKU)
                                           ->where('ASIN', $getASIN)
                                           ->update([
                                               'fnsku_status' => 'available',
                                               'Units' => $UdpatedUnitavailable
                                           ]);
                                   } else {
                                       DB::table($this->fnskuTable)
                                           ->where('FNSKU', $getAvailableFNSKU)
                                           ->where('ASIN', $getASIN)
                                           ->update([
                                               'fnsku_status' => 'available',
                                               'Units' => $UdpatedUnitavailable
                                           ]);
                                   }

                                     // Insert history
                                DB::table($this->itemProcessHistoryTable)->insert([
                                    'rtcounter' => $newrt,
                                    'employeeName' => $User,
                                    'editDate' => $curentDatetimeString,
                                    'Module' => $Module,
                                    'Action' => $Action
                                ]);

   

                                DB::commit();

                                return response()->json([
                                    'success' => true,
                                    'message' => "Scanned and Inserted Successfully"
                                ]);
                            } else {
                                return response()->json([
                                    'success' => false,
                                    'message' => "No Available FNSKU found for this ASIN and condition",
                                    'reason' => 'no_available_fnsku'
                                ]);
                            }
                        }
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => "FNSKU not found in database",
                            'reason' => 'fnsku_not_found'
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
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
    public function printLabel(Request $request)
    {
        $request->validate([
            'productId' => 'required|integer'
        ]);

        $productId = $request->productId;

        try {
            $product = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->first();

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found'
                ]);
            }

            // Here you would implement your actual label printing logic
            // This might involve generating a print file and sending to printer

            // For now, we'll just simulate a successful print

            return response()->json([
                'status' => 'success',
                'message' => 'Label printing started'
            ]);
        } catch (\Exception $e) {
            $this->logError('Error in printLabel', $e, ['productId' => $productId]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error printing label: ' . $e->getMessage()
            ], 500);
        }
    }
}
