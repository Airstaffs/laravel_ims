<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Rpn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use DateTime;
use DateTimeZone;  

class UnreceivedController extends BasetablesController
{   
    
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $location = $request->input('location', 'Orders');
        
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
        
        // First, check if the product exists in Received status (already scanned)
        $receivedProduct = DB::table($this->productTable)
            ->where('trackingnumber', 'like', '%' . $last12Digits . '%')
            ->whereIn('ProductModuleLoc', ['Received', 'Labeling'])
            ->first();
            
        if ($receivedProduct) {
            // Product exists but has already been received
            return response()->json([
                'found' => true,
                'productId' => $receivedProduct->ProductID,
                'rtcounter' => $receivedProduct->rtcounter,
                'trackingnumber' => $receivedProduct->trackingnumber,
                'alreadyScanned' => true
            ]);
        }
        
        // If not found in Received, check in Orders
        $ordersProduct = DB::table($this->productTable)
            ->where('trackingnumber', 'like', '%' . $last12Digits . '%')
            ->where('ProductModuleLoc', 'Orders')
            ->first();
            
        if ($ordersProduct) {
            // Product found in Orders and ready for receiving
            return response()->json([
                'found' => true,
                'productId' => $ordersProduct->ProductID,
                'rtcounter' => $ordersProduct->rtcounter,
                'trackingnumber' => $ordersProduct->trackingnumber,
                'alreadyScanned' => false
            ]);
        }
        
        // Product not found anywhere
        return response()->json(['found' => false]);
    }
    
            
    public function getNextRpn()
    {
        try {
            // Get the current RPN from RPN sticker table
            $currentRpn = DB::table($this->rpnStickerTable)
                ->where('RPNid', 1)
                ->first();
                
            if (!$currentRpn) {
                return response()->json(['error' => 'RPN record not found'], 404);
            }
            
            // Calculate the next RPN value
            $nextRpnValue = $currentRpn->RPNstart + 1;
            $formattedRpn = 'RPN' . str_pad($nextRpnValue, 5, '0', STR_PAD_LEFT);
            
            return response()->json(['rpn' => $formattedRpn, 'rawValue' => $nextRpnValue]);
        } catch (\Exception $e) {
            $this->logError('Error getting next RPN', $e);
            return response()->json(['error' => 'Could not retrieve next RPN'], 500);
        }
    }

    
    public function processScan(Request $request)
    {
        // Log all incoming data
        Log::info('Received data:', $request->all());
        
        try {
            // Validate the request
            $request->validate([
                'trackingNumber' => 'required',
                'rpnNumber' => 'required',
                'prdDate' => 'required|date',
                'productId' => 'required',
                'rtcounter' => 'required' // Added rtcounter validation
            ]);
            
            DB::beginTransaction();
            
            // Format the date for PRD
            $prdDate = new DateTime($request->prdDate);
            $formattedPRD = 'PRD' . $prdDate->format('mdy');
            
            Log::info('Formatted PRD value:', ['PRD' => $formattedPRD]);
            
            // Get the last 12 digits of the tracking number
            $last12Digits = substr($request->trackingNumber, -12);
            
            // Get current user ID from session
            $User = Auth::id() ?? session('user_name', 'Unknown');
            
            // Get California time
            $californiaTimezone = new DateTimeZone('America/Los_Angeles');
            $currentDatetime = new DateTime('now', $californiaTimezone);
            $formattedDatetime = $currentDatetime->format('Y-m-d H:i:s');
            
            // Parse the RPN number to get the numeric value
            $rpnValue = $request->rpnNumber;
            if (strpos($rpnValue, 'RPN') === 0) {
                $rpnValue = intval(substr($rpnValue, 3)); // Extract numeric part
            } else {
                $rpnValue = intval($rpnValue);
            }
            
            // Check if the product exists before updating
            $productExists = DB::table($this->productTable)
                ->where('ProductID', $request->productId)
                ->where('ProductModuleLoc', 'Orders')
                ->exists();
                
            if (!$productExists) {
                Log::error('Product not found', [
                    'productId' => $request->productId,
                    'location' => 'Orders'
                ]);
                throw new \Exception('Product not found with ID: ' . $request->productId);
            }
            
            // Update the specific product using its ID
            $updateResult = DB::table($this->productTable)
                ->where('ProductID', $request->productId)
                ->where('ProductModuleLoc', 'Orders')
                ->update([
                    'RPN' => $request->rpnNumber,
                    'PRD' => $formattedPRD, // Use the correctly formatted PRD value
                    'ProductModuleLoc' => 'Received'
                ]);
                
            Log::info('Update result:', ['rowsAffected' => $updateResult]);
                
            if ($updateResult === 0) {
                Log::warning('No rows were updated', [
                    'productId' => $request->productId,
                    'ProductModuleLoc' => 'Orders'
                ]);
                // Don't throw an exception here, it might be that the product exists 
                // but another condition failed
            }
            
            // Get the next RPN value
            $nextRpnValue = $rpnValue + 1;
            
            // Update the RPN in rpnsticker table with ID 1
            DB::table($this->rpnStickerTable)
                ->where('RPNid', 1)
                ->update([
                    'RPNstart' => $nextRpnValue,
                    'RPNend' => $nextRpnValue,
                    'RPNsticker' => $nextRpnValue
                ]);
            
            // Record history with rtcounter
            DB::table($this->itemProcessHistoryTable)->insert([
                'employeeName' => $User,
                'editDate' => $formattedDatetime,
                'Module' => 'Unreceived Module',
                'Action' => 'Scan and Received',
                'rtcounter' => $request->rtcounter // Added rtcounter to history
            ]);
            
            // Save images if provided
            if ($request->has('Images') && !empty($request->Images)) {
                // Code to save images
                Log::info('Processing images:', ['count' => count($request->Images)]);
            }
            
            DB::commit();
            Log::info('Transaction committed successfully');
            
            return response()->json([
                'success' => true,
                'item' => $request->trackingNumber . ' processed successfully',
                'last12Digits' => $last12Digits,
                'playsound' => 1
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error:', ['errors' => $e->errors()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . json_encode($e->errors()),
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