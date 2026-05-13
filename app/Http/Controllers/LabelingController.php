<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class LabelingController extends BasetablesController
{
    use TracksHistory;

    /**
     * Extract base FNSKU from prefixed FNSKU (same as StockroomController)
     */
    private function extractBaseFnsku($fnsku)
    {
        if (empty($fnsku)) {
            return $fnsku;
        }

        // Check if it's a prefixed FNSKU (starts with letter C-W or Y-Z, excluding X)
        // Pattern: Letter(C-W,Y-Z) + Number(1-9) + BaseFNSKU (which starts with X)
        if (preg_match('/^([C-W]|[Y-Z])(\d+)(X.+)$/', $fnsku, $matches)) {
            return $matches[3]; // Return the base FNSKU (starting with X)
        }

        return $fnsku; // Return as-is if not prefixed
    }

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
            $location = $request->input('location', 'Labeling');
            $includeImages = $request->boolean('include_images', false);

            // ── Base select (always includes eBay fallback images) ──────────────
            $selectColumns = [
                'prod.*',
                // ✅ eBay order images — overrides prod.* img columns,
                //    used as fallback when no captured images exist
                'ebayimgs.img1',  'ebayimgs.img2',  'ebayimgs.img3',
                'ebayimgs.img4',  'ebayimgs.img5',  'ebayimgs.img6',
                'ebayimgs.img7',  'ebayimgs.img8',  'ebayimgs.img9',
                'ebayimgs.img10', 'ebayimgs.img11', 'ebayimgs.img12',
                'ebayimgs.img13', 'ebayimgs.img14', 'ebayimgs.img15',
                // FNSKU / ASIN fields
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

            // ── Add captured image columns only when requested ──────────────────
            if ($includeImages) {
                $selectColumns = array_merge($selectColumns, [
                    'img.capturedimg1',  'img.capturedimg2',  'img.capturedimg3',
                    'img.capturedimg4',  'img.capturedimg5',  'img.capturedimg6',
                    'img.capturedimg7',  'img.capturedimg8',  'img.capturedimg9',
                    'img.capturedimg10', 'img.capturedimg11', 'img.capturedimg12',
                    'img.serialimg1',    'img.serialimg2',    'img.serialimg3',
                    'img.serialimg4',    'img.serialimg5',
                    'img.trackingimg1',  'img.trackingimg2',
                ]);
            }

            // ── Build query ─────────────────────────────────────────────────────
            $productsQuery = DB::table($this->productTable.' as prod')
                ->leftJoin($this->fnskuTable.' as fnsku', 'prod.MSKUviewer', '=', 'fnsku.MSKU')
                ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                // ✅ Always join — needed for thumbnail fallback even without include_images
                ->leftJoin('tblEbayOrderImages as ebayimgs', 'prod.ProductID', '=', 'ebayimgs.ProductID');

            if ($includeImages) {
                $productsQuery->leftJoin(
                    $this->capturedImagesTable.' as img',
                    'prod.ProductID', '=', 'img.ProductID'
                );
            }

            $productsQuery
                ->select($selectColumns)
                ->where('prod.ProductModuleLoc', $location)
                ->distinct();

            // ── Search ──────────────────────────────────────────────────────────
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

            // ── Transform ───────────────────────────────────────────────────────
            $products->getCollection()->transform(function ($product) use ($includeImages) {

                if (empty($product->FNSKU) && ! empty($product->FNSKUviewer)) {
                    $product->FNSKU = $product->FNSKUviewer;
                }

                if (empty($product->MSKU) && ! empty($product->MSKUviewer)) {
                    $product->MSKU = $product->MSKUviewer;
                }

                $product->company = $this->company;

                if ($includeImages) {
                    // ── Build capturedImages object ──────────────────────────
                    $capturedImages = (object) [];

                    for ($i = 1; $i <= 12; $i++) {
                        $key = "capturedimg{$i}";
                        if (! empty($product->$key)) {
                            $capturedImages->$key = $product->$key;
                        }
                        unset($product->$key);
                    }

                    foreach (['serialimg1', 'serialimg2', 'serialimg3', 'serialimg4', 'serialimg5',
                        'trackingimg1', 'trackingimg2'] as $key) {
                        if (! empty($product->$key)) {
                            $capturedImages->$key = $product->$key;
                        }
                        unset($product->$key);
                    }

                    $product->capturedImages = $capturedImages;

                    // ── Thumbnail priority ───────────────────────────────────
                    // 1st choice: captured image  (path: /images/product_images/{company}/)
                    // 2nd choice: eBay image      (path: /images/thumbnails/)
                    // Frontend already handles the path difference via img1_source
                    if (! empty($capturedImages->capturedimg1)) {
                        // $product->img1        = $capturedImages->capturedimg1;
                        $product->img1_source = 'captured';
                    } elseif (! empty($product->img1)) {
                        // img1 already set from ebayimgs join — keep it
                        $product->img1_source = 'ebay';
                    } else {
                        $product->img1_source = null;
                    }

                } else {
                    // No captured images requested — capturedImages empty object,
                    // but img1–img15 from ebayimgs join are still present on $product
                    // so the table thumbnail fallback works automatically
                    $product->capturedImages = (object) [];
                    $product->img1_source = ! empty($product->img1) ? 'ebay' : null;
                }

                return $product;
            });

            return response()->json($products);

        } catch (\Exception $e) {
            Log::error('Error in LabelingController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching products',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getFnskuData(Request $request)
    {
        $search = $request->input('search');
        $location = $request->input('location');

        // ✅ UPDATED: Keep same structure but fetch system_title
        $productsQuery = DB::table($this->productTable.' as prod')
            ->select(['prod.*'])
            ->where('prod.ProductModuleLoc', $location);

        if (! empty($search)) {
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
            if (! empty($product->FNSKUviewer)) {
                $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                $baseFnskus[] = $baseFnsku;
            }
        }
        $baseFnskus = array_unique($baseFnskus);

        // Get FNSKU data
        $fnskuData = [];
        if (! empty($baseFnskus)) {
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
        if (! empty($asinList)) {
            // ✅ UPDATED: Fetch both system_title and internal
            $asinRecords = DB::table($this->asinTable)
                ->select('ASIN', 'internal', 'system_title')
                ->whereIn('ASIN', $asinList)
                ->get();

            foreach ($asinRecords as $record) {
                $asinData[$record->ASIN] = $record;
            }
        }

        // ✅ UPDATED: Combine data with proper title priority
        $results = $products->map(function ($product) use ($fnskuData, $asinData) {
            $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);

            if (isset($fnskuData[$baseFnsku])) {
                $fnskuRecord = $fnskuData[$baseFnsku];
                $product->FNSKU = $fnskuRecord->FNSKU;
                $product->MSKU = $fnskuRecord->MSKU;
                $product->ASIN = $fnskuRecord->ASIN;
                $product->grading = $fnskuRecord->grading;
                $product->storename = $fnskuRecord->storename;

                // ✅ NEW: Apply title priority logic
                if (isset($asinData[$fnskuRecord->ASIN])) {
                    $asinRecord = $asinData[$fnskuRecord->ASIN];

                    // Priority: system_title > internal > ProductTitle
                    if (! empty(trim($asinRecord->system_title ?? ''))) {
                        $product->AStitle = $asinRecord->system_title;
                    } elseif (! empty(trim($asinRecord->internal ?? ''))) {
                        $product->AStitle = $asinRecord->internal;
                    } elseif (! empty(trim($product->ProductTitle ?? ''))) {
                        $product->AStitle = $product->ProductTitle;
                    } else {
                        $product->AStitle = '—';
                    }

                    // ✅ Also set these for JavaScript access
                    $product->system_title = $asinRecord->system_title ?? null;
                    $product->internal = $asinRecord->internal ?? null;
                } else {
                    // No ASIN data, fallback to ProductTitle
                    $product->AStitle = ! empty(trim($product->ProductTitle ?? ''))
                        ? $product->ProductTitle
                        : '—';
                    $product->system_title = null;
                    $product->internal = null;
                }
            }

            return $product;
        });

        // ✅ UPDATED: Apply additional filtering including system_title
        if (! empty($search)) {
            $results = $results->filter(function ($product) use ($search) {
                return stripos($product->MSKU ?? '', $search) !== false ||
                    stripos($product->ASIN ?? '', $search) !== false ||
                    stripos($product->AStitle ?? '', $search) !== false ||
                    stripos($product->system_title ?? '', $search) !== false ||
                    stripos($product->internal ?? '', $search) !== false ||
                    stripos($product->serialnumber ?? '', $search) !== false ||
                    stripos($product->FNSKUviewer ?? '', $search) !== false ||
                    stripos($product->rtcounter ?? '', $search) !== false;
            });
        }

        return response()->json(['data' => $results->values()]);
    }

    public function moveToValidation(Request $request)
    {
        Log::info('=== MOVE TO VALIDATION CALLED ===');
        Log::info('Request method: '.$request->method());
        Log::info('Request URL: '.$request->fullUrl());
        Log::info('Request headers: ', $request->headers->all());
        Log::info('Request body: ', $request->all());
        Log::info('Product table: '.$this->productTable);

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
                    'request_data' => $request->all(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            Log::info('Validation passed, attempting to update product', [
                'product_id' => $request->product_id,
                'rt_counter' => $request->rt_counter,
                'current_location' => $request->current_location,
            ]);

            // Check if product exists first and get FNSKU data using base FNSKU
            $existingProduct = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->first();

            if (! $existingProduct) {
                Log::error('Product not found for moveToValidation', [
                    'product_id' => $request->product_id,
                    'table' => $this->productTable,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            Log::info('Product found, current location: '.$existingProduct->ProductModuleLoc);

            // 🔒 Check if item has been tested
            if (! $existingProduct->isTested) {
                Log::warning('Cannot move to Validation - item has not been tested yet', [
                    'product_id' => $request->product_id,
                    'rt_counter' => $request->rt_counter,
                    'isTested' => $existingProduct->isTested,
                ]);

                $this->trackHistory(
                    'Labeling',
                    'Move to Validation Failed',
                    "RTC: {$request->rt_counter}",
                    'Item not yet tested'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Cannot move to Validation. This item has not been tested yet. Please move it to Testing first.',
                    'requires_testing' => true,
                ], 422);
            }

            // Check if quantity > 1
            $quantity = (int) ($existingProduct->quantity ?? 0);
            if ($quantity > 1) {
                Log::warning('Cannot move to Validation - quantity is greater than 1', [
                    'product_id' => $request->product_id,
                    'rt_counter' => $request->rt_counter,
                    'quantity' => $quantity,
                ]);

                $this->trackHistory(
                    'Labeling',
                    'Move to Validation Failed',
                    "RTC: {$request->rt_counter}",
                    "Quantity: {$quantity} (Split Required)"
                );

                return response()->json([
                    'success' => false,
                    'message' => "Cannot move to Validation. This item has a quantity of {$quantity}. Please split the item into individual units first.",
                    'requires_split' => true,
                    'quantity' => $quantity,
                    'product_data' => [
                        'ProductID' => $existingProduct->ProductID,
                        'rtcounter' => $existingProduct->rtcounter,
                        'ProductTitle' => $existingProduct->ProductTitle,
                        'quantity' => $quantity,
                        'price' => $existingProduct->price ?? 0,
                        'priceshipping' => $existingProduct->priceshipping ?? 0,
                        'tax' => $existingProduct->tax ?? 0,
                    ],
                ], 422);
            }

            // Extract base FNSKU and get related data
            $baseFnsku = $this->extractBaseFnsku($existingProduct->FNSKUviewer);

            $fnskuRecord = null;
            if (! empty($baseFnsku)) {
                $fnskuRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();
            }

            // Check for required fields (ASIN, FNSKU, MSKU)
            $missingFields = [];

            if (empty($existingProduct->FNSKUviewer)) {
                $missingFields[] = 'FNSKU';
            }

            if (! $fnskuRecord || empty($fnskuRecord->MSKU)) {
                $missingFields[] = 'MSKU';
            }

            if (! $fnskuRecord || empty($fnskuRecord->ASIN)) {
                $missingFields[] = 'ASIN';
            }

            // If any required fields are missing, return error
            if (! empty($missingFields)) {
                $missingFieldsText = implode(', ', $missingFields);
                Log::warning('Cannot move to Validation - missing required fields', [
                    'product_id' => $request->product_id,
                    'rt_counter' => $request->rt_counter,
                    'missing_fields' => $missingFields,
                ]);

                $this->trackHistory(
                    'Labeling',
                    'Move to Validation Failed',
                    "RTC: {$request->rt_counter}",
                    "Missing: {$missingFieldsText}"
                );

                return response()->json([
                    'success' => false,
                    'message' => "Cannot move to Validation. Missing required fields: {$missingFieldsText}. Please set the FNSKU first.",
                    'missing_fields' => $missingFields,
                    'requires_fnsku_setup' => true,
                ], 422);
            }

            // All required fields are present, proceed with the move
            Log::info('All required fields present, proceeding with move to Validation');

            // Update the product location in the database
            $updateResult = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Validation',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                ]);

            Log::info('Update result: '.$updateResult.' rows affected');

            $this->trackLocationChange(
                'Labeling',
                "RTC: {$request->rt_counter} | FNSKU: {$baseFnsku}",
                $request->current_location,
                'Validation'
            );

            return response()->json([
                'success' => true,
                'message' => 'Product successfully moved to Validation',
                'debug_info' => [
                    'rows_affected' => $updateResult,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in moveToValidation', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all(),
            ]);

            if (isset($request->rt_counter)) {
                $this->trackHistory(
                    'Labeling',
                    'Move to Validation Error',
                    "RTC: {$request->rt_counter}",
                    "Error: {$e->getMessage()}"
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to move product to Validation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function moveToStockroom(Request $request)
    {
        Log::info('=== MOVE TO STOCKROOM CALLED ===');
        Log::info('Request method: '.$request->method());
        Log::info('Request URL: '.$request->fullUrl());
        Log::info('Request headers: ', $request->headers->all());
        Log::info('Request body: ', $request->all());
        Log::info('Product table: '.$this->productTable);

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
                    'request_data' => $request->all(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            Log::info('Validation passed, attempting to update product', [
                'product_id' => $request->product_id,
                'rt_counter' => $request->rt_counter,
                'current_location' => $request->current_location,
            ]);

            // Check if product exists first and get FNSKU data using base FNSKU
            $existingProduct = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->first();

            if (! $existingProduct) {
                Log::error('Product not found for moveToStockroom', [
                    'product_id' => $request->product_id,
                    'table' => $this->productTable,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            Log::info('Product found, current location: '.$existingProduct->ProductModuleLoc);

            // 🔒 Check if item has been tested
            if (! $existingProduct->isTested) {
                Log::warning('Cannot move to Stockroom - item has not been tested yet', [
                    'product_id' => $request->product_id,
                    'rt_counter' => $request->rt_counter,
                    'isTested' => $existingProduct->isTested,
                ]);

                $this->trackHistory(
                    'Labeling',
                    'Move to Stockroom Failed',
                    "RTC: {$request->rt_counter}",
                    'Item not yet tested'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Cannot move to Stockroom. This item has not been tested yet. Please move it to Testing first.',
                    'requires_testing' => true,
                ], 422);
            }

            // Extract base FNSKU and get related data
            $baseFnsku = $this->extractBaseFnsku($existingProduct->FNSKUviewer);

            $fnskuRecord = null;
            if (! empty($baseFnsku)) {
                $fnskuRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();
            }

            // Check for required fields (ASIN, FNSKU, MSKU)
            $missingFields = [];

            if (empty($existingProduct->FNSKUviewer)) {
                $missingFields[] = 'FNSKU';
            }

            if (! $fnskuRecord || empty($fnskuRecord->MSKU)) {
                $missingFields[] = 'MSKU';
            }

            if (! $fnskuRecord || empty($fnskuRecord->ASIN)) {
                $missingFields[] = 'ASIN';
            }

            // If any required fields are missing, return error
            if (! empty($missingFields)) {
                $missingFieldsText = implode(', ', $missingFields);
                Log::warning('Cannot move to Stockroom - missing required fields', [
                    'product_id' => $request->product_id,
                    'rt_counter' => $request->rt_counter,
                    'missing_fields' => $missingFields,
                ]);

                $this->trackHistory(
                    'Labeling',
                    'Move to Stockroom Failed',
                    "RTC: {$request->rt_counter}",
                    "Missing: {$missingFieldsText}"
                );

                return response()->json([
                    'success' => false,
                    'message' => "Cannot move to Stockroom. Missing required fields: {$missingFieldsText}. Please set the FNSKU first.",
                    'missing_fields' => $missingFields,
                    'requires_fnsku_setup' => true,
                ], 422);
            }

            // All required fields are present, proceed with the move
            Log::info('All required fields present, proceeding with move to Stockroom');

            // Update the product location in the database
            $updateResult = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Stockroom',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                ]);

            Log::info('Update result: '.$updateResult.' rows affected');

            $this->trackLocationChange(
                'Labeling',
                "RTC: {$request->rt_counter} | FNSKU: {$baseFnsku}",
                $request->current_location,
                'Stockroom'
            );

            return response()->json([
                'success' => true,
                'message' => 'Product successfully moved to Stockroom',
                'debug_info' => [
                    'rows_affected' => $updateResult,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in moveToStockroom', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all(),
            ]);

            if (isset($request->rt_counter)) {
                $this->trackHistory(
                    'Labeling',
                    'Move to Stockroom Error',
                    "RTC: {$request->rt_counter}",
                    "Error: {$e->getMessage()}"
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to move product to Stockroom',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function moveBackToReceived(Request $request)
    {
        Log::info('=== MOVE BACK TO RECEIVED CALLED ===');
        Log::info('Request method: '.$request->method());
        Log::info('Request URL: '.$request->fullUrl());
        Log::info('Request headers: ', $request->headers->all());
        Log::info('Request body: ', $request->all());
        Log::info('Product table: '.$this->productTable);

        try {
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'product_id' => 'required',
                'rt_counter' => 'required',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed in moveBackToReceived', [
                    'errors' => $validator->errors(),
                    'request_data' => $request->all(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            Log::info('Validation passed, attempting to update product', [
                'product_id' => $request->product_id,
                'rt_counter' => $request->rt_counter,
            ]);

            // Check if product exists first
            $existingProduct = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->first();

            if (! $existingProduct) {
                Log::error('Product not found for moveBackToReceived', [
                    'product_id' => $request->product_id,
                    'table' => $this->productTable,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            Log::info('Product found, current location: '.$existingProduct->ProductModuleLoc);

            // Extract base FNSKU for tracking purposes
            $baseFnsku = $this->extractBaseFnsku($existingProduct->FNSKUviewer);

            // Update the product location and clear serial number
            $updateResult = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Received',
                    'serialnumber' => null,
                    'serialnumberb' => null,
                    'serialnumberc' => null,
                    'serialnumberd' => null,
                    'PCN' => null,
                    'basketnumber' => null,
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                ]);

            Log::info('Update result: '.$updateResult.' rows affected');

            // Delete captured images from tblcapturedimages
            $deletedImages = DB::table($this->capturedImagesTable)
                ->where('ProductID', $request->product_id)
                ->delete();

            Log::info('Deleted captured images', [
                'product_id' => $request->product_id,
                'rows_deleted' => $deletedImages,
            ]);

            // ✅ Track successful move with TracksHistory
            $this->trackLocationChange(
                'Labeling',
                "RTC: {$request->rt_counter}".($baseFnsku ? " | FNSKU: {$baseFnsku}" : ''),
                $existingProduct->ProductModuleLoc,
                'Received'
            );

            return response()->json([
                'success' => true,
                'message' => 'Product successfully moved back to Received',
                'debug_info' => [
                    'rows_affected' => $updateResult,
                    'images_deleted' => $deletedImages,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Exception in moveBackToReceived', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all(),
            ]);

            // ✅ Track error with TracksHistory
            if (isset($request->rt_counter)) {
                $this->trackHistory(
                    'Labeling',
                    'Move Back to Received Error',
                    "RTC: {$request->rt_counter}",
                    "Error: {$e->getMessage()}"
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to move product back to Received',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function moveToTesting(Request $request)
    {
        Log::info('=== MOVE TO TESTING CALLED ===');
        Log::info('Request method: '.$request->method());
        Log::info('Request URL: '.$request->fullUrl());
        Log::info('Request headers: ', $request->headers->all());
        Log::info('Request body: ', $request->all());
        Log::info('Product table: '.$this->productTable);

        try {
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'product_id' => 'required',
                'rt_counter' => 'required',
                'current_location' => 'required',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed in moveToTesting', [
                    'errors' => $validator->errors(),
                    'request_data' => $request->all(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            Log::info('Validation passed, attempting to update product', [
                'product_id' => $request->product_id,
                'rt_counter' => $request->rt_counter,
                'current_location' => $request->current_location,
            ]);

            // Check if product exists first and get FNSKU data using base FNSKU
            $existingProduct = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->first();

            if (! $existingProduct) {
                Log::error('Product not found for moveToTesting', [
                    'product_id' => $request->product_id,
                    'table' => $this->productTable,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            Log::info('Product found, current location: '.$existingProduct->ProductModuleLoc);

            // ── Checklist gate ─────────────────────────────────────────────
            $hasChecklist = DB::table('tblreceivedchecklist')
                ->where('rtcounter', $request->rt_counter)
                ->exists();

            if (! $hasChecklist) {
                Log::warning('Cannot move to Testing - no checklist record found', [
                    'product_id' => $request->product_id,
                    'rt_counter' => $request->rt_counter,
                ]);

                $this->trackHistory(
                    'Labeling',
                    'Move to Testing Failed',
                    "RTC: {$request->rt_counter}",
                    'No checklist record found'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Cannot move to Testing. This item has no receiving checklist record. Please complete the receiving checklist first.',
                    'requires_checklist' => true,
                ], 422);
            }
            // ──────────────────────────────────────────────────────────────

            // Check if quantity > 1
            $quantity = (int) ($existingProduct->quantity ?? 0);
            if ($quantity > 1) {
                Log::warning('Cannot move to Testing - quantity is greater than 1', [
                    'product_id' => $request->product_id,
                    'rt_counter' => $request->rt_counter,
                    'quantity' => $quantity,
                ]);

                $this->trackHistory(
                    'Labeling',
                    'Move to Testing Failed',
                    "RTC: {$request->rt_counter}",
                    "Quantity: {$quantity} (Split Required)"
                );

                return response()->json([
                    'success' => false,
                    'message' => "Cannot move to Testing. This item has a quantity of {$quantity}. Please split the item into individual units first.",
                    'requires_split' => true,
                    'quantity' => $quantity,
                    'product_data' => [
                        'ProductID' => $existingProduct->ProductID,
                        'rtcounter' => $existingProduct->rtcounter,
                        'ProductTitle' => $existingProduct->ProductTitle,
                        'quantity' => $quantity,
                        'price' => $existingProduct->price ?? 0,
                        'priceshipping' => $existingProduct->priceshipping ?? 0,
                        'tax' => $existingProduct->tax ?? 0,
                    ],
                ], 422);
            }

            // Extract base FNSKU and get related data
            $baseFnsku = $this->extractBaseFnsku($existingProduct->FNSKUviewer);

            $fnskuRecord = null;
            if (! empty($baseFnsku)) {
                $fnskuRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();
            }

            // Check for required fields (ASIN, FNSKU, MSKU)
            $missingFields = [];

            if (empty($existingProduct->FNSKUviewer)) {
                $missingFields[] = 'FNSKU';
            }

            if (! $fnskuRecord || empty($fnskuRecord->MSKU)) {
                $missingFields[] = 'MSKU';
            }

            if (! $fnskuRecord || empty($fnskuRecord->ASIN)) {
                $missingFields[] = 'ASIN';
            }

            if (! empty($missingFields)) {
                $missingFieldsText = implode(', ', $missingFields);
                Log::warning('Cannot move to Testing - missing required fields', [
                    'product_id' => $request->product_id,
                    'rt_counter' => $request->rt_counter,
                    'missing_fields' => $missingFields,
                ]);

                $this->trackHistory(
                    'Labeling',
                    'Move to Testing Failed',
                    "RTC: {$request->rt_counter}",
                    "Missing: {$missingFieldsText}"
                );

                return response()->json([
                    'success' => false,
                    'message' => "Cannot move to Testing. Missing required fields: {$missingFieldsText}. Please set the FNSKU first.",
                    'missing_fields' => $missingFields,
                    'requires_fnsku_setup' => true,
                ], 422);
            }

            // All checks passed, proceed with the move
            Log::info('All checks passed, proceeding with move to Testing');

            $updateResult = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Testing',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                ]);

            Log::info('Update result: '.$updateResult.' rows affected');

            $this->trackLocationChange(
                'Labeling',
                "RTC: {$request->rt_counter} | FNSKU: {$baseFnsku}",
                $request->current_location,
                'Testing'
            );

            return response()->json([
                'success' => true,
                'message' => 'Product successfully moved to Testing',
                'debug_info' => [
                    'rows_affected' => $updateResult,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in moveToTesting', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all(),
            ]);

            if (isset($request->rt_counter)) {
                $this->trackHistory(
                    'Labeling',
                    'Move to Testing Error',
                    "RTC: {$request->rt_counter}",
                    "Error: {$e->getMessage()}"
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to move product to Testing',
                'error' => $e->getMessage(),
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
            'user' => Auth::user()->name ?? 'system',
        ]);

        try {
            // Validation
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
                    'request_data' => $request->all(),
                ]);

                if ($request->rt_counter) {
                    $this->trackHistory(
                        'Labeling',
                        'Split Item Failed',
                        "RTC: {$request->rt_counter}",
                        'Validation Error'
                    );
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
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

            Log::info('Processing split request', [
                'product_id' => $productId,
                'rt_counter' => $rtCounter,
                'current_quantity' => $currentQuantity,
                'total_price' => $totalPrice,
            ]);

            // Check if quantity is valid for splitting
            if ($currentQuantity <= 1) {
                $this->trackHistory(
                    'Labeling',
                    'Split Item Failed',
                    "RTC: {$rtCounter}",
                    "Invalid Quantity: {$currentQuantity}"
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Split not possible. Quantity must be greater than 1.',
                ], 422);
            }

            // Get the original product
            $originalProduct = DB::table($this->productTable)
                ->where('ProductID', $productId)
                ->first();

            if (! $originalProduct) {
                Log::error('Product not found for splitting');

                $this->trackHistory(
                    'Labeling',
                    'Split Item Failed',
                    "RTC: {$rtCounter}",
                    'Product Not Found'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            // Verify the quantity matches
            $dbQuantity = (int) ($originalProduct->quantity ?? 0);
            if ($dbQuantity !== $currentQuantity) {
                Log::warning('Quantity mismatch detected');

                $this->trackHistory(
                    'Labeling',
                    'Split Item Failed',
                    "RTC: {$rtCounter} | Qty: {$currentQuantity}",
                    "DB Qty: {$dbQuantity} (Mismatch)"
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Quantity mismatch. The item may have been modified by another user.',
                ], 422);
            }

            // Fetch ASIN from the FNSKU table using the base FNSKU
            $baseFnsku = $this->extractBaseFnsku($originalProduct->FNSKUviewer);
            $asinViewer = null;

            if (! empty($baseFnsku)) {
                $fnskuRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();

                if ($fnskuRecord && ! empty($fnskuRecord->ASIN)) {
                    $asinViewer = $fnskuRecord->ASIN;
                }
            }

            // Calculate unit prices for ALL THREE fields
            $unitPrice = $currentQuantity > 0 ? round($originalPrice / $currentQuantity, 2) : 0;
            $unitPriceShipping = $currentQuantity > 0 ? round($originalPriceShipping / $currentQuantity, 2) : 0;
            $unitTax = $currentQuantity > 0 ? round($originalTax / $currentQuantity, 2) : 0;
            $totalUnitPrice = $unitPrice + $unitPriceShipping + $unitTax;

            // Get current max rtcounter to generate new RT numbers
            $maxRtResult = DB::table($this->productTable)
                ->selectRaw('MAX(rtcounter) as maxrt')
                ->first();
            $newRt = (int) ($maxRtResult->maxrt ?? 0);

            // Start database transaction
            DB::beginTransaction();

            try {
                // Update original item to quantity = 1
                $updateData = [
                    'quantity' => 1,
                    'price' => $unitPrice,
                    'priceshipping' => $unitPriceShipping,
                    'tax' => $unitTax,
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                ];

                if (! empty($asinViewer)) {
                    $updateData['ASINviewer'] = $asinViewer;
                }

                $updateResult = DB::table($this->productTable)
                    ->where('ProductID', $productId)
                    ->update($updateData);

                if ($updateResult === 0) {
                    throw new \Exception('Failed to update original product');
                }

                $newItemsCreated = 0;
                $newRtCounters = [];

                // Create (quantity - 1) new items
                for ($i = 0; $i < $currentQuantity - 1; $i++) {
                    $newRt++;
                    $newRtCounters[] = $newRt;

                    $newItemData = [
                        'ProductTitle' => $originalProduct->ProductTitle ?? null,
                        'itemnumber' => $originalProduct->itemnumber ?? null,
                        'rtid' => $originalProduct->rtid ?? null,
                        'RPN' => $originalProduct->RPN ?? null,
                        'PRD' => $originalProduct->PRD ?? null,
                        'quantity' => 1,
                        'price' => $unitPrice,
                        'priceshipping' => $unitPriceShipping,
                        'tax' => $unitTax,
                        'orderdate' => $originalProduct->orderdate ?? null,
                        'paymentdate' => $originalProduct->paymentdate ?? null,
                        'shipdate' => $originalProduct->shipdate ?? null,
                        'datedelivered' => $originalProduct->datedelivered ?? null,
                        'description' => $originalProduct->description ?? null,
                        'supplierNotes' => $originalProduct->supplierNotes ?? null,
                        'EmployeeNote' => $originalProduct->EmployeeNote ?? null,
                        'stickernote' => $originalProduct->stickernote ?? null,
                        'trackingnumber' => $originalProduct->trackingnumber ?? null,
                        'ProductModuleLoc' => 'Labeling',
                        'rtcounter' => $newRt,
                        'splitfromRT' => $rtCounter,
                        'ASINviewer' => $asinViewer,
                        'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                    ];

                    // Remove null/empty values before inserting
                    $newItemData = array_filter($newItemData, function ($value) {
                        return $value !== null && $value !== '';
                    });

                    $insertResult = DB::table($this->productTable)->insert($newItemData);

                    if (! $insertResult) {
                        throw new \Exception("Failed to create new item with RT: $newRt");
                    }

                    $newItemsCreated++;
                }

                // Single consolidated history entry
                $newRtList = implode(', ', $newRtCounters);
                $beforeState = "RTC: {$rtCounter} | Qty: {$currentQuantity} | Price: $".number_format($totalPrice, 2);
                $afterState = "Split into {$currentQuantity} items (RTC: {$rtCounter}, {$newRtList}) @ $".number_format($totalUnitPrice, 2).' each';

                $this->trackHistory(
                    'Labeling',
                    'Split Item',
                    $beforeState,
                    $afterState
                );

                // Commit transaction
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Successfully split item into individual units',
                    'data' => [
                        'original_rt' => $rtCounter,
                        'original_quantity' => $currentQuantity,
                        'new_items_count' => $newItemsCreated,
                        'new_rt_counters' => $newRtCounters,
                        'unit_price' => $unitPrice,
                        'unit_priceshipping' => $unitPriceShipping,
                        'unit_tax' => $unitTax,
                        'asin_viewer' => $asinViewer,
                    ],
                ]);

            } catch (\Exception $e) {
                DB::rollback();

                Log::error('Database transaction failed during split', [
                    'error' => $e->getMessage(),
                ]);

                $this->trackHistory(
                    'Labeling',
                    'Split Item Failed',
                    "RTC: {$rtCounter}",
                    "Error: {$e->getMessage()}"
                );

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Exception in splitItem', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to split item: '.$e->getMessage(),
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
                'EmployeeNote' => 'nullable|string',
                'stickernote' => 'nullable|string',
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

            // ✅ UPDATED: Track with exact before/after values
            if ($isUpdate && $originalProduct) {
                // Collect only the fields that actually changed
                $changes = [];
                foreach ($validated as $key => $newValue) {
                    if (property_exists($originalProduct, $key)) {
                        $oldValue = $originalProduct->$key;

                        // Compare values (handle null, empty strings, etc.)
                        $oldValueNormalized = $oldValue === null ? '' : (string) $oldValue;
                        $newValueNormalized = $newValue === null ? '' : (string) $newValue;

                        if ($oldValueNormalized !== $newValueNormalized) {
                            $changes[$key] = [
                                'old' => $oldValue ?? 'null',
                                'new' => $newValue ?? 'null',
                            ];
                        }
                    }
                }

                // Only log if there are actual changes
                if (count($changes) > 0) {
                    // Build before and after strings with exact values
                    $beforeParts = ["RTC: {$rtCounter}"];
                    $afterParts = ["RTC: {$rtCounter}"];

                    foreach ($changes as $field => $change) {
                        // Format field names for readability
                        $fieldDisplay = ucfirst(str_replace('_', ' ', $field));

                        // Truncate long values for readability
                        $oldDisplay = $this->truncateForDisplay($change['old'], 50);
                        $newDisplay = $this->truncateForDisplay($change['new'], 50);

                        $beforeParts[] = "{$fieldDisplay}: {$oldDisplay}";
                        $afterParts[] = "{$fieldDisplay}: {$newDisplay}";
                    }

                    $beforeState = implode(' | ', $beforeParts);
                    $afterState = implode(' | ', $afterParts);

                    $this->trackHistory(
                        'Labeling',
                        'Update',
                        $beforeState,
                        $afterState
                    );
                }
            } elseif (! $isUpdate) {
                // New product created
                $this->trackCreate(
                    'Labeling',
                    "RTC: {$rtCounter}"
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Labeling product saved successfully',
                'product' => $result,
                'action' => $isUpdate ? 'updated' : 'created',
                'changes_made' => $isUpdate ? count($changes ?? []) : 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in store method', [
                'message' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            // ✅ Track error
            if ($request->ProductID) {
                $product = DB::table($this->productTable)
                    ->where('ProductID', $request->ProductID)
                    ->first();
                if ($product && isset($product->rtcounter)) {
                    $this->trackHistory(
                        'Labeling',
                        'Update Failed',
                        "RTC: {$product->rtcounter}",
                        "Error: {$e->getMessage()}"
                    );
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to save product: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper method to truncate long values for display
     */
    private function truncateForDisplay($value, $maxLength = 50)
    {
        if ($value === null || $value === 'null') {
            return 'null';
        }

        $strValue = (string) $value;

        if (mb_strlen($strValue) <= $maxLength) {
            return $strValue;
        }

        return mb_substr($strValue, 0, $maxLength - 3).'...';
    }

    public function checkDuplicateSerial(Request $request)
    {
        $serial = $request->input('serial');
        $currentProductId = $request->input('current_product_id');
        $currentSerialField = $request->input('serial_field'); // e.g., 'serialnumbera' or 'serialnumberb'

        if (empty($serial)) {
            return response()->json(['duplicate' => false]);
        }

        // Get all serial columns from the products table
        $cols = array_filter(
            Schema::getColumnListing($this->productTable),
            fn ($c) => str_starts_with($c, 'serialnumber')
        );

        // Check 1: Duplicate across different products
        // Exclude records where ProductModuleLoc is rts, soldlist, returnlist, or Merged
        $query = DB::table($this->productTable)
            ->select('*')
            ->where(function ($q) use ($cols, $serial) {
                foreach ($cols as $c) {
                    $q->orWhere($c, $serial);
                }
            })
            ->whereNotIn('ProductModuleLoc', ['rts', 'soldlist', 'returnlist', 'Merged']);

        // Exclude the current product if provided
        if (! empty($currentProductId)) {
            $query->where('ProductID', '!=', $currentProductId);
        }

        $existing = $query->first();

        if ($existing) {
            return response()->json([
                'duplicate' => true,
                'type' => 'cross_product',
                'message' => 'This serial number already exists in another product.',
                'product' => $existing,
            ]);
        }

        // Check 2: Duplicate within the same product (Serial A vs Serial B vs Serial C, etc.)
        if (! empty($currentProductId) && ! empty($currentSerialField)) {
            $product = DB::table($this->productTable)
                ->where('ProductID', $currentProductId)
                ->first();

            if ($product) {
                // Get other serial fields to compare against
                $otherSerialFields = array_filter($cols, fn ($c) => $c !== $currentSerialField);

                foreach ($otherSerialFields as $otherField) {
                    if (isset($product->$otherField) && trim($product->$otherField) !== '' && $serial === $product->$otherField) {
                        // Extract labels for better error message
                        $currentLabel = strtoupper(str_replace('serialnumber', '', $currentSerialField));
                        $otherLabel = strtoupper(str_replace('serialnumber', '', $otherField));

                        return response()->json([
                            'duplicate' => true,
                            'type' => 'same_product',
                            'message' => "Serial {$currentLabel} and Serial {$otherLabel} cannot have the same value.",
                            'conflicting_field' => $otherField,
                            'current_field' => $currentSerialField,
                        ]);
                    }
                }
            }
        }

        return response()->json(['duplicate' => false]);
    }
}
