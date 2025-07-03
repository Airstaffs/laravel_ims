<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ASINlistController extends BasetablesController
{
    /**
     * Display a listing of ASINs with their FNSKU data
     */
    public function index(Request $request)
    {
        try {
            $perPage = min($request->input('per_page', 15), 100);
            $search = $request->input('search', '');
            $store = $request->input('store', '');
            $page = $request->input('page', 1);
            
            // Build the main query - ASIN with FNSKU aggregated data
            $asinQuery = DB::table($this->asinTable . ' as asin')
                ->select([
                    'asin.ASIN',
                    'asin.internal as AStitle',
                    'asin.metakeyword',
                    'asin.EAN',
                    'asin.UPC',
                    'asin.ParentAsin',
                    'asin.CousinASIN',
                    'asin.UpgradeASIN',
                    'asin.GrandASIN',
                    'asin.instructioncard',
                    'asin.instructioncard2',
                    'asin.instructionlink',
                    'asin.usermanuallink',
                    'asin.asinimg',
                    'asin.vectorimage',
                    'asin.TRANSPARENCY_QR_STATUS',
                    // Amazon dimensions (read-only)
                    'asin.dimension_length',
                    'asin.dimension_width',
                    'asin.dimension_height',
                    'asin.weight_value',
                    'asin.weight_unit',
                    // White/Default dimensions (editable)
                    'asin.white_length',
                    'asin.white_width',
                    'asin.white_height',
                    'asin.white_value',
                    'asin.white_unit',
                    DB::raw('COUNT(fnsku.FNSKU) as fnsku_count')
                ])
                ->leftJoin($this->fnskuTable . ' as fnsku', 'asin.ASIN', '=', 'fnsku.ASIN')
                ->where('asin.ASIN', '!=', '')
                ->whereNotNull('asin.ASIN');
            
            // Apply search filters
            if (!empty($search)) {
                $asinQuery->where(function ($query) use ($search) {
                    $query->where('asin.ASIN', 'like', "%{$search}%")
                          ->orWhere('asin.internal', 'like', "%{$search}%")
                          ->orWhere('fnsku.FNSKU', 'like', "%{$search}%");
                });
            }
            
            // Apply store filter
            if (!empty($store)) {
                $asinQuery->where('fnsku.storename', $store);
            }
            
            // Group by ASIN and having clause to ensure we have at least one FNSKU
            $asinQuery->groupBy('asin.ASIN', 'asin.internal', 'asin.metakeyword', 'asin.EAN', 'asin.UPC', 'asin.ParentAsin', 'asin.CousinASIN', 'asin.UpgradeASIN', 'asin.GrandASIN', 'asin.instructioncard', 'asin.instructioncard2', 'asin.instructionlink', 'asin.usermanuallink', 'asin.asinimg', 'asin.vectorimage', 'asin.TRANSPARENCY_QR_STATUS', 'asin.dimension_length', 'asin.dimension_width', 'asin.dimension_height', 'asin.weight_value', 'asin.weight_unit', 'asin.white_length', 'asin.white_width', 'asin.white_height', 'asin.white_value', 'asin.white_unit')
                     ->having('fnsku_count', '>', 0);
            
            // Order by ASIN
            $asinQuery->orderBy('asin.ASIN', 'asc');
            
            // Get paginated results
            $asins = $asinQuery->paginate($perPage);
            
            // Get ASINs for batch loading FNSKU details
            $asinList = $asins->getCollection()->pluck('ASIN')->toArray();
            
            if (empty($asinList)) {
                $result = $asins->toArray();
                $result['data'] = [];
                return response()->json($result);
            }
            
            // Batch load detailed FNSKU data for all ASINs including grading
            $fnskuDetails = DB::table($this->fnskuTable)
                ->select([
                    'ASIN',
                    'FNSKU',
                    'MSKU',
                    'storename',
                    'grading',
                    'Units'
                ])
                ->whereIn('ASIN', $asinList)
                ->orderBy('FNSKU', 'asc')
                ->get()
                ->groupBy('ASIN');
            
            // Process results with batch-loaded FNSKU data
            $results = $asins->getCollection()->map(function($item) use ($fnskuDetails) {
                if (empty($item->ASIN)) {
                    return null;
                }
                
                // Add FNSKU details from batch-loaded data
                $item->fnskus = isset($fnskuDetails[$item->ASIN]) 
                    ? $fnskuDetails[$item->ASIN]->toArray() 
                    : [];
                
                // Add instruction card URLs from database
                $item->instruction_card_urls = [
                    'card1' => $item->instructioncard ? url($item->instructioncard) : null,
                    'card2' => $item->instructioncard2 ? url($item->instructioncard2) : null
                ];
                
                // Add user manual URL if exists
                $item->user_manual_url = $item->usermanuallink ? url($item->usermanuallink) : null;
                
                // Add ASIN image URL if exists
                $item->asin_image_url = $item->asinimg ? url($item->asinimg) : null;
                
                // Add vector image URL if exists
                $item->vector_image_url = $item->vectorimage ? url($item->vectorimage) : null;
                
                // Ensure numeric values are properly typed
                $item->fnsku_count = (int) $item->fnsku_count;
                
                return $item;
            })->filter(); // Remove null items
            
            // Update the collection
            $asins->setCollection($results);
            $result = $asins->toArray();
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Error in ASINlistController@index: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'error' => 'An error occurred while retrieving ASIN data',
                'message' => $e->getMessage(),
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0
            ], 500);
        }
    }

    /**
     * Get list of store names for the dropdown
     */
    public function getStores()
    {
        try {
            return response()->json(Cache::remember('asin_stores', 3600, function() {
                return DB::table($this->fnskuTable)
                    ->select('storename')
                    ->distinct()
                    ->whereNotNull('storename')
                    ->where('storename', '!=', '')
                    ->orderBy('storename')
                    ->pluck('storename');
            }));
        } catch (\Exception $e) {
            Log::error('Error getting stores: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'An error occurred while retrieving store list',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ASIN details (EAN/UPC/Instruction Link/Meta Keyword/Transparency)
     */
    public function updateAsinDetails(Request $request)
    {
        try {
            $validated = $request->validate([
                'asin' => 'required|string',
                'ean' => 'nullable|string|max:20',
                'upc' => 'nullable|string|max:20',
                'instruction_link' => 'nullable|string|max:1000',
                'metakeyword' => 'nullable|string|max:500',
                'transparency_qr_status' => 'nullable|string|max:1000'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            // Prepare update data
            $updateData = [
                'EAN' => $validated['ean'],
                'UPC' => $validated['upc'],
                'instructionlink' => $validated['instruction_link'],
                'metakeyword' => $validated['metakeyword'],
                'TRANSPARENCY_QR_STATUS' => $validated['transparency_qr_status']
            ];

            // Update ASIN details
            $updated = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->update($updateData);

            Log::info("ASIN details update attempt for: {$validated['asin']}", $updateData);

            if ($updated !== false) {
                Log::info("ASIN details updated: {$validated['asin']}");
                
                return response()->json([
                    'success' => true,
                    'message' => 'ASIN details updated successfully'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'ASIN details saved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating ASIN details: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating ASIN details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update default dimensions and weight
     */
    public function updateDefaultDimensions(Request $request)
    {
        try {
            $validated = $request->validate([
                'asin' => 'required|string',
                'def_length' => 'nullable|numeric|min:0',
                'def_width' => 'nullable|numeric|min:0',
                'def_height' => 'nullable|numeric|min:0',
                'def_weight' => 'nullable|numeric|min:0',
                'def_weight_unit' => 'nullable|string|max:10'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            // Update default dimensions
            $updateData = [
                'white_length' => $validated['def_length'],
                'white_width' => $validated['def_width'],
                'white_height' => $validated['def_height'],
                'white_value' => $validated['def_weight'],
                'white_unit' => $validated['def_weight_unit']
            ];

            $updated = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->update($updateData);

            if ($updated !== false) {
                Log::info("Default dimensions updated for: {$validated['asin']}");
                
                return response()->json([
                    'success' => true,
                    'message' => 'Default dimensions updated successfully'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Default dimensions saved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating default dimensions: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating default dimensions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update related ASINs
     */
    public function updateRelatedAsins(Request $request)
    {
        try {
            $validated = $request->validate([
                'asin' => 'required|string',
                'parent_asin' => 'nullable|string|max:20',
                'cousin_asin' => 'nullable|string|max:20',
                'upgrade_asin' => 'nullable|string|max:20',
                'grand_asin' => 'nullable|string|max:20'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            // Update related ASINs
            $updateData = [
                'ParentAsin' => $validated['parent_asin'],
                'CousinASIN' => $validated['cousin_asin'],
                'UpgradeASIN' => $validated['upgrade_asin'],
                'GrandASIN' => $validated['grand_asin']
            ];

            $updated = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->update($updateData);

            if ($updated !== false) {
                Log::info("Related ASINs updated for: {$validated['asin']}");
                
                return response()->json([
                    'success' => true,
                    'message' => 'Related ASINs updated successfully'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Related ASINs saved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating related ASINs: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating related ASINs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload instruction card image
     */
    public function uploadInstructionCard(Request $request)
    {
        try {
            $validated = $request->validate([
                'instruction_card' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
                'asin' => 'required|string',
                'card_slot' => 'required|in:1,2' // Which instruction card slot (1 or 2)
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            $file = $request->file('instruction_card');
            $asinCode = $validated['asin'];
            $cardSlot = $validated['card_slot'];
            
            // Create instruction cards directory if it doesn't exist
            $uploadPath = public_path('images/instructioncard');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            // Generate filename: {ASIN}_card{slot}.{extension}
            $extension = $file->getClientOriginalExtension();
            $filename = $asinCode . '_card' . $cardSlot . '.' . $extension;
            
            // Remove old instruction card if exists (different extensions) for this slot
            $oldFiles = glob($uploadPath . '/' . $asinCode . '_card' . $cardSlot . '.*');
            foreach ($oldFiles as $oldFile) {
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            
            // Move file to destination
            if ($file->move($uploadPath, $filename)) {
                $relativePath = 'images/instructioncard/' . $filename;
                $fileUrl = url($relativePath);
                
                // Update database with just the filename
                $columnName = $cardSlot == 1 ? 'instructioncard' : 'instructioncard2';
                DB::table($this->asinTable)
                    ->where('ASIN', $asinCode)
                    ->update([
                        $columnName => $filename // Store only filename
                    ]);
                
                Log::info("Instruction card {$cardSlot} uploaded for ASIN: {$asinCode}");
                
                return response()->json([
                    'success' => true,
                    'message' => 'Instruction card uploaded successfully',
                    'file_url' => $fileUrl,
                    'filename' => $filename,
                    'card_slot' => $cardSlot,
                    'relative_path' => $relativePath
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error uploading instruction card: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading instruction card',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload user manual PDF
     */
    public function uploadUserManual(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_manual' => 'required|file|mimes:pdf|max:10240', // 10MB max for PDF
                'asin' => 'required|string'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            $file = $request->file('user_manual');
            $asinCode = $validated['asin'];
            
            // Create user manual directory if it doesn't exist
            $uploadPath = public_path('images/usermanual');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            // Generate filename: {ASIN}.pdf
            $filename = $asinCode . '.pdf';
            
            // Remove old user manual if exists
            $oldFile = $uploadPath . '/' . $filename;
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
            
            // Move file to destination
            if ($file->move($uploadPath, $filename)) {
                $relativePath = 'images/usermanual/' . $filename;
                $fileUrl = url($relativePath);
                
                // Update database with just the filename
                DB::table($this->asinTable)
                    ->where('ASIN', $asinCode)
                    ->update([
                        'usermanuallink' => $filename // Store only filename
                    ]);
                
                Log::info("User manual uploaded for ASIN: {$asinCode}");
                
                return response()->json([
                    'success' => true,
                    'message' => 'User manual uploaded successfully',
                    'file_url' => $fileUrl,
                    'filename' => $filename,
                    'relative_path' => $relativePath
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload user manual'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error uploading user manual: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading user manual',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload ASIN main image
     */
    public function uploadAsinImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'asin_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
                'asin' => 'required|string'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            $file = $request->file('asin_image');
            $asinCode = $validated['asin'];
            
            // Create ASIN image directory if it doesn't exist
            $uploadPath = public_path('images/asinimg');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            // Generate filename: {ASIN}_0.{extension}
            $extension = $file->getClientOriginalExtension();
            $filename = $asinCode . '_0.' . $extension;
            
            // Remove old ASIN images if exists (different extensions)
            $oldFiles = glob($uploadPath . '/' . $asinCode . '_0.*');
            foreach ($oldFiles as $oldFile) {
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            
            // Move file to destination
            if ($file->move($uploadPath, $filename)) {
                $relativePath = 'images/asinimg/' . $filename;
                $fileUrl = url($relativePath);
                
                // Update database with just the filename
                DB::table($this->asinTable)
                    ->where('ASIN', $asinCode)
                    ->update([
                        'asinimg' => $filename // Store only filename
                    ]);
                
                Log::info("ASIN image uploaded for ASIN: {$asinCode}");
                
                return response()->json([
                    'success' => true,
                    'message' => 'ASIN image uploaded successfully',
                    'file_url' => $fileUrl,
                    'filename' => $filename,
                    'relative_path' => $relativePath
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload ASIN image'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error uploading ASIN image: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading ASIN image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload ASIN vector image
     */
    public function uploadAsinVectorImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'vector_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
                'asin' => 'required|string'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            $file = $request->file('vector_image');
            $asinCode = $validated['asin'];
            
            // Create vector image directory if it doesn't exist
            $uploadPath = public_path('images/asinvectorsimg');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            // Generate filename: {ASIN}.{extension}
            $extension = $file->getClientOriginalExtension();
            $filename = $asinCode . '.' . $extension;
            
            // Remove old vector images if exists (different extensions)
            $oldFiles = glob($uploadPath . '/' . $asinCode . '.*');
            foreach ($oldFiles as $oldFile) {
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            
            // Move file to destination
            if ($file->move($uploadPath, $filename)) {
                $relativePath = 'images/asinvectorsimg/' . $filename;
                $fileUrl = url($relativePath);
                
                // Update database with the relative path
                DB::table($this->asinTable)
                    ->where('ASIN', $asinCode)
                    ->update([
                        'vectorimage' => $relativePath
                    ]);
                
                Log::info("Vector image uploaded for ASIN: {$asinCode}");
                
                return response()->json([
                    'success' => true,
                    'message' => 'Vector image uploaded successfully',
                    'file_url' => $fileUrl,
                    'filename' => $filename,
                    'relative_path' => $relativePath
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload vector image'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error uploading vector image: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading vector image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}