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

            // Check if it's a prefixed FNSKU (starts with letter C-W or Y-Z, excluding X)
            // Pattern: Letter(C-W,Y-Z) + Number(1-9) + BaseFNSKU (which starts with X)
            if (preg_match('/^([C-W]|[Y-Z])(\d+)(X.+)$/', $fnsku, $matches)) {
                return $matches[3]; // Return the base FNSKU (starting with X)
            }

            return $fnsku; // Return as-is if not prefixed
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
                'prod.MSKUviewer',
                'prod.ASINviewer',
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
                'fnsku.MSKU',
                'fnsku.FNSKU',
                DB::raw("COALESCE(
                    NULLIF(TRIM(asin.system_title), ''), 
                    NULLIF(TRIM(asin.internal), ''), 
                    NULLIF(TRIM(prod.ProductTitle), '')
                ) as ProductTitle"),
                'asin.internal',
                'asin.system_title',
                'asin.metakeyword'
            )
            ->leftJoin('tbllpn', 'prod.ProductID', '=', 'tbllpn.ProdID')
            ->leftJoin($this->fnskuTable . ' as fnsku', 'prod.MSKUviewer', '=', 'fnsku.MSKU')
            ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
            ->where('prod.ProductModuleLoc', $location)
            ->when($search, function($query) use ($search) {
                return $query->where(function($q) use ($search) {
                    $q->where('prod.serialnumber', 'like', "%{$search}%")
                      ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                      ->orWhere('prod.MSKUviewer', 'like', "%{$search}%")
                      ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                      ->orWhere('tbllpn.LPN', 'like', "%{$search}%")
                      ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                      ->orWhere('fnsku.MSKU', 'like', "%{$search}%")
                      ->orWhere('fnsku.FNSKU', 'like', "%{$search}%")
                      ->orWhere('asin.internal', 'like', "%{$search}%")
                      ->orWhere('asin.system_title', 'like', "%{$search}%")
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
        // Search across all 4 serial fields - EXCLUDE Returnlist and Merged
        $product = DB::table($this->productTable)
            ->where(function ($query) use ($serial) {
                $query->where('serialnumber', $serial)
                    ->orWhere('serialnumberb', $serial)
                    ->orWhere('serialnumberc', $serial)
                    ->orWhere('serialnumberd', $serial);
            })
            ->whereIn('ProductModuleLoc', ['Stockroom', 'Shipment', 'Soldlist']) // ✅ FIXED: Only these locations
            ->first();
        
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Serial number not found or not in a valid location'
            ]);
        }

        // Helper function to check if serial is valid
        $isValidSerial = function($serialValue) {
            return !empty($serialValue) && 
                   trim($serialValue) !== '' &&
                   strtoupper(trim($serialValue)) !== 'N/A';
        };

        // Collect all valid serials with their positions
        $allSerials = [];
        $serialFields = [
            1 => 'serialnumber',
            2 => 'serialnumberb', 
            3 => 'serialnumberc',
            4 => 'serialnumberd'
        ];
        $serialLabels = [
            1 => 'Serial Number',
            2 => 'Second Serial',
            3 => 'Third Serial',
            4 => 'Fourth Serial'
        ];
        
        foreach ($serialFields as $index => $field) {
            $value = $product->$field ?? null;
            if ($isValidSerial($value)) {
                $allSerials[] = [
                    'index' => $index,
                    'field' => $field,
                    'value' => $value,
                    'label' => $serialLabels[$index]
                ];
            }
        }

        $totalSerials = count($allSerials);
        
        // Determine which serial was scanned
        $scannedSerialIndex = null;
        $scannedSerialPosition = null;
        
        foreach ($allSerials as $serialInfo) {
            if ($serialInfo['value'] === $serial) {
                $scannedSerialIndex = $serialInfo['index'];
                $scannedSerialPosition = $serialInfo['field'];
                break;
            }
        }

        // Build array of other serials (excluding the scanned one)
        $otherSerials = [];
        foreach ($allSerials as $serialInfo) {
            if ($serialInfo['value'] !== $serial) {
                $otherSerials[] = $serialInfo;
            }
        }

        $fnskuViewer = $product->FNSKUviewer ?? null;
        
        // Determine if multi-serial
        $isMultiSerial = $totalSerials > 1;
        
        // For backward compatibility with dual-serial
        $isDualSerial = $totalSerials === 2;
        $secondSerial = isset($otherSerials[0]) ? $otherSerials[0]['value'] : null;
        $thirdSerial = isset($otherSerials[1]) ? $otherSerials[1]['value'] : null;
        $fourthSerial = isset($otherSerials[2]) ? $otherSerials[2]['value'] : null;
        
        return response()->json([
            'success' => true,
            // New multi-serial fields
            'isMultiSerial' => $isMultiSerial,
            'totalSerials' => $totalSerials,
            'allSerials' => $allSerials,
            'otherSerials' => $otherSerials,
            'scannedSerialIndex' => $scannedSerialIndex,
            'scannedSerialPosition' => $scannedSerialPosition,
            // Legacy dual-serial compatibility
            'isDualSerial' => $isDualSerial,
            'secondSerial' => $secondSerial,
            'secondSerialLabel' => isset($otherSerials[0]) ? $otherSerials[0]['label'] : 'Second Serial',
            'thirdSerial' => $thirdSerial,
            'thirdSerialLabel' => isset($otherSerials[1]) ? $otherSerials[1]['label'] : 'Third Serial',
            'fourthSerial' => $fourthSerial,
            'fourthSerialLabel' => isset($otherSerials[2]) ? $otherSerials[2]['label'] : 'Fourth Serial',
            // Product info
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
                'ThirdSerial' => 'nullable|string',
                'FourthSerial' => 'nullable|string',
                'Location' => 'required|string',
                'ReturnId' => 'nullable|string',
                'SingleSerialMode' => 'nullable|boolean',
                'ProductID' => 'nullable|integer',
                'FNSKUviewer' => 'nullable|string',
                'ScannedSerialPosition' => 'nullable|string',
                'TotalSerials' => 'nullable|integer',
                'IsMultiSerial' => 'nullable|boolean',
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
        $thirdSerial = trim($request->input('ThirdSerial', ''));
        $fourthSerial = trim($request->input('FourthSerial', ''));
        $totalExpectedSerials = $request->input('TotalSerials', 1);
        $isMultiSerial = $request->input('IsMultiSerial', false);
        
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
            'thirdSerial' => $thirdSerial,
            'fourthSerial' => $fourthSerial,
            'location' => $location,
            'totalExpectedSerials' => $totalExpectedSerials,
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

        // Validate all serial number formats
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

        if (!empty($thirdSerial) && !preg_match('/^[a-zA-Z0-9-]+$/', $thirdSerial)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid Third Serial Number format',
                'reason' => 'invalid_third_serial'
            ]);
        }

        if (!empty($fourthSerial) && !preg_match('/^[a-zA-Z0-9-]+$/', $fourthSerial)) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Invalid Fourth Serial Number format',
                'reason' => 'invalid_fourth_serial'
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

        // Build serials to check array (all 4 serials)
        $serialsToCheck = [$serial];
        if (!empty($secondSerial) && !$singleSerialMode) {
            $serialsToCheck[] = $secondSerial;
        }
        if (!empty($thirdSerial) && !$singleSerialMode) {
            $serialsToCheck[] = $thirdSerial;
        }
        if (!empty($fourthSerial) && !$singleSerialMode) {
            $serialsToCheck[] = $fourthSerial;
        }
        
        // Check for existing serials in Production Area (search all 4 serial columns)
        foreach ($serialsToCheck as $serialToCheck) {
            $existingSerialCheck = DB::table($this->productTable)
                ->where(function ($query) use ($serialToCheck) {
                    $query->where('serialnumber', $serialToCheck)
                        ->orWhere('serialnumberb', $serialToCheck)
                        ->orWhere('serialnumberc', $serialToCheck)
                        ->orWhere('serialnumberd', $serialToCheck);
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
                            ->orWhere('serialnumberb', $serialToCheck)
                            ->orWhere('serialnumberc', $serialToCheck)
                            ->orWhere('serialnumberd', $serialToCheck);
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
                        WHEN prod.FNSKUviewer REGEXP '^[C-Z][0-9]+' 
                        THEN REGEXP_REPLACE(prod.FNSKUviewer, '^[C-Z][0-9]+', '')
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
            // Search by serial number across all 4 serial columns
            $existingItem = DB::table($this->productTable . ' as prod')
                ->select(
                    'prod.*',
                    'fnsku.ASIN',
                    'asin.internal as ProductTitle'
                )
                ->leftJoin($this->fnskuTable . ' as fnsku', function ($join) {
                    $join->on(DB::raw("CASE 
                        WHEN prod.FNSKUviewer REGEXP '^[C-Z][0-9]+' 
                        THEN REGEXP_REPLACE(prod.FNSKUviewer, '^[C-Z][0-9]+', '')
                        ELSE prod.FNSKUviewer 
                    END"), '=', 'fnsku.FNSKU');
                })
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->where(function ($query) use ($serial, $secondSerial, $thirdSerial, $fourthSerial) {
                    $query->where('prod.serialnumber', $serial)
                        ->orWhere('prod.serialnumberb', $serial)
                        ->orWhere('prod.serialnumberc', $serial)
                        ->orWhere('prod.serialnumberd', $serial);
                    
                    if (!empty($secondSerial)) {
                        $query->orWhere('prod.serialnumber', $secondSerial)
                            ->orWhere('prod.serialnumberb', $secondSerial)
                            ->orWhere('prod.serialnumberc', $secondSerial)
                            ->orWhere('prod.serialnumberd', $secondSerial);
                    }
                    if (!empty($thirdSerial)) {
                        $query->orWhere('prod.serialnumber', $thirdSerial)
                            ->orWhere('prod.serialnumberb', $thirdSerial)
                            ->orWhere('prod.serialnumberc', $thirdSerial)
                            ->orWhere('prod.serialnumberd', $thirdSerial);
                    }
                    if (!empty($fourthSerial)) {
                        $query->orWhere('prod.serialnumber', $fourthSerial)
                            ->orWhere('prod.serialnumberb', $fourthSerial)
                            ->orWhere('prod.serialnumberc', $fourthSerial)
                            ->orWhere('prod.serialnumberd', $fourthSerial);
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
                'ASINviewer' => null,  // ← ADDED for fallback
                'MSKUviewer' => null,  // ← ADDED for fallback
                'FNSKUviewer' => null,
                'serialnumber' => null,
                'serialnumberb' => null,
                'serialnumberc' => null,
                'serialnumberd' => null,
                'ProductModuleLoc' => null,
                'ProductTitle' => null
            ];
        }

        // ========== MULTI-SERIAL VALIDATION - ALLOW SWITCHERU ==========
        if ($isSerialKnown && !$singleSerialMode) {
            // Helper function to check if serial is valid
            $isValidSerial = function($s) {
                return !empty($s) && trim($s) !== '' && strtoupper(trim($s)) !== 'N/A';
            };
            
            // Collect all valid serials from DB
            $dbSerials = [];
            if ($isValidSerial($existingItem->serialnumber ?? null)) {
                $dbSerials[1] = $existingItem->serialnumber;
            }
            if ($isValidSerial($existingItem->serialnumberb ?? null)) {
                $dbSerials[2] = $existingItem->serialnumberb;
            }
            if ($isValidSerial($existingItem->serialnumberc ?? null)) {
                $dbSerials[3] = $existingItem->serialnumberc;
            }
            if ($isValidSerial($existingItem->serialnumberd ?? null)) {
                $dbSerials[4] = $existingItem->serialnumberd;
            }
            
            $totalDbSerials = count($dbSerials);
            
            if ($totalDbSerials > 1) {
                // Build provided serials array
                $providedSerials = array_filter([$serial, $secondSerial, $thirdSerial, $fourthSerial], function($s) {
                    return !empty(trim($s));
                });
                
                $providedCount = count($providedSerials);
                
                // ✅ RELAXED VALIDATION: Only require at least ONE serial to match (confirms correct product)
                $dbSerialsValues = array_values($dbSerials);
                $hasAtLeastOneMatch = false;
                
                foreach ($providedSerials as $provided) {
                    if (in_array($provided, $dbSerialsValues)) {
                        $hasAtLeastOneMatch = true;
                        break;
                    }
                }
                
                if (!$hasAtLeastOneMatch) {
                    // None of the provided serials match - this is the WRONG product
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "None of the provided serials match this product. Expected serials: " . implode(', ', $dbSerialsValues),
                        'reason' => 'wrong_product',
                        'expectedSerials' => $dbSerialsValues,
                        'providedSerials' => array_values($providedSerials)
                    ]);
                }
                
                // ✅ LOG WARNINGS for tracking (but don't block)
                if ($providedCount < $totalDbSerials) {
                    Log::warning("⚠️ Partial return detected", [
                        'expected' => $totalDbSerials,
                        'provided' => $providedCount,
                        'dbSerials' => $dbSerials,
                        'providedSerials' => $providedSerials,
                        'productId' => $existingItem->ProductID
                    ]);
                }
                
                // Check for any mismatches (potential switcheru - log but allow)
                foreach ($providedSerials as $provided) {
                    if (!in_array($provided, $dbSerialsValues)) {
                        Log::warning("⚠️ Potential SWITCHERU detected during validation", [
                            'provided_serial' => $provided,
                            'expected_serials' => $dbSerialsValues,
                            'productId' => $existingItem->ProductID
                        ]);
                    }
                }
            }
        }

        // Build serialsToProcess array (all provided serials)
        $serialsToProcess = [];
        if (!$singleSerialMode) {
            if (!empty($serial)) $serialsToProcess[] = $serial;
            if (!empty($secondSerial)) $serialsToProcess[] = $secondSerial;
            if (!empty($thirdSerial)) $serialsToProcess[] = $thirdSerial;
            if (!empty($fourthSerial)) $serialsToProcess[] = $fourthSerial;
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
        
        // ========== ATTRIBUTE-BASED FNSKU LOGIC ==========
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
            $mskuToUse = null;
            $color = null;
            
            try {
                if ($originalFnsku) {
                    $baseFnsku = $this->extractBaseFnsku($originalFnsku);
                    
                    $fnskuInfo = DB::table($this->fnskuTable . ' as fnsku')
                        ->select('fnsku.*', 'asin.quantityinside', 'asin.color')
                        ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                        ->where('fnsku.FNSKU', $baseFnsku)
                        ->first();

                    // ========== NEW: FALLBACK when FNSKU not found in tblFNSKU ==========
                    if (!$fnskuInfo) {
                        Log::warning("FNSKU '{$baseFnsku}' not found in tblFNSKU, attempting fallback via ASINviewer", [
                            'baseFnsku' => $baseFnsku,
                            'originalFnsku' => $originalFnsku,
                            'serial' => $currentSerial,
                            'ASINviewer' => $existingItem->ASINviewer ?? null
                        ]);

                        $fallbackAsin = $existingItem->ASINviewer ?? null;

                        if (!$fallbackAsin) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "FNSKU '{$baseFnsku}' not found in database and no ASINviewer available to fallback",
                                'reason' => 'fnsku_not_found_no_asin_fallback',
                                'details' => [
                                    'fnsku' => $baseFnsku,
                                    'serial' => $currentSerial
                                ]
                            ]);
                        }

                        $asinFallbackInfo = DB::table($this->asinTable)
                            ->select('quantityinside', 'color')
                            ->where('ASIN', $fallbackAsin)
                            ->first();

                        if (!$asinFallbackInfo) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "FNSKU '{$baseFnsku}' not found and ASIN '{$fallbackAsin}' has no record in tblasin",
                                'reason' => 'fnsku_and_asin_not_found',
                                'details' => [
                                    'fnsku' => $baseFnsku,
                                    'asin' => $fallbackAsin,
                                    'serial' => $currentSerial
                                ]
                            ]);
                        }

                        // Use data from ASINviewer fallback
                        $color = $asinFallbackInfo->color ?? null;
                        $quantityInside = $asinFallbackInfo->quantityinside ?? 1;
                        $condition = null;       // No grading since no FNSKU record
                        $storename = null;       // No storename since no FNSKU record
                        $OriginalFnskuUnitCount = 0; // Force alternative search
                        $mskuToUse = $existingItem->MSKUviewer ?? null;
                        $packAsin = $fallbackAsin;

                        Log::info("✅ Fallback ASIN info retrieved", [
                            'asin' => $fallbackAsin,
                            'color' => $color,
                            'quantityinside' => $quantityInside
                        ]);

                    } else {
                        // ✅ Normal flow - FNSKU found in tblFNSKU
                        $packAsin = $fnskuInfo->ASIN ?? null;
                        $mskuToUse = $fnskuInfo->MSKU ?? null;
                        $condition = $fnskuInfo->grading ?? null;
                        $storename = $fnskuInfo->storename ?? null;
                        $OriginalFnskuUnitCount = $fnskuInfo->Units ?? 0;
                        $quantityInside = $fnskuInfo->quantityinside ?? 1;
                        $color = $fnskuInfo->color ?? null;
                    }
                    // ========== END FALLBACK ==========

                    // ✅ STRICT COLOR VALIDATION
                    if (empty($color) || $color === null || trim($color) === '') {
                        Log::warning("FNSKU has no color defined", [
                            'fnsku' => $baseFnsku,
                            'asin' => $packAsin,
                            'serial' => $currentSerial
                        ]);
                        
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot process return: Product color is not defined in the system for FNSKU '{$baseFnsku}'",
                            'reason' => 'missing_color',
                            'details' => [
                                'fnsku' => $baseFnsku,
                                'asin' => $packAsin,
                                'serial' => $currentSerial,
                                'condition' => $condition
                            ]
                        ]);
                    }
                    
                    // ✅ STRICT STORE NAME VALIDATION (only when fnskuInfo was found)
                    if ($fnskuInfo && (empty($storename) || $storename === null || trim($storename) === '')) {
                        Log::warning("FNSKU has no store name defined", [
                            'fnsku' => $baseFnsku,
                            'asin' => $packAsin,
                            'serial' => $currentSerial
                        ]);
                        
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot process return: Store name is not defined in the system for FNSKU '{$baseFnsku}'",
                            'reason' => 'missing_storename',
                            'details' => [
                                'fnsku' => $baseFnsku,
                                'asin' => $packAsin,
                                'serial' => $currentSerial,
                                'condition' => $condition,
                                'color' => $color
                            ]
                        ]);
                    }
                    
                    Log::info("FNSKU Info", [
                        'FNSKU' => $baseFnsku,
                        'ASIN' => $packAsin,
                        'quantityinside' => $quantityInside,
                        'color' => $color,
                        'condition' => $condition,
                        'storename' => $storename,
                        'units' => $OriginalFnskuUnitCount
                    ]);
                    
                    // ✅ MULTI-PACK CONVERSION - ATTRIBUTE-BASED SEARCH
                    if ($quantityInside > 1) {
                        Log::info("Pack detected: ASIN {$packAsin} has {$quantityInside} items inside", [
                            'pack_asin' => $packAsin,
                            'pack_fnsku' => $baseFnsku,
                            'pack_quantity' => $quantityInside,
                            'pack_color' => $color,
                            'pack_condition' => $condition,
                            'pack_storename' => $storename
                        ]);
                        
                        // ✅ FIND SINGLE-UNIT ASIN WITH MATCHING ATTRIBUTES
                        Log::info("Searching for single-unit FNSKU with matching attributes", [
                            'required_quantity' => 1,
                            'required_color' => $color,
                            'required_condition' => $condition,
                            'required_storename' => $storename
                        ]);
                        
                        // Direct search for single-unit FNSKU with matching attributes
                        $singleItem = DB::table($this->fnskuTable . ' as fnsku')
                            ->select('fnsku.*', 'asin.ASIN as single_asin', 'asin.color', 'asin.quantityinside')
                            ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                            ->where('asin.quantityinside', 1)
                            ->where('asin.color', $color)
                            ->where('fnsku.grading', $condition)
                            ->where('fnsku.storename', $storename)
                            ->where('fnsku.fnsku_status', 'Available')
                            ->whereIn('fnsku.amazon_status', ['Active', 'Inactive', 'Notposted'])
                            ->where('fnsku.LimitStatus', 'False')
                            ->where('fnsku.Units', '>', 0)
                            ->orderByDesc('fnsku.FNSKUID')
                            ->first();
                        
                        if ($singleItem) {
                            Log::info("✅ Found single-unit FNSKU with exact attributes match", [
                                'pack_asin' => $packAsin,
                                'pack_fnsku' => $baseFnsku,
                                'pack_quantity' => $quantityInside,
                                'single_asin' => $singleItem->single_asin,
                                'single_fnsku' => $singleItem->FNSKU,
                                'single_msku' => $singleItem->MSKU,
                                'matched_color' => $color,
                                'matched_condition' => $condition,
                                'matched_storename' => $storename,
                                'units_available' => $singleItem->Units
                            ]);
                            
                            $baseFnskuToUse = $singleItem->FNSKU;
                            $asinToUse = $singleItem->single_asin;
                            $mskuToUse = $singleItem->MSKU;
                            $condition = $singleItem->grading;
                            $storename = $singleItem->storename;
                        } else {
                            // ✅ FALLBACK: Try case-insensitive color match
                            Log::info("No exact color match, trying case-insensitive search");
                            
                            $singleItem = DB::table($this->fnskuTable . ' as fnsku')
                                ->select('fnsku.*', 'asin.ASIN as single_asin', 'asin.color', 'asin.quantityinside')
                                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                                ->where('asin.quantityinside', 1)
                                ->whereRaw('LOWER(TRIM(asin.color)) = LOWER(TRIM(?))', [$color])
                                ->where('fnsku.grading', $condition)
                                ->where('fnsku.storename', $storename)
                                ->where('fnsku.fnsku_status', 'Available')
                                ->whereIn('fnsku.amazon_status', ['Active', 'Inactive', 'Notposted'])
                                ->where('fnsku.LimitStatus', 'False')
                                ->where('fnsku.Units', '>', 0)
                                ->orderByDesc('fnsku.FNSKUID')
                                ->first();
                            
                            if ($singleItem) {
                                Log::warning("⚠️ Found single-unit with case-insensitive color match", [
                                    'pack_color' => $color,
                                    'found_color' => $singleItem->color,
                                    'single_asin' => $singleItem->single_asin,
                                    'single_fnsku' => $singleItem->FNSKU
                                ]);
                                
                                $baseFnskuToUse = $singleItem->FNSKU;
                                $asinToUse = $singleItem->single_asin;
                                $mskuToUse = $singleItem->MSKU;
                                $condition = $singleItem->grading;
                                $storename = $singleItem->storename;
                            } else {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'message' => "Cannot process multi-pack: No single-unit FNSKU available with matching attributes\n\n" .
                                               "Pack Details:\n" .
                                               "• ASIN: {$packAsin}\n" .
                                               "• FNSKU: {$baseFnsku}\n" .
                                               "• Quantity Inside: {$quantityInside}\n\n" .
                                               "Required Single-Unit Must Have:\n" .
                                               "• Quantity Inside: 1\n" .
                                               "• Color: '{$color}'\n" .
                                               "• Condition: '{$condition}'\n" .
                                               "• Store: '{$storename}'\n" .
                                               "• Status: Available with units > 0\n\n" .
                                               "Please create a single-unit FNSKU with these exact attributes.",
                                    'reason' => 'no_single_unit_available',
                                    'details' => [
                                        'pack_asin' => $packAsin,
                                        'pack_fnsku' => $baseFnsku,
                                        'pack_quantity_inside' => $quantityInside,
                                        'required_single_attributes' => [
                                            'quantity_inside' => 1,
                                            'color' => $color,
                                            'grading' => $condition,
                                            'storename' => $storename
                                        ],
                                        'serial' => $currentSerial
                                    ]
                                ]);
                            }
                        }
                        
                    } else {
                        // ✅ SINGLE UNIT - CHECK IF AVAILABLE OR FIND ALTERNATIVE
                        if ($fnskuInfo && (strtolower($fnskuInfo->fnsku_status ?? '') !== 'available' || $OriginalFnskuUnitCount <= 0)) {
                            Log::info("Original single-unit FNSKU not available, searching for alternative", [
                                'original_fnsku' => $baseFnsku,
                                'original_asin' => $packAsin,
                                'required_color' => $color,
                                'required_storename' => $storename,
                                'status' => $fnskuInfo->fnsku_status,
                                'units' => $OriginalFnskuUnitCount
                            ]);
                            
                            // Search for alternative single-unit FNSKU with same attributes
                            $alternativeFnsku = DB::table($this->fnskuTable . ' as fnsku')
                                ->select('fnsku.*', 'asin.ASIN as single_asin')
                                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                                ->where('asin.quantityinside', 1)
                                ->whereRaw('LOWER(TRIM(asin.color)) = LOWER(TRIM(?))', [$color])
                                ->where('fnsku.grading', $condition)
                                ->where('fnsku.storename', $storename)
                                ->where('fnsku.fnsku_status', 'Available')
                                ->whereIn('fnsku.amazon_status', ['Active', 'Inactive', 'Notposted'])
                                ->where('fnsku.LimitStatus', 'False')
                                ->where('fnsku.Units', '>', 0)
                                ->orderByDesc('fnsku.FNSKUID')
                                ->first();
                            
                            if ($alternativeFnsku) {
                                $baseFnskuToUse = $alternativeFnsku->FNSKU;
                                $asinToUse = $alternativeFnsku->single_asin;
                                $mskuToUse = $alternativeFnsku->MSKU;
                                $storename = $alternativeFnsku->storename;
                                
                                Log::info("✅ Found alternative single-unit FNSKU", [
                                    'original_fnsku' => $baseFnsku,
                                    'alternative_fnsku' => $baseFnskuToUse,
                                    'alternative_asin' => $asinToUse,
                                    'matched_color' => $color,
                                    'matched_storename' => $storename,
                                    'units' => $alternativeFnsku->Units
                                ]);
                            } else {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'message' => "FNSKU '{$baseFnsku}' is not available" . 
                                               " (Status: " . ($fnskuInfo->fnsku_status ?? 'unknown') . 
                                               ", Units: {$OriginalFnskuUnitCount}). No alternative single-unit FNSKU found with matching color '{$color}', condition '{$condition}', and store '{$storename}'.",
                                    'reason' => 'fnsku_not_available',
                                    'details' => [
                                        'fnsku' => $baseFnsku,
                                        'asin' => $packAsin,
                                        'current_status' => $fnskuInfo->fnsku_status ?? 'unknown',
                                        'current_units' => $OriginalFnskuUnitCount,
                                        'required_color' => $color,
                                        'required_grading' => $condition,
                                        'required_storename' => $storename,
                                        'serial' => $currentSerial
                                    ]
                                ]);
                            }

                        } elseif (!$fnskuInfo && $OriginalFnskuUnitCount <= 0) {
                            // ========== NEW: FALLBACK PATH - FNSKU not in tblFNSKU, search by color only ==========
                            Log::info("FNSKU not in tblFNSKU, searching alternative by color from ASINviewer fallback", [
                                'color' => $color,
                                'serial' => $currentSerial
                            ]);

                            $alternativeFnsku = DB::table($this->fnskuTable . ' as fnsku')
                                ->select('fnsku.*', 'asin.ASIN as single_asin')
                                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                                ->where('asin.quantityinside', 1)
                                ->whereRaw('LOWER(TRIM(asin.color)) = LOWER(TRIM(?))', [$color])
                                ->where('fnsku.fnsku_status', 'Available')
                                ->whereIn('fnsku.amazon_status', ['Active', 'Inactive', 'Notposted'])
                                ->where('fnsku.LimitStatus', 'False')
                                ->where('fnsku.Units', '>', 0)
                                ->orderByDesc('fnsku.FNSKUID')
                                ->first();

                            if ($alternativeFnsku) {
                                $baseFnskuToUse = $alternativeFnsku->FNSKU;
                                $asinToUse = $alternativeFnsku->single_asin;
                                $mskuToUse = $alternativeFnsku->MSKU;
                                $condition = $alternativeFnsku->grading;
                                $storename = $alternativeFnsku->storename;
                                Log::info("✅ Found alternative FNSKU via ASINviewer color fallback", [
                                    'alternative_fnsku' => $baseFnskuToUse,
                                    'alternative_asin' => $asinToUse,
                                    'matched_color' => $color,
                                    'units' => $alternativeFnsku->Units
                                ]);
                            } else {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'message' => "FNSKU '{$baseFnsku}' not found in database and no alternative FNSKU found with color '{$color}'. Please create an available FNSKU with this color.",
                                    'reason' => 'fnsku_not_found_no_alternative',
                                    'details' => [
                                        'fnsku' => $baseFnsku,
                                        'asin' => $packAsin,
                                        'required_color' => $color,
                                        'serial' => $currentSerial
                                    ]
                                ]);
                            }
                            // ========== END NEW FALLBACK PATH ==========

                        } else {
                            $baseFnskuToUse = $fnskuInfo->FNSKU;
                            $asinToUse = $packAsin;
                            $storename = $fnskuInfo->storename;
                            Log::info("✅ Using original single-unit FNSKU {$baseFnskuToUse} (Units: {$OriginalFnskuUnitCount})");
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
                
                // ✅ AT THIS POINT, WE HAVE A VALID FNSKU WITH VERIFIED ATTRIBUTES
                if ($baseFnskuToUse && $mskuToUse) {
                    $fnskuGenerationInfo = $this->getNextAvailableFnsku(
                        $baseFnskuToUse,
                        $mskuToUse,
                        $asinToUse,
                        $condition,
                        $storename
                    );

                    $actualFnskuToUse = $fnskuGenerationInfo['actual_fnsku'];

                    Log::info('✅ Generated prefixed FNSKU', [
                        'base_fnsku' => $baseFnskuToUse,
                        'actual_fnsku' => $actualFnskuToUse,
                        'msku' => $mskuToUse,
                        'asin' => $asinToUse,
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
                        'validation_status' => 'validated',
                        'ASINviewer' => $asinToUse,
                        'MSKUviewer' => $mskuToUse
                    ]);
                    
                    DB::table($this->itemProcessHistoryTable)->insert([
                        'rtcounter' => $newRt,
                        'employeeName' => $User,
                        'editDate' => $curentDatetimeString,
                        'Module' => 'Scan Return Module',
                        'Action' => 'Scanned and insert to ' . $modulelocation
                    ]);
                    
                    $becameUnavailable = $this->updateFnskuUnits(
                        $mskuToUse,
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
                        'msku' => $mskuToUse,
                        'asin' => $asinToUse,
                        'location' => $modulelocation,
                        'rt' => $newRt
                    ];
                    
                    $successCount++;
                } else {
                    Log::error("❌ No FNSKU or MSKU found for serial: {$currentSerial}");
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Failed to assign FNSKU for serial: {$currentSerial}",
                        'reason' => 'fnsku_assignment_failed',
                        'details' => [
                            'serial' => $currentSerial,
                            'fnsku' => $baseFnskuToUse,
                            'msku' => $mskuToUse
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
        // ========== END OF ATTRIBUTE-BASED FNSKU LOGIC ==========
        
        // ========== SWITCHERU DETECTION (FIXED FOR UP TO 4 SERIALS) ==========
        $switcheruRecords = [];
        $switcheruFound = false;
        $serialsNotReturned = [];
        $newSwitchedSerials = [];

        if (!$isSerialKnown) {
            // Unknown serial case - no original product found
            $switcheruRecords[] = [
                'buyer' => $buyerName ?? 'Unknown',
                'sendserial' => '',
                'receiveserial' => $serial,
                'rtcounter' => null,
                'created_at' => $curentDatetimeString
            ];
            $switcheruFound = true;
            $newSwitchedSerials[] = $serial;
            
            Log::info("✅ Switcheru detected: Unknown serial", [
                'receiveserial' => $serial
            ]);
        } 
        else if (!$singleSerialMode) {
            // ✅ FIXED: Multi-serial switcheru detection (2, 3, or 4 serials)
            
            // Helper function
            $isValidSerial = function($s) {
                return !empty($s) && trim($s) !== '' && strtoupper(trim($s)) !== 'N/A';
            };
            
            // Collect all DB serials
            $dbSerials = [];
            if ($isValidSerial($existingItem->serialnumber ?? null)) {
                $dbSerials[1] = $existingItem->serialnumber;
            }
            if ($isValidSerial($existingItem->serialnumberb ?? null)) {
                $dbSerials[2] = $existingItem->serialnumberb;
            }
            if ($isValidSerial($existingItem->serialnumberc ?? null)) {
                $dbSerials[3] = $existingItem->serialnumberc;
            }
            if ($isValidSerial($existingItem->serialnumberd ?? null)) {
                $dbSerials[4] = $existingItem->serialnumberd;
            }
            
            // Collect all received serials
            $receivedSerials = [];
            if ($isValidSerial($serial)) $receivedSerials[1] = $serial;
            if ($isValidSerial($secondSerial)) $receivedSerials[2] = $secondSerial;
            if ($isValidSerial($thirdSerial)) $receivedSerials[3] = $thirdSerial;
            if ($isValidSerial($fourthSerial)) $receivedSerials[4] = $fourthSerial;
            
            $dbSerialValues = array_values($dbSerials);
            $receivedSerialValues = array_values($receivedSerials);
            
            // Find serials NOT returned (switched out)
            foreach ($dbSerials as $idx => $dbSerial) {
                if (!in_array($dbSerial, $receivedSerialValues)) {
                    $serialsNotReturned[$idx] = $dbSerial;
                }
            }
            
            // Find NEW serials received (switched in)
            foreach ($receivedSerials as $idx => $receivedSerial) {
                if (!in_array($receivedSerial, $dbSerialValues)) {
                    $newSwitchedSerials[$idx] = $receivedSerial;
                }
            }
            
            Log::info("✅ Switcheru Analysis", [
                'dbSerials' => $dbSerials,
                'receivedSerials' => $receivedSerials,
                'serialsNotReturned' => $serialsNotReturned,
                'newSwitchedSerials' => $newSwitchedSerials
            ]);
            
            // Create switcheru records ONLY IF there are switched serials
            if (!empty($serialsNotReturned) && !empty($newSwitchedSerials)) {
                // TRUE SWITCHERU: Sent serials not returned AND new serials received
                $switcheruFound = true;
                
                $notReturnedList = array_values($serialsNotReturned);
                $newSwitchedList = array_values($newSwitchedSerials);
                
                $maxCount = max(count($notReturnedList), count($newSwitchedList));
                
                for ($i = 0; $i < $maxCount; $i++) {
                    $sendSerial = $notReturnedList[$i] ?? '';
                    $receiveSerial = $newSwitchedList[$i] ?? '';
                    
                    if (!empty($sendSerial) || !empty($receiveSerial)) {
                        $switcheruRecords[] = [
                            'buyer' => $buyerName ?? 'Unknown',
                            'sendserial' => $sendSerial,
                            'receiveserial' => $receiveSerial,
                            'rtcounter' => $rtCounter,
                            'created_at' => $curentDatetimeString
                        ];
                    }
                }
                
                Log::info("✅ Switcheru Records Created", [
                    'count' => count($switcheruRecords),
                    'records' => $switcheruRecords
                ]);
            } else if (!empty($serialsNotReturned) && empty($newSwitchedSerials)) {
                // PARTIAL RETURN: Some serials not returned, but no new serials received
                // This is NOT a switcheru, just missing items
                Log::info("ℹ️ Partial return detected (NOT switcheru)", [
                    'serialsNotReturned' => $serialsNotReturned,
                    'totalDbSerials' => count($dbSerials),
                    'totalReceivedSerials' => count($receivedSerials)
                ]);
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
    
                // ✅ FIXED: Build update data with switcheru handling
                $updateData = [
                    'ProductModuleLoc' => 'Returnlist',
                    'returnstatus' => 'returned'
                ];
                
                // ✅ FIXED: Remove switched-out serials from original product ONLY IF switcheru detected
                if ($switcheruFound && !empty($serialsNotReturned)) {
                    Log::info("🔄 Removing switched serials from original product", [
                        'productId' => $originalItem->ProductID,
                        'serialsNotReturned' => $serialsNotReturned
                    ]);
                    
                    foreach ($serialsNotReturned as $idx => $notReturnedSerial) {
                        switch ($idx) {
                            case 1: $updateData['serialnumber'] = null; break;
                            case 2: $updateData['serialnumberb'] = null; break;
                            case 3: $updateData['serialnumberc'] = null; break;
                            case 4: $updateData['serialnumberd'] = null; break;
                        }
                    }
                } else if (!empty($serialsNotReturned) && empty($newSwitchedSerials)) {
                    // PARTIAL RETURN: Also remove serials that weren't returned
                    Log::info("🔄 Removing unreturned serials from original product (partial return)", [
                        'productId' => $originalItem->ProductID,
                        'serialsNotReturned' => $serialsNotReturned
                    ]);
                    
                    foreach ($serialsNotReturned as $idx => $notReturnedSerial) {
                        switch ($idx) {
                            case 1: $updateData['serialnumber'] = null; break;
                            case 2: $updateData['serialnumberb'] = null; break;
                            case 3: $updateData['serialnumberc'] = null; break;
                            case 4: $updateData['serialnumberd'] = null; break;
                        }
                    }
                }
                
                DB::table($this->productTable)
                    ->where('ProductID', $originalItem->ProductID)
                    ->update($updateData);

                if ($originalFnsku && $mskuToUse) {
                    $baseFnsku = $this->extractBaseFnsku($originalFnsku);
                    $this->returnFnskuUnits($mskuToUse, $baseFnsku);
                    Log::info("Restored units to original FNSKU {$baseFnsku}");
                }
                
                DB::table('tbldoneshipping')
                    ->where('Prodid', $originalItem->ProductID)
                    ->delete();
                
                // ✅ IMPROVED: Action message with switcheru details
                $actionMessage = ($singleSerialMode && !empty($existingItem->serialnumberb)) 
                    ? 'Item returned with only one serial and added to Return List' 
                    : 'Item returned and added to Return List';
                
                if ($switcheruFound) {
                    $actionMessage .= ' - SWITCHERU DETECTED';
                    if (!empty($serialsNotReturned)) {
                        $actionMessage .= ' - Not returned: ' . implode(', ', $serialsNotReturned);
                    }
                    if (!empty($newSwitchedSerials)) {
                        $actionMessage .= ' - Switched in: ' . implode(', ', $newSwitchedSerials);
                    }
                } else if (!empty($serialsNotReturned)) {
                    $actionMessage .= ' - Partial return - Missing: ' . implode(', ', $serialsNotReturned);
                }
                
                DB::table($this->itemProcessHistoryTable)->insert([
                    'rtcounter' => $originalItem->rtcounter,
                    'employeeName' => $User,
                    'editDate' => $curentDatetimeString,
                    'Module' => 'Returnlist',
                    'Action' => $actionMessage,
                ]);
            }
            
            DB::commit();
            
            // ✅ FIXED: Insert switcheru records ONLY if switcheru was detected
            if ($switcheruFound && !empty($switcheruRecords)) {
                $insertedCount = 0;
                foreach ($switcheruRecords as $switcheruRecord) {
                    try {
                        DB::table('tblswitcherus')->insert($switcheruRecord);
                        $insertedCount++;
                        Log::info("✅ Switcheru record inserted", $switcheruRecord);
                    } catch (\Exception $e) {
                        Log::error("❌ Failed to insert switcheru", [
                            'error' => $e->getMessage(),
                            'record' => $switcheruRecord
                        ]);
                    }
                }
                Log::info("✅ Total switcheru records inserted: {$insertedCount}");
            }
            
            $successMessage = "Successfully processed " . count($serialsToProcess) . " items";
            if ($switcheruFound) {
                $successMessage .= " - ⚠️ SWITCHERU DETECTED (" . count($switcheruRecords) . " record(s))";
            } else if (!empty($serialsNotReturned) && empty($newSwitchedSerials)) {
                $successMessage .= " - ℹ️ Partial return (" . count($serialsNotReturned) . " item(s) not returned)";
            }
            
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'item' => [
                    'serial_number' => $serial,
                    'second_serial' => $secondSerial,
                    'third_serial' => $thirdSerial,
                    'fourth_serial' => $fourthSerial,
                    'location' => $location,
                    'return_id' => $returnId,
                    'lpn_id' => $currentLpnId,
                    'status' => 'returned',
                    'original_location' => $existingItem->ProductModuleLoc,
                    'single_serial_mode' => $singleSerialMode && !empty($existingItem->serialnumberb),
                    'fnsku' => $originalFnsku,
                    'product_id' => $existingItem->ProductID,
                    'switcheru_found' => $switcheruFound,
                    'is_serial_known' => $isSerialKnown,
                    'total_serials_processed' => count($serialsToProcess)
                ],
                'createdItems' => $createdItems,
                'imagesReceived' => count($images),
                'switcheru' => [
                    'detected' => $switcheruFound,
                    'count' => count($switcheruRecords),
                    'records' => $switcheruRecords,
                    'serialsNotReturned' => array_values($serialsNotReturned),
                    'newSwitchedSerials' => array_values($newSwitchedSerials)
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
private function getNextAvailableFnsku($baseFnsku, $msku, $asin, $grading, $storename)
{
    try {
        // ✅ Lock FNSKU record using MSKU
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('MSKU', $msku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->where('LimitStatus', 'False')
            ->whereIn('amazon_status', ['Active', 'Inactive', 'Notposted'])
            ->lockForUpdate()
            ->first();

        if (!$fnskuRecord) {
            Log::warning("FNSKU not found in database", [
                'base_fnsku' => $baseFnsku,
                'msku' => $msku,
                'asin' => $asin,
                'grading' => $grading,
                'storename' => $storename
            ]);
            
            return [
                'actual_fnsku' => $baseFnsku,
                'actual_msku' => $msku,
                'times_used' => 0,
                'remaining_units' => 0
            ];
        }

        $currentUnits = $fnskuRecord->Units;

        if ($currentUnits <= 0) {
            throw new \Exception("No remaining units for MSKU: {$msku} (Units: {$currentUnits})");
        }

        // ✅ Get ALL active FNSKUs (with and without prefix) currently in use
        $activeFnskus = DB::table($this->productTable)
            ->select('FNSKUviewer')
            ->where(function($query) use ($baseFnsku) {
                $query->where('FNSKUviewer', $baseFnsku)
                      ->orWhere('FNSKUviewer', 'LIKE', '%' . $baseFnsku); // Match any prefix
            })
            ->whereNotIn('ProductModuleLoc', ['Shipment', 'Soldlist', 'Returnlist', 'Merged', 'RTS'])
            ->lockForUpdate()
            ->pluck('FNSKUviewer')
            ->toArray();

        Log::info("Active FNSKUs found", [
            'base_fnsku' => $baseFnsku,
            'active_fnskus' => $activeFnskus,
            'active_count' => count($activeFnskus),
            'remaining_units' => $currentUnits
        ]);

        // ✅ Extract used prefixes from active products (supports C-Z)
        $usedPrefixes = [];
        
        foreach ($activeFnskus as $fnsku) {
            if ($fnsku === $baseFnsku) {
                // Base FNSKU (no prefix) is used
                $usedPrefixes[] = ['letter' => null, 'number' => 0];
            } elseif (preg_match('/^([C-Z])(\d+)' . preg_quote($baseFnsku, '/') . '$/', $fnsku, $matches)) {
                // Extract prefix letter and number (e.g., "C3", "D5", "E1")
                $usedPrefixes[] = [
                    'letter' => $matches[1],
                    'number' => (int)$matches[2]
                ];
            }
        }

        Log::info("Prefix analysis", [
            'base_fnsku' => $baseFnsku,
            'used_prefixes' => $usedPrefixes,
            'used_count' => count($usedPrefixes),
            'remaining_units_in_db' => $currentUnits
        ]);

        // ✅ Generate prefix sequence from C to Z (C1-C9, D1-D9, ..., Z1-Z9)
        $prefixSequence = [];
        
        // No prefix (base FNSKU)
        $prefixSequence[] = ['letter' => null, 'number' => 0];
        
        // C through Z, each with 1-9 (24 letters × 9 numbers = 216 slots + 1 base = 217 total)
        for ($charCode = ord('C'); $charCode <= ord('Z'); $charCode++) {
            $letter = chr($charCode);
            for ($i = 1; $i <= 9; $i++) {
                $prefixSequence[] = ['letter' => $letter, 'number' => $i];
            }
        }

        Log::info("Prefix sequence generated", [
            'total_slots_available' => count($prefixSequence),
            'pattern' => 'base + C1-C9 + D1-D9 + ... + Z1-Z9'
        ]);

        // ✅ Find first UNUSED prefix in sequence
        $nextPrefix = null;

        foreach ($prefixSequence as $candidate) {
            $isUsed = false;
            
            foreach ($usedPrefixes as $used) {
                if ($used['letter'] === $candidate['letter'] && 
                    $used['number'] === $candidate['number']) {
                    $isUsed = true;
                    break;
                }
            }
            
            if (!$isUsed) {
                $nextPrefix = $candidate;
                break;
            }
        }

        // ✅ Check if we found an available prefix slot
        if ($nextPrefix === null) {
            throw new \Exception(
                "All prefix slots exhausted for FNSKU: {$baseFnsku}. " .
                "All " . count($prefixSequence) . " prefixes (base + C1-Z9) are in use."
            );
        }

        // ✅ Generate FNSKU with correct prefix
        if ($nextPrefix['letter'] === null) {
            $actualFnsku = $baseFnsku; // No prefix (base FNSKU)
        } else {
            $actualFnsku = "{$nextPrefix['letter']}{$nextPrefix['number']}{$baseFnsku}";
        }

        $prefixDisplay = $nextPrefix['letter'] 
            ? "{$nextPrefix['letter']}{$nextPrefix['number']}" 
            : 'base';

        Log::info("✅ Generated FNSKU with available prefix", [
            'base_fnsku' => $baseFnsku,
            'used_count' => count($usedPrefixes),
            'next_prefix' => $prefixDisplay,
            'actual_fnsku' => $actualFnsku,
            'remaining_units' => $currentUnits,
            'total_capacity' => count($prefixSequence)
        ]);

        return [
            'actual_fnsku' => $actualFnsku,
            'actual_msku' => $msku,
            'times_used' => count($usedPrefixes),
            'remaining_units' => $currentUnits,
            'next_prefix' => $prefixDisplay
        ];

    } catch (\Exception $e) {
        Log::error("Error in getNextAvailableFnsku: " . $e->getMessage(), [
            'base_fnsku' => $baseFnsku,
            'msku' => $msku,
            'trace' => $e->getTraceAsString()
        ]);

        throw $e;
    }
}

/**
 * Update FNSKU units (decrement by 1)
 */
private function updateFnskuUnits($msku, $baseFnsku, $asin, $grading, $storename)
{
    try {
        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('MSKU', $msku)
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
        $newStatus = ($newUnits <= 0) ? 'Unavailable' : 'Available';

        DB::table($this->fnskuTable)
            ->where('MSKU', $msku)
            ->where('FNSKU', $baseFnsku)
            ->where('ASIN', $asin)
            ->where('grading', $grading)
            ->where('storename', $storename)
            ->update([
                'Units' => $newUnits,
                'fnsku_status' => $newStatus
            ]);
        
        $asin = $fnskuRecord->ASIN;
        $grading = $fnskuRecord->grading;
        $storename = $storename->storename;
        
        //update fnsku limit status
        $this->updateFnskuLimitStatus($asin, $msku);

        return ($newStatus === 'Unavailable');

    } catch (\Exception $e) {
        Log::error("Error updating FNSKU units: " . $e->getMessage());
        return false;
    }
}

/**
 * Return FNSKU units (increment by 1)
 */
private function returnFnskuUnits($mskuViewer, $fnskuViewer)
{
    try {
        $baseFnsku = $this->extractBaseFnsku($fnskuViewer);

        $fnskuRecord = DB::table($this->fnskuTable)
            ->where('MSKU', $mskuViewer)
            ->where('FNSKU', $baseFnsku)
            ->first();

        if (!$fnskuRecord) {
            return false;
        }

        $currentUnits = $fnskuRecord->Units ?? 0;
        $newUnits = $currentUnits + 1;

        DB::table($this->fnskuTable)
            ->where('MSKU', $mskuViewer)
            ->where('FNSKU', $baseFnsku)
            ->update([
                'Units' => $newUnits,
                'fnsku_status' => 'Available'
            ]);

        $asin = $fnskuRecord->ASIN;
        $grading = $fnskuRecord->grading;
        $storename = $storename->storename;
        
        //update fnsku limit status
        $this->updateFnskuLimitStatus($asin, $mskuViewer);

        return true;

    } catch (\Exception $e) {
        Log::error("Error returning FNSKU units: " . $e->getMessage());
        return false;
    }
}

private function isValidSerial($serial)
{
    if ($serial === null) return false;
    if (!is_string($serial)) return false;
    
    $trimmed = trim($serial);
    if ($trimmed === '') return false;
    if (strtoupper($trimmed) === 'N/A') return false;
    if (strtoupper($trimmed) === 'NA') return false;
    if (strtoupper($trimmed) === 'NULL') return false;
    if ($trimmed === '0') return false;
    
    return true;
}

    public function updateFnskuLimitStatus($asin, $msku) {

    //get asin limit
    $asinLimit = (int) (DB::table($this->asinTable)
            ->where('ASIN', $asin)
            ->value('asin_limit') ?? 0);

    //get current units
    $currentUnits = (int) DB::table($this->fnskuTable)
                        ->where('MSKU', $msku)
                        ->where('ASIN', $asin)
                        ->value('Units');

    $maximumUnits = 10;
    $usedUnits = max(0, $maximumUnits - $currentUnits);

    DB::table($this->fnskuTable)
        ->where('MSKU', $msku)
        ->where('ASIN', $asin)
        ->update(['LimitStatus' => ($asinLimit > 0 && $usedUnits >= $asinLimit) ? 'True' : 'False']);

}
}