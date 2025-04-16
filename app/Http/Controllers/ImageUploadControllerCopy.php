<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;

class ImageUploadController extends BasetablesController
{
    /**
     * Constructor to initialize table names
     */
    public function __construct()
    {
        parent::__construct();
        
        // The capturedImagesTable will be dynamically set based on the company
        // from the authenticated user in the parent constructor
        $this->capturedImagesTable = $this->getTableName('capturedimages');
        
        Log::debug('Initialized table names:', [
            'capturedImagesTable' => $this->capturedImagesTable,
            'productTable' => $this->productTable
        ]);
    }

    public function upload(Request $request)
    {
        Log::info('Received image upload request:', [
            'productId' => $request->input('productId'),
            'imageIndex' => $request->input('imageIndex'),
            'company' => $this->company,
            'capturedImagesTable' => $this->capturedImagesTable
        ]);
        
        try {
            // Skip validation for some fields to speed things up
            $productId = (int)$request->input('productId');
            $imageIndex = (int)$request->input('imageIndex');
            $imageData = $request->input('imageData');
            
            // Quick validation of critical fields
            if (empty($productId) || !is_numeric($productId) || empty($imageData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields'
                ], 400);
            }
            
            // Check if product exists and continue if it does
            if (!DB::table($this->productTable)->where('ProductID', $productId)->exists()) {
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
                $companyFolder = $this->company ?: 'default';
                $directory = public_path("images/product_images/{$companyFolder}");
                if (!File::exists($directory)) {
                    File::makeDirectory($directory, 0755, true);
                }
                
                // Simplified filename calculation
                $imageNumber = $imageIndex + 1;
                $filename = $productId . '_img' . $imageNumber . '.jpg';
                
                // Column name for the capturedimages table
                $capturedImgColumn = 'capturedimg' . min($imageNumber, 12); // Limit to 12 columns
                
                $path = $directory . '/' . $filename;
                
                // Store the image
                File::put($path, $imageData);
                
                // Begin transaction
                DB::beginTransaction();
                
                try {
                    // Check if a record exists for this product
                    $existingRecord = DB::table($this->capturedImagesTable)
                        ->where('ProductID', $productId)
                        ->first();
                    
                    if ($existingRecord) {
                        // Update existing record with the new image
                        DB::table($this->capturedImagesTable)
                            ->where('id', $existingRecord->id)
                            ->update([
                                $capturedImgColumn => $filename,
                                'UpdatedAt' => now()
                            ]);
                        
                        $imageRecordId = $existingRecord->id;
                    } else {
                        // Insert new record
                        $imageRecordId = DB::table($this->capturedImagesTable)->insertGetId([
                            'ProductID' => $productId,
                            $capturedImgColumn => $filename,
                            'CreatedAt' => now(),
                            'UpdatedAt' => now()
                        ]);
                    }
                    
                    // Update reference in the product table
                    DB::table($this->productTable)
                        ->where('ProductID', $productId)
                        ->update([$capturedImgColumn => $imageRecordId]);
                    
                    DB::commit();
                    
                    Log::info('Image saved:', [
                        'path' => $path, 
                        'filename' => $filename, 
                        'column' => $capturedImgColumn,
                        'imageRecordId' => $imageRecordId,
                        'company' => $this->company,
                        'capturedImagesTable' => $this->capturedImagesTable
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'filename' => $filename,
                        'column' => $capturedImgColumn,
                        'imageRecordId' => $imageRecordId
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid image data format'
                ], 400);
            }
        } catch (\Exception $e) {
            $this->logError('Error uploading image', $e, [
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