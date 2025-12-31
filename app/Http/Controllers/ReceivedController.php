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
                        // ✅ FIXED: Search tracking by last 12 digits
                        ->orWhere('trackingnumber', 'like', '%'.substr($search, -12).'%');
                });
            })
            ->paginate($perPage);

        return response()->json($products);
    }

    public function verifyTracking(Request $request)
    {
        $tracking = $request->input('tracking');

        // Extract the last 12 digits
        $last12Digits = substr($tracking, -12);

        // ✅ FIRST: Check if it exists in Received (valid for processing)
        $receivedProduct = DB::table($this->productTable)
            ->where('trackingnumber', 'like', '%'.$last12Digits.'%')
            ->where('ProductModuleLoc', 'Received')
            ->first();

        if ($receivedProduct) {
            // Get image fields for the product
            $imageFields = [
                'img1', 'img2', 'img3', 'img4', 'img5',
                'img6', 'img7', 'img8', 'img9', 'img10',
                'img11', 'img12', 'img13', 'img14', 'img15',
            ];

            // Create a productDetails object with just the necessary fields
            $productDetails = new \stdClass;

            // Add image fields if they exist
            foreach ($imageFields as $field) {
                if (property_exists($receivedProduct, $field) && ! empty($receivedProduct->$field)) {
                    $productDetails->$field = $receivedProduct->$field;
                }
            }

            // ✅ Return with quantity info - even if some units are already in Labeling
            return response()->json([
                'found' => true,
                'productId' => $receivedProduct->ProductID,
                'rtcounter' => $receivedProduct->rtcounter,
                'trackingnumber' => $receivedProduct->trackingnumber,
                'quantity' => $receivedProduct->quantity ?? 1,
                'productDetails' => $productDetails,
                'alreadyScanned' => false, // Still valid to process
            ]);
        }

        // ✅ SECOND: Check if tracking exists in Labeling/Validation (fully processed)
        $labelingProduct = DB::table($this->productTable)
            ->where('trackingnumber', 'like', '%'.$last12Digits.'%')
            ->whereIn('ProductModuleLoc', ['Labeling', 'Validation'])
            ->first();

        if ($labelingProduct) {
            // Product exists but has been completely processed (no remaining in Received)
            return response()->json([
                'found' => true,
                'productId' => $labelingProduct->ProductID,
                'rtcounter' => $labelingProduct->rtcounter,
                'trackingnumber' => $labelingProduct->trackingnumber,
                'alreadyScanned' => true, // All units processed
            ]);
        }

        // Product not found anywhere
        return response()->json(['found' => false]);
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
            // ✅ FIXED: Updated validation rules
            if ($request->status === 'fail') {
                $request->validate([
                    'trackingNumber' => 'required',
                    'status' => 'required|in:fail',
                    'basketNumber' => ['required', 'regex:/^(BKT|SI|ENV)\d+$/i'], // ✅ FIXED: BKT, SI, ENV
                    'pcnNumber' => ['required', 'regex:/^PCN\d+$/i'],
                    'productId' => 'required',
                    'rtcounter' => 'required',
                ]);
            } else {
                $request->validate([
                    'trackingNumber' => 'required',
                    'status' => 'required|in:pass',
                    'firstSerialNumber' => ['required', 'regex:/^[A-Z0-9]+$/i'], // ✅ ADDED: Serial validation
                    'secondSerialNumber' => ['required', 'regex:/^(N\/A|[A-Z0-9]+)$/i'], // ✅ ADDED: Allow N/A or alphanumeric
                    'pcnNumber' => ['required', 'regex:/^PCN\d+$/i'],
                    'basketNumber' => ['required', 'regex:/^(BKT|SI|ENV)\d+$/i'], // ✅ FIXED: BKT, SI, ENV
                    'productId' => 'required',
                    'rtcounter' => 'required',
                ]);
            }

            DB::beginTransaction();

            // Get the last 12 digits of the tracking number
            $last12Digits = substr($request->trackingNumber, -12);

            // Get current user ID from session
            $user = $this->getCurrentUserName();

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

            // ✅ Handle splitting logic for PASS items
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
                    ->selectRaw('MAX(rtcounter) as maxrt')
                    ->first();
                $newRt = (int) ($maxRtResult->maxrt ?? 0) + 1;

                // Create new item with quantity 1
                $newItemData = [
                    'ProductTitle' => $originalProduct->ProductTitle ?? null,
                    'itemnumber' => $originalProduct->itemnumber ?? null,
                    'RPN' => $originalProduct->RPN ?? null,
                    'PRD' => $originalProduct->PRD ?? null,
                    'quantity' => 1,
                    'price' => $unitPrice,
                    'priceshipping' => $unitPriceShipping,
                    'tax' => $unitTax,
                    'orderdate' => $originalProduct->orderdate ?? null,
                    'paymentdate' => $originalProduct->paymentdate ?? null,
                    'shipdate' => $originalProduct->shipdate ?? null,
                    'datedelivered' => $originalProduct->datedelivered ?? null,
                    'description' => $originalProduct->description ?? null,
                    'supplierNotes' => $originalProduct->supplierNotes ?? null,
                    'employeeNotes' => $originalProduct->employeeNotes ?? null,
                    'stickerNotes' => $originalProduct->stickerNotes ?? null,
                    'trackingnumber' => $originalProduct->trackingnumber ?? null,
                    'serialnumber' => $request->firstSerialNumber,
                    'serialnumberb' => $request->secondSerialNumber,
                    'PCN' => $request->pcnNumber,
                    'basketnumber' => $request->basketNumber,
                    'ProductModuleLoc' => 'Labeling',
                    'rtcounter' => $newRt,
                    'splitfromRT' => $request->rtcounter,
                    'Username' => $user,
                    'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                ];

                // Filter out null values
                $newItemData = array_filter($newItemData, function ($value) {
                    return $value !== null && $value !== '';
                });

                // Insert new item
                $insertResult = DB::table($this->productTable)->insert($newItemData);

                if (! $insertResult) {
                    throw new \Exception("Failed to create new item with RT: $newRt");
                }

                // ✅ Get the actual inserted ProductID
                $newProductId = DB::getPdo()->lastInsertId();
                
                Log::info('Split item created', [
                    'newProductId' => $newProductId,
                    'newRtCounter' => $newRt,
                    'originalProductId' => $request->productId
                ]);

                // ✅ Update original item: decrement quantity AND update prices
                $remainingQty = $currentQuantity - 1;
                $newOriginalPrice = $unitPrice * $remainingQty;
                $newOriginalPriceShipping = $unitPriceShipping * $remainingQty;
                $newOriginalTax = $unitTax * $remainingQty;

                $updateResult = DB::table($this->productTable)
                    ->where('ProductID', $request->productId)
                    ->update([
                        'quantity' => $remainingQty,
                        'price' => $newOriginalPrice,
                        'priceshipping' => $newOriginalPriceShipping,
                        'tax' => $newOriginalTax,
                        'lastDateUpdate' => now()->format('Y-m-d H:i:s'),
                    ]);

                if ($updateResult === 0) {
                    throw new \Exception('Failed to update original product quantity');
                }

                // Track history for split
                $totalUnitPrice = $unitPrice + $unitPriceShipping + $unitTax;
                $this->trackHistory(
                    'Received Module',
                    'Split & Process',
                    "RTC: {$request->rtcounter} | Qty: {$currentQuantity} | Total: $".number_format($originalPrice + $originalPriceShipping + $originalTax, 2),
                    "Created RTC: {$newRt} (Qty: 1, Price: $".number_format($totalUnitPrice, 2).") | Remaining: {$remainingQty} @ $".number_format($newOriginalPrice + $newOriginalPriceShipping + $newOriginalTax, 2)
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
                // ✅ ORIGINAL LOGIC: Process without splitting (quantity = 1 or failed item)
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

                    // Track history
                    $this->trackLocationChange(
                        'Received Module',
                        "Tracking: {$last12Digits} | RTC: {$request->rtcounter}",
                        'Received',
                        'RTS (Failed)',
                        $user
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
                    // Process successfully received item (quantity = 1, no split needed)
                    $updateData = [
                        'serialnumber' => $request->firstSerialNumber,
                        'serialnumberb' => $request->secondSerialNumber,
                        'PCN' => $request->pcnNumber,
                        'basketnumber' => $request->basketNumber,
                        'ProductModuleLoc' => 'Labeling',
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

                    // Track history
                    $this->trackLocationChange(
                        'Received Module',
                        "Tracking: {$last12Digits} | RTC: {$request->rtcounter}",
                        'Received',
                        'Labeling',
                        $user
                    );

                    DB::commit();
                    Log::info('Transaction committed successfully');

                    return response()->json([
                        'success' => true,
                        'item' => $request->trackingNumber.' processed successfully',
                        'playsound' => 1,
                        'wasSplit' => false,
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
}