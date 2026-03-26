<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AsinFnskuCleaner extends Command
{
    protected $signature = 'asin-fnsku-cleaner
    {--store=AR : Store code (AR or RT)}
    {--date=2024-05-01 : Date input (YYYY-MM-DD)}';

    protected $description = 'Fetch Amazon GET_MERCHANT_LISTINGS_ALL_DATA report and export rows to CSV';

    public function handle(): int
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        date_default_timezone_set('America/Los_Angeles');

        $this->info("Time initiated: " . now('America/Los_Angeles')->format('m-d-Y H:i:s') . " (Los Angeles)");

        // ✅ inputs
        $store = strtoupper(trim((string) $this->option('store'))) ?: 'AR';
        $inputDate = trim((string) $this->option('date')) ?: '2024-05-01';

        // Validate date
        if (!$this->isValidDate($inputDate)) {
            $this->error("Invalid date format. Use YYYY-MM-DD.");
            return self::FAILURE;
        }

        // ✅ Get credentials (Laravel DB)
        $credentials = $this->AWSCredentials($store);
        if (!$credentials) {
            $this->error("No credentials found for store={$store}");
            return self::FAILURE;
        }

        $accessToken = $this->fetchAccessToken($credentials);
        if (!$accessToken) {
            $this->error("Failed to fetch access token.");
            return self::FAILURE;
        }

        // ✅ Create report
        $json = [
            "reportType" => "GET_MERCHANT_LISTINGS_ALL_DATA",
            "marketplaceIds" => ["ATVPDKIKX0DER"],
        ];

        $pathCreate = "/reports/2021-06-30/reports";
        $jsonbody = json_encode($json);

        $data_id = $this->fetch_id($credentials, $accessToken, $jsonbody, null, $pathCreate);

        $this->ErrorChecker($data_id);

        $reportId = $data_id['reportId'] ?? null;
        if (!$reportId) {
            $this->error("Missing reportId from create report response.");
            return self::FAILURE;
        }

        // ✅ Poll report until done
        do {
            sleep(2);
            $pathStatus = "/reports/2021-06-30/reports/{$reportId}";
            $status = $this->fetchdetailsID($credentials, $accessToken, null, $pathStatus);

            $processingStatus = $status['processingStatus'] ?? null;
            $this->line("Report {$reportId} status: " . ($processingStatus ?? 'UNKNOWN'));

        } while (($processingStatus ?? '') === 'IN_QUEUE' || ($processingStatus ?? '') === 'IN_PROGRESS');

        if (($processingStatus ?? '') !== 'DONE') {
            $this->error("Report ended with status: " . ($processingStatus ?? 'UNKNOWN'));
            return self::FAILURE;
        }

        $documentid = $status['reportDocumentId'] ?? null;
        if (!$documentid) {
            $this->error("Missing reportDocumentId.");
            return self::FAILURE;
        }

        // ✅ Get document info
        $pathDoc = "/reports/2021-06-30/documents/{$documentid}";
        $docInfo = $this->fetchSuccessDetails($credentials, $accessToken, "", null, $pathDoc);

        $compressionAlgorithm = $docInfo['compressionAlgorithm'] ?? '';
        $url = $docInfo['url'] ?? null;

        if (!$url) {
            $this->error("Missing download URL in document response.");
            return self::FAILURE;
        }

        // ✅ Download + parse
        $retrievedData = $this->download($url, $compressionAlgorithm);
        if (!is_string($retrievedData) || $retrievedData === '') {
            $this->error("Download returned empty/invalid data.");
            return self::FAILURE;
        }

        $rows = $this->processRetrievedData($retrievedData);
        if (empty($rows)) {
            $this->warn("No rows parsed from report.");
        }

        // ✅ Export to CSV (Excel-friendly)
        $filename = 'merchant_listings_' . now()->format('Ymd_His') . '.csv';
        $filepath = storage_path('app/exports/' . $filename);

        $this->exportRowsToCsv($rows, $filepath);

        $this->info("✅ CSV exported: {$filepath}");
        $this->info("Time Finished: " . now('America/Los_Angeles')->format('m-d-Y H:i:s') . " (Los Angeles)");

        return self::SUCCESS;
    }

    // ==========================================================
    // Helpers
    // ==========================================================

    private function isValidDate(string $date): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date;
    }

    /**
     * ✅ Pulls credentials from tblstores (Laravel DB)
     * Adjust table/ids if needed.
     */
    private function AWSCredentials(string $store): ?array
    {
        $store = strtoupper($store);

        $id = null;
        if ($store === 'RT')
            $id = 6;
        if ($store === 'AR')
            $id = 10;

        if (!$id)
            return null;

        $row = DB::table('tblstores')
            ->where('store_id', $id)
            ->first(['client_id', 'client_secret', 'refresh_token']);

        if (!$row)
            return null;

        return [
            'client_id' => $row->client_id,
            'client_secret' => $row->client_secret,
            'refresh_token' => $row->refresh_token,
        ];
    }

    private function fetchAccessToken(array $credentials): string|false
    {
        $postfields = [
            'grant_type' => 'refresh_token',
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'refresh_token' => $credentials['refresh_token'],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.amazon.com/auth/o2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        $decoded = json_decode($response, true);
        return $decoded['access_token'] ?? false;
    }

    private function ErrorChecker(array $data): void
    {
        if (isset($data['errors'][0])) {
            $msg = $data['errors'][0]['message'] ?? 'Unknown error';
            $code = $data['errors'][0]['code'] ?? 'Unknown code';
            $http = $data['httpcode'] ?? null;

            $this->error("Amazon error ({$http}) {$code}: {$msg}");
            throw new \RuntimeException("Amazon error: {$code} {$msg}");
        }
    }

    private function buildQueryString(?string $nextToken = null): string
    {
        return $nextToken ? $nextToken : '';
    }

    private function buildHeaders(array $credentials, string $accessToken, string $path, string $region, string $service, string $method): array
    {
        $amzDate = gmdate('Ymd\THis\Z');
        $sig = $this->calculateSignature($credentials, $amzDate, $path, $region, $service, $method);

        return [
            "Content-Type: application/json",
            "x-amz-date: {$amzDate}",
            "x-amz-access-token: {$accessToken}",
            "Authorization: {$sig['algorithm']} Credential={$credentials['client_id']}/{$sig['dateStamp']}/{$sig['region']}/{$sig['service']}/aws4_request, SignedHeaders={$sig['signedHeaders']}, Signature={$sig['signature']}"
        ];
    }

    private function calculateSignature(array $credentials, string $amzDate, string $path, string $region, string $service, string $method): array
    {
        $canonicalUri = $path;
        $canonicalQueryString = $this->buildQueryString();
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com\nx-amz-date:{$amzDate}\n";
        $signedHeaders = 'host;x-amz-date';

        // NOTE: this matches your current approach; keep it consistent
        $payloadHash = ($method === 'POST') ? hash('sha256', '') : '';

        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $algorithm = 'AWS4-HMAC-SHA256';
        $dateStamp = substr($amzDate, 0, 8);
        $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $signatureKey = $this->getSignatureKey($credentials['client_secret'], $dateStamp, $region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signatureKey);

        return [
            'algorithm' => $algorithm,
            'dateStamp' => $dateStamp,
            'signedHeaders' => $signedHeaders,
            'signature' => $signature,
            'region' => $region,
            'service' => $service
        ];
    }

    private function getSignatureKey(string $key, string $dateStamp, string $regionName, string $serviceName)
    {
        $kSecret = 'AWS4' . $key;
        $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    private function fetch_id(array $credentials, string $accessToken, string $jsonbody, ?string $nextToken, string $path): array
    {
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $service = 'execute-api';
        $region = 'us-east-1';
        $method = 'POST';

        do {
            $headers = $this->buildHeaders($credentials, $accessToken, $path, $region, $service, $method);
            $url = "{$endpoint}{$path}" . $this->buildQueryString($nextToken);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonbody);

            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($result, true) ?: [];
            $data['httpcode'] = $httpcode;

            if ($httpcode == 429) {
                sleep(60);
                continue;
            }

            if ($httpcode == 401) {
                // In your original code you refresh token here; keeping simple:
                // If you have refresh logic, add it here.
                sleep(3);
                continue;
            }

            return $data;

        } while (true);
    }

    private function fetchdetailsID(array $credentials, string $accessToken, ?string $nextToken, string $path): array
    {
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $service = 'execute-api';
        $region = 'us-east-1';
        $method = 'GET';

        do {
            $headers = $this->buildHeaders($credentials, $accessToken, $path, $region, $service, $method);
            $url = "{$endpoint}{$path}" . $this->buildQueryString($nextToken);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);

            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($result, true) ?: [];
            $data['httpcode'] = $httpcode;

            if ($httpcode == 429) {
                sleep(60);
                continue;
            }

            if ($httpcode == 401) {
                sleep(3);
                continue;
            }

            return $data;

        } while (true);
    }

    private function fetchSuccessDetails(array $credentials, string $accessToken, string $jsonbody, ?string $nextToken, string $path): array
    {
        // same as fetchdetailsID but kept separate to match your flow
        return $this->fetchdetailsID($credentials, $accessToken, $nextToken, $path);
    }

    private function download(string $url, string $compressionAlgorithm): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Download error: {$err}");
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode != 200) {
            throw new \RuntimeException("Download HTTP {$statusCode}");
        }

        if ($compressionAlgorithm === 'GZIP') {
            $decoded = gzdecode($response);
            if ($decoded === false)
                throw new \RuntimeException("GZIP decode failed");
            $response = $decoded;
        }

        // ensure UTF-8 best effort
        $encoding = mb_detect_encoding($response, 'UTF-8', true);
        if ($encoding !== 'UTF-8' && $encoding) {
            $response = iconv($encoding, 'UTF-8//IGNORE', $response);
        }

        return $response;
    }

    private function processRetrievedData(string $retrievedData): array
    {
        $rows = explode("\n", trim($retrievedData));
        $out = [];

        $header = null;
        $mskuIndex = null;

        foreach ($rows as $rowIndex => $row) {
            if (trim($row) === '') {
                continue;
            }

            $columns = explode("\t", $row);

            if ($header === null) {
                $header = $columns;

                foreach ($header as $index => $columnName) {
                    $normalized = strtolower(trim((string) $columnName));

                    if (in_array($normalized, ['seller-sku', 'sku', 'msku', 'merchant-sku'])) {
                        $mskuIndex = $index;
                        break;
                    }
                }

                $out[] = $header;
                continue;
            }

            if ($mskuIndex !== null && array_key_exists($mskuIndex, $columns)) {
                $columns[$mskuIndex] = $this->cleanMsku($columns[$mskuIndex]);
            }

            $out[] = $columns;
        }

        return $out;
    }

    private function exportRowsToCsv(array $rows, string $filepath): void
    {
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($filepath, 'w');
        if (!$fp) {
            throw new \RuntimeException("Failed to open file for writing: {$filepath}");
        }

        foreach ($rows as $row) {
            if (is_array($row)) {
                fputcsv($fp, $row);
            }
        }

        fclose($fp);
    }

    private function cleanMsku(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        // remove UTF-8 BOM if present
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

        // convert non-breaking spaces to normal spaces
        $value = str_replace("\xC2\xA0", ' ', $value);

        // remove zero-width characters
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value);

        // trim outer spaces only
        $value = trim($value);

        return $value;
    }

    private function cleanDatabaseMskus(): void
    {
        $rows = DB::table('tblfnsku')
            ->select('FNSKUID', 'MSKU')
            ->whereNotNull('MSKU')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $original = $row->MSKU;
            $cleaned = $this->cleanMsku($original);

            // skip if no change
            if ($original === $cleaned) {
                continue;
            }

            // check if cleaned value already exists (avoid conflicts)
            $exists = DB::table('tblfnsku')
                ->where('MSKU', $cleaned)
                ->where('FNSKUID', '!=', $row->FNSKUID)
                ->exists();

            if ($exists) {
                $this->warn("⚠️ Conflict: {$original} → {$cleaned} already exists, skipping");
                $skipped++;
                continue;
            }

            DB::table('tblfnsku')
                ->where('FNSKUID', $row->FNSKUID)
                ->update(['MSKU' => $cleaned]);

            $this->info("✅ Updated: {$original} → {$cleaned}");
            $updated++;
        }

        $this->info("Done cleaning MSKUs. Updated: {$updated}, Skipped: {$skipped}");
    }
}

