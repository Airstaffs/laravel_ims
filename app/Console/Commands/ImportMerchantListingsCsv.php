<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportMerchantListingsCsv extends Command
{
    protected $signature = 'import:merchant-listings
        {file : Path to CSV file (absolute or relative to project root)}
        {--store=AR : Store code (AR or RT)}
        {--dry-run : Parse + show counts but do not write to DB}';

    protected $description = 'Import GET_MERCHANT_LISTINGS_ALL_DATA CSV into tblasin and tblfnsku (manual upsert).';

    public function handle(): int
    {
        $fileArg = (string) $this->argument('file');
        $filePath = $this->resolvePath($fileArg);

        if (!is_file($filePath)) {
            $this->error("CSV file not found: {$filePath}");
            return self::FAILURE;
        }

        $store = strtoupper(trim((string) $this->option('store'))) ?: 'AR';
        $storename = $store === 'RT' ? 'Renovartech' : 'Allrenewed';
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Importing CSV: {$filePath}");
        $this->info("Store: {$store} → storename={$storename}");
        $this->info("Dry run: " . ($dryRun ? 'YES' : 'NO'));

        [$headerMap, $rowCount] = $this->buildHeaderMap($filePath);
        if ($rowCount <= 1) {
            $this->warn("CSV has no data rows.");
            return self::SUCCESS;
        }

        $required = ['item-name', 'seller-sku', 'status'];
        foreach ($required as $col) {
            if (!isset($headerMap[$col])) {
                $this->error("Missing required column: {$col}");
                $this->line("Found columns: " . implode(', ', array_keys($headerMap)));
                return self::FAILURE;
            }
        }

        // ASIN can come from product-id or asin1
        if (!isset($headerMap['product-id']) && !isset($headerMap['asin1'])) {
            $this->error("CSV must include either 'product-id' or 'asin1' for ASIN.");
            return self::FAILURE;
        }

        $insertAsin = 0;
        $updateAsin = 0;
        $insertFnsku = 0;
        $updateFnsku = 0;
        $skipped = 0;

        $fh = fopen($filePath, 'r');
        if (!$fh) {
            $this->error("Failed to open file for reading.");
            return self::FAILURE;
        }

        // Read header
        $headerRow = fgetcsv($fh);
        if (!$headerRow) {
            fclose($fh);
            $this->error("Failed to read header row.");
            return self::FAILURE;
        }

        $lineNo = 1;

        while (($row = fgetcsv($fh)) !== false) {
            $lineNo++;

            $msku = $this->col($row, $headerMap, 'seller-sku');
            $msku = strtoupper(trim($msku));

            $asin = $this->col($row, $headerMap, 'product-id');
            if ($asin === '') $asin = $this->col($row, $headerMap, 'asin1');
            $asin = trim($asin);

            $itemName = $this->normalizeText($this->col($row, $headerMap, 'item-name'));
            $status = trim($this->col($row, $headerMap, 'status'));

            if ($msku === '' || $asin === '') {
                $skipped++;
                continue;
            }

            $amazonStatus = (strcasecmp($status, 'Active') === 0) ? 'Active' : 'Inactive';

            if ($dryRun) {
                // no DB writes
                continue;
            }

            DB::beginTransaction();
            try {
                // -----------------------
                // tblasin manual upsert
                // -----------------------
                $asinExists = DB::table('tblasin')->where('ASIN', $asin)->exists();

                if ($asinExists) {
                    DB::table('tblasin')->where('ASIN', $asin)->update([
                        'amazon_title' => $itemName ?: null,
                        // DO NOT TOUCH amazon_status
                    ]);
                    $updateAsin++;
                } else {
                    DB::table('tblasin')->insert([
                        'ASIN' => $asin,
                        'internal' => $itemName ?: null,
                        'amazon_title' => $itemName ?: null,
                        // amazon_status defaults to 'Existed'
                    ]);
                    $insertAsin++;
                }

                // -----------------------
                // tblfnsku manual upsert (storename + MSKU)
                // -----------------------
                $fnExists = DB::table('tblfnsku')
                    ->where('storename', $storename)
                    ->where('MSKU', $msku)
                    ->exists();

                $fnData = [
                    'amazon_status' => $amazonStatus,
                    'addedby' => 'Auto_Insert_Code_Import',
                    'ASIN' => $asin,
                ];

                if ($fnExists) {
                    DB::table('tblfnsku')
                        ->where('storename', $storename)
                        ->where('MSKU', $msku)
                        ->update($fnData);
                    $updateFnsku++;
                } else {
                    DB::table('tblfnsku')->insert(array_merge($fnData, [
                        'storename' => $storename,
                        'MSKU' => $msku,
                        // insert_date default CURRENT_TIMESTAMP
                    ]));
                    $insertFnsku++;
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Line {$lineNo}: DB error: " . $e->getMessage());
                return self::FAILURE;
            }
        }

        fclose($fh);

        $this->info("Done.");
        $this->line("tblasin: inserted={$insertAsin}, updated={$updateAsin}");
        $this->line("tblfnsku: inserted={$insertFnsku}, updated={$updateFnsku}");
        $this->line("skipped (missing msku/asin)={$skipped}");

        if ($dryRun) {
            $this->warn("Dry-run mode: no DB writes were performed.");
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') return $path;

        // absolute path on Windows like C:\...
        if (preg_match('/^[A-Za-z]:\\\\/', $path)) return $path;

        // absolute unix path
        if (str_starts_with($path, '/')) return $path;

        // relative to project root
        return base_path($path);
    }

    private function buildHeaderMap(string $filePath): array
    {
        $fh = fopen($filePath, 'r');
        if (!$fh) return [[], 0];

        $header = fgetcsv($fh);
        $count = 1;

        $map = [];
        if (is_array($header)) {
            foreach ($header as $i => $name) {
                $key = strtolower(trim((string) $name));
                if ($key !== '') $map[$key] = $i;
            }
        }

        while (!feof($fh)) {
            $line = fgets($fh);
            if ($line !== false) $count++;
        }

        fclose($fh);
        return [$map, $count];
    }

    private function col(array $row, array $map, string $col): string
    {
        $i = $map[$col] ?? null;
        if ($i === null) return '';
        return (string)($row[$i] ?? '');
    }

    private function normalizeText(string $s): string
    {
        $s = trim($s);

        // Fix the common Amazon export "�" replacement issues best-effort:
        // Convert from Windows-1252 to UTF-8 if it looks non-UTF8.
        if ($s !== '' && !mb_check_encoding($s, 'UTF-8')) {
            $s = @iconv('Windows-1252', 'UTF-8//IGNORE', $s) ?: $s;
        }

        return $s;
    }
}
