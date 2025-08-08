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
     * Generate and print a label for a product
     * UPDATED to use base FNSKU for database lookups while preserving display FNSKU
     */
    public function printLabel($productId, $username, $selectedPrinter = null)
    {
        try {
            // Set printer IP dynamically if provided
            if ($selectedPrinter && isset($selectedPrinter->printerip)) {
                $this->printerIp = $selectedPrinter->printerip;
                Log::info('Using selected printer:', [
                    'printer_name' => $selectedPrinter->printername,
                    'printer_ip' => $selectedPrinter->printerip
                ]);
            }

            // UPDATED: Get product first, then match FNSKU data using base FNSKU
            $product = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->where('returnstatus', 'Not Returned')
                ->where('ProductModuleLoc', '!=', 'Migrated')
                ->where('validation_status', 'validated')
                ->orderBy('ProductID', 'desc')
                ->first();

            if (!$product) {
                return [
                    'status' => 'error',
                    'message' => 'Product not found or not validated'
                ];
            }

            // UPDATED: Extract base FNSKU and get related data
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

            // UPDATED: Combine data properly
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

            // Get return counts for all serials
            $returnCounts = $this->getReturnCounts($enrichedProduct);
            
            // Format condition
            $condition = $this->formatCondition(
                $enrichedProduct->gradingviewer ?? '', 
                $enrichedProduct->StoreName ?? '', 
                $enrichedProduct->ASINviewer ?? '', 
                $enrichedProduct->asinStatus ?? ''
            );
            
            // Generate ZPL code with all functions - COMPLETE VERSION
            $zplData = $this->generateCompleteZplCode($enrichedProduct, $condition, $returnCounts, $username);
            
            // Separate main ZPL from instruction card ZPL
            $mainZpl = $zplData['mainZpl'];
            $instructionCardZpl = $zplData['instructionCardZpl'];
            
            // Send main labels to selected printer
            $result = $this->sendToPrinter($mainZpl);
            
            // Send instruction cards to dedicated printer if available
            if (!empty($instructionCardZpl)) {
                Log::info('Sending instruction cards to printer', [
                    'zpl_length' => strlen($instructionCardZpl),
                    'printer_ip' => $this->instructionCardPrinterIp
                ]);
                
                $instructionCardResult = $this->sendToInstructionCardPrinter($instructionCardZpl);
                
                // Update result message to include instruction card printing status
                if ($result['status'] === 'success' && $instructionCardResult['status'] === 'success') {
                    $result['message'] = 'Printing All Labels...';
                } else if ($result['status'] === 'success') {
                    $result['message'] = 'Printing Small labels only...';
                }
                
                Log::info('Instruction card result:', $instructionCardResult);
            } else {
                Log::info('No instruction cards to print - ZPL is empty');
            }
            
            // Update print count if successful
            if ($result['status'] === 'success') {
                $currentPrintCount = $product->printCount ?? 0;
                $newPrintCount = $currentPrintCount + 1;
                
                DB::table($this->productTable)
                    ->where('ProductID', $productId)
                    ->update([
                        'printCount' => $newPrintCount,
                        'printby' => $username
                    ]);
                
                // Log the printing activity
                if (isset($this->itemProcessHistoryTable) && 
                    DB::getSchemaBuilder()->hasTable($this->itemProcessHistoryTable)) {
                    
                    $printerInfo = $selectedPrinter ? $selectedPrinter->printername : 'Default Printer';
                    
                    DB::table($this->itemProcessHistoryTable)->insert([
                        'rtcounter' => $product->rtcounter,
                        'employeeName' => $username,
                        'editDate' => $this->getCurrentDateTime(),
                        'Module' => 'Label Printing',
                        'Action' => 'Label printed for ' . ($product->FNSKUviewer ?? 'unknown FNSKU') . ' on ' . $printerInfo
                    ]);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            Log::error('Error in printLabel service:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'productId' => $productId
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Error printing label: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reprint a single label type for a product
     * UPDATED to use base FNSKU for database lookups
     */
    public function reprintSingleLabel($productId, $labelType, $username, $selectedPrinter = null)
    {
        try {
            // Set printer IP dynamically if provided
            if ($selectedPrinter && isset($selectedPrinter->printerip)) {
                $this->printerIp = $selectedPrinter->printerip;
                Log::info('Using selected printer for reprint:', [
                    'printer_name' => $selectedPrinter->printername,
                    'printer_ip' => $selectedPrinter->printerip
                ]);
            }

            // UPDATED: Get product first, then match FNSKU data using base FNSKU
            $product = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->first();

            if (!$product) {
                return [
                    'status' => 'error',
                    'message' => 'Product not found'
                ];
            }

            // UPDATED: Extract base FNSKU and get related data
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

            // UPDATED: Combine data properly
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

            // Get return counts for all serials
            $returnCounts = $this->getReturnCounts($enrichedProduct);
            
            // Format condition
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
            
            // Send to appropriate printer based on label type
            if ($labelType === 'instruction_cards') {
                $result = $this->sendToInstructionCardPrinter($zpl);
            } else {
                $result = $this->sendToPrinter($zpl);
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
     * Generate ZPL for a specific label type
     * NEW METHOD to support individual label reprinting
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
     * Generate all serial labels for reprint
     * Helper method for serial label reprinting
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
     * Generate complete ZPL code with all label functions - COMPLETE VERSION
     * This integrates ALL functions from the original PHP code
     * Returns separate main ZPL and instruction card ZPL
     */
    protected function generateCompleteZplCode($product, $condition, $returnCounts, $username)
    {
        try {
            $zpl = '';
            $qrzpl = '';
            $zplIC = '';
            $nonNullCount = 0;
            $isRenewed = ($condition === 'Refurbished - Excellent');
            $printmanual = false;
            
            // Initial dividers and renewed header
            if (!empty($product->ProductID)) {
                $nonNullCount++;
                $zpl .= "^XA^FO40,120^ADN,36,20^FW^FD--- DIVIDER ---^FS^XZ";
                $zpl .= "^XA^FO40,120^ADN,36,20^FW^FD--- DIVIDER ---^FS^XZ";
                
                if ($isRenewed) {
                    $zpl .= "^XA^FO5,70^ADN,200,42^FW^FD RENEWED ^FS^XZ";
                }
            }

            // Serial number labels (A and B) - EXACT replication from original
            if (!empty($product->serialnumber) && !empty($product->serialnumberb)) {
                $nonNullCount++;
                $zpl .= $this->generateDualSerialLabels($product->serialnumber, $product->serialnumberb, $condition);
            } else if (!empty($product->serialnumber)) {
                $nonNullCount++;
                $returnInfo = "R:" . ($returnCounts['a'] ?? 0) . " ";
                $zpl .= $this->generateSingleSerialLabels($product->serialnumber, $condition, $returnInfo);
            }

            // Serial number labels (C and D) - EXACT replication from original
            if (!empty($product->serialnumberc) && !empty($product->serialnumberd)) {
                $nonNullCount++;
                $zpl .= $this->generateDualSerialLabels($product->serialnumberc, $product->serialnumberd, $condition);
            } else if (!empty($product->serialnumberc) && empty($product->serialnumberd)) {
                $nonNullCount++;
                $zpl .= $this->generateSingleSerialLabels($product->serialnumberc, $condition, "");
            }

            // FNSKU label with special prefix handling - EXACT replication
            if (!empty($product->FNSKUviewer)) {
                $nonNullCount++;
                $zpl .= $this->generateFnskuLabel($product, $condition);
            }

            // Title label with RT/AR package number - EXACT replication
            if (!empty($product->AStitle)) {
                $nonNullCount++;
                $zpl .= $this->generateTitleLabel($product);
            }

            // Vector image processing - EXACT replication
            if (!empty($product->ASINviewer) && !empty($product->vectorimage)) {
                $nonNullCount++;
                $zpl .= $this->processVectorImage($product->vectorimage);
            }

            // Item number label - EXACT replication
            if (!empty($product->itemnumber)) {
                $nonNullCount++;
                $zpl .= $this->generateItemNumberLabel($product);
            }

            // Timestamp and priority label - EXACT replication
            if (!empty($product->rtcounter)) {
                $nonNullCount++;
                $zpl .= $this->generateTimestampLabel($product, $username);
            }

            // Sticker notes with word wrapping - EXACT replication
            if (!empty($product->stickernote)) {
                $nonNullCount++;
                $zpl .= $this->generateStickerNoteLabel($product->stickernote, $product->mID ?? 0);
            }

            // Item status (Not Working) - EXACT replication
            if (isset($product->itemstatus) && $product->itemstatus === 'Not Working') {
                $nonNullCount++;
                $zpl .= $this->generateItemStatusLabel($product->itemstatus);
            }

            // Notes label - EXACT replication
            if (!empty($product->notes)) {
                $nonNullCount++;
                $zpl .= $this->generateNotesLabel($product->notes);
            }

            // Transparency QR status - EXACT replication
            if (!empty($product->ASINviewer) && !empty($product->TRANSPARENCY_QR_STATUS)) {
                $nonNullCount++;
                $zpl .= $this->generateTransparencyQRLabel($product->TRANSPARENCY_QR_STATUS);
            }

            // QR codes for manual and serial - EXACT replication from original
            if (!empty($product->ASINviewer)) {
                $zpl .= $this->imageProcessingService->convertImageQRmanual($product->ASINviewer, $product->AStitle ?? '');
            }

            if (!empty($product->serialnumber)) {
                $nonNullCount++;
                $zpl .= $this->imageProcessingService->convertImageQRserial($product->serialnumber);
            }

            // Print count - EXACT replication
            if (isset($product->printCount) && $product->printCount > 0) {
                $nonNullCount++;
                $zpl .= $this->generatePrintCountLabel($product->printCount + 1);
            }

            // Check for dogpage input - from original
            if (isset($_POST['dogpageInput']) && $_POST['dogpageInput'] === 'dogpage') {
                $nonNullCount++;
                $zpl .= "^XA^FO5,70^ADN,200,42^FW^FD DOGPAGE ^FS^XZ";
            }

            // Additional renewed labels - from original
            if (!empty($product->ProductID) && $isRenewed) {
                $nonNullCount++;
                $zpl .= "^XA^FO5,70^ADN,200,42^FW^FD RENEWED ^FS^XZ";
                $zpl .= "^XA^FO5,70^ADN,200,42^FW^FD RENEWED ^FS^XZ";
            }

            // Warehouse location - EXACT replication
            if (!empty($product->warehouselocation)) {
                $nonNullCount++;
                $zpl .= $this->generateWarehouseLocationLabel($product->warehouselocation);
            }

            // RTS (Return to Seller) handling - EXACT replication
            if (isset($product->ProductModuleLoc) && $product->ProductModuleLoc === 'RTS') {
                $nonNullCount++;
                $zpl .= $this->generateRTSLabel($product);
            }

            // RT/AR counter with barcode - EXACT replication
            if (!empty($product->rtcounter)) {
                $nonNullCount++;
                $zpl .= $this->generateRTARCounterLabel($product, $condition);
            }

            // Validation status - EXACT replication
            if (isset($product->validation_status) && in_array($product->validation_status, ['invalid', 'unvalidated'])) {
                $zpl .= $this->generateValidationStatusLabel($product->validation_status);
            }

            // Instruction card processing - Generate separately for different printer
            if (!empty($product->ASINviewer)) {
                Log::info('Checking instruction cards for ASIN:', [
                    'asin' => $product->ASINviewer,
                    'instructioncard' => $product->instructioncard ?? 'null',
                    'instructioncard2' => $product->instructioncard2 ?? 'null',
                    'instructioncard3' => $product->instructioncard3 ?? 'null'
                ]);
                
                Log::info('About to call generateInstructionCardLabels...');
                $zplIC = $this->generateInstructionCardLabels($product);
                Log::info('Returned from generateInstructionCardLabels', ['zpl_length' => strlen($zplIC)]);
                
                if (!empty($zplIC)) {
                    Log::info('Generated instruction card ZPL', ['length' => strlen($zplIC)]);
                } else {
                    Log::info('No instruction card ZPL generated - investigating why...');
                    Log::info('Product data for debugging:', [
                        'ASINviewer' => $product->ASINviewer,
                        'basketnumber' => $product->basketnumber ?? 'null',
                        'serialnumber' => $product->serialnumber ?? 'null',
                        'instructioncard_type' => gettype($product->instructioncard),
                        'instructioncard2_type' => gettype($product->instructioncard2),
                        'instructioncard3_type' => gettype($product->instructioncard3)
                    ]);
                }
            } else {
                Log::info('No ASIN found - skipping instruction cards');
                $zplIC = '';
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
        
        // Generate 3 copies - EXACT replication
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
        
        // Generate 3 copies - EXACT replication
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
     * Generate FNSKU label with special handling for prefixes - EXACT replication from original
     */
    protected function generateFnskuLabel($product, $condition)
    {
        $fnsku = $product->FNSKUviewer;
        $asin = $product->ASINviewer;
        $title = $product->AStitle ?? '';
        $subvariant = $product->subvariant ?? '';
        
        $zpl = "^XA";
        
        // Check if FNSKU equals ASIN - EXACT replication
        if ($fnsku == $asin) {
            $zpl .= "^FO55,30^FB400,2,0,C^AON,24,24^BCN,100,N,N,N,A^FD" . $fnsku . "^FS";
            $zpl .= "^FO10,140^FB400,1,0,C^ADN,24,24^FD" . $fnsku . "^FS";
            $zpl .= "^FO30,170^FB400,10,0^AON,17,10^FD" . $condition . "- " . $title . "^FS";
            if (!empty($subvariant)) {
                $zpl .= "^FO30,220^FB400,10,0^AON,17,10^FD " . $subvariant . "^FS";
            }
        } else {
            $prefix = substr($fnsku, 0, 2);
            
            // Check if prefix matches B-W[0-9] pattern - EXACT replication
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
        
        // Add subvariant for SONOS products - EXACT replication
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
        
        // Username with dynamic font size - EXACT replication
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
        
        // Check if merged item - EXACT replication
        if ($checkmid > 0) {
            $zpl .= "^FO5,40^ADN,1,1^FW^FDMerged RT#^FS";
            $y_position = 70;
        } else {
            $y_position = 50;
        }
        
        // Split the stickernote by line breaks - EXACT replication
        $stickernote_parts = explode("\n", $stickerNote);
        $line_spacing = 30;
        $text_width = 180;
        $font_height = 20;
        $font_width = 20;
        $char_width = $font_height / 2;
        
        // EXACT replication of word wrapping algorithm from original
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
        
        // Get test result details - EXACT replication from original
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
     * Generate instruction card labels - FIXED to handle filename strings
     * Based on ASIN naming convention: {ASIN}_card1.png, {ASIN}_card2.png, {ASIN}_card3.png
     */
    protected function generateInstructionCardLabels($product)
    {
        try {
            $zplIC = '';
            $asinfind = $product->ASINviewer ?? '';
            $basketnumber = $product->basketnumber ?? '';
            $Wserial = $product->serialnumber ?? '';
            
            // FIXED: Check if we have instruction card data - handle both filename strings and boolean values
            $hasInstructionCard1 = !empty($product->instructioncard) && 
                ($product->instructioncard == 1 || !is_numeric($product->instructioncard));
            $hasInstructionCard2 = !empty($product->instructioncard2) && 
                ($product->instructioncard2 == 1 || !is_numeric($product->instructioncard2));
            $hasInstructionCard3 = !empty($product->instructioncard3) && 
                ($product->instructioncard3 == 1 || !is_numeric($product->instructioncard3));
            
            if (!$hasInstructionCard1 && !$hasInstructionCard2 && !$hasInstructionCard3) {
                // No instruction cards to process
                Log::info('No instruction cards to process - all conditions false');
                return '';
            }
            
            Log::info('Processing instruction cards:', [
                'asin' => $asinfind,
                'card1' => $hasInstructionCard1 ? 'yes' : 'no',
                'card2' => $hasInstructionCard2 ? 'yes' : 'no',
                'card3' => $hasInstructionCard3 ? 'yes' : 'no',
                'serial' => $Wserial,
                'card1_value' => $product->instructioncard ?? 'null',
                'card2_value' => $product->instructioncard2 ?? 'null',
                'card3_value' => $product->instructioncard3 ?? 'null'
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
                
                Log::info('Checking card 1 path:', ['path' => $card1Path, 'exists' => file_exists($card1Path)]);
                
                if (file_exists($card1Path)) {
                    $newWidth = 800;
                    $newHeight = 1200;
                    
                    if ($this->imageProcessingService->safeConvertImage($card1Path, $monochromeBasePath, $newWidth, $newHeight)) {
                        $monochromeImagePath = $monochromeBasePath . $card1FileName;
                        $zplIC .= $this->imageProcessingService->enhanceAndConvertToZPL($monochromeImagePath, $asinfind, $basketnumber);
                        
                        Log::info('Successfully processed card 1', ['file' => $card1FileName]);
                    } else {
                        Log::warning('Failed to convert card 1 to monochrome', ['file' => $card1FileName]);
                    }
                } else {
                    Log::warning('Card 1 file not found', [
                        'expected_path' => $card1Path,
                        'asin' => $asinfind
                    ]);
                    
                    // Add error label to ZPL
                    $zplIC .= "^XA^FO50,50^ADN,18,18^FDCard 1 not found: " . $card1FileName . "^FS^XZ";
                }
            }
            
            // Process Card 2 if enabled
            if ($hasInstructionCard2) {
                $card2Path = $instructionCardBasePath . $card2FileName;
                
                Log::info('Checking card 2 path:', ['path' => $card2Path, 'exists' => file_exists($card2Path)]);
                
                if (file_exists($card2Path)) {
                    $newWidth = 800;
                    $newHeight = 1200;
                    
                    if ($this->imageProcessingService->safeConvertImage($card2Path, $monochromeBasePath, $newWidth, $newHeight)) {
                        $monochromeImagePath = $monochromeBasePath . $card2FileName;
                        $zplIC .= $this->imageProcessingService->enhanceAndConvertToZPL($monochromeImagePath, $asinfind, $basketnumber);
                        
                        Log::info('Successfully processed card 2', ['file' => $card2FileName]);
                    } else {
                        Log::warning('Failed to convert card 2 to monochrome', ['file' => $card2FileName]);
                    }
                } else {
                    Log::warning('Card 2 file not found', [
                        'expected_path' => $card2Path,
                        'asin' => $asinfind
                    ]);
                    
                    // Add error label to ZPL
                    $zplIC .= "^XA^FO50,50^ADN,18,18^FDCard 2 not found: " . $card2FileName . "^FS^XZ";
                }
            }
            
            // Process Card 3 if enabled - EXACT replication from original with convertImageLayout
            if ($hasInstructionCard3) {
                $card3Path = $instructionCardBasePath . $card3FileName;
                
                Log::info('Checking card 3 path:', ['path' => $card3Path, 'exists' => file_exists($card3Path)]);
                
                if (file_exists($card3Path)) {
                    $newWidth = 800;
                    $newHeight = 1200;
                    
                    if ($this->imageProcessingService->safeConvertImage($card3Path, $monochromeBasePath, $newWidth, $newHeight)) {
                        $monochromeImagePath = $monochromeBasePath . $card3FileName;
                        $zplIC .= $this->imageProcessingService->convertImageLayout($monochromeImagePath, $asinfind, $basketnumber);
                        
                        Log::info('Successfully processed card 3', ['file' => $card3FileName]);
                    } else {
                        Log::warning('Failed to convert card 3 to monochrome', ['file' => $card3FileName]);
                    }
                } else {
                    Log::warning('Card 3 file not found', [
                        'expected_path' => $card3Path,
                        'asin' => $asinfind
                    ]);
                    
                    // Add error label to ZPL
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
                
                // Generate serial-specific images from templates if they don't exist
                $serialCard1Path = $generatedImagesPath . $serialCard1FileName;
                $serialCard2Path = $generatedImagesPath . $serialCard2FileName;
                
                if (!file_exists($serialCard1Path) || !file_exists($serialCard2Path)) {
                    Log::info('Generating serial-specific warranty cards', ['serial' => $Wserial]);
                    $this->imageProcessingService->generateSerialImagesFromTemplates($Wserial, $templatePath1, $templatePath2);
                }
                
                // Process serial card 1
                if (file_exists($serialCard1Path)) {
                    $newWidth = 800;
                    $newHeight = 1200;
                    
                    if ($this->imageProcessingService->safeConvertImage($serialCard1Path, $monochromeBasePath, $newWidth, $newHeight)) {
                        $monochromeImagePath = $monochromeBasePath . $serialCard1FileName;
                        $zplIC .= $this->imageProcessingService->convertImageLayout($monochromeImagePath, $asinfind, $basketnumber);
                        
                        Log::info('Successfully processed serial card 1', ['file' => $serialCard1FileName]);
                    }
                }
                
                // Process serial card 2
                if (file_exists($serialCard2Path)) {
                    $newWidth = 800;
                    $newHeight = 1200;
                    
                    if ($this->imageProcessingService->safeConvertImage($serialCard2Path, $monochromeBasePath, $newWidth, $newHeight)) {
                        $monochromeImagePath = $monochromeBasePath . $serialCard2FileName;
                        $zplIC .= $this->imageProcessingService->convertImageLayout($monochromeImagePath, $asinfind, $basketnumber);
                        
                        Log::info('Successfully processed serial card 2', ['file' => $serialCard2FileName]);
                    }
                }
            }
            
            Log::info('Final instruction card ZPL length:', ['length' => strlen($zplIC)]);
            return $zplIC;
            
        } catch (Exception $e) {
            Log::error('Error generating instruction card labels:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'product_id' => $product->ProductID ?? 'unknown',
                'asin' => $product->ASINviewer ?? 'unknown'
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
            // Handle both just filename and path with directories
            $filename = basename($vectorImage);
            $inputPath = public_path('images/asinvectorsimg/' . $filename);
            $outputPath = storage_path('app/public/images/monochrome');
            $newWidth = 400;
            $newHeight = 300;
            
            // Convert image to monochrome
            $success = $this->imageProcessingService->convertImageToMonochrome($inputPath, $outputPath, $newWidth, $newHeight);
            
            if ($success) {
                // Use just the filename for the monochrome path
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
     * Test print functionality with selected printer
     */
    public function testPrint($username, $selectedPrinter = null)
    {
        try {
            // Set printer IP dynamically if provided
            if ($selectedPrinter && isset($selectedPrinter->printerip)) {
                $this->printerIp = $selectedPrinter->printerip;
            }

            // Generate a simple test ZPL
            $testZpl = $this->generateTestZpl($username, $selectedPrinter);
            
            // Send to printer
            $result = $this->sendToPrinter($testZpl);
            
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
                
                // EXACT replication of the original return count query
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
            
            // EXACT replication of the original condition formatting logic
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
                        // Check ASIN status from database - EXACT replication
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
     * Send ZPL code to printer - EXACT replication from original
     */
    protected function sendToPrinter($zpl)
    {
        try {
            Log::info('Sending print job to printer:', [
                'printer_ip' => $this->printerIp,
                'server_url' => $this->printServerUrl
            ]);
            
            // EXACT replication of the original POST data structure
            $postData = http_build_query([
                'zpl' => $zpl,
                'printerSelect' => $this->printerIp
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
                'printer_ip' => $this->printerIp
            ]);
            
            // EXACT replication of the original success condition
            if ($response === "Message sent to printer successfully." || $status === 200) {
                return [
                    'status' => 'success',
                    'message' => 'Label printed successfully to printer ' . $this->printerIp
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to print label to printer ' . $this->printerIp . ': ' . ($response ?: $error)
                ];
            }
            
        } catch (Exception $e) {
            Log::error('Error sending to printer:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'printer_ip' => $this->printerIp
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Error sending to printer ' . $this->printerIp . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send ZPL code to instruction card printer - Dedicated printer for instruction cards
     */
    protected function sendToInstructionCardPrinter($zpl)
    {
        try {
            Log::info('Sending instruction card print job to printer:', [
                'printer_ip' => $this->instructionCardPrinterIp,
                'server_url' => $this->printServerUrl
            ]);
            
            // Send to dedicated instruction card printer
            $postData = http_build_query([
                'zpl' => $zpl,
                'printerSelect' => $this->instructionCardPrinterIp
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
            
            Log::info('Instruction card printer response:', [
                'response' => $response,
                'status' => $status,
                'error' => $error,
                'printer_ip' => $this->instructionCardPrinterIp
            ]);
            
            if ($response === "Message sent to printer successfully." || $status === 200) {
                return [
                    'status' => 'success',
                    'message' => 'Instruction cards printed successfully to printer ' . $this->instructionCardPrinterIp
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to print instruction cards to printer ' . $this->instructionCardPrinterIp . ': ' . ($response ?: $error)
                ];
            }
            
        } catch (Exception $e) {
            Log::error('Error sending instruction cards to printer:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'printer_ip' => $this->instructionCardPrinterIp
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Error sending instruction cards to printer ' . $this->instructionCardPrinterIp . ': ' . $e->getMessage()
            ];
        }
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