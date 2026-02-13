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
        $perPage = $request->input('per_page', 10);

        $products = DB::table('tblreconciliation')
            ->paginate($perPage);

        return response()->json($products);
    }

}
