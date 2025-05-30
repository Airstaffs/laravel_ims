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
                $q->where('ASIN', 'like', "%{$search}%")
                    ->orWhere('ASIN', 'like', "%{$search}%");
                
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

    
    public function getFnskuList(Request $request)
    {
        try {
            // Get limit from request, default to 50, max 200
            $limit = min($request->input('limit', 50), 200);
            $search = $request->input('search', '');
            
            // Updated to join with ASIN table to get the title
            $query = DB::table($this->fnskuTable . ' as fnsku')
                ->select([
                    'fnsku.FNSKU',
                    'fnsku.MSKU', 
                    'fnsku.ASIN',
                    'fnsku.grading',
                    'fnsku.Units',
                    'fnsku.storename',
                    'fnsku.fnsku_status',
                    'asin.internal as astitle' // Get title from ASIN table, same as StockroomController
                ])
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->where('fnsku.fnsku_status', 'available')
                ->where('fnsku.Units', '>', 0);

            // Add search functionality if search parameter is provided
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('fnsku.FNSKU', 'like', "%{$search}%")
                      ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                      ->orWhere('fnsku.grading', 'like', "%{$search}%")
                      ->orWhere('asin.internal', 'like', "%{$search}%");
                });
            }

            $fnskuList = $query->orderBy('fnsku.FNSKU')
                ->limit($limit)
                ->get();

            return response()->json([
                'data' => $fnskuList,
                'total' => $fnskuList->count(),
                'limit' => $limit,
                'has_more' => $fnskuList->count() >= $limit
            ]);
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
        Log::info('Received update request:', $request->all());
        
        try {
            // More flexible validation - only require what you actually need
            $request->validate([
                'product_id' => 'required|integer',
                'fnsku' => 'required|string|min:1',
                // Make these optional or provide defaults
                'msku' => 'nullable|string',
                'asin' => 'nullable|string', 
                'grading' => 'nullable|string'
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
            
            // Prepare update data - only include non-null values
            $updateData = ['FNSKUviewer' => $request->fnsku];
            
            // Update the product
            DB::table($this->productTable)
                ->where('ProductID', $request->product_id)
                ->update($updateData);
            
            // Find an available FNSKU record
            $fnsku = DB::table($this->fnskuTable)
                ->where('FNSKU', $request->fnsku)
                ->where('Units', '>', 0)
                ->lockForUpdate() // Lock the row
                ->first();
                
            if ($fnsku) {
                // Decrement the units
                DB::table($this->fnskuTable)
                    ->where('FNSKU', $request->fnsku)
                    ->update([
                        'Units' => DB::raw('Units - 1')
                    ]);
                    
                // Mark as unavailable if units reach zero
                if ($fnsku->Units == 1) { // Check if it will be 0 after decrement
                    DB::table($this->fnskuTable)
                        ->where('FNSKU', $request->fnsku)
                        ->update([
                            'fnsku_status' => 'unavailable'
                        ]);
                }
            } else {
                Log::warning('No available units found for FNSKU: ' . $request->fnsku);
            }
            
            // Commit the transaction
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'FNSKU updated successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();
            
            Log::error('Error updating FNSKU: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update FNSKU. Please try again later.'
            ], 500);
        }
    }
}