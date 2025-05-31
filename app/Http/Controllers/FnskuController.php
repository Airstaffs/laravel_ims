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
        $exclude_assigned = $request->boolean('exclude_assigned', true); // New parameter
        
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
            ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN');

        // If exclude_assigned is true, exclude FNSKUs that are already assigned to products
        if ($exclude_assigned) {
            $query->whereNotExists(function ($subquery) {
                $subquery->select(DB::raw(1))
                    ->from($this->productTable)
                    ->whereColumn($this->productTable . '.FNSKUviewer', 'fnsku.FNSKU')
                    ->whereNotNull($this->productTable . '.FNSKUviewer')
                    ->where($this->productTable . '.FNSKUviewer', '!=', '')
                    ->where($this->productTable . '.FNSKUviewer', '!=', 'NULL');
            });
        }

        $query->where('fnsku.fnsku_status', 'available')
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
            'has_more' => $fnskuList->count() >= $limit,
            'excluded_assigned' => $exclude_assigned
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
        
        // Check if product exists and get current FNSKU
        $product = DB::table($this->productTable)
            ->where('ProductID', $request->product_id)
            ->lockForUpdate() // Lock row to prevent race conditions
            ->first();
            
        if (!$product) {
            throw new \Exception('Product not found');
        }
        
        // Store the old FNSKU for potential inventory return
        $oldFnsku = $product->FNSKUviewer;
        $newFnsku = $request->fnsku;
        
        Log::info('FNSKU Update Details:', [
            'product_id' => $request->product_id,
            'old_fnsku' => $oldFnsku,
            'new_fnsku' => $newFnsku
        ]);
        
        // Update the product with new FNSKU
        $updateData = ['FNSKUviewer' => $newFnsku];
        
        DB::table($this->productTable)
            ->where('ProductID', $request->product_id)
            ->update($updateData);
        
        // Handle OLD FNSKU - Return unit back to inventory if it exists
        if (!empty($oldFnsku) && $oldFnsku !== null && $oldFnsku !== 'NULL' && trim($oldFnsku) !== '') {
            Log::info('Returning unit to old FNSKU: ' . $oldFnsku);
            
            // Find the old FNSKU record
            $oldFnskuRecord = DB::table($this->fnskuTable)
                ->where('FNSKU', $oldFnsku)
                ->lockForUpdate()
                ->first();
                
            if ($oldFnskuRecord) {
                // Increment the units (return the unit)
                DB::table($this->fnskuTable)
                    ->where('FNSKU', $oldFnsku)
                    ->update([
                        'Units' => DB::raw('Units + 1'),
                        'fnsku_status' => 'available' // Make sure it's marked as available
                    ]);
                    
                Log::info('Successfully returned 1 unit to old FNSKU: ' . $oldFnsku);
            } else {
                Log::warning('Old FNSKU record not found in inventory: ' . $oldFnsku);
            }
        } else {
            Log::info('No old FNSKU to return units to (was null/empty)');
        }
        
        // Handle NEW FNSKU - Deduct unit from inventory
        $newFnskuRecord = DB::table($this->fnskuTable)
            ->where('FNSKU', $newFnsku)
            ->where('Units', '>', 0)
            ->lockForUpdate() // Lock the row
            ->first();
            
        if ($newFnskuRecord) {
            Log::info('Deducting unit from new FNSKU: ' . $newFnsku . ' (Current units: ' . $newFnskuRecord->Units . ')');
            
            // Decrement the units
            DB::table($this->fnskuTable)
                ->where('FNSKU', $newFnsku)
                ->update([
                    'Units' => DB::raw('Units - 1')
                ]);
                
            // Mark as unavailable if units reach zero
            if ($newFnskuRecord->Units == 1) { // Check if it will be 0 after decrement
                DB::table($this->fnskuTable)
                    ->where('FNSKU', $newFnsku)
                    ->update([
                        'fnsku_status' => 'unavailable'
                    ]);
                Log::info('Marked new FNSKU as unavailable (0 units): ' . $newFnsku);
            }
            
            Log::info('Successfully deducted 1 unit from new FNSKU: ' . $newFnsku);
        } else {
            Log::warning('No available units found for new FNSKU: ' . $newFnsku);
            // Note: We don't throw an exception here because the assignment should still work
            // The FNSKU might be tracked differently or this might be a special case
        }
        
        // Commit the transaction
        DB::commit();
        
        Log::info('FNSKU update transaction completed successfully');
        
        return response()->json([
            'success' => true,
            'message' => 'FNSKU updated successfully',
            'details' => [
                'old_fnsku' => $oldFnsku,
                'new_fnsku' => $newFnsku,
                'old_fnsku_returned' => !empty($oldFnsku) && $oldFnsku !== 'NULL'
            ]
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
        Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to update FNSKU. Please try again later.',
            'debug' => [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]
        ], 500);
    }
}
}