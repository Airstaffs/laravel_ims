<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\tblproduct;
use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HouseageController extends BasetablesController
{
    use TracksHistory;

    /**
     * Extract base FNSKU from prefixed FNSKU (same as StockroomController)
     */
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
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $includeImages = $request->boolean('include_images', false);

            $ebayImgColumns = [
                'ebayimgs.img1',  'ebayimgs.img2',  'ebayimgs.img3',
                'ebayimgs.img4',  'ebayimgs.img5',  'ebayimgs.img6',
                'ebayimgs.img7',  'ebayimgs.img8',  'ebayimgs.img9',
                'ebayimgs.img10', 'ebayimgs.img11', 'ebayimgs.img12',
                'ebayimgs.img13', 'ebayimgs.img14', 'ebayimgs.img15',
            ];

            // ── $workLogColumns ────────────────────────────────────────────────────────
            $workLogColumns = [
                // ── Testing (initial) ──────────────────────────────────────────────────
                DB::raw('twl.field_values as testing_field_values'),
                DB::raw('twl.test_result as twl_test_result'),
                DB::raw('twl.tested_by as twl_tested_by'),
                DB::raw('twl.date_tested as twl_date_tested'),
                // ── Repair ─────────────────────────────────────────────────────────────
                'rwl.repaired_by',
                'rwl.date_repaired',
                'rwl.mark_done as repair_done',
                DB::raw('rwl.category_values as repair_category_values'),
                DB::raw('rwl.failed_items as repair_failed_items'),
                // ── Re-Testing (reuses tbltestingworklogs, aliased as rtwl) ───────────
                DB::raw('rtwl.field_values as retest_field_values'),
                DB::raw('rtwl.test_result as retest_result'),
                DB::raw('rtwl.tested_by as retest_by'),
                DB::raw('rtwl.date_tested as retest_date'),
                // ── Cleaning ───────────────────────────────────────────────────────────
                'cwl.cleaned_by',
                'cwl.date_cleaned',
                'cwl.mark_done as cleaning_done',
                DB::raw('cwl.category_values as cleaning_category_values'),
                // ── Packaging ──────────────────────────────────────────────────────────
                'pwl.packed_by',
                'pwl.date_packed',
                DB::raw('pwl.mark_done as packaging_done'),
                DB::raw('pwl.category_values as packaging_category_values'),
                // ── Checklist (received) ───────────────────────────────────────────────
                'chk.pass_fail_result',
                'chk.correct_on_order',
                'chk.condition_on_arrival',
                'chk.condition_notes',
                'chk.date_received',
                'chk.received_by',
                DB::raw('chk.basket_number as chk_basket_number'),
                DB::raw('chk.pcn_number as chk_pcn_number'),
            ];

            // ── Shared checklist join closure ──────────────────────────────────
            $chkJoin = function ($join) {
                $join->on(
                    DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                    '=',
                    DB::raw('CONVERT(chk.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                );
            };

            if (empty($search)) {
                $products = DB::table('tblproduct as prod')
                    ->leftJoin('tblEbayOrderImages as ebayimgs', 'prod.ProductID', '=', 'ebayimgs.ProductID')
                    // Initial test
                    ->leftJoin('tbltestingworklogs as twl', function ($join) {
                        $join->on(
                            DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                            '=',
                            DB::raw('CONVERT(twl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                        )->where(function ($q) {
                            $q->where('twl.test_type', 'initial')
                                ->orWhereNull('twl.test_type');
                        });
                    })
                    // Repair
                    ->leftJoin('tblrepairworklogs as rwl',
                        DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                        '=',
                        DB::raw('CONVERT(rwl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                    )
                    // Re-test (same table, different alias + test_type filter)
                    ->leftJoin('tbltestingworklogs as rtwl', function ($join) {
                        $join->on(
                            DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                            '=',
                            DB::raw('CONVERT(rtwl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                        )->where('rtwl.test_type', 'retest');
                    })
                    ->leftJoin('tblcleaningWorkLogs as cwl',
                        DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                        '=',
                        DB::raw('CONVERT(cwl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                    )
                    ->leftJoin('tblpackagingworklogs as pwl',
                        DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                        '=',
                        DB::raw('CONVERT(pwl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                    )
                    ->leftJoin('tblreceivedchecklist as chk', $chkJoin)
                    ->select(array_merge($workLogColumns, ['prod.*'], $ebayImgColumns))
                    ->orderBy('prod.ProductID', 'desc')
                    ->paginate($perPage);

                $products->getCollection()->transform(function ($product) {
                    $product->company = $this->company;
                    $product->ASIN = null;
                    $product->MSKU = $product->MSKUviewer;
                    $product->FNSKU = $product->FNSKUviewer;
                    $product->grading = null;
                    $product->storename = null;
                    $product->AStitle = $product->ProductTitle;
                    $product->internal = null;
                    $product->system_title = null;
                    $product->metakeyword = null;
                    $product->img1_source = ! empty($product->img1) ? 'ebay' : null;

                    return $product;
                });

            } else {
                $products = DB::table('tblproduct as prod')
                    ->leftJoin('tblfnsku as fnsku', 'prod.MSKUviewer', '=', 'fnsku.MSKU')
                    ->leftJoin('tblasin as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                    ->leftJoin('tblEbayOrderImages as ebayimgs', 'prod.ProductID', '=', 'ebayimgs.ProductID')
                    // Initial test
                    ->leftJoin('tbltestingworklogs as twl', function ($join) {
                        $join->on(
                            DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                            '=',
                            DB::raw('CONVERT(twl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                        )->where(function ($q) {
                            $q->where('twl.test_type', 'initial')
                                ->orWhereNull('twl.test_type');
                        });
                    })
                    // Repair
                    ->leftJoin('tblrepairworklogs as rwl',
                        DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                        '=',
                        DB::raw('CONVERT(rwl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                    )
                    // Re-test (same table, different alias + test_type filter)
                    ->leftJoin('tbltestingworklogs as rtwl', function ($join) {
                        $join->on(
                            DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                            '=',
                            DB::raw('CONVERT(rtwl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                        )->where('rtwl.test_type', 'retest');
                    })
                    ->leftJoin('tblcleaningWorkLogs as cwl',
                        DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                        '=',
                        DB::raw('CONVERT(cwl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                    )
                    ->leftJoin('tblpackagingworklogs as pwl',
                        DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                        '=',
                        DB::raw('CONVERT(pwl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
                    )
                    ->leftJoin('tblreceivedchecklist as chk', $chkJoin)
                    ->select(array_merge($workLogColumns, [
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
                    ], $ebayImgColumns))
                    ->where(function ($q) use ($search) {
                        $q->where('prod.serialnumber', 'like', "%{$search}%")
                            ->orWhere('prod.ProductTitle', 'like', "%{$search}%")
                            ->orWhere('prod.rtid', 'like', "%{$search}%")
                            ->orWhere('prod.itemnumber', 'like', "%{$search}%")
                            ->orWhere('prod.trackingnumber', 'like', '%'.substr($search, -12).'%')
                            ->orWhere('prod.PCN', 'like', "%{$search}%")
                            ->orWhere('prod.RPN', 'like', "%{$search}%")
                            ->orWhere('prod.PRD', 'like', "%{$search}%")
                            ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                            ->orWhere('prod.MSKUviewer', 'like', "%{$search}%")
                            ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                            ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                            ->orWhere('fnsku.MSKU', 'like', "%{$search}%")
                            ->orWhere('fnsku.FNSKU', 'like', "%{$search}%")
                            ->orWhere('asin.internal', 'like', "%{$search}%")
                            ->orWhere('asin.system_title', 'like', "%{$search}%")
                            ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                    })
                    ->orderBy('prod.ProductID', 'desc')
                    ->paginate($perPage);

                $products->getCollection()->transform(function ($product) {
                    $product->company = $this->company;
                    if (empty($product->FNSKU) && ! empty($product->FNSKUviewer)) {
                        $product->FNSKU = $product->FNSKUviewer;
                    }
                    if (empty($product->MSKU) && ! empty($product->MSKUviewer)) {
                        $product->MSKU = $product->MSKUviewer;
                    }
                    $product->img1_source = ! empty($product->img1) ? 'ebay' : null;

                    return $product;
                });
            }

            Log::info('Products fetched', [
                'count' => $products->count(),
                'total' => $products->total(),
                'has_search' => ! empty($search),
            ]);

            if ($includeImages) {
                $this->attachImages($products);
            } else {
                $products->getCollection()->transform(function ($product) {
                    $product->capturedImages = (object) [];

                    return $product;
                });
            }

            return response()->json($products);

        } catch (\Exception $e) {
            Log::error('Error in HouseageController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => true,
                'message' => 'An error occurred while fetching products',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Attach images to products collection (extracted for cleaner code)
     */
    private function attachImages($products)
    {
        try {
            $productIds = $products->pluck('ProductID')->toArray();

            if (empty($productIds)) {
                $products->getCollection()->transform(function ($product) {
                    $product->capturedImages = (object) [];

                    return $product;
                });

                return;
            }

            Log::info('Fetching images', ['count' => count($productIds)]);

            if (! Schema::hasTable('tblcapturedimages')) {
                $products->getCollection()->transform(function ($product) {
                    $product->capturedImages = (object) [];

                    return $product;
                });

                return;
            }

            // ✅ Use keyBy() for O(1) lookup instead of foreach loop
            $capturedImages = DB::table('tblcapturedimages')
                ->whereIn('ProductID', $productIds)
                ->get()
                ->keyBy('ProductID');

            $products->getCollection()->transform(function ($product) use ($capturedImages) {
                $capturedImg = $capturedImages->get($product->ProductID);

                if ($capturedImg) {
                    $capturedImagesObj = [];

                    // Captured images 1-12
                    for ($i = 1; $i <= 12; $i++) {
                        $field = "capturedimg{$i}";
                        if (! empty($capturedImg->$field) && $capturedImg->$field !== 'NULL') {
                            $capturedImagesObj[$field] = $capturedImg->$field;
                        }
                    }

                    // Serial images 1-5
                    for ($i = 1; $i <= 5; $i++) {
                        $field = "serialimg{$i}";
                        if (! empty($capturedImg->$field) && $capturedImg->$field !== 'NULL') {
                            $capturedImagesObj[$field] = $capturedImg->$field;
                        }
                    }

                    // Tracking images 1-2
                    for ($i = 1; $i <= 2; $i++) {
                        $field = "trackingimg{$i}";
                        if (! empty($capturedImg->$field) && $capturedImg->$field !== 'NULL') {
                            $capturedImagesObj[$field] = $capturedImg->$field;
                        }
                    }

                    $product->capturedImages = (object) $capturedImagesObj;
                } else {
                    $product->capturedImages = (object) [];
                }

                return $product;
            });

        } catch (\Exception $e) {
            Log::error('Error fetching images', ['message' => $e->getMessage()]);
            $products->getCollection()->transform(function ($product) {
                $product->capturedImages = (object) [];

                return $product;
            });
        }
    }

    public function store(Request $request)
    {
        try {
            // Sanitize serial number before validation
            if ($request->has('serialnumber')) {
                $sn = Str::upper(trim((string) $request->input('serialnumber')));
                $request->merge(['serialnumber' => $sn !== '' ? $sn : null]);
            }

            // Sanitize other serial number fields
            $serialFields = ['serialnumberb', 'serialnumberc', 'serialnumberd'];
            foreach ($serialFields as $field) {
                if ($request->has($field)) {
                    $value = Str::upper(trim((string) $request->input($field)));
                    $request->merge([$field => $value !== '' ? $value : null]);
                }
            }

            $validated = $request->validate([
                'itemnumber' => 'required|string|max:255|unique:'.$this->productTable.',itemnumber',
                'ProductTitle' => 'nullable|string|max:255',
                'rtid' => 'nullable|string|max:255',
                'orderdate' => 'nullable|date',
                'paymentdate' => 'nullable|date',
                'shipdate' => 'nullable|date',
                'datedelivered' => 'nullable|date',
                'seller' => 'nullable|string|max:255',
                'materialtype' => 'nullable|string|max:255',
                'sourceType' => 'nullable|string|max:255',
                'carrier' => 'nullable|string|max:255',
                'listedcondition' => 'nullable|string|max:255',
                'paymentmethod' => 'nullable|string|max:255',
                'quantity' => 'nullable|numeric',
                'Discount' => 'nullable|numeric',
                'tax' => 'nullable|numeric',
                'priceshipping' => 'nullable|numeric',
                'refund' => 'nullable|numeric',
                'description' => 'nullable|string',
                'supplierNotes' => 'nullable|string',
                'employeeNotes' => 'nullable|string',
                'serialnumber' => 'nullable|string|max:255',
                'serialnumberb' => 'nullable|string|max:255',
                'serialnumberc' => 'nullable|string|max:255',
                'serialnumberd' => 'nullable|string|max:255',
                'trackingnumber' => 'nullable|string|max:255',
                'trackingnumber2' => 'nullable|string|max:255',
                'trackingnumber3' => 'nullable|string|max:255',
                'trackingnumber4' => 'nullable|string|max:255',
                'trackingnumber5' => 'nullable|string|max:255',
                'validation' => 'nullable|string|max:255',
                'price' => 'nullable|numeric',
                'RPN' => 'nullable|string',
                'PRD' => 'nullable|string',
                'PCN' => 'nullable|string',
                'basketnumber' => 'nullable|string',
            ]);

            // Set default validation status if not provided
            $validated['validation'] = $validated['validation'] ?? 'unvalidated';

            // Check for duplicate serial number across all serial fields
            $allSerialFields = ['serialnumber', 'serialnumberb', 'serialnumberc', 'serialnumberd'];

            foreach ($allSerialFields as $field) {
                if (! empty($validated[$field])) {
                    $serialValue = Str::upper(trim($validated[$field]));

                    // Check if this serial exists in ANY serial field of ANY product
                    $exists = \App\Models\tblproduct::where(function ($query) use ($allSerialFields, $serialValue) {
                        foreach ($allSerialFields as $checkField) {
                            $query->orWhere($checkField, $serialValue);
                        }
                    })->first();

                    if ($exists) {
                        return response()->json([
                            'success' => false,
                            'message' => "Serial Number '{$serialValue}' is already assigned to another product.",
                            'duplicate_product' => [
                                'ProductID' => $exists->ProductID,
                                'ProductTitle' => $exists->ProductTitle,
                                'rtcounter' => $exists->rtcounter,
                                'serialnumber' => $exists->serialnumber,
                                'itemnumber' => $exists->itemnumber,
                            ],
                        ], 422);
                    }
                }
            }

            // Create new product
            $product = \App\Models\tblproduct::create($validated);

            // Reload the product with all relationships
            $createdProduct = DB::table($this->productTable.' as prod')
                ->leftJoin($this->fnskuTable.' as fnsku', function ($join) {
                    $join->whereRaw(
                        'fnsku.FNSKU = CASE 
                        WHEN prod.FNSKUviewer REGEXP ? 
                        THEN REGEXP_REPLACE(prod.FNSKUviewer, ?, ?)
                        ELSE prod.FNSKUviewer 
                    END',
                        ['^C[0-9]+', '^C[0-9]+', '']
                    );
                })
                ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->leftJoin($this->capturedImagesTable.' as ci', 'prod.ProductID', '=', 'ci.ProductID')
                ->where('prod.ProductID', $product->ProductID)
                ->select([
                    'prod.*',
                    'fnsku.ASIN',
                    'fnsku.MSKU',
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
                    'ci.capturedimg1',
                    'ci.capturedimg2',
                    'ci.capturedimg3',
                    'ci.capturedimg4',
                    'ci.capturedimg5',
                    'ci.capturedimg6',
                    'ci.capturedimg7',
                    'ci.capturedimg8',
                    'ci.capturedimg9',
                    'ci.capturedimg10',
                    'ci.capturedimg11',
                    'ci.capturedimg12',
                    'ci.serialimg1',
                    'ci.serialimg2',
                    'ci.serialimg3',
                    'ci.serialimg4',
                    'ci.serialimg5',
                ])
                ->first();

            // Format capturedImages as an object
            if ($createdProduct) {
                $capturedImages = [];

                for ($i = 1; $i <= 12; $i++) {
                    $field = "capturedimg{$i}";
                    if (! empty($createdProduct->$field)) {
                        $capturedImages[$field] = $createdProduct->$field;
                    }
                }

                if (! empty($createdProduct->serialimg1)) {
                    $capturedImages['serialimg1'] = $createdProduct->serialimg1;
                }

                if (! empty($createdProduct->serialimg2)) {
                    $capturedImages['serialimg2'] = $createdProduct->serialimg2;
                }
                if (! empty($createdProduct->serialimg3)) {
                    $capturedImages['serialimg3'] = $createdProduct->serialimg3;
                }
                if (! empty($createdProduct->serialimg4)) {
                    $capturedImages['serialimg4'] = $createdProduct->serialimg4;
                }
                if (! empty($createdProduct->serialimg5)) {
                    $capturedImages['serialimg5'] = $createdProduct->serialimg5;
                }

                $createdProduct->capturedImages = (object) $capturedImages;
                $createdProduct->FNSKU = $createdProduct->FNSKUviewer;
                $createdProduct->company = $this->company;
            }

            // 🔥 TRACK CREATION
            $employeeName = auth()->user()->username ?? 'System';
            $identifier = "Item #{$validated['itemnumber']}".
                          (! empty($validated['ProductTitle']) ? " - {$validated['ProductTitle']}" : '');

            $this->trackCreate(
                'Houseage',
                $identifier,
                $employeeName
            );

            Log::info('Product created successfully', [
                'ProductID' => $product->ProductID,
                'itemnumber' => $validated['itemnumber'],
                'employee' => $employeeName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Houseage product created successfully',
                'product' => $createdProduct ?? $product,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed during product creation', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error creating Houseage product', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create product',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Find the product
            $product = \App\Models\tblproduct::find($id);

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            // Sanitize serial numbers
            $serialFields = ['serialnumber', 'serialnumberb', 'serialnumberc', 'serialnumberd'];

            foreach ($serialFields as $field) {
                if ($request->has($field)) {
                    $value = Str::upper(trim((string) $request->input($field)));
                    $request->merge([$field => $value !== '' ? $value : null]);
                }
            }

            $validated = $request->validate([
                'itemnumber' => 'sometimes|required|string|max:255',
                'ProductTitle' => 'nullable|string|max:255',
                'rtid' => 'nullable|string|max:255',
                'orderdate' => 'nullable|date',
                'paymentdate' => 'nullable|date',
                'shipdate' => 'nullable|date',
                'datedelivered' => 'nullable|date',
                'seller' => 'nullable|string|max:255',
                'materialtype' => 'nullable|string|max:255',
                'sourceType' => 'nullable|string|max:255',
                'carrier' => 'nullable|string|max:255',
                'listedcondition' => 'nullable|string|max:255',
                'paymentmethod' => 'nullable|string|max:255',
                'quantity' => 'nullable|numeric',
                'Discount' => 'nullable|numeric',
                'tax' => 'nullable|numeric',
                'priceshipping' => 'nullable|numeric',
                'refund' => 'nullable|numeric',
                'description' => 'nullable|string',
                'supplierNotes' => 'nullable|string',
                'employeeNotes' => 'nullable|string',
                'serialnumber' => 'nullable|string|max:255',
                'serialnumberb' => 'nullable|string|max:255',
                'serialnumberc' => 'nullable|string|max:255',
                'serialnumberd' => 'nullable|string|max:255',
                'trackingnumber' => 'nullable|string|max:255',
                'trackingnumber2' => 'nullable|string|max:255',
                'trackingnumber3' => 'nullable|string|max:255',
                'trackingnumber4' => 'nullable|string|max:255',
                'trackingnumber5' => 'nullable|string|max:255',
                'validation' => 'nullable|string|max:255',
                'price' => 'nullable|numeric',
                'RPN' => 'nullable|string',
                'PRD' => 'nullable|string',
                'PCN' => 'nullable|string',
                'basketnumber' => 'nullable|string',
                'ProductModuleLoc' => 'nullable|string|max:255',
            ]);

            // Only move the item if it's currently in Labeling
            if (isset($validated['ProductModuleLoc']) && in_array($validated['ProductModuleLoc'], ['Labeling', 'Supplies', 'Components', 'Office Equipment'])
            ) {
                $materialTypeMap = [
                    'Inventory' => 'Labeling',
                    'Supplies' => 'Supplies',
                    'Components' => 'Components',
                    'Office Equipment' => 'Office Equipment',
                ];

                if (isset($validated['materialtype']) && isset($materialTypeMap[$validated['materialtype']])) {
                    $validated['ProductModuleLoc'] = $materialTypeMap[$validated['materialtype']];
                }
            }

            // Check each serial number field separately, only if it's being changed
            foreach ($serialFields as $field) {
                // Skip if field is not in validated data or is empty
                if (! array_key_exists($field, $validated) || empty($validated[$field])) {
                    continue;
                }

                // Normalize both values for comparison
                $newSerial = Str::upper(trim($validated[$field]));
                $oldSerial = Str::upper(trim($product->$field ?? ''));

                // Only check for duplicates if the serial number is actually being changed
                if ($newSerial !== '' && $newSerial !== $oldSerial) {
                    // Check if this serial number exists on ANY other product
                    $duplicate = \App\Models\tblproduct::where(function ($query) use ($serialFields, $newSerial) {
                        foreach ($serialFields as $checkField) {
                            $query->orWhere($checkField, $newSerial);
                        }
                    })
                        ->where('ProductID', '!=', $id)
                        ->first();

                    if ($duplicate) {
                        return response()->json([
                            'success' => false,
                            'message' => "Serial Number '{$newSerial}' is already assigned to another product.",
                            'duplicate_product' => [
                                'ProductID' => $duplicate->ProductID,
                                'ProductTitle' => $duplicate->ProductTitle,
                                'rtcounter' => $duplicate->rtcounter,
                                'serialnumber' => $duplicate->serialnumber,
                                'itemnumber' => $duplicate->itemnumber,
                            ],
                        ], 422);
                    }
                }
            }

            // Track what changed
            $changes = [];
            foreach ($validated as $key => $value) {
                // Normalize values for comparison
                $oldValue = $product->$key ?? null;
                $newValue = $value ?? null;

                // For strings, normalize whitespace and case for serial numbers
                if (in_array($key, $serialFields)) {
                    $oldValue = $oldValue ? Str::upper(trim($oldValue)) : null;
                    $newValue = $newValue ? Str::upper(trim($newValue)) : null;
                }

                // Skip if value hasn't changed
                if ($oldValue == $newValue) {
                    continue;
                }

                $oldVal = $oldValue ?? 'null';
                $newVal = $newValue ?? 'null';

                // Format dates nicely
                if (in_array($key, ['orderdate', 'paymentdate', 'shipdate', 'datedelivered'])) {
                    $oldDisplay = ($oldVal !== 'null' && $oldVal) ? date('Y-m-d', strtotime($oldVal)) : 'null';
                    $newDisplay = ($newVal !== 'null' && $newVal) ? date('Y-m-d', strtotime($newVal)) : 'null';
                    $changes[] = "$key: $oldDisplay → $newDisplay";
                }
                // Format numeric values
                elseif (in_array($key, ['price', 'quantity', 'Discount', 'tax', 'priceshipping', 'refund'])) {
                    $changes[] = "$key: $oldVal → $newVal";
                }
                // Truncate long text values
                else {
                    $oldDisplay = (strlen($oldVal) > 30) ? substr($oldVal, 0, 27).'...' : $oldVal;
                    $newDisplay = (strlen($newVal) > 30) ? substr($newVal, 0, 27).'...' : $newVal;
                    $changes[] = "$key: $oldDisplay → $newDisplay";
                }
            }

            // Update the product
            $product->update($validated);

            // ✅ Reload the product with capturedImages
            $updatedProduct = DB::table($this->productTable.' as prod')
                ->leftJoin($this->fnskuTable.' as fnsku', function ($join) {
                    $join->whereRaw(
                        'fnsku.FNSKU = CASE 
                        WHEN prod.FNSKUviewer REGEXP ? 
                        THEN REGEXP_REPLACE(prod.FNSKUviewer, ?, ?)
                        ELSE prod.FNSKUviewer 
                    END',
                        ['^C[0-9]+', '^C[0-9]+', '']
                    );
                })
                ->leftJoin($this->asinTable.' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->leftJoin($this->capturedImagesTable.' as ci', 'prod.ProductID', '=', 'ci.ProductID')
                ->where('prod.ProductID', $id)
                ->select([
                    'prod.*',
                    'fnsku.ASIN',
                    'fnsku.MSKU',
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
                    'ci.capturedimg1',
                    'ci.capturedimg2',
                    'ci.capturedimg3',
                    'ci.capturedimg4',
                    'ci.capturedimg5',
                    'ci.capturedimg6',
                    'ci.capturedimg7',
                    'ci.capturedimg8',
                    'ci.capturedimg9',
                    'ci.capturedimg10',
                    'ci.capturedimg11',
                    'ci.capturedimg12',
                    'ci.serialimg1',
                    'ci.serialimg2',
                ])
                ->first();

            // ✅ Format capturedImages as an object
            if ($updatedProduct) {
                $capturedImages = [];

                for ($i = 1; $i <= 12; $i++) {
                    $field = "capturedimg{$i}";
                    if (! empty($updatedProduct->$field)) {
                        $capturedImages[$field] = $updatedProduct->$field;
                    }
                }

                if (! empty($updatedProduct->serialimg1)) {
                    $capturedImages['serialimg1'] = $updatedProduct->serialimg1;
                }

                if (! empty($updatedProduct->serialimg2)) {
                    $capturedImages['serialimg2'] = $updatedProduct->serialimg2;
                }

                $updatedProduct->capturedImages = (object) $capturedImages;
                $updatedProduct->FNSKU = $updatedProduct->FNSKUviewer;
                $updatedProduct->company = $this->company;
            }

            // Add history tracking
            if (! empty($changes)) {
                $employeeName = auth()->user()->username ?? 'System';

                // Build RT# prefix using ProductID
                $rtPrefix = "RT#{$product->ProductID} | ";

                // Build separate before/after strings
                $beforeParts = [];
                $afterParts = [];

                foreach ($changes as $change) {
                    // Parse "fieldname: oldvalue → newvalue"
                    if (preg_match('/^(.+?): (.+?) → (.+)$/', $change, $matches)) {
                        $field = $matches[1];
                        $oldValue = $matches[2];
                        $newValue = $matches[3];

                        $beforeParts[] = "$field: $oldValue";
                        $afterParts[] = "$field: $newValue";
                    }
                }

                // Add RT# prefix to both before and after (limit to 5 changes)
                $beforeString = $rtPrefix.implode(', ', array_slice($beforeParts, 0, 5));
                $afterString = $rtPrefix.implode(', ', array_slice($afterParts, 0, 5));

                // Add count if more than 5 changes
                $changeCount = count($changes);
                if ($changeCount > 5) {
                    $beforeString .= ' (+'.($changeCount - 5).' more)';
                    $afterString .= ' (+'.($changeCount - 5).' more)';
                }

                $identifier = "Item #{$product->itemnumber}".
                              (! empty($product->ProductTitle) ? " - {$product->ProductTitle}" : '');

                $this->trackUpdate(
                    'Houseage',
                    $identifier,
                    $beforeString,  // BEFORE column with RT# prefix
                    $afterString,   // AFTER column with RT# prefix
                    $employeeName
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Houseage product updated successfully',
                'product' => $updatedProduct,
                'changes_made' => count($changes),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating Houseage product', [
                'ProductID' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update product',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkDuplicateSerial(Request $request)
    {
        $serial = $request->input('serial');
        $currentProductId = $request->input('current_product_id');
        $currentSerialField = $request->input('serial_field'); // e.g., 'serialnumbera' or 'serialnumberb'

        if (empty($serial)) {
            return response()->json(['duplicate' => false]);
        }

        // Get all serial columns from the products table
        $cols = array_filter(
            Schema::getColumnListing($this->productTable),
            fn ($c) => str_starts_with($c, 'serialnumber')
        );

        // Check 1: Duplicate across different products
        // Exclude records where ProductModuleLoc is rts, soldlist, returnlist, or Merged
        $query = DB::table($this->productTable)
            ->select('*')
            ->where(function ($q) use ($cols, $serial) {
                foreach ($cols as $c) {
                    $q->orWhere($c, $serial);
                }
            })
            ->whereNotIn('ProductModuleLoc', ['rts', 'soldlist', 'returnlist', 'Merged']);

        // Exclude the current product if provided
        if (! empty($currentProductId)) {
            $query->where('ProductID', '!=', $currentProductId);
        }

        $existing = $query->first();

        if ($existing) {
            return response()->json([
                'duplicate' => true,
                'type' => 'cross_product',
                'message' => 'This serial number already exists in another product.',
                'product' => $existing,
            ]);
        }

        // Check 2: Duplicate within the same product (Serial A vs Serial B vs Serial C, etc.)
        if (! empty($currentProductId) && ! empty($currentSerialField)) {
            $product = DB::table($this->productTable)
                ->where('ProductID', $currentProductId)
                ->first();

            if ($product) {
                // Get other serial fields to compare against
                $otherSerialFields = array_filter($cols, fn ($c) => $c !== $currentSerialField);

                foreach ($otherSerialFields as $otherField) {
                    if (isset($product->$otherField) && trim($product->$otherField) !== '' && $serial === $product->$otherField) {
                        // Extract labels for better error message
                        $currentLabel = strtoupper(str_replace('serialnumber', '', $currentSerialField));
                        $otherLabel = strtoupper(str_replace('serialnumber', '', $otherField));

                        return response()->json([
                            'duplicate' => true,
                            'type' => 'same_product',
                            'message' => "Serial {$currentLabel} and Serial {$otherLabel} cannot have the same value.",
                            'conflicting_field' => $otherField,
                            'current_field' => $currentSerialField,
                        ]);
                    }
                }
            }
        }

        return response()->json(['duplicate' => false]);
    }

    public function uploadSerialNumber(Request $request)
    {
        // Enhanced validation
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,jpg,png,webp,avif|max:5120',
            'product_id' => 'required|integer',
            'old_path' => 'nullable|string',
            'serial_number' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('image');

        // Verify file extension is in allowed list
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

        if (! in_array($ext, $allowedExtensions)) {
            return response()->json([
                'message' => 'Invalid file type.',
                'errors' => ['image' => ['Only '.implode(', ', $allowedExtensions).' files are allowed.']],
            ], 422);
        }

        // Get company folder
        $company = $request->input('company', $this->company ?? 'Airstaffs');
        $targetDir = public_path('images/product_images/'.$company);

        if (! File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        // Get product ID for filename
        $productId = (int) $request->input('product_id');

        // Verify product exists
        if (! class_exists(tblproduct::class)) {
            return response()->json([
                'message' => 'Product model not found.',
            ], 500);
        }

        $product = tblproduct::find($productId);
        if (! $product) {
            return response()->json([
                'message' => 'Product not found.',
                'errors' => ['product_id' => ['Invalid product ID.']],
            ], 404);
        }

        // Check capturedImages table to determine serial number (serial1 or serial2)
        $capturedImagesTable = $this->capturedImagesTable ?? 'tblcapturedimages';
        $serialNumber = 1; // Default to serial1

        if (Schema::hasTable($capturedImagesTable)) {
            $capturedImage = DB::table($capturedImagesTable)
                ->where('ProductID', $productId)
                ->first();

            if ($capturedImage) {
                // If serialimg1 is empty, use serial1
                if (empty($capturedImage->serialimg1)) {
                    $serialNumber = 1;
                }
                // If serialimg1 exists but serialimg2 is empty, use serial2
                elseif (empty($capturedImage->serialimg2)) {
                    $serialNumber = 2;
                } elseif (empty($capturedImage->serialimg3)) {
                    $serialNumber = 3;
                } elseif (empty($capturedImage->serialimg4)) {
                    $serialNumber = 4;
                } elseif (empty($capturedImage->serialimg5)) {
                    $serialNumber = 5;
                }
                // If both exist, replace serial1
                else {
                    $serialNumber = 1;
                }
            }
        }

        // Generate filename as ProductID_serial1.png or ProductID_serial2.png
        $filename = "{$productId}_serial{$serialNumber}.{$ext}";
        $absPath = $targetDir.DIRECTORY_SEPARATOR.$filename;

        // Check if file already exists and log it
        $isReplacement = File::exists($absPath);
        if ($isReplacement) {
            Log::info('Replacing existing serial image', [
                'product_id' => $productId,
                'filename' => $filename,
                'company' => $company,
                'serial_number' => $serialNumber,
            ]);
            @File::delete($absPath);
        }

        // Move uploaded file
        try {
            $file->move($targetDir, $filename);
        } catch (\Exception $e) {
            Log::error('Failed to move uploaded file', [
                'error' => $e->getMessage(),
                'product_id' => $productId,
                'company' => $company,
            ]);

            return response()->json([
                'message' => 'Failed to save image file.',
                'errors' => ['image' => ['Could not save the uploaded image.']],
            ], 500);
        }

        // Use new path structure
        $relativePath = 'images/product_images/'.$company.'/'.$filename;
        $url = asset($relativePath);

        // Delete old files with different naming patterns
        if ($request->filled('old_path')) {
            $oldInput = (string) $request->old_path;
            $oldPath = ltrim(parse_url($oldInput, PHP_URL_PATH) ?: $oldInput, '/');

            $oldAbs = public_path($oldPath);
            if (File::exists($oldAbs) && $oldAbs !== $absPath) {
                @File::delete($oldAbs);
                Log::info('Deleted old serial image', ['path' => $oldPath]);
            }
        }

        // Also clean up any old serial-number-based filenames for this product
        $serialRaw = trim((string) $request->input('serial_number'));
        $serialSan = preg_replace('/[^A-Za-z0-9._-]+/', '_', $serialRaw);
        $serialSan = ltrim($serialSan, '.');

        if ($serialSan !== '') {
            $oldSerialPatterns = [
                "images/serimg/{$serialSan}.*",
                "images/product_images/{$company}/{$serialSan}.*",
            ];

            foreach ($oldSerialPatterns as $pattern) {
                foreach (glob(public_path($pattern)) as $oldFile) {
                    if ($oldFile !== $absPath) {
                        @File::delete($oldFile);
                        Log::info('Cleaned up old serial-based filename', ['file' => $oldFile]);
                    }
                }
            }
        }

        // ✅ FIXED: Update capturedImages table without timestamps
        if (Schema::hasTable($capturedImagesTable)) {
            $capturedImage = DB::table($capturedImagesTable)
                ->where('ProductID', $productId)
                ->first();

            $fieldToUpdate = "serialimg{$serialNumber}";

            if ($capturedImage) {
                DB::table($capturedImagesTable)
                    ->where('ProductID', $productId)
                    ->update([
                        $fieldToUpdate => $filename,
                    ]);

                Log::info('Updated serial image in capturedImages table', [
                    'ProductID' => $productId,
                    'field' => $fieldToUpdate,
                    'filename' => $filename,
                ]);
            } else {
                DB::table($capturedImagesTable)->insert([
                    'ProductID' => $productId,
                    $fieldToUpdate => $filename,
                ]);

                Log::info('Created new record in capturedImages table', [
                    'ProductID' => $productId,
                    'field' => $fieldToUpdate,
                    'filename' => $filename,
                ]);
            }
        }

        // History tracking
        $employeeName = auth()->user()->username ?? 'System';
        $productInfo = " for Item #{$product->itemnumber}";
        $actionDescription = $isReplacement ? 'Replaced serial image' : 'Uploaded serial image';

        $this->trackUpdate(
            'Houseage',
            "Product ID: {$productId}{$productInfo}",
            "{$actionDescription} (serial{$serialNumber})",
            $filename,
            $employeeName
        );

        return response()->json([
            'message' => 'Uploaded',
            'path' => $relativePath,
            'url' => $url,
            'product_id' => $productId,
            'serial_number' => $serialNumber,
            'filename' => $filename,
            'company' => $company,
            'replaced' => $isReplacement,
        ]);
    }

    public function getSerialImage(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'company' => 'nullable|string',
        ]);

        $productId = (int) $request->input('product_id');
        $company = $request->input('company', $this->company ?? 'Airstaffs');

        // ✅ Check for ProductID_serial1.* and ProductID_serial2.* files
        $dir = public_path('images/product_images/'.$company);

        $serialImages = [];

        // Check for serial1 and serial2
        foreach ([1, 2] as $serialNum) {
            $candidates = [
                "{$dir}/{$productId}_serial{$serialNum}.jpg",
                "{$dir}/{$productId}_serial{$serialNum}.jpeg",
                "{$dir}/{$productId}_serial{$serialNum}.png",
                "{$dir}/{$productId}_serial{$serialNum}.webp",
                "{$dir}/{$productId}_serial{$serialNum}.avif",
            ];

            foreach ($candidates as $abs) {
                if (File::exists($abs)) {
                    $rel = 'images/product_images/'.$company.'/'.basename($abs);
                    $serialImages["serial{$serialNum}"] = [
                        'url' => asset($rel),
                        'path' => $rel,
                        'filename' => basename($abs),
                    ];
                    break; // Found this serial image, move to next
                }
            }
        }

        if (! empty($serialImages)) {
            return response()->json([
                'exists' => true,
                'product_id' => $productId,
                'company' => $company,
                'images' => $serialImages,
                // Return primary image (serial1 if exists, otherwise serial2)
                'url' => $serialImages['serial1']['url'] ?? $serialImages['serial2']['url'] ?? null,
                'path' => $serialImages['serial1']['path'] ?? $serialImages['serial2']['path'] ?? null,
            ]);
        }

        // ✅ Fallback: Check old serial-number-based naming (for backward compatibility)
        // This would require the serial_number parameter, so we'll skip this for now
        // or you can add it as optional parameter if needed

        return response()->json(['exists' => false]);
    }

    public function uploadMultipleImages(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'images' => 'required|array|min:1|max:12',
                'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max per image
                'productId' => 'required|string|max:255',
                'imageNumbers' => 'required|array',
                'imageNumbers.*' => 'required|integer|min:1|max:12',
                'imageType' => 'required|string|in:tracking,captured,serial',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $images = $request->file('images');
            $productId = $request->input('productId');
            $imageNumbers = $request->input('imageNumbers');
            $imageType = $request->input('imageType');

            // Validate array lengths match
            if (count($images) !== count($imageNumbers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Images and imageNumbers arrays must have the same length',
                ], 422);
            }

            // Validate image count based on type
            $maxAllowed = $this->getMaxImagesForType($imageType);
            foreach ($imageNumbers as $num) {
                if ($num > $maxAllowed) {
                    return response()->json([
                        'success' => false,
                        'message' => "{$imageType} images only support up to {$maxAllowed} images",
                    ], 422);
                }
            }

            // Create directory if it doesn't exist
            $uploadPath = public_path('images/product_images/Airstaffs');
            if (! File::exists($uploadPath)) {
                File::makeDirectory($uploadPath, 0775, true);
            }

            // Sanitize productId for filename
            $safeProductId = preg_replace('/[^a-zA-Z0-9_-]/', '', $productId);

            // Process uploads
            $results = [];
            $dbUpdates = [];

            DB::beginTransaction();

            try {
                foreach ($images as $index => $image) {
                    $imageNumber = $imageNumbers[$index];

                    try {
                        // Process single image
                        $result = $this->processSingleImage(
                            $image,
                            $safeProductId,
                            $imageNumber,
                            $imageType,
                            $uploadPath
                        );

                        if ($result['success']) {
                            $dbUpdates[$result['columnName']] = $result['filename'];
                            $results[] = [
                                'success' => true,
                                'filename' => $result['filename'],
                                'imageNumber' => $imageNumber,
                                'message' => "Image {$imageNumber} uploaded successfully",
                            ];
                        } else {
                            $results[] = [
                                'success' => false,
                                'imageNumber' => $imageNumber,
                                'message' => $result['message'],
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Individual image upload error', [
                            'productId' => $productId,
                            'imageNumber' => $imageNumber,
                            'error' => $e->getMessage(),
                        ]);

                        $results[] = [
                            'success' => false,
                            'imageNumber' => $imageNumber,
                            'message' => 'Failed to process image',
                        ];
                    }
                }

                // Batch update database if any images were successful
                if (! empty($dbUpdates)) {
                    $dbUpdates['UpdatedAt'] = now();

                    DB::table('tblcapturedimages')->updateOrInsert(
                        ['ProductID' => $productId],
                        $dbUpdates
                    );
                }

                DB::commit();

                // Count successes
                $successCount = collect($results)->where('success', true)->count();
                $failCount = count($results) - $successCount;

                Log::info('Multiple images uploaded', [
                    'ProductID' => $productId,
                    'imageType' => $imageType,
                    'totalProcessed' => count($results),
                    'successful' => $successCount,
                    'failed' => $failCount,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "{$successCount} image(s) uploaded successfully".
                                ($failCount > 0 ? ", {$failCount} failed" : ''),
                    'results' => $results,
                    'summary' => [
                        'total' => count($results),
                        'successful' => $successCount,
                        'failed' => $failCount,
                    ],
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Throwable $th) {
            Log::error('Multiple image upload error: '.$th->getMessage(), [
                'productId' => $request->input('productId'),
                'imageType' => $request->input('imageType'),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images',
                'error' => config('app.debug') ? $th->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Process a single image upload
     */
    private function processSingleImage($image, $safeProductId, $imageNumber, $imageType, $uploadPath)
    {
        // Get extension
        $extension = $image->getClientOriginalExtension();

        // Generate filename based on type
        if ($imageType === 'captured') {
            $filename = "{$safeProductId}_img{$imageNumber}.{$extension}";
            $searchPattern = "{$uploadPath}/{$safeProductId}_img{$imageNumber}.*";
            $columnName = "capturedimg{$imageNumber}";
        } else {
            $filename = "{$safeProductId}_{$imageType}{$imageNumber}.{$extension}";
            $searchPattern = "{$uploadPath}/{$safeProductId}_{$imageType}{$imageNumber}.*";
            $columnName = "{$imageType}img{$imageNumber}";
        }

        // Remove old files with different extensions
        $this->removeOldFiles($searchPattern);

        // Move file to destination
        $moved = $image->move($uploadPath, $filename);

        if (! $moved) {
            return [
                'success' => false,
                'message' => 'Failed to move uploaded file',
            ];
        }

        return [
            'success' => true,
            'filename' => $filename,
            'columnName' => $columnName,
        ];
    }

    /**
     * Remove old files matching pattern
     */
    private function removeOldFiles($searchPattern)
    {
        $oldFiles = glob($searchPattern);
        if ($oldFiles !== false) {
            foreach ($oldFiles as $oldFile) {
                if (file_exists($oldFile) && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
        }
    }

    /**
     * Get max images allowed for type
     */
    private function getMaxImagesForType($imageType)
    {
        return match ($imageType) {
            'tracking' => 2,
            'serial' => 5,
            'captured' => 12,
            default => 12,
        };
    }

    /**
     * Delete a single product image
     */
    public function deleteImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'productId' => 'required|string|max:255',
                'capturedImgCount' => 'required|integer|min:1|max:12',
                'imageType' => 'required|string|in:tracking,captured,serial',
            ]);

            $productId = $validated['productId'];
            $imageNumber = $validated['capturedImgCount'];
            $imageType = $validated['imageType'];

            // Sanitize productId
            $safeProductId = preg_replace('/[^a-zA-Z0-9_-]/', '', $productId);

            // Build search pattern
            $uploadPath = public_path('images/product_images/Airstaffs');

            if ($imageType === 'captured') {
                $searchPattern = "{$uploadPath}/{$safeProductId}_img{$imageNumber}.*";
                $columnName = "capturedimg{$imageNumber}";
            } else {
                $searchPattern = "{$uploadPath}/{$safeProductId}_{$imageType}{$imageNumber}.*";
                $columnName = "{$imageType}img{$imageNumber}";
            }

            // Delete files
            $filesDeleted = false;
            $oldFiles = glob($searchPattern);
            if ($oldFiles !== false && count($oldFiles) > 0) {
                foreach ($oldFiles as $oldFile) {
                    if (file_exists($oldFile) && is_file($oldFile)) {
                        @unlink($oldFile);
                        $filesDeleted = true;
                    }
                }
            }

            // Update database - set to NULL
            DB::table('tblcapturedimages')
                ->where('ProductID', $productId)
                ->update([
                    $columnName => null,
                    'UpdatedAt' => now(),
                ]);

            Log::info('Product image deleted', [
                'ProductID' => $productId,
                'imageType' => $imageType,
                'imageNumber' => $imageNumber,
                'filesDeleted' => $filesDeleted,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
                'filesDeleted' => $filesDeleted,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            Log::error('Image deletion error: '.$th->getMessage(), [
                'productId' => $request->input('productId'),
                'imageType' => $request->input('imageType'),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
                'error' => config('app.debug') ? $th->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Batch delete multiple images (optional utility method)
     */
    public function deleteMultipleImages(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'productId' => 'required|string|max:255',
                'imageNumbers' => 'required|array|min:1',
                'imageNumbers.*' => 'required|integer|min:1|max:12',
                'imageType' => 'required|string|in:tracking,captured,serial',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $productId = $request->input('productId');
            $imageNumbers = $request->input('imageNumbers');
            $imageType = $request->input('imageType');

            $safeProductId = preg_replace('/[^a-zA-Z0-9_-]/', '', $productId);
            $uploadPath = public_path('images/product_images/Airstaffs');

            $results = [];
            $dbUpdates = [];

            DB::beginTransaction();

            try {
                foreach ($imageNumbers as $imageNumber) {
                    // Build search pattern
                    if ($imageType === 'captured') {
                        $searchPattern = "{$uploadPath}/{$safeProductId}_img{$imageNumber}.*";
                        $columnName = "capturedimg{$imageNumber}";
                    } else {
                        $searchPattern = "{$uploadPath}/{$safeProductId}_{$imageType}{$imageNumber}.*";
                        $columnName = "{$imageType}img{$imageNumber}";
                    }

                    // Delete files
                    $oldFiles = glob($searchPattern);
                    $filesDeleted = false;

                    if ($oldFiles !== false) {
                        foreach ($oldFiles as $oldFile) {
                            if (file_exists($oldFile) && is_file($oldFile)) {
                                @unlink($oldFile);
                                $filesDeleted = true;
                            }
                        }
                    }

                    $dbUpdates[$columnName] = null;

                    $results[] = [
                        'success' => true,
                        'imageNumber' => $imageNumber,
                        'filesDeleted' => $filesDeleted,
                    ];
                }

                // Batch update database
                if (! empty($dbUpdates)) {
                    $dbUpdates['UpdatedAt'] = now();

                    DB::table('tblcapturedimages')
                        ->where('ProductID', $productId)
                        ->update($dbUpdates);
                }

                DB::commit();

                Log::info('Multiple images deleted', [
                    'ProductID' => $productId,
                    'imageType' => $imageType,
                    'count' => count($imageNumbers),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => count($imageNumbers).' image(s) deleted successfully',
                    'results' => $results,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Throwable $th) {
            Log::error('Multiple image deletion error: '.$th->getMessage(), [
                'productId' => $request->input('productId'),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete images',
                'error' => config('app.debug') ? $th->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function deleteCapturedImagesBulk(Request $request)
    {
        try {
            $validated = $request->validate([
                'productId' => 'required|string|max:255',
                'imageNumbers' => 'required|array|min:1',
                'imageNumbers.*' => 'required|integer|min:1|max:12',
                'imageType' => 'required|string|in:tracking,captured,serial',
            ]);

            $productId = $validated['productId'];
            $imageNumbers = array_unique($validated['imageNumbers']);
            $imageType = $validated['imageType'];

            // Validate count per type
            foreach ($imageNumbers as $num) {
                if (in_array($imageType, ['tracking', 'serial']) && $num > 2) {
                    return response()->json([
                        'success' => false,
                        'message' => ucfirst($imageType).' images only support img1 and img2',
                    ], 422);
                }
            }

            $record = DB::table('tblcapturedimages')
                ->where('ProductID', $productId)
                ->first();

            if (! $record) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            $successCount = 0;
            $failCount = 0;
            $updates = ['UpdatedAt' => now()];

            foreach ($imageNumbers as $num) {
                $columnName = $imageType.'img'.$num;
                $filename = $record->{$columnName} ?? null;

                if (! $filename) {
                    $failCount++;

                    continue;
                }

                // Delete file from filesystem
                $filePath = public_path('images/product_images/Airstaffs/'.$filename);
                if (file_exists($filePath) && is_file($filePath)) {
                    if (! @unlink($filePath)) {
                        Log::warning("Bulk delete: failed to delete file: {$filePath}");
                    }
                }

                $updates[$columnName] = null;
                $successCount++;
            }

            // Single DB update for all nullified columns
            if (! empty($updates)) {
                DB::table('tblcapturedimages')
                    ->where('ProductID', $productId)
                    ->update($updates);
            }

            Log::info('Bulk product image delete', [
                'ProductID' => $productId,
                'imageType' => $imageType,
                'imageNumbers' => $imageNumbers,
                'successCount' => $successCount,
                'failCount' => $failCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$successCount} image(s) deleted successfully",
                'successCount' => $successCount,
                'failCount' => $failCount,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            Log::error('Bulk image delete error: '.$th->getMessage(), [
                'productId' => $request->input('productId'),
                'imageType' => $request->input('imageType'),
                'imageNumbers' => $request->input('imageNumbers'),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete images',
                'error' => config('app.debug') ? $th->getMessage() : 'Server error',
            ], 500);
        }
    }
}
