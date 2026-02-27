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
        $perPage  = $request->input('per_page', 10);
        $search   = $request->input('search', '');
        $location = $request->input('location', 'Orders');

        $productsQuery = DB::table($this->productTable . ' as prod')
            ->leftJoin($this->asinTable . ' as asin', 'prod.ASINviewer', '=', 'asin.ASIN')
            // ✅ Join image table
            ->leftJoin('tblEbayOrderImages as imgs', 'prod.ProductID', '=', 'imgs.ProductID')
            ->select([
                'prod.*',
                // ✅ Pull images from tblEbayOrderImages (overrides prod.* img columns)
                'imgs.img1',  'imgs.img2',  'imgs.img3',  'imgs.img4',  'imgs.img5',
                'imgs.img6',  'imgs.img7',  'imgs.img8',  'imgs.img9',  'imgs.img10',
                'imgs.img11', 'imgs.img12', 'imgs.img13', 'imgs.img14', 'imgs.img15',
                // ASIN fields
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
                'asin.QuantityInside as asin_quantity',
                // Tracking fields
                'prod.tracking1_status',
                'prod.tracking2_status',
                'prod.tracking3_status',
                'prod.tracking4_status',
                'prod.tracking1_delivered_date',
                'prod.tracking2_delivered_date',
                'prod.tracking3_delivered_date',
                'prod.tracking4_delivered_date',
                'prod.tracking_last_checked',
                // Delivery sort field
                DB::raw("COALESCE(
                    LEAST(
                        COALESCE(prod.tracking1_delivered_date, '9999-12-31'),
                        COALESCE(prod.tracking2_delivered_date, '9999-12-31'),
                        COALESCE(prod.tracking3_delivered_date, '9999-12-31'),
                        COALESCE(prod.tracking4_delivered_date, '9999-12-31')
                    ),
                    prod.datedelivered,
                    SUBSTRING_INDEX(prod.estimated_deliverydate, ' to ', 1),
                    '9999-12-31'
                ) as delivery_sort_date"),
            ])
            ->where('prod.ProductModuleLoc', $location)
            ->whereYear('prod.orderdate', 2026)
            ->orderBy('prod.orderdate', 'desc');

        if (!empty($search)) {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('prod.ProductTitle', 'like', "%{$search}%")
                    ->orWhere('prod.rtid', 'like', "%{$search}%")
                    ->orWhere('prod.itemnumber', 'like', "%{$search}%")
                    ->orWhere('prod.trackingnumber', 'like', "%{$search}%")
                    ->orWhere('prod.trackingnumber2', 'like', "%{$search}%")
                    ->orWhere('prod.trackingnumber3', 'like', "%{$search}%")
                    ->orWhere('prod.trackingnumber4', 'like', "%{$search}%")
                    ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                    ->orWhere('prod.ASINviewer', 'like', "%{$search}%")
                    ->orWhere('asin.ASIN', 'like', "%{$search}%")
                    ->orWhere('asin.internal', 'like', "%{$search}%")
                    ->orWhere('asin.system_title', 'like', "%{$search}%")
                    ->orWhere('asin.metakeyword', 'like', "%{$search}%");
            });
        }

        $products = $productsQuery->paginate($perPage);

        $products->getCollection()->transform(function ($product) {
            if (empty($product->asin_code) && !empty($product->ASINviewer)) {
                $product->asin_code = $product->ASINviewer;
            }

            $product->display_asin         = $product->asin_code ?? $product->ASINviewer ?? null;
            $product->asin_quantity_inside  = $product->asin_quantity ?? null;
            $product->tracking_info         = $this->buildTrackingInfo($product);

            unset($product->asin_quantity);

            return $product;
        });

        return response()->json($products);

    } catch (\Exception $e) {
        Log::error('Error in OrdersController index', [
            'message' => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);

        return response()->json([
            'error'   => 'An error occurred while fetching orders',
            'message' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * ✅ NEW: Build tracking information array
     */
    private function buildTrackingInfo($product)
    {
        $trackingInfo = [];
        
        for ($i = 1; $i <= 4; $i++) {
            $trackingField = $i === 1 ? 'trackingnumber' : "trackingnumber{$i}";
            $statusField = "tracking{$i}_status";
            $dateField = "tracking{$i}_delivered_date";
            
            $trackingNumber = $product->{$trackingField} ?? null;
            
            if (!empty($trackingNumber)) {
                $trackingInfo[] = [
                    'number' => $trackingNumber,
                    'status' => $product->{$statusField} ?? 'Unknown',
                    'delivered_date' => $product->{$dateField} ?? null,
                    'index' => $i
                ];
            }
        }
        
        return $trackingInfo;
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


    
/**
 * Update material type for a product
 */
public function updateMaterialType(Request $request, $id)
{
    try {
        $validated = $request->validate([
            'materialtype' => 'required|string|max:255'
        ]);

        $product = tblproduct::findOrFail($id);
        $oldMaterialType = $product->materialtype ?? '';
        $newMaterialType = $validated['materialtype'];

        // Only update if material type changed
        if ($oldMaterialType != $newMaterialType) {
            $product->materialtype = $newMaterialType;
            $product->save();

            // Track material type change
            $employeeName = auth()->user()->username ?? 'System';
            $identifier = "RT#{$product->ProductID}";
            if (!empty($product->ProductTitle)) {
                $identifier .= " - {$product->ProductTitle}";
            }

            $this->trackUpdate(
                'Orders',
                $identifier,
                "Material Type: " . ($oldMaterialType ?: '(empty)'),
                "Material Type: {$newMaterialType}",
                $employeeName
            );

            Log::info("Material type updated for ProductID: {$product->ProductID}", [
                'old' => $oldMaterialType,
                'new' => $newMaterialType
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Material type updated successfully',
                'product' => $product
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'No changes made to material type',
            'product' => $product
        ]);

    } catch (\Exception $e) {
        Log::error('Error updating material type: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while updating material type',
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



public function getIncomingCount(Request $request)
{
    try {
        $search = $request->input('search', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');
        $trackingStatus = $request->input('delivery_status', ''); // Keep param name for frontend compatibility
        $seller = $request->input('seller', '');

        Log::info('Incoming count search params', compact('search', 'dateFrom', 'dateTo', 'trackingStatus', 'seller'));

        // Build base query
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
                // ✅ Use individual tracking delivered dates
                'prod.tracking1_delivered_date',
                'prod.tracking2_delivered_date',
                'prod.tracking3_delivered_date',
                'prod.tracking4_delivered_date',
                // ✅ Use individual tracking statuses
                'prod.tracking1_status',
                'prod.tracking2_status',
                'prod.tracking3_status',
                'prod.tracking4_status',
                'prod.estimated_deliverydate'
            ])
            ->where('prod.ProductModuleLoc', 'Orders')
            ->whereNotNull('prod.ASINviewer')
            ->where('prod.ASINviewer', '!=', '');

        // Apply EXACT search filter
        if (!empty($search)) {
            $subQuery->where(function ($q) use ($search) {
                $q->where('asin.ASIN', '=', $search)
                    ->orWhere('asin.metakeyword', '=', $search)
                    ->orWhere('asin.internal', 'like', "%{$search}%")
                    ->orWhere('asin.system_title', 'like', "%{$search}%")
                    ->orWhere('prod.ProductTitle', 'like', "%{$search}%");
            });
        }

        // Apply seller filter
        if (!empty($seller)) {
            $subQuery->where('prod.seller', 'like', "%{$seller}%");
        }

        // ✅ Apply tracking status filter - check ALL tracking statuses
        if (!empty($trackingStatus)) {
            $subQuery->where(function ($q) use ($trackingStatus) {
                $q->where('prod.tracking1_status', '=', $trackingStatus)
                    ->orWhere('prod.tracking2_status', '=', $trackingStatus)
                    ->orWhere('prod.tracking3_status', '=', $trackingStatus)
                    ->orWhere('prod.tracking4_status', '=', $trackingStatus);
            });
        }

        // ✅ Date range filter using individual tracking dates
        if (!empty($dateFrom) || !empty($dateTo)) {
            $subQuery->where(function ($q) use ($dateFrom, $dateTo) {
                // Check any of the tracking delivered dates
                $q->where(function ($trackingQ) use ($dateFrom, $dateTo) {
                    for ($i = 1; $i <= 4; $i++) {
                        $trackingQ->orWhere(function ($dateQ) use ($i, $dateFrom, $dateTo) {
                            if (!empty($dateFrom) && !empty($dateTo)) {
                                $dateQ->whereBetween("prod.tracking{$i}_delivered_date", [$dateFrom, $dateTo]);
                            } elseif (!empty($dateFrom)) {
                                $dateQ->where("prod.tracking{$i}_delivered_date", '>=', $dateFrom);
                            } elseif (!empty($dateTo)) {
                                $dateQ->where("prod.tracking{$i}_delivered_date", '<=', $dateTo);
                            }
                        });
                    }
                });
                
                // Also check estimated_deliverydate VARCHAR field
                $q->orWhere(function ($subQ) use ($dateFrom, $dateTo) {
                    if (!empty($dateFrom)) {
                        $subQ->where('prod.estimated_deliverydate', 'like', "%{$dateFrom}%");
                    }
                    if (!empty($dateTo)) {
                        $subQ->orWhere('prod.estimated_deliverydate', 'like', "%{$dateTo}%");
                    }
                    if (!empty($dateFrom) && !empty($dateTo)) {
                        $subQ->orWhere(function ($dateQ) use ($dateFrom, $dateTo) {
                            $dateQ->whereRaw("
                                (SUBSTRING_INDEX(prod.estimated_deliverydate, ' to ', 1) BETWEEN ? AND ?)
                                OR (SUBSTRING_INDEX(prod.estimated_deliverydate, ' to ', -1) BETWEEN ? AND ?)
                            ", [$dateFrom, $dateTo, $dateFrom, $dateTo]);
                        });
                    }
                });
            });
        }

        // Group the results
        $query = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
            ->mergeBindings($subQuery)
            ->select([
                'sub.asin',
                'sub.title',
                DB::raw('GROUP_CONCAT(DISTINCT sub.seller ORDER BY sub.seller SEPARATOR ", ") as sellers'),
                DB::raw('SUM(COALESCE(sub.quantity, 1)) as total_quantity'),
                // ✅ Get earliest delivery date - use MIN with NULLIF to ignore nulls
                DB::raw('MIN(
                    CASE 
                        WHEN sub.tracking1_delivered_date IS NOT NULL THEN sub.tracking1_delivered_date
                        WHEN sub.tracking2_delivered_date IS NOT NULL THEN sub.tracking2_delivered_date
                        WHEN sub.tracking3_delivered_date IS NOT NULL THEN sub.tracking3_delivered_date
                        WHEN sub.tracking4_delivered_date IS NOT NULL THEN sub.tracking4_delivered_date
                        ELSE SUBSTRING_INDEX(sub.estimated_deliverydate, " to ", 1)
                    END
                ) as earliest_delivery'),
                // ✅ Get latest delivery date
                DB::raw('MAX(
                    CASE 
                        WHEN sub.tracking1_delivered_date IS NOT NULL THEN sub.tracking1_delivered_date
                        WHEN sub.tracking2_delivered_date IS NOT NULL THEN sub.tracking2_delivered_date
                        WHEN sub.tracking3_delivered_date IS NOT NULL THEN sub.tracking3_delivered_date
                        WHEN sub.tracking4_delivered_date IS NOT NULL THEN sub.tracking4_delivered_date
                        ELSE SUBSTRING_INDEX(sub.estimated_deliverydate, " to ", -1)
                    END
                ) as latest_delivery'),
                // ✅ Get primary tracking status (prioritize Delivered > In Transit > others)
                DB::raw("MAX(CASE
                    WHEN sub.tracking1_status = 'Delivered' OR sub.tracking2_status = 'Delivered' OR sub.tracking3_status = 'Delivered' OR sub.tracking4_status = 'Delivered' THEN 'Delivered'
                    WHEN sub.tracking1_status = 'In Transit' OR sub.tracking2_status = 'In Transit' OR sub.tracking3_status = 'In Transit' OR sub.tracking4_status = 'In Transit' THEN 'In Transit'
                    WHEN sub.tracking1_status = 'Exception' OR sub.tracking2_status = 'Exception' OR sub.tracking3_status = 'Exception' OR sub.tracking4_status = 'Exception' THEN 'Exception'
                    WHEN sub.tracking1_status = 'Out for Delivery' OR sub.tracking2_status = 'Out for Delivery' OR sub.tracking3_status = 'Out for Delivery' OR sub.tracking4_status = 'Out for Delivery' THEN 'Out for Delivery'
                    ELSE COALESCE(sub.tracking1_status, sub.tracking2_status, sub.tracking3_status, sub.tracking4_status, 'Unknown')
                END) as delivery_status"),
                // ✅ Flag to indicate if we have actual dates
                DB::raw('MAX(CASE 
                    WHEN sub.tracking1_delivered_date IS NOT NULL OR sub.tracking2_delivered_date IS NOT NULL OR sub.tracking3_delivered_date IS NOT NULL OR sub.tracking4_delivered_date IS NOT NULL 
                    THEN 1 ELSE 0 
                END) as has_actual_date')
            ])
            ->groupBy('sub.asin', 'sub.title')
            ->orderByDesc('total_quantity');

        $results = $query->get();

        Log::info('Query results count', ['count' => $results->count()]);

        // Transform results
        $results = $results->map(function ($item) {
            return [
                'asin' => $item->asin,
                'title' => $item->title ?: 'No Title',
                'sellers' => $item->sellers ?: 'N/A',
                'total_quantity' => (int) $item->total_quantity,
                'earliest_delivery' => $item->earliest_delivery,
                'latest_delivery' => $item->latest_delivery,
                'delivery_status' => $item->delivery_status ?: 'Unknown',
                'has_actual_date' => (bool) $item->has_actual_date
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
            'line' => $e->getLine()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'An error occurred',
            'error' => $e->getMessage(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : null
        ], 500);
    }
}

// ✅ Details endpoint
public function getIncomingCountDetails(Request $request)
{
    try {
        $asin = $request->input('asin', '');
        $search = $request->input('search', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');
        $trackingStatus = $request->input('delivery_status', '');
        $seller = $request->input('seller', '');

        $query = DB::table($this->productTable . ' as prod')
            ->leftJoin($this->asinTable . ' as asin', 'prod.ASINviewer', '=', 'asin.ASIN')
            ->select([
                'prod.ProductID',
                'prod.rtcounter',
                'prod.ProductTitle',
                'prod.quantity',
                // ✅ Include all tracking fields
                'prod.trackingnumber',
                'prod.trackingnumber2',
                'prod.trackingnumber3',
                'prod.trackingnumber4',
                'prod.tracking1_status',
                'prod.tracking2_status',
                'prod.tracking3_status',
                'prod.tracking4_status',
                'prod.tracking1_delivered_date',
                'prod.tracking2_delivered_date',
                'prod.tracking3_delivered_date',
                'prod.tracking4_delivered_date',
                'prod.estimated_deliverydate',
                'prod.serialnumber',
                'prod.warehouselocation',
                'prod.seller',
                'asin.ASIN as asin_code',
                DB::raw("COALESCE(
                    NULLIF(TRIM(asin.system_title), ''), 
                    NULLIF(TRIM(asin.internal), ''), 
                    NULLIF(TRIM(prod.ProductTitle), '')
                ) as display_title"),
                // ✅ Computed delivery status
                DB::raw("CASE
                    WHEN prod.tracking1_status = 'Delivered' OR prod.tracking2_status = 'Delivered' OR prod.tracking3_status = 'Delivered' OR prod.tracking4_status = 'Delivered' THEN 'Delivered'
                    WHEN prod.tracking1_status = 'In Transit' OR prod.tracking2_status = 'In Transit' OR prod.tracking3_status = 'In Transit' OR prod.tracking4_status = 'In Transit' THEN 'In Transit'
                    WHEN prod.tracking1_status = 'Exception' OR prod.tracking2_status = 'Exception' OR prod.tracking3_status = 'Exception' OR prod.tracking4_status = 'Exception' THEN 'Exception'
                    ELSE COALESCE(prod.tracking1_status, prod.tracking2_status, prod.tracking3_status, prod.tracking4_status, 'Unknown')
                END as delivery_status"),
                // ✅ Earliest delivered date
                DB::raw('CASE 
                    WHEN prod.tracking1_delivered_date IS NOT NULL THEN prod.tracking1_delivered_date
                    WHEN prod.tracking2_delivered_date IS NOT NULL THEN prod.tracking2_delivered_date
                    WHEN prod.tracking3_delivered_date IS NOT NULL THEN prod.tracking3_delivered_date
                    WHEN prod.tracking4_delivered_date IS NOT NULL THEN prod.tracking4_delivered_date
                    ELSE NULL
                END as datedelivered')
            ])
            ->where('prod.ProductModuleLoc', 'Orders');

        if (!empty($asin)) {
            $query->where(function ($q) use ($asin) {
                $q->where('asin.ASIN', $asin)
                    ->orWhere('prod.ASINviewer', $asin);
            });
        }

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

        // Seller filter
        if (!empty($seller)) {
            $query->where('prod.seller', 'like', "%{$seller}%");
        }

        // ✅ Tracking status filter
        if (!empty($trackingStatus)) {
            $query->where(function ($q) use ($trackingStatus) {
                $q->where('prod.tracking1_status', '=', $trackingStatus)
                    ->orWhere('prod.tracking2_status', '=', $trackingStatus)
                    ->orWhere('prod.tracking3_status', '=', $trackingStatus)
                    ->orWhere('prod.tracking4_status', '=', $trackingStatus);
            });
        }

        // ✅ Date filter using tracking dates
        if (!empty($dateFrom) || !empty($dateTo)) {
            $query->where(function ($q) use ($dateFrom, $dateTo) {
                // Check any tracking delivered date
                $q->where(function ($trackingQ) use ($dateFrom, $dateTo) {
                    for ($i = 1; $i <= 4; $i++) {
                        $trackingQ->orWhere(function ($dateQ) use ($i, $dateFrom, $dateTo) {
                            if (!empty($dateFrom) && !empty($dateTo)) {
                                $dateQ->whereBetween("prod.tracking{$i}_delivered_date", [$dateFrom, $dateTo]);
                            } elseif (!empty($dateFrom)) {
                                $dateQ->where("prod.tracking{$i}_delivered_date", '>=', $dateFrom);
                            } elseif (!empty($dateTo)) {
                                $dateQ->where("prod.tracking{$i}_delivered_date", '<=', $dateTo);
                            }
                        });
                    }
                });
                
                // Also check VARCHAR estimated_deliverydate
                $q->orWhere(function ($subQ) use ($dateFrom, $dateTo) {
                    if (!empty($dateFrom)) {
                        $subQ->where('prod.estimated_deliverydate', 'like', "%{$dateFrom}%");
                    }
                    if (!empty($dateTo)) {
                        $subQ->orWhere('prod.estimated_deliverydate', 'like', "%{$dateTo}%");
                    }
                });
            });
        }

        $query->orderByDesc('prod.ProductID');

        $items = $query->get();

        $totalQuantity = $items->sum(function ($item) {
            return (int) ($item->quantity ?? 1);
        });

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
