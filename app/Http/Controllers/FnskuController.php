<?php

namespace App\Http\Controllers;

use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FnskuController extends BasetablesController
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
    }/* */

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
                ->whereIn('amazon_status', ['Active', 'Inactive', 'Notposted'])
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
     * Update FNSKU units after using an FNSKU
     */
private function updateFnskuUnits($msku, $asin, $grading, $storename, $currentFnsku)
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
        
        //check limit status everytime the unit is updating
        $this->updateFnskuLimitStatus($asin, $msku, $currentFnsku);

        $becameUnavailable = false;
        if ($updatedRecord && $updatedRecord->Units <= 0) {
            DB::table($this->fnskuTable)
                ->where('MSKU', $msku)
                ->where('ASIN', $asin)
                ->where('grading', $grading)
                ->where('storename', $storename)
                ->update(['fnsku_status' => 'unavailable']);
            $becameUnavailable = true;
        }

        return $becameUnavailable;
}

    /**
     * Return units to FNSKU (reverse operation)
     */
 private function returnFnskuUnits($mskuViewer, $asinViewer)
    {
        if (empty($mskuViewer) || empty($asinViewer)) {
            Log::warning("Missing MSKU or ASIN for unit return", [
                'msku' => $mskuViewer,
                'asin' => $asinViewer
            ]);
            return false;
        }

        // Find the FNSKU record using MSKU
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('MSKU', $mskuViewer)
            ->where('ASIN', $asinViewer)
            ->first();

        if (!$fnskuRecord) {
            Log::warning("FNSKU record not found for return", [
                'msku' => $mskuViewer,
                'asin' => $asinViewer
            ]);
            return false;
        }

        // Increment the units (return the unit)
        DB::table($this->fnskuTable)
            ->where('MSKU', $mskuViewer)
            ->where('ASIN', $asinViewer)
            ->update([
                'Units' => DB::raw('Units + 1'),
                'fnsku_status' => 'available',
            ]);

        Log::info('Successfully returned 1 unit using MSKU', [
            'msku' => $mskuViewer,
            'asin' => $asinViewer,
            'fnsku' => $fnskuRecord->FNSKU
        ]);

        return true;
 }

    public function index(Request $request)
    {
        $search = $request->query('search');

        // Initialize the query
        $fnskuTable = DB::table($this->fnskuTable);

        // Apply search filters if search parameter exists
        if ($search) {
            $fnskuTable->where(function ($q) use ($search) {
                $q->where('ASIN', 'like', "%{$search}%")
                    ->orWhere('ASIN', 'like', "%{$search}%");

            });
        }

        // Laravel pagination
        $data = $fnskuTable->paginate(10); // 10 items per page

        return response()->json([
            'data' => $data->items(),
            'total' => $data->total(),
            'per_page' => $data->perPage(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
        ]);
    }

    /**
     * UPDATED getFnskuList to handle prefixed FNSKUs in exclusion logic
     */
    public function getFnskuList(Request $request)
    {
        try {
            Log::info('=== FNSKU LIST REQUEST START ===');
            Log::info('Request parameters:', $request->all());

            // Get all filter parameters
            $perPage = min($request->input('limit', 50), 500);
            $search = $request->input('search', '');
            $fnsku = $request->input('fnsku', ''); // FNSKU filter
            $store = $request->input('store', ''); // Store filter
            $grading = $request->input('grading', ''); // Grading filter
            $exclude_assigned = $request->boolean('exclude_assigned', true);

            Log::info('Processed parameters:', [
                'per_page' => $perPage,
                'search' => $search,
                'fnsku' => $fnsku,
                'store' => $store,
                'grading' => $grading,
                'exclude_assigned' => $exclude_assigned,
            ]);

            if (! isset($this->fnskuTable) || ! isset($this->asinTable) || ! isset($this->productTable)) {
                Log::error('Table properties not set');

                return response()->json([
                    'error' => 'Database configuration error',
                    'data' => [],
                ], 500);
            }

            // Build base query
            $query = DB::table($this->fnskuTable.' as fnsku')
                ->select([
                    'fnsku.FNSKU',
                    'fnsku.MSKU',
                    'fnsku.ASIN',
                    'fnsku.grading',
                    'fnsku.Units',
                    'fnsku.storename',
                    'fnsku.fnsku_status',
                    'asin.internal as astitle',
                    'asin.asin_limit as asinLimit'
                ])
                ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->where('fnsku.fnsku_status', 'available')
                ->where('fnsku.Units', '>', 0)
                ->whereNotNull('fnsku.FNSKU')
                ->where('fnsku.FNSKU', '!=', '')
                ->where('fnsku.FNSKU', '!=', 'NULL')
                ->whereNotNull('fnsku.ASIN')
                ->where('fnsku.ASIN', '!=', '')
                ->where('fnsku.ASIN', '!=', 'NULL')
                ->where('fnsku.LimitStatus', 'False')
                ->whereIn('fnsku.amazon_status', ['Active', 'Inactive', 'Notposted']);

            // Apply exclusion logic
            if ($exclude_assigned) {
                $query->whereNotIn('fnsku.FNSKU', function ($subquery) {
                    $subquery->select('FNSKUviewer')
                        ->from($this->productTable)
                        ->whereNotNull('FNSKUviewer')
                        ->where('FNSKUviewer', '!=', '')
                        ->where('FNSKUviewer', '!=', 'NULL');
                });
                Log::info('Exclusion logic applied');
            }

            // STACK ALL FILTERS with AND logic

            // Filter 1: General search (Title or ASIN)
            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('fnsku.ASIN', 'like', "%{$search}%")
                        ->orWhere('asin.internal', 'like', "%{$search}%")
                        ->orWhere('asin.system_title', 'like', "%{$search}%");
                });
                Log::info('Search filter applied:', ['search' => $search]);
            }

            // Filter 2: FNSKU exact or partial match
            if (! empty($fnsku)) {
                $query->where('fnsku.FNSKU', 'like', "%{$fnsku}%");
                Log::info('FNSKU filter applied:', ['fnsku' => $fnsku]);
            }

            // Filter 3: Store filter
            if (! empty($store)) {
                $query->where('fnsku.storename', $store);
                Log::info('Store filter applied:', ['store' => $store]);
            }

            // Filter 4: Grading/Condition filter
            if (! empty($grading)) {
                $query->where('fnsku.grading', $grading);
                Log::info('Grading filter applied:', ['grading' => $grading]);
            }

            // Apply sorting
            if (! empty($search)) {
                // When searching, prioritize exact matches
                $query->orderByRaw('
                CASE 
                    WHEN fnsku.ASIN = ? THEN 1
                    WHEN fnsku.ASIN LIKE ? THEN 2
                    WHEN asin.internal LIKE ? THEN 3
                    ELSE 4
                END, fnsku.FNSKU
            ', [$search, $search.'%', '%'.$search.'%']);
            } else {
                $query->orderBy('fnsku.ASIN')
                    ->orderBy('fnsku.FNSKU');
            }

            Log::info('About to execute query...');

            // Get total count for the filtered results
            $totalCount = $query->count();
            Log::info('Total filtered records:', ['count' => $totalCount]);

            // Paginate
            $fnskuList = $query->simplePaginate($perPage);

            Log::info('Query executed successfully', [
                'current_page_count' => $fnskuList->count(),
                'per_page' => $perPage,
                'current_page' => $fnskuList->currentPage(),
            ]);

            // Filter out any remaining empty FNSKUs
            $filteredItems = $fnskuList->getCollection()->filter(function ($item) {
                return ! empty($item->FNSKU) && $item->FNSKU !== 'NULL' && trim($item->FNSKU) !== '';
            })->values();

            $fnskuList->setCollection($filteredItems);

            Log::info('After filtering empty FNSKUs:', ['count' => $fnskuList->count()]);
            Log::info('=== FNSKU LIST REQUEST END ===');

            return response()->json([
                'data' => $fnskuList->items(),
                'current_page' => $fnskuList->currentPage(),
                'per_page' => $fnskuList->perPage(),
                'has_more_pages' => $fnskuList->hasMorePages(),
                'from' => $fnskuList->firstItem(),
                'to' => $fnskuList->lastItem(),
                'total' => $totalCount,
                'excluded_assigned' => $exclude_assigned,
                'filters_applied' => [
                    'search' => ! empty($search),
                    'fnsku' => ! empty($fnsku),
                    'store' => ! empty($store),
                    'grading' => ! empty($grading),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('=== FNSKU LIST ERROR ===');
            Log::error('Error message: '.$e->getMessage());
            Log::error('Error line: '.$e->getLine());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'error' => 'Failed to fetch FNSKU list',
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function insertFnsku(Request $request)
    {
        try {
            $request->validate([
                'fnsku' => 'required|string|unique:'.$this->fnskuTable.',FNSKU',
                'asin' => 'required|string',
                'grading' => 'required|string',
                'msku' => 'nullable|string',
                'storename' => 'nullable|string',
            ]);

            // Insert new FNSKU with default 11 units
            DB::table($this->fnskuTable)->insert([
                'FNSKU' => $request->fnsku,
                'MSKU' => $request->msku,
                'ASIN' => $request->asin,
                'grading' => $request->grading,
                'storename' => $request->storename,
                'fnsku_status' => 'available',
                'insert_date' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FNSKU added successfully with 11 units',
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding FNSKU: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to add FNSKU: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * UPDATED updateFnsku method with improved history tracking
     */
public function updateFnsku(Request $request)
{
    Log::info('=== FNSKU UPDATE REQUEST START ===');
    Log::info('Received FNSKU update request:', $request->all());

    try {
        $request->validate([
            'product_id' => 'required|integer',
            'fnsku' => 'required|string|min:1',
            'msku' => 'required|string|min:1',  // ✅ Make MSKU required
            'asin' => 'required|string|min:1',  // ✅ Make ASIN required
            'grading' => 'nullable|string',
            'currentFnsku' => 'nullable|array',
        ]);

        DB::beginTransaction();

        // Get current product
        $product = DB::table($this->productTable)
            ->where('ProductID', $request->product_id)
            ->lockForUpdate()
            ->first();

        if (!$product) {
            DB::rollBack();
            Log::error('Product not found:', ['product_id' => $request->product_id]);

            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        // Store old and new values
        $oldFnskuViewer = $product->FNSKUviewer;
        $oldMskuViewer = $product->MSKUviewer;
        $oldAsinViewer = $product->ASINviewer;
        $newBaseFnsku = $request->fnsku;
        $newMsku = $request->msku;
        $newAsin = $request->asin;
        $rtCounter = $product->rtcounter ?? 'Unknown';

        Log::info('FNSKU Update Details:', [
            'product_id' => $request->product_id,
            'rt_counter' => $rtCounter,
            'old_fnsku_viewer' => $oldFnskuViewer,
            'old_msku_viewer' => $oldMskuViewer,
            'old_asin_viewer' => $oldAsinViewer,
            'new_base_fnsku' => $newBaseFnsku,
            'new_msku' => $newMsku,
            'new_asin' => $newAsin,
        ]);

        // ✅ Handle OLD FNSKU - Return unit using MSKU
        if (!empty($oldMskuViewer) && !empty($oldAsinViewer) && 
            $oldMskuViewer !== 'NULL' && $oldAsinViewer !== 'NULL' &&
            trim($oldMskuViewer) !== '' && trim($oldAsinViewer) !== '') {
            
            Log::info('Returning unit to old MSKU: ' . $oldMskuViewer);
            $returnSuccess = $this->returnFnskuUnits($oldMskuViewer, $oldAsinViewer);

            if (!$returnSuccess) {
                Log::warning('Failed to return units to old MSKU: ' . $oldMskuViewer);
            }
        }

        // ✅ Handle NEW FNSKU - Get record using MSKU
        $newFnskuRecord = DB::table($this->fnskuTable)
            ->where('MSKU', $newMsku)
            ->where('ASIN', $newAsin)
            ->where('fnsku_status', 'available')
            ->where('Units', '>', 0)
            ->first();

        if (!$newFnskuRecord) {
            DB::rollBack();
            Log::error('New FNSKU/MSKU combination not available:', [
                'fnsku' => $newBaseFnsku,
                'msku' => $newMsku,
                'asin' => $newAsin
            ]);

            $beforeState = empty($oldFnskuViewer) || $oldFnskuViewer === 'NULL' || trim($oldFnskuViewer) === ''
                ? "RTC: {$rtCounter} | No FNSKU/MSKU"
                : "RTC: {$rtCounter} | FNSKU: {$oldFnskuViewer} | MSKU: {$oldMskuViewer}";

            $this->trackHistory(
                'Labeling',
                'Set FNSKU Failed',
                $beforeState,
                "FNSKU/MSKU not available: {$newBaseFnsku}/{$newMsku}"
            );

            return response()->json([
                'success' => false,
                'message' => "FNSKU/MSKU combination not available: {$newBaseFnsku}/{$newMsku}",
            ], 400);
        }

        // ✅ Get the next available FNSKU with prefix (pass all 5 parameters)
        $fnskuInfo = $this->getNextAvailableFnsku(
            $newBaseFnsku,
            $newMsku,                    // ✅ Pass MSKU
            $newFnskuRecord->ASIN,
            $newFnskuRecord->grading,
            $newFnskuRecord->storename
        );

        $actualFnskuToUse = $fnskuInfo['actual_fnsku'];
        $actualMskuToUse = $fnskuInfo['actual_msku'];

        Log::info('Generated prefixed FNSKU for use', [
            'base_fnsku' => $newBaseFnsku,
            'actual_fnsku_to_use' => $actualFnskuToUse,
            'msku_to_use' => $actualMskuToUse,
            'times_used' => $fnskuInfo['times_used'],
            'remaining_units' => $fnskuInfo['remaining_units'],
        ]);

        // ✅ Update the product with FNSKU, MSKU, and ASIN
        DB::table($this->productTable)
            ->where('ProductID', $request->product_id)
            ->update([
                'FNSKUviewer' => $actualFnskuToUse,
                'MSKUviewer' => $actualMskuToUse,   // ✅ Populate MSKUviewer
                'ASINviewer' => $newAsin,           // ✅ Populate ASINviewer
            ]);

        // ✅ Update FNSKU units using MSKU
        $becameUnavailable = $this->updateFnskuUnits(
            $newMsku,
            $newFnskuRecord->ASIN,
            $newFnskuRecord->grading,
            $newFnskuRecord->storename,
            $request->currentFnsku
        );

        // ✅ Track history
        $beforeState = empty($oldFnskuViewer) || $oldFnskuViewer === 'NULL' || trim($oldFnskuViewer) === ''
            ? "RTC: {$rtCounter} | No FNSKU/MSKU"
            : "RTC: {$rtCounter} | FNSKU: {$oldFnskuViewer} | MSKU: {$oldMskuViewer} | ASIN: {$oldAsinViewer}";

        $afterState = "RTC: {$rtCounter} | FNSKU: {$actualFnskuToUse} | MSKU: {$actualMskuToUse} | ASIN: {$newAsin} | Grade: {$newFnskuRecord->grading} | Units Left: {$fnskuInfo['remaining_units']}";

        $this->trackHistory(
            'Labeling',
            'Set FNSKU',
            $beforeState,
            $afterState
        );

        DB::commit();

        Log::info('✅ FNSKU update transaction completed successfully');
        Log::info('=== FNSKU UPDATE REQUEST END ===');

        return response()->json([
            'success' => true,
            'message' => 'FNSKU updated successfully',
            'details' => [
                'old_fnsku_viewer' => $oldFnskuViewer,
                'old_msku_viewer' => $oldMskuViewer,
                'old_asin_viewer' => $oldAsinViewer,
                'new_base_fnsku' => $newBaseFnsku,
                'new_base_msku' => $newMsku,
                'actual_fnsku_assigned' => $actualFnskuToUse,
                'actual_msku_assigned' => $actualMskuToUse,
                'asin_assigned' => $newAsin,
                'remaining_units' => $fnskuInfo['remaining_units'],
                'times_used' => $fnskuInfo['times_used'],
                'became_unavailable' => $becameUnavailable,
            ],
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        Log::error('❌ Validation error: ' . json_encode($e->errors()));

        if (isset($request->product_id)) {
            $product = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->first();
            if ($product) {
                $beforeState = empty($product->FNSKUviewer) || $product->FNSKUviewer === 'NULL' || trim($product->FNSKUviewer) === ''
                    ? "RTC: {$product->rtcounter} | No FNSKU"
                    : "RTC: {$product->rtcounter} | FNSKU: {$product->FNSKUviewer}";

                $this->trackHistory(
                    'Labeling',
                    'Set FNSKU Failed',
                    $beforeState,
                    'Validation Error'
                );
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('❌ Error updating FNSKU: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());

        if (isset($request->product_id)) {
            $product = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->first();
            if ($product) {
                $beforeState = empty($product->FNSKUviewer) || $product->FNSKUviewer === 'NULL' || trim($product->FNSKUviewer) === ''
                    ? "RTC: {$product->rtcounter} | No FNSKU"
                    : "RTC: {$product->rtcounter} | FNSKU: {$product->FNSKUviewer}";

                $this->trackHistory(
                    'Labeling',
                    'Set FNSKU Failed',
                    $beforeState,
                    "Error: {$e->getMessage()}"
                );
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update FNSKU: ' . $e->getMessage(),
            'debug' => [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
            ],
        ], 500);
    }
}

    public function getProduct($productId)
    {
        try {
            Log::info('Fetching single product:', ['product_id' => $productId]);

            // Get the product with all necessary joins
            $product = DB::table($this->productTable.' as prod')
                ->select([
                    'prod.*',
                    'prod.FNSKUviewer as FNSKU', // Make sure FNSKU field is available
                    // Add any other fields your frontend needs
                ])
                ->where('prod.ProductID', $productId)
                ->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            Log::info('Product found:', [
                'ProductID' => $product->ProductID,
                'FNSKUviewer' => $product->FNSKUviewer,
                'rtcounter' => $product->rtcounter ?? 'N/A',
            ]);

            return response()->json([
                'success' => true,
                'data' => $product,
                'message' => 'Product retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching single product:', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving product: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * New method: Get FNSKU availability info (for frontend display)
     */
   public function getFnskuAvailability(Request $request)
    {
        try {
            $msku = $request->input('msku');

            if (empty($msku)) {
                return response()->json([
                    'success' => false,
                    'message' => 'MSKU is required',
                ]);
            }

            $fnskuRecord = DB::table($this->fnskuTable)
                ->where('MSKU', $msku)
                ->where('fnsku_status', 'available')
                ->where('Units', '>', 0)
                ->first();

            if (!$fnskuRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'MSKU not available or not found',
                    'available' => false,
                ]);
            }

            try {
                $fnskuInfo = $this->getNextAvailableFnsku(
                    $fnskuRecord->FNSKU,
                    $msku,
                    $fnskuRecord->ASIN,
                    $fnskuRecord->grading,
                    $fnskuRecord->storename,
                    $msku
                );

                return response()->json([
                    'success' => true,
                    'available' => true,
                    'fnsku_info' => [
                        'base_fnsku' => $fnskuRecord->FNSKU,
                        'base_msku' => $msku,
                        'next_fnsku_to_use' => $fnskuInfo['actual_fnsku'],
                        'msku_to_use' => $fnskuInfo['actual_msku'],
                        'times_used' => $fnskuInfo['times_used'],
                        'remaining_units' => $fnskuRecord->Units,
                        'units_after_use' => $fnskuInfo['remaining_units'],
                        'asin' => $fnskuRecord->ASIN,
                        'grading' => $fnskuRecord->grading,
                        'storename' => $fnskuRecord->storename,
                        'msku' => $fnskuRecord->MSKU,
                        'fnskuid' => $fnskuRecord->FNSKUID 
                    ],
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'available' => false,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error checking FNSKU availability: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error checking FNSKU availability',
            ], 500);
        }
    }

    private function updateFnskuLimitStatus($asin, $msku, $currentFnsku = null)
{
    try {
        // ===============================
        // 0. DETERMINE IF THIS IS NEW OR UPDATE
        // ===============================
        $isNewAssignment = empty($currentFnsku) || 
            !isset($currentFnsku['MSKU']);

        // ===============================
        // 1. GET ASIN LIMIT
        // ===============================
        $asinLimit = (int) (DB::table($this->asinTable)
            ->where('ASIN', $asin)
            ->value('asin_limit') ?? 0);

        // ===============================
        // 2. PREPARE COMMON DATA
        // ===============================
        $maximumUnits = 10;

        $newFnskuWhere = [
            'ASIN'      => $asin,
            'MSKU'      => $msku,
        ];

        // Only set up previous FNSKU if not a new assignment
        $fnskuChanged = false;
        $prevFnskuWhere = null;
        
        if (!$isNewAssignment) {
            $prevFnskuWhere = [
                'ASIN'      => $asin,
                'MSKU'      => $currentFnsku['MSKU'],
            ];
            
            $fnskuChanged = (
                $currentFnsku['MSKU'] !== $msku
            );
        }

        // ===============================
        // 3. USE TRANSACTION FOR ATOMICITY
        // ===============================
        DB::transaction(function () use (
            $newFnskuWhere, 
            $prevFnskuWhere, 
            $asinLimit, 
            $maximumUnits, 
            $fnskuChanged,
            $isNewAssignment,
            $msku
        ) {
            // Re-fetch units within transaction
            $currentUnits = (int) DB::table($this->fnskuTable)
                ->where($newFnskuWhere)
                ->value('Units');

            // ===============================
            // 4. CALCULATE USED UNITS
            // ===============================
            $usedUnits = max(0, $maximumUnits - $currentUnits);

            // ===============================
            // 6. UPDATE LIMIT STATUS
            // ===============================
            $prevUpdated = 0;
            
            // Only update previous if FNSKU details changed AND it's not a new assignment
            if ($fnskuChanged && !$isNewAssignment) {
                $previousCurrentUnits = (int) DB::table($this->fnskuTable)
                    ->where($prevFnskuWhere)
                    ->value('Units');
                    
                $previousUsedUnits = max(0, $maximumUnits - $previousCurrentUnits);
                
                $prevUpdated = DB::table($this->fnskuTable)
                    ->where($prevFnskuWhere)
                    ->update([
                        'LimitStatus' => ($asinLimit > 0 && $previousUsedUnits >= $asinLimit) ? "True" : "False"
                    ]);
            }

            // Always update the new/current FNSKU
            $newLimitStatus = ($asinLimit > 0 && $usedUnits >= $asinLimit) ? "True" : "False";

            $newUpdated = DB::table($this->fnskuTable)
                ->where($newFnskuWhere)
                ->update(['LimitStatus' => $newLimitStatus]);

            // ===============================
            // 7. VERIFY UPDATES
            // ===============================
        });

    } catch (\Exception $e) {
        Log::error('Failed to update FNSKU limit status', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'asin' => $asin,
            'grading' => $grading,
            'storename' => $storename,
            'msku' => $msku,
        ]);
    }
}


}
