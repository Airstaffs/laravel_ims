<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryStatisticsController extends BasetablesController
{
    /**
     * Extract base FNSKU from prefixed FNSKU (e.g., C12X001ABC123 -> X001ABC123)
     */
    private function extractBaseFnsku($fnsku)
    {
        if (empty($fnsku)) {
            return $fnsku;
        }

        // Check if it starts with C followed by digits
        if (preg_match('/^C\d+([A-Z].+)$/', $fnsku, $matches)) {
            return $matches[1]; // Return the part after C and digits
        }

        return $fnsku; // Return as-is if not prefixed
    }

    /**
     * Get comprehensive inventory statistics
     */
    public function getSummary(Request $request)
    {
        try {
            Log::info('Fetching inventory statistics summary');

            // Define all modules to track
            $modules = [
                'Orders',
                'Received',
                'Labeling',
                'Testing',
                'Cleaning',
                'Packing',
                'Validation',
                'Production Area',
                'Stockroom',
                'Shipment',
                'Returnlist',
                'Soldlist',
            ];

            // Get module distribution with quantities
            $moduleDistribution = DB::table($this->productTable . ' as prod')
                ->select([
                    'prod.ProductModuleLoc as name',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(COALESCE(prod.quantity, 1)) as total_quantity')
                ])
                ->whereIn('prod.ProductModuleLoc', $modules)
                ->groupBy('prod.ProductModuleLoc')
                ->orderByDesc('count')
                ->get();

            // Calculate summary statistics
            $totalItems = $moduleDistribution->sum('count');
            $totalQuantity = $moduleDistribution->sum('total_quantity');

            // Get unique ASINs count
            // For Orders & Received: use ASINviewer directly
            $ordersReceivedAsins = DB::table($this->productTable)
                ->whereIn('ProductModuleLoc', ['Orders', 'Received'])
                ->whereNotNull('ASINviewer')
                ->where('ASINviewer', '!=', '')
                ->distinct()
                ->pluck('ASINviewer');

            // For Labeling onwards: use FNSKUviewer -> manually extract base -> join with fnsku table
            $labelingOnwardsProducts = DB::table($this->productTable)
                ->select('FNSKUviewer')
                ->whereIn('ProductModuleLoc', [
                    'Labeling', 'Testing', 'Cleaning', 'Packing',
                    'Validation', 'Production Area', 'Stockroom', 'Shipment',
                    'Returnlist', 'Soldlist'
                ])
                ->whereNotNull('FNSKUviewer')
                ->where('FNSKUviewer', '!=', '')
                ->distinct()
                ->get();

            // Extract base FNSKUs and get their ASINs
            $baseFnskus = [];
            foreach ($labelingOnwardsProducts as $product) {
                $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                if (!empty($baseFnsku)) {
                    $baseFnskus[] = $baseFnsku;
                }
            }
            $baseFnskus = array_unique($baseFnskus);

            $labelingOnwardsAsins = collect([]);
            if (!empty($baseFnskus)) {
                $labelingOnwardsAsins = DB::table($this->fnskuTable)
                    ->whereIn('FNSKU', $baseFnskus)
                    ->whereNotNull('ASIN')
                    ->where('ASIN', '!=', '')
                    ->distinct()
                    ->pluck('ASIN');
            }

            $allAsins = $ordersReceivedAsins->merge($labelingOnwardsAsins)->unique();
            $uniqueAsins = $allAsins->count();

            // Count unlabeled items (no ASIN)
            $unlabeledOrdersReceived = DB::table($this->productTable)
                ->whereIn('ProductModuleLoc', ['Orders', 'Received'])
                ->where(function ($q) {
                    $q->whereNull('ASINviewer')
                      ->orWhere('ASINviewer', '');
                })
                ->count();

            // For labeling onwards, count items where base FNSKU has no ASIN
            $labelingOnwardsAllProducts = DB::table($this->productTable)
                ->select('FNSKUviewer')
                ->whereIn('ProductModuleLoc', [
                    'Labeling', 'Testing', 'Cleaning', 'Packing',
                    'Validation', 'Production Area', 'Stockroom', 'Shipment',
                    'Returnlist', 'Soldlist'
                ])
                ->get();

            $unlabeledOthers = 0;
            foreach ($labelingOnwardsAllProducts as $product) {
                if (empty($product->FNSKUviewer)) {
                    $unlabeledOthers++;
                    continue;
                }

                $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                $asinRecord = DB::table($this->fnskuTable)
                    ->where('FNSKU', $baseFnsku)
                    ->first();

                if (!$asinRecord || empty($asinRecord->ASIN)) {
                    $unlabeledOthers++;
                }
            }

            $unlabeledItems = $unlabeledOrdersReceived + $unlabeledOthers;

            // Get ASIN details grouped by ASIN
            $asinDetails = $this->getAsinDetailsGrouped($modules);

            // Get top sold items
            $soldItems = $this->getTopItemsByModule('Soldlist', 10);

            // Get top returned items
            $returnItems = $this->getTopItemsByModule('Returnlist', 10);

            Log::info('Statistics summary compiled', [
                'total_items' => $totalItems,
                'unique_asins' => $uniqueAsins,
                'unlabeled_items' => $unlabeledItems,
            ]);

            return response()->json([
                'summary' => [
                    'total_items' => (int) $totalItems,
                    'unique_asins' => (int) $uniqueAsins,
                    'unlabeled_items' => (int) $unlabeledItems,
                    'total_quantity' => (int) $totalQuantity,
                ],
                'module_distribution' => $moduleDistribution,
                'asin_details' => $asinDetails,
                'sold_items' => $soldItems,
                'return_items' => $returnItems,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in InventoryStatisticsController@getSummary', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ASIN details with module distribution (internal helper)
     */
    private function getAsinDetailsGrouped($modules)
    {
        // Get Orders & Received data with ASINviewer
        $ordersReceivedData = DB::table($this->productTable . ' as prod')
            ->leftJoin($this->asinTable . ' as asin', 'prod.ASINviewer', '=', 'asin.ASIN')
            ->select([
                DB::raw('COALESCE(prod.ASINviewer, "UNLABELED") as asin'),
                DB::raw('COALESCE(
                    NULLIF(TRIM(asin.system_title), ""), 
                    NULLIF(TRIM(asin.internal), ""),
                    NULLIF(TRIM(prod.ProductTitle), ""),
                    "No Title"
                ) as title'),
                'prod.ProductModuleLoc as module',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(COALESCE(prod.quantity, 1)) as quantity')
            ])
            ->whereIn('prod.ProductModuleLoc', ['Orders', 'Received'])
            ->groupBy('prod.ASINviewer', 'asin.system_title', 'asin.internal', 'prod.ProductTitle', 'prod.ProductModuleLoc')
            ->get();

        // Get Labeling onwards data - process manually
        $labelingOnwardsProducts = DB::table($this->productTable)
            ->select([
                'FNSKUviewer',
                'ProductTitle',
                'ProductModuleLoc',
                'quantity'
            ])
            ->whereIn('ProductModuleLoc', [
                'Labeling', 'Testing', 'Cleaning', 'Packing',
                'Validation', 'Production Area', 'Stockroom', 'Shipment',
                'Returnlist', 'Soldlist'
            ])
            ->get();

        // Group by base FNSKU and get ASIN
        $labelingGrouped = [];
        foreach ($labelingOnwardsProducts as $product) {
            $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
            
            if (empty($baseFnsku)) {
                $asin = 'UNLABELED';
                $title = $product->ProductTitle ?: 'No Title';
            } else {
                $fnskuRecord = DB::table($this->fnskuTable)->where('FNSKU', $baseFnsku)->first();
                $asin = $fnskuRecord->ASIN ?? 'UNLABELED';
                
                if ($asin !== 'UNLABELED') {
                    $asinRecord = DB::table($this->asinTable)->where('ASIN', $asin)->first();
                    $title = $asinRecord->system_title ?? $asinRecord->internal ?? $product->ProductTitle ?? 'No Title';
                } else {
                    $title = $product->ProductTitle ?: 'No Title';
                }
            }

            $key = $asin . '|' . $product->ProductModuleLoc;
            if (!isset($labelingGrouped[$key])) {
                $labelingGrouped[$key] = (object) [
                    'asin' => $asin,
                    'title' => $title,
                    'module' => $product->ProductModuleLoc,
                    'count' => 0,
                    'quantity' => 0
                ];
            }

            $labelingGrouped[$key]->count++;
            $labelingGrouped[$key]->quantity += ($product->quantity ?? 1);
        }

        $labelingOnwardsData = collect(array_values($labelingGrouped));

        // Merge all data
        $allData = $ordersReceivedData->merge($labelingOnwardsData);

        // Group by ASIN
        $asinGroups = [];
        foreach ($allData as $row) {
            $asin = $row->asin;
            
            if (!isset($asinGroups[$asin])) {
                $asinGroups[$asin] = [
                    'asin' => $asin === 'UNLABELED' ? null : $asin,
                    'title' => $row->title,
                    'total_items' => 0,
                    'total_quantity' => 0,
                    'modules' => [],
                ];
            }

            $asinGroups[$asin]['total_items'] += $row->count;
            $asinGroups[$asin]['total_quantity'] += $row->quantity;
            $asinGroups[$asin]['modules'][$row->module] = (int) $row->count;
        }

        // Sort by total_items descending
        $asinDetails = array_values($asinGroups);
        usort($asinDetails, function ($a, $b) {
            return $b['total_items'] - $a['total_items'];
        });

        return $asinDetails;
    }

    /**
     * Get top items by module (for Soldlist and Returnlist)
     */
    private function getTopItemsByModule($module, $limit = 10)
    {
        if (in_array($module, ['Orders', 'Received'])) {
            // For Orders & Received, use ASINviewer
            $items = DB::table($this->productTable . ' as prod')
                ->leftJoin($this->asinTable . ' as asin', 'prod.ASINviewer', '=', 'asin.ASIN')
                ->select([
                    DB::raw('COALESCE(prod.ASINviewer, "UNLABELED") as asin'),
                    DB::raw('COALESCE(
                        NULLIF(TRIM(asin.system_title), ""), 
                        NULLIF(TRIM(asin.internal), ""),
                        "No Title"
                    ) as title'),
                    DB::raw('COUNT(*) as count')
                ])
                ->where('prod.ProductModuleLoc', $module)
                ->groupBy('prod.ASINviewer', 'asin.system_title', 'asin.internal')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();

            return $items->map(function ($item) {
                return [
                    'asin' => $item->asin === 'UNLABELED' ? null : $item->asin,
                    'title' => $item->title,
                    'count' => (int) $item->count,
                ];
            });
        } else {
            // For Labeling onwards, manually process FNSKUs
            $products = DB::table($this->productTable)
                ->select('FNSKUviewer', 'ProductTitle')
                ->where('ProductModuleLoc', $module)
                ->get();

            $asinCounts = [];
            foreach ($products as $product) {
                $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                
                if (empty($baseFnsku)) {
                    $asin = 'UNLABELED';
                    $title = 'No Title';
                } else {
                    $fnskuRecord = DB::table($this->fnskuTable)->where('FNSKU', $baseFnsku)->first();
                    $asin = $fnskuRecord->ASIN ?? 'UNLABELED';
                    
                    if ($asin !== 'UNLABELED') {
                        $asinRecord = DB::table($this->asinTable)->where('ASIN', $asin)->first();
                        $title = $asinRecord->system_title ?? $asinRecord->internal ?? 'No Title';
                    } else {
                        $title = 'No Title';
                    }
                }

                if (!isset($asinCounts[$asin])) {
                    $asinCounts[$asin] = [
                        'asin' => $asin === 'UNLABELED' ? null : $asin,
                        'title' => $title,
                        'count' => 0
                    ];
                }
                $asinCounts[$asin]['count']++;
            }

            // Sort and limit
            $asinCounts = collect(array_values($asinCounts))
                ->sortByDesc('count')
                ->take($limit)
                ->values();

            return $asinCounts;
        }
    }

    /**
     * Get detailed items for a specific ASIN
     */
    public function getAsinDetails(Request $request)
    {
        try {
            $asin = $request->input('asin');

            Log::info('Fetching ASIN details', ['asin' => $asin]);

            if ($asin === 'UNLABELED' || empty($asin)) {
                // Get unlabeled items from Orders & Received
                $ordersReceivedItems = DB::table($this->productTable)
                    ->select([
                        'ProductID',
                        'rtcounter',
                        'ProductTitle',
                        'ProductModuleLoc',
                        'quantity',
                        'serialnumber',
                        'warehouselocation'
                    ])
                    ->whereIn('ProductModuleLoc', ['Orders', 'Received'])
                    ->where(function ($q) {
                        $q->whereNull('ASINviewer')
                          ->orWhere('ASINviewer', '');
                    })
                    ->get();

                // Get unlabeled items from other modules
                $otherProducts = DB::table($this->productTable)
                    ->select([
                        'ProductID',
                        'rtcounter',
                        'ProductTitle',
                        'ProductModuleLoc',
                        'quantity',
                        'serialnumber',
                        'warehouselocation',
                        'FNSKUviewer'
                    ])
                    ->whereNotIn('ProductModuleLoc', ['Orders', 'Received'])
                    ->get();

                $otherItems = collect([]);
                foreach ($otherProducts as $product) {
                    $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                    
                    if (empty($baseFnsku)) {
                        $otherItems->push($product);
                        continue;
                    }

                    $fnskuRecord = DB::table($this->fnskuTable)->where('FNSKU', $baseFnsku)->first();
                    if (!$fnskuRecord || empty($fnskuRecord->ASIN)) {
                        $otherItems->push($product);
                    }
                }

                $items = $ordersReceivedItems->merge($otherItems);
            } else {
                // Get items with specific ASIN from Orders & Received
                $ordersReceivedItems = DB::table($this->productTable)
                    ->select([
                        'ProductID',
                        'rtcounter',
                        'ProductTitle',
                        'ProductModuleLoc',
                        'quantity',
                        'serialnumber',
                        'warehouselocation'
                    ])
                    ->whereIn('ProductModuleLoc', ['Orders', 'Received'])
                    ->where('ASINviewer', $asin)
                    ->get();

                // Get items with specific ASIN from other modules
                $otherProducts = DB::table($this->productTable)
                    ->select([
                        'ProductID',
                        'rtcounter',
                        'ProductTitle',
                        'ProductModuleLoc',
                        'quantity',
                        'serialnumber',
                        'warehouselocation',
                        'FNSKUviewer'
                    ])
                    ->whereNotIn('ProductModuleLoc', ['Orders', 'Received'])
                    ->get();

                $otherItems = collect([]);
                foreach ($otherProducts as $product) {
                    $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                    
                    if (empty($baseFnsku)) {
                        continue;
                    }

                    $fnskuRecord = DB::table($this->fnskuTable)->where('FNSKU', $baseFnsku)->first();
                    if ($fnskuRecord && $fnskuRecord->ASIN === $asin) {
                        $otherItems->push($product);
                    }
                }

                $items = $ordersReceivedItems->merge($otherItems);
            }

            return response()->json([
                'items' => $items,
                'count' => $items->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getAsinDetails', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching ASIN details',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}