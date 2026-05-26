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
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Packaging');
            $includeImages = $request->boolean('include_images', false);

            Log::info('Packaging API called', compact('search', 'location', 'perPage', 'includeImages'));

            // ── Base select ─────────────────────────────────────────────────
            $selectColumns = [
                'prod.*',
                'ebayimgs.img1',  'ebayimgs.img2',  'ebayimgs.img3',
                'ebayimgs.img4',  'ebayimgs.img5',  'ebayimgs.img6',
                'ebayimgs.img7',  'ebayimgs.img8',  'ebayimgs.img9',
                'ebayimgs.img10', 'ebayimgs.img11', 'ebayimgs.img12',
                'ebayimgs.img13', 'ebayimgs.img14', 'ebayimgs.img15',
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
                // ── Packaging work log ──────────────────────────────────────
                'pwl.packed_by',
                'pwl.date_packed',
                'pwl.mark_done as packaging_done',
                DB::raw('pwl.category_values as packaging_category_values'),
            ];

            if ($includeImages) {
                $selectColumns = array_merge($selectColumns, [
                    'img.capturedimg1',  'img.capturedimg2',  'img.capturedimg3',
                    'img.capturedimg4',  'img.capturedimg5',  'img.capturedimg6',
                    'img.capturedimg7',  'img.capturedimg8',  'img.capturedimg9',
                    'img.capturedimg10', 'img.capturedimg11', 'img.capturedimg12',
                    'img.serialimg1',    'img.serialimg2',
                ]);
            }

            // ── Build query ─────────────────────────────────────────────────
            $productsQuery = DB::table($this->productTable.' as prod')
                ->leftJoin($this->fnskuTable.' as fnsku', 'prod.MSKUviewer', '=', 'fnsku.MSKU')
                ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->leftJoin('tblEbayOrderImages as ebayimgs', 'prod.ProductID', '=', 'ebayimgs.ProductID')
                ->leftJoin('tblpackagingworklogs as pwl',
                    DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                    '=',
                    DB::raw('CONVERT(pwl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                );

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

            // ── Search ──────────────────────────────────────────────────────
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
            Log::info('Products fetched successfully', ['count' => $products->count()]);

            // ── Transform ───────────────────────────────────────────────────
            $products->getCollection()->transform(function ($product) use ($includeImages) {
                if (empty($product->FNSKU) && ! empty($product->FNSKUviewer)) {
                    $product->FNSKU = $product->FNSKUviewer;
                }
                if (empty($product->MSKU) && ! empty($product->MSKUviewer)) {
                    $product->MSKU = $product->MSKUviewer;
                }

                $product->company = $this->company;

                if ($includeImages) {
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

    public function saveWorkLog(Request $request)
    {
        $request->validate([
            'rtcounter' => 'required',
            'product_id' => 'required',
        ]);

        try {
            $existing = DB::table('tblpackagingworklogs')
                ->where('rtcounter', $request->rtcounter)
                ->first();

            $data = [
                'asin' => $request->asin,
                'ProductID' => $request->product_id,
                'packed_by' => $request->packed_by,
                'date_packed' => $request->date_packed ? now()->parse($request->date_packed) : now(),
                'mark_done' => $request->boolean('mark_done') ? 1 : 0,
                'category_values' => $request->category_values,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('tblpackagingworklogs')
                    ->where('rtcounter', $request->rtcounter)
                    ->update($data);
            } else {
                $data['rtcounter'] = $request->rtcounter;
                $data['created_at'] = now();
                DB::table('tblpackagingworklogs')->insert($data);
            }

            if ($request->boolean('mark_done')) {
                DB::table('tblproduct')
                    ->where('ProductID', $request->product_id)
                    ->update([
                        'ProductModuleLoc' => 'Stockroom',
                        'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => $request->boolean('mark_done')
                    ? 'Packaging complete. Item moved to Stockroom.'
                    : 'Packaging work log saved.',
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving packaging work log', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save packaging work log.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getWorkLog(Request $request, $rtcounter)
    {
        try {
            $log = DB::table('tblpackagingworklogs')
                ->where('rtcounter', $rtcounter)
                ->first();

            if (!$log) {
                return response()->json(['success' => true, 'data' => null]);
            }

            $log->category_values = json_decode($log->category_values, true);

            return response()->json(['success' => true, 'data' => $log]);

        } catch (\Exception $e) {
            Log::error('getWorkLog error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
