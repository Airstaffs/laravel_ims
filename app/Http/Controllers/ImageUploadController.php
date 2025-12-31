<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;

class ImageUploadController extends BasetablesController
{

    public function upload(Request $request)
    {
        Log::info('=== IMAGE UPLOAD REQUEST RECEIVED ===', [
            'productId' => $request->input('productId'),
            'imageIndex' => $request->input('imageIndex'),
            'isSerial' => $request->input('isSerial', false),
            'serialIndex' => $request->input('serialIndex', 0),
            'company' => $this->company,
            'capturedImagesTable' => $this->capturedImagesTable,
            'productTable' => $this->productTable,
        ]);
        
        try {
            // Skip validation for some fields to speed things up
            $productId = (int)$request->input('productId');
            $imageIndex = (int)$request->input('imageIndex');
            $imageData = $request->input('imageData');
            
            // Quick validation of critical fields
            if (empty($productId) || !is_numeric($productId) || empty($imageData)) {
                Log::error('Missing required fields', [
                    'productId' => $productId,
                    'imageIndex' => $imageIndex,
                    'hasImageData' => !empty($imageData)
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields'
                ], 400);
            }
            
            // Check if product exists
            $productExists = DB::table($this->productTable)->where('ProductID', $productId)->exists();
            Log::info('Product existence check', [
                'productId' => $productId,
                'exists' => $productExists,
                'table' => $this->productTable
            ]);
            
            if (!$productExists) {
                Log::error('Product not found', [
                    'productId' => $productId,
                    'table' => $this->productTable
                ]);
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
                
                // Ensure directory exists, create company-specific subfolder
                $companyFolder = $this->company ?: 'Airstaffs';
                $directory = public_path("images/product_images/{$companyFolder}");
                if (!File::exists($directory)) {
                    File::makeDirectory($directory, 0755, true);
                    Log::info('Created directory', ['directory' => $directory]);
                }
                
                // Simplified filename calculation
                $imageNumber = $imageIndex + 1;

                // 🧠 Detect if this upload is for serial images
                $isSerial = $request->input('isSerial', false);
                $serialIndex = (int) $request->input('serialIndex', 0);

                Log::info('Image type detection', [
                    'isSerial' => $isSerial,
                    'serialIndex' => $serialIndex,
                    'imageNumber' => $imageNumber
                ]);

                if ($isSerial && $serialIndex === 1) {
                    $capturedImgColumn = 'serialimg1';
                    $filename = $productId . '_serial1.jpg';
                } elseif ($isSerial && $serialIndex === 2) {
                    $capturedImgColumn = 'serialimg2';
                    $filename = $productId . '_serial2.jpg';
                } else {
                    // Normal product images (max 12)
                    $capturedImgColumn = 'capturedimg' . min($imageNumber, 12);
                    $filename = $productId . '_img' . $imageNumber . '.jpg';
                }
                
                $path = $directory . '/' . $filename;
                
                Log::info('Saving image to disk', [
                    'path' => $path,
                    'filename' => $filename,
                    'column' => $capturedImgColumn,
                    'dataSize' => strlen($imageData)
                ]);
                
                // Store the image
                File::put($path, $imageData);
                
                if (!File::exists($path)) {
                    Log::error('File was not created on disk', ['path' => $path]);
                    throw new \Exception('Failed to save image file to disk');
                }
                
                Log::info('Image file saved successfully', [
                    'path' => $path,
                    'fileSize' => File::size($path)
                ]);
                
                // Begin transaction
                DB::beginTransaction();
                
                try {
                    // Check if a record exists for this product
                    $existingRecord = DB::table($this->capturedImagesTable)
                        ->where('ProductID', $productId)
                        ->first();
                    
                    Log::info('Checking for existing record', [
                        'productId' => $productId,
                        'existingRecordFound' => $existingRecord ? true : false,
                        'existingRecordId' => $existingRecord ? $existingRecord->id : null,
                        'table' => $this->capturedImagesTable
                    ]);
                    
                    if ($existingRecord) {
                        // Update existing record with the new image
                        $updateData = [
                            $capturedImgColumn => $filename,
                            'UpdatedAt' => now()
                        ];
                        
                        Log::info('Updating existing record', [
                            'id' => $existingRecord->id,
                            'updateData' => $updateData
                        ]);
                        
                        $updateResult = DB::table($this->capturedImagesTable)
                            ->where('id', $existingRecord->id)
                            ->update($updateData);
                        
                        Log::info('Update result', [
                            'rowsAffected' => $updateResult,
                            'id' => $existingRecord->id
                        ]);
                        
                        $imageRecordId = $existingRecord->id;
                    } else {
                        // Insert new record
                        $insertData = [
                            'ProductID' => $productId,
                            $capturedImgColumn => $filename,
                            'CreatedAt' => now(),
                            'UpdatedAt' => now()
                        ];
                        
                        Log::info('Inserting new record', [
                            'insertData' => $insertData,
                            'table' => $this->capturedImagesTable
                        ]);
                        
                        $imageRecordId = DB::table($this->capturedImagesTable)->insertGetId($insertData);
                        
                        Log::info('Insert result', [
                            'newRecordId' => $imageRecordId
                        ]);
                    }
                    
                    // Verify the record was saved
                    $verifyRecord = DB::table($this->capturedImagesTable)
                        ->where('ProductID', $productId)
                        ->first();
                    
                    Log::info('Verification check', [
                        'recordFound' => $verifyRecord ? true : false,
                        'recordId' => $verifyRecord ? $verifyRecord->id : null,
                        'columnValue' => $verifyRecord ? $verifyRecord->{$capturedImgColumn} : null
                    ]);
                    
                    DB::commit();
                    
                    Log::info('=== IMAGE UPLOAD SUCCESS ===', [
                        'path' => $path, 
                        'filename' => $filename, 
                        'column' => $capturedImgColumn,
                        'imageRecordId' => $imageRecordId,
                        'productId' => $productId,
                        'company' => $this->company,
                        'capturedImagesTable' => $this->capturedImagesTable
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'filename' => $filename,
                        'column' => $capturedImgColumn,
                        'imageRecordId' => $imageRecordId,
                        'productId' => $productId
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Database transaction failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            } else {
                Log::error('Invalid image data format', [
                    'dataPrefix' => substr($imageData, 0, 50)
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid image data format'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('=== IMAGE UPLOAD ERROR ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'productId' => $request->input('productId'),
                'imageIndex' => $request->input('imageIndex'),
                'company' => $this->company,
                'capturedImagesTable' => $this->capturedImagesTable
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error uploading image: ' . $e->getMessage()
            ], 500);
        }
    }
}