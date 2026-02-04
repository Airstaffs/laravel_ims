<?php

namespace App\Http\Controllers;

use App\Traits\TracksHistory;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockroomController extends BasetablesController
{
    use TracksHistory;

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
    private function getNextAvailableFnsku($baseFnsku, $msku, $asin, $grading, $storename)
    {
        try {
            // ✅ Lock FNSKU record using MSKU (only change - more accurate lookup)
            $fnskuRecord = DB::table($this->fnskuTable)
                ->where('MSKU', $msku)
                ->where('ASIN', $asin)
                ->where('grading', $grading)
                ->where('storename', $storename)
                ->where('LimitStatus', 'False')
                ->whereIn('amazon_status', ['Active', 'Notposted'])
                ->lockForUpdate()
                ->first();

            if (!$fnskuRecord) {
                Log::warning("FNSKU not found in database", [
                    'base_fnsku' => $baseFnsku,
                    'msku' => $msku,
                    'asin' => $asin,
                    'grading' => $grading,
                    'storename' => $storename
                ]);
                
                return [
                    'actual_fnsku' => $baseFnsku,
                    'actual_msku' => $msku,
                    'times_used' => 0,
                    'remaining_units' => 0
                ];
            }

            $currentUnits = $fnskuRecord->Units;

            if ($currentUnits <= 0) {
                throw new \Exception("No remaining units for MSKU: {$msku} (Units: {$currentUnits})");
            }

            // ✅ ORIGINAL LOGIC - Get ALL active FNSKUs (with and without prefix) currently in use
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
                'active_count' => count($activeFnskus),
                'remaining_units' => $currentUnits
            ]);

            // ✅ ORIGINAL LOGIC - Extract used prefixes from active products
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

            // ✅ ORIGINAL LOGIC - Maximum prefix is 9 (C0 through C9 = 10 total slots)
            $maxAllowedPrefix = 9;

            Log::info("Prefix analysis", [
                'base_fnsku' => $baseFnsku,
                'used_prefixes' => $usedPrefixes,
                'used_count' => count($usedPrefixes),
                'max_allowed_prefix' => $maxAllowedPrefix,
                'remaining_units_in_db' => $currentUnits
            ]);

            // ✅ ORIGINAL LOGIC - Find first UNUSED prefix within the allowed range
            $nextPrefix = null;

            for ($i = 0; $i <= $maxAllowedPrefix; $i++) {
                if (!in_array($i, $usedPrefixes)) {
                    $nextPrefix = $i;
                    break;
                }
            }

            // ✅ ORIGINAL LOGIC - Check if we found an available prefix slot
            if ($nextPrefix === null) {
                throw new \Exception(
                    "All prefix slots exhausted for FNSKU: {$baseFnsku}. " .
                    "All " . ($maxAllowedPrefix + 1) . " prefixes (C0-C9) are in use. " .
                    "Used prefixes: " . implode(', ', $usedPrefixes)
                );
            }

            // ✅ ORIGINAL LOGIC - Generate FNSKU with correct prefix
            if ($nextPrefix === 0) {
                $actualFnsku = $baseFnsku; // No prefix
            } else {
                $actualFnsku = "C{$nextPrefix}{$baseFnsku}";
            }

            Log::info("✅ Generated FNSKU with available prefix", [
                'base_fnsku' => $baseFnsku,
                'used_prefixes' => $usedPrefixes,
                'next_prefix' => $nextPrefix,
                'actual_fnsku' => $actualFnsku,
                'remaining_units' => $currentUnits
            ]);

            return [
                'actual_fnsku' => $actualFnsku,
                'actual_msku' => $msku,  // ✅ Return MSKU for consistency
                'times_used' => count($usedPrefixes),
                'remaining_units' => $currentUnits,
                'next_prefix' => $nextPrefix
            ];

        } catch (\Exception $e) {
            Log::error("Error in getNextAvailableFnsku: " . $e->getMessage(), [
                'base_fnsku' => $baseFnsku,
                'msku' => $msku,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Check if an FNSKU (with or without prefix) is available
     */
    private function checkFnskuAvailabilityWithPrefix($inputFnsku, $inputMsku = null)
    {
        // Extract base FNSKU if prefixed
        $baseFnsku = $this->extractBaseFnsku($inputFnsku);

        // ✅ PRIORITY 1: Search by MSKU if provided (most unique)
        if (! empty($inputMsku)) {
            $fnskuRecord = DB::table($this->fnskuTable)
                ->where('MSKU', $inputMsku)
                ->where('fnsku_status', 'Available')
                ->where('Units', '>', 0)
                ->first();

            if ($fnskuRecord) {
                Log::info('✅ Found FNSKU by MSKU (most accurate)', [
                    'input_msku' => $inputMsku,
                    'found_fnsku' => $fnskuRecord->FNSKU,
                    'found_msku' => $fnskuRecord->MSKU,
                ]);

                return [
                    'available' => true,
                    'base_fnsku' => $fnskuRecord->FNSKU,
                    'msku' => $fnskuRecord->MSKU,
                    'asin' => $fnskuRecord->ASIN,
                    'grading' => $fnskuRecord->grading,
                    'storename' => $fnskuRecord->storename,
                    'record' => $fnskuRecord,
                ];
            }
        }

        // ✅ PRIORITY 2: Search by FNSKU (fallback or when MSKU not provided)
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->where('fnsku_status', 'Available')
            ->where('Units', '>', 0)
            ->first();

        if ($fnskuRecord) {
            Log::info('✅ Found FNSKU by FNSKU search', [
                'input_fnsku' => $inputFnsku,
                'base_fnsku' => $baseFnsku,
                'found_msku' => $fnskuRecord->MSKU,
            ]);

            return [
                'available' => true,
                'base_fnsku' => $baseFnsku,
                'msku' => $fnskuRecord->MSKU,
                'asin' => $fnskuRecord->ASIN,
                'grading' => $fnskuRecord->grading,
                'storename' => $fnskuRecord->storename,
                'record' => $fnskuRecord,
            ];
        }

        Log::warning('❌ FNSKU not found or not available', [
            'input_fnsku' => $inputFnsku,
            'input_msku' => $inputMsku,
            'base_fnsku' => $baseFnsku,
        ]);

        return [
            'available' => false,
            'base_fnsku' => null,
            'msku' => null,
            'record' => null,
        ];
    }

    // Check if FNSKU already reached its limit
    private function isFnskuLimitReached($fnsku)
    {
        return DB::table($this->fnskuTable)
            ->where('FNSKU', $fnsku)
            ->where('LimitStatus', 'True')
            ->exists();
    }

    /**
     * Find related ASINs with full recursive search - exact conversion from original function
     */
    private function findRelatedAsins($searchTerm)
    {
        $cacheKey = 'related_asins_'.md5($searchTerm);

        return Cache::remember($cacheKey, 300, function () use ($searchTerm) { // Cache for 5 minutes
            $related = [$searchTerm]; // Start with the search term in the array
            $checked = [];

            // Safety counter to prevent infinite loops
            $maxIterations = 50;
            $iterations = 0;

            while (! empty($related) && $iterations < $maxIterations) {
                $asinToCheck = array_pop($related);
                if (in_array($asinToCheck, $checked)) {
                    continue;
                }
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
                        if (! empty($val) && ! in_array($val, $checked) && ! in_array($val, $related)) {
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

            $cacheKey = "stockroom_inventory_{$page}_{$perPage}_{$store}_".md5($search);

            // Only use cache if NOT forced fresh AND search is empty
            if (! $forceFresh && empty($search)) {
                $cachedResult = Cache::get($cacheKey);
                if ($cachedResult) {
                    Log::info('Returning cached inventory data');

                    return response()->json($cachedResult);
                }
            }

            if ($forceFresh) {
                Log::info('Force fresh request - bypassing cache');
            }

            // Get products first
            $productsQuery = DB::table($this->productTable.' as prod')
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

                if (! isset($fnskuProductMap[$baseFnsku])) {
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
                    'total' => 0,
                ]);
            }

            // Modified query to include QuantityInside
            $asinQuery = DB::table($this->asinTable.' as asin')
                ->select([
                    'asin.ASIN',
                    'asin.internal as AStitle',
                    'asin.system_title',
                    'asin.asinStatus',
                    'asin.QuantityInside',  // Include QuantityInside column
                ])
                ->whereIn('asin.ASIN', $asinList)
                ->where('asin.ASIN', '!=', '')
                ->whereNotNull('asin.ASIN');

            // Apply search functionality
            if (! empty($search)) {
                $asinQuery->where(function ($query) use ($search, $baseFnskus, $products) {
                    $query->where('asin.ASIN', 'like', "%{$search}%");

                    if (strlen($search) > 3) {
                        $query->orWhere('asin.internal', 'like', "%{$search}%")
                            ->orWhere('asin.system_title', 'like', "%{$search}%")
                            ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                    }

                    // Search in FNSKUs
                    $matchingFnskus = array_filter($baseFnskus, function ($fnsku) use ($search) {
                        return strpos($fnsku, $search) !== false;
                    });
                    if (! empty($matchingFnskus)) {
                        $matchingAsins = DB::table($this->fnskuTable)
                            ->whereIn('FNSKU', $matchingFnskus)
                            ->pluck('ASIN')
                            ->toArray();
                        if (! empty($matchingAsins)) {
                            $query->orWhereIn('asin.ASIN', $matchingAsins);
                        }
                    }

                    // Search in serial numbers
                    $matchingSerials = $products->filter(function ($product) use ($search) {
                        return strpos($product->serialnumber, $search) !== false;
                    });
                    if ($matchingSerials->count() > 0) {
                        $matchingFnskusFromSerials = $matchingSerials->pluck('FNSKUviewer')
                            ->map(function ($fnsku) {
                                return $this->extractBaseFnsku($fnsku);
                            })
                            ->unique()
                            ->toArray();

                        $matchingAsinsFromSerials = DB::table($this->fnskuTable)
                            ->whereIn('FNSKU', $matchingFnskusFromSerials)
                            ->pluck('ASIN')
                            ->toArray();
                        if (! empty($matchingAsinsFromSerials)) {
                            $query->orWhereIn('asin.ASIN', $matchingAsinsFromSerials);
                        }
                    }

                    if (preg_match('/^B0[A-Z0-9]{8}$/i', $search)) {
                        $relatedAsins = $this->findRelatedAsins($search);
                        if (! empty($relatedAsins)) {
                            $relatedAsins = array_filter($relatedAsins, function ($asin) {
                                return ! empty($asin) && $asin !== null;
                            });

                            if (! empty($relatedAsins)) {
                                $query->orWhereIn('asin.ASIN', $relatedAsins);
                            }
                        }
                    }
                });
            }

            // Apply store filter
            if (! empty($store)) {
                $storeFilteredFnskus = DB::table($this->fnskuTable)
                    ->where('storename', $store)
                    ->whereIn('FNSKU', $baseFnskus)
                    ->pluck('ASIN')
                    ->toArray();

                if (! empty($storeFilteredFnskus)) {
                    $asinQuery->whereIn('asin.ASIN', $storeFilteredFnskus);
                } else {
                    return response()->json([
                        'data' => [],
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
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
                if (! empty($store)) {
                    $storeAsinFnskus = $asinFnskus->where('storename', $store);
                    if ($storeAsinFnskus->isEmpty()) {
                        continue;
                    }
                }

                // Aggregate the data
                $item = new \stdClass;
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
                $quantityInside = max(1, min(4, (int) $quantityInside)); // Ensure it's between 1-4

                $unitCount = $asinProducts->count(); // Number of units in stockroom
                $item->item_count = $unitCount * $quantityInside; // Total quantity
                $item->unit_count = $unitCount; // Keep track of actual units
                $item->quantity_inside = $quantityInside; // Store the QuantityInside value

                // Add FNSKUs and serials
                $item->fnskus = $asinFnskus->toArray();
                $item->serials = $asinProducts->map(function ($product) use ($fnskuData) {
                    $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                    $fnskuRecord = $fnskuData->get($baseFnsku);

                    return (object) [
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
                'total' => $total,
            ];

            // Only cache if NOT forced fresh and search is empty
            if (! $forceFresh && empty($search)) {
                Cache::put($cacheKey, $result, 30);
                Log::info('Cached inventory data');
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
            Log::error('Error in StockroomController@index: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'error' => 'An error occurred while retrieving stockroom data',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
            Log::error('Error getting stores: '.$e->getMessage());

            return response()->json([
                'error' => 'An error occurred while retrieving store list',
                'message' => $e->getMessage(),
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
                'normalized' => $normalizedFnsku,
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
        $msku = $request->input('msku'); // ✅ Optional MSKU parameter

        if (empty($fnsku) && empty($msku)) {
            return response()->json([
                'exists' => false,
                'status' => 'invalid',
                'message' => 'FNSKU or MSKU is required',
            ]);
        }

        try {

            $isLimitReached = $this->isFnskuLimitReached($fnsku);

            if ($isLimitReached) {
                return response()->json([
                    'exist' => false,
                    'status' => 'limit_reached',
                    'message' => 'FNSKU has already reached its usage limit.',
                    'fnsku' => $fnsku,
                ], 409);
            }
            $normalizedFnsku = ! empty($fnsku) ? $this->normalizeFnsku($fnsku) : null;

            Log::info('🔍 Checking FNSKU availability', [
                'input_fnsku' => $fnsku,
                'normalized_fnsku' => $normalizedFnsku,
                'input_msku' => $msku,
                'priority' => ! empty($msku) ? 'MSKU (more unique)' : 'FNSKU',
            ]);

            // ✅ Pass MSKU to get more accurate results
            $availability = $this->checkFnskuAvailabilityWithPrefix($normalizedFnsku, $msku);

            if ($availability['available']) {
                $record = $availability['record'];
                $baseFnsku = $availability['base_fnsku'];
                $msku = $availability['msku'];

                try {
                    $fnskuInfo = $this->getNextAvailableFnsku(
                        $baseFnsku,
                        $msku,  // ✅ Pass MSKU
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
                        'msku' => $msku,  // ✅ Return MSKU
                        'asin' => $record->ASIN,  // ✅ Return ASIN
                        'grading' => $record->grading,
                        'storename' => $record->storename,
                        'next_fnsku_to_use' => $fnskuInfo['actual_fnsku'],
                        'next_msku_to_use' => $fnskuInfo['actual_msku'],
                        'remaining_units' => $record->Units,
                        'times_used' => $fnskuInfo['times_used'],
                        'units_after_use' => $fnskuInfo['remaining_units'],
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'exists' => true,
                        'status' => 'exhausted',
                        'message' => $e->getMessage(),
                        'normalized_fnsku' => $normalizedFnsku,
                        'original_fnsku' => $fnsku,
                        'msku' => $msku,
                    ]);
                }
            } else {
                return response()->json([
                    'exists' => false,
                    'status' => 'not_found',
                    'message' => 'FNSKU/MSKU not found or no units remaining',
                    'normalized_fnsku' => $normalizedFnsku,
                    'original_fnsku' => $fnsku,
                    'input_msku' => $msku,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error checking FNSKU', [
                'fnsku' => $fnsku,
                'msku' => $msku,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'exists' => false,
                'status' => 'error',
                'message' => 'Error checking FNSKU status',
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
                    'FNSKU' => 'nullable|string',
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: '.implode(', ', $e->errors()),
                    'reason' => 'validation_error',
                ], 422);
            }

            $User = $this->getCurrentUserName();
            $serial = trim($request->input('SerialNumber', ''));
            $location = trim($request->input('Location', ''));
            $scannedFNSKU = trim($request->input('FNSKU', ''));

            $isReached = $this->isFnskuLimitReached($scannedFNSKU);

            if ($isReached) {
                return response()->json([
                    'exist' => false,
                    'status' => 'limit_reached',
                    'message' => 'FNSKU has already reached its usage limit.',
                    'fnsku' => $scannedFNSKU,
                ], 409);
            }

            if (! empty($scannedFNSKU)) {
                $scannedFNSKU = $this->normalizeFnsku($scannedFNSKU);
            }

            $Module = 'Stockroom';
            $Action = 'Scanned and insert to Stockroom';

            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');

            // Validate serial format
            if (! preg_match('/^[a-zA-Z0-9]+$/', $serial) || strpos($serial, 'X00') !== false) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Serial Number',
                    'reason' => 'invalid_serial',
                ]);
            }

            // Validate location format
            if (! preg_match('/^L\d{3}[A-G]$/i', $location) && $location !== 'Floor' && $location !== 'L800G') {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Location Format',
                    'reason' => 'invalid_location',
                ]);
            }

            $modulelocation = (substr($location, 0, 4) === 'L800') ? 'Production Area' : 'Stockroom';

            // CHECK 1: Does item exist in Stockroom/Production Area?
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
                $id = $existingItem->ProductID;
                $rt = $existingItem->rtcounter;

                if ($existingItem->ProductModuleLoc === 'Production Area' && $modulelocation === 'Production Area') {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Data Already in Production Area',
                        'reason' => 'duplicate_data',
                    ]);
                }

                if ($existingItem->ProductModuleLoc === 'Stockroom' && $modulelocation === 'Stockroom') {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Duplicate Data in Stockroom',
                        'reason' => 'duplicate_data',
                    ]);
                }

                DB::table($this->productTable)
                    ->where('ProductID', $id)
                    ->update([
                        'ProductModuleLoc' => $modulelocation,
                        'warehouselocation' => $location,
                        'stockroom_insert_date' => $curentDatetimeString,
                    ]);

                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $rt,
                    'employeeName' => $User,
                    'editDate' => $curentDatetimeString,
                    'Module' => 'Stockroom',
                    'Action' => "Updated location to {$location}",
                ]);

                $employeeName = auth()->user()->username ?? $User ?? 'System';
                $this->trackLocationChange(
                    'Stockroom',
                    "RT#{$rt} | Serial: {$serial}",
                    $existingItem->ProductModuleLoc,
                    $modulelocation,
                    $employeeName
                );

                DB::commit();
                $this->clearStockroomCaches();

                return response()->json([
                    'success' => true,
                    'message' => 'Location updated successfully',
                ]);
            }

            // CHECK 2: Does item exist in Validation?
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
                if ($existingInValidation->validation_status !== 'validated') {
                    DB::rollBack();
                    Log::warning('Attempted to scan non-validated item from Validation', [
                        'productId' => $existingInValidation->ProductID,
                        'rtcounter' => $existingInValidation->rtcounter,
                        'serial' => $serial,
                        'validation_status' => $existingInValidation->validation_status,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Item not validated yet. Please complete validation first.',
                        'reason' => 'not_validated',
                    ]);
                }

                $id = $existingInValidation->ProductID;
                $rtnumberofitem = $existingInValidation->rtcounter;
                $existingFNSKU = $existingInValidation->FNSKUviewer;
                $existingMSKU = $existingInValidation->MSKUviewer;  // ✅ Get existing MSKU
                $existingASIN = $existingInValidation->ASINviewer;  // ✅ Get existing ASIN

                if (empty($existingFNSKU) || empty($existingMSKU) || empty($existingASIN)) {
                    DB::rollBack();
                    Log::warning('Validated item missing data', [
                        'productId' => $id,
                        'rtcounter' => $rtnumberofitem,
                        'serial' => $serial,
                        'existing_fnsku' => $existingFNSKU,
                        'existing_msku' => $existingMSKU,
                        'existing_asin' => $existingASIN,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Item has incomplete data. Please complete validation first.',
                        'reason' => 'incomplete_validation_data',
                    ]);
                }

                Log::info('✅ Moving validated item from Validation to Stockroom', [
                    'productId' => $id,
                    'rtcounter' => $rtnumberofitem,
                    'existing_fnsku' => $existingFNSKU,
                    'existing_msku' => $existingMSKU,
                    'existing_asin' => $existingASIN,
                    'scanned_location' => $location,
                    'no_fnsku_check' => true,
                ]);

                DB::table($this->productTable)
                    ->where('ProductID', $id)
                    ->update([
                        'ProductModuleLoc' => $modulelocation,
                        'warehouselocation' => $location,
                        'stockroom_insert_date' => $curentDatetimeString,
                    ]);

                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $rtnumberofitem,
                    'employeeName' => $User,
                    'editDate' => $curentDatetimeString,
                    'Module' => 'Stockroom',
                    'Action' => "Scanned and insert to {$modulelocation}",
                ]);

                $employeeName = auth()->user()->username ?? $User ?? 'System';
                $this->trackLocationChange(
                    'Stockroom',
                    "RT#{$rtnumberofitem} | Serial: {$serial} | FNSKU: {$existingFNSKU}",
                    'Validation',
                    $modulelocation,
                    $employeeName
                );

                DB::commit();
                $this->clearStockroomCaches();

                return response()->json([
                    'success' => true,
                    'message' => "Scanned and Forwarded to {$modulelocation} Successfully",
                    'fnsku_preserved' => true,
                    'existing_fnsku' => $existingFNSKU,
                    'existing_msku' => $existingMSKU,
                ]);
            }

            // CHECK 3: Brand New Item (Floating Item) - FNSKU REQUIRED
            Log::info('🆕 Creating new item - checking FNSKU availability', [
                'serial' => $serial,
                'scanned_fnsku' => $scannedFNSKU,
                'reason' => 'Item not found in system (floating item)',
            ]);

            if (empty($scannedFNSKU)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'FNSKU is required for new items',
                    'reason' => 'fnsku_required_for_new_item',
                ]);
            }

            // ✅ Check FNSKU availability (no MSKU yet for new items, will get from lookup)
            $fnskuAvailability = $this->checkFnskuAvailabilityWithPrefix($scannedFNSKU, null);

            if (! $fnskuAvailability['available']) {
                DB::rollBack();

                Log::warning('❌ FNSKU not available for new item', [
                    'fnsku' => $scannedFNSKU,
                    'serial' => $serial,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'FNSKU not found or not available: '.$scannedFNSKU,
                    'reason' => 'fnsku_not_found',
                ]);
            }

            $baseFnsku = $fnskuAvailability['base_fnsku'];
            $msku = $fnskuAvailability['msku'];  // ✅ Get MSKU from lookup
            $asin = $fnskuAvailability['asin'];  // ✅ Get ASIN from lookup
            $fnskuRecord = $fnskuAvailability['record'];

            Log::info('✅ Found FNSKU record for new item', [
                'base_fnsku' => $baseFnsku,
                'msku' => $msku,
                'asin' => $asin,
                'grading' => $fnskuRecord->grading,
                'storename' => $fnskuRecord->storename,
            ]);

            try {
                // ✅ Pass MSKU to getNextAvailableFnsku
                $fnskuInfo = $this->getNextAvailableFnsku(
                    $baseFnsku,
                    $msku,
                    $asin,
                    $fnskuRecord->grading,
                    $fnskuRecord->storename
                );
            } catch (\Exception $e) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'reason' => 'fnsku_exhausted',
                ]);
            }

            $actualFnskuToUse = $fnskuInfo['actual_fnsku'];
            $actualMskuToUse = $fnskuInfo['actual_msku'];

            $maxxrt = DB::table($this->productTable)->max('rtcounter');
            $newrt = $maxxrt + 1;

            Log::info('✅ Creating new item with FNSKU, MSKU, and ASIN', [
                'rt' => $newrt,
                'serial' => $serial,
                'base_fnsku' => $baseFnsku,
                'actual_fnsku' => $actualFnskuToUse,
                'msku' => $actualMskuToUse,
                'asin' => $asin,
                'location' => $location,
            ]);

            // ✅ Insert with FNSKU, MSKU, and ASIN
            $newItemId = DB::table($this->productTable)->insertGetId([
                'rtcounter' => $newrt,
                'serialnumber' => $serial,
                'ProductModuleLoc' => $modulelocation,
                'warehouselocation' => $location,
                'FNSKUviewer' => $actualFnskuToUse,
                'MSKUviewer' => $actualMskuToUse,  // ✅ Set MSKU from lookup
                'ASINviewer' => $asin,              // ✅ Set ASIN from lookup
                'FbmAvailable' => 1,
                'Fulfilledby' => 'FBM',
                'validation_status' => 'validated',
                'quantity' => 1,
                'stockroom_insert_date' => $curentDatetimeString,
            ]);

            // ✅ Deduct FNSKU units using MSKU
            $this->updateFnskuUnits(
                $msku,
                $asin,
                $fnskuRecord->grading,
                $fnskuRecord->storename
            );

            DB::table($this->itemProcessHistoryTable)->insert([
                'rtcounter' => $newrt,
                'employeeName' => $User,
                'editDate' => $curentDatetimeString,
                'Module' => $Module,
                'Action' => $Action,
            ]);

            $employeeName = auth()->user()->username ?? $User ?? 'System';
            $this->trackCreate(
                'Stockroom',
                "RT#{$newrt} | Serial: {$serial} | FNSKU: {$actualFnskuToUse} | MSKU: {$actualMskuToUse}",
                $employeeName
            );

            DB::commit();
            $this->clearStockroomCaches();

            return response()->json([
                'success' => true,
                'message' => 'New item scanned and inserted successfully',
                'fnsku_used' => $actualFnskuToUse,
                'msku_used' => $actualMskuToUse,
                'asin' => $asin,
                'remaining_units' => $fnskuInfo['remaining_units'],
                'new_item' => true,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Unhandled error in processScan', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing scan: '.$e->getMessage(),
                'reason' => 'server_error',
            ], 500);
        }
    }

    public function checkSerial(Request $request)
    {
        $serial = $request->input('serial');

        if (empty($serial)) {
            return response()->json([
                'exists' => false,
                'message' => 'Serial is required',
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
                    'fnsku' => $item->FNSKUviewer,
                ]);

                return response()->json([
                    'exists' => true,
                    'location' => $item->ProductModuleLoc,
                    'rtcounter' => $item->rtcounter,
                    'fnsku' => $item->FNSKUviewer,
                    'validation_status' => $item->validation_status ?? null,
                ]);
            } else {
                Log::info('❌ Serial not found', ['serial' => $serial]);

                return response()->json([
                    'exists' => false,
                    'message' => 'Serial not found in system (new item)',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error checking serial', [
                'serial' => $serial,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'exists' => false,
                'error' => 'Error checking serial: '.$e->getMessage(),
            ], 500);
        }
    }

    // Continue with other methods (mergeItems, updateLocation, etc.) - they remain the same
    // but I'll include them for completeness

    /**
     * ✅ COMPLETE: Merge items with MSKU-based FNSKU operations
     */
    public function mergeItems(Request $request)
    {
        Log::info('🔍 MERGE DEBUG: Request received', $request->all());

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'title' => 'sometimes|string',
            'productId' => 'sometimes|integer',
            'asin' => 'sometimes|string',
            'store' => 'sometimes|string',
            'serialNumbers' => 'sometimes|array',
            'fnsku' => 'nullable|string',
            'msku' => 'nullable|string',
        ]);

        $selectedIds = $request->items;
        $numOfSerial = count($selectedIds);

        Log::info('🔍 MERGE DEBUG: Validated data', [
            'selected_ids' => $selectedIds,
            'num_items' => $numOfSerial,
        ]);

        if (empty($selectedIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No selected items to merge.',
            ]);
        }

        try {
            DB::beginTransaction();

            Log::info('🔍 MERGE DEBUG: Transaction started');

            // Get selected items
            $serialNumberResults = DB::table($this->productTable.' as prod')
                ->select('prod.*')
                ->whereIn('prod.ProductID', $selectedIds)
                ->get();

            Log::info('🔍 MERGE DEBUG: Products fetched', [
                'count' => $serialNumberResults->count(),
                'product_ids' => $serialNumberResults->pluck('ProductID')->toArray(),
            ]);

            if ($serialNumberResults->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No products found with the selected IDs',
                ]);
            }

            // Enrich with FNSKU data using MSKU
            $serialNumberResults = $serialNumberResults->map(function ($item) {
                $msku = $item->MSKUviewer;

                Log::info('🔍 MERGE DEBUG: Enriching product', [
                    'ProductID' => $item->ProductID,
                    'MSKUviewer' => $msku,
                ]);

                if (! empty($msku)) {
                    $fnskuRecord = DB::table($this->fnskuTable)
                        ->where('MSKU', $msku)
                        ->first();

                    if ($fnskuRecord) {
                        $item->ASIN = $fnskuRecord->ASIN;
                        $item->grading = $fnskuRecord->grading;
                        $item->storename = $fnskuRecord->storename;
                        $item->FNSKU = $fnskuRecord->FNSKU;

                        Log::info('🔍 MERGE DEBUG: FNSKU record found', [
                            'MSKU' => $msku,
                            'ASIN' => $fnskuRecord->ASIN,
                            'grading' => $fnskuRecord->grading,
                        ]);

                        $asinRecord = DB::table($this->asinTable)
                            ->where('ASIN', $fnskuRecord->ASIN)
                            ->first();

                        if ($asinRecord) {
                            $item->ProductTitle = $asinRecord->internal;
                            $item->color = $asinRecord->color;
                            $item->QuantityInside = $asinRecord->QuantityInside;

                            Log::info('🔍 MERGE DEBUG: ASIN record found', [
                                'ASIN' => $fnskuRecord->ASIN,
                                'title' => $asinRecord->internal,
                                'QuantityInside' => $asinRecord->QuantityInside,
                            ]);
                        } else {
                            Log::warning('🔍 MERGE DEBUG: ASIN record NOT found', [
                                'ASIN' => $fnskuRecord->ASIN,
                            ]);
                        }
                    } else {
                        Log::warning('🔍 MERGE DEBUG: FNSKU record NOT found', [
                            'MSKU' => $msku,
                        ]);
                    }
                } else {
                    Log::warning('🔍 MERGE DEBUG: MSKUviewer is empty', [
                        'ProductID' => $item->ProductID,
                    ]);
                }

                return $item;
            });

            // Validation checks
            $firstItem = $serialNumberResults->first();
            $firstAsin = $firstItem->ASIN ?? null;
            $firstColor = $firstItem->color ?? null;
            $firstQuantityInside = $firstItem->QuantityInside ?? 1;
            $firstTitle = $firstItem->ProductTitle ?? '';
            $firstStoreName = $firstItem->storename ?? '';
            $firstCondition = $firstItem->grading ?? '';

            Log::info('🔍 MERGE DEBUG: First item validation data', [
                'first_asin' => $firstAsin,
                'first_color' => $firstColor,
                'first_quantity_inside' => $firstQuantityInside,
                'first_title' => $firstTitle,
                'first_storename' => $firstStoreName,
                'first_condition' => $firstCondition,
                'total_items' => $numOfSerial,
            ]);

            if (empty($firstAsin)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Cannot merge: First item has no ASIN. Make sure all items have valid MSKU with FNSKU records.',
                    'debug' => [
                        'first_product_id' => $firstItem->ProductID,
                        'first_msku' => $firstItem->MSKUviewer ?? 'null',
                    ],
                ]);
            }

            $incompatibleItems = [];
            foreach ($serialNumberResults as $item) {
                $itemAsin = $item->ASIN ?? null;
                $itemColor = $item->color ?? null;
                $itemQuantityInside = $item->QuantityInside ?? 1;
                $itemSerial = $item->serialnumber;
                $itemStoreName = $item->storename ?? '';
                $itemCondition = $item->grading ?? '';

                if ($itemAsin !== $firstAsin) {
                    $incompatibleItems[] = [
                        'serial' => $itemSerial,
                        'reason' => 'Different ASIN',
                        'expected' => $firstAsin,
                        'actual' => $itemAsin,
                    ];
                }

                if ($itemColor !== $firstColor) {
                    $incompatibleItems[] = [
                        'serial' => $itemSerial,
                        'reason' => 'Different Color',
                        'expected' => $firstColor ?: 'none',
                        'actual' => $itemColor ?: 'none',
                    ];
                }

                if ($itemQuantityInside !== $firstQuantityInside) {
                    $incompatibleItems[] = [
                        'serial' => $itemSerial,
                        'reason' => 'Different QuantityInside',
                        'expected' => $firstQuantityInside,
                        'actual' => $itemQuantityInside,
                    ];
                }

                if ($itemStoreName !== $firstStoreName) {
                    $incompatibleItems[] = [
                        'serial' => $itemSerial,
                        'reason' => 'Different Store',
                        'expected' => $firstStoreName ?: 'none',
                        'actual' => $itemStoreName ?: 'none',
                    ];
                }

                if ($itemCondition !== $firstCondition) {
                    $incompatibleItems[] = [
                        'serial' => $itemSerial,
                        'reason' => 'Different Condition',
                        'expected' => $firstCondition ?: 'none',
                        'actual' => $itemCondition ?: 'none',
                    ];
                }
            }

            if (! empty($incompatibleItems)) {
                DB::rollBack();

                Log::warning('🔍 MERGE DEBUG: Validation failed - incompatible items', [
                    'incompatible_items' => $incompatibleItems,
                ]);

                $errorMessage = "Cannot merge items - incompatible products detected:\n";
                foreach ($incompatibleItems as $issue) {
                    $errorMessage .= "- Serial {$issue['serial']}: {$issue['reason']} (Expected: {$issue['expected']}, Got: {$issue['actual']})\n";
                }

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'reason' => 'incompatible_items',
                    'incompatible_items' => $incompatibleItems,
                ]);
            }

            Log::info('🔍 MERGE DEBUG: ✅ Validation passed');

            // Collect serial numbers
            $serialNumberA = null;
            $serialNumberB = null;
            $serialNumberC = null;
            $serialNumberD = null;
            $totalPrice = 0;

            $title = $request->title ?? '';
            $productAsin = $request->asin ?? '';
            $firstStore = $request->store ?? '';
            $providedFnsku = $request->fnsku ?? '';
            $providedMsku = $request->msku ?? '';

            if (! empty($providedFnsku)) {
                $providedFnsku = $this->normalizeFnsku($providedFnsku);
            }

            $orderedSerials = [];

            foreach ($selectedIds as $productId) {
                $matchingItem = $serialNumberResults->firstWhere('ProductID', $productId);
                if ($matchingItem) {
                    $orderedSerials[] = $matchingItem->serialnumber;
                    $totalPrice += $matchingItem->price ?? 0;

                    if (empty($title)) {
                        $title = $matchingItem->ProductTitle ?? $matchingItem->AStitle ?? '';
                        $firstStore = $matchingItem->storename ?? $matchingItem->StoreName ?? '';
                    }
                }
            }

            if (count($orderedSerials) > 0) {
                $serialNumberA = $orderedSerials[0];
            }
            if (count($orderedSerials) > 1) {
                $serialNumberB = $orderedSerials[1];
            }
            if (count($orderedSerials) > 2) {
                $serialNumberC = $orderedSerials[2];
            }
            if (count($orderedSerials) > 3) {
                $serialNumberD = $orderedSerials[3];
            }

            Log::info('🔍 MERGE DEBUG: Serials collected', [
                'serials' => $orderedSerials,
                'title' => $title,
            ]);

            preg_match('/\((.*?)\)/', $title, $matches);
            $colorFromTitle = isset($matches[1]) ? $matches[1] : $firstColor;

            $baseTitle = trim(preg_replace('/\s*\(.*?\)\s*/', '', $title));
            $baseTitle = trim(preg_replace('/\s+\d+-Pack\s*/', ' ', $baseTitle));

            $targetQuantityInside = $numOfSerial * $firstQuantityInside;

            Log::info('🔍 MERGE DEBUG: Pack calculation', [
                'base_title' => $baseTitle,
                'color_from_title' => $colorFromTitle,
                'target_quantity_inside' => $targetQuantityInside,
            ]);

            $allowedPackSizes = [2, 4];

            if (! in_array($targetQuantityInside, $allowedPackSizes)) {
                DB::rollBack();

                Log::warning('🔍 MERGE DEBUG: Invalid pack size', [
                    'target_quantity_inside' => $targetQuantityInside,
                    'allowed' => $allowedPackSizes,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Cannot merge: Invalid pack size.\n\n".
                                "You are trying to create a {$targetQuantityInside}-pack, but only 2-pack and 4-pack merges are allowed.\n\n".
                                "Current selection:\n".
                                "- {$numOfSerial} items selected\n".
                                "- Each item contains {$firstQuantityInside} unit(s)\n".
                                "- Target pack size: {$targetQuantityInside}-pack\n\n".
                                "To create a 2-pack: Select 2 single items\n".
                                'To create a 4-pack: Select 4 single items or 2 double items',
                    'reason' => 'invalid_pack_size',
                    'target_pack_size' => $targetQuantityInside,
                    'allowed_pack_sizes' => $allowedPackSizes,
                ]);
            }

            // Find exact matching ASIN for pack
            $asinResult = DB::table($this->asinTable)
                ->where('internal', 'like', '%'.$baseTitle.'%')
                ->where('QuantityInside', $targetQuantityInside)
                ->where(function ($query) use ($colorFromTitle) {
                    if (! empty($colorFromTitle)) {
                        $query->where('color', 'like', '%'.$colorFromTitle.'%');
                    }
                })
                ->first();

            Log::info('🔍 MERGE DEBUG: Pack ASIN search', [
                'base_title' => $baseTitle,
                'target_quantity' => $targetQuantityInside,
                'color' => $colorFromTitle,
                'found' => $asinResult ? 'YES' : 'NO',
            ]);

            if (! $asinResult) {
                DB::rollBack();

                Log::error('🔍 MERGE DEBUG: No pack ASIN found');

                return response()->json([
                    'success' => false,
                    'message' => "Cannot merge: No exact matching pack ASIN found.\n\n".
                                "Required:\n".
                                "- Title: {$baseTitle}\n".
                                "- Pack Size: {$targetQuantityInside}-pack\n".
                                '- Color: '.($colorFromTitle ?: 'Any')."\n\n".
                                "Please ensure a {$targetQuantityInside}-pack variant exists in the database before merging.",
                    'reason' => 'no_exact_asin_match',
                    'required' => [
                        'title' => $baseTitle,
                        'quantity_inside' => $targetQuantityInside,
                        'color' => $colorFromTitle,
                    ],
                ]);
            }

            $asinTitle = $asinResult->internal;
            $targetAsin = $asinResult->ASIN;
            $asinColor = $asinResult->color ?? '';
            $asinQuantityInside = $asinResult->QuantityInside ?? 1;
            $store = $firstStore;

            $constructedTitle = $asinTitle;

            if ($asinQuantityInside > 1 && stripos($constructedTitle, '-pack') === false) {
                $constructedTitle .= ' '.$asinQuantityInside.'-Pack';
            }

            if (! empty($asinColor) && stripos($constructedTitle, '('.$asinColor.')') === false) {
                $constructedTitle .= ' ('.$asinColor.')';
            }

            Log::info('🔍 MERGE DEBUG: Pack ASIN found', [
                'target_asin' => $targetAsin,
                'title' => $constructedTitle,
            ]);

            // Find pack FNSKU
            $condition = $firstCondition;
            $storename = $firstStoreName;
            $packFnsku = null;

            Log::info('🔍 MERGE DEBUG: Starting pack FNSKU search', [
                'target_asin' => $targetAsin,
                'condition' => $condition,
                'storename' => $storename,
                'quantity_inside' => $asinQuantityInside,
            ]);

            try {
                // Priority 1: Search by MSKU if provided
                if (! empty($providedMsku)) {
                    Log::info('🔍 MERGE DEBUG: Searching by provided MSKU', [
                        'provided_msku' => $providedMsku,
                    ]);

                    $packFnsku = DB::table($this->fnskuTable.' as fnsku')
                        ->select('fnsku.*')
                        ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                        ->where('fnsku.MSKU', $providedMsku)
                        ->where('fnsku.fnsku_status', 'Available')
                        ->whereIn('fnsku.amazon_status', ['Active', 'Notposted'])
                        ->where('fnsku.LimitStatus', 'False')
                        ->where('fnsku.Units', '>', 0)
                        ->where('asin.quantityinside', $asinQuantityInside)
                        ->where('fnsku.grading', $condition)
                        ->where('fnsku.storename', $storename)
                        ->when($asinColor, function ($query) use ($asinColor) {
                            return $query->where('asin.color', $asinColor);
                        })
                        ->first();

                    Log::info('🔍 MERGE DEBUG: MSKU search result', [
                        'found' => $packFnsku ? 'YES' : 'NO',
                    ]);
                }

                // Priority 2: Search by ASIN
                if (! $packFnsku) {
                    Log::info('🔍 MERGE DEBUG: Searching by ASIN');

                    $packFnsku = DB::table($this->fnskuTable.' as fnsku')
                        ->select('fnsku.*')
                        ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                        ->where('fnsku.ASIN', $targetAsin)
                        ->where('fnsku.fnsku_status', 'Available')
                        ->whereIn('fnsku.amazon_status', ['Active', 'Notposted'])
                        ->where('fnsku.LimitStatus', 'False')
                        ->where('fnsku.Units', '>', 0)
                        ->where('asin.quantityinside', $asinQuantityInside)
                        ->where('fnsku.grading', $condition)
                        ->where('fnsku.storename', $storename)
                        ->when($asinColor, function ($query) use ($asinColor) {
                            return $query->where('asin.color', $asinColor);
                        })
                        ->orderByDesc('fnsku.FNSKUID')
                        ->first();

                    Log::info('🔍 MERGE DEBUG: ASIN search result', [
                        'found' => $packFnsku ? 'YES' : 'NO',
                    ]);
                }

                // Priority 3: Related ASINs
                if (! $packFnsku) {
                    Log::info('🔍 MERGE DEBUG: Trying related ASINs');

                    $relatedAsins = $this->findRelatedAsins($targetAsin);

                    Log::info('🔍 MERGE DEBUG: Related ASINs found', [
                        'count' => count($relatedAsins),
                        'asins' => $relatedAsins,
                    ]);

                    if (! empty($relatedAsins)) {
                        $packFnsku = DB::table($this->fnskuTable.' as fnsku')
                            ->select('fnsku.*')
                            ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                            ->whereIn('fnsku.ASIN', $relatedAsins)
                            ->where('fnsku.fnsku_status', 'Available')
                            ->whereIn('fnsku.amazon_status', ['Active', 'Notposted'])
                            ->where('fnsku.LimitStatus', 'False')
                            ->where('fnsku.Units', '>', 0)
                            ->where('asin.quantityinside', $asinQuantityInside)
                            ->where('fnsku.grading', $condition)
                            ->where('fnsku.storename', $storename)
                            ->when($asinColor, function ($query) use ($asinColor) {
                                return $query->where('asin.color', $asinColor);
                            })
                            ->orderByDesc('fnsku.FNSKUID')
                            ->first();

                        Log::info('🔍 MERGE DEBUG: Related ASIN search result', [
                            'found' => $packFnsku ? 'YES' : 'NO',
                        ]);
                    }
                }

                if (! $packFnsku) {
                    DB::rollBack();

                    Log::error('🔍 MERGE DEBUG: No pack FNSKU found after all searches');

                    return response()->json([
                        'success' => false,
                        'message' => "Cannot merge: No available pack FNSKU found.\n\n".
                                    "Required:\n".
                                    "• ASIN: {$targetAsin}\n".
                                    "• Pack Size: {$asinQuantityInside}-pack\n".
                                    '• Color: '.($asinColor ?: 'Any')."\n".
                                    "• Condition: {$condition}\n".
                                    "• Store: {$storename}\n".
                                    "• Status: Available with units\n\n".
                                    'Please create an FNSKU matching all criteria.',
                        'reason' => 'no_pack_fnsku_available',
                    ]);
                }

                Log::info('🔍 MERGE DEBUG: Pack FNSKU found!', [
                    'fnsku' => $packFnsku->FNSKU,
                    'msku' => $packFnsku->MSKU,
                    'units' => $packFnsku->Units,
                ]);

                // Generate prefixed FNSKU
                $fnskuInfo = $this->getNextAvailableFnsku(
                    $packFnsku->FNSKU,
                    $packFnsku->MSKU,
                    $targetAsin,
                    $condition,
                    $storename
                );
                $actualFnskuToUse = $fnskuInfo['actual_fnsku'];
                $actualMskuToUse = $fnskuInfo['actual_msku'];

                Log::info('🔍 MERGE DEBUG: Generated prefixed FNSKU', [
                    'actual_fnsku' => $actualFnskuToUse,
                    'actual_msku' => $actualMskuToUse,
                    'remaining_units' => $fnskuInfo['remaining_units'],
                ]);

            } catch (\Exception $e) {
                DB::rollBack();

                Log::error('🔍 MERGE DEBUG: Exception during FNSKU search', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error getting FNSKU for merge: '.$e->getMessage(),
                ]);
            }

            // Create merge record
            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            $currentDate = $currentDatetime->format('Y-m-d');
            $currentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');

            $mergeId = DB::table('tblmigrateditem')->insertGetId([
                'migratedDate' => $currentDate,
            ]);

            $maxRt = DB::table($this->productTable)->max('rtcounter') ?? 0;
            $newRt = $maxRt + 1;

            Log::info('🔍 MERGE DEBUG: Creating merged product', [
                'merge_id' => $mergeId,
                'new_rt' => $newRt,
            ]);

            // Insert merged product
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
                'FNSKUviewer' => $actualFnskuToUse,
                'MSKUviewer' => $actualMskuToUse,
                'ASINviewer' => $targetAsin,
            ];

            $productId = DB::table($this->productTable)->insertGetId($productData);

            Log::info('🔍 MERGE DEBUG: Merged product created', [
                'product_id' => $productId,
            ]);

            // Update FNSKU units
            $becameUnavailable = $this->updateFnskuUnits(
                $actualMskuToUse,
                $targetAsin,
                $condition,
                $storename
            );

            Log::info('🔍 MERGE DEBUG: FNSKU units updated', [
                'became_unavailable' => $becameUnavailable,
            ]);

            // Update original items to Merged status
            DB::table($this->productTable)
                ->whereIn('ProductID', $selectedIds)
                ->update([
                    'ProductModuleLoc' => 'Merged',
                    'mergedTO' => $newRt,
                ]);

            Log::info('🔍 MERGE DEBUG: Original items marked as Merged');

            DB::commit();
            $this->clearStockroomCaches();

            $employeeName = auth()->user()->username ?? 'System';
            $originalRTs = $serialNumberResults->pluck('rtcounter')->toArray();

            $this->trackHistory(
                'Stockroom',
                'Merge Items',
                "Merged {$numOfSerial} items | Original RTs: ".implode(', ', $originalRTs),
                "Created RT#{$newRt} | FNSKU: {$actualFnskuToUse} | MSKU: {$actualMskuToUse} | {$targetQuantityInside}-pack",
                $employeeName
            );

            Log::info('🔍 MERGE DEBUG: ✅ Transaction committed successfully!');

            return response()->json([
                'success' => true,
                'message' => 'Items merged successfully.',
                'newrt' => $newRt,
                'SERIAL' => $serialNumberA,
                'productid' => $productId,
                'store' => $store,
                'title' => $constructedTitle,
                'fnsku' => $actualFnskuToUse,
                'msku' => $actualMskuToUse,
                'asin' => $targetAsin,
                'units' => $fnskuInfo['remaining_units'],
                'merged_items_count' => count($selectedIds),
                'asin_data' => [
                    'ASIN' => $targetAsin,
                    'color' => $asinColor,
                    'QuantityInside' => $asinQuantityInside,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('🔍 MERGE DEBUG: ❌ FATAL ERROR', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error during merge operation: '.$e->getMessage(),
                'debug' => [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile()),
                ],
            ], 500);
        }
    }

    private function updateFnskuUnits($msku, $asin, $grading, $storename)
    {
        // Decrement the units using MSKU
        $affected = DB::table($this->fnskuTable)
            ->where('MSKU', $msku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->where('Units', '>', 0)
            ->decrement('Units');

        if ($affected == 0) {
            throw new \Exception("Could not update FNSKU units for MSKU: {$msku} - no available units");
        }

        // Check if FNSKU should become unavailable
        $updatedRecord = DB::table($this->fnskuTable)
            ->where('MSKU', $msku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->first();

        $this->updateFnskuLimitStatus($asin, $msku);

        $becameUnavailable = false;
        if ($updatedRecord && $updatedRecord->Units <= 0) {
            DB::table($this->fnskuTable)
                ->where('MSKU', $msku)
                ->where('ASIN', $asin)
                ->where('grading', $grading)
                ->where('storename', $storename)
                ->update(['fnsku_status' => 'Unavailable']);
            $becameUnavailable = true;
        }

        return $becameUnavailable;
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
                    'message' => 'No valid item IDs provided.',
                ]);
            }

            // 🔥 GET PRODUCT INFO BEFORE UPDATE
            $products = DB::table($this->productTable)
                ->whereIn('ProductID', $idsToUpdate)
                ->get();

            DB::table($this->productTable)
                ->whereIn('ProductID', $idsToUpdate)
                ->update([
                    'warehouselocation' => $request->newLocation,
                ]);

            // 🔥 ADD HISTORY TRACKING
            $employeeName = auth()->user()->username ?? 'System';
            foreach ($products as $product) {
                $oldLocation = $product->warehouselocation ?? 'Unknown';
                $this->trackHistory(
                    'Stockroom',
                    'Location Update',
                    "RT#{$product->rtcounter} | Location: {$oldLocation}",
                    "RT#{$product->rtcounter} | Location: {$request->newLocation}",
                    $employeeName
                );
            }

            DB::commit();
            $this->clearStockroomCaches();

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully.',
                'count' => count($idsToUpdate),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error updating location: '.$e->getMessage(),
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
                'message' => 'No items selected.',
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

            if (! $fnsku) {
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
            'invalid' => $invalid,
        ]);
    }

    private function mskuPostToAmazon(array $items, $marketplace, $fulfillmentChannel, $currency, $price)
    {
        require_once base_path('automations/bulk_msku_creation.php');

        if (empty($items)) {
            echo 'No items to post.<br>';

            return;
        }

        $grouped = [];
        foreach ($items as $product) {
            // MODIFIED: Extract base FNSKU before lookup
            $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);

            $fnsku = DB::table('tblfnsku')
                ->where('FNSKU', $baseFnsku) // Use base FNSKU for lookup
                ->first();

            if (! $fnsku) {
                continue;
            }

            $msku = $fnsku->MSKU;
            if (! isset($grouped[$msku])) {
                $grouped[$msku] = [
                    'msku' => $msku,
                    'asin' => $fnsku->ASIN,
                    'storename' => $fnsku->storename,
                    'grading' => $fnsku->grading,
                    'condition' => strtolower(str_replace(' ', '_', $fnsku->Condition ?? 'new_new')),
                    'count' => 0,
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
            echo 'No valid MSKUs found.<br>';

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
                                'user_ids' => [session('userid')],
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
            if (! isset($productTypeCache[$asinKey])) {
                $response = Http::get(url('/amzn/catalog/get_asin_catalog'), [
                    'searchedAsin' => $asinKey,
                    'store' => $data['storename'],
                    'destinationMarketplace' => $marketplace,
                ]);

                $productTypeCache[$asinKey] = 'generic';
                if ($response->successful()) {
                    $result = $response->json();
                    $productTypeCache[$asinKey] = $result['results'][0]['rates']['productTypes'][0]['productType'] ?? 'generic';
                }
            }

            $productType = $productTypeCache[$asinKey];

            $feedItems[] = [
                'messageId' => $messageId++,
                'operationType' => 'UPDATE',
                'sku' => $data['msku'],
                'productType' => $productType,
                'requirements' => 'LISTING_OFFER_ONLY',
                'attributes' => [
                    'condition_type' => [
                        [
                            'value' => $amzncondition,
                            'marketplace_id' => $marketplace,
                        ],
                    ],
                    'fulfillment_availability' => [
                        [
                            'fulfillment_channel_code' => $fulfillmentChannel,
                            'marketplace_id' => $marketplace,
                            'quantity' => $data['count'],
                        ],
                    ],
                    'merchant_suggested_asin' => [
                        [
                            'value' => $data['asin'],
                            'marketplace_id' => $marketplace,
                        ],
                    ],
                    'list_price' => [
                        [
                            'currency' => $currency,
                            'value' => 0,
                            'marketplace_id' => $marketplace,
                        ],
                    ],
                    'purchasable_offer' => [
                        [
                            'currency' => $currency,
                            'audience' => 'ALL',
                            'our_price' => [
                                [
                                    'schedule' => [
                                        [
                                            'value_with_tax' => (float) $price,
                                        ],
                                    ],
                                ],
                            ],
                            'marketplace_id' => $marketplace,
                        ],
                    ],
                ],
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
            'messages' => $feedItems,
        ];

        echo '<pre>';
        print_r($payload);
        echo '</pre>';

        $feedDataJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $uploadSuccess = upload_feed_to_amazon_s3($createdocumentid_data['data']['url'], $feedDataJson);

        echo "Rawr $uploadSuccess";

        $payload = [
            'feedType' => 'JSON_LISTINGS_FEED',
            'marketplaceIds' => [$marketplace],
            'inputFeedDocumentId' => $feeddocumentid,
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

            if (! $today) {
                $today = (new DateTime('now', $timezone))->format('Y-m-d');
            }

            Log::info('🔍 Fetching count for date: '.$today);

            // Don't use cache for fresh scans
            $cacheKey = 'new_scanned_count_'.$today;
            Cache::forget($cacheKey); // Clear any existing cache

            $count = DB::table($this->productTable)
                ->where('ProductModuleLoc', 'Stockroom')
                ->whereDate('stockroom_insert_date', $today)
                ->whereNotNull('stockroom_insert_date')
                ->count();

            Log::info('✅ Count result: '.$count.' for date: '.$today);

            // Cache for only 10 seconds to allow quick refresh
            Cache::put($cacheKey, $count, 10);

            return response()->json([
                'success' => true,
                'count' => $count,
                'date' => $today,
                'timezone' => 'America/Los_Angeles',
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error fetching count: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching count: '.$e->getMessage(),
                'count' => 0,
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
            $items = DB::table($this->productTable.' as prod')
                ->select([
                    'prod.ProductID',
                    'prod.rtcounter',
                    'prod.warehouselocation',
                    'prod.stockroom_insert_date',
                    'prod.FNSKUviewer',
                    'prod.amzn_status',
                    'prod.shipment_tracking_number',
                    'hist.employeeName',
                ])
                ->join($this->itemProcessHistoryTable.' as hist', 'prod.rtcounter', '=', 'hist.rtcounter')
                ->where(function ($query) {
                    $query->where('hist.Action', 'Scanned and insert to Stockroom')
                        ->orWhere('hist.Action', 'Move Item to Stockroom');
                })
                ->where('prod.ProductModuleLoc', 'Stockroom')
                ->whereBetween(DB::raw('DATE(prod.stockroom_insert_date)'), [$startDate, $endDate])
                ->orderBy('prod.stockroom_insert_date', 'desc')
                ->get();

            // Process items to add FNSKU data
            $processedItems = $items->map(function ($item) {
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
                'endDate' => $endDate,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching new scanned items: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching items: '.$e->getMessage(),
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
                'status' => 'nullable|string',
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
                'status' => $status,
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating FBM status: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating status: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Print label method - MODIFIED to handle prefixed FNSKUs
     */
    public function printLabel($productId)
    {
        try {
            $response = axios()->post('/api/stockroom/print-label', [
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
                    'message' => 'Label printing started.',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error: '.$response['message'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error printing label: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to print label. Please try again.',
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
            'items' => 'required|array|min:1',
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
                    'processed_by' => $user,
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
                    'Action' => "Processed - {$shipmentType}",
                ]);
            }

            DB::commit();

            // ✅ CLEAR CACHES IMMEDIATELY AFTER COMMIT
            $this->clearStockroomCaches();

            return response()->json([
                'success' => true,
                'message' => "Successfully processed {$productInfo->count()} items",
                'processed_count' => $productInfo->count(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing items: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error processing items: '.$e->getMessage(),
            ], 500);
        }
    }

    public function unmergeItem(Request $request)
    {
        $validated = $request->validate([
            'productId' => 'required|integer',
        ]);

        try {
            DB::beginTransaction();

            $mergedItem = DB::table($this->productTable)
                ->where('ProductID', $validated['productId'])
                ->where('ProductModuleLoc', 'Stockroom')
                ->first();

            if (! $mergedItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found or not in Stockroom',
                ]);
            }

            if (empty($mergedItem->mergeID)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This item is not a merged item',
                ]);
            }

            $mergeId = $mergedItem->mergeID;
            $rtCounter = $mergedItem->rtcounter;

            $originalItems = DB::table($this->productTable)
                ->where('mergedTO', $rtCounter)
                ->where('ProductModuleLoc', 'Merged')
                ->get();

            if ($originalItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No original items found to restore',
                ]);
            }

            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            $currentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
            $user = $this->getCurrentUserName();

            $restoredCount = 0;
            foreach ($originalItems as $item) {
                DB::table($this->productTable)
                    ->where('ProductID', $item->ProductID)
                    ->update([
                        'ProductModuleLoc' => 'Stockroom',
                        'mergedTO' => null,
                        'stockroom_insert_date' => $currentDatetimeString,
                    ]);

                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $item->rtcounter,
                    'employeeName' => $user,
                    'editDate' => $currentDatetimeString,
                    'Module' => 'Stockroom',
                    'Action' => 'Unmerged - Restored to Stockroom',
                ]);

                $restoredCount++;
            }

            // ✅ ONLY Return the 1 unit to the pack FNSKU (reverse the merge deduction)
            if (! empty($mergedItem->MSKUviewer) && ! empty($mergedItem->ASINviewer)) {
                $returnSuccess = $this->returnFnskuUnits(
                    $mergedItem->MSKUviewer,  // Pack MSKU
                    $mergedItem->ASINviewer   // Pack ASIN
                );

                if ($returnSuccess) {
                    Log::info('✅ Returned 1 unit to pack FNSKU after unmerge', [
                        'pack_msku' => $mergedItem->MSKUviewer,
                        'pack_asin' => $mergedItem->ASINviewer,
                        'pack_fnsku' => $mergedItem->FNSKUviewer,
                        'rt_counter' => $rtCounter,
                        'action' => 'Reversed merge deduction',
                    ]);
                } else {
                    Log::warning('⚠️ Failed to return unit to pack FNSKU', [
                        'pack_msku' => $mergedItem->MSKUviewer,
                        'pack_asin' => $mergedItem->ASINviewer,
                        'pack_fnsku' => $mergedItem->FNSKUviewer,
                    ]);
                }
            } else {
                Log::warning('⚠️ Cannot return unit - Missing pack MSKU or ASIN', [
                    'pack_msku' => $mergedItem->MSKUviewer ?? 'null',
                    'pack_asin' => $mergedItem->ASINviewer ?? 'null',
                    'pack_fnsku' => $mergedItem->FNSKUviewer ?? 'null',
                    'rt_counter' => $rtCounter,
                ]);
            }

            // ❌ DO NOT deduct units from original items - they keep their existing state

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
                'Action' => "Unmerged - Deleted merged item, restored {$restoredCount} original items",
            ]);

            $employeeName = auth()->user()->username ?? $user ?? 'System';
            $this->trackHistory(
                'Stockroom',
                'Unmerge Items',
                "RT#{$rtCounter} | {$restoredCount}-pack | FNSKU: {$mergedItem->FNSKUviewer}",
                "Restored {$restoredCount} individual items to Stockroom | Returned 1 pack unit",
                $employeeName
            );

            // Delete merge record
            DB::table('tblmigrateditem')
                ->where('migrateID', $mergeId)
                ->delete();

            DB::commit();
            $this->clearStockroomCaches();

            return response()->json([
                'success' => true,
                'message' => "Successfully unmerged item. Restored {$restoredCount} original items to Stockroom.",
                'restored_count' => $restoredCount,
                'units_returned' => ! empty($mergedItem->MSKUviewer) && ! empty($mergedItem->ASINviewer),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error unmerging item: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error unmerging item: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Verify returnFnskuUnits signature (for reference)
     */

    /**
     * Return FNSKU units (increment by 1) - helper for unmerge
     */
    private function returnFnskuUnits($mskuViewer, $asinViewer)
    {
        if (empty($mskuViewer) || empty($asinViewer)) {
            Log::warning('Missing MSKU or ASIN for unit return', [
                'msku' => $mskuViewer,
                'asin' => $asinViewer,
            ]);

            return false;
        }

        // Find the FNSKU record using MSKU
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('MSKU', $mskuViewer)
            ->where('ASIN', $asinViewer)
            ->first();

        if (! $fnskuRecord) {
            Log::warning('FNSKU record not found for return', [
                'msku' => $mskuViewer,
                'asin' => $asinViewer,
            ]);

            return false;
        }

        // Increment the units (return the unit)
        DB::table($this->fnskuTable)
            ->where('MSKU', $mskuViewer)
            ->where('ASIN', $asinViewer)
            ->update([
                'Units' => DB::raw('Units + 1'),
                'fnsku_status' => 'Available',
            ]);

        $this->updateFnskuLimitStatus($asinViewer, $mskuViewer);

        Log::info('Successfully returned 1 unit using MSKU', [
            'msku' => $mskuViewer,
            'asin' => $asinViewer,
            'fnsku' => $fnskuRecord->FNSKU,
        ]);

        return true;
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
                        $cacheKey = "stockroom_inventory_{$page}_{$perPage}_{$store}_".md5('');
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
                $countCacheKey = 'new_scanned_count_'.$dateString;

                if (Cache::has($countCacheKey)) {
                    Cache::forget($countCacheKey);
                    $clearedCount++;
                }
            }

            Log::info("✅ Cleared {$clearedCount} stockroom cache entries");

            return true;

        } catch (\Exception $e) {
            Log::error('❌ Error clearing caches: '.$e->getMessage());

            return false;
        }
    }

    public function updateFnskuLimitStatus($asin, $msku)
    {

        // get asin limit
        $asinLimit = (int) (DB::table($this->asinTable)
            ->where('ASIN', $asin)
            ->value('asin_limit') ?? 0);

        // get current units
        $currentUnits = (int) DB::table($this->fnskuTable)
            ->where('MSKU', $msku)
            ->where('ASIN', $asin)
            ->value('Units');

        $maximumUnits = 10;
        $usedUnits = max(0, $maximumUnits - $currentUnits);

        DB::table($this->fnskuTable)
            ->where('MSKU', $msku)
            ->where('ASIN', $asin)
            ->update(['LimitStatus' => ($asinLimit > 0 && $usedUnits >= $asinLimit) ? 'True' : 'False']);

    }

    /**
     * Move selected items back to Labeling module
     */
    public function moveBackToLabeling(Request $request)
    {
        try {
            // Validate the incoming request
            $validated = $request->validate([
                'itemIds' => 'required|array|min:1',
                'itemIds.*' => 'required|integer',
                'reason' => 'nullable|string|max:500',
            ]);

            $itemIds = $validated['itemIds'];
            $reason = $validated['reason'] ?? null;

            DB::beginTransaction();

            // Get product details before update
            $products = DB::table($this->productTable)
                ->whereIn('ProductID', $itemIds)
                ->where('ProductModuleLoc', 'Stockroom')
                ->get();

            if ($products->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No valid items found in Stockroom to move',
                ], 404);
            }

            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            $currentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
            $user = $this->getCurrentUserName();
            $employeeName = auth()->user()->username ?? $user ?? 'System';

            // Update items to Labeling
            DB::table($this->productTable)
                ->whereIn('ProductID', $itemIds)
                ->update([
                    'ProductModuleLoc' => 'Labeling',
                    'lastDateUpdate' => $currentDatetimeString,
                    'validation_status' => 'pending', // Reset validation status
                ]);

            // Track each item individually
            foreach ($products as $product) {
                // Insert into old history table
                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $product->rtcounter,
                    'employeeName' => $user,
                    'editDate' => $currentDatetimeString,
                    'Module' => 'Stockroom',
                    'Action' => 'Moved back to Labeling'.($reason ? " | Reason: {$reason}" : ''),
                ]);

                // Track with new history system
                $identifier = "RT#{$product->rtcounter}".
                              (! empty($product->serialnumber) ? " | Serial: {$product->serialnumber}" : '');

                if (! empty($product->FNSKUviewer)) {
                    $identifier .= " | FNSKU: {$product->FNSKUviewer}";
                }

                $afterDescription = 'Moved to Labeling';
                if ($reason) {
                    $afterDescription .= " | Reason: {$reason}";
                }

                $this->trackLocationChange(
                    'Stockroom',
                    $identifier,
                    'Stockroom',
                    'Labeling',
                    $employeeName
                );
            }

            DB::commit();
            $this->clearStockroomCaches();

            return response()->json([
                'success' => true,
                'message' => "Successfully moved {$products->count()} item(s) back to Labeling",
                'moved_count' => $products->count(),
                'items' => $products->map(function ($product) {
                    return [
                        'ProductID' => $product->ProductID,
                        'rtcounter' => $product->rtcounter,
                        'serialnumber' => $product->serialnumber,
                    ];
                }),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error moving items back to Labeling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to move items back to Labeling',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
