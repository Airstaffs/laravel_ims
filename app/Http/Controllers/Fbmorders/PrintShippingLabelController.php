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
                ->orderBy('id', 'desc')
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

        Log::info('Page count: ' . $imagick->getNumberImages());
        if (!file_exists($pdfPath)) {
            Log::error("PDF file does not exist: $pdfPath");
        }

        // 4x6 at 300 DPI
        $targetW = 1200;
        $targetH = 1800;

        // how much margin you want after trimming
        $pad = 80;

        $zplCode = "";

        for ($i = 0; $i < $imagick->getNumberImages(); $i++) {
            $imagick->setIteratorIndex($i);
            $img = $imagick->getImage();

            // Flatten
            $img->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $img->setBackgroundColor(new \ImagickPixel('white'));
            $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $img->setImageFormat('png');
            $img->setImagePage(0, 0, 0, 0);

            // ✅ Rotate (keep this since it works for your label)
            $img->rotateImage(new \ImagickPixel('white'), 90);
            $img->setImagePage(0, 0, 0, 0);

            // ✅ Trim extra whitespace (tolerate near-white)
            $img->setOption('fuzz', '10%');
            $img->trimImage(0);
            $img->setImagePage(0, 0, 0, 0);

            // ✅ Add padding so it isn't too tight
            if ($pad > 0) {
                $img->borderImage(new \ImagickPixel('white'), $pad, $pad);
                $img->setImagePage(0, 0, 0, 0);
            }

            // ✅ Fit inside 4x6 canvas WITHOUT upscaling (prevents "zoomed in")
            // bestfit=true, fill=false
            $img->thumbnailImage($targetW, $targetH, true, false);
            $img->setImagePage(0, 0, 0, 0);

            // ✅ Center on 4x6 canvas
            $imgW = $img->getImageWidth();
            $imgH = $img->getImageHeight();

            $x = (int) (($targetW - $imgW) / 2);
            $y = (int) (($targetH - $imgH) / 2);

            $canvas = new \Imagick();
            $canvas->newImage($targetW, $targetH, new \ImagickPixel('white'));
            $canvas->setImageFormat('png');
            $canvas->compositeImage($img, \Imagick::COMPOSITE_DEFAULT, $x, $y);

            $imagePath = public_path("images/FBM_docs/shipping_label/shippinglabel_{$orderId}_page{$i}.png");
            $canvas->writeImage($imagePath);

            // ✅ rebuild preview PDF from the corrected PNG
            $mpdf = new \Mpdf\Mpdf([
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_left' => 0,
                'margin_right' => 0,
            ]);
            $mpdf->WriteHTML('<img src="' . $imagePath . '" style="width:100%; height:auto;">');
            $mpdf->Output($pdfPath, 'F');

            // ZPL
            $zplCode .= $this->convertImageToZPL($testPrint, $imagePath) . "\n";

            // cleanup
            $canvas->clear();
            $canvas->destroy();
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