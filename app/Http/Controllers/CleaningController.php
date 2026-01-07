<?php

namespace App\Http\Controllers;

use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleaningController extends BasetablesController
{
    use TracksHistory;

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
            $location = $request->input('location', 'Testing');
            $includeImages = $request->boolean('include_images', false);

            Log::info('Testing API called', [
                'search' => $search,
                'location' => $location,
                'perPage' => $perPage,
                'includeImages' => $includeImages,
            ]);

            // UPDATED: Build query with proper joins to include ASIN and metakeyword in search
            $query = DB::table($productTable.' as prod')
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
                ->where('prod.ProductModuleLoc', $location);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('prod.ProductTitle', 'like', "%{$search}%")
                        ->orWhere('prod.rtid', 'like', "%{$search}%")
                        ->orWhere('prod.itemnumber', 'like', "%{$search}%")
                        ->orWhere('prod.trackingnumber', 'like', "%{$search}%")
                        ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                        ->orWhere('prod.serialnumber', 'like', "%{$search}%")
                        ->orWhere('prod.PCN', 'like', "%{$search}%")
                        ->orWhere('prod.RPN', 'like', "%{$search}%")
                        ->orWhere('prod.PRD', 'like', "%{$search}%")
                        ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                        // Add FNSKU table search
                        ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                        ->orWhere('fnsku.MSKU', 'like', "%{$search}%")
                        // Add ASIN table search
                        ->orWhere('asin.internal', 'like', "%{$search}%")
                        ->orWhere('asin.system_title', 'like', "%{$search}%")
                        ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                });
            }

            $products = $query->paginate($perPage);

            Log::info('Products fetched successfully', ['count' => $products->count()]);

            // Transform products to add company field
            $products->getCollection()->transform(function ($product) use ($company) {
                $product->company = $company;

                return $product;
            });

            // If images are requested, check for captured image files
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

            return response()->json([
                'data' => $products->items(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]);

        } catch (\Exception $e) {
            Log::error('Testing API error', [
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

    private function getCurrentUserName()
    {
        $user = Auth::user();

        return $user ? ($user->username ?? $user->name ?? 'Unknown') : 'Unknown';
    }
}
