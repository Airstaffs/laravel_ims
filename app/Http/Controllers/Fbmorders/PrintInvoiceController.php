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
        $platform_order_ids = $request->input('platform_order_ids', []);
        $action = $request->input('action', ''); // 'PrintInvoice' or 'ViewInvoice'
        $settings = $request->input('settings', ''); // could be the display price, test print, or signature required


        $results = [];

        foreach ($platform_order_ids as $platform_order_id) {
            $order = DB::table('tbloutboundorders')->where('platform_order_id', $platform_order_id)->first();
            $items = DB::table('tbloutboundordersitem')->where('platform_order_id', $platform_order_id)->get();

            if (!$order)
                continue;

            $orderData = (array) $order;
            $orderData['items'] = json_decode(json_encode($items), true); // ✅ force array for all items
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
                'pdf_url' => $pdfUrl
            ];
        }

        //return response($html)->header('Content-Type', 'text/html');

        return response()->json([
            'success' => true,
            'results' => $results,
            'orders' => $order,
            'items' => $items,
        ]);

    }

    protected function generateHtml($settings, $orderData, $action)
    {
        $itemCount = count($orderData['items']);

        $generator = new BarcodeGeneratorPNG();

        $barcode_AmazonOrderId = $generator->getBarcode($orderData["platform_order_id"], $generator::TYPE_CODE_128);
        $AddressLine1 = $orderData["address_line1"];
        $city = $orderData["city"];
        $stateOrRegion = $orderData["StateOrRegion"];
        $postalCode = $orderData["postal_code"];
        $countryCode = $orderData["CountryCode"];
        // $shipmentid = getshipmentid($orderData['trackingnumber']);

        // Logic to split the address based on the city
        $cityPosition = strpos($AddressLine1, $city);
        $address1 = ($cityPosition !== false) ? trim(substr($AddressLine1, 0, $cityPosition)) : $AddressLine1;
        $address2 = ($cityPosition !== false) ? trim(substr($AddressLine1, $cityPosition)) : "";

        if ($cityPosition !== false) {
            $address2 = trim(substr($AddressLine1, $cityPosition));
        } else {
            // If address2 is empty, create it using the additional variables
            $address2 = trim(string: $city . ', ' . $stateOrRegion . ' ' . $postalCode . ', ' . $countryCode);
        }

        $newdate = "";
        if (!empty($orderData["PurchaseDate"])) {
            $newdate = Carbon::parse($orderData["PurchaseDate"])->format('D, M j, Y');
        }

        if (!empty($orderData["EarliestDeliveryDate"])) {
            $date3 = new DateTime($orderData["EarliestDeliveryDate"]);
            $EarliestDelivery = $date3->format("D, M j, Y");
        }

        if (!empty($orderData["LatestShipDate"])) {
            $date4 = new DateTime($orderData["LatestDeliveryDate"]);
            $LatestDelivery = $date4->format("D, M j, Y");
        }

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

        if ($itemCount > 1) {
            $html .= '        <div class="product-table">';
            $html .= '            <p style="text-align: right;">' . getinvoicenumberid($orderData['platform_order_id']) . '</p>';
            $html .= '            <table>';

            foreach ($orderData['items'] as $item) {
                $html .= '                <tr>';
                $html .= '                    <td>Quantity</td>';
                $html .= '                    <td>' . $item['QuantityOrdered'] . '</td>';
                $html .= '                </tr>';
                $html .= '                <tr>';
                $html .= '                    <td>Product Details</td>';
                $html .= '                    <td>';
                $html .= '                        ' . $item["platform_title"] . ' <br>';
                $html .= '                        <strong>SKU:</strong> ' . $item["platform_sku"] . ' <br>';
                $asin = $item["platform_asin"] ?? '';
                $cleanAsin = preg_replace('/[^A-Za-z0-9]/', '', $asin); // Remove non-barcode-safe chars

                $html .= '                        <strong>ASIN:</strong> ' . htmlspecialchars($asin) . ' <br>';

                // Only render barcode if ASIN is valid
                if (!empty($cleanAsin)) {
                    try {
                        $barcode_ASIN = $generator->getBarcode($cleanAsin, $generator::TYPE_CODE_128);
                        $html .= '                        <img src="data:image/png;base64,' . base64_encode($barcode_ASIN) . '" alt="ASIN Barcode" style="height:40px;"> <br>';
                    } catch (Exception $e) {
                        $html .= '                        <em>Barcode generation failed.</em><br>';
                    }
                } else {
                    $html .= '                        <em>Invalid ASIN for barcode</em><br>';
                }

                $html .= '                        <strong>Condition:</strong> ' . $item["ConditionId"] . ' - ' . $item["ConditionSubtypeId"] . '<br>';
                $html .= '                        <strong>Order Item ID:</strong> ' . $item["platform_order_item_id"] . '<br>';
                $html .= '                        <strong>P Code:</strong> $' . convertNumberToCustomCode($item["unit_price"] ?? 00.00) . '<br>';
                $html .= '                        <strong>S Code:</strong> $' . convertNumberToCustomCode(isset($item["shippingPrice"]) ? $item["shippingPrice"] : 0.00) . '<br>';
                $html .= '                        <strong>L Code:</strong> $' . convertNumberToCustomCode($LCode ?? 00.00) . '';
                $html .= '                    </td>';
                $html .= '                </tr>';
                $html .= '                <tr>';
                $html .= '                    <td>Note:</td>';
                $html .= '                    <td>' . fetchNote($orderData['platform_order_id']) . '</td>';
                $html .= '                </tr>';

                if ($settings['displayPrice'] == 'TRUE') {
                    $html .= '                <tr>';
                    $html .= '                    <td style="width: 80px;">Order Total</td>';
                    $html .= '                    <td class="text-right" style="width:185px;">';
                    $html .= '                        <table class="subtotal-table">';
                    $html .= '                            <tr style="font-size: 10; height: 30px;">';
                    $html .= '                                <td><strong>Item Price</strong></td>';
                    $html .= '                                <td>$' . $item["unit_price"] . '</td>';
                    $html .= '                            </tr>';
                    $html .= '                            <tr style="font-size: 10; height: 30px;">';
                    $html .= '                                <td><strong>Item Tax</strong></td>';
                    $html .= '                                <td>$' . $item["unit_tax"] . '</td>';
                    $html .= '                            </tr>';
                    $html .= '                            <tr style="font-size: 10; border-bottom: 1px solid gray; height: 30px;">';
                    $html .= '                                <td><strong>Shipping Price</strong></td>';
                    $html .= '                                <td>$' . (isset($item["shippingPrice"]) ? number_format($item["shippingPrice"], 2) : '0.00') . '</td>';
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
            $html .= '                <p style="text-align: right;">' . getinvoicenumberid($orderData['platform_order_id']) . '</p>';
            $html .= '                <h1>Ship To:</h1>';
            $html .= '                <h2>' . ($row["ship_to_name"] ?? $orderData["BuyerName"]) . '</h2>';
            $html .= '                <h2>' . $orderData['address_line1'] . '<br>' . $orderData['city'] . ' ' . $orderData['city'] . ' ' . $orderData['StateOrRegion'] . ' ' . $orderData['postal_code'] . ' ' . $orderData['CountryCode'] . '</h2>';
            $html .= '            </div>';
            $html .= '            <div class="top-right">';

            foreach ($orderData['items'] as $item) {
                $internal = isset($item["platform_asin"]) ? getInternalByASIN($item["platform_asin"]) : "";
                $amazontitle = isset($item["platform_title"]) ? $item["platform_title"] : "";
                $html .= '                <strong>' . $internal . '</strong> <br>';

                if (!empty($amazontitle)) {
                    $html .= '                <strong>' . $amazontitle . '</strong> <br>';
                }
            }

            $html .= '            </div>';
            $html .= '        </div>';

            $html .= '        <div class="divider"></div>';

            $html .= '        <div class="order-info">';
            $html .= '            <strong>Order ID: ' . $orderData['platform_order_id'] . '</strong> <br>';
            $html .= '            <img src="data:image/png;base64,' . base64_encode($barcode_AmazonOrderId) . '" alt="Amazon Barcode"><br>';
            $html .= '            <p>Thank you for buying from ' . $orderData['items'][0]['storename'] . ' on Amazon Marketplace.</p>';
            $html .= '        </div>';

            $html .= '        <div class="shipping-info">';
            $html .= '            <table>';
            $html .= '                <tr>';
            $html .= '                    <td><strong>Billing Address:</strong></td>';
            $html .= '                    <td style="width: 135px;">Order Date:</td>';
            $html .= '                    <td>' . $newdate . '</td>';
            $html .= '                </tr>';
            $html .= '                <tr>';
            $html .= '                    <td>' . $orderData["BuyerName"] . '</td>';
            $html .= '                    <td style="width: 135px;">Ship by Date:</td>';
            $html .= '                    <td>';

            $shipDate_LatestShipDate_Raw = getLatestShipDate($orderData['platform_order_id'], $orderData['items'][0]['platform_order_item_id']);
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

            $shipDateRaw = getShipDate($orderData['platform_order_id'], $orderData['items'][0]['platform_order_item_id']);
            if ($shipDateRaw) {
                $date = new DateTime($shipDateRaw, new \DateTimeZone('UTC'));
                $date->setTimezone(new \DateTimeZone('America/Los_Angeles'));
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
            $html .= '                    <td>' . $orderData["ShipmentServiceLevelCategory"] . '</td>';
            $html .= '                </tr>';
            $html .= '                <tr>';
            $html .= '                    <td></td>';
            $html .= '                    <td>Seller Name:</td>';
            $html .= '                    <td>' . $orderData['items'][0]['storename'] . '</td>';
            $html .= '                </tr>';
            $html .= '            </table>';

            $deliveryExperience = DeliveryExperience($orderData['platform_order_id']);
            if (
                strtoupper($settings['signatureRequired']) == "TRUE" ||
                (
                    $deliveryExperience == "DeliveryConfirmationWithSignature" ||
                    $deliveryExperience == "DeliveryConfirmationWithAdultSignature"
                )
            ) {
                $html .= '            <div style="text-align: center;"><strong>Confirmation Services: Signature confirmation</strong></div>';
            }

            $html .= '        </div>';

            $html .= '        <div style="text-align: right; transform: translate(-50px, 0px); font-size: 16px; color: #555;">';
            $html .= '            - ' . htmlspecialchars($user ?? "usrawr");
            $html .= '        </div>';
        } else {
            $html .= '<div class="header-and-right">';
            $html .= '    <div class="header">';
            $html .= '        <p style="text-align: right;">' . getinvoicenumberid($orderData['platform_order_id']) . '</p>';
            $html .= '        <h1>Ship To:</h1>';
            $html .= '        <h2>' . ($row["ship_to_name"] ?? $orderData["BuyerName"]) . '</h2>';
            $html .= '        <h2>' . $address1 . '<br>' . $address2 . '</h2>';
            $html .= '    </div>';
            $html .= '    <div class="top-right">';

            foreach ($orderData['items'] as $item) {
                $internal = isset($item["platform_asin"]) ? getInternalByASIN($item["platform_asin"]) : "";
                $amazontitle = isset($item["platform_title"]) ? $item["platform_title"] : "";
                $html .= '                <strong>' . $internal . '</strong> <br>';

                if (!empty($amazontitle)) {
                    $html .= '                <strong>' . $amazontitle . '</strong> <br>';
                }
            }

            $html .= '    </div>';
            $html .= '</div>';

            $html .= '<div class="divider"></div>';

            $html .= '        <div class="order-info">';
            $html .= '            <strong>Order ID: ' . $orderData['platform_order_id'] . '</strong> <br>';
            $html .= '            <img src="data:image/png;base64,' . base64_encode($barcode_AmazonOrderId) . '" alt="Amazon Barcode"><br>';
            $html .= '            <p>Thank you for buying from ' . $orderData['items'][0]['storename'] . ' on Amazon Marketplace.</p>';
            $html .= '        </div>';

            $html .= '        <div class="shipping-info">';
            $html .= '            <table>';
            $html .= '                <tr>';
            $html .= '                    <td><strong>Billing Address:</strong></td>';
            $html .= '                    <td style="width: 135px;">Order Date:</td>';
            $html .= '                    <td>' . $newdate . '</td>';
            $html .= '                </tr>';
            $html .= '                <tr>';
            $html .= '                    <td>' . $orderData["BuyerName"] . '</td>';
            $html .= '                    <td style="width: 135px;">Ship by Date:</td>';
            $html .= '                    <td>';

            $shipDate_LatestShipDate_Raw = getLatestShipDate($orderData['platform_order_id'], $orderData['items'][0]['platform_order_item_id']);
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

            $shipDateRaw = getShipDate($orderData['platform_order_id'], $orderData['items'][0]['platform_order_item_id']);
            if ($shipDateRaw) {
                $date = new DateTime($shipDateRaw, new \DateTimeZone('UTC'));
                $date->setTimezone(new \DateTimeZone('America/Los_Angeles'));
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
            $html .= '                    <td>' . $orderData["ShipmentServiceLevelCategory"] . '</td>';
            $html .= '                </tr>';
            $html .= '                <tr>';
            $html .= '                    <td></td>';
            $html .= '                    <td>Seller Name:</td>';
            $html .= '                    <td>' . $orderData['items'][0]['storename'] . '</td>';
            $html .= '                </tr>';
            $html .= '            </table>';

            $deliveryExperience = DeliveryExperience($orderData['platform_order_id']);
            if (
                strtoupper($settings['signatureRequired']) == "TRUE" ||
                (
                    $deliveryExperience == "DeliveryConfirmationWithSignature" ||
                    $deliveryExperience == "DeliveryConfirmationWithAdultSignature"
                )
            ) {
                $html .= '            <div style="text-align: center;"><strong>Confirmation Services: Signature confirmation</strong></div>';
            }

            $html .= '</div>';

            $html .= '        <div style="text-align: right; transform: translate(-50px, 0px); font-size: 16px; color: #555;">';
            $html .= '            - ' . htmlspecialchars($user ?? "usrawr");
            $html .= '        </div>';

            $html .= '<div class="product-table">';
            $html .= '    <table>';
            foreach ($orderData['items'] as $item) {
                $html .= '                <tr>';
                $html .= '                    <td>Quantity</td>';
                $html .= '                    <td>' . $item['QuantityOrdered'] . '</td>';
                $html .= '                </tr>';
                $html .= '                <tr>';
                $html .= '                    <td>Product Details</td>';
                $html .= '                    <td>';
                $html .= '                        ' . $item["platform_title"] . ' <br>';
                $html .= '                        <strong>SKU:</strong> ' . $item["platform_sku"] . ' <br>';
                $asin = $item["platform_asin"] ?? '';
                $cleanAsin = preg_replace('/[^A-Za-z0-9]/', '', $asin); // Remove non-barcode-safe chars

                $html .= '                        <strong>ASIN:</strong> ' . htmlspecialchars($asin) . ' <br>';

                // Only render barcode if ASIN is valid
                if (!empty($cleanAsin)) {
                    try {
                        $barcode_ASIN = $generator->getBarcode($cleanAsin, $generator::TYPE_CODE_128);
                        $html .= '                        <img src="data:image/png;base64,' . base64_encode($barcode_ASIN) . '" alt="ASIN Barcode" style="height:40px;"> <br>';
                    } catch (Exception $e) {
                        $html .= '                        <em>Barcode generation failed.</em><br>';
                    }
                } else {
                    $html .= '                        <em>Invalid ASIN for barcode</em><br>';
                }


                $html .= '                        <strong>Condition:</strong> ' . $item["ConditionId"] . ' - ' . $item["ConditionSubtypeId"] . '<br>';
                $html .= '                        <strong>Order Item ID:</strong> ' . $item["platform_order_item_id"] . '<br>';
                $html .= '                        <strong>P Code:</strong> $' . convertNumberToCustomCode($item["unit_price"] ?? 00.00) . '<br>';
                $html .= '                        <strong>S Code:</strong> $' . convertNumberToCustomCode(isset($item["shippingPrice"]) ? $item["shippingPrice"] : 0.00) . '<br>';
                $html .= '                        <strong>L Code:</strong> $' . convertNumberToCustomCode($LCode ?? 00.00) . '';
                $html .= '                    </td>';
                $html .= '                </tr>';
                $html .= '                <tr>';
                $html .= '                    <td>Note:</td>';
                $html .= '                    <td>' . fetchNote($orderData['platform_order_id']) . '</td>';
                $html .= '                </tr>';
                if ($settings['displayPrice'] == 'TRUE') {
                    $html .= '                <tr>';
                    $html .= '                    <td style="width: 80px;">Order Total</td>';
                    $html .= '                    <td class="text-right" style="width:185px;">';
                    $html .= '                        <table class="subtotal-table">';
                    $html .= '                            <tr style="font-size: 10; height: 30px;">';
                    $html .= '                                <td><strong>Item Price</strong></td>';
                    $html .= '                                <td>$' . $item["unit_price"] . '</td>';
                    $html .= '                            </tr>';
                    $html .= '                            <tr style="font-size: 10; height: 30px;">';
                    $html .= '                                <td><strong>Item Tax</strong></td>';
                    $html .= '                                <td>$' . $item["unit_tax"] . '</td>';
                    $html .= '                            </tr>';
                    $html .= '                            <tr style="font-size: 10; border-bottom: 1px solid gray; height: 30px;">';
                    $html .= '                                <td><strong>Shipping Price</strong></td>';
                    $html .= '                                <td>$' . (isset($item["shippingPrice"]) ? number_format($item["shippingPrice"], 2) : '0.00') . '</td>';

                    $html .= '                            </tr>';
                    $html .= '                        </table>';
                    $html .= '                    </td>';
                    $html .= '                </tr>';
                }
            }
            $html .= '    </table>';
            $html .= '</div>';

            $html .= '<div style="text-align: right; transform: translate(-50px, 0px); font-size: 16px; color: #555;">';
            $html .= '            - ' . htmlspecialchars($user ?? "usrawr");
            $html .= '</div>';
        }

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

        // If sending ZPL only
        if (!$savetoprintserver || !file_exists($pdfFile)) {
            $response = Http::asForm()->post($printerIP, [
                'zpl' => $zplCode,
                'printerSelect' => $pIp,
            ]);

        } /*else {
      // If also sending the PDF file (with save mode)
      $response = Http::attach(
          'pdf_file',
          file_get_contents($pdfFile),
          basename($pdfFile)
      )
          ->asMultipart()
          ->post($printerIP, [
              ['name' => 'zpl', 'contents' => $zplCode],
              ['name' => 'printerSelect', 'contents' => $pIp],
              ['name' => 'savemode', 'contents' => 'ShipmentInvoice'],
          ]);
  }*/

        Log::info('Printer response:', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }


}