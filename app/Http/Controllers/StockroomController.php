<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StockroomController extends BasetablesController
{

    /**
     * Extract base FNSKU from prefixed FNSKU
     */
    private function extractBaseFnsku($fnsku)
    {
        if (empty($fnsku)) {
            return $fnsku;
        }

        // Check if it's a prefixed FNSKU (starts with C followed by digits)
        if (preg_match('/^C(\d+)(.+)$/', $fnsku, $matches)) {
            return $matches[2]; // Return the base FNSKU without prefix
        }

        return $fnsku; // Return as-is if not prefixed
    }

    /**
     * Generate the next available FNSKU with incremental prefix based on remaining units
     */
    private function getNextAvailableFnsku($baseFnsku, $asin, $grading, $storename)
    {
        // Get the FNSKU record
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->first();

        if (!$fnskuRecord) {
            throw new \Exception("FNSKU not found: {$baseFnsku}");
        }

        $remainingUnits = $fnskuRecord->Units ?? 0;
        $maxUnits = 11; // Your standard max units

        // Check if we have any units left
        if ($remainingUnits <= 0) {
            throw new \Exception("FNSKU {$baseFnsku} has no remaining units");
        }

        // Calculate how many times this FNSKU has been used
        $timesUsed = $maxUnits - $remainingUnits;

        // Generate the actual FNSKU to use
        if ($timesUsed == 0) {
            // First usage - use original FNSKU
            $actualFnsku = $baseFnsku;
        } else {
            // Subsequent usage - add prefix
            $prefix = 'C' . $timesUsed;
            $actualFnsku = $prefix . $baseFnsku;
        }

        return [
            'actual_fnsku' => $actualFnsku,
            'times_used' => $timesUsed,
            'remaining_units' => $remainingUnits - 1, // After this use
            'base_fnsku' => $baseFnsku,
            'fnsku_id' => $fnskuRecord->FNSKUID ?? null
        ];
    }

    /**
     * Update FNSKU units after using an FNSKU
     */
    private function updateFnskuUnits($baseFnsku, $asin, $grading, $storename)
    {
        // Decrement the units
        $affected = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->where('Units', '>', 0) // Only decrement if units > 0
            ->decrement('Units');

        if ($affected == 0) {
            throw new \Exception("Could not update FNSKU units - no available units");
        }

        // Check if FNSKU should become unavailable (Units = 0)
        $updatedRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->first();

        $becameUnavailable = false;
        if ($updatedRecord && $updatedRecord->Units <= 0) {
            DB::table($this->fnskuTable)
                ->where('FNSKU', $baseFnsku)
                ->where('ASIN', $asin)
                ->where('grading', $grading)
                ->where('storename', $storename)
                ->update(['fnsku_status' => 'unavailable']);
            $becameUnavailable = true;
        }

        return $becameUnavailable;
    }

    /**
     * Check if an FNSKU (with or without prefix) is available
     */
    private function checkFnskuAvailabilityWithPrefix($inputFnsku)
    {
        // First, try to find it as-is (for original FNSKUs)
        $directMatch = DB::table($this->fnskuTable)
            ->where('FNSKU', $inputFnsku)
            ->where('fnsku_status', 'available')
            ->where('Units', '>', 0)
            ->first();

        if ($directMatch) {
            return [
                'available' => true,
                'base_fnsku' => $inputFnsku, // This IS the base FNSKU
                'record' => $directMatch
            ];
        }

        // Check if it's a prefixed FNSKU (starts with C followed by digits)
        if (preg_match('/^C(\d+)(.+)$/', $inputFnsku, $matches)) {
            $baseFnsku = $matches[2]; // Extract the base FNSKU
            
            $baseRecord = DB::table($this->fnskuTable)
                ->where('FNSKU', $baseFnsku)
                ->where('fnsku_status', 'available')
                ->where('Units', '>', 0)
                ->first();

            if ($baseRecord) {
                return [
                    'available' => true,
                    'base_fnsku' => $baseFnsku,
                    'record' => $baseRecord
                ];
            }
        }

        return [
            'available' => false,
            'base_fnsku' => null,
            'record' => null
        ];
    }

    /**
     * Find related ASINs with full recursive search - exact conversion from original function
     */
    private function findRelatedAsins($searchTerm)
    {
        $cacheKey = "related_asins_" . md5($searchTerm);

        return Cache::remember($cacheKey, 300, function () use ($searchTerm) { // Cache for 5 minutes
            $related = [$searchTerm]; // Start with the search term in the array
            $checked = [];

            // Safety counter to prevent infinite loops
            $maxIterations = 50;
            $iterations = 0;

            while (!empty($related) && $iterations < $maxIterations) {
                $asinToCheck = array_pop($related);
                if (in_array($asinToCheck, $checked))
                    continue;
                $checked[] = $asinToCheck;

                // Query that matches your original exactly - including internal field
                $results = DB::table($this->asinTable)
                    ->select('ASIN', 'ParentAsin', 'CousinASIN', 'UpgradeASIN', 'GrandASIN')
                    ->where(function ($query) use ($asinToCheck) {
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
     * MODIFIED to handle prefixed FNSKUs in joins - SIMPLIFIED VERSION
     */
    public function index(Request $request)
    {
        try {
            $perPage = min($request->input('per_page', 15), 100);
            $search = $request->input('search', '');
            $store = $request->input('store', '');
            $page = $request->input('page', 1);

            $cacheKey = "stockroom_inventory_{$page}_{$perPage}_{$store}_" . md5($search);

            if (empty($search)) {
                $cachedResult = Cache::get($cacheKey);
                if ($cachedResult) {
                    return response()->json($cachedResult);
                }
            }

            // SIMPLIFIED: Get products first, then match FNSKUs in PHP
            $productsQuery = DB::table($this->productTable . ' as prod')
                ->select([
                    'prod.ProductID',
                    'prod.serialnumber',
                    'prod.rtcounter',
                    'prod.warehouselocation',
                    'prod.FNSKUviewer',
                    'prod.FBMAvailable',
                    'prod.FbaAvailable',
                    'prod.Outbound',
                    'prod.Inbound',
                    'prod.Unfulfillable',
                    'prod.Reserved'
                ])
                ->where('prod.ProductModuleLoc', 'Stockroom');

            $products = $productsQuery->get();

            // Extract all unique base FNSKUs
            $baseFnskus = [];
            $fnskuProductMap = [];
            
            foreach ($products as $product) {
                $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                $baseFnskus[] = $baseFnsku;
                
                if (!isset($fnskuProductMap[$baseFnsku])) {
                    $fnskuProductMap[$baseFnsku] = [];
                }
                $fnskuProductMap[$baseFnsku][] = $product;
            }

            $baseFnskus = array_unique($baseFnskus);

            // Get FNSKU data for base FNSKUs
            $fnskuData = DB::table($this->fnskuTable)
                ->select('ASIN', 'FNSKU', 'MSKU', 'grading', 'storename')
                ->whereIn('FNSKU', $baseFnskus)
                ->get()
                ->keyBy('FNSKU');

            // Get ASIN data
            $asinList = $fnskuData->pluck('ASIN')->unique()->toArray();
            
            if (empty($asinList)) {
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0
                ]);
            }

            $asinQuery = DB::table($this->asinTable . ' as asin')
                ->select([
                    'asin.ASIN',
                    'asin.internal as AStitle',
                    'asin.asinStatus'
                ])
                ->whereIn('asin.ASIN', $asinList)
                ->where('asin.ASIN', '!=', '')
                ->whereNotNull('asin.ASIN');

            // Apply search functionality
            if (!empty($search)) {
                $asinQuery->where(function ($query) use ($search, $baseFnskus, $products) {
                    $query->where('asin.ASIN', 'like', "%{$search}%");

                    if (strlen($search) > 3) {
                        $query->orWhere('asin.internal', 'like', "%{$search}%")
                            ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                    }

                    // Search in FNSKUs
                    $matchingFnskus = array_filter($baseFnskus, function($fnsku) use ($search) {
                        return strpos($fnsku, $search) !== false;
                    });
                    if (!empty($matchingFnskus)) {
                        $matchingAsins = DB::table($this->fnskuTable)
                            ->whereIn('FNSKU', $matchingFnskus)
                            ->pluck('ASIN')
                            ->toArray();
                        if (!empty($matchingAsins)) {
                            $query->orWhereIn('asin.ASIN', $matchingAsins);
                        }
                    }

                    // Search in serial numbers
                    $matchingSerials = $products->filter(function($product) use ($search) {
                        return strpos($product->serialnumber, $search) !== false;
                    });
                    if ($matchingSerials->count() > 0) {
                        $matchingFnskusFromSerials = $matchingSerials->pluck('FNSKUviewer')
                            ->map(function($fnsku) {
                                return $this->extractBaseFnsku($fnsku);
                            })
                            ->unique()
                            ->toArray();
                        
                        $matchingAsinsFromSerials = DB::table($this->fnskuTable)
                            ->whereIn('FNSKU', $matchingFnskusFromSerials)
                            ->pluck('ASIN')
                            ->toArray();
                        if (!empty($matchingAsinsFromSerials)) {
                            $query->orWhereIn('asin.ASIN', $matchingAsinsFromSerials);
                        }
                    }

                    if (preg_match('/^B0[A-Z0-9]{8}$/i', $search)) {
                        $relatedAsins = $this->findRelatedAsins($search);
                        if (!empty($relatedAsins)) {
                            $relatedAsins = array_filter($relatedAsins, function ($asin) {
                                return !empty($asin) && $asin !== null;
                            });

                            if (!empty($relatedAsins)) {
                                $query->orWhereIn('asin.ASIN', $relatedAsins);
                            }
                        }
                    }
                });
            }

            // Apply store filter
            if (!empty($store)) {
                $storeFilteredFnskus = DB::table($this->fnskuTable)
                    ->where('storename', $store)
                    ->whereIn('FNSKU', $baseFnskus)
                    ->pluck('ASIN')
                    ->toArray();
                
                if (!empty($storeFilteredFnskus)) {
                    $asinQuery->whereIn('asin.ASIN', $storeFilteredFnskus);
                } else {
                    // No matching ASINs for this store
                    return response()->json([
                        'data' => [],
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0
                    ]);
                }
            }

            $asins = $asinQuery->get();

            // Process results and aggregate data
            $results = [];
            foreach ($asins as $asin) {
                // Get FNSKUs for this ASIN
                $asinFnskus = $fnskuData->where('ASIN', $asin->ASIN);
                
                if ($asinFnskus->isEmpty()) {
                    continue;
                }

                // Get products for these FNSKUs
                $asinProducts = collect();
                foreach ($asinFnskus as $fnskuRecord) {
                    if (isset($fnskuProductMap[$fnskuRecord->FNSKU])) {
                        foreach ($fnskuProductMap[$fnskuRecord->FNSKU] as $product) {
                            $asinProducts->push($product);
                        }
                    }
                }

                if ($asinProducts->isEmpty()) {
                    continue;
                }

                // Apply store filter at product level if needed
                if (!empty($store)) {
                    $storeAsinFnskus = $asinFnskus->where('storename', $store);
                    if ($storeAsinFnskus->isEmpty()) {
                        continue;
                    }
                }

                // Aggregate the data
                $item = new \stdClass();
                $item->ASIN = $asin->ASIN;
                $item->AStitle = $asin->AStitle;
                $item->asinStatus = $asin->asinStatus;
                $item->storename = $asinFnskus->first()->storename ?? '';
                
                // Aggregate inventory numbers
                $item->FBMAvailable = $asinProducts->sum('FBMAvailable');
                $item->FbaAvailable = $asinProducts->sum('FbaAvailable');
                $item->Outbound = $asinProducts->sum('Outbound');
                $item->Inbound = $asinProducts->sum('Inbound');
                $item->Unfulfillable = $asinProducts->sum('Unfulfillable');
                $item->Reserved = $asinProducts->sum('Reserved');
                $item->item_count = $asinProducts->count();

                // Add FNSKUs and serials
                $item->fnskus = $asinFnskus->toArray();
                $item->serials = $asinProducts->map(function($product) use ($fnskuData) {
                    $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                    $fnskuRecord = $fnskuData->get($baseFnsku);
                    
                    return (object)[
                        'ProductID' => $product->ProductID,
                        'serialnumber' => $product->serialnumber,
                        'rtcounter' => $product->rtcounter,
                        'warehouselocation' => $product->warehouselocation,
                        'FNSKUviewer' => $product->FNSKUviewer,
                        'MSKU' => $fnskuRecord->MSKU ?? '',
                        'grading' => $fnskuRecord->grading ?? ''
                    ];
                })->toArray();

                // Handle pack sizes
                $packSize = $this->extractPackSizeFromTitle($item->AStitle);
                if ($packSize > 1) {
                    $item->box_count = $item->item_count;
                    $item->item_count = $item->item_count * $packSize;
                    $item->pack_size = $packSize;
                } else {
                    $item->box_count = $item->item_count;
                    $item->pack_size = 1;
                }

                $results[] = $item;
            }

            // Manual pagination
            $total = count($results);
            $totalPages = ceil($total / $perPage);
            $offset = ($page - 1) * $perPage;
            $paginatedResults = array_slice($results, $offset, $perPage);

            $result = [
                'data' => $paginatedResults,
                'current_page' => $page,
                'last_page' => $totalPages,
                'per_page' => $perPage,
                'total' => $total
            ];

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
                $packSize = (int) $matches[1];
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
            return response()->json(Cache::remember('stockroom_stores', 3600, function () {
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

    private function normalizeFnsku($fnsku)
    {
        if (empty($fnsku)) {
            return $fnsku;
        }

        $fnsku = trim($fnsku);

        if (
            strpos($fnsku, '-') === false &&
            strlen($fnsku) > 10 &&
            preg_match('/^[A-Z0-9]{2}[X0-9]/', strtoupper($fnsku))
        ) {
            $normalizedFnsku = substr($fnsku, 2);
            Log::info('FNSKU normalized', [
                'original' => $fnsku,
                'normalized' => $normalizedFnsku
            ]);
            return $normalizedFnsku;
        }

        return $fnsku;
    }

    /**
     * UPDATED checkFnsku method with prefix system
     */
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
            $normalizedFnsku = $this->normalizeFnsku($fnsku);
            
            // Check availability with prefix system
            $availability = $this->checkFnskuAvailabilityWithPrefix($normalizedFnsku);

            if ($availability['available']) {
                $record = $availability['record'];
                $baseFnsku = $availability['base_fnsku'];
                
                try {
                    $fnskuInfo = $this->getNextAvailableFnsku(
                        $baseFnsku,
                        $record->ASIN,
                        $record->grading,
                        $record->storename
                    );

                    return response()->json([
                        'exists' => true,
                        'status' => 'available',
                        'message' => 'FNSKU is available',
                        'normalized_fnsku' => $normalizedFnsku,
                        'original_fnsku' => $fnsku,
                        'base_fnsku' => $baseFnsku,
                        'next_fnsku_to_use' => $fnskuInfo['actual_fnsku'],
                        'remaining_units' => $record->Units,
                        'times_used' => $fnskuInfo['times_used'],
                        'units_after_use' => $fnskuInfo['remaining_units']
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'exists' => true,
                        'status' => 'exhausted',
                        'message' => $e->getMessage(),
                        'normalized_fnsku' => $normalizedFnsku,
                        'original_fnsku' => $fnsku
                    ]);
                }
            } else {
                return response()->json([
                    'exists' => false,
                    'status' => 'not_found',
                    'message' => 'FNSKU not found or no units remaining',
                    'normalized_fnsku' => $normalizedFnsku,
                    'original_fnsku' => $fnsku
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error checking FNSKU', $e, ['fnsku' => $fnsku]);

            return response()->json([
                'exists' => false,
                'status' => 'error',
                'message' => 'Error checking FNSKU status'
            ], 500);
        }
    }

    private function getCurrentUserName()
    {
        $user = Auth::user();
        return $user ? ($user->username ?? $user->name ?? 'Unknown') : 'Unknown';
    }


public function processScan(Request $request)
{
    DB::beginTransaction();

    try {
        try {
            $validatedData = $request->validate([
                'SerialNumber' => 'required_without:FNSKU|nullable|string',
                'FNSKU' => 'required_without:SerialNumber|nullable|string',
                'Location' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->errors()),
                'reason' => 'validation_error'
            ], 422);
        }

        $User = $this->getCurrentUserName();
        $serial = trim($request->input('SerialNumber', ''));
        $location = trim($request->input('Location', ''));
        $FNSKU = trim($request->input('FNSKU', ''));

        if (!empty($FNSKU)) {
            $FNSKU = $this->normalizeFnsku($FNSKU);
            Log::info('Processing scan with normalized FNSKU', [
                'original_fnsku' => $request->input('FNSKU', ''),
                'normalized_fnsku' => $FNSKU
            ]);
        }

        if (empty($serial) && empty($FNSKU)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Either Serial Number or FNSKU must be provided',
                'reason' => 'missing_identifiers'
            ], 422);
        }

        $Module = "Stockroom";
        $Action = "Scanned and insert to Stockroom";

        try {
            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            $formatted_datetime = $currentDatetime->format('Y-m-d h:i A');
            $currentDate = date('Y-m-d', strtotime($formatted_datetime));
            $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Log::warning('Error with timezone, using default', ['error' => $e->getMessage()]);
            $currentDatetime = new DateTime();
            $formatted_datetime = $currentDatetime->format('Y-m-d h:i A');
            $currentDate = date('Y-m-d');
            $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
        }

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

        if (!empty($FNSKU) && preg_match('/^L\d{3}[A-G]$/i', $FNSKU)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid FNSKU - appears to be a location code',
                'reason' => 'invalid_fnsku'
            ]);
        }

        if (!preg_match('/^L\d{3}[A-G]$/i', $location) && $location !== 'Floor' && $location !== 'L800G') {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid Location Format',
                'reason' => 'invalid_location'
            ]);
        }

        $modulelocation = (substr($location, 0, 4) === 'L800') ? 'Production Area' : 'Stockroom';

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
            // Handle existing item in Stockroom/Production Area
            $id = $existingItem->ProductID;
            $rt = $existingItem->rtcounter;

            if ($existingItem->ProductModuleLoc === 'Production Area') {
                if ($modulelocation === 'Production Area') {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Data Already in Production Area',
                        'reason' => 'Duplicate Data not allowed'
                    ]);
                } else {
                    // Moving from Production to Stockroom - requires FNSKU
                    $fnskuAvailability = $this->checkFnskuAvailabilityWithPrefix($FNSKU);

                    if (!$fnskuAvailability['available']) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'FNSKU not found or not available',
                            'reason' => 'fnsku_not_found'
                        ]);
                    }

                    $baseFnsku = $fnskuAvailability['base_fnsku'];
                    $fnskuRecord = $fnskuAvailability['record'];

                    try {
                        $fnskuInfo = $this->getNextAvailableFnsku(
                            $baseFnsku, 
                            $fnskuRecord->ASIN, 
                            $fnskuRecord->grading, 
                            $fnskuRecord->storename
                        );
                    } catch (\Exception $e) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => $e->getMessage(),
                            'reason' => 'fnsku_exhausted'
                        ]);
                    }

                    $actualFnskuToUse = $fnskuInfo['actual_fnsku'];

                    DB::table($this->productTable)
                        ->where('ProductID', $id)
                        ->update([
                            'ProductModuleLoc' => $modulelocation,
                            'warehouselocation' => $location,
                            'FNSKUviewer' => $actualFnskuToUse,
                            'validation_status' => 'validated',
                            'stockroom_insert_date' => $curentDatetimeString
                        ]);

                    $this->updateFnskuUnits(
                        $baseFnsku, 
                        $fnskuRecord->ASIN, 
                        $fnskuRecord->grading, 
                        $fnskuRecord->storename
                    );

                    DB::table($this->itemProcessHistoryTable)->insert([
                        'rtcounter' => $rt,
                        'employeeName' => $User,
                        'editDate' => $curentDatetimeString,
                        'Module' => "Stockroom",
                        'Action' => "Scanned and insert to {$modulelocation}"
                    ]);

                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => "Scanned and insert to {$modulelocation}",
                        'fnsku_used' => $actualFnskuToUse
                    ]);
                }
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate Data in Stockroom',
                    'reason' => 'duplicate_data'
                ]);
            }
        } else {
            // Check if item exists in Validation (regardless of validation status)
            $existingInValidation = DB::table($this->productTable)
                ->where(function ($query) use ($serial) {
                    $query->where('serialnumber', $serial)
                        ->orWhere('serialnumberb', $serial);
                })
                ->where('returnstatus', 'Not Returned')
                ->where('ProductModuleLoc', 'Validation')
                ->first();

            if ($existingInValidation) {
                // Check if item is validated
                if ($existingInValidation->validation_status !== 'validated') {
                    // Item exists but is NOT validated
                    DB::rollBack();
                    Log::warning('Attempted to scan non-validated item from Validation', [
                        'productId' => $existingInValidation->ProductID,
                        'rtcounter' => $existingInValidation->rtcounter,
                        'serial' => $serial,
                        'validation_status' => $existingInValidation->validation_status
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Item not validated yet. Please complete validation first.',
                        'reason' => 'not_validated'
                    ]);
                }

                // Item is validated - proceed with moving to stockroom
                $id = $existingInValidation->ProductID;
                $rtnumberofitem = $existingInValidation->rtcounter;
                $checkFNSKUviewer = $existingInValidation->FNSKUviewer;

                // Validated items MUST have an FNSKU
                if (empty($checkFNSKUviewer)) {
                    DB::rollBack();
                    Log::warning('Validated item missing FNSKU', [
                        'productId' => $id,
                        'rtcounter' => $rtnumberofitem,
                        'serial' => $serial
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Item has incomplete data - Missing FNSKU. Please complete validation first.',
                        'reason' => 'incomplete_validation_data'
                    ]);
                }

                // Use existing FNSKU from validation - preserve it exactly
                $actualFnskuToUse = $checkFNSKUviewer;
                $needReprint = false;

                Log::info('Moving validated item from Validation to Stockroom', [
                    'productId' => $id,
                    'rtcounter' => $rtnumberofitem,
                    'existing_fnsku' => $actualFnskuToUse,
                    'scanned_location' => $location
                ]);
                
                // Optional: Check if scanned FNSKU matches
                if (!empty($FNSKU)) {
                    $normalizedScanned = $this->normalizeFnsku($FNSKU);
                    $normalizedExisting = $this->normalizeFnsku($actualFnskuToUse);
                    
                    if (trim($normalizedScanned) != trim($normalizedExisting)) {
                        Log::warning('Scanned FNSKU differs from existing', [
                            'scanned' => $FNSKU,
                            'existing' => $actualFnskuToUse,
                            'rtcounter' => $rtnumberofitem
                        ]);
                        $needReprint = true;
                    }
                }

                // Update the product location without changing FNSKU
                DB::table($this->productTable)
                    ->where('ProductID', $id)
                    ->update([
                        'ProductModuleLoc' => $modulelocation,
                        'warehouselocation' => $location,
                        'FNSKUviewer' => $actualFnskuToUse,
                        'stockroom_insert_date' => $curentDatetimeString
                    ]);

                // Log the history
                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $rtnumberofitem,
                    'employeeName' => $User,
                    'editDate' => $curentDatetimeString,
                    'Module' => "Stockroom",
                    'Action' => "Scanned and insert to {$modulelocation}"
                ]);

                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => "Scanned and Forwarded to {$modulelocation} Successfully",
                    'needReprint' => $needReprint,
                    'productId' => $needReprint ? $id : null,
                    'fnsku_used' => $actualFnskuToUse,
                    'fnsku_preserved' => true
                ]);
            } else {
                // Create new entry - requires FNSKU
                $fnskuAvailability = $this->checkFnskuAvailabilityWithPrefix($FNSKU);

                if (!$fnskuAvailability['available']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'FNSKU not found or not available: ' . $FNSKU,
                        'reason' => 'fnsku_not_found'
                    ]);
                }

                $baseFnsku = $fnskuAvailability['base_fnsku'];
                $fnskuRecord = $fnskuAvailability['record'];

                try {
                    $fnskuInfo = $this->getNextAvailableFnsku(
                        $baseFnsku, 
                        $fnskuRecord->ASIN, 
                        $fnskuRecord->grading, 
                        $fnskuRecord->storename
                    );
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'reason' => 'fnsku_exhausted'
                    ]);
                }

                $actualFnskuToUse = $fnskuInfo['actual_fnsku'];

                $maxxrt = DB::table($this->productTable)->max('rtcounter');
                $newrt = $maxxrt + 1;

                $newItemId = DB::table($this->productTable)->insertGetId([
                    'rtcounter' => $newrt,
                    'serialnumber' => $serial,
                    'ProductModuleLoc' => $modulelocation,
                    'warehouselocation' => $location,
                    'FNSKUviewer' => $actualFnskuToUse,
                    'FbmAvailable' => 1,
                    'Fulfilledby' => 'FBM',
                    'validation_status' => 'validated',
                    'quantity' => 1,
                    'stockroom_insert_date' => $curentDatetimeString,
                ]);

                $this->updateFnskuUnits(
                    $baseFnsku, 
                    $fnskuRecord->ASIN, 
                    $fnskuRecord->grading, 
                    $fnskuRecord->storename
                );

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
                    'message' => "Scanned and Inserted Successfully",
                    'fnsku_used' => $actualFnskuToUse,
                    'remaining_units' => $fnskuInfo['remaining_units']
                ]);
            }
        }
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Unhandled error in processScan', $e);

        return response()->json([
            'success' => false,
            'message' => 'Error processing scan: ' . $e->getMessage(),
            'reason' => 'server_error'
        ], 500);
    }
}

    // Continue with other methods (mergeItems, updateLocation, etc.) - they remain the same
    // but I'll include them for completeness

    public function mergeItems(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'title' => 'sometimes|string',
            'productId' => 'sometimes|integer',
            'asin' => 'sometimes|string',
            'store' => 'sometimes|string',
            'serialNumbers' => 'sometimes|array',
            'fnsku' => 'sometimes|string'
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
            DB::beginTransaction();

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

            $title = $request->title ?? '';
            $productAsin = $request->asin ?? '';
            $firstStore = $request->store ?? '';
            $providedFnsku = $request->fnsku ?? '';

            if (!empty($providedFnsku)) {
                $providedFnsku = $this->normalizeFnsku($providedFnsku);
                Log::info('Merge items with normalized FNSKU', [
                    'original_fnsku' => $request->fnsku ?? '',
                    'normalized_fnsku' => $providedFnsku
                ]);
            }

            foreach ($serialNumberResults as $row) {
                $serialNumber = $row->serialnumber;
                $price = $row->price ?? 0;

                if (empty($title) && $index === 0) {
                    $title = $row->AStitle ?? '';
                    $firstStore = $row->StoreName ?? '';
                }

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

            preg_match('/\((.*?)\)/', $title, $matches);
            $color = isset($matches[1]) ? $matches[1] : '';

            $baseTitle = trim(preg_replace('/\s*\(.*?\)\s*/', '', $title));
            $baseTitle = trim(preg_replace('/\s+\d+-Pack\s*/', ' ', $baseTitle));

            $exactTitlePattern = $baseTitle;
            if ($numOfSerial > 1) {
                $exactTitlePattern .= ' ' . $numOfSerial . '-Pack';
            }
            $exactTitlePattern .= ' (' . $color . ')';

            $exactTitlePatternForLike = '%' . $exactTitlePattern . '%';
            $baseTitleForLike = '%' . $baseTitle . '%';
            $colorForLike = '%(' . $color . ')%';
            $packTextForLike = $numOfSerial > 1 ? '%' . $numOfSerial . '-Pack%' : '';

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

            $asinResult = null;

            $asinResult = DB::table($this->asinTable)
                ->where('internal', 'like', $exactTitlePatternForLike)
                ->first();

            if ($asinResult) {
                Log::info('Found ASIN by exact title pattern', [
                    'ASIN' => $asinResult->ASIN,
                    'internal' => $asinResult->internal
                ]);
            }

            if (!$asinResult && $numOfSerial > 1) {
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

            if (!$asinResult) {
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

            $getfnsku = null;
            $getFNSKUID = null;

            // MODIFIED: Use the prefix system for merge as well
            $fnskuResult = DB::table($this->fnskuTable)
                ->where('ASIN', $getAsin)
                ->where('fnsku_status', 'available')
                ->where('Units', '>', 0)
                ->first();

            if (!$fnskuResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available FNSKU found for ASIN: ' . $getAsin
                ]);
            }

            // Get the next available FNSKU with prefix for merge
            try {
                $fnskuInfo = $this->getNextAvailableFnsku(
                    $fnskuResult->FNSKU,
                    $fnskuResult->ASIN,
                    $fnskuResult->grading,
                    $fnskuResult->storename
                );
                $getfnsku = $fnskuInfo['actual_fnsku']; // This will be the prefixed FNSKU
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error getting FNSKU for merge: ' . $e->getMessage()
                ]);
            }

            $getFNSKUID = $fnskuResult->FNSKUID;

            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            $currentDate = $currentDatetime->format('Y-m-d');
            $currentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');

            $mergeId = DB::table('tblmigrateditem')->insertGetId([
                'migratedDate' => $currentDate
            ]);

            $maxRt = DB::table($this->productTable)->max('rtcounter') ?? 0;
            $newRt = $maxRt + 1;

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
                'FNSKUviewer' => $getfnsku // This will be the prefixed FNSKU
            ];

            $productId = DB::table($this->productTable)->insertGetId($productData);

            // Update FNSKU units using the new system
            $becameUnavailable = $this->updateFnskuUnits(
                $fnskuResult->FNSKU,
                $fnskuResult->ASIN,
                $fnskuResult->grading,
                $fnskuResult->storename
            );

            Log::info('Updated FNSKU Units count for merge', [
                'FNSKU' => $fnskuResult->FNSKU,
                'actual_fnsku_used' => $getfnsku,
                'became_unavailable' => $becameUnavailable
            ]);

            DB::table($this->productTable)
                ->whereIn('ProductID', $selectedIds)
                ->update([
                    'ProductModuleLoc' => 'Merged',
                    'mergedTO' => $newRt
                ]);

            if (!empty($providedFnsku)) {
                // Return units to the provided FNSKU if it was different
                $baseFnskuProvided = $this->extractBaseFnsku($providedFnsku);
                if ($baseFnskuProvided !== $fnskuResult->FNSKU) {
                    $fnskuResult1 = DB::table($this->fnskuTable)
                        ->where('FNSKU', $baseFnskuProvided)
                        ->first();

                    if ($fnskuResult1) {
                        $oldunit = $fnskuResult1->Units ?? 0;
                        $returnOldUNIT = $oldunit + $numOfSerial;

                        DB::table($this->fnskuTable)
                            ->where('FNSKU', $baseFnskuProvided)
                            ->update([
                                'fnsku_status' => 'available',
                                'Units' => $returnOldUNIT
                            ]);
                    }
                }
            }

            DB::commit();

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
                'units' => $fnskuInfo['remaining_units']
            ]);

        } catch (\Exception $e) {
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
        $validated = $request->validate([
            'itemIds' => 'required_without:itemId|array',
            'itemId' => 'required_without:itemIds|integer',
            'newLocation' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

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

            DB::table($this->productTable)
                ->whereIn('ProductID', $idsToUpdate)
                ->update([
                    'warehouselocation' => $request->newLocation
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully.',
                'count' => count($idsToUpdate)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error updating location: ' . $e->getMessage()
            ]);
        }
    }

    public function PostItemstoAmazon(Request $request)
    {
        $selectedItems = $request->input('selectedItems', []);
        $marketplace = $request->input('marketplace');
        $fulfillmentChannel = $request->input('fulfillmentChannel');
        $currency = $request->input('currency');
        $price = $request->input('price');

        if (empty($selectedItems)) {
            return response()->json([
                'success' => false,
                'message' => 'No items selected.'
            ]);
        }

        $products = DB::table('tblproduct')
            ->whereIn('ProductID', $selectedItems)
            ->get();

        $alreadyPosted = [];
        $readyToPost = [];
        $invalid = [];

        foreach ($products as $product) {
            if (trim($product->amzn_status) === 'POSTED') {
                $alreadyPosted[] = $product->ProductID;
                continue;
            }

            // MODIFIED: Extract base FNSKU before checking
            $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
            
            $fnsku = DB::table('tblfnsku')
                ->where('FNSKU', $baseFnsku) // Use base FNSKU for lookup
                ->first();

            if (!$fnsku) {
                $invalid[] = $product->ProductID;
                continue;
            }

            if ($fnsku->amazon_status === 'Not Existed') {
                $readyToPost[] = $product;
            } else {
                $alreadyPosted[] = $product->ProductID;
            }
        }

        $this->mskuPostToAmazon($readyToPost, $marketplace, $fulfillmentChannel, $currency, $price);

        return response()->json([
            'success' => true,
            'message' => 'Check complete.',
            'ready_to_post' => $readyToPost,
            'already_posted' => $alreadyPosted,
            'invalid' => $invalid
        ]);
    }

    private function mskuPostToAmazon(array $items = [], $marketplace, $fulfillmentChannel, $currency, $price)
    {
        require_once base_path('automations/bulk_msku_creation.php');

        if (empty($items)) {
            echo "No items to post.<br>";
            return;
        }

        $grouped = [];
        foreach ($items as $product) {
            // MODIFIED: Extract base FNSKU before lookup
            $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
            
            $fnsku = DB::table('tblfnsku')
                ->where('FNSKU', $baseFnsku) // Use base FNSKU for lookup
                ->first();

            if (!$fnsku) {
                continue;
            }

            $msku = $fnsku->MSKU;
            if (!isset($grouped[$msku])) {
                $grouped[$msku] = [
                    'msku' => $msku,
                    'asin' => $fnsku->ASIN,
                    'storename' => $fnsku->storename,
                    'grading' => $fnsku->grading,
                    'condition' => strtolower(str_replace(' ', '_', $fnsku->Condition ?? 'new_new')),
                    'count' => 0
                ];
            }

            $grouped[$msku]['count']++;
        }

        // Rest of the method remains the same...
        foreach ($grouped as $msku => &$data) {
            $alreadyCount = DB::table('tblproduct')
                ->where('FNSKUviewer', $msku)
                ->where('amzn_status', 'POSTED')
                ->count();
            $data['count'] += $alreadyCount;
        }
        unset($data);

        if (empty($grouped)) {
            echo "No valid MSKUs found.<br>";
            return;
        }

        $first = reset($grouped);
        $tblstore = sheesh_fetchtblstores($first['storename']);

        $messageId = 1;
        $feedItems = [];
        $productTypeCache = [];

        foreach ($grouped as $msku => $data) {
            $asinKey = $data['asin'];
            $amzncondition = normalize_db_condition($data['grading']);

            $listing_restrict = fetch_listing_retrict($data['storename'], $data['asin']);
            if ($listing_restrict['status'] == '200') {
                foreach ($listing_restrict['data']['restrictions'] ?? [] as $r) {
                    if ($r['conditionType'] === $amzncondition) {
                        $reason = $r['reasons'][0]['reasonCode'] ?? null;
                        if ($reason === 'NOT_ELIGIBLE') {
                            create_notification([
                                'module' => 'Stockroom',
                                'title' => "Amazon Posting: Blocked {$data['asin']}",
                                'subtitle' => $amzncondition,
                                'content' => $r['reasons'][0]['message'] ?? 'Blocked by Amazon',
                                'severity' => 'action_required',
                                'user_ids' => [session('userid')]
                            ]);

                            DB::table('tblfnsku')
                                ->where('ASIN', $data['asin'])
                                ->where('storename', $data['storename'])
                                ->where('grading', $data['grading'])
                                ->update(['amazon_status' => 'Blocked']);

                            continue 2;
                        }
                    }
                }
            }

            $productType = null;
            if (!isset($productTypeCache[$asinKey])) {
                $response = Http::get(url('/amzn/catalog/get_asin_catalog'), [
                    'searchedAsin' => $asinKey,
                    'store' => $data['storename'],
                    'destinationMarketplace' => $marketplace
                ]);

                $productTypeCache[$asinKey] = 'generic';
                if ($response->successful()) {
                    $result = $response->json();
                    $productTypeCache[$asinKey] = $result['results'][0]['rates']['productTypes'][0]['productType'] ?? 'generic';
                }
            }

            $productType = $productTypeCache[$asinKey];

            $feedItems[] = [
                "messageId" => $messageId++,
                "operationType" => "UPDATE",
                "sku" => $data['msku'],
                "productType" => $productType,
                "requirements" => "LISTING_OFFER_ONLY",
                "attributes" => [
                    "condition_type" => [
                        [
                            "value" => $amzncondition,
                            "marketplace_id" => $marketplace,
                        ]
                    ],
                    "fulfillment_availability" => [
                        [
                            "fulfillment_channel_code" => $fulfillmentChannel,
                            "marketplace_id" => $marketplace,
                            "quantity" => $data['count']
                        ]
                    ],
                    "merchant_suggested_asin" => [
                        [
                            "value" => $data['asin'],
                            "marketplace_id" => $marketplace
                        ]
                    ],
                    "list_price" => [
                        [
                            "currency" => $currency,
                            "value" => 0,
                            "marketplace_id" => $marketplace
                        ]
                    ],
                    "purchasable_offer" => [
                        [
                            "currency" => $currency,
                            "audience" => "ALL",
                            "our_price" => [
                                [
                                    "schedule" => [
                                        [
                                            "value_with_tax" => (float) $price
                                        ]
                                    ]
                                ]
                            ],
                            "marketplace_id" => $marketplace
                        ]
                    ],
                ]
            ];
        }

        $createdocumentid_data = Create_feed_document_passing_json($first['storename'], null);
        $feeddocumentid = $createdocumentid_data['data']['feedDocumentId'];

        $payload = [
            'header' => [
                'version' => '2.0',
                'feedType' => 'JSON_LISTINGS_FEED',
                'marketplaceIds' => [$marketplace],
                'sellerId' => $tblstore['MerchantID'],
            ],
            'messages' => $feedItems
        ];

        echo "<pre>";
        print_r($payload);
        echo "</pre>";

        $feedDataJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $uploadSuccess = upload_feed_to_amazon_s3($createdocumentid_data['data']['url'], $feedDataJson);

        echo "Rawr $uploadSuccess";

        $payload = [
            "feedType" => "JSON_LISTINGS_FEED",
            "marketplaceIds" => [$marketplace],
            "inputFeedDocumentId" => $feeddocumentid
        ];

        if ($uploadSuccess) {
            $feedId = create_feed_from_document($first['storename'], $feeddocumentid, $payload);
            if ($feedId) {
                insert_created_feed(
                    $feedId,
                    'JSON_LISTINGS_FEED',
                    $feeddocumentid,
                    $first['storename']
                );
            }
        }
    }

    /**
     * Get count of new scanned items for today (US timezone)
     * MODIFIED to handle prefixed FNSKUs in joins
     */
 public function getNewScannedCount(Request $request)
{
    try {
        $timezone = new DateTimeZone('America/Los_Angeles');
        $today = $request->input('date');
        
        if (!$today) {
            $today = (new DateTime('now', $timezone))->format('Y-m-d');
        }
        
        Log::info('🔍 Fetching count for date: ' . $today);
        
        // Don't use cache for fresh scans
        $cacheKey = 'new_scanned_count_' . $today;
        Cache::forget($cacheKey); // Clear any existing cache
        
        $count = DB::table($this->productTable)
            ->where('ProductModuleLoc', 'Stockroom')
            ->whereDate('stockroom_insert_date', $today)
            ->whereNotNull('stockroom_insert_date')
            ->count();

        Log::info('✅ Count result: ' . $count . ' for date: ' . $today);

        // Cache for only 10 seconds to allow quick refresh
        Cache::put($cacheKey, $count, 10);

        return response()->json([
            'success' => true,
            'count' => $count,
            'date' => $today,
            'timezone' => 'America/Los_Angeles'
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Error fetching count: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error fetching count: ' . $e->getMessage(),
            'count' => 0
        ], 500);
    }
}

    /**
     * Get list of new scanned items with date filtering (US timezone)
     * SIMPLIFIED to avoid complex MySQL regex functions
     */
    public function getNewScannedItems(Request $request)
    {
        try {
            $timezone = new DateTimeZone('America/New_York');
            
            $startDate = $request->input('startDate');
            $endDate = $request->input('endDate');
            
            if (empty($startDate) && empty($endDate)) {
                $today = new DateTime('now', $timezone);
                $fourDaysAgo = new DateTime('now', $timezone);
                $fourDaysAgo->modify('-4 days');
                
                $endDate = $today->format('Y-m-d');
                $startDate = $fourDaysAgo->format('Y-m-d');
            }

            // SIMPLIFIED: Get products first, then match FNSKUs in PHP
            $items = DB::table($this->productTable . ' as prod')
                ->select([
                    'prod.ProductID',
                    'prod.rtcounter',
                    'prod.warehouselocation',
                    'prod.stockroom_insert_date',
                    'prod.FNSKUviewer',
                    'prod.amzn_status',
                    'prod.shipment_tracking_number',
                    'hist.employeeName'
                ])
                ->join($this->itemProcessHistoryTable . ' as hist', 'prod.rtcounter', '=', 'hist.rtcounter')
                ->where(function($query) {
                    $query->where('hist.Action', 'Scanned and insert to Stockroom')
                          ->orWhere('hist.Action', 'Move Item to Stockroom');
                })
                ->where('prod.ProductModuleLoc', 'Stockroom')
                ->whereBetween(DB::raw('DATE(prod.stockroom_insert_date)'), [$startDate, $endDate])
                ->orderBy('prod.stockroom_insert_date', 'desc')
                ->get();

            // Process items to add FNSKU data
            $processedItems = $items->map(function($item) {
                $baseFnsku = $this->extractBaseFnsku($item->FNSKUviewer);
                
                // Get FNSKU record
                $fnskuRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();

                if ($fnskuRecord) {
                    $item->MSKUviewer = $fnskuRecord->MSKU;
                    $item->gradingviewer = $fnskuRecord->grading;
                    $item->StoreName = $fnskuRecord->storename;
                    
                    // Get ASIN record
                    $asinRecord = DB::table($this->asinTable)
                        ->where('ASIN', $fnskuRecord->ASIN)
                        ->first();
                    
                    if ($asinRecord) {
                        $item->ASINviewer = $asinRecord->ASIN;
                        $item->AStitle = $asinRecord->internal;
                    } else {
                        $item->ASINviewer = $fnskuRecord->ASIN;
                        $item->AStitle = '';
                    }
                } else {
                    // Set default values if FNSKU not found
                    $item->MSKUviewer = '';
                    $item->gradingviewer = '';
                    $item->StoreName = '';
                    $item->ASINviewer = '';
                    $item->AStitle = '';
                }
                
                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $processedItems,
                'count' => $processedItems->count(),
                'startDate' => $startDate,
                'endDate' => $endDate
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching new scanned items: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update FBM list status for new scanned items
     */
    public function updateFbmStatus(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer',
                'status' => 'nullable|string'
            ]);

            $id = $validated['id'];
            $status = $validated['status'] === 'listed' ? 'listed' : null;

            DB::table($this->productTable)
                ->where('ProductID', $id)
                ->update(['fbm_list_status' => $status]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'id' => $id,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating FBM status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print label method - MODIFIED to handle prefixed FNSKUs
     */
    public function printLabel($productId)
    {
        try {
            $response = axios()->post("/api/stockroom/print-label", [
                'productId' => $productId,
            ], [
                'withCredentials' => true,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-CSRF-TOKEN' => csrf_token(),
                ],
            ]);

            if ($response['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => 'Label printing started.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error: ' . $response['message']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error printing label: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to print label. Please try again.'
            ]);
        }
    }

    /**
     * Process items method - handles the processing of selected items
     */
    public function processItems(Request $request)
    {
        $validated = $request->validate([
            'shipmentType' => 'required|string',
            'trackingNumber' => 'required|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1'
        ]);

        try {
            DB::beginTransaction();

            $shipmentType = $validated['shipmentType'];
            $trackingNumber = $validated['trackingNumber'];
            $notes = $validated['notes'] ?? '';
            $selectedItems = $validated['items'];

            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            $currentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
            $user = $this->getCurrentUserName();

            // Update selected items
            DB::table($this->productTable)
                ->whereIn('ProductID', $selectedItems)
                ->update([
                    'shipment_type' => $shipmentType,
                    'shipment_tracking_number' => $trackingNumber,
                    'shipment_notes' => $notes,
                    'shipment_processed_date' => $currentDatetimeString,
                    'processed_by' => $user
                ]);

            // Log the processing in history
            $productInfo = DB::table($this->productTable)
                ->whereIn('ProductID', $selectedItems)
                ->get();

            foreach ($productInfo as $product) {
                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $product->rtcounter,
                    'employeeName' => $user,
                    'editDate' => $currentDatetimeString,
                    'Module' => 'Stockroom Processing',
                    'Action' => "Processed - {$shipmentType}"
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully processed {$productInfo->count()} items",
                'processed_count' => $productInfo->count()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing items: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing items: ' . $e->getMessage()
            ], 500);
        }
    }

}