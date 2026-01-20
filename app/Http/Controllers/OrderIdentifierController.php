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
                ['table' => 'tblshelfsticker', 'name' => 'SHLF', 'prefix' => 'SHLF'],
            ];
            
            $data = [];
            
            foreach ($identifiers as $identifier) {
                $record = DB::table($identifier['table'])->first();
                
                if ($record) {
                    $prefix = $identifier['prefix'];
                    $data[] = [
                        'name' => $identifier['name'],
                        'id' => $record->{$prefix . 'id'},
                        'start' => $record->{$prefix . 'start'},
                        'end' => $record->{$prefix . 'end'},
                        'QTY' => $record->QTY,
                        'sticker' => $record->{$prefix . 'sticker'}
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order identifiers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStartCount(Request $request) 
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|in:RPN,PCN,SHLF',
                'start' => 'required|integer|min:0',
            ]);

            $tableMap = [
                'RPN' => ['table' => 'tblrpnsticker', 'prefix' => 'RPN'],
                'PCN' => ['table' => 'tblpcnsticker', 'prefix' => 'PCN'],
                'SHLF' => ['table' => 'tblshelfsticker', 'prefix' => 'SHLF']
            ];

            $config = $tableMap[$validated['name']];
            $tableName = $config['table'];
            $prefix = $config['prefix'];

            $updated = DB::table($tableName)
                ->where($prefix . 'id', 1)
                ->update([
                    $prefix . 'start' => $validated['start'],
                    $prefix . 'end' => $validated['start'],
                ]);

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
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update start count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function processPrintRPN_PCN_SH(Request $request)
    {
        try {
            // 1️⃣ Validate request
            $validated = $request->validate([
                'labelName'   => 'required|string|in:RPN,PCN,SHLF',
                'quantity'    => 'required|integer|min:1|max:500',
                'printerIp'   => 'required|ip',
                'lastNumber'  => 'required|integer|min:0|max:999999'
            ]);

            $labelName   = $validated['labelName'];
            $quantity    = $validated['quantity'];
            $lastNumber  = $validated['lastNumber'];
            $printerIp   = $validated['printerIp'];

            // 2️⃣ Calculate print range with explicit formula
            $startNumber = $lastNumber + 1;
            $endNumber   = $startNumber + $quantity - 1;  // FIXED: More explicit
            $totalLabels = $endNumber - $startNumber + 1; // Should equal quantity
            
            // Safety check
            if ($totalLabels !== $quantity) {
                throw new Exception("Label calculation error: Expected {$quantity} labels but calculated {$totalLabels}");
            }

            Log::info('Starting print job:', [
                'label' => $labelName,
                'start' => $startNumber,
                'end' => $endNumber,
                'quantity' => $totalLabels,
                'printer' => $printerIp,
                'example' => "{$labelName}{$startNumber} to {$labelName}{$endNumber}"
            ]);

            // 3️⃣ Generate ZPL with safety checks - FIXED: Added $quantity parameter
            $zpl = $this->generateZPL($labelName, $startNumber, $endNumber, $quantity);

            // 4️⃣ Send to printer
            $printResult = $this->sendToPrinter($zpl, $printerIp);

            // 5️⃣ Check if print was successful
            if ($printResult['status'] !== 'success') {
                throw new Exception($printResult['message']);
            }

            // 6️⃣ Update database
            $this->updateEndCount($labelName, $endNumber);

            Log::info('Print job completed successfully:', [
                'label' => $labelName,
                'new_end' => $endNumber,
                'labels_printed' => $totalLabels
            ]);

            // 7️⃣ Return response
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
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Print job failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to print labels',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate ZPL code for multiple labels with tear-off mode
     * Each label is separate and can be torn off
     */
   protected function generateZPL(string $labelName, int $startNumber, int $endNumber, int $expectedCount): string
{
    $config = [
        'paperWidth'   => 812,     // 4 inch @ 203dpi
        'labelHeight'  => 406,     // 2 inch height
        'barcodeY'     => 60,      // Barcode Y position
        'textY'        => 250,     // Text Y position  
        'barcodeH'     => 150,     // Barcode height
        'textSize'     => 60,      // Text size
    ];

    $calculatedLabels = $endNumber - $startNumber + 1;
    
    if ($calculatedLabels !== $expectedCount) {
        throw new Exception("ZPL generation error: Expected {$expectedCount} labels but calculated {$calculatedLabels}");
    }
    
    if ($calculatedLabels > 500) {
        throw new Exception("Safety limit: Cannot generate more than 500 labels");
    }

    Log::info('Generating ZPL:', [
        'start' => $startNumber,
        'end' => $endNumber,
        'count' => $calculatedLabels
    ]);

    $zpl = [];
    $labelCounter = 0;
    
    for ($i = $startNumber; $i <= $endNumber; $i++) {
        if ($labelCounter >= $expectedCount) break;
        
        $value = $labelName . $i;
        
        $zpl[] = "^XA";
        $zpl[] = "^PW{$config['paperWidth']}";
        $zpl[] = "^LL{$config['labelHeight']}";
        $zpl[] = "^MNN";
        $zpl[] = "^PQ1,0,1,Y";
        
        // Centered barcode using ^FB (Field Block) for centering
        $zpl[] = "^FO0,{$config['barcodeY']}^BY3^A0N,1,1^FB{$config['paperWidth']},1,0,C^BCN,{$config['barcodeH']},Y,N,N^FD{$value}^FS";
        
        // Centered text
        $zpl[] = "^FO0,{$config['textY']}^A0N,{$config['textSize']},{$config['textSize']}^FB{$config['paperWidth']},1,0,C^FD{$value}^FS";
        
        $zpl[] = "^XZ";
        
        $labelCounter++;
    }

    Log::info('ZPL generation complete:', [
        'labels_generated' => $labelCounter,
        'expected' => $expectedCount,
        'zpl_length' => strlen(implode("\n", $zpl))
    ]);

    if ($labelCounter !== $expectedCount) {
        throw new Exception("Label count mismatch: Generated {$labelCounter} but expected {$expectedCount}");
    }

    return implode("\n", $zpl);
}

    /**
     * Update the end count in database
     */
    protected function updateEndCount(string $labelName, int $endNumber): void
    {
        $tableMap = [
            'RPN' => ['table' => 'tblrpnsticker', 'prefix' => 'RPN'],
            'PCN' => ['table' => 'tblpcnsticker', 'prefix' => 'PCN'],
            'SHLF'  => ['table' => 'tblshelfsticker', 'prefix' => 'SHLF']
        ];

        if (!isset($tableMap[$labelName])) {
            throw new Exception("Invalid label name: {$labelName}");
        }

        $config = $tableMap[$labelName];
        $tableName = $config['table'];
        $prefix = $config['prefix'];

        $updated = DB::table($tableName)
            ->where($prefix . 'id', 1)
            ->update([
                $prefix . 'end' => $endNumber,
                $prefix . 'start' => $endNumber
            ]);

        if (!$updated) {
            throw new Exception("Failed to update end count for {$labelName}");
        }

        Log::info('Database updated:', [
            'table' => $tableName,
            'label' => $labelName,
            'new_end' => $endNumber
        ]);
    }

    /**
     * Send ZPL code to printer via print server
     */
    protected function sendToPrinter(string $zpl, string $printerIp): array
    {
        try {
            Log::info('Sending print job:', [
                'printer_ip' => $printerIp,
                'server_url' => $this->printServerUrl,
                'zpl_length' => strlen($zpl)
            ]);
            
            // Prepare POST data
            $postData = http_build_query([
                'zpl' => $zpl,
                'printerSelect' => $printerIp
            ]);
            
            // Initialize cURL
            $ch = curl_init($this->printServerUrl);
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HEADER => false,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            
            // Execute request
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            Log::info('Print server response:', [
                'response' => $response,
                'http_code' => $httpCode,
                'error' => $error,
                'printer_ip' => $printerIp
            ]);
            
            // Check for success
            if ($httpCode === 200 && $response === "Message sent to printer successfully.") {
                return [
                    'status' => 'success',
                    'message' => "Label printed successfully to printer {$printerIp}"
                ];
            }
            
            // Handle errors
            $errorMsg = $response ?: $error ?: 'Unknown error';
            return [
                'status' => 'error',
                'message' => "Failed to print to {$printerIp}: {$errorMsg}",
                'http_code' => $httpCode
            ];
            
        } catch (\Throwable $e) {
            Log::error('Printer communication error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'printer_ip' => $printerIp
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Printer communication error: ' . $e->getMessage()
            ];
        }
    }
}