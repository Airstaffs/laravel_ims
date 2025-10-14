<?php

namespace App\Http\Controllers;

class DaysSupplyController extends Controller
{
    public function index(Request $req)
    {
        $store = (string) $req->query('store', '');   // optional
        $search = trim((string) $req->query('search', ''));
        $limitDays = (int) $req->query('datalimit', 14);
        $perPage = (int) $req->query('per_page', 25);
        $page = max(1, (int) $req->query('page', 1));
        $hasSearch = $search !== '';

        // Assumptions (will adjust to your real column names after you send schemas):
        // tblasin: asin (PK), parent_asin, internal, amazon_status, buybox_status,
        //          sales7_fba, sales7_fbm, fba_posted_qty, fbm_posted_qty, fba_total_quantity
        // tblproduct: id, asin, title, store_name, module_loc, fbm_available (1/0)
        // tblfnsku: id, asin, fnsku, msku, store_name  (for listing FNSKUs/MSKUs; not needed for DS math)

        // Core idea:
        //   FbmAvailableCount = COUNT(tblproduct where fbm_available=1 AND module_loc='Stockroom' [AND store filter])
        //   TotalQOH          = fba_posted_qty + FbmAvailableCount
        //   Sales7            = sales7_fba + sales7_fbm
        //   DS                = (TotalQOH * 7) / Sales7   (if Sales7 > 0 else 0)

        $base = DB::table('tblasin as a')
            ->leftJoin('tblproduct as p', 'a.asin', '=', 'p.asin')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('a.asin', 'like', "%{$search}%")
                        ->orWhere('p.title', 'like', "%{$search}%");
                });
            })
            ->groupBy('a.asin')
            ->selectRaw('
                a.asin                                     AS ASIN,
                MIN(p.title)                               AS astitle,
                GROUP_CONCAT(DISTINCT p.store_name)        AS stores,
                COALESCE(a.fba_posted_qty,0)               AS FBAposted,
                COALESCE(a.fbm_posted_qty,0)               AS FBMposted,
                COALESCE(a.fba_total_quantity,0)           AS FbaAvailableCount,
                COALESCE(a.sales7_fba,0)                   AS totalUnitsSold_FBA_ds7,
                COALESCE(a.sales7_fbm,0)                   AS totalUnitsSold_FBM_ds7,
                (COALESCE(a.sales7_fba,0)+COALESCE(a.sales7_fbm,0)) AS TotalUnitSold,
                a.buybox_status                            AS BBstatus,
                a.amazon_status                            AS amazon_status,
                MIN(a.parent_asin)                         AS ParentAsin,
                MIN(a.internal)                            AS internal,
                MIN(p.asin)                                AS asin_join_debug
            ')
            // FbmAvailableCount (filtered by store if provided)
            ->selectRaw("
                SUM(CASE
                    WHEN p.fbm_available = 1
                     AND p.module_loc = 'Stockroom'
                     " . ($store ? "AND p.store_name = ?" : "") . "
                    THEN 1 ELSE 0 END
                ) AS FbmAvailableCount
            ", $store ? [$store] : [])
            // TotalQOH = FBAposted + FbmAvailableCount
            ->selectRaw("
                ( COALESCE(a.fba_posted_qty,0)
                  + SUM(CASE
                        WHEN p.fbm_available = 1
                         AND p.module_loc = 'Stockroom'
                         " . ($store ? "AND p.store_name = ?" : "") . "
                        THEN 1 ELSE 0 END)
                ) AS TotalQOH
            ", $store ? [$store] : [])
            // DS formula
            ->selectRaw("
                CASE
                  WHEN (COALESCE(a.sales7_fba,0)+COALESCE(a.sales7_fbm,0)) > 0
                  THEN (
                        COALESCE(a.fba_posted_qty,0)
                        + SUM(CASE
                                WHEN p.fbm_available = 1
                                 AND p.module_loc = 'Stockroom'
                                 " . ($store ? "AND p.store_name = ?" : "") . "
                                THEN 1 ELSE 0 END)
                       ) * 7.0
                       / (COALESCE(a.sales7_fba,0)+COALESCE(a.sales7_fbm,0))
                  ELSE 0
                END AS DS
            ", $store ? [$store] : []);

        // Having filters (same semantics as legacy)
        $base->havingRaw($hasSearch ? 'DS >= 0' : 'DS > 0')
            ->having('DS', '<=', $limitDays)
            ->orderBy('DS', 'asc');

        // Pagination (manual because of groupBy/having)
        $count = DB::query()->fromSub($base, 'x')->count(); // wrap to count
        $rows = DB::query()->fromSub($base, 'x')
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