<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReconciliationController extends Controller
{
    /**
     * Debug / audit view of reconciliation records
     */
    public function index(Request $request)
    {
        $rows = DB::table('tblreconciliation')
            ->whereNotNull('trackingnumber')
            ->orderByRaw('COALESCE(stockroom_insert_date, DateCreated) DESC')
            ->limit(300)
            ->get();

        $normalized = [];

        foreach ($rows as $row) {
            $normalized[] = [
                'product_id'      => $row->ProductID,
                'tracking_number' => $row->trackingnumber,
                'image_path'      => $row->img1, // ✅ tracking image ONLY
                'created_at'      => $row->stockroom_insert_date ?? $row->DateCreated,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $normalized,
        ]);
    }

}
