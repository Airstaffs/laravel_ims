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

        // IMPROVED: Apply status filter at SQL level using EXISTS subqueries
        if (!empty($statusFilter)) {
            switch ($statusFilter) {
                case 'Canceled':
                    // Orders where ALL items are canceled
                    $query->whereExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                                ->from('tbloutboundordersitem as oi')
                                ->whereRaw('oi.platform_order_id = tbloutboundorders.platform_order_id')
                                ->where('oi.order_status', 'Canceled');
                    })
                    ->whereNotExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                                ->from('tbloutboundordersitem as oi')
                                ->whereRaw('oi.platform_order_id = tbloutboundorders.platform_order_id')
                                ->where('oi.order_status', '!=', 'Canceled');
                    });
                    break;
                    
                case 'Shipped':
                    // Orders where ALL items are shipped
                    $query->whereExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                                ->from('tbloutboundordersitem as oi')
                                ->whereRaw('oi.platform_order_id = tbloutboundorders.platform_order_id')
                                ->where('oi.order_status', 'Shipped');
                    })
                    ->whereNotExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                                ->from('tbloutboundordersitem as oi')
                                ->whereRaw('oi.platform_order_id = tbloutboundorders.platform_order_id')
                                ->where('oi.order_status', '!=', 'Shipped');
                    });
                    break;
                    
                case 'Pending':
                    // Orders with at least one pending item
                    $query->whereExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                                ->from('tbloutboundordersitem as oi')
                                ->whereRaw('oi.platform_order_id = tbloutboundorders.platform_order_id')
                                ->where('oi.order_status', 'Pending');
                    });
                    break;
                    
                case 'Unshipped':
                    // Orders with mixed statuses (not all shipped, not all canceled, not all pending)
                    $query->whereExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                                ->from('tbloutboundordersitem as oi')
                                ->whereRaw('oi.platform_order_id = tbloutboundorders.platform_order_id');
                    })
                    ->whereNotExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                                ->from('tbloutboundordersitem as oi')
                                ->whereRaw('oi.platform_order_id = tbloutboundorders.platform_order_id')
                                ->where('oi.order_status', 'Shipped');
                    })
                    ->whereNotExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                                ->from('tbloutboundordersitem as oi')
                                ->whereRaw('oi.platform_order_id = tbloutboundorders.platform_order_id')
                                ->where('oi.order_status', 'Canceled');
                    });
                    break;
            }
        }
        
        // Get total for pagination AFTER applying all filters
        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $perPage);
        
        Log::info('Query built, total count after filtering: ' . $totalCount);
        
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
                Log::info('Looking for items with platform_order_id: ' . $order->platform_order_id);

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
                        'oi.unit_tax'
                    )
                    ->where('oi.platform_order_id', $order->platform_order_id)
                    ->get();

                Log::info('Found ' . $items->count() . ' items for platform_order_id: ' . $order->platform_order_id);
                
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
        
        // REMOVED: No longer need to filter here since we filter in SQL
        // The status filtering is now handled at the database level
        
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
Yes! You're absolutely right. Since you have the extractBaseFnsku() function to handle FNSKU prefixes (like C0X004BWMS3B → X004BWMS3B), you should use it in the getDispensedProductsForItem() method too.
Here's the updated version that uses your extractBaseFnsku() logic:
phpprivate function getDispensedProductsForItem($orderItemId)
{
    try {
        // Check if the dispensed table exists first
        if (!Schema::hasTable('tblorderitemdispense')) {
            Log::warning('Table tblorderitemdispense does not exist');
            return [];
        }
        
        // Get dispensed products with basic product info
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
        
        Log::info("Found {$dispensedProducts->count()} dispensed products for item {$orderItemId}");
        
        // Process each product to get title and ASIN using base FNSKU matching
        return $dispensedProducts->map(function($item) {
            // Extract base FNSKU (removes C0 prefix if present)
            $baseFnsku = $this->extractBaseFnsku($item->FNSKU);
            
            Log::info("Processing dispensed product {$item->product_id}: FNSKU={$item->FNSKU}, Base={$baseFnsku}");
            
            // Find matching FNSKU record using base FNSKU with LIKE pattern
            $fnskuRecord = DB::table('tblfnsku')
                ->where('FNSKU', 'like', "%{$baseFnsku}%")
                ->first();
            
            $title = 'N/A';
            $asin = 'N/A';
            
            if ($fnskuRecord) {
                Log::info("Found FNSKU record for {$baseFnsku}: ASIN={$fnskuRecord->ASIN}");
                
                // Get ASIN details
                $asinRecord = DB::table('tblasin')
                    ->where('ASIN', $fnskuRecord->ASIN)
                    ->first();
                
                if ($asinRecord) {
                    $title = $asinRecord->internal ?? 'N/A';
                    $asin = $asinRecord->ASIN ?? 'N/A';
                    Log::info("Found ASIN details: Title={$title}");
                } else {
                    Log::warning("No ASIN record found for ASIN: {$fnskuRecord->ASIN}");
                }
            } else {
                Log::warning("No FNSKU record found for base FNSKU: {$baseFnsku}");
                
                // Try exact match as fallback
                $fnskuRecordExact = DB::table('tblfnsku')
                    ->where('FNSKU', $item->FNSKU)
                    ->first();
                    
                if ($fnskuRecordExact) {
                    Log::info("Found exact FNSKU match: {$item->FNSKU}");
                    $asinRecord = DB::table('tblasin')
                        ->where('ASIN', $fnskuRecordExact->ASIN)
                        ->first();
                    
                    if ($asinRecord) {
                        $title = $asinRecord->internal ?? 'N/A';
                        $asin = $asinRecord->ASIN ?? 'N/A';
                    }
                }
            }
            
            return [
                'product_id' => $item->product_id,
                'title' => $title,
                'asin' => $asin,
                'warehouseLocation' => $item->warehouseLocation ?? '',
                'serialNumber' => $item->serialNumber ?? '',
                'rtCounter' => $item->rtCounter ?? '',
                'FNSKU' => $item->FNSKU ?? ''
            ];
        })->toArray();
        
    } catch (\Exception $e) {
        Log::error('Error getting dispensed products for item ' . $orderItemId . ': ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return [];
    }
}


public function markProductNotFound(Request $request)
{
    try {
        // Validate request
        $request->validate([
            'product_id' => 'required|integer',
            'item_id' => 'required|integer',
            'order_id' => 'required|integer'
        ]);

        $productId = $request->product_id;
        $itemId = $request->item_id;
        $orderId = $request->order_id;

        Log::info("Marking product {$productId} as not found for item {$itemId}");

        // Start transaction
        DB::beginTransaction();

        // 1. Update the product's location to 'Not Found'
        $productUpdated = DB::table('tblproduct')
            ->where('ProductID', $productId)
            ->update([
                'ProductModuleLoc' => 'Not Found',
                'notfoundDate' => now()
            ]);

        if (!$productUpdated) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Product not found in database'
            ], 404);
        }

        // 2. Remove the dispense record for this product
        $dispenseDeleted = DB::table('tblorderitemdispense')
            ->where('productid', $productId)
            ->where('orderitemid', $itemId)
            ->delete();

        if (!$dispenseDeleted) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Dispense record not found'
            ], 404);
        }

        // 3. Increment FBMAvailable for the not found product (if column exists)
        if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
            DB::table('tblproduct')
                ->where('ProductID', $productId)
                ->increment('FbmAvailable', 1);
        }

        // 4. Get the order item details to find a replacement
        $orderItem = DB::table('tbloutboundordersitem')
            ->select('platform_asin', 'ConditionId', 'ConditionSubtypeId', 'QuantityOrdered')
            ->where('outboundorderitemid', $itemId)
            ->first();

        if (!$orderItem) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Order item not found'
            ], 404);
        }

        // 5. Get the order's store name for condition matching
        $order = DB::table('tbloutboundorders')
            ->select('storename')
            ->where('outboundorderid', $orderId)
            ->first();

        if (!$order) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $storeName = $order->storename;
        $normalizedStoreName = $this->normalizeStoreName($storeName);

        // 6. Check how many products are still needed for this item
        $currentDispensedCount = DB::table('tblorderitemdispense')
            ->where('orderitemid', $itemId)
            ->count();

        $quantityNeeded = max(0, $orderItem->QuantityOrdered - $currentDispensedCount);

        $replacementFound = false;
        $replacementDetails = null;

        // 7. If we still need products, try to find a replacement
        if ($quantityNeeded > 0) {
            // Get all already dispensed product IDs for this entire order to avoid conflicts
            $allDispensedProductIds = DB::table('tblorderitemdispense as d')
                ->join('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
                ->join('tbloutboundorders as o', 'oi.platform_order_id', '=', 'o.platform_order_id')
                ->where('o.outboundorderid', $orderId)
                ->pluck('d.productid')
                ->toArray();

            // Also exclude the product we just marked as not found
            $allDispensedProductIds[] = $productId;

            // Find a replacement product using the same logic as auto-dispense
            $replacementProduct = $this->findReplacementProduct(
                $orderItem->platform_asin,
                $orderItem->ConditionId,
                $orderItem->ConditionSubtypeId,
                $storeName,
                $normalizedStoreName,
                $allDispensedProductIds
            );

            // 8. If we found a replacement, dispense it automatically
            if ($replacementProduct) {
                // Insert new dispense record
                DB::table('tblorderitemdispense')->insert([
                    'orderitemid' => $itemId,
                    'productid' => $replacementProduct['ProductID'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Decrement FBMAvailable for the replacement product
                if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
                    DB::table('tblproduct')
                        ->where('ProductID', $replacementProduct['ProductID'])
                        ->decrement('FBMAvailable', 1);
                }

                $replacementFound = true;
                $replacementDetails = [
                    'product_id' => $replacementProduct['ProductID'],
                    'title' => $replacementProduct['title'],
                    'asin' => $replacementProduct['asin'],
                    'warehouseLocation' => $replacementProduct['warehouseLocation'],
                    'serialNumber' => $replacementProduct['serialNumber'],
                    'rtCounter' => $replacementProduct['rtCounter'],
                    'FNSKU' => $replacementProduct['fnsku']
                ];
            }
        }

        // 9. Add note to order
        $currentNote = DB::table('tbloutboundorders')
            ->where('outboundorderid', $orderId)
            ->value('ordernote');

        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        $timestamp = $dateTime->format('Y-m-d H:i:s');

        $notFoundNote = $timestamp . " - Product {$productId} marked as 'Not Found'";
        if ($replacementFound) {
            $notFoundNote .= ". Replacement product {$replacementDetails['product_id']} auto-selected.";
        } else {
            $notFoundNote .= ". No replacement product available.";
        }

        $newNote = $currentNote 
            ? $currentNote . "\n\n" . $notFoundNote
            : $notFoundNote;

        DB::table('tbloutboundorders')
            ->where('outboundorderid', $orderId)
            ->update([
                'ordernote' => $newNote,
                'updated_at' => now()
            ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Product marked as not found successfully',
            'replacement_found' => $replacementFound,
            'replacement_details' => $replacementDetails
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error marking product as not found: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json([
            'success' => false, 
            'message' => 'Error marking product as not found', 
            'error' => $e->getMessage()
        ], 500);
    }
}


private function getPossibleConditionVariations($conditionId, $conditionSubtypeId)
{
    $variations = [];
    
    // Add the original condition ID alone
    if (!empty($conditionId)) {
        $variations[] = $conditionId;
    }
    
    // Add condition + subtype combinations (various formats)
    if (!empty($conditionId) && !empty($conditionSubtypeId)) {
        // No space version: "UsedVeryGood"
        $variations[] = $conditionId . $conditionSubtypeId;
        
        // With space version: "Used Very Good" 
        $variations[] = $conditionId . ' ' . $conditionSubtypeId;
        
        // Handle specific condition mappings
        $subtypeMap = [
            'Very Good' => ['VeryGood', 'Very Good'],
            'Like New' => ['LikeNew', 'Like New'],
            'Good' => ['Good'],
            'Acceptable' => ['Acceptable'],
            'New' => ['New']
        ];
        
        if (isset($subtypeMap[$conditionSubtypeId])) {
            foreach ($subtypeMap[$conditionSubtypeId] as $variation) {
                $variations[] = $conditionId . $variation;
                $variations[] = $conditionId . ' ' . $variation;
            }
        }
    }
    
    // Remove duplicates and empty values
    $variations = array_unique(array_filter($variations));
    
    return $variations;
}

/**
 * Find a replacement product for a specific ASIN and condition
 */
private function findReplacementProduct($asin, $conditionId, $conditionSubtypeId, $storeName, $normalizedStoreName, $excludeProductIds = [])
{
    try {
        // STEP 1: Get FNSKU records first
        $fnskuQuery = DB::table('tblfnsku')
            ->select(['FNSKU', 'MSKU', 'storename', 'grading', 'ASIN'])
            ->where('ASIN', $asin);

        // Apply condition filtering
        if ($normalizedStoreName === 'allrenewed') {
            $fnskuQuery->where(function($q) {
                $q->where('storename', 'All Renewed')
                    ->orWhere('storename', 'AllRenewed')
                    ->orWhere('storename', 'Allrenewed');
            });
            $fnskuQuery->where('grading', 'New');
        } else {
            $possibleConditions = $this->getPossibleConditionVariations($conditionId, $conditionSubtypeId);
            
            if (!empty($possibleConditions)) {
                $fnskuQuery->whereIn('grading', $possibleConditions);
            } else {
                $fnskuQuery->where('grading', $conditionId);
            }
        }

        $fnskuRecords = $fnskuQuery->get();
        
        if ($fnskuRecords->isEmpty()) {
            return null;
        }

        // STEP 2: Extract base FNSKUs and build mapping
        $baseFnskus = [];
        $fnskuMap = [];
        foreach ($fnskuRecords as $record) {
            $baseFnsku = $this->extractBaseFnsku($record->FNSKU);
            $baseFnskus[] = $baseFnsku;
            $fnskuMap[$baseFnsku] = $record;
        }
        $baseFnskus = array_unique($baseFnskus);

        // STEP 3: Get products matching these base FNSKUs
        $productsQuery = DB::table('tblproduct')
            ->select([
                'ProductID',
                'FNSKUviewer',
                'FBMAvailable',
                'warehouseLocation',
                'serialNumber',
                'rtCounter',
                'stockroom_insert_date'
            ]);

        // Add availability filter
        if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
            $productsQuery->where('FBMAvailable', '>', 0);
        }

        // Add location filter - exclude Not Found products
        if (Schema::hasColumn('tblproduct', 'ProductModuleLoc')) {
            $productsQuery->where('ProductModuleLoc', 'Stockroom');
        }

        // Exclude already dispensed products
        if (!empty($excludeProductIds)) {
            $productsQuery->whereNotIn('ProductID', $excludeProductIds);
        }

        // Match using base FNSKUs with LIKE for flexible matching
        $productsQuery->where(function($q) use ($baseFnskus) {
            foreach ($baseFnskus as $baseFnsku) {
                $q->orWhere('FNSKUviewer', 'like', "%{$baseFnsku}%");
            }
        });

        // Order by stockroom date for FIFO
        if (Schema::hasColumn('tblproduct', 'stockroom_insert_date')) {
            $productsQuery->orderBy('stockroom_insert_date', 'asc');
        }

        $allProducts = $productsQuery->get();

        // STEP 4: Match products with FNSKU records and apply store filtering
        foreach ($allProducts as $product) {
            $productBaseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
            
            // Find matching FNSKU record
            if (!isset($fnskuMap[$productBaseFnsku])) {
                continue;
            }
            
            $fnskuRecord = $fnskuMap[$productBaseFnsku];
            
            // Apply store name filter using normalization
            $productStoreName = $fnskuRecord->storename ?? '';
            $normalizedProductStore = $this->normalizeStoreName($productStoreName);
            
            // Store matching logic
            if ($normalizedStoreName === 'allrenewed') {
                // Already filtered in FNSKU query, accept all
                $storeMatches = true;
            } else {
                $storeMatches = ($normalizedProductStore === $normalizedStoreName);
            }
            
            if (!$storeMatches) {
                continue;
            }

            // Get ASIN title
            $asinTitle = DB::table('tblasin')
                ->where('ASIN', $fnskuRecord->ASIN)
                ->value('internal') ?? 'No title';

            // This product matches - return it
            return [
                'ProductID' => $product->ProductID,
                'asin' => $fnskuRecord->ASIN,
                'title' => $asinTitle,
                'msku' => $fnskuRecord->MSKU ?? '',
                'warehouseLocation' => $product->warehouseLocation ?? '',
                'serialNumber' => $product->serialNumber ?? '',
                'rtCounter' => $product->rtCounter ?? '',
                'fnsku' => $fnskuRecord->FNSKU
            ];
        }

        return null;

    } catch (\Exception $e) {
        Log::error('Error finding replacement product: ' . $e->getMessage());
        return null;
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
        
        Log::info('Getting items for order platform ID: ' . $order->platform_order_id);
        
        // FIXED: Get items using platform_order_id instead of outboundorderid
        $itemsQuery = DB::table('tbloutboundordersitem AS oi')
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
                'oi.unit_tax'
            )
            // CRITICAL FIX: Use platform_order_id for the join since outboundorderid doesn't exist in items table
            ->where('oi.platform_order_id', $order->platform_order_id);
            
        $items = $itemsQuery->get();
        
        Log::info('Found ' . $items->count() . ' items for order platform ID: ' . $order->platform_order_id);
        
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
                ->where('FulfillmentChannel', 'FBM')
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
                    'auto_selected_products' => $selectedProducts,
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

/**
 * AUTO DISPENSE - Completely rewritten to work correctly
 */
public function autoDispense(Request $request)
{
    try {
        Log::info('🤖 Auto dispense request received', $request->all());
        
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

        // Get the order's store name
        $order = DB::table('tbloutboundorders')
            ->select('outboundorderid', 'storename', 'platform_order_id')
            ->where('outboundorderid', $request->order_id)
            ->first();
            
        if (!$order) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }
        
        $storeName = $order->storename;
        $normalizedStoreName = $this->normalizeStoreName($storeName);

        // Get order items
        $items = DB::table('tbloutboundordersitem')
            ->select(
                'outboundorderitemid',
                'platform_order_id',
                'platform_asin',
                'platform_title',
                'ConditionId',
                'ConditionSubtypeId',
                'QuantityOrdered'
            )
            ->whereIn('outboundorderitemid', $request->item_ids)
            ->get();

        if ($items->isEmpty()) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'No items found for dispense'
            ], 404);
        }

        // Get ALL already dispensed products for this entire order
        $allDispensedProductIds = DB::table('tblorderitemdispense as d')
            ->join('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
            ->where('oi.platform_order_id', $order->platform_order_id)
            ->pluck('d.productid')
            ->toArray();

        Log::info('Already dispensed product IDs:', $allDispensedProductIds);

        $usedProductIds = $allDispensedProductIds;
        $dispenseItems = [];
        
        // Process each item
        foreach ($items as $item) {
            if (empty($item->platform_asin)) continue;
            
            // Get already dispensed for this specific item
            $dispensedProducts = $this->getDispensedProductsForItem($item->outboundorderitemid);
            $alreadyDispensed = count($dispensedProducts);
            $quantityNeeded = max(0, $item->QuantityOrdered - $alreadyDispensed);
            
            Log::info("Processing item {$item->outboundorderitemid}: Ordered={$item->QuantityOrdered}, Dispensed={$alreadyDispensed}, Needed={$quantityNeeded}");
            
            if ($quantityNeeded > 0) {
                // Find matching products
                $allMatchingProducts = $this->findMatchingProductsForItem($item, $storeName, $normalizedStoreName);
                
                // Filter out already used products
                $availableProducts = array_filter($allMatchingProducts, function($product) use ($usedProductIds) {
                    return !in_array($product['ProductID'], $usedProductIds);
                });
                
                // Sort by FIFO
                usort($availableProducts, function($a, $b) {
                    $dateA = $a['stockroom_insert_date'] ?? '1970-01-01';
                    $dateB = $b['stockroom_insert_date'] ?? '1970-01-01';
                    return strcmp($dateA, $dateB);
                });
                
                // Select products
                $productsToTake = min($quantityNeeded, count($availableProducts));
                
                for ($i = 0; $i < $productsToTake; $i++) {
                    $product = $availableProducts[$i];
                    
                    $dispenseItems[] = [
                        'item_id' => $item->outboundorderitemid,
                        'product_id' => $product['ProductID']
                    ];
                    
                    $usedProductIds[] = $product['ProductID'];
                }
                
                Log::info("Selected {$productsToTake} products for item {$item->outboundorderitemid}");
            }
        }
        
        if (empty($dispenseItems)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'No products available for auto-dispense'
            ], 400);
        }
        
        // Now actually dispense the items
        $dispensedCount = 0;
        foreach ($dispenseItems as $dispenseItem) {
            $itemId = $dispenseItem['item_id'];
            $productId = $dispenseItem['product_id'];
            
            // Verify item exists and not fully dispensed
            $orderItem = DB::table('tbloutboundordersitem')
                ->select('QuantityOrdered')
                ->where('outboundorderitemid', $itemId)
                ->first();
            
            if (!$orderItem) continue;
            
            $currentDispensedCount = DB::table('tblorderitemdispense')
                ->where('orderitemid', $itemId)
                ->count();
            
            if ($currentDispensedCount >= $orderItem->QuantityOrdered) continue;
            
            // Insert dispense record
            DB::table('tblorderitemdispense')->insert([
                'orderitemid' => $itemId,
                'productid' => $productId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Decrement FBMAvailable
            if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
                DB::table('tblproduct')
                    ->where('ProductID', $productId)
                    ->decrement('FBMAvailable', 1);
            }
            
            $dispensedCount++;
        }
        
        // Add note to order
        $currentNote = DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->value('ordernote');
        
        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        $timestamp = $dateTime->format('Y-m-d H:i:s');
        $dispenseNote = $timestamp . " - Auto dispense completed for {$dispensedCount} products";
        
        $newNote = $currentNote ? $currentNote . "\n\n" . $dispenseNote : $dispenseNote;
        
        DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->update([
                'ordernote' => $newNote,
                'updated_at' => now()
            ]);

        DB::commit();
        
        Log::info("✅ Auto dispense successful: {$dispensedCount} products dispensed");
        
        return response()->json([
            'success' => true,
            'message' => 'Items auto-dispensed successfully',
            'dispensed_count' => $dispensedCount,
            'items_processed' => count($items)
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('❌ Error in auto dispense: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json([
            'success' => false, 
            'message' => 'Error in auto dispense', 
            'error' => $e->getMessage()
        ], 500);
    }
}


/**
 * Extract base FNSKU by removing common prefixes
 * Examples: C0X004BWMS3B -> X004BWMS3B, X004BWMS3B -> X004BWMS3B
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
        
        Log::info("Finding products for item {$item->outboundorderitemid}: ASIN={$item->platform_asin}, Store='{$storeName}' (normalized: '{$normalizedStoreName}'), Condition={$originalConditionId}{$originalSubtypeId}");
        
        // STEP 1: Get FNSKU records for this ASIN with store and condition pre-filtering
        $fnskuQuery = DB::table('tblfnsku')
            ->select(['FNSKU', 'MSKU', 'storename', 'grading', 'ASIN'])
            ->where('ASIN', trim($item->platform_asin));
        
        // Apply condition filtering at FNSKU level
        if ($normalizedStoreName === 'allrenewed') {
            Log::info("Applying AllRenewed-specific filters");
            
            // Match All Renewed store name patterns
            $fnskuQuery->where(function($q) {
                $q->where('storename', 'All Renewed')
                  ->orWhere('storename', 'AllRenewed')
                  ->orWhere('storename', 'Allrenewed');
            });
            
            // Support both New (Refurbished) AND Used conditions
            if ($originalConditionId === 'New') {
                $fnskuQuery->where('grading', 'New');
            } else {
                $possibleConditions = $this->getPossibleConditionVariations($originalConditionId, $originalSubtypeId);
                Log::info("AllRenewed with non-New condition - possible variations: " . implode(', ', $possibleConditions));
                
                if (!empty($possibleConditions)) {
                    $fnskuQuery->whereIn('grading', $possibleConditions);
                } else {
                    $fnskuQuery->where('grading', $originalConditionId);
                }
            }
        } else {
            Log::info("Applying flexible condition matching for: {$storeName}");
            
            // For other stores, try multiple condition patterns
            $possibleConditions = $this->getPossibleConditionVariations($originalConditionId, $originalSubtypeId);
            Log::info("Possible condition variations for '{$originalConditionId}' + '{$originalSubtypeId}': " . implode(', ', $possibleConditions));
            
            if (!empty($possibleConditions)) {
                $fnskuQuery->whereIn('grading', $possibleConditions);
            } else {
                $fnskuQuery->where('grading', $originalConditionId);
            }
        }
        
        $fnskuRecords = $fnskuQuery->get();
        
        if ($fnskuRecords->isEmpty()) {
            Log::warning("No FNSKU records found for ASIN: {$item->platform_asin} with the specified filters");
            return [];
        }
        
        Log::info("Found " . $fnskuRecords->count() . " FNSKU records matching ASIN and conditions");
        
        // STEP 2: Extract base FNSKUs and build mapping
        $baseFnskus = [];
        $fnskuMap = []; // Map base FNSKU to full FNSKU record
        foreach ($fnskuRecords as $record) {
            $baseFnsku = $this->extractBaseFnsku($record->FNSKU);
            $baseFnskus[] = $baseFnsku;
            
            // Store the record indexed by base FNSKU
            // If multiple records have same base FNSKU, last one wins (could be enhanced)
            $fnskuMap[$baseFnsku] = $record;
            
            Log::debug("FNSKU: {$record->FNSKU} -> Base: {$baseFnsku}, Store: {$record->storename}, Grading: {$record->grading}");
        }
        $baseFnskus = array_unique($baseFnskus);
        
        Log::info("Found " . count($baseFnskus) . " unique base FNSKUs: " . implode(', ', $baseFnskus));
        
        // STEP 3: Get products matching these base FNSKUs using LIKE pattern
        $productsQuery = DB::table('tblproduct')
            ->select([
                'ProductID',
                'FNSKUviewer',
                'FBMAvailable',
                'warehouseLocation',
                'serialNumber',
                'rtCounter',
                'stockroom_insert_date'
            ]);
        
        // Add availability filter
        if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
            $productsQuery->where('FBMAvailable', '>', 0);
        }
        
        // Add location filter
        if (Schema::hasColumn('tblproduct', 'ProductModuleLoc')) {
            $productsQuery->where('ProductModuleLoc', 'Stockroom');
        }
        
        // Match using base FNSKUs with LIKE for flexible matching
        $productsQuery->where(function($q) use ($baseFnskus) {
            foreach ($baseFnskus as $baseFnsku) {
                // Match exact or with prefix (e.g., X004BWMS3B or C0X004BWMS3B)
                $q->orWhere('FNSKUviewer', 'like', "%{$baseFnsku}%");
            }
        });
        
        // Order by FIFO - oldest products first
        if (Schema::hasColumn('tblproduct', 'stockroom_insert_date')) {
            $productsQuery->orderBy('stockroom_insert_date', 'asc');
        }
        
        $allProducts = $productsQuery->get();
        
        Log::info("Found " . $allProducts->count() . " products before store name filtering");
        
        // STEP 4: Match products with FNSKU records and apply store filtering
        $matchingProducts = [];
        
        foreach ($allProducts as $product) {
            $productBaseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
            
            Log::debug("Processing Product {$product->ProductID}: FNSKUviewer={$product->FNSKUviewer} -> Base={$productBaseFnsku}");
            
            // Find matching FNSKU record
            if (!isset($fnskuMap[$productBaseFnsku])) {
                Log::debug("❌ No FNSKU record found for product base FNSKU: {$productBaseFnsku}");
                continue;
            }
            
            $fnskuRecord = $fnskuMap[$productBaseFnsku];
            
            // Apply store name filter using normalization
            $productStoreName = $fnskuRecord->storename ?? '';
            $normalizedProductStore = $this->normalizeStoreName($productStoreName);
            
            // Store matching logic
            $storeMatches = false;
            if ($normalizedStoreName === 'allrenewed') {
                // Already filtered in FNSKU query, so accept all
                $storeMatches = true;
                Log::debug("✅ AllRenewed product accepted: Store='{$productStoreName}'");
            } else {
                $storeMatches = ($normalizedProductStore === $normalizedStoreName);
                
                if ($storeMatches) {
                    Log::info("✅ Store MATCH: Order store '{$storeName}' (normalized: '{$normalizedStoreName}') matches product store '{$productStoreName}' (normalized: '{$normalizedProductStore}')");
                } else {
                    Log::debug("❌ Store MISMATCH: Order store '{$storeName}' (normalized: '{$normalizedStoreName}') does NOT match product store '{$productStoreName}' (normalized: '{$normalizedProductStore}')");
                }
            }
            
            if (!$storeMatches) {
                continue;
            }
            
            // Get ASIN title
            $asinTitle = DB::table('tblasin')
                ->where('ASIN', $fnskuRecord->ASIN)
                ->value('internal') ?? 'No title';
            
            // Format condition display (for UI)
            $productGrading = $fnskuRecord->grading ?? '';
            $productCondition = $this->formatCondition($productGrading, '', $productStoreName);
            
            // This product matches all criteria
            $matchingProducts[] = [
                'ProductID' => $product->ProductID,
                'asin' => $fnskuRecord->ASIN,
                'msku' => $fnskuRecord->MSKU,
                'title' => $asinTitle,
                'store' => $productStoreName,
                'condition' => $productCondition,
                'fbm_available' => $product->FBMAvailable ?? 0,
                'grading' => $productGrading,
                'warehouseLocation' => $product->warehouseLocation ?? '',
                'serialNumber' => $product->serialNumber ?? '',
                'rtCounter' => $product->rtCounter ?? '',
                'fnsku' => $fnskuRecord->FNSKU,
                'stockroom_insert_date' => $product->stockroom_insert_date
            ];
            
            Log::info("✅ FINAL MATCH: Product {$product->ProductID}, FNSKUviewer: {$product->FNSKUviewer} -> Base: {$productBaseFnsku}, FNSKU: {$fnskuRecord->FNSKU}, Store: {$productStoreName}, Condition: {$productGrading}, Location: {$product->warehouseLocation}");
        }
        
        Log::info("🎯 FINAL RESULT: Returning " . count($matchingProducts) . " formatted products for store '{$storeName}' (normalized: '{$normalizedStoreName}')");
        
        // Log summary
        if (count($matchingProducts) > 0) {
            Log::info("✅ SUCCESS: Found products for auto-dispense");
            foreach ($matchingProducts as $fp) {
                Log::info("  - Product {$fp['ProductID']}: {$fp['title']} (Store: {$fp['store']}, Condition: {$fp['grading']}, Location: {$fp['warehouseLocation']})");
            }
        } else {
            Log::warning("❌ NO PRODUCTS FOUND for store '{$storeName}' (normalized: '{$normalizedStoreName}') and ASIN {$item->platform_asin}");
            Log::warning("🔍 DEBUGGING INFO:");
            Log::warning("  - Total FNSKU records found: " . $fnskuRecords->count());
            Log::warning("  - Total products found: " . $allProducts->count());
            Log::warning("  - Base FNSKUs searched: " . implode(', ', $baseFnskus));
            
            // Show what we found but didn't match
            $foundStores = $fnskuRecords->pluck('storename')->unique()->values()->toArray();
            $foundConditions = $fnskuRecords->pluck('grading')->unique()->values()->toArray();
            
            Log::warning("  - FNSKU stores found: " . implode(', ', $foundStores));
            Log::warning("  - FNSKU conditions found: " . implode(', ', $foundConditions));
        }
        
        return $matchingProducts;
        
    } catch (\Exception $e) {
        Log::error('Error finding matching products for item: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return [];
    }
}


public function debugStoreNames(Request $request)
{
    try {
        // Get some sample data to debug
        $orders = DB::table('tbloutboundorders')
            ->select('storename')
            ->where('FulfillmentChannel', 'FBM')
            ->distinct()
            ->limit(10)
            ->get();
            
        $products = DB::table('tblfnsku')
            ->select('storename')
            ->distinct()
            ->limit(10)
            ->get();
            
        $orderStores = [];
        foreach ($orders as $order) {
            $orderStores[] = [
                'original' => $order->storename,
                'normalized' => $this->normalizeStoreName($order->storename)
            ];
        }
        
        $productStores = [];
        foreach ($products as $product) {
            $productStores[] = [
                'original' => $product->storename,
                'normalized' => $this->normalizeStoreName($product->storename)
            ];
        }
        
        return response()->json([
            'success' => true,
            'order_stores' => $orderStores,
            'product_stores' => $productStores
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * MANUAL DISPENSE - Enhanced with better error handling and debugging
 */
public function dispense(Request $request)
{
    try {
        // Log the raw request
        Log::info('📦 Manual dispense RAW request', [
            'all' => $request->all(),
            'json' => $request->json()->all(),
            'input' => $request->input()
        ]);
        
        // Check if dispensed table exists
        if (!Schema::hasTable('tblorderitemdispense')) {
            Log::error('❌ Table tblorderitemdispense does not exist');
            return response()->json([
                'success' => false,
                'message' => 'Dispensed products table not found. Please contact system administrator.'
            ], 500);
        }
        
        // More flexible validation - try to handle different payload formats
        try {
            $validated = $request->validate([
                'order_id' => 'required|integer',
                'dispense_items' => 'required|array|min:1',
                'dispense_items.*.item_id' => 'required|integer',
                'dispense_items.*.product_id' => 'required|integer'
            ]);
            
            Log::info('✅ Validation passed', $validated);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            
            // Return detailed validation errors
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'received_data' => $request->all()
            ], 422);
        }

        // Start transaction
        DB::beginTransaction();

        try {
            // Get order to verify it exists
            $order = DB::table('tbloutboundorders')
                ->where('outboundorderid', $request->order_id)
                ->first();
                
            if (!$order) {
                Log::error("❌ Order not found: {$request->order_id}");
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found with ID: ' . $request->order_id
                ], 404);
            }

            Log::info("✅ Order found: {$order->platform_order_id}");

            // Extract product IDs for duplicate check
            $productIds = array_column($request->dispense_items, 'product_id');
            $uniqueProductIds = array_unique($productIds);
            
            Log::info('Product IDs to dispense', [
                'all' => $productIds,
                'unique' => $uniqueProductIds,
                'has_duplicates' => count($productIds) !== count($uniqueProductIds)
            ]);
            
            // Check for duplicates in request
            if (count($productIds) !== count($uniqueProductIds)) {
                Log::error('❌ Duplicate product IDs detected');
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot dispense the same product multiple times. Please select different products for each slot.'
                ], 400);
            }
            
            // Check if any products are already dispensed to ANY order
            $alreadyDispensed = DB::table('tblorderitemdispense')
                ->whereIn('productid', $productIds)
                ->get();
                
            if ($alreadyDispensed->count() > 0) {
                Log::error('❌ Products already assigned', [
                    'products' => $alreadyDispensed->toArray()
                ]);
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'One or more selected products are already assigned to orders. Product IDs: ' . 
                                 implode(', ', $alreadyDispensed->pluck('productid')->toArray())
                ], 400);
            }

            $totalDispensed = 0;
            
            // Process each dispense item
            foreach ($request->dispense_items as $index => $dispenseItem) {
                $itemId = $dispenseItem['item_id'];
                $productId = $dispenseItem['product_id'];
                
                Log::info("Processing dispense #{$index}: item_id={$itemId}, product_id={$productId}");
                
                // Verify order item exists
                $orderItem = DB::table('tbloutboundordersitem')
                    ->select('QuantityOrdered', 'outboundorderitemid', 'platform_order_id')
                    ->where('outboundorderitemid', $itemId)
                    ->first();
                
                if (!$orderItem) {
                    Log::error("❌ Order item not found: {$itemId}");
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Order item not found with ID: {$itemId}"
                    ], 404);
                }
                
                Log::info("✅ Order item found", [
                    'item_id' => $orderItem->outboundorderitemid,
                    'quantity_ordered' => $orderItem->QuantityOrdered
                ]);
                
                // Count existing dispense records for this item
                $currentDispensedCount = DB::table('tblorderitemdispense')
                    ->where('orderitemid', $itemId)
                    ->count();
                
                Log::info("Current dispensed count for item {$itemId}: {$currentDispensedCount}/{$orderItem->QuantityOrdered}");
                
                // Check if already fully dispensed
                if ($currentDispensedCount >= $orderItem->QuantityOrdered) {
                    Log::error("❌ Item {$itemId} already fully dispensed");
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Order item {$itemId} already has the maximum number of dispensed products ({$orderItem->QuantityOrdered})"
                    ], 400);
                }
                
                // Verify product exists and is available
                $product = DB::table('tblproduct')
                    ->where('ProductID', $productId)
                    ->first();
                    
                if (!$product) {
                    Log::error("❌ Product not found: {$productId}");
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Product not found with ID: {$productId}"
                    ], 404);
                }
                
                Log::info("✅ Product found: {$productId}");
                
                // Check if product is in correct location
                if (isset($product->ProductModuleLoc) && $product->ProductModuleLoc !== 'Stockroom') {
                    Log::warning("⚠️ Product {$productId} not in Stockroom, location: {$product->ProductModuleLoc}");
                }
                
                // Insert dispense record
                $insertId = DB::table('tblorderitemdispense')->insertGetId([
                    'orderitemid' => $itemId,
                    'productid' => $productId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                Log::info("✅ Dispense record created with ID: {$insertId}");
                
                // Decrement FBMAvailable
                if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
                    $fbmBefore = $product->FBMAvailable ?? 0;
                    
                    DB::table('tblproduct')
                        ->where('ProductID', $productId)
                        ->decrement('FBMAvailable', 1);
                    
                    $fbmAfter = DB::table('tblproduct')
                        ->where('ProductID', $productId)
                        ->value('FBMAvailable');
                        
                    Log::info("FBMAvailable updated for product {$productId}: {$fbmBefore} -> {$fbmAfter}");
                }
                
                $totalDispensed++;
            }
            
            // Add note to order
            $currentNote = DB::table('tbloutboundorders')
                ->where('outboundorderid', $request->order_id)
                ->value('ordernote');
            
            $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
            $timestamp = $dateTime->format('Y-m-d H:i:s');
            $dispenseNote = $timestamp . " - Manual dispense completed for {$totalDispensed} product(s)";
            
            $newNote = $currentNote ? $currentNote . "\n\n" . $dispenseNote : $dispenseNote;
            
            DB::table('tbloutboundorders')
                ->where('outboundorderid', $request->order_id)
                ->update([
                    'ordernote' => $newNote,
                    'updated_at' => now()
                ]);
            
            Log::info("✅ Order note updated");

            DB::commit();
            
            Log::info("✅✅✅ Manual dispense completed successfully: {$totalDispensed} products dispensed");
            
            return response()->json([
                'success' => true,
                'message' => 'Items dispensed successfully',
                'dispensed_count' => $totalDispensed
            ]);
            
        } catch (\Exception $innerE) {
            DB::rollBack();
            Log::error('❌ Transaction error: ' . $innerE->getMessage(), [
                'trace' => $innerE->getTraceAsString()
            ]);
            throw $innerE;
        }
        
    } catch (\Exception $e) {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        
        Log::error('❌❌❌ Fatal error in manual dispense', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false, 
            'message' => 'Error dispensing items', 
            'error' => $e->getMessage(),
            'error_details' => [
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]
        ], 500);
    }
}    /**
     * Cancel auto dispense
     */public function cancelDispense(Request $request)
{
    try {
        Log::info('🗑️ Cancel dispense request received', $request->all());
        
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

        // Get the dispensed products for these items
        $dispensedProducts = DB::table('tblorderitemdispense')
            ->whereIn('orderitemid', $request->item_ids)
            ->get();
        
        Log::info("Found {$dispensedProducts->count()} dispensed products to cancel");
        
        // Delete the dispense records
        $deletedCount = DB::table('tblorderitemdispense')
            ->whereIn('orderitemid', $request->item_ids)
            ->delete();
        
        Log::info("Deleted {$deletedCount} dispense records");
        
        // Increment FBMAvailable for each product
        if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
            foreach ($dispensedProducts as $dispense) {
                DB::table('tblproduct')
                    ->where('ProductID', $dispense->productid)
                    ->increment('FBMAvailable', 1);
                    
                Log::info("Incremented FBMAvailable for product {$dispense->productid}");
            }
        }

        // Add note to order
        $currentNote = DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->value('ordernote');
        
        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        $timestamp = $dateTime->format('Y-m-d H:i:s');
        $cancelNote = $timestamp . " - Dispense canceled for " . count($dispensedProducts) . " products";
        
        $newNote = $currentNote ? $currentNote . "\n\n" . $cancelNote : $cancelNote;
        
        DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->update([
                'ordernote' => $newNote,
                'updated_at' => now()
            ]);

        DB::commit();
        
        Log::info("✅ Cancel dispense successful");
        
        return response()->json([
            'success' => true,
            'message' => 'Dispense canceled successfully',
            'canceled_count' => count($dispensedProducts)
        ]);

    } catch (\Exception $e) {
        DB::rollback();
        Log::error('❌ Error canceling dispense: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json([
            'success' => false, 
            'message' => 'Error canceling dispense', 
            'error' => $e->getMessage()
        ], 500);
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
    if (empty($storeName)) {
        Log::warning("Empty store name provided to normalizeStoreName");
        return '';
    }
    
    $original = $storeName;
    $normalized = strtolower(preg_replace('/[\s\-_]+/', '', $storeName));
    
    Log::debug("Store name normalization: '{$original}' -> '{$normalized}'");
    
    return $normalized;
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
    
    // ✅ UPDATED: Special handling for AllRenewed store
    if ($normalizedStoreName === 'allrenewed') {
        // Only apply Refurbished mapping for New items
        if ($conditionId === 'New') {
            $combinedCondition = $conditionId . $conditionSubtypeId;
            
            switch ($combinedCondition) {
                case 'NewNew':
                    return 'Refurbished - Excellent';
                case 'NewGood':
                    return 'Refurbished - Good';
                case 'NewAcceptable':
                    return 'Refurbished - Acceptable';
                default:
                    // Fallback for unexpected New combinations
                    return $combinedCondition;
            }
        } else {
            // ✅ NEW: For Used or other conditions, use standard formatting
            // This will display as "Used Very Good", "Used Good", etc.
            $condition = $conditionId;
            $subtype = $conditionSubtypeId;
            
            // Format with space if subtype exists
            if (!empty($subtype)) {
                return $condition . ' ' . $subtype;
            }
            return $condition;
        }
    }
    
    // Default condition mapping for other stores (unchanged)
    $conditionMap = [
        'New' => 'New',
        'Used' => 'Used',
        'Refurbished' => 'Refurbished',
    ];
    
    $subtypeMap = [
        'New' => 'New',
        'Like New' => 'LikeNew',
        'Very Good' => 'VeryGood',
        'Good' => 'Good',
        'Acceptable' => 'Acceptable',
    ];
    
    $condition = $conditionMap[$conditionId] ?? $conditionId;
    $subtype = $subtypeMap[$conditionSubtypeId] ?? $conditionSubtypeId;
    
    return $condition . $subtype;
  }

public function shippinglabelselecteditem(Request $request)
{
    $itemIds = $request->query('itemIds');

    if (!$itemIds) {
        return response()->json(['error' => 'Missing item IDs'], 400);
    }

    $itemIdArray = explode(',', $itemIds);

    // Fetch the selected order items
    $items = DB::table('tbloutboundordersitem')
        ->whereIn('outboundorderitemid', $itemIdArray)
        ->get();

    // Group items by platform_order_id
    $itemsGrouped = $items->groupBy('platform_order_id');

    // Fetch the corresponding orders
    $platformOrderIds = $itemsGrouped->keys();

    $orders = DB::table('tbloutboundorders')
        ->whereIn('platform_order_id', $platformOrderIds)
        ->get();

    // Combine items into each order
    $response = $orders->map(function ($order) use ($itemsGrouped) {
        $orderArray = (array) $order;
        $orderArray['items'] = $itemsGrouped[$order->platform_order_id]->values();
        return $orderArray;
    });

    return response()->json($response);
}

public function fbmorderauthorizedusers(Request $request)
{
    $users = DB::table('tblusers')
        ->select('id', 'username')
        ->where('fbmorder', 1)
        ->get();

    return response()->json([
        'success' => true,
        'users' => $users
    ]);
}

}