<?php

namespace App\Http\Controllers\printer;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PrintLabelService;
use App\Services\ImageProcessingService; 
use App\Http\Controllers\BasetablesController;
use Exception;

class PrinterController extends BasetablesController
{
    protected $imageProcessingService;
    protected $printLabelService; 

    public function __construct()
    {
        parent::__construct();
        $this->imageProcessingService = new ImageProcessingService();
        $this->printLabelService = new PrintLabelService();       
    }

    /**
     * Extract base FNSKU from prefixed FNSKU (same as StockroomController)
     */
    private function extractBaseFnsku($fnsku)
    {
        if (empty($fnsku)) {
            return $fnsku;
        }

        // Check if it's a prefixed FNSKU (starts with C followed by digits)
        if (preg_match('/^C(\d+)(.+)$/', $fnsku, $matches)) {
            return $matches[2]; // Return the base FNSKU without prefix
        }

        return $fnsku; // Return as-is if not prefixed
    }

    /**
     * Check if a serial number meets print conditions
     * UPDATED to use base FNSKU for database lookups
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

            $searchTerm = trim($request->serial_number);
            
            // Search for the product using enhanced search logic
            $product = $this->searchProductForPrinting($searchTerm);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found with search term: ' . $searchTerm,
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
                        'FNSKUviewer' => $product->FNSKUviewer
                    ]
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error checking serial for printing:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'search_term' => $request->serial_number ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking item: ' . $e->getMessage(),
                'meets_print_conditions' => false
            ], 500);
        }
    }

    /**
     * Enhanced search function for printing - UPDATED to use base FNSKU for database lookups
     *
     * @param string $searchTerm
     * @return object|null
     */
    protected function searchProductForPrinting($searchTerm)
    {
        try {
            // UPDATED: Get products first, then match FNSKUs in PHP
            $productsQuery = DB::table($this->productTable . ' as prod')
                ->select(['prod.*'])
                ->where('prod.returnstatus', 'Not Returned')
                ->where('prod.ProductModuleLoc', '!=', 'Migrated');

            // Enhanced search logic
            // Check if search term looks like RT counter (RT + numbers)
            if (preg_match('/^RT(\d+)$/i', $searchTerm, $matches)) {
                $rtNumber = (int)$matches[1];
                $productsQuery->where('prod.rtcounter', $rtNumber);
                Log::info('Searching by RT counter:', ['rt_number' => $rtNumber]);
            }
            // Check if search term looks like AR counter (AR + numbers)  
            elseif (preg_match('/^AR(\d+)$/i', $searchTerm, $matches)) {
                $arNumber = (int)$matches[1];
                $productsQuery->where('prod.rtcounter', $arNumber);
                Log::info('Searching by AR counter:', ['ar_number' => $arNumber]);
            }
            // Check if it's just a number (could be PCN or RT counter without prefix)
            elseif (is_numeric($searchTerm)) {
                $number = (int)$searchTerm;
                $productsQuery->where(function($q) use ($number, $searchTerm) {
                    $q->where('prod.rtcounter', $number)
                      ->orWhere('prod.itemnumber', $searchTerm)
                      ->orWhere('prod.PCN', $searchTerm)
                      ->orWhere('prod.PRD', $searchTerm);
                });
                Log::info('Searching by numeric value:', ['number' => $number, 'search_term' => $searchTerm]);
            }
            // Otherwise search by serial numbers and other text fields
            else {
                $productsQuery->where(function($q) use ($searchTerm) {
                    $q->where('prod.serialnumber', $searchTerm)
                      ->orWhere('prod.serialnumberb', $searchTerm)
                      ->orWhere('prod.serialnumberc', $searchTerm)
                      ->orWhere('prod.serialnumberd', $searchTerm)
                      ->orWhere('prod.itemnumber', $searchTerm)
                      ->orWhere('prod.PCN', $searchTerm)
                      ->orWhere('prod.PRD', $searchTerm)
                      ->orWhere('prod.FNSKUviewer', $searchTerm);
                });
                Log::info('Searching by text fields:', ['search_term' => $searchTerm]);
            }

            $product = $productsQuery->orderBy('prod.ProductID', 'desc')->first();

            if (!$product) {
                Log::warning('No product found for search term:', ['search_term' => $searchTerm]);
                return null;
            }

            // UPDATED: Now get FNSKU and ASIN data using base FNSKU
            $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
            
            $fnskuRecord = null;
            if (!empty($baseFnsku)) {
                $fnskuRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();
            }

            $asinRecord = null;
            if ($fnskuRecord && !empty($fnskuRecord->ASIN)) {
                $asinRecord = DB::table($this->asinTable)
                    ->where('ASIN', $fnskuRecord->ASIN)
                    ->first();
            }

            // Add FNSKU and ASIN data to product
            if ($fnskuRecord) {
                $product->FNSKU = $fnskuRecord->FNSKU;
                $product->fnsku_grading = $fnskuRecord->grading;
                $product->fnsku_storename = $fnskuRecord->storename;
                $product->ASINviewer = $fnskuRecord->ASIN;
            }

            if ($asinRecord) {
                $product->AStitle = $asinRecord->internal;
                $product->asinStatus = $asinRecord->asinStatus;
            }

            Log::info('Product found for printing:', [
                'ProductID' => $product->ProductID,
                'rtcounter' => $product->rtcounter,
                'FNSKU' => $product->FNSKUviewer,
                'base_fnsku' => $baseFnsku,
                'ASIN' => $product->ASINviewer ?? 'null'
            ]);

            return $product;

        } catch (Exception $e) {
            Log::error('Error in searchProductForPrinting:', [
                'error' => $e->getMessage(),
                'search_term' => $searchTerm
            ]);
            
            return null;
        }
    }

    /**
     * Search for a product to reprint by serial number, PCN, or RT counter
     * UPDATED to use base FNSKU for database lookups
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchForReprint(Request $request)
    {
        try {
            $request->validate([
                'search_term' => 'required|string'
            ]);

            $searchTerm = trim($request->search_term);
            
            Log::info('Searching for reprint:', ['search_term' => $searchTerm]);
            
            // Search by different criteria
            $product = $this->searchProductByTerm($searchTerm);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found with search term: ' . $searchTerm
                ]);
            }

            // Return product data for reprint
            return response()->json([
                'success' => true,
                'message' => 'Product found successfully',
                'product_data' => [
                    'ProductID' => $product->ProductID,
                    'rtcounter' => $product->rtcounter,
                    'FNSKUviewer' => $product->FNSKUviewer,
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
                    'itemnumber' => $product->itemnumber ?? null,
                    'PRD' => $product->PRD ?? null,
                    'PCN' => $product->PCN ?? null,
                    'itemstatus' => $product->itemstatus ?? null,
                    'subvariant' => $product->subvariant ?? null,
                    'mID' => $product->mID ?? null,
                    'vectorimage' => $product->vectorimage ?? null,
                    'instructioncard' => $product->instructioncard ?? null,
                    'instructioncard2' => $product->instructioncard2 ?? null,
                    'instructioncard3' => $product->instructioncard3 ?? null,
                    'TRANSPARENCY_QR_STATUS' => $product->TRANSPARENCY_QR_STATUS ?? null
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error searching for reprint:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'search_term' => $request->search_term ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error searching for product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reprint a single label type
     * The service handles the FNSKU prefix logic
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reprintSingleLabel(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|integer',
                'label_type' => 'required|string',
                'printer_id' => 'required|integer',
                'search_term' => 'required|string'
            ]);

            $productId = $request->product_id;
            $labelType = $request->label_type;
            $printerId = $request->printer_id;
            $searchTerm = $request->search_term;
            
            // Get selected printer info
            $selectedPrinter = DB::table('tblprinters')
                ->where('printerid', $printerId)
                ->first();
                
            if (!$selectedPrinter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected printer not found'
                ], 404);
            }
            
            // Get username safely
            $user = Auth::user();
            $username = $user ? ($user->username ?? $user->name ?? 'Unknown') : 'System';

            // UPDATED: Get the product with base FNSKU lookups (service handles this now)
            $product = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Use the PrintLabelService to print the specific label type
            // The service now handles the FNSKU prefix system internally
            $printResult = $this->printLabelService->reprintSingleLabel(
                $productId, 
                $labelType, 
                $username, 
                $selectedPrinter
            );

            // Check if the print service returned a successful result
            if ($printResult['status'] === 'success') {
                // Log the reprint activity
                if (isset($this->itemProcessHistoryTable) && 
                    DB::getSchemaBuilder()->hasTable($this->itemProcessHistoryTable)) {
                    
                    DB::table($this->itemProcessHistoryTable)->insert([
                        'rtcounter' => $product->rtcounter,
                        'employeeName' => $username,
                        'editDate' => now()->format('Y-m-d H:i:s'),
                        'Module' => 'Label Reprinting',
                        'Action' => 'Single label reprinted (' . $labelType . ') for ' . ($product->FNSKUviewer ?? 'unknown FNSKU') . ' on ' . $selectedPrinter->printername . ' - Search: ' . $searchTerm
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Label reprinted successfully to ' . $selectedPrinter->printername,
                    'label_type' => $labelType,
                    'search_term' => $searchTerm,
                    'printer_name' => $selectedPrinter->printername,
                    'product_title' => 'Product Title', // Service will populate this
                    'asin' => 'ASIN', // Service will populate this
                    'fnsku' => $product->FNSKUviewer,
                    'product_data' => [
                        'ProductID' => $product->ProductID,
                        'rtcounter' => $product->rtcounter,
                        'ProductModuleLoc' => $product->ProductModuleLoc
                    ]
                ], 200);
            } else {
                // Print service failed
                return response()->json([
                    'success' => false,
                    'message' => 'Reprint failed: ' . ($printResult['message'] ?? 'Unknown error')
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Error reprinting single label:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error reprinting label: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for a product by various criteria (enhanced version for reprint)
     * UPDATED to use base FNSKU for database lookups
     *
     * @param string $searchTerm
     * @return object|null
     */
    protected function searchProductByTerm($searchTerm)
    {
        try {
            // UPDATED: Get products first, then match FNSKUs in PHP
            $productsQuery = DB::table($this->productTable . ' as prod')
                ->select(['prod.*'])
                ->where('prod.returnstatus', 'Not Returned')
                ->where('prod.ProductModuleLoc', '!=', 'Migrated');

            // Check if search term looks like RT counter (RT + numbers)
            if (preg_match('/^RT(\d+)$/i', $searchTerm, $matches)) {
                $rtNumber = (int)$matches[1];
                $productsQuery->where('prod.rtcounter', $rtNumber);
                Log::info('Searching by RT counter:', ['rt_number' => $rtNumber]);
            }
            // Check if search term looks like AR counter (AR + numbers)  
            elseif (preg_match('/^AR(\d+)$/i', $searchTerm, $matches)) {
                $arNumber = (int)$matches[1];
                $productsQuery->where('prod.rtcounter', $arNumber);
                Log::info('Searching by AR counter:', ['ar_number' => $arNumber]);
            }
            // Check if it's just a number (could be PCN or RT counter without prefix)
            elseif (is_numeric($searchTerm)) {
                $number = (int)$searchTerm;
                $productsQuery->where(function($q) use ($number, $searchTerm) {
                    $q->where('prod.rtcounter', $number)
                      ->orWhere('prod.itemnumber', $searchTerm)
                      ->orWhere('prod.PCN', $searchTerm)
                      ->orWhere('prod.PRD', $searchTerm);
                });
                Log::info('Searching by numeric value:', ['number' => $number, 'search_term' => $searchTerm]);
            }
            // Otherwise search by serial numbers and other text fields
            else {
                $productsQuery->where(function($q) use ($searchTerm) {
                    $q->where('prod.serialnumber', $searchTerm)
                      ->orWhere('prod.serialnumberb', $searchTerm)
                      ->orWhere('prod.serialnumberc', $searchTerm)
                      ->orWhere('prod.serialnumberd', $searchTerm)
                      ->orWhere('prod.itemnumber', $searchTerm)
                      ->orWhere('prod.PCN', $searchTerm)
                      ->orWhere('prod.PRD', $searchTerm)
                      ->orWhere('prod.FNSKUviewer', $searchTerm);
                });
                Log::info('Searching by text fields:', ['search_term' => $searchTerm]);
            }

            $product = $productsQuery->orderBy('prod.ProductID', 'desc')->first();

            if (!$product) {
                Log::warning('No product found for search term:', ['search_term' => $searchTerm]);
                return null;
            }

            // UPDATED: Now get FNSKU and ASIN data using base FNSKU
            $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
            
            $fnskuRecord = null;
            if (!empty($baseFnsku)) {
                $fnskuRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();
            }

            $asinRecord = null;
            if ($fnskuRecord && !empty($fnskuRecord->ASIN)) {
                $asinRecord = DB::table($this->asinTable)
                    ->where('ASIN', $fnskuRecord->ASIN)
                    ->first();
            }

            // Add FNSKU and ASIN data to product
            if ($fnskuRecord) {
                $product->FNSKU = $fnskuRecord->FNSKU;
                $product->fnsku_grading = $fnskuRecord->grading;
                $product->fnsku_storename = $fnskuRecord->storename;
                $product->ASINviewer = $fnskuRecord->ASIN;
            }

            if ($asinRecord) {
                $product->AStitle = $asinRecord->internal;
                $product->asinStatus = $asinRecord->asinStatus;
                $product->vectorimage = $asinRecord->vectorimage;
                $product->instructioncard = $asinRecord->instructioncard;
                $product->instructioncard2 = $asinRecord->instructioncard2;
                $product->instructioncard3 = $asinRecord->instructioncard3;
                $product->TRANSPARENCY_QR_STATUS = $asinRecord->TRANSPARENCY_QR_STATUS;
            }

            Log::info('Product found:', [
                'ProductID' => $product->ProductID,
                'rtcounter' => $product->rtcounter,
                'FNSKU' => $product->FNSKUviewer,
                'base_fnsku' => $baseFnsku,
                'ASIN' => $product->ASINviewer ?? 'null'
            ]);

            return $product;

        } catch (Exception $e) {
            Log::error('Error in searchProductByTerm:', [
                'error' => $e->getMessage(),
                'search_term' => $searchTerm
            ]);
            
            return null;
        }
    }

    /**
     * Print label for a product
     * The service handles the FNSKU prefix logic
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

            // UPDATED: Double-check the product still exists and meets conditions using base FNSKU
            $product = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->where('returnstatus', 'Not Returned')
                ->where('ProductModuleLoc', '!=', 'Migrated')
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found or status changed'
                ], 404);
            }

            // UPDATED: Get FNSKU and ASIN data using base FNSKU for condition checking
            $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
            
            $fnskuRecord = null;
            if (!empty($baseFnsku)) {
                $fnskuRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();
            }

            $asinRecord = null;
            if ($fnskuRecord && !empty($fnskuRecord->ASIN)) {
                $asinRecord = DB::table($this->asinTable)
                    ->where('ASIN', $fnskuRecord->ASIN)
                    ->first();
            }

            // Add FNSKU and ASIN data to product for condition checking
            if ($fnskuRecord) {
                $product->FNSKU = $fnskuRecord->FNSKU;
                $product->fnsku_grading = $fnskuRecord->grading;
                $product->fnsku_storename = $fnskuRecord->storename;
                $product->ASINviewer = $fnskuRecord->ASIN;
            }

            if ($asinRecord) {
                $product->AStitle = $asinRecord->internal;
                $product->asinStatus = $asinRecord->asinStatus;
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
            // The service now handles all the FNSKU prefix logic internally
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
     * UPDATED to work with enriched product data (now includes FNSKU/ASIN info)
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
                    
                    // UPDATED: Test the new separated query approach
                    if ($sampleProduct) {
                        $testSerial = $sampleProduct->serialnumber ?? $serialNumber;
                        
                        // Test product lookup
                        $testProduct = DB::table($this->productTable)
                            ->where('serialnumber', $testSerial)
                            ->first();
                            
                        $debug['test_product_result'] = $testProduct;
                        
                        // Test FNSKU lookup if product found
                        if ($testProduct && !empty($testProduct->FNSKUviewer)) {
                            $baseFnsku = $this->extractBaseFnsku($testProduct->FNSKUviewer);
                            
                            $testFnskuRecord = DB::table($this->fnskuTable)
                                ->where('FNSKU', $baseFnsku)
                                ->first();
                                
                            $debug['test_fnsku_result'] = [
                                'original_fnsku' => $testProduct->FNSKUviewer,
                                'base_fnsku' => $baseFnsku,
                                'fnsku_record' => $testFnskuRecord
                            ];
                            
                            // Test ASIN lookup if FNSKU found
                            if ($testFnskuRecord && !empty($testFnskuRecord->ASIN)) {
                                $testAsinRecord = DB::table($this->asinTable)
                                    ->where('ASIN', $testFnskuRecord->ASIN)
                                    ->first();
                                    
                                $debug['test_asin_result'] = $testAsinRecord;
                            }
                        }
                        
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
                ->select('printerid', 'printername', 'printerip')
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