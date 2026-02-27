<?php

namespace App\Http\Controllers;

use App\Models\ItemCondition;
use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TestingController extends BasetablesController
{
    use TracksHistory;


public function index(Request $request)
{
    try {
        $perPage       = $request->input('per_page', 10);
        $search        = $request->input('search', '');
        $location      = $request->input('location', 'Testing');
        $includeImages = $request->boolean('include_images', false);

        Log::info('Testing API called', [
            'search'        => $search,
            'location'      => $location,
            'perPage'       => $perPage,
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
            ->leftJoin($this->fnskuTable.' as fnsku',     'prod.MSKUviewer', '=', 'fnsku.MSKU')
            ->leftJoin($this->asinTable.' as asin',       'fnsku.ASIN',      '=', 'asin.ASIN')
            // ✅ Always join — needed for thumbnail fallback even without include_images
            ->leftJoin('tblEbayOrderImages as ebayimgs',  'prod.ProductID',  '=', 'ebayimgs.ProductID');

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
        if (!empty($search)) {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('prod.serialnumber',   'like', "%{$search}%")
                  ->orWhere('prod.ProductTitle',  'like', "%{$search}%")
                  ->orWhere('prod.PCN',           'like', "%{$search}%")
                  ->orWhere('prod.RPN',           'like', "%{$search}%")
                  ->orWhere('prod.PRD',           'like', "%{$search}%")
                  ->orWhere('prod.FNSKUviewer',   'like', "%{$search}%")
                  ->orWhere('prod.MSKUviewer',    'like', "%{$search}%")
                  ->orWhere('prod.trackingnumber','like', "%{$search}%")
                  ->orWhere('prod.rtcounter',     'like', "%{$search}%")
                  ->orWhere('fnsku.ASIN',         'like', "%{$search}%")
                  ->orWhere('fnsku.MSKU',         'like', "%{$search}%")
                  ->orWhere('fnsku.FNSKU',        'like', "%{$search}%")
                  ->orWhere('asin.internal',      'like', "%{$search}%")
                  ->orWhere('asin.system_title',  'like', "%{$search}%")
                  ->orWhere('asin.metakeyword',   'like', "%{$search}%");
            });
        }

        $products = $productsQuery->paginate($perPage);
        Log::info('Products fetched successfully with joins', ['count' => $products->count()]);

        // ── Transform ───────────────────────────────────────────────────────
        $products->getCollection()->transform(function ($product) use ($includeImages) {

            if (empty($product->FNSKU) && !empty($product->FNSKUviewer)) {
                $product->FNSKU = $product->FNSKUviewer;
            }

            if (empty($product->MSKU) && !empty($product->MSKUviewer)) {
                $product->MSKU = $product->MSKUviewer;
            }

            $product->company = $this->company;

            if ($includeImages) {
                // ── Build capturedImages object ──────────────────────────
                $capturedImages = (object) [];

                for ($i = 1; $i <= 12; $i++) {
                    $key = "capturedimg{$i}";
                    if (!empty($product->$key)) {
                        $capturedImages->$key = $product->$key;
                    }
                    unset($product->$key);
                }

                foreach (['serialimg1', 'serialimg2'] as $key) {
                    if (!empty($product->$key)) {
                        $capturedImages->$key = $product->$key;
                    }
                    unset($product->$key);
                }

                $product->capturedImages = $capturedImages;

                // ── Thumbnail priority ───────────────────────────────────
                // 1st: captured image  (path: /images/product_images/{company}/)
                // 2nd: eBay image      (path: /images/thumbnails/)
                if (!empty($capturedImages->capturedimg1)) {
                    $product->img1        = $capturedImages->capturedimg1;
                    $product->img1_source = 'captured';
                } elseif (!empty($product->img1)) {
                    $product->img1_source = 'ebay';
                } else {
                    $product->img1_source = null;
                }

            } else {
                $product->capturedImages = (object) [];
                $product->img1_source    = !empty($product->img1) ? 'ebay' : null;
            }

            return $product;
        });

        return response()->json($products);

    } catch (\Exception $e) {
        Log::error('Testing API error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'error'        => 'Failed to fetch products',
            'message'      => $e->getMessage(),
            'data'         => [],
            'total'        => 0,
            'per_page'     => 10,
            'current_page' => 1,
            'last_page'    => 1,
        ], 500);
    }
}

    private function getCurrentUserName()
    {
        $user = Auth::user();

        return $user ? ($user->username ?? $user->name ?? 'Unknown') : 'Unknown';
    }

    public function getCondition(Request $request, $itemNumber)
    {
        try {
            $conditionType = $request->input('type', 'receive');

            // Get the LATEST condition of this type for this item
            $condition = ItemCondition::where('item_number', $itemNumber)
                ->where('condition_type', $conditionType)
                ->latest('created_at')
                ->first();

            // Check if item has both receive and release conditions
            $hasReceive = ItemCondition::hasReceiveCondition($itemNumber);
            $hasRelease = ItemCondition::hasReleaseCondition($itemNumber);

            return response()->json([
                'success' => true,
                'condition' => $condition,
                'has_existing' => $condition !== null,
                'has_receive' => $hasReceive,
                'has_release' => $hasRelease,
                'can_add_release' => $hasReceive,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch condition data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save condition checklist (removed overall_condition)
     */
    public function saveCondition(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_number' => 'required|string',
            'product_id' => 'required|string',
            'condition_type' => 'required|in:receive,release',
            'physical_damage' => 'nullable|boolean',
            'scratches' => 'nullable|boolean',
            'dents' => 'nullable|boolean',
            'cracks' => 'nullable|boolean',
            'original_packaging' => 'nullable|boolean',
            'packaging_damaged' => 'nullable|boolean',
            'missing_accessories' => 'nullable|boolean',
            'powers_on' => 'nullable|boolean',
            'all_functions_work' => 'nullable|boolean',
            'connectivity_tested' => 'nullable|boolean',
            'display_condition' => 'nullable|boolean',
            'manual_included' => 'nullable|boolean',
            'cables_included' => 'nullable|boolean',
            'warranty_card' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['inspected_by'] = $this->getCurrentUserName();
            $data['inspected_at'] = now();

            // Check if trying to add release before receive
            if ($data['condition_type'] === 'release') {
                $hasReceive = ItemCondition::where('item_number', $data['item_number'])
                    ->where('product_id', $data['product_id'])
                    ->where('condition_type', 'receive')
                    ->exists();

                if (! $hasReceive) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot add release condition without a receive condition first',
                    ], 422);
                }
            }

            // UPDATE OR CREATE based on item_number + product_id + condition_type
            $condition = ItemCondition::updateOrCreate(
                [
                    'item_number' => $data['item_number'],
                    'product_id' => $data['product_id'],
                    'condition_type' => $data['condition_type'],
                ],
                $data
            );

            $wasRecentlyCreated = $condition->wasRecentlyCreated;

            return response()->json([
                'success' => true,
                'message' => $wasRecentlyCreated
                    ? 'Condition checklist created successfully'
                    : 'Condition checklist updated successfully',
                'condition' => $condition,
                'score' => $condition->condition_score,
            ]);
        } catch (\Exception $e) {
            Log::error('Save condition error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save condition data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Move item from Testing to Cleaning & Prepping module
     * Updates ProductModuleLoc in tblproduct
     */
    public function moveToCleaning(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_number' => 'required|string',
            'product_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $itemNumber = $request->input('item_number');
            $productId = $request->input('product_id');

            // Verify item exists and is in Testing module
            $item = DB::table($this->productTable)
                ->where('itemnumber', $itemNumber)
                ->where('ProductID', $productId)
                ->first();

            if (! $item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            if ($item->ProductModuleLoc !== 'Testing') {
                return response()->json([
                    'success' => false,
                    'message' => 'Item is not in Testing module',
                ], 422);
            }

            // Check if receive condition exists
            $hasReceiveCondition = ItemCondition::where('item_number', $itemNumber)
                ->where('product_id', $productId)
                ->where('condition_type', 'receive')
                ->exists();

            if (! $hasReceiveCondition) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot move item without completing receive condition checklist',
                ], 422);
            }

            // Update ProductModuleLoc to Cleaning
            DB::table($this->productTable)
                ->where('itemnumber', $itemNumber)
                ->where('ProductID', $productId)
                ->update([
                    'ProductModuleLoc' => 'Cleaning',
                    'lastDateUpdate' => now(),
                ]);

            // Log the movement in history if TracksHistory trait is available
            if (method_exists($this, 'logHistory')) {
                $this->logHistory(
                    $itemNumber,
                    'module_change',
                    'Testing',
                    'Cleaning',
                    'Item moved to Cleaning & Prepping module after testing completion'
                );
            }

            Log::info('Item moved to Cleaning', [
                'item_number' => $itemNumber,
                'product_id' => $productId,
                'moved_by' => $this->getCurrentUserName(),
                'timestamp' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Item successfully moved to Cleaning & Prepping module',
                'item' => [
                    'item_number' => $itemNumber,
                    'product_id' => $productId,
                    'new_location' => 'Cleaning',
                    'moved_at' => now()->toDateTimeString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Move to Cleaning error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to move item to Cleaning module',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get complete condition history for an item
     */
    public function getConditionHistory(Request $request, $itemNumber)
    {
        try {
            $conditions = ItemCondition::itemHistory($itemNumber)->get();

            $grouped = [
                'receive' => $conditions->where('condition_type', 'receive')->values(),
                'release' => $conditions->where('condition_type', 'release')->values(),
                'total_inspections' => $conditions->count(),
            ];

            return response()->json([
                'success' => true,
                'history' => $conditions,
                'grouped' => $grouped,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch condition history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare receive vs release conditions
     */
    public function compareConditions(Request $request, $itemNumber)
    {
        try {
            $comparison = ItemCondition::compareConditions($itemNumber);

            if (! $comparison) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item does not have both receive and release conditions',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'comparison' => $comparison,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to compare conditions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get latest conditions for all items in testing
     */
    public function getTestingOverview(Request $request)
    {
        try {
            $items = ItemCondition::select('item_number', 'product_id')
                ->distinct()
                ->get()
                ->map(function ($item) {
                    $receive = ItemCondition::latestReceive($item->item_number)->first();
                    $release = ItemCondition::latestRelease($item->item_number)->first();

                    return [
                        'item_number' => $item->item_number,
                        'product_id' => $item->product_id,
                        'has_receive' => $receive !== null,
                        'has_release' => $release !== null,
                        'receive_score' => $receive ? $receive->condition_score : null,
                        'release_score' => $release ? $release->condition_score : null,
                        'latest_inspection' => $release ? $release->inspected_at : ($receive ? $receive->inspected_at : null),
                    ];
                });

            return response()->json([
                'success' => true,
                'items' => $items,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch testing overview',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a specific condition record
     */
    public function deleteCondition(Request $request, $id)
    {
        try {
            $condition = ItemCondition::findOrFail($id);
            $condition->delete();

            return response()->json([
                'success' => true,
                'message' => 'Condition record deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete condition record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
