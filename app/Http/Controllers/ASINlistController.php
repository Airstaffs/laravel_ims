<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ASINlistController extends BasetablesController
{
    /**
     * Display a listing of ASINs with their FNSKU data
     */
    public function index(Request $request)
    {
        try {
            $perPage = min($request->input('per_page', 15), 100);
            $search = $request->input('search', '');
            $store = $request->input('store', '');
            $page = $request->input('page', 1);
            
            // Build the main query - ASIN with FNSKU aggregated data
            $asinQuery = DB::table($this->asinTable . ' as asin')
                ->select([
                    'asin.ASIN',
                    'asin.internal as AStitle',
                    'asin.metakeyword',
                    'asin.EAN',
                    'asin.UPC',
                    'asin.ParentAsin',
                    'asin.CousinASIN',
                    'asin.UpgradeASIN',
                    'asin.GrandASIN',
                    DB::raw('COUNT(fnsku.FNSKU) as fnsku_count')
                ])
                ->leftJoin($this->fnskuTable . ' as fnsku', 'asin.ASIN', '=', 'fnsku.ASIN')
                ->where('asin.ASIN', '!=', '')
                ->whereNotNull('asin.ASIN');
            
            // Apply search filters
            if (!empty($search)) {
                $asinQuery->where(function ($query) use ($search) {
                    $query->where('asin.ASIN', 'like', "%{$search}%")
                          ->orWhere('asin.internal', 'like', "%{$search}%")
                          ->orWhere('fnsku.FNSKU', 'like', "%{$search}%");
                });
            }
            
            // Apply store filter
            if (!empty($store)) {
                $asinQuery->where('fnsku.storename', $store);
            }
            
            // Group by ASIN and having clause to ensure we have at least one FNSKU
            $asinQuery->groupBy('asin.ASIN', 'asin.internal', 'asin.metakeyword', 'asin.EAN', 'asin.UPC', 'asin.ParentAsin', 'asin.CousinASIN', 'asin.UpgradeASIN', 'asin.GrandASIN')
                     ->having('fnsku_count', '>', 0);
            
            // Order by ASIN
            $asinQuery->orderBy('asin.ASIN', 'asc');
            
            // Get paginated results
            $asins = $asinQuery->paginate($perPage);
            
            // Get ASINs for batch loading FNSKU details
            $asinList = $asins->getCollection()->pluck('ASIN')->toArray();
            
            if (empty($asinList)) {
                $result = $asins->toArray();
                $result['data'] = [];
                return response()->json($result);
            }
            
            // Batch load detailed FNSKU data for all ASINs
            $fnskuDetails = DB::table($this->fnskuTable)
                ->select([
                    'ASIN',
                    'FNSKU',
                    'MSKU',
                    'storename',
                    'Units'
                ])
                ->whereIn('ASIN', $asinList)
                ->orderBy('FNSKU', 'asc')
                ->get()
                ->groupBy('ASIN');
            
            // Process results with batch-loaded FNSKU data
            $results = $asins->getCollection()->map(function($item) use ($fnskuDetails) {
                if (empty($item->ASIN)) {
                    return null;
                }
                
                // Add FNSKU details from batch-loaded data
                $item->fnskus = isset($fnskuDetails[$item->ASIN]) 
                    ? $fnskuDetails[$item->ASIN]->toArray() 
                    : [];
                
                // Ensure numeric values are properly typed
                $item->fnsku_count = (int) $item->fnsku_count;
                
                return $item;
            })->filter(); // Remove null items
            
            // Update the collection
            $asins->setCollection($results);
            $result = $asins->toArray();
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Error in ASINlistController@index: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'error' => 'An error occurred while retrieving ASIN data',
                'message' => $e->getMessage(),
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0
            ], 500);
        }
    }

    /**
     * Get list of store names for the dropdown
     */
    public function getStores()
    {
        try {
            return response()->json(Cache::remember('asin_stores', 3600, function() {
                return DB::table($this->fnskuTable)
                    ->select('storename')
                    ->distinct()
                    ->whereNotNull('storename')
                    ->where('storename', '!=', '')
                    ->orderBy('storename')
                    ->pluck('storename');
            }));
        } catch (\Exception $e) {
            Log::error('Error getting stores: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'An error occurred while retrieving store list',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}