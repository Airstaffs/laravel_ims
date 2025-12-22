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

        if (! $fnskuRecord) {
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
            $prefix = 'C'.$timesUsed;
            $actualFnsku = $prefix.$baseFnsku;
        }

        return [
            'actual_fnsku' => $actualFnsku,
            'times_used' => $timesUsed,
            'remaining_units' => $remainingUnits - 1, // After this use
            'base_fnsku' => $baseFnsku,
            'fnsku_id' => $fnskuRecord->FNSKUID ?? null,
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
            throw new \Exception('Could not update FNSKU units - no available units');
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
     * Return units to FNSKU (reverse operation)
     */
    private function returnFnskuUnits($fnskuViewer)
    {
        $baseFnsku = $this->extractBaseFnsku($fnskuViewer);

        if (empty($baseFnsku)) {
            return false;
        }

        // Find the FNSKU record
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->first();

        if (! $fnskuRecord) {
            Log::warning("Base FNSKU not found for return: {$baseFnsku}");

            return false;
        }

        // Increment the units (return the unit)
        DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->update([
                'Units' => DB::raw('Units + 1'),
                'fnsku_status' => 'available', // Make sure it's marked as available
            ]);

        Log::info('Successfully returned 1 unit to base FNSKU', [
            'original_fnsku_viewer' => $fnskuViewer,
            'base_fnsku' => $baseFnsku,
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
            // Add extensive logging for debugging
            Log::info('=== FNSKU LIST REQUEST START ===');
            Log::info('Request parameters:', $request->all());

            // Get pagination parameters
            $perPage = min($request->input('limit', 50), 500);
            $search = $request->input('search', '');
            $exclude_assigned = $request->boolean('exclude_assigned', true);

            Log::info('Processed parameters:', [
                'per_page' => $perPage,
                'search' => $search,
                'exclude_assigned' => $exclude_assigned,
            ]);

            // Check if table properties are set
            if (! isset($this->fnskuTable) || ! isset($this->asinTable) || ! isset($this->productTable)) {
                Log::error('Table properties not set');

                return response()->json([
                    'error' => 'Database configuration error',
                    'data' => [],
                ], 500);
            }

            // Updated to join with ASIN table to get the title
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
                ])
                ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->where('fnsku.fnsku_status', 'available')
                ->where('fnsku.Units', '>', 0)
                // IMPORTANT: Filter out empty/null FNSKUs
                ->whereNotNull('fnsku.FNSKU')
                ->where('fnsku.FNSKU', '!=', '')
                ->where('fnsku.FNSKU', '!=', 'NULL')
                // Also filter out empty ASINs
                ->whereNotNull('fnsku.ASIN')
                ->where('fnsku.ASIN', '!=', '')
                ->where('fnsku.ASIN', '!=', 'NULL');

            Log::info('Base query built, checking exclusion logic...');

            // MODIFIED: SIMPLIFIED exclusion logic
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

            // IMPROVED: Add search functionality with priority ordering
            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('fnsku.FNSKU', 'like', "%{$search}%")
                        ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                        ->orWhere('fnsku.grading', 'like', "%{$search}%")
                        ->orWhere('asin.internal', 'like', "%{$search}%");
                });

                // When searching, prioritize exact matches and ASIN matches
                $query->orderByRaw('
                CASE 
                    WHEN fnsku.ASIN = ? THEN 1
                    WHEN fnsku.FNSKU LIKE ? THEN 2
                    WHEN fnsku.ASIN LIKE ? THEN 3
                    ELSE 4
                END, fnsku.FNSKU
            ', [$search, $search.'%', '%'.$search.'%']);

                Log::info('Search filters applied for: '.$search);
            } else {
                $query->orderBy('fnsku.ASIN')
                    ->orderBy('fnsku.FNSKU');
            }

            Log::info('About to execute query...');

            // Get total count for the filtered results
            $totalCount = $query->count();

            // Then do the pagination
            $fnskuList = $query->simplePaginate($perPage);

            Log::info('Query executed successfully', [
                'current_page_count' => $fnskuList->count(),
                'per_page' => $perPage,
                'current_page' => $fnskuList->currentPage(),
            ]);

            // Filter out any remaining empty FNSKUs (extra safety)
            $filteredItems = $fnskuList->getCollection()->filter(function ($item) {
                return ! empty($item->FNSKU) && $item->FNSKU !== 'NULL' && trim($item->FNSKU) !== '';
            })->values();

            // Replace the collection with filtered items
            $fnskuList->setCollection($filteredItems);

            Log::info('After filtering empty FNSKUs:', ['count' => $fnskuList->count()]);
            Log::info('=== FNSKU LIST REQUEST END ===');

            // Add total to the response
            return response()->json([
                'data' => $fnskuList->items(),
                'current_page' => $fnskuList->currentPage(),
                'per_page' => $fnskuList->perPage(),
                'has_more_pages' => $fnskuList->hasMorePages(),
                'from' => $fnskuList->firstItem(),
                'to' => $fnskuList->lastItem(),
                'total' => $totalCount, // Add this
                'excluded_assigned' => $exclude_assigned,
                'search_applied' => ! empty($search),
            ]);

        } catch (\Exception $e) {
            Log::error('=== FNSKU LIST ERROR ===');
            Log::error('Error message: '.$e->getMessage());
            Log::error('Error line: '.$e->getLine());
            Log::error('Error file: '.$e->getFile());
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
                'Units' => 11, // Default units
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
                'msku' => 'nullable|string',
                'asin' => 'nullable|string',
                'grading' => 'nullable|string',
            ]);

            // Begin transaction
            DB::beginTransaction();

            // Check if product exists and get current FNSKU
            $product = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                DB::rollBack();
                Log::error('Product not found:', ['product_id' => $request->product_id]);

                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            // Store the old and new FNSKU values
            $oldFnskuViewer = $product->FNSKUviewer;
            $newBaseFnsku = $request->fnsku;
            $rtCounter = $product->rtcounter ?? 'Unknown';

            Log::info('FNSKU Update Details:', [
                'product_id' => $request->product_id,
                'rt_counter' => $rtCounter,
                'old_fnsku_viewer' => $oldFnskuViewer,
                'new_base_fnsku' => $newBaseFnsku,
            ]);

            // Handle OLD FNSKU - Return unit back to inventory if it exists
            if (! empty($oldFnskuViewer) && $oldFnskuViewer !== 'NULL' && trim($oldFnskuViewer) !== '') {
                Log::info('Returning unit to old FNSKU: '.$oldFnskuViewer);
                $returnSuccess = $this->returnFnskuUnits($oldFnskuViewer);

                if (! $returnSuccess) {
                    Log::warning('Failed to return units to old FNSKU: '.$oldFnskuViewer);
                }
            }

            // Handle NEW FNSKU - Get the next available FNSKU with prefix
            $newFnskuRecord = DB::table($this->fnskuTable)
                ->where('FNSKU', $newBaseFnsku)
                ->where('fnsku_status', 'available')
                ->where('Units', '>', 0)
                ->first();

            if (! $newFnskuRecord) {
                DB::rollBack();
                Log::error('New FNSKU not available:', ['fnsku' => $newBaseFnsku]);

                // ✅ Track failed FNSKU assignment
                $beforeState = empty($oldFnskuViewer) || $oldFnskuViewer === 'NULL' || trim($oldFnskuViewer) === ''
                    ? "RTC: {$rtCounter} | No FNSKU"
                    : "RTC: {$rtCounter} | FNSKU: {$oldFnskuViewer}";

                $this->trackHistory(
                    'Labeling',
                    'Set FNSKU Failed',
                    $beforeState,
                    "FNSKU not available: {$newBaseFnsku}"
                );

                return response()->json([
                    'success' => false,
                    'message' => "FNSKU not available or not found: {$newBaseFnsku}",
                ], 400);
            }

            // Get the next available FNSKU with prefix
            $fnskuInfo = $this->getNextAvailableFnsku(
                $newBaseFnsku,
                $newFnskuRecord->ASIN,
                $newFnskuRecord->grading,
                $newFnskuRecord->storename
            );

            $actualFnskuToUse = $fnskuInfo['actual_fnsku'];

            Log::info('Generated prefixed FNSKU for use', [
                'base_fnsku' => $newBaseFnsku,
                'actual_fnsku_to_use' => $actualFnskuToUse,
                'times_used' => $fnskuInfo['times_used'],
                'remaining_units' => $fnskuInfo['remaining_units'],
            ]);

            // Update the product with the new prefixed FNSKU
            DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update(['FNSKUviewer' => $actualFnskuToUse]);

            // Update FNSKU units (decrement from base FNSKU)
            $becameUnavailable = $this->updateFnskuUnits(
                $newBaseFnsku,
                $newFnskuRecord->ASIN,
                $newFnskuRecord->grading,
                $newFnskuRecord->storename
            );

            // ✅ UPDATED: Better history tracking with clear before/after states
            $beforeState = empty($oldFnskuViewer) || $oldFnskuViewer === 'NULL' || trim($oldFnskuViewer) === ''
                ? "RTC: {$rtCounter} | No FNSKU"
                : "RTC: {$rtCounter} | FNSKU: {$oldFnskuViewer}";

            $afterState = "RTC: {$rtCounter} | FNSKU: {$actualFnskuToUse} | ASIN: {$newFnskuRecord->ASIN} | Grade: {$newFnskuRecord->grading} | Units Left: {$fnskuInfo['remaining_units']}";

            $this->trackHistory(
                'Labeling',
                'Set FNSKU',
                $beforeState,
                $afterState
            );

            // Commit the transaction
            DB::commit();

            Log::info('✅ FNSKU update transaction completed successfully');
            Log::info('=== FNSKU UPDATE REQUEST END ===');

            return response()->json([
                'success' => true,
                'message' => 'FNSKU updated successfully',
                'details' => [
                    'old_fnsku_viewer' => $oldFnskuViewer,
                    'new_base_fnsku' => $newBaseFnsku,
                    'actual_fnsku_assigned' => $actualFnskuToUse,
                    'remaining_units' => $fnskuInfo['remaining_units'],
                    'times_used' => $fnskuInfo['times_used'],
                    'became_unavailable' => $becameUnavailable,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('❌ Validation error: '.json_encode($e->errors()));

            // ✅ Track validation error
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
            Log::error('❌ Error updating FNSKU: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            // ✅ Track error
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
                'message' => 'Failed to update FNSKU: '.$e->getMessage(),
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
            $fnsku = $request->input('fnsku');

            if (empty($fnsku)) {
                return response()->json([
                    'success' => false,
                    'message' => 'FNSKU is required',
                ]);
            }

            // Get the FNSKU record
            $fnskuRecord = DB::table($this->fnskuTable)
                ->where('FNSKU', $fnsku)
                ->where('fnsku_status', 'available')
                ->where('Units', '>', 0)
                ->first();

            if (! $fnskuRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'FNSKU not available or not found',
                    'available' => false,
                ]);
            }

            // Calculate what the next FNSKU would be
            try {
                $fnskuInfo = $this->getNextAvailableFnsku(
                    $fnsku,
                    $fnskuRecord->ASIN,
                    $fnskuRecord->grading,
                    $fnskuRecord->storename
                );

                return response()->json([
                    'success' => true,
                    'available' => true,
                    'fnsku_info' => [
                        'base_fnsku' => $fnsku,
                        'next_fnsku_to_use' => $fnskuInfo['actual_fnsku'],
                        'times_used' => $fnskuInfo['times_used'],
                        'remaining_units' => $fnskuRecord->Units,
                        'units_after_use' => $fnskuInfo['remaining_units'],
                        'asin' => $fnskuRecord->ASIN,
                        'grading' => $fnskuRecord->grading,
                        'storename' => $fnskuRecord->storename,
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
            Log::error('Error checking FNSKU availability: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error checking FNSKU availability',
            ], 500);
        }
    }
}
