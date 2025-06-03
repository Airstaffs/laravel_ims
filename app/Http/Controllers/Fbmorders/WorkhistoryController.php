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

class WorkhistoryController extends Controller
{
    public function fetchWorkHistory(Request $request)
    {
        $user_id = $request->input('user_id', 'Jundell');
        $start_date = $request->input('start_date') ? $request->input('start_date') . ' 00:01:00' : Carbon::now()->startOfDay()->format('Y-m-d H:i:s');
        $end_date = $request->input('end_date') ? $request->input('end_date') . ' 23:59:59' : Carbon::now()->endOfDay()->format('Y-m-d H:i:s');
        $sort_by = $request->input('sort_by', 'purchase_date');
        $sort_order = strtoupper($request->input('sort_order', 'DESC'));
        $search_query = trim($request->input('search_query', ''));

        $allowed_orders = ['ASC', 'DESC'];
        $sort_order = in_array($sort_order, $allowed_orders) ? $sort_order : 'DESC';
        $sort_column = $sort_by === 'purchase_date' ? 'lh.createdDate' : 'oo.purchase_date';

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

        if ($user_id !== 'all') {
            $query->where('lh.user', $user_id);
        }

        if (!empty($search_query)) {
            $query->where(function ($q) use ($search_query) {
                $q->where('lh.AmazonOrderId', 'like', "%$search_query%")
                    ->orWhere('lh.trackingid', 'like', "%$search_query%");
            });
        }

        $query->orderBy('lh.AmazonOrderId')
            ->orderBy('oi.platform_order_item_id')
            ->orderBy($sort_column, $sort_order);

        if (!empty($search_query)) {
            $query->limit(30);
        }

        $results = $query->get();

        $groupedHistory = [];

        foreach ($results as $row) {
            $orderId = $row->AmazonOrderId;

            // Format dates
            $purchaselabeldate = $row->createdDate ? Carbon::parse($row->createdDate)->format('m-d-Y') : null;
            $datecreatedsheesh = $row->PurchaseDate ? Carbon::parse($row->PurchaseDate)->format('m-d-Y') : null;
            $LatestShipDateoforder = $row->LatestShipDate ? Carbon::parse($row->LatestShipDate)->format('m-d-Y') : null;
            $EarliestDeliveryDateSheesh = $row->EarliestDeliveryDate ? Carbon::parse($row->EarliestDeliveryDate)->format('m-d-Y') : null;
            $LatestDeliveryDateSheesh = $row->LatestDeliveryDate ? Carbon::parse($row->LatestDeliveryDate)->format('m-d-Y') : null;
            $datedeliveredsheesh = $row->datedelivered ? Carbon::parse($row->datedelivered)->format('m-d-Y') : null;

            // Fetch dispensed FNSKU via tblorderitemdispense -> tblproduct
            $fnskuArray = DB::table('tblorderitemdispense as oid')
                ->join('tblproduct as p', 'oid.productid', '=', 'p.ProductID')
                ->where('oid.orderitemid', $row->outboundorderitemid)
                ->pluck('p.FNSKUviewer')
                ->toArray();

            if (!isset($groupedHistory[$orderId])) {
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

            $groupedHistory[$orderId]['orderInfo']['items'][] = [
                'OrderItemId' => $row->OrderItemId,
                'Title' => $row->Title,
                'ASIN' => $row->ASIN,
                'MSKU' => $row->MSKU,
                'ProductID' => $row->ProductID
            ];
        }

        return response()->json([
            'success' => true,
            'history' => array_values($groupedHistory),
            'userid' => $user_id,
            'message' => count($groupedHistory) ? null : 'No work history found.'
        ]);
    }

    public function exportWorkHistory(Request $request)
    {
        try {
            // Get the same parameters used for filtering
            $user_id = $request->input('user_id', 'all');
            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');
            $sort_by = $request->input('sort_by', 'purchase_date');
            $sort_order = strtoupper($request->input('sort_order', 'DESC'));
            $search_query = trim($request->input('search_query', ''));
            $carrier_filter = $request->input('carrier_filter', '');
            $store_filter = $request->input('store_filter', '');
            $late_orders = $request->input('late_orders', '');

            // Determine if dates are filtered
            $date_filtered = !empty($start_date) && !empty($end_date);
            
            // Set default dates if not provided (export all data)
            if (!$date_filtered) {
                $start_date_query = '2020-01-01 00:01:00'; // Very early date to get all records
                $end_date_query = Carbon::now()->addYear()->format('Y-m-d H:i:s'); // Future date
            } else {
                $start_date_query = $start_date . ' 00:01:00';
                $end_date_query = $end_date . ' 23:59:59';
            }

            $allowed_orders = ['ASC', 'DESC'];
            $sort_order = in_array($sort_order, $allowed_orders) ? $sort_order : 'DESC';
            $sort_column = $sort_by === 'purchase_date' ? 'lh.createdDate' : 'oo.purchase_date';

            // Build the same query as fetchWorkHistory but for export
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

            // Order the results
            $query->orderBy('lh.AmazonOrderId')
                ->orderBy('oi.platform_order_item_id')
                ->orderBy($sort_column, $sort_order);

            $results = $query->get();

            // Group the results like in fetchWorkHistory
            $groupedHistory = [];
            foreach ($results as $row) {
                $orderId = $row->AmazonOrderId;

                // Format dates for Excel
                $purchaselabeldate = $row->createdDate ? Carbon::parse($row->createdDate)->format('m/d/Y H:i:s') : 'N/A';
                $datecreatedsheesh = $row->PurchaseDate ? Carbon::parse($row->PurchaseDate)->format('m/d/Y') : 'N/A';
                $datedeliveredsheesh = $row->datedelivered ? Carbon::parse($row->datedelivered)->format('m/d/Y') : 'N/A';

                // Fetch dispensed FNSKU
                $fnskuArray = DB::table('tblorderitemdispense as oid')
                    ->join('tblproduct as p', 'oid.productid', '=', 'p.ProductID')
                    ->where('oid.orderitemid', $row->outboundorderitemid)
                    ->pluck('p.FNSKUviewer')
                    ->toArray();

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

                $groupedHistory[$orderId]['items'][] = [
                    'OrderItemId' => $row->OrderItemId ?? 'N/A',
                    'Title' => $row->Title ?? 'N/A',
                    'ASIN' => $row->ASIN ?? 'N/A',
                    'MSKU' => $row->MSKU ?? 'N/A'
                ];
            }

            // Create Excel file using PhpSpreadsheet
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

            // Add data rows
            $row = 2;
            foreach ($groupedHistory as $order) {
                // Format items for display
                $itemsDisplay = '';
                if (!empty($order['items'])) {
                    $itemParts = [];
                    foreach ($order['items'] as $item) {
                        $itemParts[] = $item['ASIN'] . ' / ' . $item['Title'] . ' / ' . $item['MSKU'];
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

                // Style the data rows
                $dataStyle = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC']
                        ]
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_TOP,
                        'wrapText' => true
                    ]
                ];

                $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray($dataStyle);
                $row++;
            }

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
            $writer->save($tempFile);

            // Set headers for Excel download
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0',
                'Cache-Control' => 'max-age=1',
                'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
                'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
                'Cache-Control' => 'cache, must-revalidate',
                'Pragma' => 'public'
            ];

            return response()->download($tempFile, $filename, $headers)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
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