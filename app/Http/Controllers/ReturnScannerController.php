<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
                ->whereIn('ProductModuleLoc', ['Stockroom', 'Shipment', 'Soldlist','Returnlist'])
                ->first();
            
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Serial number not found or not in a valid location'
                ]);
            }
           // ✅ FIXED: Check if serialnumberb is valid (not empty, not null, not "N/A")
            $isValidSecondSerial = !empty($product->serialnumberb) && 
                              trim($product->serialnumberb) !== '' &&
                              strtoupper(trim($product->serialnumberb)) !== 'N/A';
            
            $isDualSerial = $isValidSecondSerial;
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
    
/**
 * Find related ASINs with full recursive search
 */
private function findRelatedAsins($searchTerm)
{
    $cacheKey = "related_asins_" . md5($searchTerm);

    return Cache::remember($cacheKey, 300, function () use ($searchTerm) {
        $related = [$searchTerm];
        $checked = [];
        $maxIterations = 50;
        $iterations = 0;

        while (!empty($related) && $iterations < $maxIterations) {
            $asinToCheck = array_pop($related);
            if (in_array($asinToCheck, $checked))
                continue;
            $checked[] = $asinToCheck;

            $results = DB::table($this->asinTable)
                ->select('ASIN', 'ParentAsin', 'CousinASIN', 'UpgradeASIN', 'GrandASIN')
                ->where(function ($query) use ($asinToCheck) {
                    $query->where('ASIN', $asinToCheck)
                        ->orWhere('ParentAsin', $asinToCheck)
                        ->orWhere('CousinASIN', $asinToCheck)
                        ->orWhere('UpgradeASIN', $asinToCheck)
                        ->orWhere('GrandASIN', $asinToCheck)
                        ->orWhere('internal', $asinToCheck);
                })
                ->get();

            foreach ($results as $row) {
                foreach (['ASIN', 'ParentAsin', 'CousinASIN', 'UpgradeASIN', 'GrandASIN'] as $field) {
                    $val = $row->$field ?? '';
                    if (!empty($val) && !in_array($val, $checked) && !in_array($val, $related)) {
                        $related[] = $val;
                    }
                }
            }

            $iterations++;
        }

        return $checked;
    });
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
                'ScannedSecondarySerial' => 'nullable|string',
                'Images' => 'nullable|array',
                'Images.*.data' => 'nullable|string',
                'Images.*.serialIndex' => 'nullable|integer',
                'Images.*.serial' => 'nullable|string',
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
        $images = $request->input('Images', []);

        Log::info("Processing return scan", [
            'serial' => $serial,
            'secondSerial' => $secondSerial,
            'location' => $location,
            'imagesCount' => count($images)
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
            
            if (strtoupper(trim($dbSerial2)) === 'N/A') {
                // Treat as single serial
            } else {
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
        
        // ========== MODIFIED FNSKU LOGIC WITH STRICT COLOR MATCHING ==========
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
                    
                    $fnskuInfo = DB::table($this->fnskuTable . ' as fnsku')
                        ->select('fnsku.*', 'asin.quantityinside', 'asin.color')
                        ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                        ->where('fnsku.FNSKU', $baseFnsku)
                        ->first();

                    if (!$fnskuInfo) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "FNSKU '{$baseFnsku}' not found in database",
                            'reason' => 'fnsku_not_found',
                            'details' => [
                                'fnsku' => $baseFnsku,
                                'serial' => $currentSerial
                            ]
                        ]);
                    }
                    
                    $asinToUse = $fnskuInfo->ASIN ?? null;
                    $condition = $fnskuInfo->grading ?? null;
                    $storename = $fnskuInfo->storename ?? null;
                    $OriginalFnskuUnitCount = $fnskuInfo->Units ?? 0;
                    $quantityInside = $fnskuInfo->quantityinside ?? 1;
                    $color = $fnskuInfo->color ?? null;
                    
                    // ✅ STRICT COLOR VALIDATION
                    if (empty($color) || $color === null || trim($color) === '') {
                        Log::warning("FNSKU has no color defined", [
                            'fnsku' => $baseFnsku,
                            'asin' => $asinToUse,
                            'serial' => $currentSerial
                        ]);
                        
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot process return: Product color is not defined in the system for FNSKU '{$baseFnsku}'",
                            'reason' => 'missing_color',
                            'details' => [
                                'fnsku' => $baseFnsku,
                                'asin' => $asinToUse,
                                'serial' => $currentSerial,
                                'condition' => $condition
                            ]
                        ]);
                    }
                    
                    // ✅ STRICT STORE NAME VALIDATION
                    if (empty($storename) || $storename === null || trim($storename) === '') {
                        Log::warning("FNSKU has no store name defined", [
                            'fnsku' => $baseFnsku,
                            'asin' => $asinToUse,
                            'serial' => $currentSerial
                        ]);
                        
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot process return: Store name is not defined in the system for FNSKU '{$baseFnsku}'",
                            'reason' => 'missing_storename',
                            'details' => [
                                'fnsku' => $baseFnsku,
                                'asin' => $asinToUse,
                                'serial' => $currentSerial,
                                'condition' => $condition,
                                'color' => $color
                            ]
                        ]);
                    }
                    
                    Log::info("FNSKU Info", [
                        'FNSKU' => $baseFnsku,
                        'ASIN' => $asinToUse,
                        'quantityinside' => $quantityInside,
                        'color' => $color,
                        'condition' => $condition,
                        'storename' => $storename,
                        'units' => $OriginalFnskuUnitCount
                    ]);
                    
                    // ✅ MULTI-PACK CONVERSION WITH STRICT COLOR MATCHING
                    if ($quantityInside > 1) {
                        Log::info("Pack detected: ASIN {$asinToUse} has {$quantityInside} items inside");
                        
                        $asinBase = preg_replace('/-pack\d*$/i', '', $asinToUse);
                        $asinBase = preg_replace('/-\d+$/i', '', $asinBase);
                        
                        // PRIMARY: Try exact ASIN pattern match with STRICT color and store
                        $query = DB::table($this->fnskuTable . ' as fnsku')
                            ->select('fnsku.*')
                            ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                            ->where('fnsku.fnsku_status', 'available')
                            ->where('fnsku.amazon_status', 'Existed')
                            ->where('fnsku.LimitStatus', 'False')
                            ->where('fnsku.grading', $condition)
                            ->where('fnsku.storename', $storename)  // ✅ STRICT STORE MATCH
                            ->where('fnsku.Units', '>', 0)
                            ->where('asin.quantityinside', 1)
                            ->where('fnsku.ASIN', 'LIKE', $asinBase . '%')
                            ->where('asin.color', $color);  // ✅ ALWAYS REQUIRED
                        
                        $singleItem = $query->orderByDesc('fnsku.FNSKUID')->first();
                        
                        // FALLBACK: Search in related ASINs with STRICT color and store
                        if (!$singleItem) {
                            Log::info("No direct match found, searching related ASINs", [
                                'original_asin' => $asinToUse,
                                'required_color' => $color,
                                'required_condition' => $condition,
                                'required_storename' => $storename,
                                'required_quantity' => 1
                            ]);
                            
                            $relatedAsins = $this->findRelatedAsins($asinToUse);
                            
                            if (!empty($relatedAsins)) {
                                $relatedQuery = DB::table($this->fnskuTable . ' as fnsku')
                                    ->select('fnsku.*')
                                    ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                                    ->whereIn('fnsku.ASIN', $relatedAsins)
                                    ->where('fnsku.fnsku_status', 'available')
                                    ->where('fnsku.amazon_status', 'Existed')
                                    ->where('fnsku.LimitStatus', 'False')
                                    ->where('fnsku.grading', $condition)
                                    ->where('fnsku.storename', $storename)  // ✅ STRICT STORE MATCH
                                    ->where('fnsku.Units', '>', 0)
                                    ->where('asin.quantityinside', 1)
                                    ->where('asin.color', $color);  // ✅ ALWAYS REQUIRED
                                
                                $singleItem = $relatedQuery->orderByDesc('fnsku.FNSKUID')->first();
                                
                                if ($singleItem) {
                                    Log::info("✅ Found single-unit FNSKU in related ASINs", [
                                        'found_fnsku' => $singleItem->FNSKU,
                                        'found_asin' => $singleItem->ASIN,
                                        'matched_color' => $color,
                                        'matched_storename' => $storename
                                    ]);
                                }
                            }
                        }
                        
                        if (!$singleItem) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Cannot process multi-pack: No single-unit FNSKU available for ASIN '{$asinToUse}'" . 
                                           " with color '{$color}', grading '{$condition}', and store '{$storename}' (checked related ASINs too)",
                                'reason' => 'no_single_unit_available',
                                'details' => [
                                    'original_asin' => $asinToUse,
                                    'original_fnsku' => $baseFnsku,
                                    'quantity_inside' => $quantityInside,
                                    'required_color' => $color,
                                    'required_grading' => $condition,
                                    'required_storename' => $storename,
                                    'asin_pattern' => $asinBase,
                                    'serial' => $currentSerial,
                                    'related_asins_checked' => count($relatedAsins ?? [])
                                ]
                            ]);
                        }
                        
                        $baseFnskuToUse = $singleItem->FNSKU;
                        $asinToUse = $singleItem->ASIN;
                        $condition = $singleItem->grading;
                        $storename = $singleItem->storename;
                        Log::info("✅ Found single-unit FNSKU: {$baseFnskuToUse} with color: {$color} and store: {$storename}");
                        
                    } else {
                        // ✅ SINGLE UNIT WITH STRICT COLOR AND STORE FALLBACK
                        if (strtolower($fnskuInfo->fnsku_status ?? '') !== 'available' || $OriginalFnskuUnitCount <= 0) {
                            Log::info("Original FNSKU not available, searching related ASINs", [
                                'original_fnsku' => $baseFnsku,
                                'original_asin' => $asinToUse,
                                'required_color' => $color,
                                'required_storename' => $storename,
                                'status' => $fnskuInfo->fnsku_status,
                                'units' => $OriginalFnskuUnitCount
                            ]);
                            
                            // Search in related ASINs with STRICT color and store
                            $relatedAsins = $this->findRelatedAsins($asinToUse);
                            $foundInRelated = false;
                            
                            if (!empty($relatedAsins)) {
                                $relatedQuery = DB::table($this->fnskuTable . ' as fnsku')
                                    ->select('fnsku.*')
                                    ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                                    ->whereIn('fnsku.ASIN', $relatedAsins)
                                    ->where('fnsku.fnsku_status', 'available')
                                    ->where('fnsku.amazon_status', 'Existed')
                                    ->where('fnsku.LimitStatus', 'False')
                                    ->where('fnsku.grading', $condition)
                                    ->where('fnsku.storename', $storename)  // ✅ STRICT STORE MATCH
                                    ->where('fnsku.Units', '>', 0)
                                    ->where('asin.quantityinside', $quantityInside)
                                    ->where('asin.color', $color);  // ✅ ALWAYS REQUIRED
                                
                                $relatedFnsku = $relatedQuery->orderByDesc('fnsku.FNSKUID')->first();
                                
                                if ($relatedFnsku) {
                                    $baseFnskuToUse = $relatedFnsku->FNSKU;
                                    $asinToUse = $relatedFnsku->ASIN;
                                    $storename = $relatedFnsku->storename;
                                    $foundInRelated = true;
                                    
                                    Log::info("✅ Found available FNSKU in related ASINs", [
                                        'found_fnsku' => $baseFnskuToUse,
                                        'found_asin' => $asinToUse,
                                        'matched_color' => $color,
                                        'matched_storename' => $storename,
                                        'units' => $relatedFnsku->Units
                                    ]);
                                }
                            }
                            
                            if (!$foundInRelated) {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'message' => "FNSKU '{$baseFnsku}' is not available" . 
                                               " (Status: " . ($fnskuInfo->fnsku_status ?? 'unknown') . 
                                               ", Units: {$OriginalFnskuUnitCount}). No available FNSKU found in related ASINs with matching color '{$color}', condition '{$condition}', and store '{$storename}'.",
                                    'reason' => 'fnsku_not_available',
                                    'details' => [
                                        'fnsku' => $baseFnsku,
                                        'asin' => $asinToUse,
                                        'current_status' => $fnskuInfo->fnsku_status ?? 'unknown',
                                        'current_units' => $OriginalFnskuUnitCount,
                                        'required_color' => $color,
                                        'required_grading' => $condition,
                                        'required_storename' => $storename,
                                        'serial' => $currentSerial,
                                        'related_asins_checked' => count($relatedAsins ?? [])
                                    ]
                                ]);
                            }
                        } else {
                            $baseFnskuToUse = $fnskuInfo->FNSKU;
                            $storename = $fnskuInfo->storename;
                            Log::info("✅ Using original FNSKU {$baseFnskuToUse} with color {$color} and store {$storename} (Units: {$OriginalFnskuUnitCount})");
                        }
                    }
                } else {
                    // ❌ NO FNSKU - REJECT
                    if ($isSerialKnown) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot process: Original product has no FNSKU assigned",
                            'reason' => 'missing_fnsku',
                            'details' => [
                                'serial' => $currentSerial,
                                'product_id' => $productId,
                                'asin' => $originalAsin
                            ]
                        ]);
                    }
                    
                    // ❌ UNKNOWN SERIAL - REJECT
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot process unknown serial '{$currentSerial}': No FNSKU information available. Please verify the product exists in the system.",
                        'reason' => 'unknown_serial_no_fnsku',
                        'details' => [
                            'serial' => $currentSerial
                        ]
                    ]);
                }
                
                // ✅ AT THIS POINT, WE HAVE A VALID FNSKU WITH VERIFIED COLOR
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
                        'color' => $color,
                        'condition' => $condition,
                        'storename' => $storename
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
                        'FNSKUviewer' => $actualFnskuToUse,
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
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Failed to assign FNSKU for serial: {$currentSerial}",
                        'reason' => 'fnsku_assignment_failed',
                        'details' => [
                            'serial' => $currentSerial
                        ]
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error processing serial ' . $currentSerial, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Error processing serial: ' . $e->getMessage(),
                    'reason' => 'processing_error',
                    'details' => [
                        'serial' => $currentSerial
                    ]
                ]);
            }
        }
        // ========== END OF MODIFIED FNSKU LOGIC ==========
        
        // Switcheru detection
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
                    'switcheru_found' => $switcheruFound,
                    'is_serial_known' => $isSerialKnown
                ],
                'createdItems' => $createdItems,
                'imagesReceived' => count($images)
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
        // ✅ Lock FNSKU record
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $baseFnsku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->lockForUpdate()
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

        if ($currentUnits <= 0) {
            throw new \Exception("No remaining units for FNSKU: {$baseFnsku}");
        }

        // ✅ Get ALL active FNSKUs (with and without prefix)
        $activeFnskus = DB::table($this->productTable)
            ->select('FNSKUviewer')
            ->where(function($query) use ($baseFnsku) {
                $query->where('FNSKUviewer', $baseFnsku)
                      ->orWhere('FNSKUviewer', 'LIKE', 'C%' . $baseFnsku);
            })
            ->whereNotIn('ProductModuleLoc', ['Shipment', 'Soldlist', 'Returnlist', 'Merged', 'RTS'])
            ->lockForUpdate()
            ->pluck('FNSKUviewer')
            ->toArray();

        Log::info("Active FNSKUs found", [
            'base_fnsku' => $baseFnsku,
            'active_fnskus' => $activeFnskus,
            'total_units' => $currentUnits
        ]);

        // ✅ Extract used prefixes
        $usedPrefixes = [];
        
        foreach ($activeFnskus as $fnsku) {
            if ($fnsku === $baseFnsku) {
                // Base FNSKU (no prefix) is used
                $usedPrefixes[] = 0;
            } elseif (preg_match('/^C(\d+)' . preg_quote($baseFnsku, '/') . '$/', $fnsku, $matches)) {
                // Extract prefix number (e.g., "C3" -> 3)
                $usedPrefixes[] = (int)$matches[1];
            }
        }

        sort($usedPrefixes);

        Log::info("Used prefixes", [
            'base_fnsku' => $baseFnsku,
            'used_prefixes' => $usedPrefixes,
            'max_allowed' => $currentUnits - 1
        ]);

        // ✅ Find first UNUSED prefix
        $nextPrefix = null;
        $maxPrefix = $currentUnits - 1; // If Units = 7, max prefix is C6 (0-6 = 7 total)

        for ($i = 0; $i <= $maxPrefix; $i++) {
            if (!in_array($i, $usedPrefixes)) {
                $nextPrefix = $i;
                break;
            }
        }

        if ($nextPrefix === null) {
            // All prefixes are used
            throw new \Exception("All available prefixes exhausted for FNSKU: {$baseFnsku} (Units: {$currentUnits})");
        }

        // ✅ Generate FNSKU with correct prefix
        if ($nextPrefix === 0) {
            $actualFnsku = $baseFnsku; // No prefix
        } else {
            $actualFnsku = "C{$nextPrefix}{$baseFnsku}";
        }

        Log::info("Generated FNSKU with first available prefix", [
            'base_fnsku' => $baseFnsku,
            'used_prefixes' => $usedPrefixes,
            'next_prefix' => $nextPrefix,
            'actual_fnsku' => $actualFnsku,
            'remaining_units' => $currentUnits
        ]);

        return [
            'actual_fnsku' => $actualFnsku,
            'times_used' => count($usedPrefixes),
            'remaining_units' => $currentUnits,
            'next_prefix' => $nextPrefix
        ];

    } catch (\Exception $e) {
        Log::error("Error in getNextAvailableFnsku: " . $e->getMessage(), [
            'base_fnsku' => $baseFnsku,
            'trace' => $e->getTraceAsString()
        ]);

        throw $e;
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