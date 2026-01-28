<?php

namespace App\Http\Controllers\Fbmorders;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;
use DateTime;

use Mpdf\Mpdf;
use Imagick;
use ImagickPixel;

use Picqer\Barcode\BarcodeGeneratorPNG;

require base_path('app/Helpers/print_helpers.php');
require_once app_path('Http/Controllers/printer/phpqrcode/qrlib.php');

class PrintInvoiceController extends Controller
{
    public function printInvoice(Request $request)
    {
        $ts = microtime(true);
        $mark = function ($label) use (&$ts) {
            Log::info("[PrintInvoice] {$label} +" . round((microtime(true) - $ts), 3) . "s");
        };
        $mark('start');

        $platform_order_ids = (array) $request->input('platform_order_ids', []);
        $action = $request->input('action', 'ViewInvoice');

        $settings = $request->input('settings', []);
        if (!is_array($settings))
            $settings = [];

        $settings = array_merge([
            'displayPrice' => 'FALSE',
            'signatureRequired' => 'FALSE',
            'testPrint' => false,
            'width' => 350, // mpdf height
        ], $settings);

        if (!in_array($action, ['PrintInvoice', 'ViewInvoice'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid action'], 422);
        }

        $results = [];

        foreach ($platform_order_ids as $platform_order_id) {
            $mark("{$platform_order_id} start");

            // ---------- Fetch order ----------
            $order = DB::table('tbloutboundorders')
                ->where('platform_order_id', $platform_order_id)
                ->first();
            $mark("{$platform_order_id} after order");

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
            $mark("{$platform_order_id} after items");

            // latest label header (like old getUser/getinvoicenumberid/getLCode)
            $label = DB::table('tbllabelhistory')
                ->where('AmazonOrderId', $platform_order_id)
                ->orderByDesc('id')
                ->first();
            $mark("{$platform_order_id} after label header");

            // item-level label info (delivery experience + est delivery + shipDate)
            $labelItems = DB::table('tbllabelhistoryitems')
                ->where('AmazonOrderId', $platform_order_id)
                ->orderByDesc('id')
                ->get()
                ->keyBy('orderitemid'); // key by platform_order_item_id
            $mark("{$platform_order_id} after label items");

            $orderData = (array) $order;
            $orderData['items'] = json_decode(json_encode($items), true);

            $orderData['meta'] = [
                'user' => $label->user ?? '',
                'invoice' => $label->invoicenumberid ?? '',
                'LCode' => isset($label->labelprice) ? (string) $label->labelprice : '00.00',
                'ShipDate' => $label->ShipDate ?? null,
            ];

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

            // ✅ NEW: temp file pipeline + cleanup (per order)
            $tmpDir = $this->makeTempDir("invoice_{$platform_order_id}_");

            try {
                $html = $this->generateHtml($settings, $orderData, $action);
                $mark("{$platform_order_id} after html");

                // temp PDF path
                $tmpPdf = $tmpDir . DIRECTORY_SEPARATOR . "invoice_{$platform_order_id}.pdf";

                $this->generatePDF($html, $tmpPdf, $settings);
                $mark("{$platform_order_id} after pdf(temp)");

                // ✅ NEW: multi-page PDF → images → ZPL (from temp PDF, temp images)
                $zplCode = $this->convertPDFToZPL($tmpPdf, $platform_order_id, $settings, $tmpDir);
                $mark("{$platform_order_id} after zpl(multipage)");

                // Keep a public PDF for viewing
                $publicPdf = public_path("images/FBM_docs/invoices/invoice_{$platform_order_id}.pdf");
                @mkdir(dirname($publicPdf), 0777, true);
                @copy($tmpPdf, $publicPdf);

                $pdfUrl = asset("images/FBM_docs/invoices/invoice_{$platform_order_id}.pdf");

                if ($action === 'PrintInvoice') {
                    $this->sendToPrinter($zplCode);
                    $mark("{$platform_order_id} after print");
                }

                $results[] = [
                    'order_id' => $platform_order_id,
                    'zpl_preview' => $action === 'ViewInvoice' ? $zplCode : null,
                    'pdf_url' => $pdfUrl,
                ];
            } finally {
                // ✅ NEW: cleanup temp folder (pdf + page images)
                $this->rrmdir($tmpDir);
            }
        }

        $mark('done');

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    // =========================================================
    // ✅ NEW: Temp folder helpers
    // =========================================================

    protected function makeTempDir(string $prefix = 'invoice_'): string
    {
        $dir = storage_path('app/tmp/' . $prefix . Str::random(12));
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    protected function rrmdir(string $dir): void
    {
        if (!is_dir($dir))
            return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    // =========================================================
    // ✅ NEW: Warehouse + Serial section (adjust query as needed)
    // =========================================================

    protected function addWarehouseDetails(string $amazonOrderId, array $items): array
    {
        $result = [];

        foreach ($items as $it) {
            $orderItemId = (string) ($it['OrderItemId'] ?? '');
            $asin = (string) ($it['ASIN'] ?? '');
            $msku = (string) ($it['MSKU'] ?? '');

            if ($orderItemId === '') {
                $result[] = ['OrderItemId' => '', 'ASIN' => $asin, 'MSKU' => $msku, 'pairs' => []];
                continue;
            }

            // 1) newest outbound row wins (same as your shipment code)
            $outboundorderitemid = DB::table('tbloutboundordersitem')
                ->where('platform_order_id', $amazonOrderId)
                ->where('platform_order_item_id', $orderItemId)
                ->orderByDesc('outboundorderitemid')
                ->value('outboundorderitemid');

            if (!$outboundorderitemid) {
                $result[] = ['OrderItemId' => $orderItemId, 'ASIN' => $asin, 'MSKU' => $msku, 'pairs' => []];
                continue;
            }

            // 2) get dispensed ProductIDs (these are the CHILD units)
            $productIds = DB::table('tblorderitemdispense')
                ->where('orderitemid', $outboundorderitemid)
                ->orderByDesc('id')
                ->limit(4)
                ->pluck('productid')
                ->filter()
                ->values()
                ->all();

            if (!count($productIds)) {
                $result[] = ['OrderItemId' => $orderItemId, 'ASIN' => $asin, 'MSKU' => $msku, 'pairs' => []];
                continue;
            }

            // 3) pull serial/location from tblproduct for those ProductIDs
            $rows = DB::table('tblproduct')
                ->select('warehouselocation', 'serialnumber')
                ->whereIn('ProductID', $productIds)
                ->whereNotNull('warehouselocation')->where('warehouselocation', '<>', '')
                ->whereNotNull('serialnumber')->where('serialnumber', '<>', '')
                ->get();

            $pairs = [];
            foreach ($rows as $r) {
                $pairs[] = [
                    'warehouselocation' => $r->warehouselocation ?? '',
                    'serialnumber' => $r->serialnumber ?? '',
                ];
            }

            $result[] = [
                'OrderItemId' => $orderItemId,
                'ASIN' => $asin,
                'MSKU' => $msku,
                'pairs' => $pairs,
            ];
        }

        return $result;
    }

    protected function findFnskuByMsku(string $msku, string $store = ''): string
    {
        $q = DB::table('tblfnsku')->select('FNSKU')->where('MSKU', $msku);

        // If your tblfnsku has store column(s), add them here:
        // if ($store !== '') $q->where('storename', $store);

        $row = $q->orderByDesc('FNSKUID')->first();
        return (string) ($row->FNSKU ?? '');
    }

    // =========================================================
    // HTML generation (with ✅ NEW QR + warehouse/serial section)
    // =========================================================


    protected function generateHtml($settings, $orderData, $action)
    {
        $user = $orderData['meta']['user'] ?? '';
        $LCode = $orderData['meta']['LCode'] ?? '00.00';
        $invoice = $orderData['meta']['invoice'] ?? '';

        $items = $orderData['items'] ?? [];
        $itemCount = count($items);

        $generator = new BarcodeGeneratorPNG();

        $platformOrderId = (string) ($orderData["platform_order_id"] ?? '');
        $AddressLine1 = (string) ($orderData["address_line1"] ?? '');
        $city = (string) ($orderData["city"] ?? '');
        $stateOrRegion = (string) ($orderData["StateOrRegion"] ?? '');
        $postalCode = (string) ($orderData["postal_code"] ?? '');
        $countryCode = (string) ($orderData["CountryCode"] ?? '');

        $cityPosition = ($city !== '') ? strpos($AddressLine1, $city) : false;
        $address1 = ($cityPosition !== false) ? trim(substr($AddressLine1, 0, $cityPosition)) : $AddressLine1;

        if ($cityPosition !== false) {
            $address2 = trim(substr($AddressLine1, $cityPosition));
        } else {
            $address2 = trim($city . ', ' . $stateOrRegion . ' ' . $postalCode . ', ' . $countryCode);
        }

        $newdate = "";
        if (!empty($orderData["PurchaseDate"])) {
            $newdate = Carbon::parse($orderData["PurchaseDate"])->format('D, M j, Y');
        }

        $EarliestDelivery = '';
        $LatestDelivery = '';

        $earliestRaw = $items[0]['_label']['EarliestEstimatedDeliveryDate'] ?? ($orderData["EarliestDeliveryDate"] ?? null);
        $latestRaw = $items[0]['_label']['LatestEstimatedDeliveryDate'] ?? ($orderData["LatestDeliveryDate"] ?? null);

        if ($earliestRaw)
            $EarliestDelivery = Carbon::parse($earliestRaw)->format('D, M j, Y');
        if ($latestRaw)
            $LatestDelivery = Carbon::parse($latestRaw)->format('D, M j, Y');

        $invoiceDisplay = getinvoicenumberid($platformOrderId);

        // Barcode sizing (scan reliability)
        $widthFactor = 3;
        $totalHeight = 60;
        $barcode_AmazonOrderId = $generator->getBarcode(
            preg_replace('/\s+/', '', $platformOrderId),
                $generator::TYPE_CODE_128,
            $widthFactor,
            $totalHeight
        );

        $shipToName = $orderData["ship_to_name"] ?? $orderData["BuyerName"] ?? '';

        // ✅ NEW: Warehouse details build (unique ASIN|MSKU)
        $unique = [];
        $ItemsForWarehouse = [];

        foreach ($items as $it) {
            $oid = (string) ($it['platform_order_item_id'] ?? '');
            $asin = (string) ($it['platform_asin'] ?? '');
            $msku = (string) ($it['platform_sku'] ?? '');

            if ($oid === '')
                continue;

            $ItemsForWarehouse[] = [
                'OrderItemId' => $oid,
                'ASIN' => $asin,
                'MSKU' => $msku,
            ];
        }

        $warehouseDetails = $this->addWarehouseDetails($platformOrderId, $ItemsForWarehouse);

        $warehouseByOrderItemId = [];
        foreach ($warehouseDetails as $wd) {
            $oid = (string) ($wd['OrderItemId'] ?? '');
            if ($oid === '')
                continue;
            $warehouseByOrderItemId[$oid] = $wd['pairs'] ?? [];
        }

        $itemsText = '';
        foreach ($warehouseDetails as $entry) {
            if (empty($entry['pairs']))
                continue;

            $asin = trim((string) ($entry['ASIN'] ?? ''));
            if ($asin === '')
                $asin = (string) ($entry['MSKU'] ?? '');

            $itemsText .= '<strong>' . htmlspecialchars($asin) . "</strong><br>\n";
            foreach ($entry['pairs'] as $p) {
                $loc = (string) ($p['warehouselocation'] ?? '');
                $sn = (string) ($p['serialnumber'] ?? '');
                $itemsText .= htmlspecialchars($loc) . ' - ' . htmlspecialchars($sn) . "<br>\n";
            }
            $itemsText .= "<br>\n";
        }

        // ✅ NEW: QR payload + base64
        $qrPayload = trim((string) $shipToName);
        foreach ($items as $it) {
            $t = trim((string) ($it['platform_title'] ?? ''));
            if ($t !== '')
                $qrPayload .= ' ' . $t;
        }
        $qrPayload = trim(preg_replace('/\s+/', ' ', $qrPayload));
        $qrImgBase64 = $qrPayload !== '' ? $this->makeQrBase64($qrPayload, 4, 2) : '';

        $html = '<!DOCTYPE html><html lang="en"><head>';
        $html .= '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= '<title>Invoice</title>';
        $html .= '<style>
        body{font-family:Arial,sans-serif;margin:0;padding:0;display:flex;justify-content:center;align-items:center;height:100vh;font-size:20px;}
        .container{width:100%;max-width:9in;padding:0.5in;box-sizing:border-box;}
        .header-and-right{display:flex;justify-content:space-between;align-items:flex-start;gap:0px;}
        .divider{border-top:1px solid #000;margin:0.2in 0;}
        .shipping-info,.order-info,.product-table{margin-bottom:0.01in;}
        .product-table table{width:100%;border-collapse:collapse;}
        .product-table th,.product-table td{border:1px solid #000;padding:10px 10px;text-align:left;}
        .product-table th{background-color:#f2f2f2;}
        .text-right{text-align:right;}
        .subtotal-table{width:100%;text-align:right;border:none;}
        .subtotal-table td{padding:0;border:none;}
        .top-right { text-align:right; width:220px; }
        /* mPDF-safe header layout */
        .header-table{ width:100%; border-collapse:collapse; }
        .header-left{ width:70%; vertical-align:top; }
        .header-right{ width:30%; vertical-align:top; text-align:right; }

        .qrbox{ margin-top:10px; text-align:right; }
        .qrbox img{ width:140px; height:140px; display:inline-block; image-rendering:pixelated; }
.pd-wrap { width:100%; }
.pd-title { font-weight:bold; margin-bottom:6px; }

.pd-serials { width:100%; border-collapse:collapse; margin:6px 0 8px 0; font-size:14px; }
.pd-serials th, .pd-serials td { border:1px solid #000; padding:4px 6px; vertical-align:top; }
.pd-serials th { background-color:#f2f2f2; }

.pd-meta { width:100%; border-collapse:collapse; margin-top:6px; font-size:14px; }
.pd-meta td { border:none; padding:2px 0; vertical-align:top; }
.pd-meta .label { font-weight:bold; }

.pd-barcode-asin { height:40px; display:block; margin:6px 0; }
.pd-barcode-serial { height:32px; display:block; margin:2px 0; }

/* ✅ product-table: make the LEFT column narrower */
.product-table table { table-layout: fixed; width: 100%; } /* important for mPDF */
.product-table col.pt-label { width: 18%; }   /* try 18% first */
.product-table col.pt-content { width: 82%; }

/* keep left label tight but readable */
.product-table td:first-child {
  padding: 8px 8px;          /* less padding = less width */
  vertical-align: middle;
  font-weight: bold;
  white-space: nowrap;       /* prevents "Product Details" wrapping */
}
    </style>';
        $html .= '</head><body><div class="container">';

        // ---------------- MULTI ITEM BRANCH ----------------
        if ($itemCount > 1) {
            // Items table first
            $html .= '<div class="product-table">';
            $html .= '<table>';
            $html .= '<colgroup>';
            $html .= '<col class="pt-label">';
            $html .= '<col class="pt-content">';
            $html .= '</colgroup>';
            $html .= '<p style="text-align:right;">' . htmlspecialchars($invoiceDisplay) . '</p>';
            $html .= '<table>';

            foreach ($items as $item) {
                $qty = $item['QuantityOrdered'] ?? ($item['QuantityShipped'] ?? 1);

                $html .= '<tr><td>Quantity</td><td>' . htmlspecialchars((string) $qty) . '</td></tr>';
                $html .= '<tr><td>Product Details</td><td>';

                $title = $item["platform_title"] ?? '';
                $sku = $item["platform_sku"] ?? '';
                $asin = $item["platform_asin"] ?? '';
                $orderItemId = $item["platform_order_item_id"] ?? '';

                $html .= htmlspecialchars($title) . '<br>';
                $html .= '<strong>SKU:</strong> ' . htmlspecialchars($sku) . '<br>';
                $html .= '<strong>ASIN:</strong> ' . htmlspecialchars($asin) . '<br>';

                $cleanAsin = preg_replace('/[^A-Za-z0-9]/', '', (string) $asin);
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

                $html .= '<strong>Order Item ID:</strong> ' . htmlspecialchars((string) $orderItemId) . '<br>';



                $condId = $item["ConditionId"] ?? '';
                $condSub = $item["ConditionSubtypeId"] ?? '';
                $html .= '<strong>Condition:</strong> ' . htmlspecialchars($condId . ' - ' . $condSub) . '<br>';
                $pairs = $warehouseByOrderItemId[(string) $orderItemId] ?? [];

                if (!empty($pairs)) {
                    $html .= '<strong>Serials:</strong><br>';

                    foreach ($pairs as $p) {
                        $loc = (string) ($p['warehouselocation'] ?? '');
                        $sn = (string) ($p['serialnumber'] ?? '');

                        if ($sn === '')
                            continue;

                        $html .= htmlspecialchars($loc) . ' - ' . htmlspecialchars($sn) . '<br>';

                        // Barcode for serial
                        $cleanSn = preg_replace('/[^A-Za-z0-9]/', '', $sn);
                        if ($cleanSn !== '') {
                            try {
                                $barcode_SN = $generator->getBarcode(
                                    $cleanSn,
                                        $generator::TYPE_CODE_128,
                                    2,   // widthFactor
                                    35   // height
                                );
                                $html .= '<img src="data:image/png;base64,' . base64_encode($barcode_SN) . '" alt="Serial Barcode" style="height:35px;">';
                            } catch (\Throwable $e) {
                                $html .= '<em>Serial barcode failed.</em><br>';
                            }
                        } else {
                            $html .= '<em>Invalid serial for barcode.</em><br>';
                        }

                        $html .= '<br>';
                    }
                } else {
                    // Optional: show something if no serials
                    $html .= '<br><em>No serials found.</em><br>';
                }

                $p = $item["unit_price"] ?? 0.00;
                $s = $item["shippingPrice"] ?? 0.00;

                $html .= '<strong>P Code:</strong> $' . htmlspecialchars(convertNumberToCustomCode($p)) . '&nbsp;&nbsp;|&nbsp;&nbsp;';
                $html .= '<strong>S Code:</strong> $' . htmlspecialchars(convertNumberToCustomCode($s)) . '&nbsp;&nbsp;|&nbsp;&nbsp;';
                $html .= '<strong>L Code:</strong> $' . htmlspecialchars(convertNumberToCustomCode($LCode));

                $html .= '</td></tr>';

                $note = fetchNote($platformOrderId);
                if (!empty($note) && strtolower(trim($note)) !== 'n/a') {
                    $html .= '<tr><td>Note:</td><td>' . htmlspecialchars($note) . '</td></tr>';
                }

                if (($settings['displayPrice'] ?? 'FALSE') === 'TRUE') {
                    $unitTax = $item["unit_tax"] ?? 0.00;

                    $html .= '<tr><td style="width:80px;">Order Total</td><td class="text-right" style="width:185px;">';
                    $html .= '<table class="subtotal-table">';
                    $html .= '<tr style="font-size:10;height:30px;"><td><strong>Item Price</strong></td><td>$' . htmlspecialchars(number_format((float) $p, 2)) . '</td></tr>';
                    $html .= '<tr style="font-size:10;height:30px;"><td><strong>Item Tax</strong></td><td>$' . htmlspecialchars(number_format((float) $unitTax, 2)) . '</td></tr>';
                    $html .= '<tr style="font-size:10;border-bottom:1px solid gray;height:30px;"><td><strong>Shipping Price</strong></td><td>$' . htmlspecialchars(number_format((float) $s, 2)) . '</td></tr>';
                    $html .= '</table></td></tr>';
                }
            }

            $html .= '</table></div>';

            $html .= '<table class="header-table">';
            $html .= '<tr>';

            // LEFT: Ship To
            $html .= '<td class="header-left">';
            $html .= '<h1>Ship To:</h1>';
            $html .= '<h2>' . htmlspecialchars($shipToName) . '</h2>';
            $html .= '<h2>' . htmlspecialchars($address1) . '<br>' . htmlspecialchars($address2) . '</h2>';
            // internal + title UNDER QR (optional)
            // $html .= '<div style="margin-top:8px; font-size:12px; line-height:1.2;">';
            foreach ($items as $item) {
                $asin = $item["platform_asin"] ?? '';
                $internal = $asin ? getInternalByASIN($asin) : '';
                $title = $item["platform_title"] ?? '';

                if ($internal !== '')
                    $html .= '<strong>' . htmlspecialchars($internal) . '</strong><br>';
                if ($title !== '')
                    $html .= '<strong>' . htmlspecialchars($title) . '</strong><br>';
            }
            // $html .= '</div>';
            $html .= '</td>';


            // RIGHT: QR + item text
            $html .= '<td class="header-right">';
            $html .= '<p style="text-align:right;">' . htmlspecialchars($invoiceDisplay) . '</p>';
            // QR at top-right
            if ($qrImgBase64 !== '') {
                $html .= '<div class="qrbox">';
                $html .= '<img src="data:image/png;base64,' . $qrImgBase64 . '" alt="QR Code">';
                $html .= '</div>';
            }



            $html .= '</td>';

            $html .= '</tr>';
            $html .= '</table>';


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

            $shipByRaw = $orderData['LatestShipDate'] ?? null;
            if ($shipByRaw) {
                $dt = new DateTime($shipByRaw);
                $html .= htmlspecialchars($dt->format('D, F j, Y'));
            }
            $html .= '</td></tr>';

            $html .= '<tr><td>' . htmlspecialchars($address1) . '</td><td style="width:135px;">Ship Date:</td><td>';

            $shipDateRaw = $items[0]['_label']['shipDate'] ?? null;
            if ($shipDateRaw) {
                $dt = new DateTime($shipDateRaw, new \DateTimeZone('UTC'));
                $dt->setTimezone(new \DateTimeZone('America/Los_Angeles'));
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
            if ($deliveryExperience === '')
                $deliveryExperience = DeliveryExperience($platformOrderId);

            if (
                strtoupper($settings['signatureRequired'] ?? 'FALSE') === "TRUE" ||
                in_array($deliveryExperience, ['DeliveryConfirmationWithSignature', 'DeliveryConfirmationWithAdultSignature'], true)
            ) {
                $html .= '<div style="text-align:center;"><strong>Confirmation Services: Signature confirmation</strong></div>';
            }

            $html .= '</div>'; // shipping-info

            // Warehouse block + user
            /*
            if ($itemsText !== '') {
                $html .= '
            <div style="display:flex; justify-content:space-between; align-items:flex-start; width:100%; font-size:16px; color:#555; margin-top:20px;">
                <div style="flex:1; max-width:70%;">' . $itemsText . '</div>
                <div style="text-align:right; min-width:25%;">- ' . htmlspecialchars($user ?: ' ') . '</div>
            </div>';
            } else {
                $html .= '<div style="text-align:right;transform:translate(-50px,0px);font-size:16px;color:#555;">- ' . htmlspecialchars($user ?: ' ') . '</div>';
            }*/
        }

        // ---------------- SINGLE ITEM BRANCH ----------------
        else {
            $html .= '<table class="header-table">';
            $html .= '<tr>';

            // LEFT: Ship To
            $html .= '<td class="header-left">';
            $html .= '<h1>Ship To:</h1>';
            $html .= '<h2>' . htmlspecialchars($shipToName) . '</h2>';
            $html .= '<h2>' . htmlspecialchars($address1) . '<br>' . htmlspecialchars($address2) . '</h2>';
            // internal + title UNDER QR (optional)
            // $html .= '<div style="margin-top:8px; font-size:12px; line-height:1.2;">';
            foreach ($items as $item) {
                $asin = $item["platform_asin"] ?? '';
                $internal = $asin ? getInternalByASIN($asin) : '';
                $title = $item["platform_title"] ?? '';

                if ($internal !== '')
                    $html .= '<strong>' . htmlspecialchars($internal) . '</strong><br>';
                if ($title !== '')
                    $html .= '<strong>' . htmlspecialchars($title) . '</strong><br>';
            }
            // $html .= '</div>';
            $html .= '</td>';

            // RIGHT: QR + item text
            $html .= '<td class="header-right">';

            // QR at top-right
            $html .= '<p style="text-align:right;">' . htmlspecialchars($invoiceDisplay) . '</p>';
            if ($qrImgBase64 !== '') {
                $html .= '<div class="qrbox">';
                $html .= '<img src="data:image/png;base64,' . $qrImgBase64 . '" alt="QR Code">';
                $html .= '</div>';
            }
            $html .= '</td>';

            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<div class="divider"></div>';

            $storeName = $items[0]['storename'] ?? ($orderData['storename'] ?? '');
            $html .= '<div class="order-info" style="margin-bottom:0.2in;">';
            $html .= '<strong>Order ID: ' . htmlspecialchars($platformOrderId) . '</strong><br>';
            $html .= '<img style="background:#fff;padding:10px;display:block" src="data:image/png;base64,' . base64_encode($barcode_AmazonOrderId) . '" alt="Amazon Order Barcode">';
            $html .= '<p>Thank you for buying from ' . htmlspecialchars($storeName) . ' on Amazon Marketplace.</p>';
            $html .= '</div>';

            $html .= '<div class="shipping-info"><table>';
            $html .= '<tr><td><strong>Billing Address:</strong></td><td style="width:135px;">Order Date:</td><td>' . htmlspecialchars($newdate) . '</td></tr>';

            $buyerName = $orderData["BuyerName"] ?? '';
            $html .= '<tr><td>' . htmlspecialchars($buyerName) . '</td><td style="width:135px;">Ship by Date:</td><td>';

            $shipByRaw = $orderData['LatestShipDate'] ?? null;
            if ($shipByRaw) {
                $dt = new DateTime($shipByRaw);
                $html .= htmlspecialchars($dt->format('D, F j, Y'));
            }
            $html .= '</td></tr>';

            $html .= '<tr><td>' . htmlspecialchars($address1) . '</td><td style="width:135px;">Ship Date:</td><td>';

            $shipDateRaw = $items[0]['_label']['shipDate'] ?? null;
            if ($shipDateRaw) {
                $dt = new DateTime($shipDateRaw, new \DateTimeZone('UTC'));
                $dt->setTimezone(new \DateTimeZone('America/Los_Angeles'));
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
            if ($deliveryExperience === '')
                $deliveryExperience = DeliveryExperience($platformOrderId);

            if (
                strtoupper($settings['signatureRequired'] ?? 'FALSE') === "TRUE" ||
                in_array($deliveryExperience, ['DeliveryConfirmationWithSignature', 'DeliveryConfirmationWithAdultSignature'], true)
            ) {
                $html .= '<div style="text-align:center;"><strong>Confirmation Services: Signature confirmation</strong></div>';
            }

            $html .= '</div>'; // shipping-info
            /*
            if ($itemsText !== '') {
                $html .= '
            <div style="display:flex; justify-content:space-between; align-items:flex-start; width:100%; font-size:16px; color:#555; margin-top:20px;">
                <div style="flex:1; max-width:70%;">' . $itemsText . '</div>
                <div style="text-align:right; min-width:25%;">- ' . htmlspecialchars($user ?: ' ') . '</div>
            </div>';
            } else {
                $html .= '<div style="text-align:right;transform:translate(-50px,0px);font-size:16px;color:#555;">- ' . htmlspecialchars($user ?: ' ') . '</div>';
            }
            */

            // Product table (single item)
            $html .= '<div class="product-table">';
            $html .= '<table>';
            $html .= '<colgroup>';
            $html .= '<col class="pt-label">';
            $html .= '<col class="pt-content">';
            $html .= '</colgroup>';

            foreach ($items as $item) {
                $qty = $item['QuantityOrdered'] ?? ($item['QuantityShipped'] ?? 1);

                $html .= '<tr><td>Quantity</td><td>' . htmlspecialchars((string) $qty) . '</td></tr>';
                $html .= '<tr><td>Product Details</td><td>';

                $title = $item["platform_title"] ?? '';
                $sku = $item["platform_sku"] ?? '';
                $asin = $item["platform_asin"] ?? '';
                $orderItemId = $item["platform_order_item_id"] ?? '';

                $html .= htmlspecialchars($title) . '<br>';


                $cleanAsin = preg_replace('/[^A-Za-z0-9]/', '', (string) $asin);
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


                $pairs = $warehouseByOrderItemId[(string) $orderItemId] ?? [];

                if (!empty($pairs)) {
                    // $html .= '<strong>Serials:</strong><br>';

                    foreach ($pairs as $p) {
                        $loc = (string) ($p['warehouselocation'] ?? '');
                        $sn = (string) ($p['serialnumber'] ?? '');

                        if ($sn === '')
                            continue;

                        $html .= htmlspecialchars($loc) . ' - ' . htmlspecialchars($sn) . '<br>';

                        // Barcode for serial
                        $cleanSn = preg_replace('/[^A-Za-z0-9]/', '', $sn);
                        if ($cleanSn !== '') {
                            try {
                                $barcode_SN = $generator->getBarcode(
                                    $cleanSn,
                                        $generator::TYPE_CODE_128,
                                    2,   // widthFactor
                                    35   // height
                                );
                                $html .= '<img src="data:image/png;base64,' . base64_encode($barcode_SN) . '" alt="Serial Barcode" style="height:35px;">';
                            } catch (\Throwable $e) {
                                $html .= '<em>Serial barcode failed.</em><br>';
                            }
                        } else {
                            $html .= '<em>Invalid serial for barcode.</em><br>';
                        }

                        $html .= '<br>';
                    }
                } else {
                    // Optional: show something if no serials
                    $html .= '<br><em>No serials found.</em><br>';
                }

                $p = $item["unit_price"] ?? 0.00;
                $s = $item["shippingPrice"] ?? 0.00;
                $condId = $item["ConditionId"] ?? '';
                $condSub = $item["ConditionSubtypeId"] ?? '';

                $condText = trim($condId . ' - ' . $condSub, " -");

                $html .= '<table class="pd-meta">';

                $html .= '<tr>';
                $html .= '<td><span class="label">SKU:</span><strong>' . htmlspecialchars($sku) . '</strong></td>';
                $html .= '<td><span class="label">ASIN:</span> <strong>' . htmlspecialchars($asin) . '</strong></td>';
                $html .= '</tr>';

                $html .= '<tr>';
                $html .= '<td><span class="label">Condition:</span> <strong>' . htmlspecialchars($condText) . '</strong></td>';
                $html .= '<td><span class="label">Order Item ID:</span> <strong>' . htmlspecialchars($orderItemId) . '</strong></td>';
                $html .= '</tr>';

                $html .= '<tr>';
                $html .= '<td><span class="label">P Code:</span> <strong>$' . htmlspecialchars(convertNumberToCustomCode($p)) . '</strong></td>';
                $html .= '<td><span class="label">S Code:</span> <strong>$' . htmlspecialchars(convertNumberToCustomCode($s)) . '</strong></td>';
                $html .= '</tr>';

                $html .= '<tr>';
                $html .= '<td colspan="2"><span class="label">L Code:</span> <strong>$' . htmlspecialchars(convertNumberToCustomCode($LCode)) . '</strong></td>';
                $html .= '</tr>';

                $html .= '</table>';

                $html .= '</td></tr>';

                $note = fetchNote($platformOrderId);
                if (!empty($note) && strtolower(trim($note)) !== 'n/a') {
                    $html .= '<tr><td>Note:</td><td>' . htmlspecialchars($note) . '</td></tr>';
                }

                if (($settings['displayPrice'] ?? 'FALSE') === 'TRUE') {
                    $unitTax = $item["unit_tax"] ?? 0.00;

                    $html .= '<tr><td style="width:80px;">Order Total</td><td class="text-right" style="width:185px;">';
                    $html .= '<table class="subtotal-table">';
                    $html .= '<tr style="font-size:10;height:30px;"><td><strong>Item Price</strong></td><td>$' . htmlspecialchars(number_format((float) $p, 2)) . '</td></tr>';
                    $html .= '<tr style="font-size:10;height:30px;"><td><strong>Item Tax</strong></td><td>$' . htmlspecialchars(number_format((float) $unitTax, 2)) . '</td></tr>';
                    $html .= '<tr style="font-size:10;border-bottom:1px solid gray;height:30px;"><td><strong>Shipping Price</strong></td><td>$' . htmlspecialchars(number_format((float) $s, 2)) . '</td></tr>';
                    $html .= '</table></td></tr>';
                }
            }

            $html .= '</table></div>';
        }

        $html .= '</div></body></html>';
        return $html;
    }


    protected function generatePDF($html, $pdfPath, $settings)
    {
        $width = $settings['width'] ?? 350;

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
                        $rowBinary .= "0";
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

    // ✅ NEW: Multi-page PDF -> images -> ZPL (writes temp images into $tmpDir)
    protected function convertPDFToZPL(string $pdfPath, string $orderId, array $settings, string $tmpDir): string
    {
        $testPrint = $settings['testPrint'] ?? false;

        $imagick = new \Imagick();
        $imagick->setResolution(300, 300);

        // ✅ load ALL pages (no [0])
        $imagick->readImage($pdfPath);
        $imagick->setImageFormat('png');

        $pageCount = $imagick->getNumberImages();
        Log::info("convertPDFToZPL pageCount={$pageCount} pdf={$pdfPath}");

        if ($pageCount <= 0) {
            $imagick->clear();
            $imagick->destroy();
            throw new \RuntimeException("No pages detected in PDF: {$pdfPath}");
        }

        $zplCode = "";

        for ($i = 0; $i < $pageCount; $i++) {
            $imagick->setIteratorIndex($i);
            $img = $imagick->getImage();

            $img->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $img->setBackgroundColor(new \ImagickPixel('white'));
            $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $img->setImageFormat('png');

            $imagePath = $tmpDir . DIRECTORY_SEPARATOR . "invoice_{$orderId}_page{$i}.png";
            $img->writeImage($imagePath);

            $zplCode .= self::convertImageToZPL($testPrint, $imagePath) . "\n";

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

    protected function buildQrImgTag(string $payload, int $size = 6, int $margin = 2): string
    {
        if (!class_exists('QRcode')) {
            return '<!-- QRcode class not loaded -->';
        }

        // Generate PNG into output buffer (no temp files needed)
        ob_start();
        QRcode::png($payload, null, QR_ECLEVEL_Q, $size, $margin);
        $png = ob_get_clean();

        if (!$png || strlen($png) < 50) {
            return '<!-- QR generation returned empty -->';
        }

        // IMPORTANT: no space after comma
        $b64 = base64_encode($png);

        return '<img src="data:image/png;base64,' . $b64 . '" 
                 alt="QR Code"
                 style="display:inline-block; width:120px; height:120px; image-rendering:pixelated;">';
    }

    protected function makeQrBase64(string $payload, int $size = 6, int $margin = 2): string
    {
        if (!class_exists('QRcode'))
            return '';

        ob_start();
        \QRcode::png($payload, null, QR_ECLEVEL_Q, $size, $margin);
        $png = ob_get_clean();

        if (!$png || strlen($png) < 50)
            return '';

        return base64_encode($png);
    }

    protected function resolveChildMskusFromParent(string $parentMsku, string $asin = '', string $store = ''): array
    {
        $children = [];

        /**
         * TODO: Replace this query with YOUR real mapping table.
         *
         * Common patterns in IMS-like DBs:
         * - tblmsku_components(parent_msku, child_msku)
         * - tblbundles(parent_sku, component_sku)
         * - tblkits(parent_msku, child_msku, qty)
         * - tblasin_pack / tblparentchild
         */

        // Example (placeholder):
        // $rows = DB::table('tblmsku_components')
        //     ->select('child_msku')
        //     ->where('parent_msku', $parentMsku)
        //     ->get();

        // foreach ($rows as $r) $children[] = (string) $r->child_msku;

        // Fallback: if nothing found, return empty array
        $children = array_values(array_unique(array_filter($children)));

        return $children;
    }
}
