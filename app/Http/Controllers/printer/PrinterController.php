<?php

namespace App\Http\Controllers\printer;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PrintLabelService;
use App\Services\ImageProcessingService; 
use App\Http\Controllers\BasetablesController;
use Illuminate\Support\Facades\Artisan;
use DateTime;
use DateTimeZone;
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

            // Check if it's a prefixed FNSKU (starts with letter C-Z followed by digit(s))
            if (preg_match('/^([C-Z])(\d+)(.+)$/', $fnsku, $matches)) {
                return $matches[3]; // Return the base FNSKU without prefix
            }

            return $fnsku; // Return as-is if not prefixed
        }

    /**
     * NEW: Insert unvalidated item record
     * 
     * @param int $productId
     * @return bool
     */
    protected function insertUnvalidatedItem($productId)
    {
        try {
            // Check if this ProductID already exists in unvalidated items table with NotProcessed status
            $existing = DB::table($this->unvalidatedItemTable)
                ->where('ProductID', $productId)
                ->where('status', 'NotProcessed')
                ->first();

            if ($existing) {
                Log::info('Unvalidated item already exists in tracking table:', [
                    'ProductID' => $productId,
                    'existing_record_id' => $existing->UnvalidatedID ?? 'unknown'
                ]);
                return true; // Already tracked, no need to insert again
            }

            // Insert new unvalidated item record
            DB::table($this->unvalidatedItemTable)->insert([
                'ProductID' => $productId,
                'status' => 'NotProcessed',
                'scanned_date' => now()
            ]);

            Log::info('Unvalidated item inserted into tracking table:', [
                'ProductID' => $productId
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Error inserting unvalidated item:', [
                'error' => $e->getMessage(),
                'ProductID' => $productId,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't fail the main operation if logging fails
            return false;
        }
    }

    /**
     * Check if a serial number meets print conditions
     * UPDATED with better error handling and unvalidated item tracking
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
            
            Log::info('CheckSerial called with search term:', ['search_term' => $searchTerm]);
            
            // Search for the product using enhanced search logic
            try {
                $product = $this->searchProductForPrinting($searchTerm);
            } catch (Exception $searchException) {
                Log::error('Error in searchProductForPrinting:', [
                    'error' => $searchException->getMessage(),
                    'trace' => $searchException->getTraceAsString(),
                    'search_term' => $searchTerm
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error searching for product: ' . $searchException->getMessage(),
                    'meets_print_conditions' => false
                ], 500);
            }

            if (!$product) {
                Log::warning('Product not found:', ['search_term' => $searchTerm]);
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found with search term: ' . $searchTerm,
                    'meets_print_conditions' => false
                ]);
            }

            Log::info('Product found:', [
                'ProductID' => $product->ProductID ?? 'null',
                'validation_status' => $product->validation_status ?? 'null'
            ]);

            // NEW: Check validation status FIRST before other conditions
            if (!isset($product->validation_status) || 
                strcasecmp($product->validation_status, 'validated') !== 0) {
                
                // Get the validation status or default to 'Not Validated'
                $validationStatus = $product->validation_status ?? 'Not Validated';
                
                // INSERT into tblUnvalidatedItem
                $this->insertUnvalidatedItem($product->ProductID);
                
                Log::info('Validation check failed:', [
                    'ProductID' => $product->ProductID,
                    'validation_status' => $validationStatus
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Item is not validated yet (Status: ' . $validationStatus . ')',
                    'meets_print_conditions' => false,
                    'requires_confirmation' => true,
                    'validation_status' => $validationStatus,
                    'product_data' => [
                        'ProductID' => $product->ProductID ?? null,
                        'rtcounter' => $product->rtcounter ?? null,
                        'FNSKUviewer' => $product->FNSKUviewer ?? null,
                        'ASINviewer' => $product->ASINviewer ?? null,
                        'AStitle' => $product->AStitle ?? 'Unknown Title',
                        'serialnumber' => $product->serialnumber ?? null,
                        'validation_status' => $validationStatus
                    ]
                ]);
            }

            // Check if product meets print conditions
            try {
                $conditions = $this->checkPrintConditions($product);
            } catch (Exception $conditionException) {
                Log::error('Error in checkPrintConditions:', [
                    'error' => $conditionException->getMessage(),
                    'ProductID' => $product->ProductID ?? 'null'
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error checking print conditions: ' . $conditionException->getMessage(),
                    'meets_print_conditions' => false
                ], 500);
            }
            
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
                        'fnsku_grading' => $product->fnsku_grading ?? null,
                        'fnsku_storename' => $product->fnsku_storename ?? null,
                        'serialnumber' => $product->serialnumber,
                        'serialnumberb' => $product->serialnumberb ?? null,
                        'serialnumberc' => $product->serialnumberc ?? null,
                        'serialnumberd' => $product->serialnumberd ?? null,
                        'ProductModuleLoc' => $product->ProductModuleLoc,
                        'printCount' => $product->printCount ?? 0,
                        'warehouselocation' => $product->warehouselocation ?? null,
                        'notes' => $product->notes ?? null,
                        'stickernote' => $product->stickernote ?? null,
                        'basketnumber' => $product->basketnumber ?? null,
                        'priorityrank' => $product->priorityrank ?? null,
                        'validation_status' => $product->validation_status,
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
                        'rtcounter' => $product->rtcounter ?? null,
                        'ProductModuleLoc' => $product->ProductModuleLoc,
                        'current_status' => $conditions['current_status'] ?? 'Unknown',
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
                'search_term' => $request->serial_number ?? 'unknown',
                'line' => $e->getLine(),
                'file' => $e->getFile()
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
     * UPDATED to use base FNSKU for database lookups and track unvalidated items
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

            // NEW: Check validation status before allowing reprint
            if (!isset($product->validation_status) || 
                strcasecmp($product->validation_status, 'validated') !== 0) {
                
                $validationStatus = $product->validation_status ?? 'Not Validated';
                
                // INSERT into tblUnvalidatedItem
                $this->insertUnvalidatedItem($product->ProductID);
                
                Log::info('Reprint search - validation check failed:', [
                    'ProductID' => $product->ProductID,
                    'validation_status' => $validationStatus,
                    'search_term' => $searchTerm
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Item is not validated yet (Status: ' . $validationStatus . '). Cannot reprint unvalidated items.',
                    'requires_validation' => true,
                    'validation_status' => $validationStatus,
                    'product_preview' => [
                        'ProductID' => $product->ProductID ?? null,
                        'rtcounter' => $product->rtcounter ?? null,
                        'FNSKUviewer' => $product->FNSKUviewer ?? null,
                        'AStitle' => $product->AStitle ?? 'Unknown Title',
                        'validation_status' => $validationStatus
                    ]
                ]);
            }

            // Return product data for reprint (only if validated)
            return response()->json([
                'success' => true,
                'message' => 'Product found successfully',
                'product_data' => [
                    'ProductID' => $product->ProductID,
                    'rtcounter' => $product->rtcounter,
                    'FNSKUviewer' => $product->FNSKUviewer,
                    'ASINviewer' => $product->ASINviewer ?? null,
                    'AStitle' => $product->AStitle ?? null,
                    'fnsku_grading' => $product->fnsku_grading ?? null,
                    'fnsku_storename' => $product->fnsku_storename ?? null,
                    'serialnumber' => $product->serialnumber,
                    'serialnumberb' => $product->serialnumberb ?? null,
                    'serialnumberc' => $product->serialnumberc ?? null,
                    'serialnumberd' => $product->serialnumberd ?? null,
                    'ProductModuleLoc' => $product->ProductModuleLoc,
                    'printCount' => $product->printCount ?? 0,
                    'warehouselocation' => $product->warehouselocation ?? null,
                    'notes' => $product->notes ?? null,
                    'stickernote' => $product->stickernote ?? null,
                    'basketnumber' => $product->basketnumber ?? null,
                    'priorityrank' => $product->priorityrank ?? null,
                    'validation_status' => $product->validation_status,
                    'asinStatus' => $product->asinStatus ?? null,
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
     * UPDATED: Reprint a single label type with STRICT ENFORCEMENT
     * Now supports strict printer type enforcement with clear error messages
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
            
            // Get selected printer info with marriage details
            $selectedPrinter = DB::table('tblprinters')
                ->where('printerid', $printerId)
                ->first();
                
            if (!$selectedPrinter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected printer not found'
                ], 404);
            }

            // Check if printer is active
            if ($selectedPrinter->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected printer is not active. Current status: ' . $selectedPrinter->status
                ], 400);
            }
            
            // Get username safely
            $user = Auth::user();
            $username = $user ? ($user->username ?? $user->name ?? 'Unknown') : 'System';

            // Get the product with base FNSKU lookups
            $product = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // STRICT COMPATIBILITY CHECK
            $compatibilityCheck = $this->checkPrinterLabelCompatibility($selectedPrinter, $labelType);
            
            if (!$compatibilityCheck['compatible']) {
                return response()->json([
                    'success' => false,
                    'message' => $compatibilityCheck['message'],
                    'suggested_action' => $compatibilityCheck['suggested_action'] ?? null,
                    'available_printers' => $compatibilityCheck['available_printers'] ?? null
                ], 400);
            }

            // Determine the target printer to use (could be different due to smart routing)
            $targetPrinter = $compatibilityCheck['target_printer'];

            // Use the PrintLabelService to print the specific label type
            $printResult = $this->printLabelService->reprintSingleLabel(
                $productId, 
                $labelType, 
                $username, 
                $targetPrinter
            );

            // Check if the print service returned a successful result
            if ($printResult['status'] === 'success') {
                // Log the reprint activity
                if (isset($this->itemProcessHistoryTable) && 
                    DB::getSchemaBuilder()->hasTable($this->itemProcessHistoryTable)) {
                    
                    $actionDescription = $this->getReprintActionDescription($selectedPrinter, $targetPrinter, $labelType);
                    
                    DB::table($this->itemProcessHistoryTable)->insert([
                        'rtcounter' => $product->rtcounter,
                        'employeeName' => $username,
                        'editDate' => now()->format('Y-m-d H:i:s'),
                        'Module' => 'Label Reprinting',
                        'Action' => $actionDescription . ' for ' . ($product->FNSKUviewer ?? 'unknown FNSKU') . ' - Search: ' . $searchTerm
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => $printResult['message'] ?? 'Label reprinted successfully',
                    'label_type' => $labelType,
                    'search_term' => $searchTerm,
                    'printer_info' => [
                        'selected_printer' => $selectedPrinter->printername,
                        'target_printer' => $targetPrinter->printername,
                        'target_printer_ip' => $targetPrinter->printerip,
                        'was_routed' => $selectedPrinter->printerid !== $targetPrinter->printerid,
                        'routing_reason' => $compatibilityCheck['routing_reason'] ?? null
                    ],
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
     * NEW: Check printer and label compatibility with STRICT ENFORCEMENT
     * This method handles the logic for married printers and single printer compatibility
     *
     * @param object $selectedPrinter
     * @param string $labelType
     * @return array
     */
    protected function checkPrinterLabelCompatibility($selectedPrinter, $labelType)
    {
        try {
            // Define label type categories - CORRECTED: vector_image is small label
            $instructionCardLabels = [
                'instruction_cards'  // Only instruction cards go to instruction card printer
            ];

            $smallLabelTypes = [
                'serial_labels',
                'fnsku_label', 
                'title_label',
                'item_number_label',
                'timestamp_label',
                'sticker_note_label',
                'warehouse_location_label',
                'rtcounter_label',
                'qr_manual',
                'qr_serial',
                'transparency_qr',
                'print_count',
                'vector_image'  // Vector image goes to small label printer
            ];

            $isInstructionCardLabel = in_array($labelType, $instructionCardLabels);
            $isSmallLabel = in_array($labelType, $smallLabelTypes);

            // If printer is married, use smart routing
            if (!empty($selectedPrinter->married_to_printer_id)) {
                return $this->handleMarriedPrinterRouting($selectedPrinter, $labelType, $isInstructionCardLabel, $isSmallLabel);
            }
            
            // For single printers, check strict compatibility
            return $this->handleSinglePrinterCompatibility($selectedPrinter, $labelType, $isInstructionCardLabel, $isSmallLabel);

        } catch (Exception $e) {
            Log::error('Error checking printer label compatibility:', [
                'error' => $e->getMessage(),
                'printer_id' => $selectedPrinter->printerid ?? 'unknown',
                'label_type' => $labelType
            ]);

            return [
                'compatible' => false,
                'message' => 'Error checking printer compatibility: ' . $e->getMessage(),
                'target_printer' => $selectedPrinter
            ];
        }
    }

    /**
     * NEW: Handle married printer routing logic - STRICT ENFORCEMENT FOR REPRINT
     * For reprint, even married printers must have the exact type needed
     *
     * @param object $selectedPrinter
     * @param string $labelType
     * @param bool $isInstructionCardLabel
     * @param bool $isSmallLabel
     * @return array
     */
    protected function handleMarriedPrinterRouting($selectedPrinter, $labelType, $isInstructionCardLabel, $isSmallLabel)
    {
        try {
            // Get the married printer
            $marriedPrinter = DB::table('tblprinters')
                ->where('printerid', $selectedPrinter->married_to_printer_id)
                ->where('status', 'active')
                ->first();

            if (!$marriedPrinter) {
                return [
                    'compatible' => false,
                    'message' => 'Married printer is not available or inactive. Please select a different printer.',
                    'suggested_action' => 'Select a single printer of the correct type',
                    'target_printer' => $selectedPrinter
                ];
            }

            // STRICT ENFORCEMENT: Check if either printer in the marriage can handle the label type
            if ($isInstructionCardLabel) {
                // Need instruction card printer
                if ($selectedPrinter->printer_type === 'instruction_card') {
                    return [
                        'compatible' => true,
                        'message' => 'Using selected instruction card printer from married pair',
                        'target_printer' => $selectedPrinter,
                        'routing_reason' => 'Selected printer is correct type for instruction cards'
                    ];
                } else if ($marriedPrinter->printer_type === 'instruction_card') {
                    return [
                        'compatible' => true,
                        'message' => 'Smart routing: Using married instruction card printer',
                        'target_printer' => $marriedPrinter,
                        'routing_reason' => 'Routed to married instruction card printer'
                    ];
                } else {
                    // Neither printer can handle instruction cards
                    $instructionCardPrinters = DB::table('tblprinters')
                        ->where('printer_type', 'instruction_card')
                        ->where('status', 'active')
                        ->select('printerid', 'printername', 'printer_type')
                        ->get();

                    return [
                        'compatible' => false,
                        'message' => 'Neither printer in this married pair can print instruction cards. Please select an instruction card printer.',
                        'suggested_action' => 'Select an instruction card printer from the list below:',
                        'available_printers' => $instructionCardPrinters->toArray(),
                        'target_printer' => $selectedPrinter
                    ];
                }
            } else if ($isSmallLabel) {
                // Need small label printer
                if ($selectedPrinter->printer_type === 'small_label') {
                    return [
                        'compatible' => true,
                        'message' => 'Using selected small label printer from married pair',
                        'target_printer' => $selectedPrinter,
                        'routing_reason' => 'Selected printer is correct type for small labels'
                    ];
                } else if ($marriedPrinter->printer_type === 'small_label') {
                    return [
                        'compatible' => true,
                        'message' => 'Smart routing: Using married small label printer',
                        'target_printer' => $marriedPrinter,
                        'routing_reason' => 'Routed to married small label printer'
                    ];
                } else {
                    // Neither printer can handle small labels
                    $smallLabelPrinters = DB::table('tblprinters')
                        ->where('printer_type', 'small_label')
                        ->where('status', 'active')
                        ->select('printerid', 'printername', 'printer_type')
                        ->get();

                    return [
                        'compatible' => false,
                        'message' => 'Neither printer in this married pair can print small labels. Please select a small label printer.',
                        'suggested_action' => 'Select a small label printer from the list below:',
                        'available_printers' => $smallLabelPrinters->toArray(),
                        'target_printer' => $selectedPrinter
                    ];
                }
            } else {
                // Unknown label type - use selected printer
                return [
                    'compatible' => true,
                    'message' => 'Using selected printer for unknown label type',
                    'target_printer' => $selectedPrinter,
                    'routing_reason' => 'Unknown label type, no routing applied'
                ];
            }

        } catch (Exception $e) {
            Log::error('Error in married printer routing:', [
                'error' => $e->getMessage(),
                'selected_printer_id' => $selectedPrinter->printerid
            ]);

            return [
                'compatible' => false,
                'message' => 'Error checking married printer compatibility: ' . $e->getMessage(),
                'target_printer' => $selectedPrinter
            ];
        }
    }

    /**
     * NEW: Handle single printer compatibility check - STRICT ENFORCEMENT
     * For reprint, we enforce strict compatibility - no cross-type printing allowed
     *
     * @param object $selectedPrinter
     * @param string $labelType
     * @param bool $isInstructionCardLabel
     * @param bool $isSmallLabel
     * @return array
     */
    protected function handleSinglePrinterCompatibility($selectedPrinter, $labelType, $isInstructionCardLabel, $isSmallLabel)
    {
        try {
            // STRICT ENFORCEMENT: No cross-type printing allowed
            if ($isInstructionCardLabel && $selectedPrinter->printer_type !== 'instruction_card') {
                // Get available instruction card printers
                $instructionCardPrinters = DB::table('tblprinters')
                    ->where('printer_type', 'instruction_card')
                    ->where('status', 'active')
                    ->select('printerid', 'printername', 'printer_type')
                    ->get();

                return [
                    'compatible' => false,
                    'message' => 'Instruction card labels can only be printed on instruction card printers. Please select an instruction card printer.',
                    'suggested_action' => 'Select an instruction card printer from the list below:',
                    'available_printers' => $instructionCardPrinters->toArray(),
                    'target_printer' => $selectedPrinter
                ];
            }

            if ($isSmallLabel && $selectedPrinter->printer_type !== 'small_label') {
                // Get available small label printers
                $smallLabelPrinters = DB::table('tblprinters')
                    ->where('printer_type', 'small_label')
                    ->where('status', 'active')
                    ->select('printerid', 'printername', 'printer_type')
                    ->get();

                return [
                    'compatible' => false,
                    'message' => 'Small labels (including vector images) can only be printed on small label printers. Please select a small label printer.',
                    'suggested_action' => 'Select a small label printer from the list below:',
                    'available_printers' => $smallLabelPrinters->toArray(),
                    'target_printer' => $selectedPrinter
                ];
            }

            // Compatible - same printer type as required
            return [
                'compatible' => true,
                'message' => 'Printer is compatible with selected label type',
                'target_printer' => $selectedPrinter,
                'routing_reason' => 'Direct printing to selected printer'
            ];

        } catch (Exception $e) {
            Log::error('Error in single printer compatibility check:', [
                'error' => $e->getMessage(),
                'printer_id' => $selectedPrinter->printerid
            ]);

            return [
                'compatible' => false,
                'message' => 'Error checking printer compatibility: ' . $e->getMessage(),
                'target_printer' => $selectedPrinter
            ];
        }
    }

    /**
     * NEW: Generate description for reprint action in history log
     *
     * @param object $selectedPrinter
     * @param object $targetPrinter
     * @param string $labelType
     * @return string
     */
    protected function getReprintActionDescription($selectedPrinter, $targetPrinter, $labelType)
    {
        $labelTypeName = ucwords(str_replace('_', ' ', $labelType));
        
        if ($selectedPrinter->printerid === $targetPrinter->printerid) {
            return "Single label reprinted ({$labelTypeName}) on {$selectedPrinter->printername}";
        } else {
            return "Single label reprinted ({$labelTypeName}) - Smart routed from {$selectedPrinter->printername} to {$targetPrinter->printername}";
        }
    }

    /**
     * NEW: Get available printers for specific label type
     * This helps the frontend suggest appropriate printers
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailablePrintersForLabelType(Request $request)
    {
        try {
            $request->validate([
                'label_type' => 'required|string'
            ]);

            $labelType = $request->label_type;
            
            // Define label type categories - CORRECTED
            $instructionCardLabels = ['instruction_cards'];
            $isInstructionCardLabel = in_array($labelType, $instructionCardLabels);
            
            if ($isInstructionCardLabel) {
                // Get instruction card printers (including married pairs that have instruction card capability)
                $printers = DB::table('tblprinters as p1')
                    ->leftJoin('tblprinters as p2', 'p1.married_to_printer_id', '=', 'p2.printerid')
                    ->where('p1.status', 'active')
                    ->where(function($query) {
                        $query->where('p1.printer_type', 'instruction_card')
                              ->orWhere('p2.printer_type', 'instruction_card');
                    })
                    ->select([
                        'p1.printerid',
                        'p1.printername',
                        'p1.printer_type',
                        'p1.married_to_printer_id',
                        'p1.marriage_name',
                        'p2.printername as married_printer_name',
                        'p2.printer_type as married_printer_type'
                    ])
                    ->get();
            } else {
                // Get small label printers (including married pairs that have small label capability)
                $printers = DB::table('tblprinters as p1')
                    ->leftJoin('tblprinters as p2', 'p1.married_to_printer_id', '=', 'p2.printerid')
                    ->where('p1.status', 'active')
                    ->where(function($query) {
                        $query->where('p1.printer_type', 'small_label')
                              ->orWhere('p2.printer_type', 'small_label');
                    })
                    ->select([
                        'p1.printerid',
                        'p1.printername', 
                        'p1.printer_type',
                        'p1.married_to_printer_id',
                        'p1.marriage_name',
                        'p2.printername as married_printer_name',
                        'p2.printer_type as married_printer_type'
                    ])
                    ->get();
            }

            return response()->json([
                'success' => true,
                'label_type' => $labelType,
                'compatible_printers' => $printers
            ]);

        } catch (Exception $e) {
            Log::error('Error getting available printers for label type:', [
                'error' => $e->getMessage(),
                'label_type' => $request->label_type ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching compatible printers: ' . $e->getMessage()
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
                $product->fnsku_grading = $fnskuRecord->grading ?? null;
                $product->fnsku_storename = $fnskuRecord->storename ?? null;
                $product->ASINviewer = $fnskuRecord->ASIN ?? null;
            } else {
                // Set defaults if no FNSKU record found
                $product->FNSKU = null;
                $product->fnsku_grading = null;
                $product->fnsku_storename = null;
                $product->ASINviewer = null;
            }

            if ($asinRecord) {
                $product->AStitle = $asinRecord->internal ?? null;
                $product->asinStatus = $asinRecord->asinStatus ?? null;
                $product->vectorimage = $asinRecord->vectorimage ?? null;
                $product->instructioncard = $asinRecord->instructioncard ?? null;
                $product->instructioncard2 = $asinRecord->instructioncard2 ?? null;
                $product->instructioncard3 = $asinRecord->instructioncard3 ?? null;
                $product->TRANSPARENCY_QR_STATUS = $asinRecord->TRANSPARENCY_QR_STATUS ?? null;
            } else {
                // Set defaults if no ASIN record found
                $product->AStitle = null;
                $product->asinStatus = null;
                $product->vectorimage = null;
                $product->instructioncard = null;
                $product->instructioncard2 = null;
                $product->instructioncard3 = null;
                $product->TRANSPARENCY_QR_STATUS = null;
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
     * UPDATED to work with married printer system
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
                'print_data' => 'required|array',
                 'small_label_only' => 'sometimes|boolean' 
            ]);

            $serialNumber = trim($request->serial_number);
            $printerId = $request->printer_id;
            $printData = $request->print_data;
             $smallLabelOnly = $request->input('small_label_only', false);
            
            // Get selected printer info with marriage details
            $selectedPrinter = DB::table('tblprinters')
                ->where('printerid', $printerId)
                ->first();

            Log::info('Selected printer details:', [
                'printer_id' => $printerId,
                'printer_data' => $selectedPrinter,
                'printer_ip' => $selectedPrinter->printerip ?? 'NOT FOUND',
                'printer_type' => $selectedPrinter->printer_type ?? 'NOT FOUND',
                'married_to' => $selectedPrinter->married_to_printer_id ?? 'Single'
            ]);
                
            if (!$selectedPrinter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected printer not found'
                ], 404);
            }

            // Check if printer is active
            if ($selectedPrinter->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected printer is not active. Current status: ' . $selectedPrinter->status
                ], 400);
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

            // Get FNSKU and ASIN data using base FNSKU for condition checking
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

            // NEW: Use enhanced printer management system for married printers
            $printResult = $this->printLabelService->printLabelWithMarriedPrinters(
                $productId, 
                $username, 
                $selectedPrinter,
                $smallLabelOnly 
            );

            // Check if the print service returned a successful result
            if ($printResult['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => $printResult['message'],
                    'serial_number' => $serialNumber,
                    'printer_info' => $printResult['printer_info'] ?? [],
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
     * NEW: Get available printers with marriage information
     * UPDATED to include marriage details and status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPrinters()
    {
        try {
            $printers = DB::table('tblprinters as p1')
                ->leftJoin('tblprinters as p2', 'p1.married_to_printer_id', '=', 'p2.printerid')
                ->select([
                    'p1.printerid',
                    'p1.printername',
                    'p1.printerip',
                    'p1.port',
                    'p1.printer_type',
                    'p1.status',
                    'p1.married_to_printer_id',
                    'p1.marriage_name',
                    'p1.marriage_description',
                    'p2.printername as married_printer_name',
                    'p2.printerip as married_printer_ip',
                    'p2.printer_type as married_printer_type',
                    'p2.status as married_printer_status'
                ])
                ->where('p1.status', 'active')
                ->orderBy('p1.printername')
                ->get();

            // Enhanced printer data with marriage information
            $enhancedPrinters = $printers->map(function ($printer) {
                $isMarried = !empty($printer->married_to_printer_id);
                $marriageInfo = '';
                
                if ($isMarried) {
                    $marriageInfo = " (Married to {$printer->married_printer_name})";
                    
                    // Check if married printer is active
                    if ($printer->married_printer_status !== 'active') {
                        $marriageInfo .= " - Partner Inactive";
                    }
                }

                return [
                    'printerid' => $printer->printerid,
                    'printername' => $printer->printername . $marriageInfo,
                    'printername_short' => $printer->printername,
                    'printerip' => $printer->printerip,
                    'port' => $printer->port,
                    'printer_type' => $printer->printer_type,
                    'status' => $printer->status,
                    'is_married' => $isMarried,
                    'marriage_name' => $printer->marriage_name,
                    'marriage_description' => $printer->marriage_description,
                    'married_printer' => $isMarried ? [
                        'id' => $printer->married_to_printer_id,
                        'name' => $printer->married_printer_name,
                        'ip' => $printer->married_printer_ip,
                        'type' => $printer->married_printer_type,
                        'status' => $printer->married_printer_status
                    ] : null
                ];
            });
            
            return response()->json([
                'success' => true,
                'printers' => $enhancedPrinters
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching printers:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch printers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * NEW: Get married printer pairs for synchronized printing
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMarriedPrinterPairs()
    {
        try {
            $marriages = DB::table('tblprinters as sl')
                ->join('tblprinters as ic', 'sl.married_to_printer_id', '=', 'ic.printerid')
                ->where('sl.printer_type', 'small_label')
                ->where('ic.printer_type', 'instruction_card')
                ->where('sl.status', 'active')
                ->where('ic.status', 'active')
                ->whereNotNull('sl.married_to_printer_id')
                ->select([
                    'sl.printerid as small_label_id',
                    'sl.printername as small_label_name',
                    'sl.printerip as small_label_ip',
                    'sl.port as small_label_port',
                    'ic.printerid as instruction_card_id',
                    'ic.printername as instruction_card_name',
                    'ic.printerip as instruction_card_ip',
                    'ic.port as instruction_card_port',
                    'sl.marriage_name',
                    'sl.marriage_description'
                ])
                ->get();

            return response()->json([
                'success' => true,
                'married_pairs' => $marriages
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching married printer pairs:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch married printer pairs: ' . $e->getMessage()
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
            $printerTableExists = DB::getSchemaBuilder()->hasTable('tblprinters');
            
            $debug = [
                'table_names' => [
                    'product_table' => $this->productTable,
                    'fnsku_table' => $this->fnskuTable,
                    'asin_table' => $this->asinTable,
                    'printer_table' => 'tblprinters',
                    'history_table' => $this->itemProcessHistoryTable ?? 'Not set'
                ],
                'table_existence' => [
                    'product_exists' => $productTableExists,
                    'fnsku_exists' => $fnskuTableExists,
                    'asin_exists' => $asinTableExists,
                    'printer_exists' => $printerTableExists
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

            if ($printerTableExists) {
                $debug['printer_columns'] = DB::getSchemaBuilder()->getColumnListing('tblprinters');
                
                // Get sample printer data with marriage info
                $samplePrinters = DB::table('tblprinters as p1')
                    ->leftJoin('tblprinters as p2', 'p1.married_to_printer_id', '=', 'p2.printerid')
                    ->select([
                        'p1.printerid',
                        'p1.printername',
                        'p1.printer_type',
                        'p1.status',
                        'p1.married_to_printer_id',
                        'p1.marriage_name',
                        'p2.printername as married_to_name',
                        'p2.printer_type as married_to_type'
                    ])
                    ->limit(3)
                    ->get();
                    
                $debug['sample_printers'] = $samplePrinters;
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
                    
                    // Test the new separated query approach
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
     * Clear any cache or temporary files
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache()
    {
        try {
            // Clear Laravel cache
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            
            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
            
        } catch (Exception $e) {
            Log::error('Error clearing cache:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error clearing cache: ' . $e->getMessage()
            ], 500);
        }
    }
}