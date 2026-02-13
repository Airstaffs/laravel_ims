<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceivedController extends BasetablesController
{
    use TracksHistory;

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $location = $request->input('location', 'Received');

        $products = DB::table($this->productTable)
            ->where('ProductModuleLoc', $location)
            ->when($search, function ($query) use ($search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('ProductTitle', 'like', "%{$search}%")
                        ->orWhere('rtid', 'like', "%{$search}%")
                        ->orWhere('itemnumber', 'like', "%{$search}%")
                        ->orWhere('rtcounter', 'like', "%{$search}%")
                        // âœ… FIXED: Search tracking by last 12 digits
                        ->orWhere('trackingnumber', 'like', '%'.substr($search, -12).'%');
                });
            })
            ->paginate($perPage);

        return response()->json($products);
    }

    public function verifyTracking(Request $request)
    {
        $tracking = $request->input('tracking');
        $last12Digits = substr($tracking, -12);

        // -------------------------------------------------
        // 1️⃣ Check tblreconciliation (ALWAYS ALLOW)
        // -------------------------------------------------
        $recon = DB::table('tblreconciliation')
            ->where('trackingnumber', 'like', "%{$last12Digits}%")
            ->orderBy('lastDateUpdate')
            ->first();

        if ($recon) {
            $trackingImageData = $this->getTrackingImagesByTrackingNumber(
                $recon->trackingnumber
            );

            // 🔥 Build productDetails same as Received
            $imageFields = [
                'img1','img2','img3','img4','img5',
                'img6','img7','img8','img9','img10',
                'img11','img12','img13','img14','img15',
            ];

            $productDetails = new \stdClass;

            foreach ($imageFields as $field) {
                if (property_exists($recon, $field) && !empty($recon->$field)) {
                    $productDetails->$field = $recon->$field;
                }
            }

            $last12Digits = substr($recon->trackingnumber, -12);

            // Count reconciliation items
            $reconCount = DB::table('tblreconciliation')
                ->where('trackingnumber', 'like', "%{$last12Digits}%")
                ->count();

            // Check if still exists in Received
            $receivedQty = DB::table($this->productTable)
                ->where('trackingnumber', 'like', "%{$last12Digits}%")
                ->where('ProductModuleLoc', 'Received')
                ->sum('quantity');

            // 🔥 Total batch quantity
            $totalQuantity = $reconCount + $receivedQty;


            return response()->json([
                'found' => true,
                'productId' => null, // important
                'rtcounter' => $recon->rtcounter,
                'trackingnumber' => $recon->trackingnumber,
                'quantity' => $totalQuantity,
                'alreadyScanned' => false,
                'source' => 'Reconciliation',
                'moduleLocation' => 'Reconciliation',
                'productDetails' => $productDetails,
                'hasTrackingImage' => $trackingImageData['hasTrackingImage'],
                'requireTrackingImage' => $trackingImageData['requireTrackingImage'],
                'trackingImages' => $trackingImageData['trackingImages'],
                'reuseTrackingImages' => $trackingImageData['hasTrackingImage'],
            ]);
        }

        // -------------------------------------------------
        // 2️⃣ Check Received
        // -------------------------------------------------
        $receivedProduct = DB::table($this->productTable)
            ->where('trackingnumber', 'like', '%'.$last12Digits.'%')
            ->where('ProductModuleLoc', 'Received')
            ->first();

        // 3️⃣ Check processed modules
        $processedProduct = DB::table($this->productTable)
            ->where('trackingnumber', 'like', '%' . $last12Digits . '%')
            ->whereIn('ProductModuleLoc', ['Labeling', 'Validation'])
            ->orderByDesc('lastDateUpdate')
            ->first();

        /**
         * 🟡 CASE: Partially processed
         */
        if ($processedProduct && $receivedProduct) {
            $trackingImages = $this->getTrackingImagesByTrackingNumber($processedProduct->trackingnumber);

            $imageFields = [
                'img1','img2','img3','img4','img5',
                'img6','img7','img8','img9','img10',
                'img11','img12','img13','img14','img15',
            ];

            $productDetails = new \stdClass;

            foreach ($imageFields as $field) {
                if (property_exists($receivedProduct, $field) && !empty($receivedProduct->$field)) {
                    $productDetails->$field = $receivedProduct->$field;
                }
            }

            return response()->json([
                'found' => true,
                'productId' => $receivedProduct->ProductID,
                'rtcounter' => $receivedProduct->rtcounter,
                'trackingnumber' => $receivedProduct->trackingnumber,
                'quantity' => $receivedProduct->quantity ?? 1,
                'productDetails' => $productDetails,
                'reuseTrackingImages' => true,
                'trackingImages' => $trackingImages,
                'alreadyScanned' => false,
                'moduleLocation' => 'Received',
            ]);
        }

        // -------------------------------------------------
        // 4️⃣ Normal Received Flow
        // -------------------------------------------------
        if ($receivedProduct) {

            // $hasTrackingImages = DB::table($this->capturedImagesTable)
            //     ->where('ProductID', $receivedProduct->ProductID)
            //     ->where(function ($q) {
            //         $q->whereNotNull('trackingimg1')
            //         ->orWhereNotNull('trackingimg2');
            //     })
            //     ->exists();

            $trackingImageData = $this->getTrackingImagesByTrackingNumber(
                $receivedProduct->trackingnumber
            );

            $imageFields = [
                'img1','img2','img3','img4','img5',
                'img6','img7','img8','img9','img10',
                'img11','img12','img13','img14','img15',
            ];

            $productDetails = new \stdClass;

            foreach ($imageFields as $field) {
                if (property_exists($receivedProduct, $field) && !empty($receivedProduct->$field)) {
                    $productDetails->$field = $receivedProduct->$field;
                }
            }

            return response()->json([
                'found' => true,
                'productId' => $receivedProduct->ProductID,
                'rtcounter' => $receivedProduct->rtcounter,
                'trackingnumber' => $receivedProduct->trackingnumber,
                'quantity' => $receivedProduct->quantity ?? 1,
                'productDetails' => $productDetails,

                'hasTrackingImage' => $trackingImageData['hasTrackingImage'],
                'requireTrackingImage' => $trackingImageData['requireTrackingImage'],
                'trackingImages' => $trackingImageData['trackingImages'],
                'reuseTrackingImages' => $trackingImageData['hasTrackingImage'],

                'alreadyScanned' => false,
                'moduleLocation' => 'Received',
            ]);
        }

        // -------------------------------------------------
        // 5️⃣ Fully Processed → BLOCK
        // -------------------------------------------------
        if ($processedProduct) {
            return response()->json([
                'found' => true,
                'productId' => $processedProduct->ProductID,
                'rtcounter' => $processedProduct->rtcounter,
                'trackingnumber' => $processedProduct->trackingnumber,
                'alreadyScanned' => true,
                'moduleLocation' => $processedProduct->ProductModuleLoc,
            ]);
        }

        // -------------------------------------------------
        // 6️⃣ Not Found
        // -------------------------------------------------
        return response()->json([
            'found' => false
        ]);
    }

    private function getTrackingImagesByTrackingNumber(string $trackingNumber)
    {
        $last12Digits = substr($trackingNumber, -12);

        $record = DB::table($this->capturedImagesTable)
            ->join(
                $this->productTable,
                "{$this->productTable}.ProductID",
                '=',
                "{$this->capturedImagesTable}.ProductID"
            )
            ->where("{$this->productTable}.trackingnumber", 'like', "%{$last12Digits}%")
            ->where(function ($q) {
                $q->whereNotNull('trackingimg1')
                ->orWhereNotNull('trackingimg2');
            })
            ->orderByDesc("{$this->capturedImagesTable}.UpdatedAt")
            ->first();

        if (!$record) {
            return [
                'hasTrackingImage' => false,
                'requireTrackingImage' => true,
                'trackingImages' => []
            ];
        }

        return [
            'hasTrackingImage' => true,
            'requireTrackingImage' => false,
            'trackingImages' => [
                'trackingimg1' => $record->trackingimg1,
                'trackingimg2' => $record->trackingimg2,
            ]
        ];
    }


    private function copyTrackingImagesToProduct(string $trackingNumber, int $targetProductId)
    {
        $last12Digits = substr($trackingNumber, -12);

        // 🔍 Find the most complete tracking images for this tracking number
        $source = DB::table($this->capturedImagesTable)
            ->join(
                $this->productTable,
                "{$this->productTable}.ProductID",
                '=',
                "{$this->capturedImagesTable}.ProductID"
            )
            ->where("{$this->productTable}.trackingnumber", 'like', '%' . $last12Digits . '%')
            ->whereNotNull("{$this->capturedImagesTable}.trackingimg1")
            ->orderByDesc("{$this->capturedImagesTable}.UpdatedAt")
            ->first();

        if (!$source) {
            Log::warning('No tracking images found to copy', [
                'tracking' => $trackingNumber,
                'targetProductId' => $targetProductId
            ]);
            return;
        }

        // 🧠 Copy ONLY tracking images (no product images)
        DB::table($this->capturedImagesTable)->updateOrInsert(
            ['ProductID' => $targetProductId],
            [
                'trackingimg1' => $source->trackingimg1,
                'trackingimg2' => $source->trackingimg2,
                'UpdatedAt'    => now(),
                'CreatedAt'    => now(),
            ]
        );

        Log::info('Tracking images copied to new product', [
            'targetProductId' => $targetProductId,
            'trackingimg1' => $source->trackingimg1,
            'trackingimg2' => $source->trackingimg2,
        ]);
    }

    private function getTrackingImagesByProductId(int $productId)
    {
        $record = DB::table($this->capturedImagesTable)
            ->where('ProductID', $productId)
            ->first();

        if (!$record) {
            return [];
        }

        return [
            'trackingimg1' => $record->trackingimg1 ?? null,
            'trackingimg2' => $record->trackingimg2 ?? null,
        ];
    }

    public function validatePcn(Request $request)
    {
        try {
            // Get the PCN from the request
            $pcn = $request->input('pcn');

            if (empty($pcn)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'PCN is required',
                ], 400);
            }

            // Check if PCN exists in the database
            $pcnExists = DB::table($this->productTable)
                ->where('PCN', $pcn)
                ->exists();

            return response()->json([
                'valid' => true, // Format is valid (validated in frontend)
                'alreadyUsed' => $pcnExists,
                'pcn' => $pcn,
            ]);
        } catch (\Exception $e) {
            // Log the error
            $this->logError('Error validating PCN', $e, ['pcn' => $pcn]);

            return response()->json([
                'valid' => false,
                'message' => 'Error validating PCN: '.$e->getMessage(),
            ], 500);
        }
    }

    private function getCurrentUserName()
    {
        $user = Auth::user();

        return $user ? ($user->username ?? $user->name ?? 'Unknown') : 'Unknown';
    }

    public function processScan(Request $request)
    {
        Log::info('Received data:', $request->all());

        try {

            $fullTrackingNumber = $request->trackingNumber;
            $last12Digits = substr($request->trackingNumber, -12);
            $isReconciliation = $request->trackingSource === 'Reconciliation';
            $user = $this->getCurrentUserName();
            $employeeName = auth()->user()->username ?? $user ?? 'System';

            if ($request->status === 'fail') {

                $request->validate([
                    'trackingNumber' => 'required',
                    'status' => 'required|in:fail',
                    'basketNumber' => ['required', 'regex:/^(BKT|SI|ENV)\d+$/i'],
                    'pcnNumber' => ['required', 'regex:/^PCN\d+$/i'],
                    'productId' => $isReconciliation ? 'nullable' : 'required',
                    'rtcounter' => 'required',
                ]);

            } else {

                $request->validate([
                    'trackingNumber' => 'required',
                    'status' => 'required|in:pass',
                    'firstSerialNumber' => ['required', 'regex:/^[A-Z0-9]+$/i'],
                    'secondSerialNumber' => ['required', 'regex:/^(N\/A|[A-Z0-9]+)$/i'],
                    'pcnNumber' => ['required', 'regex:/^PCN\d+$/i'],
                    'basketNumber' => ['required', 'regex:/^(BKT|SI|ENV)\d+$/i'],
                    'productId' => $isReconciliation ? 'nullable' : 'required',
                    'rtcounter' => 'required',
                ]);
            }


            DB::beginTransaction();
            // -----------------------------------------------------
            // 🔥 HANDLE RECONCILIATION DIRECT TO LABELING
            // -----------------------------------------------------
            if (!$request->productId) {

                $reconItem = DB::table('tblreconciliation')
                    ->where('trackingnumber', 'like', "%{$last12Digits}%")
                    ->orderBy('lastDateUpdate')
                    ->lockForUpdate()
                    ->first();

                if (!$reconItem) {
                    throw new \Exception('Reconciliation item not found');
                }

                $reconId = $reconItem->ProductID;

                $data = (array) $reconItem;
                unset($data['id']); // remove reconciliation PK
                unset($data['ProductID']);   // 🔥 CRITICAL FIX

                // 🔒 Generate NEW RT for this insertion
                $maxRtResult = DB::table($this->productTable)
                    ->lockForUpdate()
                    ->selectRaw('MAX(CAST(rtcounter AS UNSIGNED)) as maxrt')
                    ->first();

                $newRt = (int) ($maxRtResult->maxrt ?? 0) + 1;

                // Override rtcounter
                $data['rtcounter'] = $newRt;

                // Directly process into Labeling
                $data['serialnumber']   = $request->firstSerialNumber;
                $data['serialnumberb']  = $request->secondSerialNumber;
                $data['PCN']            = $request->pcnNumber;
                $data['basketnumber']   = $request->basketNumber;
                $data['ProductModuleLoc'] = 'Labeling';
                $data['Username']       = $user;
                $data['lastDateUpdate'] = now();

                $newProductId = DB::table($this->productTable)
                    ->insertGetId($data);

                // ✅ Copy tracking images to this new product
                $this->copyTrackingImagesToProduct($reconItem->trackingnumber, $newProductId);

                // Delete reconciliation row
                DB::table('tblreconciliation')
                    ->where('ProductID', $reconItem->ProductID)
                    ->delete();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'newProductId' => $newProductId,
                    'wasSplit' => false,
                    'source' => 'Reconciliation',
                ]);
            }


            // ðŸ”¥ ADD: Store full tracking number
            $fullTrackingNumber = $request->trackingNumber;
            $last12Digits = substr($request->trackingNumber, -12);

            // Get current user ID from session
            $user = $this->getCurrentUserName();

            // ðŸ”¥ ADD: Get employee name for history
            $employeeName = auth()->user()->username ?? $user ?? 'System';

            // Get the original product
            $originalProduct = DB::table($this->productTable)
                ->where('ProductID', $request->productId)
                ->first();

            if (! $originalProduct) {
                Log::error('Product not found', [
                    'productId' => $request->productId,
                    'location' => 'Received',
                ]);
                throw new \Exception('Product not found with ID: '.$request->productId);
            }

            $currentQuantity = (int) ($originalProduct->quantity ?? 1);
            $needsSplitting = $currentQuantity > 1;

            // âœ… Handle splitting logic for PASS items
            if ($needsSplitting && $request->status === 'pass') {
                // Calculate unit prices
                $originalPrice = (float) ($originalProduct->price ?? 0);
                $originalPriceShipping = (float) ($originalProduct->priceshipping ?? 0);
                $originalTax = (float) ($originalProduct->tax ?? 0);

                $unitPrice = $currentQuantity > 0 ? round($originalPrice / $currentQuantity, 2) : 0;
                $unitPriceShipping = $currentQuantity > 0 ? round($originalPriceShipping / $currentQuantity, 2) : 0;
                $unitTax = $currentQuantity > 0 ? round($originalTax / $currentQuantity, 2) : 0;

                // Get current max rtcounter
                $maxRtResult = DB::table($this->productTable)
                    ->lockForUpdate()
                    ->selectRaw('MAX(CAST(rtcounter AS UNSIGNED)) as maxrt')
                    ->first();

                $newRt = (int) ($maxRtResult->maxrt ?? 0) + 1;

                // Create new item with quantity 1
                // 🔥 Clone FULL original product row
                $newItemData = (array) $originalProduct;

                // ❌ Remove primary key so new row can be created
                unset($newItemData['ProductID']);

                // 🔒 Override ONLY what must change
                $newItemData['quantity'] = 1;
                $newItemData['price'] = $unitPrice;
                $newItemData['priceshipping'] = $unitPriceShipping;
                $newItemData['tax'] = $unitTax;

                $newItemData['ProductModuleLoc'] = 'Labeling';

                $newItemData['serialnumber'] = $request->firstSerialNumber;
                $newItemData['serialnumberb'] = $request->secondSerialNumber;
                $newItemData['PCN'] = $request->pcnNumber;
                $newItemData['basketnumber'] = $request->basketNumber;

                $newItemData['rtcounter'] = $newRt;
                $newItemData['splitfromRT'] = $request->rtcounter;
                $newItemData['Username'] = $user;
                $newItemData['lastDateUpdate'] = now();


                // Filter out null values
                $newProductId = DB::table($this->productTable)
                ->insertGetId($newItemData);


                if (! $newProductId) {
                    throw new \Exception("Failed to create new item with RT: $newRt");
                }

                // âœ… Get the actual inserted ProductID
                // $newProductId = DB::getPdo()->lastInsertId();

                // ✅ COPY tracking images to the new split product
                // $this->copyTrackingImagesToProduct(
                //     $originalProduct->trackingnumber,
                //     $newProductId
                // );

                Log::info('Split item created', [
                    'newProductId' => $newProductId,
                    'newRtCounter' => $newRt,
                    'originalProductId' => $request->productId,
                ]);

                // âœ… Update original item: decrement quantity AND update prices
                $remainingQty = $currentQuantity - 1;
                $newOriginalPrice = $unitPrice * $remainingQty;
                $newOriginalPriceShipping = $unitPriceShipping * $remainingQty;
                $newOriginalTax = $unitTax * $remainingQty;

                // 🟢 Move remaining units to reconciliation
                $this->moveRemainingToReconciliation(
                    $originalProduct,
                    $remainingQty,
                    $unitPrice,
                    $unitPriceShipping,
                    $unitTax,
                    $user,
                    $request->rtcounter
                );

                DB::table($this->productTable)
                ->where('ProductID', $request->productId)
                ->delete();


                // ðŸ”¥ UPDATED: Track history for split with full tracking and employee
                $totalUnitPrice = $unitPrice + $unitPriceShipping + $unitTax;
    
                $this->trackHistory(
                    'Received',
                    'Split to Reconciliation',
                    "RT#{$request->rtcounter} | Tracking: {$fullTrackingNumber} | Qty: {$currentQuantity}",
                    "1 → Labeling (RT#{$newRt}) | {$remainingQty} → Reconciliation",
                    $employeeName
                );

                Log::info('Item split and processed', [
                    'originalRt' => $request->rtcounter,
                    'newRt' => $newRt,
                    'newProductId' => $newProductId,
                    'remainingQty' => $remainingQty,
                    'unitPrice' => $unitPrice,
                    'newOriginalPrice' => $newOriginalPrice,
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'item' => $request->trackingNumber.' processed successfully (split)',
                    'playsound' => 1,
                    'newProductId' => $newProductId,
                    'newRtCounter' => $newRt,
                    'remainingQuantity' => $remainingQty,
                    'wasSplit' => true,
                ]);

            } else {
                // âœ… ORIGINAL LOGIC: Process without splitting (quantity = 1 or failed item)
                if ($request->status === 'fail') {
                    // Prepare update data
                    $updateData = [
                        'ProductModuleLoc' => 'RTS',
                        'PCN' => $request->pcnNumber,
                        'basketnumber' => $request->basketNumber,
                        'Username' => $user,
                    ];

                    // Update product status for failed item
                    $updateResult = DB::table($this->productTable)
                        ->where('ProductID', $request->productId)
                        ->update($updateData);

                    // ðŸ”¥ UPDATED: Track history with full tracking number
                    $this->trackLocationChange(
                        'Received',
                        "RT#{$request->rtcounter} | Tracking: {$fullTrackingNumber}",
                        'Received',
                        'RTS (Failed)',
                        $employeeName
                    );

                    Log::info('Failed item processed', [
                        'updateResult' => $updateResult,
                    ]);

                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'item' => $request->trackingNumber.' marked as failed',
                        'playsound' => 1,
                    ]);
                } else {
                                        
                    // Set designated module location for the product
                    $materialTypeMap = [
                        'Inventory'       => 'Labeling',
                        'Supplies'        => 'Supplies',
                        'Components'      => 'Components',
                        'Office Equipment'=> 'Office Equipment',
                    ];

                    $moduleLocation = $materialTypeMap[$originalProduct->materialtype] ?? null;
                    // Process successfully received item (quantity = 1, no split needed)
                    $updateData = [
                        'serialnumber' => $request->firstSerialNumber,
                        'serialnumberb' => $request->secondSerialNumber,
                        'PCN' => $request->pcnNumber,
                        'basketnumber' => $request->basketNumber,
                        'ProductModuleLoc' => $moduleLocation,
                        'Username' => $user,
                    ];

                    // Update the product
                    $updateResult = DB::table($this->productTable)
                        ->where('ProductID', $request->productId)
                        ->update($updateData);

                    Log::info('Update result:', [
                        'rowsAffected' => $updateResult,
                    ]);

                    if ($updateResult === 0) {
                        Log::warning('No rows were updated', [
                            'productId' => $request->productId,
                        ]);
                    }

                    // ðŸ”¥ UPDATED: Track history with full tracking number
                    $this->trackLocationChange(
                        'Received',
                        "RT#{$request->rtcounter} | Tracking: {$fullTrackingNumber}",
                        'Received',
                        'Labeling',
                        $employeeName
                    );

                    // DB::table($this->productTable)
                    //     ->where('ProductID', $request->productId)
                    //     ->delete();

                    DB::commit();
                    Log::info('Transaction committed successfully');

                    return response()->json([
                        'success' => true,
                        'item' => $request->trackingNumber.' processed successfully',
                        'playsound' => 1,
                        'wasSplit' => false,
                        'newProductId' => $request->productId,
                    ]);
                }
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error:', ['errors' => $e->errors()]);

            return response()->json([
                'success' => false,
                'message' => 'Validation error: '.json_encode($e->errors()),
                'errors' => $e->errors(),
                'reason' => 'validation_error',
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error processing scan', $e, $request->all());

            return response()->json([
                'success' => false,
                'message' => 'Error processing scan: '.$e->getMessage(),
                'reason' => 'server_error',
            ], 500);
        }
    }

    private function moveRemainingToReconciliation(
            object $originalProduct,
            int $remainingQty,
            float $unitPrice,
            float $unitShipping,
            float $unitTax,
            string $username,
            int $splitFromRt
        ) : int {

            if ($remainingQty <= 0) {
                return 0;
            }

            // 🔥 Copy FULL product row
            $baseData = (array) $originalProduct;

            // ❌ Remove primary key
            unset($baseData['ProductID']);

            // ✅ Override ONLY what must change
            $baseData['quantity'] = 1;
            $baseData['price'] = $unitPrice;
            $baseData['priceshipping'] = $unitShipping;
            $baseData['tax'] = $unitTax;
            $baseData['ProductModuleLoc'] = 'Reconciliation';
            $baseData['Username'] = $username;
            $baseData['lastDateUpdate'] = now();

            // (Optional but logical)
            $baseData['splitfromRT'] = $splitFromRt;

            $inserted = 0;

            for ($i = 0; $i < $remainingQty; $i++) {
                DB::table('tblreconciliation')->insert($baseData);
                $inserted++;
            }

            return $inserted;
        }

}
