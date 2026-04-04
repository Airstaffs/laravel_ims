<?php

namespace App\Http\Controllers;

use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

class CleaningController extends BasetablesController
{
    use TracksHistory;

    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Cleaning');
            $includeImages = $request->boolean('include_images', false);

            Log::info('Cleaning API called', [
                'search' => $search,
                'location' => $location,
                'perPage' => $perPage,
                'includeImages' => $includeImages,
            ]);

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
            Log::error('Cleaning API error', [
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

    public function moveToPackaging(Request $request)
    {
        Log::info('=== MOVE TO PACKAGING FROM CLEANING ===', $request->all());

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

            $this->trackLocationChange(
                'Cleaning',
                "RTC: {$request->rt_counter}",
                $request->current_location,
                'Packaging'
            );

            return response()->json([
                'success' => true,
                'message' => 'Product successfully moved to Packaging',
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in Cleaning moveToPackaging', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to move product to Packaging',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
