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

    public function __construct()
    {
        parent::__construct();
        
        // Set printer settings - these should be configurable
        $this->printerIp = config('app.printer_ip', '192.168.1.109');
        $this->printServerUrl = config('app.print_server_url', 'http://99.0.87.190:1450/ims/Admin/modules/PRD-RPN-PCN/print.php');
    }

    /**
     * Get the correct FNSKU column name from the database
     */
    private function getFnskuColumnName()
    {
        if (!DB::getSchemaBuilder()->hasTable($this->productTable)) {
            return null;
        }
        
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
     * Generate and print a label for a product
     * UPDATED to work with dynamic column detection
     */
    public function printLabel($productId, $username)
    {
        try {
            // Get the correct FNSKU column name
            $fnskuColumn = $this->getFnskuColumnName();
            
            if (!$fnskuColumn) {
                return [
                    'status' => 'error',
                    'message' => 'Database configuration error: FNSKU column not found'
                ];
            }

            // Check if tables exist
            $productTableExists = DB::getSchemaBuilder()->hasTable($this->productTable);
            $fnskuTableExists = DB::getSchemaBuilder()->hasTable($this->fnskuTable);
            $asinTableExists = DB::getSchemaBuilder()->hasTable($this->asinTable);
            
            if (!$productTableExists) {
                return [
                    'status' => 'error',
                    'message' => 'Product table not found: ' . $this->productTable
                ];
            }

            // Get available columns for each table
            $productColumns = DB::getSchemaBuilder()->getColumnListing($this->productTable);
            $fnskuColumns = $fnskuTableExists ? DB::getSchemaBuilder()->getColumnListing($this->fnskuTable) : [];
            $asinColumns = $asinTableExists ? DB::getSchemaBuilder()->getColumnListing($this->asinTable) : [];

            // Build the query with proper joins
            $query = DB::table($this->productTable . ' as prod');
            
            // Add joins only if tables exist
            if ($fnskuTableExists) {
                $query->leftJoin($this->fnskuTable . ' as fnsku', 'prod.' . $fnskuColumn, '=', 'fnsku.FNSKU');
            }
            
            if ($asinTableExists) {
                $query->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN');
            }
            
            // Build select array with only existing columns
            $selectColumns = ['prod.*']; // Start with all product columns
            
            // Add FNSKU table columns if they exist
            if ($fnskuTableExists) {
                if (in_array('ASIN', $fnskuColumns)) {
                    $selectColumns[] = 'fnsku.ASIN as ASINviewer';
                }
                if (in_array('grading', $fnskuColumns)) {
                    $selectColumns[] = 'fnsku.grading as fnsku_grading';
                }
                if (in_array('storename', $fnskuColumns)) {
                    $selectColumns[] = 'fnsku.storename as fnsku_storename';
                }
            }
            
            // Add ASIN table columns if they exist
            if ($asinTableExists) {
                if (in_array('internal', $asinColumns)) {
                    $selectColumns[] = 'asin.internal as AStitle';
                }
                if (in_array('asinStatus', $asinColumns)) {
                    $selectColumns[] = 'asin.asinStatus';
                }
                if (in_array('TRANSPARENCY_QR_STATUS', $asinColumns)) {
                    $selectColumns[] = 'asin.TRANSPARENCY_QR_STATUS';
                }
            }
            
            $query->select($selectColumns);
            
            // Add where conditions only if columns exist
            $query->where('prod.ProductID', $productId);
            
            if (in_array('returnstatus', $productColumns)) {
                $query->where('prod.returnstatus', 'Not Returned');
            }
            if (in_array('ProductModuleLoc', $productColumns)) {
                $query->where('prod.ProductModuleLoc', '!=', 'Migrated');
            }
            
            $product = $query->first();

            if (!$product) {
                return [
                    'status' => 'error',
                    'message' => 'Product not found or already migrated'
                ];
            }

            // Get related FNSKU and ASIN data safely
            $fnsku = $product->{$fnskuColumn} ?? null;
            $asin = $product->ASINviewer ?? null;
            
            // Get ASIN details if available
            $asinDetails = null;
            if ($asin) {
                $asinDetails = (object)[
                    'ASIN' => $asin,
                    'asinStatus' => $product->asinStatus ?? null,
                    'TRANSPARENCY_QR_STATUS' => $product->TRANSPARENCY_QR_STATUS ?? null
                ];
            }
            
            // Prepare additional data - use the correct fields from joins
            $storeName = $product->fnsku_storename ?? '';
            $grading = $product->fnsku_grading ?? '';
            $condition = $this->formatCondition($grading, $storeName, $asin);
            $returnCounts = $this->getReturnCounts($product);
            
            // Generate ZPL code
            $zpl = $this->generateZplCode($product, $condition, $returnCounts, $username, $asinDetails, $fnskuColumn);
            
            // Send to printer
            $result = $this->sendToPrinter($zpl);
            
            // Update print count if successful
            if ($result['status'] === 'success') {
                if (in_array('printCount', $productColumns)) {
                    DB::table($this->productTable)
                        ->where('ProductID', $productId)
                        ->increment('printCount');
                }
                
                // Log the printing activity if history table exists
                if (DB::getSchemaBuilder()->hasTable($this->itemProcessHistoryTable)) {
                    DB::table($this->itemProcessHistoryTable)->insert([
                        'rtcounter' => $product->rtcounter,
                        'employeeName' => $username,
                        'editDate' => $this->getCurrentDateTime(),
                        'Module' => 'Label Printing',
                        'Action' => 'Label printed for ' . ($fnsku ?? 'unknown FNSKU')
                    ]);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logError('Error in printLabel service', $e, [
                'productId' => $productId,
                'productTable' => $this->productTable,
                'fnskuTable' => $this->fnskuTable,
                'asinTable' => $this->asinTable
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Error printing label: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test print functionality
     *
     * @param string $username
     * @return array
     */
    public function testPrint($username)
    {
        try {
            // Generate a simple test ZPL
            $testZpl = $this->generateTestZpl($username);
            
            // Send to printer
            $result = $this->sendToPrinter($testZpl);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logError('Error in test print', $e);
            
            return [
                'status' => 'error',
                'message' => 'Error testing printer: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate test ZPL code
     *
     * @param string $username
     * @return string
     */
    protected function generateTestZpl($username)
    {
        $dateTime = $this->getCurrentDateTime();
        
        return "^XA" .
               "^FO50,50^FB400,1,0,C^ADN,24,24^FDTest Print^FS" .
               "^FO50,100^FB400,1,0,C^ADN,18,18^FDPrinted by: " . $username . "^FS" .
               "^FO50,150^FB400,1,0,C^ADN,14,14^FD" . $dateTime . "^FS" .
               "^FO50,200^FB400,1,0,C^ADN,12,12^FDPrinter test successful^FS" .
               "^XZ";
    }
    
    /**
     * Get the number of times each serial number has been returned
     *
     * @param object $product The product object
     * @return array Return counts for each serial
     */
    protected function getReturnCounts($product)
    {
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
            
            try {
                $count = DB::table($this->productTable)
                    ->where(function ($query) use ($serial) {
                        $query->where('serialnumber', $serial);
                        
                        // Only add these conditions if columns exist
                        $columns = DB::getSchemaBuilder()->getColumnListing($this->productTable);
                        if (in_array('serialnumberb', $columns)) {
                            $query->orWhere('serialnumberb', $serial);
                        }
                        if (in_array('serialnumberc', $columns)) {
                            $query->orWhere('serialnumberc', $serial);
                        }
                        if (in_array('serialnumberd', $columns)) {
                            $query->orWhere('serialnumberd', $serial);
                        }
                    })
                    ->where('returnstatus', 'Returned')
                    ->count();
                    
                $returnCounts[$key] = $count;
            } catch (Exception $e) {
                Log::warning('Error getting return count for serial: ' . $serial, ['error' => $e->getMessage()]);
                $returnCounts[$key] = 0;
            }
        }
        
        return $returnCounts;
    }
    
    /**
     * Format the condition text based on store and grading
     *
     * @param string $grading The product grading
     * @param string $storeName The store name
     * @param string $asin The ASIN
     * @return string The formatted condition
     */
    protected function formatCondition($grading, $storeName, $asin)
    {
        $isAllRenewed = (stripos($storeName, 'Allrenewed') !== false || 
                          stripos($storeName, 'All renewed') !== false ||
                          stripos($storeName, 'All Renewed') !== false);
        
        switch ($grading) {
            case 'UsedLikeNew':
                return 'Used - Like New';
                
            case 'UsedVeryGood':
                return $isAllRenewed ? 'Refurbished - Excellent' : 'Used - Very Good';
                
            case 'UsedGood':
                return $isAllRenewed ? 'Refurbished - Good' : 'Used - Good';
                
            case 'UsedAcceptable':
                return $isAllRenewed ? 'Refurbished - Acceptable' : 'Used - Acceptable';
                
            case 'New':
                if ($isAllRenewed) {
                    // Check if ASIN is marked as Renewed
                    try {
                        if (DB::getSchemaBuilder()->hasTable($this->asinTable)) {
                            $asinStatus = DB::table($this->asinTable)
                                ->where('ASIN', $asin)
                                ->value('asinStatus');
                                
                            if (strtolower($asinStatus) === 'renewed') {
                                return 'Refurbished - Excellent';
                            }
                        }
                    } catch (Exception $e) {
                        Log::warning('Error checking ASIN status', ['error' => $e->getMessage()]);
                    }
                }
                return $grading;
                
            default:
                return $grading;
        }
    }
    
    /**
     * Generate the ZPL code for the label
     *
     * @param object $product The product data
     * @param string $condition The formatted condition
     * @param array $returnCounts Return counts for serials
     * @param string $username Who is printing
     * @param object|null $asinDetails ASIN details if available
     * @param string $fnskuColumn The FNSKU column name
     * @return string The generated ZPL code
     */
    protected function generateZplCode($product, $condition, $returnCounts, $username, $asinDetails = null, $fnskuColumn = 'FNSKUviewer')
    {
        $zpl = '';
        $isRenewed = ($condition === 'Refurbished - Excellent');
        
        // Add divider
        $zpl .= "^XA^FO40,120^ADN,36,20^FW^FD--- DIVIDER ---^FS^XZ";
        
        // Add RENEWED label if applicable
        if ($isRenewed) {
            $zpl .= "^XA^FO5,70^ADN,200,42^FW^FD RENEWED ^FS^XZ";
        }
        
        // Generate return info string
        $returnInfo = "";
        if (isset($returnCounts['a']) && $returnCounts['a'] >= 0) {
            $returnInfo .= "R:" . $returnCounts['a'] . " ";
        }
        $returnInfo = rtrim($returnInfo);
        
        // Print serial number label(s)
        $zpl .= $this->generateSerialLabels($product, $condition, $returnInfo);
        
        // Print FNSKU label if available
        $fnskuValue = $product->{$fnskuColumn} ?? null;
        if (!empty($fnskuValue)) {
            $zpl .= $this->generateFnskuLabel($product, $condition, $fnskuColumn);
        }
        
        // Print title label if available
        if (!empty($product->AStitle)) {
            $zpl .= $this->generateTitleLabel($product);
        }
        
        // Print QR code for serial if available
        if (!empty($product->serialnumber)) {
            $zpl .= $this->generateQRCode($product->serialnumber);
        }
        
        // Print QR code for ASIN if available
        if (!empty($product->ASINviewer)) {
            $zpl .= $this->generateAsinQRCode($product->ASINviewer, $product->AStitle ?? '');
        }
        
        // Print notes if available
        if (!empty($product->notes)) {
            $zpl .= $this->generateNotesLabel($product->notes);
        }
        
        // Print transparency QR status if available
        if (!empty($product->ASINviewer) && $asinDetails && !empty($asinDetails->TRANSPARENCY_QR_STATUS)) {
            $zpl .= "^XA";
            $zpl .= "^FO5,20^ADN,3,3^FW^FDTransparency QR Status^FS";
            $zpl .= "^FO40,50^FB400,10,0^AON,16,16^FW^FD" . $asinDetails->TRANSPARENCY_QR_STATUS . "^FS";
            $zpl .= "^XZ";
        }
        
        // Print warehouse location if available
        if (!empty($product->warehouselocation)) {
            $zpl .= "^XA";
            $zpl .= "^FO5,30^ADN,3,3^FW^FD Warehouse Location^FS";
            $zpl .= "^FO10,100^FB400,10,0^AON,28,25^FW^FD" . $product->warehouselocation . "^FS";
            $zpl .= "^XZ";
        }
        
        // Print print count if available
        if (!empty($product->printCount)) {
            $zpl .= "^XA";
            $zpl .= "^FO30,100^FB400,2,0,C^AON,18,18^FW^FDPrint Count " . ($product->printCount + 1) . "^FS";
            $zpl .= "^XZ";
        }
        
        // Print RT/AR number and condition
        if (!empty($product->rtcounter)) {
            $zpl .= $this->generateRtArLabel($product, $condition);
        }
        
        // Print timestamp and user info
        if (!empty($product->rtcounter)) {
            $zpl .= $this->generateTimestampLabel($product, $username);
        }
        
        // Print sticky notes if available
        if (!empty($product->stickernote)) {
            $zpl .= $this->generateStickerNoteLabel($product->stickernote);
        }
        
        return $zpl;
    }
    
    /**
     * Generate serial number labels
     *
     * @param object $product The product data
     * @param string $condition The formatted condition
     * @param string $returnInfo Return count info
     * @return string ZPL code for serial labels
     */
    protected function generateSerialLabels($product, $condition, $returnInfo)
    {
        $zpl = '';
        $isRenewed = ($condition === 'Refurbished - Excellent');
        $serialA = $product->serialnumber ?? null;
        $serialB = $product->serialnumberb ?? null;
        $serialC = $product->serialnumberc ?? null;
        $serialD = $product->serialnumberd ?? null;
        
        // Check if we have both serial A and B
        if (!empty($serialA) && !empty($serialB)) {
            // Generate 3 copies of dual serial label
            for ($i = 0; $i < 3; $i++) {
                $zpl .= "^XA";
                // Header
                if ($isRenewed) {
                    $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO205,23^ADN,20,15^FD Renewed^FS";
                } else {
                    $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO135,23^ADN,7,7^FD Certified Pre-Owned Unit^FS";
                }
                
                // Serial A barcode and text
                $zpl .= "^FO50,60^FB200,2,0,C^ADN,7,9^BCN,50,N,N,N,A^FD" . $serialA . "^FS";
                $zpl .= "^FO10,117^FB400,1,0,C^ADN,7,9^FD" . $serialA . "^FS";
                
                // Serial B barcode and text
                $zpl .= "^FO50,140^FB200,2,0,C^ADN,7,9^BCN,50,N,N,N,A^FD" . $serialB . "^FS";
                $zpl .= "^FO10,197^FB400,1,0,C^ADN,7,9^FD" . $serialB . "^FS";
                
                // Footer
                $zpl .= "^FO9,215^ADN,1,1^FW^FDThis SN is recorded,and if returning,^FS";
                $zpl .= "^FO9,235^ADN,1,1^FW^FDMUST MATCH item's to avoid charges^FS";
                $zpl .= "^XZ";
            }
        } else if (!empty($serialA)) {
            // Generate 3 copies of single serial label
            for ($i = 0; $i < 3; $i++) {
                $zpl .= "^XA";
                // Header
                if ($isRenewed) {
                    $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO205,23^ADN,20,15^FD Renewed^FS";
                } else {
                    $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO135,23^ADN,7,7^FD Certified Pre-Owned Unit^FS";
                }
                
                // Return count
                $zpl .= "^FO245,45^ADN,16,16^FW^FD" . $returnInfo . "^FS";
                
                // Serial barcode and text
                $zpl .= "^FO35,100^FB400,2,0,C^ADN,12,12^BCN,80,N,N,N,A^FD" . $serialA . "^FS";
                $zpl .= "^FO10,185^FB400,1,0,C^ADN,12,12^FD" . $serialA . "^FS";
                
                // Footer
                $zpl .= "^FO6,220^ADN,1,1^FW^FDThis SN is recorded,and if returning,^FS";
                $zpl .= "^FO6,240^ADN,1,1^FW^FDMUST MATCH item's to avoid charges^FS";
                $zpl .= "^XZ";
            }
        }
        
        // Check if we have both serial C and D
        if (!empty($serialC) && !empty($serialD)) {
            // Generate 3 copies of dual serial label for C and D
            for ($i = 0; $i < 3; $i++) {
                $zpl .= "^XA";
                // Header
                if ($isRenewed) {
                    $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO205,23^ADN,20,15^FD Renewed^FS";
                } else {
                    $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO135,23^ADN,7,7^FD Certified Pre-Owned Unit^FS";
                }
                
                // Serial C barcode and text
                $zpl .= "^FO50,60^FB200,2,0,C^ADN,7,9^BCN,50,N,N,N,A^FD" . $serialC . "^FS";
                $zpl .= "^FO10,117^FB400,1,0,C^ADN,7,9^FD" . $serialC . "^FS";
                
                // Serial D barcode and text
                $zpl .= "^FO50,140^FB200,2,0,C^ADN,7,9^BCN,50,N,N,N,A^FD" . $serialD . "^FS";
                $zpl .= "^FO10,197^FB400,1,0,C^ADN,7,9^FD" . $serialD . "^FS";
                
                // Footer
                $zpl .= "^FO9,215^ADN,1,1^FW^FDThis SN is recorded,and if returning,^FS";
                $zpl .= "^FO9,235^ADN,1,1^FW^FDMUST MATCH item's to avoid charges^FS";
                $zpl .= "^XZ";
            }
        } else if (!empty($serialC)) {
            // Generate 3 copies of single serial label for C
            for ($i = 0; $i < 3; $i++) {
                $zpl .= "^XA";
                // Header
                if ($isRenewed) {
                    $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO205,23^ADN,20,15^FD Renewed^FS";
                } else {
                    $zpl .= "^FO0,35^ADN,16,16^FW^FD SN: ^FS^FO135,23^ADN,7,7^FD Certified Pre-Owned Unit^FS";
                }
                
                // Serial barcode and text
                $zpl .= "^FO35,100^FB400,2,0,C^ADN,12,12^BCN,80,N,N,N,A^FD" . $serialC . "^FS";
                $zpl .= "^FO10,185^FB400,1,0,C^ADN,12,12^FD" . $serialC . "^FS";
                
                // Footer
                $zpl .= "^FO6,220^ADN,1,1^FW^FDThis SN is recorded,and if returning,^FS";
                $zpl .= "^FO6,240^ADN,1,1^FW^FDMUST MATCH item's to avoid charges^FS";
                $zpl .= "^XZ";
            }
        }
        
        return $zpl;
    }
    
    /**
     * Generate FNSKU label
     *
     * @param object $product The product data
     * @param string $condition The formatted condition
     * @param string $fnskuColumn The FNSKU column name
     * @return string ZPL code for FNSKU label
     */
    protected function generateFnskuLabel($product, $condition, $fnskuColumn = 'FNSKUviewer')
    {
        $fnsku = $product->{$fnskuColumn} ?? null;
        $asin = $product->ASINviewer ?? null;
        $title = $product->AStitle ?? '';
        
        if (!$fnsku) {
            return '';
        }
        
        // Check if the FNSKU equals ASIN
        if ($fnsku == $asin) {
            return "^XA" . 
                   "^FO55,30^FB400,2,0,C^AON,24,24^BCN,100,N,N,N,A^FD" . $fnsku . "^FS" .
                   "^FO10,140^FB400,1,0,C^ADN,24,24^FD" . $fnsku . "^FS" . 
                   "^FO30,170^FB400,10,0^AON,17,10^FD" . $condition . "- " . $title . "^FS" .
                   "^XZ";
        } 
        
        // Check if the FNSKU has B-W prefix
        $prefix = substr($fnsku, 0, 2);
        if (preg_match('/^[B-W][0-9]/', $prefix)) {
            $barcodeWithoutPrefix = substr($fnsku, 2);
            $displayText = $barcodeWithoutPrefix . ' - ' . $prefix;
            
            return "^XA" . 
                   "^FO55,30^FB400,2,0,C^AON,24,24^BCN,100,N,N,N,A^FD" . $barcodeWithoutPrefix . "^FS" .
                   "^FO10,140^FB400,1,0,C^ADN,24,24^FD" . $displayText . "^FS" . 
                   "^FO30,170^FB400,10,0^AON,17,10^FD" . $condition . "- " . $title . "^FS" .
                   "^XZ";
        }
        
        // Default FNSKU label
        return "^XA" . 
               "^FO55,30^FB400,2,0,C^AON,24,24^BCN,100,N,N,N,A^FD" . $fnsku . "^FS" .
               "^FO10,140^FB400,1,0,C^ADN,24,24^FD" . $fnsku . "^FS" . 
               "^FO30,170^FB400,10,0^AON,17,10^FD" . $condition . "- " . $title . "^FS" .
               "^XZ";
    }
    
    /**
     * Generate title label
     *
     * @param object $product The product data
     * @return string ZPL code for title label
     */
    protected function generateTitleLabel($product)
    {
        $storeName = $product->fnsku_storename ?? '';
        $isRenovarTech = (stripos($storeName, 'Renovar Tech') !== false || 
                           stripos($storeName, 'Renovartech') !== false || 
                           empty($storeName));
        $rtPrefix = $isRenovarTech ? 'RT' : 'AR';
        
        return "^XA" . 
               "^FO20,50^FB400,10,0^AON,17,17^FW^FD" . $product->AStitle . "^FS" .
               "^FO100,220^FB400,10,0^AOC,14,14^FW^FDPKG# " . $rtPrefix . sprintf("%05d", $product->rtcounter) . "^FS" .
               "^XZ";
    }
    
    /**
     * Generate RT/AR label with condition
     *
     * @param object $product The product data
     * @param string $condition The formatted condition
     * @return string ZPL code for RT/AR label
     */
    protected function generateRtArLabel($product, $condition)
    {
        $storeName = $product->fnsku_storename ?? '';
        $isRenovarTech = (stripos($storeName, 'Renovar Tech') !== false || 
                           stripos($storeName, 'Renovartech') !== false || 
                           empty($storeName));
        $rtPrefix = $isRenovarTech ? 'RT' : 'AR';
        
        $zpl = "^XA";
        
        if ($isRenovarTech) {
            $zpl .= "^FO100,30^FB400,2,0,C^AON,18,18^BCN,100,N,N,N,A^FD" . $rtPrefix . sprintf("%05d", $product->rtcounter) . "^FS" .
                    "^FO10,140^FB400,1,0,C^ADN,26,22^FD" . $rtPrefix . sprintf("%05d", $product->rtcounter) . "^FS";
        } else {
            $zpl .= "^FO100,30^FB400,2,0,C^AON,26,22^BCN,100,N,N,N,A^FD" . $rtPrefix . sprintf("%05d", $product->rtcounter) . "^FS" .
                    "^FO10,140^FB400,1,0,C^ADN,26,22^FD" . $rtPrefix . sprintf("%05d", $product->rtcounter) . "^FS";
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
     * Generate timestamp and user label
     *
     * @param object $product The product data
     * @param string $username Who is printing
     * @return string ZPL code for timestamp label
     */
    protected function generateTimestampLabel($product, $username)
    {
        $california_timezone = new DateTimeZone('America/Los_Angeles');
        $current_datetime = new DateTime('now', $california_timezone);
        $formatted_date = $current_datetime->format('Y-m-d');
        $formatted_time = $current_datetime->format('h:i A');
        
        $zpl = "^XA";
        
        // Priority Rank
        $zpl .= "^FO30,100^FB400,2,0,C^AON,18,18^FW^FDPRIORITY " . ($product->priorityrank ?? '') . "^FS";
        
        // Username with dynamic font size
        if (strlen($username) > 6) {
            // Smaller font if username is longer than 6 characters
            $zpl .= "^FO30,130^FB400,2,0,C^AON,14,14^FW^FDPRINT BY:" . $username . "^FS";
        } else {
            // Regular font if username is 6 characters or shorter
            $zpl .= "^FO30,130^FB400,2,0,C^AON,18,18^FW^FDPRINT BY:" . $username . "^FS";
        }
        
        // Date and time
        $zpl .= "^FO30,160^FB400,2,0,C^AON,18,18^FW^FD" . $formatted_date . "^FS";
        $zpl .= "^FO30,190^FB400,2,0,C^AON,18,18^FW^FD" . $formatted_time . "^FS";
        
        $zpl .= "^XZ";
        
        return $zpl;
    }
    
    /**
     * Generate sticker notes label
     *
     * @param string $stickerNote The sticker note text
     * @return string ZPL code for sticker note
     */
    protected function generateStickerNoteLabel($stickerNote)
    {
        $zpl = "^XA"; // Initialize the ZPL code
        $y_position = 50; // Default initial Y position
        
        // Split the stickernote by line breaks
        $stickernote_parts = explode("\n", $stickerNote);
        
        // Settings for text formatting
        $line_spacing = 30;
        $text_width = 200;
        $font_height = 25;
        $font_width = 11;
        $char_width = $font_height / 6;
        
        // Process each line of the sticker note
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
        
        $zpl .= "^XZ"; // End the ZPL code
        return $zpl;
    }
    
    /**
     * Generate notes label
     *
     * @param string $notes The notes text
     * @return string ZPL code for notes
     */
    protected function generateNotesLabel($notes)
    {
        return "^XA" .
               "^FO5,20^ADN,1,1^FW^FDS Notes^FS" .
               "^FO30,50^FB400,10,0^AON,16,16^FW^FD" . $notes . "^FS" .
               "^XZ";
    }
    
    /**
     * Generate QR code for serial number
     * This is a placeholder - you'll need to implement the actual QR generation
     *
     * @param string $serial The serial number
     * @return string ZPL code for QR
     */
    protected function generateQRCode($serial)
    {
        // In the original code, this calls convertImageQRserial
        // You'll need to implement the actual QR code generation
        // For now, returning a placeholder ZPL
        return "^XA" .
               "^FO50,50^BQN,2,10^FDQA," . $serial . "^FS" .
               "^FO50,225^FB400,1,0,C^ADN,12,12^FD" . $serial . "^FS" .
               "^XZ";
    }
    
    /**
     * Generate QR code for ASIN
     * This is a placeholder - you'll need to implement the actual QR generation
     *
     * @param string $asin The ASIN
     * @param string $title The product title
     * @return string ZPL code for QR
     */
    protected function generateAsinQRCode($asin, $title)
    {
        // In the original code, this calls convertImageQRmanual
        // You'll need to implement the actual QR code generation
        // For now, returning a placeholder ZPL
        return "^XA" .
               "^FO50,50^BQN,2,10^FDQA," . $asin . "^FS" .
               "^FO50,225^FB400,1,0,C^ADN,12,12^FD" . $asin . "^FS" .
               "^XZ";
    }
    
    /**
     * Send ZPL code to printer
     *
     * @param string $zpl The ZPL code to print
     * @return array Result of the print operation
     */
    protected function sendToPrinter($zpl)
    {
        try {
            // Prepare data for the print server
            $postData = http_build_query([
                'zpl' => $zpl,
                'printerSelect' => $this->printerIp
            ]);
            
            // Set up curl request
            $ch = curl_init($this->printServerUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            // Execute request
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            // Log the response
            Log::info('Print server response:', [
                'response' => $response,
                'status' => $status,
                'error' => $error
            ]);
            
            // Check for success
            if ($response === "Message sent to printer successfully." || $status === 200) {
                return [
                    'status' => 'success',
                    'message' => 'Label printed successfully'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to print label: ' . ($response ?: $error)
                ];
            }
            
        } catch (Exception $e) {
            $this->logError('Error sending to printer', $e);
            
            return [
                'status' => 'error',
                'message' => 'Error sending to printer: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get current date and time in a formatted string
     *
     * @return string Formatted date and time
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

    /**
     * Log errors with context - Updated to match BasetablesController signature
     *
     * @param string $message
     * @param Exception $exception
     * @param array $additionalContext
     */
    protected function logError($message, $exception, $additionalContext = [])
    {
        Log::error($message, array_merge([
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ], $additionalContext));
    }
}