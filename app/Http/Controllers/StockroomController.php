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
    try {
        // ✅ Lock FNSKU record
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->lockForUpdate()
            ->first();

        if (!$fnskuRecord) {
            Log::warning("FNSKU not found in database", [
                'base_fnsku' => $baseFnsku,
                'asin' => $asin,
                'grading' => $grading,
                'storename' => $storename
            ]);
            
            return [
                'actual_fnsku' => $baseFnsku,
                'times_used' => 0,
                'remaining_units' => 0
            ];
        }

        $currentUnits = $fnskuRecord->Units;

        if ($currentUnits <= 0) {
            throw new \Exception("No remaining units for FNSKU: {$baseFnsku}");
        }

        // ✅ Get ALL active FNSKUs (with and without prefix)
        $activeFnskus = DB::table($this->productTable)
            ->select('FNSKUviewer')
            ->where(function($query) use ($baseFnsku) {
                $query->where('FNSKUviewer', $baseFnsku)
                      ->orWhere('FNSKUviewer', 'LIKE', 'C%' . $baseFnsku);
            })
            ->whereNotIn('ProductModuleLoc', ['Shipment', 'Soldlist', 'Returnlist', 'Merged', 'RTS'])
            ->lockForUpdate()
            ->pluck('FNSKUviewer')
            ->toArray();

        Log::info("Active FNSKUs found", [
            'base_fnsku' => $baseFnsku,
            'active_fnskus' => $activeFnskus,
            'total_units' => $currentUnits
        ]);

        // ✅ Extract used prefixes
        $usedPrefixes = [];
        
        foreach ($activeFnskus as $fnsku) {
            if ($fnsku === $baseFnsku) {
                // Base FNSKU (no prefix) is used
                $usedPrefixes[] = 0;
            } elseif (preg_match('/^C(\d+)' . preg_quote($baseFnsku, '/') . '$/', $fnsku, $matches)) {
                // Extract prefix number (e.g., "C3" -> 3)
                $usedPrefixes[] = (int)$matches[1];
            }
        }

        sort($usedPrefixes);

        Log::info("Used prefixes", [
            'base_fnsku' => $baseFnsku,
            'used_prefixes' => $usedPrefixes,
            'max_allowed' => $currentUnits - 1
        ]);

        // ✅ Find first UNUSED prefix
        $nextPrefix = null;
        $maxPrefix = $currentUnits - 1; // If Units = 7, max prefix is C6 (0-6 = 7 total)

        for ($i = 0; $i <= $maxPrefix; $i++) {
            if (!in_array($i, $usedPrefixes)) {
                $nextPrefix = $i;
                break;
            }
        }

        if ($nextPrefix === null) {
            // All prefixes are used
            throw new \Exception("All available prefixes exhausted for FNSKU: {$baseFnsku} (Units: {$currentUnits})");
        }

        // ✅ Generate FNSKU with correct prefix
        if ($nextPrefix === 0) {
            $actualFnsku = $baseFnsku; // No prefix
        } else {
            $actualFnsku = "C{$nextPrefix}{$baseFnsku}";
        }

        Log::info("Generated FNSKU with first available prefix", [
            'base_fnsku' => $baseFnsku,
            'used_prefixes' => $usedPrefixes,
            'next_prefix' => $nextPrefix,
            'actual_fnsku' => $actualFnsku,
            'remaining_units' => $currentUnits
        ]);

        return [
            'actual_fnsku' => $actualFnsku,
            'times_used' => count($usedPrefixes),
            'remaining_units' => $currentUnits,
            'next_prefix' => $nextPrefix
        ];

    } catch (\Exception $e) {
        Log::error("Error in getNextAvailableFnsku: " . $e->getMessage(), [
            'base_fnsku' => $baseFnsku,
            'trace' => $e->getTraceAsString()
        ]);

        throw $e;
    }
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

        // NEW: Check if this is a forced refresh (skip cache)
        $forceFresh = $request->has('_t');

        $cacheKey = "stockroom_inventory_{$page}_{$perPage}_{$store}_" . md5($search);

        // Only use cache if NOT forced fresh AND search is empty
        if (!$forceFresh && empty($search)) {
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                Log::info("Returning cached inventory data");
                return response()->json($cachedResult);
            }
        }

        if ($forceFresh) {
            Log::info("Force fresh request - bypassing cache");
        }

        // Get products first
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
                'prod.Reserved',
                'prod.mergeID',  
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

        // Get ASIN data with QuantityInside
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

        // Modified query to include QuantityInside
        $asinQuery = DB::table($this->asinTable . ' as asin')
            ->select([
                'asin.ASIN',
                'asin.internal as AStitle',
                'asin.system_title',
                'asin.asinStatus',
                'asin.QuantityInside'  // Include QuantityInside column
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
                        ->orWhere('asin.system_title', 'like', "%{$search}%")   
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
            $item->system_title = $asin->system_title;
            $item->asinStatus = $asin->asinStatus;
            $item->storename = $asinFnskus->first()->storename ?? '';
            
            // Aggregate inventory numbers
            $item->FBMAvailable = $asinProducts->sum('FBMAvailable');
            $item->FbaAvailable = $asinProducts->sum('FbaAvailable');
            $item->Outbound = $asinProducts->sum('Outbound');
            $item->Inbound = $asinProducts->sum('Inbound');
            $item->Unfulfillable = $asinProducts->sum('Unfulfillable');
            $item->Reserved = $asinProducts->sum('Reserved');

            // Calculate quantity based on QuantityInside
            $quantityInside = $asin->QuantityInside ?? 1; // Default to 1 if NULL
            $quantityInside = max(1, min(4, (int)$quantityInside)); // Ensure it's between 1-4
            
            $unitCount = $asinProducts->count(); // Number of units in stockroom
            $item->item_count = $unitCount * $quantityInside; // Total quantity
            $item->unit_count = $unitCount; // Keep track of actual units
            $item->quantity_inside = $quantityInside; // Store the QuantityInside value

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
                    'grading' => $fnskuRecord->grading ?? '',
                    'storename' => $fnskuRecord->storename ?? '',
                    'mergeID' => $product->mergeID ?? null,
                ];
            })->toArray();

            $item->pack_size = $quantityInside;
            $item->box_count = $unitCount;

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

        // Only cache if NOT forced fresh and search is empty
        if (!$forceFresh && empty($search)) {
            Cache::put($cacheKey, $result, 30);
            Log::info("Cached inventory data");
        }

        // Add no-cache headers for forced fresh requests
        if ($forceFresh) {
            return response()->json($result)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
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
                'SerialNumber' => 'required|string',
                'Location' => 'required|string',
                'FNSKU' => 'nullable|string',  // FNSKU is optional
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
        $scannedFNSKU = trim($request->input('FNSKU', ''));

        if (!empty($scannedFNSKU)) {
            $scannedFNSKU = $this->normalizeFnsku($scannedFNSKU);
        }

        $Module = "Stockroom";
        $Action = "Scanned and insert to Stockroom";

        $california_timezone = new DateTimeZone('America/Los_Angeles');
        $currentDatetime = new DateTime('now', $california_timezone);
        $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');

        // Validate serial format
        if (!preg_match('/^[a-zA-Z0-9]+$/', $serial) || strpos($serial, 'X00') !== false) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid Serial Number',
                'reason' => 'invalid_serial'
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

        $modulelocation = (substr($location, 0, 4) === 'L800') ? 'Production Area' : 'Stockroom';

        // ============================================
        // CHECK 1: Does item exist in Stockroom/Production Area?
        // ============================================
        $existingItem = DB::table($this->productTable)
            ->where(function ($query) use ($serial) {
                $query->where('serialnumber', $serial)
                    ->orWhere('serialnumberb', $serial)
                    ->orWhere('serialnumberc', $serial)
                    ->orWhere('serialnumberd', $serial);
            })
            ->where(function ($query) {
                $query->where('ProductModuleLoc', 'Stockroom')
                    ->orWhere('ProductModuleLoc', 'Production Area');
            })
            ->first();

        if ($existingItem) {
            // Item already in Stockroom/Production - just update location
            $id = $existingItem->ProductID;
            $rt = $existingItem->rtcounter;

            if ($existingItem->ProductModuleLoc === 'Production Area' && $modulelocation === 'Production Area') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data Already in Production Area',
                    'reason' => 'duplicate_data'
                ]);
            }

            if ($existingItem->ProductModuleLoc === 'Stockroom' && $modulelocation === 'Stockroom') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate Data in Stockroom',
                    'reason' => 'duplicate_data'
                ]);
            }

            // Moving between Production Area and Stockroom
            DB::table($this->productTable)
                ->where('ProductID', $id)
                ->update([
                    'ProductModuleLoc' => $modulelocation,
                    'warehouselocation' => $location,
                    'stockroom_insert_date' => $curentDatetimeString
                ]);

            DB::table($this->itemProcessHistoryTable)->insert([
                'rtcounter' => $rt,
                'employeeName' => $User,
                'editDate' => $curentDatetimeString,
                'Module' => "Stockroom",
                'Action' => "Updated location to {$location}"
            ]);

            DB::commit();
            $this->clearStockroomCaches();
            
            return response()->json([
                'success' => true,
                'message' => "Location updated successfully"
            ]);
        }

        // ============================================
        // CHECK 2: Does item exist in Validation?
        // ============================================
        $existingInValidation = DB::table($this->productTable)
            ->where(function ($query) use ($serial) {
                $query->where('serialnumber', $serial)
                    ->orWhere('serialnumberb', $serial)
                    ->orWhere('serialnumberc', $serial)
                    ->orWhere('serialnumberd', $serial);
            })
            ->where('returnstatus', 'Not Returned')
            ->where('ProductModuleLoc', 'Validation')
            ->first();

        if ($existingInValidation) {
            // ✅ Item exists in Validation
            
            // Check if validated
            if ($existingInValidation->validation_status !== 'validated') {
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

            // ✅ Validated item - move to stockroom
            // ✅ DON'T check FNSKU availability (already has FNSKU from validation)
            // ✅ DON'T deduct units (already deducted during validation)
            
            $id = $existingInValidation->ProductID;
            $rtnumberofitem = $existingInValidation->rtcounter;
            $existingFNSKU = $existingInValidation->FNSKUviewer;

            // Item MUST have FNSKU from validation
            if (empty($existingFNSKU)) {
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

            Log::info('✅ Moving validated item from Validation to Stockroom', [
                'productId' => $id,
                'rtcounter' => $rtnumberofitem,
                'existing_fnsku' => $existingFNSKU,
                'scanned_location' => $location,
                'no_fnsku_check' => true,
                'reason' => 'Item already in system with FNSKU assigned'
            ]);

            // Just move the item - preserve existing FNSKU
            DB::table($this->productTable)
                ->where('ProductID', $id)
                ->update([
                    'ProductModuleLoc' => $modulelocation,
                    'warehouselocation' => $location,
                    'stockroom_insert_date' => $curentDatetimeString
                ]);

            DB::table($this->itemProcessHistoryTable)->insert([
                'rtcounter' => $rtnumberofitem,
                'employeeName' => $User,
                'editDate' => $curentDatetimeString,
                'Module' => "Stockroom",
                'Action' => "Scanned and insert to {$modulelocation}"
            ]);

            DB::commit();
            $this->clearStockroomCaches();
            
            return response()->json([
                'success' => true,
                'message' => "Scanned and Forwarded to {$modulelocation} Successfully",
                'fnsku_preserved' => true,
                'existing_fnsku' => $existingFNSKU
            ]);
        }

        // ============================================
        // CHECK 3: Brand New Item (Floating Item)
        // ✅ THIS IS WHERE WE CHECK FNSKU AVAILABILITY
        // ============================================
        
        Log::info('🆕 Creating new item - checking FNSKU availability', [
            'serial' => $serial,
            'scanned_fnsku' => $scannedFNSKU,
            'reason' => 'Item not found in system (floating item)'
        ]);

        // For new items, FNSKU is REQUIRED
        if (empty($scannedFNSKU)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'FNSKU is required for new items',
                'reason' => 'fnsku_required_for_new_item'
            ]);
        }

        // ✅ NOW check FNSKU availability (only for new items)
        $fnskuAvailability = $this->checkFnskuAvailabilityWithPrefix($scannedFNSKU);

        if (!$fnskuAvailability['available']) {
            DB::rollBack();
            
            Log::warning('❌ FNSKU not available for new item', [
                'fnsku' => $scannedFNSKU,
                'serial' => $serial
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'FNSKU not found or not available: ' . $scannedFNSKU,
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

        Log::info('✅ Creating new item with FNSKU', [
            'rt' => $newrt,
            'serial' => $serial,
            'base_fnsku' => $baseFnsku,
            'actual_fnsku' => $actualFnskuToUse,
            'location' => $location
        ]);

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

        // ✅ Deduct FNSKU units (only for new items)
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
        $this->clearStockroomCaches();
        
        return response()->json([
            'success' => true,
            'message' => "New item scanned and inserted successfully",
            'fnsku_used' => $actualFnskuToUse,
            'remaining_units' => $fnskuInfo['remaining_units'],
            'new_item' => true
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Unhandled error in processScan', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error processing scan: ' . $e->getMessage(),
            'reason' => 'server_error'
        ], 500);
    }
}

public function checkSerial(Request $request)
{
    $serial = $request->input('serial');

    if (empty($serial)) {
        return response()->json([
            'exists' => false,
            'message' => 'Serial is required'
        ]);
    }

    try {
        Log::info('🔍 Checking serial existence', ['serial' => $serial]);

        // Check in Validation, Stockroom, or Production Area
        $item = DB::table($this->productTable)
            ->where(function ($query) use ($serial) {
                $query->where('serialnumber', $serial)
                    ->orWhere('serialnumberb', $serial)
                    ->orWhere('serialnumberc', $serial)
                    ->orWhere('serialnumberd', $serial);
            })
            ->where(function ($query) {
                $query->where('ProductModuleLoc', 'Validation')
                    ->orWhere('ProductModuleLoc', 'Production Area');
            })
            ->first();

        if ($item) {
            Log::info('✅ Serial exists', [
                'serial' => $serial,
                'location' => $item->ProductModuleLoc,
                'rt' => $item->rtcounter,
                'fnsku' => $item->FNSKUviewer
            ]);

            return response()->json([
                'exists' => true,
                'location' => $item->ProductModuleLoc,
                'rtcounter' => $item->rtcounter,
                'fnsku' => $item->FNSKUviewer,
                'validation_status' => $item->validation_status ?? null
            ]);
        } else {
            Log::info('❌ Serial not found', ['serial' => $serial]);

            return response()->json([
                'exists' => false,
                'message' => 'Serial not found in system (new item)'
            ]);
        }
    } catch (\Exception $e) {
        Log::error('Error checking serial', [
            'serial' => $serial,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'exists' => false,
            'error' => 'Error checking serial: ' . $e->getMessage()
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
        'fnsku' => 'nullable|string'
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

        // ============================================
        // GET SELECTED ITEMS WITH ASIN DATA
        // ============================================
            $serialNumberResults = DB::table($this->productTable . ' as prod')
                ->select('prod.*')
                ->whereIn('prod.ProductID', $selectedIds)
                ->get();

            // Now enrich with FNSKU data using PHP
            $serialNumberResults = $serialNumberResults->map(function($item) {
                // Extract base FNSKU
                $baseFnsku = $this->extractBaseFnsku($item->FNSKUviewer);
                
                // Get FNSKU record
                $fnskuRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();
                
                if ($fnskuRecord) {
                    $item->ASIN = $fnskuRecord->ASIN;
                    $item->grading = $fnskuRecord->grading;
                    $item->storename = $fnskuRecord->storename;
                    
                    // Get ASIN data
                    $asinRecord = DB::table($this->asinTable)
                        ->where('ASIN', $fnskuRecord->ASIN)
                        ->first();
                    
                    if ($asinRecord) {
                        $item->ProductTitle = $asinRecord->internal;
                        $item->color = $asinRecord->color;
                        $item->QuantityInside = $asinRecord->QuantityInside;
                    }
                }
                
                return $item;
            });
        // ============================================
        // VALIDATION: Check items are compatible for merging
        // ============================================
        $firstItem = $serialNumberResults->first();
        $firstAsin = $firstItem->ASIN;
        $firstColor = $firstItem->color;
        $firstQuantityInside = $firstItem->QuantityInside ?? 1;
        $firstTitle = $firstItem->ProductTitle;
        $firstStoreName = $firstItem->storename;
        $firstCondition = $firstItem->grading;

        Log::info('Validating items for merge', [
            'first_asin' => $firstAsin,
            'first_color' => $firstColor,
            'first_quantity_inside' => $firstQuantityInside,
            'first_title' => $firstTitle,
            'first_storename' => $firstStoreName,
            'first_condition' => $firstCondition,
            'total_items' => $numOfSerial
        ]);

        $incompatibleItems = [];
        foreach ($serialNumberResults as $item) {
            $itemAsin = $item->ASIN;
            $itemColor = $item->color;
            $itemQuantityInside = $item->QuantityInside ?? 1;
            $itemSerial = $item->serialnumber;
            $itemStoreName = $item->storename;
            $itemCondition = $item->grading;

            // Check ASIN match
            if ($itemAsin !== $firstAsin) {
                $incompatibleItems[] = [
                    'serial' => $itemSerial,
                    'reason' => 'Different ASIN',
                    'expected' => $firstAsin,
                    'actual' => $itemAsin
                ];
            }

            // Check Color match
            if ($itemColor !== $firstColor) {
                $incompatibleItems[] = [
                    'serial' => $itemSerial,
                    'reason' => 'Different Color',
                    'expected' => $firstColor ?? 'none',
                    'actual' => $itemColor ?? 'none'
                ];
            }

            // Check QuantityInside match
            if ($itemQuantityInside !== $firstQuantityInside) {
                $incompatibleItems[] = [
                    'serial' => $itemSerial,
                    'reason' => 'Different QuantityInside',
                    'expected' => $firstQuantityInside,
                    'actual' => $itemQuantityInside
                ];
            }

            // Check Store Name match
            if ($itemStoreName !== $firstStoreName) {
                $incompatibleItems[] = [
                    'serial' => $itemSerial,
                    'reason' => 'Different Store',
                    'expected' => $firstStoreName ?? 'none',
                    'actual' => $itemStoreName ?? 'none'
                ];
            }

            // Check Condition match
            if ($itemCondition !== $firstCondition) {
                $incompatibleItems[] = [
                    'serial' => $itemSerial,
                    'reason' => 'Different Condition',
                    'expected' => $firstCondition ?? 'none',
                    'actual' => $itemCondition ?? 'none'
                ];
            }
        }

        // If there are incompatible items, return error
        if (!empty($incompatibleItems)) {
            DB::rollBack();
            
            $errorMessage = "Cannot merge items - incompatible products detected:\n";
            foreach ($incompatibleItems as $issue) {
                $errorMessage .= "- Serial {$issue['serial']}: {$issue['reason']} (Expected: {$issue['expected']}, Got: {$issue['actual']})\n";
            }
            
            Log::warning('Merge validation failed', [
                'incompatible_items' => $incompatibleItems
            ]);

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'reason' => 'incompatible_items',
                'incompatible_items' => $incompatibleItems
            ]);
        }

        Log::info('✅ All items validated - compatible for merge', [
            'validated_storename' => $firstStoreName,
            'validated_condition' => $firstCondition
        ]);

        // ============================================
        // COLLECT SERIAL NUMBERS AND PRICES
        // ============================================
        $serialNumberA = null;
        $serialNumberB = null;
        $serialNumberC = null;
        $serialNumberD = null;
        $totalPrice = 0;

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

        // Get serials in the order of selectedIds
        $orderedSerials = [];
        foreach ($selectedIds as $productId) {
            $matchingItem = $serialNumberResults->firstWhere('ProductID', $productId);
            if ($matchingItem) {
                $orderedSerials[] = $matchingItem->serialnumber;
                $totalPrice += $matchingItem->price ?? 0;
                
                // Get title and store from first item
                if (empty($title)) {
                    $title = $matchingItem->ProductTitle ?? $matchingItem->AStitle ?? '';
                    $firstStore = $matchingItem->storename ?? $matchingItem->StoreName ?? '';
                }
            }
        }

        // Assign serials to A, B, C, D based on order
        if (count($orderedSerials) > 0) $serialNumberA = $orderedSerials[0];
        if (count($orderedSerials) > 1) $serialNumberB = $orderedSerials[1];
        if (count($orderedSerials) > 2) $serialNumberC = $orderedSerials[2];
        if (count($orderedSerials) > 3) $serialNumberD = $orderedSerials[3];

        // Extract color from title (if it exists in parentheses)
        preg_match('/\((.*?)\)/', $title, $matches);
        $colorFromTitle = isset($matches[1]) ? $matches[1] : $firstColor;

        // Remove color and pack info from title to get base title
        $baseTitle = trim(preg_replace('/\s*\(.*?\)\s*/', '', $title));
        $baseTitle = trim(preg_replace('/\s+\d+-Pack\s*/', ' ', $baseTitle));

        // ============================================
        // CALCULATE TARGET QUANTITY FOR MERGED PACK
        // ============================================
        $targetQuantityInside = $numOfSerial * $firstQuantityInside;

        // ============================================
        // VALIDATE PACK SIZE (ONLY 2-PACK OR 4-PACK ALLOWED)
        // ============================================
        $allowedPackSizes = [2, 4];
        
        if (!in_array($targetQuantityInside, $allowedPackSizes)) {
            DB::rollBack();
            
            Log::warning('Invalid pack size for merge', [
                'target_quantity_inside' => $targetQuantityInside,
                'num_items_merging' => $numOfSerial,
                'each_item_quantity_inside' => $firstQuantityInside,
                'allowed_pack_sizes' => $allowedPackSizes
            ]);

            return response()->json([
                'success' => false,
                'message' => "Cannot merge: Invalid pack size.\n\n" .
                            "You are trying to create a {$targetQuantityInside}-pack, but only 2-pack and 4-pack merges are allowed.\n\n" .
                            "Current selection:\n" .
                            "- {$numOfSerial} items selected\n" .
                            "- Each item contains {$firstQuantityInside} unit(s)\n" .
                            "- Target pack size: {$targetQuantityInside}-pack\n\n" .
                            "To create a 2-pack: Select 2 single items\n" .
                            "To create a 4-pack: Select 4 single items or 2 double items",
                'reason' => 'invalid_pack_size',
                'target_pack_size' => $targetQuantityInside,
                'allowed_pack_sizes' => $allowedPackSizes
            ]);
        }

        Log::info('✅ Pack size validation passed', [
            'num_items_merging' => $numOfSerial,
            'each_item_quantity_inside' => $firstQuantityInside,
            'target_quantity_inside' => $targetQuantityInside,
            'base_title' => $baseTitle,
            'color' => $colorFromTitle
        ]);

        // ============================================
        // ACCURATE ASIN SELECTION - NO FALLBACKS
        // ============================================
        $asinResult = DB::table($this->asinTable)
            ->where('internal', 'like', '%' . $baseTitle . '%')
            ->where('QuantityInside', $targetQuantityInside)
            ->where(function($query) use ($colorFromTitle) {
                if (!empty($colorFromTitle)) {
                    $query->where('color', 'like', '%' . $colorFromTitle . '%');
                }
            })
            ->first();

        // If no exact match found, REJECT the merge
        if (!$asinResult) {
            DB::rollBack();
            
            Log::error('No exact matching ASIN found for merge', [
                'base_title' => $baseTitle,
                'target_quantity_inside' => $targetQuantityInside,
                'color' => $colorFromTitle,
                'num_items' => $numOfSerial
            ]);

            return response()->json([
                'success' => false,
                'message' => "Cannot merge: No exact matching pack ASIN found.\n\n" .
                            "Required:\n" .
                            "- Title: {$baseTitle}\n" .
                            "- Pack Size: {$targetQuantityInside}-pack\n" .
                            "- Color: " . ($colorFromTitle ?: 'Any') . "\n\n" .
                            "Please ensure a {$targetQuantityInside}-pack variant exists in the database before merging.",
                'reason' => 'no_exact_asin_match',
                'required' => [
                    'title' => $baseTitle,
                    'quantity_inside' => $targetQuantityInside,
                    'color' => $colorFromTitle
                ]
            ]);
        }

        Log::info('✅ Found exact matching ASIN', [
            'ASIN' => $asinResult->ASIN,
            'internal' => $asinResult->internal,
            'QuantityInside' => $asinResult->QuantityInside,
            'color' => $asinResult->color
        ]);

        $asinTitle = $asinResult->internal;
        $targetAsin = $asinResult->ASIN;
        $asinColor = $asinResult->color ?? '';
        $asinQuantityInside = $asinResult->QuantityInside ?? 1;
        $store = $firstStore;

        // Construct title using ASIN data
        $constructedTitle = $asinTitle;
        
        // Add pack info if QuantityInside > 1
        if ($asinQuantityInside > 1 && stripos($constructedTitle, '-pack') === false) {
            $constructedTitle .= ' ' . $asinQuantityInside . '-Pack';
        }
        
        // Add color if exists and not already in title
        if (!empty($asinColor) && stripos($constructedTitle, '(' . $asinColor . ')') === false) {
            $constructedTitle .= ' (' . $asinColor . ')';
        }

        Log::info('Using constructed title from ASIN data', [
            'originalTitle' => $asinTitle,
            'constructedTitle' => $constructedTitle,
            'color' => $asinColor,
            'QuantityInside' => $asinQuantityInside
        ]);

        // ============================================
        // MERGE FNSKU LOGIC WITH RELATED ASIN FALLBACK
        // Find PACK FNSKU for target ASIN
        // ============================================
        $baseFnskuToUse = null;
        $actualFnskuToUse = null;
        
        // Use the validated condition and storename from first item
        $condition = $firstCondition;
        $storename = $firstStoreName;

        try {
            // Override with provided FNSKU info if it exists (shouldn't change condition/store)
            if ($providedFnsku) {
                $baseFnsku = $this->extractBaseFnsku($providedFnsku);
                
                $originalFnskuInfo = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();
                
                if ($originalFnskuInfo) {
                    // Verify provided FNSKU matches the validated condition and store
                    if ($originalFnskuInfo->grading !== $condition) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Provided FNSKU condition ({$originalFnskuInfo->grading}) does not match items' condition ({$condition})",
                            'reason' => 'fnsku_condition_mismatch'
                        ]);
                    }
                    
                    if ($originalFnskuInfo->storename !== $storename) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Provided FNSKU store ({$originalFnskuInfo->storename}) does not match items' store ({$storename})",
                            'reason' => 'fnsku_store_mismatch'
                        ]);
                    }
                    
                    Log::info("Validated provided FNSKU matches condition and store", [
                        'original_fnsku' => $baseFnsku,
                        'condition' => $condition,
                        'storename' => $storename
                    ]);
                }
            }

            // ============================================
            // STEP 1: SEARCH FOR PACK FNSKU - EXACT ASIN MATCH
            // ALL CRITERIA MUST MATCH: QuantityInside + Color + Condition + Store
            // ============================================
            Log::info("Searching for PACK FNSKU (exact ASIN)", [
                'target_asin' => $targetAsin,
                'quantity_inside' => $asinQuantityInside,
                'color' => $asinColor ?: 'Any',
                'condition' => $condition,
                'storename' => $storename
            ]);

            $packFnsku = DB::table($this->fnskuTable . ' as fnsku')
                ->select('fnsku.*')
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->where('fnsku.ASIN', $targetAsin)
                ->where('fnsku.fnsku_status', 'available')
                ->where('fnsku.amazon_status', 'Existed')
                ->where('fnsku.LimitStatus', 'False')
                ->where('fnsku.Units', '>', 0)
                ->where('asin.quantityinside', $asinQuantityInside)
                ->where('fnsku.grading', $condition) // Must match validated condition
                ->where('fnsku.storename', $storename) // Must match validated storename
                ->when($asinColor, function($query) use ($asinColor) {
                    return $query->where('asin.color', $asinColor);
                })
                ->orderByDesc('fnsku.FNSKUID')
                ->first();
            
            if ($packFnsku) {
                $baseFnskuToUse = $packFnsku->FNSKU;
                Log::info("✅ Found exact PACK FNSKU (primary ASIN)", [
                    'fnsku' => $baseFnskuToUse,
                    'asin' => $packFnsku->ASIN,
                    'condition' => $packFnsku->grading,
                    'store' => $packFnsku->storename,
                    'units' => $packFnsku->Units
                ]);
            }

            // ============================================
            // STEP 2: FALLBACK TO RELATED ASINs
            // SAME CRITERIA ENFORCED: QuantityInside + Color + Condition + Store
            // ============================================
            if (!$packFnsku) {
                Log::info("Exact ASIN not found, trying related ASINs...");
                
                $relatedAsins = $this->findRelatedAsins($targetAsin);
                
                if (!empty($relatedAsins)) {
                    Log::info("Found related ASINs to search", [
                        'count' => count($relatedAsins),
                        'asins' => $relatedAsins
                    ]);
                    
                    $packFnsku = DB::table($this->fnskuTable . ' as fnsku')
                        ->select('fnsku.*')
                        ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                        ->whereIn('fnsku.ASIN', $relatedAsins)
                        ->where('fnsku.fnsku_status', 'available')
                        ->where('fnsku.amazon_status', 'Existed')
                        ->where('fnsku.LimitStatus', 'False')
                        ->where('fnsku.Units', '>', 0)
                        ->where('asin.quantityinside', $asinQuantityInside)  // ✅ Same pack size required
                        ->where('fnsku.grading', $condition)  // ✅ Same condition required
                        ->where('fnsku.storename', $storename)  // ✅ Same store required
                        ->when($asinColor, function($query) use ($asinColor) {
                            return $query->where('asin.color', $asinColor);  // ✅ Same color required
                        })
                        ->orderByDesc('fnsku.FNSKUID')
                        ->first();
                    
                    if ($packFnsku) {
                        $baseFnskuToUse = $packFnsku->FNSKU;
                        Log::info("✅ Found PACK FNSKU from related ASIN", [
                            'related_asin' => $packFnsku->ASIN,
                            'original_asin' => $targetAsin,
                            'fnsku' => $baseFnskuToUse,
                            'condition' => $packFnsku->grading,
                            'storename' => $packFnsku->storename,
                            'quantity_inside' => $asinQuantityInside,
                            'color' => $asinColor ?: 'Any'
                        ]);
                    } else {
                        Log::warning("❌ No matching FNSKU in related ASINs", [
                            'searched_asins' => $relatedAsins,
                            'required_quantity_inside' => $asinQuantityInside,
                            'required_color' => $asinColor ?: 'Any',
                            'required_condition' => $condition,
                            'required_storename' => $storename
                        ]);
                    }
                } else {
                    Log::info("No related ASINs found for {$targetAsin}");
                }
            }

            // ============================================
            // STEP 3: FINAL CHECK - REJECT IF NOTHING FOUND
            // ============================================
            if (!$packFnsku) {
                DB::rollBack();
                
                $relatedAsinsList = isset($relatedAsins) && !empty($relatedAsins) 
                    ? implode(', ', array_slice($relatedAsins, 0, 5)) . (count($relatedAsins) > 5 ? ' +' . (count($relatedAsins) - 5) . ' more' : '')
                    : 'None';
                
                Log::error('No matching PACK FNSKU found (checked related ASINs)', [
                    'target_asin' => $targetAsin,
                    'related_asins_checked' => isset($relatedAsins) ? count($relatedAsins) : 0,
                    'required_quantity_inside' => $asinQuantityInside,
                    'required_color' => $asinColor ?: 'Not specified',
                    'required_condition' => $condition,
                    'required_storename' => $storename
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Cannot merge: No available FNSKU found.\n\n" .
                                "Searched ASINs:\n" .
                                "• Primary: {$targetAsin}\n" .
                                "• Related: {$relatedAsinsList}\n\n" .
                                "Required (ALL must match):\n" .
                                "• Pack Size: {$asinQuantityInside}-pack\n" .
                                "• Color: " . ($asinColor ?: 'Any') . "\n" .
                                "• Condition: {$condition}\n" .
                                "• Store: {$storename}\n" .
                                "• Status: Available with units\n\n" .
                                "Please create an FNSKU matching all criteria.",
                    'reason' => 'no_pack_fnsku_available',
                    'search_details' => [
                        'primary_asin' => $targetAsin,
                        'related_asins' => $relatedAsins ?? [],
                        'required' => [
                            'quantity_inside' => $asinQuantityInside,
                            'color' => $asinColor ?: null,
                            'condition' => $condition,
                            'storename' => $storename
                        ]
                    ]
                ]);
            }

            // Generate prefixed FNSKU
            $fnskuInfo = $this->getNextAvailableFnsku(
                $baseFnskuToUse,
                $targetAsin,
                $condition,
                $storename
            );
            $actualFnskuToUse = $fnskuInfo['actual_fnsku'];

            Log::info('✅ Generated prefixed FNSKU for merge', [
                'base_fnsku' => $baseFnskuToUse,
                'actual_fnsku' => $actualFnskuToUse,
                'target_asin' => $targetAsin,
                'times_used' => $fnskuInfo['times_used'],
                'remaining_units' => $fnskuInfo['remaining_units']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error getting FNSKU for merge: ' . $e->getMessage()
            ]);
        }

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
            'validation_status' => 'validated',
            'FNSKUviewer' => $actualFnskuToUse
        ];

        $productId = DB::table($this->productTable)->insertGetId($productData);

        // Update FNSKU units
        $becameUnavailable = $this->updateFnskuUnits(
            $baseFnskuToUse,
            $targetAsin,
            $condition,
            $storename
        );

        Log::info('Updated FNSKU Units count for merge', [
            'base_fnsku' => $baseFnskuToUse,
            'actual_fnsku_used' => $actualFnskuToUse,
            'became_unavailable' => $becameUnavailable
        ]);

        DB::table($this->productTable)
            ->whereIn('ProductID', $selectedIds)
            ->update([
                'ProductModuleLoc' => 'Merged',
                'mergedTO' => $newRt
            ]);

        if (!empty($providedFnsku)) {
            $baseFnskuProvided = $this->extractBaseFnsku($providedFnsku);
            if ($baseFnskuProvided !== $baseFnskuToUse) {
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

        // ✅ CLEAR CACHES IMMEDIATELY AFTER COMMIT
        $this->clearStockroomCaches();

        return response()->json([
            'success' => true,
            'message' => 'Items merged successfully.',
            'newrt' => $newRt,
            'SERIAL' => $serialNumberA,
            'productid' => $productId,
            'store' => $store,
            'title' => $constructedTitle,
            'fnsku' => $actualFnskuToUse,
            'units' => $fnskuInfo['remaining_units'],
            'asin_data' => [
                'ASIN' => $targetAsin,
                'color' => $asinColor,
                'QuantityInside' => $asinQuantityInside
            ]
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

private function updateFnskuUnits($baseFnsku, $asin, $grading, $storename)
{
    try {
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->first();

        if (!$fnskuRecord) {
            return false;
        }

        $currentUnits = $fnskuRecord->Units ?? 0;
        $newUnits = max(0, $currentUnits - 1);
        $newStatus = ($newUnits <= 0) ? 'Unavailable' : 'available';

        DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->update([
                'Units' => $newUnits,
                'fnsku_status' => $newStatus
            ]);

        return ($newStatus === 'Unavailable');

    } catch (\Exception $e) {
        Log::error("Error updating FNSKU units: " . $e->getMessage());
        return false;
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

             // ✅ CLEAR CACHES IMMEDIATELY AFTER COMMIT
        $this->clearStockroomCaches();

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

             // ✅ CLEAR CACHES IMMEDIATELY AFTER COMMIT
        $this->clearStockroomCaches();

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


    public function unmergeItem(Request $request)
{
    $validated = $request->validate([
        'productId' => 'required|integer'
    ]);

    try {
        DB::beginTransaction();

        $mergedItem = DB::table($this->productTable)
            ->where('ProductID', $validated['productId'])
            ->where('ProductModuleLoc', 'Stockroom')
            ->first();

        if (!$mergedItem) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found or not in Stockroom'
            ]);
        }

        if (empty($mergedItem->mergeID)) {
            return response()->json([
                'success' => false,
                'message' => 'This item is not a merged item'
            ]);
        }

        $mergeId = $mergedItem->mergeID;
        $rtCounter = $mergedItem->rtcounter;

        // Find all original items that were merged into this item
        $originalItems = DB::table($this->productTable)
            ->where('mergedTO', $rtCounter)
            ->where('ProductModuleLoc', 'Merged')
            ->get();

        if ($originalItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No original items found to restore'
            ]);
        }

        $california_timezone = new DateTimeZone('America/Los_Angeles');
        $currentDatetime = new DateTime('now', $california_timezone);
        $currentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
        $user = $this->getCurrentUserName();

        // Restore original items back to Stockroom
        $restoredCount = 0;
        foreach ($originalItems as $item) {
            DB::table($this->productTable)
                ->where('ProductID', $item->ProductID)
                ->update([
                    'ProductModuleLoc' => 'Stockroom',
                    'mergedTO' => null,
                    'stockroom_insert_date' => $currentDatetimeString
                ]);

            // Log history
            DB::table($this->itemProcessHistoryTable)->insert([
                'rtcounter' => $item->rtcounter,
                'employeeName' => $user,
                'editDate' => $currentDatetimeString,
                'Module' => 'Stockroom',
                'Action' => 'Unmerged - Restored to Stockroom'
            ]);

            $restoredCount++;
        }

        // Return FNSKU units if the merged item used one
        if (!empty($mergedItem->FNSKUviewer)) {
            $baseFnsku = $this->extractBaseFnsku($mergedItem->FNSKUviewer);
            $this->returnFnskuUnits($baseFnsku);
            Log::info("Returned FNSKU units after unmerge", [
                'fnsku' => $baseFnsku
            ]);
        }

        // Delete the merged item
        DB::table($this->productTable)
            ->where('ProductID', $validated['productId'])
            ->delete();

        // Log the unmerge action
        DB::table($this->itemProcessHistoryTable)->insert([
            'rtcounter' => $rtCounter,
            'employeeName' => $user,
            'editDate' => $currentDatetimeString,
            'Module' => 'Stockroom',
            'Action' => "Unmerged - Deleted merged item, restored {$restoredCount} original items"
        ]);

        // Delete merge record
        DB::table('tblmigrateditem')
            ->where('migrateID', $mergeId)
            ->delete();

        DB::commit();

         // ✅ CLEAR CACHES IMMEDIATELY AFTER COMMIT
        $this->clearStockroomCaches();

        return response()->json([
            'success' => true,
            'message' => "Successfully unmerged item. Restored {$restoredCount} original items to Stockroom.",
            'restored_count' => $restoredCount
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error unmerging item: ' . $e->getMessage());
        Log::error($e->getTraceAsString());

        return response()->json([
            'success' => false,
            'message' => 'Error unmerging item: ' . $e->getMessage()
        ], 500);
    }
 }

 /**
 * Return FNSKU units (increment by 1) - helper for unmerge
 */
private function returnFnskuUnits($fnskuViewer)
{
    try {
        $baseFnsku = $this->extractBaseFnsku($fnskuViewer);

        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->first();

        if (!$fnskuRecord) {
            return false;
        }

        $currentUnits = $fnskuRecord->Units ?? 0;
        $newUnits = $currentUnits + 1;

        DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->update([
                'Units' => $newUnits,
                'fnsku_status' => 'available'
            ]);

        return true;

    } catch (\Exception $e) {
        Log::error("Error returning FNSKU units: " . $e->getMessage());
        return false;
    }
}


private function clearStockroomCaches()
{
    try {
        Log::info('🧹 Starting cache clear for stockroom...');
        
        // Get all stores
        $stores = DB::table($this->fnskuTable)
            ->select('storename')
            ->distinct()
            ->whereNotNull('storename')
            ->where('storename', '!=', '')
            ->pluck('storename')
            ->toArray();
        
        // Add empty string for "all stores" option
        $stores[] = '';
        
        $clearedCount = 0;
        
        // Clear inventory caches for all page/perPage/store combinations
        foreach ([10, 15, 20, 50, 100] as $perPage) {
            for ($page = 1; $page <= 50; $page++) { // Clear first 50 pages
                foreach ($stores as $store) {
                    // Clear with empty search
                    $cacheKey = "stockroom_inventory_{$page}_{$perPage}_{$store}_" . md5('');
                    if (Cache::has($cacheKey)) {
                        Cache::forget($cacheKey);
                        $clearedCount++;
                    }
                }
            }
        }
        
        // Clear stores cache
        if (Cache::has('stockroom_stores')) {
            Cache::forget('stockroom_stores');
            $clearedCount++;
        }
        
        // Clear new scanned count caches (today + last 7 days)
        $timezone = new DateTimeZone('America/Los_Angeles');
        $currentDate = new DateTime('now', $timezone);
        
        for ($i = 0; $i < 7; $i++) {
            $date = clone $currentDate;
            if ($i > 0) {
                $date->modify("-{$i} days");
            }
            $dateString = $date->format('Y-m-d');
            $countCacheKey = 'new_scanned_count_' . $dateString;
            
            if (Cache::has($countCacheKey)) {
                Cache::forget($countCacheKey);
                $clearedCount++;
            }
        }
        
        Log::info("✅ Cleared {$clearedCount} stockroom cache entries");
        return true;
        
    } catch (\Exception $e) {
        Log::error('❌ Error clearing caches: ' . $e->getMessage());
        return false;
    }
}


}