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
            return response()->json([
                'found' => true,
                'productId' => $product->ProductID,
                'rtcounter' => $product->rtcounter, // Added rtcounter to the response
                'trackingnumber' => $product->trackingnumber
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
                    'rtcounter' => 'required',
                    'images' => 'sometimes|array'
                ]);
            } else {
                // Your existing validation for pass status with PCN format validation
                $request->validate([
                    'trackingNumber' => 'required',
                    'status' => 'required|in:pass',
                    'firstSerialNumber' => 'required',
                    'secondSerialNumber' => 'required',
                    'pcnNumber' => ['required', 'regex:/^PCN\d+$/i'], // PCN format validation for pass items
                    'basketNumber' => ['required', 'regex:/^(BKT|SH|ENV)\d+$/i'],
                    'productId' => 'required',
                    'rtcounter' => 'required',
                    'images' => 'sometimes|array'
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

                // Update product status for failed item first
                $updateResult = DB::table('tblproduct')
                    ->where('ProductID', $request->productId)
                    ->update($updateData);
                
                // Only process images if the update was successful
                $imageFilenames = [];
                $columnMapping = [];
                
                if ($updateResult > 0 && $request->has('images') && !empty($request->images)) {
                    $imageCount = count($request->images);
                    Log::info('Processing images for failed item:', ['count' => $imageCount]);
                    
                    // Use absolute path for image directory
                    $directory = public_path('images/product_images');
                    
                    // Make sure the directory exists
                    if (!File::exists($directory)) {
                        File::makeDirectory($directory, 0755, true);
                    }
                    
                    // For failed items, all images go to capturedimg1-8, starting with productid_3
                    $capturedImgColumns = [
                        'capturedimg1', 'capturedimg2', 'capturedimg3', 'capturedimg4',
                        'capturedimg5', 'capturedimg6', 'capturedimg7', 'capturedimg8'
                    ];
                    
                    for ($i = 0; $i < $imageCount && $i < count($capturedImgColumns); $i++) {
                        $imageData = $request->images[$i];
                        
                        // If the image is base64 encoded
                        if (strpos($imageData, 'data:image') === 0) {
                            // Extract the image data from base64 string
                            list($type, $data) = explode(';', $imageData);
                            list(, $data) = explode(',', $data);
                            $imageData = base64_decode($data);
                            
                            // For failed items, start with productId_3
                            $suffix = $i + 3;
                            $filename = $request->productId . '_' . $suffix . '.jpg';
                            $path = $directory . '/' . $filename;
                            
                            // Store the image directly using File
                            File::put($path, $imageData);
                            $imageFilenames[] = $filename;
                            $columnMapping[] = $capturedImgColumns[$i];
                            
                            Log::info('Failed item image saved:', ['path' => $path, 'filename' => $filename]);
                        }
                    }
                    
                    // Now update with image information if we have any
                    if (count($imageFilenames) > 0) {
                        $imageUpdateData = [];
                        
                        // Add image filenames to update data
                        for ($i = 0; $i < count($imageFilenames); $i++) {
                            $imageUpdateData[$columnMapping[$i]] = $imageFilenames[$i];
                        }
                        
                        // Update with image information
                        DB::table('tblproduct')
                            ->where('ProductID', $request->productId)
                            ->update($imageUpdateData);
                    }
                }
                    
                // Record history
                DB::table('tblitemprocesshistory')->insert([
                    'employeeName' => $user,
                    'editDate' => $formattedDatetime,
                    'Module' => 'Received Module',
                    'Action' => 'Scan and Failed Receive',
                    'rtcounter' => $request->rtcounter
                ]);
                
                Log::info('Failed item processed', [
                    'updateResult' => $updateResult,
                    'images' => $imageFilenames,
                    'columns' => $columnMapping
                ]);
                
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'item' => $request->trackingNumber . ' marked as failed',
                    'images' => $imageFilenames,
                    'columns' => $columnMapping,
                    'playsound' => 1,
                    'clearImages' => true // Signal frontend to clear images
                ]);
            } else {
                // Process successfully received item
                
                // Prepare the database update first
                $updateData = [
                    'serialnumber' => $request->firstSerialNumber,
                    'serialnumberb' => $request->secondSerialNumber,
                    'PCN' => $request->pcnNumber,
                    'basketnumber' => $request->basketNumber,
                    'ProductModuleLoc' => 'Labeling',
                    'Username' => $user
                ];
                
                // Update the product first before processing images
                $updateResult = DB::table('tblproduct')
                    ->where('ProductID', $request->productId)
                    ->update($updateData);
                
                // Only process images if the update was successful
                $imageLinks = [];
                $imageFilenames = [];
                $columnMapping = [];
                
                if ($updateResult > 0 && $request->has('images') && !empty($request->images)) {
                    $imageCount = count($request->images);
                    Log::info('Processing images:', ['count' => $imageCount]);
                    
                    // Use absolute path for image directory
                    $directory = public_path('images/product_images');
                    
                    // Make sure the directory exists
                    if (!File::exists($directory)) {
                        File::makeDirectory($directory, 0755, true);
                    }
                    
                    $hasSerialTwo = ($request->secondSerialNumber !== 'N/A');
                    $hasPcn = ($request->pcnNumber !== 'N/A');
                    
                    for ($i = 0; $i < $imageCount && $i < 10; $i++) {
                        $imageData = $request->images[$i];
                        
                        // If the image is base64 encoded
                        if (strpos($imageData, 'data:image') === 0) {
                            // Extract the image data from base64 string
                            list($type, $data) = explode(';', $imageData);
                            list(, $data) = explode(',', $data);
                            $imageData = base64_decode($data);
                            
                            // Generate filenames based on index and product ID
                            $filename = '';
                            
                            // First two images are special - serial captures + PCN
                            if ($i === 0) {
                                // First serial image
                                $filename = $request->productId . '.jpg';
                            } else if ($i === 1 && $hasSerialTwo) {
                                // Second serial image
                                $filename = $request->productId . '_1.jpg';
                            } else if (($i === 1 && !$hasSerialTwo && $hasPcn) || 
                                      ($i === 2 && $hasSerialTwo && $hasPcn)) {
                                // PCN image - depends on whether we have a second serial
                                $filename = $request->productId . '_2.jpg';
                            } else {
                                // Additional images - calculate correct index
                                $startingIndex = 0;
                                if ($hasSerialTwo) $startingIndex++;
                                if ($hasPcn) $startingIndex++;
                                
                                $suffix = $i + 1;
                                if ($i > $startingIndex) {
                                    $suffix = $i + (3 - $startingIndex);
                                }
                                
                                $filename = $request->productId . '_' . $suffix . '.jpg';
                            }
                            
                            $path = $directory . '/' . $filename;
                            
                            // Store the image directly using File
                            File::put($path, $imageData);
                            $imageLinks[] = $path;
                            $imageFilenames[] = $filename;
                            
                            Log::info('Image saved:', ['path' => $path, 'filename' => $filename]);
                        }
                    }
                    
                    // If images were added, update image columns
                    if (count($imageFilenames) > 0) {
                        $imageUpdateData = [];
                        
                        // First assign serial capture images
                        if (count($imageFilenames) > 0) {
                            $imageUpdateData['Serial1capturedimg'] = $imageFilenames[0];
                            $columnMapping[] = 'Serial1capturedimg';
                            
                            $imageIndex = 1;
                            
                            if ($hasSerialTwo && count($imageFilenames) > $imageIndex) {
                                $imageUpdateData['Serial2capturedimg'] = $imageFilenames[$imageIndex];
                                $columnMapping[] = 'Serial2capturedimg';
                                $imageIndex++;
                            }
                            
                            // Add PCN image if present
                            if ($hasPcn && count($imageFilenames) > $imageIndex) {
                                $imageUpdateData['PCNcapturedimg'] = $imageFilenames[$imageIndex];
                                $columnMapping[] = 'PCNcapturedimg';
                                $imageIndex++;
                            }
                            
                            // Calculate starting index for additional images
                            $startIndex = $imageIndex;
                            
                            // Map remaining images to capturedimg1 through capturedimg8
                            $capturedImgColumns = [
                                'capturedimg1', 'capturedimg2', 'capturedimg3', 'capturedimg4',
                                'capturedimg5', 'capturedimg6', 'capturedimg7', 'capturedimg8'
                            ];
                            
                            for ($i = $startIndex; $i < count($imageFilenames) && ($i - $startIndex) < count($capturedImgColumns); $i++) {
                                $columnIndex = $i - $startIndex;
                                $imageUpdateData[$capturedImgColumns[$columnIndex]] = $imageFilenames[$i];
                                $columnMapping[] = $capturedImgColumns[$columnIndex];
                            }
                        }
                        
                        // Update with image information
                        DB::table('tblproduct')
                            ->where('ProductID', $request->productId)
                            ->update($imageUpdateData);
                    }
                }
                
                Log::info('Update result:', [
                    'rowsAffected' => $updateResult, 
                    'imageColumns' => $columnMapping ?? []
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
                    'images' => $imageFilenames ?? [],
                    'columns' => $columnMapping ?? [],
                    'playsound' => 1,
                    'clearImages' => true // Signal frontend to clear images
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
}