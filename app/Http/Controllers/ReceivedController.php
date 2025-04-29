<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Rpn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use DateTime;
use DateTimeZone;

class ReceivedController extends BasetablesController
{   
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $location = $request->input('location', 'Received');
        
        $products = DB::table($this->productTable)
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
            ->paginate($perPage);
        
        return response()->json($products);
    }
    
    public function verifyTracking(Request $request)
    {
        $tracking = $request->input('tracking');
        
        // Extract the last 12 digits
        $last12Digits = substr($tracking, -12);
        
        // First, check if tracking exists in Labeling (already scanned)
        $labelingProduct = DB::table($this->productTable)
            ->where('trackingnumber', 'like', '%' . $last12Digits . '%')
            ->whereIn('ProductModuleLoc', ['Labeling', 'Validation'])
            ->first();
            
        if ($labelingProduct) {
            // Product exists but has already been scanned and moved to Labeling
            return response()->json([
                'found' => true,
                'productId' => $labelingProduct->ProductID,
                'rtcounter' => $labelingProduct->rtcounter,
                'trackingnumber' => $labelingProduct->trackingnumber,
                'alreadyScanned' => true
            ]);
        }
        
        // Then check if it exists in Received (valid for processing)
        $receivedProduct = DB::table($this->productTable)
            ->where('trackingnumber', 'like', '%' . $last12Digits . '%')
            ->where('ProductModuleLoc', 'Received')
            ->first();
            
        if ($receivedProduct) {
            // Get image fields for the product
            $imageFields = [
                'img1', 'img2', 'img3', 'img4', 'img5',
                'img6', 'img7', 'img8', 'img9', 'img10',
                'img11', 'img12', 'img13', 'img14', 'img15'
            ];
            
            // Create a productDetails object with just the necessary fields
            $productDetails = new \stdClass();
            
            // Add image fields if they exist
            foreach ($imageFields as $field) {
                if (property_exists($receivedProduct, $field) && !empty($receivedProduct->$field)) {
                    $productDetails->$field = $receivedProduct->$field;
                }
            }
            
            return response()->json([
                'found' => true,
                'productId' => $receivedProduct->ProductID,
                'rtcounter' => $receivedProduct->rtcounter,
                'trackingnumber' => $receivedProduct->trackingnumber,
                'productDetails' => $productDetails
            ]);
        }
        
        // Product not found
        return response()->json(['found' => false]);
    }

    public function validatePcn(Request $request)
    {
        try {
            // Get the PCN from the request
            $pcn = $request->input('pcn');
            
            if (empty($pcn)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'PCN is required'
                ], 400);
            }
            
            // Check if PCN exists in the database
            $pcnExists = DB::table($this->productTable)
                ->where('PCN', $pcn)
                ->exists();
                
            return response()->json([
                'valid' => true, // Format is valid (validated in frontend)
                'alreadyUsed' => $pcnExists,
                'pcn' => $pcn
            ]);
        } catch (\Exception $e) {
            // Log the error
            $this->logError('Error validating PCN', $e, ['pcn' => $pcn]);
            
            return response()->json([
                'valid' => false,
                'message' => 'Error validating PCN: ' . $e->getMessage()
            ], 500);
        }
    }

    
    public function processScan(Request $request)
    {
        // Log all incoming data
        Log::info('Received data:', $request->all());
        
        try {
            // Validate based on status (pass/fail)
            if ($request->status === 'fail') {
                $request->validate([
                    'trackingNumber' => 'required',
                    'status' => 'required|in:fail',
                    'basketNumber' => ['required', 'regex:/^(BKT|SH|ENV)\d+$/i'],
                    'pcnNumber' => ['required', 'regex:/^PCN\d+$/i'], // PCN format validation for failed items
                    'productId' => 'required',
                    'rtcounter' => 'required'
                    // Removed images validation
                ]);
            } else {
                // Your existing validation for pass status
                $request->validate([
                    'trackingNumber' => 'required',
                    'status' => 'required|in:pass',
                    'firstSerialNumber' => 'required',
                    'secondSerialNumber' => 'required',
                    'pcnNumber' => ['required', 'regex:/^PCN\d+$/i'], // PCN format validation for pass items
                    'basketNumber' => ['required', 'regex:/^(BKT|SH|ENV)\d+$/i'],
                    'productId' => 'required',
                    'rtcounter' => 'required'
                    // Removed images validation
                ]);
            }
    
            DB::beginTransaction();
            
            // Get the last 12 digits of the tracking number
            $last12Digits = substr($request->trackingNumber, -12);
            
            // Get current user ID from session
            $user = Auth::id() ?? session('user_name', 'Unknown');
            
            // Get California time
            $californiaTimezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $californiaTimezone);
            $formattedDatetime = $currentDatetime->format('Y-m-d H:i:s');
            
            // Check if the product exists before updating
            $productExists = DB::table($this->productTable)
                ->where('ProductID', $request->productId)
                ->exists();
                
            if (!$productExists) {
                Log::error('Product not found', [
                    'productId' => $request->productId,
                    'location' => 'Received'
                ]);
                throw new \Exception('Product not found with ID: ' . $request->productId);
            }
            
            // Process based on status
            if ($request->status === 'fail') {
                // Prepare update data
                $updateData = [
                    'ProductModuleLoc' => 'RTS',
                    'PCN' => $request->pcnNumber,
                    'basketnumber' => $request->basketNumber,
                    'Username' => $user
                ];
    
                // Update product status for failed item
                $updateResult = DB::table($this->productTable)
                    ->where('ProductID', $request->productId)
                    ->update($updateData);
                    
                // Record history
                DB::table($this->itemProcessHistoryTable)->insert([
                    'employeeName' => $user,
                    'editDate' => $formattedDatetime,
                    'Module' => 'Received Module',
                    'Action' => 'Scan and Failed Receive',
                    'rtcounter' => $request->rtcounter
                ]);
                
                Log::info('Failed item processed', [
                    'updateResult' => $updateResult
                ]);
                
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'item' => $request->trackingNumber . ' marked as failed',
                    'playsound' => 1
                ]);
            } else {
                // Process successfully received item
                
                // Prepare the database update
                $updateData = [
                    'serialnumber' => $request->firstSerialNumber,
                    'serialnumberb' => $request->secondSerialNumber,
                    'PCN' => $request->pcnNumber,
                    'basketnumber' => $request->basketNumber,
                    'ProductModuleLoc' => 'Labeling',
                    'Username' => $user
                ];
                
                // Update the product
                $updateResult = DB::table($this->productTable)
                    ->where('ProductID', $request->productId)
                    ->update($updateData);
                
                Log::info('Update result:', [
                    'rowsAffected' => $updateResult
                ]);
                
                if ($updateResult === 0) {
                    Log::warning('No rows were updated', [
                        'productId' => $request->productId
                    ]);
                }
                
                // Record history
                DB::table($this->itemProcessHistoryTable)->insert([
                    'employeeName' => $user,
                    'editDate' => $formattedDatetime,
                    'Module' => 'Received Module',
                    'Action' => 'Scan and Received',
                    'rtcounter' => $request->rtcounter
                ]);
                
                DB::commit();
                Log::info('Transaction committed successfully');
                
                return response()->json([
                    'success' => true,
                    'item' => $request->trackingNumber . ' processed successfully',
                    'playsound' => 1
                ]);
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error:', ['errors' => $e->errors()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . json_encode($e->errors()),
                'errors' => $e->errors(),
                'reason' => 'validation_error'
            ], 422);
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error processing scan', $e, $request->all());
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing scan: ' . $e->getMessage(),
                'reason' => 'server_error'
            ], 500);
        }
    }
}