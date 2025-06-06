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

class PrintInvoiceController extends Controller {
    public function PrintInvoice(Request $request) {

    }
/*
    private function generateAndConvertPDF($html, $orderItemId, $width_format = 100, $testprint = false)
{
    $pdfPath = storage_path("app/public/invoice_{$orderItemId}.pdf");

    // Generate PDF using mPDF
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => [230, $width_format], // width, height in mm
        'margin_left' => 1,
        'margin_right' => 1,
        'margin_top' => 0,
        'margin_bottom' => 1,
        'margin_header' => 1,
        'margin_footer' => 1,
    ]);

    $mpdf->WriteHTML(trim($html));
    $mpdf->Output($pdfPath, 'F'); // Save to file

    // Convert PDF to images
    $imagick = new Imagick();
    $imagick->setResolution(300, 300);
    $imagick->readImage($pdfPath);
    $imagick->setImageFormat('png');

    $pageCount = $imagick->getNumberImages();
    $zplFullCommand = "";

    for ($index = 0; $index < $pageCount; $index++) {
        $imagick->setIteratorIndex($index);
        $img = $imagick->getImage();

        $img->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $img->setBackgroundColor(new ImagickPixel('white'));
        $img = $img->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $img->setImageFormat('png');

        $imagePath = storage_path("app/public/invoice_{$orderItemId}_page{$index}.png");
        $img->writeImage($imagePath);

        // Convert image to ZPL
        $zplFullCommand .= $this->convertImageToZPL($testprint, $imagePath) . "\n";

        $img->clear();
        $img->destroy();
    }

    $imagick->clear();
    $imagick->destroy();

    return $zplFullCommand;
}
    */
}