<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator; // Add this line

class ValidationController extends BasetablesController
{
    private function extractBaseFnsku($fnsku)
    {
        if (empty($fnsku)) {
            return $fnsku;
        }

        // Check if it's a prefixed FNSKU (starts with C followed by digits)
        if (preg_match('/^C(\d+)(.+)$/', $fnsku, $matches)) {
            return $matches[2]; // Return the base FNSKU without prefix
        }

        return $fnsku; // Return as-is if not prefixed
    }

    public function index(Request $request)
    {
        try {
            // Define table names directly
            $productTable = 'tblproduct';
            $fnskuTable = 'tblfnsku';
            $asinTable = 'tblasin';
            $company = 'Airstaffs';

            Log::info('Tables being used:', [
                'productTable' => $productTable,
                'fnskuTable' => $fnskuTable,
                'asinTable' => $asinTable,
                'company' => $company,
            ]);

            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Validation');
            $includeImages = $request->boolean('include_images', false);

            // UPDATED: Build query with proper joins to include ASIN and metakeyword in search
            $productsQuery = DB::table($productTable.' as prod')
                ->leftJoin($fnskuTable.' as fnsku', function ($join) {
                    $join->on(DB::raw("CASE 
                    WHEN prod.FNSKUviewer REGEXP '^C[0-9]+' 
                    THEN SUBSTRING(prod.FNSKUviewer, LOCATE(REGEXP_REPLACE(prod.FNSKUviewer, '^C[0-9]+', ''), prod.FNSKUviewer))
                    ELSE prod.FNSKUviewer 
                END"), '=', 'fnsku.FNSKU');
                })
                ->leftJoin($asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->select([
                    'prod.*',
                    'fnsku.ASIN',
                    'fnsku.MSKU',
                    'fnsku.grading',
                    'fnsku.storename',
                    DB::raw("COALESCE(
                        NULLIF(TRIM(asin.system_title), ''), 
                        NULLIF(TRIM(asin.internal), ''), 
                        NULLIF(TRIM(prod.ProductTitle), '')
                    ) as AStitle"),
                    'asin.internal',
                    'asin.system_title',
                    'asin.metakeyword',
                ])
                ->where('prod.ProductModuleLoc', $location)
                ->when($search, function ($query) use ($search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('prod.serialnumber', 'like', "%{$search}%")
                            ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                            ->orWhere('prod.trackingnumber', 'like', "%{$search}%")
                            ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                            ->orWhere('prod.ProductTitle', 'like', "%{$search}%")
                            ->orWhere('prod.PCN', 'like', "%{$search}%")
                            ->orWhere('prod.RPN', 'like', "%{$search}%")
                            ->orWhere('prod.PRD', 'like', "%{$search}%")
                            // Add FNSKU table search
                            ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                            ->orWhere('fnsku.MSKU', 'like', "%{$search}%")
                            // Add ASIN table search
                            ->orWhere('asin.internal', 'like', "%{$search}%")
                            ->orWhere('asin.system_title', 'like', "%{$search}%")
                            ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                    });
                })
                ->orderBy('prod.lastDateUpdate', 'desc');

            $products = $productsQuery->paginate($perPage);
            Log::info('Products fetched successfully', ['count' => $products->count()]);

            // Transform products to add company field
            $products->getCollection()->transform(function ($product) use ($company) {
                $product->company = $company;
                $product->FNSKU = $product->FNSKUviewer;

                return $product;
            });

            // Step 2 (formerly Step 6): Add images if requested (using filesystem approach)
            if ($includeImages) {
                try {
                    $publicPath = public_path('images/product_images/'.$company);

                    Log::info('Checking for captured images in path', ['path' => $publicPath]);

                    // Add captured images data to each product
                    $products->getCollection()->transform(function ($product) use ($publicPath) {
                        $capturedImages = (object) [];

                        // Check for up to 12 captured images
                        for ($i = 1; $i <= 12; $i++) {
                            $filename = $product->ProductID.'_img'.$i.'.jpg';
                            $filePath = $publicPath.'/'.$filename;

                            if (file_exists($filePath)) {
                                $capturedImages->{"capturedimg{$i}"} = $filename;
                            }
                        }

                        // Check for serial images
                        $serialImg1 = $product->ProductID.'_serialimg1.jpg';
                        $serialImg2 = $product->ProductID.'_serialimg2.jpg';

                        if (file_exists($publicPath.'/'.$serialImg1)) {
                            $capturedImages->serialimg1 = $serialImg1;
                        }
                        if (file_exists($publicPath.'/'.$serialImg2)) {
                            $capturedImages->serialimg2 = $serialImg2;
                        }

                        $product->capturedImages = $capturedImages;

                        // Set img1 directly for the main thumbnail display if capturedimg1 exists
                        if (! empty($capturedImages->capturedimg1)) {
                            $product->img1 = $capturedImages->capturedimg1;
                        }

                        Log::info('Added captured images to product', [
                            'ProductID' => $product->ProductID,
                            'capturedImagesCount' => count((array) $capturedImages),
                            'hasImg1' => ! empty($product->img1),
                        ]);

                        return $product;
                    });
                } catch (\Exception $e) {
                    Log::error('Error checking for image files', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    // Continue without images but add empty capturedImages object
                    $products->getCollection()->transform(function ($product) {
                        $product->capturedImages = (object) [];

                        return $product;
                    });
                }
            } else {
                // Even if images are not requested, initialize empty capturedImages
                $products->getCollection()->transform(function ($product) {
                    $product->capturedImages = (object) [];

                    return $product;
                });
            }

            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error in ValidationController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching products',
                'message' => $e->getMessage(),
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
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Update the product location in the database
            DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Stockroom',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
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
                'message' => 'Product successfully moved to Stockroom',
            ]);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error moving product to Stockroom: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to move product to Stockroom',
                'error' => $e->getMessage(),
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
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Update the product location in the database
            DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Labeling',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
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
                'message' => 'Product successfully moved to Stockroom',
            ]);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error moving product to Stockroom: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to move product to Stockroom',
                'error' => $e->getMessage(),
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
                'notes' => 'nullable|string',
                'ProductModuleLoc' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Get current timestamp
            $now = now()->format('Y-m-d H:i:s');

            // Get the user ID (or default to 0 if not authenticated)
            $userId = auth()->id() ?? 0;

            // Prepare update data
            $updateData = [
                'validation_status' => $request->status,
                'lastDateUpdate' => $now,
            ];

            // ✅ Only apply ProductModuleLoc if status is 'invalid' and the value is present
            if ($request->status === 'invalid' && $request->filled('ProductModuleLoc')) {
                $updateData['ProductModuleLoc'] = $request->ProductModuleLoc;
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
                'notes' => $request->notes,
                'ProductModuleLoc' => $request->ProductModuleLoc ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product '.($request->status === 'validated' ? 'validated' : 'marked as invalid').' successfully',
            ]);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error updating validation status: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update validation status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
