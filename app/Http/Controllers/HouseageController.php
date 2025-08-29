<?php

namespace App\Http\Controllers;

use App\Models\tblproduct;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Rpn;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use DateTime;
use DateTimeZone;

class HouseageController extends BasetablesController
{
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
            $includeImages = $request->boolean('include_images', false);

            // UPDATED: Build query with proper joins to include ASIN and metakeyword in search
            $productsQuery = DB::table($this->productTable . ' as prod')
                ->leftJoin($this->fnskuTable . ' as fnsku', function ($join) {
                    $join->on(DB::raw("CASE 
                        WHEN prod.FNSKUviewer REGEXP '^C[0-9]+' 
                        THEN SUBSTRING(prod.FNSKUviewer, LOCATE(REGEXP_REPLACE(prod.FNSKUviewer, '^C[0-9]+', ''), prod.FNSKUviewer))
                        ELSE prod.FNSKUviewer 
                    END"), '=', 'fnsku.FNSKU');
                })
                ->leftJoin($this->asinTable . ' as asin', 'fnsku.ASIN', '=', 'asin.ASIN')
                ->select([
                    'prod.*',
                    'fnsku.ASIN',
                    'fnsku.MSKU',
                    'fnsku.grading',
                    'fnsku.storename',
                    'asin.internal as AStitle',
                    'asin.metakeyword'
                ]);

            // Apply comprehensive search including ASIN and metakeyword
            if (!empty($search)) {
                $productsQuery->where(function ($q) use ($search) {
                    $q->where('prod.serialnumber', 'like', "%{$search}%")
                        ->orWhere('prod.ProductTitle', 'like', "%{$search}%")
                        ->orWhere('prod.rtid', 'like', "%{$search}%")
                        ->orWhere('prod.itemnumber', 'like', "%{$search}%")
                        ->orWhere('prod.PCN', 'like', "%{$search}%")
                        ->orWhere('prod.RPN', 'like', "%{$search}%")
                        ->orWhere('prod.PRD', 'like', "%{$search}%")
                        ->orWhere('prod.FNSKUviewer', 'like', "%{$search}%")
                        ->orWhere('prod.rtcounter', 'like', "%{$search}%")
                        // Add FNSKU table search
                        ->orWhere('fnsku.ASIN', 'like', "%{$search}%")
                        ->orWhere('fnsku.MSKU', 'like', "%{$search}%")
                        // Add ASIN table search
                        ->orWhere('asin.internal', 'like', "%{$search}%")
                        ->orWhere('asin.metakeyword', 'like', "%{$search}%");
                });
            }

            $products = $productsQuery->paginate($perPage);
            Log::info('Products fetched successfully with joins', ['count' => $products->count()]);

            // Transform products to ensure proper FNSKU display and add missing data
            $products->getCollection()->transform(function ($product) {
                // Keep the original FNSKU as displayed (with prefix if it exists)
                $product->FNSKU = $product->FNSKUviewer;

                // Ensure we have the company for proper path construction
                $product->company = $this->company;

                return $product;
            });

            // If images are requested, fetch them for each product
            if ($includeImages) {
                try {
                    $productIds = $products->pluck('ProductID')->toArray();
                    Log::info('Product IDs for image fetch', ['count' => count($productIds), 'ids' => $productIds]);

                    $capturedImagesTableName = $this->capturedImagesTable;

                    Log::info('Checking table existence', [
                        'table' => $capturedImagesTableName
                    ]);

                    if (!Schema::hasTable($capturedImagesTableName)) {
                        Log::warning('Captured images table does not exist', [
                            'table' => $capturedImagesTableName
                        ]);

                        // Add empty capturedImages object to prevent JS errors
                        $products->getCollection()->transform(function ($product) {
                            $product->capturedImages = (object) [];
                            return $product;
                        });
                    } else {
                        Log::info('Captured images table exists', ['table' => $capturedImagesTableName]);

                        // Fetch all captured images for these products
                        $capturedImages = DB::table($capturedImagesTableName)
                            ->whereIn('ProductID', $productIds)
                            ->get();

                        Log::info('Captured images fetched', [
                            'count' => $capturedImages->count(),
                            'sample' => $capturedImages->take(1)
                        ]);

                        // Create a lookup by ProductID for efficient access
                        $imagesByProductId = [];
                        foreach ($capturedImages as $img) {
                            $imagesByProductId[$img->ProductID] = $img;
                        }

                        // Add capturedImages data to each product
                        $products->getCollection()->transform(function ($product) use ($imagesByProductId) {
                            // Check if we have image data for this product
                            if (isset($imagesByProductId[$product->ProductID])) {
                                $product->capturedImages = $imagesByProductId[$product->ProductID];

                                // Set img1 directly for the main thumbnail display if not already set
                                if (empty($product->img1) && !empty($product->capturedImages->capturedimg1)) {
                                    $product->img1 = $product->capturedImages->capturedimg1;
                                }

                                Log::info('Added captured images to product', [
                                    'ProductID' => $product->ProductID,
                                    'capturedImages' => json_encode($product->capturedImages)
                                ]);
                            } else {
                                Log::info('No captured images found for product', [
                                    'ProductID' => $product->ProductID
                                ]);

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

                    // Continue without images but add empty capturedImages object
                    $products->getCollection()->transform(function ($product) {
                        $product->capturedImages = (object) [];
                        return $product;
                    });
                }
            } else {
                // Even if images are not requested, initialize empty capturedImages
                $products->getCollection()->transform(function ($product) {
                    $product->capturedImages = (object) [];
                    return $product;
                });
            }

            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error in HouseageController index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching products',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $table = (new tblproduct)->getTable();

        if ($request->has('serialnumber')) {
            $sn = Str::upper(trim((string) $request->input('serialnumber')));
            $request->merge(['serialnumber' => $sn !== '' ? $sn : null]);
        }

        $validated = $request->validate([
            'ProductID' => 'required|integer',
            'itemnumber' => 'required|string|max:255',
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

        $validated['validation'] = $validated['validation'] ?? 'unvalidated';

        if (!empty($validated['serialnumber'])) {
            $exists = \App\Models\tblproduct::where('serialnumber', $validated['serialnumber'])
                ->where('itemnumber', '<>', $validated['itemnumber'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Serial Number '{$validated['serialnumber']}' is already assigned to another product."
                ], 422);
            }
        }

        // Proceed with insert/update
        $product = \App\Models\tblproduct::updateOrCreate(
            ['itemnumber' => $validated['itemnumber']],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Houseage product saved successfully',
            'product' => $product
        ]);
    }

    public function checkDuplicateSerial(Request $request)
    {
        $serial = $request->input('serial');

        if (empty($serial)) {
            return response()->json(['duplicate' => false]);
        }

        $cols = array_filter(
            Schema::getColumnListing($this->productTable),
            fn($c) => str_starts_with($c, 'serial')
        );

        $existing = DB::table($this->productTable)
            ->select('ProductID', 'ProductTitle')
            ->where(function ($q) use ($cols, $serial) {
                foreach ($cols as $c) {
                    $q->orWhere($c, $serial);
                }
            })
            ->first();


        if ($existing) {
            return response()->json([
                'duplicate' => true,
                'product_id' => $existing->ProductID,
                'product_title' => $existing->ProductTitle
            ]);
        }

        return response()->json(['duplicate' => false]);
    }

    public function uploadSerialNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,jpg,png,webp,avif|max:5120',
            'product_id' => 'nullable|integer',
            'old_path' => 'nullable|string',
            'serial_number' => 'required|string', // keep: serial is required
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('image');

        $targetDir = public_path('images/serimg');
        if (!File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        // sanitize serial
        $serialRaw = trim((string) $request->input('serial_number'));
        $serialSan = preg_replace('/[^A-Za-z0-9._-]+/', '_', $serialRaw);
        $serialSan = ltrim($serialSan, '.');
        $serialSan = Str::limit($serialSan, 120, '');
        if ($serialSan === '') {
            return response()->json([
                'message' => 'Serial number is invalid after sanitization.',
            ], 422);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $filename = "{$serialSan}.{$ext}";
        $absPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        // ensure we don't collide — replace any existing file with the same name
        if (File::exists($absPath)) {
            @File::delete($absPath);
        }

        // move uploaded file
        $file->move($targetDir, $filename);

        $relativePath = 'images/serimg/' . $filename;
        $url = asset($relativePath);

        // optional: delete any specifically provided old file (your existing rule)
        if ($request->filled('old_path')) {
            $oldInput = (string) $request->old_path;
            $oldPath = ltrim(parse_url($oldInput, PHP_URL_PATH) ?: $oldInput, '/');
            if (str_starts_with($oldPath, 'images/serimg/')) {
                $oldAbs = public_path($oldPath);
                if (File::exists($oldAbs)) {
                    @File::delete($oldAbs);
                }
            }
        }

        // optional: persist to product column if present
        if ($request->filled('product_id') && class_exists(\tblproduct::class)) {
            $product = tblproduct::find((int) $request->product_id);
            if ($product) {
                $table = $product->getTable();
                if (Schema::hasColumn($table, 'serial_number_image')) {
                    $product->serial_number_image = $relativePath;
                    $product->save();
                }
            }
        }

        return response()->json([
            'message' => 'Uploaded',
            'path' => $relativePath,
            'url' => $url,
            'serial_number' => $serialSan,
            'filename' => $filename,
        ]);
    }


}