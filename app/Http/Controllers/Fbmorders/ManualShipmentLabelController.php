<?php

namespace App\Http\Controllers\Fbmorders;

use Mpdf\Mpdf;
use Imagick;
use ImagickPixel;
use Illuminate\Support\Facades\Storage;
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
use DateTime;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Facades\Http;

require base_path('app/Helpers/print_helpers.php');

class ManualShipmentLabelController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'AmazonOrderId' => 'required|string',
            'OrderItemIds' => 'required|array|min:1',
            'OrderItemIds.*' => 'required|string',
            'LCode' => 'required|numeric|min:0',
            'ShipDate' => 'required|date',
            'TrackingNumber' => 'required|string',
            'Carrier' => 'required|string',
            'DeliveryExperience' => 'required|string',
            'shippinglabelpdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        $AmazonOrderId = $request->AmazonOrderId;
        $orderItemIds = $request->OrderItemIds;
        $LCode = $request->LCode;
        $user = session('user_name', 'system');

        // Save the PDF
        $pdfFile = $request->file('shippinglabelpdf');
        $fileName = 'amzn_manual_' . $AmazonOrderId . '.pdf';
        $destination = public_path('images/FBM_docs/manual_shipping_label/');
        $pdfFile->move($destination, $fileName);

        // Get next invoice number
        $maxInvoice = DB::table('tbllabelhistory')->max('invoicenumberid');
        $nextInvoiceId = is_null($maxInvoice) ? 1 : $maxInvoice + 1;

        // Insert to tbllabelhistory
        $labelHistoryId = DB::table('tbllabelhistory')->insertGetId([
            'shipmentid' => 'Manual',
            'AmazonOrderId' => $AmazonOrderId,
            'status' => 'Purchased',
            'trackingid' => $request->TrackingNumber,
            'ShippingServiceId' => 'Manual',
            'createdDate' => now(),
            'updatedDate' => now(),
            'user' => $user,
            'invoicenumberid' => $nextInvoiceId,
            'scanned_status' => false,
            'insert_log' => 'manual',
            'labelprice' => $LCode,
        ]);

        // Insert into tbllabelhistoryitems and update tbloutboundordersitem
        foreach ($orderItemIds as $orderItemId) {
            DB::table('tbllabelhistoryitems')->insert([
                'shipmentid' => 'Manual',
                'AmazonOrderId' => $AmazonOrderId,
                'orderitemid' => $orderItemId,
                'trackingid' => $request->TrackingNumber,
                'shipDate' => $request->ShipDate,
                'EarliestEstimatedDeliveryDate' => null,
                'LatestEstimatedDeliveryDate' => null,
                'labelhistory_id' => $labelHistoryId,
                'PNGLabel' => null,
                'PDFLabel' => 'Manual',
                'hasher' => null,
                'labelprice' => $LCode,
                'DeliveryExperience' => $request->DeliveryExperience,
            ]);

            // Update outbound item
            DB::table('tbloutboundordersitem')
                ->where('platform_order_item_id', $orderItemId)
                ->update([
                    'trackingnumber' => $request->TrackingNumber,
                    'carrier' => $request->Carrier,
                    'carrier_description' => $request->DeliveryExperience,
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Carrier description added.'
        ]);
    }

    public function newCarrierDescription(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $newOption = $request->input('name');

        // Get existing record or create new
        $record = DB::table('tbladditionaldetails')
            ->where('name', 'carrierdescription')
            ->where('operational_status', 'operational')
            ->first();

        if ($record) {
            $options = json_decode($record->value, true) ?? [];

            if (!in_array($newOption, $options)) {
                $options[] = $newOption;

                DB::table('tbladditionaldetails')
                    ->where('id', $record->id)
                    ->update([
                        'value' => json_encode($options),
                        'updated_at' => now()
                    ]);
            }
        } else {
            DB::table('tbladditionaldetails')->insert([
                'name' => 'carrierdescription',
                'operational_status' => 'operational',
                'value' => json_encode([$newOption]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function getCarrierDescriptions()
    {
        $record = DB::table('tbladditionaldetails')
            ->where('name', 'carrierdescription')
            ->where('operational_status', 'operational')
            ->first();

        $options = [];

        if ($record && $record->value) {
            $decoded = json_decode($record->value, true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }

        return response()->json([
            'success' => true,
            'options' => $options
        ]);
    }

    public function import(Request $request)
    {
        $payload = $request->validate([
            'amazon_order_id' => ['required', 'string'],
            'data' => ['required', 'array'],
            'data.tblshiphistory' => ['required', 'array', 'min:1'],
            'data.tbllabelhistory' => ['nullable', 'array'],
            'data.tbllabelhistoryitems' => ['nullable', 'array'],
            'source' => ['nullable', 'string'],
            'request_id' => ['nullable', 'string'],
        ]);

        $amazonOrderId = $payload['amazon_order_id'];
        $shipRows = $payload['data']['tblshiphistory'] ?? [];
        $labelRows = $payload['data']['tbllabelhistory'] ?? [];
        $labelItemRows = $payload['data']['tbllabelhistoryitems'] ?? [];

        return DB::transaction(function () use ($amazonOrderId, $shipRows, $labelRows, $labelItemRows) {

            // -------------------------
            // A) UPSERT tbloutboundorders (order header)
            // -------------------------
            $first = $shipRows[0];

            // map from v1 shiphistory to v2 order fields
            $orderInsert = [
                'platform' => 'Amazon',
                'storename' => $first['storename'] ?? ($first['StoreName'] ?? 'Unknown'),
                'platform_order_id' => $amazonOrderId,

                // address / buyer (fill if present in v1 row)
                'address_line1' => $first['address_line1'] ?? ($first['AddressLine1'] ?? null),
                'address_line2' => $first['address_line2'] ?? ($first['AddressLine2'] ?? null),
                'AddressLine3' => $first['AddressLine3'] ?? null,
                'StateOrRegion' => $first['StateOrRegion'] ?? null,
                'postal_code' => $first['postal_code'] ?? ($first['PostalCode'] ?? null),
                'city' => $first['city'] ?? ($first['City'] ?? null),
                'CountryCode' => $first['CountryCode'] ?? null,

                'PaymentMethod' => $first['PaymentMethod'] ?? null,
                'BuyerCompanyName' => $first['BuyerCompanyName'] ?? null,
                'BuyerName' => $first['BuyerName'] ?? null,
                'BuyerEmail' => $first['BuyerEmail'] ?? null,
                'BuyerPhoneNumber' => $first['BuyerPhoneNumber'] ?? null,

                'PurchaseDate' => $first['PurchaseDate'] ?? now(), // make sure v1 has it
                'ship_date' => $first['ship_date'] ?? null,
                'delivery_date' => $first['delivery_date'] ?? null,

                'EarliestShipDate' => $first['EarliestShipDate'] ?? null,
                'LatestShipDate' => $first['LatestShipDate'] ?? null,
                'EarliestDeliveryDate' => $first['EarliestDeliveryDate'] ?? null,
                'LatestDeliveryDate' => $first['LatestDeliveryDate'] ?? null,

                'ShipmentServiceLevelCategory' => $first['ShipmentServiceLevelCategory'] ?? null,
                'OrderType' => $first['OrderType'] ?? null,
                'ordernote' => $first['ordernote'] ?? null,
                'IsReplacementOrder' => $first['IsReplacementOrder'] ?? null,

                'FulfillmentChannel' => $first['FulfillmentChannel'] ?? 'FBM',
                'NumberOfItemsShipped' => $first['NumberOfItemsShipped'] ?? null,
                'NumberOfItemsUnshipped' => $first['NumberOfItemsUnshipped'] ?? null,
                'ShiptoName' => $first['ShiptoName'] ?? null,
            ];

            // Check if order exists
            $orderExists = DB::table('tbloutboundorders')
                ->where('platform_order_id', $amazonOrderId)
                ->exists();

            if (!$orderExists) {
                DB::table('tbloutboundorders')->insert($orderInsert);
            } else {
                // Only fill missing header fields (DO NOT overwrite good data)
                $existing = DB::table('tbloutboundorders')
                    ->where('platform_order_id', $amazonOrderId)
                    ->first();

                $orderUpdate = [];
                foreach ($orderInsert as $k => $v) {
                    if (($existing->$k ?? null) === null && $v !== null) {
                        $orderUpdate[$k] = $v;
                    }
                }
                if (!empty($orderUpdate)) {
                    DB::table('tbloutboundorders')
                        ->where('platform_order_id', $amazonOrderId)
                        ->update($orderUpdate);
                }
            }

            // -------------------------
            // B) UPSERT tbloutboundordersitem (per item)
            // rule: if exists -> update ONLY tracking fields
            // -------------------------
            $itemResults = [];

            foreach ($shipRows as $r) {
                $orderItemId = $r['OrderItemId'] ?? $r['orderitemid'] ?? $r['platform_order_item_id'] ?? null;
                if (!$orderItemId) {
                    $itemResults[] = ['ok' => false, 'reason' => 'missing OrderItemId'];
                    continue;
                }

                $itemKey = [
                    'platform_order_id' => $amazonOrderId,
                    'platform_order_item_id' => (string) $orderItemId,
                ];

                $itemInsert = array_merge($itemKey, [
                    'platform' => 'Amazon',
                    'storename' => $r['storename'] ?? ($r['StoreName'] ?? 'Unknown'),

                    'platform_sku' => $r['SellerSKU'] ?? ($r['platform_sku'] ?? null),
                    'platform_asin' => $r['ASIN'] ?? ($r['platform_asin'] ?? null),
                    'platform_title' => $r['Title'] ?? ($r['platform_title'] ?? null),

                    'ConditionSubtypeId' => $r['ConditionSubtypeId'] ?? null,
                    'ConditionId' => $r['ConditionId'] ?? null,

                    'NumberOfItemsShipped' => $r['NumberOfItemsShipped'] ?? null,
                    'NumberOfItemsUnshipped' => $r['NumberOfItemsUnshipped'] ?? null,
                    'FulfillmentChannel' => $r['FulfillmentChannel'] ?? 'FBM',
                    'order_status' => $r['order_status'] ?? 'Pending',

                    'QuantityOrdered' => $r['QuantityOrdered'] ?? null,
                    'QuantityShipped' => $r['QuantityShipped'] ?? null,

                    // tracking fields (also used on insert)
                    'trackingnumber' => $r['trackingnumber'] ?? ($r['TrackingNumber'] ?? null),
                    'trackingstatus' => $r['trackingstatus'] ?? ($r['TrackingStatus'] ?? null),
                    'carrier' => $r['carrier'] ?? null,
                    'carrier_description' => $r['carrier_description'] ?? null,

                    'unit_price' => $r['unit_price'] ?? 0.00,
                    'unit_tax' => $r['unit_tax'] ?? 0.00,
                    'shippingPrice' => $r['shippingPrice'] ?? 0.00,
                ]);

                $existingItem = DB::table('tbloutboundordersitem')
                    ->where($itemKey)
                    ->first();

                if (!$existingItem) {
                    DB::table('tbloutboundordersitem')->insert($itemInsert);
                    $itemResults[] = ['ok' => true, 'action' => 'inserted', 'orderitemid' => (string) $orderItemId];
                } else {
                    // ONLY update tracking fields
                    $trackingUpdate = [
                        'trackingnumber' => $itemInsert['trackingnumber'],
                        'trackingstatus' => $itemInsert['trackingstatus'],
                        'carrier' => $itemInsert['carrier'],
                        'carrier_description' => $itemInsert['carrier_description'],
                    ];

                    // avoid overwriting with nulls
                    $trackingUpdate = array_filter($trackingUpdate, fn($v) => $v !== null);

                    if (!empty($trackingUpdate)) {
                        DB::table('tbloutboundordersitem')
                            ->where($itemKey)
                            ->update($trackingUpdate);
                        $itemResults[] = ['ok' => true, 'action' => 'tracking_updated', 'orderitemid' => (string) $orderItemId];
                    } else {
                        $itemResults[] = ['ok' => true, 'action' => 'no_tracking_changes', 'orderitemid' => (string) $orderItemId];
                    }
                }
            }

            // -------------------------
            // C) Insert tbllabelhistory (dedupe by AmazonOrderId + trackingid)
            // -------------------------
            $labelResults = [];
            foreach ($labelRows as $lr) {
                $trackingid = $lr['trackingid'] ?? null;

                $exists = false;
                if ($trackingid) {
                    $exists = DB::table('tbllabelhistory')
                        ->where('AmazonOrderId', $amazonOrderId)
                        ->where('trackingid', $trackingid)
                        ->exists();
                }

                if ($exists) {
                    $labelResults[] = ['ok' => true, 'action' => 'skipped_existing', 'trackingid' => $trackingid];
                    continue;
                }

                $insert = [
                    'shipmentid' => $lr['shipmentid'] ?? null,
                    'AmazonOrderId' => $amazonOrderId,
                    'status' => $lr['status'] ?? null,
                    'trackingid' => $trackingid,
                    'createdDate' => $lr['createdDate'] ?? now(),
                    'updatedDate' => $lr['updatedDate'] ?? null,
                    'ShippingServiceId' => $lr['ShippingServiceId'] ?? null,
                    'ShippingServiceOfferId' => $lr['ShippingServiceOfferId'] ?? null,
                    'labelprice' => $lr['labelprice'] ?? 0.00,
                    'user' => $lr['user'] ?? null,
                    'invoicenumberid' => $lr['invoicenumberid'] ?? null,
                    'scanned_status' => $lr['scanned_status'] ?? 'false',
                    'insert_log' => 'import_v1',
                    'trackingid_status' => $lr['trackingid_status'] ?? 'for_process',
                    'ShipDate' => $lr['ShipDate'] ?? null,
                ];

                $newId = DB::table('tbllabelhistory')->insertGetId($insert);
                $labelResults[] = ['ok' => true, 'action' => 'inserted', 'id' => $newId, 'trackingid' => $trackingid];
            }

            // -------------------------
            // D) Insert tbllabelhistoryitems (dedupe by AmazonOrderId + orderitemid + trackingid)
            // -------------------------
            $labelItemResults = [];
            foreach ($labelItemRows as $lir) {
                $orderItemId = $lir['orderitemid'] ?? null;
                $trackingid = $lir['trackingid'] ?? null;

                $exists = false;
                if ($orderItemId && $trackingid) {
                    $exists = DB::table('tbllabelhistoryitems')
                        ->where('AmazonOrderId', $amazonOrderId)
                        ->where('orderitemid', (string) $orderItemId)
                        ->where('trackingid', $trackingid)
                        ->exists();
                }

                if ($exists) {
                    $labelItemResults[] = ['ok' => true, 'action' => 'skipped_existing', 'orderitemid' => (string) $orderItemId];
                    continue;
                }

                // NOTE: labelhistory_id in v1 is NOT valid in v2.
                // You can optionally map it by looking up tbllabelhistory via trackingid.
                $labelhistoryId = null;
                if ($trackingid) {
                    $labelhistoryId = DB::table('tbllabelhistory')
                        ->where('AmazonOrderId', $amazonOrderId)
                        ->where('trackingid', $trackingid)
                        ->orderByDesc('id')
                        ->value('id');
                }

                DB::table('tbllabelhistoryitems')->insert([
                    'shipmentid' => $lir['shipmentid'] ?? null,
                    'AmazonOrderId' => $amazonOrderId,
                    'orderitemid' => $orderItemId ? (string) $orderItemId : null,
                    'trackingid' => $trackingid,
                    'shipDate' => $lir['shipDate'] ?? null,
                    'EarliestEstimatedDeliveryDate' => $lir['EarliestEstimatedDeliveryDate'] ?? null,
                    'LatestEstimatedDeliveryDate' => $lir['LatestEstimatedDeliveryDate'] ?? null,
                    'labelhistory_id' => $labelhistoryId,
                    'PNGLabel' => $lir['PNGLabel'] ?? null,
                    'PDFLabel' => $lir['PDFLabel'] ?? null,
                    'hasher' => $lir['hasher'] ?? null,
                    'labelprice' => $lir['labelprice'] ?? 0.00,
                    'DeliveryExperience' => $lir['DeliveryExperience'] ?? null,
                ]);

                $labelItemResults[] = ['ok' => true, 'action' => 'inserted', 'orderitemid' => (string) $orderItemId];
            }

            return response()->json([
                'ok' => true,
                'amazon_order_id' => $amazonOrderId,
                'results' => [
                    'items' => $itemResults,
                    'labelhistory' => $labelResults,
                    'labelhistoryitems' => $labelItemResults,
                ],
            ]);
        });
    }



}