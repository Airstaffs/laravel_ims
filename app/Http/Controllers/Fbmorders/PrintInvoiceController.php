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

class PrintInvoiceController extends Controller
{
    public function printInvoice(Request $request)
    {
        $platform_order_id = $request->input('platform_order_id');
        $order_item_ids = $request->input('platform_order_item_ids'); // array
        $action = $request->input('action'); // 'PrintInvoice' or 'ViewInvoice'
        $settings = $request->input('settings'); // could be null or have size, format etc.

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

        $html .= '
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-size: 20px;
        }

        .container {
            width: 100%;
            max-width: 9in;
            padding: 0.5in;
            box-sizing: border-box;
        }

        .header-and-right {
            display: flex;
            justify-content: space-between;
            /* Distributes space between the header and top-right */
            align-items: flex-start;
            gap: 0px;
            /* Aligns them at the top */
        }

        .header,
        .footer {
            margin-bottom: 0.2in;
        }

        .header h1,
        .header h2,
        .header p {
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: left;
        }

        .divider {
            border-top: 1px solid #000;
            margin: 0.2in 0;
        }

        .shipping-info,
        .order-info,
        .product-table {
            margin-bottom: 0.2in;
        }

        .product-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .product-table th,
        .product-table td {
            border: 1px solid #000;
            padding: 10px 10px;
            text-align: left;
        }

        .product-table th {
            background-color: #f2f2f2;
        }

        .text-right {
            text-align: right;
        }

        .subtotal,
        .tax,
        .total {
            font-weight: bold;
        }

        .subtotal,
        .tax {
            border-top: 1px solid #000;
        }

        .total {
            border-top: 2px solid #000;
        }

        .subtotal-table {
            width: 100%;
            text-align: right;
            border: none;
        }

        .subtotal-table td {
            padding: 0;
            border: none;
            /* Hides the borders */
        }

        .top-right {
            text-align: left;
            max-width: 295px;
        }

        .no-border-table {
            border-collapse: collapse;
            border: none;
            width: 100%;
        }

        .no-border-table td {
            border: none;
        }
    </style>
</head>

<body>
    <div class="container">
    ';

            $html .= '

            <div class="product-table">
            <p style="text-align: right;">' . getinvoicenumberid($Connect, $AmazonOrderId) . '</p> 
                <table> ';
            foreach ($rows as $row) {
                $html .= '
                        <tr>
                            <td>Quantity</td>
                            <td>' . $row['QuantityOrdered'] . '</td>
                        </tr>
                        <tr>
                            <td>Product Details</td>
                            <td>
                                ' . $row["Title"] . ' <br>
                                <strong>SKU:</strong> ' . $row["SellerSKU"] . ' <br>
                                <strong>ASIN:</strong> ' . $row["ASIN"] . ' <br> <img src="data:image/png;base64, ' . base64_encode($barcode) . '" alt="ASIN Barcode"> <br>
                                <strong>Condition:</strong> ' . $row["ConditionId"] . ' -
                                ' . $row["ConditionSubtypeId"] . '
                                <br>
                                <strong>Order Item ID:</strong> ' . $row["orderitemid"] . '
                                <br>

                                            <strong>P Code</strong>
                                            $ ' . convertNumberToCustomCode($row["ItemPrice"]) . '';

                $html .= '
                                            <br><strong>S Code</strong>
                                            $ ' . convertNumberToCustomCode($row["shippingPrice"]) . '';

                $html .= '
                                            <br><strong>L Code</strong>
                                            $ ' . convertNumberToCustomCode($LCode) . '';

                $html .= '
                            </td>
                        </tr>
                        ';

                $html .= '  <tr>
                    <td>
                    Note:        
                    </td>
                    <td>
                        ' . fetchNote($Connect, $row['AmazonOrderId'], $row['ProductID']) . '
                    </td>
                </tr>';


                if ($display_Price == 'TRUE') {
                    $html .= '

                        <tr>
                            <td style="width: 80px;">Order Total</td>
                            <td class="text-right" style="width:185px;">
                                <table class="subtotal-table">
                                    <tr style="font-size: 10;  height: 30px;">
                                        <td><strong>Item Price</strong></td>
                                        <td>$ ' . $row["ItemPrice"] . '</td>
                                    </tr>
                                    <tr style="font-size: 10;  height: 30px;">
                                        <td><strong>Item  Tax</strong></td>
                                        <td>$ ' . $row["ItemTax"] . '</td>
                                    </tr>
                                    <tr style="font-size: 10; border-bottom: 1px solid gray; height: 30px;">
                                        <td><strong>Shipping Price</strong></td>
                                        <td>$  ' . $row["shippingPrice"] . '</t d>
                                    </tr>
                                </table>
                            </td>
                        </tr>';
                    $width_format = 370;
                }

            }
            $html .= '
                </table>
            </div>
            ';

            $html .= '
    
<div class="header-and-right" style="page-break-before: always;">
<br>
<br>
            <!-- Left (Ship To) -->

                <div class="header">
                    <p style="text-align: right;">' . getinvoicenumberid($Connect, $AmazonOrderId) . '</p>  
                    <h1>Ship To:</h1>
                    <h2>' . ($row["ship_to_name"] ?? $row["costumer_name"]) . '</h2>
                    <h2>' . $address1 . '<br>' . $address2 . '</h2>
                </div>
            </td>
            
            <!-- Right (ASIN) -->
                <div class="top-right">';
            foreach ($rows as $row) {
                $internal = isset($row["ASIN"]) ? getInternalByASIN($Connect, ASIN: $row["ASIN"]) : "";
                $amazontitle = isset($row["Title"]) ? $row["Title"] : "";

                $html .= '<strong>' . $internal . '</strong> <br>';

                if (isset($amazontitle) && !empty($amazontitle)) {
                    $html .= '<strong>' . $amazontitle . '</strong> <br>';
                }
            }
            $html .= '
                </div>
</div>

            <div class="divider"></div>

            <div class="order-info">
                <strong>Order ID: ' . $row["AmazonOrderId"] . '</strong> <br>
                <img src="data:image/png;base64, ' . base64_encode($barcode_AmazonOrderId) . '" alt="Amazon Barcode"><br>
                <p>Thank you for buying from ' . $strname . ' on Amazon Marketplace.</p>
            </div>

<div class="shipping-info">
    <table>
        <tr>
            <td><strong>Billing Address:</strong></td>
            <td style="width: 135px;">Order Date:</td>
            <td>
                ' . $newdate . '
            </td>
        </tr>
        <tr>
            <td> ' . $row["costumer_name"] . '</td>
            <td style="width: 135px;">Ship by Date:</td>
            <td>
            ';// getLatestShipDate
            /*
                $shipDate_EarliestShipDate_Raw = getEarliestShipDate($Connect, $AmazonOrderId, $orderitemid);
                if ($shipDate_EarliestShipDate_Raw) {
                    $date_earliest = new DateTime($shipDate_EarliestShipDate_Raw, new DateTimeZone('UTC'));
                    $date_earliest->setTimezone(new DateTimeZone('America/Los_Angeles'));

                    $html .= ' ' . $date_earliest->format('D, F j, Y') . ' - ';
                }
                    */

            $shipDate_LatestShipDate_Raw = getLatestShipDate($Connect, $AmazonOrderId, $orderitemid);
            if ($shipDate_LatestShipDate_Raw) {
                $dateLatest = new DateTime($shipDate_LatestShipDate_Raw);

                $html .= ' ' . $dateLatest->format('D, F j, Y') . '';
            }

            $html .= '
            </td>
        </tr>
        <tr>
            <td> ' . $address1 . ' </td>
            <td style="width: 135px;">Ship Date:</td>
        <td>
            ';
            $shipDateRaw = getShipDate($Connect, $AmazonOrderId, $orderitemid);
            if ($shipDateRaw) {
                $date = new DateTime($shipDateRaw, new DateTimeZone('UTC'));
                $date->setTimezone(new DateTimeZone('America/Los_Angeles'));

                $html .= ' ' . $date->format('D, F j, Y') . ' ';
            }
            $html .= ' 
        </td>
        </tr>
        <tr>
            <td style="width: 245px;"> ' . $address2 . ' </td>
            <td>Deliver by Date:</td>
            <td>
                ' . $EarliestDelivery . ' - ' . $LatestDelivery . '
            </td>

        </tr>
        <tr>
            <td style="width: 245px;">  </td>
                        <td>Shipping Service:</td>
            <td> ' . $row["ShipmentServiceLevelCategory"] . '</td>

        </tr>
        <tr>
            <td style="width: 245px;">  </td>
                    <td>Seller Name:</td>
            <td>' . $strname . '</td>
        </tr>
</table>';

            $deliveryExperience = DeliveryExperience($Connect, $AmazonOrderId);

            if (
                strtoupper($signatureRequired) == "TRUE" ||
                (
                    $deliveryExperience == "DeliveryConfirmationWithSignature" ||
                    $deliveryExperience == "DeliveryConfirmationWithAdultSignature"
                )
            ) {
                $html .= '<div style="text-align: center;"><strong>Confirmation Services: Signature confirmation</strong></div>';
            }
            $html .= '
</div>
';

            $html .= '
                    <div style="text-align: right; transform: translate(-50px, 0px); font-size: 16px; color: #555;">
        - ' . htmlspecialchars($user) . '
    </div>';

        } else {

            $html .= '

<div class="header-and-right">
        <!-- Left (Ship To) -->

            <div class="header">
                <p style="text-align: right;">' . getinvoicenumberid($Connect, $AmazonOrderId) . '</p> 
                <h1>Ship To:</h1>
                    <h2>' . ($row["ship_to_name"] ?? $row["costumer_name"]) . '</h2>
                <h2>' . $address1 . '<br>' . $address2 . '</h2>
            </div>
        </td>
        
        <!-- Right (ASIN) -->
            <div class="top-right">';
            foreach ($rows as $row) {
                $internal = isset($row["ASIN"]) ? getInternalByASIN($Connect, $row["ASIN"]) : "";
                $amazontitle = isset($row["Title"]) ? $row["Title"] : "";

                $html .= '<strong>' . $internal . '</strong> <br>';

                if (isset($amazontitle) && !empty($amazontitle)) {
                    $html .= '<strong>' . $amazontitle . '</strong> <br>';
                }
            }
            $html .= '
            </div>
</div>

        <div class="divider"></div>

        <div class="order-info">
            <strong>Order ID: ' . $row["AmazonOrderId"] . '</strong> <br>
            <img src="data:image/png;base64, ' . base64_encode($barcode_AmazonOrderId) . '" alt="Amazon Barcode"><br>
            <p>Thank you for buying from ' . $strname . ' on Amazon Marketplace.</p>
        </div>

<div class="shipping-info">
    <table>
        <tr>
            <td><strong>Billing Address:</strong></td>
            <td style="width: 135px;">Order Date:</td>
            <td>
                ' . $newdate . '
            </td>
        </tr>
        <tr>
            <td> ' . $row["costumer_name"] . '</td>
            <td style="width: 135px;">Ship by Date:</td>
            <td>
            ';// getLatestShipDate

            /*
                $shipDate_EarliestShipDate_Raw = getEarliestShipDate($Connect, $AmazonOrderId, $orderitemid);
                if ($shipDate_EarliestShipDate_Raw) {
                    $date_earliest = new DateTime($shipDate_EarliestShipDate_Raw, new DateTimeZone('UTC'));
                    $date_earliest->setTimezone(new DateTimeZone('America/Los_Angeles'));

                    $html .= ' ' . $date_earliest->format('D, F j, Y') . ' - ';
                }
*/
            $shipDate_LatestShipDate_Raw = getLatestShipDate($Connect, $AmazonOrderId, $orderitemid);
            if ($shipDate_LatestShipDate_Raw) {
                $dateLatest = new DateTime($shipDate_LatestShipDate_Raw);

                $html .= ' ' . $dateLatest->format('D, F j, Y') . '';
            }

            $html .= '
            </td>
        </tr>
        <tr>
            <td> ' . $address1 . ' </td>
            <td style="width: 135px;">Ship Date:</td>
        <td>
            ';
            $shipDateRaw = getShipDate($Connect, $AmazonOrderId, $orderitemid);
            if ($shipDateRaw) {
                $date = new DateTime($shipDateRaw, new DateTimeZone('UTC'));
                $date->setTimezone(new DateTimeZone('America/Los_Angeles'));

                $html .= ' ' . $date->format('D, F j, Y') . ' ';
            }
            $html .= ' 
        </td>
        </tr>
        <tr>
            <td style="width: 245px;"> ' . $address2 . ' </td>
            <td>Deliver by Date:</td>
            <td>
                ' . $EarliestDelivery . ' - ' . $LatestDelivery . '
            </td>

        </tr>
        <tr>
            <td style="width: 245px;">  </td>
                        <td>Shipping Service:</td>
            <td> ' . $row["ShipmentServiceLevelCategory"] . '</td>

        </tr>
        <tr>
            <td style="width: 245px;">  </td>
                    <td>Seller Name:</td>
            <td>' . $strname . '</td>
        </tr>
</table>';

            $deliveryExperience = DeliveryExperience($Connect, $AmazonOrderId);

            if (
                strtoupper($signatureRequired) == "TRUE" ||
                (
                    $deliveryExperience == "DeliveryConfirmationWithSignature" ||
                    $deliveryExperience == "DeliveryConfirmationWithAdultSignature"
                )
            ) {
                $html .= '<div style="text-align: center;"><strong>Confirmation Services: Signature confirmation</strong></div>';
            }
            $html .= '
</div>
';

            $html .= '
                    <div style="text-align: right; transform: translate(-50px, 0px); font-size: 16px; color: #555;">
        - ' . htmlspecialchars($user) . '
    </div>';

            $html .= '

        <div class="product-table">
            <table> ';

            foreach ($rows as $row) {
                $html .= '
                    <tr>
                        <td>Quantity</td>
                        <td>' . $row['QuantityOrdered'] . '</td>
                    </tr>
                    <tr>
                        <td>Product Details</td>
                        <td>
                            ' . $row["Title"] . ' <br>
                            <strong>SKU:</strong> ' . $row["SellerSKU"] . ' <br>
                            <strong>ASIN:</strong> ' . $row["ASIN"] . ' <br> <img src="data:image/png;base64, ' . base64_encode($barcode) . '" alt="ASIN Barcode"> <br>
                            <strong>Condition:</strong> ' . $row["ConditionId"] . ' -
                            ' . $row["ConditionSubtypeId"] . '
                            <br>
                            <strong>Order Item ID:</strong> ' . $row["orderitemid"] . '
                            <br>

                                        <strong>P Code</strong>
                                        $ ' . convertNumberToCustomCode($row["ItemPrice"]) . '';

                $html .= '
                                        <br><strong>S Code</strong>
                                        $ ' . convertNumberToCustomCode($row["shippingPrice"]) . '';

                $html .= '
                                        <br><strong>L Code</strong>
                                        $ ' . convertNumberToCustomCode($LCode) . '';

                $html .= '
                        </td>
                    </tr>
                    ';

                $html .= '  <tr>
                <td>
                Note:        
                </td>
                <td>
                    ' . fetchNote($Connect, $row['AmazonOrderId'], $row['ProductID']) . '
                </td>
            </tr>';


                if ($display_Price == 'TRUE') {
                    $html .= '

                    <tr>
                        <td style="width: 80px;">Order Total</td>
                        <td class="text-right" style="width:185px;">
                            <table class="subtotal-table">
                                <tr style="font-size: 10;  height: 30px;">
                                    <td><strong>Item Price</strong></td>
                                    <td>$ ' . $row["ItemPrice"] . '</td>
                                </tr>
                                <tr style="font-size: 10;  height: 30px;">
                                    <td><strong>Item  Tax</strong></td>
                                    <td>$ ' . $row["ItemTax"] . '</td>
                                </tr>
                                <tr style="font-size: 10; border-bottom: 1px solid gray; height: 30px;">
                                    <td><strong>Shipping Price</strong></td>
                                    <td>$  ' . $row["shippingPrice"] . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>';
                    $width_format = 370;
                }

            }
            $html .= '
            </table>
        </div>
        ';

            $html .= '
                <div style="text-align: right; transform: translate(-50px, 0px); font-size: 16px; color: #555;">
    - ' . htmlspecialchars($user) . '
</div>';
        }

        $html .= '
    </div>
</body>

</html>
';

        return view('invoices.template', compact('orderData', 'settings', 'action'))->render();
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