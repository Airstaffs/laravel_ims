<?php

namespace App\Services;

use App\Http\Controllers\BasetablesController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;
use DateTimeZone;
use Exception;

class PrintLabelService extends BasetablesController
{
    protected $printerIp;
    protected $printServerUrl;
    protected $instructionCardPrinterIp;
    protected $imageProcessingService;

    public function __construct()
    {
        parent::__construct();
        
        // Set printer settings - these should be configurable
        $this->printerIp = config('app.printer_ip', '192.168.1.109');
        $this->printServerUrl = config('app.print_server_url', 'http://99.0.87.190:1450/ims/Admin/modules/PRD-RPN-PCN/print.php');
        $this->instructionCardPrinterIp = config('app.instruction_card_printer_ip', '192.168.1.246');
        $this->imageProcessingService = new ImageProcessingService();
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
     * NEW: Print label with married printer system support
     * This method handles printing to married printers automatically
     */
    public function printLabelWithMarriedPrinters($productId, $username, $selectedPrinter)
    {
        try {
            Log::info('Starting print with married printer system:', [
                'product_id' => $productId,
                'printer_id' => $selectedPrinter->printerid,
                'printer_name' => $selectedPrinter->printername,
                'printer_type' => $selectedPrinter->printer_type,
                'is_married' => !empty($selectedPrinter->married_to_printer_id)
            ]);

            // Get product with enriched data
            $enrichedProduct = $this->getEnrichedProductData($productId);
            
            if (!$enrichedProduct) {
                return [
                    'status' => 'error',
                    'message' => 'Product not found or not validated'
                ];
            }

            // Check if the selected printer is married
            if (!empty($selectedPrinter->married_to_printer_id)) {
                return $this->printToMarriedPrinters($enrichedProduct, $username, $selectedPrinter);
            } else {
                return $this->printToSinglePrinter($enrichedProduct, $username, $selectedPrinter);
            }

        } catch (Exception $e) {
            Log::error('Error in printLabelWithMarriedPrinters:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'product_id' => $productId
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Error printing label: ' . $e->getMessage()
            ];
        }
    }

    /**
     * NEW: Print to married printers (synchronized printing)
     */
    protected function printToMarriedPrinters($product, $username, $selectedPrinter)
    {
        try {
            // Get the married printer details
            $marriedPrinter = DB::table('tblprinters')
                ->where('printerid', $selectedPrinter->married_to_printer_id)
                ->where('status', 'active')
                ->first();

            if (!$marriedPrinter) {
                Log::warning('Married printer not found or inactive:', [
                    'married_printer_id' => $selectedPrinter->married_to_printer_id
                ]);
                
                // Fall back to single printer
                return $this->printToSinglePrinter($product, $username, $selectedPrinter);
            }

            Log::info('Printing to married printer pair:', [
                'marriage_name' => $selectedPrinter->marriage_name,
                'small_label_printer' => $selectedPrinter->printer_type === 'small_label' ? $selectedPrinter->printername : $marriedPrinter->printername,
                'instruction_card_printer' => $selectedPrinter->printer_type === 'instruction_card' ? $selectedPrinter->printername : $marriedPrinter->printername
            ]);

            // Determine which printer is which type
            $smallLabelPrinter = ($selectedPrinter->printer_type === 'small_label') ? $selectedPrinter : $marriedPrinter;
            $instructionCardPrinter = ($selectedPrinter->printer_type === 'instruction_card') ? $selectedPrinter : $marriedPrinter;

            // Get return counts and condition
            $returnCounts = $this->getReturnCounts($product);
            $condition = $this->formatCondition(
                $product->gradingviewer ?? '', 
                $product->StoreName ?? '', 
                $product->ASINviewer ?? '', 
                $product->asinStatus ?? ''
            );

            // Generate complete ZPL code with separation
            $zplData = $this->generateCompleteZplCode($product, $condition, $returnCounts, $username);
            
            $printResults = [];
            $overallSuccess = true;
            $messages = [];

            // Print small labels to small label printer
            if (!empty($zplData['mainZpl'])) {
                Log::info('Sending main labels to small label printer:', [
                    'printer_name' => $smallLabelPrinter->printername,
                    'printer_ip' => $smallLabelPrinter->printerip
                ]);

                $mainResult = $this->sendToPrinter($zplData['mainZpl'], $smallLabelPrinter->printerip);
                $printResults['small_labels'] = $mainResult;
                
                if ($mainResult['status'] === 'success') {
                    $messages[] = "Small labels printed to {$smallLabelPrinter->printername}";
                } else {
                    $overallSuccess = false;
                    $messages[] = "Failed to print small labels to {$smallLabelPrinter->printername}: {$mainResult['message']}";
                }
            }

            // Print instruction cards to instruction card printer
            if (!empty($zplData['instructionCardZpl'])) {
                Log::info('Sending instruction cards to instruction card printer:', [
                    'printer_name' => $instructionCardPrinter->printername,
                    'printer_ip' => $instructionCardPrinter->printerip
                ]);

                $instructionResult = $this->sendToPrinter($zplData['instructionCardZpl'], $instructionCardPrinter->printerip);
                $printResults['instruction_cards'] = $instructionResult;
                
                if ($instructionResult['status'] === 'success') {
                    $messages[] = "Instruction cards printed to {$instructionCardPrinter->printername}";
                } else {
                    $overallSuccess = false;
                    $messages[] = "Failed to print instruction cards to {$instructionCardPrinter->printername}: {$instructionResult['message']}";
                }
            } else {
                $messages[] = "No instruction cards to print";
            }

            // Update print count if any printing was successful
            if ($overallSuccess || $printResults['small_labels']['status'] === 'success') {
                $this->updatePrintCount($product->ProductID, $username, $selectedPrinter, $marriedPrinter);
            }

            $finalMessage = implode('. ', $messages);
            
            return [
                'status' => $overallSuccess ? 'success' : 'partial',
                'message' => $finalMessage,
                'printer_info' => [
                    'marriage_name' => $selectedPrinter->marriage_name,
                    'small_label_printer' => [
                        'name' => $smallLabelPrinter->printername,
                        'ip' => $smallLabelPrinter->printerip,
                        'status' => $printResults['small_labels']['status'] ?? 'not_attempted'
                    ],
                    'instruction_card_printer' => [
                        'name' => $instructionCardPrinter->printername,
                        'ip' => $instructionCardPrinter->printerip,
                        'status' => $printResults['instruction_cards']['status'] ?? 'not_attempted'
                    ]
                ],
                'print_results' => $printResults
            ];

        } catch (Exception $e) {
            Log::error('Error in printToMarriedPrinters:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Error printing to married printers: ' . $e->getMessage()
            ];
        }
    }

    /**
     * NEW: Print to single printer (non-married)
     */
    protected function printToSinglePrinter($product, $username, $selectedPrinter)
    {
        try {
            Log::info('Printing to single printer:', [
                'printer_name' => $selectedPrinter->printername,
                'printer_type' => $selectedPrinter->printer_type,
                'printer_ip' => $selectedPrinter->printerip
            ]);

            // Get return counts and condition
            $returnCounts = $this->getReturnCounts($product);
            $condition = $this->formatCondition(
                $product->gradingviewer ?? '', 
                $product->StoreName ?? '', 
                $product->ASINviewer ?? '', 
                $product->asinStatus ?? ''
            );

            // Generate ZPL based on printer type
            if ($selectedPrinter->printer_type === 'small_label') {
                // Generate only small labels
                $zplData = $this->generateCompleteZplCode($product, $condition, $returnCounts, $username);
                $zpl = $zplData['mainZpl'];
                $labelTypes = 'small labels';
            } elseif ($selectedPrinter->printer_type === 'instruction_card') {
                // Generate only instruction cards
                $zplData = $this->generateCompleteZplCode($product, $condition, $returnCounts, $username);
                $zpl = $zplData['instructionCardZpl'];
                $labelTypes = 'instruction cards';
            } else {
                // Unknown printer type - generate all labels
                $zplData = $this->generateCompleteZplCode($product, $condition, $returnCounts, $username);
                $zpl = $zplData['mainZpl'] . $zplData['instructionCardZpl'];
                $labelTypes = 'all labels';
            }

            if (empty($zpl)) {
                return [
                    'status' => 'error',
                    'message' => "No {$labelTypes} to print for this product"
                ];
            }

            // Send to printer
            $result = $this->sendToPrinter($zpl, $selectedPrinter->printerip);

            if ($result['status'] === 'success') {
                // Update print count
                $this->updatePrintCount($product->ProductID, $username, $selectedPrinter);
                
                return [
                    'status' => 'success',
                    'message' => ucfirst($labelTypes) . " printed successfully to {$selectedPrinter->printername}",
                    'printer_info' => [
                        'printer_name' => $selectedPrinter->printername,
                        'printer_ip' => $selectedPrinter->printerip,
                        'printer_type' => $selectedPrinter->printer_type,
                        'label_types' => $labelTypes
                    ]
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => "Failed to print {$labelTypes} to {$selectedPrinter->printername}: {$result['message']}"
                ];
            }

        } catch (Exception $e) {
            Log::error('Error in printToSinglePrinter:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Error printing to single printer: ' . $e->getMessage()
            ];
        }
    }

    /**
     * NEW: Update print count and log activity with enhanced printer information
     */
    protected function updatePrintCount($productId, $username, $primaryPrinter, $marriedPrinter = null)
    {
        try {
            // Update print count
            $currentPrintCount = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->value('printCount') ?? 0;
                
            $newPrintCount = $currentPrintCount + 1;
            
            DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->update([
                    'printCount' => $newPrintCount,
                    'printby' => $username
                ]);

            // Enhanced logging with marriage information
            if (isset($this->itemProcessHistoryTable) && 
                DB::getSchemaBuilder()->hasTable($this->itemProcessHistoryTable)) {
                
                $product = DB::table($this->productTable)->where('ProductID', $productId)->first();
                
                $printerInfo = $primaryPrinter->printername;
                
                if ($marriedPrinter) {
                    $printerInfo = "Married Printers: {$primaryPrinter->printername} & {$marriedPrinter->printername}";
                    if (!empty($primaryPrinter->marriage_name)) {
                        $printerInfo .= " ({$primaryPrinter->marriage_name})";
                    }
                }
                
                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $product->rtcounter,
                    'employeeName' => $username,
                    'editDate' => $this->getCurrentDateTime(),
                    'Module' => 'Label Printing',
                    'Action' => 'Label printed for ' . ($product->FNSKUviewer ?? 'unknown FNSKU') . ' on ' . $printerInfo . ' (Print #' . $newPrintCount . ')'
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error updating print count:', [
                'error' => $e->getMessage(),
                'product_id' => $productId
            ]);
        }
    }

    /**
     * UPDATED: Legacy printLabel method for backward compatibility
     * Now uses the new married printer system
     */
    public function printLabel($productId, $username, $selectedPrinter = null)
    {
        return $this->printLabelWithMarriedPrinters($productId, $username, $selectedPrinter);
    }

    /**
     * NEW: Get enriched product data with FNSKU and ASIN information
     */
    protected function getEnrichedProductData($productId)
    {
        try {
            // Get product first
            $product = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->where('returnstatus', 'Not Returned')
                ->where('ProductModuleLoc', '!=', 'Migrated')
                ->where('validation_status', 'validated')
                ->orderBy('ProductID', 'desc')
                ->first();

            if (!$product) {
                return null;
            }

            // Extract base FNSKU and get related data
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

            // Combine data properly
            $enrichedProduct = clone $product;
            
            if ($fnskuRecord) {
                $enrichedProduct->ASINviewer = $fnskuRecord->ASIN;
                $enrichedProduct->gradingviewer = $fnskuRecord->grading;
                $enrichedProduct->StoreName = $fnskuRecord->storename;
            }

            if ($asinRecord) {
                $enrichedProduct->AStitle = $asinRecord->internal;
                $enrichedProduct->asinStatus = $asinRecord->asinStatus;
                $enrichedProduct->TRANSPARENCY_QR_STATUS = $asinRecord->TRANSPARENCY_QR_STATUS;
                $enrichedProduct->vectorimage = $asinRecord->vectorimage;
                $enrichedProduct->instructioncard = $asinRecord->instructioncard;
                $enrichedProduct->instructioncard2 = $asinRecord->instructioncard2;
                $enrichedProduct->instructioncard3 = $asinRecord->instructioncard3;
            }

            return $enrichedProduct;

        } catch (Exception $e) {
            Log::error('Error getting enriched product data:', [
                'error' => $e->getMessage(),
                'product_id' => $productId
            ]);
            
            return null;
        }
    }

    /**
     * Reprint a single label type for a product
     * UPDATED to work with new printer management system
     */
    public function reprintSingleLabel($productId, $labelType, $username, $selectedPrinter = null)
    {
        try {
            Log::info('Starting single label reprint:', [
                'product_id' => $productId,
                'label_type' => $labelType,
                'printer_name' => $selectedPrinter->printername ?? 'unknown',
                'printer_type' => $selectedPrinter->printer_type ?? 'unknown'
            ]);

            // Get enriched product data
            $enrichedProduct = $this->getEnrichedProductDataForReprint($productId);
            
            if (!$enrichedProduct) {
                return [
                    'status' => 'error',
                    'message' => 'Product not found'
                ];
            }

            // Get return counts and condition
            $returnCounts = $this->getReturnCounts($enrichedProduct);
            $condition = $this->formatCondition(
                $enrichedProduct->gradingviewer ?? '', 
                $enrichedProduct->StoreName ?? '', 
                $enrichedProduct->ASINviewer ?? '', 
                $enrichedProduct->asinStatus ?? ''
            );
            
            // Generate ZPL for the specific label type
            $zpl = $this->generateSingleLabelZpl($enrichedProduct, $labelType, $condition, $returnCounts, $username);
            
            if (empty($zpl)) {
                return [
                    'status' => 'error',
                    'message' => 'Could not generate ZPL for label type: ' . $labelType
                ];
            }
            
            // Determine target printer IP based on label type and marriage
            $targetPrinterIp = $this->determineTargetPrinterIp($selectedPrinter, $labelType);
            
            // Send to appropriate printer
            $result = $this->sendToPrinter($zpl, $targetPrinterIp);
            
            // Log the reprint activity with enhanced information
            if ($result['status'] === 'success' && 
                isset($this->itemProcessHistoryTable) && 
                DB::getSchemaBuilder()->hasTable($this->itemProcessHistoryTable)) {
                
                $targetPrinterName = $this->getPrinterNameByIp($targetPrinterIp) ?? $selectedPrinter->printername;
                
                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $enrichedProduct->rtcounter,
                    'employeeName' => $username,
                    'editDate' => $this->getCurrentDateTime(),
                    'Module' => 'Label Reprinting',
                    'Action' => "Single label reprinted ({$labelType}) for " . ($enrichedProduct->FNSKUviewer ?? 'unknown FNSKU') . " on {$targetPrinterName}"
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            Log::error('Error in reprintSingleLabel service:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'productId' => $productId,
                'labelType' => $labelType
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Error reprinting label: ' . $e->getMessage()
            ];
        }
    }

    /**
     * NEW: Get enriched product data for reprint (less restrictive than regular printing)
     */
    protected function getEnrichedProductDataForReprint($productId)
    {
        try {
            // Get product first (less restrictive for reprints)
            $product = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->first();

            if (!$product) {
                return null;
            }

            // Extract base FNSKU and get related data
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

            // Combine data properly
            $enrichedProduct = clone $product;
            
            if ($fnskuRecord) {
                $enrichedProduct->ASINviewer = $fnskuRecord->ASIN;
                $enrichedProduct->gradingviewer = $fnskuRecord->grading;
                $enrichedProduct->StoreName = $fnskuRecord->storename;
            }

            if ($asinRecord) {
                $enrichedProduct->AStitle = $asinRecord->internal;
                $enrichedProduct->asinStatus = $asinRecord->asinStatus;
                $enrichedProduct->TRANSPARENCY_QR_STATUS = $asinRecord->TRANSPARENCY_QR_STATUS;
                $enrichedProduct->vectorimage = $asinRecord->vectorimage;
                $enrichedProduct->instructioncard = $asinRecord->instructioncard;
                $enrichedProduct->instructioncard2 = $asinRecord->instructioncard2;
                $enrichedProduct->instructioncard3 = $asinRecord->instructioncard3;
            }

            return $enrichedProduct;

        } catch (Exception $e) {
            Log::error('Error getting enriched product data for reprint:', [
                'error' => $e->getMessage(),
                'product_id' => $productId
            ]);
            
            return null;
        }
    }

    /**
     * NEW: Determine target printer IP based on label type and marriage status
     */
    protected function determineTargetPrinterIp($selectedPrinter, $labelType)
    {
        try {
            // Define which label types go to instruction card printers
            $instructionCardLabels = [
                'instruction_cards',
                'vector_image'
            ];

            // If this label type should go to instruction card printer and printer is married
            if (in_array($labelType, $instructionCardLabels) && !empty($selectedPrinter->married_to_printer_id)) {
                
                $marriedPrinter = DB::table('tblprinters')
                    ->where('printerid', $selectedPrinter->married_to_printer_id)
                    ->where('status', 'active')
                    ->first();

                // If married printer exists and is instruction card type, use it
                if ($marriedPrinter && $marriedPrinter->printer_type === 'instruction_card') {
                    Log::info('Routing instruction card label to married printer:', [
                        'label_type' => $labelType,
                        'target_printer' => $marriedPrinter->printername,
                        'target_ip' => $marriedPrinter->printerip
                    ]);
                    
                    return $marriedPrinter->printerip;
                }
            }

            // Use the originally selected printer
            return $selectedPrinter->printerip;

        } catch (Exception $e) {
            Log::error('Error determining target printer IP:', [
                'error' => $e->getMessage(),
                'label_type' => $labelType
            ]);

            // Fallback to selected printer
            return $selectedPrinter->printerip;
        }
    }

    /**
     * NEW: Get printer name by IP address
     */
    protected function getPrinterNameByIp($printerIp)
    {
        try {
            $printer = DB::table('tblprinters')
                ->where('printerip', $printerIp)
                ->first();
                
            return $printer ? $printer->printername : null;
            
        } catch (Exception $e) {
            Log::error('Error getting printer name by IP:', [
                'error' => $e->getMessage(),
                'printer_ip' => $printerIp
            ]);
            
            return null;
        }
    }

    /**
     * Generate ZPL for a specific label type
     * UPDATED with new label types and better organization
     */
    protected function generateSingleLabelZpl($product, $labelType, $condition, $returnCounts, $username)
    {
        try {
            Log::info('Generating single label ZPL:', [
                'labelType' => $labelType,
                'productId' => $product->ProductID ?? 'unknown'
            ]);

            switch ($labelType) {
                case 'serial_labels':
                    return $this->generateAllSerialLabels($product, $condition, $returnCounts);
                    
                case 'fnsku_label':
                    return !empty($product->FNSKUviewer) ? 
                        $this->generateFnskuLabel($product, $condition) : '';
                    
                case 'title_label':
                    return !empty($product->AStitle) ? 
                        $this->generateTitleLabel($product) : '';
                    
                case 'item_number_label':
                    return !empty($product->itemnumber) ? 
                        $this->generateItemNumberLabel($product) : '';
                    
                case 'timestamp_label':
                    return !empty($product->rtcounter) ? 
                        $this->generateTimestampLabel($product, $username) : '';
                    
                case 'sticker_note_label':
                    return !empty($product->stickernote) ? 
                        $this->generateStickerNoteLabel($product->stickernote, $product->mID ?? 0) : '';
                    
                case 'warehouse_location_label':
                    return !empty($product->warehouselocation) ? 
                        $this->generateWarehouseLocationLabel($product->warehouselocation) : '';
                    
                case 'rtcounter_label':
                    return !empty($product->rtcounter) ? 
                        $this->generateRTARCounterLabel($product, $condition) : '';
                    
                case 'qr_manual':
                    return !empty($product->ASINviewer) ? 
                        $this->imageProcessingService->convertImageQRmanual($product->ASINviewer, $product->AStitle ?? '') : '';
                    
                case 'qr_serial':
                    return !empty($product->serialnumber) ? 
                        $this->imageProcessingService->convertImageQRserial($product->serialnumber) : '';
                    
                case 'vector_image':
                    return (!empty($product->ASINviewer) && !empty($product->vectorimage)) ? 
                        $this->processVectorImage($product->vectorimage) : '';
                    
                case 'instruction_cards':
                    return !empty($product->ASINviewer) ? 
                        $this->generateInstructionCardLabels($product) : '';
                    
                case 'transparency_qr':
                    return (!empty($product->ASINviewer) && !empty($product->TRANSPARENCY_QR_STATUS)) ? 
                        $this->generateTransparencyQRLabel($product->TRANSPARENCY_QR_STATUS) : '';
                    
                case 'print_count':
                    return (isset($product->printCount) && $product->printCount > 0) ? 
                        $this->generatePrintCountLabel($product->printCount + 1) : '';

                case 'small_label_card':
                    if (!empty($product->ASINviewer) && !empty($product->serialnumber)) {
                        $Wserial = trim($product->serialnumber);
                        $smallCardCopies = 1;
                        $zpl = '';
                         // Get return count for serial A from the passed parameter
                        $returnCountA = $returnCounts['a'] ?? 0;
                        
                        Log::info('Reprinting small label cards:', [
                            'serial' => $Wserial,
                            'copies' => $smallCardCopies
                        ]);
                        
                        // Generate 3 copies
                        for ($i = 0; $i < $smallCardCopies; $i++) {
                            $zpl .= $this->imageProcessingService->generateQRforSmallLabelCard($Wserial, $returnCountA);
                        }
                        
                        return $zpl;
                    }
                    return '';        
                    
                default:
                    Log::warning('Unknown label type for reprint:', ['labelType' => $labelType]);
                    return '';
            }
            
        } catch (Exception $e) {
            Log::error('Error generating single label ZPL:', [
                'error' => $e->getMessage(),
                'labelType' => $labelType,
                'productId' => $product->ProductID ?? 'unknown'
            ]);
            
            return '';
        }
    }

    /**
     * Generate complete ZPL code with all label functions - UPDATED VERSION
     * Returns separate main ZPL and instruction card ZPL for married printer system
     */
    protected function generateCompleteZplCode($product, $condition, $returnCounts, $username)
    {
        try {
            $zpl = '';
            $zplIC = '';
            $nonNullCount = 0;
            $isRenewed = ($condition === 'Refurbished - Excellent');
            
            // Initial dividers and renewed header
            if (!empty($product->ProductID)) {
                $nonNullCount++;
                $zpl .= "^XA^FO40,120^ADN,36,20^FW^FD--- DIVIDER ---^FS^XZ";
                $zpl .= "^XA^FO40,120^ADN,36,20^FW^FD--- DIVIDER ---^FS^XZ";
                
                if ($isRenewed) {
                    $zpl .= "^XA^FO5,70^ADN,200,42^FW^FD RENEWED ^FS^XZ";
                }
            }

            // Serial number labels (A and B)
            if (!empty($product->serialnumber) && !empty($product->serialnumberb)) {
                $nonNullCount++;
                $zpl .= $this->generateDualSerialLabels($product->serialnumber, $product->serialnumberb, $condition);
            } else if (!empty($product->serialnumber)) {
                $nonNullCount++;
                $returnInfo = "R:" . ($returnCounts['a'] ?? 0) . " ";
                $zpl .= $this->generateSingleSerialLabels($product->serialnumber, $condition, $returnInfo);
            }

            // Serial number labels (C and D)
            if (!empty($product->serialnumberc) && !empty($product->serialnumberd)) {
                $nonNullCount++;
                $zpl .= $this->generateDualSerialLabels($product->serialnumberc, $product->serialnumberd, $condition);
            } else if (!empty($product->serialnumberc) && empty($product->serialnumberd)) {
                $nonNullCount++;
                $zpl .= $this->generateSingleSerialLabels($product->serialnumberc, $condition, "");
            }

            // FNSKU label with special prefix handling
            if (!empty($product->FNSKUviewer)) {
                $nonNullCount++;
                $zpl .= $this->generateFnskuLabel($product, $condition);
            }

            // Title label with RT/AR package number
            if (!empty($product->AStitle)) {
                $nonNullCount++;
                $zpl .= $this->generateTitleLabel($product);
            }

            // Vector image processing
            if (!empty($product->ASINviewer) && !empty($product->vectorimage)) {
                $nonNullCount++;
                $zpl .= $this->processVectorImage($product->vectorimage);
            }

            // Item number label
            if (!empty($product->itemnumber)) {
                $nonNullCount++;
                $zpl .= $this->generateItemNumberLabel($product);
            }

            // Timestamp and priority label
            if (!empty($product->rtcounter)) {
                $nonNullCount++;
                $zpl .= $this->generateTimestampLabel($product, $username);
            }

            // Sticker notes with word wrapping
            if (!empty($product->stickernote)) {
                $nonNullCount++;
                $zpl .= $this->generateStickerNoteLabel($product->stickernote, $product->mID ?? 0);
            }

            // Item status (Not Working)
            if (isset($product->itemstatus) && $product->itemstatus === 'Not Working') {
                $nonNullCount++;
                $zpl .= $this->generateItemStatusLabel($product->itemstatus);
            }

            // Notes label
            if (!empty($product->notes)) {
                $nonNullCount++;
                $zpl .= $this->generateNotesLabel($product->notes);
            }

            // Transparency QR status
            if (!empty($product->ASINviewer) && !empty($product->TRANSPARENCY_QR_STATUS)) {
                $nonNullCount++;
                $zpl .= $this->generateTransparencyQRLabel($product->TRANSPARENCY_QR_STATUS);
            }

            // QR codes for manual and serial
            if (!empty($product->ASINviewer)) {
                $zpl .= $this->imageProcessingService->convertImageQRmanual($product->ASINviewer, $product->AStitle ?? '');
            }

            if (!empty($product->serialnumber)) {
                $nonNullCount++;
                $zpl .= $this->imageProcessingService->convertImageQRserial($product->serialnumber);
            }

            // Print count
            if (isset($product->printCount) && $product->printCount > 0) {
                $nonNullCount++;
                $zpl .= $this->generatePrintCountLabel($product->printCount + 1);
            }

            // Additional renewed labels
            if (!empty($product->ProductID) && $isRenewed) {
                $nonNullCount++;
                $zpl .= "^XA^FO5,70^ADN,200,42^FW^FD RENEWED ^FS^XZ";
                $zpl .= "^XA^FO5,70^ADN,200,42^FW^FD RENEWED ^FS^XZ";
            }

            // Warehouse location
            if (!empty($product->warehouselocation)) {
                $nonNullCount++;
                $zpl .= $this->generateWarehouseLocationLabel($product->warehouselocation);
            }

            // RTS (Return to Seller) handling
            if (isset($product->ProductModuleLoc) && $product->ProductModuleLoc === 'RTS') {
                $nonNullCount++;
                $zpl .= $this->generateRTSLabel($product);
            }

            // RT/AR counter with barcode
            if (!empty($product->rtcounter)) {
                $nonNullCount++;
                $zpl .= $this->generateRTARCounterLabel($product, $condition);
            }

            // Validation status
            if (isset($product->validation_status) && in_array($product->validation_status, ['invalid', 'unvalidated'])) {
                $zpl .= $this->generateValidationStatusLabel($product->validation_status);
            }

            if (!empty($product->ASINviewer) && !empty($product->serialnumber)) {
                    $Wserial = trim($product->serialnumber);
                    $smallCardCopies = 3;
                    $returnCountA = $returnCounts['a'] ?? 0;
                    
                    Log::info('Generating small label cards:', [
                        'serial' => $Wserial,
                        'copies' => $smallCardCopies
                    ]);
                    
                    // Print small label card multiple times
                    for ($i = 0; $i < $smallCardCopies; $i++) {
                        $zpl .= $this->imageProcessingService->generateQRforSmallLabelCard($Wserial, $returnCountA);
                    }
                }

            // Instruction card processing - Generate separately for different printer
            if (!empty($product->ASINviewer)) {
                Log::info('Generating instruction cards for married printer system');
                $zplIC = $this->generateInstructionCardLabels($product);
                
                if (!empty($zplIC)) {
                    Log::info('Generated instruction card ZPL for married system', ['length' => strlen($zplIC)]);
                } else {
                    Log::info('No instruction card ZPL generated');
                }
            }

            // Return both main ZPL and instruction card ZPL separately
            return [
                'mainZpl' => $zpl,
                'instructionCardZpl' => $zplIC
            ];
            
        } catch (Exception $e) {
            Log::error('Error generating complete ZPL code:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'mainZpl' => "^XA^FO50,50^ADN,18,18^FDError generating complete label^FS^XZ",
                'instructionCardZpl' => ''
            ];
        }
    }

    /**
     * Generate dual serial labels (for A&B or C&D) - EXACT replication from original
     */
    protected function generateDualSerialLabels($serialA, $serialB, $condition)
    {
        $zpl = '';
        $isRenewed = ($condition === 'Refurbished - Excellent');
        
        // Generate 3 copies
        for ($i = 0; $i < 3; $i++) {
            $zpl .= "^XA";
            
            if ($isRenewed) {
                $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO205,23^ADN,20,15^FD Renewed^FS";
            } else {
                $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO135,23^ADN,7,7^FD Certified Pre-Owned Unit^FS";
            }
            
            $zpl .= "^FO50,60^FB200,2,0,C^ADN,7,9^BCN,50,N,N,N,A^FD" . $serialA . "^FS";
            $zpl .= "^FO10,117^FB400,1,0,C^ADN,7,9^FD" . $serialA . "^FS";
            $zpl .= "^FO50,140^FB200,2,0,C^ADN,7,9^BCN,50,N,N,N,A^FD" . $serialB . "^FS";
            $zpl .= "^FO10,197^FB400,1,0,C^ADN,7,9^FD" . $serialB . "^FS";
            $zpl .= "^FO9,215^ADN,1,1^FW^FDThis SN is recorded,and if returning,^FS";
            $zpl .= "^FO9,235^ADN,1,1^FW^FDMUST MATCH item's to avoid charges^FS";
            $zpl .= "^XZ";
        }
        
        return $zpl;
    }

    /**
     * Generate single serial labels - EXACT replication from original
     */
    protected function generateSingleSerialLabels($serial, $condition, $returnInfo)
    {
        $zpl = '';
        $isRenewed = ($condition === 'Refurbished - Excellent');
        
        // Generate 3 copies
        for ($i = 0; $i < 3; $i++) {
            $zpl .= "^XA";
            
            if ($isRenewed) {
                $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO205,23^ADN,20,15^FD Renewed^FS";
            } else {
                $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO135,23^ADN,7,7^FD Certified Pre-Owned Unit^FS";
            }
            
            $zpl .= "^FO245,45^ADN,16,16^FW^FD" . $returnInfo . "^FS";
            $zpl .= "^FO35,80^FB400,2,0,C^ADN,12,12^BCN,80,N,N,N,A^FD" . $serial . "^FS";
            $zpl .= "^FO10,165^FB400,1,0,C^ADN,12,12^FD" . $serial . "^FS";
            $zpl .= "^FO6,210^ADN,1,1^FW^FDThis SN is recorded,and if returning,^FS";
            $zpl .= "^FO6,230^ADN,1,1^FW^FDMUST MATCH item's to avoid charges^FS";
            $zpl .= "^XZ";
        }
        
        return $zpl;
    }

    /**
     * Generate all serial labels for reprint
     */
    protected function generateAllSerialLabels($product, $condition, $returnCounts)
    {
        $zpl = '';
        
        // Serial number labels (A and B)
        if (!empty($product->serialnumber) && !empty($product->serialnumberb)) {
            $zpl .= $this->generateDualSerialLabels($product->serialnumber, $product->serialnumberb, $condition);
        } else if (!empty($product->serialnumber)) {
            $returnInfo = "R:" . ($returnCounts['a'] ?? 0) . " ";
            $zpl .= $this->generateSingleSerialLabels($product->serialnumber, $condition, $returnInfo);
        }

        // Serial number labels (C and D)
        if (!empty($product->serialnumberc) && !empty($product->serialnumberd)) {
            $zpl .= $this->generateDualSerialLabels($product->serialnumberc, $product->serialnumberd, $condition);
        } else if (!empty($product->serialnumberc) && empty($product->serialnumberd)) {
            $zpl .= $this->generateSingleSerialLabels($product->serialnumberc, $condition, "");
        }
        
        return $zpl;
    }

    /**
     * Generate FNSKU label with special handling for prefixes - EXACT replication from original
     */
    protected function generateFnskuLabel($product, $condition)
        {
            $fnsku = $product->FNSKUviewer;
            $asin = $product->ASINviewer;
            $title = $product->AStitle ?? '';
            $subvariant = $product->subvariant ?? '';
            $storeName = $product->StoreName ?? '';
            
            $zpl = "^XA";
            
            // Add RT/AR counter before the barcode
            $isRenovarTech = (stripos($storeName, 'Renovar Tech') !== false || 
                            stripos($storeName, 'Renovartech') !== false || 
                            empty($storeName));
            
            if ($isRenovarTech) {
                $zpl .= "^FO290,12^FB150,1,0,R^AON,16,16^FD RT" . sprintf("%05d", $product->rtcounter) . "^FS";
            } else {
                $zpl .= "^FO290,12^FB150,1,0,R^AON,16,16^FD AR" . sprintf("%05d", $product->rtcounter) . "^FS";
            }
            // Check if FNSKU equals ASIN
            if ($fnsku == $asin) {
                $zpl .= "^FO55,30^FB400,2,0,C^AON,24,24^BCN,100,N,N,N,A^FD" . $fnsku . "^FS";
                $zpl .= "^FO10,140^FB400,1,0,C^ADN,24,24^FD" . $fnsku . "^FS";
                $zpl .= "^FO30,170^FB400,10,0^AON,17,10^FD" . $condition . "- " . $title . "^FS";
                if (!empty($subvariant)) {
                    $zpl .= "^FO30,220^FB400,10,0^AON,17,10^FD " . $subvariant . "^FS";
                }
            } else {
                $prefix = substr($fnsku, 0, 2);
                
                // Check if prefix matches B-W[0-9] pattern
                if (preg_match('/^[B-W][0-9]/', $prefix)) {
                    $barcodeWithoutPrefix = substr($fnsku, 2);
                    $variable1 = $barcodeWithoutPrefix;
                    $variable2 = $barcodeWithoutPrefix . ' - ' . $prefix;
                    
                    $zpl .= "^FO55,30^FB400,2,0,C^AON,24,24^BCN,100,N,N,N,A^FD" . $variable1 . "^FS";
                    $zpl .= "^FO10,140^FB400,1,0,C^ADN,24,24^FD" . $variable2 . "^FS";
                    $zpl .= "^FO30,170^FB400,10,0^AON,17,10^FD" . $condition . "- " . $title . "^FS";
                    if (!empty($subvariant)) {
                        $zpl .= "^FO30,220^FB400,10,0^AON,17,10^FD " . $subvariant . "^FS";
                    }
                } else {
                    $zpl .= "^FO55,30^FB400,2,0,C^AON,24,24^BCN,100,N,N,N,A^FD" . $fnsku . "^FS";
                    $zpl .= "^FO10,140^FB400,1,0,C^ADN,24,24^FD" . $fnsku . "^FS";
                    $zpl .= "^FO30,170^FB400,10,0^AON,17,10^FD" . $condition . "- " . $title . "^FS";
                    if (!empty($subvariant)) {
                        $zpl .= "^FO30,220^FB400,10,0^AON,17,10^FD " . $subvariant . "^FS";
                    }
                }
            }
            
            $zpl .= "^XZ";
            return $zpl;
        }

    /**
     * Generate title label with RT/AR package number - EXACT replication from original
     */
    protected function generateTitleLabel($product)
    {
        $storeName = $product->StoreName ?? '';
        $isRenovarTech = (stripos($storeName, 'Renovar Tech') !== false || 
                          stripos($storeName, 'Renovartech') !== false || 
                          empty($storeName));
        
        $zpl = "^XA";
        $zpl .= "^FO20,50^FB400,10,0^AON,17,17^FW^FD" . $product->AStitle . "^FS";
        
        // Add subvariant for SONOS products
        if (!empty($product->subvariant) && stripos($product->AStitle, 'SONOS') !== false) {
            $zpl .= "^FO20,80^FB400,10,0^AON,17,17^FW^FD " . $product->subvariant . "^FS";
        }
        
        if ($isRenovarTech) {
            $zpl .= "^FO100,180^FB400,10,0^AOC,14,14^FW^FDPKG# RT" . sprintf("%05d", $product->rtcounter) . "^FS";
        } else {
            $zpl .= "^FO100,180^FB400,10,0^AOC,14,14^FW^FDPKG# AR" . sprintf("%05d", $product->rtcounter) . "^FS";
        }
        
        $zpl .= "^XZ";
        return $zpl;
    }

    /**
     * Generate item number label - EXACT replication from original
     */
    protected function generateItemNumberLabel($product)
    {
        $zpl = "^XA";
        $zpl .= "^FO90,30^FB400,10,0^AON,36,22^FW^FD" . ($product->PRD ?? '') . "^FS";
        $zpl .= "^FO120,70^FB400,2,0,C^AON,16,16^BCN,100,N,N,N,A^FD" . $product->itemnumber . "^FS";
        $zpl .= "^FO10,180^FB400,1,0,C^ADN,16,14^FD" . $product->itemnumber . "^FS";
        $zpl .= "^FO120,200^FB400,10,0^AON,36,22^FW^FD" . ($product->PCN ?? '') . "^FS";
        $zpl .= "^XZ";
        return $zpl;
    }

    /**
     * Generate timestamp and priority label - EXACT replication from original
     */
    protected function generateTimestampLabel($product, $username)
    {
        $california_timezone = new DateTimeZone('America/Los_Angeles');
        $current_datetime = new DateTime('now', $california_timezone);
        $formatted_date = $current_datetime->format('Y-m-d');
        $formatted_time = $current_datetime->format('h:i A');
        
        $zpl = "^XA";
        $zpl .= "^FO30,100^FB400,2,0,C^AON,18,18^FW^FDPRIORITY " . ($product->priorityrank ?? '') . "^FS";
        
        // Username with dynamic font size
        if (strlen($username) > 6) {
            $zpl .= "^FO30,130^FB400,2,0,C^AON,14,14^FW^FDPRINT BY:" . $username . "^FS";
        } else {
            $zpl .= "^FO30,130^FB400,2,0,C^AON,18,18^FW^FDPRINT BY:" . $username . "^FS";
        }
        
        $zpl .= "^FO30,160^FB400,2,0,C^AON,18,18^FW^FD" . $formatted_date . "^FS";
        $zpl .= "^FO30,190^FB400,2,0,C^AON,18,18^FW^FD" . $formatted_time . "^FS";
        $zpl .= "^XZ";
        
        return $zpl;
    }

    /**
     * Generate sticker note label with word wrapping - EXACT replication from original
     */
    protected function generateStickerNoteLabel($stickerNote, $checkmid = 0)
    {
        $zpl = "^XA";
        
        // Check if merged item
        if ($checkmid > 0) {
            $zpl .= "^FO5,40^ADN,1,1^FW^FDMerged RT#^FS";
            $y_position = 70;
        } else {
            $y_position = 50;
        }
        
        // Split the stickernote by line breaks
        $stickernote_parts = explode("\n", $stickerNote);
        $line_spacing = 30;
        $text_width = 180;
        $font_height = 20;
        $font_width = 20;
        $char_width = $font_height / 2;
        
        // Word wrapping algorithm from original
        foreach ($stickernote_parts as $part) {
            $words = explode(' ', trim($part));
            $line = '';
            
            foreach ($words as $word) {
                $word_width = strlen($word) * $char_width;
                
                if ($word_width > $text_width) {
                    if (!empty($line)) {
                        $zpl .= "^FO5," . $y_position . "^AON," . $font_height . "," . $font_width . "^FD" . $line . "^FS";
                        $y_position += $line_spacing;
                        $line = '';
                    }
                    $zpl .= "^FO5," . $y_position . "^AON," . $font_height . "," . $font_width . "^FD" . $word . "^FS";
                    $y_position += $line_spacing;
                } else {
                    $line_width = strlen($line . ' ' . $word) * $char_width;
                    
                    if ($line_width > $text_width) {
                        if (!empty($line)) {
                            $zpl .= "^FO5," . $y_position . "^AON," . $font_height . "," . $font_width . "^FD" . $line . "^FS";
                            $y_position += $line_spacing;
                            $line = '';
                        }
                        $line = $word;
                    } else {
                        $line .= (empty($line) ? '' : ' ') . $word;
                    }
                }
            }
            
            if (!empty($line)) {
                $zpl .= "^FO5," . $y_position . "^AON," . $font_height . "," . $font_width . "^FD" . $line . "^FS";
                $y_position += $line_spacing;
            }
        }
        
        $zpl .= "^XZ";
        return $zpl;
    }

    /**
     * Generate item status label - EXACT replication from original
     */
    protected function generateItemStatusLabel($itemStatus)
    {
        $zpl = "^XA";
        $zpl .= "^FO5,30^ADN,3,3^FW^FDItem Status:^FS";
        $zpl .= "^FO10,100^FB400,10,0^AON,28,25^FW^FD" . $itemStatus . "^FS";
        $zpl .= "^XZ";
        return $zpl;
    }

    /**
     * Generate notes label - EXACT replication from original
     */
    protected function generateNotesLabel($notes)
    {
        $zpl = "^XA";
        $zpl .= "^FO5,20^ADN,1,1^FW^FDS Notes^FS";
        $zpl .= "^FO30,50^FB400,10,0^AON,16,16^FW^FD" . $notes . "^FS";
        $zpl .= "^XZ";
        return $zpl;
    }

    /**
     * Generate transparency QR status label - EXACT replication from original
     */
    protected function generateTransparencyQRLabel($status)
    {
        $zpl = "^XA";
        $zpl .= "^FO5,20^ADN,3,3^FW^FDTransparency QR Status^FS";
        $zpl .= "^FO40,50^FB400,10,0^AON,16,16^FW^FD" . $status . "^FS";
        $zpl .= "^XZ";
        return $zpl;
    }

    /**
     * Generate print count label - EXACT replication from original
     */
    protected function generatePrintCountLabel($printCount)
    {
        $zpl = "^XA";
        $zpl .= "^FO30,100^FB400,2,0,C^AON,18,18^FW^FDPrint Count " . $printCount . "^FS";
        $zpl .= "^XZ";
        return $zpl;
    }

    /**
     * Generate warehouse location label - EXACT replication from original
     */
    protected function generateWarehouseLocationLabel($location)
    {
        $zpl = "^XA";
        $zpl .= "^FO5,30^ADN,3,3^FW^FD Warehouse Location^FS";
        $zpl .= "^FO10,100^FB400,10,0^AON,28,25^FW^FD" . $location . "^FS";
        $zpl .= "^XZ";
        return $zpl;
    }

    /**
     * Generate RTS (Return to Seller) label - EXACT replication from original
     */
    protected function generateRTSLabel($product)
    {
        $zpl = '';
        
        // Get test result details
        $testResult = DB::table('tbltestresult')
            ->where('ProdID', $product->ProductID)
            ->first();
        
        if ($testResult && isset($testResult->teststatus) && $testResult->teststatus === 'RTS') {
            $zpl = "^XA";
            $zpl .= "^FO10,30^ADN,18,10^FW^FD Return To Seller(RTS)^FS";
            $zpl .= "^FO20,70^ADN,14,7^FW^FD" . ($product->seller ?? '') . "^FS";
            $zpl .= "^FO30,110^FB400,10,0^AON,28,25^FW^FD" . ($testResult->reason ?? '') . "^FS";
            $zpl .= "^XZ";
        }
        
        return $zpl;
    }

    /**
     * Generate RT/AR counter label with barcode - EXACT replication from original
     */
    protected function generateRTARCounterLabel($product, $condition)
    {
        $storeName = $product->StoreName ?? '';
        $isRenovarTech = (stripos($storeName, 'Renovar Tech') !== false || 
                          stripos($storeName, 'Renovartech') !== false || 
                          empty($storeName));
        
        $zpl = "^XA";
        
        if ($isRenovarTech) {
            $zpl .= "^FO100,30^FB400,2,0,C^AON,18,18^BCN,100,N,N,N,A^FDRT" . sprintf("%05d", $product->rtcounter) . "^FS";
            $zpl .= "^FO10,140^FB400,1,0,C^ADN,26,22^FDRT" . sprintf("%05d", $product->rtcounter) . "^FS";
        } else {
            $zpl .= "^FO100,30^FB400,2,0,C^AON,26,22^BCN,100,N,N,N,A^FDAR" . sprintf("%05d", $product->rtcounter) . "^FS";
            $zpl .= "^FO10,140^FB400,1,0,C^ADN,26,22^FDAR" . sprintf("%05d", $product->rtcounter) . "^FS";
        }
        
        $zpl .= "^FO117,170^FB400,10,0^AON,36,22^FW^FD" . ($product->basketnumber ?? '') . "^FS";
        
        if ($isRenovarTech) {
            $zpl .= "^FO10,210^FB400,10,0^AON,36,22^FW^FD" . $condition . "^FS";
        } else {
            $zpl .= "^FO15,210^AON,22,13^FD" . $condition . "^FS";
        }
        
        $zpl .= "^XZ";
        return $zpl;
    }

    /**
     * Generate validation status label - EXACT replication from original
     */
    protected function generateValidationStatusLabel($validationStatus)
    {
        $zpl = "^XA";
        $zpl .= "^FO30,110^FB400,10,0^AON,28,25^FW^FD" . $validationStatus . "^FS";
        $zpl .= "^XZ";
        return $zpl;
    }

    /**
     * Generate instruction card labels - UPDATED for married printer system
     */
    protected function generateInstructionCardLabels($product)
    {
        try {
            $zplIC = '';
            $asinfind = $product->ASINviewer ?? '';
            $basketnumber = $product->basketnumber ?? '';
            $Wserial = $product->serialnumber ?? '';
            
            // Check if we have instruction card data
            $hasInstructionCard1 = !empty($product->instructioncard) && 
                ($product->instructioncard == 1 || !is_numeric($product->instructioncard));
            $hasInstructionCard2 = !empty($product->instructioncard2) && 
                ($product->instructioncard2 == 1 || !is_numeric($product->instructioncard2));
            $hasInstructionCard3 = !empty($product->instructioncard3) && 
                ($product->instructioncard3 == 1 || !is_numeric($product->instructioncard3));
            
            if (!$hasInstructionCard1 && !$hasInstructionCard2 && !$hasInstructionCard3) {
                Log::info('No instruction cards to process - all conditions false');
                return '';
            }
            
            Log::info('Processing instruction cards for married printer:', [
                'asin' => $asinfind,
                'card1' => $hasInstructionCard1 ? 'yes' : 'no',
                'card2' => $hasInstructionCard2 ? 'yes' : 'no',
                'card3' => $hasInstructionCard3 ? 'yes' : 'no',
                'serial' => $Wserial
            ]);
            
            // Generate file paths based on ASIN naming convention
            $card1FileName = $asinfind . '_card1.png';
            $card2FileName = $asinfind . '_card2.png';
            $card3FileName = $asinfind . '_card3.png';
            
            // Define base paths
            $instructionCardBasePath = public_path('images/instructioncard/');
            $monochromeBasePath = storage_path('app/public/images/monochrome/');
            
            // Process Card 1 if enabled
            if ($hasInstructionCard1) {
                $card1Path = $instructionCardBasePath . $card1FileName;
                
                if (file_exists($card1Path)) {
                    if ($this->imageProcessingService->safeConvertImage($card1Path, $monochromeBasePath, 800, 1200)) {
                        $monochromeImagePath = $monochromeBasePath . $card1FileName;
                        $zplIC .= $this->imageProcessingService->enhanceAndConvertToZPL($monochromeImagePath, $asinfind, $basketnumber);
                        Log::info('Successfully processed card 1 for married printer');
                    }
                } else {
                    Log::warning('Card 1 file not found for married printer', ['path' => $card1Path]);
                    $zplIC .= "^XA^FO50,50^ADN,18,18^FDCard 1 not found: " . $card1FileName . "^FS^XZ";
                }
            }
            
            // Process Card 2 if enabled
            if ($hasInstructionCard2) {
                $card2Path = $instructionCardBasePath . $card2FileName;
                
                if (file_exists($card2Path)) {
                    if ($this->imageProcessingService->safeConvertImage($card2Path, $monochromeBasePath, 800, 1200)) {
                        $monochromeImagePath = $monochromeBasePath . $card2FileName;
                        $zplIC .= $this->imageProcessingService->enhanceAndConvertToZPL($monochromeImagePath, $asinfind, $basketnumber);
                        Log::info('Successfully processed card 2 for married printer');
                    }
                } else {
                    Log::warning('Card 2 file not found for married printer', ['path' => $card2Path]);
                    $zplIC .= "^XA^FO50,50^ADN,18,18^FDCard 2 not found: " . $card2FileName . "^FS^XZ";
                }
            }
            
            // Process Card 3 if enabled
            if ($hasInstructionCard3) {
                $card3Path = $instructionCardBasePath . $card3FileName;
                
                if (file_exists($card3Path)) {
                    if ($this->imageProcessingService->safeConvertImage($card3Path, $monochromeBasePath, 800, 1200)) {
                        $monochromeImagePath = $monochromeBasePath . $card3FileName;
                        $zplIC .= $this->imageProcessingService->convertImageLayout($monochromeImagePath, $asinfind, $basketnumber);
                        Log::info('Successfully processed card 3 for married printer');
                    }
                } else {
                    Log::warning('Card 3 file not found for married printer', ['path' => $card3Path]);
                    $zplIC .= "^XA^FO50,50^ADN,18,18^FDCard 3 not found: " . $card3FileName . "^FS^XZ";
                }
            }
            
            // Process serial-specific warranty cards if serial number exists
            if (!empty($Wserial)) {
                $serialCard1FileName = $Wserial . "_page_1.png";
                $serialCard2FileName = $Wserial . "_page_2.png";
                
                $templatePath1 = public_path('images/warranty/templates/6_1st.png');
                $templatePath2 = public_path('images/warranty/templates/6_2nd.png');
                $generatedImagesPath = storage_path('app/public/images/warranty/generated_images/');
                
                $serialCard1Path = $generatedImagesPath . $serialCard1FileName;
                $serialCard2Path = $generatedImagesPath . $serialCard2FileName;
                
             //   if (!file_exists($serialCard1Path) || !file_exists($serialCard2Path)) {
                    $this->imageProcessingService->generateSerialImagesFromTemplates($Wserial, $templatePath1, $templatePath2);
            //    }
                
                // Process serial cards
                if (file_exists($serialCard1Path)) {
                    if ($this->imageProcessingService->safeConvertImage($serialCard1Path, $monochromeBasePath, 800, 1200)) {
                        $monochromeImagePath = $monochromeBasePath . $serialCard1FileName;
                        $zplIC .= $this->imageProcessingService->convertImageLayout($monochromeImagePath, $asinfind, $basketnumber);
                    }
                }
                
                if (file_exists($serialCard2Path)) {
                    if ($this->imageProcessingService->safeConvertImage($serialCard2Path, $monochromeBasePath, 800, 1200)) {
                        $monochromeImagePath = $monochromeBasePath . $serialCard2FileName;
                        $zplIC .= $this->imageProcessingService->convertImageLayout($monochromeImagePath, $asinfind, $basketnumber);
                    }
                }

                     $zplIC .= $this->imageProcessingService->generateQRforInstructionCard($Wserial);
            }
            
            Log::info('Final instruction card ZPL for married printer:', ['length' => strlen($zplIC)]);
            return $zplIC;
            
        } catch (Exception $e) {
            Log::error('Error generating instruction card labels for married printer:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'product_id' => $product->ProductID ?? 'unknown'
            ]);
            
            return "^XA^FO50,50^ADN,18,18^FDError processing instruction cards^FS^XZ";
        }
    }

    /**
     * Process vector image - EXACT replication from original with proper Laravel paths
     */
    protected function processVectorImage($vectorImage)
    {
        try {
            $filename = basename($vectorImage);
            $inputPath = public_path('images/asinvectorsimg/' . $filename);
            $outputPath = storage_path('app/public/images/monochrome');
            $newWidth = 400;
            $newHeight = 300;
            
            $success = $this->imageProcessingService->convertImageToMonochrome($inputPath, $outputPath, $newWidth, $newHeight);
            
            if ($success) {
                $monochromeImagePath = $outputPath . '/' . $filename;
                return $this->imageProcessingService->convertMonochromeImageToZPL($monochromeImagePath);
            } else {
                return "^XA^FO50,50^ADN,18,18^FDVector image processing failed^FS^XZ";
            }
            
        } catch (Exception $e) {
            Log::error('Error processing vector image:', [
                'error' => $e->getMessage(),
                'vectorImage' => $vectorImage
            ]);
            
            return "^XA^FO50,50^ADN,18,18^FDError processing vector image^FS^XZ";
        }
    }

    /**
     * Test print functionality with enhanced printer support
     */
    public function testPrint($username, $selectedPrinter = null)
    {
        try {
            // Generate a simple test ZPL
            $testZpl = $this->generateTestZpl($username, $selectedPrinter);
            
            // Send to printer
            $printerIp = $selectedPrinter ? $selectedPrinter->printerip : $this->printerIp;
            $result = $this->sendToPrinter($testZpl, $printerIp);
            
            return $result;
            
        } catch (Exception $e) {
            Log::error('Error in test print:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Error testing printer: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate test ZPL code
     */
    protected function generateTestZpl($username, $selectedPrinter = null)
    {
        $dateTime = $this->getCurrentDateTime();
        $printerName = $selectedPrinter ? $selectedPrinter->printername : 'Default Printer';
        
        return "^XA" .
               "^FO50,50^FB400,1,0,C^ADN,24,24^FDTest Print^FS" .
               "^FO50,100^FB400,1,0,C^ADN,18,18^FDPrinter: " . $printerName . "^FS" .
               "^FO50,130^FB400,1,0,C^ADN,18,18^FDPrinted by: " . $username . "^FS" .
               "^FO50,160^FB400,1,0,C^ADN,14,14^FD" . $dateTime . "^FS" .
               "^FO50,200^FB400,1,0,C^ADN,12,12^FDPrinter test successful^FS" .
               "^XZ";
    }
    
    /**
     * Get the number of times each serial number has been returned - EXACT replication from original
     */
    protected function getReturnCounts($product)
    {
        try {
            $serialFields = [
                'a' => $product->serialnumber ?? null,
                'b' => $product->serialnumberb ?? null,
                'c' => $product->serialnumberc ?? null,
                'd' => $product->serialnumberd ?? null
            ];
            
            $returnCounts = [];
            
            foreach ($serialFields as $key => $serial) {
                if (empty($serial)) {
                    $returnCounts[$key] = 0;
                    continue;
                }
                
                $count = DB::table($this->productTable)
                    ->where(function ($query) use ($serial) {
                        $query->where('serialnumber', $serial)
                            ->orWhere('serialnumberb', $serial)
                            ->orWhere('serialnumberc', $serial)
                            ->orWhere('serialnumberd', $serial);
                    })
                    ->where('returnstatus', 'Returned')
                    ->count();
                    
                $returnCounts[$key] = $count;
            }
            
            return $returnCounts;
            
        } catch (Exception $e) {
            Log::warning('Error getting return counts:', [
                'error' => $e->getMessage(),
                'productId' => $product->ProductID ?? 'unknown'
            ]);
            
            return ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0];
        }
    }
    
    /**
     * Format the condition text based on store and grading - EXACT replication from original
     */
    protected function formatCondition($grading, $storeName, $asin, $asinStatus = null)
    {
        try {
            $isAllRenewed = (stripos($storeName, 'Allrenewed') !== false || 
                              stripos($storeName, 'All renewed') !== false ||
                              stripos($storeName, 'All Renewed') !== false);
            
            switch ($grading) {
                case 'UsedLikeNew':
                    return 'Used - Like New ';
                    
                case 'UsedVeryGood':
                    if ($isAllRenewed) {
                        return 'Refurbished - Excellent';
                    } else {
                        return 'Used - Very Good ';
                    }
                    
                case 'UsedGood':
                    if ($isAllRenewed) {
                        return 'Refurbished - Good';
                    } else {
                        return 'Used - Good';
                    }
                    
                case 'UsedAcceptable':
                    if ($isAllRenewed) {
                        return 'Refurbished - Acceptable';
                    } else {
                        return 'Used - Acceptable';
                    }
                    
                case 'New':
                    if ($isAllRenewed && $asin) {
                        if (strtolower($asinStatus) === 'renewed') {
                            return 'Refurbished - Excellent';
                        }
                    }
                    return $grading;
                    
                default:
                    return $grading ?: 'Unknown';
            }
            
        } catch (Exception $e) {
            Log::warning('Error formatting condition:', [
                'error' => $e->getMessage(),
                'grading' => $grading,
                'storeName' => $storeName
            ]);
            
            return $grading ?: 'Unknown';
        }
    }
    
    /**
     * Send ZPL code to printer - UPDATED to accept custom printer IP
     */
    protected function sendToPrinter($zpl, $printerIp = null)
    {
        try {
            $targetPrinterIp = $printerIp ?: $this->printerIp;
            
            Log::info('Sending print job to printer:', [
                'printer_ip' => $targetPrinterIp,
                'server_url' => $this->printServerUrl,
                'zpl_length' => strlen($zpl)
            ]);
            
            $postData = http_build_query([
                'zpl' => $zpl,
                'printerSelect' => $targetPrinterIp
            ]);
            
            $ch = curl_init($this->printServerUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            Log::info('Print server response:', [
                'response' => $response,
                'status' => $status,
                'error' => $error,
                'printer_ip' => $targetPrinterIp
            ]);
            
            if ($response === "Message sent to printer successfully." || $status === 200) {
                return [
                    'status' => 'success',
                    'message' => 'Label printed successfully to printer ' . $targetPrinterIp
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to print label to printer ' . $targetPrinterIp . ': ' . ($response ?: $error)
                ];
            }
            
        } catch (Exception $e) {
            Log::error('Error sending to printer:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'printer_ip' => $printerIp ?: $this->printerIp
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Error sending to printer: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * DEPRECATED: Send ZPL code to instruction card printer 
     * This method is kept for backward compatibility but is replaced by the married printer system
     */
    protected function sendToInstructionCardPrinter($zpl)
    {
        return $this->sendToPrinter($zpl, $this->instructionCardPrinterIp);
    }
    
    /**
     * Get current date and time in a formatted string
     */
    protected function getCurrentDateTime()
    {
        try {
            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            return $currentDatetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            Log::warning('Error with timezone, using default', ['error' => $e->getMessage()]);
            $currentDatetime = new DateTime();
            return $currentDatetime->format('Y-m-d H:i:s');
        }
    }
}