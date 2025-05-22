<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use DateTime;
use DateTimeZone;

class FbmOrderController extends BasetablesController
{
    /**
     * Main method for getting FBM orders data
     */
   public function index(Request $request)
{
    try {
        // Get pagination parameters
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search', '');
        $storeFilter = $request->input('store', '');
        $statusFilter = $request->input('status', '');
        
        // Base query for orders
        $query = DB::table('tbloutboundorders')
            ->select(
                'outboundorderid', 
                'platform', 
                'storename', 
                'platform_order_id',
                'FulfillmentChannel',
                'BuyerName as buyer_name',
                DB::raw("CONCAT(COALESCE(address_line1, ''), ', ', COALESCE(city, ''), ', ', COALESCE(StateOrRegion, ''), ' ', COALESCE(postal_code, '')) as address"),
                'PurchaseDate as purchase_date',
                'ship_date',
                'delivery_date',
                'ShipmentServiceLevelCategory as shipment_service',
                'OrderType as order_type',
                'ordernote',
                'IsReplacementOrder as is_replacement'
            )
            ->where('FulfillmentChannel', 'MFN');
            
        // Apply filters if provided
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('platform_order_id', 'LIKE', "%{$search}%")
                  ->orWhere('BuyerName', 'LIKE', "%{$search}%");
            });
        }
        
        if (!empty($storeFilter)) {
            $query->where('storename', $storeFilter);
        }
        
        // Get total for pagination
        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $perPage);
        
        // Get paginated orders
        $orders = $query->orderBy('PurchaseDate', 'desc')
                      ->skip(($page - 1) * $perPage)
                      ->take($perPage)
                      ->get();
        
        // Get orders with their items
        $formattedOrders = [];
        foreach ($orders as $order) {
            $orderData = (array) $order;
            
            // Get items for this order - JOIN with product table to get additional details
            $items = DB::table('tbloutboundordersitem AS oi')
                ->select(
                    'oi.outboundorderitemid',
                    'oi.platform_order_id',
                    'oi.platform_order_item_id',
                    'oi.platform_sku',
                    'oi.platform_asin',
                    'oi.platform_title',
                    'oi.ConditionId',
                    'oi.ConditionSubtypeId',
                    'oi.order_status',
                    'oi.QuantityOrdered as quantity_ordered',
                    'oi.QuantityShipped as quantity_shipped',
                    'oi.trackingnumber as tracking_number',
                    'oi.trackingstatus as tracking_status',
                    'oi.unit_price',
                    'oi.unit_tax',
                    'oi.ProductID as product_id',
                    // Join with product table to get these details when product_id exists
                    'p.warehouseLocation',
                    'p.serialNumber',
                    'p.rtCounter',
                    'p.FNSKUviewer as FNSKU'
                )
                ->leftJoin('tblproduct AS p', 'oi.ProductID', '=', 'p.ProductID')
                ->where('oi.platform_order_id', $order->platform_order_id)
                ->get();
            
            // Format items with condition - pass store name for store-specific formatting
            $formattedItems = [];
            foreach ($items as $item) {
                $itemArray = (array) $item;
                $itemArray['condition'] = $this->formatCondition($item->ConditionId, $item->ConditionSubtypeId, $order->storename);
                $formattedItems[] = $itemArray;
            }
            
            // Add items to order
            $orderData['items'] = $formattedItems;
            
            // Set order status based on items
            if (!empty($formattedItems)) {
                $statuses = array_column($formattedItems, 'order_status');
                
                if (in_array('Canceled', $statuses)) {
                    $orderData['order_status'] = 'Canceled';
                } elseif (in_array('Pending', $statuses)) {
                    $orderData['order_status'] = 'Pending';
                } elseif (count(array_filter($statuses, function($s) { return $s == 'Shipped'; })) == count($statuses)) {
                    $orderData['order_status'] = 'Shipped';
                } else {
                    $orderData['order_status'] = 'Unshipped';
                }
            } else {
                $orderData['order_status'] = 'Pending';
            }
            
            $formattedOrders[] = $orderData;
        }
        
        // Additional status filtering if needed
        if (!empty($statusFilter)) {
            $formattedOrders = array_filter($formattedOrders, function($order) use ($statusFilter) {
                return $order['order_status'] === $statusFilter;
            });
            $formattedOrders = array_values($formattedOrders);
        }
        
        return response()->json([
            'success' => true,
            'data' => $formattedOrders,
            'total' => $totalCount,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $totalPages
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error fetching FBM orders: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json(['success' => false, 'message' => 'Error fetching orders', 'error' => $e->getMessage()], 500);
    }
}
    
    /**
     * Get list of stores for filtering
     */
    public function getStores()
    {
        try {
            $stores = DB::table('tbloutboundorders')
                ->select('storename')
                ->where('FulfillmentChannel', 'MFN')
                ->distinct()
                ->pluck('storename')
                ->toArray();

            return response()->json($stores);
        } catch (\Exception $e) {
            Log::error('Error fetching stores: ' . $e->getMessage());
            return response()->json(['error' => 'Error fetching stores'], 500);
        }
    }

    /**
     * Find matching products for auto dispense with store-specific condition handling
     */
public function findDispenseProducts(Request $request)
{
    try {
        Log::info('findDispenseProducts request received', $request->all());
        
        // Validate request
        $request->validate([
            'order_id' => 'required|integer',
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer'
        ]);

        // Get the order's store name for condition formatting
        $order = DB::table('tbloutboundorders')
            ->select('outboundorderid', 'storename', 'platform_order_id')
            ->where('outboundorderid', $request->order_id)
            ->first();
            
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }
        
        $storeName = $order->storename;
        $normalizedStoreName = $this->normalizeStoreName($storeName);
        Log::info('Processing order from store: ' . $storeName . ' (normalized: ' . $normalizedStoreName . ')');

        // Create a pool of available products by ASIN+condition
        $productPool = [];
        
        // Get order items
        $items = DB::table('tbloutboundordersitem')
            ->select(
                'outboundorderitemid',
                'platform_order_id',
                'platform_order_item_id',
                'platform_sku',
                'platform_asin',
                'platform_title',
                'ConditionId',
                'ConditionSubtypeId',
                'QuantityOrdered'
            )
            ->whereIn('outboundorderitemid', $request->item_ids)
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No items found for dispense'
            ], 404);
        }

        // First, find all products that match our order criteria
        foreach ($items as $item) {
            if (empty($item->platform_asin)) continue;
            
            $itemCondition = $this->formatCondition($item->ConditionId, $item->ConditionSubtypeId, $storeName);
            $key = $item->platform_asin . '-' . $itemCondition;
            
            if (!isset($productPool[$key])) {
                // Find all matching products for this ASIN+condition
                $productPool[$key] = $this->findMatchingProductsForItem($item, $storeName, $normalizedStoreName);
            }
        }
        
        // Keep track of allocated products
        $allocatedProductIds = [];
        
        // Results array for API response
        $results = [];
        
        // Now, for each order item, assign a unique product
        foreach ($items as $item) {
            if (empty($item->platform_asin)) continue;
            
            $itemCondition = $this->formatCondition($item->ConditionId, $item->ConditionSubtypeId, $storeName);
            $key = $item->platform_asin . '-' . $itemCondition;
            
            // Get available products for this type
            $availableProducts = $productPool[$key] ?? [];
            
            // Find the first product that hasn't been allocated yet
            $selectedProduct = null;
            foreach ($availableProducts as $product) {
                if (!in_array($product['ProductID'], $allocatedProductIds)) {
                    $selectedProduct = $product;
                    $allocatedProductIds[] = $product['ProductID'];
                    break;
                }
            }
            
            // Add to results - only include the single selected product, or empty array if none found
            $results[] = [
                'item_id' => $item->outboundorderitemid,
                'ordered_item' => $item,
                'ordered_condition' => $itemCondition,
                'matching_products' => $selectedProduct ? [$selectedProduct] : []
            ];
        }

        Log::info('findDispenseProducts completed successfully', ['items_with_matches' => count($results)]);
        
        return response()->json([
            'success' => true,
            'data' => $results
        ]);

    } catch (\Exception $e) {
        Log::error('Error finding dispense products: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json([
            'success' => false, 
            'message' => 'Error finding dispense products', 
            'error' => $e->getMessage(),
            'trace' => explode("\n", $e->getTraceAsString()),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
}

// New helper method to find matching products for an item
private function findMatchingProductsForItem($item, $storeName, $normalizedStoreName)
{
    $originalConditionId = $item->ConditionId;
    $originalSubtypeId = $item->ConditionSubtypeId;
    
    // Build the query differently depending on the store
    $asinQuery = DB::table('tblasin')
        ->select([
            'tblasin.ASIN',
            'tblfnsku.MSKU as MSKUviewer',
            'tblasin.internal as AStitle',
            'tblfnsku.storename',
            'tblfnsku.grading',
            'tblfnsku.FNSKU',
            'tblproduct.FBMAvailable',
            'tblproduct.ProductID',
            'tblproduct.warehouseLocation',
            'tblproduct.serialNumber',
            'tblproduct.rtCounter',
            'tblproduct.stockroom_insert_date'
        ])
        ->leftJoin('tblfnsku', 'tblasin.ASIN', '=', 'tblfnsku.ASIN')
        ->leftJoin('tblproduct', function ($join) {
            $join->on('tblfnsku.FNSKU', '=', 'tblproduct.FNSKUviewer');
        })
        ->where('tblasin.ASIN', $item->platform_asin);
        
    // Add availability filter
    if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
        $asinQuery->where('tblproduct.FBMAvailable', '>', 0);
    }
        
    // Add location filter if column exists
    if (Schema::hasColumn('tblproduct', 'ProductModuleLoc')) {
        $asinQuery->where('tblproduct.ProductModuleLoc', 'Stockroom');
    }
    
    // Add store-specific filtering
    if ($normalizedStoreName === 'allrenewed') {
        // For AllRenewed, match on storename with various patterns
        $asinQuery->where(function($q) {
            $q->where('tblfnsku.storename', 'All Renewed')
                ->orWhere('tblfnsku.storename', 'AllRenewed')
                ->orWhere('tblfnsku.storename', 'Allrenewed');
        });
        
        // For AllRenewed, only match on the "New" condition in the database
        // regardless of the ConditionSubtypeId
        $asinQuery->where('tblfnsku.grading', 'New');
    } else {
        // For other stores like Renovartech, match the exact store name
        $asinQuery->where('tblfnsku.storename', $storeName);
        
        // For other stores, match the exact condition
        $asinQuery->where('tblfnsku.grading', $originalConditionId);
    }
    
    // Order by stockroom_insert_date ASC for FIFO - oldest products first
    if (Schema::hasColumn('tblproduct', 'stockroom_insert_date')) {
        $asinQuery->orderBy('tblproduct.stockroom_insert_date', 'asc');
    }
    
    // Execute the query
    $matchingProducts = $asinQuery->get();
    
    // Format matching products
    $formattedProducts = [];
    foreach ($matchingProducts as $product) {
        // Get and normalize the product's store name
        $productStore = $product->storename ?? '';
        $productGrading = $product->grading ?? '';
        
        // Format condition display (for UI)
        $productCondition = $this->formatCondition($productGrading, '', $productStore);
        
        // Format insert date for display if available
        $stockroomDate = null;
        if (isset($product->stockroom_insert_date)) {
            $stockroomDate = $product->stockroom_insert_date;
        }
        
        // Add this product to formatted results
        $formattedProducts[] = [
            'ProductID' => $product->ProductID,
            'asin' => $product->ASIN,
            'msku' => $product->MSKUviewer,
            'title' => $product->AStitle ?? 'No title',
            'store' => $product->storename ?? 'No store',
            'condition' => $productCondition,
            'fbm_available' => $product->FBMAvailable ?? 0,
            'grading' => $productGrading,
            'warehouseLocation' => $product->warehouseLocation ?? '',
            'serialNumber' => $product->serialNumber ?? '',
            'rtCounter' => $product->rtCounter ?? '',
            'fnsku' => $product->FNSKU ?? '',
            'stockroom_insert_date' => $stockroomDate
        ];
    }
    
    return $formattedProducts;
}
    /**
     * Perform auto dispense (assign products to order items)
     */
 public function dispense(Request $request)
{
    try {
        // Validate request
        $request->validate([
            'order_id' => 'required|integer',
            'dispense_items' => 'required|array',
            'dispense_items.*.item_id' => 'required|integer',
            'dispense_items.*.product_id' => 'required|integer'
        ]);

        // Start transaction
        DB::beginTransaction();

        // Safety check: Make sure we're not dispensing the same product ID multiple times
        $productIds = array_column($request->dispense_items, 'product_id');
        $uniqueProductIds = array_unique($productIds);
        
        // If we have duplicate product IDs, return an error
        if (count($productIds) !== count($uniqueProductIds)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Cannot dispense the same product multiple times within one order. Please select different products for each order item.'
            ], 400);
        }
        
        // Also check if any of the requested products are already assigned to another order
        $alreadyAssignedProducts = DB::table('tbloutboundordersitem')
            ->whereIn('ProductID', $productIds)
            ->whereNotNull('ProductID')
            ->count();
            
        if ($alreadyAssignedProducts > 0) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'One or more selected products are already assigned to other orders. Please refresh and try again.'
            ], 400);
        }

        // Process each item - store the ProductID
        foreach ($request->dispense_items as $dispenseItem) {
            $itemId = $dispenseItem['item_id'];
            $productId = $dispenseItem['product_id'];
            
            // Update the order item with product ID 
            DB::table('tbloutboundordersitem')
                ->where('outboundorderitemid', $itemId)
                ->update([
                    'ProductID' => $productId,
                    'updated_at' => now()
                ]);
            
            // Decrement the FBMAvailable count for the product
            DB::table('tblproduct')
                ->where('ProductID', $productId)
                ->decrement('FBMAvailable', 1);
        }
        
        // Add note to order
        $currentNote = DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->value('ordernote');
        
        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        $timestamp = $dateTime->format('Y-m-d H:i:s');
        
        $dispenseNote = $timestamp . " - Auto dispense completed for " . count($request->dispense_items) . " items";
        
        $newNote = $currentNote 
            ? $currentNote . "\n\n" . $dispenseNote
            : $dispenseNote;
        
        DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->update([
                'ordernote' => $newNote,
                'updated_at' => now()
            ]);

        DB::commit();
        
        return response()->json([
            'success' => true,
            'message' => 'Items dispensed successfully'
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error dispensing items: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json([
            'success' => false, 
            'message' => 'Error dispensing items', 
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Cancel auto dispense
     */
    public function cancelDispense(Request $request)
{
    try {
        // Validate request
        $request->validate([
            'order_id' => 'required|integer',
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer'
        ]);

        // Start transaction
        DB::beginTransaction();

        // IMPORTANT: Get the current product IDs before removing them (to restore availability)
        $itemsWithProducts = DB::table('tbloutboundordersitem')
            ->select('outboundorderitemid', 'ProductID')
            ->whereIn('outboundorderitemid', $request->item_ids)
            ->whereNotNull('ProductID')
            ->get();
            
        // Get product IDs to increment FBMAvailable counts
        $productIds = [];
        foreach ($itemsWithProducts as $item) {
            if ($item->ProductID) {
                $productIds[] = $item->ProductID;
            }
        }

        // Update items to remove product_id and related fields
        DB::table('tbloutboundordersitem')
            ->whereIn('outboundorderitemid', $request->item_ids)
            ->update([
                'ProductID' => null,
                'updated_at' => now()
            ]);
            
        // CRITICAL FIX: Increment FBMAvailable for all affected products
        if (!empty($productIds)) {
            foreach ($productIds as $productId) {
                DB::table('tblproduct')
                    ->where('ProductID', $productId)
                    ->increment('FBMAvailable', 1);
            }
        }

        // Add note to order
        $currentNote = DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->value('ordernote');
        
        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        $timestamp = $dateTime->format('Y-m-d H:i:s');
        
        $cancelNote = $timestamp . " - Auto dispense canceled for " . count($request->item_ids) . " items";
        
        $newNote = $currentNote 
            ? $currentNote . "\n\n" . $cancelNote
            : $cancelNote;
        
        DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->update([
                'ordernote' => $newNote,
                'updated_at' => now()
            ]);

        DB::commit();
        
        return response()->json([
            'success' => true,
            'message' => 'Dispense canceled successfully'
        ]);

    } catch (\Exception $e) {
        DB::rollback();
        Log::error('Error canceling dispense: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json(['success' => false, 'message' => 'Error canceling dispense', 'error' => $e->getMessage()], 500);
    }
}

    /**
     * Process an order (update status, tracking info)
     */
    public function processOrder(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'order_id' => 'required|integer',
                'item_ids' => 'required|array',
                'item_ids.*' => 'integer',
                'shipment_type' => 'required|string',
                'tracking_number' => 'required|string',
                'notes' => 'nullable|string'
            ]);

            // Start transaction
            DB::beginTransaction();

            // Process each item
            foreach ($request->item_ids as $itemId) {
                // Basic update data
                $updateData = [
                    'order_status' => 'Shipped',
                    'trackingnumber' => $request->tracking_number,
                    'QuantityShipped' => DB::raw('QuantityOrdered'),
                    'updated_at' => now()
                ];
                
                // Update the order item
                DB::table('tbloutboundordersitem')
                    ->where('outboundorderitemid', $itemId)
                    ->update($updateData);
            }

            // Add note to order if provided
            if ($request->notes) {
                $currentNote = DB::table('tbloutboundorders')
                    ->where('outboundorderid', $request->order_id)
                    ->value('ordernote');
                
                $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
                $timestamp = $dateTime->format('Y-m-d H:i:s');
                
                $newNote = $currentNote 
                    ? $currentNote . "\n\n" . $timestamp . " - Processing: " . $request->notes
                    : $timestamp . " - Processing: " . $request->notes;
                
                DB::table('tbloutboundorders')
                    ->where('outboundorderid', $request->order_id)
                    ->update([
                        'ordernote' => $newNote,
                        'updated_at' => now()
                    ]);
            }

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Order processed successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error processing order: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error processing order', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate and print packing slip
     */
    public function generatePackingSlip(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|integer'
            ]);

            $orderId = $request->order_id;
            
            // Get order details
            $order = DB::table('tbloutboundorders')
                ->where('outboundorderid', $orderId)
                ->first();
                
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }
            
            // Get order items
            $items = DB::table('tbloutboundordersitem')
                ->where('outboundorderid', $orderId)
                ->get();
                
            if ($items->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No items found for this order'], 404);
            }

            // Generate PDF (this would be implemented based on your PDF generation library)
            // For this example, we'll just return success
            
            return response()->json([
                'success' => true,
                'message' => 'Packing slip generated successfully',
                'order_id' => $order->platform_order_id
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating packing slip: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error generating packing slip', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate and print shipping label
     */
    public function printShippingLabel(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|integer'
            ]);

            $orderId = $request->order_id;
            
            // Get order details
            $order = DB::table('tbloutboundorders')
                ->where('outboundorderid', $orderId)
                ->first();
                
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }
            
            // Here you would implement the logic to generate and print the shipping label
            // This could involve calling a shipping API (USPS, UPS, FedEx, etc.)
            
            return response()->json([
                'success' => true,
                'message' => 'Shipping label printed successfully',
                'order_id' => $order->platform_order_id
            ]);

        } catch (\Exception $e) {
            Log::error('Error printing shipping label: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error printing shipping label', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|integer'
            ]);

            $orderId = $request->order_id;
            
            // Start transaction
            DB::beginTransaction();
            
            // Update all items to Canceled status
            DB::table('tbloutboundordersitem')
                ->where('outboundorderid', $orderId)
                ->update([
                    'order_status' => 'Canceled',
                    'updated_at' => now()
                ]);
            
            // Add cancellation note
            $currentNote = DB::table('tbloutboundorders')
                ->where('outboundorderid', $orderId)
                ->value('ordernote');
            
            $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
            $timestamp = $dateTime->format('Y-m-d H:i:s');
            
            $cancelNote = $timestamp . " - Order canceled";
            
            $newNote = $currentNote 
                ? $currentNote . "\n\n" . $cancelNote
                : $cancelNote;
            
            DB::table('tbloutboundorders')
                ->where('outboundorderid', $orderId)
                ->update([
                    'ordernote' => $newNote,
                    'updated_at' => now()
                ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Order canceled successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error canceling order: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error canceling order', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Normalize store name for consistent comparison
     * 
     * @param string $storeName The store name to normalize
     * @return string Normalized store name (lowercase, no spaces)
     */
    private function normalizeStoreName($storeName)
    {
        // Remove spaces, hyphens, underscores and convert to lowercase
        return strtolower(preg_replace('/[\s\-_]+/', '', $storeName));
    }

    /**
     * Format the condition from ID and subtype with store-specific handling
     * 
     * @param string $conditionId The condition ID
     * @param string $conditionSubtypeId The condition subtype ID
     * @param string $storeName The store name for store-specific formatting (optional)
     * @return string The formatted condition
     */
    private function formatCondition($conditionId, $conditionSubtypeId, $storeName = null)
    {
        // Normalize store name for consistent comparison
        $normalizedStoreName = $this->normalizeStoreName($storeName);
        
        // Special handling for AllRenewed store (now matches both "All Renewed" and "Allrenewed")
        if ($normalizedStoreName === 'allrenewed') {
            $combinedCondition = $conditionId . $conditionSubtypeId;
            
            switch ($combinedCondition) {
                case 'NewNew':
                    return 'Refurbished - Excellent';
                case 'NewGood':
                    return 'Refurbished - Good';
                case 'NewAcceptable':
                    return 'Refurbished - Acceptable';
                default:
                    // Fallback to normal formatting if condition combination is not recognized
                    break;
            }
        }
        
        // Default condition mapping (used for other stores or fallback)
        $conditionMap = [
            'New' => 'New',
            'Used' => 'Used',
            'Refurbished' => 'Refurbished',
            // Add other conditions as needed
        ];
        
        $subtypeMap = [
            'New' => 'New',
            'Like New' => 'LikeNew',
            'Very Good' => 'VeryGood',
            'Good' => 'Good',
            'Acceptable' => 'Acceptable',
            // Add other subtypes as needed
        ];
        
        $condition = $conditionMap[$conditionId] ?? $conditionId;
        $subtype = $subtypeMap[$conditionSubtypeId] ?? $conditionSubtypeId;
        
        return $condition . $subtype;
    }


    public function getOrderDetail(Request $request)
{
    try {
        // Validate request
        $request->validate([
            'order_id' => 'required|integer'
        ]);
        
        $orderId = $request->input('order_id');
        
        // Get the order
        $order = DB::table('tbloutboundorders')
            ->select(
                'outboundorderid', 
                'platform', 
                'storename', 
                'platform_order_id',
                'FulfillmentChannel',
                'BuyerName as buyer_name',
                DB::raw("CONCAT(COALESCE(address_line1, ''), ', ', COALESCE(city, ''), ', ', COALESCE(StateOrRegion, ''), ' ', COALESCE(postal_code, '')) as address"),
                'PurchaseDate as purchase_date',
                'ship_date',
                'delivery_date',
                'ShipmentServiceLevelCategory as shipment_service',
                'OrderType as order_type',
                'ordernote',
                'IsReplacementOrder as is_replacement'
            )
            ->where('outboundorderid', $orderId)
            ->first();
            
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }
        
        $orderData = (array) $order;
        
        // Get items for this order with product details
        $items = DB::table('tbloutboundordersitem AS oi')
            ->select(
                'oi.outboundorderitemid',
                'oi.outboundorderid', // Add this to match the regular API response
                'oi.platform_order_id',
                'oi.platform_order_item_id',
                'oi.platform_sku',
                'oi.platform_asin',
                'oi.platform_title',
                'oi.ConditionId',
                'oi.ConditionSubtypeId',
                'oi.order_status',
                'oi.QuantityOrdered as quantity_ordered',
                'oi.QuantityShipped as quantity_shipped',
                'oi.trackingnumber as tracking_number',
                'oi.trackingstatus as tracking_status',
                'oi.unit_price',
                'oi.unit_tax',
                'oi.ProductID as product_id',
                'p.warehouseLocation',
                'p.serialNumber',
                'p.rtCounter',
                'p.FNSKUviewer as FNSKU'
            )
            ->leftJoin('tblproduct AS p', 'oi.ProductID', '=', 'p.ProductID')
            ->where('oi.outboundorderid', $orderId)
            ->get();
        
        // Format items with condition
        $formattedItems = [];
        foreach ($items as $item) {
            $itemArray = (array) $item;
            $itemArray['condition'] = $this->formatCondition($item->ConditionId, $item->ConditionSubtypeId, $order->storename);
            $formattedItems[] = $itemArray;
        }
        
        // Add items to order
        $orderData['items'] = $formattedItems;
        
        // Set order status based on items
        if (!empty($formattedItems)) {
            $statuses = array_column($formattedItems, 'order_status');
            
            if (in_array('Canceled', $statuses)) {
                $orderData['order_status'] = 'Canceled';
            } elseif (in_array('Pending', $statuses)) {
                $orderData['order_status'] = 'Pending';
            } elseif (count(array_filter($statuses, function($s) { return $s == 'Shipped'; })) == count($statuses)) {
                $orderData['order_status'] = 'Shipped';
            } else {
                $orderData['order_status'] = 'Unshipped';
            }
        } else {
            $orderData['order_status'] = 'Pending';
        }
        
        return response()->json([
            'success' => true,
            'data' => $orderData
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error fetching order detail: ' . $e->getMessage());
        return response()->json([
            'success' => false, 
            'message' => 'Error fetching order detail', 
            'error' => $e->getMessage()
        ], 500);
    }
  }
}