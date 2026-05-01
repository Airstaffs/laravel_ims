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

            // ── Base select (always includes eBay fallback images) ──────────────
            $selectColumns = [
                'prod.*',
                // ✅ eBay order images — fallback when no captured images exist
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
                    'img.serialimg1',    'img.serialimg2',
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

                    foreach (['serialimg1', 'serialimg2'] as $key) {
                        if (! empty($product->$key)) {
                            $capturedImages->$key = $product->$key;
                        }
                        unset($product->$key);
                    }

                    $product->capturedImages = $capturedImages;

                    // ── Thumbnail priority ───────────────────────────────────
                    // 1st: captured image  (path: /images/product_images/{company}/)
                    // 2nd: eBay image      (path: /images/thumbnails/)
                    if (! empty($capturedImages->capturedimg1)) {
                        $product->img1 = $capturedImages->capturedimg1;
                        $product->img1_source = 'captured';
                    } elseif (! empty($product->img1)) {
                        $product->img1_source = 'ebay';
                    } else {
                        $product->img1_source = null;
                    }

                } else {
                    $product->capturedImages = (object) [];
                    $product->img1_source = ! empty($product->img1) ? 'ebay' : null;
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

    public function moveToPackaging(Request $request)
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
                    'ProductModuleLoc' => 'Packaging',
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                ]);

            $employeeName = auth()->user()->username ?? 'System';
            $identifier = "RT#{$request->rt_counter}".
                          (! empty($product->ProductTitle) ? " - {$product->ProductTitle}" : '');

            $this->trackLocationChange(
                'Validation', // from module
                $identifier,
                $request->current_location,
                'Packaging', // to module
                $employeeName
            );

            return response()->json([
                'success' => true,
                'message' => 'Product successfully moved to Packaging',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error moving product to Packaging: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to move product to Packaging',
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
            $employeeName = auth()->user()->username ?? 'System';

            $oldValidationStatus = $product->validation_status ?? 'pending';
            $newValidationStatus = $request->status;

            $currentLocation = $product->ProductModuleLoc ?? 'Unknown';

            /*
            |--------------------------------------------------------------------------
            | Decide destination based on validation status
            |--------------------------------------------------------------------------
            | validated = Packaging
            | invalid   = Labeling
            */
            $newLocation = $request->status === 'validated'
                ? 'Packaging'
                : 'Labeling';

            $updateData = [
                'validation_status' => $request->status,
                'ProductModuleLoc' => $newLocation,
                'lastDateUpdate' => $now,
            ];

            // ── Update product table ───────────────────────────────────────────
            DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update($updateData);

            // ── Save to validation log table ───────────────────────────────────
            DB::table('tblvalidationlogs')->updateOrInsert(
                ['rtcounter' => (string) $request->rt_counter],
                [
                    'validation_status' => $request->status,
                    'validated_by' => $employeeName,
                    'date_validated' => $now,
                    'notes' => $request->notes ?? null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            // ── Track history ──────────────────────────────────────────────────
            $identifier = "RT#{$request->rt_counter}".
                (! empty($product->ProductTitle) ? " - {$product->ProductTitle}" : '');

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

            /*
            |--------------------------------------------------------------------------
            | Optional but recommended:
            | Also track the location movement in your location tracking system.
            |--------------------------------------------------------------------------
            */
            if ($currentLocation !== $newLocation) {
                $this->trackLocationChange(
                    'Validation',
                    $identifier,
                    $currentLocation,
                    $newLocation,
                    $employeeName
                );
            }

            Log::info('Product validation status updated', [
                'product_id' => $request->product_id,
                'rt_counter' => $request->rt_counter,
                'status' => $request->status,
                'validated_by' => $userId,
                'notes' => $request->notes,
                'old_location' => $currentLocation,
                'new_location' => $newLocation,
            ]);

            return response()->json([
                'success' => true,
                'message' => $request->status === 'validated'
                    ? 'Product validated successfully and moved to Packaging'
                    : 'Product marked as invalid successfully and moved to Labeling',
                'location' => $newLocation,
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
