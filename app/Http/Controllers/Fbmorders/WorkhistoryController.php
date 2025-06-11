<?php

namespace App\Http\Controllers\Fbmorders;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Log;

class WorkhistoryController extends Controller
{
   public function fetchWorkHistory(Request $request)
    {
        // Get filter parameters
        $user_id = $request->input('user_id', 'Jundell');
        $start_date = $request->input('start_date') ? $request->input('start_date') . ' 00:01:00' : Carbon::now()->startOfDay()->format('Y-m-d H:i:s');
        $end_date = $request->input('end_date') ? $request->input('end_date') . ' 23:59:59' : Carbon::now()->endOfDay()->format('Y-m-d H:i:s');
        $sort_by = $request->input('sort_by', 'purchase_date');
        $sort_order = strtoupper($request->input('sort_order', 'DESC'));
        $search_query = trim($request->input('search_query', ''));
        
        // Get pagination parameters
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 20);
        
        // Additional filters
        $carrier_filter = $request->input('carrier_filter', '');
        $store_filter = $request->input('store_filter', '');
        $late_orders = $request->input('late_orders', '');

        $allowed_orders = ['ASC', 'DESC'];
        $sort_order = in_array($sort_order, $allowed_orders) ? $sort_order : 'DESC';
        $sort_column = $sort_by === 'purchase_date' ? 'lh.createdDate' : 'oo.purchase_date';

        // Build the base query
        $query = DB::table('tbllabelhistory as lh')
            ->join('tbllabelhistoryitems as lhi', 'lh.AmazonOrderId', '=', 'lhi.AmazonOrderId')
            ->leftJoin('tbloutboundordersitem as oi', function ($join) {
                $join->on('lhi.AmazonOrderId', '=', 'oi.platform_order_id')
                    ->on('lhi.OrderItemId', '=', 'oi.platform_order_item_id');
            })
            ->leftJoin('tbloutboundorders as oo', 'oi.platform_order_id', '=', 'oo.platform_order_id')
            ->leftJoin('tblorderitemdispense as oid', 'oi.outboundorderitemid', '=', 'oid.orderitemid')
            ->leftJoin('tblproduct as p', 'oid.productid', '=', 'p.ProductID')
            ->leftJoin('tblamzntrackinghistory as amznth', 'lh.trackingid', '=', 'amznth.trackingnumber')
            ->select(
                'lh.*',
                'oo.ordernote',
                'oo.PurchaseDate as PurchaseDate',
                'oo.LatestShipDate as LatestShipDate',
                'oo.EarliestDeliveryDate as EarliestDeliveryDate',
                'oo.LatestDeliveryDate as LatestDeliveryDate',
                'oo.BuyerName as customer_name',
                'p.ProductID',
                'oi.outboundorderitemid',
                'oi.platform_order_item_id as OrderItemId',
                'oi.platform_title as Title',
                'oi.platform_sku as MSKU',
                'oi.platform_asin as ASIN',
                'oo.storename as strname',
                'oo.delivery_date as datedelivered',
                'amznth.current_tracking_status as trackingstatus',
                'amznth.carrier',
                'amznth.carrier_description',
                'p.FNSKUviewer'
            )
            ->where('lh.status', 'Purchased')
            ->whereBetween($sort_column, [$start_date, $end_date]);

        // Apply filters
        if ($user_id !== 'all') {
            $query->where('lh.user', $user_id);
        }

        if (!empty($search_query)) {
            $query->where(function ($q) use ($search_query) {
                $q->where('lh.AmazonOrderId', 'like', "%$search_query%")
                    ->orWhere('lh.trackingid', 'like', "%$search_query%");
            });
        }

        if (!empty($carrier_filter)) {
            $query->where(function ($q) use ($carrier_filter) {
                $q->where('amznth.carrier', 'like', "%$carrier_filter%")
                    ->orWhere('amznth.carrier_description', 'like', "%$carrier_filter%");
            });
        }

        if (!empty($store_filter)) {
            $query->where('oo.storename', 'like', "%$store_filter%");
        }

        // Apply late orders filter if needed
        if ($late_orders === 'late') {
            $query->whereRaw('oo.LatestShipDate < NOW()');
        } elseif ($late_orders === 'ontime') {
            $query->whereRaw('oo.LatestShipDate >= NOW()');
        }

        // Order the results
        $query->orderBy('lh.AmazonOrderId')
            ->orderBy('oi.platform_order_item_id')
            ->orderBy($sort_column, $sort_order);

        // Clone query for counting total records before pagination
        $countQuery = clone $query;
        
        // Get distinct order count for total records
        $totalOrders = $countQuery->distinct('lh.AmazonOrderId')->count('lh.AmazonOrderId');

        // Apply pagination to get results
        $offset = ($page - 1) * $per_page;
        $results = $query->offset($offset)->limit($per_page * 5)->get(); // Get extra records to handle grouping

        // Group the results by order
        $groupedHistory = [];
        $orderCount = 0;
        $startIndex = 0;
        $endIndex = 0;

        foreach ($results as $index => $row) {
            $orderId = $row->AmazonOrderId;

            // Format dates
            $purchaselabeldate = $row->createdDate ? Carbon::parse($row->createdDate)->format('m-d-Y') : null;
            $datecreatedsheesh = $row->PurchaseDate ? Carbon::parse($row->PurchaseDate)->format('m-d-Y') : null;
            $LatestShipDateoforder = $row->LatestShipDate ? Carbon::parse($row->LatestShipDate)->format('m-d-Y') : null;
            $EarliestDeliveryDateSheesh = $row->EarliestDeliveryDate ? Carbon::parse($row->EarliestDeliveryDate)->format('m-d-Y') : null;
            $LatestDeliveryDateSheesh = $row->LatestDeliveryDate ? Carbon::parse($row->LatestDeliveryDate)->format('m-d-Y') : null;
            $datedeliveredsheesh = $row->datedelivered ? Carbon::parse($row->datedelivered)->format('m-d-Y') : null;

            // Fetch dispensed FNSKU
            $fnskuArray = [];
            if ($row->outboundorderitemid) {
                $fnskuArray = DB::table('tblorderitemdispense as oid')
                    ->join('tblproduct as p', 'oid.productid', '=', 'p.ProductID')
                    ->where('oid.orderitemid', $row->outboundorderitemid)
                    ->pluck('p.FNSKUviewer')
                    ->toArray();
            }

            if (!isset($groupedHistory[$orderId])) {
                $orderCount++;
                
                // Check if we've reached our per_page limit
                if ($orderCount > $per_page) {
                    break;
                }
                
                if ($orderCount === 1) {
                    $startIndex = $offset + 1;
                }
                $endIndex = $offset + $orderCount;

                $groupedHistory[$orderId] = [
                    'orderInfo' => [
                        'AmazonOrderId' => $orderId ?? '',
                        'customer_name' => $row->customer_name ?? '',
                        'PurchaseDate' => $row->PurchaseDate ?? '',
                        'purchaselabeldate' => $purchaselabeldate ?? '',
                        'datecreatedsheesh' => $datecreatedsheesh ?? '',
                        'LatestShipDateoforder' => $LatestShipDateoforder ?? '',
                        'EarliestDeliveryDateSheesh' => $EarliestDeliveryDateSheesh ?? '',
                        'LatestDeliveryDateSheesh' => $LatestDeliveryDateSheesh ?? '',
                        'datedeliveredsheesh' => $datedeliveredsheesh ?? '',
                        'ordernote' => $row->ordernote ?? '',
                        'strname' => $row->strname ?? '',
                        'trackingstatus' => $row->trackingstatus ?? '',
                        'carrier' => $row->carrier ?? '',
                        'carrier_description' => $row->carrier_description ?? '',
                        'dispensedFNSKU' => $fnskuArray ?? [],
                        'trackingid' => $row->trackingid ?? '',
                        'items' => []
                    ]
                ];
            }

            // Only add items for orders within our page limit
            if ($orderCount <= $per_page) {
                $groupedHistory[$orderId]['orderInfo']['items'][] = [
                    'OrderItemId' => $row->OrderItemId,
                    'Title' => $row->Title,
                    'ASIN' => $row->ASIN,
                    'MSKU' => $row->MSKU,
                    'ProductID' => $row->ProductID
                ];
            }
        }

        // Calculate pagination info
        $totalPages = ceil($totalOrders / $per_page);
        $currentPage = min($page, $totalPages);
        
        // Adjust indices if no results
        if (empty($groupedHistory)) {
            $startIndex = 0;
            $endIndex = 0;
        }

        return response()->json([
            'success' => true,
            'history' => array_values($groupedHistory),
            'userid' => $user_id,
            'message' => count($groupedHistory) ? null : 'No work history found.',
            // Pagination info
            'total' => $totalOrders,
            'per_page' => $per_page,
            'current_page' => $currentPage,
            'last_page' => $totalPages,
            'from' => $startIndex,
            'to' => $endIndex
        ]);
    }


public function exportWorkHistory(Request $request)
{
    try {
        Log::info('Export Work History started', [
            'request_data' => $request->all(),
        ]);

        // ✅ FIXED: More flexible validation rules
        $validated = $request->validate([
            'user_id' => 'sometimes|nullable|string',
            'start_date' => 'sometimes|nullable|string', // Changed from 'date' to 'string' since it comes as YYYY-MM-DD
            'end_date' => 'sometimes|nullable|string',   // Changed from 'date' to 'string' since it comes as YYYY-MM-DD
            'sort_by' => 'sometimes|nullable|string',
            'sort_order' => 'sometimes|nullable|string',
            'search_query' => 'sometimes|nullable|string', // ✅ Added 'nullable'
            'carrier_filter' => 'sometimes|nullable|string', // ✅ Added 'nullable'
            'store_filter' => 'sometimes|nullable|string',   // ✅ Added 'nullable'
            'late_orders' => 'sometimes|nullable|string',    // ✅ Added 'nullable'
        ]);

        Log::info('Validation passed', ['validated' => $validated]);

        // ✅ FIXED: Better parameter handling with null checks
        $user_id = $request->input('user_id', 'all');
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $sort_by = $request->input('sort_by', 'purchase_date');
        $sort_order = strtoupper($request->input('sort_order', 'DESC'));
        $search_query = trim($request->input('search_query', '') ?? ''); // ✅ Added null coalescing
        $carrier_filter = $request->input('carrier_filter', '') ?? '';   // ✅ Added null coalescing
        $store_filter = $request->input('store_filter', '') ?? '';       // ✅ Added null coalescing
        $late_orders = $request->input('late_orders', '') ?? '';         // ✅ Added null coalescing

        Log::info('Parameters processed', [
            'user_id' => $user_id,
            'date_range' => [$start_date, $end_date],
            'search_query' => $search_query,
            'carrier_filter' => $carrier_filter,
            'store_filter' => $store_filter,
        ]);

        // Determine if dates are filtered
        $date_filtered = !empty($start_date) && !empty($end_date);
        
        // Set default dates if not provided (export all data)
        if (!$date_filtered) {
            $start_date_query = '2020-01-01 00:01:00';
            $end_date_query = Carbon::now()->addYear()->format('Y-m-d H:i:s');
        } else {
            // ✅ FIXED: Better date handling
            try {
                $start_date_query = Carbon::parse($start_date)->format('Y-m-d') . ' 00:01:00';
                $end_date_query = Carbon::parse($end_date)->format('Y-m-d') . ' 23:59:59';
            } catch (\Exception $e) {
                Log::error('Date parsing error', ['start_date' => $start_date, 'end_date' => $end_date, 'error' => $e->getMessage()]);
                throw new \Exception('Invalid date format provided');
            }
        }

        $allowed_orders = ['ASC', 'DESC'];
        $sort_order = in_array($sort_order, $allowed_orders) ? $sort_order : 'DESC';
        $sort_column = $sort_by === 'purchase_date' ? 'lh.createdDate' : 'oo.purchase_date';

        Log::info('About to build database query', [
            'sort_column' => $sort_column,
            'date_range' => [$start_date_query, $end_date_query]
        ]);

        // Test database connection first
        try {
            DB::connection()->getPdo();
            Log::info('Database connection successful');
        } catch (\Exception $e) {
            Log::error('Database connection failed', ['error' => $e->getMessage()]);
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }

        // Build the query
        $query = DB::table('tbllabelhistory as lh')
            ->join('tbllabelhistoryitems as lhi', 'lh.AmazonOrderId', '=', 'lhi.AmazonOrderId')
            ->leftJoin('tbloutboundordersitem as oi', function ($join) {
                $join->on('lhi.AmazonOrderId', '=', 'oi.platform_order_id')
                    ->on('lhi.OrderItemId', '=', 'oi.platform_order_item_id');
            })
            ->leftJoin('tbloutboundorders as oo', 'oi.platform_order_id', '=', 'oo.platform_order_id')
            ->leftJoin('tblorderitemdispense as oid', 'oi.outboundorderitemid', '=', 'oid.orderitemid')
            ->leftJoin('tblproduct as p', 'oid.productid', '=', 'p.ProductID')
            ->leftJoin('tblamzntrackinghistory as amznth', 'lh.trackingid', '=', 'amznth.trackingnumber')
            ->select(
                'lh.*',
                'oo.ordernote',
                'oo.PurchaseDate as PurchaseDate',
                'oo.LatestShipDate as LatestShipDate',
                'oo.EarliestDeliveryDate as EarliestDeliveryDate',
                'oo.LatestDeliveryDate as LatestDeliveryDate',
                'oo.BuyerName as customer_name',
                'p.ProductID',
                'oi.outboundorderitemid',
                'oi.platform_order_item_id as OrderItemId',
                'oi.platform_title as Title',
                'oi.platform_sku as MSKU',
                'oi.platform_asin as ASIN',
                'oo.storename as strname',
                'oo.delivery_date as datedelivered',
                'amznth.current_tracking_status as trackingstatus',
                'amznth.carrier',
                'amznth.carrier_description',
                'p.FNSKUviewer'
            )
            ->where('lh.status', 'Purchased')
            ->whereBetween($sort_column, [$start_date_query, $end_date_query]);

        // Apply filters with better null checking
        if ($user_id !== 'all' && !empty($user_id)) {
            $query->where('lh.user', $user_id);
        }

        if (!empty($search_query)) {
            $query->where(function ($q) use ($search_query) {
                $q->where('lh.AmazonOrderId', 'like', "%$search_query%")
                    ->orWhere('lh.trackingid', 'like', "%$search_query%");
            });
        }

        if (!empty($carrier_filter)) {
            $query->where(function ($q) use ($carrier_filter) {
                $q->where('amznth.carrier', 'like', "%$carrier_filter%")
                    ->orWhere('amznth.carrier_description', 'like', "%$carrier_filter%");
            });
        }

        if (!empty($store_filter)) {
            $query->where('oo.storename', 'like', "%$store_filter%");
        }

        // Order the results
        $query->orderBy('lh.AmazonOrderId')
            ->orderBy('oi.platform_order_item_id')
            ->orderBy($sort_column, $sort_order);

        Log::info('About to execute database query');

        $results = $query->get();
        
        Log::info('Query executed successfully', ['result_count' => $results->count()]);

        // Check if we have results
        if ($results->isEmpty()) {
            Log::warning('No data found for export');
            return response()->json([
                'success' => false,
                'message' => 'No data found for the specified criteria.'
            ], 404);
        }

        Log::info('Starting to group results');

        // Group the results
        $groupedHistory = [];
        foreach ($results as $row) {
            $orderId = $row->AmazonOrderId;

            // Format dates for Excel with null checks
            $purchaselabeldate = $row->createdDate ? Carbon::parse($row->createdDate)->format('m/d/Y H:i:s') : 'N/A';
            $datecreatedsheesh = $row->PurchaseDate ? Carbon::parse($row->PurchaseDate)->format('m/d/Y') : 'N/A';
            $datedeliveredsheesh = $row->datedelivered ? Carbon::parse($row->datedelivered)->format('m/d/Y') : 'N/A';

            // Fetch dispensed FNSKU
            $fnskuArray = [];
            if ($row->outboundorderitemid) {
                try {
                    $fnskuArray = DB::table('tblorderitemdispense as oid')
                        ->join('tblproduct as p', 'oid.productid', '=', 'p.ProductID')
                        ->where('oid.orderitemid', $row->outboundorderitemid)
                        ->pluck('p.FNSKUviewer')
                        ->toArray();
                } catch (\Exception $e) {
                    Log::warning('FNSKU query failed', ['order_item_id' => $row->outboundorderitemid, 'error' => $e->getMessage()]);
                    $fnskuArray = [];
                }
            }

            if (!isset($groupedHistory[$orderId])) {
                $groupedHistory[$orderId] = [
                    'AmazonOrderId' => $orderId ?? 'N/A',
                    'customer_name' => $row->customer_name ?? 'N/A',
                    'purchaselabeldate' => $purchaselabeldate,
                    'datecreatedsheesh' => $datecreatedsheesh,
                    'datedeliveredsheesh' => $datedeliveredsheesh,
                    'trackingid' => $row->trackingid ?? 'N/A',
                    'carrier' => $this->getCarrierText($row->carrier ?? $row->carrier_description ?? 'N/A'),
                    'strname' => $row->strname ?? 'N/A',
                    'dispensedFNSKU' => implode(', ', $fnskuArray),
                    'ordernote' => $row->ordernote ?? 'N/A',
                    'items' => []
                ];
            }

            if ($row->OrderItemId) {
                $groupedHistory[$orderId]['items'][] = [
                    'OrderItemId' => $row->OrderItemId ?? 'N/A',
                    'Title' => $row->Title ?? 'N/A',
                    'ASIN' => $row->ASIN ?? 'N/A',
                    'MSKU' => $row->MSKU ?? 'N/A'
                ];
            }
        }

        if (empty($groupedHistory)) {
            Log::warning('No grouped data for export');
            return response()->json([
                'success' => false,
                'message' => 'No grouped data available for export.'
            ], 404);
        }

        Log::info('About to create Excel file', ['grouped_count' => count($groupedHistory)]);

        // Check if PhpSpreadsheet is available
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            Log::error('PhpSpreadsheet not found');
            return response()->json([
                'success' => false,
                'message' => 'PhpSpreadsheet library not found. Please install it with: composer require phpoffice/phpspreadsheet'
            ], 500);
        }

        // Create Excel file
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Work History');

        // Set up headers
        $headers = [
            'A1' => 'Purchase Date',
            'B1' => 'Label Purchase Date',
            'C1' => 'Customer Name',
            'D1' => 'Ordered Items (ASIN / Title / MSKU)',
            'E1' => 'Amazon Order ID',
            'F1' => 'Tracking ID',
            'G1' => 'Carrier',
            'H1' => 'Date Delivered',
            'I1' => 'Dispensed FNSKU',
            'J1' => 'Store Name',
            'K1' => 'Remarks'
        ];

        // Set headers
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style the header row
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4CAF50']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];

        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

        // Auto-size columns
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        Log::info('Headers set, adding data rows');

        // Add data rows
        $row = 2;
        foreach ($groupedHistory as $order) {
            // Format items for display
            $itemsDisplay = '';
            if (!empty($order['items'])) {
                $itemParts = [];
                foreach ($order['items'] as $item) {
                    $itemParts[] = ($item['ASIN'] ?? 'N/A') . ' / ' . ($item['Title'] ?? 'N/A') . ' / ' . ($item['MSKU'] ?? 'N/A');
                }
                $itemsDisplay = implode(' | ', $itemParts);
            } else {
                $itemsDisplay = 'N/A';
            }

            $sheet->setCellValue('A' . $row, $order['datecreatedsheesh']);
            $sheet->setCellValue('B' . $row, $order['purchaselabeldate']);
            $sheet->setCellValue('C' . $row, $order['customer_name']);
            $sheet->setCellValue('D' . $row, $itemsDisplay);
            $sheet->setCellValue('E' . $row, $order['AmazonOrderId']);
            $sheet->setCellValue('F' . $row, $order['trackingid']);
            $sheet->setCellValue('G' . $row, $order['carrier']);
            $sheet->setCellValue('H' . $row, $order['datedeliveredsheesh']);
            $sheet->setCellValue('I' . $row, $order['dispensedFNSKU'] ?: 'N/A');
            $sheet->setCellValue('J' . $row, $order['strname']);
            $sheet->setCellValue('K' . $row, $order['ordernote']);

            $row++;
        }

        Log::info('Data rows added, generating file');

        // Generate filename
        $filename = 'work_history_' . Carbon::now()->format('Y-m-d_H-i-s') . '.xlsx';
        if ($date_filtered) {
            $start_formatted = Carbon::parse($start_date)->format('Y-m-d');
            $end_formatted = Carbon::parse($end_date)->format('Y-m-d');
            $filename = 'work_history_' . $start_formatted . '_to_' . $end_formatted . '_' . Carbon::now()->format('H-i-s') . '.xlsx';
        }

        // Create the Excel file in memory
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'work_history');
        
        Log::info('About to save Excel file', ['temp_file' => $tempFile]);
        
        $writer->save($tempFile);

        Log::info('Excel file saved successfully');

        // Set headers for Excel download
        $downloadHeaders = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ];

        Log::info('Export Work History completed successfully');

        return response()->download($tempFile, $filename, $downloadHeaders)->deleteFileAfterSend(true);

    } catch (\Exception $e) {
        Log::error('Export Work History Error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request' => $request->all(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Export failed: ' . $e->getMessage()
        ], 500);
    }
}


     private function getCarrierText($carrier)
    {
        if (!$carrier || $carrier === 'N/A') {
            return 'N/A';
        }
        
        $carrierUpper = strtoupper($carrier);
        if (strpos($carrierUpper, 'UPS') !== false) {
            return 'UPS';
        } elseif (strpos($carrierUpper, 'FEDEX') !== false || strpos($carrierUpper, 'FEDX') !== false) {
            return 'FEDEX';
        } elseif (strpos($carrierUpper, 'USPS') !== false) {
            return 'USPS';
        } elseif (strpos($carrierUpper, 'DHL') !== false) {
            return 'DHL';
        }
        
        return $carrier;
    }
}