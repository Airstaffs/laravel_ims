<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class notfoundController extends BasetablesController
{
    public function index(Request $request)
    {
        try {
            // Log tables being used for debugging
            Log::info('Tables being used:', [
                'productTable' => $this->productTable,
                'capturedImagesTable' => $this->capturedImagesTable,
                'fnskuTable' => $this->fnskuTable,
                'asinTable' => $this->asinTable,
                'company' => $this->company,
            ]);

            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Not Found');
            $includeImages = $request->boolean('include_images', false);

            // Build query with proper joins to include ASIN and metakeyword in search
            $productsQuery = DB::table($this->productTable.' as prod')
                ->leftJoin($this->fnskuTable.' as fnsku', function ($join) {
                    $join->on(DB::raw("CASE 
                    WHEN prod.FNSKUviewer REGEXP '^C[0-9]+' 
                    THEN SUBSTRING(prod.FNSKUviewer, LOCATE(REGEXP_REPLACE(prod.FNSKUviewer, '^C[0-9]+', ''), prod.FNSKUviewer))
                    ELSE prod.FNSKUviewer 
                END"), '=', 'fnsku.FNSKU');
                })
                ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
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
                ->where('prod.ProductModuleLoc', $location);

            // Apply comprehensive search including ASIN and metakeyword
            if (! empty($search)) {
                $productsQuery->where(function ($q) use ($search) {
                    $q->where('prod.serialnumber', 'like', "%{$search}%")
                        ->orWhere('prod.ProductTitle', 'like', "%{$search}%")
                        ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                        ->orWhere('prod.MSKUviewer', 'like', "%{$search}%")
                        ->orWhere('prod.ASINviewer', 'like', "%{$search}%")
                        ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                        ->orWhere('prod.PCN', 'like', "%{$search}%")
                        ->orWhere('prod.RPN', 'like', "%{$search}%")
                        ->orWhere('prod.PRD', 'like', "%{$search}%")
                        ->orWhere('prod.trackingnumber', 'like', "%{$search}%")
                        // Add FNSKU table search
                        ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                        ->orWhere('fnsku.MSKU', 'like', "%{$search}%")
                        // Add ASIN table search
                        ->orWhere('asin.internal', 'like', "%{$search}%")
                        ->orWhere('asin.system_title', 'like', "%{$search}%")
                        ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                });
            }

            $products = $productsQuery->paginate($perPage);
            Log::info('Products fetched successfully with joins', ['count' => $products->count()]);

            // Transform products to ensure proper FNSKU display and company data
            $products->getCollection()->transform(function ($product) {
                // Keep the original FNSKU as displayed (with prefix if it exists)
                $product->FNSKU = $product->FNSKUviewer;

                // Ensure we have the company for proper path construction
                $product->company = $this->company;

                return $product;
            });

            // If images are requested, fetch them for each product
            if ($includeImages) {
                try {
                    $productIds = $products->pluck('ProductID')->toArray();
                    Log::info('Product IDs for image fetch', ['count' => count($productIds), 'ids' => $productIds]);

                    $capturedImagesTableName = $this->capturedImagesTable;

                    Log::info('Checking table existence', [
                        'table' => $capturedImagesTableName,
                    ]);

                    if (! Schema::hasTable($capturedImagesTableName)) {
                        Log::warning('Captured images table does not exist', [
                            'table' => $capturedImagesTableName,
                        ]);

                        // Add empty capturedImages object to prevent JS errors
                        $products->getCollection()->transform(function ($product) {
                            $product->capturedImages = (object) [];

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
                            'sample' => $capturedImages->take(1),
                        ]);

                        // Create a lookup by ProductID for efficient access
                        $imagesByProductId = [];
                        foreach ($capturedImages as $img) {
                            $imagesByProductId[$img->ProductID] = $img;
                        }

                        // Add capturedImages data to each product
                        $products->getCollection()->transform(function ($product) use ($imagesByProductId) {
                            // Check if we have image data for this product
                            if (isset($imagesByProductId[$product->ProductID])) {
                                $product->capturedImages = $imagesByProductId[$product->ProductID];

                                // Set img1 directly for the main thumbnail display if not already set
                                if (empty($product->img1) && ! empty($product->capturedImages->capturedimg1)) {
                                    $product->img1 = $product->capturedImages->capturedimg1;
                                }

                                Log::info('Added captured images to product', [
                                    'ProductID' => $product->ProductID,
                                    'capturedImages' => json_encode($product->capturedImages),
                                ]);
                            } else {
                                Log::info('No captured images found for product', [
                                    'ProductID' => $product->ProductID,
                                ]);

                                $product->capturedImages = (object) [];
                            }

                            return $product;
                        });
                    }
                } catch (\Exception $e) {
                    Log::error('Error fetching images', [
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
            Log::error('Error in NotFoundController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching products',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function moveToStockroom(Request $request)
    {
        $id = $request->input('id');

        if (! $id) {
            return response()->json(['success' => false, 'message' => 'No ID provided'], 400);
        }

        $updated = DB::table($this->productTable)
            ->where('ProductID', $id)
            ->update([
                'ProductModuleLoc' => 'Stockroom',
                'notfoundDate' => null, // set to NULL
            ]);

        if ($updated) {
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false, 'message' => 'Update failed'], 500);
        }
    }
}
