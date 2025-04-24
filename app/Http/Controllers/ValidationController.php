<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Rpn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator; // Add this line
use DateTime;
use DateTimeZone;

class ValidationController extends BasetablesController
{
    public function index(Request $request)
    {
        try {
            // Log tables being used for debugging
            Log::info('Tables being used:', [
                'productTable' => $this->productTable,
                'capturedImagesTable' => $this->capturedImagesTable,
                'company' => $this->company
            ]);
            
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Validation');
            $includeImages = $request->boolean('include_images', false);
            
            // Query base products
            $query = DB::table($this->productTable)
            ->leftJoin($this->fnskuTable, $this->productTable.'.FNSKUviewer', '=', $this->fnskuTable.'.fnsku')
            ->select($this->productTable.'.*', $this->fnskuTable.'.asin', $this->fnskuTable.'.astitle') // Select all from product table plus asin from fnsku table
            ->where('ProductModuleLoc', $location)
            ->when($search, function($query) use ($search) {
                return $query->where(function($q) use ($search) {
                    $q->where($this->productTable.'.serialnumber', 'like', "%{$search}%")
                    ->orWhere($this->productTable.'.FNSKUviewer', 'like', "%{$search}%")
                    ->orWhere($this->productTable.'.rtcounter', 'like', "%{$search}%");
                });
            })
            ->orderBy($this->productTable.'.lastDateUpdate', 'desc');
            
            // Get paginated products
            $products = $query->paginate($perPage);
            Log::info('Products fetched successfully', ['count' => $products->count()]);
            
            // If images are requested, fetch them for each product
            if ($includeImages) {
                try {
                    $productIds = $products->pluck('ProductID')->toArray();
                    Log::info('Product IDs for image fetch', ['count' => count($productIds), 'ids' => $productIds]);
                    
                    // IMPORTANT FIX: Use the original table name with 'tbl' prefix
                    $capturedImagesTableName = $this->capturedImagesTable;
                    
                    // Log the actual table name we're checking
                    Log::info('Checking table existence', [
                        'table' => $capturedImagesTableName
                    ]);
                    
                    if (!Schema::hasTable($capturedImagesTableName)) {
                        Log::warning('Captured images table does not exist', [
                            'table' => $capturedImagesTableName
                        ]);
                        // Add company but skip image fetching
                        $products->getCollection()->transform(function ($product) {
                            $product->company = $this->company;
                            return $product;
                        });
                    } else {
                        Log::info('Captured images table exists', ['table' => $capturedImagesTableName]);
                        
                        // Fetch all captured images for these products
                        $capturedImages = DB::table($capturedImagesTableName)
                            ->whereIn('ProductID', $productIds)
                            ->get();
                        
                        Log::info('Captured images fetched', [
                            'count' => $capturedImages->count(),
                            'sample' => $capturedImages->take(1)
                        ]);
                        
                        // Create a lookup by ProductID for efficient access
                        $imagesByProductId = [];
                        foreach ($capturedImages as $img) {
                            $imagesByProductId[$img->ProductID] = $img;
                        }
                        
                        // Add capturedImages data to each product
                        $products->getCollection()->transform(function ($product) use ($imagesByProductId) {
                            // Always add the company for proper image path construction
                            $product->company = $this->company;
                            
                            // Check if we have image data for this product
                            if (isset($imagesByProductId[$product->ProductID])) {
                                // Set capturedImages as a proper object
                                $product->capturedImages = $imagesByProductId[$product->ProductID];
                                
                                // Set img1 directly for the main thumbnail display if not already set
                                if (empty($product->img1) && !empty($product->capturedImages->capturedimg1)) {
                                    $product->img1 = $product->capturedImages->capturedimg1;
                                }
                                
                                // Log success for debugging
                                Log::info('Added captured images to product', [
                                    'ProductID' => $product->ProductID,
                                    'capturedImages' => json_encode($product->capturedImages)
                                ]);
                            } else {
                                // Log failure for debugging
                                Log::info('No captured images found for product', [
                                    'ProductID' => $product->ProductID
                                ]);
                                
                                // Initialize empty capturedImages object to prevent JS errors
                                $product->capturedImages = (object)[];
                            }
                            
                            return $product;
                        });
                    }
                } catch (\Exception $e) {
                    Log::error('Error fetching images', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // Continue without images but with company
                    $products->getCollection()->transform(function ($product) {
                        $product->company = $this->company;
                        $product->capturedImages = (object)[]; // Initialize empty object to prevent JS errors
                        return $product;
                    });
                }
            }
            
            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error in ValidationController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'An error occurred while fetching products',
                'message' => $e->getMessage()
            ], 500);
        }
    }

     
     // Move a product from Labeling to Stockroom
     
     public function moveToStockroom(Request $request)
     {
         try {
             // Validate the incoming request
             $validator = Validator::make($request->all(), [
                 'product_id' => 'required',
                 'rt_counter' => 'required',
                 'current_location' => 'required',
             ]);
 
             if ($validator->fails()) {
                 return response()->json([
                     'success' => false,
                     'message' => 'Validation error',
                     'errors' => $validator->errors()
                 ], 422);
             }
 
             // Update the product location in the database
             DB::table($this->productTable)
                 ->where('ProductID', $request->product_id)
                 ->update([
                     'ProductModuleLoc' => 'Stockroom',
                     'lastDateUpdate' => now()->format('Y-m-d H:i:s')
                 ]);
 
             // Optional: Log the location change
             /*DB::table('product_location_logs')->insert([
                 'product_id' => $request->product_id,
                 'rt_counter' => $request->rt_counter,
                 'from_location' => $request->current_location,
                 'to_location' => 'Stockroom',
                 'moved_by' => auth()->id() ?? 0,
                 'moved_at' => now()->format('Y-m-d H:i:s')
             ]);*/
 
             return response()->json([
                 'success' => true,
                 'message' => 'Product successfully moved to Stockroom'
             ]);
         } catch (\Exception $e) {
             // Log the error
             \Log::error('Error moving product to Stockroom: ' . $e->getMessage());
             
             return response()->json([
                 'success' => false,
                 'message' => 'Failed to move product to Stockroom',
                 'error' => $e->getMessage()
             ], 500);
         }
     }

     
     public function moveToLabeling(Request $request)
     {
         try {
             // Validate the incoming request
             $validator = Validator::make($request->all(), [
                 'product_id' => 'required',
                 'rt_counter' => 'required',
                 'current_location' => 'required',
             ]);
 
             if ($validator->fails()) {
                 return response()->json([
                     'success' => false,
                     'message' => 'Validation error',
                     'errors' => $validator->errors()
                 ], 422);
             }
 
             // Update the product location in the database
             DB::table($this->productTable)
                 ->where('ProductID', $request->product_id)
                 ->update([
                     'ProductModuleLoc' => 'Labeling',
                     'lastDateUpdate' => now()->format('Y-m-d H:i:s')
                 ]);
 
             // Optional: Log the location change
             /*DB::table('product_location_logs')->insert([
                 'product_id' => $request->product_id,
                 'rt_counter' => $request->rt_counter,
                 'from_location' => $request->current_location,
                 'to_location' => 'Stockroom',
                 'moved_by' => auth()->id() ?? 0,
                 'moved_at' => now()->format('Y-m-d H:i:s')
             ]);*/
 
             return response()->json([
                 'success' => true,
                 'message' => 'Product successfully moved to Stockroom'
             ]);
         } catch (\Exception $e) {
             // Log the error
             \Log::error('Error moving product to Stockroom: ' . $e->getMessage());
             
             return response()->json([
                 'success' => false,
                 'message' => 'Failed to move product to Stockroom',
                 'error' => $e->getMessage()
             ], 500);
         }
     }
     
     public function validate(Request $request)
    {
        try {
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'product_id' => 'required',
                'rt_counter' => 'required',
                'status' => 'required|in:validated,invalid',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get current timestamp
            $now = now()->format('Y-m-d H:i:s');
            
            // Get the user ID (or default to 0 if not authenticated)
            $userId = auth()->id() ?? 0;

            // Prepare update data
            $updateData = [
                'validation_status' => $request->status,
                //'validation_date' => $now,
                //'validated_by' => $userId,
                //'validation_notes' => $request->notes,
                'lastDateUpdate' => $now
            ];

            // If item is validated, we may want to set additional fields
            if ($request->status === 'validated') {
                // You might want to update additional fields for validated items
                // Example: $updateData['is_ready_for_labeling'] = 1;
            }

            // Update the product in the database
            DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update($updateData);

            // Log the validation action
            Log::info('Product validation status updated', [
                'product_id' => $request->product_id,
                'rt_counter' => $request->rt_counter,
                'status' => $request->status,
                'validated_by' => $userId,
                'notes' => $request->notes
            ]);

            // Optional: Add to validation history log table if you have one
            /*
            DB::table('validation_history')->insert([
                'product_id' => $request->product_id,
                'rt_counter' => $request->rt_counter,
                'status' => $request->status,
                'notes' => $request->notes,
                'validated_by' => $userId,
                'validated_at' => $now
            ]);
            */

            return response()->json([
                'success' => true,
                'message' => 'Product ' . ($request->status === 'validated' ? 'validated' : 'marked as invalid') . ' successfully'
            ]);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error updating validation status: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update validation status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}