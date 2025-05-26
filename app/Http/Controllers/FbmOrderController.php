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
        
        Log::info('FBM Orders index called with params:', [
            'per_page' => $perPage,
            'page' => $page,
            'search' => $search,
            'store' => $storeFilter,
            'status' => $statusFilter
        ]);
        
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
            ->where('FulfillmentChannel', 'FBM');
            
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
        
        Log::info('Query built, total count: ' . $totalCount);
        
        // Get paginated orders
        $orders = $query->orderBy('PurchaseDate', 'desc')
                      ->skip(($page - 1) * $perPage)
                      ->take($perPage)
                      ->get();
        
        Log::info('Orders fetched: ' . $orders->count());
        
        // Get orders with their items
        $formattedOrders = [];
        foreach ($orders as $order) {
            $orderData = (array) $order;
            
            try {
                // Get items for this order
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
                        'oi.ProductID as product_id' // Keep this for backward compatibility
                    )
                    ->where('oi.platform_order_id', $order->platform_order_id)
                    ->get();
                
                Log::info('Items fetched for order ' . $order->platform_order_id . ': ' . $items->count());
                
                // Format items with condition and get dispensed product details
                $formattedItems = [];
                foreach ($items as $item) {
                    $itemArray = (array) $item;
                    
                    try {
                        $itemArray['condition'] = $this->formatCondition($item->ConditionId, $item->ConditionSubtypeId, $order->storename);
                        
                        // Get all dispensed products for this item
                        $dispensedProducts = $this->getDispensedProductsForItem($item->outboundorderitemid);
                        
                        // If we have dispensed products, add their details to the item
                        if (!empty($dispensedProducts)) {
                            // For backward compatibility, keep the first product_id
                            $itemArray['product_id'] = $dispensedProducts[0]['product_id'];
                            
                            // Add detailed information from the first dispensed product
                            $itemArray['warehouseLocation'] = $dispensedProducts[0]['warehouseLocation'] ?? '';
                            $itemArray['serialNumber'] = $dispensedProducts[0]['serialNumber'] ?? '';
                            $itemArray['rtCounter'] = $dispensedProducts[0]['rtCounter'] ?? '';
                            $itemArray['FNSKU'] = $dispensedProducts[0]['FNSKU'] ?? '';
                            
                            // Add all dispensed products array for multiple quantity handling
                            $itemArray['dispensed_products'] = $dispensedProducts;
                            $itemArray['dispensed_count'] = count($dispensedProducts);
                        } else {
                            // No dispensed products
                            $itemArray['product_id'] = null;
                            $itemArray['warehouseLocation'] = '';
                            $itemArray['serialNumber'] = '';
                            $itemArray['rtCounter'] = '';
                            $itemArray['FNSKU'] = '';
                            $itemArray['dispensed_products'] = [];
                            $itemArray['dispensed_count'] = 0;
                        }
                        
                        $formattedItems[] = $itemArray;
                        
                    } catch (\Exception $e) {
                        Log::error('Error processing item ' . $item->outboundorderitemid . ': ' . $e->getMessage());
                        // Add item with basic info if processing fails
                        $itemArray['condition'] = $item->ConditionId . $item->ConditionSubtypeId;
                        $itemArray['product_id'] = null;
                        $itemArray['warehouseLocation'] = '';
                        $itemArray['serialNumber'] = '';
                        $itemArray['rtCounter'] = '';
                        $itemArray['FNSKU'] = '';
                        $itemArray['dispensed_products'] = [];
                        $itemArray['dispensed_count'] = 0;
                        $formattedItems[] = $itemArray;
                    }
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
                
            } catch (\Exception $e) {
                Log::error('Error processing order ' . $order->outboundorderid . ': ' . $e->getMessage());
                // Add order with basic info if processing fails
                $orderData['items'] = [];
                $orderData['order_status'] = 'Pending';
                $formattedOrders[] = $orderData;
            }
        }
        
        // Additional status filtering if needed
        if (!empty($statusFilter)) {
            $formattedOrders = array_filter($formattedOrders, function($order) use ($statusFilter) {
                return $order['order_status'] === $statusFilter;
            });
            $formattedOrders = array_values($formattedOrders);
        }
        
        Log::info('Formatted orders: ' . count($formattedOrders));
        
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
        return response()->json([
            'success' => false, 
            'message' => 'Error fetching orders', 
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
}

/**
 * Get dispensed products for a specific order item
 */
private function getDispensedProductsForItem($orderItemId)
{
    try {
        // Check if the dispensed table exists first
        if (!Schema::hasTable('tblorderitemdispense')) {
            Log::warning('Table tblorderitemdispense does not exist');
            return [];
        }
        
        $dispensedProducts = DB::table('tblorderitemdispense as d')
            ->select(
                'd.productid as product_id',
                'p.warehouseLocation',
                'p.serialNumber', 
                'p.rtCounter',
                'p.FNSKUviewer as FNSKU'
            )
            ->leftJoin('tblproduct as p', 'd.productid', '=', 'p.ProductID')
            ->where('d.orderitemid', $orderItemId)
            ->get();
        
        return $dispensedProducts->map(function($item) {
            return [
                'product_id' => $item->product_id,
                'warehouseLocation' => $item->warehouseLocation ?? '',
                'serialNumber' => $item->serialNumber ?? '',
                'rtCounter' => $item->rtCounter ?? '',
                'FNSKU' => $item->FNSKU ?? ''
            ];
        })->toArray();
        
    } catch (\Exception $e) {
        Log::error('Error getting dispensed products for item ' . $orderItemId . ': ' . $e->getMessage());
        return [];
    }
}



// Fixed getOrderDetail method for FbmOrderController
public function getOrderDetail(Request $request)
{
    try {
        // Validate request
        $request->validate([
            'order_id' => 'required|integer'
        ]);
        
        $orderId = $request->input('order_id');
        
        Log::info('Getting order detail for order ID: ' . $orderId);
        
        // Get the order with better error handling
        $orderQuery = DB::table('tbloutboundorders')
            ->select(
                'outboundorderid', 
                'platform', 
                'storename', 
                'platform_order_id',
                'FulfillmentChannel',
                'BuyerName as buyer_name',
                'address_line1',
                'city',
                'StateOrRegion',
                'postal_code',
                'PurchaseDate as purchase_date',
                'ship_date',
                'delivery_date',
                'ShipmentServiceLevelCategory as shipment_service',
                'OrderType as order_type',
                'ordernote',
                'IsReplacementOrder as is_replacement'
            )
            ->where('outboundorderid', $orderId);
            
        Log::info('Order query built for ID: ' . $orderId);
        
        $order = $orderQuery->first();
        
        if (!$order) {
            Log::warning('Order not found for ID: ' . $orderId);
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }
        
        Log::info('Order found: ' . $order->platform_order_id);
        
        $orderData = (array) $order;
        
        // Build address manually to avoid CONCAT issues
        $addressParts = array_filter([
            $order->address_line1 ?? '',
            $order->city ?? '',
            $order->StateOrRegion ?? '',
            $order->postal_code ?? ''
        ]);
        $orderData['address'] = implode(', ', $addressParts);
        
        // Remove individual address fields to clean up response
        unset($orderData['address_line1'], $orderData['city'], $orderData['StateOrRegion'], $orderData['postal_code']);
        
        Log::info('Getting items for order ID: ' . $orderId);
        
        // Get items for this order with better error handling
        $itemsQuery = DB::table('tbloutboundordersitem AS oi')
            ->select(
                'oi.outboundorderitemid',
                'oi.outboundorderid',
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
                'oi.ProductID as product_id'
            )
            ->where('oi.outboundorderid', $orderId);
            
        $items = $itemsQuery->get();
        
        Log::info('Found ' . $items->count() . ' items for order');
        
        // Format items with condition and dispensed product details
        $formattedItems = [];
        foreach ($items as $item) {
            $itemArray = (array) $item;
            
            try {
                // Format condition
                $itemArray['condition'] = $this->formatCondition(
                    $item->ConditionId, 
                    $item->ConditionSubtypeId, 
                    $order->storename
                );
                
                Log::info('Processing item: ' . $item->outboundorderitemid);
                
                // Get all dispensed products for this item
                $dispensedProducts = $this->getDispensedProductsForItem($item->outboundorderitemid);
                
                Log::info('Found ' . count($dispensedProducts) . ' dispensed products for item ' . $item->outboundorderitemid);
                
                // If we have dispensed products, add their details to the item
                if (!empty($dispensedProducts)) {
                    // For backward compatibility, keep the first product_id
                    $itemArray['product_id'] = $dispensedProducts[0]['product_id'];
                    
                    // Add detailed information from the first dispensed product
                    $itemArray['warehouseLocation'] = $dispensedProducts[0]['warehouseLocation'] ?? '';
                    $itemArray['serialNumber'] = $dispensedProducts[0]['serialNumber'] ?? '';
                    $itemArray['rtCounter'] = $dispensedProducts[0]['rtCounter'] ?? '';
                    $itemArray['FNSKU'] = $dispensedProducts[0]['FNSKU'] ?? '';
                    
                    // Add all dispensed products array for multiple quantity handling
                    $itemArray['dispensed_products'] = $dispensedProducts;
                    $itemArray['dispensed_count'] = count($dispensedProducts);
                } else {
                    // No dispensed products
                    $itemArray['product_id'] = null;
                    $itemArray['warehouseLocation'] = '';
                    $itemArray['serialNumber'] = '';
                    $itemArray['rtCounter'] = '';
                    $itemArray['FNSKU'] = '';
                    $itemArray['dispensed_products'] = [];
                    $itemArray['dispensed_count'] = 0;
                }
                
                $formattedItems[] = $itemArray;
                
            } catch (\Exception $e) {
                Log::error('Error processing item ' . $item->outboundorderitemid . ' in detail view: ' . $e->getMessage());
                Log::error('Item processing error trace: ' . $e->getTraceAsString());
                
                // Add item with basic info if processing fails
                $itemArray['condition'] = ($item->ConditionId ?? '') . ($item->ConditionSubtypeId ?? '');
                $itemArray['product_id'] = null;
                $itemArray['warehouseLocation'] = '';
                $itemArray['serialNumber'] = '';
                $itemArray['rtCounter'] = '';
                $itemArray['FNSKU'] = '';
                $itemArray['dispensed_products'] = [];
                $itemArray['dispensed_count'] = 0;
                $formattedItems[] = $itemArray;
            }
        }
        
        // Add items to order
        $orderData['items'] = $formattedItems;
        
        Log::info('Processed ' . count($formattedItems) . ' items');
        
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
        
        Log::info('Order detail processing completed successfully');
        
        return response()->json([
            'success' => true,
            'data' => $orderData
        ]);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation error in getOrderDetail: ' . json_encode($e->errors()));
        return response()->json([
            'success' => false, 
            'message' => 'Invalid request parameters', 
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('Error fetching order detail: ' . $e->getMessage());
        Log::error('Error trace: ' . $e->getTraceAsString());
        Log::error('Error file: ' . $e->getFile() . ' at line ' . $e->getLine());
        
        return response()->json([
            'success' => false, 
            'message' => 'Error fetching order detail', 
            'error' => $e->getMessage(),
            'debug_info' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'order_id' => $request->input('order_id', 'not provided')
            ]
        ], 500);
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
/**
 * Find matching products for auto dispense with quantity handling
 */
public function findDispenseProducts(Request $request)
{
    try {
        Log::info('findDispenseProducts request received', $request->all());
        
        // Check if dispensed table exists
        if (!Schema::hasTable('tblorderitemdispense')) {
            return response()->json([
                'success' => false,
                'message' => 'Dispensed products table not found. Please contact system administrator.'
            ], 500);
        }
        
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

        // Get order items with detailed info
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

        // Get ALL already dispensed products for this entire order to avoid conflicts
        $allDispensedProductIds = DB::table('tblorderitemdispense as d')
            ->join('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
            ->where('oi.platform_order_id', $order->platform_order_id)
            ->pluck('d.productid')
            ->toArray();

        Log::info('Already dispensed product IDs for entire order:', $allDispensedProductIds);

        // Results array for API response
        $results = [];
        
        // Track used products across all items in this request to prevent duplicates
        $usedProductIds = $allDispensedProductIds;
        
        // Process each order item
        foreach ($items as $item) {
            if (empty($item->platform_asin)) continue;
            
            $itemCondition = $this->formatCondition($item->ConditionId, $item->ConditionSubtypeId, $storeName);
            
            // Get already dispensed products for THIS specific item
            $dispensedProducts = $this->getDispensedProductsForItem($item->outboundorderitemid);
            $alreadyDispensed = count($dispensedProducts);
            
            // Calculate remaining quantity needed for THIS item
            $quantityNeeded = max(0, $item->QuantityOrdered - $alreadyDispensed);
            
            Log::info("Item {$item->outboundorderitemid}: Ordered={$item->QuantityOrdered}, Dispensed={$alreadyDispensed}, Needed={$quantityNeeded}");
            
            // If we still need products for this item
            if ($quantityNeeded > 0) {
                // Find ALL matching products for this ASIN/condition
                $allMatchingProducts = $this->findMatchingProductsForItem($item, $storeName, $normalizedStoreName);
                
                // Filter out products that are already used (dispensed to ANY item in this order)
                $availableProducts = array_filter($allMatchingProducts, function($product) use ($usedProductIds) {
                    return !in_array($product['ProductID'], $usedProductIds);
                });
                
                // Sort by stockroom date for FIFO
                usort($availableProducts, function($a, $b) {
                    $dateA = $a['stockroom_insert_date'] ?? '1970-01-01';
                    $dateB = $b['stockroom_insert_date'] ?? '1970-01-01';
                    return strcmp($dateA, $dateB);
                });
                
                // Auto-select the needed quantity of products for this item
                $selectedProducts = [];
                $productsToTake = min($quantityNeeded, count($availableProducts));
                
                for ($i = 0; $i < $productsToTake; $i++) {
                    $selectedProducts[] = $availableProducts[$i];
                    // Mark this product as used so other items can't use it
                    $usedProductIds[] = $availableProducts[$i]['ProductID'];
                }
                
                Log::info("Item {$item->outboundorderitemid}: Selected {$productsToTake} products from " . count($availableProducts) . " available");
                
                // Add to results
                $results[] = [
                    'item_id' => $item->outboundorderitemid,
                    'ordered_item' => $item,
                    'ordered_condition' => $itemCondition,
                    'quantity_ordered' => $item->QuantityOrdered,
                    'quantity_dispensed' => $alreadyDispensed,
                    'quantity_remaining' => $quantityNeeded,
                    'available_products_count' => count($availableProducts),
                    'auto_selected_products' => $selectedProducts, // Auto-selected products
                    'matching_products' => [], // Empty since we auto-select
                    'already_dispensed_products' => array_column($dispensedProducts, 'product_id'),
                    'dispensed_products_details' => $dispensedProducts
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $results,
            'debug_info' => [
                'total_items_processed' => count($items),
                'items_needing_dispense' => count($results),
                'total_products_used' => count($usedProductIds),
                'order_platform_id' => $order->platform_order_id
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error finding dispense products: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json([
            'success' => false, 
            'message' => 'Error finding dispense products', 
            'error' => $e->getMessage()
        ], 500);
    }
}


public function autoDispense(Request $request)
{
    try {
        // Check if dispensed table exists
        if (!Schema::hasTable('tblorderitemdispense')) {
            return response()->json([
                'success' => false,
                'message' => 'Dispensed products table not found. Please contact system administrator.'
            ], 500);
        }
        
        // Validate request
        $request->validate([
            'order_id' => 'required|integer',
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer'
        ]);

        // Start transaction
        DB::beginTransaction();

        // Get fresh matching products and auto-select them
        $findProductsRequest = new \Illuminate\Http\Request();
        $findProductsRequest->merge([
            'order_id' => $request->order_id,
            'item_ids' => $request->item_ids
        ]);
        
        $findResponse = $this->findDispenseProducts($findProductsRequest);
        $findData = $findResponse->getData(true);
        
        if (!$findData['success'] || empty($findData['data'])) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'No products available for auto-dispense'
            ], 400);
        }
        
        $dispenseItems = [];
        
        // Build dispense items from auto-selected products
        foreach ($findData['data'] as $itemData) {
            foreach ($itemData['auto_selected_products'] as $product) {
                $dispenseItems[] = [
                    'item_id' => $itemData['item_id'],
                    'product_id' => $product['ProductID']
                ];
            }
        }
        
        if (empty($dispenseItems)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'No products were auto-selected for dispensing'
            ], 400);
        }
        
        // Use the existing dispense logic
        $dispenseRequest = new \Illuminate\Http\Request();
        $dispenseRequest->merge([
            'order_id' => $request->order_id,
            'dispense_items' => $dispenseItems
        ]);
        
        // Call the existing dispense method
        $dispenseResponse = $this->dispense($dispenseRequest);
        $dispenseData = $dispenseResponse->getData(true);
        
        if ($dispenseData['success']) {
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Items auto-dispensed successfully',
                'dispensed_count' => count($dispenseItems),
                'items_processed' => count($findData['data'])
            ]);
        } else {
            DB::rollBack();
            return response()->json($dispenseData, $dispenseResponse->getStatusCode());
        }

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error in auto dispense: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json([
            'success' => false, 
            'message' => 'Error in auto dispense', 
            'error' => $e->getMessage()
        ], 500);
    }
}

// New helper method to find matching products for an item
private function findMatchingProductsForItem($item, $storeName, $normalizedStoreName)
{
    try {
        $originalConditionId = $item->ConditionId;
        $originalSubtypeId = $item->ConditionSubtypeId;
        
        // Check if required tables exist
        if (!Schema::hasTable('tblasin') || !Schema::hasTable('tblfnsku') || !Schema::hasTable('tblproduct')) {
            Log::warning('Required tables for product matching do not exist');
            return [];
        }
        
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
        
    } catch (\Exception $e) {
        Log::error('Error finding matching products for item: ' . $e->getMessage());
        return [];
    }
}
    /**
     * Perform auto dispense (assign products to order items)
     */
 public function dispense(Request $request)
{
    try {
        // Check if dispensed table exists
        if (!Schema::hasTable('tblorderitemdispense')) {
            return response()->json([
                'success' => false,
                'message' => 'Dispensed products table not found. Please contact system administrator.'
            ], 500);
        }
        
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
                'message' => 'Cannot dispense the same product multiple times. Please select different products for each slot.'
            ], 400);
        }
        
        // Also check if any of the requested products are already assigned to another order
        $alreadyAssignedProducts = DB::table('tblorderitemdispense')
            ->whereIn('productid', $productIds)
            ->count();
            
        if ($alreadyAssignedProducts > 0) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'One or more selected products are already assigned to other orders. Please refresh and try again.'
            ], 400);
        }

        // Process each item - insert into tblorderitemdispense
        foreach ($request->dispense_items as $dispenseItem) {
            $itemId = $dispenseItem['item_id'];
            $productId = $dispenseItem['product_id'];
            
            // Check if this order item has already been fully dispensed
            $orderItem = DB::table('tbloutboundordersitem')
                ->select('QuantityOrdered')
                ->where('outboundorderitemid', $itemId)
                ->first();
            
            if (!$orderItem) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Order item not found: ' . $itemId
                ], 404);
            }
            
            // Count existing dispense records
            $dispensedCount = DB::table('tblorderitemdispense')
                ->where('orderitemid', $itemId)
                ->count();
            
            // Check if we already have enough dispensed products
            if ($dispensedCount >= $orderItem->QuantityOrdered) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Order item ' . $itemId . ' already has the maximum number of dispensed products'
                ], 400);
            }
            
            // Insert into tblorderitemdispense
            DB::table('tblorderitemdispense')->insert([
                'orderitemid' => $itemId,
                'productid' => $productId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Decrement the FBMAvailable count for the product if column exists
            if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
                DB::table('tblproduct')
                    ->where('ProductID', $productId)
                    ->decrement('FBMAvailable', 1);
            }
        }
        
        // Add note to order
        $currentNote = DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->value('ordernote');
        
        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        $timestamp = $dateTime->format('Y-m-d H:i:s');
        
        $dispenseNote = $timestamp . " - Auto dispense completed for " . count($request->dispense_items) . " products";
        
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
        // Check if dispensed table exists
        if (!Schema::hasTable('tblorderitemdispense')) {
            return response()->json([
                'success' => false,
                'message' => 'Dispensed products table not found. Please contact system administrator.'
            ], 500);
        }
        
        // Validate request
        $request->validate([
            'order_id' => 'required|integer',
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer'
        ]);

        // Start transaction
        DB::beginTransaction();

        // Get the dispensed products for these items to increment FBMAvailable correctly
        $dispensedProducts = DB::table('tblorderitemdispense')
            ->whereIn('orderitemid', $request->item_ids)
            ->get();
        
        // Delete the dispense records for these items
        DB::table('tblorderitemdispense')
            ->whereIn('orderitemid', $request->item_ids)
            ->delete();
        
        // Increment FBMAvailable for each product if column exists
        if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
            foreach ($dispensedProducts as $dispense) {
                DB::table('tblproduct')
                    ->where('ProductID', $dispense->productid)
                    ->increment('FBMAvailable', 1);
            }
        }

        // Add note to order
        $currentNote = DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->value('ordernote');
        
        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        $timestamp = $dateTime->format('Y-m-d H:i:s');
        
        $cancelNote = $timestamp . " - Auto dispense canceled for " . count($dispensedProducts) . " products";
        
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
}