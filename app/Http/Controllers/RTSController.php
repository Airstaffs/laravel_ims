<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\tblproduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class RTSController extends BasetablesController
{
        private function extractBaseFnsku($fnsku)
        {
            if (empty($fnsku)) {
                return $fnsku;
            }

            // Check if it's a prefixed FNSKU (starts with letter C-W or Y-Z, excluding X)
            // Pattern: Letter(C-W,Y-Z) + Number(1-9) + BaseFNSKU (which starts with X)
            if (preg_match('/^([C-W]|[Y-Z])(\d+)(X.+)$/', $fnsku, $matches)) {
                return $matches[3]; // Return the base FNSKU (starting with X)
            }

            return $fnsku; // Return as-is if not prefixed
        }

    public function index(Request $request)
    {
        try {
            // Define table names directly
            $productTable = 'tblproduct';
            $fnskuTable = 'tblfnsku';
            $asinTable = 'tblasin';
            $company = 'Airstaffs';

            Log::info('Tables being used:', [
                'productTable' => $productTable,
                'fnskuTable' => $fnskuTable,
                'asinTable' => $asinTable,
                'company' => $company,
            ]);

            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'RTS');
            $includeImages = $request->boolean('include_images', false);

            // Build base select array
            $selectColumns = [
                'prod.*',
                'fnsku.ASIN',
                'fnsku.MSKU',
                'fnsku.FNSKU',
                'fnsku.grading',
                'fnsku.storename',
                DB::raw("COALESCE(
                    NULLIF(TRIM(asin.system_title), ''), 
                    NULLIF(TRIM(asin.internal), ''), 
                    NULLIF(TRIM(prod.ProductTitle), '')
                ) as AStitle"),
                'asin.internal',
                'asin.system_title',
                'asin.metakeyword',
            ];

            // Add image columns if requested
            if ($includeImages) {
                $selectColumns = array_merge($selectColumns, [
                    'img.capturedimg1',
                    'img.capturedimg2',
                    'img.capturedimg3',
                    'img.capturedimg4',
                    'img.capturedimg5',
                    'img.capturedimg6',
                    'img.capturedimg7',
                    'img.capturedimg8',
                    'img.capturedimg9',
                    'img.capturedimg10',
                    'img.capturedimg11',
                    'img.capturedimg12',
                    'img.serialimg1',
                    'img.serialimg2',
                ]);
            }

            // Build query with MSKU join instead of FNSKU join
            $productsQuery = DB::table($this->productTable.' as prod')
                ->leftJoin($this->fnskuTable.' as fnsku', 'prod.MSKUviewer', '=', 'fnsku.MSKU')
                ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN');

            // Only join images table if images are requested
            if ($includeImages) {
                $productsQuery->leftJoin($this->capturedImagesTable.' as img', 'prod.ProductID', '=', 'img.ProductID');
            }

            $productsQuery->select($selectColumns)
                ->where('prod.ProductModuleLoc', $location)
                ->distinct();

            // Apply comprehensive search including ASIN and metakeyword
            if (! empty($search)) {
                $productsQuery->where(function ($q) use ($search) {
                    $q->where('prod.serialnumber', 'like', "%{$search}%")
                        ->orWhere('prod.ProductTitle', 'like', "%{$search}%")
                        ->orWhere('prod.PCN', 'like', "%{$search}%")
                        ->orWhere('prod.RPN', 'like', "%{$search}%")
                        ->orWhere('prod.PRD', 'like', "%{$search}%")
                        ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                        ->orWhere('prod.MSKUviewer', 'like', "%{$search}%")
                        ->orWhere('prod.trackingnumber', 'like', "%{$search}%")
                        ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                        ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                        ->orWhere('fnsku.MSKU', 'like', "%{$search}%")
                        ->orWhere('fnsku.FNSKU', 'like', "%{$search}%")
                        ->orWhere('asin.internal', 'like', "%{$search}%")
                        ->orWhere('asin.system_title', 'like', "%{$search}%")
                        ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                });
            }

            $products = $productsQuery->paginate($perPage);
            Log::info('Products fetched successfully with joins', ['count' => $products->count()]);

            // Transform products to organize data properly
            $products->getCollection()->transform(function ($product) use ($includeImages) {
                // Keep the original FNSKU as displayed (from the join or FNSKUviewer)
                if (empty($product->FNSKU) && ! empty($product->FNSKUviewer)) {
                    $product->FNSKU = $product->FNSKUviewer;
                }

                // Keep MSKUviewer from product table
                if (empty($product->MSKU) && ! empty($product->MSKUviewer)) {
                    $product->MSKU = $product->MSKUviewer;
                }

                // Ensure we have the company for proper path construction
                $product->company = $this->company;

                // Organize captured images into an object if images were requested
                if ($includeImages) {
                    $capturedImages = (object) [];

                    for ($i = 1; $i <= 12; $i++) {
                        $imgKey = "capturedimg{$i}";
                        if (! empty($product->$imgKey)) {
                            $capturedImages->$imgKey = $product->$imgKey;
                        }
                        // Remove from main product object to keep it clean
                        unset($product->$imgKey);
                    }

                    // Handle serial images
                    if (! empty($product->serialimg1)) {
                        $capturedImages->serialimg1 = $product->serialimg1;
                    }

                    if (! empty($product->serialimg2)) {
                        $capturedImages->serialimg2 = $product->serialimg2;
                    }

                    unset($product->serialimg1);
                    unset($product->serialimg2);

                    $product->capturedImages = $capturedImages;

                    // Set img1 directly for the main thumbnail display if capturedimg1 exists
                   if (!empty($capturedImages->capturedimg1)) {
                                $product->img1 = null; // don't override img1, let gallery slot handle it
                            }
                } else {
                    // Initialize empty capturedImages if not requested
                    $product->capturedImages = (object) [];
                }

                return $product;
            });

            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error in RTSController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching products',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save RTS Options for a product
     */
    public function saveRTSOptions(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'rtcounter' => 'required',
                'ProductID' => 'required|integer',
                'FNSKU' => 'nullable|string',
                'serialnumber' => 'nullable|string',
                'dateField' => 'required|date',
                'testResult' => 'required|in:Passed,Failed',
                'status' => 'required|in:RTS,Dismantle',
                'rtsResult' => 'nullable|in:PRNR,FRNR,LST,Replacement,Ship-Back',
                'filedInES' => 'boolean',
                'filedInPPL' => 'boolean',
                'refundAmount' => 'nullable|numeric|min:0',
                'refundDate' => 'nullable|date',
                'reasonOfReturn' => 'nullable|string|max:1000',
                'returnTN' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:2000',
            ]);

            if ($validator->fails()) {
                Log::warning('RTS Options validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();

            // Convert rtcounter to string to ensure consistency
            $data['rtcounter'] = (string) $data['rtcounter'];

            Log::info('Saving RTS Options', [
                'rtcounter' => $data['rtcounter'],
                'ProductID' => $data['ProductID'],
                'user_id' => Auth::id(),
                'testResult' => $data['testResult'],
                'status' => $data['status'],
            ]);

            // Create the RTS table name
            $rtsTableName = $this->company.'tblrts';

            // Create table if it doesn't exist
            if (! Schema::hasTable($rtsTableName)) {
                Log::info('Creating RTS table as it does not exist', ['table' => $rtsTableName]);

                Schema::create($rtsTableName, function ($table) {
                    $table->id('rts_id');
                    $table->string('rtcounter', 50)->index();
                    $table->unsignedBigInteger('ProductID')->index();
                    $table->string('FNSKU', 100)->index();
                    $table->string('serialnumber', 100)->nullable();

                    // Main RTS fields
                    $table->date('filed_date');
                    $table->boolean('filed_in_es')->default(false);
                    $table->boolean('filed_in_ppl')->default(false);
                    $table->enum('test_result', ['Passed', 'Failed']);
                    $table->enum('status', ['RTS', 'Dismantle']);
                    $table->enum('rts_result', ['PRNR', 'FRNR', 'LST', 'Replacement', 'Ship-Back']);

                    // Refund status fields
                    $table->decimal('refund_amount', 10, 2)->nullable();
                    $table->date('refund_date')->nullable();
                    $table->text('reason_of_return')->nullable();
                    $table->string('return_tn', 255)->nullable();
                    $table->text('notes')->nullable();

                    // Audit fields
                    $table->string('created_by', 100)->nullable();
                    $table->string('updated_by', 100)->nullable();
                    $table->timestamps();

                    // Add indexes for better performance
                    $table->unique(['rtcounter', 'ProductID'], 'unique_rt_product');
                    $table->index(['status', 'test_result'], 'status_test_idx');
                    $table->index('filed_date', 'filed_date_idx');
                });

                Log::info('Successfully created RTS table', ['table' => $rtsTableName]);
            }

            // Helper function to get current user identifier
            $getCurrentUser = function () {
                $user = Auth::user();

                return $user ? ($user->username ?? $user->name ?? 'Unknown') : 'Unknown';
            };

            // Check if record exists
            $existingRecord = DB::table($rtsTableName)
                ->where('rtcounter', $data['rtcounter'])
                ->where('ProductID', $data['ProductID'])
                ->first();

            // Handle different test results
            if ($data['testResult'] === 'Passed') {
                Log::info('Test result is Passed - removing RTS record and updating product to Labeling', [
                    'rtcounter' => $data['rtcounter'],
                    'ProductID' => $data['ProductID'],
                ]);

                // If test passed, remove from RTS table (if exists)
                if ($existingRecord) {
                    DB::table($rtsTableName)
                        ->where('rtcounter', $data['rtcounter'])
                        ->where('ProductID', $data['ProductID'])
                        ->delete();

                    Log::info('Deleted RTS record for passed test', [
                        'rts_id' => $existingRecord->rts_id,
                        'rtcounter' => $data['rtcounter'],
                        'ProductID' => $data['ProductID'],
                    ]);
                }

                // Update product table - move to Labeling and reset refund
                DB::table($this->productTable)
                    ->where('ProductID', $data['ProductID'])
                    ->update([
                        'ProductModuleLoc' => 'Labeling',
                        'refund' => 0,
                    ]);

                Log::info('Updated product to Labeling module with reset refund', [
                    'ProductID' => $data['ProductID'],
                    'ProductModuleLoc' => 'Labeling',
                    'refund' => 0,
                ]);

                $message = 'Test passed successfully. Product moved to Labeling module and refund reset.';

            } elseif ($data['testResult'] === 'Failed') {
                Log::info('Test result is Failed - saving/updating RTS record and updating product to RTS module', [
                    'rtcounter' => $data['rtcounter'],
                    'ProductID' => $data['ProductID'],
                    'refundAmount' => $data['refundAmount'] ?? 0,
                ]);

                // Prepare data for database
                $rtsData = [
                    'rtcounter' => $data['rtcounter'],
                    'ProductID' => $data['ProductID'],
                    'FNSKU' => $data['FNSKU'],
                    'serialnumber' => $data['serialnumber'] ?? null,
                    'filed_date' => $data['dateField'],
                    'filed_in_es' => $data['filedInES'] ?? false,
                    'filed_in_ppl' => $data['filedInPPL'] ?? false,
                    'test_result' => $data['testResult'],
                    'status' => $data['status'],
                    'rts_result' => $data['rtsResult'],
                    'refund_amount' => ! empty($data['refundAmount']) ? $data['refundAmount'] : null,
                    'refund_date' => ! empty($data['refundDate']) ? $data['refundDate'] : null,
                    'reason_of_return' => $data['reasonOfReturn'] ?? null,
                    'return_tn' => $data['returnTN'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'updated_by' => $getCurrentUser(),
                    'updated_at' => now(),
                ];

                if ($existingRecord) {
                    // Update existing record
                    DB::table($rtsTableName)
                        ->where('rtcounter', $data['rtcounter'])
                        ->where('ProductID', $data['ProductID'])
                        ->update($rtsData);

                    Log::info('Updated existing RTS record for failed test', [
                        'rts_id' => $existingRecord->rts_id,
                        'rtcounter' => $data['rtcounter'],
                        'ProductID' => $data['ProductID'],
                        'updated_by' => $getCurrentUser(),
                    ]);
                } else {
                    // Create new record
                    $rtsData['created_by'] = $getCurrentUser();
                    $rtsData['created_at'] = now();

                    $rtsId = DB::table($rtsTableName)->insertGetId($rtsData);

                    Log::info('Created new RTS record for failed test', [
                        'rts_id' => $rtsId,
                        'rtcounter' => $data['rtcounter'],
                        'ProductID' => $data['ProductID'],
                        'created_by' => $getCurrentUser(),
                    ]);
                }

                // Update product table - move to RTS module and set refund amount
                $refundAmount = ! empty($data['refundAmount']) ? $data['refundAmount'] : 0;

                DB::table($this->productTable)
                    ->where('ProductID', $data['ProductID'])
                    ->update([
                        'ProductModuleLoc' => 'RTS',
                        'refund' => $refundAmount,
                    ]);

                Log::info('Updated product to RTS module with refund amount', [
                    'ProductID' => $data['ProductID'],
                    'ProductModuleLoc' => 'RTS',
                    'refund' => $refundAmount,
                ]);

                $message = 'Test failed. RTS options saved and product moved to RTS module with refund amount set.';
            }

            Log::info('Successfully processed RTS options', [
                'rtcounter' => $data['rtcounter'],
                'ProductID' => $data['ProductID'],
                'testResult' => $data['testResult'],
                'finalStatus' => $data['testResult'] === 'Passed' ? 'Moved to Labeling' : 'Moved to RTS',
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'rtcounter' => $data['rtcounter'],
                    'testResult' => $data['testResult'],
                    'status' => $data['status'],
                    'result' => $data['rtsResult'],
                    'filed_date' => $data['dateField'],
                    'productModuleLoc' => $data['testResult'] === 'Passed' ? 'Labeling' : 'RTS',
                    'refundAmount' => $data['testResult'] === 'Passed' ? 0 : ($data['refundAmount'] ?? 0),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving RTS options', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving RTS options: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get RTS Options for a specific product
     */
    public function getRTSOptions(Request $request)
    {
        try {
            $rtcounter = $request->input('rtcounter');
            $productId = $request->input('ProductID');

            if (empty($rtcounter) || empty($productId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'RT Counter and Product ID are required',
                ], 400);
            }

            // Updated table name without underscore
            $rtsTableName = $this->company.'tblrts';

            if (! Schema::hasTable($rtsTableName)) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No RTS options found',
                ]);
            }

            $rtsOptions = DB::table($rtsTableName)
                ->where('rtcounter', $rtcounter)
                ->where('ProductID', $productId)
                ->first();

            if ($rtsOptions) {
                // Convert database fields back to form field names
                $formData = [
                    'dateField' => $rtsOptions->filed_date,
                    'filedInES' => (bool) $rtsOptions->filed_in_es,
                    'filedInPPL' => (bool) $rtsOptions->filed_in_ppl,
                    'testResult' => $rtsOptions->test_result,
                    'status' => $rtsOptions->status,
                    'rtsResult' => $rtsOptions->rts_result,
                    'refundAmount' => $rtsOptions->refund_amount,
                    'refundDate' => $rtsOptions->refund_date,
                    'reasonOfReturn' => $rtsOptions->reason_of_return,
                    'returnTN' => $rtsOptions->return_tn,
                    'notes' => $rtsOptions->notes,
                    'rts_id' => $rtsOptions->rts_id,
                    'created_by' => $rtsOptions->created_by, // Now username instead of ID
                    'updated_by' => $rtsOptions->updated_by, // Now username instead of ID
                    'created_at' => $rtsOptions->created_at,
                    'updated_at' => $rtsOptions->updated_at,
                ];

                Log::info('Retrieved RTS options', [
                    'rts_id' => $rtsOptions->rts_id,
                    'rtcounter' => $rtcounter,
                    'ProductID' => $productId,
                    'created_by' => $rtsOptions->created_by,
                    'updated_by' => $rtsOptions->updated_by,
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $formData,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No RTS options found for this product',
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching RTS options', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'rtcounter' => $rtcounter ?? null,
                'ProductID' => $productId ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching RTS options',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ProductID' => 'required|integer',
            'itemnumber' => 'required|string',
            'ProductTitle' => 'nullable|string',
            'rtid' => 'nullable|string',
            'orderdate' => 'nullable|date',
            'paymentdate' => 'nullable|date',
            'shipdate' => 'nullable|date',
            'datedelivered' => 'nullable|date',
            'seller' => 'nullable|string',
            'materialtype' => 'nullable|string',
            'sourceType' => 'nullable|string',
            'carrier' => 'nullable|string',
            'listedcondition' => 'nullable|string',
            'paymentmethod' => 'nullable|string',
            'quantity' => 'nullable|numeric',
            'Discount' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'priceshipping' => 'nullable|numeric',
            'refund' => 'nullable|numeric',
            'description' => 'nullable|string',
            'supplierNotes' => 'nullable|string',
            'employeeNotes' => 'nullable|string',
            'serialnumber' => 'nullable|string',
            'serialnumberb' => 'nullable|string',
            'serialnumberc' => 'nullable|string',
            'serialnumberd' => 'nullable|string',
            'trackingnumber' => 'nullable|string',
            'trackingnumber2' => 'nullable|string',
            'trackingnumber3' => 'nullable|string',
            'trackingnumber4' => 'nullable|string',
            'trackingnumber5' => 'nullable|string',
            'validation' => 'nullable|string',
        ]);

        $validated['validation'] = $validated['validation'] ?? 'unvalidated';

        // dd($validated);

        $product = tblproduct::updateOrCreate(
            ['itemnumber' => $validated['itemnumber']],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Order product saved successfully',
            'product' => $product,
        ]);
    }
}
