<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProductionAreaController extends BasetablesController
{
    public function index(Request $request)
    {
        try {
            Log::info('Tables being used:', [
                'productTable' => $this->productTable,
                'capturedImagesTable' => $this->capturedImagesTable,
                'fnskuTable' => $this->fnskuTable,
                'asinTable' => $this->asinTable,
                'company' => $this->company,
            ]);

            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Production Area');
            $includeImages = $request->boolean('include_images', false);

            // ✅ Get base products first (no joins = no duplicates)
            $baseProductsQuery = DB::table($this->productTable.' as prod')
                ->where('prod.ProductModuleLoc', $location);

            // Apply search on product fields only
            if (!empty($search)) {
                $baseProductsQuery->where(function ($q) use ($search) {
                    $q->where('prod.serialnumber', 'like', "%{$search}%")
                        ->orWhere('prod.ProductTitle', 'like', "%{$search}%")
                        ->orWhere('prod.rtid', 'like', "%{$search}%")
                        ->orWhere('prod.itemnumber', 'like', "%{$search}%")
                        ->orWhere('prod.trackingnumber', 'like', '%'.substr($search, -12).'%')
                        ->orWhere('prod.PCN', 'like', "%{$search}%")
                        ->orWhere('prod.RPN', 'like', "%{$search}%")
                        ->orWhere('prod.PRD', 'like', "%{$search}%")
                        ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                        ->orWhere('prod.rtcounter', 'like', "%{$search}%");
                });
            }

            // Get paginated products
            $products = $baseProductsQuery->paginate($perPage);
            
            Log::info('Base products fetched', [
                'count' => $products->count(),
                'total' => $products->total()
            ]);

            // ✅ Now enrich each product with FNSKU/ASIN data
            $products->getCollection()->transform(function ($product) {
                // Extract base FNSKU for lookup
                $baseFnsku = $product->FNSKUviewer;
                if (preg_match('/^C\d+(.+)$/', $baseFnsku, $matches)) {
                    $baseFnsku = $matches[1];
                }

                // Fetch FNSKU data for this product
                $fnskuData = DB::table($this->fnskuTable.' as fnsku')
                    ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                    ->select([
                        'fnsku.ASIN',
                        'fnsku.MSKU',
                        'fnsku.grading',
                        'fnsku.storename',
                        'asin.internal',
                        'asin.system_title',
                        'asin.metakeyword',
                    ])
                    ->where('fnsku.FNSKU', $baseFnsku)
                    ->first();

                // Merge FNSKU/ASIN data into product
                if ($fnskuData) {
                    $product->ASIN = $fnskuData->ASIN;
                    $product->MSKU = $fnskuData->MSKU;
                    $product->grading = $fnskuData->grading;
                    $product->storename = $fnskuData->storename;
                    $product->internal = $fnskuData->internal;
                    $product->system_title = $fnskuData->system_title;
                    $product->metakeyword = $fnskuData->metakeyword;
                    
                    // Calculate AStitle
                    $product->AStitle = !empty(trim($fnskuData->system_title)) 
                        ? trim($fnskuData->system_title)
                        : (!empty(trim($fnskuData->internal)) 
                            ? trim($fnskuData->internal)
                            : trim($product->ProductTitle ?? ''));
                } else {
                    // No FNSKU data found
                    $product->ASIN = null;
                    $product->MSKU = null;
                    $product->grading = null;
                    $product->storename = null;
                    $product->internal = null;
                    $product->system_title = null;
                    $product->metakeyword = null;
                    $product->AStitle = $product->ProductTitle ?? '';
                }

                // Add MSKUviewer (derived from MSKU)
                $product->MSKUviewer = $product->MSKU;

                // Keep original FNSKU as displayed
                $product->FNSKU = $product->FNSKUviewer;
                $product->company = $this->company;

                return $product;
            });

            // Handle images
            if ($includeImages) {
                try {
                    $productIds = $products->pluck('ProductID')->toArray();
                    Log::info('Product IDs for image fetch', ['count' => count($productIds), 'ids' => $productIds]);

                    $capturedImagesTableName = $this->capturedImagesTable;

                    if (!Schema::hasTable($capturedImagesTableName)) {
                        Log::warning('Captured images table does not exist', [
                            'table' => $capturedImagesTableName,
                        ]);

                        $products->getCollection()->transform(function ($product) {
                            $product->capturedImages = (object) [];
                            return $product;
                        });
                    } else {
                        Log::info('Captured images table exists', ['table' => $capturedImagesTableName]);

                        $capturedImages = DB::table($capturedImagesTableName)
                            ->whereIn('ProductID', $productIds)
                            ->get();

                        Log::info('Captured images fetched', [
                            'count' => $capturedImages->count(),
                        ]);

                        $imagesByProductId = [];
                        foreach ($capturedImages as $img) {
                            $imagesByProductId[$img->ProductID] = $img;
                        }

                        $products->getCollection()->transform(function ($product) use ($imagesByProductId) {
                            if (isset($imagesByProductId[$product->ProductID])) {
                                $product->capturedImages = $imagesByProductId[$product->ProductID];

                                if (empty($product->img1) && !empty($product->capturedImages->capturedimg1)) {
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

                    $products->getCollection()->transform(function ($product) {
                        $product->capturedImages = (object) [];
                        return $product;
                    });
                }
            } else {
                $products->getCollection()->transform(function ($product) {
                    $product->capturedImages = (object) [];
                    return $product;
                });
            }

            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error in ProductionAreaController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching products',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}