<?php 

namespace App\Http\Controllers;

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
            $productsQuery->where(function($query) {
                $query->whereNotIn('prod.ProductModuleLoc', ['Stockroom', 'Shipment', 'Soldlist'])
                      ->where(function($subQuery) {
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
                            $product->capturedImages = (object)[];
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
                                $product->capturedImages = (object)[];
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
                        $product->capturedImages = (object)[];
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
}