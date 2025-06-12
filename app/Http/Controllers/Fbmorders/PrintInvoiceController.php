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

require base_path('app/Helpers/ebay_helpers.php');

class PrintInvoiceController extends Controller
{
    public function printInvoice(Request $request)
    {
        $platform_order_id = $request->input('platform_order_id', '');
        $order_item_ids = $request->input('platform_order_item_ids', ''); // array
        $action = $request->input('action', ''); // 'PrintInvoice' or 'ViewInvoice'
        $settings = $request->input('settings', ''); // could be the display price, test print, or signature required

        // Step 1: Fetch order and items
        $order = DB::table('tbloutboundorders')
            ->where('platform_order_id', $platform_order_id)
            ->first();

        $items = DB::table('tbloutboundordersitem')
            ->where('platform_order_id', $platform_order_id)
            ->get(); // no need to filter item IDs since you're selecting all

        // Step 2: Merge items into order
        $orderData = $order->toArray();
        $orderData['items'] = $items->toArray();

        // Step 3: Generate HTML
        $html = $this->generateHtml($settings, $orderData, $action);

        // Step 4: Generate PDF
        $pdfFile = storage_path("app/public/invoice_{$platform_order_id}.pdf");
        $this->generatePDF($html, $pdfFile, $settings);

        // Step 5: Convert to ZPL
        $zplCode = $this->convertPDFToZPL($pdfFile, $platform_order_id, $settings);

        // Step 6: Send to printer if requested
        if ($action === 'PrintInvoice') {
            $this->sendToPrinter($zplCode); 
        }

        return response()->json([
            'success' => true,
            'zpl_preview' => $action === 'ViewInvoice' ? $zplCode : null
        ]);
    }

    protected function generateHtml($settings, $orderData, $action)
    {

        $rowCount = $orderData;

        $html = ' ';
        $html .= '<!DOCTYPE html>';
        $html .= '<html lang="en">';

        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= '<title>Invoice</title>';
        $html .= '<style>';
        $html .= 'body {';
        $html .= '        font-family: Arial, sans-serif;';
        $html .= '        margin: 0;';
        $html .= '        padding: 0;';
        $html .= '        display: flex;';
        $html .= '        justify-content: center;';
        $html .= '        align-items: center;';
        $html .= '        height: 100vh;';
        $html .= '        font-size: 20px;';
        $html .= '    }';

        $html .= '';
        $html .= '    .container {';
        $html .= '        width: 100%;';
        $html .= '        max-width: 9in;';
        $html .= '        padding: 0.5in;';
        $html .= '        box-sizing: border-box;';
        $html .= '    }';

        $html .= '';
        $html .= '    .header-and-right {';
        $html .= '        display: flex;';
        $html .= '        justify-content: space-between;';
        $html .= '        /* Distributes space between the header and top-right */';
        $html .= '        align-items: flex-start;';
        $html .= '        gap: 0px;';
        $html .= '        /* Aligns them at the top */';
        $html .= '    }';

        $html .= '';
        $html .= '    .header,';
        $html .= '    .footer {';
        $html .= '        margin-bottom: 0.2in;';
        $html .= '    }';

        $html .= '';
        $html .= '    .header h1,';
        $html .= '    .header h2,';
        $html .= '    .header p {';
        $html .= '        margin: 0;';
        $html .= '        padding: 0;';
        $html .= '    }';

        $html .= '';
        $html .= '    .header {';
        $html .= '        text-align: left;';
        $html .= '    }';

        $html .= '';
        $html .= '    .divider {';
        $html .= '        border-top: 1px solid #000;';
        $html .= '        margin: 0.2in 0;';
        $html .= '    }';

        $html .= '';
        $html .= '    .shipping-info,';
        $html .= '    .order-info,';
        $html .= '    .product-table {';
        $html .= '        margin-bottom: 0.2in;';
        $html .= '    }';

        $html .= '';
        $html .= '    .product-table table {';
        $html .= '        width: 100%;';
        $html .= '        border-collapse: collapse;';
        $html .= '    }';

        $html .= '';
        $html .= '    .product-table th,';
        $html .= '    .product-table td {';
        $html .= '        border: 1px solid #000;';
        $html .= '        padding: 10px 10px;';
        $html .= '        text-align: left;';
        $html .= '    }';

        $html .= '';
        $html .= '    .product-table th {';
        $html .= '        background-color: #f2f2f2;';
        $html .= '    }';

        $html .= '';
        $html .= '    .text-right {';
        $html .= '        text-align: right;';
        $html .= '    }';

        $html .= '';
        $html .= '    .subtotal,';
        $html .= '    .tax,';
        $html .= '    .total {';
        $html .= '        font-weight: bold;';
        $html .= '    }';

        $html .= '';
        $html .= '    .subtotal,';
        $html .= '    .tax {';
        $html .= '        border-top: 1px solid #000;';
        $html .= '    }';

        $html .= '';
        $html .= '    .total {';
        $html .= '        border-top: 2px solid #000;';
        $html .= '    }';

        $html .= '';
        $html .= '    .subtotal-table {';
        $html .= '        width: 100%;';
        $html .= '        text-align: right;';
        $html .= '        border: none;';
        $html .= '    }';

        $html .= '';
        $html .= '    .subtotal-table td {';
        $html .= '        padding: 0;';
        $html .= '        border: none;';
        $html .= '        /* Hides the borders */';
        $html .= '    }';

        $html .= '';
        $html .= '    .top-right {';
        $html .= '        text-align: left;';
        $html .= '        max-width: 295px;';
        $html .= '    }';

        $html .= '';
        $html .= '    .no-border-table {';
        $html .= '        border-collapse: collapse;';
        $html .= '        border: none;';
        $html .= '        width: 100%;';
        $html .= '    }';

        $html .= '';
        $html .= '    .no-border-table td {';
        $html .= '        border: none;';
        $html .= '    }';
        $html .= '    </style>';
        $html .= '</head>';

        $html .= '<body>';
        $html .= '    <div class="container">';

        if ($rowCount > 1) {
            $html .= '        <div class="product-table">';
            $html .= '            <p style="text-align: right;">' . getinvoicenumberid($AmazonOrderId) . '</p>';
            $html .= '            <table>';

            foreach ($rows as $row) {
                $html .= '                <tr>';
                $html .= '                    <td>Quantity</td>';
                $html .= '                    <td>' . $row['QuantityOrdered'] . '</td>';
                $html .= '                </tr>';
                $html .= '                <tr>';
                $html .= '                    <td>Product Details</td>';
                $html .= '                    <td>';
                $html .= '                        ' . $row["Title"] . ' <br>';
                $html .= '                        <strong>SKU:</strong> ' . $row["SellerSKU"] . ' <br>';
                $html .= '                        <strong>ASIN:</strong> ' . $row["ASIN"] . ' <br>';
                $html .= '                        <img src="data:image/png;base64,' . base64_encode($barcode) . '" alt="ASIN Barcode"> <br>';
                $html .= '                        <strong>Condition:</strong> ' . $row["ConditionId"] . ' - ' . $row["ConditionSubtypeId"] . '<br>';
                $html .= '                        <strong>Order Item ID:</strong> ' . $row["orderitemid"] . '<br>';
                $html .= '                        <strong>P Code:</strong> $' . convertNumberToCustomCode($row["ItemPrice"]) . '<br>';
                $html .= '                        <strong>S Code:</strong> $' . convertNumberToCustomCode($row["shippingPrice"]) . '<br>';
                $html .= '                        <strong>L Code:</strong> $' . convertNumberToCustomCode($LCode) . '';
                $html .= '                    </td>';
                $html .= '                </tr>';
                $html .= '                <tr>';
                $html .= '                    <td>Note:</td>';
                $html .= '                    <td>' . fetchNote($row['AmazonOrderId'], $row['ProductID']) . '</td>';
                $html .= '                </tr>';

                if ($display_Price == 'TRUE') {
                    $html .= '                <tr>';
                    $html .= '                    <td style="width: 80px;">Order Total</td>';
                    $html .= '                    <td class="text-right" style="width:185px;">';
                    $html .= '                        <table class="subtotal-table">';
                    $html .= '                            <tr style="font-size: 10; height: 30px;">';
                    $html .= '                                <td><strong>Item Price</strong></td>';
                    $html .= '                                <td>$' . $row["ItemPrice"] . '</td>';
                    $html .= '                            </tr>';
                    $html .= '                            <tr style="font-size: 10; height: 30px;">';
                    $html .= '                                <td><strong>Item Tax</strong></td>';
                    $html .= '                                <td>$' . $row["ItemTax"] . '</td>';
                    $html .= '                            </tr>';
                    $html .= '                            <tr style="font-size: 10; border-bottom: 1px solid gray; height: 30px;">';
                    $html .= '                                <td><strong>Shipping Price</strong></td>';
                    $html .= '                                <td>$' . $row["shippingPrice"] . '</td>';
                    $html .= '                            </tr>';
                    $html .= '                        </table>';
                    $html .= '                    </td>';
                    $html .= '                </tr>';
                }
            }

            $html .= '            </table>';
            $html .= '        </div>';

            $html .= '        <div class="header-and-right" style="page-break-before: always;">';
            $html .= '            <br><br>';
            $html .= '            <div class="header">';
            $html .= '                <p style="text-align: right;">' . getinvoicenumberid($AmazonOrderId) . '</p>';
            $html .= '                <h1>Ship To:</h1>';
            $html .= '                <h2>' . ($row["ship_to_name"] ?? $row["costumer_name"]) . '</h2>';
            $html .= '                <h2>' . $address1 . '<br>' . $address2 . '</h2>';
            $html .= '            </div>';
            $html .= '            <div class="top-right">';

            foreach ($rows as $row) {
                $internal = isset($row["ASIN"]) ? getInternalByASIN($row["ASIN"]) : "";
                $amazontitle = isset($row["Title"]) ? $row["Title"] : "";
                $html .= '                <strong>' . $internal . '</strong> <br>';

                if (!empty($amazontitle)) {
                    $html .= '                <strong>' . $amazontitle . '</strong> <br>';
                }
            }

            $html .= '            </div>';
            $html .= '        </div>';

            $html .= '        <div class="divider"></div>';

            $html .= '        <div class="order-info">';
            $html .= '            <strong>Order ID: ' . $row["AmazonOrderId"] . '</strong> <br>';
            $html .= '            <img src="data:image/png;base64,' . base64_encode($barcode_AmazonOrderId) . '" alt="Amazon Barcode"><br>';
            $html .= '            <p>Thank you for buying from ' . $strname . ' on Amazon Marketplace.</p>';
            $html .= '        </div>';

            $html .= '        <div class="shipping-info">';
            $html .= '            <table>';
            $html .= '                <tr>';
            $html .= '                    <td><strong>Billing Address:</strong></td>';
            $html .= '                    <td style="width: 135px;">Order Date:</td>';
            $html .= '                    <td>' . $newdate . '</td>';
            $html .= '                </tr>';
            $html .= '                <tr>';
            $html .= '                    <td>' . $row["costumer_name"] . '</td>';
            $html .= '                    <td style="width: 135px;">Ship by Date:</td>';
            $html .= '                    <td>';

            $shipDate_LatestShipDate_Raw = getLatestShipDate($AmazonOrderId, $orderitemid);
            if ($shipDate_LatestShipDate_Raw) {
                $dateLatest = new DateTime($shipDate_LatestShipDate_Raw);
                $html .= ' ' . $dateLatest->format('D, F j, Y');
            }

            $html .= '                    </td>';
            $html .= '                </tr>';
            $html .= '                <tr>';
            $html .= '                    <td>' . $address1 . '</td>';
            $html .= '                    <td style="width: 135px;">Ship Date:</td>';
            $html .= '                    <td>';

            $shipDateRaw = getShipDate($AmazonOrderId, $orderitemid);
            if ($shipDateRaw) {
                $date = new DateTime($shipDateRaw, new DateTimeZone('UTC'));
                $date->setTimezone(new DateTimeZone('America/Los_Angeles'));
                $html .= ' ' . $date->format('D, F j, Y');
            }

            $html .= '                    </td>';
            $html .= '                </tr>';
            $html .= '                <tr>';
            $html .= '                    <td>' . $address2 . '</td>';
            $html .= '                    <td>Deliver by Date:</td>';
            $html .= '                    <td>' . $EarliestDelivery . ' - ' . $LatestDelivery . '</td>';
            $html .= '                </tr>';
            $html .= '                <tr>';
            $html .= '                    <td></td>';
            $html .= '                    <td>Shipping Service:</td>';
            $html .= '                    <td>' . $row["ShipmentServiceLevelCategory"] . '</td>';
            $html .= '                </tr>';
            $html .= '                <tr>';
            $html .= '                    <td></td>';
            $html .= '                    <td>Seller Name:</td>';
            $html .= '                    <td>' . $strname . '</td>';
            $html .= '                </tr>';
            $html .= '            </table>';

            $deliveryExperience = DeliveryExperience($AmazonOrderId);
            if (
                strtoupper($signatureRequired) == "TRUE" ||
                (
                    $deliveryExperience == "DeliveryConfirmationWithSignature" ||
                    $deliveryExperience == "DeliveryConfirmationWithAdultSignature"
                )
            ) {
                $html .= '            <div style="text-align: center;"><strong>Confirmation Services: Signature confirmation</strong></div>';
            }

            $html .= '        </div>';

            $html .= '        <div style="text-align: right; transform: translate(-50px, 0px); font-size: 16px; color: #555;">';
            $html .= '            - ' . htmlspecialchars($user);
            $html .= '        </div>';
        } else {
            $html .= '<div class="header-and-right">';
            $html .= '    <div class="header">';
            $html .= '        <p style="text-align: right;">' . getinvoicenumberid($AmazonOrderId) . '</p>';
            $html .= '        <h1>Ship To:</h1>';
            $html .= '        <h2>' . ($row["ship_to_name"] ?? $row["costumer_name"]) . '</h2>';
            $html .= '        <h2>' . $address1 . '<br>' . $address2 . '</h2>';
            $html .= '    </div>';
            $html .= '    <div class="top-right">';

            foreach ($rows as $row) {
                $internal = isset($row["ASIN"]) ? getInternalByASIN($row["ASIN"]) : "";
                $amazontitle = isset($row["Title"]) ? $row["Title"] : "";
                $html .= '        <strong>' . $internal . '</strong> <br>';
                if (!empty($amazontitle)) {
                    $html .= '        <strong>' . $amazontitle . '</strong> <br>';
                }
            }

            $html .= '    </div>';
            $html .= '</div>';

            $html .= '<div class="divider"></div>';

            $html .= '<div class="order-info">';
            $html .= '    <strong>Order ID: ' . $row["AmazonOrderId"] . '</strong> <br>';
            $html .= '    <img src="data:image/png;base64,' . base64_encode($barcode_AmazonOrderId) . '" alt="Amazon Barcode"><br>';
            $html .= '    <p>Thank you for buying from ' . $strname . ' on Amazon Marketplace.</p>';
            $html .= '</div>';

            $html .= '<div class="shipping-info">';
            $html .= '    <table>';
            $html .= '        <tr>';
            $html .= '            <td><strong>Billing Address:</strong></td>';
            $html .= '            <td style="width: 135px;">Order Date:</td>';
            $html .= '            <td>' . $newdate . '</td>';
            $html .= '        </tr>';
            $html .= '        <tr>';
            $html .= '            <td>' . $row["costumer_name"] . '</td>';
            $html .= '            <td style="width: 135px;">Ship by Date:</td>';
            $html .= '            <td>';
            $shipDate_LatestShipDate_Raw = getLatestShipDate($AmazonOrderId, $orderitemid);
            if ($shipDate_LatestShipDate_Raw) {
                $dateLatest = new DateTime($shipDate_LatestShipDate_Raw);
                $html .= ' ' . $dateLatest->format('D, F j, Y');
            }
            $html .= '            </td>';
            $html .= '        </tr>';
            $html .= '        <tr>';
            $html .= '            <td>' . $address1 . '</td>';
            $html .= '            <td style="width: 135px;">Ship Date:</td>';
            $html .= '            <td>';
            $shipDateRaw = getShipDate($AmazonOrderId, $orderitemid);

            if ($shipDateRaw) {
                $date = new DateTime($shipDateRaw, new DateTimeZone('UTC'));
                $date->setTimezone(new DateTimeZone('America/Los_Angeles'));
                $html .= ' ' . $date->format('D, F j, Y');
            }

            $html .= '            </td>';
            $html .= '        </tr>';
            $html .= '        <tr>';
            $html .= '            <td>' . $address2 . '</td>';
            $html .= '            <td>Deliver by Date:</td>';
            $html .= '            <td>' . $EarliestDelivery . ' - ' . $LatestDelivery . '</td>';
            $html .= '        </tr>';
            $html .= '        <tr>';
            $html .= '            <td></td>';
            $html .= '            <td>Shipping Service:</td>';
            $html .= '            <td>' . $row["ShipmentServiceLevelCategory"] . '</td>';
            $html .= '        </tr>';
            $html .= '        <tr>';
            $html .= '            <td></td>';
            $html .= '            <td>Seller Name:</td>';
            $html .= '            <td>' . $strname . '</td>';
            $html .= '        </tr>';
            $html .= '    </table>';
            $deliveryExperience = DeliveryExperience($AmazonOrderId);
            if (
                strtoupper($signatureRequired) == "TRUE" ||
                ($deliveryExperience == "DeliveryConfirmationWithSignature" ||
                    $deliveryExperience == "DeliveryConfirmationWithAdultSignature")
            ) {
                $html .= '<div style="text-align: center;"><strong>Confirmation Services: Signature confirmation</strong></div>';
            }
            $html .= '</div>';

            $html .= '<div style="text-align: right; transform: translate(-50px, 0px); font-size: 16px; color: #555;">';
            $html .= '    - ' . htmlspecialchars($user);
            $html .= '</div>';

            $html .= '<div class="product-table">';
            $html .= '    <table>';
            foreach ($rows as $row) {
                $html .= '        <tr>';
                $html .= '            <td>Quantity</td>';
                $html .= '            <td>' . $row['QuantityOrdered'] . '</td>';
                $html .= '        </tr>';
                $html .= '        <tr>';
                $html .= '            <td>Product Details</td>';
                $html .= '            <td>';
                $html .= '                ' . $row["Title"] . ' <br>';
                $html .= '                <strong>SKU:</strong> ' . $row["SellerSKU"] . ' <br>';
                $html .= '                <strong>ASIN:</strong> ' . $row["ASIN"] . ' <br>';
                $html .= '                <img src="data:image/png;base64,' . base64_encode($barcode) . '" alt="ASIN Barcode"> <br>';
                $html .= '                <strong>Condition:</strong> ' . $row["ConditionId"] . ' - ' . $row["ConditionSubtypeId"] . '<br>';
                $html .= '                <strong>Order Item ID:</strong> ' . $row["orderitemid"] . '<br>';
                $html .= '                <strong>P Code:</strong> $' . convertNumberToCustomCode($row["ItemPrice"]) . '<br>';
                $html .= '                <strong>S Code:</strong> $' . convertNumberToCustomCode($row["shippingPrice"]) . '<br>';
                $html .= '                <strong>L Code:</strong> $' . convertNumberToCustomCode($LCode) . '';
                $html .= '            </td>';
                $html .= '        </tr>';
                $html .= '        <tr>';
                $html .= '            <td>Note:</td>';
                $html .= '            <td>' . fetchNote($row['AmazonOrderId'], $row['ProductID']) . '</td>';
                $html .= '        </tr>';
                if ($display_Price == 'TRUE') {
                    $html .= '        <tr>';
                    $html .= '            <td style="width: 80px;">Order Total</td>';
                    $html .= '            <td class="text-right" style="width:185px;">';
                    $html .= '                <table class="subtotal-table">';
                    $html .= '                    <tr style="font-size: 10; height: 30px;">';
                    $html .= '                        <td><strong>Item Price</strong></td>';
                    $html .= '                        <td>$' . $row["ItemPrice"] . '</td>';
                    $html .= '                    </tr>';
                    $html .= '                    <tr style="font-size: 10; height: 30px;">';
                    $html .= '                        <td><strong>Item Tax</strong></td>';
                    $html .= '                        <td>$' . $row["ItemTax"] . '</td>';
                    $html .= '                    </tr>';
                    $html .= '                    <tr style="font-size: 10; border-bottom: 1px solid gray; height: 30px;">';
                    $html .= '                        <td><strong>Shipping Price</strong></td>';
                    $html .= '                        <td>$' . $row["shippingPrice"] . '</td>';
                    $html .= '                    </tr>';
                    $html .= '                </table>';
                    $html .= '            </td>';
                    $html .= '        </tr>';
                }
            }
            $html .= '    </table>';
            $html .= '</div>';

            $html .= '<div style="text-align: right; transform: translate(-50px, 0px); font-size: 16px; color: #555;">';
            $html .= '    - ' . htmlspecialchars($user);
            $html .= '</div>';
        }
    }

    protected function generatePDF($html, $pdfPath, $settings)
    {
        $width = $settings['width'] ?? 100;

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

    protected function convertImageToZPL($testPrint, $imagePath)
    {
        $imagick = new \Imagick($imagePath);
        $imagick->setImageFormat('mono'); // 1-bit black & white
        $imagick->thresholdImage(0.5 * \Imagick::getQuantum()); // Convert to B&W
        $imagick->resizeImage(384, 0, \Imagick::FILTER_LANCZOS, 1); // Resize for 4" label width

        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();
        $bytesPerRow = (int) ceil($width / 8);
        $totalBytes = $bytesPerRow * $height;

        $rawData = $imagick->getImageBlob();
        $hexData = bin2hex($rawData);

        // Build ZPL command
        $zpl = "^XA\n";
        $zpl .= "^FO0,0^GFA," . $totalBytes . "," . $totalBytes . "," . $bytesPerRow . "," . strtoupper($hexData) . "^FS\n";
        if ($testPrint) {
            $zpl .= "^PQ1\n";
        }
        $zpl .= "^XZ";

        $imagick->clear();
        $imagick->destroy();

        return $zpl;
    }

    protected function convertPDFToZPL($pdfPath, $orderId, $settings)
    {
        $imagick = new \Imagick();
        $imagick->setResolution(300, 300);
        $imagick->readImage($pdfPath);
        $imagick->setImageFormat('png');

        $zplCode = "";
        for ($i = 0; $i < $imagick->getNumberImages(); $i++) {
            $imagick->setIteratorIndex($i);
            $img = $imagick->getImage();

            $img->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $img->setBackgroundColor(new \ImagickPixel('white'));
            $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $img->setImageFormat('png');

            $imagePath = storage_path("app/public/invoice_{$orderId}_page{$i}.png");
            $img->writeImage($imagePath);

            $zplCode .= $this->convertImageToZPL(false, $imagePath) . "\n";

            $img->clear();
            $img->destroy();
        }

        $imagick->clear();
        $imagick->destroy();

        return $zplCode;
    }

    protected function sendToPrinter($zplCode)
    {
        // Example HTTP POST to local printer API
        Http::post('http://localhost:9100/print', [
            'zpl' => $zplCode
        ]);
    }


}