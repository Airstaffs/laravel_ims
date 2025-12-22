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

        // Check if this is an update or create
        $existingProduct = tblproduct::where('itemnumber', $validated['itemnumber'])->first();
        $isUpdate = $existingProduct !== null;

        $product = tblproduct::updateOrCreate(
            ['itemnumber' => $validated['itemnumber']],
            $validated
        );

        // Track history
        if ($isUpdate) {
            $this->trackUpdate(
                'Orders',
                "Item: {$product->itemnumber} - {$product->ProductTitle}"
            );
        } else {
            $this->trackCreate(
                'Orders',
                "Item: {$product->itemnumber} - {$product->ProductTitle}"
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Order product saved successfully',
            'product' => $product,
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
