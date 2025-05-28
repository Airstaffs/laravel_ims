<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use DateTime;
use DateTimeZone;
class notfoundController extends BasetablesController
{  
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $location = $request->input('location', 'Not Found');
        
        $products = DB::table($this->productTable)
            ->where('ProductModuleLoc', $location)
            ->when($search, function($query) use ($search) {
                return $query->where(function($q) use ($search) {
                    $q->where('AStitle', 'like', "%{$search}%")
                      ->orWhere('serialnumber', 'like', "%{$search}%")
                      ->orWhere('FNSKUviewer', 'like', "%{$search}%")
                      ->orWhere('MSKUviewer', 'like', "%{$search}%")
                      ->orWhere('ASINviewer', 'like', "%{$search}%")
                      ->orWhere('rtcounter', 'like', "%{$search}%");
                });
            })
            ->paginate($perPage);
        
        return response()->json($products);
    }

      public function moveToStockroom(Request $request)
        {
            $id = $request->input('id');

            if (!$id) {
                return response()->json(['success' => false, 'message' => 'No ID provided'], 400);
            }

            $updated = DB::table($this->productTable)
                ->where('ProductID', $id)
                ->update([
                    'ProductModuleLoc' => 'Stockroom',
                    'notfoundDate' => null, // set to NULL
                ]);

            if ($updated) {
                return response()->json(['success' => true]);
            } else {
                return response()->json(['success' => false, 'message' => 'Update failed'], 500);
            }
        }
    
}

?>
