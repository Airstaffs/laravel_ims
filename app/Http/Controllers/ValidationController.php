<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator; // Add this line

class ValidationController extends BasetablesController
{
    use TracksHistory;

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
            $capturedImagesTable = 'tblcapturedimages';
            $company = 'Airstaffs';

            Log::info('Tables being used:', [
                'productTable' => $productTable,
                'fnskuTable' => $fnskuTable,
                'asinTable' => $asinTable,
                'capturedImagesTable' => $capturedImagesTable,
                'company' => $company,
            ]);

            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Validation');
            $includeImages = $request->boolean('include_images', false);

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

            $product = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Stockroom',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                ]);

            $employeeName = auth()->user()->username ?? 'System';
            $identifier = "RT#{$request->rt_counter}".
                          (! empty($product->ProductTitle) ? " - {$product->ProductTitle}" : '');

            $this->trackLocationChange(
                'Labeling',
                $identifier,
                $request->current_location,
                'Stockroom',
                $employeeName
            );

            return response()->json([
                'success' => true,
                'message' => 'Product successfully moved to Stockroom',
            ]);
        } catch (\Exception $e) {
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

            $product = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'ProductModuleLoc' => 'Labeling',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                ]);

            $employeeName = auth()->user()->username ?? 'System';
            $identifier = "RT#{$request->rt_counter}".
                          (! empty($product->ProductTitle) ? " - {$product->ProductTitle}" : '');

            $this->trackLocationChange(
                'Validation',
                $identifier,
                $request->current_location,
                'Labeling',
                $employeeName
            );

            return response()->json([
                'success' => true,
                'message' => 'Product successfully moved to Labeling',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error moving product to Labeling: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to move product to Labeling',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function validate(Request $request)
    {
        try {

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

            $product = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $now = now()->format('Y-m-d H:i:s');

            $userId = auth()->id() ?? 0;

            $oldValidationStatus = $product->validation_status ?? 'pending';

            $updateData = [
                'validation_status' => $request->status,
                'lastDateUpdate' => $now,
            ];

            if ($request->status === 'invalid' && $request->filled('ProductModuleLoc')) {
                $updateData['ProductModuleLoc'] = $request->ProductModuleLoc;
            }

            DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update($updateData);

            $employeeName = auth()->user()->username ?? 'System';
            $identifier = "RT#{$request->rt_counter}".
                          (! empty($product->ProductTitle) ? " - {$product->ProductTitle}" : '');

            $currentLocation = $product->ProductModuleLoc ?? 'Unknown';
            $newValidationStatus = $request->status;

            if ($request->status === 'validated') {

                $this->trackHistory(
                    'Validation',
                    'Status Change',
                    "{$identifier} | {$oldValidationStatus} | Moved from {$currentLocation}",
                    "{$identifier} | {$newValidationStatus} | Remains in {$currentLocation}",
                    $employeeName
                );
            } else {

                if ($request->filled('ProductModuleLoc')) {

                    $newLocation = $request->ProductModuleLoc;
                    $oldDisplay = "{$identifier} | {$oldValidationStatus} | Moved from {$currentLocation}";
                    $newDisplay = "{$identifier} | {$newValidationStatus} | Moved to {$newLocation}";

                    if ($request->notes) {
                        $newDisplay .= " | Note: {$request->notes}";
                    }

                    $this->trackHistory(
                        'Validation',
                        'Status Change & Location',
                        $oldDisplay,
                        $newDisplay,
                        $employeeName
                    );
                } else {

                    $oldDisplay = "{$identifier} | {$oldValidationStatus} | Moved from {$currentLocation}";
                    $newDisplay = "{$identifier} | {$newValidationStatus} | Remains in {$currentLocation}";

                    if ($request->notes) {
                        $newDisplay .= " | Note: {$request->notes}";
                    }

                    $this->trackHistory(
                        'Validation',
                        'Status Change',
                        $oldDisplay,
                        $newDisplay,
                        $employeeName
                    );
                }
            }

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
