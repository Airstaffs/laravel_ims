<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DaysSupplyController extends Controller
{
    public function index(Request $req)
    {
        // ---- Query params
        $store = (string) $req->query('store', '');    // exact match to tblfnsku.storename
        $search = trim((string) $req->query('search', ''));
        $limitDays = (int) $req->query('datalimit', 14);   // show DS <= this
        $perPage = max(1, (int) $req->query('per_page', 25));
        $page = max(1, (int) $req->query('page', 1));
        $hasSearch = $search !== '';

        // NEW knobs
        $includeOOS = (bool) $req->query('include_oos', true);       // show OOS with recent sales
        $window = (int) $req->query('window', 7);                 // 7 or 30
        $minSold = (int) $req->query('min_sold', 0);               // hide low/noise
        $sort = (string) $req->query('sort', 'ds_asc');         // ds_asc|ds_desc|sold_desc
        $useOrders = (bool) $req->query('use_orders', false);        // when true, compute sales from tbloutboundorders*

        // pick column names if using tblasin
        $colFbaSold = $window === 30 ? 'a.totalUnitsSold_FBA_ds30' : 'a.totalUnitsSold_FBA_ds7';
        $colFbmSold = $window === 30 ? 'a.totalUnitsSold_FBM_ds30' : 'a.totalUnitsSold_FBM_ds7';

        // helper: raw SQL snippet for sales subquery when useOrders=true
        $soldFbaSub = "(
            SELECT COALESCE(SUM(ii.QuantityShipped),0)
            FROM tbloutboundordersitem ii
            JOIN tbloutboundorders oo
              ON oo.platform_order_id = ii.platform_order_id
            WHERE ii.platform_asin = a.ASIN
              AND ii.FulfillmentChannel = 'FBA'
              AND ii.order_status = 'Shipped'
              AND oo.PurchaseDate >= NOW() - INTERVAL {$window} DAY
        )";
        $soldFbmSub = "(
            SELECT COALESCE(SUM(ii.QuantityShipped),0)
            FROM tbloutboundordersitem ii
            JOIN tbloutboundorders oo
              ON oo.platform_order_id = ii.platform_order_id
            WHERE ii.platform_asin = a.ASIN
              AND ii.FulfillmentChannel = 'FBM'
              AND ii.order_status = 'Shipped'
              AND oo.PurchaseDate >= NOW() - INTERVAL {$window} DAY
        )";

        // choose which sales source to use
        $soldFbaExpr = $useOrders ? $soldFbaSub : "COALESCE(MIN($colFbaSold),0)";
        $soldFbmExpr = $useOrders ? $soldFbmSub : "COALESCE(MIN($colFbmSold),0)";

        // ----- BASE (grouped by ASIN)
        $base = DB::table('tblasin as a')
            ->leftJoin('tblfnsku as f', 'a.ASIN', '=', 'f.ASIN')
            ->leftJoin('tblproduct as p', 'p.FNSKUviewer', '=', 'f.FNSKU')

            // REAL store row filter (not just counting)
            ->when($store !== '', fn($q) => $q->where('f.storename', $store))

            // search
            ->when($hasSearch, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('a.ASIN', 'like', "%{$search}%")
                        ->orWhere('a.amazon_title', 'like', "%{$search}%");
                });
            })

            ->groupBy('a.ASIN')

            // identity
            ->selectRaw("
                a.ASIN AS ASIN,
                COALESCE(NULLIF(MIN(a.amazon_title), ''), MIN(a.internal)) AS astitle,
                MIN(a.internal)      AS internal,
                MIN(a.ParentAsin)    AS ParentAsin,
                MIN(a.CousinASIN)    AS CousinASIN,
                MIN(a.amazon_status) AS amazon_status,
                MIN(a.BuyboxStatus)  AS BBstatus
            ")

            // conveniences
            ->selectRaw("GROUP_CONCAT(DISTINCT f.storename ORDER BY f.storename SEPARATOR ', ') AS stores")
            ->selectRaw("GROUP_CONCAT(DISTINCT f.FNSKU ORDER BY f.FNSKU SEPARATOR ',') AS fnskus")

            // posted & inventory
            ->selectRaw("
                COALESCE(MIN(a.fbm_posted_qty),0)   AS FBMposted,
                COALESCE(MIN(a.fba_posted_qty),0)   AS FBAposted,
                COALESCE(MIN(a.fbaTotalQuantity),0) AS FbaAvailableCount
            ")

            // stockroom FBM count (already constrained by store if provided above)
            ->selectRaw("
                SUM(CASE
                      WHEN p.FbmAvailable = 1
                       AND p.ProductModuleLoc = 'Stockroom'
                      THEN 1 ELSE 0
                    END) AS FbmAvailableCount
            ")

            // TotalQOH + FBA QOH for breakdown
            ->selectRaw("COALESCE(MIN(a.fba_posted_qty),0) AS FbaQoh")
            ->selectRaw("
                (COALESCE(MIN(a.fba_posted_qty),0)
                 + SUM(CASE WHEN p.FbmAvailable = 1 AND p.ProductModuleLoc = 'Stockroom' THEN 1 ELSE 0 END)
                ) AS TotalQOH
            ")

            // SALES (windowed) — choose source
            ->selectRaw("$soldFbaExpr AS totalUnitsSold_FBA_window")
            ->selectRaw("$soldFbmExpr AS totalUnitsSold_FBM_window")
            ->selectRaw("( ($soldFbaExpr) + ($soldFbmExpr) ) AS TotalUnitSold")

            // flags/buckets and DS (computed at this level for HAVING/order)
            ->selectRaw("
                CASE
                  WHEN (COALESCE(MIN(a.fba_posted_qty),0)
                        + SUM(CASE WHEN p.FbmAvailable = 1 AND p.ProductModuleLoc = 'Stockroom' THEN 1 ELSE 0 END)
                       ) = 0
                   AND ( ($soldFbaExpr) + ($soldFbmExpr) ) > 0
                THEN 1 ELSE 0 END AS is_oos
            ")
            ->selectRaw("
                CASE
                  WHEN ( ($soldFbaExpr) + ($soldFbmExpr) ) > 0
                  THEN (
                        COALESCE(MIN(a.fba_posted_qty),0)
                        + SUM(CASE WHEN p.FbmAvailable = 1 AND p.ProductModuleLoc = 'Stockroom' THEN 1 ELSE 0 END)
                       ) * 7.0
                       / ( ($soldFbaExpr) + ($soldFbmExpr) )
                  ELSE 0
                END AS DS
            ");

        // --- filters
        if ($includeOOS) {
            // Show DS in range OR true OOS with recent sales
            $base->havingRaw('(DS > 0 AND DS <= ?) OR (is_oos = 1)', [$limitDays]);
        } else {
            $base->havingRaw($hasSearch ? 'DS >= 0' : 'DS > 0')
                ->having('DS', '<=', $limitDays);
        }

        if ($minSold > 0) {
            $base->having('TotalUnitSold', '>=', $minSold);
        }

        // --- sorting
        switch ($sort) {
            case 'ds_desc':
                $base->orderBy('DS', 'desc');
                break;
            case 'sold_desc':
                $base->orderBy('TotalUnitSold', 'desc');
                break;
            default:
                $base->orderBy('DS', 'asc');
                break;
        }

        // --- page
        $count = DB::query()->fromSub($base, 'q')->count();
        $rows = DB::query()->fromSub($base, 'q')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return response()->json([
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $count,
                'total_pages' => $perPage ? (int) ceil($count / $perPage) : 1,
            ],
            'applied_filters' => [
                'store' => $store ?: null,
                'search' => $search ?: null,
                'datalimit' => $limitDays,
                'window' => $window,
                'include_oos' => $includeOOS,
                'min_sold' => $minSold,
                'sort' => $sort,
                'use_orders' => $useOrders,
            ],
        ]);
    }
}
