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

class PrintInvoiceController extends Controller
{
    public function printInvoice(Request $request)
    {
        $platform_order_ids = (array) $request->input('platform_order_ids', []);
        $action = $request->input('action', 'ViewInvoice');

        $settings = $request->input('settings', []);
        if (!is_array($settings))
            $settings = [];

        $settings = array_merge([
            'displayPrice' => 'FALSE',
            'signatureRequired' => 'FALSE',
            'testPrint' => false,
            'width' => 350,
        ], $settings);

        if (!in_array($action, ['PrintInvoice', 'ViewInvoice'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid action'], 422);
        }

        $results = [];

        foreach ($platform_order_ids as $platform_order_id) {

            $order = DB::table('tbloutboundorders')
                ->where('platform_order_id', $platform_order_id)
                ->first();

            if (!$order) {
                $results[] = [
                    'order_id' => $platform_order_id,
                    'error' => 'Order not found in tbloutboundorders',
                ];
                continue;
            }

            $items = DB::table('tbloutboundordersitem')
                ->where('platform_order_id', $platform_order_id)
                ->get();

            // latest label header (like old getUser/getinvoicenumberid/getLCode)
            $label = DB::table('tbllabelhistory')
                ->where('AmazonOrderId', $platform_order_id)
                ->orderByDesc('id')
                ->first();

            // item-level label info (delivery experience + est delivery + shipDate)
            $labelItems = DB::table('tbllabelhistoryitems')
                ->where('AmazonOrderId', $platform_order_id)
                ->orderByDesc('id')
                ->get()
                ->keyBy('orderitemid'); // key by platform_order_item_id

            $orderData = (array) $order;
            $orderData['items'] = json_decode(json_encode($items), true);

            // attach meta that your HTML needs (replaces old globals: $user, $LCode, etc.)
            $orderData['meta'] = [
                'user' => $label->user ?? '',
                'invoice' => $label->invoicenumberid ?? '',
                'LCode' => isset($label->labelprice) ? (string) $label->labelprice : '00.00',
                'ShipDate' => $label->ShipDate ?? null,
            ];

            // attach per-item label meta (delivery experience & dates)
            foreach ($orderData['items'] as &$it) {
                $oid = $it['platform_order_item_id'] ?? null;
                $li = $oid ? ($labelItems[$oid] ?? null) : null;

                $it['_label'] = [
                    'shipDate' => $li->shipDate ?? null,
                    'EarliestEstimatedDeliveryDate' => $li->EarliestEstimatedDeliveryDate ?? null,
                    'LatestEstimatedDeliveryDate' => $li->LatestEstimatedDeliveryDate ?? null,
                    'DeliveryExperience' => $li->DeliveryExperience ?? null,
                ];
            }
            unset($it);

            $html = $this->generateHtml($settings, $orderData, $action);

            $pdfFile = public_path("images/FBM_docs/invoices/invoice_{$platform_order_id}.pdf");
            $this->generatePDF($html, $pdfFile, $settings);

            $zplCode = $this->convertPDFToZPL($pdfFile, $platform_order_id, $settings);
            $pdfUrl = asset("images/FBM_docs/invoices/invoice_{$platform_order_id}.pdf");

            if ($action === 'PrintInvoice') {
                $this->sendToPrinter($zplCode);
            }

            $results[] = [
                'order_id' => $platform_order_id,
                'zpl_preview' => $action === 'ViewInvoice' ? $zplCode : null,
                'pdf_url' => $pdfUrl,
            ];
        }

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }


protected function generateHtml($settings, $orderData, $action)
{
    $user    = $orderData['meta']['user'] ?? '';
    $LCode   = $orderData['meta']['LCode'] ?? '00.00';
    $invoice = $orderData['meta']['invoice'] ?? '';

    $items = $orderData['items'] ?? [];
    $itemCount = count($items);

    $generator = new BarcodeGeneratorPNG();

    // Safer defaults
    $platformOrderId = (string)($orderData["platform_order_id"] ?? '');
    $AddressLine1 = (string)($orderData["address_line1"] ?? '');
    $city = (string)($orderData["city"] ?? '');
    $stateOrRegion = (string)($orderData["StateOrRegion"] ?? '');
    $postalCode = (string)($orderData["postal_code"] ?? '');
    $countryCode = (string)($orderData["CountryCode"] ?? '');

    // Split address (same intent as old script)
    $cityPosition = ($city !== '') ? strpos($AddressLine1, $city) : false;
    $address1 = ($cityPosition !== false) ? trim(substr($AddressLine1, 0, $cityPosition)) : $AddressLine1;

    if ($cityPosition !== false) {
        $address2 = trim(substr($AddressLine1, $cityPosition));
    } else {
        $address2 = trim($city . ', ' . $stateOrRegion . ' ' . $postalCode . ', ' . $countryCode);
    }

    // Dates
    $newdate = "";
    if (!empty($orderData["PurchaseDate"])) {
        $newdate = Carbon::parse($orderData["PurchaseDate"])->format('D, M j, Y');
    }

    $EarliestDelivery = '';
    $LatestDelivery   = '';

    if (!empty($orderData["EarliestDeliveryDate"])) {
        $EarliestDelivery = Carbon::parse($orderData["EarliestDeliveryDate"])->format('D, M j, Y');
    }
    if (!empty($orderData["LatestDeliveryDate"])) {
        $LatestDelivery = Carbon::parse($orderData["LatestDeliveryDate"])->format('D, M j, Y');
    }

    // Invoice display (prefer meta, fallback helper)
    $invoiceDisplay = $invoice ?: getinvoicenumberid($platformOrderId);

    // Barcode sizing like old script (better scan reliability)
    $widthFactor = 3;
    $totalHeight = 60;
    $barcode_AmazonOrderId = $generator->getBarcode(
        preg_replace('/\s+/', '', $platformOrderId),
        $generator::TYPE_CODE_128,
        $widthFactor,
        $totalHeight
    );

    $shipToName = $orderData["ship_to_name"] ?? $orderData["BuyerName"] ?? '';

    $html  = '<!DOCTYPE html><html lang="en"><head>';
    $html .= '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    $html .= '<title>Invoice</title>';
    $html .= '<style>
        body{font-family:Arial,sans-serif;margin:0;padding:0;display:flex;justify-content:center;align-items:center;height:100vh;font-size:20px;}
        .container{width:100%;max-width:9in;padding:0.5in;box-sizing:border-box;}
        .header-and-right{display:flex;justify-content:space-between;align-items:flex-start;gap:0px;}
        .header,.footer{margin-bottom:0.2in;}
        .header h1,.header h2,.header p{margin:0;padding:0;}
        .divider{border-top:1px solid #000;margin:0.2in 0;}
        .shipping-info,.order-info,.product-table{margin-bottom:0.2in;}
        .product-table table{width:100%;border-collapse:collapse;}
        .product-table th,.product-table td{border:1px solid #000;padding:10px 10px;text-align:left;}
        .product-table th{background-color:#f2f2f2;}
        .text-right{text-align:right;}
        .subtotal,.tax,.total{font-weight:bold;}
        .subtotal,.tax{border-top:1px solid #000;}
        .total{border-top:2px solid #000;}
        .subtotal-table{width:100%;text-align:right;border:none;}
        .subtotal-table td{padding:0;border:none;}
        .top-right{text-align:left;max-width:295px;}
        .no-border-table{border-collapse:collapse;border:none;width:100%;}
        .no-border-table td{border:none;}
    </style>';
    $html .= '</head><body><div class="container">';

    // ---- MULTI ITEM BRANCH ----
    if ($itemCount > 1) {
        // Items table FIRST (matches your existing structure)
        $html .= '<div class="product-table">';
        $html .= '<p style="text-align:right;">' . htmlspecialchars($invoiceDisplay) . '</p>';
        $html .= '<table>';

        foreach ($items as $item) {
            $qty = $item['QuantityOrdered'] ?? ($item['QuantityShipped'] ?? 1);

            $html .= '<tr><td>Quantity</td><td>' . htmlspecialchars((string)$qty) . '</td></tr>';
            $html .= '<tr><td>Product Details</td><td>';

            $title = $item["platform_title"] ?? '';
            $sku   = $item["platform_sku"] ?? '';
            $asin  = $item["platform_asin"] ?? '';
            $orderItemId = $item["platform_order_item_id"] ?? '';

            $html .= htmlspecialchars($title) . '<br>';
            $html .= '<strong>SKU:</strong> ' . htmlspecialchars($sku) . '<br>';
            $html .= '<strong>ASIN:</strong> ' . htmlspecialchars($asin) . '<br>';

            // ASIN barcode (safe)
            $cleanAsin = preg_replace('/[^A-Za-z0-9]/', '', (string)$asin);
            if ($cleanAsin !== '') {
                try {
                    $barcode_ASIN = $generator->getBarcode($cleanAsin, $generator::TYPE_CODE_128);
                    $html .= '<img src="data:image/png;base64,' . base64_encode($barcode_ASIN) . '" alt="ASIN Barcode" style="height:40px;"><br>';
                } catch (\Throwable $e) {
                    $html .= '<em>Barcode generation failed.</em><br>';
                }
            } else {
                $html .= '<em>Invalid ASIN for barcode</em><br>';
            }

            $condId = $item["ConditionId"] ?? '';
            $condSub = $item["ConditionSubtypeId"] ?? '';
            $html .= '<strong>Condition:</strong> ' . htmlspecialchars($condId . ' - ' . $condSub) . '<br>';
            $html .= '<strong>Order Item ID:</strong> ' . htmlspecialchars((string)$orderItemId) . '<br>';

            $p = $item["unit_price"] ?? 0.00;
            $s = $item["shippingPrice"] ?? 0.00;

            $html .= '<strong>P Code:</strong> $' . htmlspecialchars(convertNumberToCustomCode($p)) . '<br>';
            $html .= '<strong>S Code:</strong> $' . htmlspecialchars(convertNumberToCustomCode($s)) . '<br>';
            $html .= '<strong>L Code:</strong> $' . htmlspecialchars(convertNumberToCustomCode($LCode)) . '';

            $html .= '</td></tr>';

            // Note (order-level)
            $note = fetchNote($platformOrderId);
            if (!empty($note) && strtolower(trim($note)) !== 'n/a') {
                $html .= '<tr><td>Note:</td><td>' . htmlspecialchars($note) . '</td></tr>';
            }

            if (($settings['displayPrice'] ?? 'FALSE') === 'TRUE') {
                $unitTax = $item["unit_tax"] ?? 0.00;

                $html .= '<tr><td style="width:80px;">Order Total</td><td class="text-right" style="width:185px;">';
                $html .= '<table class="subtotal-table">';
                $html .= '<tr style="font-size:10;height:30px;"><td><strong>Item Price</strong></td><td>$' . htmlspecialchars(number_format((float)$p, 2)) . '</td></tr>';
                $html .= '<tr style="font-size:10;height:30px;"><td><strong>Item Tax</strong></td><td>$' . htmlspecialchars(number_format((float)$unitTax, 2)) . '</td></tr>';
                $html .= '<tr style="font-size:10;border-bottom:1px solid gray;height:30px;"><td><strong>Shipping Price</strong></td><td>$' . htmlspecialchars(number_format((float)$s, 2)) . '</td></tr>';
                $html .= '</table></td></tr>';
            }
        }

        $html .= '</table></div>';

        // Header page
        $html .= '<div class="header-and-right" style="page-break-before: always;">';
        $html .= '<br><br><div class="header">';
        $html .= '<p style="text-align:right;">' . htmlspecialchars($invoiceDisplay) . '</p>';
        $html .= '<h1>Ship To:</h1>';
        $html .= '<h2>' . htmlspecialchars($shipToName) . '</h2>';
        $html .= '<h2>' . htmlspecialchars($address1) . '<br>' . htmlspecialchars($address2) . '</h2>';
        $html .= '</div><div class="top-right">';

        foreach ($items as $item) {
            $asin = $item["platform_asin"] ?? '';
            $internal = $asin ? getInternalByASIN($asin) : '';
            $title = $item["platform_title"] ?? '';

            if ($internal !== '') $html .= '<strong>' . htmlspecialchars($internal) . '</strong><br>';
            if ($title !== '') $html .= '<strong>' . htmlspecialchars($title) . '</strong><br>';
        }

        $html .= '</div></div>';
        $html .= '<div class="divider"></div>';

        // Order info
        $storeName = $items[0]['storename'] ?? ($orderData['storename'] ?? '');
        $html .= '<div class="order-info">';
        $html .= '<strong>Order ID: ' . htmlspecialchars($platformOrderId) . '</strong><br>';
        $html .= '<img style="background:#fff;padding:10px;display:block" src="data:image/png;base64,' . base64_encode($barcode_AmazonOrderId) . '" alt="Amazon Order Barcode">';
        $html .= '<p>Thank you for buying from ' . htmlspecialchars($storeName) . ' on Amazon Marketplace.</p>';
        $html .= '</div>';

        // Shipping table
        $html .= '<div class="shipping-info"><table>';
        $html .= '<tr><td><strong>Billing Address:</strong></td><td style="width:135px;">Order Date:</td><td>' . htmlspecialchars($newdate) . '</td></tr>';

        $buyerName = $orderData["BuyerName"] ?? '';
        $html .= '<tr><td>' . htmlspecialchars($buyerName) . '</td><td style="width:135px;">Ship by Date:</td><td>';

        // Prefer order-level LatestShipDate if present; otherwise use helper
        $shipByRaw = $orderData['LatestShipDate'] ?? null;
        if (!$shipByRaw) {
            $firstItemId = $items[0]['platform_order_item_id'] ?? null;
            if ($firstItemId) $shipByRaw = getLatestShipDate($platformOrderId);
        }
        if ($shipByRaw) {
            $dt = new DateTime($shipByRaw);
            $html .= htmlspecialchars($dt->format('D, F j, Y'));
        }

        $html .= '</td></tr>';

        // Ship Date (prefer per-item label shipDate if attached)
        $html .= '<tr><td>' . htmlspecialchars($address1) . '</td><td style="width:135px;">Ship Date:</td><td>';

        $shipDateRaw = $items[0]['_label']['shipDate'] ?? null;
        if (!$shipDateRaw) {
            $firstItemId = $items[0]['platform_order_item_id'] ?? null;
            if ($firstItemId) $shipDateRaw = getShipDate($platformOrderId, $firstItemId);
        }
        if ($shipDateRaw) {
            $dt = new DateTime($shipDateRaw, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
            $html .= htmlspecialchars($dt->format('D, F j, Y'));
        }

        $html .= '</td></tr>';

        $html .= '<tr><td>' . htmlspecialchars($address2) . '</td><td>Deliver by Date:</td><td>' .
            htmlspecialchars($EarliestDelivery) . ' - ' . htmlspecialchars($LatestDelivery) .
            '</td></tr>';

        $shipService = $orderData["ShipmentServiceLevelCategory"] ?? '';
        $html .= '<tr><td></td><td>Shipping Service:</td><td>' . htmlspecialchars($shipService) . '</td></tr>';
        $html .= '<tr><td></td><td>Seller Name:</td><td>' . htmlspecialchars($storeName) . '</td></tr>';
        $html .= '</table>';

        // DeliveryExperience (prefer attached label meta)
        $deliveryExperience = $items[0]['_label']['DeliveryExperience'] ?? '';
        if ($deliveryExperience === '') {
            $deliveryExperience = DeliveryExperience($platformOrderId);
        }

        if (
            strtoupper($settings['signatureRequired'] ?? 'FALSE') === "TRUE" ||
            in_array($deliveryExperience, ['DeliveryConfirmationWithSignature', 'DeliveryConfirmationWithAdultSignature'], true)
        ) {
            $html .= '<div style="text-align:center;"><strong>Confirmation Services: Signature confirmation</strong></div>';
        }

        $html .= '</div>'; // end shipping-info

        $html .= '<div style="text-align:right;transform:translate(-50px,0px);font-size:16px;color:#555;">- ' .
            htmlspecialchars($user ?: ' ') . '</div>';
    }

    // ---- SINGLE ITEM BRANCH ----
    else {
        $html .= '<div class="header-and-right">';
        $html .= '<div class="header">';
        $html .= '<p style="text-align:right;">' . htmlspecialchars($invoiceDisplay) . '</p>';
        $html .= '<h1>Ship To:</h1>';
        $html .= '<h2>' . htmlspecialchars($shipToName) . '</h2>';
        $html .= '<h2>' . htmlspecialchars($address1) . '<br>' . htmlspecialchars($address2) . '</h2>';
        $html .= '</div><div class="top-right">';

        foreach ($items as $item) {
            $asin = $item["platform_asin"] ?? '';
            $internal = $asin ? getInternalByASIN($asin) : '';
            $title = $item["platform_title"] ?? '';

            if ($internal !== '') $html .= '<strong>' . htmlspecialchars($internal) . '</strong><br>';
            if ($title !== '') $html .= '<strong>' . htmlspecialchars($title) . '</strong><br>';
        }

        $html .= '</div></div>';
        $html .= '<div class="divider"></div>';

        $storeName = $items[0]['storename'] ?? ($orderData['storename'] ?? '');
        $html .= '<div class="order-info">';
        $html .= '<strong>Order ID: ' . htmlspecialchars($platformOrderId) . '</strong><br>';
        $html .= '<img style="background:#fff;padding:10px;display:block" src="data:image/png;base64,' . base64_encode($barcode_AmazonOrderId) . '" alt="Amazon Order Barcode">';
        $html .= '<p>Thank you for buying from ' . htmlspecialchars($storeName) . ' on Amazon Marketplace.</p>';
        $html .= '</div>';

        $html .= '<div class="shipping-info"><table>';
        $html .= '<tr><td><strong>Billing Address:</strong></td><td style="width:135px;">Order Date:</td><td>' . htmlspecialchars($newdate) . '</td></tr>';

        $buyerName = $orderData["BuyerName"] ?? '';
        $html .= '<tr><td>' . htmlspecialchars($buyerName) . '</td><td style="width:135px;">Ship by Date:</td><td>';

        $shipByRaw = $orderData['LatestShipDate'] ?? null;
        if (!$shipByRaw) {
            $firstItemId = $items[0]['platform_order_item_id'] ?? null;
            if ($firstItemId) $shipByRaw = getLatestShipDate($platformOrderId, $firstItemId);
        }
        if ($shipByRaw) {
            $dt = new DateTime($shipByRaw);
            $html .= htmlspecialchars($dt->format('D, F j, Y'));
        }
        $html .= '</td></tr>';

        $html .= '<tr><td>' . htmlspecialchars($address1) . '</td><td style="width:135px;">Ship Date:</td><td>';

        $shipDateRaw = $items[0]['_label']['shipDate'] ?? null;
        if (!$shipDateRaw) {
            $firstItemId = $items[0]['platform_order_item_id'] ?? null;
            if ($firstItemId) $shipDateRaw = getShipDate($platformOrderId, $firstItemId);
        }
        if ($shipDateRaw) {
            $dt = new DateTime($shipDateRaw, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
            $html .= htmlspecialchars($dt->format('D, F j, Y'));
        }
        $html .= '</td></tr>';

        $html .= '<tr><td>' . htmlspecialchars($address2) . '</td><td>Deliver by Date:</td><td>' .
            htmlspecialchars($EarliestDelivery) . ' - ' . htmlspecialchars($LatestDelivery) .
            '</td></tr>';

        $shipService = $orderData["ShipmentServiceLevelCategory"] ?? '';
        $html .= '<tr><td></td><td>Shipping Service:</td><td>' . htmlspecialchars($shipService) . '</td></tr>';
        $html .= '<tr><td></td><td>Seller Name:</td><td>' . htmlspecialchars($storeName) . '</td></tr>';
        $html .= '</table>';

        $deliveryExperience = $items[0]['_label']['DeliveryExperience'] ?? '';
        if ($deliveryExperience === '') {
            $deliveryExperience = DeliveryExperience($platformOrderId);
        }

        if (
            strtoupper($settings['signatureRequired'] ?? 'FALSE') === "TRUE" ||
            in_array($deliveryExperience, ['DeliveryConfirmationWithSignature', 'DeliveryConfirmationWithAdultSignature'], true)
        ) {
            $html .= '<div style="text-align:center;"><strong>Confirmation Services: Signature confirmation</strong></div>';
        }

        $html .= '</div>'; // end shipping-info

        $html .= '<div style="text-align:right;transform:translate(-50px,0px);font-size:16px;color:#555;">- ' .
            htmlspecialchars($user ?: ' ') . '</div>';

        // Product table (single item)
        $html .= '<div class="product-table"><table>';

        foreach ($items as $item) {
            $qty = $item['QuantityOrdered'] ?? ($item['QuantityShipped'] ?? 1);

            $html .= '<tr><td>Quantity</td><td>' . htmlspecialchars((string)$qty) . '</td></tr>';
            $html .= '<tr><td>Product Details</td><td>';

            $title = $item["platform_title"] ?? '';
            $sku   = $item["platform_sku"] ?? '';
            $asin  = $item["platform_asin"] ?? '';
            $orderItemId = $item["platform_order_item_id"] ?? '';

            $html .= htmlspecialchars($title) . '<br>';
            $html .= '<strong>SKU:</strong> ' . htmlspecialchars($sku) . '<br>';
            $html .= '<strong>ASIN:</strong> ' . htmlspecialchars($asin) . '<br>';

            $cleanAsin = preg_replace('/[^A-Za-z0-9]/', '', (string)$asin);
            if ($cleanAsin !== '') {
                try {
                    $barcode_ASIN = $generator->getBarcode($cleanAsin, $generator::TYPE_CODE_128);
                    $html .= '<img src="data:image/png;base64,' . base64_encode($barcode_ASIN) . '" alt="ASIN Barcode" style="height:40px;"><br>';
                } catch (\Throwable $e) {
                    $html .= '<em>Barcode generation failed.</em><br>';
                }
            } else {
                $html .= '<em>Invalid ASIN for barcode</em><br>';
            }

            $condId = $item["ConditionId"] ?? '';
            $condSub = $item["ConditionSubtypeId"] ?? '';
            $html .= '<strong>Condition:</strong> ' . htmlspecialchars($condId . ' - ' . $condSub) . '<br>';
            $html .= '<strong>Order Item ID:</strong> ' . htmlspecialchars((string)$orderItemId) . '<br>';

            $p = $item["unit_price"] ?? 0.00;
            $s = $item["shippingPrice"] ?? 0.00;

            $html .= '<strong>P Code:</strong> $' . htmlspecialchars(convertNumberToCustomCode($p)) . '<br>';
            $html .= '<strong>S Code:</strong> $' . htmlspecialchars(convertNumberToCustomCode($s)) . '<br>';
            $html .= '<strong>L Code:</strong> $' . htmlspecialchars(convertNumberToCustomCode($LCode)) . '';

            $html .= '</td></tr>';

            $note = fetchNote($platformOrderId);
            if (!empty($note) && strtolower(trim($note)) !== 'n/a') {
                $html .= '<tr><td>Note:</td><td>' . htmlspecialchars($note) . '</td></tr>';
            }

            if (($settings['displayPrice'] ?? 'FALSE') === 'TRUE') {
                $unitTax = $item["unit_tax"] ?? 0.00;

                $html .= '<tr><td style="width:80px;">Order Total</td><td class="text-right" style="width:185px;">';
                $html .= '<table class="subtotal-table">';
                $html .= '<tr style="font-size:10;height:30px;"><td><strong>Item Price</strong></td><td>$' . htmlspecialchars(number_format((float)$p, 2)) . '</td></tr>';
                $html .= '<tr style="font-size:10;height:30px;"><td><strong>Item Tax</strong></td><td>$' . htmlspecialchars(number_format((float)$unitTax, 2)) . '</td></tr>';
                $html .= '<tr style="font-size:10;border-bottom:1px solid gray;height:30px;"><td><strong>Shipping Price</strong></td><td>$' . htmlspecialchars(number_format((float)$s, 2)) . '</td></tr>';
                $html .= '</table></td></tr>';
            }
        }

        $html .= '</table></div>';

        $html .= '<div style="text-align:right;transform:translate(-50px,0px);font-size:16px;color:#555;">- ' .
            htmlspecialchars($user ?: ' ') . '</div>';
    }

    // IMPORTANT: close the document
    $html .= '</div></body></html>';

    return $html;
}


    protected function generatePDF($html, $pdfPath, $settings)
    {
        $width = $settings['width'] ?? 350; // 370

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => [230, $width],
            'margin_left' => 1,
            'margin_right' => 1,
            'margin_top' => 0,
            'margin_bottom' => 1,
            'margin_header' => 1,
            'margin_footer' => 1
        ]);

        $mpdf->WriteHTML(trim($html));
        $mpdf->Output($pdfPath, 'F');
    }

    public static function convertImageToZPL($testPrint, $imagePath, $maxWidth = 1250, $maxHeight = 1100, $bottomRightNumber = "0313")
    {
        $originalImg = imagecreatefrompng($imagePath);
        $origWidth = imagesx($originalImg);
        $origHeight = imagesy($originalImg);

        $aspectRatio = $origWidth / $origHeight;
        if ($origWidth > $origHeight) {
            $newWidth = $maxWidth;
            $newHeight = $maxWidth / $aspectRatio;
        } else {
            $newHeight = $maxHeight;
            $newWidth = $maxHeight * $aspectRatio;
        }

        $newWidth = min($newWidth, $maxWidth);
        $newHeight = min($newHeight, $maxHeight);

        $resizedImg = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resizedImg, $originalImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        $paddedWidth = ceil($newWidth / 8) * 8;
        $bytesPerRow = $paddedWidth / 8;

        $binaryData = "";

        for ($y = 0; $y < $newHeight; $y++) {
            $rowBinary = "";
            for ($x = 0; $x < $paddedWidth; $x++) {
                if ($x < $newWidth) {
                    if ($x >= imagesx($resizedImg) || $y >= imagesy($resizedImg)) {
                        $rowBinary .= "0"; // fallback safety padding
                    } else {
                        $colorIndex = imagecolorat($resizedImg, $x, $y);
                        $rgba = imagecolorsforindex($resizedImg, $colorIndex);
                        $gray = ($rgba['red'] + $rgba['green'] + $rgba['blue']) / 3;
                        $rowBinary .= ($gray < 128) ? "1" : "0";
                    }
                } else {
                    $rowBinary .= "0";
                }
            }

            for ($i = 0; $i < strlen($rowBinary); $i += 8) {
                $byte = substr($rowBinary, $i, 8);
                $binaryData .= str_pad(dechex(bindec($byte)), 2, "0", STR_PAD_LEFT);
            }
        }

        $totalBytes = strlen($binaryData) / 2;

        $zpl = "^XA\n";
        $zpl .= "^FO50,50\n";
        $zpl .= "^GFA,$totalBytes,$totalBytes,$bytesPerRow," . strtoupper($binaryData) . "\n";

        if ($testPrint) {
            $labelWidth = 1200;
            $labelHeight = 1800;

            $fontSize = 100;
            $charWidth = 100;
            $textLength = strlen("Please Dispose all of same data") * ($charWidth / 2);
            $textX = ($labelWidth - $textLength) / 2;
            $textY = $newHeight - 200;

            $zpl .= "^FO{$textX},{$textY}^A0N,{$fontSize},{$charWidth}^FDPlease Dispose all of same data^FS\n";
        }

        $zpl .= "^XZ\n";

        return $zpl;
    }

    protected function convertPDFToZPL($pdfPath, $orderId, $settings)
    {
        $testPrint = $settings['testPrint'] ?? false;
        $imagick = new \Imagick();
        $imagick->setResolution(300, 300);
        $imagick->readImage($pdfPath . '[0-10]');
        $imagick->setImageFormat('png');

        Log::info('Page count: ' . $imagick->getNumberImages());
        if (!file_exists($pdfPath)) {
            Log::error("PDF file does not exist: $pdfPath");
        }

        $zplCode = "";
        for ($i = 0; $i < $imagick->getNumberImages(); $i++) {
            $imagick->setIteratorIndex($i);
            $img = $imagick->getImage();

            $img->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $img->setBackgroundColor(new \ImagickPixel('white'));
            $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $img->setImageFormat('png');

            $imagePath = public_path("images/FBM_docs/invoices/invoice_{$orderId}_page{$i}.png");
            $img->writeImage($imagePath);

            $zplCode .= $this->convertImageToZPL($testPrint, $imagePath) . "\n";

            $img->clear();
            $img->destroy();
        }

        $imagick->clear();
        $imagick->destroy();

        return $zplCode;
    }

    protected function sendToPrinter($zplCode, $pdfFile = null, $savetoprintserver = false)
    {
        $printerIP = 'http://99.0.87.190:1450/ims/Admin/modules/PRD-RPN-PCN/print.php';
        $pIp = '192.168.1.240';

        try {
            $postData = [
                'zpl' => $zplCode,
                'printerSelect' => $pIp,
            ];

            Log::info('Printer request debug', [
                'url' => $printerIP,
                'printerSelect' => $pIp,
                'zpl_len' => strlen($zplCode),
            ]);

            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->withBody(http_build_query($postData), 'application/x-www-form-urlencoded')
                ->post($printerIP);

            Log::info('Printer response debug', [
                'status' => $response->status(),
                'ok' => $response->successful(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Printer exception', [
                'message' => $e->getMessage(),
            ]);
        }
    }


}