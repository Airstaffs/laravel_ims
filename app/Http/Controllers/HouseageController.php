<?php

namespace App\Http\Controllers;

use App\Models\tblproduct;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Rpn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use DateTime;
use DateTimeZone;

class HouseageController extends BasetablesController
{
    /**
     * Extract base FNSKU from prefixed FNSKU (same as StockroomController)
     */
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
            // Log tables being used for debugging
            Log::info('Tables being used:', [
                'productTable' => $this->productTable,
                'capturedImagesTable' => $this->capturedImagesTable,
                'fnskuTable' => $this->fnskuTable,
                'asinTable' => $this->asinTable,
                'company' => $this->company
            ]);

            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            // $location = 'Orders'; // Commented out as it was commented in original
            $includeImages = $request->boolean('include_images', false);

            // UPDATED: Get products first, then match FNSKUs in PHP (like StockroomController)
            $productsQuery = DB::table($this->productTable . ' as prod')
                ->select([
                    'prod.*'
                ]);
            // Removed the location filter as it was commented out in original

            // Apply search to products directly first
            if (!empty($search)) {
                $productsQuery->where(function ($q) use ($search) {
                    $q->where('prod.serialnumber', 'like', "%{$search}%")
                        ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                        ->orWhere('prod.rtcounter', 'like', "%{$search}%");
                });
            }

            $products = $productsQuery->paginate($perPage);
            Log::info('Products fetched successfully', ['count' => $products->count()]);

            // Extract all unique base FNSKUs from products
            $baseFnskus = [];
            $fnskuProductMap = [];

            foreach ($products->items() as $product) {
                if (!empty($product->FNSKUviewer)) {
                    $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                    $baseFnskus[] = $baseFnsku;

                    if (!isset($fnskuProductMap[$baseFnsku])) {
                        $fnskuProductMap[$baseFnsku] = [];
                    }
                    $fnskuProductMap[$baseFnsku][] = $product;
                }
            }

            $baseFnskus = array_unique($baseFnskus);

            // Get FNSKU data for base FNSKUs
            $fnskuData = [];
            if (!empty($baseFnskus)) {
                $fnskuRecords = DB::table($this->fnskuTable)
                    ->select('ASIN', 'FNSKU', 'MSKU', 'grading', 'storename')
                    ->whereIn('FNSKU', $baseFnskus)
                    ->get();

                foreach ($fnskuRecords as $record) {
                    $fnskuData[$record->FNSKU] = $record;
                }
            }

            // Get ASIN data
            $asinList = [];
            foreach ($fnskuData as $fnskuRecord) {
                $asinList[] = $fnskuRecord->ASIN;
            }
            $asinList = array_unique($asinList);

            $asinData = [];
            if (!empty($asinList)) {
                $asinRecords = DB::table($this->asinTable)
                    ->select('ASIN', 'internal')
                    ->whereIn('ASIN', $asinList)
                    ->get();

                foreach ($asinRecords as $record) {
                    $asinData[$record->ASIN] = $record;
                }
            }

            // Apply additional search filters if needed
            if (!empty($search)) {
                $products->getCollection()->transform(function ($product) use ($fnskuData, $asinData, $search) {
                    // Add FNSKU and ASIN data
                    $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);

                    if (isset($fnskuData[$baseFnsku])) {
                        $fnskuRecord = $fnskuData[$baseFnsku];
                        // Keep the original FNSKU as displayed (with prefix if it exists)
                        $product->FNSKU = $product->FNSKUviewer; // Show the actual FNSKU with prefix
                        $product->MSKU = $fnskuRecord->MSKU;
                        $product->ASIN = $fnskuRecord->ASIN;
                        $product->grading = $fnskuRecord->grading;
                        $product->storename = $fnskuRecord->storename;

                        if (isset($asinData[$fnskuRecord->ASIN])) {
                            $product->AStitle = $asinData[$fnskuRecord->ASIN]->internal;
                        }
                    } else {
                        // If no FNSKU record found, still show the original FNSKU
                        $product->FNSKU = $product->FNSKUviewer;
                    }

                    return $product;
                });

                // Filter products that match additional search criteria
                $filteredProducts = $products->getCollection()->filter(function ($product) use ($search) {
                    return stripos($product->MSKU ?? '', $search) !== false ||
                        stripos($product->ASIN ?? '', $search) !== false ||
                        stripos($product->AStitle ?? '', $search) !== false ||
                        stripos($product->serialnumber ?? '', $search) !== false ||
                        stripos($product->FNSKUviewer ?? '', $search) !== false ||
                        stripos($product->rtcounter ?? '', $search) !== false;
                });

                $products->setCollection($filteredProducts);
            } else {
                // Add FNSKU and ASIN data to all products
                $products->getCollection()->transform(function ($product) use ($fnskuData, $asinData) {
                    $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);

                    if (isset($fnskuData[$baseFnsku])) {
                        $fnskuRecord = $fnskuData[$baseFnsku];
                        // Keep the original FNSKU as displayed (with prefix if it exists)
                        $product->FNSKU = $product->FNSKUviewer; // Show the actual FNSKU with prefix
                        $product->MSKU = $fnskuRecord->MSKU;
                        $product->ASIN = $fnskuRecord->ASIN;
                        $product->grading = $fnskuRecord->grading;
                        $product->storename = $fnskuRecord->storename;

                        if (isset($asinData[$fnskuRecord->ASIN])) {
                            $product->AStitle = $asinData[$fnskuRecord->ASIN]->internal;
                        }
                    } else {
                        // If no FNSKU record found, still show the original FNSKU
                        $product->FNSKU = $product->FNSKUviewer;
                    }

                    return $product;
                });
            }

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

                        // Add capturedImages data and company to each product
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
                                $product->capturedImages = (object) [];
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
                        $product->capturedImages = (object) []; // Initialize empty object to prevent JS errors
                        return $product;
                    });
                }
            } else {
                // Even if images are not requested, still add company info
                $products->getCollection()->transform(function ($product) {
                    $product->company = $this->company;
                    return $product;
                });
            }

            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error in HouseageController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching products',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ProductID' => 'required|integer',
            'itemnumber' => 'required|string|max:255',
            'ProductTitle' => 'nullable|string|max:255',
            'rtid' => 'nullable|string|max:255',
            'orderdate' => 'nullable|date',
            'paymentdate' => 'nullable|date',
            'shipdate' => 'nullable|date',
            'datedelivered' => 'nullable|date',
            'seller' => 'nullable|string|max:255',
            'materialtype' => 'nullable|string|max:255',
            'sourceType' => 'nullable|string|max:255',
            'carrier' => 'nullable|string|max:255',
            'listedcondition' => 'nullable|string|max:255',
            'paymentmethod' => 'nullable|string|max:255',
            'quantity' => 'nullable|numeric',
            'Discount' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'priceshipping' => 'nullable|numeric',
            'refund' => 'nullable|numeric',
            'description' => 'nullable|string',
            'supplierNotes' => 'nullable|string',
            'employeeNotes' => 'nullable|string',
            'serialnumber' => 'nullable|string|max:255',
            'serialnumberb' => 'nullable|string|max:255',
            'serialnumberc' => 'nullable|string|max:255',
            'serialnumberd' => 'nullable|string|max:255',
            'trackingnumber' => 'nullable|string|max:255',
            'trackingnumber2' => 'nullable|string|max:255',
            'trackingnumber3' => 'nullable|string|max:255',
            'trackingnumber4' => 'nullable|string|max:255',
            'trackingnumber5' => 'nullable|string|max:255',
            'validation' => 'nullable|string|max:255',
            'price' => 'nullable|numeric',
            'RPN' => 'nullable|string',
            'PRD' => 'nullable|string',
            'PCN' => 'nullable|string',
            'basketnumber' => 'nullable|string',
        ]);

        // Ensure default for validation
        $validated['validation'] = $validated['validation'] ?? 'unvalidated';

        // You may log or inspect this if needed
        // dd($validated);

        $product = tblproduct::updateOrCreate(
            ['itemnumber' => $validated['itemnumber']],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Houseage product saved successfully',
            'product' => $product
        ]);
    }
}
