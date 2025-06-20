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
                'shipment' => 'Manual',
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
                    'tracking_number' => $request->TrackingNumber,
                    'carrier' => $request->Carrier,
                    'carrier_description' => $request->DeliveryExperience,
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Manual label and items saved successfully.'
        ]);
    }

}