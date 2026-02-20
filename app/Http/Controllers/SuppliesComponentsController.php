<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SuppliesComponentsController extends BasetablesController
{
    const CATEGORIES = ['Components', 'Supplies', 'Office Equipment'];

    /**
     * Get paginated items from tblproduct only
     */
    public function index(Request $request)
    {
        try {
            $perPage  = $request->input('per_page', 10);
            $page     = $request->input('page', 1);
            $search   = $request->input('search', '');
            $category = $request->input('category', '');

            $query = DB::table('tblproduct as p')
                ->leftJoin($this->capturedImagesTable . ' as img', 'p.ProductID', '=', 'img.ProductID')
                ->select(
                    'p.ProductID',
                    'p.rtcounter',
                    'p.ProductTitle',
                    'p.quantity',
                    'p.orderdate',
                    'p.datedelivered',
                    'p.ProductModuleLoc',
                    'p.img1', 'p.img2', 'p.img3', 'p.img4', 'p.img5',
                    'img.capturedimg1', 'img.capturedimg2', 'img.capturedimg3',
                    'img.capturedimg4', 'img.capturedimg5', 'img.capturedimg6',
                    'img.capturedimg7', 'img.capturedimg8', 'img.capturedimg9',
                    'img.capturedimg10', 'img.capturedimg11', 'img.capturedimg12',
                    'img.serialimg1', 'img.serialimg2',
                    'img.trackingimg1', 'img.trackingimg2'
                )
                ->whereIn('p.ProductModuleLoc', self::CATEGORIES);

            // Category filter
            if (!empty($category) && in_array($category, self::CATEGORIES)) {
                $query->where('p.ProductModuleLoc', $category);
            }

            // Search filter
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('p.ProductID', 'LIKE', "%{$search}%")
                      ->orWhere('p.ProductTitle', 'LIKE', "%{$search}%")
                      ->orWhere('p.rtcounter', 'LIKE', "%{$search}%");
                });
            }

            $totalCount = $query->count();
            $totalPages = ceil($totalCount / $perPage);

            $rows = $query
                ->orderBy('p.orderdate', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

                $formatted = $rows->map(function ($r) {
                    // Build capturedImages object (same pattern as LabelingController)
                    $capturedImages = [];

                    for ($i = 1; $i <= 12; $i++) {
                        $key = "capturedimg{$i}";
                        if (!empty($r->$key) && $r->$key !== 'NULL') {
                            $capturedImages[$key] = $r->$key;
                        }
                    }

                    foreach (['serialimg1', 'serialimg2', 'trackingimg1', 'trackingimg2'] as $key) {
                        if (!empty($r->$key) && $r->$key !== 'NULL') {
                            $capturedImages[$key] = $r->$key;
                        }
                    }

                    return [
                        'product_id'      => $r->ProductID,
                        'rt_counter'      => $r->rtcounter,
                        'product_title'   => $r->ProductTitle ?? 'N/A',
                        'quantity'        => $r->quantity,
                        'order_date'      => $r->orderdate,
                        'delivered_date'  => $r->datedelivered,   // ← also was missing this field
                        'category'        => $r->ProductModuleLoc,
                        'company'         => 'Airstaffs',           // ← needed for image path construction
                        // Regular thumbnail images
                        'img1'            => $r->img1,
                        'img2'            => $r->img2,
                        'img3'            => $r->img3,
                        'img4'            => $r->img4,
                        'img5'            => $r->img5,
                        // Captured images object (same structure as labeling)
                        'capturedImages'  => !empty($capturedImages) ? $capturedImages : null,
                    ];
                });

            return response()->json([
                'success'      => true,
                'data'         => $formatted,
                'total'        => $totalCount,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $totalPages,
            ]);

        } catch (\Exception $e) {
            Log::error('SuppliesComponents index error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching items', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get count statistics per category
     */
    public function getStats()
    {
        try {
            $byCategory = DB::table('tblproduct')
                ->whereIn('ProductModuleLoc', self::CATEGORIES)
                ->select('ProductModuleLoc', DB::raw('COUNT(*) as count'))
                ->groupBy('ProductModuleLoc')
                ->get()
                ->keyBy('ProductModuleLoc');

            $stats = [
                'total'            => $byCategory->sum('count'),
                'components'       => $byCategory->get('Components')?->count ?? 0,
                'supplies'         => $byCategory->get('Supplies')?->count ?? 0,
                'office_equipment' => $byCategory->get('Office Equipment')?->count ?? 0,
            ];

            return response()->json(['success' => true, 'stats' => $stats]);

        } catch (\Exception $e) {
            Log::error('SuppliesComponents getStats error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching statistics'], 500);
        }
    }

    /**
     * Move a product to Labeling
     */
    public function moveToLabeling(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $productId = (int) $data['product_id'];

        try {
            $product = DB::table('tblproduct')
                ->where('ProductID', $productId)
                ->whereIn('ProductModuleLoc', self::CATEGORIES)
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => "Product {$productId} not found or not in Supplies/Components/Office Equipment.",
                ], 422);
            }

            DB::table('tblproduct')
                ->where('ProductID', $productId)
                ->update([
                    'ProductModuleLoc' => 'Labeling',
                    'materialtype'     => 'Inventory',
                ]);

            Log::info("Product {$productId} moved from {$product->ProductModuleLoc} to Labeling.");

            return response()->json([
                'success' => true,
                'message' => "Product {$productId} moved to Labeling successfully.",
            ]);

        } catch (\Throwable $e) {
            Log::error("moveToLabeling error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}