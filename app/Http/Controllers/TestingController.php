<?php

namespace App\Http\Controllers;

use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;  
use App\Models\ItemCondition;

class TestingController extends BasetablesController
{
    use TracksHistory;

    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Received');

            Log::info('Testing API called', [
                'search' => $search,
                'location' => $location,
                'perPage' => $perPage
            ]);

            $query = DB::table($this->productTable)
                ->where('ProductModuleLoc', $location);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ProductTitle', 'like', "%{$search}%")
                        ->orWhere('rtid', 'like', "%{$search}%")
                        ->orWhere('itemnumber', 'like', "%{$search}%")
                        ->orWhere('trackingnumber', 'like', "%{$search}%")
                        ->orWhere('rtcounter', 'like', "%{$search}%");
                });
            }

            $products = $query->paginate($perPage);

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
                'trace' => $e->getTraceAsString()
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
                'can_add_release' => $hasReceive, // Can only add release if receive exists
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch condition data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save condition checklist
     * ALWAYS creates a NEW record (allows multiple receive/release for returned items)
     */
    public function saveCondition(Request $request)
    {
        $validator = Validator::make($request->all(), [
        'item_number' => 'required|string',
        'product_id' => 'nullable',
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
        'overall_condition' => 'nullable|in:excellent,good,fair,poor',
        'notes' => 'nullable|string|max:1000',
    ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['inspected_by'] = $this->getCurrentUserName(); 
            $data['inspected_at'] = now();

            // Check if trying to add release before receive
            if ($data['condition_type'] === 'release') {
                $hasReceive = ItemCondition::hasReceiveCondition($data['item_number']);
                if (!$hasReceive) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot add release condition without a receive condition first',
                    ], 422);
                }
            }

            // ALWAYS create NEW record - allows tracking history for returned items
            $condition = ItemCondition::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Condition checklist saved successfully',
                'condition' => $condition,
                'score' => $condition->condition_score
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save condition data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get complete condition history for an item
     * Shows all receive and release conditions (for returned items)
     */
    public function getConditionHistory(Request $request, $itemNumber)
    {
        try {
            $conditions = ItemCondition::itemHistory($itemNumber)->get();

            // Group by type for easier display
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
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compare receive vs release conditions
     * Shows what changed during processing
     */
    public function compareConditions(Request $request, $itemNumber)
    {
        try {
            $comparison = ItemCondition::compareConditions($itemNumber);

            if (!$comparison) {
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
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get latest conditions for all items in testing
     * Useful for dashboard/overview
     */
    public function getTestingOverview(Request $request)
    {
        try {
            // Get unique item numbers with their latest conditions
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
                'error' => $e->getMessage()
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
                'message' => 'Condition record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete condition record',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}