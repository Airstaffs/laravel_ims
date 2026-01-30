<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PackagingController extends BasetablesController
{
    public function index(Request $request)
    {
        try {
            // Define table names directly
            $productTable = 'tblproduct'; // Your actual product table name
            $fnskuTable = 'tblfnsku'; // Your FNSKU table name
            $asinTable = 'tblasin'; // Your ASIN table name
            $company = 'Airstaffs'; // Your company name

            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Packaging');
            $includeImages = $request->boolean('include_images', false);

            Log::info('Packaging API called', [
                'search' => $search,
                'location' => $location,
                'perPage' => $perPage,
                'includeImages' => $includeImages,
                'productTable' => $productTable,
                'company' => $company,
            ]);

            // Build base select array
            $selectColumns = [
                'prod.*',
                'fnsku.ASIN',
                'fnsku.MSKU',
                'fnsku.FNSKU',
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
            ];

            // Add image columns if requested
            if ($includeImages) {
                $selectColumns = array_merge($selectColumns, [
                    'img.capturedimg1',
                    'img.capturedimg2',
                    'img.capturedimg3',
                    'img.capturedimg4',
                    'img.capturedimg5',
                    'img.capturedimg6',
                    'img.capturedimg7',
                    'img.capturedimg8',
                    'img.capturedimg9',
                    'img.capturedimg10',
                    'img.capturedimg11',
                    'img.capturedimg12',
                    'img.serialimg1',
                    'img.serialimg2',
                ]);
            }

            // Build query with MSKU join instead of FNSKU join
            $productsQuery = DB::table($this->productTable.' as prod')
                ->leftJoin($this->fnskuTable.' as fnsku', 'prod.MSKUviewer', '=', 'fnsku.MSKU')
                ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN');

            // Only join images table if images are requested
            if ($includeImages) {
                $productsQuery->leftJoin($this->capturedImagesTable.' as img', 'prod.ProductID', '=', 'img.ProductID');
            }

            $productsQuery->select($selectColumns)
                ->where('prod.ProductModuleLoc', $location)
                ->distinct();

            // Apply comprehensive search including ASIN and metakeyword
            if (! empty($search)) {
                $productsQuery->where(function ($q) use ($search) {
                    $q->where('prod.serialnumber', 'like', "%{$search}%")
                        ->orWhere('prod.ProductTitle', 'like', "%{$search}%")
                        ->orWhere('prod.PCN', 'like', "%{$search}%")
                        ->orWhere('prod.RPN', 'like', "%{$search}%")
                        ->orWhere('prod.PRD', 'like', "%{$search}%")
                        ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                        ->orWhere('prod.MSKUviewer', 'like', "%{$search}%")
                        ->orWhere('prod.trackingnumber', 'like', "%{$search}%")
                        ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                        ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                        ->orWhere('fnsku.MSKU', 'like', "%{$search}%")
                        ->orWhere('fnsku.FNSKU', 'like', "%{$search}%")
                        ->orWhere('asin.internal', 'like', "%{$search}%")
                        ->orWhere('asin.system_title', 'like', "%{$search}%")
                        ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                });
            }

            $products = $productsQuery->paginate($perPage);
            Log::info('Products fetched successfully with joins', ['count' => $products->count()]);

            // Transform products to organize data properly
            $products->getCollection()->transform(function ($product) use ($includeImages) {
                // Keep the original FNSKU as displayed (from the join or FNSKUviewer)
                if (empty($product->FNSKU) && ! empty($product->FNSKUviewer)) {
                    $product->FNSKU = $product->FNSKUviewer;
                }

                // Keep MSKUviewer from product table
                if (empty($product->MSKU) && ! empty($product->MSKUviewer)) {
                    $product->MSKU = $product->MSKUviewer;
                }

                // Ensure we have the company for proper path construction
                $product->company = $this->company;

                // Organize captured images into an object if images were requested
                if ($includeImages) {
                    $capturedImages = (object) [];

                    for ($i = 1; $i <= 12; $i++) {
                        $imgKey = "capturedimg{$i}";
                        if (! empty($product->$imgKey)) {
                            $capturedImages->$imgKey = $product->$imgKey;
                        }
                        // Remove from main product object to keep it clean
                        unset($product->$imgKey);
                    }

                    // Handle serial images
                    if (! empty($product->serialimg1)) {
                        $capturedImages->serialimg1 = $product->serialimg1;
                    }

                    if (! empty($product->serialimg2)) {
                        $capturedImages->serialimg2 = $product->serialimg2;
                    }

                    unset($product->serialimg1);
                    unset($product->serialimg2);

                    $product->capturedImages = $capturedImages;

                    // Set img1 directly for the main thumbnail display if capturedimg1 exists
                    if (! empty($capturedImages->capturedimg1)) {
                        $product->img1 = $capturedImages->capturedimg1;
                    }
                } else {
                    // Initialize empty capturedImages if not requested
                    $product->capturedImages = (object) [];
                }

                return $product;
            });

            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Packaging API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch products',
                'message' => $e->getMessage(),
                'data' => [],
                'total' => 0,
                'per_page' => 10,
                'current_page' => 1,
                'last_page' => 1,
            ], 500);
        }
    }
}
