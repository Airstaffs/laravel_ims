<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HrController extends Controller
{
    public function getEmployees()
    {
        $employees = DB::table('tbluser')
            ->select('id', 'username as name', 'office_role as position')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($employees);
    }

    public function getTimeRecords(Request $request)
    {
        $query = DB::table('tblemployeeclocks');

        // Filter by Employee
        if ($request->filled('employee')) {
            $query->where('Employee', $request->input('employee'));
        }

        // Filter by Date Range
        if ($request->filled('dateFrom') && $request->filled('dateTo')) {
            $query->whereBetween('DateToday', [
                $request->input('dateFrom'),
                $request->input('dateTo')
            ]);
        }

        // Optional Grouping (can be expanded)
        if ($request->input('group_by') === 'employee') {
            $query->select('Employee', DB::raw('COUNT(*) as total_entries'))
                ->groupBy('Employee');
        }

        // Sorting
        $sort = $request->input('sort', 'desc'); // default to 'desc'
        $query->orderBy('TimeIn', $sort);

        // Pagination
        $perPage = $request->input('per_page', 20);
        $records = $query->paginate($perPage);

        return response()->json($records);
    }
    /**
     * Summary of editTimeRecord
     * @param \Illuminate\Http\Request $request
     * @param mixed $id
     * @return \Illuminate\Http\JsonResponse
     * JSON Structure of changes field
     *  {
     *      "TimeIn": { "from": "2025-08-09 08:00:00", "to": "2025-08-09 09:00:00" },
     *      "Notes": { "from": "Late", "to": "On Time" }
     *  }
     */
public function editTimeRecord(Request $request, int $id): JsonResponse
{
    // 1) Load BEFORE state from DB
    $beforeRow = DB::table('tblemployeeclocks')->where('ID', $id)->first();
    if (!$beforeRow) {
        return response()->json(['message' => 'Time record not found.'], 404);
    }
    $before = (array) $beforeRow;

    // 2) Validate AFTER payload (frontend sends only "after")
    //    You can expand rules as needed.
    $validated = $request->validate([
        'after'                        => 'required|array',
        'after.DateToday'              => 'nullable|date_format:Y-m-d',
        'after.TimeIn'                 => 'nullable|date_format:Y-m-d H:i:s',
        'after.TimeOut'                => 'nullable|date_format:Y-m-d H:i:s',
        'after.shortbreak_start'       => 'nullable|date_format:Y-m-d H:i:s',
        'after.shortbreak_end'         => 'nullable|date_format:Y-m-d H:i:s',
        'after.shortbreak_totaltime'   => 'nullable|integer|min:0',
        'after.Notes'                  => 'nullable|string',
        'after.AdminNote'              => 'nullable|string',
        // ignore any other keys the client might send
    ]);

    $after = $validated['after'];

    // 3) Whitelist of editable columns in tblemployeeclocks
    $allowed = [
        'DateToday',
        'TimeIn', 'TimeOut',
        'shortbreak_start', 'shortbreak_end', 'shortbreak_totaltime',
        'Notes', 'AdminNote',
    ];

    // 4) Build update set & diff (only changed values)
    $update = [];
    $changes = [];

    foreach ($allowed as $col) {
        if (!array_key_exists($col, $after)) {
            continue; // not sent => not changing
        }

        $new = $after[$col];
        if ($new === '') {
            $new = null; // normalize empty -> null
        }

        $old = $before[$col] ?? null;

        // compare as strings to smooth null/empty mismatches
        if ((string)($old ?? '') !== (string)($new ?? '')) {
            $update[$col] = $new;
            $changes[$col] = ['from' => $old, 'to' => $new];
        }
    }

    // Optional domain checks (uncomment if you want guards)
    // if (isset($update['TimeIn'], $update['TimeOut']) && $update['TimeIn'] && $update['TimeOut']) {
    //     if (strtotime($update['TimeOut']) < strtotime($update['TimeIn'])) {
    //         return response()->json(['message' => 'TimeOut cannot be earlier than TimeIn.'], 422);
    //     }
    // }
    // if (isset($update['shortbreak_start'], $update['shortbreak_end']) && $update['shortbreak_start'] && $update['shortbreak_end']) {
    //     if (strtotime($update['shortbreak_end']) < strtotime($update['shortbreak_start'])) {
    //         return response()->json(['message' => 'Break end cannot be earlier than break start.'], 422);
    //     }
    // }

    if (empty($update)) {
        // Nothing actually changed. Return 200 so the UI can quietly close.
        return response()->json(['message' => 'No changes provided.'], 200);
    }

    // 5) Persist + log atomically
    DB::beginTransaction();
    try {
        // Update main row
        DB::table('tblemployeeclocks')->where('ID', $id)->update($update);

        // Log JSON diff only (before/from, after/to)
        DB::table('tblemployeeclocks_edit_history')->insert([
            'clock_id'       => $id,
            'edited_by'      => session('userid') ?? null,  // fallback if no auth
            'changes'        => json_encode($changes, JSON_UNESCAPED_UNICODE),
            'edit_timestamp' => now(),
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Time record updated successfully.',
            'updated' => array_keys($changes),
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Update failed.',
            'error'   => config('app.debug') ? $e->getMessage() : 'Server error',
        ], 500);
    }
}

    public function getLeaveHistory()
    {
        return response()->json([
            ['employee' => 'Bob', 'type' => 'Vacation', 'date_from' => '2025-08-01', 'date_to' => '2025-08-05']
        ]);
    }

    public function getViolations()
    {
        return response()->json([
            ['employee' => 'Alice', 'violation' => 'Late', 'date' => '2025-07-30']
        ]);
    }
}