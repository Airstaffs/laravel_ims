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
use Illuminate\Support\Facades\Cache;

class FbmOrderController extends BasetablesController
{
    /**
     * Main method for getting FBM orders data
     */


private function getNextAvailableFnsku($baseFnsku, $msku, $asin, $grading, $storename)
{
    try {
        // ✅ Lock FNSKU record using MSKU
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

        // ✅ Get ALL active FNSKUs (with and without prefix) currently in use
        $activeFnskus = DB::table($this->productTable)
            ->select('FNSKUviewer')
            ->where(function($query) use ($baseFnsku) {
                $query->where('FNSKUviewer', $baseFnsku)
                      ->orWhere('FNSKUviewer', 'LIKE', '%' . $baseFnsku); // Match any prefix
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

        // ✅ Extract used prefixes from active products (supports C-W, Y-Z, excluding X)
        $usedPrefixes = [];
        
        foreach ($activeFnskus as $fnsku) {
            if ($fnsku === $baseFnsku) {
                // Base FNSKU (no prefix) is used
                $usedPrefixes[] = ['letter' => null, 'number' => 0];
            } elseif (preg_match('/^([C-W]|[Y-Z])(\d+)' . preg_quote($baseFnsku, '/') . '$/', $fnsku, $matches)) {
                // Extract prefix letter and number (e.g., "C3", "D5", "E1")
                // Excluding X since base FNSKUs start with X
                $usedPrefixes[] = [
                    'letter' => $matches[1],
                    'number' => (int)$matches[2]
                ];
            }
        }

        Log::info("Prefix analysis", [
            'base_fnsku' => $baseFnsku,
            'used_prefixes' => $usedPrefixes,
            'used_count' => count($usedPrefixes),
            'remaining_units_in_db' => $currentUnits
        ]);

        // ✅ Generate prefix sequence from C to Z (excluding X since base FNSKUs start with X)
        // C-W (22 letters) + Y-Z (2 letters) = 24 letters total
        // 24 letters × 9 numbers = 216 slots + 1 base = 217 total
        $prefixSequence = [];
        
        // No prefix (base FNSKU)
        $prefixSequence[] = ['letter' => null, 'number' => 0];
        
        // C through W (excluding X)
        for ($charCode = ord('C'); $charCode <= ord('W'); $charCode++) {
            $letter = chr($charCode);
            for ($i = 1; $i <= 9; $i++) {
                $prefixSequence[] = ['letter' => $letter, 'number' => $i];
            }
        }
        
        // Y through Z
        for ($charCode = ord('Y'); $charCode <= ord('Z'); $charCode++) {
            $letter = chr($charCode);
            for ($i = 1; $i <= 9; $i++) {
                $prefixSequence[] = ['letter' => $letter, 'number' => $i];
            }
        }

        Log::info("Prefix sequence generated", [
            'total_slots_available' => count($prefixSequence),
            'pattern' => 'base + C1-W9 + Y1-Z9 (excluding X)'
        ]);

        // ✅ Find first UNUSED prefix in sequence
        $nextPrefix = null;

        foreach ($prefixSequence as $candidate) {
            $isUsed = false;
            
            foreach ($usedPrefixes as $used) {
                if ($used['letter'] === $candidate['letter'] && 
                    $used['number'] === $candidate['number']) {
                    $isUsed = true;
                    break;
                }
            }
            
            if (!$isUsed) {
                $nextPrefix = $candidate;
                break;
            }
        }

        // ✅ Check if we found an available prefix slot
        if ($nextPrefix === null) {
            throw new \Exception(
                "All prefix slots exhausted for FNSKU: {$baseFnsku}. " .
                "All " . count($prefixSequence) . " prefixes (base + C1-W9 + Y1-Z9) are in use."
            );
        }

        // ✅ Generate FNSKU with correct prefix
        if ($nextPrefix['letter'] === null) {
            $actualFnsku = $baseFnsku; // No prefix (base FNSKU)
        } else {
            $actualFnsku = "{$nextPrefix['letter']}{$nextPrefix['number']}{$baseFnsku}";
        }

        $prefixDisplay = $nextPrefix['letter'] 
            ? "{$nextPrefix['letter']}{$nextPrefix['number']}" 
            : 'base';

        Log::info("✅ Generated FNSKU with available prefix", [
            'base_fnsku' => $baseFnsku,
            'used_count' => count($usedPrefixes),
            'next_prefix' => $prefixDisplay,
            'actual_fnsku' => $actualFnsku,
            'remaining_units' => $currentUnits,
            'total_capacity' => count($prefixSequence)
        ]);

        return [
            'actual_fnsku' => $actualFnsku,
            'actual_msku' => $msku,
            'times_used' => count($usedPrefixes),
            'remaining_units' => $currentUnits,
            'next_prefix' => $prefixDisplay
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

  
public function index(Request $request)
{
    try {
        // Get pagination parameters
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search', '');
        $storeFilter = $request->input('store', '');
        $statusFilter = $request->input('status', '');
        $orderBy = $request->input('order_by', 'desc');
        
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

                  case 'Delivered':
                    // Orders with at least one delivered item
                    $query->whereExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                                ->from('tbloutboundordersitem as oi')
                                ->whereRaw('oi.platform_order_id = tbloutboundorders.platform_order_id')
                                ->where('oi.order_status', 'Delivered');
                    });
                    break;    
                    
                case 'Unshipped':
                // ✅ FIXED: Orders where ALL items have status "Unshipped"
                $query->whereExists(function($subQuery) {
                    $subQuery->select(DB::raw(1))
                            ->from('tbloutboundordersitem as oi')
                            ->whereRaw('oi.platform_order_id = tbloutboundorders.platform_order_id')
                            ->where('oi.order_status', 'Unshipped');
                })
                ->whereNotExists(function($subQuery) {
                    $subQuery->select(DB::raw(1))
                            ->from('tbloutboundordersitem as oi')
                            ->whereRaw('oi.platform_order_id = tbloutboundorders.platform_order_id')
                            ->where('oi.order_status', '!=', 'Unshipped');
                });
                break;
            }
        }
        
        // Get total for pagination AFTER applying all filters
        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $perPage);
        
        Log::info('Query built, total count after filtering: ' . $totalCount);

         // ✅ PurchaseDate DESC

            $query->orderBy('PurchaseDate', $orderBy);

            // Get paginated orders
            $orders = $query->skip(($page - 1) * $perPage)
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
                        'oi.carrier',                      
                        'oi.carrier_description',
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
                        
                    } elseif (count(array_filter($statuses, function($s) { return $s == 'Delivered'; })) == count($statuses)) {
                        $orderData['order_status'] = 'Delivered';
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

private function getDispensedProductsForItem($orderItemId)
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
                'p.FNSKUviewer as FNSKU',
                'p.serialnumberb',
                'p.serialnumberc',
                'p.serialnumberd'
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
            
            // Build base response
            $response = [
                'product_id' => $item->product_id,
                'title' => $title,
                'asin' => $asin,
                'warehouseLocation' => $item->warehouseLocation ?? '',
                'rtCounter' => $item->rtCounter ?? '',
                'FNSKU' => $item->FNSKU ?? ''
            ];

            // Only add serial numbers if they're not null
            if ($item->serialNumber !== null && $item->serialNumber !== '') {
                $response['serialNumber'] = $item->serialNumber;
            }

            if ($item->serialnumberb !== null && $item->serialnumberb !== '') {
                $response['serialNumberb'] = $item->serialnumberb;
            }

            if ($item->serialnumberc !== null && $item->serialnumberc !== '') {
                $response['serialNumberc'] = $item->serialnumberc;
            }

            if ($item->serialnumberd !== null && $item->serialnumberd !== '') {
                $response['serialNumberd'] = $item->serialnumberd;
            }

            return $response;
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

        // ✅ Get ALL already dispensed products across ALL orders
        $allDispensedProductIds = DB::table('tblorderitemdispense')
            ->pluck('productid')
            ->toArray();

        Log::info('Already dispensed product IDs (ALL orders):', ['count' => count($allDispensedProductIds), 'ids' => $allDispensedProductIds]);

        // Results array for API response
        $results = [];
        
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
                
                // ✅ FIXED: Use count() instead of ->count() since it's an array
                Log::info("Item {$item->outboundorderitemid}: Total matching products found: " . count($allMatchingProducts));
                
                // ✅ Filter out products that are already dispensed to ANY order
                $availableProducts = array_filter($allMatchingProducts, function($product) use ($allDispensedProductIds) {
                    return !in_array($product['ProductID'], $allDispensedProductIds);
                });
                
                // Re-index array after filtering
                $availableProducts = array_values($availableProducts);
                
                Log::info("Item {$item->outboundorderitemid}: Available after filtering dispensed products: " . count($availableProducts));
                
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
                }
                
                Log::info("Item {$item->outboundorderitemid}: Auto-selected {$productsToTake} products");
                
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
                    'matching_products' => $availableProducts, // ✅ All available products for manual selection
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
                'total_products_already_dispensed_globally' => count($allDispensedProductIds),
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
        Log::info('🤖 Smart auto-dispense started', $request->all());
        
        if (!Schema::hasTable('tblorderitemdispense')) {
            return response()->json([
                'success' => false,
                'message' => 'Dispensed products table not found.'
            ], 500);
        }
        
        $request->validate([
            'order_id' => 'required|integer',
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer'
        ]);

        DB::beginTransaction();

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

        // Get all already dispensed products for entire order
        $allDispensedProductIds = DB::table('tblorderitemdispense as d')
            ->join('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
            ->where('oi.platform_order_id', $order->platform_order_id)
            ->pluck('d.productid')
            ->toArray();

        $usedProductIds = $allDispensedProductIds;
        $itemDispenseResults = [];
        
        // ✅ STEP 1: Process each item
        foreach ($items as $item) {
            if (empty($item->platform_asin)) continue;
            
            // Get ASIN record to check QuantityInside
            $asinRecord = DB::table('tblasin')
                ->select('ASIN', 'QuantityInside', 'color', 'internal')
                ->where('ASIN', $item->platform_asin)
                ->first();

            if (!$asinRecord) {
                Log::warning("ASIN not found", ['asin' => $item->platform_asin]);
                continue;
            }

            $quantityInside = $asinRecord->QuantityInside ?? 1;
            $quantityOrdered = $item->QuantityOrdered ?? 1;
            $totalSinglesNeeded = $quantityInside * $quantityOrdered;
            
            Log::info("Processing item", [
                'item_id' => $item->outboundorderitemid,
                'asin' => $item->platform_asin,
                'quantity_inside' => $quantityInside,
                'quantity_ordered' => $quantityOrdered,
                'total_singles_needed' => $totalSinglesNeeded
            ]);
            
            // Check already dispensed
            $dispensedProducts = $this->getDispensedProductsForItem($item->outboundorderitemid);
            $alreadyDispensed = count($dispensedProducts);
            $singlesStillNeeded = max(0, $totalSinglesNeeded - $alreadyDispensed);
            
            if ($singlesStillNeeded <= 0) {
                Log::info("Item already fully dispensed", [
                    'item_id' => $item->outboundorderitemid,
                    'already_dispensed' => $alreadyDispensed,
                    'needed' => $totalSinglesNeeded
                ]);
                continue;
            }
            
            // ✅ NEW: Determine if this is a PACK order
            $isPackOrder = $quantityInside > 1 && in_array($quantityInside, [2, 4]);
            
            if ($isPackOrder) {
                Log::info("🎁 Pack order detected", [
                    'pack_size' => $quantityInside,
                    'packs_needed' => $quantityOrdered
                ]);
                
                // ✅ STEP A: Try to find existing packs first
                $existingPackProducts = $this->findMatchingProductsForItem($item, $storeName, $normalizedStoreName);
                
                $availablePacks = array_filter($existingPackProducts, function($product) use ($usedProductIds) {
                    return !in_array($product['ProductID'], $usedProductIds);
                });
                
                Log::info("📦 Pack availability check", [
                    'existing_packs_found' => count($availablePacks),
                    'packs_needed' => $quantityOrdered
                ]);
                
                // ✅ DECISION POINT: Use packs OR search for singles to merge
                if (count($availablePacks) >= $quantityOrdered) {
                    // ✅ CASE 1: Enough existing packs found - use them directly
                    Log::info("✅ Found existing packs in stockroom - dispensing directly", [
                        'available' => count($availablePacks),
                        'needed' => $quantityOrdered
                    ]);
                    
                    usort($availablePacks, function($a, $b) {
                        $dateA = $a['stockroom_insert_date'] ?? '1970-01-01';
                        $dateB = $b['stockroom_insert_date'] ?? '1970-01-01';
                        return strcmp($dateA, $dateB);
                    });
                    
                    $selectedPacks = array_slice($availablePacks, 0, $quantityOrdered);
                    
                    $itemDispensedProducts = [];
                    foreach ($selectedPacks as $pack) {
                        $itemDispensedProducts[] = $pack['ProductID'];
                        $usedProductIds[] = $pack['ProductID'];
                    }
                    
                    $itemDispenseResults[$item->outboundorderitemid] = [
                        'order_item' => $item,
                        'asin_record' => $asinRecord,
                        'dispensed_product_ids' => $itemDispensedProducts,
                        'quantity_inside' => $quantityInside,
                        'quantity_ordered' => $quantityOrdered,
                        'total_singles_needed' => $totalSinglesNeeded,
                        'used_existing_packs' => true
                    ];
                    
                } else {
                    // ✅ CASE 2: Not enough packs - need to check for singles to merge
                    Log::info("⚠️ Not enough existing packs - checking for singles", [
                        'existing_packs' => count($availablePacks),
                        'needed_packs' => $quantityOrdered,
                        'singles_needed' => $totalSinglesNeeded
                    ]);
                    
                    // ✅ CRITICAL DEBUG: Log the pack ASIN we're searching from
                    Log::info("🔍 CRITICAL DEBUG - Starting single ASIN search", [
                        'pack_asin' => $asinRecord->ASIN,
                        'pack_title' => $asinRecord->internal,
                        'pack_color' => $asinRecord->color,
                        'pack_quantity_inside' => $asinRecord->QuantityInside
                    ]);
                    
                    // ✅ CRITICAL FIX: Find the related SINGLE ASIN (QuantityInside = 1)
                    $singleAsinRecord = $this->findRelatedSingleAsin(
                        $asinRecord->ASIN, 
                        $asinRecord->color, 
                        $asinRecord->internal
                    );
                    
                    if (!$singleAsinRecord) {
                        Log::error("❌ CRITICAL FAILURE: No related single ASIN found for pack", [
                            'pack_asin' => $asinRecord->ASIN,
                            'color' => $asinRecord->color,
                            'internal' => $asinRecord->internal,
                            'ACTION_REQUIRED' => 'Check if single ASIN exists in tblasin table'
                        ]);
                        
                        // ✅ DEBUG: Show what ASINs exist for this color
                        $allAsinsWithColor = DB::table('tblasin')
                            ->select('ASIN', 'internal', 'QuantityInside')
                            ->where('color', $asinRecord->color)
                            ->get();
                        
                        Log::error("🔍 DEBUG: All ASINs with this color", [
                            'color' => $asinRecord->color,
                            'count' => $allAsinsWithColor->count(),
                            'asins' => $allAsinsWithColor->map(function($a) {
                                return "ASIN: {$a->ASIN}, QtyInside: {$a->QuantityInside}, Title: {$a->internal}";
                            })->toArray()
                        ]);
                        
                        continue;
                    }
                    
                    Log::info("✅ SUCCESS: Found related single ASIN", [
                        'single_asin' => $singleAsinRecord->ASIN,
                        'single_title' => $singleAsinRecord->internal,
                        'single_color' => $singleAsinRecord->color,
                        'single_quantity_inside' => $singleAsinRecord->QuantityInside,
                        'pack_asin' => $asinRecord->ASIN
                    ]);
                    
                    // ✅ CRITICAL FIX: Create temporary item with SINGLE ASIN to search for singles
                    $tempSingleItem = clone $item;
                    $tempSingleItem->platform_asin = $singleAsinRecord->ASIN;
                    
                    Log::info("🔍 CRITICAL: Now searching for SINGLE products", [
                        'searching_with_asin' => $singleAsinRecord->ASIN,
                        'store' => $storeName,
                        'condition' => $item->ConditionId . ' ' . $item->ConditionSubtypeId
                    ]);
                    
                    // ✅ CRITICAL FIX: Find single products matching the SINGLE ASIN
                    $availableSingles = $this->findMatchingProductsForItem(
                        $tempSingleItem, 
                        $storeName, 
                        $normalizedStoreName
                    );
                    
                    Log::info("🔍 CRITICAL: Product search completed", [
                        'single_asin' => $singleAsinRecord->ASIN,
                        'products_found_before_filter' => 'see previous log',
                        'products_available_after_filter' => count($availableSingles)
                    ]);
                    
                    // Filter out already used products
                    $availableSingles = array_filter($availableSingles, function($product) use ($usedProductIds) {
                        return !in_array($product['ProductID'], $usedProductIds);
                    });
                    
                    Log::info("📦 FINAL: Single products availability after filtering", [
                        'single_asin' => $singleAsinRecord->ASIN,
                        'available_singles' => count($availableSingles),
                        'needed' => $totalSinglesNeeded,
                        'can_proceed' => count($availableSingles) >= $totalSinglesNeeded ? 'YES' : 'NO'
                    ]);
                    
                    if (count($availableSingles) >= $totalSinglesNeeded) {
                        Log::info("✅✅✅ SUCCESS: Found enough singles for auto-merge", [
                            'available_singles' => count($availableSingles),
                            'needed' => $totalSinglesNeeded
                        ]);
                        
                        // Sort by FIFO
                        usort($availableSingles, function($a, $b) {
                            $dateA = $a['stockroom_insert_date'] ?? '1970-01-01';
                            $dateB = $b['stockroom_insert_date'] ?? '1970-01-01';
                            return strcmp($dateA, $dateB);
                        });
                        
                        // Take needed singles
                        $selectedSingles = array_slice($availableSingles, 0, $totalSinglesNeeded);
                        
                        Log::info("📦 Selected singles for dispensing", [
                            'product_ids' => array_column($selectedSingles, 'ProductID'),
                            'locations' => array_column($selectedSingles, 'warehouseLocation')
                        ]);
                        
                        $itemDispensedProducts = [];
                        foreach ($selectedSingles as $single) {
                            $itemDispensedProducts[] = $single['ProductID'];
                            $usedProductIds[] = $single['ProductID'];
                        }
                        
                        $itemDispenseResults[$item->outboundorderitemid] = [
                            'order_item' => $item,
                            'asin_record' => $asinRecord,
                            'dispensed_product_ids' => $itemDispensedProducts,
                            'quantity_inside' => $quantityInside,
                            'quantity_ordered' => $quantityOrdered,
                            'total_singles_needed' => $totalSinglesNeeded,
                            'needs_auto_merge' => true
                        ];
                        
                    } else {
                        Log::error("❌❌❌ FAILURE: Not enough singles for auto-merge", [
                            'available_singles' => count($availableSingles),
                            'needed' => $totalSinglesNeeded,
                            'shortage' => $totalSinglesNeeded - count($availableSingles),
                            'ACTION_REQUIRED' => 'Add more products to stockroom or check product conditions'
                        ]);
                        
                        // ✅ DEBUG: Show what products were found but filtered out
                        if (count($availableSingles) > 0) {
                            Log::info("🔍 Available singles details:", [
                                'products' => array_map(function($p) {
                                    return [
                                        'id' => $p['ProductID'],
                                        'location' => $p['warehouseLocation'],
                                        'condition' => $p['condition'],
                                        'fnsku' => $p['fnsku']
                                    ];
                                }, $availableSingles)
                            ]);
                        }
                    }
                }
                
            } else {
                // ✅ CASE 3: Single item order (QuantityInside = 1)
                Log::info("📦 Single item order", [
                    'singles_needed' => $singlesStillNeeded
                ]);
                
                $allMatchingProducts = $this->findMatchingProductsForItem($item, $storeName, $normalizedStoreName);
                
                $availableProducts = array_filter($allMatchingProducts, function($product) use ($usedProductIds) {
                    return !in_array($product['ProductID'], $usedProductIds);
                });
                
                usort($availableProducts, function($a, $b) {
                    $dateA = $a['stockroom_insert_date'] ?? '1970-01-01';
                    $dateB = $b['stockroom_insert_date'] ?? '1970-01-01';
                    return strcmp($dateA, $dateB);
                });
                
                $productsToTake = min($singlesStillNeeded, count($availableProducts));
                
                if ($productsToTake === 0) {
                    Log::warning("No products available for single item", [
                        'item_id' => $item->outboundorderitemid,
                        'needed' => $singlesStillNeeded
                    ]);
                    continue;
                }
                
                $itemDispensedProducts = [];
                for ($i = 0; $i < $productsToTake; $i++) {
                    $product = $availableProducts[$i];
                    $itemDispensedProducts[] = $product['ProductID'];
                    $usedProductIds[] = $product['ProductID'];
                }
                
                $itemDispenseResults[$item->outboundorderitemid] = [
                    'order_item' => $item,
                    'asin_record' => $asinRecord,
                    'dispensed_product_ids' => $itemDispensedProducts,
                    'quantity_inside' => $quantityInside,
                    'quantity_ordered' => $quantityOrdered,
                    'total_singles_needed' => $singlesStillNeeded
                ];
            }
        }
        
        if (empty($itemDispenseResults)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'No products available for auto-dispense. Please check if matching products exist in stockroom.'
            ], 400);
        }
        
        // ✅ STEP 2: Insert dispense records for singles
        $dispensedCount = 0;
        
        foreach ($itemDispenseResults as $itemId => $result) {
            foreach ($result['dispensed_product_ids'] as $productId) {
                DB::table('tblorderitemdispense')->insert([
                    'orderitemid' => $itemId,
                    'productid' => $productId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
                    DB::table('tblproduct')
                        ->where('ProductID', $productId)
                        ->decrement('FBMAvailable', 1);
                }
                
                $dispensedCount++;
            }
        }
        
        Log::info("✅ Dispensed {$dispensedCount} items (singles)");
        
        // ✅ STEP 3: Auto-merge for pack orders
        $totalPacksCreated = 0;
        $mergeDetails = [];
        
        foreach ($itemDispenseResults as $itemId => $result) {
            if (isset($result['needs_auto_merge']) && $result['needs_auto_merge']) {
                Log::info("🎁 Auto-merging singles into packs for item", [
                    'item_id' => $itemId
                ]);
                
                $mergeResult = $this->smartAutoMergeForPackOrder(
                    $request->order_id,
                    $itemId,
                    $result['dispensed_product_ids'],
                    $result['order_item'],
                    $result['asin_record']
                );
                
                if ($mergeResult['success'] && $mergeResult['merge_needed']) {
                    $totalPacksCreated += $mergeResult['total_packs'];
                    
                    $packLocations = array_column($mergeResult['packs_created'], 'location');
                    
                    $mergeDetails[] = [
                        'item_id' => $itemId,
                        'asin' => $result['asin_record']->ASIN,
                        'pack_size' => $mergeResult['pack_size'],
                        'packs_created' => $mergeResult['total_packs'],
                        'singles_used' => $mergeResult['singles_used'],
                        'pack_locations' => $packLocations
                    ];
                    
                    Log::info("✅ Item auto-merged", [
                        'item_id' => $itemId,
                        'packs_created' => $mergeResult['total_packs'],
                        'pack_size' => $mergeResult['pack_size']
                    ]);
                }
            }
        }
        
        // ✅ STEP 4: Add note to order
        $currentNote = DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->value('ordernote');
        
        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        $timestamp = $dateTime->format('Y-m-d H:i:s');
        
        $dispenseNote = $timestamp . " - Smart auto-dispense: {$dispensedCount} items dispensed";
        
        if ($totalPacksCreated > 0) {
            $dispenseNote .= ", {$totalPacksCreated} pack(s) auto-merged";
            
            foreach ($mergeDetails as $detail) {
                $dispenseNote .= "\n  • ASIN {$detail['asin']}: {$detail['packs_created']} × {$detail['pack_size']}-pack ({$detail['singles_used']} singles)";
            }
        }
        
        $newNote = $currentNote ? $currentNote . "\n\n" . $dispenseNote : $dispenseNote;
        
        DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->update([
                'ordernote' => $newNote,
                'updated_at' => now()
            ]);

        DB::commit();
        
        Log::info("🎉 Smart auto-dispense completed", [
            'items_dispensed' => $dispensedCount,
            'packs_created' => $totalPacksCreated,
            'items_processed' => count($items)
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Smart auto-dispense completed successfully',
            'dispensed_count' => $dispensedCount,
            'packs_created' => $totalPacksCreated,
            'items_processed' => count($items),
            'merge_details' => $mergeDetails
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('❌ Error in smart auto-dispense: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json([
            'success' => false, 
            'message' => 'Error in auto-dispense', 
            'error' => $e->getMessage()
        ], 500);
    }
}
private function findRelatedSingleAsin($packAsin, $color, $internalTitle)
{
    // Strategy 1: Match by color + title + QuantityInside = 1
    $singleAsin = DB::table('tblasin')
        ->where('color', $color)
        ->where('internal', 'like', '%' . $internalTitle . '%')
        ->where('QuantityInside', 1)
        ->first();
    
    if ($singleAsin) {
        Log::info("✅ Found single ASIN via color+title", [
            'single_asin' => $singleAsin->ASIN,
            'pack_asin' => $packAsin
        ]);
        return $singleAsin;
    }
    
    // Strategy 2: Match by color only
    $singleAsin = DB::table('tblasin')
        ->where('color', $color)
        ->where('QuantityInside', 1)
        ->first();
    
    if ($singleAsin) {
        Log::info("✅ Found single ASIN via color only", [
            'single_asin' => $singleAsin->ASIN,
            'pack_asin' => $packAsin
        ]);
    } else {
        Log::warning("❌ No single ASIN found", [
            'pack_asin' => $packAsin,
            'color' => $color
        ]);
    }
    
    return $singleAsin;
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

            // Check if it's a prefixed FNSKU (starts with letter C-W or Y-Z, excluding X)
            // Pattern: Letter(C-W,Y-Z) + Number(1-9) + BaseFNSKU (which starts with X)
            if (preg_match('/^([C-W]|[Y-Z])(\d+)(X.+)$/', $fnsku, $matches)) {
                return $matches[3]; // Return the base FNSKU (starting with X)
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
// Replace the dispense() method in FbmOrderController.php with this fixed version:

public function dispense(Request $request)
{
    try {
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
            
            // ✅ FIXED: Check if products are already dispensed to OTHER orders (not this one)
            $alreadyDispensed = DB::table('tblorderitemdispense as d')
                ->join('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
                ->join('tbloutboundorders as o', 'oi.platform_order_id', '=', 'o.platform_order_id')
                ->whereIn('d.productid', $productIds)
                ->where('o.outboundorderid', '!=', $request->order_id) // ✅ EXCLUDE current order
                ->select('d.productid', 'o.outboundorderid', 'o.platform_order_id')
                ->get();
                
            if ($alreadyDispensed->count() > 0) {
                Log::error('❌ Products already assigned to OTHER orders', [
                    'products' => $alreadyDispensed->toArray()
                ]);
                
                $assignedOrderIds = $alreadyDispensed->pluck('platform_order_id')->unique()->implode(', ');
                
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'One or more selected products are already assigned to other orders: ' . $assignedOrderIds . '. Product IDs: ' . 
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
                
                // ✅ Check if THIS specific product is already dispensed to THIS specific item
                $alreadyDispensedToThisItem = DB::table('tblorderitemdispense')
                    ->where('orderitemid', $itemId)
                    ->where('productid', $productId)
                    ->exists();
                    
                if ($alreadyDispensedToThisItem) {
                    Log::error("❌ Product {$productId} already dispensed to item {$itemId}");
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Product {$productId} is already dispensed to this order item"
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
}  


/**
     * Cancel auto dispense
     */
public function cancelDispense(Request $request)
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

        // ✅ Get tracking status for all items
        $itemsWithTracking = DB::table('tbloutboundordersitem')
            ->select('outboundorderitemid', 'trackingnumber')
            ->whereIn('outboundorderitemid', $request->item_ids)
            ->get()
            ->keyBy('outboundorderitemid');

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
        
        $returnedToStockroom = 0;
        $notUpdated = 0;
        
        // Increment FBMAvailable and conditionally update ProductModuleLoc for each product
        if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
            foreach ($dispensedProducts as $dispense) {
                // Increment FBMAvailable
                DB::table('tblproduct')
                    ->where('ProductID', $dispense->productid)
                    ->increment('FBMAvailable', 1);
                    
                Log::info("✅ Incremented FBMAvailable for product {$dispense->productid}");
                
                // ✅ Check if this item has tracking
                $itemTracking = $itemsWithTracking->get($dispense->orderitemid);
                $hasTracking = $itemTracking && !empty($itemTracking->trackingnumber);
                
                // ✅ If tracking is NOT NULL, update ProductModuleLoc to Stockroom
                if ($hasTracking) {
                    DB::table('tblproduct')
                        ->where('ProductID', $dispense->productid)
                        ->update([
                            'ProductModuleLoc' => 'Stockroom'
                        ]);
                    
                    Log::info("✅ Updated ProductModuleLoc to 'Stockroom' for product {$dispense->productid} (tracking exists)");
                    $returnedToStockroom++;
                } else {
                    // If tracking is NULL, do NOT update ProductModuleLoc
                    Log::info("ℹ️ ProductModuleLoc NOT updated for product {$dispense->productid} (no tracking)");
                    $notUpdated++;
                }
            }
        }

        // Add note to order
        $currentNote = DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->value('ordernote');
        
        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        $timestamp = $dateTime->format('Y-m-d H:i:s');
        $cancelNote = $timestamp . " - Dispense canceled for " . count($dispensedProducts) . " products";
        
        if ($returnedToStockroom > 0) {
            $cancelNote .= " [{$returnedToStockroom} returned to Stockroom]";
        }
        
        $newNote = $currentNote ? $currentNote . "\n\n" . $cancelNote : $cancelNote;
        
        DB::table('tbloutboundorders')
            ->where('outboundorderid', $request->order_id)
            ->update([
                'ordernote' => $newNote,
                'updated_at' => now()
            ]);

        DB::commit();
        
        Log::info("✅ Cancel dispense successful", [
            'total_canceled' => count($dispensedProducts),
            'returned_to_stockroom' => $returnedToStockroom,
            'not_updated' => $notUpdated
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Dispense canceled successfully',
            'canceled_count' => count($dispensedProducts),
            'returned_to_stockroom' => $returnedToStockroom
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

public function cancelSingleDispense(Request $request)
{
    try {
        Log::info('🗑️ Cancel single dispense request received', $request->all());
        
        // Validate request
        $request->validate([
            'product_id' => 'required|integer',
            'item_id' => 'required|integer',
            'order_id' => 'required|integer'
        ]);

        $productId = $request->product_id;
        $itemId = $request->item_id;
        $orderId = $request->order_id;

        // Check if dispensed table exists
        if (!Schema::hasTable('tblorderitemdispense')) {
            return response()->json([
                'success' => false,
                'message' => 'Dispensed products table not found.'
            ], 500);
        }

        // Start transaction
        DB::beginTransaction();

        // Verify the dispense record exists
        $dispenseRecord = DB::table('tblorderitemdispense')
            ->where('productid', $productId)
            ->where('orderitemid', $itemId)
            ->first();

        if (!$dispenseRecord) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Dispense record not found'
            ], 404);
        }

        // ✅ Get order item to check tracking status
        $orderItem = DB::table('tbloutboundordersitem')
            ->select('trackingnumber')
            ->where('outboundorderitemid', $itemId)
            ->first();

        if (!$orderItem) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Order item not found'
            ], 404);
        }

        $hasTracking = !empty($orderItem->trackingnumber);

        Log::info("Tracking status for item {$itemId}", [
            'has_tracking' => $hasTracking,
            'tracking_number' => $orderItem->trackingnumber ?? 'NULL'
        ]);

        // Delete the specific dispense record
        $deleted = DB::table('tblorderitemdispense')
            ->where('productid', $productId)
            ->where('orderitemid', $itemId)
            ->delete();

        if (!$deleted) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete dispense record'
            ], 500);
        }

        Log::info("✅ Deleted dispense record for product {$productId}");

        // Increment FBMAvailable for the product
        if (Schema::hasColumn('tblproduct', 'FBMAvailable')) {
            DB::table('tblproduct')
                ->where('ProductID', $productId)
                ->increment('FBMAvailable', 1);
                
            Log::info("✅ Incremented FBMAvailable for product {$productId}");
        }

        // ✅ If tracking is NOT NULL, update ProductModuleLoc to Stockroom
        if ($hasTracking) {
            DB::table('tblproduct')
                ->where('ProductID', $productId)
                ->update([
                    'ProductModuleLoc' => 'Stockroom'
                ]);
            
            Log::info("✅ Updated ProductModuleLoc to 'Stockroom' for product {$productId} (tracking exists)");
        } else {
            // If tracking is NULL, do NOT update ProductModuleLoc
            Log::info("ℹ️ ProductModuleLoc NOT updated for product {$productId} (no tracking)");
        }

        // Get product details for the note
        $product = DB::table('tblproduct')
            ->where('ProductID', $productId)
            ->first();

        // Add note to order
        $currentNote = DB::table('tbloutboundorders')
            ->where('outboundorderid', $orderId)
            ->value('ordernote');
        
        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        $timestamp = $dateTime->format('Y-m-d H:i:s');
        
        $productLocation = $product->warehouseLocation ?? 'Unknown';
        $productSerial = $product->serialNumber ?? 'N/A';
        
        $cancelNote = $timestamp . " - Single dispense canceled: Product {$productId} (Location: {$productLocation}, Serial: {$productSerial}) removed from item {$itemId}";
        
        if ($hasTracking) {
            $cancelNote .= " [Returned to Stockroom]";
        }
        
        $newNote = $currentNote ? $currentNote . "\n\n" . $cancelNote : $cancelNote;
        
        DB::table('tbloutboundorders')
            ->where('outboundorderid', $orderId)
            ->update([
                'ordernote' => $newNote,
                'updated_at' => now()
            ]);

        DB::commit();
        
        Log::info("✅ Single dispense cancel completed successfully");
        
        return response()->json([
            'success' => true,
            'message' => 'Single product dispense canceled successfully',
            'product_id' => $productId,
            'item_id' => $itemId,
            'returned_to_stockroom' => $hasTracking
        ]);

    } catch (\Exception $e) {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        
        Log::error('❌ Error canceling single dispense: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        
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

    $itemIdArray = array_values(array_filter(array_map('intval', explode(',', $itemIds))));
    if (empty($itemIdArray)) {
        return response()->json(['error' => 'No valid item IDs'], 400);
    }

    // ✅ Fetch the selected order items + tblasin white_* defaults
$items = DB::table('tbloutboundordersitem as i')
    ->leftJoin('tblasin as a', function ($join) {
        $join->on(
            DB::raw('a.ASIN COLLATE utf8mb4_unicode_ci'),
            '=',
            DB::raw('i.platform_asin COLLATE utf8mb4_unicode_ci')
        );
    })
    ->whereIn('i.outboundorderitemid', $itemIdArray)
    ->get([
        'i.*',
        'a.white_length','a.white_width','a.white_height','a.white_value','a.white_unit',
    ]);

    $itemsGrouped = $items->groupBy('platform_order_id');
    $platformOrderIds = $itemsGrouped->keys()->values();

    $orders = DB::table('tbloutboundorders')
        ->whereIn('platform_order_id', $platformOrderIds)
        ->get();

    $response = $orders->map(function ($order) use ($itemsGrouped) {
        $orderArray = (array) $order;
        $orderArray['items'] = ($itemsGrouped[$order->platform_order_id] ?? collect())->values();
        return $orderArray;
    })->values();

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


 private function smartAutoMergeForPackOrder($orderId, $itemId, $dispensedProductIds, $orderItem, $asinRecord)
    {
        try {
            $quantityInside = $asinRecord->QuantityInside ?? 1;
            $quantityOrdered = $orderItem->QuantityOrdered ?? 1;
            $totalSinglesNeeded = $quantityInside * $quantityOrdered;
            $dispensedCount = count($dispensedProductIds);

            Log::info("🎯 Smart auto-merge analysis", [
                'order_id' => $orderId,
                'item_id' => $itemId,
                'asin' => $orderItem->platform_asin,
                'color' => $asinRecord->color,
                'quantity_inside' => $quantityInside,
                'quantity_ordered' => $quantityOrdered,
                'total_singles_needed' => $totalSinglesNeeded,
                'dispensed_count' => $dispensedCount
            ]);

            // ✅ RULE 1: If QuantityInside = 1, it's a SINGLE item order → NO merging
            if ($quantityInside === 1) {
                Log::info("✅ Single item order - no merging needed", [
                    'asin' => $orderItem->platform_asin,
                    'quantity_ordered' => $quantityOrdered
                ]);
                return [
                    'success' => true,
                    'merge_needed' => false,
                    'packs_created' => [],
                    'reason' => 'single_item_order'
                ];
            }

            // ✅ RULE 2: Only merge for 2-pack or 4-pack orders
            if (!in_array($quantityInside, [2, 4])) {
                Log::info("⚠️ QuantityInside not 2 or 4 - no merging", [
                    'quantity_inside' => $quantityInside
                ]);
                return [
                    'success' => true,
                    'merge_needed' => false,
                    'packs_created' => [],
                    'reason' => 'unsupported_pack_size'
                ];
            }

            // ✅ RULE 3: Check if we have enough singles dispensed
            if ($dispensedCount < $totalSinglesNeeded) {
                Log::warning("❌ Not enough singles dispensed for pack creation", [
                    'needed' => $totalSinglesNeeded,
                    'dispensed' => $dispensedCount
                ]);
                return [
                    'success' => false,
                    'merge_needed' => true,
                    'packs_created' => [],
                    'reason' => 'insufficient_singles',
                    'needed' => $totalSinglesNeeded,
                    'have' => $dispensedCount
                ];
            }

            // ✅ RULE 4: Get products in FIFO order
            $products = DB::table('tblproduct')
                ->whereIn('ProductID', $dispensedProductIds)
                ->orderBy('stockroom_insert_date', 'asc')
                ->get();

            if ($products->count() !== $dispensedCount) {
                Log::warning("Not all dispensed products found in database");
                return [
                    'success' => false,
                    'merge_needed' => true,
                    'packs_created' => [],
                    'reason' => 'products_not_found'
                ];
            }

            // ✅ RULE 5: Validate all products match (ASIN, Color, Store, Condition)
            $validationResult = $this->validateProductsForMerge($products, $asinRecord);
            if (!$validationResult['valid']) {
                Log::warning("❌ Products validation failed for auto-merge", [
                    'errors' => $validationResult['errors']
                ]);
                return [
                    'success' => false,
                    'merge_needed' => true,
                    'packs_created' => [],
                    'reason' => 'validation_failed',
                    'errors' => $validationResult['errors']
                ];
            }

            Log::info("✅ Creating packs", [
                'pack_size' => $quantityInside,
                'number_of_packs' => $quantityOrdered,
                'total_singles_used' => $totalSinglesNeeded
            ]);

            // ✅ RULE 6: Create packs based on QuantityOrdered
            $packsCreated = [];
            $productIndex = 0;

            for ($packNumber = 1; $packNumber <= $quantityOrdered; $packNumber++) {
                // Take next N singles for this pack
                $packProducts = $products->slice($productIndex, $quantityInside);
                $productIndex += $quantityInside;

                if ($packProducts->count() !== $quantityInside) {
                    Log::error("Not enough products for pack #{$packNumber}", [
                        'needed' => $quantityInside,
                        'available' => $packProducts->count()
                    ]);
                    break;
                }

                // Create this pack
                $mergeResult = $this->performAutoMergePack(
                    $packProducts->pluck('ProductID')->toArray(),
                    $asinRecord,
                    $quantityInside,
                    $packNumber
                );

                if ($mergeResult['success']) {
                    $packsCreated[] = [
                        'pack_number' => $packNumber,
                        'pack_size' => $quantityInside,
                        'product_id' => $mergeResult['product_id'],
                        'rt_counter' => $mergeResult['newrt'],
                        'fnsku' => $mergeResult['fnsku'],
                        'serials' => $mergeResult['serials'],
                        'location' => $mergeResult['location'] // ✅ Include location
                    ];

                    Log::info("✅ Pack #{$packNumber} created at location", [
                        'pack_size' => $quantityInside,
                        'product_id' => $mergeResult['product_id'],
                        'rt' => $mergeResult['newrt'],
                        'location' => $mergeResult['location'] // ✅ Log location
                    ]);
                } else {
                    Log::error("❌ Failed to create pack #{$packNumber}", [
                        'error' => $mergeResult['message']
                    ]);
                    // Continue creating other packs
                }
            }

            if (empty($packsCreated)) {
                Log::error("❌ No packs were created");
                return [
                    'success' => false,
                    'merge_needed' => true,
                    'packs_created' => [],
                    'reason' => 'pack_creation_failed'
                ];
            }

            // ✅ RULE 7: Update dispense records
            // Delete old single dispense records
            DB::table('tblorderitemdispense')
                ->where('orderitemid', $itemId)
                ->whereIn('productid', $dispensedProductIds)
                ->delete();

            // Create new dispense records for merged packs
            foreach ($packsCreated as $pack) {
                DB::table('tblorderitemdispense')->insert([
                    'orderitemid' => $itemId,
                    'productid' => $pack['product_id'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                Log::info("✅ Dispense record updated for pack", [
                    'item_id' => $itemId,
                    'pack_product_id' => $pack['product_id']
                ]);
            }

            Log::info("🎉 Smart auto-merge completed successfully", [
                'total_packs_created' => count($packsCreated),
                'pack_size' => $quantityInside,
                'quantity_ordered' => $quantityOrdered,
                'singles_used' => $totalSinglesNeeded
            ]);

            return [
                'success' => true,
                'merge_needed' => true,
                'packs_created' => $packsCreated,
                'total_packs' => count($packsCreated),
                'pack_size' => $quantityInside,
                'singles_used' => $totalSinglesNeeded
            ];

        } catch (\Exception $e) {
            Log::error("❌ Error in smart auto-merge: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'merge_needed' => true,
                'packs_created' => [],
                'reason' => 'exception',
                'error' => $e->getMessage()
            ];
        }
    }

    private function validateProductsForMerge($products, $asinRecord)
    {
        $errors = [];
        $firstProduct = $products->first();

        // Extract base FNSKU from first product
        $baseFnsku = $this->extractBaseFnsku($firstProduct->FNSKUviewer);
        
        // Get FNSKU record to check store and condition
        $fnskuRecord = DB::table('tblfnsku')
            ->where('FNSKU', $baseFnsku)
            ->first();

        if (!$fnskuRecord) {
            $errors[] = "FNSKU record not found for product";
            return ['valid' => false, 'errors' => $errors];
        }

        $expectedStore = $fnskuRecord->storename;
        $expectedCondition = $fnskuRecord->grading;
        $expectedColor = $asinRecord->color;

        // Validate each product
        foreach ($products as $idx => $product) {
            $productBaseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
            
            $productFnskuRecord = DB::table('tblfnsku')
                ->where('FNSKU', $productBaseFnsku)
                ->first();

            if (!$productFnskuRecord) {
                $errors[] = "Product #{$idx}: FNSKU record not found";
                continue;
            }

            // Check store match
            if ($productFnskuRecord->storename !== $expectedStore) {
                $errors[] = "Product #{$idx}: Different store (expected: {$expectedStore}, got: {$productFnskuRecord->storename})";
            }

            // Check condition match
            if ($productFnskuRecord->grading !== $expectedCondition) {
                $errors[] = "Product #{$idx}: Different condition (expected: {$expectedCondition}, got: {$productFnskuRecord->grading})";
            }

            // Get ASIN record for this product's FNSKU
            $productAsinRecord = DB::table('tblasin')
                ->where('ASIN', $productFnskuRecord->ASIN)
                ->first();

            if ($productAsinRecord) {
                // Check color match
                if ($productAsinRecord->color !== $expectedColor) {
                    $errors[] = "Product #{$idx}: Different color (expected: {$expectedColor}, got: {$productAsinRecord->color})";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
   

    private function performAutoMergePack($productIds, $asinRecord, $packSize, $packNumber)
{
    try {
        // Get product details WITH locations
        $products = DB::table('tblproduct')
            ->whereIn('ProductID', $productIds)
            ->orderBy('stockroom_insert_date', 'asc') // Maintain FIFO order
            ->get();

        if ($products->count() !== $packSize) {
            return [
                'success' => false,
                'message' => 'Product count mismatch'
            ];
        }

        $firstProduct = $products->first();
        $baseFnsku = $this->extractBaseFnsku($firstProduct->FNSKUviewer);
        
        // Get FNSKU record for store and condition
        $fnskuRecord = DB::table('tblfnsku')
            ->where('FNSKU', $baseFnsku)
            ->first();

        if (!$fnskuRecord) {
            return [
                'success' => false,
                'message' => 'FNSKU record not found'
            ];
        }

        // ✅ NEW: Collect serial numbers, locations, and calculate total price
        $serials = [];
        $locations = [];
        $totalPrice = 0;

        foreach ($products as $product) {
            $serials[] = $product->serialnumber;
            
            // Collect location for concatenation
            if (!empty($product->warehouselocation)) {
                $locations[] = $product->warehouselocation;
            }
            
            $totalPrice += $product->price ?? 0;
        }

        // ✅ NEW: Concatenate locations with " + " separator
        $mergedLocation = !empty($locations) ? implode(' + ', $locations) : '';

        Log::info("📍 Location concatenation for pack", [
            'pack_number' => $packNumber,
            'individual_locations' => $locations,
            'merged_location' => $mergedLocation
        ]);

        // Find pack ASIN (must match: title, QuantityInside, color)
        $packAsin = DB::table('tblasin')
            ->where('internal', 'like', '%' . $asinRecord->internal . '%')
            ->where('QuantityInside', $packSize)
            ->where('color', $asinRecord->color)
            ->first();

        if (!$packAsin) {
            // Try with related ASINs
            $relatedAsins = $this->findRelatedAsins($asinRecord->ASIN);
            
            $packAsin = DB::table('tblasin')
                ->whereIn('ASIN', $relatedAsins)
                ->where('QuantityInside', $packSize)
                ->where('color', $asinRecord->color)
                ->first();

            if (!$packAsin) {
                Log::warning("No pack ASIN found", [
                    'base_asin' => $asinRecord->ASIN,
                    'pack_size' => $packSize,
                    'color' => $asinRecord->color
                ]);
                return [
                    'success' => false,
                    'message' => "Pack ASIN not found for {$packSize}-pack"
                ];
            }
        }

        // Find pack FNSKU (must match: ASIN, condition, store, QuantityInside, available units)
        $packFnsku = DB::table('tblfnsku as fnsku')
            ->join('tblasin as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
            ->where('fnsku.ASIN', $packAsin->ASIN)
            ->where('fnsku.fnsku_status', 'available')
            ->where('fnsku.Units', '>', 0)
            ->where('fnsku.grading', $fnskuRecord->grading)
            ->where('fnsku.storename', $fnskuRecord->storename)
            ->where('asin.QuantityInside', $packSize)
            ->select('fnsku.*')
            ->first();

        if (!$packFnsku) {
            Log::warning("No pack FNSKU available", [
                'pack_asin' => $packAsin->ASIN,
                'condition' => $fnskuRecord->grading,
                'store' => $fnskuRecord->storename,
                'pack_size' => $packSize
            ]);
            return [
                'success' => false,
                'message' => "Pack FNSKU not available for {$packSize}-pack"
            ];
        }

        // Get next available FNSKU with prefix
        $fnskuInfo = $this->getNextAvailableFnsku(
            $packFnsku->FNSKU,
            $packAsin->ASIN,
            $fnskuRecord->grading,
            $fnskuRecord->storename
        );

        // Create merge record
        $california_timezone = new DateTimeZone('America/Los_Angeles');
        $currentDatetime = new DateTime('now', $california_timezone);
        $currentDate = $currentDatetime->format('Y-m-d');
        $currentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');

        $mergeId = DB::table('tblmigrateditem')->insertGetId([
            'migratedDate' => $currentDate
        ]);

        // Get new RT counter
        $maxRt = DB::table('tblproduct')->max('rtcounter') ?? 0;
        $newRt = $maxRt + 1;

        // ✅ UPDATED: Create merged product WITH concatenated location
        $productData = [
            'rtcounter' => $newRt,
            'mergeID' => $mergeId,
            'price' => $totalPrice,
            'quantity' => $packSize,
            'stockroom_insert_date' => $currentDatetimeString,
            'ProductModuleLoc' => 'Stockroom',
            'warehouselocation' => $mergedLocation, // ✅ Concatenated location
            'serialnumber' => $serials[0] ?? null,
            'serialnumberb' => $serials[1] ?? null,
            'serialnumberc' => $serials[2] ?? null,
            'serialnumberd' => $serials[3] ?? null,
            'validation_status' => 'validated',
            'FNSKUviewer' => $fnskuInfo['actual_fnsku'],
            'FbmAvailable' => 1,
            'Fulfilledby' => 'FBM'
        ];

        $newProductId = DB::table('tblproduct')->insertGetId($productData);

        // Update FNSKU units
        $this->updateFnskuUnits(
            $packFnsku->FNSKU,
            $packAsin->ASIN,
            $fnskuRecord->grading,
            $fnskuRecord->storename
        );

        // Mark original products as merged
        DB::table('tblproduct')
            ->whereIn('ProductID', $productIds)
            ->update([
                'ProductModuleLoc' => 'Merged',
                'mergedTO' => $newRt
            ]);

        // Add history entry
        $user = Auth::user();
        $userName = $user ? ($user->username ?? $user->name ?? 'System') : 'System';

        DB::table('tblitemprocesshistory')->insert([
            'rtcounter' => $newRt,
            'employeeName' => $userName,
            'editDate' => $currentDatetimeString,
            'Module' => 'Auto-Dispense Merge',
            'Action' => "Auto-merged {$packSize} singles into pack #{$packNumber} at location: {$mergedLocation}"
        ]);

        Log::info("✅ Pack created successfully with merged location", [
            'pack_number' => $packNumber,
            'product_id' => $newProductId,
            'rt' => $newRt,
            'pack_size' => $packSize,
            'fnsku' => $fnskuInfo['actual_fnsku'],
            'location' => $mergedLocation,
            'from_locations' => $locations
        ]);

        return [
            'success' => true,
            'product_id' => $newProductId,
            'newrt' => $newRt,
            'fnsku' => $fnskuInfo['actual_fnsku'],
            'serials' => $serials,
            'location' => $mergedLocation, // ✅ Return merged location
            'pack_asin' => $packAsin->ASIN
        ];

    } catch (\Exception $e) {
        Log::error("Error creating pack: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

}