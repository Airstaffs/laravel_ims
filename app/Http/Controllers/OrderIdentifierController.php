<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderIdentifierController extends Controller
{
    protected $printServerUrl;

    public function __construct()
    {
        $this->printServerUrl = config('app.print_server_url', 'http://99.0.87.190:1450/ims/Admin/modules/PRD-RPN-PCN/print.php');
    }

    public function getOrderIdentifiers(Request $request)
    {
        try {
            $identifiers = [
                ['table' => 'tblrpnsticker', 'name' => 'RPN', 'prefix' => 'RPN'],
                ['table' => 'tblpcnsticker', 'name' => 'PCN', 'prefix' => 'PCN'],
                ['table' => 'tblshsticker',  'name' => 'SH',  'prefix' => 'SH'],
            ];

            $data = [];

            foreach ($identifiers as $identifier) {
                $record = DB::table($identifier['table'])->first();

                if ($record) {
                    $prefix = $identifier['prefix'];
                    $data[] = [
                        'name'    => $identifier['name'],
                        'id'      => $record->{$prefix . 'id'},
                        'start'   => $record->{$prefix . 'start'},
                        'end'     => $record->{$prefix . 'end'},
                        'QTY'     => $record->QTY,
                        'sticker' => $record->{$prefix . 'sticker'}
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data'    => $data
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order identifiers',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function updateStartCount(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'  => 'required|string|in:RPN,PCN,SH',
                'start' => 'required|integer|min:0|max:9999',
            ]);

            $tableMap = [
                'RPN' => ['table' => 'tblrpnsticker', 'prefix' => 'RPN'],
                'PCN' => ['table' => 'tblpcnsticker', 'prefix' => 'PCN'],
                'SH'  => ['table' => 'tblshsticker',  'prefix' => 'SH'],
            ];

            $config    = $tableMap[$validated['name']];
            $tableName = $config['table'];
            $prefix    = $config['prefix'];

            if ($validated['name'] === 'SH') {
                // Read current letter from DB, preserve it
                $record     = DB::table($tableName)->where($prefix . 'id', 1)->first();
                $currentEnd = $record->{$prefix . 'end'} ?? 'SH0000';
                $letter     = preg_match('/^S([H-Z])/', $currentEnd, $m) ? $m[1] : 'H';
                $newValue   = 'S' . $letter . str_pad($validated['start'], 4, '0', STR_PAD_LEFT);

                $updated = DB::table($tableName)
                    ->where($prefix . 'id', 1)
                    ->update([
                        $prefix . 'start' => $newValue,
                        $prefix . 'end'   => $newValue,
                    ]);
            } else {
                // RPN / PCN — plain integer
                $updated = DB::table($tableName)
                    ->where($prefix . 'id', 1)
                    ->update([
                        $prefix . 'start' => $validated['start'],
                        $prefix . 'end'   => $validated['start'],
                    ]);
            }

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Start count updated successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No record found to update'
                ], 404);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update start count',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function processPrintRPN_PCN_SH(Request $request)
    {
        try {
            $validated = $request->validate([
                'labelName'  => 'required|string|in:RPN,PCN,SH',
                'quantity'   => 'required|integer|min:1|max:500',
                'printerIp'  => 'required|ip',
                'lastNumber' => 'required|integer|min:0',
            ]);

            $labelName  = $validated['labelName'];
            $quantity   = $validated['quantity'];
            $printerIp  = $validated['printerIp'];

            // SH uses string-based counter with letter rollover
            if ($labelName === 'SH') {
                $record     = DB::table('tblshsticker')->where('SHid', 1)->first();
                $currentEnd = $record->SHend ?? 'SH0000';

                $labels     = $this->generateShRange($currentEnd, $quantity);
                $startLabel = $labels[0];
                $endLabel   = end($labels);

                Log::info('Starting SH print job:', [
                    'start'    => $startLabel,
                    'end'      => $endLabel,
                    'quantity' => count($labels),
                    'printer'  => $printerIp,
                ]);

                $zpl = $this->generateZPL_SH($labels);

                $printResult = $this->sendToPrinter($zpl, $printerIp);
                if ($printResult['status'] !== 'success') {
                    throw new Exception($printResult['message']);
                }

                DB::table('tblshsticker')
                    ->where('SHid', 1)
                    ->update([
                        'SHstart' => $endLabel,
                        'SHend'   => $endLabel,
                    ]);

                Log::info('SH print job completed:', [
                    'new_end'        => $endLabel,
                    'labels_printed' => count($labels),
                ]);

                return response()->json([
                    'success'      => true,
                    'message'      => "Successfully printed " . count($labels) . " SH labels",
                    'startNumber'  => $startLabel,
                    'endNumber'    => $endLabel,
                    'printedCount' => count($labels),
                    'printerIp'    => $printerIp,
                    'labels'       => "{$startLabel} to {$endLabel}",
                ]);
            }

            // RPN / PCN — original integer-based logic
            $lastNumber  = $validated['lastNumber'];
            $startNumber = $lastNumber + 1;
            $endNumber   = $startNumber + $quantity - 1;
            $totalLabels = $endNumber - $startNumber + 1;

            if ($totalLabels !== $quantity) {
                throw new Exception("Label calculation error: Expected {$quantity} labels but calculated {$totalLabels}");
            }

            Log::info('Starting print job:', [
                'label'    => $labelName,
                'start'    => $startNumber,
                'end'      => $endNumber,
                'quantity' => $totalLabels,
                'printer'  => $printerIp,
                'example'  => "{$labelName}{$startNumber} to {$labelName}{$endNumber}"
            ]);

            $zpl = $this->generateZPL($labelName, $startNumber, $endNumber, $quantity);

            $printResult = $this->sendToPrinter($zpl, $printerIp);
            if ($printResult['status'] !== 'success') {
                throw new Exception($printResult['message']);
            }

            $this->updateEndCount($labelName, $endNumber);

            Log::info('Print job completed successfully:', [
                'label'          => $labelName,
                'new_end'        => $endNumber,
                'labels_printed' => $totalLabels
            ]);

            return response()->json([
                'success'      => true,
                'message'      => "Successfully printed {$totalLabels} labels",
                'startNumber'  => $startNumber,
                'endNumber'    => $endNumber,
                'printedCount' => $totalLabels,
                'printerIp'    => $printerIp,
                'labels'       => "{$labelName}{$startNumber} to {$labelName}{$endNumber}"
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Print job failed:', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to print labels',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // =============================================
    // SH COUNTER HELPERS
    // =============================================

    protected function parseShCounter(string $value): array
    {
        if (preg_match('/^S([H-Z])(\d{4})$/', $value, $m)) {
            return [$m[1], (int) $m[2]];
        }
        return ['H', 0]; // default fallback
    }

    protected function formatShCounter(string $letter, int $num): string
    {
        return 'S' . $letter . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    protected function generateShRange(string $currentEnd, int $quantity): array
    {
        if ($quantity > 500) {
            throw new Exception("Safety limit: Cannot generate more than 500 labels at once.");
        }

        [$letter, $num] = $this->parseShCounter($currentEnd);

        $labels = [];

        for ($i = 0; $i < $quantity; $i++) {
            $num++;

            if ($num > 9999) {
                $next = chr(ord($letter) + 1);
                if ($next > 'Z') {
                    // Reached SZ9999 — reset back to SH0001
                    $letter = 'H';
                    $num    = 1;
                } else {
                    $letter = $next;
                    $num    = 1;
                }
            }

            $labels[] = $this->formatShCounter($letter, $num);
        }

        return $labels;
    }

    protected function generateZPL_SH(array $labels): string
    {
        $zpl = '';

        foreach ($labels as $value) {
            $zpl .= "^XA";
            $zpl .= "^FO55,30^FB400,2,0,C^AON,24,24^BCN,100,N,N,N,A^FD" . $value . "^FS";
            $zpl .= "^FO10,140^FB400,1,0,C^ADN,24,24^FD" . $value . "^FS";
            $zpl .= "^XZ";
        }

        return $zpl;
    }

    // =============================================
    // ORIGINAL RPN/PCN METHODS (unchanged)
    // =============================================

    protected function generateZPL(string $labelName, int $startNumber, int $endNumber, int $expectedCount): string
    {
        $calculatedLabels = $endNumber - $startNumber + 1;

        if ($calculatedLabels !== $expectedCount) {
            throw new Exception("ZPL generation error: Expected {$expectedCount} labels but calculated {$calculatedLabels}");
        }

        if ($calculatedLabels > 500) {
            throw new Exception("Safety limit: Cannot generate more than 500 labels");
        }

        Log::info('Generating ZPL:', [
            'start' => $startNumber,
            'end'   => $endNumber,
            'count' => $calculatedLabels
        ]);

        $zpl          = '';
        $labelCounter = 0;

        for ($i = $startNumber; $i <= $endNumber; $i++) {
            if ($labelCounter >= $expectedCount) break;

            $value  = $labelName . $i;
            $zpl   .= "^XA";
            $zpl   .= "^FO55,30^FB400,2,0,C^AON,24,24^BCN,100,N,N,N,A^FD" . $value . "^FS";
            $zpl   .= "^FO10,140^FB400,1,0,C^ADN,24,24^FD" . $value . "^FS";
            $zpl   .= "^XZ";

            $labelCounter++;
        }

        Log::info('ZPL generation complete:', [
            'labels_generated' => $labelCounter,
            'expected'         => $expectedCount,
        ]);

        if ($labelCounter !== $expectedCount) {
            throw new Exception("Label count mismatch: Generated {$labelCounter} but expected {$expectedCount}");
        }

        return $zpl;
    }

    protected function updateEndCount(string $labelName, int $endNumber): void
    {
        $tableMap = [
            'RPN' => ['table' => 'tblrpnsticker', 'prefix' => 'RPN'],
            'PCN' => ['table' => 'tblpcnsticker', 'prefix' => 'PCN'],
        ];

        if (!isset($tableMap[$labelName])) {
            throw new Exception("Invalid label name: {$labelName}");
        }

        $config    = $tableMap[$labelName];
        $tableName = $config['table'];
        $prefix    = $config['prefix'];

        $updated = DB::table($tableName)
            ->where($prefix . 'id', 1)
            ->update([
                $prefix . 'end'   => $endNumber,
                $prefix . 'start' => $endNumber
            ]);

        if (!$updated) {
            throw new Exception("Failed to update end count for {$labelName}");
        }

        Log::info('Database updated:', [
            'table'   => $tableName,
            'label'   => $labelName,
            'new_end' => $endNumber
        ]);
    }

    protected function sendToPrinter(string $zpl, string $printerIp): array
    {
        try {
            Log::info('Sending print job:', [
                'printer_ip' => $printerIp,
                'server_url' => $this->printServerUrl,
                'zpl_length' => strlen($zpl)
            ]);

            $postData = http_build_query([
                'zpl'           => $zpl,
                'printerSelect' => $printerIp
            ]);

            $ch = curl_init($this->printServerUrl);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postData,
                CURLOPT_HEADER         => false,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $error    = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            Log::info('Print server response:', [
                'response'   => $response,
                'http_code'  => $httpCode,
                'error'      => $error,
                'printer_ip' => $printerIp
            ]);

            if ($httpCode === 200 && $response === "Message sent to printer successfully.") {
                return [
                    'status'  => 'success',
                    'message' => "Label printed successfully to printer {$printerIp}"
                ];
            }

            $errorMsg = $response ?: $error ?: 'Unknown error';
            return [
                'status'    => 'error',
                'message'   => "Failed to print to {$printerIp}: {$errorMsg}",
                'http_code' => $httpCode
            ];

        } catch (\Throwable $e) {
            Log::error('Printer communication error:', [
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
                'printer_ip' => $printerIp
            ]);

            return [
                'status'  => 'error',
                'message' => 'Printer communication error: ' . $e->getMessage()
            ];
        }
    }
}