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
        // Copy your existing uploadImage method code here
        Log::info('Received image upload request in new controller:', [
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
            Log::error('Error uploading image in new controller:', [
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