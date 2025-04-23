<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                ->select('*')
                ->where('fnsku_status', 'available')
                ->where('Units', '>', 0)
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
                // Only include fields you actually use in validation
                // If these fields are for future use, consider making them optional
                'msku' => 'required|string',
                'asin' => 'required|string',
                'grading' => 'required|string',
                'astitle' => 'required|string',
            ]);
            
            // Begin transaction
            DB::beginTransaction();
            
            // Check if product exists
            $product = DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->lockForUpdate() // Lock row to prevent race conditions
                ->first();
                
            if (!$product) {
                throw new \Exception('Product not found');
            }
            
            // Update the product
            DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update([
                    'FNSKUviewer' => $request->fnsku,
                    // Uncomment these if you actually need to update them
                    // 'MSKUviewer' => $request->msku,
                    // 'ASINviewer' => $request->asin,
                    // 'gradingviewer' => $request->grading,
                    // 'AStitle' => $request->astitle,
                ]);
            
            // Find an available FNSKU record
            $fnsku = DB::table($this->fnskuTable)
                ->where('FNSKU', $request->fnsku)
                ->where('Units', '>', 0)
                ->lockForUpdate() // Lock the row
                ->first();
                
            if ($fnsku) {
                // Decrement the units
                DB::table($this->fnskuTable)
                ->where('FNSKU', $request->fnsku) // Use primary key for precise update
                    ->update([
                        'Units' => DB::raw('Units - 1')
                    ]);
                    
                // Mark as unavailable if units reach zero
                if ($fnsku->Units == 1) {
                    DB::table($this->fnskuTable)
                    ->where('FNSKU', $request->fnsku)
                        ->update([
                            'fnsku_status' => 'unavailable'
                        ]);
                }
            } else {
                \Log::warning('No available units found for FNSKU: ' . $request->fnsku);
            }
            
            // Commit the transaction
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'FNSKU updated successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            \Log::error('Validation error: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();
            
            \Log::error('Error updating FNSKU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update FNSKU. Please try again later.'
            ], 500);
        }
    }

}