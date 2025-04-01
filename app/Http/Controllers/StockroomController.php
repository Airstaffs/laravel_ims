<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Log;

class StockroomController extends Controller
{
    /**
     * Display a listing of products in stockroom.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $location = $request->input('location', 'stockroom');
        
        $products = DB::table('tblproduct')
            ->where('ProductModuleLoc', $location)
            ->when($search, function($query) use ($search) {
                return $query->where(function($q) use ($search) {
                    $q->where('AStitle', 'like', "%{$search}%")
                      ->orWhere('serialnumber', 'like', "%{$search}%")
                      ->orWhere('FNSKUviewer', 'like', "%{$search}%")
                      ->orWhere('MSKUviewer', 'like', "%{$search}%")
                      ->orWhere('ASINviewer', 'like', "%{$search}%")
                      ->orWhere('rtcounter', 'like', "%{$search}%");
                });
            })
            ->orderBy('lastDateUpdate', 'desc')
            ->paginate($perPage);
        
        return response()->json($products);
    }

    /**
     * Process scanner data
     */
    public function processScan(Request $request)
    {
        try {
            // Validate input
            $request->validate([
                'SerialNumber' => 'required_without:FNSKU',
                'FNSKU' => 'required_without:SerialNumber',
                'Location' => 'required',
            ]);

            // Get data from request
            $User = Auth::id() ?? session('user_name', 'Unknown'); // Fallback to session or 'Unknown'
            $serial = trim($request->SerialNumber);
            $location = trim($request->Location);
            $FNSKU = $request->FNSKU;
            $store = '';
            $Module = "Stockroom";
            
            // Time handling
            $california_timezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $california_timezone);
            $formatted_datetime = $currentDatetime->format('Y-m-d h:i A');
            $currentDate = date('Y-m-d', strtotime($formatted_datetime));
            $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
            $Action = "Scanned and insert to Stockroom";
            
            // Basic validation
            if ((!preg_match('/^[a-zA-Z0-9]+$/', $serial)) || (strpos($serial, 'X00') !== false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Serial Number',
                    'reason' => 'invalid_serial'
                ]);
            }
            
            if (preg_match('/^L\d{3}[A-G]$/i', $FNSKU)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid FNSKU',
                    'reason' => 'invalid_fnsku'
                ]);
            }
            
            if (!preg_match('/^L\d{3}[A-G]$/i', $location) && $location !== 'Floor' && $location !== 'L800G') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Location Format',
                    'reason' => 'invalid_location'
                ]);
            }
            
            // Check if the serial exists in the stockroom list
            $existingItem = DB::table('tblproduct')
                ->where(function($query) use ($serial) {
                    $query->where('serialnumber', $serial)
                          ->orWhere('serialnumberb', $serial);
                })
                ->where(function($query) {
                    $query->where('ProductModuleLoc', 'Stockroom')
                          ->orWhere('ProductModuleLoc', 'SoldList')
                          ->orWhere('ProductModuleLoc', 'Production Area')
                          ->orWhere('ProductModuleLoc', 'Shipment');
                })
                ->first();
                
            if ($existingItem) {
                $id = $existingItem->ProductID;
                $module = $existingItem->ProductModuleLoc;
                $rt = $existingItem->rtcounter;
                $needReprint = false;
                
                // Case: Item is in SoldList and Fulfilledby is FBM or FBA, or ProductModuleLoc is Shipment
                if (($existingItem->ProductModuleLoc === 'SoldList' && 
                    ($existingItem->Fulfilledby === 'FBM' || $existingItem->Fulfilledby === 'FBA')) || 
                    $existingItem->ProductModuleLoc === 'Shipment') {
                    
                    $table = '';
                    // Find FNSKU in master tables
                    $fnsku_data = DB::table('tblmasterfnsku')
                        ->where('FNSKU', $FNSKU)
                        ->first();
                    
                    if ($fnsku_data) {
                        $ASINmainFnsku = $fnsku_data->ASIN;
                        $getCondition1 = $fnsku_data->grading;
                        $getTitle1 = $fnsku_data->astitle;
                        $getMSKU1 = $fnsku_data->MSKU;
                        $table = 'tblmasterfnsku';
                        $store = 'Renovar Tech';
                        
                        $asinData = DB::table('tblmasterasin')
                        ->where('ASIN', $ASINmainFnsku)
                        ->first();
                        
                        $weightRetail = $asinData->lbs ?? 0;
                        $weightWhite = $asinData->white_lbs ?? 0;
                        $getmetaKeyword = $asinData->metakeyword ?? null;

                        $Status = $fnsku_data->Status;
                        $SetID = $fnsku_data->productid;
                    } else {
                        $fnsku_data = DB::table('tblmasterfnskuAllrenewed')
                            ->where('FNSKU', $FNSKU)
                            ->first();
                        
                        if ($fnsku_data) {
                            $ASINmainFnsku = $fnsku_data->ASIN;
                            $getCondition1 = $fnsku_data->grading;
                            $getTitle1 = $fnsku_data->astitle;
                            $getMSKU1 = $fnsku_data->MSKU;
                            $table = 'tblmasterfnskuAllrenewed';
                            $store = 'Allrenewed';
                                    
                            $asinData = DB::table('tblmasterasin')
                            ->where('ASIN', $ASINmainFnsku)
                            ->first();
                            
                            $weightRetail = $asinData->lbs ?? 0;
                            $weightWhite = $asinData->white_lbs ?? 0;
                            $getmetaKeyword = $asinData->metakeyword ?? null;

                            $Status = $fnsku_data->Status;
                            $SetID = $fnsku_data->productid;
                        } else {
                            return response()->json([
                                'success' => false,
                                'message' => 'FNSKU not found in database',
                                'reason' => 'fnsku_not_found'
                            ]);
                        }
                    }
                    
                    // Determine SKU condition and box type
                    $skuCondition = $existingItem->gradingviewer ?? ''; // Get from existing item or default to empty
                    
                    if (($skuCondition === 'UsedLikeNew') || ($skuCondition === 'New')) {
                        $weight = $weightRetail;
                        $SelectedBox = 'Retailbox';
                    } else {
                        if ($weightWhite > 0) {
                            $weight = $weightWhite;
                            $SelectedBox = 'Whitebox';
                        } else {
                            $weight = $weightRetail;
                            $SelectedBox = 'Retailbox';
                        }
                    }
                    
                    // Set module location based on location code
                    if (substr($location, 0, 4) === 'L800') {
                        $modulelocation = 'Production Area';
                        $insertedDate = null;
                    } else {
                        $modulelocation = 'Stockroom';
                        $insertedDate = $curentDatetimeString;
                    }
                    
                    // Process based on Status or module
                    if (($Status === 'Available') || ($module === 'Shipment')) {
                        try {
                            DB::beginTransaction();
                            
                            // Update existing item to returnlist
                            DB::table('tblproduct')
                                ->where('ProductID', $id)
                                ->update([
                                    'returnstatus' => 'Returned',
                                    'ReceivedStatus' => 'Received',
                                    'ProductModuleLoc' => 'Returnlist'
                                ]);
                            
                            // Insert to LPN table
                            $lpnId = DB::table('tbllpn')->insertGetId([
                                'SERIAL' => $serial,
                                'LPN' => null,
                                'LPNDATE' => $curentDatetimeString,
                                'ProdID' => $id,
                                'BuyerName' => null
                            ]);
                            
                            // Insert history
                            DB::table('tblitemprocesshistory')->insert([
                                'rtcounter' => $rt,
                                'employeeName' => $User,
                                'editDate' => $curentDatetimeString,
                                'Module' => 'Scanner Return Module',
                                'Action' => 'Return Item'
                            ]);
                            
                            // Get next RT counter
                            $maxxrt = DB::table('tblproduct')->max('rtcounter');
                            $newrt = $maxxrt + 1;
                            
                            // Insert new item
                            $newItemId = DB::table('tblproduct')->insertGetId([
                                'rtcounter' => $newrt,
                                'rtid' => null,
                                'itemnumber' => null,
                                'Username' => $User,
                                'serialnumber' => $serial,
                                'serialnumberb' => null,
                                'serialnumberc' => null,
                                'serialnumberd' => null,
                                'ProductModuleLoc' => $modulelocation,
                                'quantity' => 1,
                                'price' => null,
                                'lpnID' => $lpnId,
                                'warehouselocation' => $location,
                                'ASiNviewer' => $ASINmainFnsku,
                                'FNSKUviewer' => $FNSKU,
                                'gradingviewer' => $getCondition1,
                                'MSKUviewer' => $getMSKU1,
                                'metakeyword' => $getmetaKeyword,
                                'AStitle' => $getTitle1,
                                'stockroom_insert_date' => $insertedDate,
                                'StoreName' => $store,
                                'BoxWeight' => $weight,
                                'boxChoice' => $SelectedBox
                            ]);
                            
                            // Insert history for new item
                            DB::table('tblitemprocesshistory')->insert([
                                'rtcounter' => $newrt,
                                'employeeName' => $User,
                                'editDate' => $curentDatetimeString,
                                'Module' => 'Scan Add Module',
                                'Action' => "Scanned and insert to {$modulelocation}"
                            ]);
                            
                            // Update FNSKU status
                            DB::table($table)
                                ->where('FNSKU', $FNSKU)
                                ->where('ASIN', $ASINmainFnsku)
                                ->update([
                                    'Status' => 'Unavailable',
                                    'productid' => $newItemId
                                ]);
                            
                            // Delete from shipping if needed
                            DB::table('tbldoneshipping')
                                ->where('Prodid', $id)
                                ->delete();
                                
                            DB::commit();
                            
                            return response()->json([
                                'success' => true,
                                'message' => "Scanned and Updated. Moved to {$modulelocation}",
                                'item' => $getTitle1
                            ]);
                        } catch (\Exception $e) {
                            DB::rollback();
                            Log::error('Error in processScan - move to returnlist: ' . $e->getMessage());
                            
                            return response()->json([
                                'success' => false,
                                'message' => 'Error processing scan: ' . $e->getMessage(),
                                'reason' => 'database_error'
                            ], 500);
                        }
                    } else {
                        // Try to find available FNSKU with same ASIN and grading
                        $availableFnsku = DB::table($table)
                            ->where('Status', 'Available')
                            ->where('amazon_status', 'Existed')
                            ->where('LimitStatus', 'False')
                            ->where('ASIN', $ASINmainFnsku)
                            ->where('grading', $getCondition1)
                            ->first();
                            
                        if ($availableFnsku) {
                            try {
                                DB::beginTransaction();
                                
                                // Update existing item to returnlist
                                DB::table('tblproduct')
                                    ->where('ProductID', $id)
                                    ->update([
                                        'returnstatus' => 'Returned',
                                        'ReceivedStatus' => 'Received',
                                        'ProductModuleLoc' => 'Returnlist'
                                    ]);
                                
                                // Insert to LPN table
                                $lpnId = DB::table('tbllpn')->insertGetId([
                                    'SERIAL' => $serial,
                                    'LPN' => null,
                                    'LPNDATE' => $curentDatetimeString,
                                    'ProdID' => $id,
                                    'BuyerName' => null
                                ]);
                                
                                // Insert history
                                DB::table('tblitemprocesshistory')->insert([
                                    'rtcounter' => $rt,
                                    'employeeName' => $User,
                                    'editDate' => $curentDatetimeString,
                                    'Module' => 'Scanner Return Module',
                                    'Action' => 'Return Item'
                                ]);
                                
                                // Get next RT counter
                                $maxxrt = DB::table('tblproduct')->max('rtcounter');
                                $newrt = $maxxrt + 1;
                                
                                // Insert new item
                                $newItemId = DB::table('tblproduct')->insertGetId([
                                    'rtcounter' => $newrt,
                                    'rtid' => null,
                                    'itemnumber' => null,
                                    'Username' => $User,
                                    'serialnumber' => $serial,
                                    'serialnumberb' => null,
                                    'serialnumberc' => null,
                                    'serialnumberd' => null,
                                    'ProductModuleLoc' => $modulelocation,
                                    'quantity' => 1,
                                    'price' => null,
                                    'lpnID' => $lpnId,
                                    'warehouselocation' => $location,
                                    'ASiNviewer' => $availableFnsku->ASIN,
                                    'FNSKUviewer' => $availableFnsku->FNSKU,
                                    'gradingviewer' => $availableFnsku->grading,
                                    'MSKUviewer' => $availableFnsku->MSKU,
                                    'metakeyword' => $getmetaKeyword,
                                    'AStitle' => $availableFnsku->astitle,
                                    'stockroom_insert_date' => $insertedDate,
                                    'StoreName' => $store,
                                    'BoxWeight' => $weight,
                                    'boxChoice' => $SelectedBox
                                ]);
                                
                                // Insert history for new item
                                DB::table('tblitemprocesshistory')->insert([
                                    'rtcounter' => $newrt,
                                    'employeeName' => $User,
                                    'editDate' => $curentDatetimeString,
                                    'Module' => 'Scan Add Module',
                                    'Action' => "Scanned and insert to {$modulelocation}"
                                ]);
                                
                                // Update FNSKU status
                                DB::table($table)
                                    ->where('FNSKU', $availableFnsku->FNSKU)
                                    ->where('ASIN', $availableFnsku->ASIN)
                                    ->update([
                                        'Status' => 'Unavailable',
                                        'productid' => $newItemId
                                    ]);
                                
                                // Delete from shipping if needed
                                DB::table('tbldoneshipping')
                                    ->where('Prodid', $id)
                                    ->delete();
                                    
                                DB::commit();
                                
                                return response()->json([
                                    'success' => true,
                                    'message' => "Scanned and Updated. Moved to \"{$modulelocation}\"",
                                    'item' => $availableFnsku->astitle
                                ]);
                            } catch (\Exception $e) {
                                DB::rollback();
                                Log::error('Error in processScan - available FNSKU: ' . $e->getMessage());
                                
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Error processing scan: ' . $e->getMessage(),
                                    'reason' => 'database_error'
                                ], 500);
                            }
                        } else {
                            return response()->json([
                                'success' => false,
                                'message' => 'No Available FNSKU for this item',
                                'item' => $getTitle1
                            ]);
                        }
                    }
                } 
                // Case: Item is in Production Area and Fulfilledby is FBM
                else if (($existingItem->ProductModuleLoc === 'Production Area') && ($existingItem->Fulfilledby === 'FBM')) {
                    try {
                        // Set module location based on location code
                        if (substr($location, 0, 4) === 'L800') {
                            $modulelocation = 'Production Area';
                            $insertedDate = null;
                        } else {
                            $modulelocation = 'Stockroom';
                            $insertedDate = $curentDatetimeString;
                        }
                        
                        // Update the item
                        DB::table('tblproduct')
                            ->where('ProductID', $id)
                            ->update([
                                'warehouselocation' => $location,
                                'ProductModuleLoc' => $modulelocation,
                                'stockroom_insert_date' => $insertedDate
                            ]);
                        
                        // Insert history
                        DB::table('tblitemprocesshistory')->insert([
                            'rtcounter' => $rt,
                            'employeeName' => $User,
                            'editDate' => $curentDatetimeString,
                            'Module' => 'Scan Add Module',
                            'Action' => "Scanned and insert to {$modulelocation}"
                        ]);
                        
                        return response()->json([
                            'success' => true,
                            'message' => "Scanned and Updated. Moved to {$modulelocation}",
                            'item' => $existingItem->AStitle
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Error in processScan - Production Area update: ' . $e->getMessage());
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Error processing scan: ' . $e->getMessage(),
                            'reason' => 'database_error'
                        ], 500);
                    }
                }
                // Case: Item has warehouselocation set to 'Floor'
                else if ($existingItem->warehouselocation === 'Floor') {
                    try {
                        // Just update the location
                        DB::table('tblproduct')
                            ->where('ProductID', $id)
                            ->update([
                                'warehouselocation' => $location
                            ]);
                        
                        return response()->json([
                            'success' => true,
                            'message' => "Scanned and Updated Location Successfully",
                            'item' => $existingItem->AStitle
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Error in processScan - Floor update: ' . $e->getMessage());
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Error processing scan: ' . $e->getMessage(),
                            'reason' => 'database_error'
                        ], 500);
                    }
                }
                // Case: Duplicate serial in stockroom
                else {
                    try {
                        // Log duplicate
                        DB::table('tbladditemstockroomlogs')->insert([
                            'FNSKU' => $FNSKU,
                            'LOCATION' => $location,
                            'SERIALNUMBER' => $serial,
                            'NOTE' => 'Duplicate Serial'
                        ]);
                        
                        return response()->json([
                            'success' => false,
                            'message' => "Serial already exists in stockroom module",
                            'reason' => 'duplicate_serial'
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Error in processScan - log duplicate: ' . $e->getMessage());
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Error processing scan: ' . $e->getMessage(),
                            'reason' => 'database_error'
                        ], 500);
                    }
                }
            } 
            // Serial not found in main stockroom tables
            else {
                // Check for item with different FNSKU
                $existingWithDifferentFNSKU = DB::table('tblproduct')
                    ->where(function($query) use ($serial) {
                        $query->where('serialnumber', $serial)
                              ->orWhere('serialnumberb', $serial);
                    })
                    ->where('returnstatus', 'Not Returned')
                    ->where('validation_status', 'validated')
                    ->whereNotIn('ProductModuleLoc', ['Orders', 'Migrated', 'Labeling', 'Soldlist', 'Shipment', 'RTS'])
                    ->first();
                
                if ($existingWithDifferentFNSKU) {
                    $id = $existingWithDifferentFNSKU->ProductID;
                    $rtnumberofitem = $existingWithDifferentFNSKU->rtcounter;
                    $checkFNSKUviewer = $existingWithDifferentFNSKU->FNSKUviewer;
                    $needReprint = false;
                    
                    if (!empty($checkFNSKUviewer)) {
                        $trimmedFNSKU = trim($checkFNSKUviewer);
                        $trimmedFNSKU2 = trim($FNSKU);
                        $prefix = substr($trimmedFNSKU, 0, 2);
                        $prefix2 = substr($trimmedFNSKU2, 0, 2);
                        
                        if (preg_match('/^[B-W][0-9]/', $prefix)) {
                            $mainFnsku = substr($trimmedFNSKU, 2);
                        } else {
                            $mainFnsku = $trimmedFNSKU;
                        }

                        if (preg_match('/^[B-W][0-9]/', $prefix2)) {
                            $inputFnsku = substr($trimmedFNSKU2, 2);
                        } else {
                            $inputFnsku = $trimmedFNSKU2;
                        }

                        if (trim($mainFnsku) != trim($inputFnsku)) {
                            $needReprint = true;
                        }
                        
                        try {
                            // Update product to Stockroom
                            DB::table('tblproduct')
                                ->where('ProductID', $id)
                                ->update([
                                    'ProductModuleLoc' => 'Stockroom',
                                    'warehouselocation' => $location,
                                    'stockroom_insert_date' => $curentDatetimeString
                                ]);
                            
                            // Insert history
                            DB::table('tblitemprocesshistory')->insert([
                                'rtcounter' => $rtnumberofitem,
                                'employeeName' => $User,
                                'editDate' => $curentDatetimeString,
                                'Module' => $Module,
                                'Action' => $Action
                            ]);
                            
                            return response()->json([
                                'success' => true,
                                'message' => "Scanned and Forwarded to Stockroom Successfully",
                                'item' => $existingWithDifferentFNSKU->AStitle,
                                'needReprint' => $needReprint,
                                'productId' => $needReprint ? $id : null
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error in processScan - existing with different FNSKU: ' . $e->getMessage());
                            
                            return response()->json([
                                'success' => false,
                                'message' => 'Error processing scan: ' . $e->getMessage(),
                                'reason' => 'database_error'
                            ], 500);
                        }
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot Proceed to Move item - FNSKU is Blank",
                            'reason' => 'blank_fnsku'
                        ]);
                    }
                }
                // Check for new FNSKU entry
                else {
                    $fnsku_data = DB::table('tblmasterfnsku')
                        ->where('FNSKU', $FNSKU)
                        ->first();
                    
                    if ($fnsku_data) {
                        $checkFNSKUstatus = $fnsku_data->Status;
                        $getASIN = $fnsku_data->ASIN;
                        $getCondition = $fnsku_data->grading;
                        $getTitle = $fnsku_data->astitle;
                        $getMSKU = $fnsku_data->MSKU;
                        $getFNSKU = $fnsku_data->FNSKU;
                        $store = 'Renovar Tech';

                        
                        $asinData = DB::table('tblmasterasin')
                        ->where('ASIN', $getASIN)
                        ->first();
                        
                        $weightRetail = $asinData->lbs ?? 0;
                        $weightWhite = $asinData->white_lbs ?? 0;
                        $getmetaKeyword = $asinData->metakeyword ?? null;
                        
                        if (($checkFNSKUstatus == "Available") || ($checkFNSKUstatus == null)) {
                            try {
                                // Get next RT counter
                                $maxxrt = DB::table('tblproduct')->max('rtcounter');
                                $newrt = $maxxrt + 1;
                                
                                // Insert new item
                                $newItemId = DB::table('tblproduct')->insertGetId([
                                    'rtcounter' => $newrt,
                                    'serialnumber' => $serial,
                                    'ProductModuleLoc' => $Module,
                                    'warehouselocation' => $location,
                                    'ASiNviewer' => $getASIN,
                                    'FNSKUviewer' => $getFNSKU,
                                    'gradingviewer' => $getCondition,
                                    'AStitle' => $getTitle,
                                    'MSKUviewer' => $getMSKU,
                                    'FbmAvailable' => 1,
                                    'Fulfilledby' => 'FBM',
                                    'quantity' => 1,
                                    'DateCreated' => $curentDatetimeString,
                                    'stockroom_insert_date' => $curentDatetimeString,
                                    'StoreName' => $store
                                ]);
                                
                                // Insert history
                                DB::table('tblitemprocesshistory')->insert([
                                    'rtcounter' => $newrt,
                                    'employeeName' => $User,
                                    'editDate' => $curentDatetimeString,
                                    'Module' => $Module,
                                    'Action' => $Action
                                ]);
                                
                                // Update FNSKU status
                                DB::table('tblmasterfnsku')
                                    ->where('FNSKU', $getFNSKU)
                                    ->update([
                                        'Status' => 'Unavailable',
                                        'productid' => $newItemId
                                    ]);
                                
                                return response()->json([
                                    'success' => true,
                                    'message' => "Scanned and Inserted Successfully",
                                    'item' => $getTitle
                                ]);
                            } catch (\Exception $e) {
                                Log::error('Error in processScan - new FNSKU insert: ' . $e->getMessage());
                                
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Error processing scan: ' . $e->getMessage(),
                                    'reason' => 'database_error'
                                ], 500);
                            }
                        } else {
                            // Try to find product with this FNSKU and serial
                            $existingWithSameFNSKU = DB::table('tblproduct')
                                ->where('FNSKUviewer', $getFNSKU)
                                ->where('serialnumber', $serial)
                                ->where('returnstatus', 'Not Returned')
                                ->whereNotIn('ProductModuleLoc', ['Stockroom', 'Soldlist', 'Migrated', 'RTS'])
                                ->first();
                            
                            if ($existingWithSameFNSKU) {
                                try {
                                    $findInsertedrtcounter = $existingWithSameFNSKU->rtcounter;
                                    $prodIDunique = $existingWithSameFNSKU->ProductID;
                                    
                                    // Update item to stockroom
                                    DB::table('tblproduct')
                                        ->where('ProductID', $prodIDunique)
                                        ->update([
                                            'ProductModuleLoc' => 'Stockroom',
                                            'stockroom_insert_date' => $curentDatetimeString,
                                            'warehouselocation' => $location
                                        ]);
                                    
                                    // Insert history
                                    DB::table('tblitemprocesshistory')->insert([
                                        'rtcounter' => $findInsertedrtcounter,
                                        'employeeName' => $User,
                                        'editDate' => $curentDatetimeString,
                                        'Module' => $Module,
                                        'Action' => $Action
                                    ]);
                                    
                                    return response()->json([
                                        'success' => true,
                                        'message' => "Scanned and Inserted Successfully",
                                        'item' => $existingWithSameFNSKU->AStitle
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error('Error in processScan - existing with same FNSKU: ' . $e->getMessage());
                                    
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'Error processing scan: ' . $e->getMessage(),
                                        'reason' => 'database_error'
                                    ], 500);
                                }
                            } else {
                                try {
                                    // Log that FNSKU is already used
                                    DB::table('tbladditemstockroomlogs')->insert([
                                        'ASIN' => $getASIN,
                                        'TITLE' => $getTitle,
                                        'FNSKU' => $getFNSKU,
                                        'MSKU' => $getMSKU,
                                        'CONDITIONS' => $getCondition,
                                        'LOCATION' => $location,
                                        'SERIALNUMBER' => $serial
                                    ]);
                                    
                                    return response()->json([
                                        'success' => false,
                                        'message' => "FNSKU is Already Used",
                                        'reason' => 'fnsku_in_use'
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error('Error in processScan - log FNSKU already used: ' . $e->getMessage());
                                    
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'Failed Error inserting to logs',
                                        'reason' => 'database_error'
                                    ], 500);
                                }
                            }
                        }
                    } else {
                        // Try Allrenewed FNSKU
                        $fnsku_data = DB::table('tblmasterfnskuAllrenewed')
                            ->where('FNSKU', $FNSKU)
                            ->first();
                            
                        if ($fnsku_data) {
                            $checkFNSKUstatus = $fnsku_data->Status;
                            $getASIN = $fnsku_data->ASIN;
                            $getCondition = $fnsku_data->grading;
                            $getTitle = $fnsku_data->astitle;
                            $getMSKU = $fnsku_data->MSKU;
                            $getFNSKU = $fnsku_data->FNSKU;
                            $store = 'Allrenewed';

                            
                            $asinData = DB::table('tblmasterasin')
                            ->where('ASIN', $getASIN)
                            ->first();
                            
                            $weightRetail = $asinData->lbs ?? 0;
                            $weightWhite = $asinData->white_lbs ?? 0;
                            $getmetaKeyword = $asinData->metakeyword ?? null;
                            
                            if (($checkFNSKUstatus == "Available") || ($checkFNSKUstatus == null)) {
                                try {
                                    // Get next RT counter
                                    $maxxrt = DB::table('tblproduct')->max('rtcounter');
                                    $newrt = $maxxrt + 1;
                                    
                                    // Insert new item
                                    $newItemId = DB::table('tblproduct')->insertGetId([
                                        'rtcounter' => $newrt,
                                        'serialnumber' => $serial,
                                        'ProductModuleLoc' => $Module,
                                        'warehouselocation' => $location,
                                        'ASiNviewer' => $getASIN,
                                        'FNSKUviewer' => $getFNSKU,
                                        'gradingviewer' => $getCondition,
                                        'AStitle' => $getTitle,
                                        'MSKUviewer' => $getMSKU,
                                        'FbmAvailable' => 1,
                                        'Fulfilledby' => 'FBM',
                                        'quantity' => 1,
                                        'DateCreated' => $curentDatetimeString,
                                        'stockroom_insert_date' => $curentDatetimeString,
                                        'StoreName' => $store
                                    ]);
                                    
                                    // Insert history
                                    DB::table('tblitemprocesshistory')->insert([
                                        'rtcounter' => $newrt,
                                        'employeeName' => $User,
                                        'editDate' => $curentDatetimeString,
                                        'Module' => $Module,
                                        'Action' => $Action
                                    ]);
                                    
                                    // Update FNSKU status
                                    DB::table('tblmasterfnskuAllrenewed')
                                        ->where('FNSKU', $getFNSKU)
                                        ->update([
                                            'Status' => 'Unavailable',
                                            'productid' => $newItemId
                                        ]);
                                    
                                    return response()->json([
                                        'success' => true,
                                        'message' => "Scanned and Inserted Successfully",
                                        'item' => $getTitle
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error('Error in processScan - new Allrenewed FNSKU insert: ' . $e->getMessage());
                                    
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'Error processing scan: ' . $e->getMessage(),
                                        'reason' => 'database_error'
                                    ], 500);
                                }
                            } else {
                                // Check for existing product with this FNSKU and serial
                                $existingWithSameFNSKU = DB::table('tblproduct')
                                    ->where('FNSKUviewer', $getFNSKU)
                                    ->where('serialnumber', $serial)
                                    ->where('returnstatus', 'Not Returned')
                                    ->whereNotIn('ProductModuleLoc', ['Stockroom', 'Soldlist', 'Migrated'])
                                    ->first();
                                
                                if ($existingWithSameFNSKU) {
                                    try {
                                        $findInsertedrtcounter = $existingWithSameFNSKU->rtcounter;
                                        $prodIDunique = $existingWithSameFNSKU->ProductID;
                                        
                                        // Update item to stockroom
                                        DB::table('tblproduct')
                                            ->where('ProductID', $prodIDunique)
                                            ->update([
                                                'ProductModuleLoc' => 'Stockroom',
                                                'stockroom_insert_date' => $curentDatetimeString,
                                                'warehouselocation' => $location
                                            ]);
                                        
                                        // Insert history
                                        DB::table('tblitemprocesshistory')->insert([
                                            'rtcounter' => $findInsertedrtcounter,
                                            'employeeName' => $User,
                                            'editDate' => $curentDatetimeString,
                                            'Module' => $Module,
                                            'Action' => $Action
                                        ]);
                                        
                                        return response()->json([
                                            'success' => true,
                                            'message' => "Scanned and Inserted Successfully",
                                            'item' => $existingWithSameFNSKU->AStitle
                                        ]);
                                    } catch (\Exception $e) {
                                        Log::error('Error in processScan - existing with same Allrenewed FNSKU: ' . $e->getMessage());
                                        
                                        return response()->json([
                                            'success' => false,
                                            'message' => 'Error processing scan: ' . $e->getMessage(),
                                            'reason' => 'database_error'
                                        ], 500);
                                    }
                                } else {
                                    try {
                                        // Log that FNSKU is already used
                                        DB::table('tbladditemstockroomlogs')->insert([
                                            'ASIN' => $getASIN,
                                            'TITLE' => $getTitle,
                                            'FNSKU' => $getFNSKU,
                                            'MSKU' => $getMSKU,
                                            'CONDITIONS' => $getCondition,
                                            'LOCATION' => $location,
                                            'SERIALNUMBER' => $serial
                                        ]);
                                        
                                        return response()->json([
                                            'success' => false,
                                            'message' => "FNSKU is Already Used",
                                            'reason' => 'fnsku_in_use'
                                        ]);
                                    } catch (\Exception $e) {
                                        Log::error('Error in processScan - log Allrenewed FNSKU already used: ' . $e->getMessage());
                                        
                                        return response()->json([
                                            'success' => false,
                                            'message' => 'Failed Error inserting to logs',
                                            'reason' => 'database_error'
                                        ], 500);
                                    }
                                }
                            }
                        } else {
                            // Handle possible different FNSKU format (with prefix)
                            $prefix = substr($FNSKU, 0, 2);
                            
                            if (preg_match('/^[B-W][0-9]/', $prefix)) {
                                $mainFnsku = substr($FNSKU, 2);
                                
                                // Check in tblmasterfnsku
                                $fnsku_data = DB::table('tblmasterfnsku')
                                    ->where('FNSKU', $mainFnsku)
                                    ->first();
                                    
                                if ($fnsku_data) {
                                    try {
                                        $getASIN = $fnsku_data->ASIN;
                                        $getCondition1 = $fnsku_data->grading;
                                        $getTitle1 = $fnsku_data->astitle;
                                        $getMSKU1 = $fnsku_data->MSKU;
                                        $table = 'tblmasterfnsku';
                                        $store = 'Renovar Tech';
                                        
                                        // Determine box type based on condition
                                        $skuCondition = $getCondition1;

                                        $asinData = DB::table('tblmasterasin')
                                        ->where('ASIN', $getASIN)
                                        ->first();
                                        
                                        $weightRetail = $asinData->lbs ?? 0;
                                        $weightWhite = $asinData->white_lbs ?? 0;
                                        $getmetaKeyword = $asinData->metakeyword ?? null;
                                        
                                        if (($skuCondition === 'UsedLikeNew') || ($skuCondition === 'New')) {
                                            $weight = $weightRetail;
                                            $SelectedBox = 'Retailbox';
                                        } else {
                                            if ($weightWhite > 0) {
                                                $weight = $weightWhite;
                                                $SelectedBox = 'Whitebox';
                                            } else {
                                                $weight = $weightRetail;
                                                $SelectedBox = 'Retailbox';
                                            }
                                        }
                                        
                                        // Get next RT counter
                                        $maxxrt = DB::table('tblproduct')->max('rtcounter');
                                        $newrt = $maxxrt + 1;
                                        
                                        // Get current date in different format
                                        $curentDatet2 = $currentDatetime->format('Y-m-d');
                                        
                                        DB::beginTransaction();
                                        
                                        // Insert new item
                                        $newItemId = DB::table('tblproduct')->insertGetId([
                                            'metakeyword' => $getmetaKeyword,
                                            'rtcounter' => $newrt,
                                            'serialnumber' => $serial,
                                            'ProductModuleLoc' => $Module,
                                            'warehouselocation' => $location,
                                            'ASiNviewer' => $getASIN,
                                            'FNSKUviewer' => $FNSKU,
                                            'gradingviewer' => $getCondition1,
                                            'AStitle' => $getTitle1,
                                            'MSKUviewer' => $getMSKU1,
                                            'FbmAvailable' => 1,
                                            'Fulfilledby' => 'FBM',
                                            'quantity' => 1,
                                            'DateCreated' => $curentDatetimeString,
                                            'stockroom_insert_date' => $curentDatetimeString,
                                            'StoreName' => $store,
                                            'BoxWeight' => $weight,
                                            'boxChoice' => $SelectedBox
                                        ]);
                                        
                                        // Insert history
                                        DB::table('tblitemprocesshistory')->insert([
                                            'rtcounter' => $newrt,
                                            'employeeName' => $User,
                                            'editDate' => $curentDatetimeString,
                                            'Module' => $Module,
                                            'Action' => $Action
                                        ]);
                                        
                                        // Insert FNSKU to master list
                                        DB::table($table)->insert([
                                            'ASIN' => $getASIN,
                                            'grading' => $getCondition1,
                                            'astitle' => $getTitle1,
                                            'MSKU' => $getMSKU1,
                                            'FNSKU' => $FNSKU,
                                            'Status' => 'Unavailable',
                                            'productid' => $newItemId,
                                            'dateFreeUp' => $curentDatet2
                                        ]);
                                        
                                        DB::commit();
                                        
                                        return response()->json([
                                            'success' => true,
                                            'message' => "Scanned and Inserted Successfully",
                                            'item' => $getTitle1
                                        ]);
                                    } catch (\Exception $e) {
                                        DB::rollback();
                                        Log::error('Error in processScan - prefixed FNSKU: ' . $e->getMessage());
                                        
                                        // Log the error
                                        DB::table('tbladditemstockroomlogs')->insert([
                                            'FNSKU' => $FNSKU,
                                            'LOCATION' => $location,
                                            'SERIALNUMBER' => $serial,
                                            'NOTE' => 'Cannot find Main FNSKU in database'
                                        ]);
                                        
                                        return response()->json([
                                            'success' => false,
                                            'message' => 'Cannot find Main FNSKU in database',
                                            'reason' => 'main_fnsku_not_found'
                                        ]);
                                    }
                                } 
                                // Check in Allrenewed
                                else {
                                    $fnsku_data = DB::table('tblmasterfnskuAllrenewed')
                                        ->where('FNSKU', $mainFnsku)
                                        ->first();
                                    
                                    if ($fnsku_data) {
                                        try {
                                            $getASIN = $fnsku_data->ASIN;
                                            $getCondition1 = $fnsku_data->grading;
                                            $getTitle1 = $fnsku_data->astitle;
                                            $getMSKU1 = $fnsku_data->MSKU;
                                            $table = 'tblmasterfnskuAllrenewed';
                                            $store = 'Allrenewed';

                                            $asinData = DB::table('tblmasterasin')
                                            ->where('ASIN', $getASIN)
                                            ->first();
                                            
                                            $weightRetail = $asinData->lbs ?? 0;
                                            $weightWhite = $asinData->white_lbs ?? 0;
                                            $getmetaKeyword = $asinData->metakeyword ?? null;
                                            
                                            // Determine box type based on condition
                                            $skuCondition = $getCondition1;
                                            
                                            if (($skuCondition === 'UsedLikeNew') || ($skuCondition === 'New')) {
                                                $weight = $weightRetail;
                                                $SelectedBox = 'Retailbox';
                                            } else {
                                                if ($weightWhite > 0) {
                                                    $weight = $weightWhite;
                                                    $SelectedBox = 'Whitebox';
                                                } else {
                                                    $weight = $weightRetail;
                                                    $SelectedBox = 'Retailbox';
                                                }
                                            }
                                            
                                            // Get next RT counter
                                            $maxxrt = DB::table('tblproduct')->max('rtcounter');
                                            $newrt = $maxxrt + 1;
                                            
                                            // Get current date in different format
                                            $curentDatet2 = $currentDatetime->format('Y-m-d');
                                            
                                            DB::beginTransaction();
                                            
                                            // Insert new item
                                            $newItemId = DB::table('tblproduct')->insertGetId([
                                                'metakeyword' => $getmetaKeyword,
                                                'rtcounter' => $newrt,
                                                'serialnumber' => $serial,
                                                'ProductModuleLoc' => $Module,
                                                'warehouselocation' => $location,
                                                'ASiNviewer' => $getASIN,
                                                'FNSKUviewer' => $FNSKU,
                                                'gradingviewer' => $getCondition1,
                                                'AStitle' => $getTitle1,
                                                'MSKUviewer' => $getMSKU1,
                                                'FbmAvailable' => 1,
                                                'Fulfilledby' => 'FBM',
                                                'quantity' => 1,
                                                'DateCreated' => $curentDatetimeString,
                                                'stockroom_insert_date' => $curentDatetimeString,
                                                'StoreName' => $store,
                                                'BoxWeight' => $weight,
                                                'boxChoice' => $SelectedBox
                                            ]);
                                            
                                            // Insert history
                                            DB::table('tblitemprocesshistory')->insert([
                                                'rtcounter' => $newrt,
                                                'employeeName' => $User,
                                                'editDate' => $curentDatetimeString,
                                                'Module' => $Module,
                                                'Action' => $Action
                                            ]);
                                            
                                            // Insert FNSKU to master list
                                            DB::table($table)->insert([
                                                'ASIN' => $getASIN,
                                                'grading' => $getCondition1,
                                                'astitle' => $getTitle1,
                                                'MSKU' => $getMSKU1,
                                                'FNSKU' => $FNSKU,
                                                'Status' => 'Unavailable',
                                                'productid' => $newItemId,
                                                'dateFreeUp' => $curentDatet2
                                            ]);
                                            
                                            DB::commit();
                                            
                                            return response()->json([
                                                'success' => true,
                                                'message' => "Scanned and Inserted Successfully",
                                                'item' => $getTitle1
                                            ]);
                                        } catch (\Exception $e) {
                                            DB::rollback();
                                            Log::error('Error in processScan - prefixed Allrenewed FNSKU: ' . $e->getMessage());
                                            
                                            // Log the error
                                            DB::table('tbladditemstockroomlogs')->insert([
                                                'FNSKU' => $FNSKU,
                                                'LOCATION' => $location,
                                                'SERIALNUMBER' => $serial,
                                                'NOTE' => 'Cannot find Main FNSKU in database'
                                            ]);
                                            
                                            return response()->json([
                                                'success' => false,
                                                'message' => 'Cannot find Main FNSKU in database',
                                                'reason' => 'main_fnsku_not_found'
                                            ]);
                                        }
                                    } else {
                                        return response()->json([
                                            'success' => false,
                                            'message' => "FNSKU not found in database",
                                            'reason' => 'fnsku_not_found'
                                        ]);
                                    }
                                }
                            } else {
                                return response()->json([
                                    'success' => false,
                                    'message' => "Invalid FNSKU",
                                    'reason' => 'invalid_fnsku_format'
                                ]);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Unhandled error in processScan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing scan: ' . $e->getMessage(),
                'reason' => 'server_error'
            ], 500);
        }
    }

    /**
     * Print a label for a product
     */
    public function printLabel(Request $request)
    {
        $request->validate([
            'productId' => 'required|integer'
        ]);
        
        $productId = $request->productId;
        
        try {
            $product = DB::table('tblproduct')
                ->where('ProductID', $productId)
                ->first();
            
            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found'
                ]);
            }
            
            // Here you would implement your actual label printing logic
            // This might involve generating a print file and sending to printer
            
            // For now, we'll just simulate a successful print
            
            return response()->json([
                'status' => 'success',
                'message' => 'Label printing started'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in printLabel: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error printing label: ' . $e->getMessage()
            ], 500);
        }
    }
}