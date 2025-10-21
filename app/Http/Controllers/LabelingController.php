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

    /**
     * Helper method to insert item processing history using BasetablesController pattern
     */
    private function insertItemHistory($rtCounter, $action, $additionalData = [])
    {
        try {
            // Get current user
            $username = Auth::id() ?? (Auth::user()->name ?? 'system');

            // Use the itemProcessHistoryTable from BasetablesController pattern
            $historyTable = $this->itemProcessHistoryTable;

            Log::info('Attempting to insert history', [
                'table' => $historyTable,
                'rtcounter' => $rtCounter,
                'action' => $action,
                'username' => $username,
                'additional_data' => $additionalData
            ]);

            if (Schema::hasTable($historyTable)) {
                $historyData = [
                    'rtcounter' => $rtCounter,
                    'employeeName' => $username,
                    'EditDate' => now()->format('Y-m-d H:i:s'),
                    'Module' => 'Labeling',
                    'Action' => $action,
                ];

                // Add any additional data if provided
                $historyData = array_merge($historyData, $additionalData);

                DB::table($historyTable)->insert($historyData);

                Log::info('Item history inserted successfully', [
                    'rtcounter' => $rtCounter,
                    'action' => $action,
                    'table' => $historyTable,
                    'data' => $historyData
                ]);

                return true;
            } else {
                Log::warning('History table does not exist, skipping history insert', [
                    'table' => $historyTable,
                    'company' => $this->company
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Failed to insert item history', [
                'rtcounter' => $rtCounter,
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
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
            $location = $request->input('location', 'Labeling');
            $includeImages = $request->boolean('include_images', false);

            // UPDATED: Build query with proper joins to include ASIN and metakeyword in search
            $productsQuery = DB::table($this->productTable . ' as prod')
                ->leftJoin($this->fnskuTable . ' as fnsku', function($join) {
                    $join->on(DB::raw("CASE 
                        WHEN prod.FNSKUviewer REGEXP '^C[0-9]+' 
                        THEN SUBSTRING(prod.FNSKUviewer, LOCATE(REGEXP_REPLACE(prod.FNSKUviewer, '^C[0-9]+', ''), prod.FNSKUviewer))
                        ELSE prod.FNSKUviewer 
                    END"), '=', 'fnsku.FNSKU');
                })
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->select([
                    'prod.*',
                    'fnsku.ASIN',
                    'fnsku.MSKU',
                    'fnsku.grading',
                    'fnsku.storename',
                    'asin.internal as AStitle',
                    'asin.metakeyword'
                ])
                ->where('prod.ProductModuleLoc', $location);

            // Apply comprehensive search including ASIN and metakeyword
            if (!empty($search)) {
                $productsQuery->where(function ($q) use ($search) {
                    $q->where('prod.serialnumber', 'like', "%{$search}%")
                        ->orWhere('prod.ProductTitle', 'like', "%{$search}%")
                        ->orWhere('prod.PCN', 'like', "%{$search}%")
                        ->orWhere('prod.RPN', 'like', "%{$search}%")
                        ->orWhere('prod.PRD', 'like', "%{$search}%")
                        ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                        ->orWhere('prod.trackingnumber', 'like', "%{$search}%")
                        ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                        // Add FNSKU table search
                        ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                        ->orWhere('fnsku.MSKU', 'like', "%{$search}%")
                        // Add ASIN table search
                        ->orWhere('asin.internal', 'like', "%{$search}%")
                        ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                });
            }

            $products = $productsQuery->paginate($perPage);
            Log::info('Products fetched successfully with joins', ['count' => $products->count()]);

            // Transform products to ensure proper FNSKU display
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
                        'table' => $capturedImagesTableName
                    ]);

                    if (!Schema::hasTable($capturedImagesTableName)) {
                        Log::warning('Captured images table does not exist', [
                            'table' => $capturedImagesTableName
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
                            'sample' => $capturedImages->take(1)
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
                                if (empty($product->img1) && !empty($product->capturedImages->capturedimg1)) {
                                    $product->img1 = $product->capturedImages->capturedimg1;
                                }

                                Log::info('Added captured images to product', [
                                    'ProductID' => $product->ProductID,
                                    'capturedImages' => json_encode($product->capturedImages)
                                ]);
                            } else {
                                Log::info('No captured images found for product', [
                                    'ProductID' => $product->ProductID
                                ]);

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

    public function getFnskuData(Request $request)
    {
        $search = $request->input('search');
        $location = $request->input('location');

        // UPDATED: Use the same approach as index method
        $productsQuery = DB::table($this->productTable . ' as prod')
            ->select(['prod.*'])
            ->where('prod.ProductModuleLoc', $location);

        if (!empty($search)) {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('prod.serialnumber', 'like', "%{$search}%")
                    ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                    ->orWhere('prod.rtcounter', 'like', "%{$search}%");
            });
        }

        $products = $productsQuery->get();

        // Extract base FNSKUs and get related data
        $baseFnskus = [];
        foreach ($products as $product) {
            if (!empty($product->FNSKUviewer)) {
                $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                $baseFnskus[] = $baseFnsku;
            }
        }
        $baseFnskus = array_unique($baseFnskus);

        // Get FNSKU data
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

        // Combine data
        $results = $products->map(function ($product) use ($fnskuData, $asinData, $search) {
            $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);

            if (isset($fnskuData[$baseFnsku])) {
                $fnskuRecord = $fnskuData[$baseFnsku];
                $product->FNSKU = $fnskuRecord->FNSKU;
                $product->MSKU = $fnskuRecord->MSKU;
                $product->ASIN = $fnskuRecord->ASIN;
                $product->grading = $fnskuRecord->grading;
                $product->storename = $fnskuRecord->storename;

                if (isset($asinData[$fnskuRecord->ASIN])) {
                    $product->AStitle = $asinData[$fnskuRecord->ASIN]->internal;
                }
            }

            return $product;
        });

        // Apply additional filtering if search term matches FNSKU/ASIN data
        if (!empty($search)) {
            $results = $results->filter(function ($product) use ($search) {
                return stripos($product->MSKU ?? '', $search) !== false ||
                    stripos($product->ASIN ?? '', $search) !== false ||
                    stripos($product->AStitle ?? '', $search) !== false ||
                    stripos($product->serialnumber ?? '', $search) !== false ||
                    stripos($product->FNSKUviewer ?? '', $search) !== false ||
                    stripos($product->rtcounter ?? '', $search) !== false;
            });
        }

        return response()->json(['data' => $results->values()]);
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

            // UPDATED: Check if product exists first and get FNSKU data using base FNSKU
            $existingProduct = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
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

            // UPDATED: Extract base FNSKU and get related data
            $baseFnsku = $this->extractBaseFnsku($existingProduct->FNSKUviewer);

            $fnskuRecord = null;
            if (!empty($baseFnsku)) {
                $fnskuRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();
            }

            // Check for required fields (ASIN, FNSKU, MSKU)
            $missingFields = [];

            // Check FNSKU from product table
            if (empty($existingProduct->FNSKUviewer)) {
                $missingFields[] = 'FNSKU';
            }

            // Check MSKU from fnsku table
            if (!$fnskuRecord || empty($fnskuRecord->MSKU)) {
                $missingFields[] = 'MSKU';
            }

            // Check ASIN from fnsku table
            if (!$fnskuRecord || empty($fnskuRecord->ASIN)) {
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
                        'base_fnsku' => $baseFnsku,
                        'MSKU' => $fnskuRecord->MSKU ?? null,
                        'ASIN' => $fnskuRecord->ASIN ?? null
                    ]
                ]);

                // Insert history for failed attempt
                $this->insertItemHistory($request->rt_counter, 'Move to Validation Failed - Missing Fields', [
                    'reason' => $missingFieldsText,
                    'attempted_by' => Auth::user()->name ?? 'system'
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
                'base_fnsku' => $baseFnsku,
                'MSKU' => $fnskuRecord->MSKU,
                'ASIN' => $fnskuRecord->ASIN
            ]);

            // Update the product location in the database
            $updateResult = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Validation',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s')
                ]);

            Log::info('Update result: ' . $updateResult . ' rows affected');

            // Insert success history
            $this->insertItemHistory($request->rt_counter, 'Moved to Validation', [
                'from_location' => $request->current_location,
                'to_location' => 'Validation',
                'fnsku' => $existingProduct->FNSKUviewer,
                'asin' => $fnskuRecord->ASIN,
                'msku' => $fnskuRecord->MSKU,
                'moved_by' => Auth::user()->name ?? 'system'
            ]);

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

            // Insert error history
            if (isset($request->rt_counter)) {
                $this->insertItemHistory($request->rt_counter, 'Move to Validation Error', [
                    'error' => $e->getMessage(),
                    'attempted_by' => Auth::user()->name ?? 'system'
                ]);
            }

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

            // UPDATED: Check if product exists first and get FNSKU data using base FNSKU
            $existingProduct = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
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

            // UPDATED: Extract base FNSKU and get related data
            $baseFnsku = $this->extractBaseFnsku($existingProduct->FNSKUviewer);

            $fnskuRecord = null;
            if (!empty($baseFnsku)) {
                $fnskuRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();
            }

            // Check for required fields (ASIN, FNSKU, MSKU)
            $missingFields = [];

            // Check FNSKU from product table
            if (empty($existingProduct->FNSKUviewer)) {
                $missingFields[] = 'FNSKU';
            }

            // Check MSKU from fnsku table
            if (!$fnskuRecord || empty($fnskuRecord->MSKU)) {
                $missingFields[] = 'MSKU';
            }

            // Check ASIN from fnsku table
            if (!$fnskuRecord || empty($fnskuRecord->ASIN)) {
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
                        'base_fnsku' => $baseFnsku,
                        'MSKU' => $fnskuRecord->MSKU ?? null,
                        'ASIN' => $fnskuRecord->ASIN ?? null
                    ]
                ]);

                // Insert history for failed attempt
                $this->insertItemHistory($request->rt_counter, 'Move to Stockroom Failed - Missing Fields', [
                    'reason' => $missingFieldsText,
                    'attempted_by' => Auth::user()->name ?? 'system'
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
                'base_fnsku' => $baseFnsku,
                'MSKU' => $fnskuRecord->MSKU,
                'ASIN' => $fnskuRecord->ASIN
            ]);

            // Update the product location in the database
            $updateResult = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Stockroom',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s')
                ]);

            Log::info('Update result: ' . $updateResult . ' rows affected');

            // Insert success history
            $this->insertItemHistory($request->rt_counter, 'Moved to Stockroom', [
                'from_location' => $request->current_location,
                'to_location' => 'Stockroom',
                'fnsku' => $existingProduct->FNSKUviewer,
                'asin' => $fnskuRecord->ASIN,
                'msku' => $fnskuRecord->MSKU,
                'moved_by' => Auth::user()->name ?? 'system'
            ]);

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

            // Insert error history
            if (isset($request->rt_counter)) {
                $this->insertItemHistory($request->rt_counter, 'Move to Stockroom Error', [
                    'error' => $e->getMessage(),
                    'attempted_by' => Auth::user()->name ?? 'system'
                ]);
            }

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

    /**
     * Split an item into individual units with history tracking
     */
    public function splitItem(Request $request)
    {
        Log::info('=== SPLIT ITEM CALLED ===', [
            'request_data' => $request->all(),
            'product_table' => $this->productTable,
            'user' => Auth::user()->name ?? 'system'
        ]);

        try {
            // Updated validation - handle all three price fields separately
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer',
                'rt_counter' => 'required',
                'quantity' => 'required|integer|min:2',
                'price' => 'nullable|numeric|min:0',
                'priceshipping' => 'nullable|numeric|min:0',
                'tax' => 'nullable|numeric|min:0',
                'total_price' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed in splitItem', [
                    'errors' => $validator->errors(),
                    'request_data' => $request->all()
                ]);

                // Insert history for failed validation
                if ($request->rt_counter) {
                    $this->insertItemHistory($request->rt_counter, 'Split Item Failed - Validation Error', [
                        'errors' => $validator->errors()->toArray(),
                        'attempted_by' => Auth::user()->name ?? 'system'
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $productId = $request->product_id;
            $rtCounter = $request->rt_counter;
            $currentQuantity = (int) $request->quantity;

            // Handle all three price fields
            $originalPrice = (float) ($request->price ?? 0);
            $originalPriceShipping = (float) ($request->priceshipping ?? 0);
            $originalTax = (float) ($request->tax ?? 0);
            $totalPrice = $originalPrice + $originalPriceShipping + $originalTax;

            Log::info('Processing split request with all three price fields', [
                'product_id' => $productId,
                'rt_counter' => $rtCounter,
                'current_quantity' => $currentQuantity,
                'original_price' => $originalPrice,
                'original_priceshipping' => $originalPriceShipping,
                'original_tax' => $originalTax,
                'total_price' => $totalPrice
            ]);

            // Check if quantity is valid for splitting
            if ($currentQuantity <= 1) {
                $this->insertItemHistory($rtCounter, 'Split Item Failed - Invalid Quantity', [
                    'quantity' => $currentQuantity,
                    'attempted_by' => Auth::user()->name ?? 'system'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Split not possible. Quantity must be greater than 1.'
                ], 422);
            }

            // Get the original product
            $originalProduct = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->first();

            if (!$originalProduct) {
                Log::error('Product not found for splitting', [
                    'product_id' => $productId,
                    'table' => $this->productTable
                ]);

                $this->insertItemHistory($rtCounter, 'Split Item Failed - Product Not Found', [
                    'product_id' => $productId,
                    'attempted_by' => Auth::user()->name ?? 'system'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            Log::info('Original product found', [
                'ProductID' => $originalProduct->ProductID,
                'rtcounter' => $originalProduct->rtcounter,
                'quantity' => $originalProduct->quantity,
                'price' => $originalProduct->price ?? 'null',
                'priceshipping' => $originalProduct->priceshipping ?? 'null',
                'tax' => $originalProduct->tax ?? 'null'
            ]);

            // Verify the quantity matches what was sent
            $dbQuantity = (int) ($originalProduct->quantity ?? 0);
            if ($dbQuantity !== $currentQuantity) {
                Log::warning('Quantity mismatch detected', [
                    'sent_quantity' => $currentQuantity,
                    'db_quantity' => $dbQuantity,
                    'product_id' => $productId
                ]);

                $this->insertItemHistory($rtCounter, 'Split Item Failed - Quantity Mismatch', [
                    'sent_quantity' => $currentQuantity,
                    'db_quantity' => $dbQuantity,
                    'attempted_by' => Auth::user()->name ?? 'system'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Quantity mismatch. The item may have been modified by another user.'
                ], 422);
            }

            // Calculate unit prices for ALL THREE fields
            $unitPrice = $currentQuantity > 0 ? round($originalPrice / $currentQuantity, 2) : 0;
            $unitPriceShipping = $currentQuantity > 0 ? round($originalPriceShipping / $currentQuantity, 2) : 0;
            $unitTax = $currentQuantity > 0 ? round($originalTax / $currentQuantity, 2) : 0;
            $totalUnitPrice = $unitPrice + $unitPriceShipping + $unitTax;

            Log::info('Calculated unit prices for all three fields', [
                'original_price' => $originalPrice,
                'original_priceshipping' => $originalPriceShipping,
                'original_tax' => $originalTax,
                'total_original' => $totalPrice,
                'quantity' => $currentQuantity,
                'unit_price' => $unitPrice,
                'unit_priceshipping' => $unitPriceShipping,
                'unit_tax' => $unitTax,
                'total_unit_price' => $totalUnitPrice
            ]);

            // Get current max rtcounter to generate new RT numbers
            $maxRtResult = DB::table($this->productTable)
                ->selectRaw('MAX(rtcounter) as maxrt')
                ->first();

            $newRt = (int) ($maxRtResult->maxrt ?? 0);

            Log::info('Current max RT counter', [
                'max_rt' => $newRt,
                'will_start_new_items_from' => $newRt + 1
            ]);

            // Start database transaction
            DB::beginTransaction();

            try {
                // Insert history for split start
                $this->insertItemHistory($rtCounter, 'Split Item', [
                    'original_quantity' => $currentQuantity,
                    'original_price' => $originalPrice,
                    'original_priceshipping' => $originalPriceShipping,
                    'original_tax' => $originalTax,
                    'total_original_price' => $totalPrice,
                    'unit_price' => $unitPrice,
                    'unit_priceshipping' => $unitPriceShipping,
                    'unit_tax' => $unitTax,
                    'total_unit_price' => $totalUnitPrice,
                    'split_by' => Auth::user()->name ?? 'system'
                ]);

                // Prepare update data for original item - update ALL THREE price fields
                $updateData = [
                    'quantity' => 1,
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s')
                ];

                // Always update all three fields (even if they're 0)
                $updateData['price'] = $unitPrice;
                $updateData['priceshipping'] = $unitPriceShipping;
                $updateData['tax'] = $unitTax;

                // Update original item to quantity = 1 and unit prices
                $updateResult = DB::table($this->productTable)
                    ->where('ProductID', $productId)
                    ->update($updateData);

                Log::info('Original product updated with all three price fields', [
                    'updated_rows' => $updateResult,
                    'new_price' => $unitPrice,
                    'new_priceshipping' => $unitPriceShipping,
                    'new_tax' => $unitTax,
                    'update_data' => $updateData
                ]);

                if ($updateResult === 0) {
                    throw new \Exception("Failed to update original product quantity and prices");
                }

                // Insert history for original item update
                $this->insertItemHistory($rtCounter, 'Original Item Updated After Split', [
                    'new_quantity' => 1,
                    'new_price' => $unitPrice,
                    'new_priceshipping' => $unitPriceShipping,
                    'new_tax' => $unitTax,
                    'original_quantity' => $currentQuantity,
                    'original_price' => $originalPrice,
                    'original_priceshipping' => $originalPriceShipping,
                    'original_tax' => $originalTax,
                    'updated_by' => Auth::user()->name ?? 'system'
                ]);

                $newItemsCreated = 0;
                $newRtCounters = [];

                // Create (quantity - 1) new items
                for ($i = 0; $i < $currentQuantity - 1; $i++) {
                    $newRt++;
                    $newRtCounters[] = $newRt;

                    // Create new item data based on the original product
                    $newItemData = [
                        'ProductTitle' => $originalProduct->ProductTitle ?? null,
                        'itemnumber' => $originalProduct->itemnumber ?? null,
                        'RPN' => $originalProduct->RPN ?? null,
                        'PRD' => $originalProduct->PRD ?? null,
                        'quantity' => 1,
                        // Set ALL THREE price fields to their respective unit prices
                        'price' => $unitPrice,
                        'priceshipping' => $unitPriceShipping,
                        'tax' => $unitTax,
                        'orderdate' => $originalProduct->orderdate ?? null,
                        'paymentdate' => $originalProduct->paymentdate ?? null,
                        'shipdate' => $originalProduct->shipdate ?? null,
                        'datedelivered' => $originalProduct->datedelivered ?? null,
                        'description' => $originalProduct->description ?? null,
                        'supplierNotes' => $originalProduct->supplierNotes ?? null,
                        'employeeNotes' => $originalProduct->employeeNotes ?? null,
                        'stickerNotes' => $originalProduct->stickerNotes ?? null,
                        'trackingnumber' => $originalProduct->trackingnumber ?? null,
                        'trackingnumber2' => $originalProduct->trackingnumber2 ?? null,
                        'trackingnumber3' => $originalProduct->trackingnumber3 ?? null,
                        'trackingnumber4' => $originalProduct->trackingnumber4 ?? null,
                        'trackingnumber5' => $originalProduct->trackingnumber5 ?? null,
                        'ProductModuleLoc' => 'Labeling', // Keep in same location
                        'rtcounter' => $newRt,
                        'splitfromRT' => $rtCounter, // Track original RT
                        'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                    ];

                    // Remove null/empty values to prevent database issues
                    $newItemData = array_filter($newItemData, function ($value) {
                        return $value !== null && $value !== '';
                    });

                    Log::info('Preparing to insert new item with all three price fields', [
                        'new_rt' => $newRt,
                        'unit_price' => $unitPrice,
                        'unit_priceshipping' => $unitPriceShipping,
                        'unit_tax' => $unitTax,
                        'data_fields_count' => count($newItemData),
                        'table' => $this->productTable
                    ]);

                    $insertResult = DB::table($this->productTable)->insert($newItemData);

                    if (!$insertResult) {
                        throw new \Exception("Failed to create new item with RT: $newRt");
                    }

                    $newItemsCreated++;

                    // Insert history for new item
                    $this->insertItemHistory($newRt, 'New Item Created from Split', [
                        'split_from_rt' => $rtCounter,
                        'quantity' => 1,
                        'price' => $unitPrice,
                        'priceshipping' => $unitPriceShipping,
                        'tax' => $unitTax,
                        'total_unit_price' => $totalUnitPrice,
                        'created_by' => Auth::user()->name ?? 'system'
                    ]);

                    Log::info('New item created successfully with all three price fields', [
                        'new_rt' => $newRt,
                        'price' => $unitPrice,
                        'priceshipping' => $unitPriceShipping,
                        'tax' => $unitTax,
                        'split_from' => $rtCounter,
                        'items_created_so_far' => $newItemsCreated
                    ]);
                }

                // Insert final history for split completion
                $this->insertItemHistory($rtCounter, 'Split Item Completed Successfully', [
                    'original_quantity' => $currentQuantity,
                    'new_items_created' => $newItemsCreated,
                    'new_rt_counters' => implode(', ', $newRtCounters),
                    'unit_price' => $unitPrice,
                    'unit_priceshipping' => $unitPriceShipping,
                    'unit_tax' => $unitTax,
                    'total_unit_price' => $totalUnitPrice,
                    'all_three_price_fields_split' => true,
                    'completed_by' => Auth::user()->name ?? 'system'
                ]);

                // Commit transaction
                DB::commit();

                Log::info('Split operation completed successfully with all three price fields', [
                    'original_rt' => $rtCounter,
                    'original_quantity' => $currentQuantity,
                    'new_items_created' => $newItemsCreated,
                    'new_rt_counters' => $newRtCounters,
                    'unit_price' => $unitPrice,
                    'unit_priceshipping' => $unitPriceShipping,
                    'unit_tax' => $unitTax,
                    'total_unit_price' => $totalUnitPrice,
                    'total_items_after' => $currentQuantity
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Successfully split item into individual units with proportional price distribution across all fields',
                    'data' => [
                        'original_rt' => $rtCounter,
                        'original_quantity' => $currentQuantity,
                        'new_items_count' => $newItemsCreated,
                        'new_rt_counters' => $newRtCounters,
                        'price_breakdown' => [
                            'original_price' => $originalPrice,
                            'original_priceshipping' => $originalPriceShipping,
                            'original_tax' => $originalTax,
                            'original_total' => $totalPrice,
                            'unit_price' => $unitPrice,
                            'unit_priceshipping' => $unitPriceShipping,
                            'unit_tax' => $unitTax,
                            'unit_total' => $totalUnitPrice,
                        ],
                        'total_items_after_split' => $currentQuantity,
                        'all_three_fields_split' => true
                    ]
                ]);

            } catch (\Exception $e) {
                // Rollback transaction on any error
                DB::rollback();

                Log::error('Database transaction failed during split', [
                    'error' => $e->getMessage(),
                    'original_rt' => $rtCounter,
                    'product_id' => $productId
                ]);

                // Insert error history
                $this->insertItemHistory($rtCounter, 'Split Item Failed - Database Error', [
                    'error' => $e->getMessage(),
                    'attempted_by' => Auth::user()->name ?? 'system'
                ]);

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Exception in splitItem', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to split item: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
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
                'price' => 'nullable|numeric',
                'priceshipping' => 'nullable|numeric',
                'tax' => 'nullable|numeric',
            ]);

            // Get the original product to compare changes
            $originalProduct = DB::table($this->productTable)
                ->where('ProductID', $validated['ProductID'])
                ->first();

            $isUpdate = $originalProduct ? true : false;
            $rtCounter = $originalProduct->rtcounter ?? 'Unknown';

            // Update or insert the product
            $result = DB::table($this->productTable)
                ->updateOrInsert(
                    ['ProductID' => $validated['ProductID']],
                    array_merge($validated, ['lastDateUpdate' => now()->format('Y-m-d H:i:s')])
                );

            // Determine what changed for history
            $changes = [];
            if ($isUpdate && $originalProduct) {
                foreach ($validated as $key => $value) {
                    if (property_exists($originalProduct, $key)) {
                        $oldValue = $originalProduct->$key;
                        if ($oldValue != $value) {
                            $changes[$key] = [
                                'from' => $oldValue,
                                'to' => $value
                            ];
                        }
                    }
                }
            }

            // Insert history record
            $action = $isUpdate ? 'Product Updated' : 'Product Created';
            $this->insertItemHistory($rtCounter, $action, [
                'changes' => $changes,
                'total_fields_changed' => count($changes),
                'updated_by' => Auth::user()->name ?? 'system'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Labeling product saved successfully',
                'product' => $result,
                'action' => $action,
                'changes_made' => count($changes)
            ]);
        } catch (\Exception $e) {
            Log::error('Error in store method', [
                'message' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            // Try to insert error history if we have an RT counter
            if ($request->ProductID) {
                $product = DB::table($this->productTable)
                    ->where('ProductID', $request->ProductID)
                    ->first();
                if ($product && isset($product->rtcounter)) {
                    $this->insertItemHistory($product->rtcounter, 'Product Save Failed', [
                        'error' => $e->getMessage(),
                        'attempted_by' => Auth::user()->name ?? 'system'
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to save product: ' . $e->getMessage()
            ], 500);
        }
    }
}
