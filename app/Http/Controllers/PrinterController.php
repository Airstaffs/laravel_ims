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
            
            // Search for the product by serial number with proper joins to get all needed data
            $product = DB::table($this->productTable . ' as prod')
                ->leftJoin($this->fnskuTable . ' as fnsku', 'prod.FNSKUviewer', '=', 'fnsku.FNSKU')
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->select([
                    'prod.ProductID',
                    'prod.rtcounter',
                    'prod.serialnumber',
                    'prod.serialnumberb',
                    'prod.serialnumberc',
                    'prod.serialnumberd',
                    'prod.ProductModuleLoc',
                    'prod.printCount',
                    'prod.warehouselocation',
                    'prod.notes',
                    'prod.stickernote',
                    'prod.basketnumber',
                    'prod.priorityrank',
                    'prod.returnstatus',
                    'prod.validation_status',
                    'prod.FNSKUviewer', // FIX: Add this field back - it was missing in paste-2.txt
                    'fnsku.FNSKU',
                    'fnsku.grading as fnsku_grading',
                    'fnsku.storename as fnsku_storename',
                    'asin.ASIN as ASINviewer',
                    'asin.internal as AStitle',
                    'asin.asinStatus'
                ])
                ->where(function ($query) use ($serialNumber) {
                    $query->where('prod.serialnumber', $serialNumber)
                          ->orWhere('prod.serialnumberb', $serialNumber)
                          ->orWhere('prod.serialnumberc', $serialNumber)
                          ->orWhere('prod.serialnumberd', $serialNumber);
                })
                ->where('prod.returnstatus', 'Not Returned')
                ->where('prod.ProductModuleLoc', '!=', 'Migrated')
                ->first();

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
                        'FNSKUviewer' => $product->FNSKUviewer, // This should now work
                        'ASINviewer' => $product->ASINviewer,
                        'AStitle' => $product->AStitle,
                        'fnsku_grading' => $product->fnsku_grading,
                        'fnsku_storename' => $product->fnsku_storename,
                        'serialnumber' => $product->serialnumber,
                        'serialnumberb' => $product->serialnumberb,
                        'serialnumberc' => $product->serialnumberc,
                        'serialnumberd' => $product->serialnumberd,
                        'ProductModuleLoc' => $product->ProductModuleLoc,
                        'printCount' => $product->printCount ?? 0,
                        'warehouselocation' => $product->warehouselocation,
                        'notes' => $product->notes,
                        'stickernote' => $product->stickernote,
                        'basketnumber' => $product->basketnumber,
                        'priorityrank' => $product->priorityrank,
                        'validation_status' => $product->validation_status,
                        'asinStatus' => $product->asinStatus,
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
                        'ProductModuleLoc' => $product->ProductModuleLoc,
                        'current_status' => $conditions['current_status'],
                        'AStitle' => $product->AStitle ?? 'Unknown Title',
                        'ASINviewer' => $product->ASINviewer,
                        'FNSKUviewer' => $product->FNSKUviewer // This should now work
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
            'printer_id' => 'required|integer',
            'print_data' => 'required|array'
        ]);

        $serialNumber = trim($request->serial_number);
        $printerId = $request->printer_id;
        $printData = $request->print_data;
        
        // Get selected printer info
        $selectedPrinter = DB::table('tblprinters')
            ->where('printerid', $printerId)
            ->first();

            // Add this debug logging:
            Log::info('Selected printer details:', [
                'printer_id' => $printerId,
                'printer_data' => $selectedPrinter,
                'printer_ip' => $selectedPrinter->printerip ?? 'NOT FOUND'
            ]);
            
        if (!$selectedPrinter) {
            return response()->json([
                'success' => false,
                'message' => 'Selected printer not found'
            ], 404);
        }
        
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

        // Double-check the product still exists and meets conditions
        $product = DB::table($this->productTable . ' as prod')
            ->leftJoin($this->fnskuTable . ' as fnsku', 'prod.FNSKUviewer', '=', 'fnsku.FNSKU')
            ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
            ->select([
                'prod.ProductID',
                'prod.rtcounter',
                'prod.serialnumber',
                'prod.serialnumberb',
                'prod.serialnumberc',
                'prod.serialnumberd',
                'prod.ProductModuleLoc',
                'prod.printCount',
                'prod.warehouselocation',
                'prod.notes',
                'prod.stickernote',
                'prod.basketnumber',
                'prod.priorityrank',
                'prod.returnstatus',
                'prod.validation_status',
                'prod.FNSKUviewer',
                'fnsku.FNSKU',
                'fnsku.grading as fnsku_grading',
                'fnsku.storename as fnsku_storename',
                'asin.ASIN as ASINviewer',
                'asin.internal as AStitle',
                'asin.asinStatus'
            ])
            ->where('prod.ProductID', $productId)
            ->where('prod.returnstatus', 'Not Returned')
            ->where('prod.ProductModuleLoc', '!=', 'Migrated')
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

        // Use the PrintLabelService to print the label with selected printer
        $printResult = $this->printLabelService->printLabel($productId, $username, $selectedPrinter);

        // Check if the print service returned a successful result
        if ($printResult['status'] === 'success') {
            return response()->json([
                'success' => true,
                'message' => 'Label printed successfully to ' . $selectedPrinter->printername,
                'serial_number' => $serialNumber,
                'printer_name' => $selectedPrinter->printername,
                'print_count' => ($product->printCount ?? 0) + 1,
                'product_title' => $product->AStitle ?? 'Unknown Title',
                'asin' => $product->ASINviewer,
                'fnsku' => $product->FNSKUviewer,
                'product_data' => [
                    'ProductID' => $product->ProductID,
                    'rtcounter' => $product->rtcounter,
                    'ProductModuleLoc' => $product->ProductModuleLoc,
                    'current_status' => $conditions['current_status'],
                    'printCount' => ($product->printCount ?? 0) + 1
                ]
            ], 200);
        } else {
            // Print service failed
            return response()->json([
                'success' => false,
                'message' => 'Print failed: ' . ($printResult['message'] ?? 'Unknown error')
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
            
            if (!in_array($product->ProductModuleLoc, $validLocations)) {
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
            if ($product->ProductModuleLoc === 'Migrated') {
                return [
                    'meets_conditions' => false,
                    'message' => 'Cannot print label for migrated items',
                    'current_status' => 'Migrated'
                ];
            }

            // 4. Check if required FNSKU or ASIN information is present
            // FIX: Use the correct field names that are selected in the query
            $fnskuValue = $product->FNSKUviewer ?? null;
            $asinValue = $product->ASINviewer ?? null;
            
            if (empty($fnskuValue) && empty($asinValue)) {
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

            // 6. Check if grading is complete
            if (empty($product->fnsku_grading)) {
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
            if ($product->ProductModuleLoc === 'Validation' && 
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
            
            // First, let's check if the tables exist and what columns they have
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
                    
                    // Test the complex query with a known serial or just the first product
                    if ($sampleProduct) {
                        $testSerial = $sampleProduct->serialnumber ?? $serialNumber;
                        
                        $testQuery = DB::table($this->productTable . ' as prod')
                            ->leftJoin($this->fnskuTable . ' as fnsku', 'prod.FNSKUviewer', '=', 'fnsku.FNSKU')
                            ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                            ->select([
                                'prod.ProductID',
                                'prod.rtcounter',
                                'prod.serialnumber',
                                'prod.serialnumberb',
                                'prod.serialnumberc',
                                'prod.serialnumberd',
                                'prod.ProductModuleLoc',
                                'prod.printCount',
                                'prod.warehouselocation',
                                'prod.notes',
                                'prod.stickernote',
                                'prod.basketnumber',
                                'prod.priorityrank',
                                'prod.returnstatus',
                                'prod.FNSKUviewer',
                                'prod.validation_status',
                                'fnsku.grading as fnsku_grading',
                                'fnsku.storename as fnsku_storename',
                                'asin.ASIN as ASINviewer',
                                'asin.internal as AStitle',
                                'asin.asinStatus'
                            ])
                            ->where('prod.serialnumber', $testSerial)
                            ->first();
                            
                        $debug['test_query_result'] = $testQuery;
                        $debug['test_serial'] = $testSerial;
                    }
                    
                } catch (Exception $e) {
                    $debug['product_query_error'] = $e->getMessage();
                }
            }
            
            // Test connection to each table individually
            foreach (['product', 'fnsku', 'asin'] as $tableType) {
                $tableName = $this->{$tableType . 'Table'};
                try {
                    if (DB::getSchemaBuilder()->hasTable($tableName)) {
                        $count = DB::table($tableName)->count();
                        $debug[$tableType . '_table_test'] = [
                            'success' => true,
                            'count' => $count,
                            'message' => 'Table accessible'
                        ];
                    } else {
                        $debug[$tableType . '_table_test'] = [
                            'success' => false,
                            'message' => 'Table does not exist'
                        ];
                    }
                } catch (Exception $e) {
                    $debug[$tableType . '_table_test'] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
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

        /**
     * Get all available printers
     */
    public function getPrinters()
    {
        try {
            $printers = DB::table('tblprinters')
                ->select('printerid', 'printername')
                ->orderBy('printername')
                ->get();
            
            return response()->json([
                'success' => true,
                'printers' => $printers
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch printers: ' . $e->getMessage()
            ], 500);
        }
    }
}