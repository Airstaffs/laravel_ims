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

            $query = DB::table($productTable)
                ->where('ProductModuleLoc', $location);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('AStitle', 'like', "%{$search}%")
                        ->orWhere('ProductTitle', 'like', "%{$search}%")
                        ->orWhere('serialnumber', 'like', "%{$search}%")
                        ->orWhere('FNSKUviewer', 'like', "%{$search}%")
                        ->orWhere('MSKUviewer', 'like', "%{$search}%")
                        ->orWhere('ASINviewer', 'like', "%{$search}%")
                        ->orWhere('rtcounter', 'like', "%{$search}%");
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
