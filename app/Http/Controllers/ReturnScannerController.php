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
     * Extract base FNSKU from prefixed FNSKU
     */
    private function extractBaseFnsku($fnsku)
    {
        if (empty($fnsku)) {
            return $fnsku;
        }

        if (preg_match('/^C(\d+)(.+)$/', $fnsku, $matches)) {
            return $matches[2];
        }

        return $fnsku;
    }

    /**
     * Display a listing of products in return list with joined LPN data
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $location = $request->input('location', 'Returnlist');
        
        try {
            $products = DB::table($this->productTable . ' as prod')
                ->select(
                    'prod.ProductID',
                    'prod.rtcounter',
                    'prod.rtid',
                    'prod.serialnumber',
                    'prod.serialnumberb',
                    'prod.FNSKUviewer',
                    'prod.warehouselocation',
                    'prod.returnstatus',
                    'prod.img1',
                    'prod.img2',
                    'prod.img3',
                    'prod.img4',
                    'prod.img5',
                    'prod.img6',
                    'prod.img7',
                    'prod.img8',
                    'prod.img9',
                    'prod.img10',
                    'prod.img11',
                    'prod.img12',
                    'prod.img13',
                    'prod.img14',
                    'prod.img15',
                    'tbllpn.LPN',
                    'tbllpn.LPNDATE',
                    'tbllpn.BuyerName',
                    'fnsku.storename',
                    'fnsku.ASIN',
                    'asin.internal as ProductTitle'
                )
                ->leftJoin('tbllpn', 'prod.ProductID', '=', 'tbllpn.ProdID')
                ->leftJoin($this->fnskuTable . ' as fnsku', function ($join) {
                    $join->on(DB::raw("CASE 
                        WHEN prod.FNSKUviewer REGEXP '^C[0-9]+' 
                        THEN SUBSTRING(prod.FNSKUviewer, LOCATE(REGEXP_REPLACE(prod.FNSKUviewer, '^C[0-9]+', ''), prod.FNSKUviewer))
                        ELSE prod.FNSKUviewer 
                    END"), '=', 'fnsku.FNSKU');
                })
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->where('prod.ProductModuleLoc', $location)
                ->when($search, function($query) use ($search) {
                    return $query->where(function($q) use ($search) {
                        $q->where('prod.serialnumber', 'like', "%{$search}%")
                          ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                          ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                          ->orWhere('tbllpn.LPN', 'like', "%{$search}%")
                          ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                          ->orWhere('asin.internal', 'like', "%{$search}%")
                          ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                    });
                })
                ->orderBy('prod.ProductID', 'desc')
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
            
            $isDualSerial = !empty($product->serialnumberb);
            $secondSerial = null;
            $scannedSerialPosition = null;
            
            if ($serial === $product->serialnumber && !empty($product->serialnumberb)) {
                $secondSerial = $product->serialnumberb;
                $scannedSerialPosition = 'primary';
            } else if ($serial === $product->serialnumberb && !empty($product->serialnumber)) {
                $secondSerial = $product->serialnumber;
                $scannedSerialPosition = 'secondary';
            }
            
            $fnskuViewer = $product->FNSKUviewer ?? null;
            
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

  private function getCurrentUserName()
    {
        $user = Auth::user();
        return $user ? ($user->username ?? $user->name ?? 'Unknown') : 'Unknown';
    }
    
 public function processScan(Request $request)
{
    DB::beginTransaction();
    
    try {
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
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->errors()),
                'reason' => 'validation_error'
            ], 422);
        }

        $User = $this->getCurrentUserName();
        $serial = trim($request->input('SerialNumber', ''));
        $secondSerial = trim($request->input('SecondSerial', ''));
        $location = trim($request->input('Location', ''));
        $returnId = trim($request->input('ReturnId', ''));
        $singleSerialMode = (bool)$request->input('SingleSerialMode', false);
        $productId = $request->input('ProductID');
        $fnsku = $request->input('FNSKUviewer');
        $scannedSerialPosition = $request->input('ScannedSerialPosition');

        Log::info("Processing return scan", [
            'serial' => $serial,
            'secondSerial' => $secondSerial,
            'location' => $location
        ]);

        if (empty($serial)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Serial Number must be provided',
                'reason' => 'missing_identifiers'
            ], 422);
        }

        if (!preg_match('/^[a-zA-Z0-9-]+$/', $serial)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid Serial Number format',
                'reason' => 'invalid_serial'
            ]);
        }

        if (!empty($secondSerial) && !preg_match('/^[a-zA-Z0-9-]+$/', $secondSerial)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid Second Serial Number format',
                'reason' => 'invalid_second_serial'
            ]);
        }

        if (!preg_match('/^L\d{3}[A-G]$/i', $location) && $location !== 'Floor' && $location !== 'L800G') {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid Location Format',
                'reason' => 'invalid_location'
            ]);
        }

        $serialsToCheck = [$serial];
        if (!empty($secondSerial) && !$singleSerialMode) {
            $serialsToCheck[] = $secondSerial;
        }
        
        foreach ($serialsToCheck as $serialToCheck) {
            $existingSerialCheck = DB::table($this->productTable)
                ->where(function ($query) use ($serialToCheck) {
                    $query->where('serialnumber', $serialToCheck)
                        ->orWhere('serialnumberb', $serialToCheck);
                })
                ->where('ProductModuleLoc', 'Production Area')
                ->first();
            
            if ($existingSerialCheck) {
                $existingLocation = $existingSerialCheck->ProductModuleLoc;
                $existingWarehouseLocation = $existingSerialCheck->warehouselocation ?? 'Unknown';
                
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Serial {$serialToCheck} already exists in {$existingLocation} at location {$existingWarehouseLocation}",
                    'reason' => 'serial_already_exists',
                    'existingLocation' => $existingLocation,
                    'productId' => $existingSerialCheck->ProductID
                ]);
            }
        }

        if (substr($location, 0, 4) === 'L800') {
            foreach ($serialsToCheck as $serialToCheck) {
                $existingProductionItem = DB::table($this->productTable)
                    ->where(function ($query) use ($serialToCheck) {
                        $query->where('serialnumber', $serialToCheck)
                            ->orWhere('serialnumberb', $serialToCheck);
                    })
                    ->where('ProductModuleLoc', 'Production Area')
                    ->first();
                
                if ($existingProductionItem) {
                    $existingWarehouseLocation = $existingProductionItem->warehouselocation ?? 'Unknown';
                    
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Serial {$serialToCheck} already exists in Production Area at location {$existingWarehouseLocation}",
                        'reason' => 'serial_already_in_production',
                        'productId' => $existingProductionItem->ProductID
                    ]);
                }
            }
        }

        try {
            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            $formatted_datetime = $currentDatetime->format('Y-m-d h:i A');
            $currentDate = date('Y-m-d', strtotime($formatted_datetime));
            $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Log::warning('Error with timezone, using default', ['error' => $e->getMessage()]);
            $currentDatetime = new DateTime();
            $formatted_datetime = $currentDatetime->format('Y-m-d h:i A');
            $currentDate = date('Y-m-d');
            $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
        }

        $isSerialKnown = false;
        $existingItem = null;
        
        if ($productId) {
            $existingItem = DB::table($this->productTable . ' as prod')
                ->select(
                    'prod.*',
                    'fnsku.ASIN',
                    'asin.internal as ProductTitle'
                )
                ->leftJoin($this->fnskuTable . ' as fnsku', function ($join) {
                    $join->on(DB::raw("CASE 
                        WHEN prod.FNSKUviewer REGEXP '^C[0-9]+' 
                        THEN SUBSTRING(prod.FNSKUviewer, LOCATE(REGEXP_REPLACE(prod.FNSKUviewer, '^C[0-9]+', ''), prod.FNSKUviewer))
                        ELSE prod.FNSKUviewer 
                    END"), '=', 'fnsku.FNSKU');
                })
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->where('prod.ProductID', $productId)
                ->whereIn('prod.ProductModuleLoc', ['Stockroom', 'Shipment', 'Soldlist'])
                ->first();
                
            if ($existingItem) {
                $isSerialKnown = true;
            }
        }
        
        if (!$existingItem) {
            $existingItem = DB::table($this->productTable . ' as prod')
                ->select(
                    'prod.*',
                    'fnsku.ASIN',
                    'asin.internal as ProductTitle'
                )
                ->leftJoin($this->fnskuTable . ' as fnsku', function ($join) {
                    $join->on(DB::raw("CASE 
                        WHEN prod.FNSKUviewer REGEXP '^C[0-9]+' 
                        THEN SUBSTRING(prod.FNSKUviewer, LOCATE(REGEXP_REPLACE(prod.FNSKUviewer, '^C[0-9]+', ''), prod.FNSKUviewer))
                        ELSE prod.FNSKUviewer 
                    END"), '=', 'fnsku.FNSKU');
                })
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->where(function ($query) use ($serial, $secondSerial) {
                    $query->where('prod.serialnumber', $serial)
                        ->orWhere('prod.serialnumberb', $serial);
                    
                    if (!empty($secondSerial)) {
                        $query->orWhere('prod.serialnumber', $secondSerial)
                            ->orWhere('prod.serialnumberb', $secondSerial);
                    }
                })
                ->whereIn('prod.ProductModuleLoc', ['Stockroom', 'Shipment', 'Soldlist'])
                ->first();
                
            if ($existingItem) {
                $isSerialKnown = true;
            }
        }

        if (!$existingItem) {
            $existingItem = (object)[
                'ProductID' => null,
                'rtcounter' => null,
                'rtid' => null,
                'itemnumber' => null,
                'price' => null,
                'costumer_name' => 'Unknown',
                'ASIN' => null,
                'FNSKUviewer' => null,
                'serialnumber' => null,
                'serialnumberb' => null,
                'ProductModuleLoc' => null,
                'ProductTitle' => null
            ];
        }

        if ($isSerialKnown && !empty($existingItem->serialnumberb) && !$singleSerialMode) {
            $dbSerial1 = $existingItem->serialnumber;
            $dbSerial2 = $existingItem->serialnumberb;
            
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
        }

        $serialsToProcess = [];
        if (!empty($secondSerial) && !$singleSerialMode) {
            $serialsToProcess[] = $serial;
            $serialsToProcess[] = $secondSerial;
        } else {
            $serialsToProcess[] = $serial;
        }

        $originalItem = $existingItem;
        $rtCounter = $existingItem->rtcounter ?? null;
        $rtId = $existingItem->rtid ?? null;
        $itemNumber = $existingItem->itemnumber ?? null;
        $price = $existingItem->price ?? null;
        $buyerName = $existingItem->costumer_name ?? null;
        $originalAsin = $existingItem->ASIN ?? null;
        $originalFnsku = $existingItem->FNSKUviewer ?? null;

        $lpnInsertion = DB::table('tbllpn')->insertGetId([
            'SERIAL' => $serial,
            'LPN' => $returnId,
            'LPNDATE' => $curentDatetimeString,
            'ProdID' => $originalItem->ProductID,
            'BuyerName' => $buyerName ?? 'Unknown'
        ]);
        
        $currentLpnId = $lpnInsertion;
        
        $successCount = 0;
        $createdItems = [];
        
        foreach ($serialsToProcess as $currentSerial) {
            if (substr($location, 0, 4) === 'L800') {
                $modulelocation = 'Production Area';
                $insertedDate = null;
            } else {
                $modulelocation = 'Stockroom';
                $insertedDate = $curentDatetimeString;
            }
            
            $asinToUse = $originalAsin;
            $baseFnskuToUse = null;
            $actualFnskuToUse = null;
            $condition = null;
            $storename = null;
            
            try {
                if ($originalFnsku) {
                    $baseFnsku = $this->extractBaseFnsku($originalFnsku);
                    
                    // ✅ Get FNSKU info WITH quantityinside and color
                    $fnskuInfo = DB::table($this->fnskuTable . ' as fnsku')
                        ->select('fnsku.*', 'asin.quantityinside', 'asin.color')
                        ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                        ->where('fnsku.FNSKU', $baseFnsku)
                        ->first();
  
                    if ($fnskuInfo) {
                        $asinToUse = $fnskuInfo->ASIN ?? null;
                        $condition = $fnskuInfo->grading ?? null;
                        $storename = $fnskuInfo->storename ?? null;
                        $OriginalFnskuUnitCount = $fnskuInfo->Units ?? 0;
                        
                        // ✅ Get quantityinside and color from database
                        $quantityInside = $fnskuInfo->quantityinside ?? 1;
                        $color = $fnskuInfo->color ?? null;
                        
                        Log::info("FNSKU Info", [
                            'FNSKU' => $baseFnsku,
                            'ASIN' => $asinToUse,
                            'quantityinside' => $quantityInside,
                            'color' => $color,
                            'condition' => $condition
                        ]);
                        
                        // ✅ PACK DETECTION using quantityinside column
                        if ($quantityInside > 1) {
                            Log::info("Pack detected: ASIN {$asinToUse} has {$quantityInside} items inside");
                            
                            // Find single-unit equivalent
                            $query = DB::table($this->fnskuTable . ' as fnsku')
                                ->select('fnsku.*')
                                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                                ->where('fnsku.fnsku_status', 'available')
                                ->where('fnsku.amazon_status', 'Existed')
                                ->where('fnsku.LimitStatus', 'False')
                                ->where('fnsku.grading', $condition)
                                ->where('fnsku.Units', '>', 0)
                                ->where('asin.quantityinside', 1); // ✅ Single units only
                            
                            // ✅ Match color if exists
                            if ($color) {
                                $query->where('asin.color', $color);
                                Log::info("Filtering by color: {$color}");
                            }
                            
                            // Try same ASIN base
                            $asinBase = preg_replace('/-pack\d*$/i', '', $asinToUse);
                            $asinBase = preg_replace('/-\d+$/i', '', $asinBase);
                            $query->where('fnsku.ASIN', 'LIKE', $asinBase . '%');
                            
                            $singleItem = $query->orderByDesc('fnsku.FNSKUID')->first();
                            
                            if ($singleItem) {
                                $baseFnskuToUse = $singleItem->FNSKU;
                                $asinToUse = $singleItem->ASIN;
                                $condition = $singleItem->grading;
                                $storename = $singleItem->storename;
                                Log::info("✅ Found single-unit FNSKU: {$baseFnskuToUse}");
                            } else {
                                // Fallback: any single unit with same condition/color
                                $fallbackQuery = DB::table($this->fnskuTable . ' as fnsku')
                                    ->select('fnsku.*')
                                    ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                                    ->where('fnsku.fnsku_status', 'available')
                                    ->where('fnsku.amazon_status', 'Existed')
                                    ->where('fnsku.LimitStatus', 'False')
                                    ->where('fnsku.grading', $condition)
                                    ->where('fnsku.Units', '>', 0)
                                    ->where('asin.quantityinside', 1);
                                
                                if ($color) {
                                    $fallbackQuery->where('asin.color', $color);
                                }
                                
                                $fallbackItem = $fallbackQuery->orderByDesc('fnsku.FNSKUID')->first();
                                
                                if ($fallbackItem) {
                                    $baseFnskuToUse = $fallbackItem->FNSKU;
                                    $asinToUse = $fallbackItem->ASIN;
                                    $condition = $fallbackItem->grading;
                                    $storename = $fallbackItem->storename;
                                    Log::info("✅ Found fallback FNSKU: {$baseFnskuToUse}");
                                } else {
                                    Log::warning("❌ No single-unit FNSKU found");
                                }
                            }
                        } else {
                            // ✅ SINGLE UNIT PRODUCT
                            if (strtolower($fnskuInfo->fnsku_status ?? '') == 'available' && ($OriginalFnskuUnitCount > 0)) {
                                $baseFnskuToUse = $fnskuInfo->FNSKU;
                                $storename = $fnskuInfo->storename;
                                Log::info("Using original FNSKU {$baseFnskuToUse}");
                            } else {
                                // Find alternative
                                $alternativeQuery = DB::table($this->fnskuTable)
                                    ->where('ASIN', $asinToUse)
                                    ->where('grading', $condition)
                                    ->where('fnsku_status', 'available')
                                    ->where('Units', '>', 0)
                                    ->where('amazon_status', 'Existed')
                                    ->where('LimitStatus', 'False');
                                
                                $alternativeFnsku = $alternativeQuery->first();
                                
                                if ($alternativeFnsku) {
                                    $baseFnskuToUse = $alternativeFnsku->FNSKU;
                                    $storename = $alternativeFnsku->storename;
                                } else {
                                    $anyConditionQuery = DB::table($this->fnskuTable)
                                        ->where('ASIN', $asinToUse)
                                        ->where('fnsku_status', 'available')
                                        ->where('Units', '>', 0)
                                        ->where('amazon_status', 'Existed')
                                        ->where('LimitStatus', 'False');
                                    
                                    $anyConditionFnsku = $anyConditionQuery->first();
                                    
                                    if ($anyConditionFnsku) {
                                        $baseFnskuToUse = $anyConditionFnsku->FNSKU;
                                        $condition = $anyConditionFnsku->grading;
                                        $storename = $anyConditionFnsku->storename;
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Fallback if no FNSKU found yet
                if (!$baseFnskuToUse && $asinToUse) {
                    $query = DB::table($this->fnskuTable . ' as fnsku')
                        ->select('fnsku.*')
                        ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                        ->where('fnsku.ASIN', $asinToUse)
                        ->where('fnsku.fnsku_status', 'available')
                        ->where('fnsku.Units', '>', 0)
                        ->where('fnsku.amazon_status', 'Existed')
                        ->where('fnsku.LimitStatus', 'False')
                        ->where('asin.quantityinside', 1);
                        
                    if ($condition) {
                        $query->where('fnsku.grading', $condition);
                    }
                    
                    $alternativeFnsku = $query->first();
                    
                    if (!$alternativeFnsku) {
                        $query = DB::table($this->fnskuTable)
                            ->where('ASIN', $asinToUse)
                            ->where('fnsku_status', 'available')
                            ->where('Units', '>', 0)
                            ->where('amazon_status', 'Existed')
                            ->where('LimitStatus', 'False');
                            
                        if ($condition) {
                            $query->where('grading', $condition);
                        }
                        
                        $alternativeFnsku = $query->first();
                    }
                    
                    if ($alternativeFnsku) {
                        $baseFnskuToUse = $alternativeFnsku->FNSKU;
                        $asinToUse = $alternativeFnsku->ASIN;
                        $condition = $alternativeFnsku->grading;
                        $storename = $alternativeFnsku->storename;
                    }
                }
                
                // Generic FNSKU for unknown serials
                if (!$isSerialKnown && !$baseFnskuToUse) {
                    $genericFnsku = DB::table($this->fnskuTable)
                        ->where('fnsku_status', 'available')
                        ->where('Units', '>', 0)
                        ->where('amazon_status', 'Existed')
                        ->where('LimitStatus', 'False')
                        ->first();
                    
                    if ($genericFnsku) {
                        $baseFnskuToUse = $genericFnsku->FNSKU;
                        $asinToUse = $genericFnsku->ASIN;
                        $condition = $genericFnsku->grading;
                        $storename = $genericFnsku->storename;
                    }
                }
                
                // ✅ GENERATE PREFIXED FNSKU
                if ($baseFnskuToUse) {
                    $fnskuGenerationInfo = $this->getNextAvailableFnsku(
                        $baseFnskuToUse,
                        $asinToUse,
                        $condition,
                        $storename
                    );

                    $actualFnskuToUse = $fnskuGenerationInfo['actual_fnsku'];

                    Log::info('✅ Generated prefixed FNSKU', [
                        'base_fnsku' => $baseFnskuToUse,
                        'actual_fnsku' => $actualFnskuToUse,
                        'times_used' => $fnskuGenerationInfo['times_used'],
                        'remaining_units' => $fnskuGenerationInfo['remaining_units'],
                    ]);

                    $maxRt = DB::table($this->productTable)->max('rtcounter');
                    $newRt = $maxRt + 1;
        
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
                        'FNSKUviewer' => $actualFnskuToUse, // ✅ Prefixed FNSKU
                        'stockroom_insert_date' => $insertedDate,
                        'validation_status' => 'validated'
                    ]);
                    
                    DB::table($this->itemProcessHistoryTable)->insert([
                        'rtcounter' => $newRt,
                        'employeeName' => $User,
                        'editDate' => $curentDatetimeString,
                        'Module' => 'Scan Return Module',
                        'Action' => 'Scanned and insert to ' . $modulelocation
                    ]);
                    
                    // ✅ UPDATE FNSKU UNITS
                    $becameUnavailable = $this->updateFnskuUnits(
                        $baseFnskuToUse,
                        $asinToUse,
                        $condition,
                        $storename
                    );
                    
                    $createdItems[] = [
                        'id' => $newItemId,
                        'serial' => $currentSerial,
                        'base_fnsku' => $baseFnskuToUse,
                        'actual_fnsku' => $actualFnskuToUse,
                        'asin' => $asinToUse,
                        'location' => $modulelocation,
                        'rt' => $newRt
                    ];
                    
                    $successCount++;
                } else {
                    Log::error("❌ No FNSKU found for serial: {$currentSerial}");
                }
            } catch (\Exception $e) {
                Log::error('Error processing serial ' . $currentSerial, [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // PREPARE SWITCHERU DATA
        $switcheruData = null;
        $switcheruFound = false;

        if (!$isSerialKnown) {
            $switcheruData = [
                'buyer' => $buyerName ?? 'Unknown',
                'sendserial' => '',
                'receiveserial' => $serial,
                'created_at' => $curentDatetimeString
            ];
            $switcheruFound = true;
        }
        else if (!$singleSerialMode && !empty($secondSerial) && !empty($existingItem->serialnumberb)) {
            $dbSerial1 = $existingItem->serialnumber;
            $dbSerial2 = $existingItem->serialnumberb;
            
            $expectedSecondSerial = ($serial === $dbSerial1) ? $dbSerial2 : 
                                  (($serial === $dbSerial2) ? $dbSerial1 : null);
            
            if ($expectedSecondSerial && $secondSerial !== $expectedSecondSerial) {
                $switcheruData = [
                    'buyer' => $buyerName,
                    'sendserial' => $expectedSecondSerial,
                    'receiveserial' => $secondSerial,
                    'created_at' => $curentDatetimeString
                ];
                $switcheruFound = true;
            }
        }
        
        // COMMIT IF SUCCESSFUL
        if ($successCount == count($serialsToProcess)) {
            if ($isSerialKnown && $originalItem->ProductID) {
                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $rtCounter,
                    'employeeName' => $User,
                    'editDate' => $curentDatetimeString,
                    'Module' => 'Scanner Return Module',
                    'Action' => 'Return Item'
                ]);
    
                DB::table($this->productTable)
                    ->where('ProductID', $originalItem->ProductID)
                    ->update([
                        'ProductModuleLoc' => 'Returnlist',
                        'returnstatus' => 'returned'
                    ]);

                if ($originalFnsku) {
                    $baseFnsku = $this->extractBaseFnsku($originalFnsku);
                    
                    // ✅ Restore units using helper method
                    $this->returnFnskuUnits($baseFnsku);
                    
                    Log::info("Restored units to original FNSKU {$baseFnsku}");
                }
                
                DB::table('tbldoneshipping')
                    ->where('Prodid', $originalItem->ProductID)
                    ->delete();
                
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
            
            DB::commit();
            
            if ($switcheruFound && $switcheruData) {
                try {
                    DB::table('tblswitcherus')->insert($switcheruData);
                    Log::info("✓ Switcheru inserted", $switcheruData);
                } catch (\Exception $e) {
                    Log::error("✗ Failed to insert switcheru", ['error' => $e->getMessage()]);
                }
            }
            
            $successMessage = "Successfully processed " . count($serialsToProcess) . " items";
            if ($switcheruFound) {
                $successMessage .= " (Switcheru detected)";
            }
            
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
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing items. Only ' . $successCount . ' out of ' . count($serialsToProcess) . ' processed.',
                'reason' => 'fnsku_not_available',
                'items_processed' => $successCount,
                'total_items' => count($serialsToProcess)
            ]);
        }
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Unhandled error in processScan', ['error' => $e->getMessage()]);

        return response()->json([
            'success' => false,
            'message' => 'Error processing scan: ' . $e->getMessage(),
            'reason' => 'server_error'
        ], 500);
    }
}

/**
 * Get the next available FNSKU with usage prefix
 * SIMPLIFIED VERSION - NO originalUnits column needed
 */
private function getNextAvailableFnsku($baseFnsku, $asin, $grading, $storename)
{
    try {
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->first();

        if (!$fnskuRecord) {
            Log::warning("FNSKU not found in database", [
                'base_fnsku' => $baseFnsku,
                'asin' => $asin,
                'grading' => $grading,
                'storename' => $storename
            ]);
            
            return [
                'actual_fnsku' => $baseFnsku,
                'times_used' => 0,
                'remaining_units' => 0
            ];
        }

        $currentUnits = $fnskuRecord->Units;

        // ✅ COUNT ACTIVE uses of this FNSKU in tblproduct
        // Exclude: Soldlist, Returnlist, Merged, RTS (these are "out of circulation")
        $timesUsed = DB::table($this->productTable)
            ->where(function($query) use ($baseFnsku) {
                $query->where('FNSKUviewer', $baseFnsku)
                      ->orWhere('FNSKUviewer', 'LIKE', 'C%' . $baseFnsku);
            })
            ->whereNotIn('ProductModuleLoc', ['Shipment','Soldlist', 'Returnlist', 'Merged', 'RTS'])
            ->count();

        // Generate prefixed FNSKU based on active count
        if ($timesUsed == 0) {
            $actualFnsku = $baseFnsku; // First active use - no prefix
        } else {
            $actualFnsku = "C{$timesUsed}{$baseFnsku}"; // Add prefix based on count
        }

        Log::info("Generated FNSKU prefix", [
            'base_fnsku' => $baseFnsku,
            'actual_fnsku' => $actualFnsku,
            'current_units' => $currentUnits,
            'times_used' => $timesUsed,
            'excluded_locations' => 'Soldlist, Returnlist, Merged, RTS'
        ]);

        return [
            'actual_fnsku' => $actualFnsku,
            'times_used' => $timesUsed,
            'remaining_units' => $currentUnits
        ];

    } catch (\Exception $e) {
        Log::error("Error in getNextAvailableFnsku: " . $e->getMessage(), [
            'base_fnsku' => $baseFnsku,
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'actual_fnsku' => $baseFnsku,
            'times_used' => 0,
            'remaining_units' => 0
        ];
    }
}

/**
 * Update FNSKU units (decrement by 1)
 */
private function updateFnskuUnits($baseFnsku, $asin, $grading, $storename)
{
    try {
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->first();

        if (!$fnskuRecord) {
            return false;
        }

        $currentUnits = $fnskuRecord->Units ?? 0;
        $newUnits = max(0, $currentUnits - 1);
        $newStatus = ($newUnits <= 0) ? 'Unavailable' : 'available';

        DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->update([
                'Units' => $newUnits,
                'fnsku_status' => $newStatus
            ]);

        return ($newStatus === 'Unavailable');

    } catch (\Exception $e) {
        Log::error("Error updating FNSKU units: " . $e->getMessage());
        return false;
    }
}

/**
 * Return FNSKU units (increment by 1)
 */
private function returnFnskuUnits($fnskuViewer)
{
    try {
        $baseFnsku = $this->extractBaseFnsku($fnskuViewer);

        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->first();

        if (!$fnskuRecord) {
            return false;
        }

        $currentUnits = $fnskuRecord->Units ?? 0;
        $newUnits = $currentUnits + 1;

        DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->update([
                'Units' => $newUnits,
                'fnsku_status' => 'available'
            ]);

        return true;

    } catch (\Exception $e) {
        Log::error("Error returning FNSKU units: " . $e->getMessage());
        return false;
    }
}
}