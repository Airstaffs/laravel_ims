<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryStatisticsController extends BasetablesController
{
    private const ORDERS_RECEIVED = ['Orders', 'Received'];

    private const LABELING_ONWARDS = [
        'Labeling', 'Testing', 'Cleaning', 'Packing',
        'Validation', 'Production Area', 'Stockroom', 'Shipment',
        'Returnlist', 'Soldlist'
    ];

    private const ALL_MODULES = [
        'Orders', 'Received', 'Labeling', 'Testing', 'Cleaning',
        'Packing', 'Validation', 'Production Area', 'Stockroom',
        'Shipment', 'Returnlist', 'Soldlist',
    ];

    /**
     * Extract base FNSKU from prefixed FNSKU (e.g., C12X001ABC123 -> X001ABC123)
     */
    private function extractBaseFnsku($fnsku)
    {
        if (empty($fnsku)) return $fnsku;
        if (preg_match('/^C\d+([A-Z].+)$/', $fnsku, $matches)) {
            return $matches[1];
        }
        return $fnsku;
    }

    /**
     * Batch-load FNSKU->ASIN map for given FNSKUviewer values
     * Returns: ['baseFnsku' => 'ASIN', ...]
     */
    private function buildFnskuAsinMap(array $rawFnskus): array
    {
        $baseToRaw = [];
        foreach ($rawFnskus as $raw) {
            if (empty($raw)) continue;
            $base = $this->extractBaseFnsku($raw);
            if (!empty($base)) {
                $baseToRaw[$base] = $raw;
            }
        }

        if (empty($baseToRaw)) return [];

        $baseFnskus = array_keys($baseToRaw);
        $rows = DB::table($this->fnskuTable)
            ->whereIn('FNSKU', $baseFnskus)
            ->whereNotNull('ASIN')
            ->where('ASIN', '!=', '')
            ->pluck('ASIN', 'FNSKU'); // ['fnsku' => 'asin']

        // Map raw FNSKU -> ASIN (via base)
        $map = [];
        foreach ($baseToRaw as $base => $raw) {
            $map[$raw] = $rows[$base] ?? null;
        }

        return $map; // null value means no ASIN found
    }

    /**
     * Batch-load ASIN->title map
     * Returns: ['ASIN' => 'title', ...]
     */
    private function buildAsinTitleMap(array $asins): array
    {
        if (empty($asins)) return [];

        return DB::table($this->asinTable)
            ->whereIn('ASIN', $asins)
            ->get(['ASIN', 'system_title', 'internal'])
            ->mapWithKeys(function ($row) {
                $title = trim($row->system_title ?? '') ?: trim($row->internal ?? '') ?: 'No Title';
                return [$row->ASIN => $title];
            })
            ->toArray();
    }

    /**
     * GET /api/inventory-statistics/summary
     */
    public function getSummary(Request $request)
    {
        try {
            $cacheKey = 'inventory_statistics_summary_v2';

            $result = Cache::remember($cacheKey, now()->addMinutes(5), function () {
                return $this->buildSummary();
            });

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('InventoryStatisticsController@getSummary', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error'   => 'An error occurred while fetching statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build the full summary payload (called inside cache closure)
     */
    private function buildSummary(): array
    {
        // ── 1. Module distribution (single query) ────────────────────────────
        $moduleDistribution = DB::table($this->productTable)
            ->select([
                'ProductModuleLoc as name',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(COALESCE(quantity, 1)) as total_quantity'),
            ])
            ->whereIn('ProductModuleLoc', self::ALL_MODULES)
            ->groupBy('ProductModuleLoc')
            ->orderByDesc('count')
            ->get();

        $totalItems    = $moduleDistribution->sum('count');
        $totalQuantity = $moduleDistribution->sum('total_quantity');

        // ── 2. Unique ASINs (batch, no loops) ────────────────────────────────
        // Orders & Received → ASINviewer
        $ordersReceivedAsins = DB::table($this->productTable)
            ->whereIn('ProductModuleLoc', self::ORDERS_RECEIVED)
            ->whereNotNull('ASINviewer')
            ->where('ASINviewer', '!=', '')
            ->distinct()
            ->pluck('ASINviewer');

        // Labeling onwards → FNSKUviewer batch resolve
        $rawFnskus = DB::table($this->productTable)
            ->whereIn('ProductModuleLoc', self::LABELING_ONWARDS)
            ->whereNotNull('FNSKUviewer')
            ->where('FNSKUviewer', '!=', '')
            ->distinct()
            ->pluck('FNSKUviewer')
            ->toArray();

        $fnskuAsinMap = $this->buildFnskuAsinMap($rawFnskus);
        $labelingAsins = collect(array_values($fnskuAsinMap))->filter()->unique();

        $uniqueAsins = $ordersReceivedAsins->merge($labelingAsins)->unique()->count();

        // ── 3. Unlabeled count ────────────────────────────────────────────────
        $unlabeledOrdersReceived = DB::table($this->productTable)
            ->whereIn('ProductModuleLoc', self::ORDERS_RECEIVED)
            ->where(function ($q) {
                $q->whereNull('ASINviewer')->orWhere('ASINviewer', '');
            })
            ->count();

        // Labeling onwards unlabeled: FNSKUviewer is empty OR maps to no ASIN
        $allLabelingFnskus = DB::table($this->productTable)
            ->whereIn('ProductModuleLoc', self::LABELING_ONWARDS)
            ->pluck('FNSKUviewer');

        $labelingFnskuAsinMap = $this->buildFnskuAsinMap(
            $allLabelingFnskus->filter()->unique()->toArray()
        );

        $unlabeledOthers = $allLabelingFnskus->filter(function ($raw) use ($labelingFnskuAsinMap) {
            if (empty($raw)) return true;
            return empty($labelingFnskuAsinMap[$raw] ?? null);
        })->count();

        $unlabeledItems = $unlabeledOrdersReceived + $unlabeledOthers;

        // ── 4. ASIN details grouped ───────────────────────────────────────────
        $asinDetails = $this->getAsinDetailsGrouped($labelingFnskuAsinMap);

        // ── 5. Top sold / returned ────────────────────────────────────────────
        $soldItems   = $this->getTopItemsByModule('Soldlist', 10);
        $returnItems = $this->getTopItemsByModule('Returnlist', 10);

        return [
            'summary' => [
                'total_items'      => (int) $totalItems,
                'unique_asins'     => (int) $uniqueAsins,
                'unlabeled_items'  => (int) $unlabeledItems,
                'total_quantity'   => (int) $totalQuantity,
            ],
            'module_distribution' => $moduleDistribution,
            'asin_details'        => $asinDetails,
            'sold_items'          => $soldItems,
            'return_items'        => $returnItems,
        ];
    }

    /**
     * Build ASIN details grouped by ASIN with module distribution.
     * Accepts a pre-built $fnskuAsinMap to avoid re-querying.
     */
    private function getAsinDetailsGrouped(array $fnskuAsinMapForLabeling = []): array
    {
        // ── Orders & Received (SQL-level join) ────────────────────────────────
        $ordersData = DB::table($this->productTable . ' as prod')
            ->leftJoin($this->asinTable . ' as a', 'prod.ASINviewer', '=', 'a.ASIN')
            ->select([
                DB::raw('COALESCE(NULLIF(prod.ASINviewer,""), "UNLABELED") as asin'),
                DB::raw('COALESCE(
                    NULLIF(TRIM(a.system_title),""),
                    NULLIF(TRIM(a.internal),""),
                    NULLIF(TRIM(prod.ProductTitle),""),
                    "No Title"
                ) as title'),
                'prod.ProductModuleLoc as module',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(COALESCE(prod.quantity,1)) as quantity'),
            ])
            ->whereIn('prod.ProductModuleLoc', self::ORDERS_RECEIVED)
            ->groupBy('prod.ASINviewer', 'a.system_title', 'a.internal', 'prod.ProductTitle', 'prod.ProductModuleLoc')
            ->get();

        // ── Labeling onwards (batch-resolved, no per-row queries) ─────────────
        $labelingProducts = DB::table($this->productTable)
            ->select(['FNSKUviewer', 'ProductTitle', 'ProductModuleLoc', 'quantity'])
            ->whereIn('ProductModuleLoc', self::LABELING_ONWARDS)
            ->get();

        // Build ASIN map if not provided
        if (empty($fnskuAsinMapForLabeling)) {
            $rawFnskus = $labelingProducts->pluck('FNSKUviewer')->filter()->unique()->toArray();
            $fnskuAsinMapForLabeling = $this->buildFnskuAsinMap($rawFnskus);
        }

        // Get all unique ASINs to batch-load titles
        $asinIds = collect(array_values($fnskuAsinMapForLabeling))->filter()->unique()->toArray();
        $asinTitleMap = $this->buildAsinTitleMap($asinIds);

        // Group labeling products
        $labelingGrouped = [];
        foreach ($labelingProducts as $p) {
            $asin = $fnskuAsinMapForLabeling[$p->FNSKUviewer] ?? null;

            if ($asin) {
                $title = $asinTitleMap[$asin] ?? ($p->ProductTitle ?: 'No Title');
            } else {
                $asin  = 'UNLABELED';
                $title = $p->ProductTitle ?: 'No Title';
            }

            $key = $asin . '|' . $p->ProductModuleLoc;
            if (!isset($labelingGrouped[$key])) {
                $labelingGrouped[$key] = (object)[
                    'asin'     => $asin,
                    'title'    => $title,
                    'module'   => $p->ProductModuleLoc,
                    'count'    => 0,
                    'quantity' => 0,
                ];
            }
            $labelingGrouped[$key]->count++;
            $labelingGrouped[$key]->quantity += ($p->quantity ?? 1);
        }

        // ── Merge & group by ASIN ─────────────────────────────────────────────
        $allData    = $ordersData->merge(collect(array_values($labelingGrouped)));
        $asinGroups = [];

        foreach ($allData as $row) {
            $asin = $row->asin;
            if (!isset($asinGroups[$asin])) {
                $asinGroups[$asin] = [
                    'asin'           => $asin === 'UNLABELED' ? null : $asin,
                    'title'          => $row->title,
                    'total_items'    => 0,
                    'total_quantity' => 0,
                    'modules'        => [],
                ];
            }
            $asinGroups[$asin]['total_items']    += $row->count;
            $asinGroups[$asin]['total_quantity'] += $row->quantity;
            $asinGroups[$asin]['modules'][$row->module] = (int) $row->count;
        }

        $result = array_values($asinGroups);
        usort($result, fn($a, $b) => $b['total_items'] - $a['total_items']);

        return $result;
    }

    /**
     * Get top N items by module grouped by ASIN
     */
    private function getTopItemsByModule(string $module, int $limit = 10)
    {
        if (in_array($module, self::ORDERS_RECEIVED)) {
            return DB::table($this->productTable . ' as prod')
                ->leftJoin($this->asinTable . ' as a', 'prod.ASINviewer', '=', 'a.ASIN')
                ->select([
                    DB::raw('COALESCE(NULLIF(prod.ASINviewer,""), "UNLABELED") as asin'),
                    DB::raw('COALESCE(
                        NULLIF(TRIM(a.system_title),""),
                        NULLIF(TRIM(a.internal),""),
                        "No Title"
                    ) as title'),
                    DB::raw('COUNT(*) as count'),
                ])
                ->where('prod.ProductModuleLoc', $module)
                ->groupBy('prod.ASINviewer', 'a.system_title', 'a.internal')
                ->orderByDesc('count')
                ->limit($limit)
                ->get()
                ->map(fn($item) => [
                    'asin'  => $item->asin === 'UNLABELED' ? null : $item->asin,
                    'title' => $item->title,
                    'count' => (int) $item->count,
                ]);
        }

        // Labeling onwards — batch load
        $products = DB::table($this->productTable)
            ->select(['FNSKUviewer', 'ProductTitle'])
            ->where('ProductModuleLoc', $module)
            ->get();

        $rawFnskus    = $products->pluck('FNSKUviewer')->filter()->unique()->toArray();
        $fnskuAsinMap = $this->buildFnskuAsinMap($rawFnskus);

        $asinIds      = collect(array_values($fnskuAsinMap))->filter()->unique()->toArray();
        $asinTitleMap = $this->buildAsinTitleMap($asinIds);

        $counts = [];
        foreach ($products as $p) {
            $asin = $fnskuAsinMap[$p->FNSKUviewer] ?? null;

            if ($asin) {
                $title = $asinTitleMap[$asin] ?? 'No Title';
            } else {
                $asin  = 'UNLABELED';
                $title = 'No Title';
            }

            if (!isset($counts[$asin])) {
                $counts[$asin] = ['asin' => $asin === 'UNLABELED' ? null : $asin, 'title' => $title, 'count' => 0];
            }
            $counts[$asin]['count']++;
        }

        return collect(array_values($counts))
            ->sortByDesc('count')
            ->take($limit)
            ->values();
    }

    /**
     * GET /api/inventory-statistics/asin-details?asin=XXXXX
     */
    public function getAsinDetails(Request $request)
    {
        try {
            $asin = $request->input('asin');
            $isUnlabeled = ($asin === 'UNLABELED' || empty($asin));

            Log::info('Fetching ASIN details', ['asin' => $asin]);

            $selectFields = [
                'ProductID', 'rtcounter', 'ProductTitle',
                'ProductModuleLoc', 'quantity', 'serialnumber', 'warehouselocation',
            ];

            if ($isUnlabeled) {
                // Orders & Received with no ASINviewer
                $ordersItems = DB::table($this->productTable)
                    ->select($selectFields)
                    ->whereIn('ProductModuleLoc', self::ORDERS_RECEIVED)
                    ->where(fn($q) => $q->whereNull('ASINviewer')->orWhere('ASINviewer', ''))
                    ->get();

                // Labeling onwards — batch resolve, filter unlabeled
                $otherProducts = DB::table($this->productTable)
                    ->select(array_merge($selectFields, ['FNSKUviewer']))
                    ->whereIn('ProductModuleLoc', self::LABELING_ONWARDS)
                    ->get();

                $rawFnskus    = $otherProducts->pluck('FNSKUviewer')->filter()->unique()->toArray();
                $fnskuAsinMap = $this->buildFnskuAsinMap($rawFnskus);

                $otherItems = $otherProducts->filter(function ($p) use ($fnskuAsinMap) {
                    if (empty($p->FNSKUviewer)) return true;
                    return empty($fnskuAsinMap[$p->FNSKUviewer] ?? null);
                });

                $items = $ordersItems->merge($otherItems);

            } else {
                // Orders & Received with matching ASIN
                $ordersItems = DB::table($this->productTable)
                    ->select($selectFields)
                    ->whereIn('ProductModuleLoc', self::ORDERS_RECEIVED)
                    ->where('ASINviewer', $asin)
                    ->get();

                // Labeling onwards — find all FNSKUs that map to this ASIN
                $matchingFnskus = DB::table($this->fnskuTable)
                    ->where('ASIN', $asin)
                    ->pluck('FNSKU')
                    ->toArray();

                if (empty($matchingFnskus)) {
                    $otherItems = collect([]);
                } else {
                    // Build all possible FNSKUviewer values (base + prefixed C\d+ variants)
                    // by querying only the FNSKUviewer values that exist for these base FNSKUs
                    $allFnskuViewers = DB::table($this->productTable)
                        ->whereIn('ProductModuleLoc', self::LABELING_ONWARDS)
                        ->whereNotNull('FNSKUviewer')
                        ->where('FNSKUviewer', '!=', '')
                        ->distinct()
                        ->pluck('FNSKUviewer')
                        ->filter(function ($raw) use ($matchingFnskus) {
                            return in_array($this->extractBaseFnsku($raw), $matchingFnskus);
                        })
                        ->values()
                        ->toArray();

                    if (empty($allFnskuViewers)) {
                        $otherItems = collect([]);
                    } else {
                        // Now query only matching rows directly — no full table scan in PHP
                        $otherItems = DB::table($this->productTable)
                            ->select($selectFields)
                            ->whereIn('ProductModuleLoc', self::LABELING_ONWARDS)
                            ->whereIn('FNSKUviewer', $allFnskuViewers)
                            ->get();
                    }
                }

                $items = $ordersItems->merge($otherItems);
            }

            return response()->json([
                'items' => $items->values(),
                'count' => $items->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('InventoryStatisticsController@getAsinDetails', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error'   => 'An error occurred while fetching ASIN details',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/inventory-statistics/clear-cache
     * Call this from your save/update routes whenever inventory changes
     */
    public function clearCache()
    {
        Cache::forget('inventory_statistics_summary_v2');
        return response()->json(['message' => 'Cache cleared']);
    }
}