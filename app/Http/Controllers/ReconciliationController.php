<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconciliationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');

            $query = DB::table('tblreconciliation')
                ->orderBy('datedelivered', 'desc');

            // Apply search filter if provided
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('ProductTitle', 'like', "%{$search}%")
                        ->orWhere('rtcounter', 'like', "%{$search}%")
                        ->orWhere('trackingnumber', 'like', "%{$search}%")
                        ->orWhere('itemnumber', 'like', "%{$search}%");
                });
            }

            $products = $query->paginate($perPage);

            Log::info('Reconciliation products fetched successfully', ['count' => $products->count()]);

            return response()->json($products);

        } catch (\Exception $e) {
            Log::error('Error in ReconciliationController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching reconciliation products',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}