<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HrController extends Controller
{
    public function getEmployees()
    {
        $today = date('Y-m-d');

        $employees = DB::table('tbluser as u')
            ->select(
                'u.id',
                'u.username',
                DB::raw('u.username as name'),
                DB::raw('u.office_role as position'),
                // current monthly
                DB::raw("(SELECT er.monthly_rate
                       FROM tblemployeerate er
                       WHERE er.employee_id = u.id
                         AND er.effective_start <= '{$today}'
                         AND (er.effective_end IS NULL OR er.effective_end >= '{$today}')
                       ORDER BY er.effective_start DESC
                       LIMIT 1) as current_monthly_rate"),
                // current hourly
                DB::raw("(SELECT er.hourly_rate
                       FROM tblemployeerate er
                       WHERE er.employee_id = u.id
                         AND er.effective_start <= '{$today}'
                         AND (er.effective_end IS NULL OR er.effective_end >= '{$today}')
                       ORDER BY er.effective_start DESC
                       LIMIT 1) as current_hourly_rate"),
                // current currency
                DB::raw("(SELECT er.currency
                       FROM tblemployeerate er
                       WHERE er.employee_id = u.id
                         AND er.effective_start <= '{$today}'
                         AND (er.effective_end IS NULL OR er.effective_end >= '{$today}')
                       ORDER BY er.effective_start DESC
                       LIMIT 1) as current_currency")
            )
            ->orderBy('u.id', 'asc')
            ->get();

        return response()->json($employees);
    }

    public function getEmployeeRateHistory(Request $request)
    {
        $employeeId = $request->query('employee_id');

        $q = \DB::table('tblemployeerate as er')
            ->leftJoin('tbluser as u', 'u.id', '=', 'er.employee_id')
            ->select(
                'er.id',
                'er.employee_id',
                'u.username',
                'er.employee_username', // snapshot
                'er.effective_start',
                'er.effective_end',
                'er.monthly_rate',
                'er.hourly_rate',
                'er.currency',
                'er.created_by',
                'er.created_at'
            )
            ->when($employeeId, fn($qq) => $qq->where('er.employee_id', $employeeId))
            ->orderBy('er.employee_id')
            ->orderByDesc('er.effective_start');

        return response()->json($q->get());
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
        $perPage = $request->input('per_page', 10);
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
            'after' => 'required|array',
            'after.DateToday' => 'nullable|date_format:Y-m-d',
            'after.TimeIn' => 'nullable|date_format:Y-m-d H:i:s',
            'after.TimeOut' => 'nullable|date_format:Y-m-d H:i:s',
            'after.shortbreak_start' => 'nullable|date_format:Y-m-d H:i:s',
            'after.shortbreak_end' => 'nullable|date_format:Y-m-d H:i:s',
            'after.shortbreak_totaltime' => 'nullable|integer|min:0',
            'after.Notes' => 'nullable|string',
            'after.AdminNote' => 'nullable|string',
            // ignore any other keys the client might send
        ]);

        $after = $validated['after'];

        // 3) Whitelist of editable columns in tblemployeeclocks
        $allowed = [
            'DateToday',
            'TimeIn',
            'TimeOut',
            'shortbreak_start',
            'shortbreak_end',
            'shortbreak_totaltime',
            'Notes',
            'AdminNote',
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
            if ((string) ($old ?? '') !== (string) ($new ?? '')) {
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
                'clock_id' => $id,
                'edited_by' => session('userid') ?? null,  // fallback if no auth
                'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE),
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
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
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

    // Employee Rate
    public function index($employee)
    {
        $rows = DB::table('tblemployeerate')
            ->where('employee_id', $employee)
            ->orderByDesc('effective_start')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // Optional: get current active rate
    public function indexRate($employee)
    {
        $rows = \DB::table('tblemployeerate')
            ->where('employee_id', $employee)
            ->orderByDesc('effective_start')
            ->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function currentRate($employee)
    {
        $today = date('Y-m-d');
        $row = \DB::table('tblemployeerate')
            ->where('employee_id', $employee)
            ->where('effective_start', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_end')->orWhere('effective_end', '>=', $today);
            })
            ->orderByDesc('effective_start')
            ->first();
        return response()->json(['success' => true, 'data' => $row]);
    }

    public function storeRate(Request $request, $employee)
    {
        $data = $request->validate([
            'effective_start' => ['required', 'date'],
            'effective_end' => ['nullable', 'date', 'after_or_equal:effective_start'],
            'monthly_rate' => ['nullable', 'numeric'],
            'hourly_rate' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        if (is_null($data['monthly_rate']) && is_null($data['hourly_rate'])) {
            return response()->json([
                'success' => false,
                'error' => 'Provide at least monthly_rate or hourly_rate.'
            ], 422);
        }

        // Resolve snapshot username from your users table
        $employeeUsername = \DB::table('tbluser')->where('id', $employee)->value('username');

        // Creator snapshot (username); adjust if you store differently
        $createdBy = session('user_name') ?? optional($request->user())->username ?? null;

        $start = $data['effective_start'];
        $end = $data['effective_end'] ?? null;

        \DB::beginTransaction();
        try {
            // Optional: close currently active open-ended row
            $active = \DB::table('tblemployeerate')
                ->where('employee_id', $employee)
                ->whereNull('effective_end')
                ->first();

            if ($active && $start > $active->effective_start) {
                $newEnd = date('Y-m-d', strtotime($start . ' -1 day'));
                \DB::table('tblemployeerate')
                    ->where('id', $active->id)
                    ->update([
                        'effective_end' => $newEnd,
                        'updated_at' => now(),
                    ]);
            }

            // Insert new rate row
            \DB::table('tblemployeerate')->insert([
                'employee_id' => (int) $employee,
                'employee_username' => $employeeUsername,                  // snapshot (Option A)
                'effective_start' => $start,
                'effective_end' => $end,
                'monthly_rate' => $data['monthly_rate'],
                'hourly_rate' => $data['hourly_rate'],
                'currency' => strtoupper($data['currency'] ?? 'PHP'),
                'created_by' => $createdBy,                         // snapshot of creator username
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \DB::commit();
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Failed to save rate',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function listHolidays(Request $request): JsonResponse
{
    $year = (int)($request->input('year') ?: Carbon::now()->year);

    $rows = DB::table('tblholiday')
        ->select('holidayID','holidate','status','title','is_recurring')
        ->orderBy('holidate','asc')
        ->get();

    // Expand display_date for UI based on selected year if recurring
    $items = $rows->map(function($r) use ($year){
        $md = Carbon::parse($r->holidate)->format('m-d');
        $displayDate = $r->is_recurring ? Carbon::createFromFormat('Y-m-d', $year.'-'.$md)->toDateString() : $r->holidate;
        return [
            'holidayID'    => (int)$r->holidayID,
            'holidate'     => $r->holidate,
            'display_date' => $displayDate,
            'status'       => $r->status,
            'title'        => $r->title,
            'is_recurring' => (int)$r->is_recurring
        ];
    })->values();

    return response()->json(['success'=>true,'year'=>$year,'items'=>$items]);
}

public function storeHoliday(Request $request): JsonResponse
{
    $v = Validator::make($request->all(), [
        'title'        => 'required|string|max:255',
        'status'       => 'required|string|max:255',
        'holidate'     => 'required|date',
        'is_recurring' => 'required|in:0,1'
    ]);

    if ($v->fails()) {
        return response()->json(['success'=>false,'errors'=>$v->errors()], 422);
    }

    $id = DB::table('tblholiday')->insertGetId([
        'holidate'     => $request->holidate,
        'status'       => $request->status,
        'title'        => $request->title,
        'is_recurring' => (int)$request->is_recurring
    ]);

    return response()->json(['success'=>true,'id'=>$id]);
}

public function updateHoliday(Request $request): JsonResponse
{
    $v = Validator::make($request->all(), [
        'holidayID'    => 'required|integer|exists:tblholiday,holidayID',
        'title'        => 'required|string|max:255',
        'status'       => 'required|string|max:255',
        'holidate'     => 'required|date',
        'is_recurring' => 'required|in:0,1'
    ]);

    if ($v->fails()) {
        return response()->json(['success'=>false,'errors'=>$v->errors()], 422);
    }

    DB::table('tblholiday')->where('holidayID', $request->holidayID)->update([
        'holidate'     => $request->holidate,
        'status'       => $request->status,
        'title'        => $request->title,
        'is_recurring' => (int)$request->is_recurring
    ]);

    return response()->json(['success'=>true]);
}

public function deleteHoliday(Request $request): JsonResponse
{
    $v = Validator::make($request->all(), [
        'holidayID' => 'required|integer|exists:tblholiday,holidayID'
    ]);

    if ($v->fails()) {
        return response()->json(['success'=>false,'errors'=>$v->errors()], 422);
    }

    DB::table('tblholiday')->where('holidayID', $request->holidayID)->delete();
    return response()->json(['success'=>true]);
}
}
