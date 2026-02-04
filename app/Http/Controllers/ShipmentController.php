<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use DateTime;
use DateTimeZone;

class ShipmentController extends Controller
{
    /**
     * Get all shipments with pagination and filters
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search', '');
            $storeFilter = $request->input('store', '');
            $carrierFilter = $request->input('carrier', '');
            $dateFrom = $request->input('date_from', '');
            $dateTo = $request->input('date_to', '');
            $orderBy = $request->input('order_by', 'desc');
            
            Log::info('Shipments index called with params:', [
                'per_page' => $perPage,
                'page' => $page,
                'search' => $search,
                'store' => $storeFilter,
                'carrier' => $carrierFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'order_by' => $orderBy
            ]);
            
            // Base query for products in Shipment location
            $query = DB::table('tblproduct as p')
                ->select(
                    'p.ProductID',
                    'p.FNSKUviewer',
                    'p.MSKUviewer',
                    'p.warehouseLocation',
                    'p.serialNumber',
                    'p.rtCounter',
                    'p.price',
                    'p.quantity',
                    'p.stockroom_insert_date',
                    'p.ProductModuleLoc',
                    // Get FNSKU details
                    'fnsku.FNSKU',
                    'fnsku.MSKU',
                    'fnsku.ASIN',
                    'fnsku.storename',
                    'fnsku.grading as condition',
                    // Get ASIN details
                    'asin.internal as product_title',
                    'asin.color',
                    'asin.QuantityInside',
                    // Get order item details if linked
                    'oi.platform_order_item_id',
                    'oi.platform_asin as order_asin',
                    'oi.platform_sku as order_sku',
                    'oi.QuantityOrdered',
                    'oi.order_status',
                    'oi.trackingnumber',
                    'oi.trackingstatus',
                    'oi.carrier',
                    'oi.carrier_description',
                    // Get order details
                    'o.platform_order_id',
                    'o.BuyerName as customer_name',
                    'o.PurchaseDate as order_date',
                    'o.ship_date',
                    'o.delivery_date'
                )
                // Join by MSKU instead of FNSKU to avoid duplicates (like Labeling controller)
                ->leftJoin('tblfnsku as fnsku', 'p.MSKUviewer', '=', 'fnsku.MSKU')
                ->leftJoin('tblasin as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->leftJoin('tblorderitemdispense as d', 'p.ProductID', '=', 'd.productid')
                ->leftJoin('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
                ->leftJoin('tbloutboundorders as o', 'oi.platform_order_id', '=', 'o.platform_order_id')
                ->where('p.ProductModuleLoc', 'Shipment')
                ->distinct();
            
            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('p.ProductID', 'LIKE', "%{$search}%")
                      ->orWhere('p.FNSKUviewer', 'LIKE', "%{$search}%")
                      ->orWhere('p.MSKUviewer', 'LIKE', "%{$search}%")
                      ->orWhere('p.serialNumber', 'LIKE', "%{$search}%")
                      ->orWhere('p.rtCounter', 'LIKE', "%{$search}%")
                      ->orWhere('fnsku.ASIN', 'LIKE', "%{$search}%")
                      ->orWhere('fnsku.MSKU', 'LIKE', "%{$search}%")
                      ->orWhere('fnsku.FNSKU', 'LIKE', "%{$search}%")
                      ->orWhere('o.platform_order_id', 'LIKE', "%{$search}%")
                      ->orWhere('oi.trackingnumber', 'LIKE', "%{$search}%");
                });
            }
            
            // Apply store filter
            if (!empty($storeFilter)) {
                $query->where('fnsku.storename', $storeFilter);
            }
            
            // Apply carrier filter
            if (!empty($carrierFilter)) {
                $query->where(function($q) use ($carrierFilter) {
                    $q->where('oi.carrier', 'LIKE', "%{$carrierFilter}%")
                      ->orWhere('oi.carrier_description', 'LIKE', "%{$carrierFilter}%");
                });
            }
            
            // Apply date range filter (using ship_date from orders table)
            if (!empty($dateFrom)) {
                $query->where('o.ship_date', '>=', $dateFrom);
            }
            if (!empty($dateTo)) {
                $query->where('o.ship_date', '<=', $dateTo . ' 23:59:59');
            }
            
            // Get total count
            $totalCount = $query->count();
            $totalPages = ceil($totalCount / $perPage);
            
            // Apply ordering (use ship_date or stockroom_insert_date)
            $query->orderBy(
                DB::raw('COALESCE(o.ship_date, p.stockroom_insert_date)'), 
                $orderBy
            );
            
            // Get paginated results
            $shipments = $query->skip(($page - 1) * $perPage)
                               ->take($perPage)
                               ->get();
            
            Log::info('Shipments fetched: ' . $shipments->count());
            
            // Format shipments data
            $formattedShipments = $shipments->map(function($shipment) {
                return [
                    'product_id' => $shipment->ProductID,
                    'fnsku' => $shipment->FNSKU ?? $shipment->FNSKUviewer,
                    'msku' => $shipment->MSKU ?? $shipment->MSKUviewer,
                    'asin' => $shipment->ASIN,
                    'product_title' => $shipment->product_title ?? 'N/A',
                    'warehouse_location' => $shipment->warehouseLocation,
                    'serial_number' => $shipment->serialNumber,
                    'rt_counter' => $shipment->rtCounter,
                    'price' => $shipment->price,
                    'quantity' => $shipment->quantity,
                    'quantity_inside' => $shipment->QuantityInside ?? 1,
                    'store_name' => $shipment->storename,
                    'condition' => $shipment->condition,
                    'color' => $shipment->color,
                    'stockroom_date' => $shipment->stockroom_insert_date,
                    'shipment_date' => $shipment->ship_date,
                    
                    // Order information
                    'order_id' => $shipment->platform_order_id,
                    'order_item_id' => $shipment->platform_order_item_id,
                    'customer_name' => $shipment->customer_name,
                    'order_date' => $shipment->order_date,
                    'order_asin' => $shipment->order_asin,
                    'order_sku' => $shipment->order_sku,
                    'quantity_ordered' => $shipment->QuantityOrdered,
                    'order_status' => $shipment->order_status,
                    
                    // Tracking information
                    'tracking_number' => $shipment->trackingnumber,
                    'tracking_status' => $shipment->trackingstatus,
                    'carrier' => $shipment->carrier ?? $shipment->carrier_description,
                    'ship_date' => $shipment->ship_date,
                    'delivery_date' => $shipment->delivery_date,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedShipments,
                'total' => $totalCount,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $totalPages
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching shipments: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching shipments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get shipment details by product ID
     */
    public function show(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|integer'
            ]);
            
            $productId = $request->input('product_id');
            
            $shipment = DB::table('tblproduct as p')
                ->select(
                    'p.*',
                    'fnsku.FNSKU',
                    'fnsku.MSKU',
                    'fnsku.ASIN',
                    'fnsku.storename',
                    'fnsku.grading',
                    'asin.internal as product_title',
                    'asin.color',
                    'asin.QuantityInside',
                    'oi.platform_order_item_id',
                    'oi.trackingnumber',
                    'oi.trackingstatus',
                    'oi.carrier',
                    'oi.carrier_description',
                    'o.platform_order_id',
                    'o.BuyerName as customer_name',
                    'o.address_line1',
                    'o.city',
                    'o.StateOrRegion',
                    'o.postal_code'
                )
                ->leftJoin('tblfnsku as fnsku', 'p.MSKUviewer', '=', 'fnsku.MSKU')
                ->leftJoin('tblasin as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->leftJoin('tblorderitemdispense as d', 'p.ProductID', '=', 'd.productid')
                ->leftJoin('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
                ->leftJoin('tbloutboundorders as o', 'oi.platform_order_id', '=', 'o.platform_order_id')
                ->where('p.ProductID', $productId)
                ->where('p.ProductModuleLoc', 'Shipment')
                ->first();
            
            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shipment not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $shipment
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching shipment details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching shipment details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get list of stores for filtering
     */
    public function getStores()
    {
        try {
            $stores = DB::table('tblproduct as p')
                ->join('tblfnsku as fnsku', 'p.MSKUviewer', '=', 'fnsku.MSKU')
                ->where('p.ProductModuleLoc', 'Shipment')
                ->select('fnsku.storename')
                ->distinct()
                ->pluck('storename')
                ->filter()
                ->values()
                ->toArray();
            
            return response()->json([
                'success' => true,
                'stores' => $stores
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stores: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stores'
            ], 500);
        }
    }
    
    /**
     * Get list of carriers for filtering
     */
    public function getCarriers()
    {
        try {
            $carriers = DB::table('tblproduct as p')
                ->join('tblorderitemdispense as d', 'p.ProductID', '=', 'd.productid')
                ->join('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
                ->where('p.ProductModuleLoc', 'Shipment')
                ->whereNotNull('oi.carrier')
                ->select('oi.carrier')
                ->distinct()
                ->pluck('carrier')
                ->filter()
                ->values()
                ->toArray();
            
            return response()->json([
                'success' => true,
                'carriers' => $carriers
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching carriers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching carriers'
            ], 500);
        }
    }
    
    /**
     * Get shipment statistics
     */
    public function getStats()
    {
        try {
            $stats = [
                'total_shipments' => DB::table('tblproduct')
                    ->where('ProductModuleLoc', 'Shipment')
                    ->count(),
                
                'shipped_today' => DB::table('tblproduct as p')
                    ->join('tblorderitemdispense as d', 'p.ProductID', '=', 'd.productid')
                    ->join('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
                    ->join('tbloutboundorders as o', 'oi.platform_order_id', '=', 'o.platform_order_id')
                    ->where('p.ProductModuleLoc', 'Shipment')
                    ->whereDate('o.ship_date', today())
                    ->count(),
                
                'shipped_this_week' => DB::table('tblproduct as p')
                    ->join('tblorderitemdispense as d', 'p.ProductID', '=', 'd.productid')
                    ->join('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
                    ->join('tbloutboundorders as o', 'oi.platform_order_id', '=', 'o.platform_order_id')
                    ->where('p.ProductModuleLoc', 'Shipment')
                    ->whereBetween('o.ship_date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count(),
                
                'shipped_this_month' => DB::table('tblproduct as p')
                    ->join('tblorderitemdispense as d', 'p.ProductID', '=', 'd.productid')
                    ->join('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
                    ->join('tbloutboundorders as o', 'oi.platform_order_id', '=', 'o.platform_order_id')
                    ->where('p.ProductModuleLoc', 'Shipment')
                    ->whereMonth('o.ship_date', now()->month)
                    ->whereYear('o.ship_date', now()->year)
                    ->count(),
                
                'by_carrier' => DB::table('tblproduct as p')
                    ->join('tblorderitemdispense as d', 'p.ProductID', '=', 'd.productid')
                    ->join('tbloutboundordersitem as oi', 'd.orderitemid', '=', 'oi.outboundorderitemid')
                    ->where('p.ProductModuleLoc', 'Shipment')
                    ->select('oi.carrier', DB::raw('COUNT(*) as count'))
                    ->groupBy('oi.carrier')
                    ->get(),
                
                'by_store' => DB::table('tblproduct as p')
                    ->join('tblfnsku as fnsku', 'p.MSKUviewer', '=', 'fnsku.MSKU')
                    ->where('p.ProductModuleLoc', 'Shipment')
                    ->select('fnsku.storename', DB::raw('COUNT(*) as count'))
                    ->groupBy('fnsku.storename')
                    ->get()
            ];
            
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching shipment stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching statistics'
            ], 500);
        }
    }
}