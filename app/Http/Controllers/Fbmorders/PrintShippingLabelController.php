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

class PrintShippingLabelController extends Controller
{
    public function printshippinglabel(Request $request)
    {
        $platform_order_ids = $request->input('platform_order_ids', []);
        $action = $request->input('action', '');
        $note = $request->input('note', '');
        $results = [];

        foreach ($platform_order_ids as $platform_order_id) {
            $labelRow = DB::table('tbllabelhistoryitems')
                ->where('AmazonOrderId', $platform_order_id)
                ->orderBy('id', 'asc')
                ->first();

            if (!$labelRow || empty($labelRow->PDFLabel)) {
                Log::warning("Missing PDFLabel for order: {$platform_order_id}");
                continue;
            }

            // Step 1: Decode base64
            $decoded = base64_decode($labelRow->PDFLabel, true);
            if (!$decoded) {
                return response()->json([
                    'success' => false,
                    'error' => "Base64 decode failed for order: {$platform_order_id}"
                ]);
            }

            // Step 2: Try gzdecode
            $pdfData = gzdecode($decoded);
            if ($pdfData === false) {
                $pdfData = $decoded; // maybe it was not gzipped
            }

            $pdfPath = public_path("images/FBM_docs/shipping_label/shippinglabel_{$platform_order_id}.pdf");

            // Step 3A: If PNG, render using mPDF
            if (substr($pdfData, 0, 4) === "\x89PNG") {
                $tmpImagePath = tempnam(sys_get_temp_dir(), 'png');
                file_put_contents($tmpImagePath, $pdfData);

                $mpdf = new Mpdf(['margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0]);
                $mpdf->WriteHTML('<img src="' . $tmpImagePath . '" style="width:100%; height:auto;">');
                $mpdf->Output($pdfPath, 'F');

                unlink($tmpImagePath);
            }

            // Step 3B: If real PDF
            elseif (substr($pdfData, 0, 4) === '%PDF') {
                file_put_contents($pdfPath, $pdfData);
            }

            // Step 3C: Invalid data
            else {
                return response()->json([
                    'success' => false,
                    'error' => "Decoded data is not a valid PNG or PDF for order: {$platform_order_id}"
                ]);
            }

            // Step 4: Convert to ZPL
            $zplCode = $this->convertPDFToZPL($pdfPath, $platform_order_id, ['note' => $note]);

            // Step 5: Optional print
            if ($action === 'PrintShipmentLabel') {
                $this->sendToPrinter($zplCode);
            }

            // Step 6: Add result
            $results[] = [
                'order_id' => $platform_order_id,
                'pdf_url' => asset("images/FBM_docs/shipping_label/shippinglabel_{$platform_order_id}.pdf"),
                'zpl_preview' => $action === 'ViewShipmentLabel' ? $zplCode : null,
            ];
        }

        return response()->json([
            'success' => true,
            'results' => $results
        ]);
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