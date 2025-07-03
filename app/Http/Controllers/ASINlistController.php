<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
            $results = $asins->getCollection()->map(function ($item) use ($fnskuDetails) {
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
            return response()->json(Cache::remember('asin_stores', 3600, function () {
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

    public function searchAsin(Request $request)
    {
        $keyword = $request->query('keyword');

        $results = DB::table('tblasin')
            ->select('ASIN', 'internal AS title')
            ->where('ASIN', 'LIKE', "%$keyword%")
            ->orWhere('internal', 'LIKE', "%$keyword%")
            ->limit(15)
            ->get();

        return response()->json($results);
    }

    public function saveMsku(Request $request)
    {
        $request->validate([
            'mskus' => 'required|array',
            'mskus.*.asin' => 'required|string',
            'mskus.*.msku' => 'required|string',
            'mskus.*.condition' => 'required|string',
            'mskus.*.storename' => 'required|string',
        ]);

        $success = [];
        $duplicate = [];
        $failed = [];

        foreach ($request->mskus as $row) {
            $existing = DB::table('tblfnsku')->where('MSKU', $row['msku'])->exists();

            if ($existing) {
                $duplicate[] = $row['msku'];
                continue;
            }

            try {
                DB::table('tblfnsku')->insert([
                    'ASIN' => $row['asin'],
                    'MSKU' => $row['msku'],
                    'grading' => $this->convertConditionToGrading($row['condition']),
                    'storename' => $row['storename'],
                    'insert_date' => now(),
                    'amazon_status' => 'Not Existed',
                    'fnsku_status' => 'available',
                    'LimitStatus' => 'False',
                    'donotreplenish' => 'none',
                    'Units' => 11,
                ]);
                $success[] = $row['msku'];
            } catch (\Exception $e) {
                $failed[] = ['msku' => $row['msku'], 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => $success,
            'duplicates' => $duplicate,
            'failed' => $failed,
            'message' => 'Processed MSKUs with duplicate and error checking.'
        ]);
    }

    private function convertConditionToGrading($condition)
    {
        return match ($condition) {
            'new_new' => 'New',
            'new_open_box' => 'OpenBox',
            'new_oem' => 'OEM',
            'refurbished_refurbished' => 'Refurbished',
            'used_like_new' => 'UsedLikeNew',
            'used_very_good' => 'UsedVeryGood',
            'used_good' => 'UsedGood',
            'used_acceptable' => 'UsedAcceptable',
            'collectible_like_new' => 'CollectibleLikeNew',
            'collectible_very_good' => 'CollectibleVeryGood',
            'collectible_good' => 'CollectibleGood',
            'collectible_acceptable' => 'CollectibleAcceptable',
            'club_club' => 'Club',
            default => 'Unknown',
        };
    }

    public function generateMsku(Request $request)
    {
        $request->validate([
            'asin' => 'required|string',
            'condition' => 'required|string',
        ]);

        $asin = $request->asin;
        $condition = $request->condition;

        $prefixMap = [
            "new_new" => "NN",
            "new_open_box" => "NOB",
            "new_oem" => "NOEM",
            "refurbished_refurbished" => "RR",
            "used_like_new" => "ULN",
            "used_very_good" => "UVG",
            "used_good" => "UG",
            "used_acceptable" => "UA",
            "collectible_like_new" => "CLN",
            "collectible_very_good" => "CVG",
            "collectible_good" => "CG",
            "collectible_acceptable" => "CA",
            "club_club" => "CLUB"
        ];

        $code = $prefixMap[$condition] ?? 'UNK';
        $asinLast4 = substr($asin, -4);

        $attempt = 0;
        $maxAttempts = 30;

        Log::info('Generating MSKU', ['asin' => $asin, 'condition' => $condition, 'code' => $code]);

        do {
            $rand5 = strtoupper(Str::random(5));
            $msku = "{$asinLast4}-{$code}-{$rand5}";
            $exists = DB::table('tblfnsku')->where('MSKU', $msku)->exists();

            Log::debug('MSKU generation attempt', [
                'attempt' => $attempt + 1,
                'generated' => $msku,
                'exists' => $exists
            ]);

            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        if ($attempt >= $maxAttempts) {
            Log::warning('Failed to generate unique MSKU', [
                'asin' => $asin,
                'condition' => $condition,
                'attempts' => $attempt
            ]);

            return response()->json([
                'error' => 'Unable to generate unique MSKU after multiple attempts.'
            ], 422);
        }

        Log::info('Generated unique MSKU', ['msku' => $msku]);

        return response()->json([
            'msku' => $msku,
            'condition' => $condition
        ]);
    }

    public function fetchStores()
    {
        try {
            return response()->json(
                DB::table('tblstores')
                    ->select('storename')
                    ->whereNotNull('storename')
                    ->where('storename', '!=', '')
                    ->distinct()
                    ->orderBy('storename')
                    ->pluck('storename')
            );
        } catch (\Exception $e) {
            Log::error('Error fetching stores from tblstores: ' . $e->getMessage());

            return response()->json([
                'error' => 'An error occurred while fetching store list',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}