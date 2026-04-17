<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UtilsScanners extends Controller
{
    /**
     * Check item status (replacement for item_checker_backend.php)
     */
    public function checkItemStatus(Request $request)
    {
        $request->validate([
            'serialnumber' => 'required|string'
        ]);

        $serial = trim($request->serialnumber);

        try {
            // 🔥 Adjust table/columns based on your DB
            $item = DB::table('tblproduct') // or your actual table
                ->where('serialnumber', $serial)
                ->orWhere('pcn', $serial)
                ->first();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found'
                ]);
            }

            return response()->json([
                'success' => true,
                'itemstatus' => $item->status ?? 'Unknown'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update item status (for Update Mode)
     */
    public function updateItemStatus(Request $request)
    {
        $request->validate([
            'serialnumber' => 'required|string',
            'status' => 'required|string',
            'reason' => 'nullable|string'
        ]);

        $serial = trim($request->serialnumber);

        try {
            $updated = DB::table('tblproduct') // adjust table
                ->where('serialnumber', $serial)
                ->orWhere('pcn', $serial)
                ->update([
                    'status' => $request->status,
                    'updated_at' => now()
                ]);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found or not updated'
                ]);
            }

            // Optional: log reason
            if ($request->reason) {
                DB::table('tblproduct_status_logs')->insert([
                    'serialnumber' => $serial,
                    'status' => $request->status,
                    'reason' => $request->reason,
                    'created_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ]);
        }
    }
}