<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\tblproduct;

class tblproductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $location = $request->query('location', ''); // Default to 'stockroom' if not provided
        $perPage = $request->query('per_page', 10); // Default to 10 items per page
    
        $query = tblproduct::where('ProductModuleLoc', $location);
    
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ProductTitle', 'like', "%{$search}%")
                    ->orWhere('ASINviewer', 'like', "%{$search}%")
                    ->orWhere('serialnumber', 'like', "%{$search}%")
                    ->orWhere('FNSKUviewer', 'like', "%{$search}%");
            });
        }
    
        // Use dynamic perPage value
        $data = $query->paginate($perPage);
    
        return response()->json([
            'data' => $data->items(),
            'total' => $data->total(),
            'per_page' => $data->perPage(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
        ]);
    }
    
    
}
