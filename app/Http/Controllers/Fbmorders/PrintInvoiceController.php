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