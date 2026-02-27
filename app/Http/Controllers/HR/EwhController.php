<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\EwhRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EwhController extends Controller
{
    /**
     * GET /hr/ewh
     * Paginated list of EWH records
     * - HR/admin sees all records (draft + released)
     * - Regular employee sees only their own released records
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $user = auth()->user();

        $query = EwhRecord::orderBy('created_at', 'desc');

        if ($user->isHR()) {
            // HR/Admin: see all records
        } else {
            // Regular employee: only their own released records
            $query->where('employee_id', $user->id)
                ->where('status', 'released');
        }

        // Optional date filters (used by employee modal)
        if ($request->filled('from')) {
            $query->where('cutoff_from', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('cutoff_to', '<=', $request->to);
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * POST /hr/ewh
     * Save batch EWH records — always saved as 'draft'
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'records' => 'required|array|min:1',
            'records.*.employee_id' => 'required|integer',
            'records.*.employee_name' => 'required|string',
            'records.*.payout_date' => 'required|date',
            'records.*.cutoff_from' => 'required|date',
            'records.*.cutoff_to' => 'required|date',
            'records.*.total_days' => 'required|integer',
            'records.*.total_hours' => 'required|numeric',
            'records.*.regular_hours' => 'nullable|numeric',
            'records.*.ot_hours' => 'nullable|numeric',
            'records.*.regular_holiday_days' => 'nullable|integer',
            'records.*.special_holiday_days' => 'nullable|integer',
            'records.*.attendance_records' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $saved = [];
            foreach ($request->records as $record) {
                $ewh = EwhRecord::create([
                    'employee_id' => $record['employee_id'],
                    'employee_name' => $record['employee_name'],
                    'payout_date' => $record['payout_date'],
                    'cutoff_from' => $record['cutoff_from'],
                    'cutoff_to' => $record['cutoff_to'],
                    'total_days' => $record['total_days'],
                    'total_hours' => $record['total_hours'],
                    'regular_hours' => $record['regular_hours'] ?? 0,
                    'ot_hours' => $record['ot_hours'] ?? 0,
                    'regular_holiday_days' => $record['regular_holiday_days'] ?? 0,
                    'special_holiday_days' => $record['special_holiday_days'] ?? 0,
                    'attendance_records' => $record['attendance_records'] ?? [],
                    'status' => 'draft', // always draft on creation
                ]);
                $saved[] = $ewh;
            }

            DB::commit();

            return response()->json([
                'message' => 'EWH records saved successfully.',
                'data' => $saved,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to save EWH records.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /hr/ewh/{id}/release
     * Release an EWH record so the employee can see it
     */
    public function release($id)
    {
        $record = EwhRecord::find($id);

        if (! $record) {
            return response()->json(['message' => 'EWH record not found.'], 404);
        }

        $record->update(['status' => 'released']);

        return response()->json([
            'message' => 'EWH record released successfully.',
            'data' => $record,
        ]);
    }

    /**
     * GET /hr/ewh/{id}
     * Get a single EWH record
     */
    public function show($id)
    {
        $record = EwhRecord::find($id);

        if (! $record) {
            return response()->json(['message' => 'EWH record not found.'], 404);
        }

        return response()->json($record);
    }

    /**
     * DELETE /hr/ewh/{id}
     * Delete a single EWH record
     */
    public function destroy($id)
    {
        $record = EwhRecord::find($id);

        if (! $record) {
            return response()->json(['message' => 'EWH record not found.'], 404);
        }

        $record->delete();

        return response()->json(['message' => 'EWH record deleted successfully.']);
    }

    /**
     * PATCH /hr/ewh/{id}/employee-status
     * Employee updates their own status (viewed / acknowledged)
     */
    public function updateEmployeeStatus(Request $request, $id)
    {
        $record = EwhRecord::find($id);

        if (! $record) {
            return response()->json(['message' => 'EWH record not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'employee_status' => 'required|in:new,viewed,acknowledged',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid status.', 'errors' => $validator->errors()], 422);
        }

        // Only allow upgrade: new → viewed → acknowledged (no downgrade)
        $order = ['new' => 0, 'viewed' => 1, 'acknowledged' => 2];
        $current = $order[$record->employee_status] ?? 0;
        $incoming = $order[$request->employee_status] ?? 0;

        if ($incoming > $current) {
            $record->update(['employee_status' => $request->employee_status]);
        }

        return response()->json([
            'message' => 'Employee status updated.',
            'data' => $record,
        ]);
    }
}
