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
            // Define table names directly
            $productTable = 'tblproduct'; // Your actual product table name
            $fnskuTable = 'tblfnsku'; // Your FNSKU table name
            $asinTable = 'tblasin'; // Your ASIN table name
            $company = 'Airstaffs'; // Your company name

            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Testing');
            $includeImages = $request->boolean('include_images', false);

            Log::info('Testing API called', [
                'search' => $search,
                'location' => $location,
                'perPage' => $perPage,
                'includeImages' => $includeImages,
            ]);

            // UPDATED: Build query with proper joins to include ASIN and metakeyword in search
            $query = DB::table($productTable.' as prod')
                ->leftJoin($fnskuTable.' as fnsku', function ($join) {
                    $join->on(DB::raw("CASE 
                    WHEN prod.FNSKUviewer REGEXP '^C[0-9]+' 
                    THEN SUBSTRING(prod.FNSKUviewer, LOCATE(REGEXP_REPLACE(prod.FNSKUviewer, '^C[0-9]+', ''), prod.FNSKUviewer))
                    ELSE prod.FNSKUviewer 
                END"), '=', 'fnsku.FNSKU');
                })
                ->leftJoin($asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->select([
                    'prod.*',
                    'fnsku.ASIN',
                    'fnsku.MSKU',
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
                ])
                ->where('prod.ProductModuleLoc', $location);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('prod.ProductTitle', 'like', "%{$search}%")
                        ->orWhere('prod.rtid', 'like', "%{$search}%")
                        ->orWhere('prod.itemnumber', 'like', "%{$search}%")
                        ->orWhere('prod.trackingnumber', 'like', "%{$search}%")
                        ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                        ->orWhere('prod.serialnumber', 'like', "%{$search}%")
                        ->orWhere('prod.PCN', 'like', "%{$search}%")
                        ->orWhere('prod.RPN', 'like', "%{$search}%")
                        ->orWhere('prod.PRD', 'like', "%{$search}%")
                        ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                        // Add FNSKU table search
                        ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                        ->orWhere('fnsku.MSKU', 'like', "%{$search}%")
                        // Add ASIN table search
                        ->orWhere('asin.internal', 'like', "%{$search}%")
                        ->orWhere('asin.system_title', 'like', "%{$search}%")
                        ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                });
            }

            $products = $query->paginate($perPage);

            Log::info('Products fetched successfully', ['count' => $products->count()]);

            // Transform products to add company field
            $products->getCollection()->transform(function ($product) use ($company) {
                $product->company = $company;

                return $product;
            });

            // If images are requested, check for captured image files
            if ($includeImages) {
                try {
                    $publicPath = public_path('images/product_images/'.$company);

                    Log::info('Checking for captured images in path', ['path' => $publicPath]);

                    // Add captured images data to each product
                    $products->getCollection()->transform(function ($product) use ($publicPath) {
                        $capturedImages = (object) [];

                        // Check for up to 12 captured images
                        for ($i = 1; $i <= 12; $i++) {
                            $filename = $product->ProductID.'_img'.$i.'.jpg';
                            $filePath = $publicPath.'/'.$filename;

                            if (file_exists($filePath)) {
                                $capturedImages->{"capturedimg{$i}"} = $filename;
                            }
                        }

                        // Check for serial images
                        $serialImg1 = $product->ProductID.'_serialimg1.jpg';
                        $serialImg2 = $product->ProductID.'_serialimg2.jpg';

                        if (file_exists($publicPath.'/'.$serialImg1)) {
                            $capturedImages->serialimg1 = $serialImg1;
                        }
                        if (file_exists($publicPath.'/'.$serialImg2)) {
                            $capturedImages->serialimg2 = $serialImg2;
                        }

                        $product->capturedImages = $capturedImages;

                        // Set img1 directly for the main thumbnail display if capturedimg1 exists
                        if (! empty($capturedImages->capturedimg1)) {
                            $product->img1 = $capturedImages->capturedimg1;
                        }

                        Log::info('Added captured images to product', [
                            'ProductID' => $product->ProductID,
                            'capturedImagesCount' => count((array) $capturedImages),
                            'hasImg1' => ! empty($product->img1),
                        ]);

                        return $product;
                    });
                } catch (\Exception $e) {
                    Log::error('Error checking for image files', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
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

            return response()->json([
                'data' => $products->items(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]);

        } catch (\Exception $e) {
            Log::error('Testing API error', [
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
