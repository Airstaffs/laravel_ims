<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SoldListImport extends Command
{
    protected $signature = 'SoldListImport {csv}';
    protected $description = 'Import SoldList CSV into tblproduct';

    public function handle()
    {
        $file = $this->argument('csv');

        if (!file_exists($file)) {
            $this->error("CSV file not found.");
            return;
        }

        $this->info("Starting import...");

        // get current max rtcounter
        $maxRt = DB::table('tblproduct')->max('rtcounter');
        $rt = $maxRt ?? 0;

        if (($handle = fopen($file, 'r')) === false) {
            $this->error("Unable to open CSV.");
            return;
        }

        $headers = fgetcsv($handle);

        $inserted = 0;

        while (($row = fgetcsv($handle)) !== false) {

            $rt++;

            $data = [
                'rtcounter' => $rt,
                'ProductTitle' => $row[1] ?? null,
                'price' => $row[3] ?? null,
                'priceshipping' => $row[4] ?? null,
                'tax' => $row[5] ?? null,
                'Discount' => $row[6] ?? null,
                'quantity' => $row[7] ?? null,
                'orderdate' => $row[8] ?? null,
                'paymentdate' => $row[9] ?? null,
                'paymentmethod' => $row[10] ?? null,
                'shipdate' => $row[11] ?? null,
                'itemnumber' => $row[12] ?? null,
                'rtid' => $row[13] ?? null,
                'trackingnumber' => $row[14] ?? null,
                'seller' => $row[17] ?? null,
                'description' => $row[18] ?? null,
                'serialnumber' => $row[20] ?? null,
                'serialnumberb' => $row[21] ?? null,
                'serialnumberc' => $row[22] ?? null,
                'serialnumberd' => $row[23] ?? null,
                'datedelivered' => $row[24] ?? null,
                'ASINviewer' => $row[25] ?? null,
                'FNSKUviewer' => $row[28] ?? null,
                'MSKUviewer' => $row[29] ?? null,
                'PCN' => $row[31] ?? null,
                'RPN' => $row[32] ?? null,
                'PRD' => $row[33] ?? null,
                'basketnumber' => $row[34] ?? null,
                'materialtype' => $row[35] ?? null,
                'priorityrank' => $row[36] ?? null,
                'notes' => $row[37] ?? null,
                'EmployeeNote' => $row[38] ?? null,
                'carrier' => $row[40] ?? null,
                'warehouselocation' => $row[41] ?? null,
                'Fulfilledby' => $row[42] ?? null,
                'FbmAvailable' => $row[44] ?? 1,
                'FbaAvailable' => $row[45] ?? 0,
                'Inbound' => $row[46] ?? 0,
                'Outbound' => $row[47] ?? 0,
                'Reserved' => $row[48] ?? 0,
                'Unfulfillable' => $row[49] ?? 0,
                'ShipAddress' => $row[52] ?? null,
                'returnstatus' => $row[57] ?? null,
                'validation' => $row[58] ?? null,
                'stockroom_insert_date' => $row[59] ?? null,
                'ProductModuleLoc' => $row[60] ?? null,
                'splitfromRT' => $row[61] ?? null,
                'shipment_tracking_number' => $row[63] ?? null,
            ];

            DB::table('tblproduct')->insert($data);

            $inserted++;
        }

        fclose($handle);

        $this->info("Import finished. Inserted: {$inserted}");
    }
}
