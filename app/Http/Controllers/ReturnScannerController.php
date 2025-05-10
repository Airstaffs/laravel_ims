<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Log;

class ReturnScannerController extends BasetablesController
{
 /**
 * Display a listing of products in return list with joined LPN data.
 */
public function index(Request $request)
{
    $perPage = $request->input('per_page', 10);
    $search = $request->input('search', '');
    $location = $request->input('location', 'Returnlist');
    
    try {
        $products = DB::table($this->productTable)
            ->select(
                $this->productTable.'.ProductID',
                $this->productTable.'.rtcounter',
                $this->productTable.'.rtid',
                $this->productTable.'.serialnumber',
                $this->productTable.'.serialnumberb',
                $this->productTable.'.FNSKUviewer',
                $this->productTable.'.warehouselocation',
                $this->productTable.'.returnstatus',
                // Add image fields
                $this->productTable.'.img1',
                $this->productTable.'.img2',
                $this->productTable.'.img3',
                $this->productTable.'.img4',
                $this->productTable.'.img5',
                $this->productTable.'.img6',
                $this->productTable.'.img7',
                $this->productTable.'.img8',
                $this->productTable.'.img9',
                $this->productTable.'.img10',
                $this->productTable.'.img11',
                $this->productTable.'.img12',
                $this->productTable.'.img13',
                $this->productTable.'.img14',
                $this->productTable.'.img15',
                'tbllpn.LPN',
                'tbllpn.LPNDATE',
                'tbllpn.BuyerName'
            )
            ->leftJoin('tbllpn', $this->productTable.'.ProductID', '=', 'tbllpn.ProdID')
            ->where($this->productTable.'.ProductModuleLoc', $location)
            ->when($search, function($query) use ($search) {
                return $query->where(function($q) use ($search) {
                    $q->where($this->productTable.'.serialnumber', 'like', "%{$search}%")
                      ->orWhere($this->productTable.'.FNSKUviewer', 'like', "%{$search}%")
                      ->orWhere($this->productTable.'.rtcounter', 'like', "%{$search}%")
                      ->orWhere('tbllpn.LPN', 'like', "%{$search}%");
                });
            })
            ->orderBy($this->productTable.'.ProductID', 'desc')
            ->paginate($perPage);
        
        return response()->json($products);
    } catch (\Exception $e) {
        Log::error('Error fetching return products: ' . $e->getMessage());
        return response()->json([
            'error' => 'Database error occurred',
            'message' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Get list of store names for the dropdown
     */
    public function getStores()
    {
        try {
            $stores = DB::table($this->fnskuTable)
                ->select('storename')
                ->distinct()
                ->orderBy('storename')
                ->get()
                ->pluck('storename');
                
            return response()->json($stores);
        } catch (\Exception $e) {
            Log::error('Error getting stores: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'An error occurred while retrieving store list',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a serial number belongs to a dual-serial product
     */
    /**
 * Check if a serial number belongs to a dual-serial product
 * Only searches for products in Stockroom, Shipment, or Soldlist
 */
/**
 * Check if a serial number belongs to a dual-serial product
 * Only searches for products in Stockroom, Shipment, or Soldlist
 * Returns only the essential information needed
 */
public function checkSerial(Request $request)
{
    $serial = $request->get('serial');
    
    if (!$serial) {
        return response()->json([
            'success' => false,
            'message' => 'No serial number provided'
        ]);
    }
    
    try {
        // Check if this serial exists in the database
        // Only look for items in Stockroom, Shipment, or Soldlist
        $product = DB::table($this->productTable)
            ->where(function ($query) use ($serial) {
                $query->where('serialnumber', $serial)
                    ->orWhere('serialnumberb', $serial);
            })
            ->whereIn('ProductModuleLoc', ['Stockroom', 'Shipment', 'Soldlist'])
            ->first();
        
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Serial number not found or not in a valid location'
            ]);
        }
        
        // Determine if this is a dual-serial product and which serial was scanned
        $isDualSerial = !empty($product->serialnumberb);
        $secondSerial = null;
        $scannedSerialPosition = null;
        
        // Check which serial was scanned and get the other one
        if ($serial === $product->serialnumber && !empty($product->serialnumberb)) {
            $secondSerial = $product->serialnumberb;
            $scannedSerialPosition = 'primary';
        } else if ($serial === $product->serialnumberb && !empty($product->serialnumber)) {
            $secondSerial = $product->serialnumber;
            $scannedSerialPosition = 'secondary';
        }
        
        // Get FNSKU directly from the product
        $fnskuViewer = $product->FNSKUviewer ?? null;
        
        // Return only the essential information
        return response()->json([
            'success' => true,
            'isDualSerial' => $isDualSerial,
            'secondSerial' => $secondSerial,
            'scannedSerialPosition' => $scannedSerialPosition,
            'secondSerialLabel' => 'Second Serial',
            'productId' => $product->ProductID,
            'fnskuViewer' => $fnskuViewer
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error checking serial: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error checking serial: ' . $e->getMessage()
        ]);
    }
}
    
    /**
     * Process a return scan
     */
    public function processScan(Request $request)
{
    // Start the database transaction at the very beginning
    DB::beginTransaction();
    
    try {
        // Validate input with robust error handling
        try {
            $validatedData = $request->validate([
                'SerialNumber' => 'required|string',
                'SecondSerial' => 'nullable|string',
                'Location' => 'required|string',
                'ReturnId' => 'nullable|string',
                'SingleSerialMode' => 'nullable|boolean',
                'ProductID' => 'nullable|integer',
                'FNSKUviewer' => 'nullable|string',
                'ScannedSerialPosition' => 'nullable|string',
                'ScannedPrimarySerial' => 'nullable|string',
                'ScannedSecondarySerial' => 'nullable|string'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return a clean validation error response
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->errors()),
                'reason' => 'validation_error'
            ], 422);
        }

        // Get data from request with defensive coding
        $User = Auth::id() ?? $request->session()->get('user_name', 'Unknown');
        $serial = trim($request->input('SerialNumber', ''));
        $secondSerial = trim($request->input('SecondSerial', ''));
        $location = trim($request->input('Location', ''));
        $returnId = trim($request->input('ReturnId', ''));
        $singleSerialMode = (bool)$request->input('SingleSerialMode', false);
        $productId = $request->input('ProductID');
        $fnsku = $request->input('FNSKUviewer');
        $scannedSerialPosition = $request->input('ScannedSerialPosition');
        $scannedPrimarySerial = $request->input('ScannedPrimarySerial');
        $scannedSecondarySerial = $request->input('ScannedSecondarySerial');

        // Log important inputs for debugging
        Log::info("Processing return scan with params:", [
            'serial' => $serial,
            'secondSerial' => $secondSerial,
            'singleSerialMode' => $singleSerialMode,
            'scannedSerialPosition' => $scannedSerialPosition
        ]);

        // Check for empty serial
        if (empty($serial)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Serial Number must be provided',
                'reason' => 'missing_identifiers'
            ], 422);
        }

        // Time handling with error checking
        try {
            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            $formatted_datetime = $currentDatetime->format('Y-m-d h:i A');
            $currentDate = date('Y-m-d', strtotime($formatted_datetime));
            $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            // Use fallback timezone if there's an issue
            Log::warning('Error with timezone, using default', ['error' => $e->getMessage()]);
            $currentDatetime = new DateTime();
            $formatted_datetime = $currentDatetime->format('Y-m-d h:i A');
            $currentDate = date('Y-m-d');
            $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
        }

        // Validate serial number
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $serial)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid Serial Number format',
                'reason' => 'invalid_serial'
            ]);
        }

        // Validate second serial if provided
        if (!empty($secondSerial) && !preg_match('/^[a-zA-Z0-9-]+$/', $secondSerial)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid Second Serial Number format',
                'reason' => 'invalid_second_serial'
            ]);
        }

        // Validate location format
        if (!preg_match('/^L\d{3}[A-G]$/i', $location) && $location !== 'Floor' && $location !== 'L800G') {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid Location Format',
                'reason' => 'invalid_location'
            ]);
        }

        // Track if the serial is found in the database
        $isSerialKnown = false;
        
        // Find the item by ProductID if provided
        $existingItem = null;
        
        if ($productId) {
            // If ProductID is provided, use it directly
            $existingItem = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->whereIn('ProductModuleLoc', ['Stockroom', 'Shipment', 'Soldlist'])
                ->first();
                
            if (!$existingItem) {
                Log::warning('Product ID provided but not found or not in valid location', [
                    'ProductID' => $productId,
                    'SerialNumber' => $serial,
                    'User' => $User
                ]);
            } else {
                $isSerialKnown = true;
            }
        }
        
        // If not found by ProductID, search by serial
        if (!$existingItem) {
            $existingItem = DB::table($this->productTable)
                ->where(function ($query) use ($serial, $secondSerial) {
                    $query->where('serialnumber', $serial)
                        ->orWhere('serialnumberb', $serial);
                    
                    if (!empty($secondSerial)) {
                        $query->orWhere('serialnumber', $secondSerial)
                            ->orWhere('serialnumberb', $secondSerial);
                    }
                })
                ->whereIn('ProductModuleLoc', ['Stockroom', 'Shipment', 'Soldlist'])
                ->first();
                
            if ($existingItem) {
                $isSerialKnown = true;
            }
        }

        // If serial not found in database, handle as unknown serial
        if (!$existingItem) {
            Log::info("Serial {$serial} not found in database, will process as unknown item switcheru");
            
            // Create a dummy existingItem to continue processing
            $existingItem = (object)[
                'ProductID' => null,
                'rtcounter' => null,
                'rtid' => null,
                'itemnumber' => null,
                'price' => null,
                'costumer_name' => $buyerName ?? 'Unknown',
                'ASINviewer' => null,
                'FNSKUviewer' => null,
                'serialnumber' => null,
                'serialnumberb' => null,
                'ProductModuleLoc' => null
            ];
        }

        // Handle dual-serial validation if applicable and not in single serial mode
        // Only do this validation if we found the item in the database
        if ($isSerialKnown && !empty($existingItem->serialnumberb) && !$singleSerialMode) {
            // This is a dual-serial product and not in single serial mode
            // Make sure both serials are provided
            $dbSerial1 = $existingItem->serialnumber;
            $dbSerial2 = $existingItem->serialnumberb;
            
            // If second serial is not provided but required
            if (empty($secondSerial)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This is a dual-serial product. Second serial number is required.',
                    'reason' => 'missing_second_serial',
                    'secondSerialLabel' => 'Second Serial',
                    'isDualSerial' => true,
                    'secondSerial' => $serial === $dbSerial1 ? $dbSerial2 : $dbSerial1
                ]);
            }
            
            // Verify at least one serial matches the original item
            // This is the bare minimum to proceed - we'll handle the switcheru detection later
            $anySerialMatches = in_array($serial, [$dbSerial1, $dbSerial2]) || 
                                in_array($secondSerial, [$dbSerial1, $dbSerial2]);
            
            if (!$anySerialMatches) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'The provided serial numbers do not match the product record.',
                    'reason' => 'serial_mismatch',
                    'correctSerials' => [
                        'serial1' => $dbSerial1,
                        'serial2' => $dbSerial2
                    ]
                ]);
            }
            
            // Note: We'll detect actual switcheru cases later in the code
            // (when one serial matches but the other doesn't match the expected pair)
        }

        // Define what serials we're processing
        $serialsToProcess = [];
        if (!empty($secondSerial) && !$singleSerialMode) {
            // Process both serials
            $serialsToProcess[] = $serial;
            $serialsToProcess[] = $secondSerial;
        } else {
            // Process just the main serial
            $serialsToProcess[] = $serial;
        }

        // Track the original item info for buyer name, return info, etc.
        $originalItem = $existingItem;
        $rtCounter = $existingItem->rtcounter ?? null;
        $rtId = $existingItem->rtid ?? null;
        $itemNumber = $existingItem->itemnumber ?? null;
        $price = $existingItem->price ?? null;
        $buyerName = $existingItem->costumer_name ?? null;
        $originalAsin = $existingItem->ASINviewer ?? null;
        $originalFnsku = $existingItem->FNSKUviewer ?? null;

        // Create a single LPN record for this return
        $lpnInsertion = DB::table('tbllpn')->insertGetId([
            'SERIAL' => $serial, // Use primary serial
            'LPN' => $returnId,
            'LPNDATE' => $curentDatetimeString,
            'ProdID' => $originalItem->ProductID,
            'BuyerName' => $buyerName ?? 'Unknown'
        ]);
        
        // Get the LPN ID we just created
        $currentLpnId = $lpnInsertion;
        
        $successCount = 0;
        $createdItems = [];
        
        // Process each serial in the array
        foreach ($serialsToProcess as $currentSerial) {
            // Determine if we should use "Stockroom" or "Production Area" based on location
            if (substr($location, 0, 4) === 'L800') {
                $modulelocation = 'Production Area';
                $insertedDate = null;
            } else {
                $modulelocation = 'Stockroom';
                $insertedDate = $curentDatetimeString;
            }
            
            // Initialize variables for the FNSKU lookup
            $asinToUse = $originalAsin;
            $fnskuToUse = null;
            $condition = null;
            $title = null;
            $status = null;
            
            try {
                // Only try to find FNSKU if we have original FNSKU
                if ($originalFnsku) {
                    // First try the original FNSKU
                    $fnskuInfo = DB::table($this->fnskuTable)
                        ->where('FNSKU', $originalFnsku)
                        ->first();
  
                    if ($fnskuInfo) {
                        $asinToUse = $fnskuInfo->ASIN;
                        $condition = $fnskuInfo->grading;
                        $title = $fnskuInfo->astitle;
                        
                        // Check if the title indicates this is a pack
                        $hasPack = preg_match('/\b(?:pack|Pack|PACK|(\d+)(?:-|\s)?(?:pack|Pack|PACK))\b/', $title);
                        
                        // If this is a pack item, try to find a single item version
                        if ($hasPack) {
                            Log::info("Original FNSKU {$originalFnsku} is a pack item, looking for single item version");
                            
                            // Clean up all pack references in title
                            // 1. Remove patterns like "2-Pack", "2 Pack", "2Pack"
                            $cleanTitle = preg_replace('/\b\d+\s*-?\s*(?:pack|Pack|PACK)\b/', '', $title);
                            
                            
                            // 3. Remove parenthesized parts that contain "pack"
                            $cleanTitle = preg_replace('/\s*\([^)]*(?:pack|Pack|PACK)[^)]*\)/', '', $cleanTitle);
                            
                            // 4. Clean up any double spaces and trim
                            $cleanTitle = preg_replace('/\s+/', ' ', $cleanTitle);
                            $cleanTitle = trim($cleanTitle);
                            
                            Log::info("Searching for non-pack item with clean title: {$cleanTitle}");
                            
                            // Extract color if available
                            $color = null;
                            if (preg_match('/\((.*?)\)/', $title, $colorMatches)) {
                                $color = $this->getBaseColor($colorMatches[1]);
                                Log::info("Extracted color: {$color}");
                            }
                            
                            // Create title-based query for single item versions
                            $query = DB::table($this->fnskuTable)
                                ->whereRaw("fnsku_status = 'available'")
                                ->whereRaw("amazon_status = 'Existed'")
                                ->whereRaw("LimitStatus = 'False'")
                                ->whereRaw("grading = ?", [$condition]) // Use original condition/grading
                                ->whereRaw("astitle NOT LIKE '%pack%'")
                                ->whereRaw("astitle NOT LIKE '%Pack%'")
                                ->whereRaw("astitle NOT LIKE '%PACK%'")
                                ->where('Units', '>', 0);
                            
                            // Split the title into words for matching
                            $titleWords = array_values(array_filter(explode(' ', $cleanTitle), function($word) {
                                return strlen($word) > 1; // Filter out very short words
                            }));
                            
                            // Try exact title match first (highest priority)
                            if (count($titleWords) > 0) {
                                $titleMatch = clone $query;
                                
                                // Add color constraint if available
                                if ($color) {
                                    $titleMatch->whereRaw("astitle LIKE ?", ['%'.$color.'%']);
                                }
                                
                                // Find any numbers in the title
                                $numberMatches = [];
                                foreach ($titleWords as $word) {
                                    if (is_numeric($word) || preg_match('/^\d+$/', $word)) {
                                        $numberMatches[] = $word;
                                    }
                                }
                                
                                // Add specific number constraints to avoid wrong models
                                if (!empty($numberMatches)) {
                                    $titleMatch->where(function($q) use ($numberMatches) {
                                        foreach ($numberMatches as $num) {
                                            // Must include the exact number
                                            $q->whereRaw("astitle LIKE ?", ['%'.$num.'%']);
                                            
                                            // For single-digit numbers, prevent matching higher numbers
                                            if ($num < 10) {
                                                for ($i = 0; $i <= 9; $i++) {
                                                    if ($i != 0) { // Don't exclude the number itself
                                                        $q->whereRaw("astitle NOT LIKE ?", ['%'.$num.$i.'%']);
                                                    }
                                                }
                                            }
                                        }
                                    });
                                }
                                
                                // Match title pattern
                                $titlePattern = '%' . implode('%', $titleWords) . '%';
                                $titleMatch->whereRaw("astitle LIKE ?", [$titlePattern]);
                                
                                // Execute the query
                                $singleItem = $titleMatch->orderByDesc('FNSKUID')->first();
                                
                                if ($singleItem) {
                                    Log::info("Found single item version: {$singleItem->FNSKU} with title: {$singleItem->astitle}");
                                    $fnskuToUse = $singleItem->FNSKU;
                                    $status = $singleItem->fnsku_status;
                                } else {
                                    Log::info("No precise match found, will try fallback options");
                                    
                                    // Fallback - look for any FNSKU with same condition and ASIN
                                    $fallbackQuery = DB::table($this->fnskuTable)
                                        ->whereRaw("fnsku_status = 'available'")
                                        ->whereRaw("amazon_status = 'Existed'")
                                        ->whereRaw("LimitStatus = 'False'")
                                        ->whereRaw("grading = ?", [$condition])
                                        ->where('ASIN', $asinToUse)
                                        ->whereRaw("astitle NOT LIKE '%pack%'")
                                        ->whereRaw("astitle NOT LIKE '%Pack%'")
                                        ->whereRaw("astitle NOT LIKE '%PACK%'")
                                        ->where('Units', '>', 0);
                                    
                                    $fallbackItem = $fallbackQuery->orderByDesc('FNSKUID')->first();
                                    
                                    if ($fallbackItem) {
                                        Log::info("Found fallback item with same ASIN: {$fallbackItem->FNSKU} with title: {$fallbackItem->astitle}");
                                        $fnskuToUse = $fallbackItem->FNSKU;
                                        $status = $fallbackItem->fnsku_status;
                                    } else {
                                        // Last resort - look for any product with same condition and brand
                                        if (count($titleWords) > 0) {
                                            $brand = $titleWords[0]; // First word is usually brand name
                                            
                                            $brandQuery = DB::table($this->fnskuTable)
                                                ->whereRaw("fnsku_status = 'available'")
                                                ->whereRaw("amazon_status = 'Existed'")
                                                ->whereRaw("LimitStatus = 'False'")
                                                ->whereRaw("grading = ?", [$condition])
                                                ->whereRaw("astitle LIKE ?", ['%'.$brand.'%'])
                                                ->whereRaw("astitle NOT LIKE '%pack%'")
                                                ->whereRaw("astitle NOT LIKE '%Pack%'")
                                                ->whereRaw("astitle NOT LIKE '%PACK%'")
                                                ->where('Units', '>', 0);
                                            
                                            if ($color) {
                                                $brandQuery->whereRaw("astitle LIKE ?", ['%'.$color.'%']);
                                            }
                                            
                                            $brandItem = $brandQuery->orderByDesc('FNSKUID')->first();
                                            
                                            if ($brandItem) {
                                                Log::info("Found item with same brand: {$brandItem->FNSKU} with title: {$brandItem->astitle}");
                                                $fnskuToUse = $brandItem->FNSKU;
                                                $status = $brandItem->fnsku_status;
                                            } else {
                                                Log::warning("No suitable FNSKU found for pack item with title: {$title}");
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            // Not a pack item - check if the original FNSKU is available with units
                            if (strtolower($fnskuInfo->fnsku_status) == 'available' && ($fnskuInfo->units > 0)) {
                                $fnskuToUse = $fnskuInfo->FNSKU;
                                $status = $fnskuInfo->fnsku_status;
                                Log::info("Using original FNSKU {$fnskuToUse} with {$fnskuInfo->units} units remaining");
                            } else {
                                // Original FNSKU is unavailable or has 0 units, look for alternative with same ASIN and condition
                                Log::info("Original FNSKU {$originalFnsku} unavailable or has 0 units, looking for alternative");
                                
                                // Simple query for same ASIN and condition
                                $alternativeQuery = DB::table($this->fnskuTable)
                                    ->where('ASIN', $asinToUse)
                                    ->where('grading', $condition)
                                    ->where('fnsku_status', 'available')
                                    ->where('Units', '>', 0)
                                    ->where('amazon_status', 'Existed')
                                    ->where('LimitStatus', 'False');
                                
                                $alternativeFnsku = $alternativeQuery->first();
                                
                                if ($alternativeFnsku) {
                                    $fnskuToUse = $alternativeFnsku->FNSKU;
                                    $status = $alternativeFnsku->fnsku_status;
                                    Log::info("Found alternative FNSKU {$fnskuToUse} for ASIN {$asinToUse} with same condition");
                                } else {
                                    // No match with same condition, try any condition
                                    $anyConditionQuery = DB::table($this->fnskuTable)
                                        ->where('ASIN', $asinToUse)
                                        ->where('fnsku_status', 'available')
                                        ->where('Units', '>', 0)
                                        ->where('amazon_status', 'Existed')
                                        ->where('LimitStatus', 'False');
                                    
                                    $anyConditionFnsku = $anyConditionQuery->first();
                                    
                                    if ($anyConditionFnsku) {
                                        $fnskuToUse = $anyConditionFnsku->FNSKU;
                                        $condition = $anyConditionFnsku->grading; // Update condition
                                        $status = $anyConditionFnsku->fnsku_status;
                                        Log::info("Found alternative FNSKU {$fnskuToUse} for ASIN {$asinToUse} with different condition: {$condition}");
                                    } else {
                                        Log::warning("No available FNSKU found for ASIN {$asinToUse}");
                                    }
                                }
                            }
                        }
                    } else {
                        // Handle case when original FNSKU is not found
                        Log::warning("Original FNSKU {$originalFnsku} not found in database");
                    }
                }
                
                // If we need an alternative FNSKU (original not available or not found)
                // or if this is an unknown serial (no original FNSKU)
                if (!$fnskuToUse && $asinToUse) {
                    // If we don't have condition info from original FNSKU, try to find any matching ASIN
                    $query = DB::table($this->fnskuTable)
                        ->where('ASIN', $asinToUse)
                        ->where('fnsku_status', 'available')
                        ->where('Units', '>', 0)
                        ->where('amazon_status', 'Existed')
                        ->where('LimitStatus', 'False')
                        ->whereRaw("astitle NOT LIKE '%pack%'")  // Prefer non-pack items
                        ->whereRaw("astitle NOT LIKE '%Pack%'")
                        ->whereRaw("astitle NOT LIKE '%PACK%'");
                        
                    // Only filter by condition if we know it
                    if ($condition) {
                        $query->where('grading', $condition);
                    }
                    
                    // Get the first available FNSKU that matches
                    $alternativeFnsku = $query->first();
                    
                    // If no non-pack alternatives found, try any available FNSKU
                    if (!$alternativeFnsku) {
                        Log::info("No non-pack alternative found, checking for any available FNSKU");
                        
                        $query = DB::table($this->fnskuTable)
                            ->where('ASIN', $asinToUse)
                            ->where('fnsku_status', 'available')
                            ->where('Units', '>', 0)
                            ->where('amazon_status', 'Existed')
                            ->where('LimitStatus', 'False');
                            
                        // Only filter by condition if we know it
                        if ($condition) {
                            $query->where('grading', $condition);
                        }
                        
                        $alternativeFnsku = $query->first();
                    }
                    
                    if ($alternativeFnsku) {
                        $fnskuToUse = $alternativeFnsku->FNSKU;
                        $asinToUse = $alternativeFnsku->ASIN;
                        $condition = $alternativeFnsku->grading;
                        $status = $alternativeFnsku->fnsku_status;
                        
                        Log::info("Found alternative FNSKU {$fnskuToUse} for ASIN {$asinToUse} with {$alternativeFnsku->units} units and title: {$alternativeFnsku->astitle}");
                    } else {
                        Log::warning("No available FNSKU found for ASIN {$asinToUse}" . ($condition ? " with condition {$condition}" : ""));
                    }
                }
                
                // If unknown serial and no ASIN/FNSKU, try to get a default FNSKU for tracking
                if (!$isSerialKnown && !$fnskuToUse) {
                    // Try to find a generic FNSKU for unknown items
                    $genericFnsku = DB::table($this->fnskuTable)
                        ->where('fnsku_status', 'available')
                        ->where('Units', '>', 0)
                        ->where('amazon_status', 'Existed')
                        ->where('LimitStatus', 'False')
                        ->first();
                    
                    if ($genericFnsku) {
                        $fnskuToUse = $genericFnsku->FNSKU;
                        $asinToUse = $genericFnsku->ASIN;
                        $condition = $genericFnsku->grading;
                        $status = $genericFnsku->fnsku_status;
                        
                        Log::info("Using generic FNSKU {$fnskuToUse} for unknown serial {$currentSerial}");
                    } else {
                        Log::warning("No available FNSKU found for unknown serial {$currentSerial}");
                    }
                }
                
                // Step 4: If we have a valid FNSKU, proceed with creating the item
                if ($fnskuToUse) {
                    // Generate a new RT counter
                    $maxRt = DB::table($this->productTable)
                        ->max('rtcounter');
                    $newRt = $maxRt + 1;
        
                    // Insert new product record
                    $newItemId = DB::table($this->productTable)->insertGetId([
                        'rtcounter' => $newRt,
                        'rtid' => $rtId,
                        'itemnumber' => $itemNumber,
                        'Username' => $User,
                        'serialnumber' => $currentSerial,
                        'ProductModuleLoc' => $modulelocation,
                        'quantity' => 1,
                        'price' => $price,
                        'lpnID' => $currentLpnId,
                        'warehouselocation' => $location,
                        'FNSKUviewer' => $fnskuToUse,
                        'stockroom_insert_date' => $insertedDate,
                        'validation_status' => 'validated'
                    ]);
                    
                    // Add item to history
                    DB::table($this->itemProcessHistoryTable)->insert([
                        'rtcounter' => $newRt,
                        'employeeName' => $User,
                        'editDate' => $curentDatetimeString,
                        'Module' => 'Scan Return Module',
                        'Action' => 'Scanned and insert to ' . $modulelocation
                    ]);
                    
                    // Now update the FNSKU units
                    $fnskuData = DB::table($this->fnskuTable)
                        ->where('FNSKU', $fnskuToUse)
                        ->first();
                    
                    if ($fnskuData) {
                        // Get current units (with a default of 0 if null)
                        $currentUnits = $fnskuData->Units ?? 0;
                        $newUnits = max(0, $currentUnits - 1); // Subtract 1 but never go below 0
                        
                        // Update FNSKU status based on units
                        $newStatus = ($newUnits <= 0) ? 'Unavailable' : 'available';
                        
                        // Update FNSKU with new unit count and status
                        DB::table($this->fnskuTable)
                            ->where('FNSKU', $fnskuToUse)
                            ->update([
                                'fnsku_status' => $newStatus,
                                'Units' => $newUnits,
                                'productid' => $newItemId
                            ]);
                        
                        Log::info("Updated FNSKU {$fnskuToUse} units from {$currentUnits} to {$newUnits}, status: {$newStatus}");
                    } else {
                        Log::error("Could not find FNSKU {$fnskuToUse} with ASIN {$asinToUse} in the database for unit update");
                    }
                    
                    // Add to created items array
                    $createdItems[] = [
                        'id' => $newItemId,
                        'serial' => $currentSerial,
                        'fnsku' => $fnskuToUse,
                        'asin' => $asinToUse,
                        'location' => $modulelocation,
                        'rt' => $newRt
                    ];
                    
                    $successCount++;
                } else {
                    // No available FNSKU found
                    Log::warning("No available FNSKU found for serial {$currentSerial}");
                }
            } catch (\Exception $e) {
                Log::error('Error processing serial ' . $currentSerial, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Now that all serials are processed, handle switcheru cases
        $switcheruFound = false;

        // Case 1: Unknown serial (not found in database)
        if (!$isSerialKnown) {
            // This is a switcheru with unknown serial - record with blank 'sendserial'
            DB::table('tblswitcherus')->insert([
                'buyer' => $buyerName ?? 'Unknown',
                'sendserial' => '', // Expected serial is blank
                'receiveserial' => $serial // Actual scanned serial
            ]);
            
            Log::info("Unknown serial switcheru recorded: Serial {$serial} not found in database");
            $switcheruFound = true;
        }
        // Case 2: Normal switcheru - when second serial doesn't match expected partner
        else if (!$singleSerialMode && !empty($secondSerial) && !empty($existingItem->serialnumberb)) {
            // Get the original serial pair from database
            $dbSerial1 = $existingItem->serialnumber;
            $dbSerial2 = $existingItem->serialnumberb;
            
            // Get what would have been expected as the second serial based on the first
            $expectedSecondSerial = ($serial === $dbSerial1) ? $dbSerial2 : 
                                  (($serial === $dbSerial2) ? $dbSerial1 : null);
            
            // If we have an expected second serial and it doesn't match what was provided
            if ($expectedSecondSerial && $secondSerial !== $expectedSecondSerial) {
                // This is a switcheru - the second serial doesn't match the expected pair
                DB::table('tblswitcherus')->insert([
                    'buyer' => $buyerName,
                    'sendserial' => $expectedSecondSerial, // Expected second serial
                    'receiveserial' => $secondSerial       // Actual provided second serial
                ]);
                
                Log::info("Switcheru detected: Expected second serial {$expectedSecondSerial}, but received {$secondSerial}");
                $switcheruFound = true;
            }
        }

        // Note: singleSerialMode means the user clicked X and is only returning one serial
        // This is NOT a switcheru, it's a valid return scenario for just one serial
        
        // If we were able to successfully process all serials
        if ($successCount == count($serialsToProcess)) {
            // For known items, update original item status and add history
            if ($isSerialKnown && $originalItem->ProductID) {
                // Insert item process history
                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $rtCounter,
                    'employeeName' => $User,
                    'editDate' => $curentDatetimeString,
                    'Module' => 'Scanner Return Module',
                    'Action' => 'Return Item'
                ]);
    
                // Update original item status
                DB::table($this->productTable)
                    ->where('ProductID', $originalItem->ProductID)
                    ->update([
                        'ProductModuleLoc' => 'Returnlist',
                        'returnstatus' => 'returned'
                    ]);
                
                // Delete any shipping records
                DB::table('tbldoneshipping')
                    ->where('Prodid', $originalItem->ProductID)
                    ->delete();
                
                // Insert history record
                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $originalItem->rtcounter,
                    'employeeName' => $User,
                    'editDate' => $curentDatetimeString,
                    'Module' => 'Returnlist',
                    'Action' => ($singleSerialMode && !empty($existingItem->serialnumberb)) 
                        ? 'Item returned with only one serial and added to Return List' 
                        : 'Item returned and added to Return List',
                ]);
            }
            
            // Create appropriate action message
            $actionMessage = 'Item returned and added to Return List';
            if (!$isSerialKnown) {
                $actionMessage = 'Unknown serial processed as switcheru';
            } else if ($singleSerialMode && !empty($existingItem->serialnumberb)) {
                $actionMessage = 'Item returned with only one serial and added to Return List';
                if ($scannedSerialPosition) {
                    $actionMessage .= " (Returned with " . ($scannedSerialPosition === 'primary' ? 'primary' : 'secondary') . " serial only)";
                }
            } else if ($switcheruFound) {
                $actionMessage = 'Item returned with unexpected second serial (switcheru detected)';
            }
            
            // Build success message based on whether it was a switcheru
            $successMessage = "Successfully processed " . count($serialsToProcess) . " items";
            if ($switcheruFound) {
                $successMessage .= " (Switcheru detected)";
            }
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'item' => [
                    'serial_number' => $serial,
                    'second_serial' => $secondSerial,
                    'location' => $location,
                    'return_id' => $returnId,
                    'lpn_id' => $currentLpnId,
                    'status' => 'returned',
                    'original_location' => $existingItem->ProductModuleLoc,
                    'single_serial_mode' => $singleSerialMode && !empty($existingItem->serialnumberb),
                    'fnsku' => $originalFnsku,
                    'product_id' => $existingItem->ProductID,
                    'created_items' => $createdItems,
                    'switcheru_found' => $switcheruFound,
                    'is_serial_known' => $isSerialKnown
                ]
            ]);
        } else {
            // Not all serials were processed successfully
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error processing some items. Only ' . $successCount . ' out of ' . count($serialsToProcess) . ' serials processed.',
                'reason' => 'fnsku_not_available',
                'items_processed' => $successCount,
                'total_items' => count($serialsToProcess)
            ]);
        }
        
    } catch (\Exception $e) {
        // Roll back transaction on any error
        DB::rollBack();
        Log::error('Unhandled error in processScan', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

        return response()->json([
            'success' => false,
            'message' => 'Error processing scan: ' . $e->getMessage(),
            'reason' => 'server_error'
        ], 500);
    }
}
    
    /**
     * Helper function to get base color from color string
     */
    private function getBaseColor($colorString)
    {
        $baseColors = [
            'black', 'white', 'red', 'blue', 'green', 'yellow', 'orange', 
            'purple', 'pink', 'gray', 'grey', 'brown', 'silver', 'gold'
        ];
        
        $colorString = strtolower(trim($colorString));
        
        foreach ($baseColors as $color) {
            if (strpos($colorString, $color) !== false) {
                return $color;
            }
        }
        
        return $colorString;
    }
}