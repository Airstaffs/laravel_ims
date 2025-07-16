<?php

namespace App\Http\Controllers;

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

class LabelingController extends BasetablesController
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
                'company' => $this->company
            ]);

            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Labeling');
            $includeImages = $request->boolean('include_images', false);

            // Enhanced query with joins similar to StockroomController
            $query = DB::table($this->productTable . ' as prod')
                ->select([
                    'prod.*',
                    'fnsku.FNSKU',
                    'fnsku.MSKU',
                    'fnsku.ASIN',
                    'fnsku.grading',
                    'fnsku.storename',
                    'asin.internal as AStitle'
                ])
                ->leftJoin($this->fnskuTable . ' as fnsku', 'prod.FNSKUviewer', '=', 'fnsku.FNSKU')
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->where('prod.ProductModuleLoc', $location)
                ->when($search, function ($query) use ($search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('prod.serialnumber', 'like', "%{$search}%")
                            ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                            ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                            ->orWhere('fnsku.MSKU', 'like', "%{$search}%")
                            ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                            ->orWhere('asin.internal', 'like', "%{$search}%");
                    });
                });

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
            } else {
                // Even if images are not requested, still add company info
                $products->getCollection()->transform(function ($product) {
                    $product->company = $this->company;
                    return $product;
                });
            }

            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error in LabelingController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching products',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function moveToValidation(Request $request)
    {
        // Log that the method was called
        Log::info('=== MOVE TO VALIDATION CALLED ===');
        Log::info('Request method: ' . $request->method());
        Log::info('Request URL: ' . $request->fullUrl());
        Log::info('Request headers: ', $request->headers->all());
        Log::info('Request body: ', $request->all());
        Log::info('Product table: ' . $this->productTable);

        try {
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'product_id' => 'required',
                'rt_counter' => 'required',
                'current_location' => 'required',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed in moveToValidation', [
                    'errors' => $validator->errors(),
                    'request_data' => $request->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            Log::info('Validation passed, attempting to update product', [
                'product_id' => $request->product_id,
                'rt_counter' => $request->rt_counter,
                'current_location' => $request->current_location
            ]);

            // Check if product exists first with joined data to get FNSKU, MSKU, and ASIN
            $existingProduct = DB::table($this->productTable . ' as prod')
                ->select([
                    'prod.*',
                    'fnsku.FNSKU',
                    'fnsku.MSKU',
                    'fnsku.ASIN',
                    'fnsku.grading',
                    'fnsku.storename'
                ])
                ->leftJoin($this->fnskuTable . ' as fnsku', 'prod.FNSKUviewer', '=', 'fnsku.FNSKU')
                ->where('prod.ProductID', $request->product_id)
                ->first();

            if (!$existingProduct) {
                Log::error('Product not found for moveToValidation', [
                    'product_id' => $request->product_id,
                    'table' => $this->productTable
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            Log::info('Product found, current location: ' . $existingProduct->ProductModuleLoc);

            // Check for required fields (ASIN, FNSKU, MSKU)
            $missingFields = [];

            // Check FNSKU from product table
            if (empty($existingProduct->FNSKUviewer)) {
                $missingFields[] = 'FNSKU';
            }

            // Check MSKU from joined fnsku table
            if (empty($existingProduct->MSKU)) {
                $missingFields[] = 'MSKU';
            }

            // Check ASIN from joined fnsku table
            if (empty($existingProduct->ASIN)) {
                $missingFields[] = 'ASIN';
            }

            // If any required fields are missing, return error
            if (!empty($missingFields)) {
                $missingFieldsText = implode(', ', $missingFields);
                Log::warning('Cannot move to Validation - missing required fields', [
                    'product_id' => $request->product_id,
                    'rt_counter' => $request->rt_counter,
                    'missing_fields' => $missingFields,
                    'existing_product' => [
                        'FNSKUviewer' => $existingProduct->FNSKUviewer,
                        'MSKU' => $existingProduct->MSKU,
                        'ASIN' => $existingProduct->ASIN
                    ]
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Cannot move to Validation. Missing required fields: {$missingFieldsText}. Please set the FNSKU first.",
                    'missing_fields' => $missingFields,
                    'requires_fnsku_setup' => true
                ], 422);
            }

            // All required fields are present, proceed with the move
            Log::info('All required fields present, proceeding with move to Validation', [
                'FNSKU' => $existingProduct->FNSKUviewer,
                'MSKU' => $existingProduct->MSKU,
                'ASIN' => $existingProduct->ASIN
            ]);

            // Update the product location in the database
            $updateResult = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Validation',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s')
                ]);

            Log::info('Update result: ' . $updateResult . ' rows affected');

            // Verify the update worked
            $updatedProduct = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->first();

            Log::info('Product after update:', [
                'ProductID' => $updatedProduct->ProductID,
                'ProductModuleLoc' => $updatedProduct->ProductModuleLoc,
                'lastDateUpdate' => $updatedProduct->lastDateUpdate
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product successfully moved to Validation',
                'debug_info' => [
                    'rows_affected' => $updateResult,
                    'new_location' => $updatedProduct->ProductModuleLoc
                ]
            ]);
        } catch (\Exception $e) {
            // Log the error with full details
            Log::error('Exception in moveToValidation', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to move product to Validation',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }
    public function moveToStockroom(Request $request)
    {
        // Log that the method was called
        Log::info('=== MOVE TO STOCKROOM CALLED ===');
        Log::info('Request method: ' . $request->method());
        Log::info('Request URL: ' . $request->fullUrl());
        Log::info('Request headers: ', $request->headers->all());
        Log::info('Request body: ', $request->all());
        Log::info('Product table: ' . $this->productTable);

        try {
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'product_id' => 'required',
                'rt_counter' => 'required',
                'current_location' => 'required',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed in moveToStockroom', [
                    'errors' => $validator->errors(),
                    'request_data' => $request->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            Log::info('Validation passed, attempting to update product', [
                'product_id' => $request->product_id,
                'rt_counter' => $request->rt_counter,
                'current_location' => $request->current_location
            ]);

            // Check if product exists first with joined data to get FNSKU, MSKU, and ASIN
            $existingProduct = DB::table($this->productTable . ' as prod')
                ->select([
                    'prod.*',
                    'fnsku.FNSKU',
                    'fnsku.MSKU',
                    'fnsku.ASIN',
                    'fnsku.grading',
                    'fnsku.storename'
                ])
                ->leftJoin($this->fnskuTable . ' as fnsku', 'prod.FNSKUviewer', '=', 'fnsku.FNSKU')
                ->where('prod.ProductID', $request->product_id)
                ->first();

            if (!$existingProduct) {
                Log::error('Product not found for moveToStockroom', [
                    'product_id' => $request->product_id,
                    'table' => $this->productTable
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            Log::info('Product found, current location: ' . $existingProduct->ProductModuleLoc);

            // Check for required fields (ASIN, FNSKU, MSKU)
            $missingFields = [];

            // Check FNSKU from product table
            if (empty($existingProduct->FNSKUviewer)) {
                $missingFields[] = 'FNSKU';
            }

            // Check MSKU from joined fnsku table
            if (empty($existingProduct->MSKU)) {
                $missingFields[] = 'MSKU';
            }

            // Check ASIN from joined fnsku table
            if (empty($existingProduct->ASIN)) {
                $missingFields[] = 'ASIN';
            }

            // If any required fields are missing, return error
            if (!empty($missingFields)) {
                $missingFieldsText = implode(', ', $missingFields);
                Log::warning('Cannot move to Stockroom - missing required fields', [
                    'product_id' => $request->product_id,
                    'rt_counter' => $request->rt_counter,
                    'missing_fields' => $missingFields,
                    'existing_product' => [
                        'FNSKUviewer' => $existingProduct->FNSKUviewer,
                        'MSKU' => $existingProduct->MSKU,
                        'ASIN' => $existingProduct->ASIN
                    ]
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Cannot move to Stockroom. Missing required fields: {$missingFieldsText}. Please set the FNSKU first.",
                    'missing_fields' => $missingFields,
                    'requires_fnsku_setup' => true
                ], 422);
            }

            // All required fields are present, proceed with the move
            Log::info('All required fields present, proceeding with move to Stockroom', [
                'FNSKU' => $existingProduct->FNSKUviewer,
                'MSKU' => $existingProduct->MSKU,
                'ASIN' => $existingProduct->ASIN
            ]);

            // Update the product location in the database
            $updateResult = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Stockroom',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s')
                ]);

            Log::info('Update result: ' . $updateResult . ' rows affected');

            // Verify the update worked
            $updatedProduct = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->first();

            Log::info('Product after update:', [
                'ProductID' => $updatedProduct->ProductID,
                'ProductModuleLoc' => $updatedProduct->ProductModuleLoc,
                'lastDateUpdate' => $updatedProduct->lastDateUpdate
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product successfully moved to Stockroom',
                'debug_info' => [
                    'rows_affected' => $updateResult,
                    'new_location' => $updatedProduct->ProductModuleLoc
                ]
            ]);
        } catch (\Exception $e) {
            // Log the error with full details
            Log::error('Exception in moveToStockroom', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to move product to Stockroom',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ProductID' => 'required|integer',
            'ProductTitle' => 'nullable|string|max:255',
            'itemnumber' => 'nullable|string|max:255',
            'basketnumber' => 'nullable|string|max:255',
            'RPN' => 'nullable|string|max:255',
            'PRD' => 'nullable|string|max:255',
            'PCN' => 'nullable|string|max:255',
            'priorityrank' => 'nullable|string|max:255',
            'quantity' => 'nullable|numeric',
            'orderdate' => 'nullable|date',
            'paymentdate' => 'nullable|date',
            'shipdate' => 'nullable|date',
            'datedelivered' => 'nullable|date',
            'description' => 'nullable|string',
            'supplierNotes' => 'nullable|string',
            'employeeNotes' => 'nullable|string',
            'stickerNotes' => 'nullable|string',
            'serialnumber' => 'nullable|string|max:255',
            'serialnumberb' => 'nullable|string|max:255',
            'serialnumberc' => 'nullable|string|max:255',
            'serialnumberd' => 'nullable|string|max:255',
            'trackingnumber' => 'nullable|string|max:255',
            'trackingnumber2' => 'nullable|string|max:255',
            'trackingnumber3' => 'nullable|string|max:255',
            'trackingnumber4' => 'nullable|string|max:255',
            'trackingnumber5' => 'nullable|string|max:255',
        ]);

        // You can adjust based on your model binding
        $product = DB::table($this->productTable)
            ->updateOrInsert(
                ['ProductID' => $validated['ProductID']],
                $validated
            );

        return response()->json([
            'success' => true,
            'message' => 'Labeling product saved successfully',
            'product' => $product
        ]);
    }
}
