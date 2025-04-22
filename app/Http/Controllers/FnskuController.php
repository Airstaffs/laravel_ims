<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon; // Make sure this is imported
use App\Models\tblproduct;

class FnskuController extends BasetablesController 
{
    public function index(Request $request)
    {
        $search = $request->query('search');
    
        // Initialize the query
        $fnskuTable = DB::table($this->fnskuTable);
    
        // Apply search filters if search parameter exists
        if ($search) {
            $fnskuTable->where(function ($q) use ($search) {
                $q->where('astitle', 'like', "%{$search}%")
                    ->orWhere('ASIN', 'like', "%{$search}%")
                    ->orWhere('FNSKU', 'like', "%{$search}%");
            });
        }
    
        // Laravel pagination
        $data = $fnskuTable->paginate(10); // 10 items per page
    
        return response()->json([
            'data' => $data->items(),
            'total' => $data->total(),
            'per_page' => $data->perPage(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
        ]);
    }

    
    public function getFnskuList()
    {
        try {
            $fnskuList = DB::table($this->fnskuTable)
                ->select('FNSKU', 'grading', 'MSKU', 'ASIN', 'astitle', 'fnsku_status')
                ->where('fnsku_status', 'available')
                ->orderBy('FNSKU')
                ->get();

            return response()->json($fnskuList);
        } catch (\Exception $e) {
            Log::error('Error fetching FNSKU list: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch FNSKU list',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function insertFnsku(Request $request)
    {
        try {
            $request->validate([
                'fnsku' => 'required|string|unique:' . $this->fnskuTable . ',FNSKU',
                'asin' => 'required|string',
                'astitle' => 'required|string',
                'grading' => 'required|string',
                'msku' => 'nullable|string',
                'storename' => 'nullable|string',
            ]);

            // Insert new FNSKU
            DB::table($this->fnskuTable)->insert([
                'FNSKU' => $request->fnsku,
                'MSKU' => $request->msku,
                'ASIN' => $request->asin,
                'grading' => $request->grading,
                'astitle' => $request->astitle,
                'storename' => $request->storename,
                'fnsku_status' => 'available',
                'insert_date' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FNSKU added successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding FNSKU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add FNSKU: ' . $e->getMessage()
            ], 500);
        }
    }


    public function updateFnsku(Request $request) {
        \Log::info('Received update request:', $request->all());
        try {
            $request->validate([
                'product_id' => 'required|integer',
                'fnsku' => 'required|string',
                'msku' => 'required|string',
                'asin' => 'required|string',
                'grading' => 'required|string',
                'astitle' => 'required|string',
            ]);

            // Begin transaction
            DB::beginTransaction();

            // Update the product
            $product = DB::table($this->productTable)->where('ProductID', $request->product_id)->first();
            if (!$product) {
                throw new \Exception('Product not found');
            }
            
            // Update the product using update method since we're using Query Builder
            DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'FNSKUviewer' => $request->fnsku,
                    //'MSKUviewer' => $request->msku,
                    //'ASINviewer' => $request->asin,
                    //'gradingviewer' => $request->grading,
                    //'AStitle' => $request->astitle,
                ]);

            // Update the FNSKU status in tblfnsku to 'Unavailable'
            DB::table('tblfnsku')
                ->where('FNSKU', $request->fnsku)
                ->update(['fnsku_status' => 'unavailable']);

            // Commit the transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'FNSKU updated successfully'
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();
            
            \Log::error('Error updating FNSKU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update FNSKU: ' . $e->getMessage()
            ], 500);
        }
    }

}