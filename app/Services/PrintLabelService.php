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
    protected $imageProcessingService;

    public function __construct()
    {
        parent::__construct();
        
        // Set printer settings - these should be configurable
        $this->printerIp = config('app.printer_ip', '192.168.1.109');
        $this->printServerUrl = config('app.print_server_url', 'http://99.0.87.190:1450/ims/Admin/modules/PRD-RPN-PCN/print.php');
        $this->imageProcessingService = new ImageProcessingService();
    }

    /**
     * Generate and print a label for a product
     * UPDATED to work with dynamic printer selection
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

            // Get product with proper joins to get all needed data
            $product = DB::table($this->productTable . ' as prod')
                ->leftJoin($this->fnskuTable . ' as fnsku', 'prod.FNSKUviewer', '=', 'fnsku.FNSKU')
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->select([
                    'prod.*',
                    'fnsku.ASIN as ASINviewer',
                    'fnsku.grading as gradingviewer',
                    'fnsku.storename as StoreName',
                    'asin.internal as AStitle',
                    'asin.asinStatus',
                    'asin.TRANSPARENCY_QR_STATUS',
                    'asin.vectorimage',
                    'asin.card_id'
                ])
                ->where('prod.ProductID', $productId)
                ->where('prod.returnstatus', 'Not Returned')
                ->where('prod.ProductModuleLoc', '!=', 'Migrated')
                ->where('prod.validation_status', 'validated')
                ->orderBy('prod.ProductID', 'desc')
                ->first();

            if (!$product) {
                return [
                    'status' => 'error',
                    'message' => 'Product not found or not validated'
                ];
            }

            // Get return counts for all serials
            $returnCounts = $this->getReturnCounts($product);
            
            // Format condition
            $condition = $this->formatCondition($product->gradingviewer, $product->StoreName, $product->ASINviewer, $product->asinStatus);
            
            // Generate ZPL code with all functions - COMPLETE VERSION
            $zpl = $this->generateCompleteZplCode($product, $condition, $returnCounts, $username);
            
            // Send to printer
            $result = $this->sendToPrinter($zpl);
            
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
     * Generate complete ZPL code with all label functions - COMPLETE VERSION
     * This integrates ALL functions from the original PHP code
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

            // Instruction card processing - EXACT replication from original
            if (!empty($product->ASINviewer) && !empty($product->card_id)) {
                $nonNullCount++;
                $zplIC .= $this->generateInstructionCardLabels($product);
                $zpl .= $zplIC; // Add instruction card ZPL to main ZPL
            }

            return $zpl;
            
        } catch (Exception $e) {
            Log::error('Error generating complete ZPL code:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return "^XA^FO50,50^ADN,18,18^FDError generating complete label^FS^XZ";
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
     * Generate instruction card labels - EXACT replication from original with complete logic
     */
    protected function generateInstructionCardLabels($product)
    {
        try {
            $zplIC = '';
            
            // Get instruction card details - EXACT replication
            $instructionCard = DB::table('tblinstructioncard')
                ->where('id', $product->card_id)
                ->first();
            
            if ($instructionCard) {
                $Page1 = $instructionCard->page1 ?? '';
                $Page2 = $instructionCard->page2 ?? '';
                $Page3 = $instructionCard->page3 ?? '';
                $Wserial = $product->serialnumber ?? '';
                $Page4 = $Wserial . "_page_1.png";
                $Page5 = $Wserial . "_page_2.png";
                $asinfind = $product->ASINviewer ?? '';
                $basketnumber = $product->basketnumber ?? '';
                
                // Generate serial-specific images from templates if they don't exist
                if (!empty($Wserial)) {
                    $templatePath1 = storage_path('app/public/instructioncard/warranty/templates/6_1st.png');
                    $templatePath2 = storage_path('app/public/instructioncard/warranty/templates/6_2nd.png');
                    
                    // Check if serial pages exist, if not generate them
                    if (!file_exists(storage_path('app/public/instructioncard/generated_images/' . $Page4)) || 
                        !file_exists(storage_path('app/public/instructioncard/generated_images/' . $Page5))) {
                        
                        $this->imageProcessingService->generateSerialImagesFromTemplates($Wserial, $templatePath1, $templatePath2);
                    }
                }
                
                // Process ASIN pages - EXACT replication
                if (!empty($Page1)) {
                    $inputPath = storage_path('app/public/instructioncard/' . $Page1);
                    $outputPath = storage_path('app/public/images/monochrome/');
                    $newWidth = 800;
                    $newHeight = 1200;
                    
                    if ($this->imageProcessingService->safeConvertImage($inputPath, $outputPath, $newWidth, $newHeight)) {
                        $monochromeImagePath = storage_path('app/public/images/monochrome/' . $Page1);
                        $zplIC .= $this->imageProcessingService->enhanceAndConvertToZPL($monochromeImagePath, $asinfind, $basketnumber);
                    }
                }
                
                if (!empty($Page2)) {
                    $inputPath = storage_path('app/public/instructioncard/' . $Page2);
                    $outputPath = storage_path('app/public/images/monochrome/');
                    $newWidth = 800;
                    $newHeight = 1200;
                    
                    if ($this->imageProcessingService->safeConvertImage($inputPath, $outputPath, $newWidth, $newHeight)) {
                        $monochromeImagePath = storage_path('app/public/images/monochrome/' . $Page2);
                        $zplIC .= $this->imageProcessingService->enhanceAndConvertToZPL($monochromeImagePath, $asinfind, $basketnumber);
                    }
                }
                
                if (!empty($Page3)) {
                    $inputPath = storage_path('app/public/instructioncard/' . $Page3);
                    $outputPath = storage_path('app/public/images/monochrome/');
                    $newWidth = 800;
                    $newHeight = 1200;
                    
                    if ($this->imageProcessingService->safeConvertImage($inputPath, $outputPath, $newWidth, $newHeight)) {
                        $monochromeImagePath = storage_path('app/public/images/monochrome/' . $Page3);
                        $zplIC .= $this->imageProcessingService->convertImageLayout($monochromeImagePath, $asinfind, $basketnumber);
                    }
                }
                
                // Process serial-specific pages
                if (!empty($Page4)) {
                    $inputPath = storage_path('app/public/instructioncard/generated_images/' . $Page4);
                    $outputPath = storage_path('app/public/images/monochrome/');
                    $newWidth = 800;
                    $newHeight = 1200;
                    
                    if ($this->imageProcessingService->safeConvertImage($inputPath, $outputPath, $newWidth, $newHeight)) {
                        $monochromeImagePath = storage_path('app/public/images/monochrome/' . $Page4);
                        $zplIC .= $this->imageProcessingService->convertImageLayout($monochromeImagePath, $asinfind, $basketnumber);
                    }
                }
                
                if (!empty($Page5)) {
                    $inputPath = storage_path('app/public/instructioncard/generated_images/' . $Page5);
                    $outputPath = storage_path('app/public/images/monochrome/');
                    $newWidth = 800;
                    $newHeight = 1200;
                    
                    if ($this->imageProcessingService->safeConvertImage($inputPath, $outputPath, $newWidth, $newHeight)) {
                        $monochromeImagePath = storage_path('app/public/images/monochrome/' . $Page5);
                        $zplIC .= $this->imageProcessingService->convertImageLayout($monochromeImagePath, $asinfind, $basketnumber);
                    }
                }
            }
            
            return $zplIC;
            
        } catch (Exception $e) {
            Log::error('Error generating instruction card labels:', [
                'error' => $e->getMessage(),
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
            $inputPath = storage_path('app/public/images/vector/' . $vectorImage);
            $outputPath = storage_path('app/public/images/monochrome');
            $newWidth = 400;
            $newHeight = 300;
            
            // Convert image to monochrome
            $success = $this->imageProcessingService->convertImageToMonochrome($inputPath, $outputPath, $newWidth, $newHeight);
            
            if ($success) {
                $monochromeImagePath = $outputPath . '/' . $vectorImage;
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