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
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $records = EwhRecord::orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($records);
    }

    /**
     * POST /hr/ewh
     * Save batch EWH records
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
}
