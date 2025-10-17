<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DaysSupplyController extends Controller
{
    public function index(Request $req)
    {
        $store = (string) $req->query('store', '');     // matches tblfnsku.storename (optional)
        $search = trim((string) $req->query('search', ''));// ASIN or title search
        $limitDays = (int) $req->query('datalimit', 14);  // DS <= this
        $perPage = max(1, (int) $req->query('per_page', 25));
        $page = max(1, (int) $req->query('page', 1));
        $hasSearch = $search !== '';

        // ASIN -> FNSKU (tblfnsku) -> Products (tblproduct via FNSKUviewer)
        $base = DB::
            table('tblasin as a')
            ->leftJoin('tblfnsku as f', 'a.ASIN', '=', 'f.ASIN')
            ->leftJoin('tblproduct as p', 'p.FNSKUviewer', '=', 'f.FNSKU')
            ->when($hasSearch, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('a.ASIN', 'like', "%{$search}%")
                        ->orWhere('a.amazon_title', 'like', "%{$search}%");
                });
            })
            ->groupBy('a.ASIN')
            // Identity/display fields (pull a title from tblasin)
            ->selectRaw('
                a.ASIN AS ASIN,
                COALESCE(NULLIF(MIN(a.amazon_title), \'\'), MIN(a.internal)) AS astitle,
                MIN(a.internal)      AS internal,
                MIN(a.ParentAsin)    AS ParentAsin,
                MIN(a.CousinASIN)    AS CousinASIN,
                MIN(a.amazon_status) AS amazon_status,
                MIN(a.BuyboxStatus)  AS BBstatus
            ')
            // Stores & FNSKUs for convenience in UI
            ->selectRaw('GROUP_CONCAT(DISTINCT f.storename ORDER BY f.storename SEPARATOR \', \') AS stores')
            ->selectRaw('GROUP_CONCAT(DISTINCT f.FNSKU ORDER BY f.FNSKU SEPARATOR \',\') AS fnskus')
            // Posted & sales from tblasin
            ->selectRaw('
                COALESCE(MIN(a.fbm_posted_qty),0)         AS FBMposted,
                COALESCE(MIN(a.fba_posted_qty),0)         AS FBAposted,
                COALESCE(MIN(a.fbaTotalQuantity),0)       AS FbaAvailableCount,
                COALESCE(MIN(a.totalUnitsSold_FBA_ds7),0) AS totalUnitsSold_FBA_ds7,
                COALESCE(MIN(a.totalUnitsSold_FBM_ds7),0) AS totalUnitsSold_FBM_ds7,
                (COALESCE(MIN(a.totalUnitsSold_FBA_ds7),0)
                 + COALESCE(MIN(a.totalUnitsSold_FBM_ds7),0)) AS TotalUnitSold
            ')
            // FBM-in-Stockroom count from tblproduct, optionally filtered by store (from tblfnsku)
            ->selectRaw("
                SUM(CASE
                      WHEN p.FbmAvailable = 1
                       AND p.ProductModuleLoc = 'Stockroom'
                       " . ($store ? "AND f.storename = ?" : "") . "
                      THEN 1 ELSE 0
                    END) AS FbmAvailableCount
            ", $store ? [$store] : [])
            // TotalQOH = FBAposted + FBM-in-Stockroom
            ->selectRaw("
                ( COALESCE(MIN(a.fba_posted_qty),0)
                  + SUM(CASE
                          WHEN p.FbmAvailable = 1
                           AND p.ProductModuleLoc = 'Stockroom'
                           " . ($store ? "AND f.storename = ?" : "") . "
                          THEN 1 ELSE 0
                        END)
                ) AS TotalQOH
            ", $store ? [$store] : [])
            // DS = TotalQOH * 7 / (7-day sales) when sales > 0 else 0
            ->selectRaw("
                CASE
                  WHEN (COALESCE(MIN(a.totalUnitsSold_FBA_ds7),0)
                        + COALESCE(MIN(a.totalUnitsSold_FBM_ds7),0)) > 0
                  THEN (
                        COALESCE(MIN(a.fba_posted_qty),0)
                        + SUM(CASE
                                WHEN p.FbmAvailable = 1
                                 AND p.ProductModuleLoc = 'Stockroom'
                                 " . ($store ? "AND f.storename = ?" : "") . "
                                THEN 1 ELSE 0
                              END)
                       ) * 7.0
                       / (COALESCE(MIN(a.totalUnitsSold_FBA_ds7),0)
                          + COALESCE(MIN(a.totalUnitsSold_FBM_ds7),0))
                  ELSE 0
                END AS DS
            ", $store ? [$store] : []);

        // Legacy behavior: DS > 0 (or >= 0 when searching), DS <= limitDays, ascending
        $base->havingRaw($hasSearch ? 'DS >= 0' : 'DS > 0')
            ->having('DS', '<=', $limitDays)
            ->orderBy('DS', 'asc');

        // Count + page (wrap grouped query)
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
            ],
        ]);
    }
}