<?php

namespace App\Http\Controllers;

use App\Models\tblproduct;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Rpn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use DateTime;
use DateTimeZone;

class RTSController extends BasetablesController
{
    private function extractBaseFnsku($fnsku)
    {
        if (empty($fnsku)) {
            return $fnsku;
        }

        // Check if it's a prefixed FNSKU (starts with C followed by digits)
        if (preg_match('/^C(\d+)(.+)$/', $fnsku, $matches)) {
            return $matches[2]; // Return the base FNSKU without prefix
        }

        return $fnsku; // Return as-is if not prefixed
    }

    public function index(Request $request)
    {
        try {
            // Log tables being used for debugging
            Log::info('Tables being used:', [
                'productTable' => $this->productTable,
                'capturedImagesTable' => $this->capturedImagesTable,
                'fnskuTable' => $this->fnskuTable,
                'asinTable' => $this->asinTable,
                'company' => $this->company
            ]);

            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $location = $request->input('location', 'Labeling');
            $includeImages = $request->boolean('include_images', false);

            // Log the parameters being used
            Log::info('Request parameters:', [
                'per_page' => $perPage,
                'search' => $search,
                'location' => $location,
                'include_images' => $includeImages
            ]);

            // Build the products query with proper filtering
            $productsQuery = DB::table($this->productTable . ' as prod')
                ->select([
                    'prod.*'
                ]);

            // Apply location filter - show only products in RTS module
            if (!empty($location)) {
                $productsQuery->where('prod.ProductModuleLoc', $location);
                Log::info('Applied location filter', ['location' => $location]);
            }

            // IMPORTANT: Exclude products that should not be shown in RTS
            $productsQuery->where(function ($query) {
                $query->whereNotIn('prod.ProductModuleLoc', ['Stockroom', 'Shipment', 'Soldlist'])
                    ->where(function ($subQuery) {
                        $subQuery->whereNull('prod.returnstatus')
                            ->orWhere('prod.returnstatus', '!=', 'Returned');
                    });
            });

            // Apply search filters
            if (!empty($search)) {
                $productsQuery->where(function ($q) use ($search) {
                    $q->where('prod.serialnumber', 'like', "%{$search}%")
                        ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                        ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                        ->orWhere('prod.ProductTitle', 'like', "%{$search}%");
                });
                Log::info('Applied search filter', ['search' => $search]);
            }

            // Log the generated SQL for debugging
            Log::info('Generated SQL Query:', [
                'sql' => $productsQuery->toSql(),
                'bindings' => $productsQuery->getBindings()
            ]);

            // Execute the query
            $products = $productsQuery->paginate($perPage);

            Log::info('Products query executed:', [
                'count' => $products->count(),
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage()
            ]);

            // If no products found, return early with debug info
            if ($products->count() === 0) {
                Log::warning('No products found matching criteria');

                // Debug query - check if any products exist at all
                $totalProducts = DB::table($this->productTable)->count();
                Log::info('Total products in table:', ['count' => $totalProducts]);

                // Check products by location
                $productsByLocation = DB::table($this->productTable)
                    ->select('ProductModuleLoc', DB::raw('count(*) as count'))
                    ->groupBy('ProductModuleLoc')
                    ->get();
                Log::info('Products by location:', $productsByLocation->toArray());

                return response()->json($products);
            }

            // Extract all unique base FNSKUs from products
            $baseFnskus = [];
            $fnskuProductMap = [];

            foreach ($products->items() as $product) {
                if (!empty($product->FNSKUviewer)) {
                    $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);
                    $baseFnskus[] = $baseFnsku;

                    if (!isset($fnskuProductMap[$baseFnsku])) {
                        $fnskuProductMap[$baseFnsku] = [];
                    }
                    $fnskuProductMap[$baseFnsku][] = $product;
                }
            }

            $baseFnskus = array_unique($baseFnskus);
            Log::info('Base FNSKUs extracted:', ['count' => count($baseFnskus)]);

            // Get FNSKU data for base FNSKUs
            $fnskuData = [];
            if (!empty($baseFnskus)) {
                $fnskuRecords = DB::table($this->fnskuTable)
                    ->select('ASIN', 'FNSKU', 'MSKU', 'grading', 'storename')
                    ->whereIn('FNSKU', $baseFnskus)
                    ->get();

                Log::info('FNSKU records found:', ['count' => $fnskuRecords->count()]);

                foreach ($fnskuRecords as $record) {
                    $fnskuData[$record->FNSKU] = $record;
                }
            }

            // Get ASIN data
            $asinList = [];
            foreach ($fnskuData as $fnskuRecord) {
                if (!empty($fnskuRecord->ASIN)) {
                    $asinList[] = $fnskuRecord->ASIN;
                }
            }
            $asinList = array_unique($asinList);

            $asinData = [];
            if (!empty($asinList)) {
                $asinRecords = DB::table($this->asinTable)
                    ->select('ASIN', 'internal')
                    ->whereIn('ASIN', $asinList)
                    ->get();

                Log::info('ASIN records found:', ['count' => $asinRecords->count()]);

                foreach ($asinRecords as $record) {
                    $asinData[$record->ASIN] = $record;
                }
            }

            // Add FNSKU and ASIN data to all products
            $products->getCollection()->transform(function ($product) use ($fnskuData, $asinData) {
                $baseFnsku = $this->extractBaseFnsku($product->FNSKUviewer);

                // Set default values
                $product->FNSKU = $product->FNSKUviewer ?? '';
                $product->MSKU = '';
                $product->ASIN = '';
                $product->grading = '';
                $product->storename = '';
                $product->AStitle = $product->ProductTitle ?? '';

                if (isset($fnskuData[$baseFnsku])) {
                    $fnskuRecord = $fnskuData[$baseFnsku];
                    $product->FNSKU = $fnskuRecord->FNSKU;
                    $product->MSKU = $fnskuRecord->MSKU;
                    $product->ASIN = $fnskuRecord->ASIN;
                    $product->grading = $fnskuRecord->grading;
                    $product->storename = $fnskuRecord->storename;

                    if (isset($asinData[$fnskuRecord->ASIN])) {
                        $product->AStitle = $asinData[$fnskuRecord->ASIN]->internal;
                    }
                }

                // Ensure required fields have default values
                $product->quantity = $product->quantity ?? 1;
                $product->serialnumber = $product->serialnumber ?? '';
                $product->fulfillment_status = $product->fulfillment_status ?? '';
                $product->returnstatus = $product->returnstatus ?? '';

                return $product;
            });

            // If images are requested, fetch them for each product
            if ($includeImages) {
                try {
                    $productIds = $products->pluck('ProductID')->toArray();
                    Log::info('Product IDs for image fetch', ['count' => count($productIds)]);

                    $capturedImagesTableName = $this->capturedImagesTable;

                    Log::info('Checking table existence', [
                        'table' => $capturedImagesTableName
                    ]);

                    if (!Schema::hasTable($capturedImagesTableName)) {
                        Log::warning('Captured images table does not exist', [
                            'table' => $capturedImagesTableName
                        ]);
                        $products->getCollection()->transform(function ($product) {
                            $product->company = $this->company;
                            $product->capturedImages = (object) [];
                            return $product;
                        });
                    } else {
                        Log::info('Captured images table exists', ['table' => $capturedImagesTableName]);

                        $capturedImages = DB::table($capturedImagesTableName)
                            ->whereIn('ProductID', $productIds)
                            ->get();

                        Log::info('Captured images fetched', [
                            'count' => $capturedImages->count()
                        ]);

                        $imagesByProductId = [];
                        foreach ($capturedImages as $img) {
                            $imagesByProductId[$img->ProductID] = $img;
                        }

                        $products->getCollection()->transform(function ($product) use ($imagesByProductId) {
                            $product->company = $this->company;

                            if (isset($imagesByProductId[$product->ProductID])) {
                                $product->capturedImages = $imagesByProductId[$product->ProductID];

                                if (empty($product->img1) && !empty($product->capturedImages->capturedimg1)) {
                                    $product->img1 = $product->capturedImages->capturedimg1;
                                }
                            } else {
                                $product->capturedImages = (object) [];
                            }

                            return $product;
                        });
                    }
                } catch (\Exception $e) {
                    Log::error('Error fetching images', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    $products->getCollection()->transform(function ($product) {
                        $product->company = $this->company;
                        $product->capturedImages = (object) [];
                        return $product;
                    });
                }
            } else {
                $products->getCollection()->transform(function ($product) {
                    $product->company = $this->company;
                    return $product;
                });
            }

            Log::info('Final products prepared for response', [
                'count' => $products->count(),
                'sample_product' => $products->count() > 0 ? $products->items()[0] : null
            ]);

            return response()->json($products);

        } catch (\Exception $e) {
            Log::error('Error in RTSController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching products',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save RTS Options for a product
     */
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
                'FNSKU' => 'required|string',
                'serialnumber' => 'nullable|string',
                'dateField' => 'required|date',
                'testResult' => 'required|in:Passed,Failed',
                'status' => 'required|in:RTS,Dismantle',
                'rtsResult' => 'required|in:PRNR,FRNR,LST,Replacement,Ship-Back',
                'filedInES' => 'boolean',
                'filedInPPL' => 'boolean',
                'refundAmount' => 'nullable|numeric|min:0',
                'refundDate' => 'nullable|date',
                'reasonOfReturn' => 'nullable|string|max:1000',
                'returnTN' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                Log::warning('RTS Options validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
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
                'status' => $data['status']
            ]);

            // Create the RTS table name
            $rtsTableName = $this->company . 'tblrts';

            // Create table if it doesn't exist
            if (!Schema::hasTable($rtsTableName)) {
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
                    'ProductID' => $data['ProductID']
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
                        'ProductID' => $data['ProductID']
                    ]);
                }

                // Update product table - move to Labeling and reset refund
                DB::table($this->productTable)
                    ->where('ProductID', $data['ProductID'])
                    ->update([
                        'ProductModuleLoc' => 'Labeling',
                        'refund' => 0
                    ]);

                Log::info('Updated product to Labeling module with reset refund', [
                    'ProductID' => $data['ProductID'],
                    'ProductModuleLoc' => 'Labeling',
                    'refund' => 0
                ]);

                $message = 'Test passed successfully. Product moved to Labeling module and refund reset.';

            } else if ($data['testResult'] === 'Failed') {
                Log::info('Test result is Failed - saving/updating RTS record and updating product to RTS module', [
                    'rtcounter' => $data['rtcounter'],
                    'ProductID' => $data['ProductID'],
                    'refundAmount' => $data['refundAmount'] ?? 0
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
                    'refund_amount' => !empty($data['refundAmount']) ? $data['refundAmount'] : null,
                    'refund_date' => !empty($data['refundDate']) ? $data['refundDate'] : null,
                    'reason_of_return' => $data['reasonOfReturn'] ?? null,
                    'return_tn' => $data['returnTN'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'updated_by' => $getCurrentUser(),
                    'updated_at' => now()
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
                        'updated_by' => $getCurrentUser()
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
                        'created_by' => $getCurrentUser()
                    ]);
                }

                // Update product table - move to RTS module and set refund amount
                $refundAmount = !empty($data['refundAmount']) ? $data['refundAmount'] : 0;

                DB::table($this->productTable)
                    ->where('ProductID', $data['ProductID'])
                    ->update([
                        'ProductModuleLoc' => 'RTS',
                        'refund' => $refundAmount
                    ]);

                Log::info('Updated product to RTS module with refund amount', [
                    'ProductID' => $data['ProductID'],
                    'ProductModuleLoc' => 'RTS',
                    'refund' => $refundAmount
                ]);

                $message = 'Test failed. RTS options saved and product moved to RTS module with refund amount set.';
            }

            Log::info('Successfully processed RTS options', [
                'rtcounter' => $data['rtcounter'],
                'ProductID' => $data['ProductID'],
                'testResult' => $data['testResult'],
                'finalStatus' => $data['testResult'] === 'Passed' ? 'Moved to Labeling' : 'Moved to RTS'
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
                    'refundAmount' => $data['testResult'] === 'Passed' ? 0 : ($data['refundAmount'] ?? 0)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving RTS options', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving RTS options: ' . $e->getMessage()
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
                    'message' => 'RT Counter and Product ID are required'
                ], 400);
            }

            // Updated table name without underscore
            $rtsTableName = $this->company . 'tblrts';

            if (!Schema::hasTable($rtsTableName)) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No RTS options found'
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
                    'updated_at' => $rtsOptions->updated_at
                ];

                Log::info('Retrieved RTS options', [
                    'rts_id' => $rtsOptions->rts_id,
                    'rtcounter' => $rtcounter,
                    'ProductID' => $productId,
                    'created_by' => $rtsOptions->created_by,
                    'updated_by' => $rtsOptions->updated_by
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $formData
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No RTS options found for this product'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching RTS options', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'rtcounter' => $rtcounter ?? null,
                'ProductID' => $productId ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching RTS options'
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
            'product' => $product
        ]);
    }
}