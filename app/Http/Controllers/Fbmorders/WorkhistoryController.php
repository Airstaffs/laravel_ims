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
        $sort_column = $sort_by === 'purchase_date' ? 'lh.createdDate' : 'oo.purchase_date';

        $query = DB::table('tbllabelhistory as lh')
            ->leftJoin('tbloutboundordersitem as oi', function ($join) {
                $join->on('lh.AmazonOrderId', '=', 'oi.platform_order_id')
                    ->on('lh.OrderItemId', '=', 'oi.platform_order_item_id');
            })
            ->leftJoin('tbloutboundorders as oo', 'oi.outbound_order_id', '=', 'oo.outboundorderid')
            ->leftJoin('tblorderitemdispense as oid', 'oi.outboundorderitemid', '=', 'oid.outboundorderitemid')
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
                'oi.title as Title',
                'oi.sku as MSKU',
                'oi.asin as ASIN',
                'oo.store_name as strname',
                'oo.date_delivered as datedelivered',
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
                ->where('oid.outboundorderitemid', $row->outboundorderitemid)
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
}
