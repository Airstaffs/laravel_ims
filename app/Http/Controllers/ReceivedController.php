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

class ReceivedController extends Controller
{   
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $location = $request->input('location', 'Received');
        
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

    public function verifyTracking(Request $request)
    {
        $tracking = $request->input('tracking');
        
        // Extract the last 12 digits
        $last12Digits = substr($tracking, -12);
        
        // Check if tracking exists in tblproduct where ProductModuleLoc = 'Orders'
        $product = DB::table('tblproduct')
            ->where('trackingnumber', 'like', '%' . $last12Digits . '%')
            ->where('ProductModuleLoc', 'Received')
            ->first();
            
        if ($product) {
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
                if (property_exists($product, $field) && !empty($product->$field)) {
                    $productDetails->$field = $product->$field;
                }
            }
            
            return response()->json([
                'found' => true,
                'productId' => $product->ProductID,
                'rtcounter' => $product->rtcounter,
                'trackingnumber' => $product->trackingnumber,
                'productDetails' => $productDetails // Include product details with images
            ]);
        } else {
            return response()->json(['found' => false]);
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
            $productExists = DB::table('tblproduct')
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
                $updateResult = DB::table('tblproduct')
                    ->where('ProductID', $request->productId)
                    ->update($updateData);
                    
                // Record history
                DB::table('tblitemprocesshistory')->insert([
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
                $updateResult = DB::table('tblproduct')
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
                DB::table('tblitemprocesshistory')->insert([
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
            Log::error('Error processing scan:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing scan: ' . $e->getMessage(),
                'reason' => 'server_error'
            ], 500);
        }
    }
    public function uploadImage(Request $request)
{
    Log::info('Received image upload request:', [
        'productId' => $request->input('productId'),
        'imageIndex' => $request->input('imageIndex'),
        'hasImage' => $request->has('imageData')
    ]);
    
    try {
        $request->validate([
            'productId' => 'required',
            'imageIndex' => 'required|integer',
            'imageData' => 'required|string',
            'hasSerialTwo' => 'required|boolean',
            'hasPcn' => 'required|boolean'
        ]);
        
        $productId = $request->input('productId');
        $imageIndex = $request->input('imageIndex');
        $imageData = $request->input('imageData');
        $hasSerialTwo = $request->input('hasSerialTwo');
        $hasPcn = $request->input('hasPcn');
        
        // Check if product exists
        $productExists = DB::table('tblproduct')
            ->where('ProductID', $productId)
            ->exists();
            
        if (!$productExists) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found with ID: ' . $productId
            ], 404);
        }
        
        // Process the image
        if (strpos($imageData, 'data:image') === 0) {
            // Extract the image data from base64 string
            list($type, $data) = explode(';', $imageData);
            list(, $data) = explode(',', $data);
            $imageData = base64_decode($data);
            
            // Use absolute path for image directory
            $directory = public_path('images/product_images');
            
            // Make sure the directory exists
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }
            
            // Generate filename based on index and product ID
            $filename = '';
            
            // First two images are special - serial captures + PCN
            if ($imageIndex === 0) {
                // First serial image
                $filename = $productId . '.jpg';
                $columnName = 'Serial1capturedimg';
            } else if ($imageIndex === 1 && $hasSerialTwo) {
                // Second serial image
                $filename = $productId . '_1.jpg';
                $columnName = 'Serial2capturedimg';
            } else if (($imageIndex === 1 && !$hasSerialTwo && $hasPcn) || 
                      ($imageIndex === 2 && $hasSerialTwo && $hasPcn)) {
                // PCN image - depends on whether we have a second serial
                $filename = $productId . '_2.jpg';
                $columnName = 'PCNcapturedimg';
            } else {
                // Additional images - calculate correct index
                $startingIndex = 0;
                if ($hasSerialTwo) $startingIndex++;
                if ($hasPcn) $startingIndex++;
                
                $suffix = $imageIndex + 1;
                if ($imageIndex > $startingIndex) {
                    $suffix = $imageIndex + (3 - $startingIndex);
                }
                
                $filename = $productId . '_' . $suffix . '.jpg';
                
                // Calculate which captured image column to use
                $columnIndex = $imageIndex - $startingIndex - 1;
                if ($columnIndex < 0) $columnIndex = 0;
                
                $capturedImgColumns = [
                    'capturedimg1', 'capturedimg2', 'capturedimg3', 'capturedimg4',
                    'capturedimg5', 'capturedimg6', 'capturedimg7', 'capturedimg8'
                ];
                
                if ($columnIndex < count($capturedImgColumns)) {
                    $columnName = $capturedImgColumns[$columnIndex];
                } else {
                    $columnName = $capturedImgColumns[0]; // Default to first column if out of range
                }
            }
            
            $path = $directory . '/' . $filename;
            
            // Store the image directly using File
            File::put($path, $imageData);
            
            // Update the database with the image filename
            DB::table('tblproduct')
                ->where('ProductID', $productId)
                ->update([$columnName => $filename]);
            
            Log::info('Image saved:', [
                'path' => $path, 
                'filename' => $filename, 
                'column' => $columnName
            ]);
            
            return response()->json([
                'success' => true,
                'filename' => $filename,
                'column' => $columnName
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image data format'
            ], 400);
        }
    } catch (\Exception $e) {
        Log::error('Error uploading image:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error uploading image: ' . $e->getMessage()
        ], 500);
    }
}

}