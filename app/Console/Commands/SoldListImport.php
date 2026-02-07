<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SoldListImport extends Command
{
    protected $signature = 'SoldListImport {csv}';
    protected $description = 'Import SoldList CSV into tblproduct (continue rtcounter, ProductID auto-increment)';

    public function handle(): int
    {
        $file = $this->argument('csv');

        if (!file_exists($file)) {
            $this->error("CSV file not found: {$file}");
            return self::FAILURE;
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
            // remove UTF-8 BOM if present + trim quotes/spaces
            $s = preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
            $s = trim($s);
            $s = trim($s, "\"'"); // remove wrapping quotes
            $s = trim(mb_strtolower($s));
            return preg_replace('/[^a-z0-9]+/i', '', $s) ?? '';
        };

        $nullIfEmpty = function ($v) {
            if ($v === null)
                return null;
            $v = trim((string) $v);
            if ($v === '' || $v === 'NULL' || $v === 'null')
                return null;
            return $v;
        };

        $cleanNumber = function ($v) use ($nullIfEmpty) {
            $v = $nullIfEmpty($v);
            if ($v === null)
                return null;

            // remove currency symbols, commas, spaces, etc. keep digits, dot, minus
            $v = preg_replace('/[^0-9.\-]/', '', (string) $v);
            $v = trim($v);
            if ($v === '' || $v === '-' || $v === '.' || $v === '-.')
                return null;

            return $v;
        };

        $intOrNull = function ($v) use ($cleanNumber) {
            $v = $cleanNumber($v);
            if ($v === null)
                return null;
            return is_numeric($v) ? (int) $v : null;
        };

        $floatOrNull = function ($v) use ($cleanNumber) {
            $v = $cleanNumber($v);
            if ($v === null)
                return null;
            return is_numeric($v) ? (float) $v : null;
        };

        $dateOrNull = function ($v) use ($nullIfEmpty) {
            $v = $nullIfEmpty($v);
            if ($v === null)
                return null;

            $v = trim((string) $v);

            // Guard common invalid/zero dates
            $bad = [
                '0000-00-00',
                '0000-00-00 00:00:00',
                '-0001-11-30',
                '-0001-11-30 00:00:00',
                '1899-12-30', // Excel serial 0 often maps here in some conversions
                '1899-12-31',
            ];
            if (in_array($v, $bad, true))
                return null;

            // Also guard empty-ish patterns
            if (preg_match('/^0{1,2}[\/\-]0{1,2}[\/\-]0{2,4}$/', $v))
                return null;

            $ts = strtotime($v);
            if ($ts === false)
                return null;

            // If year is absurdly low, treat as null
            $year = (int) date('Y', $ts);
            if ($year < 1970 || $year > 2100)
                return null;

            return date('Y-m-d', $ts);
        };

        $datetimeOrNull = function ($v) use ($nullIfEmpty) {
            $v = $nullIfEmpty($v);
            if ($v === null)
                return null;

            $v = trim((string) $v);

            $bad = [
                '0000-00-00',
                '0000-00-00 00:00:00',
                '-0001-11-30',
                '-0001-11-30 00:00:00',
                '1899-12-30',
                '1899-12-31',
            ];
            if (in_array($v, $bad, true))
                return null;

            $ts = strtotime($v);
            if ($ts === false)
                return null;

            $year = (int) date('Y', $ts);
            if ($year < 1970 || $year > 2100)
                return null;

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

        // normalized header => index
        $hIndex = [];
        foreach ($headers as $i => $h) {
            $k = $norm((string) $h);
            if ($k !== '')
                $hIndex[$k] = $i;
        }

        // normalized CSV header => db column
        $map = [
            'externaltitle' => 'ProductTitle',
            'price' => 'price',
            'priceshipping' => 'priceshipping',
            'tax' => 'tax',
            'discount' => 'Discount',
            'quantity' => 'quantity',
            'orderdate' => 'orderdate',
            'paymentdate' => 'paymentdate',
            'paymentmethod' => 'paymentmethod',
            'shipdate' => 'shipdate',
            'delivereddate' => 'datedelivered',
            'itemnumber' => 'itemnumber',
            'ordernumber' => 'rtid',
            'trackingnumber' => 'trackingnumber',
            'trackingnumber2' => 'trackingnumber2',
            'trackingnumber3' => 'trackingnumber3',
            'seller' => 'seller',
            'description' => 'description',
            'sourcetype' => 'SourceType',
            'serialnumber' => 'serialnumber',
            'serialnumberb' => 'serialnumberb',
            'serialnumberc' => 'serialnumberc',
            'serialnumberd' => 'serialnumberd',
            'asin' => 'ASINviewer',
            'fnsku' => 'FNSKUviewer',
            'msku' => 'MSKUviewer',
            'pcn' => 'PCN',
            'rpn' => 'RPN',
            'prd' => 'PRD',
            'basketshenv' => 'basketnumber',
            'materialtype' => 'materialtype',
            'priorityrank' => 'priorityrank',
            'notes' => 'notes',
            'employeenote' => 'EmployeeNote',
            'carrier' => 'carrier',
            'warehouselocation' => 'warehouselocation',
            'fulfillmentchannel' => 'Fulfilledby',
            'fbmavailable' => 'FbmAvailable',
            'fbaavailable' => 'FbaAvailable',
            'inbound' => 'Inbound',
            'outbound' => 'Outbound',
            'reserved' => 'Reserved',
            'unfulfillable' => 'Unfulfillable',
            'shipaddress' => 'ShipAddress',
            'returnstatus' => 'returnstatus',
            'validationstatus' => 'validation',
            'insertdatestockroom' => 'stockroom_insert_date',
            'modulelocation' => 'ProductModuleLoc',
            'splitfrom' => 'splitfromRT',
            'shipmenttrackingnumber' => 'shipment_tracking_number',
            'lpnid' => 'lpnID',
        ];

        $get = function (array $row, string $csvKey) use ($hIndex) {
            return array_key_exists($csvKey, $hIndex) ? ($row[$hIndex[$csvKey]] ?? null) : null;
        };

        $inserted = 0;
        $skipped = 0;

        while (($row = fgetcsv($fh)) !== false) {

            if ($inserted === 0) {
                $this->line("Row#1 col count: " . count($row));
                $this->line("Row#1 sample Price raw: " . ($get($row, 'price') ?? '[NULL]'));
            }

            // skip empty lines
            $hasData = false;
            foreach ($row as $v) {
                if (trim((string) $v) !== '') {
                    $hasData = true;
                    break;
                }
            }
            if (!$hasData) {
                $skipped++;
                continue;
            }

            $rt++; // next rtcounter

            // base payload (ProductID is NOT set; auto increment)
            $data = [
                'rtcounter' => $rt,
            ];

            foreach ($map as $csvKey => $dbCol) {
                $val = $get($row, $csvKey);

                // type casting rules
                if (in_array($dbCol, ['price', 'priceshipping', 'tax', 'Discount'], true)) {
                    $data[$dbCol] = $floatOrNull($val);
                } elseif (in_array($dbCol, ['quantity', 'FbmAvailable', 'FbaAvailable', 'Inbound', 'Outbound', 'Reserved', 'Unfulfillable', 'lpnID'], true)) {
                    $data[$dbCol] = $intOrNull($val);
                } elseif (in_array($dbCol, ['orderdate', 'paymentdate', 'shipdate', 'datedelivered'], true)) {
                    $data[$dbCol] = $dateOrNull($val);
                } elseif ($dbCol === 'stockroom_insert_date') {
                    $data[$dbCol] = $datetimeOrNull($val);
                } else {
                    $data[$dbCol] = $nullIfEmpty($val);
                }
            }

            DB::table('tblproduct')->insert($data);
            $inserted++;
        }

        fclose($fh);

        $this->info("Import finished.");
        $this->line("Inserted: {$inserted}");
        $this->line("Skipped empty rows: {$skipped}");
        $this->line("rtcounter ended at: {$rt}");

        return self::SUCCESS;
    }
}
