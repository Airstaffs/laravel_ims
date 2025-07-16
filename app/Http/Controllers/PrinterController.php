<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PrintLabelService;
use App\Http\Controllers\BasetablesController;
use Exception;

class PrinterController extends BasetablesController
{
    protected $printLabelService;

    public function __construct(PrintLabelService $printLabelService)
    {
        parent::__construct();
        $this->printLabelService = $printLabelService;
    }

    /**
     * Get the correct FNSKU column name from the database
     */
    private function getFnskuColumnName()
    {
        $productColumns = DB::getSchemaBuilder()->getColumnListing($this->productTable);
        $possibleFnskuColumns = ['FNSKUviewer', 'fnsku', 'FNSKU', 'fnsku_viewer', 'FnskuViewer'];
        
        foreach ($possibleFnskuColumns as $column) {
            if (in_array($column, $productColumns)) {
                return $column;
            }
        }
        
        return null; // No FNSKU column found
    }

    /**
     * Check if a serial number meets print conditions
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkSerial(Request $request)
    {
        try {
            $request->validate([
                'serial_number' => 'required|string'
            ]);

            $serialNumber = trim($request->serial_number);
            
            // Get the correct FNSKU column name
            $fnskuColumn = $this->getFnskuColumnName();
            
            if (!$fnskuColumn) {
                Log::error('No FNSKU column found in product table');
                return response()->json([
                    'success' => false,
                    'message' => 'Database configuration error: FNSKU column not found',
                    'meets_print_conditions' => false
                ], 500);
            }

            // Build the query with the correct column name
            $query = DB::table($this->productTable . ' as prod');
            
            // Add joins only if tables exist
            if (DB::getSchemaBuilder()->hasTable($this->fnskuTable)) {
                $query->leftJoin($this->fnskuTable . ' as fnsku', 'prod.' . $fnskuColumn, '=', 'fnsku.FNSKU');
            }
            
            if (DB::getSchemaBuilder()->hasTable($this->asinTable)) {
                $query->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN');
            }
            
            // Get available columns for each table
            $productColumns = DB::getSchemaBuilder()->getColumnListing($this->productTable);
            $fnskuColumns = DB::getSchemaBuilder()->hasTable($this->fnskuTable) ? 
                DB::getSchemaBuilder()->getColumnListing($this->fnskuTable) : [];
            $asinColumns = DB::getSchemaBuilder()->hasTable($this->asinTable) ? 
                DB::getSchemaBuilder()->getColumnListing($this->asinTable) : [];
            
            // Build select array with only existing columns
            $selectColumns = [
                'prod.ProductID',
                'prod.rtcounter',
                'prod.serialnumber',
                'prod.' . $fnskuColumn . ' as FNSKUviewer'
            ];
            
            // Add optional product columns if they exist
            $optionalProductColumns = [
                'serialnumberb', 'serialnumberc', 'serialnumberd', 'ProductModuleLoc',
                'printCount', 'warehouselocation', 'notes', 'stickernote', 'basketnumber',
                'priorityrank', 'returnstatus', 'validation_status'
            ];
            
            foreach ($optionalProductColumns as $column) {
                if (in_array($column, $productColumns)) {
                    $selectColumns[] = 'prod.' . $column;
                }
            }
            
            // Add FNSKU table columns if they exist
            if (in_array('grading', $fnskuColumns)) {
                $selectColumns[] = 'fnsku.grading as fnsku_grading';
            }
            if (in_array('storename', $fnskuColumns)) {
                $selectColumns[] = 'fnsku.storename as fnsku_storename';
            }
            if (in_array('FNSKU', $fnskuColumns)) {
                $selectColumns[] = 'fnsku.FNSKU';
            }
            
            // Add ASIN table columns if they exist
            if (in_array('ASIN', $asinColumns)) {
                $selectColumns[] = 'asin.ASIN as ASINviewer';
            }
            if (in_array('internal', $asinColumns)) {
                $selectColumns[] = 'asin.internal as AStitle';
            }
            if (in_array('asinStatus', $asinColumns)) {
                $selectColumns[] = 'asin.asinStatus';
            }
            
            $query->select($selectColumns);
            
            // Add where conditions for serial number search
            $query->where(function ($q) use ($serialNumber, $productColumns) {
                $q->where('prod.serialnumber', $serialNumber);
                
                if (in_array('serialnumberb', $productColumns)) {
                    $q->orWhere('prod.serialnumberb', $serialNumber);
                }
                if (in_array('serialnumberc', $productColumns)) {
                    $q->orWhere('prod.serialnumberc', $serialNumber);
                }
                if (in_array('serialnumberd', $productColumns)) {
                    $q->orWhere('prod.serialnumberd', $serialNumber);
                }
            });
            
            // Add status conditions if columns exist
            if (in_array('returnstatus', $productColumns)) {
                $query->where('prod.returnstatus', 'Not Returned');
            }
            if (in_array('ProductModuleLoc', $productColumns)) {
                $query->where('prod.ProductModuleLoc', '!=', 'Migrated');
            }
            
            $product = $query->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Serial number not found or item already migrated',
                    'meets_print_conditions' => false
                ]);
            }

            // Check if product meets print conditions
            $conditions = $this->checkPrintConditions($product);
            
            if ($conditions['meets_conditions']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Item ready for printing',
                    'meets_print_conditions' => true,
                    'product_data' => [
                        'ProductID' => $product->ProductID,
                        'rtcounter' => $product->rtcounter,
                        'FNSKUviewer' => $product->FNSKUviewer ?? null,
                        'ASINviewer' => $product->ASINviewer ?? null,
                        'AStitle' => $product->AStitle ?? null,
                        'fnsku_grading' => $product->fnsku_grading ?? null,
                        'fnsku_storename' => $product->fnsku_storename ?? null,
                        'serialnumber' => $product->serialnumber,
                        'serialnumberb' => $product->serialnumberb ?? null,
                        'serialnumberc' => $product->serialnumberc ?? null,
                        'serialnumberd' => $product->serialnumberd ?? null,
                        'ProductModuleLoc' => $product->ProductModuleLoc ?? null,
                        'printCount' => $product->printCount ?? 0,
                        'warehouselocation' => $product->warehouselocation ?? null,
                        'notes' => $product->notes ?? null,
                        'stickernote' => $product->stickernote ?? null,
                        'basketnumber' => $product->basketnumber ?? null,
                        'priorityrank' => $product->priorityrank ?? null,
                        'validation_status' => $product->validation_status ?? null,
                        'asinStatus' => $product->asinStatus ?? null,
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $conditions['message'],
                    'meets_print_conditions' => false,
                    'product_data' => [
                        'ProductID' => $product->ProductID,
                        'rtcounter' => $product->rtcounter,
                        'ProductModuleLoc' => $product->ProductModuleLoc ?? null,
                        'current_status' => $conditions['current_status'],
                        'AStitle' => $product->AStitle ?? 'Unknown Title',
                        'ASINviewer' => $product->ASINviewer ?? null,
                        'FNSKUviewer' => $product->FNSKUviewer ?? null
                    ]
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error checking serial for printing:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'serial_number' => $request->serial_number ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking serial number: ' . $e->getMessage(),
                'meets_print_conditions' => false
            ], 500);
        }
    }

    /**
     * Print label for a product
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function printLabel(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'serial_number' => 'required|string',
                'print_data' => 'required|array'
            ]);

            $serialNumber = trim($request->serial_number);
            $printData = $request->print_data;
            
            // Get username safely
            $user = Auth::user();
            $username = $user ? ($user->username ?? $user->name ?? 'Unknown') : 'System';

            // Get the ProductID from the print data
            $productId = $printData['product_data']['ProductID'] ?? null;
            
            if (!$productId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product ID not found in print data'
                ], 400);
            }

            // Get the correct FNSKU column name
            $fnskuColumn = $this->getFnskuColumnName();
            
            if (!$fnskuColumn) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database configuration error: FNSKU column not found'
                ], 500);
            }

            // Double-check the product still exists and meets conditions
            $query = DB::table($this->productTable . ' as prod');
            
            // Add joins only if tables exist
            if (DB::getSchemaBuilder()->hasTable($this->fnskuTable)) {
                $query->leftJoin($this->fnskuTable . ' as fnsku', 'prod.' . $fnskuColumn, '=', 'fnsku.FNSKU');
            }
            
            if (DB::getSchemaBuilder()->hasTable($this->asinTable)) {
                $query->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN');
            }
            
            // Get available columns
            $productColumns = DB::getSchemaBuilder()->getColumnListing($this->productTable);
            $fnskuColumns = DB::getSchemaBuilder()->hasTable($this->fnskuTable) ? 
                DB::getSchemaBuilder()->getColumnListing($this->fnskuTable) : [];
            $asinColumns = DB::getSchemaBuilder()->hasTable($this->asinTable) ? 
                DB::getSchemaBuilder()->getColumnListing($this->asinTable) : [];
            
            // Build select array
            $selectColumns = [
                'prod.ProductID',
                'prod.rtcounter',
                'prod.' . $fnskuColumn . ' as FNSKUviewer',
                'prod.serialnumber'
            ];
            
            // Add optional columns
            $optionalColumns = [
                'serialnumberb', 'serialnumberc', 'serialnumberd', 'ProductModuleLoc',
                'printCount', 'warehouselocation', 'notes', 'stickernote', 'basketnumber',
                'priorityrank', 'returnstatus', 'validation_status'
            ];
            
            foreach ($optionalColumns as $column) {
                if (in_array($column, $productColumns)) {
                    $selectColumns[] = 'prod.' . $column;
                }
            }
            
            // Add FNSKU table columns if they exist
            if (in_array('grading', $fnskuColumns)) {
                $selectColumns[] = 'fnsku.grading as fnsku_grading';
            }
            if (in_array('storename', $fnskuColumns)) {
                $selectColumns[] = 'fnsku.storename as fnsku_storename';
            }
            
            // Add ASIN table columns if they exist
            if (in_array('ASIN', $asinColumns)) {
                $selectColumns[] = 'asin.ASIN as ASINviewer';
            }
            if (in_array('internal', $asinColumns)) {
                $selectColumns[] = 'asin.internal as AStitle';
            }
            if (in_array('asinStatus', $asinColumns)) {
                $selectColumns[] = 'asin.asinStatus';
            }
            
            $product = $query->select($selectColumns)
                ->where('prod.ProductID', $productId)
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found or status changed'
                ], 404);
            }

            // Check conditions again before printing
            $conditions = $this->checkPrintConditions($product);
            
            if (!$conditions['meets_conditions']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product no longer meets print conditions: ' . $conditions['message']
                ], 400);
            }

            // Use the PrintLabelService to print the label
            $printResult = $this->printLabelService->printLabel($productId, $username);

            if ($printResult['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => 'Label printed successfully',
                    'serial_number' => $serialNumber,
                    'print_count' => ($product->printCount ?? 0) + 1,
                    'product_title' => $product->AStitle ?? 'Unknown Title',
                    'asin' => $product->ASINviewer ?? null,
                    'fnsku' => $product->FNSKUviewer ?? null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $printResult['message']
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Error printing label:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'serial_number' => $request->serial_number ?? 'unknown',
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error printing label: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a product meets the conditions for printing
     *
     * @param object $product The product object
     * @return array Conditions check result
     */
    protected function checkPrintConditions($product)
    {
        try {
            // 1. Check if product is in the right location/module for printing
            $validLocations = [
                'Packing', 
                'Stockroom', 
                'Validation', 
                'Production Area',
                'Testing',
                'Cleaning',
                'Labeling',
            ];
            
            if (isset($product->ProductModuleLoc) && !in_array($product->ProductModuleLoc, $validLocations)) {
                return [
                    'meets_conditions' => false,
                    'message' => 'Item is not in a valid location for printing. Current location: ' . $product->ProductModuleLoc . '. Valid locations: ' . implode(', ', $validLocations),
                    'current_status' => $product->ProductModuleLoc
                ];
            }

            // 2. Check if item is returned
            if (isset($product->returnstatus) && $product->returnstatus === 'Returned') {
                return [
                    'meets_conditions' => false,
                    'message' => 'Cannot print label for returned items',
                    'current_status' => 'Returned'
                ];
            }

            // 3. Check if item is migrated
            if (isset($product->ProductModuleLoc) && $product->ProductModuleLoc === 'Migrated') {
                return [
                    'meets_conditions' => false,
                    'message' => 'Cannot print label for migrated items',
                    'current_status' => 'Migrated'
                ];
            }

            // 4. Check if required FNSKU or ASIN information is present
            if (empty($product->FNSKUviewer) && empty($product->ASINviewer)) {
                return [
                    'meets_conditions' => false,
                    'message' => 'Item missing required FNSKU or ASIN information for printing',
                    'current_status' => 'Missing FNSKU/ASIN'
                ];
            }

            // 5. Check if serial number exists
            if (empty($product->serialnumber) && empty($product->serialnumberb) && 
                empty($product->serialnumberc) && empty($product->serialnumberd)) {
                return [
                    'meets_conditions' => false,
                    'message' => 'Item missing serial number information required for printing',
                    'current_status' => 'Missing Serial Number'
                ];
            }

            // 6. Check if grading is complete (optional check)
            if (isset($product->fnsku_grading) && empty($product->fnsku_grading)) {
                return [
                    'meets_conditions' => false,
                    'message' => 'Item grading is not complete - required for label printing',
                    'current_status' => 'Grading Incomplete'
                ];
            }

            // 7. Check if RT counter exists
            if (empty($product->rtcounter)) {
                return [
                    'meets_conditions' => false,
                    'message' => 'Item missing RT counter - required for tracking',
                    'current_status' => 'Missing RT Counter'
                ];
            }

            // 8. Check validation status for certain modules
            if (isset($product->ProductModuleLoc) && $product->ProductModuleLoc === 'Validation' && 
                isset($product->validation_status) && 
                $product->validation_status !== 'validated') {
                return [
                    'meets_conditions' => false,
                    'message' => 'Item in Validation module must be validated before printing',
                    'current_status' => 'Not Validated'
                ];
            }

            // All conditions passed
            return [
                'meets_conditions' => true,
                'message' => 'Item ready for printing',
                'current_status' => 'Ready for Print'
            ];

        } catch (Exception $e) {
            Log::error('Error checking print conditions:', [
                'error' => $e->getMessage(),
                'product_id' => $product->ProductID ?? 'unknown'
            ]);

            return [
                'meets_conditions' => false,
                'message' => 'Error checking print conditions: ' . $e->getMessage(),
                'current_status' => 'Error'
            ];
        }
    }

    /**
     * Get printer status and information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatus()
    {
        try {
            $printerIp = config('app.printer_ip', '192.168.1.109');
            
            return response()->json([
                'success' => true,
                'printer_ip' => $printerIp,
                'status' => 'online',
                'message' => 'Printer service is available'
            ]);

        } catch (Exception $e) {
            Log::error('Error getting printer status:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error getting printer status: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Test printer connection
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testConnection()
    {
        try {
            $printerIp = config('app.printer_ip', '192.168.1.109');
            $printServerUrl = config('app.print_server_url', 'http://99.0.87.190:1450/ims/Admin/modules/PRD-RPN-PCN/print.php');
            
            // Test connection to print server
            $ch = curl_init($printServerUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($httpCode === 200) {
                return response()->json([
                    'success' => true,
                    'message' => 'Printer connection test successful',
                    'printer_ip' => $printerIp,
                    'print_server_url' => $printServerUrl,
                    'status' => 'online'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Printer connection test failed',
                    'error' => $error,
                    'http_code' => $httpCode,
                    'status' => 'offline'
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error testing printer connection:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error testing printer connection: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }



    /**
     * Add test endpoint to verify printer functionality
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testPrint()
    {
        try {
            // Get username safely
            $user = Auth::user();
            $username = $user ? ($user->username ?? $user->name ?? 'Test User') : 'Test User';
            
            // Create a simple test print
            $testResult = $this->printLabelService->testPrint($username);
            
            return response()->json([
                'success' => $testResult['status'] === 'success',
                'message' => $testResult['message'],
                'username' => $username
            ]);
            
        } catch (Exception $e) {
            Log::error('Error testing printer:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error testing printer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug database structure and query
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function debugDatabase(Request $request)
    {
        try {
            $serialNumber = $request->input('serial_number', 'test123');
            
            // Check table existence
            $productTableExists = DB::getSchemaBuilder()->hasTable($this->productTable);
            $fnskuTableExists = DB::getSchemaBuilder()->hasTable($this->fnskuTable);
            $asinTableExists = DB::getSchemaBuilder()->hasTable($this->asinTable);
            
            $debug = [
                'table_names' => [
                    'product_table' => $this->productTable,
                    'fnsku_table' => $this->fnskuTable,
                    'asin_table' => $this->asinTable,
                    'history_table' => $this->itemProcessHistoryTable ?? 'Not set'
                ],
                'table_existence' => [
                    'product_exists' => $productTableExists,
                    'fnsku_exists' => $fnskuTableExists,
                    'asin_exists' => $asinTableExists
                ]
            ];
            
            // Get column names for each table
            if ($productTableExists) {
                $debug['product_columns'] = DB::getSchemaBuilder()->getColumnListing($this->productTable);
            }
            
            if ($fnskuTableExists) {
                $debug['fnsku_columns'] = DB::getSchemaBuilder()->getColumnListing($this->fnskuTable);
            }
            
            if ($asinTableExists) {
                $debug['asin_columns'] = DB::getSchemaBuilder()->getColumnListing($this->asinTable);
            }
            
            // Test the FNSKU column detection
            $debug['fnsku_column_detected'] = $this->getFnskuColumnName();
            
            // Test a simple query on the product table
            if ($productTableExists) {
                try {
                    $productCount = DB::table($this->productTable)->count();
                    $debug['product_count'] = $productCount;
                    
                    // Test getting a sample product
                    $sampleProduct = DB::table($this->productTable)
                        ->select('*')
                        ->limit(1)
                        ->first();
                        
                    $debug['sample_product'] = $sampleProduct;
                    
                } catch (Exception $e) {
                    $debug['product_query_error'] = $e->getMessage();
                }
            }
            
            return response()->json([
                'success' => true,
                'debug_info' => $debug
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Debug error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}