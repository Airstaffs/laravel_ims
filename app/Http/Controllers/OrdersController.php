<?php

namespace App\Http\Controllers;

use App\Models\tblproduct;
use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrdersController extends BasetablesController
{
    use TracksHistory;

 public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Orders');

            // Build query with ASIN join using ASINviewer
            $productsQuery = DB::table($this->productTable . ' as prod')
                ->leftJoin($this->asinTable . ' as asin', 'prod.ASINviewer', '=', 'asin.ASIN')
                ->select([
                    'prod.*',
                    'asin.ASIN as asin_code',
                    DB::raw("COALESCE(
                        NULLIF(TRIM(asin.system_title), ''), 
                        NULLIF(TRIM(asin.internal), ''), 
                        NULLIF(TRIM(prod.ProductTitle), '')
                    ) as AStitle"),
                    'asin.internal',
                    'asin.system_title',
                    'asin.metakeyword',
                    'asin.EAN',
                    'asin.UPC',
                    'asin.ParentAsin',
                    'asin.QuantityInside as asin_quantity'
                ])
                ->where('prod.ProductModuleLoc', $location);

            // Apply search filters
            if (!empty($search)) {
                $productsQuery->where(function ($q) use ($search) {
                    $q->where('prod.ProductTitle', 'like', "%{$search}%")
                        ->orWhere('prod.rtid', 'like', "%{$search}%")
                        ->orWhere('prod.itemnumber', 'like', "%{$search}%")
                        ->orWhere('prod.trackingnumber', 'like', "%{$search}%")
                        ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                        ->orWhere('prod.ASINviewer', 'like', "%{$search}%")
                        ->orWhere('asin.ASIN', 'like', "%{$search}%")
                        ->orWhere('asin.internal', 'like', "%{$search}%")
                        ->orWhere('asin.system_title', 'like', "%{$search}%")
                        ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                });
            }

            $products = $productsQuery->paginate($perPage);

            Log::info('Orders fetched successfully with ASIN join', ['count' => $products->count()]);

            // Transform products to organize data properly
            $products->getCollection()->transform(function ($product) {
                // Use asin_code from join, fallback to prod.ASINviewer
                if (empty($product->asin_code) && !empty($product->ASINviewer)) {
                    $product->asin_code = $product->ASINviewer;
                }

                // Set display ASIN for frontend
                $product->display_asin = $product->asin_code ?? $product->ASINviewer ?? null;

                // Keep the quantity from ASIN if available
                $product->asin_quantity_inside = $product->asin_quantity ?? null;

                // Clean up duplicate fields
                unset($product->asin_quantity);

                return $product;
            });

            return response()->json($products);

        } catch (\Exception $e) {
            Log::error('Error in OrdersController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching orders',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

 public function updateQuantity(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'quantity' => 'required|numeric|min:0'
            ]);

            $product = tblproduct::findOrFail($id);
            $oldQuantity = $product->quantity ?? 0;
            $newQuantity = $validated['quantity'];

            // Only update if quantity changed
            if ($oldQuantity != $newQuantity) {
                $product->quantity = $newQuantity;
                $product->save();

                // Track quantity change
                $employeeName = auth()->user()->username ?? 'System';
                $identifier = "Item #{$product->itemnumber}";
                if (!empty($product->ProductTitle)) {
                    $identifier .= " - {$product->ProductTitle}";
                }

                $this->trackUpdate(
                    'Orders',
                    $identifier,
                    "Quantity: {$oldQuantity}",
                    "Quantity: {$newQuantity}",
                    $employeeName
                );

                Log::info("Quantity updated for order: {$product->itemnumber}", [
                    'old' => $oldQuantity,
                    'new' => $newQuantity
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Quantity updated successfully',
                    'product' => $product
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'No changes made to quantity',
                'product' => $product
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating quantity: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating quantity',
                'error' => $e->getMessage()
            ], 500);
        }
    }


public function getAsinList(Request $request)
    {
        try {
            $perPage = min($request->input('per_page', 15), 100);
            $search = $request->input('search', '');
            $page = $request->input('page', 1);

            // Build the ASIN query
            $asinQuery = DB::table($this->asinTable . ' as asin')
                ->select([
                    'asin.ASIN',
                    'asin.internal as AStitle',
                    'asin.system_title',
                    'asin.metakeyword',
                    'asin.EAN',
                    'asin.UPC',
                    'asin.asinimg',
                    'asin.dimension_length',
                    'asin.dimension_width',
                    'asin.dimension_height',
                    'asin.weight_value',
                    'asin.weight_unit',
                    'asin.TRANSPARENCY_QR_STATUS',
                    DB::raw('COUNT(fnsku.FNSKU) as fnsku_count')
                ])
                ->leftJoin($this->fnskuTable . ' as fnsku', 'asin.ASIN', '=', 'fnsku.ASIN')
                ->where('asin.ASIN', '!=', '')
                ->whereNotNull('asin.ASIN');

            // Apply search filters
            if (!empty($search)) {
                $asinQuery->where(function ($query) use ($search) {
                    $query->where('asin.ASIN', 'like', "%{$search}%")
                        ->orWhere('asin.internal', 'like', "%{$search}%")
                        ->orWhere('asin.system_title', 'like', "%{$search}%")
                        ->orWhere('asin.metakeyword', 'like', "%{$search}%")
                        ->orWhere('asin.EAN', 'like', "%{$search}%")
                        ->orWhere('asin.UPC', 'like', "%{$search}%");
                });
            }

            // Group by ASIN
            $asinQuery->groupBy(
                'asin.ASIN',
                'asin.internal',
                'asin.system_title',
                'asin.metakeyword',
                'asin.EAN',
                'asin.UPC',
                'asin.asinimg',
                'asin.dimension_length',
                'asin.dimension_width',
                'asin.dimension_height',
                'asin.weight_value',
                'asin.weight_unit',
                'asin.TRANSPARENCY_QR_STATUS'
            );

            // Order by ASIN
            $asinQuery->orderBy('asin.ASIN', 'asc');

            // Get paginated results
            $asins = $asinQuery->paginate($perPage);

            // Process results
            $results = $asins->getCollection()->map(function ($item) {
                // Add display_title property - prioritize system_title over internal
                $item->display_title = !empty($item->system_title) ? $item->system_title : $item->AStitle;

                // Add ASIN image URL if exists
                $item->asin_image_url = $item->asinimg ? url($item->asinimg) : null;

                // Ensure numeric values are properly typed
                $item->fnsku_count = (int) $item->fnsku_count;

                return $item;
            });

            // Update the collection
            $asins->setCollection($results);
            $result = $asins->toArray();

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error in OrdersController@getAsinList: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'error' => 'An error occurred while retrieving ASIN data',
                'message' => $e->getMessage(),
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0
            ], 500);
        }
    }


    /**
     * Get the next available ProductID
     */
    public function getNextProductId()
    {
        $maxProductId = DB::table($this->productTable)->max('ProductID') ?? 0;

        return response()->json([
            'next_id' => $maxProductId + 1,
            'current_max' => $maxProductId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ProductID' => 'required|integer',
            'itemnumber' => 'required|string',
            'ProductTitle' => 'nullable|string',
            'rtid' => 'nullable|string',
            'orderdate' => 'nullable|date',
            'paymentdate' => 'nullable|date',
            'shipdate' => 'nullable|date',
            'datedelivered' => 'nullable|date',
            'seller' => 'nullable|string',
            'materialtype' => 'nullable|string',
            'sourceType' => 'nullable|string',
            'carrier' => 'nullable|string',
            'listedcondition' => 'nullable|string',
            'paymentmethod' => 'nullable|string',
            'quantity' => 'nullable|numeric',
            'Discount' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'priceshipping' => 'nullable|numeric',
            'refund' => 'nullable|numeric',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            // 'employeeNotes' => 'nullable|string',
            'serialnumber' => 'nullable|string',
            'serialnumberb' => 'nullable|string',
            'serialnumberc' => 'nullable|string',
            'serialnumberd' => 'nullable|string',
            'trackingnumber' => 'nullable|string',
            'trackingnumber2' => 'nullable|string',
            'trackingnumber3' => 'nullable|string',
            'trackingnumber4' => 'nullable|string',
            'trackingnumber5' => 'nullable|string',
            'validation' => 'nullable|string',
            'price' => 'nullable|numeric',
            'RPN' => 'nullable|string',
            'PRD' => 'nullable|string',
            'PCN' => 'nullable|string',
            'basketnumber' => 'nullable|string',
        ]);

        $validated['validation'] = $validated['validation'] ?? 'unvalidated';

        // Check if this is an update or create
        $existingProduct = tblproduct::where('itemnumber', $validated['itemnumber'])->first();
        $isUpdate = $existingProduct !== null;

        // 🔥 TRACK WHAT CHANGED (if update)
        $changes = [];
        if ($isUpdate && $existingProduct) {
            foreach ($validated as $key => $value) {
                // Skip ProductID and itemnumber
                if (in_array($key, ['ProductID', 'itemnumber'])) {
                    continue;
                }

                // Get old and new values
                $oldVal = $existingProduct->$key ?? null;
                $newVal = $value ?? null;

                // 🔥 BETTER NULL/EMPTY HANDLING
                // Convert empty strings to null for comparison
                if ($oldVal === '') {
                    $oldVal = null;
                }
                if ($newVal === '') {
                    $newVal = null;
                }

                // 🔥 SKIP if both are null
                if (is_null($oldVal) && is_null($newVal)) {
                    continue;
                }

                // 🔥 SKIP if values are identical (including 0 === 0)
                if ($oldVal === $newVal) {
                    continue;
                }

                // 🔥 SPECIAL HANDLING FOR NUMERIC FIELDS
                if (in_array($key, ['price', 'quantity', 'Discount', 'tax', 'priceshipping', 'refund'])) {
                    // Convert to float for proper comparison
                    $oldNum = is_null($oldVal) ? null : (float) $oldVal;
                    $newNum = is_null($newVal) ? null : (float) $newVal;

                    // Skip if both are null
                    if (is_null($oldNum) && is_null($newNum)) {
                        continue;
                    }

                    // Skip if both are 0
                    if ($oldNum == 0 && $newNum == 0) {
                        continue;
                    }

                    // Skip if values are the same
                    if ($oldNum === $newNum) {
                        continue;
                    }

                    // Only track if there's a real change
                    $oldDisplay = ! is_null($oldNum) ? $oldNum : '(empty)';
                    $newDisplay = ! is_null($newNum) ? $newNum : '(empty)';
                    $changes[] = "$key: $oldDisplay → $newDisplay";
                }
                // Format dates
                elseif (in_array($key, ['orderdate', 'paymentdate', 'shipdate', 'datedelivered'])) {
                    $oldDisplay = $oldVal ? date('Y-m-d', strtotime($oldVal)) : '(empty)';
                    $newDisplay = $newVal ? date('Y-m-d', strtotime($newVal)) : '(empty)';

                    // Skip if both are empty
                    if ($oldDisplay === '(empty)' && $newDisplay === '(empty)') {
                        continue;
                    }

                    $changes[] = "$key: $oldDisplay → $newDisplay";
                }
                // Format text values
                else {
                    // Skip if both are empty/null
                    if (empty($oldVal) && empty($newVal)) {
                        continue;
                    }

                    $oldDisplay = $oldVal ? (strlen($oldVal) > 30 ? substr($oldVal, 0, 27).'...' : $oldVal) : '(empty)';
                    $newDisplay = $newVal ? (strlen($newVal) > 30 ? substr($newVal, 0, 27).'...' : $newVal) : '(empty)';

                    $changes[] = "$key: $oldDisplay → $newDisplay";
                }
            }
        }

        // Create or update the product
        $product = tblproduct::updateOrCreate(
            ['itemnumber' => $validated['itemnumber']],
            $validated
        );

        // 🔥 ONLY TRACK HISTORY IF THERE ARE CHANGES
        if ($isUpdate && ! empty($changes)) {
            $employeeName = auth()->user()->username ?? 'System';

            // Build RT# prefix using ProductID
            $rtPrefix = "RT#{$product->ProductID} | ";

            // Build separate before/after strings
            $beforeParts = [];
            $afterParts = [];

            foreach ($changes as $change) {
                // Parse "fieldname: oldvalue → newvalue"
                if (preg_match('/^(.+?): (.+?) → (.+)$/', $change, $matches)) {
                    $field = $matches[1];
                    $oldValue = $matches[2];
                    $newValue = $matches[3];

                    $beforeParts[] = "$field: $oldValue";
                    $afterParts[] = "$field: $newValue";
                }
            }

            // Add RT# prefix to both before and after
            $beforeString = $rtPrefix.implode(', ', array_slice($beforeParts, 0, 5));
            $afterString = $rtPrefix.implode(', ', array_slice($afterParts, 0, 5));

            $identifier = "Item #{$product->itemnumber}".
                          (! empty($product->ProductTitle) ? " - {$product->ProductTitle}" : '');

            $this->trackUpdate(
                'Orders',
                $identifier,
                $beforeString,
                $afterString,
                $employeeName
            );
        } elseif (! $isUpdate) {
            // Only track creation for new products
            $employeeName = auth()->user()->username ?? 'System';
            $identifier = "Item #{$product->itemnumber}".
                          (! empty($product->ProductTitle) ? " - {$product->ProductTitle}" : '');

            $this->trackCreate(
                'Orders',
                $identifier,
                $employeeName
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Order product saved successfully',
            'product' => $product,
            'changes_made' => count($changes),
        ]);
    }

    /**
     * Update order status with history tracking
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'validation' => 'required|string',
        ]);

        $product = tblproduct::findOrFail($id);
        $oldStatus = $product->validation ?? 'none';

        $product->validation = $validated['validation'];
        $product->save();

        // Track status change
        $this->trackStatusChange(
            'Orders',
            "Item: {$product->itemnumber}",
            $oldStatus,
            $validated['validation']
        );

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'product' => $product,
        ]);
    }

    /**
     * Delete order with history tracking
     */
    public function destroy($id)
    {
        try {
            // Use DB query instead of model to avoid "No query results" error
            $product = DB::table($this->productTable)
                ->where('ProductID', $id)
                ->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            $itemInfo = "Item: {$product->itemnumber}";
            if (! empty($product->ProductTitle)) {
                $itemInfo .= " - {$product->ProductTitle}";
            }

            // Delete the product
            DB::table($this->productTable)
                ->where('ProductID', $id)
                ->delete();

            // Track deletion
            $this->trackDelete('Orders', $itemInfo);

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully',
            ]);

        } catch (\Exception $e) {
            \Log::error('Delete order error:', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting order: '.$e->getMessage(),
            ], 500);
        }
    }

   

public function setAsin(Request $request)
{
    try {
        $validated = $request->validate([
            'ProductID' => 'required|integer',
            'ASIN' => 'required|string',
        ]);

        // Find the product by ProductID
        $product = DB::table($this->productTable)
            ->where('ProductID', $validated['ProductID'])
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $oldAsin = $product->ASINviewer ?? '';

        // Update ASINviewer field
        DB::table($this->productTable)
            ->where('ProductID', $validated['ProductID'])
            ->update([
                'ASINviewer' => $validated['ASIN']
            ]);

        // Track the change
        $employeeName = auth()->user()->username ?? 'System';
        $identifier = "RT#{$product->rtcounter} - {$product->ProductTitle}";

        $this->trackUpdate(
            'Orders',
            $identifier,
            "ASINviewer: " . ($oldAsin ?: '(empty)'),
            "ASINviewer: {$validated['ASIN']}",
            $employeeName
        );

        Log::info("ASINviewer set for ProductID: {$validated['ProductID']}", [
            'old_asin' => $oldAsin,
            'new_asin' => $validated['ASIN'],
            'rt_counter' => $product->rtcounter
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ASIN set successfully',
            'asin' => $validated['ASIN']
        ]);

    } catch (\Exception $e) {
        Log::error('Error setting ASIN: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while setting ASIN',
            'error' => $e->getMessage()
        ], 500);
    }
}
/**
 * Remove ASIN from an order product
 */
public function removeAsin(Request $request)
{
    try {
        $validated = $request->validate([
            'ProductID' => 'required|integer',
        ]);

        // Find the product by ProductID
        $product = DB::table($this->productTable)
            ->where('ProductID', $validated['ProductID'])
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $oldAsin = $product->ASINviewer ?? '';

        // ✅ CLEAR ONLY ASINviewer FIELD
        DB::table($this->productTable)
            ->where('ProductID', $validated['ProductID'])
            ->update([
                'ASINviewer' => null  // or '' (empty string)
            ]);

        // Track the change
        $employeeName = auth()->user()->username ?? 'System';
        $identifier = "RT#{$product->rtcounter} - {$product->ProductTitle}";

        $this->trackUpdate(
            'Orders',
            $identifier,
            "ASINviewer: {$oldAsin}",
            "ASINviewer: (removed)",
            $employeeName
        );

        Log::info("ASINviewer removed from ProductID: {$validated['ProductID']}", [
            'old_asin' => $oldAsin,
            'rt_counter' => $product->rtcounter
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ASIN removed successfully'
        ]);

    } catch (\Exception $e) {
        Log::error('Error removing ASIN: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while removing ASIN',
            'error' => $e->getMessage()
        ], 500);
    }
}


// ========================================
// ADD THESE TWO METHODS TO YOUR OrdersController.php
// Location: app/Http/Controllers/OrdersController.php
// ========================================

public function getIncomingCount(Request $request)
{
    try {
        $search = $request->input('search', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');

        Log::info('Incoming count search params', [
            'search' => $search,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);

        // Build base query with ASIN join - using subquery approach
        $subQuery = DB::table($this->productTable . ' as prod')
            ->leftJoin($this->asinTable . ' as asin', 'prod.ASINviewer', '=', 'asin.ASIN')
            ->select([
                DB::raw('COALESCE(asin.ASIN, prod.ASINviewer) as asin'),
                DB::raw("COALESCE(
                    NULLIF(TRIM(asin.system_title), ''), 
                    NULLIF(TRIM(asin.internal), ''),
                    'No Title'
                ) as title"),
                'prod.seller',
                'prod.quantity',
                'prod.datedelivered'
            ])
            ->where('prod.ProductModuleLoc', 'Orders')
            ->whereNotNull('prod.ASINviewer')  // ✅ FIX: Exclude items without ASIN
            ->where('prod.ASINviewer', '!=', ''); // ✅ FIX: Exclude empty ASIN

        // ✅ FIX: Apply EXACT search filter (not partial matching)
        if (!empty($search)) {
            $subQuery->where(function ($q) use ($search) {
                // Exact match for ASIN and metakeyword
                $q->where('asin.ASIN', '=', $search)
                    ->orWhere('asin.metakeyword', '=', $search)
                    // Partial match only for titles
                    ->orWhere('asin.internal', 'like', "%{$search}%")
                    ->orWhere('asin.system_title', 'like', "%{$search}%")
                    ->orWhere('prod.ProductTitle', 'like', "%{$search}%");
            });
        }

        // Apply date range filter
        if (!empty($dateFrom)) {
            $subQuery->where('prod.datedelivered', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $subQuery->where('prod.datedelivered', '<=', $dateTo);
        }

        // Now group the subquery results
        $query = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
            ->mergeBindings($subQuery)
            ->select([
                'sub.asin',
                'sub.title',
                DB::raw('GROUP_CONCAT(DISTINCT sub.seller ORDER BY sub.seller SEPARATOR ", ") as sellers'),
                DB::raw('SUM(COALESCE(sub.quantity, 1)) as total_quantity'),
                DB::raw('MIN(sub.datedelivered) as earliest_delivery'),
                DB::raw('MAX(sub.datedelivered) as latest_delivery')
            ])
            ->groupBy('sub.asin', 'sub.title')
            ->orderByDesc('total_quantity');

        $results = $query->get();

        Log::info('Incoming count query results', [
            'count' => $results->count()
        ]);

        // Transform results
        $results = $results->map(function ($item) {
            return [
                'asin' => $item->asin,
                'title' => $item->title ?: 'No Title',
                'sellers' => $item->sellers ?: 'N/A',
                'total_quantity' => (int) $item->total_quantity,
                'earliest_delivery' => $item->earliest_delivery,
                'latest_delivery' => $item->latest_delivery
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $results,
            'summary' => [
                'total_items' => $results->count(),
                'total_quantity' => $results->sum('total_quantity'),
                'unique_asins' => $results->where('asin', '!=', null)->count()
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error in getIncomingCount', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'An error occurred while retrieving incoming count',
            'error' => $e->getMessage(),
            'details' => config('app.debug') ? $e->getTraceAsString() : null
        ], 500);
    }
}

/**
 * Get detailed items for a specific ASIN with filters
 * Route: GET /api/orders/incoming-count-details
 */
public function getIncomingCountDetails(Request $request)
{
    try {
        $asin = $request->input('asin', '');
        $search = $request->input('search', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');

        // Build query for specific ASIN or search term
        $query = DB::table($this->productTable . ' as prod')
            ->leftJoin($this->asinTable . ' as asin', 'prod.ASINviewer', '=', 'asin.ASIN')
            ->select([
                'prod.ProductID',
                'prod.rtcounter',
                'prod.ProductTitle',
                'prod.quantity',
                'prod.datedelivered',
                'prod.trackingnumber',
                'prod.serialnumber',
                'prod.warehouselocation',
                'asin.ASIN as asin_code',
                DB::raw("COALESCE(
                    NULLIF(TRIM(asin.system_title), ''), 
                    NULLIF(TRIM(asin.internal), ''), 
                    NULLIF(TRIM(prod.ProductTitle), '')
                ) as display_title")
            ])
            ->where('prod.ProductModuleLoc', 'Orders')
            ->whereNotNull('prod.datedelivered');

        // Filter by ASIN if provided
        if (!empty($asin)) {
            $query->where(function ($q) use ($asin) {
                $q->where('asin.ASIN', $asin)
                    ->orWhere('prod.ASINviewer', $asin);
            });
        }

        // Apply search filter if no ASIN provided
        if (empty($asin) && !empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('asin.ASIN', 'like', "%{$search}%")
                    ->orWhere('asin.internal', 'like', "%{$search}%")
                    ->orWhere('asin.system_title', 'like', "%{$search}%")
                    ->orWhere('asin.metakeyword', 'like', "%{$search}%")
                    ->orWhere('prod.ProductTitle', 'like', "%{$search}%")
                    ->orWhere('prod.ASINviewer', 'like', "%{$search}%");
            });
        }

        // Apply date range filter
        if (!empty($dateFrom)) {
            $query->where('prod.datedelivered', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->where('prod.datedelivered', '<=', $dateTo);
        }

        // Order by delivery date descending
        $query->orderByDesc('prod.datedelivered');

        $items = $query->get();

        // Calculate totals
        $totalQuantity = $items->sum(function ($item) {
            return (int) ($item->quantity ?? 1);
        });

        Log::info('Incoming count details retrieved', [
            'asin' => $asin,
            'count' => $items->count(),
            'total_quantity' => $totalQuantity
        ]);

        return response()->json([
            'success' => true,
            'data' => $items,
            'summary' => [
                'item_count' => $items->count(),
                'total_quantity' => $totalQuantity
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error in getIncomingCountDetails', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'An error occurred while retrieving item details',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Get formatted time for display
     */
    private function formatHistoryTime($datetime)
    {
        if (empty($datetime)) {
            return null;
        }

        return [
            'la_time' => \App\Helpers\TimeHelper::formatDateTime($datetime, 'M d, Y h:i:s A', 'America/Los_Angeles'),
            'ph_time' => \App\Helpers\TimeHelper::formatDateTime($datetime, 'M d, Y h:i:s A', 'Asia/Manila'),
            'la_tz' => \App\Helpers\TimeHelper::getTimezoneDisplay('America/Los_Angeles'),
            'ph_tz' => \App\Helpers\TimeHelper::getTimezoneDisplay('Asia/Manila'),
        ];
    }
}
