<?php

namespace App\Http\Controllers;

use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;  
use App\Models\ItemCondition;

class CleaningController extends BasetablesController
{
    use TracksHistory;

    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Cleaning');

            Log::info('Cleaning API called', [
                'search' => $search,
                'location' => $location,
                'perPage' => $perPage,
                'productTable' => $this->productTable,
                'company' => $this->company
            ]);

            $query = DB::table($this->productTable)
                ->where('ProductModuleLoc', $location);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ProductTitle', 'like', "%{$search}%")
                        ->orWhere('rtid', 'like', "%{$search}%")
                        ->orWhere('itemnumber', 'like', "%{$search}%")
                        ->orWhere('trackingnumber', 'like', "%{$search}%")
                        ->orWhere('rtcounter', 'like', "%{$search}%");
                });
            }

            $products = $query->paginate($perPage);

            return response()->json([
                'data' => $products->items(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]);

        } catch (\Exception $e) {
            Log::error('Cleaning API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch products',
                'message' => $e->getMessage(),
                'data' => [],
                'total' => 0,
                'per_page' => 10,
                'current_page' => 1,
                'last_page' => 1,
            ], 500);
        }
    }

    private function getCurrentUserName()
    {
        $user = Auth::user();
        return $user ? ($user->username ?? $user->name ?? 'Unknown') : 'Unknown';
    }
}