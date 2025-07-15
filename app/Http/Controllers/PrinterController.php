<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PrintLabelService;
use App\Http\Controllers\BasetablesController;

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
                    'prod.FNSKUviewer',
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
                    'prod.gradingviewer',
                    'prod.StoreName',
                    'prod.validation_status',
                    'fnsku.ASIN as ASINviewer',
                    'fnsku.grading as fnsku_grading',
                    'fnsku.storename as fnsku_storename',
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
                        'FNSKUviewer' => $product->FNSKUviewer,
                        'ASINviewer' => $product->ASINviewer,
                        'AStitle' => $product->AStitle, // This now comes from asin.internal
                        'StoreName' => $product->StoreName,
                        'gradingviewer' => $product->gradingviewer,
                        'fnsku_grading' => $product->fnsku_grading, // Additional grading from FNSKU table
                        'fnsku_storename' => $product->fnsku_storename, // Store name from FNSKU table
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
                        'asinStatus' => $product->asinStatus
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
                        'FNSKUviewer' => $product->FNSKUviewer
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error checking serial for printing:', [
                'error' => $e->getMessage(),
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
            $request->validate([
                'serial_number' => 'required|string',
                'print_data' => 'required|array'
            ]);

            $serialNumber = trim($request->serial_number);
            $printData = $request->print_data;
            $username = Auth::user()->username ?? 'Unknown';

            // Get the ProductID from the print data
            $productId = $printData['product_data']['ProductID'] ?? null;
            
            if (!$productId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product ID not found in print data'
                ], 400);
            }

            // Double-check the product still exists and meets conditions with proper joins
            $product = DB::table($this->productTable . ' as prod')
                ->leftJoin($this->fnskuTable . ' as fnsku', 'prod.FNSKUviewer', '=', 'fnsku.FNSKU')
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->select([
                    'prod.ProductID',
                    'prod.rtcounter',
                    'prod.FNSKUviewer',
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
                    'prod.gradingviewer',
                    'prod.StoreName',
                    'prod.validation_status',
                    'fnsku.ASIN as ASINviewer',
                    'fnsku.grading as fnsku_grading',
                    'fnsku.storename as fnsku_storename',
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

            // Use the PrintLabelService to print the label
            $printResult = $this->printLabelService->printLabel($productId, $username);

            if ($printResult['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => 'Label printed successfully',
                    'serial_number' => $serialNumber,
                    'print_count' => ($product->printCount ?? 0) + 1,
                    'product_title' => $product->AStitle ?? 'Unknown Title',
                    'asin' => $product->ASINviewer,
                    'fnsku' => $product->FNSKUviewer
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $printResult['message']
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error printing label:', [
                'error' => $e->getMessage(),
                'serial_number' => $request->serial_number ?? 'unknown'
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
        // Define your specific printing conditions based on your business logic
        
        // 1. Check if product is in the right location/module for printing
        $validLocations = [
            'Packing', 
            'Stockroom', 
            'Validation', 
            'Production', 
            'Production Area',
            'Testing',
            'Cleaning',
            'Labeling',
            'FNSKU'
        ];
        
        if (!in_array($product->ProductModuleLoc, $validLocations)) {
            return [
                'meets_conditions' => false,
                'message' => 'Item is not in a valid location for printing. Current location: ' . $product->ProductModuleLoc . '. Valid locations: ' . implode(', ', $validLocations),
                'current_status' => $product->ProductModuleLoc
            ];
        }

        // 2. Check if item is returned (should not print returned items)
        if (isset($product->returnstatus) && $product->returnstatus === 'Returned') {
            return [
                'meets_conditions' => false,
                'message' => 'Cannot print label for returned items',
                'current_status' => 'Returned'
            ];
        }

        // 3. Check if item is migrated (should not print migrated items)
        if ($product->ProductModuleLoc === 'Migrated') {
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

        // 5. Check if serial number exists (required for printing)
        if (empty($product->serialnumber) && empty($product->serialnumberb) && 
            empty($product->serialnumberc) && empty($product->serialnumberd)) {
            return [
                'meets_conditions' => false,
                'message' => 'Item missing serial number information required for printing',
                'current_status' => 'Missing Serial Number'
            ];
        }

        // 6. Check if grading is complete (required for condition on label)
        if (empty($product->gradingviewer)) {
            return [
                'meets_conditions' => false,
                'message' => 'Item grading is not complete - required for label printing',
                'current_status' => 'Grading Incomplete'
            ];
        }

        // 7. Check print count (prevent excessive reprinting)
        $maxPrintCount = config('app.max_print_count', 10); // Configurable max print count
        if (($product->printCount ?? 0) >= $maxPrintCount) {
            return [
                'meets_conditions' => false,
                'message' => 'Item has reached maximum print count (' . $maxPrintCount . '). Current count: ' . ($product->printCount ?? 0),
                'current_status' => 'Max Print Count Reached'
            ];
        }

        // 8. Check if RT counter exists (needed for tracking)
        if (empty($product->rtcounter)) {
            return [
                'meets_conditions' => false,
                'message' => 'Item missing RT counter - required for tracking',
                'current_status' => 'Missing RT Counter'
            ];
        }

        // 9. Optional: Check if item has a valid store name
        if (empty($product->StoreName)) {
            // This might be a warning rather than blocking condition
            Log::warning('Item has no store name but allowing print', [
                'ProductID' => $product->ProductID,
                'rtcounter' => $product->rtcounter
            ]);
        }

        // 10. Optional: Check validation status for certain modules
        if ($product->ProductModuleLoc === 'Validation' && 
            isset($product->validation_status) && 
            $product->validation_status !== 'validated') {
            return [
                'meets_conditions' => false,
                'message' => 'Item in Validation module must be validated before printing',
                'current_status' => 'Not Validated'
            ];
        }

        // 11. Additional business logic checks
        // Add any other specific conditions based on your workflow
        
        // For example, check if item is in a specific status
        if (isset($product->status) && in_array($product->status, ['blocked', 'hold', 'quarantine'])) {
            return [
                'meets_conditions' => false,
                'message' => 'Item is in ' . $product->status . ' status and cannot be printed',
                'current_status' => $product->status
            ];
        }

        // All conditions passed
        return [
            'meets_conditions' => true,
            'message' => 'Item ready for printing',
            'current_status' => 'Ready for Print'
        ];
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
                'status' => 'online', // You can add actual printer status checking here
                'message' => 'Printer service is available'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting printer status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get print history for a specific serial number
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPrintHistory(Request $request)
    {
        try {
            $request->validate([
                'serial_number' => 'required|string'
            ]);

            $serialNumber = trim($request->serial_number);
            
            // Find the product
            $product = DB::table($this->productTable)
                ->where(function ($query) use ($serialNumber) {
                    $query->where('serialnumber', $serialNumber)
                          ->orWhere('serialnumberb', $serialNumber)
                          ->orWhere('serialnumberc', $serialNumber)
                          ->orWhere('serialnumberd', $serialNumber);
                })
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Serial number not found'
                ], 404);
            }

            // Get print history from item process history
            $printHistory = DB::table($this->itemProcessHistoryTable)
                ->where('rtcounter', $product->rtcounter)
                ->where('Module', 'Label Printing')
                ->orderBy('editDate', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'print_history' => $printHistory,
                'current_print_count' => $product->printCount ?? 0
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting print history:', [
                'error' => $e->getMessage(),
                'serial_number' => $request->serial_number ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error getting print history: ' . $e->getMessage()
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

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing printer connection: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Get printing statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats()
    {
        try {
            $today = now()->toDateString();
            $thisWeek = now()->startOfWeek()->toDateString();
            $thisMonth = now()->startOfMonth()->toDateString();
            
            // Get daily stats
            $dailyStats = DB::table($this->itemProcessHistoryTable)
                ->where('Module', 'Label Printing')
                ->whereDate('editDate', $today)
                ->selectRaw('COUNT(*) as total_prints, COUNT(DISTINCT employeeName) as unique_users')
                ->first();
            
            // Get weekly stats
            $weeklyStats = DB::table($this->itemProcessHistoryTable)
                ->where('Module', 'Label Printing')
                ->whereDate('editDate', '>=', $thisWeek)
                ->selectRaw('COUNT(*) as total_prints, COUNT(DISTINCT employeeName) as unique_users')
                ->first();
            
            // Get monthly stats
            $monthlyStats = DB::table($this->itemProcessHistoryTable)
                ->where('Module', 'Label Printing')
                ->whereDate('editDate', '>=', $thisMonth)
                ->selectRaw('COUNT(*) as total_prints, COUNT(DISTINCT employeeName) as unique_users')
                ->first();
            
            // Get top users today
            $topUsers = DB::table($this->itemProcessHistoryTable)
                ->where('Module', 'Label Printing')
                ->whereDate('editDate', $today)
                ->select('employeeName', DB::raw('COUNT(*) as print_count'))
                ->groupBy('employeeName')
                ->orderBy('print_count', 'desc')
                ->limit(5)
                ->get();
            
            return response()->json([
                'success' => true,
                'stats' => [
                    'today' => [
                        'total_prints' => $dailyStats->total_prints ?? 0,
                        'unique_users' => $dailyStats->unique_users ?? 0
                    ],
                    'this_week' => [
                        'total_prints' => $weeklyStats->total_prints ?? 0,
                        'unique_users' => $weeklyStats->unique_users ?? 0
                    ],
                    'this_month' => [
                        'total_prints' => $monthlyStats->total_prints ?? 0,
                        'unique_users' => $monthlyStats->unique_users ?? 0
                    ],
                    'top_users_today' => $topUsers
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting printer statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}