<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class StockroomController extends BasetablesController
{
    
    /**
     * Find related ASINs with full recursive search - exact conversion from original function
     */
    private function findRelatedAsins($searchTerm)
    {
        $cacheKey = "related_asins_" . md5($searchTerm);
        
        return Cache::remember($cacheKey, 300, function() use ($searchTerm) { // Cache for 5 minutes
            $related = [$searchTerm]; // Start with the search term in the array
            $checked = [];

            // Safety counter to prevent infinite loops
            $maxIterations = 50;
            $iterations = 0;

            while (!empty($related) && $iterations < $maxIterations) {
                $asinToCheck = array_pop($related);
                if (in_array($asinToCheck, $checked)) continue;
                $checked[] = $asinToCheck;

                // Query that matches your original exactly - including internal field
                $results = DB::table($this->asinTable)
                    ->select('ASIN', 'ParentAsin', 'CousinASIN', 'UpgradeASIN', 'GrandASIN')
                    ->where(function($query) use ($asinToCheck) {
                        $query->where('ASIN', $asinToCheck)
                              ->orWhere('ParentAsin', $asinToCheck)
                              ->orWhere('CousinASIN', $asinToCheck)
                              ->orWhere('UpgradeASIN', $asinToCheck)
                              ->orWhere('GrandASIN', $asinToCheck)
                              ->orWhere('internal', $asinToCheck); // Added this field that was missing
                    })
                    ->get();

                foreach ($results as $row) {
                    foreach (['ASIN', 'ParentAsin', 'CousinASIN', 'UpgradeASIN', 'GrandASIN'] as $field) {
                        $val = $row->$field ?? '';
                        if (!empty($val) && !in_array($val, $checked) && !in_array($val, $related)) {
                            $related[] = $val;
                        }
                    }
                }
                
                $iterations++;
            }

            return $checked;
        });
    }

    /**
     * Display a listing of products in stockroom with optimized queries and caching
     */
    public function index(Request $request)
    {
        try {
            $perPage = min($request->input('per_page', 15), 100); // Limit max results, increased default
            $search = $request->input('search', '');
            $store = $request->input('store', '');
            $page = $request->input('page', 1);
            
            // Create cache key based on parameters
            $cacheKey = "stockroom_inventory_{$page}_{$perPage}_{$store}_" . md5($search);
            
            // Try to get from cache first (cache for 30 seconds)
            if (empty($search)) { // Only cache non-search results
                $cachedResult = Cache::get($cacheKey);
                if ($cachedResult) {
                    return response()->json($cachedResult);
                }
            }
            
            // Optimized main query with eager loading
            $asinQuery = DB::table($this->asinTable . ' as asin')
                ->select([
                    'asin.ASIN',
                    'asin.internal as AStitle',
                    DB::raw('MIN(fnsku.storename) as storename'),
                    DB::raw('SUM(CASE WHEN prod.ProductModuleLoc = "Stockroom" THEN prod.FBMAvailable ELSE 0 END) as FBMAvailable'),
                    DB::raw('SUM(CASE WHEN prod.ProductModuleLoc = "Stockroom" THEN prod.FbaAvailable ELSE 0 END) as FbaAvailable'),
                    DB::raw('SUM(CASE WHEN prod.ProductModuleLoc = "Stockroom" THEN prod.Outbound ELSE 0 END) as Outbound'),
                    DB::raw('SUM(CASE WHEN prod.ProductModuleLoc = "Stockroom" THEN prod.Inbound ELSE 0 END) as Inbound'),
                    DB::raw('SUM(CASE WHEN prod.ProductModuleLoc = "Stockroom" THEN prod.Unfulfillable ELSE 0 END) as Unfulfillable'),
                    DB::raw('SUM(CASE WHEN prod.ProductModuleLoc = "Stockroom" THEN prod.Reserved ELSE 0 END) as Reserved'),
                    DB::raw('COUNT(CASE WHEN prod.ProductModuleLoc = "Stockroom" THEN prod.ProductID ELSE NULL END) as item_count')
                ])
                ->leftJoin($this->fnskuTable . ' as fnsku', 'asin.ASIN', '=', 'fnsku.ASIN')
                ->leftJoin($this->productTable . ' as prod', function ($join) {
                    $join->on('fnsku.FNSKU', '=', 'prod.FNSKUviewer')
                         ->where('prod.ProductModuleLoc', '=', 'Stockroom'); // Move condition to join for better performance
                })
                ->where('asin.ASIN', '!=', '')
                ->whereNotNull('asin.ASIN');
            
            // Optimize search with indexed fields first
            if (!empty($search)) {
                $asinQuery->where(function ($query) use ($search) {
                    // Start with most selective searches (indexed fields)
                    $query->where('asin.ASIN', 'like', "%{$search}%")
                          ->orWhere('fnsku.FNSKU', 'like', "%{$search}%");
                    
                    // Only add expensive searches if search term is long enough
                    if (strlen($search) > 3) {
                        $query->orWhere('asin.internal', 'like', "%{$search}%")
                              ->orWhere('prod.serialnumber', 'like', "%{$search}%")
                              ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                    }
                    
                    // Handle ASIN relationship search - THIS IS THE KEY FIX
                    if (preg_match('/^B0[A-Z0-9]{8}$/i', $search)) {
                        $relatedAsins = $this->findRelatedAsins($search);
                        if (!empty($relatedAsins)) {
                            // Filter out empty or null ASINs from related ASINs
                            $relatedAsins = array_filter($relatedAsins, function($asin) {
                                return !empty($asin) && $asin !== null;
                            });
                            
                            if (!empty($relatedAsins)) {
                                // Search for related ASINs in the ASIN table directly - not limited by store
                                $query->orWhereIn('asin.ASIN', $relatedAsins);
                            }
                        }
                    } else {
                        // If not ASIN pattern, just do direct search
                        $query->orWhere('asin.ASIN', 'like', "%{$search}%");
                    }
                });
            }
            
            // Apply store filter
            if (!empty($store)) {
                $asinQuery->where('fnsku.storename', $store);
            }
            
            // Add having clause to filter out items with no stockroom products
            // BUT - if we're searching for related ASINs, we want to show all related ones even if they have 0 inventory
            if (!empty($search) && preg_match('/^B0[A-Z0-9]{8}$/i', $search)) {
                // For ASIN searches, show related ASINs even with 0 inventory
                $asinQuery->groupBy('asin.ASIN', 'asin.internal')
                         ->having('item_count', '>=', 0); // Show all related ASINs
            } else {
                // For regular searches, only show items with inventory
                $asinQuery->groupBy('asin.ASIN', 'asin.internal')
                         ->having('item_count', '>', 0); // Only show items that have stockroom inventory
            }
            
            // Get paginated results
            $asins = $asinQuery->paginate($perPage);
            
            // Get ASINs for batch loading related data
            $asinList = $asins->getCollection()->pluck('ASIN')->toArray();
            
            if (empty($asinList)) {
                $result = $asins->toArray();
                $result['data'] = [];
                
                if (empty($search)) {
                    Cache::put($cacheKey, $result, 30);
                }
                
                return response()->json($result);
            }
            
            // Batch load FNSKUs for all ASINs
            $fnskuData = DB::table($this->fnskuTable)
                ->select('ASIN', 'FNSKU', 'MSKU', 'grading', 'storename')
                ->whereIn('ASIN', $asinList)
                ->get()
                ->groupBy('ASIN');
            
            // Batch load serials for all ASINs
            $serialData = DB::table($this->productTable . ' as prod')
                ->select(
                    'fnsku.ASIN',
                    'prod.serialnumber', 
                    'prod.ProductID', 
                    'prod.rtcounter', 
                    'prod.warehouselocation',
                    'prod.FNSKUviewer',
                    'fnsku.MSKU',
                    'fnsku.grading'
                )
                ->join($this->fnskuTable . ' as fnsku', 'prod.FNSKUviewer', '=', 'fnsku.FNSKU')
                ->whereIn('fnsku.ASIN', $asinList)
                ->where('prod.ProductModuleLoc', 'Stockroom')
                ->get()
                ->groupBy('ASIN');
            
            // Process results with batch-loaded data
            $results = $asins->getCollection()->map(function($item) use ($fnskuData, $serialData) {
                if (empty($item->ASIN)) {
                    return null;
                }
                
                // Get FNSKUs from batch-loaded data
                $item->fnskus = isset($fnskuData[$item->ASIN]) ? $fnskuData[$item->ASIN]->toArray() : [];
                
                // Get serials from batch-loaded data
                $item->serials = isset($serialData[$item->ASIN]) ? $serialData[$item->ASIN]->toArray() : [];
                
                // Extract pack size and adjust counts
                $packSize = $this->extractPackSizeFromTitle($item->AStitle);
                if ($packSize > 1) {
                    $item->box_count = $item->item_count;
                    $item->item_count = $item->item_count * $packSize;
                    $item->pack_size = $packSize;
                } else {
                    $item->box_count = $item->item_count;
                    $item->pack_size = 1;
                }
                
                return $item;
            })->filter();
            
            // Update the collection
            $asins->setCollection($results);
            $result = $asins->toArray();
            
            // Cache non-search results
            if (empty($search)) {
                Cache::put($cacheKey, $result, 30);
            }
            
            return response()->json($result);
            
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


    private function convertItemCondition($itemCondition, $storeName, $asin = null, $originalGrading = null)
    {
        // Normalize store name check
        $isAllrenewed = in_array(strtolower($storeName), ['allrenewed', 'all renewed']);
        
        switch ($itemCondition) {
            case 'UsedLikeNew':
                return 'Used - Like New';
                
            case 'UsedVeryGood':
                if ($isAllrenewed) {
                    return 'Refurbished - Excellent';
                } else {
                    return 'Used - Very Good';
                }
                
            case 'UsedGood':
                if ($isAllrenewed) {
                    return 'Refurbished - Good';
                } else {
                    return 'Used - Good';
                }
                
            case 'UsedAcceptable':
                if ($isAllrenewed) {
                    return 'Refurbished - Acceptable';
                } else {
                    return 'Used - Acceptable';
                }
                
            case 'New':
                if ($isAllrenewed && $asin) {
                    // Check ASIN status for Allrenewed store
                    $asinData = DB::table($this->asinTable)
                        ->where('ASIN', $asin)
                        ->first();
                        
                    if ($asinData) {
                        $asinStatus = strtolower($asinData->asinStatus ?? '');
                        if ($asinStatus === 'renewed') {
                            return 'Refurbished - Excellent';
                        }
                    }
                    
                    // If no ASIN status or not renewed, return original grading
                    return $originalGrading ?? 'New';
                } else {
                    // For non-Allrenewed stores, return original grading
                    return $originalGrading ?? 'New';
                }
                
            default:
                // Handle unexpected condition values
                return $originalGrading ?? $itemCondition;
        }
    }

    /**
     * Helper function to extract pack size from product title with caching
     */
    private function extractPackSizeFromTitle($title)
    {
        static $packSizeCache = [];
        
        if (isset($packSizeCache[$title])) {
            return $packSizeCache[$title];
        }
        
        $packSize = 1;
        if (preg_match('/(\d+)-Pack/i', $title, $matches)) {
            if (isset($matches[1]) && is_numeric($matches[1])) {
                $packSize = (int)$matches[1];
            }
        }
        
        $packSizeCache[$title] = $packSize;
        return $packSize;
    }

    /**
     * Get list of store names for the dropdown with caching
     */
    public function getStores()
    {
        try {
            return response()->json(Cache::remember('stockroom_stores', 3600, function() { // Cache for 1 hour
                return DB::table($this->fnskuTable)
                    ->select('storename')
                    ->distinct()
                    ->whereNotNull('storename')
                    ->where('storename', '!=', '')
                    ->orderBy('storename')
                    ->pluck('storename');
            }));
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
                                'validation_status'=>'validated',
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
                    ->where('validation_status', 'validated')
                    ->where('ProductModuleLoc', 'Validation')
                    ->first();

                if ($existingInValidation) {
                    $id = $existingInValidation->ProductID;
                    $rtnumberofitem = $existingInValidation->rtcounter;
                    $checkFNSKUviewer = $existingInValidation->FNSKUviewer;
                    $needReprint = false;
                    $validationSTATUS = $existingInValidation->validation_status;

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
                    if($validationSTATUS === 'validated'){
                    return response()->json([
                        'success' => true,
                        'message' => "Scanned and Forwarded to {$modulelocation} Successfully",
                        'needReprint' => $needReprint,
                        'productId' => $needReprint ? $id : null
                    ]);
                    }else{
                        return response()->json([
                            'success' => true,
                            'message' => "Data not validated but forwarded to Stockroom", 
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
                            'validation_status'=>'validated',
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
                            'validation_status'=>'validated',
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

    public function mergeItems(Request $request)
    {
        // Validate request data - add fnsku to validation
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'title' => 'sometimes|string',
            'productId' => 'sometimes|integer',
            'asin' => 'sometimes|string',
            'store' => 'sometimes|string',
            'serialNumbers' => 'sometimes|array',
            'fnsku' => 'sometimes|string' // Add FNSKU parameter
        ]);

        $selectedIds = $request->items;
        $numOfSerial = count($selectedIds);

        if (empty($selectedIds)) {
            return response()->json([
                'success' => false, 
                'message' => 'No selected items to merge.'
            ]);
        }

        try {
            // Start database transaction
            DB::beginTransaction();

            // Get the serial numbers from selected products
            $serialNumberResults = DB::table($this->productTable)
                ->whereIn('ProductID', $selectedIds)
                ->get();

            if ($serialNumberResults->isEmpty()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'No records found for selected IDs.'
                ]);
            }

            $serialNumberA = null;
            $serialNumberB = null;
            $serialNumberC = null;
            $serialNumberD = null;
            $totalPrice = 0;
            $index = 0;
            
            // Get data directly from the request if provided
            $title = $request->title ?? '';
            $productAsin = $request->asin ?? '';
            $firstStore = $request->store ?? '';
            $providedFnsku = $request->fnsku ?? '';
            
            // Process each selected item
            foreach ($serialNumberResults as $row) {
                $serialNumber = $row->serialnumber;
                $price = $row->price ?? 0;
                
                // If title wasn't provided from the frontend, get it from the first product
                if (empty($title) && $index === 0) {
                    $title = $row->AStitle ?? '';
                    $firstStore = $row->StoreName ?? '';
                }

                // Assign serial numbers to the appropriate slots
                switch ($index) {
                    case 0:
                        $serialNumberA = $serialNumber;
                        break;
                    case 1:
                        $serialNumberB = $serialNumber;
                        break;
                    case 2:
                        $serialNumberC = $serialNumber;
                        break;
                    case 3:
                        $serialNumberD = $serialNumber;
                        break;
                }
                
                $index++;
                $totalPrice += $price;
            }

            // Extract color from the product title
            preg_match('/\((.*?)\)/', $title, $matches);
            $color = isset($matches[1]) ? $matches[1] : '';
            
            // Get the base title without the color and without any existing pack information
            $baseTitle = trim(preg_replace('/\s*\(.*?\)\s*/', '', $title)); // Remove color
            $baseTitle = trim(preg_replace('/\s+\d+-Pack\s*/', ' ', $baseTitle)); // Remove existing pack info
            
            // Create exact title pattern to search for with the correct pack size
            $exactTitlePattern = $baseTitle;
            if ($numOfSerial > 1) {
                $exactTitlePattern .= ' ' . $numOfSerial . '-Pack';
            }
            $exactTitlePattern .= ' (' . $color . ')';
            
            // For logging
            $exactTitlePatternForLike = '%' . $exactTitlePattern . '%';
            
            // Get base title for like query - used in less strict searches
            $baseTitleForLike = '%' . $baseTitle . '%';
            $colorForLike = '%(' . $color . ')%';
            $packTextForLike = $numOfSerial > 1 ? '%' . $numOfSerial . '-Pack%' : '';
            
            // Log search parameters for debugging
            Log::info('Searching for ASIN with parameters:', [
                'originalTitle' => $title,
                'baseTitle' => $baseTitle,
                'color' => $color,
                'numOfSerial' => $numOfSerial,
                'exactTitlePattern' => $exactTitlePattern,
                'exactTitlePatternForLike' => $exactTitlePatternForLike,
                'providedAsin' => $productAsin,
                'providedFnsku' => $providedFnsku
            ]);

            // If no ASIN found by direct lookup, try searching for exact match first
            $asinResult = null;
            
            // Try to match the exact title pattern with pack size and color
            $asinResult = DB::table($this->asinTable)
                ->where('internal', 'like', $exactTitlePatternForLike)
                ->first();
                
            if ($asinResult) {
                Log::info('Found ASIN by exact title pattern', [
                    'ASIN' => $asinResult->ASIN,
                    'internal' => $asinResult->internal
                ]);
            }

            // If still no ASIN found, try more specific searches
            if (!$asinResult && $numOfSerial > 1) {
                // Try to find by base title, specific pack size and color
                $asinResult = DB::table($this->asinTable)
                    ->where('internal', 'like', $baseTitleForLike)
                    ->where('internal', 'like', $packTextForLike)
                    ->where('internal', 'like', $colorForLike)
                    ->first();
                    
                if ($asinResult) {
                    Log::info('Found ASIN by base title, pack size and color', [
                        'ASIN' => $asinResult->ASIN,
                        'internal' => $asinResult->internal
                    ]);
                }
            }

            // If still no ASIN found, try less specific search
            if (!$asinResult) {
                // Try to find by base title and color without pack size constraint
                $asinResult = DB::table($this->asinTable)
                    ->where('internal', 'like', $baseTitleForLike)
                    ->where('internal', 'like', $colorForLike)
                    ->first();
                    
                if ($asinResult) {
                    Log::info('Found ASIN by base title and color', [
                        'ASIN' => $asinResult->ASIN,
                        'internal' => $asinResult->internal
                    ]);
                }
            }

            // Last resort - just find by base title
            if (!$asinResult) {
                $asinResult = DB::table($this->asinTable)
                    ->where('internal', 'like', $baseTitleForLike)
                    ->first();
                    
                if ($asinResult) {
                    Log::info('Found ASIN by base title only', [
                        'ASIN' => $asinResult->ASIN,
                        'internal' => $asinResult->internal
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No matching ASIN records found for "' . $baseTitle . '" with pack size "' . $numOfSerial . '" and color "' . $color . '".'
                    ]);
                }
            }

            // Verify that the found ASIN is a reasonable match for our product
            $asinTitle = $asinResult->internal;
            $containsBaseTitle = stripos($asinTitle, $baseTitle) !== false;
            $containsColor = stripos($asinTitle, $color) !== false;
            
            if (!$containsBaseTitle || !$containsColor) {
                Log::warning('Found ASIN may not be a good match', [
                    'searchTitle' => $baseTitle,
                    'searchColor' => $color,
                    'foundTitle' => $asinTitle,
                    'containsBaseTitle' => $containsBaseTitle,
                    'containsColor' => $containsColor
                ]);
                
                // If we really can't find a good match, construct our own title
                if (!$containsBaseTitle || !$containsColor) {
                    $constructedTitle = $baseTitle;
                    if ($numOfSerial > 1) {
                        $constructedTitle .= ' ' . $numOfSerial . '-Pack';
                    }
                    $constructedTitle .= ' (' . $color . ')';
                    
                    Log::info('Using constructed title instead', [
                        'constructedTitle' => $constructedTitle
                    ]);
                    
                    $asinTitle = $constructedTitle;
                }
            }

            $getAsin = $asinResult->ASIN;
            $store = $firstStore;

            // Handle FNSKU assignment
            $getfnsku = null;
            $getFNSKUID = null;
            
            // Look up an available FNSKU based on the ASIN
            $fnskuResult = DB::table($this->fnskuTable)
                ->where('ASIN', $getAsin)
                ->where('fnsku_status', 'available')
            //    ->orderBy('dateFreeUp', 'asc')
                ->first();

            if (!$fnskuResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available FNSKU found for ASIN: ' . $getAsin
                ]);
            }
            
            $getfnsku = $fnskuResult->FNSKU;
            $getFNSKUID = $fnskuResult->FNSKUID;

            // Get current date time in LA timezone
            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            $currentDate = $currentDatetime->format('Y-m-d');
            $currentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');

            // Insert migration record
            $mergeId = DB::table('tblmigrateditem')->insertGetId([
                'migratedDate' => $currentDate
            ]);

            // Get max RT counter and increment
            $maxRt = DB::table($this->productTable)->max('rtcounter') ?? 0;
            $newRt = $maxRt + 1;

            // Create product data array with all needed fields
            $productData = [
                'rtcounter' => $newRt,
                'mergeID' => $mergeId,
                'price' => $totalPrice,
                'quantity' => $numOfSerial,
                'stockroom_insert_date' => $currentDatetimeString,
                'ProductModuleLoc' => 'Stockroom',
                'serialnumber' => $serialNumberA,
                'serialnumberb' => $serialNumberB,
                'serialnumberc' => $serialNumberC,
                'serialnumberd' => $serialNumberD,
                'FNSKUviewer' => $getfnsku
            ];

            // Insert new product record
            $productId = DB::table($this->productTable)->insertGetId($productData);

            // Update FNSKU status and increment Units by numOfSerial
            // First get current Units value
            $currentUnits = $fnskuResult->Units ?? 0;
            $newUnits = $currentUnits + $numOfSerial;
            $newUnits = $currentUnits - 1;
            if($newUnits === 0){
            // Update FNSKU with new Units count
            DB::table($this->fnskuTable)
                ->where('FNSKU', $getfnsku)
                ->update([
                    'fnsku_status' => 'unavailable',
                    'productid' => $productId,
                    'Units' => $newUnits // Update the Units count
                ]);
            }else{
                DB::table($this->fnskuTable)
                ->where('FNSKU', $getfnsku)
                ->update([
                    'fnsku_status' => 'available',
                    'productid' => $productId,
                    'Units' => $newUnits // Update the Units count
                ]);
            }
            // Log the Units update
            Log::info('Updated FNSKU Units count', [
                'FNSKU' => $getfnsku,
                'previousUnits' => $currentUnits,
                'addedUnits' => $numOfSerial,
                'newUnits' => $newUnits
            ]);

            // Update original products
            DB::table($this->productTable)
                ->whereIn('ProductID', $selectedIds)
                ->update([
                    'ProductModuleLoc' => 'Merged',
                    'mergedTO' => $newRt
                ]);

             // If FNSKU was directly provided, use it
            if (!empty($providedFnsku)) {
                // Check if the provided FNSKU exists and is available
                $fnskuResult1 = DB::table($this->fnskuTable)
                    ->where('FNSKU', $providedFnsku)
                    ->first();
                    
                $oldfnsku = $fnskuResult1->FNSKU;
                $oldfnsku = $fnskuResult1->FNSKU;
                $oldunit = $fnskuResult1 -> Units ?? 0;
                $returnOldUNIT = $oldunit + $numOfSerial ;
                
                DB::table($this->fnskuTable)
                ->where('FNSKU', $providedFnsku)
                ->update([
                    'fnsku_status' => 'available',
                    'Units' => $returnOldUNIT
                ]);
            }   

            // Commit transaction
            DB::commit();

            // Get final title from either ASIN search or constructed title
            $finalTitle = isset($asinTitle) ? $asinTitle : $title;

            return response()->json([
                'success' => true, 
                'message' => 'Items merged successfully.',
                'newrt' => $newRt,
                'SERIAL' => $serialNumberA,
                'productid' => $productId,
                'store' => $store,
                'title' => $finalTitle,
                'fnsku' => $getfnsku,
                'units' => $newUnits // Include the updated units count in the response
            ]);

        } catch (\Exception $e) {
            // Rollback in case of error
            DB::rollBack();
            Log::error('Error in mergeItems: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false, 
                'message' => 'Error during merge operation: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function updateLocation(Request $request)
    {
        // Validate request data with more flexible validation
        $validated = $request->validate([
            'itemIds' => 'required_without:itemId|array',
            'itemId' => 'required_without:itemIds|integer',
            'newLocation' => 'required|string',
        ]);

        try {
            // Start database transaction
            DB::beginTransaction();
            
            // Determine which IDs to update
            $idsToUpdate = [];
            
            if ($request->has('itemIds') && is_array($request->itemIds)) {
                $idsToUpdate = $request->itemIds;
            } elseif ($request->has('itemId')) {
                $idsToUpdate = [$request->itemId];
            }
            
            if (empty($idsToUpdate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid item IDs provided.'
                ]);
            }
            
            // Update location for all selected items
            DB::table($this->productTable)
                ->whereIn('ProductID', $idsToUpdate)
                ->update([
                    'warehouselocation' => $request->newLocation
                ]);
                
            // Commit transaction
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully.',
                'count' => count($idsToUpdate)
            ]);
        } catch (\Exception $e) {
            // Rollback in case of error
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating location: ' . $e->getMessage()
            ]);
        }
    }
}