<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TestTable;

class TestTableController extends Controller
{
    public function index(Request $request)
    {
        // Check if the search query is present
        $search = $request->query('search');

        if ($search) {
            // Search in relevant columns
            $data = TestTable::where('fnsku', 'like', "%{$search}%")
                ->orWhere('msku', 'like', "%{$search}%")
                ->orWhere('asin', 'like', "%{$search}%")
                ->orWhere('productname', 'like', "%{$search}%")
                ->get();
        } else {
            // Return all data if no search query is provided
            $data = TestTable::all();
        }

        return response()->json($data);
    }
}
