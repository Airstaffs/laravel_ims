<?php

namespace App\Http\Controllers;

use App\Models\tblproduct;
use App\Traits\TracksHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdersController extends BasetablesController
{
    use TracksHistory;

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $location = $request->input('location', 'Orders');

        $products = DB::table($this->productTable)
            ->where('ProductModuleLoc', $location)
            ->when($search, function ($query) use ($search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('ProductTitle', 'like', "%{$search}%")
                        ->orWhere('rtid', 'like', "%{$search}%")
                        ->orWhere('itemnumber', 'like', "%{$search}%")
                        ->orWhere('trackingnumber', 'like', "%{$search}%")
                        ->orWhere('rtcounter', 'like', "%{$search}%");
                });
            })
            ->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Get the next available ProductID
     */
    public function getNextProductId()
    {
        $maxProductId = DB::table($this->productTable)->max('ProductID') ?? 0;

        return response()->json([
            'next_id' => $maxProductId + 1,
            'current_max' => $maxProductId,
        ]);
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
            'notes' => 'nullable|string',
            // 'employeeNotes' => 'nullable|string',
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
            'price' => 'nullable|numeric',
            'RPN' => 'nullable|string',
            'PRD' => 'nullable|string',
            'PCN' => 'nullable|string',
            'basketnumber' => 'nullable|string',
        ]);

        $validated['validation'] = $validated['validation'] ?? 'unvalidated';

        // Check if this is an update or create
        $existingProduct = tblproduct::where('itemnumber', $validated['itemnumber'])->first();
        $isUpdate = $existingProduct !== null;

        // 🔥 TRACK WHAT CHANGED (if update)
        $changes = [];
        if ($isUpdate && $existingProduct) {
            foreach ($validated as $key => $value) {
                // Skip ProductID and itemnumber
                if (in_array($key, ['ProductID', 'itemnumber'])) {
                    continue;
                }

                // Get old and new values
                $oldVal = $existingProduct->$key ?? null;
                $newVal = $value ?? null;

                // 🔥 BETTER NULL/EMPTY HANDLING
                // Convert empty strings to null for comparison
                if ($oldVal === '') {
                    $oldVal = null;
                }
                if ($newVal === '') {
                    $newVal = null;
                }

                // 🔥 SKIP if both are null
                if (is_null($oldVal) && is_null($newVal)) {
                    continue;
                }

                // 🔥 SKIP if values are identical (including 0 === 0)
                if ($oldVal === $newVal) {
                    continue;
                }

                // 🔥 SPECIAL HANDLING FOR NUMERIC FIELDS
                if (in_array($key, ['price', 'quantity', 'Discount', 'tax', 'priceshipping', 'refund'])) {
                    // Convert to float for proper comparison
                    $oldNum = is_null($oldVal) ? null : (float) $oldVal;
                    $newNum = is_null($newVal) ? null : (float) $newVal;

                    // Skip if both are null
                    if (is_null($oldNum) && is_null($newNum)) {
                        continue;
                    }

                    // Skip if both are 0
                    if ($oldNum == 0 && $newNum == 0) {
                        continue;
                    }

                    // Skip if values are the same
                    if ($oldNum === $newNum) {
                        continue;
                    }

                    // Only track if there's a real change
                    $oldDisplay = ! is_null($oldNum) ? $oldNum : '(empty)';
                    $newDisplay = ! is_null($newNum) ? $newNum : '(empty)';
                    $changes[] = "$key: $oldDisplay → $newDisplay";
                }
                // Format dates
                elseif (in_array($key, ['orderdate', 'paymentdate', 'shipdate', 'datedelivered'])) {
                    $oldDisplay = $oldVal ? date('Y-m-d', strtotime($oldVal)) : '(empty)';
                    $newDisplay = $newVal ? date('Y-m-d', strtotime($newVal)) : '(empty)';

                    // Skip if both are empty
                    if ($oldDisplay === '(empty)' && $newDisplay === '(empty)') {
                        continue;
                    }

                    $changes[] = "$key: $oldDisplay → $newDisplay";
                }
                // Format text values
                else {
                    // Skip if both are empty/null
                    if (empty($oldVal) && empty($newVal)) {
                        continue;
                    }

                    $oldDisplay = $oldVal ? (strlen($oldVal) > 30 ? substr($oldVal, 0, 27).'...' : $oldVal) : '(empty)';
                    $newDisplay = $newVal ? (strlen($newVal) > 30 ? substr($newVal, 0, 27).'...' : $newVal) : '(empty)';

                    $changes[] = "$key: $oldDisplay → $newDisplay";
                }
            }
        }

        // Create or update the product
        $product = tblproduct::updateOrCreate(
            ['itemnumber' => $validated['itemnumber']],
            $validated
        );

        // 🔥 ONLY TRACK HISTORY IF THERE ARE CHANGES
        if ($isUpdate && ! empty($changes)) {
            $employeeName = auth()->user()->username ?? 'System';
            $identifier = "Item #{$product->itemnumber}".
                          (! empty($product->ProductTitle) ? " - {$product->ProductTitle}" : '');

            // Log up to 5 changes, show count if more
            $changeCount = count($changes);
            $changesToLog = array_slice($changes, 0, 5);
            $changeDescription = implode(', ', $changesToLog);

            if ($changeCount > 5) {
                $changeDescription .= ' (+'.($changeCount - 5).' more)';
            }

            $this->trackUpdate(
                'Orders',
                $identifier,
                $changeDescription,
                null,
                $employeeName
            );
        } elseif (! $isUpdate) {
            // Only track creation for new products
            $employeeName = auth()->user()->username ?? 'System';
            $identifier = "Item #{$product->itemnumber}".
                          (! empty($product->ProductTitle) ? " - {$product->ProductTitle}" : '');

            $this->trackCreate(
                'Orders',
                $identifier,
                $employeeName
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Order product saved successfully',
            'product' => $product,
            'changes_made' => count($changes),
        ]);
    }

    /**
     * Update order status with history tracking
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'validation' => 'required|string',
        ]);

        $product = tblproduct::findOrFail($id);
        $oldStatus = $product->validation ?? 'none';

        $product->validation = $validated['validation'];
        $product->save();

        // Track status change
        $this->trackStatusChange(
            'Orders',
            "Item: {$product->itemnumber}",
            $oldStatus,
            $validated['validation']
        );

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'product' => $product,
        ]);
    }

    /**
     * Delete order with history tracking
     */
    public function destroy($id)
    {
        try {
            // Use DB query instead of model to avoid "No query results" error
            $product = DB::table($this->productTable)
                ->where('ProductID', $id)
                ->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            $itemInfo = "Item: {$product->itemnumber}";
            if (! empty($product->ProductTitle)) {
                $itemInfo .= " - {$product->ProductTitle}";
            }

            // Delete the product
            DB::table($this->productTable)
                ->where('ProductID', $id)
                ->delete();

            // Track deletion
            $this->trackDelete('Orders', $itemInfo);

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully',
            ]);

        } catch (\Exception $e) {
            \Log::error('Delete order error:', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting order: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tracking number
     */
    public function updateTracking(Request $request, $id)
    {
        $validated = $request->validate([
            'trackingnumber' => 'required|string',
        ]);

        $product = tblproduct::findOrFail($id);
        $oldTracking = $product->trackingnumber ?? 'none';

        $product->trackingnumber = $validated['trackingnumber'];
        $product->save();

        // Track tracking number change
        $this->trackUpdate(
            'Orders',
            "Item: {$product->itemnumber}",
            "Tracking: {$oldTracking}",
            "Tracking: {$validated['trackingnumber']}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Tracking number updated successfully',
            'product' => $product,
        ]);
    }

    /**
     * Get formatted time for display
     */
    private function formatHistoryTime($datetime)
    {
        if (empty($datetime)) {
            return null;
        }

        return [
            'la_time' => \App\Helpers\TimeHelper::formatDateTime($datetime, 'M d, Y h:i:s A', 'America/Los_Angeles'),
            'ph_time' => \App\Helpers\TimeHelper::formatDateTime($datetime, 'M d, Y h:i:s A', 'Asia/Manila'),
            'la_tz' => \App\Helpers\TimeHelper::getTimezoneDisplay('America/Los_Angeles'),
            'ph_tz' => \App\Helpers\TimeHelper::getTimezoneDisplay('Asia/Manila'),
        ];
    }
}
