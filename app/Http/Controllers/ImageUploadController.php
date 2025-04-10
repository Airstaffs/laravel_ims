<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ImageUploadController extends Controller
{
    public function upload(Request $request)
    {
        Log::info('Received image upload request:', [
            'productId' => $request->input('productId'),
            'imageIndex' => $request->input('imageIndex')
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
            if (!DB::table('tblproduct')->where('ProductID', $productId)->exists()) {
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
                
                // Ensure directory exists
                $directory = public_path('images/product_images');
                if (!File::exists($directory)) {
                    File::makeDirectory($directory, 0755, true);
                }
                
                // Simplified filename and column name calculation
                // Just use a sequential numbering system for all images
                $imageNumber = $imageIndex + 1;
                $filename = $productId . '_img' . $imageNumber . '.jpg';
                
                // Use capturedimg1-12 columns
                $columnName = 'capturedimg' . min($imageNumber, 12); // Limit to 12 columns
                
                $path = $directory . '/' . $filename;
                
                // Store the image
                File::put($path, $imageData);
                
                // Update database
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