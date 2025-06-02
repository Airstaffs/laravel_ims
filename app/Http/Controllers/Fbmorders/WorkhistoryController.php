<?php

namespace App\Http\Controllers\Fbmorders;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
        $sort_column = $sort_by === 'purchase_date' ? 'lh.createdDate' : 'sh.PurchaseDate';

        $query = DB::table('tbllabelhistory as lh')
            ->leftJoin('tblshiphistory as sh', 'lh.AmazonOrderId', '=', 'sh.AmazonOrderId')
            ->leftJoin('tblproduct as tp', 'sh.ProductID', '=', 'tp.ProductID')
            ->leftJoin('tblamzntrackinghistory as amznth', 'lh.trackingid', '=', 'amznth.trackingnumber')
            ->select(
                'lh.*',
                'sh.ordernote',
                'sh.PurchaseDate',
                'sh.LatestShipDate',
                'sh.EarliestDeliveryDate',
                'sh.LatestDeliveryDate',
                'sh.costumer_name as customer_name',
                'sh.ProductID',
                'sh.OrderItemId',
                'sh.Title',
                'sh.SellerSKU as MSKU',
                'sh.ASIN',
                'sh.strname',
                'sh.datedelivered',
                'amznth.current_tracking_status as trackingstatus',
                'amznth.carrier',
                'amznth.carrier_description'
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
              ->orderBy('sh.OrderItemId')
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
            $datedeliveredsheesh = $row->datedelivered ? Carbon::parse($row->createdDate, 'UTC')->setTimezone('America/Los_Angeles')->format('m-d-Y') : null;

            // FNSKU fetch
            $fnskuArray = DB::table('tblproduct')
                ->where('shipment_tracking_number', $row->trackingid)
                ->pluck('FNSKUviewer')
                ->toArray();

            if (!isset($groupedHistory[$orderId])) {
                $groupedHistory[$orderId] = [
                    'orderInfo' => [
                        'AmazonOrderId' => $orderId,
                        'customer_name' => $row->customer_name,
                        'PurchaseDate' => $row->PurchaseDate,
                        'purchaselabeldate' => $purchaselabeldate,
                        'datecreatedsheesh' => $datecreatedsheesh,
                        'LatestShipDateoforder' => $LatestShipDateoforder,
                        'EarliestDeliveryDateSheesh' => $EarliestDeliveryDateSheesh,
                        'LatestDeliveryDateSheesh' => $LatestDeliveryDateSheesh,
                        'datedeliveredsheesh' => $datedeliveredsheesh,
                        'ordernote' => $row->ordernote,
                        'strname' => $row->strname,
                        'trackingstatus' => $row->trackingstatus,
                        'carrier' => $row->carrier,
                        'carrier_description' => $row->carrier_description,
                        'dispensedFNSKU' => $fnskuArray,
                        'trackingid' => $row->trackingid,
                    ],
                    'items' => []
                ];
            }

            $groupedHistory[$orderId]['items'][] = [
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
}
