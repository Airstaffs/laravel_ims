<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SoldListImport1 extends Command
{
    protected $signature = 'SoldListImport1 {csv} {--company=Airstaffs}';
    protected $description = 'Import SoldList CSV into tblproduct (continue rtcounter, ProductID auto-increment)';

    // tblcaptured template row ID to copy images from
    const TEMPLATE_CAPTURED_ID = 326;

    // Number of capturedimg columns in tblcaptured
    const MAX_CAPTURED_IMGS = 12;

    // Number of img columns in tblproduct
    const MAX_PRODUCT_IMGS = 15;

    public function handle(): int
    {
        $file    = $this->argument('csv');
        $company = $this->option('company');
        $imgDir  = public_path("images/product_images/{$company}");

        if (!file_exists($file)) {
            $this->error("CSV file not found: {$file}");
            return self::FAILURE;
        }

        // ---------- Load template captured row (id=16) ----------
        $templateRow = DB::table('tblcapturedimages')->where('id', self::TEMPLATE_CAPTURED_ID)->first();
        if (!$templateRow) {
            $this->error("Template tblcaptured row (id=" . self::TEMPLATE_CAPTURED_ID . ") not found.");
            return self::FAILURE;
        }

        // Collect non-null captured images from template row
        $templateImgs = []; // e.g. ['capturedimg1' => '2474_img1.jpg', ...]
        for ($i = 1; $i <= self::MAX_CAPTURED_IMGS; $i++) {
            $col = 'capturedimg' . $i;
            $val = $templateRow->{$col} ?? null;
            if ($val) {
                $templateImgs[$col] = $val;
            }
        }

        $this->info("Template images found: " . count($templateImgs));

        // Ensure image directory exists
        if (!File::exists($imgDir)) {
            File::makeDirectory($imgDir, 0755, true);
        }

        $this->info("Starting import...");

        // Continue rtcounter from DB max
        $rt = (int) (DB::table('tblproduct')->max('rtcounter') ?? 0);

        $fh = fopen($file, 'r');
        if (!$fh) {
            $this->error("Unable to open CSV: {$file}");
            return self::FAILURE;
        }

        // ---------- helpers ----------
        $norm = function (string $s): string {
            $s = preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
            $s = trim($s);
            $s = trim($s, "\"'");
            $s = trim(mb_strtolower($s));
            return preg_replace('/[^a-z0-9]+/i', '', $s) ?? '';
        };

        $nullIfEmpty = function ($v) {
            if ($v === null) return null;
            $v = trim((string) $v);
            if ($v === '' || $v === 'NULL' || $v === 'null') return null;
            return $v;
        };

        $cleanNumber = function ($v) use ($nullIfEmpty) {
            $v = $nullIfEmpty($v);
            if ($v === null) return null;
            $v = preg_replace('/[^0-9.\-]/', '', (string) $v);
            $v = trim($v);
            if ($v === '' || $v === '-' || $v === '.' || $v === '-.') return null;
            return $v;
        };

        $intOrNull = function ($v) use ($cleanNumber) {
            $v = $cleanNumber($v);
            if ($v === null) return null;
            return is_numeric($v) ? (int) $v : null;
        };

        $floatOrNull = function ($v) use ($cleanNumber) {
            $v = $cleanNumber($v);
            if ($v === null) return null;
            return is_numeric($v) ? (float) $v : null;
        };

        $dateOrNull = function ($v) use ($nullIfEmpty) {
            $v = $nullIfEmpty($v);
            if ($v === null) return null;
            $bad = ['0000-00-00','0000-00-00 00:00:00','-0001-11-30','-0001-11-30 00:00:00','1899-12-30','1899-12-31'];
            if (in_array($v, $bad, true)) return null;
            if (preg_match('/^0{1,2}[\/\-]0{1,2}[\/\-]0{2,4}$/', $v)) return null;
            $ts = strtotime($v);
            if ($ts === false) return null;
            $year = (int) date('Y', $ts);
            if ($year < 1970 || $year > 2100) return null;
            return date('Y-m-d', $ts);
        };

        $datetimeOrNull = function ($v) use ($nullIfEmpty) {
            $v = $nullIfEmpty($v);
            if ($v === null) return null;
            $bad = ['0000-00-00','0000-00-00 00:00:00','-0001-11-30','-0001-11-30 00:00:00','1899-12-30','1899-12-31'];
            if (in_array($v, $bad, true)) return null;
            $ts = strtotime($v);
            if ($ts === false) return null;
            $year = (int) date('Y', $ts);
            if ($year < 1970 || $year > 2100) return null;
            return date('Y-m-d H:i:s', $ts);
        };

        // ---------- read headers ----------
        $headers = fgetcsv($fh);

        $this->line("Header count: " . count($headers));
        $this->line("First 8 headers: " . implode(' | ', array_slice($headers, 0, 8)));

        if (!$headers) {
            fclose($fh);
            $this->error("CSV header row is empty.");
            return self::FAILURE;
        }

        $hIndex = [];
        foreach ($headers as $i => $h) {
            $k = $norm((string) $h);
            if ($k !== '') $hIndex[$k] = $i;
        }

        $map = [
            'externaltitle'          => 'ProductTitle',
            'price'                  => 'price',
            'priceshipping'          => 'priceshipping',
            'tax'                    => 'tax',
            'discount'               => 'Discount',
            'quantity'               => 'quantity',
            'orderdate'              => 'orderdate',
            'paymentdate'            => 'paymentdate',
            'paymentmethod'          => 'paymentmethod',
            'shipdate'               => 'shipdate',
            'delivereddate'          => 'datedelivered',
            'itemnumber'             => 'itemnumber',
            'ordernumber'            => 'rtid',
            'trackingnumber'         => 'trackingnumber',
            'trackingnumber2'        => 'trackingnumber2',
            'trackingnumber3'        => 'trackingnumber3',
            'seller'                 => 'seller',
            'description'            => 'description',
            'sourcetype'             => 'SourceType',
            'serialnumber'           => 'serialnumber',
            'serialnumberb'          => 'serialnumberb',
            'serialnumberc'          => 'serialnumberc',
            'serialnumberd'          => 'serialnumberd',
            'asin'                   => 'ASINviewer',
            'fnsku'                  => 'FNSKUviewer',
            'msku'                   => 'MSKUviewer',
            'pcn'                    => 'PCN',
            'rpn'                    => 'RPN',
            'prd'                    => 'PRD',
            'basketshenv'            => 'basketnumber',
            'materialtype'           => 'materialtype',
            'priorityrank'           => 'priorityrank',
            'notes'                  => 'notes',
            'employeenote'           => 'EmployeeNote',
            'carrier'                => 'carrier',
            'warehouselocation'      => 'warehouselocation',
            'fulfillmentchannel'     => 'Fulfilledby',
            'fbmavailable'           => 'FbmAvailable',
            'fbaavailable'           => 'FbaAvailable',
            'inbound'                => 'Inbound',
            'outbound'               => 'Outbound',
            'reserved'               => 'Reserved',
            'unfulfillable'          => 'Unfulfillable',
            'shipaddress'            => 'ShipAddress',
            'returnstatus'           => 'returnstatus',
            'validationstatus'       => 'validation',
            'insertdatestockroom'    => 'stockroom_insert_date',
            'modulelocation'         => 'ProductModuleLoc',
            'splitfrom'              => 'splitfromRT',
            'shipmenttrackingnumber' => 'shipment_tracking_number',
            'lpnid'                  => 'lpnID',
        ];

        $get = function (array $row, string $csvKey) use ($hIndex) {
            return array_key_exists($csvKey, $hIndex) ? ($row[$hIndex[$csvKey]] ?? null) : null;
        };

        $inserted = 0;
        $skipped  = 0;

        while (($row = fgetcsv($fh)) !== false) {

            if ($inserted === 0) {
                $this->line("Row#1 col count: " . count($row));
                $this->line("Row#1 sample Price raw: " . ($get($row, 'price') ?? '[NULL]'));
            }

            // skip empty lines
            $hasData = false;
            foreach ($row as $v) {
                if (trim((string) $v) !== '') { $hasData = true; break; }
            }
            if (!$hasData) { $skipped++; continue; }

            $rt++;

            // ---------- Build tblproduct payload ----------
            $data = ['rtcounter' => $rt];

            foreach ($map as $csvKey => $dbCol) {
                $val = $get($row, $csvKey);
                if (in_array($dbCol, ['price','priceshipping','tax','Discount'], true)) {
                    $data[$dbCol] = $floatOrNull($val);
                } elseif (in_array($dbCol, ['quantity','FbmAvailable','FbaAvailable','Inbound','Outbound','Reserved','Unfulfillable','lpnID'], true)) {
                    $data[$dbCol] = $intOrNull($val);
                } elseif (in_array($dbCol, ['orderdate','paymentdate','shipdate','datedelivered'], true)) {
                    $data[$dbCol] = $dateOrNull($val);
                } elseif ($dbCol === 'stockroom_insert_date') {
                    $data[$dbCol] = $datetimeOrNull($val);
                } else {
                    $data[$dbCol] = $nullIfEmpty($val);
                }
            }

            // ---------- img1–img15 filename strings (no physical copy) ----------
            // img1 = {ProductID}.jpg (no underscore)
            // img2 = {ProductID}_1.jpg, img3 = {ProductID}_2.jpg ... img15 = {ProductID}_14.jpg
            // We use a placeholder '__PID__' and replace after we get the real ProductID below
            // For now just mark them — we fill after insert

            DB::beginTransaction();
            try {
                // Insert tblproduct (ProductID is auto-increment, returned below)
                $newProductId = DB::table('tblproduct')->insertGetId($data);

                // img1–img15: ALWAYS fixed constant filenames, hardcoded as 17060
                // img1 = 17060.jpg, img2 = 17060_1.jpg ... img15 = 17060_14.jpg
                $tplPid = 17060;
                $imgUpdates = [];
                for ($i = 1; $i <= self::MAX_PRODUCT_IMGS; $i++) {
                    $col = 'img' . $i;
                    $imgUpdates[$col] = $i === 1
                        ? "{$tplPid}.jpg"
                        : "{$tplPid}_" . ($i - 1) . ".jpg";
                }
                DB::table('tblproduct')->where('ProductID', $newProductId)->update($imgUpdates);

                // ---------- tblcaptured: copy template row with new filenames + physical files ----------
                $capturedData = [
                    'ProductID' => $newProductId,
                    'CreatedAt' => now(),
                    'UpdatedAt' => now(),
                ];

                $imgIndex = 1;
                foreach ($templateImgs as $col => $srcFilename) {
                    // Source file on disk (template, e.g. 2474_img1.jpg)
                    $srcPath = $imgDir . '/' . $srcFilename;

                    // New filename for this product
                    $newFilename = "{$newProductId}_img{$imgIndex}.jpeg";
                    $destPath    = $imgDir . '/' . $newFilename;

                    // Physically copy the file if source exists
                    if (File::exists($srcPath)) {
                        File::copy($srcPath, $destPath);
                    } else {
                        $this->warn("Source image not found, skipping file copy: {$srcPath}");
                    }

                    // Map capturedimg1 → capturedimg1, capturedimg2 → capturedimg2, etc.
                    $capturedData[$col] = $newFilename;
                    $imgIndex++;
                }

                DB::table('tblcapturedimages')->insert($capturedData);

                DB::commit();
                $inserted++;

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Row failed (rt={$rt}): " . $e->getMessage());
                $skipped++;
            }
        }

        fclose($fh);

        $this->info("Import finished.");
        $this->line("Inserted : {$inserted}");
        $this->line("Skipped  : {$skipped}");
        $this->line("rtcounter ended at: {$rt}");

        return self::SUCCESS;
    }
}