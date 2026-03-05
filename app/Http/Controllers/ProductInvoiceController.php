<?php

namespace App\Http\Controllers;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Str;

class ProductInvoiceController extends BasetablesController
{
public function index(Request $request)
{
    $validated = $request->validate([
        'productIds' => 'required|array',
    ]);

    $products = DB::table('tblproduct as p')
        ->whereIn('p.ProductID', $validated['productIds'])
        ->select(
            'p.ProductTitle',
            'p.price',
            'p.tax',
            'p.seller',
            DB::raw('SUM(p.quantity) as quantity'),
            DB::raw('p.price * SUM(p.quantity) as totalPrice'),
        )
        ->groupBy(
            'p.ProductTitle',
            'p.price',
            'p.tax',
            'p.seller',
        )
        ->get();

    $result = [
        [
            'name'     => 'Invoice',
            'products' => $products->map(fn($p) => [
                'ProductTitle' => $p->ProductTitle,
                'price'        => $p->price,
                'quantity'     => $p->quantity,
                'tax'          => $p->tax,
                'totalPrice'   => $p->totalPrice,
            ])->values(),
        ]
    ];

    return response()->json($result);
}

public function generatePdf(Request $request)
{
    $request->validate([
        'suppliers'        => 'required|array|min:1',
        'selectedTemplate' => 'nullable|string',
        'warrantyFrom'     => 'nullable',
        'warrantyFromUnit' => 'nullable|string',
        'warrantyTo'       => 'nullable',
        'warrantyToUnit'   => 'nullable|string',
        'title'            => 'nullable|string',
        'ownerWebsite'     => 'nullable|string',
        'ownerEmail'       => 'nullable|string',
        'ownerContact'     => 'nullable|string',
        'ownerAddress'     => 'nullable|string',
        'trackingNumber'   => 'nullable|string',
        'orderNumber'      => 'nullable|string',
        'billToName'       => 'nullable|string',
        'billToAddress1'   => 'nullable|string',
        'billToAddress2'   => 'nullable|string',
        'billToContact'    => 'nullable|string',
        'shipToName'       => 'nullable|string',
        'shipToAddress1'   => 'nullable|string',
        'shipToAddress2'   => 'nullable|string',
        'shipToContact'    => 'nullable|string',
        'shipToEmail'      => 'nullable|string',
    ]);

    $suppliers = $request->input('suppliers');
    foreach ($suppliers as &$supplier) {
        if (empty($supplier['paymentType'])) {
            $supplier['paymentType'] = 'Paypal';
        }
    }
    unset($supplier);

    $selectedTemplate = $request->input('selectedTemplate', 'template1');
    $warrantyFrom     = $request->input('warrantyFrom', 90);
    $warrantyFromUnit = $request->input('warrantyFromUnit', 'days');
    $warrantyTo       = $request->input('warrantyTo', 1);
    $warrantyToUnit   = $request->input('warrantyToUnit', 'years');
    $title            = $request->input('title', 'ALL RENEWED ELECTRONICS');
    $ownerWebsite     = $request->input('ownerWebsite', 'www.allrenewed.com');
    $ownerEmail       = $request->input('ownerEmail', 'sales@allrenewed.com');
    $ownerContact     = $request->input('ownerContact', '(415) 882-6949');
    $ownerAddress     = $request->input('ownerAddress', '4620 Northgate Blvd., Ste 180, Sacramento, CA 95834');
    $trackingNumber   = $request->input('trackingNumber', '');
    $orderNumber      = $request->input('orderNumber', '');
    $billToName       = $request->input('billToName', '');
    $billToAddress1   = $request->input('billToAddress1', '');
    $billToAddress2   = $request->input('billToAddress2', '');
    $billToContact    = $request->input('billToContact', '');
    $shipToName       = $request->input('shipToName', '');
    $shipToAddress1   = $request->input('shipToAddress1', '');
    $shipToAddress2   = $request->input('shipToAddress2', '');
    $shipToContact    = $request->input('shipToContact', '');
    $shipToEmail      = $request->input('shipToEmail', '');
    $invoiceDate      = now()->setTimezone('America/Los_Angeles')->format('m/d/Y');
    $dueDate          = now()->setTimezone('America/Los_Angeles')->addDays(15)->format('m/d/Y');
    $logoPath         = 'file://' . public_path('images/all-renewed-logo.png');

    $warrantyFromUpper = Str::upper($warrantyFromUnit);
    $warrantyToUpper   = Str::upper($warrantyToUnit);
    $warrantyFromLower = Str::lower($warrantyFromUnit);
    $warrantyToLower   = Str::lower($warrantyToUnit);

    $pages = '';
    foreach ($suppliers as $i => $supplier) {
        $isLast   = $i === count($suppliers) - 1;
        $products = $supplier['products'] ?? [];

        $subtotal  = collect($products)->sum('totalPrice');
        $taxRate   = $products[0]['tax'] ?? 0;
        $taxAmount = $subtotal * ($taxRate / 100);
        $total     = $subtotal + $taxAmount;

        // Product rows
        $productRows = '';
        foreach ($products as $ri => $p) {
            $altBg = $ri % 2 !== 0 ? '#f5f9ff' : '#ffffff';
            $productRows .= '<tr style="background:' . $altBg . ';">
                <td style="padding:8px 10px;border-bottom:1px solid #e5e5e5;width:35%;white-space:normal;word-break:break-word;">' . e($p['ProductTitle']) . '</td>
                <td style="padding:8px 10px;border-bottom:1px solid #e5e5e5;text-align:right;">' . e($p['quantity']) . '</td>
                <td style="padding:8px 10px;border-bottom:1px solid #e5e5e5;text-align:right;">$' . number_format($p['price'], 2) . '</td>
                <td style="padding:8px 10px;border-bottom:1px solid #e5e5e5;text-align:right;">$' . number_format($p['totalPrice'], 2) . '</td>
                <td style="padding:8px 10px;border-bottom:1px solid #e5e5e5;text-align:right;">' . e($p['tax']) . '%</td>
            </tr>';
        }
        for ($e = count($products); $e < 8; $e++) {
            $altBg = $e % 2 !== 0 ? '#f5f9ff' : '#ffffff';
            $productRows .= '<tr style="background:' . $altBg . ';"><td colspan="5" style="padding:8px 10px;border-bottom:1px solid #e5e5e5;">&nbsp;</td></tr>';
        }

        $pageBreak = $isLast ? '' : 'page-break-after:always;';
        $whiteLogo = $this->getWhiteLogoBase64();

        if ($selectedTemplate === 'template1') {
    $pages .= $this->template1(
        $supplier, $products, $productRows,
        $subtotal, $taxRate, $taxAmount, $total,
        $invoiceDate, $dueDate, $logoPath,
        $title,
        $warrantyFrom, $warrantyFromUpper,
        $warrantyTo,   $warrantyToUpper,
        $ownerWebsite, $ownerEmail, $ownerContact, $ownerAddress,
        $trackingNumber, $orderNumber,
        $billToName, $billToAddress1, $billToAddress2, $billToContact,
        $shipToName, $shipToAddress1, $shipToAddress2, $shipToContact, $shipToEmail,
        $pageBreak
    );
        } elseif ($selectedTemplate === 'template2') {
            $pages .= $this->template2(
                $supplier, $products, $productRows,
                $subtotal, $taxRate, $taxAmount, $total,
                $invoiceDate, $dueDate, $whiteLogo,
                $title,
                $warrantyFrom, $warrantyFromLower,
                $warrantyTo,   $warrantyToLower,
                $ownerWebsite, $ownerEmail, $ownerContact, $ownerAddress,
                $trackingNumber, $orderNumber,
                $billToName, $billToAddress1, $billToAddress2, $billToContact,
                $shipToName, $shipToAddress1, $shipToAddress2, $shipToContact, $shipToEmail,
                $pageBreak
            );
        } elseif ($selectedTemplate === 'template3') {
            $pages .= $this->template3(
                  $supplier, $products, $productRows,
                $subtotal, $taxRate, $taxAmount, $total,
                $invoiceDate, $dueDate, $logoPath,
                $title,
                $warrantyFrom, $warrantyFromLower,
                $warrantyTo,   $warrantyToLower,
                $ownerWebsite, $ownerEmail, $ownerContact, $ownerAddress,
                $trackingNumber, $orderNumber,
                $billToName, $billToAddress1, $billToAddress2, $billToContact,
                $shipToName, $shipToAddress1, $shipToAddress2, $shipToContact, $shipToEmail,
                $pageBreak
            );
        } elseif ($selectedTemplate === 'template4') {
            $pages .= $this->template4(
                 $supplier, $products, $productRows,
                $subtotal, $taxRate, $taxAmount, $total,
                $invoiceDate, $dueDate, $logoPath,
                $title,
                $warrantyFrom, $warrantyFromLower,
                $warrantyTo,   $warrantyToLower,
                $ownerWebsite, $ownerEmail, $ownerContact, $ownerAddress,
                $trackingNumber, $orderNumber,
                $billToName, $billToAddress1, $billToAddress2, $billToContact,
                $shipToName, $shipToAddress1, $shipToAddress2, $shipToContact, $shipToEmail,
                $pageBreak
            );
        } else {
            $pages .= $this->template5(
                 $supplier, $products, $productRows,
                $subtotal, $taxRate, $taxAmount, $total,
                $invoiceDate, $dueDate, $logoPath,
                $title,
                $warrantyFrom, $warrantyFromLower,
                $warrantyTo,   $warrantyToLower,
                $ownerWebsite, $ownerEmail, $ownerContact, $ownerAddress,
                $trackingNumber, $orderNumber,
                $billToName, $billToAddress1, $billToAddress2, $billToContact,
                $shipToName, $shipToAddress1, $shipToAddress2, $shipToContact, $shipToEmail,
                $pageBreak
            );
        }
    }

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <style>* { box-sizing: border-box; margin: 0; padding: 0; } body { font-family: Arial, sans-serif; font-size: 12px; color: #222; padding: 20px; }</style>
    </head><body>' . $pages . '</body></html>';

    $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait')
        ->setOption('margin_top', 15)
        ->setOption('margin_bottom', 15)
        ->setOption('margin_left', 15)
        ->setOption('margin_right', 15);

    return $pdf->download('invoice-' . now()->timestamp . '.pdf');
}

// ── Template 1: Dark/Gray ──────────────────────────────────────────────
private function template1(
    $s, $products, $productRows,
    $subtotal, $taxRate, $taxAmount, $total,
    $invoiceDate, $dueDate, $logoPath,
    $title, $wFrom, $wFromUnit, $wTo, $wToUnit,
    $website, $email, $contact, $address,
    $trackingNumber, $orderNumber,
    $billToName, $billToAddress1, $billToAddress2, $billToContact,
    $shipToName, $shipToAddress1, $shipToAddress2, $shipToContact, $shipToEmail,
    $pageBreak
) {
    // UPPERCASE singular/plural (template1 uses uppercase units)
    $wFromUnitText = $this->warrantyUnitText($wFrom, Str::upper($wFromUnit));
    $wToUnitText   = $this->warrantyUnitText($wTo,   Str::upper($wToUnit));

    // Bill To — fallback to placeholder like the Vue template
    $billToContactRow = $billToContact
        ? '<div>' . e($billToContact) . '</div>'
        : '';

    $billToLines = '
        <div>' . e($billToName     ?: "[Client's Name]") . '</div>
        <div>' . e($billToAddress1 ?: "[Client's Address Line 1]") . '</div>
        <div>' . e($billToAddress2 ?: "[Client's Address Line 2]") . '</div>
        ' . $billToContactRow;

    // Ship To — fallback to supplier fields like the Vue template
    $shipToLines = '
        <div>' . e($shipToName    ?: ($s['name']    ?? '')) . '</div>
        <div>' . e($shipToAddress1 ?: ($s['address1'] ?? '')) . '</div>
        <div>' . e($shipToAddress2 ?: ($s['address2'] ?? '')) . '</div>
        <div>' . e($shipToContact  ?: ($s['contact']  ?? '')) . '</div>
        <div>' . e($shipToEmail    ?: ($s['email']    ?? '')) . '</div>
        <div>' . e($s['websiteAddress'] ?? '') . '</div>';

    return '
    <div style="' . $pageBreak . '">

        <!-- Company Header -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border:1.5px solid #555;border-bottom:none;border-collapse:collapse;">
            <tr>
                <td style="width:90px;padding:10px 14px;border-right:1.5px solid #555;background:#fff;text-align:center;vertical-align:middle;">
                    <img src="' . $logoPath . '" style="width:55px;height:auto;filter:brightness(0);" />
                </td>
                <td style="background:#555;padding:10px 16px;text-align:center;vertical-align:middle;">
                    <div style="color:#fff;font-size:18px;font-weight:bold;letter-spacing:1px;">' . e($title) . '</div>
                    <div style="color:#ddd;font-size:11px;margin-top:4px;letter-spacing:0.5px;">' . e($wFrom) . ' ' . e($wFromUnitText) . ' TO ' . e($wTo) . ' ' . e($wToUnitText) . ' WARRANTY</div>
                </td>
            </tr>
        </table>
        <table cellspacing="0" cellpadding="0" style="width:100%;border:1.5px solid #555;border-top:1.5px solid #555;border-collapse:collapse;margin-bottom:20px;">
            <tr><td style="padding:4px 14px;font-size:11px;color:#333;border-bottom:1px solid #ccc;">' . e($website) . ' &nbsp;|&nbsp; ' . e($address) . '</td></tr>
            <tr><td style="padding:4px 14px;font-size:11px;color:#333;">' . e($email) . ' &nbsp;|&nbsp; ' . e($contact) . '</td></tr>
        </table>

        <!-- Invoice Header -->
        <table cellspacing="0" cellpadding="0" style="width:100%;margin-bottom:24px;">
            <tr>
                <td style="vertical-align:top;line-height:1.8;">
                    <div><strong>Tracking Number</strong> &nbsp;' . e($trackingNumber) . '</div>
                    <div><strong>Invoice Date</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $invoiceDate . '</div>
                    <div><strong>Due Date</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $dueDate . '</div>
                    <div><strong>Order Number</strong> &nbsp;&nbsp;&nbsp;' . e($orderNumber) . '</div>
                </td>
                <td style="text-align:right;vertical-align:top;font-size:36px;font-weight:bold;letter-spacing:2px;color:#111;">INVOICE</td>
            </tr>
        </table>
        <hr style="border:none;border-top:1px solid #aaa;margin-bottom:16px;" />

        <!-- Parties -->
        <table cellspacing="0" cellpadding="0" style="width:100%;margin-bottom:32px;">
            <tr>
                <td style="width:33%;vertical-align:top;padding-right:10px;">
                    <div style="font-weight:bold;font-size:11px;margin-bottom:6px;">BILL TO</div>
                    <div style="line-height:1.7;color:#444;">' . $billToLines . '</div>
                </td>
                <td style="width:33%;vertical-align:top;padding-right:10px;">
                    <div style="font-weight:bold;font-size:11px;margin-bottom:6px;">SHIP TO</div>
                    <div style="line-height:1.7;color:#444;">' . $shipToLines . '</div>
                </td>
                <td style="width:33%;vertical-align:top;">
                    <div style="font-weight:bold;font-size:11px;margin-bottom:6px;">PAYMENT DETAILS</div>
                    <div style="line-height:1.7;color:#444;">' . e($s['paymentType'] ?? 'Paypal') . '</div>
                </td>
            </tr>
        </table>

        <!-- Table -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;table-layout:fixed;margin-bottom:16px;">
            <thead>
                <tr style="background:#111;color:#fff;">
                    <th style="padding:8px 10px;font-size:11px;font-weight:bold;letter-spacing:0.5px;text-align:left;width:35%;color:#fff;">DESCRIPTION</th>
                    <th style="padding:8px 10px;font-size:11px;font-weight:bold;letter-spacing:0.5px;text-align:right;width:16%;color:#fff;">QTY</th>
                    <th style="padding:8px 10px;font-size:11px;font-weight:bold;letter-spacing:0.5px;text-align:right;width:16%;color:#fff;">UNIT PRICE</th>
                    <th style="padding:8px 10px;font-size:11px;font-weight:bold;letter-spacing:0.5px;text-align:right;width:16%;color:#fff;">SUBTOTAL</th>
                    <th style="padding:8px 10px;font-size:11px;font-weight:bold;letter-spacing:0.5px;text-align:right;width:17%;color:#fff;">TAX</th>
                </tr>
            </thead>
            <tbody>' . $productRows . '</tbody>
        </table>

        <!-- Totals -->
        <table cellspacing="0" cellpadding="0" style="width:260px;margin-left:auto;font-size:12px;margin-bottom:24px;">
            <tr>
                <td style="padding:4px 10px;color:#444;">Subtotal</td>
                <td style="padding:4px 10px;text-align:right;color:#444;">$' . number_format($subtotal, 2) . '</td>
            </tr>
            <tr>
                <td style="padding:4px 10px;color:#444;">Tax (' . $taxRate . '%)</td>
                <td style="padding:4px 10px;text-align:right;color:#444;">$' . number_format($taxAmount, 2) . '</td>
            </tr>
            <tr>
                <td style="padding:6px 10px;font-weight:bold;font-size:13px;border-top:2px solid #111;color:#111;">Total to Pay</td>
                <td style="padding:6px 10px;font-weight:bold;font-size:13px;border-top:2px solid #111;text-align:right;color:#111;">$' . number_format($total, 2) . '</td>
            </tr>
        </table>
        <hr style="border:none;border-top:1px solid #aaa;margin-bottom:16px;" />

        <!-- Footer -->
        <div style="background:#111;color:#fff;text-align:center;padding:12px;font-weight:bold;letter-spacing:1px;font-size:12px;">THANK YOU FOR YOUR BUSINESS!</div>

    </div>';
}

// ── Template 2: Navy Blue ──────────────────────────────────────────────
private function template2(
    $s, $products, $productRows,
    $subtotal, $taxRate, $taxAmount, $total,
    $invoiceDate, $dueDate, $logoPath,
    $title, $wFrom, $wFromUnit, $wTo, $wToUnit,
    $website, $email, $contact, $address,
    $trackingNumber, $orderNumber,
    $billToName, $billToAddress1, $billToAddress2, $billToContact,
    $shipToName, $shipToAddress1, $shipToAddress2, $shipToContact, $shipToEmail,
    $pageBreak
) {
    // Lowercase singular/plural (template2 uses lowercase units)
    $wFromUnitText = $this->warrantyUnitText($wFrom, Str::lower($wFromUnit));
    $wToUnitText   = $this->warrantyUnitText($wTo,   Str::lower($wToUnit));

    // Tracking / Order rows — only rendered when non-empty (v-if)
    $trackingRow = $trackingNumber
        ? '<div><strong>Tracking #</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . e($trackingNumber) . '</div>'
        : '';
    $orderRow = $orderNumber
        ? '<div><strong>Order #</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . e($orderNumber) . '</div>'
        : '';

    // Bill To — pure v-if, no fallback placeholders
    $billToLines = implode('', array_map(
        fn($v) => $v !== '' ? '<div>' . e($v) . '</div>' : '',
        [$billToName, $billToAddress1, $billToAddress2, $billToContact]
    ));

    // Ship To — pure v-if, no supplier fallback
    $shipToLines = implode('', array_map(
        fn($v) => $v !== '' ? '<div>' . e($v) . '</div>' : '',
        [$shipToName, $shipToAddress1, $shipToAddress2, $shipToContact, $shipToEmail]
    ));

    return '
    <div style="' . $pageBreak . '">

        <!-- Company Header -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;background:#1a3a5c;border-radius:4px 4px 0 0;overflow:hidden;min-height:90px;">
            <tr>
                <!-- Left: logo + brand -->
                <td style="width:45%;background:#1a3a5c;padding:16px 20px;vertical-align:middle;border-right:1px solid rgba(255,255,255,0.15);">
                    <table cellspacing="0" cellpadding="0"><tr>
                        <td style="vertical-align:middle;padding-right:14px;">
                            <img src="' . $logoPath . '" style="width:52px;height:auto;filter:brightness(0) invert(1);flex-shrink:0;" />
                        </td>
                        <td style="vertical-align:middle;">
                            <div style="color:#fff;font-size:15px;font-weight:bold;letter-spacing:0.5px;line-height:1.2;">' . e($title) . '</div>
                            <div style="margin-top:6px;">
                                <span style="display:inline-block;background:rgba(255,255,255,0.15);color:#c8dff5;font-size:10px;padding:3px 8px;border-radius:20px;border:1px solid rgba(255,255,255,0.25);letter-spacing:0.3px;white-space:nowrap;">
                                    ' . e($wFrom) . ' ' . e($wFromUnitText) . ' – ' . e($wTo) . ' ' . e($wToUnitText) . ' Warranty
                                </span>
                            </div>
                        </td>
                    </tr></table>
                </td>
                <!-- Right: contact info -->
                <td style="background:#223f5e;padding:14px 20px;vertical-align:middle;">
                    <div style="color:#c8dff5;font-size:11px;margin-bottom:5px;">' . e($website) . '</div>
                    <div style="color:#c8dff5;font-size:11px;margin-bottom:5px;">' . e($email) . '</div>
                    <div style="color:#c8dff5;font-size:11px;margin-bottom:5px;">' . e($contact) . '</div>
                    <div style="color:#c8dff5;font-size:11px;">' . e($address) . '</div>
                </td>
            </tr>
        </table>

        <!-- Accent bar -->
        <div style="height:4px;background:linear-gradient(90deg,#2980b9,#1abc9c);margin-bottom:20px;"></div>

        <!-- Invoice Header -->
        <table cellspacing="0" cellpadding="0" style="width:100%;margin-bottom:24px;">
            <tr>
                <td style="vertical-align:top;line-height:1.8;">
                    <div><strong>Invoice Date</strong> &nbsp;&nbsp;&nbsp;' . $invoiceDate . '</div>
                    <div><strong>Due Date</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $dueDate . '</div>
                    ' . $trackingRow . '
                    ' . $orderRow . '
                </td>
                <td style="text-align:right;vertical-align:top;font-size:36px;font-weight:bold;letter-spacing:2px;color:#1a3a5c;">INVOICE</td>
            </tr>
        </table>
        <hr style="border:none;border-top:1px solid #aaa;margin-bottom:16px;" />

        <!-- Parties -->
        <table cellspacing="0" cellpadding="0" style="width:100%;margin-bottom:32px;">
            <tr>
                <td style="width:33%;vertical-align:top;padding-right:10px;">
                    <div style="font-weight:bold;font-size:11px;margin-bottom:6px;color:#1a3a5c;letter-spacing:0.5px;">BILL TO</div>
                    <div style="line-height:1.7;color:#444;">' . $billToLines . '</div>
                </td>
                <td style="width:33%;vertical-align:top;padding-right:10px;">
                    <div style="font-weight:bold;font-size:11px;margin-bottom:6px;color:#1a3a5c;letter-spacing:0.5px;">SHIP TO</div>
                    <div style="line-height:1.7;color:#444;">' . $shipToLines . '</div>
                </td>
                <td style="width:33%;vertical-align:top;">
                    <div style="font-weight:bold;font-size:11px;margin-bottom:6px;color:#1a3a5c;letter-spacing:0.5px;">PAYMENT DETAILS</div>
                    <div style="line-height:1.7;color:#444;">' . e($s['paymentType'] ?? 'Paypal') . '</div>
                </td>
            </tr>
        </table>

        <!-- Table -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;table-layout:fixed;margin-bottom:16px;">
            <thead>
                <tr style="background:linear-gradient(90deg,#1a3a5c,#2980b9);">
                    <th style="padding:8px 10px;font-size:11px;font-weight:bold;letter-spacing:0.5px;text-align:left;width:35%;color:#fff;background-color: #1a3a5c">DESCRIPTION</th>
                    <th style="padding:8px 10px;font-size:11px;font-weight:bold;letter-spacing:0.5px;text-align:right;width:16%;color:#fff;background-color: #1a3a5c">QTY</th>
                    <th style="padding:8px 10px;font-size:11px;font-weight:bold;letter-spacing:0.5px;text-align:right;width:16%;color:#fff;background-color: #1a3a5c">UNIT PRICE</th>
                    <th style="padding:8px 10px;font-size:11px;font-weight:bold;letter-spacing:0.5px;text-align:right;width:16%;color:#fff;background-color: #1a3a5c">SUBTOTAL</th>
                    <th style="padding:8px 10px;font-size:11px;font-weight:bold;letter-spacing:0.5px;text-align:right;width:17%;color:#fff;background-color: #1a3a5c">TAX</th>
                </tr>
            </thead>
            <tbody>' . $productRows . '</tbody>
        </table>

        <!-- Totals -->
        <table cellspacing="0" cellpadding="0" style="width:260px;margin-left:auto;font-size:12px;margin-bottom:24px;">
            <tr>
                <td style="padding:4px 10px;color:#444;">Subtotal</td>
                <td style="padding:4px 10px;text-align:right;color:#444;">$' . number_format($subtotal, 2) . '</td>
            </tr>
            <tr>
                <td style="padding:4px 10px;color:#444;">Tax (' . $taxRate . '%)</td>
                <td style="padding:4px 10px;text-align:right;color:#444;">$' . number_format($taxAmount, 2) . '</td>
            </tr>
            <tr>
                <td style="padding:6px 10px;font-weight:bold;font-size:13px;border-top:2px solid #1a3a5c;color:#1a3a5c;">Total to Pay</td>
                <td style="padding:6px 10px;font-weight:bold;font-size:13px;border-top:2px solid #1a3a5c;text-align:right;color:#1a3a5c;">$' . number_format($total, 2) . '</td>
            </tr>
        </table>
        <hr style="border:none;border-top:1px solid #aaa;margin-bottom:0;" />

        <!-- Footer -->
        <table cellspacing="0" cellpadding="0" style="width:100%;background:linear-gradient(90deg,#1a3a5c,#2980b9);border-radius:0 0 4px 4px;">
            <tr>
                <td style="padding:12px 16px;color:#fff;font-weight:bold;letter-spacing:1px;font-size:11px;">THANK YOU FOR YOUR BUSINESS!</td>
                <td style="padding:12px 16px;text-align:right;color:#c8dff5;font-size:10px;font-weight:normal;letter-spacing:0.3px;">
                    ' . e($wFrom) . ' ' . e($wFromUnitText) . ' – ' . e($wTo) . ' ' . e($wToUnitText) . ' Warranty Included
                </td>
            </tr>
        </table>

    </div>';
}
// ── Template 3: Green ──────────────────────────────────────────────────
private function template3(
    $s, $products, $productRows,
    $subtotal, $taxRate, $taxAmount, $total,
    $invoiceDate, $dueDate, $logoPath,
    $title, $wFrom, $wFromUnit, $wTo, $wToUnit,
    $website, $email, $contact, $address,
    $trackingNumber, $orderNumber,
    $billToName, $billToAddress1, $billToAddress2, $billToContact,
    $shipToName, $shipToAddress1, $shipToAddress2, $shipToContact, $shipToEmail,
    $pageBreak
) {
    $wFromUnitText = $this->warrantyUnitText($wFrom, Str::lower($wFromUnit));
    $wToUnitText   = $this->warrantyUnitText($wTo,   Str::lower($wToUnit));

    $trackingCol = $trackingNumber ? '
        <td style="text-align:left;padding:0 16px 0 0;">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;margin-bottom:2px;">Tracking #</div>
            <div style="font-size:13px;font-weight:700;color:#111;">' . e($trackingNumber) . '</div>
        </td>
        <td style="width:1px;background:#d1fae5;padding:0;"><div style="width:1px;height:32px;background:#d1fae5;"></div></td>' : '';

    $orderCol = $orderNumber ? '
        <td style="text-align:left;padding:0 16px;">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;margin-bottom:2px;">Order #</div>
            <div style="font-size:13px;font-weight:700;color:#111;">' . e($orderNumber) . '</div>
        </td>
        <td style="width:1px;background:#d1fae5;padding:0;"><div style="width:1px;height:32px;background:#d1fae5;"></div></td>' : '';

    $orderLine = '';

    // Bill To lines (v-if — skip empty)
    $billToName     !== '' ? $billToNameRow     = '<div style="font-weight:700;font-size:13px;color:#111;margin-bottom:4px;">' . e($billToName) . '</div>'     : $billToNameRow = '';
    $billToAddress1 !== '' ? $billToAddr1Row    = '<div style="color:#6b7280;line-height:1.7;">' . e($billToAddress1) . '</div>'                                : $billToAddr1Row = '';
    $billToAddress2 !== '' ? $billToAddr2Row    = '<div style="color:#6b7280;line-height:1.7;">' . e($billToAddress2) . '</div>'                                : $billToAddr2Row = '';
    $billToContact  !== '' ? $billToContactRow  = '<div style="color:#6b7280;line-height:1.7;">' . e($billToContact) . '</div>'                                 : $billToContactRow = '';

    // Ship To lines (v-if — skip empty)
    $shipToName     !== '' ? $shipToNameRow     = '<div style="font-weight:700;font-size:13px;color:#111;margin-bottom:4px;">' . e($shipToName) . '</div>'      : $shipToNameRow = '';
    $shipToAddress1 !== '' ? $shipToAddr1Row    = '<div style="color:#6b7280;line-height:1.7;">' . e($shipToAddress1) . '</div>'                                : $shipToAddr1Row = '';
    $shipToAddress2 !== '' ? $shipToAddr2Row    = '<div style="color:#6b7280;line-height:1.7;">' . e($shipToAddress2) . '</div>'                                : $shipToAddr2Row = '';
    $shipToContact  !== '' ? $shipToContactRow  = '<div style="color:#6b7280;line-height:1.7;">' . e($shipToContact) . '</div>'                                 : $shipToContactRow = '';
    $shipToEmail    !== '' ? $shipToEmailRow    = '<div style="color:#6b7280;line-height:1.7;">' . e($shipToEmail) . '</div>'                                   : $shipToEmailRow = '';

    return '
    <div style="' . $pageBreak . '">

        <!-- Company Header -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;border:2px solid #059669;border-radius:6px 6px 0 0;overflow:hidden;">
            <!-- Top: logo + title + warranty -->
            <tr style="background:#fff;border-bottom:1px solid #d1fae5;">
                <td style="padding:14px 20px;vertical-align:middle;">
                    <table cellspacing="0" cellpadding="0"><tr>
                        <td style="padding-right:20px;vertical-align:middle;">
                            <img src="' . $logoPath . '" style="width:56px;height:auto;filter:brightness(0) saturate(100%) invert(29%) sepia(89%) saturate(500%) hue-rotate(120deg);" />
                        </td>
                        <td style="vertical-align:middle;padding-right:20px;">
                            <div style="width:1.5px;height:44px;background:#d1fae5;"></div>
                        </td>
                        <td style="vertical-align:middle;">
                            <div style="font-size:17px;font-weight:900;color:#065f46;letter-spacing:0.8px;text-transform:uppercase;">' . e($title) . '</div>
                            <div style="font-size:10.5px;color:#059669;font-weight:600;letter-spacing:0.4px;text-transform:uppercase;margin-top:4px;">' . e($wFrom) . ' ' . e($wFromUnitText) . ' to ' . e($wTo) . ' ' . e($wToUnitText) . ' Warranty</div>
                        </td>
                    </tr></table>
                </td>
            </tr>
            <!-- Bottom: contact row 1 -->
            <tr style="background:#f0fdf4;">
                <td style="padding:5px 20px;border-top:1px solid #d1fae5;">
                    <table cellspacing="0" cellpadding="0"><tr>
                        <td style="vertical-align:middle;padding-right:12px;">
                            <span style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;color:#059669;">Web</span>
                            <span style="font-size:10.5px;color:#374151;margin-left:5px;">' . e($website) . '</span>
                        </td>
                        <td style="vertical-align:middle;padding:0 12px;"><div style="width:1px;height:16px;background:#6ee7b7;"></div></td>
                        <td style="vertical-align:middle;padding:0 12px;">
                            <span style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;color:#059669;">Email</span>
                            <span style="font-size:10.5px;color:#374151;margin-left:5px;">' . e($email) . '</span>
                        </td>
                        <td style="vertical-align:middle;padding:0 12px;"><div style="width:1px;height:16px;background:#6ee7b7;"></div></td>
                        <td style="vertical-align:middle;padding-left:12px;">
                            <span style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;color:#059669;">Tel</span>
                            <span style="font-size:10.5px;color:#374151;margin-left:5px;">' . e($contact) . '</span>
                        </td>
                    </tr></table>
                </td>
            </tr>
            <!-- Bottom: contact row 2 (address) -->
            <tr style="background:#f0fdf4;">
                <td style="padding:5px 20px;border-top:1px solid #d1fae5;">
                    <span style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;color:#059669;">Address</span>
                    <span style="font-size:10.5px;color:#374151;margin-left:5px;">' . e($address) . '</span>
                </td>
            </tr>
        </table>

        <!-- Invoice Top Bar -->
        <div style="border-left:6px solid #059669;background:#fff;">
            <div style="height:5px;background:linear-gradient(90deg,#059669,#34d399);"></div>
            <!-- INVOICE title -->
            <div style="padding:20px 32px 8px;">
                <div style="font-size:38px;font-weight:900;letter-spacing:6px;color:#059669;line-height:1;">INVOICE</div>
            </div>
            <!-- Date row -->
            <table cellspacing="0" cellpadding="0" style="width:auto;padding:12px 32px 20px;border-top:1px solid #d1fae5;margin-top:0;">
                <tr>
                    ' . $trackingCol . '
                    ' . $orderCol . '
                    <td style="text-align:left;padding:0 16px 0 0;">
                        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;margin-bottom:2px;">Invoice Date</div>
                        <div style="font-size:13px;font-weight:700;color:#111;">' . $invoiceDate . '</div>
                    </td>
                    <td style="width:1px;background:#d1fae5;padding:0;"><div style="width:1px;height:32px;background:#d1fae5;"></div></td>
                    <td style="text-align:left;padding:0 0 0 16px;">
                        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;margin-bottom:2px;">Due Date</div>
                        <div style="font-size:13px;font-weight:700;color:#111;">' . $dueDate . '</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Parties -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;border-top:1px solid #d1fae5;border-bottom:1px solid #d1fae5;background:#f0fdf4;">
            <tr>
                <!-- Divider -->
                <td style="width:1px;background:#d1fae5;padding:4px 0;"></td>
                <!-- Bill To -->
                <td style="width:33%;padding:20px 16px;vertical-align:top;">
                    <div style="font-size:10px;font-weight:800;letter-spacing:1.5px;color:#059669;margin-bottom:8px;">BILL TO</div>
                    ' . $billToNameRow . $billToAddr1Row . $billToAddr2Row . $billToContactRow . '
                </td>
                <!-- Divider -->
                <td style="width:1px;background:#d1fae5;padding:4px 0;"></td>
                <!-- Ship To -->
                <td style="width:33%;padding:20px 16px;vertical-align:top;">
                    <div style="font-size:10px;font-weight:800;letter-spacing:1.5px;color:#059669;margin-bottom:8px;">SHIP TO</div>
                    ' . $shipToNameRow . $shipToAddr1Row . $shipToAddr2Row . $shipToContactRow . $shipToEmailRow . '
                </td>
                <!-- Divider -->
                <td style="width:1px;background:#d1fae5;padding:4px 0;"></td>
                <!-- Payment -->
                <td style="width:33%;padding:20px 16px;vertical-align:top;">
                    <div style="font-size:10px;font-weight:800;letter-spacing:1.5px;color:#059669;margin-bottom:8px;">PAYMENT DETAILS</div>
                    <div style="color:#6b7280;line-height:1.7;">' . e($s['paymentType'] ?? 'Paypal') . '</div>
                </td>
            </tr>
        </table>

        <!-- Table -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;table-layout:fixed;">
            <thead>
                <tr style="background:#ecfdf5;">
                    <th style="padding:10px 24px;font-size:11px;font-weight:800;letter-spacing:1px;color:#059669;border-bottom:2px solid #d1fae5;text-align:left;width:28%;">DESCRIPTION</th>
                    <th style="padding:10px 24px;font-size:11px;font-weight:800;letter-spacing:1px;color:#059669;border-bottom:2px solid #d1fae5;text-align:right;width:12%;">QTY</th>
                    <th style="padding:10px 24px;font-size:11px;font-weight:800;letter-spacing:1px;color:#059669;border-bottom:2px solid #d1fae5;text-align:right;width:17.67%;">UNIT PRICE</th>
                    <th style="padding:10px 24px;font-size:11px;font-weight:800;letter-spacing:1px;color:#059669;border-bottom:2px solid #d1fae5;text-align:right;width:17.67%;">SUBTOTAL</th>
                    <th style="padding:10px 24px;font-size:11px;font-weight:800;letter-spacing:1px;color:#059669;border-bottom:2px solid #d1fae5;text-align:right;width:12%;">TAX</th>
                </tr>
            </thead>
            <tbody>' . $productRows . '</tbody>
        </table>

        <!-- Totals -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-top:2px solid #d1fae5;background:#f0fdf4;">
            <tr>
                <td style="padding:16px 24px;" align="right">
                    <table cellspacing="0" cellpadding="0" style="width:280px;">
                        <tr>
                            <td style="padding:6px 10px;background:#059669;color:#fff;font-weight:600;font-size:12px;border-radius:4px 0 0 4px;">Subtotal</td>
                            <td style="padding:6px 10px;background:#059669;color:#fff;font-weight:600;font-size:12px;text-align:right;border-radius:0 4px 4px 0;">$' . number_format($subtotal, 2) . '</td>
                        </tr>
                        <tr><td colspan="2" style="height:6px;"></td></tr>
                        <tr>
                            <td style="padding:6px 10px;background:#059669;color:#fff;font-weight:600;font-size:12px;border-radius:4px 0 0 4px;">Tax (' . $taxRate . '%)</td>
                            <td style="padding:6px 10px;background:#059669;color:#fff;font-weight:600;font-size:12px;text-align:right;border-radius:0 4px 4px 0;">$' . number_format($taxAmount, 2) . '</td>
                        </tr>
                        <tr><td colspan="2" style="height:10px;"></td></tr>
                        <tr>
                            <td style="padding:10px 12px;background:#065f46;color:#fff;font-weight:800;font-size:13px;border-radius:6px 0 0 6px;">Total to Pay</td>
                            <td style="padding:10px 12px;background:#065f46;color:#fff;font-weight:800;font-size:13px;text-align:right;border-radius:0 6px 6px 0;">$' . number_format($total, 2) . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Footer -->
        <table cellspacing="0" cellpadding="0" style="width:100%;background:#059669;">
            <tr>
                <td style="padding:12px 20px;color:#fff;font-weight:800;letter-spacing:2px;font-size:11px;">THANK YOU FOR YOUR BUSINESS!</td>
                <td style="padding:12px 20px;text-align:right;color:#d1fae5;font-size:10px;font-weight:600;letter-spacing:0.5px;">' . e($wFrom) . ' ' . e($wFromUnitText) . ' – ' . e($wTo) . ' ' . e($wToUnitText) . ' Warranty</td>
            </tr>
        </table>

    </div>';
}

// ── Template 4: Red/Rose ───────────────────────────────────────────────
private function template4(
    $s, $products, $productRows,
    $subtotal, $taxRate, $taxAmount, $total,
    $invoiceDate, $dueDate, $logoPath,
    $title, $wFrom, $wFromUnit, $wTo, $wToUnit,
    $website, $email, $contact, $address,
    $trackingNumber, $orderNumber,
    $billToName, $billToAddress1, $billToAddress2, $billToContact,
    $shipToName, $shipToAddress1, $shipToAddress2, $shipToContact, $shipToEmail,
    $pageBreak
) {
    $wFromUnitText = $this->warrantyUnitText($wFrom, Str::lower($wFromUnit));
    $wToUnitText   = $this->warrantyUnitText($wTo,   Str::lower($wToUnit));

    // Conditional header date columns
    $trackingCol = $trackingNumber ? '
        <td style="text-align:right;padding:0 0 0 20px;">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;margin-bottom:2px;">Tracking #</div>
            <div style="font-size:12px;font-weight:700;color:#111;">' . e($trackingNumber) . '</div>
        </td>' : '';

    $orderCol = $orderNumber ? '
        <td style="text-align:right;padding:0 0 0 20px;">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;margin-bottom:2px;">Order #</div>
            <div style="font-size:12px;font-weight:700;color:#111;">' . e($orderNumber) . '</div>
        </td>' : '';

    // Bill To lines (v-if — skip empty)
    $billToNameRow    = $billToName     !== '' ? '<div style="font-weight:700;font-size:12px;color:#111;margin-bottom:4px;">'  . e($billToName)     . '</div>' : '';
    $billToAddr1Row   = $billToAddress1 !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($billToAddress1) . '</div>' : '';
    $billToAddr2Row   = $billToAddress2 !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($billToAddress2) . '</div>' : '';
    $billToContactRow = $billToContact  !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($billToContact)  . '</div>' : '';

    // Ship To lines (v-if — skip empty)
    $shipToNameRow    = $shipToName     !== '' ? '<div style="font-weight:700;font-size:12px;color:#111;margin-bottom:4px;">'  . e($shipToName)     . '</div>' : '';
    $shipToAddr1Row   = $shipToAddress1 !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($shipToAddress1) . '</div>' : '';
    $shipToAddr2Row   = $shipToAddress2 !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($shipToAddress2) . '</div>' : '';
    $shipToContactRow = $shipToContact  !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($shipToContact)  . '</div>' : '';
    $shipToEmailRow   = $shipToEmail    !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($shipToEmail)    . '</div>' : '';

    return '
    <div style="' . $pageBreak . '">

        <!-- Company Header -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;overflow:hidden;">
            <!-- Top: logo + title + warranty -->
            <tr style="background:#fff3f5;border-bottom:1px solid #ffe4e6;">
                <td style="padding:16px 24px;vertical-align:middle;">
                    <table cellspacing="0" cellpadding="0"><tr>
                        <td style="padding-right:16px;vertical-align:middle;">
                            <img src="' . $logoPath . '" style="width:50px;height:auto;filter:brightness(0) saturate(100%) invert(17%) sepia(95%) saturate(2000%) hue-rotate(336deg) brightness(90%);" />
                        </td>
                        <td style="vertical-align:middle;">
                            <div style="font-size:18px;font-weight:900;color:#9f1239;letter-spacing:1px;text-transform:uppercase;line-height:1;">' . e($title) . '</div>
                            <div style="font-size:10px;font-weight:700;color:#e11d48;text-transform:uppercase;letter-spacing:0.5px;margin-top:4px;">' . e($wFrom) . ' ' . e($wFromUnitText) . ' &mdash; ' . e($wTo) . ' ' . e($wToUnitText) . ' Warranty</div>
                        </td>
                    </tr></table>
                </td>
            </tr>
            <!-- Bottom: contact bar -->
            <tr style="background:#e11d48;">
                <td style="padding:8px 24px;vertical-align:middle;">
                    <table cellspacing="0" cellpadding="0" style="width:100%;"><tr>
                        <td style="font-size:10.5px;color:#fff;">' . e($website) . '</td>
                        <td style="width:1px;padding:0 12px;"><div style="width:1px;height:14px;background:rgba(255,255,255,0.4);"></div></td>
                        <td style="font-size:10.5px;color:#fff;">' . e($email) . '</td>
                        <td style="width:1px;padding:0 12px;"><div style="width:1px;height:14px;background:rgba(255,255,255,0.4);"></div></td>
                        <td style="font-size:10.5px;color:#fff;">' . e($contact) . '</td>
                        <td style="width:1px;padding:0 12px;"><div style="width:1px;height:14px;background:rgba(255,255,255,0.4);"></div></td>
                        <td style="font-size:10.5px;color:#fff;">' . e($address) . '</td>
                    </tr></table>
                </td>
            </tr>
        </table>

        <!-- Invoice Header -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;background:#fff3f5;border-bottom:3px solid #e11d48;">
            <tr>
                <!-- Left: red bar + INVOICE title -->
                <td style="vertical-align:middle;">
                    <table cellspacing="0" cellpadding="0"><tr>
                        <td style="width:8px;background:#e11d48;padding:0;">&nbsp;</td>
                        <td style="padding:24px 20px;vertical-align:middle;">
                            <div style="font-size:36px;font-weight:900;letter-spacing:5px;color:#e11d48;line-height:1;">INVOICE</div>
                        </td>
                    </tr></table>
                </td>
                <!-- Right: tracking / order / issued / due -->
                <td style="padding:20px 24px;text-align:right;vertical-align:middle;">
                    <table cellspacing="0" cellpadding="0" style="margin-left:auto;"><tr>
                        ' . $trackingCol . '
                        ' . $orderCol . '
                        <td style="text-align:right;padding:0 0 0 20px;">
                            <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;margin-bottom:2px;">Issued</div>
                            <div style="font-size:12px;font-weight:700;color:#111;">' . $invoiceDate . '</div>
                        </td>
                        <td style="text-align:right;padding:0 0 0 20px;">
                            <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;margin-bottom:2px;">Due</div>
                            <div style="font-size:12px;font-weight:700;color:#111;">' . $dueDate . '</div>
                        </td>
                    </tr></table>
                </td>
            </tr>
        </table>

        <!-- Info Strip -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;border-bottom:1px solid #ffe4e6;">
            <tr>
                <!-- Bill To -->
                <td style="width:33%;padding:20px 16px 20px 24px;vertical-align:top;">
                    <div style="font-size:9px;font-weight:900;letter-spacing:2px;color:#e11d48;text-transform:uppercase;margin-bottom:6px;">BILL TO</div>
                    ' . $billToNameRow . $billToAddr1Row . $billToAddr2Row . $billToContactRow . '
                </td>
                <td style="width:1px;background:#ffe4e6;padding:0;"></td>
                <!-- Ship To -->
                <td style="width:33%;padding:20px 16px;vertical-align:top;">
                    <div style="font-size:9px;font-weight:900;letter-spacing:2px;color:#e11d48;text-transform:uppercase;margin-bottom:6px;">SHIP TO</div>
                    ' . $shipToNameRow . $shipToAddr1Row . $shipToAddr2Row . $shipToContactRow . $shipToEmailRow . '
                </td>
                <td style="width:1px;background:#ffe4e6;padding:0;"></td>
                <!-- Payment -->
                <td style="width:33%;padding:20px 24px 20px 16px;vertical-align:top;">
                    <div style="font-size:9px;font-weight:900;letter-spacing:2px;color:#e11d48;text-transform:uppercase;margin-bottom:6px;">PAYMENT</div>
                    <div style="font-size:11px;color:#6b7280;line-height:1.7;">' . e($s['paymentType'] ?? 'Paypal') . '</div>
                </td>
            </tr>
        </table>

        <!-- Table -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;table-layout:fixed;">
            <thead>
                <tr style="background:#e11d48;">
                    <th style="padding:10px 16px;font-size:10px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;text-align:left;width:35%;">DESCRIPTION</th>
                    <th style="padding:10px 16px;font-size:10px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;text-align:right;width:10%;">QTY</th>
                    <th style="padding:10px 16px;font-size:10px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;text-align:right;width:22%;">UNIT PRICE</th>
                    <th style="padding:10px 16px;font-size:10px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;text-align:right;width:22%;">SUBTOTAL</th>
                    <th style="padding:10px 16px;font-size:10px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;text-align:right;width:11%;">TAX</th>
                </tr>
            </thead>
            <tbody>' . $productRows . '</tbody>
        </table>

        <!-- Footer -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;border-top:2px solid #ffe4e6;">
            <tr>
                <!-- Left: thank you + warranty -->
                <td style="padding:20px 24px;vertical-align:middle;">
                    <div style="font-size:13px;font-weight:800;color:#e11d48;letter-spacing:1px;text-transform:uppercase;">Thank you for your business!</div>
                    <div style="font-size:10.5px;color:#9ca3af;margin-top:4px;">' . e($wFrom) . ' ' . e($wFromUnitText) . ' – ' . e($wTo) . ' ' . e($wToUnitText) . ' Warranty Included</div>
                </td>
                <!-- Right: totals -->
                <td style="padding:20px 24px;vertical-align:middle;" align="right">
                    <table cellspacing="0" cellpadding="0" style="min-width:220px;">
                        <tr>
                            <td style="font-size:12px;color:#6b7280;padding:3px 0;border-bottom:1px dashed #ffe4e6;">Subtotal</td>
                            <td style="font-size:12px;color:#6b7280;padding:3px 0;border-bottom:1px dashed #ffe4e6;text-align:right;">$' . number_format($subtotal, 2) . '</td>
                        </tr>
                        <tr>
                            <td style="font-size:12px;color:#6b7280;padding:3px 0;border-bottom:1px dashed #ffe4e6;">Tax (' . $taxRate . '%)</td>
                            <td style="font-size:12px;color:#6b7280;padding:3px 0;border-bottom:1px dashed #ffe4e6;text-align:right;">$' . number_format($taxAmount, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-top:6px;">
                                <table cellspacing="0" cellpadding="0" style="width:100%;background:#e11d48;border-radius:6px;">
                                    <tr>
                                        <td style="padding:8px 12px;color:#fff;font-weight:800;font-size:13px;">Total to Pay</td>
                                        <td style="padding:8px 12px;color:#fff;font-weight:800;font-size:16px;text-align:right;">$' . number_format($total, 2) . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

    </div>';
}
// ── Template 5: Blue ───────────────────────────────────────────────────
private function template5(
    $s, $products, $productRows,
    $subtotal, $taxRate, $taxAmount, $total,
    $invoiceDate, $dueDate, $logoPath,
    $title, $wFrom, $wFromUnit, $wTo, $wToUnit,
    $website, $email, $contact, $address,
    $trackingNumber, $orderNumber,
    $billToName, $billToAddress1, $billToAddress2, $billToContact,
    $shipToName, $shipToAddress1, $shipToAddress2, $shipToContact, $shipToEmail,
    $pageBreak
) {
    $wFromUnitText = $this->warrantyUnitText($wFrom, Str::lower($wFromUnit));
    $wToUnitText   = $this->warrantyUnitText($wTo,   Str::lower($wToUnit));

    // Meta strip — tracking/order only when non-empty
    $trackingMeta = $trackingNumber ? '
        <td style="padding-right:24px;vertical-align:middle;">
            <div style="font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#3b82f6;font-weight:700;">Tracking #</div>
            <div style="font-size:13px;font-weight:800;color:#1e3a8a;">' . e($trackingNumber) . '</div>
        </td>' : '';

    $orderMeta = $orderNumber ? '
        <td style="padding-right:24px;vertical-align:middle;">
            <div style="font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#3b82f6;font-weight:700;">Order #</div>
            <div style="font-size:13px;font-weight:800;color:#1e3a8a;">' . e($orderNumber) . '</div>
        </td>' : '';

    // Bill To lines (skip empty)
    $billToNameRow    = $billToName     !== '' ? '<div style="font-weight:700;font-size:12px;color:#111;margin-bottom:3px;">'  . e($billToName)     . '</div>' : '';
    $billToAddr1Row   = $billToAddress1 !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($billToAddress1) . '</div>' : '';
    $billToAddr2Row   = $billToAddress2 !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($billToAddress2) . '</div>' : '';
    $billToContactRow = $billToContact  !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($billToContact)  . '</div>' : '';

    // Ship To lines (skip empty)
    $shipToNameRow    = $shipToName     !== '' ? '<div style="font-weight:700;font-size:12px;color:#111;margin-bottom:3px;">'  . e($shipToName)     . '</div>' : '';
    $shipToAddr1Row   = $shipToAddress1 !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($shipToAddress1) . '</div>' : '';
    $shipToAddr2Row   = $shipToAddress2 !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($shipToAddress2) . '</div>' : '';
    $shipToContactRow = $shipToContact  !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($shipToContact)  . '</div>' : '';
    $shipToEmailRow   = $shipToEmail    !== '' ? '<div style="font-size:11px;color:#6b7280;line-height:1.7;">'                 . e($shipToEmail)    . '</div>' : '';

    return '
    <div style="' . $pageBreak . '">

        <!-- Company Header -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;background:#1e3a8a;border-bottom:4px solid #3b82f6;">
            <tr>
                <!-- Logo box -->
                <td style="width:90px;padding:18px 20px;background:#fff;border-right:4px solid #3b82f6;vertical-align:middle;text-align:center;">
                    <img src="' . $logoPath . '" style="width:52px;height:auto;filter:brightness(0) saturate(100%) invert(21%) sepia(96%) saturate(1200%) hue-rotate(213deg);" />
                </td>
                <!-- Brand info -->
                <td style="padding:14px 20px;vertical-align:middle;">
                    <div style="font-size:17px;font-weight:900;color:#fff;letter-spacing:1px;text-transform:uppercase;">' . e($title) . '</div>
                    <div style="font-size:10px;color:#93c5fd;font-weight:600;letter-spacing:0.4px;text-transform:uppercase;margin-top:3px;">' . e($wFrom) . ' ' . e($wFromUnitText) . ' &mdash; ' . e($wTo) . ' ' . e($wToUnitText) . ' Warranty</div>
                    <div style="font-size:10px;color:#bfdbfe;margin-top:4px;">' . e($website) . ' &nbsp;<span style="color:#3b82f6;">|</span>&nbsp; ' . e($email) . ' &nbsp;<span style="color:#3b82f6;">|</span>&nbsp; ' . e($contact) . '</div>
                    <div style="font-size:10px;color:#93c5fd;margin-top:2px;">' . e($address) . '</div>
                </td>
                <!-- Watermark INVOICE text -->
                <td style="padding:0 28px;vertical-align:middle;text-align:center;border-left:1px solid rgba(255,255,255,0.1);">
                    <div style="font-size:32px;font-weight:900;letter-spacing:8px;color:rgba(255,255,255,0.6);text-transform:uppercase;">INVOICE</div>
                </td>
            </tr>
        </table>

        <!-- Meta strip -->
        <table cellspacing="0" cellpadding="0" style="width:100%;background:#eff6ff;border-bottom:2px solid #bfdbfe;">
            <tr>
                <td style="padding:10px 20px;vertical-align:middle;">
                    <table cellspacing="0" cellpadding="0"><tr>
                        ' . $trackingMeta . '
                        ' . $orderMeta . '
                        <!-- spacer -->
                        <td style="width:100%;"></td>
                        <!-- Invoice Date -->
                        <td style="padding-right:20px;vertical-align:middle;text-align:right;">
                            <div style="font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#3b82f6;font-weight:700;">Invoice Date</div>
                            <div style="font-size:13px;font-weight:800;color:#1e3a8a;">' . $invoiceDate . '</div>
                        </td>
                        <td style="padding:0 4px;vertical-align:middle;"><div style="width:1px;height:30px;background:#bfdbfe;"></div></td>
                        <!-- Due Date -->
                        <td style="padding-left:20px;vertical-align:middle;text-align:right;">
                            <div style="font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#3b82f6;font-weight:700;">Due Date</div>
                            <div style="font-size:13px;font-weight:800;color:#1e3a8a;">' . $dueDate . '</div>
                        </td>
                    </tr></table>
                </td>
            </tr>
        </table>

        <!-- Parties -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;border-bottom:2px solid #bfdbfe;">
            <tr>
                <td style="width:33%;padding:16px 20px;vertical-align:top;border-right:1px solid #dbeafe;">
                    <div style="font-size:9px;font-weight:900;letter-spacing:2px;color:#1d4ed8;text-transform:uppercase;margin-bottom:6px;padding-bottom:5px;border-bottom:2px solid #bfdbfe;">BILL TO</div>
                    ' . $billToNameRow . $billToAddr1Row . $billToAddr2Row . $billToContactRow . '
                </td>
                <td style="width:33%;padding:16px 20px;vertical-align:top;border-right:1px solid #dbeafe;">
                    <div style="font-size:9px;font-weight:900;letter-spacing:2px;color:#1d4ed8;text-transform:uppercase;margin-bottom:6px;padding-bottom:5px;border-bottom:2px solid #bfdbfe;">SHIP TO</div>
                    ' . $shipToNameRow . $shipToAddr1Row . $shipToAddr2Row . $shipToContactRow . $shipToEmailRow . '
                </td>
                <td style="width:33%;padding:16px 20px;vertical-align:top;">
                    <div style="font-size:9px;font-weight:900;letter-spacing:2px;color:#1d4ed8;text-transform:uppercase;margin-bottom:6px;padding-bottom:5px;border-bottom:2px solid #bfdbfe;">PAYMENT</div>
                    <div style="font-size:11px;color:#6b7280;line-height:1.7;">' . e($s['paymentType'] ?? 'Paypal') . '</div>
                </td>
            </tr>
        </table>

        <!-- Table -->
        <table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;table-layout:fixed;">
            <thead>
                <tr style="background:#1d4ed8;">
                    <th style="padding:10px 16px;font-size:10px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;text-align:left;width:35%;">DESCRIPTION</th>
                    <th style="padding:10px 16px;font-size:10px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;text-align:right;width:10%;">QTY</th>
                    <th style="padding:10px 16px;font-size:10px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;text-align:right;width:20%;">UNIT PRICE</th>
                    <th style="padding:10px 16px;font-size:10px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;text-align:right;width:20%;">SUBTOTAL</th>
                    <th style="padding:10px 16px;font-size:10px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;text-align:right;width:15%;">TAX</th>
                </tr>
            </thead>
            <tbody>' . $productRows . '</tbody>
        </table>

        <!-- Footer -->
        <table cellspacing="0" cellpadding="0" style="width:100%;background:#1e3a8a;">
            <tr>
                <!-- Left: thank you + warranty -->
                <td style="padding:16px 20px;vertical-align:middle;">
                    <div style="font-size:12px;font-weight:900;color:#fff;letter-spacing:1.5px;text-transform:uppercase;">THANK YOU FOR YOUR BUSINESS!</div>
                    <div style="font-size:10px;color:#93c5fd;margin-top:4px;">' . e($wFrom) . ' ' . e($wFromUnitText) . ' &mdash; ' . e($wTo) . ' ' . e($wToUnitText) . ' Warranty Included</div>
                </td>
                <!-- Right: totals -->
                <td style="padding:16px 20px;vertical-align:middle;" align="right">
                    <table cellspacing="0" cellpadding="0" style="min-width:220px;">
                        <tr>
                            <td style="font-size:11px;color:#bfdbfe;padding:3px 8px;border-bottom:1px solid rgba(255,255,255,0.1);">Subtotal</td>
                            <td style="font-size:11px;color:#bfdbfe;padding:3px 8px;border-bottom:1px solid rgba(255,255,255,0.1);text-align:right;">$' . number_format($subtotal, 2) . '</td>
                        </tr>
                        <tr>
                            <td style="font-size:11px;color:#bfdbfe;padding:3px 8px;border-bottom:1px solid rgba(255,255,255,0.1);">Tax (' . $taxRate . '%)</td>
                            <td style="font-size:11px;color:#bfdbfe;padding:3px 8px;border-bottom:1px solid rgba(255,255,255,0.1);text-align:right;">$' . number_format($taxAmount, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-top:4px;">
                                <table cellspacing="0" cellpadding="0" style="width:100%;background:#3b82f6;border-radius:4px;">
                                    <tr>
                                        <td style="padding:8px 12px;color:#fff;font-weight:900;font-size:13px;">Total to Pay</td>
                                        <td style="padding:8px 12px;color:#fff;font-weight:900;font-size:13px;text-align:right;">$' . number_format($total, 2) . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

    </div>';
}

private function getWhiteLogoBase64(): string
{
    $path = public_path('images/all-renewed-logo.png');

    if (!file_exists($path)) {
        return '';
    }

    $img = imagecreatefrompng($path);
    if (!$img) return '';

    $w = imagesx($img);
    $h = imagesy($img);

    // Create output image with transparency
    $out = imagecreatetruecolor($w, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);

    for ($x = 0; $x < $w; $x++) {
        for ($y = 0; $y < $h; $y++) {
            $rgba  = imagecolorat($img, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F; // 0=opaque, 127=transparent
            $r     = ($rgba >> 16) & 0xFF;
            $g     = ($rgba >> 8)  & 0xFF;
            $b     = $rgba         & 0xFF;

            // Invert RGB, keep alpha
            $color = imagecolorallocatealpha($out, 255 - $r, 255 - $g, 255 - $b, $alpha);
            imagesetpixel($out, $x, $y, $color);
        }
    }

    imagedestroy($img);

    // Capture output as base64
    ob_start();
    imagepng($out);
    $data = ob_get_clean();
    imagedestroy($out);

    return 'data:image/png;base64,' . base64_encode($data);
}

private function warrantyUnitText($value, string $unit): string
{
    $unit = Str::lower($unit);
    return (int) $value === 1 ? rtrim($unit, 's') : $unit;
}
}