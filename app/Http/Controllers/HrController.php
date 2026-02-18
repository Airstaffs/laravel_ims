<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HrController extends Controller
{
    private const DB_TZ = 'America/Los_Angeles';

    public function getEmployees()
    {
        $today = date('Y-m-d');

        $employees = \DB::table('tbluser as u')
            ->leftJoin('tblemployeerate as er', function ($join) use ($today) {
                $join->on('er.employee_id', '=', 'u.id')
                    ->where('er.effective_start', '<=', $today)
                    ->where(function ($query) use ($today) {
                        $query->whereNull('er.effective_end')
                            ->orWhere('er.effective_end', '>=', $today);
                    });
            })
            ->select(
                'u.id',
                'u.username',
                \DB::raw('u.username as name'),
                \DB::raw('u.office_role as position'),
                'u.accounttype',
                'u.active',
                'er.monthly_rate as current_monthly_rate',
                'er.hourly_rate as current_hourly_rate',
                'er.currency as current_currency'
            )
            ->groupBy('u.id', 'u.username', 'u.office_role', 'u.accounttype', 'u.active', 'er.monthly_rate', 'er.hourly_rate', 'er.currency')
            ->orderBy('u.id', 'asc')
            ->get();

        return response()->json($employees);
    }

    public function addEmployee(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'username' => 'required|string|max:255',
                'office_role' => 'required|string|max:255',
                'accounttype' => 'required|in:PH,US',
            ]);

            // Insert the employee
            $employeeId = \DB::table('tbluser')->insertGetId([
                'username' => $validated['username'],
                'office_role' => $validated['office_role'],
                'accounttype' => $validated['accounttype'],
                'password' => bcrypt('password123'),
                'active' => 1, // Set as active by default
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Get the newly created employee with the same structure as getEmployees
            $today = date('Y-m-d');

            $newEmployee = \DB::table('tbluser as u')
                ->select(
                    'u.id',
                    'u.username',
                    \DB::raw('u.username as name'),
                    \DB::raw('u.office_role as position'),
                    'u.accounttype',
                    'u.active',
                    \DB::raw("(SELECT er.monthly_rate
                   FROM tblemployeerate er
                   WHERE er.employee_id = u.id
                     AND er.effective_start <= '{$today}'
                     AND (er.effective_end IS NULL OR er.effective_end >= '{$today}')
                   ORDER BY er.effective_start DESC
                   LIMIT 1) as current_monthly_rate"),
                    \DB::raw("(SELECT er.hourly_rate
                   FROM tblemployeerate er
                   WHERE er.employee_id = u.id
                     AND er.effective_start <= '{$today}'
                     AND (er.effective_end IS NULL OR er.effective_end >= '{$today}')
                   ORDER BY er.effective_start DESC
                   LIMIT 1) as current_hourly_rate"),
                    \DB::raw("(SELECT er.currency
                   FROM tblemployeerate er
                   WHERE er.employee_id = u.id
                     AND er.effective_start <= '{$today}'
                     AND (er.effective_end IS NULL OR er.effective_end >= '{$today}')
                   ORDER BY er.effective_start DESC
                   LIMIT 1) as current_currency")
                )
                ->where('u.id', $employeeId)
                ->first();

            return response()->json($newEmployee, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function showemployeedetails($userId)
    {
        $profile = DB::table('tbluser_profile')
            ->where('user_id', $userId)
            ->first();

        // Fallback: if missing, seed from tbluser (optional)
        if (! $profile) {
            $u = DB::table('tbluser')->where('id', $userId)->first();
            if ($u) {
                $profile = (object) [
                    'full_name' => $u->username,
                    'work_email' => $u->email,
                    'contact_phone' => null,
                    'birthdate' => null,
                    'address' => null,
                    'ice_name' => null,
                    'ice_relationship' => null,
                    'ice_phone' => null,
                ];
            }
        }

        return response()->json(['profile' => $profile]);
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
            ->when($employeeId, fn ($qq) => $qq->where('er.employee_id', $employeeId))
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
                $request->input('dateTo'),
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
     *
     * @param  mixed  $id
     * @return \Illuminate\Http\JsonResponse
     *                                       JSON Structure of changes field
     *                                       {
     *                                       "TimeIn": { "from": "2025-08-09 08:00:00", "to": "2025-08-09 09:00:00" },
     *                                       "Notes": { "from": "Late", "to": "On Time" }
     *                                       }
     *
     **/
    public function editTimeRecord(Request $request, int $id): JsonResponse
    {
        // 1) Load BEFORE state from DB
        $beforeRow = DB::table('tblemployeeclocks')->where('ID', $id)->first();
        if (! $beforeRow) {
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
            if (! array_key_exists($col, $after)) {
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

    public function listClockEditHistory(Request $request): JsonResponse
    {
        $q = DB::table('tblemployeeclocks_edit_history')
            ->orderByDesc('edit_timestamp');

        // (optional) simple filters
        if ($request->filled('clock_id')) {
            $q->where('clock_id', (int) $request->clock_id);
        }
        if ($request->filled('edited_by')) {
            $q->where('edited_by', (int) $request->edited_by);
        }
        if ($request->filled('from')) {
            $q->whereDate('edit_timestamp', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('edit_timestamp', '<=', $request->to);
        }

        $rows = $q->get()->map(function ($row) {
            $row->changes = json_decode($row->changes, true);

            return $row;
        });

        return response()->json($rows);
    }

    // GET /api/hr/time-records/{id}/edit-history
    public function getClockEditHistoryByClock(int $id): JsonResponse
    {
        $rows = DB::table('tblemployeeclocks_edit_history')
            ->where('clock_id', $id)
            ->orderByDesc('edit_timestamp')
            ->get()
            ->map(function ($row) {
                $row->changes = json_decode($row->changes, true);

                return $row;
            });

        return response()->json($rows);
    }

    public function getLeaveHistory()
    {
        return response()->json([
            ['employee' => 'Bob', 'type' => 'Vacation', 'date_from' => '2025-08-01', 'date_to' => '2025-08-05'],
        ]);
    }

    public function getViolations()
    {
        return response()->json([
            ['employee' => 'Alice', 'violation' => 'Late', 'date' => '2025-07-30'],
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
                'error' => 'Provide at least monthly_rate or hourly_rate.',
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
                $newEnd = date('Y-m-d', strtotime($start.' -1 day'));
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
        $year = (int) ($request->input('year') ?: Carbon::now()->year);

        $rows = DB::table('tblholiday')
            ->select('holidayID', 'holidate', 'status', 'title', 'is_recurring')
            ->orderBy('holidate', 'asc')
            ->get();

        // Expand display_date for UI based on selected year if recurring
        $items = $rows->map(function ($r) use ($year) {
            $md = Carbon::parse($r->holidate)->format('m-d');
            $displayDate = $r->is_recurring ? Carbon::createFromFormat('Y-m-d', $year.'-'.$md)->toDateString() : $r->holidate;

            return [
                'holidayID' => (int) $r->holidayID,
                'holidate' => $r->holidate,
                'display_date' => $displayDate,
                'status' => $r->status,
                'title' => $r->title,
                'is_recurring' => (int) $r->is_recurring,
            ];
        })->values();

        return response()->json(['success' => true, 'year' => $year, 'items' => $items]);
    }

    public function storeHoliday(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'holidate' => 'required|date',
            'is_recurring' => 'required|in:0,1',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $id = DB::table('tblholiday')->insertGetId([
            'holidate' => $request->holidate,
            'status' => $request->status,
            'title' => $request->title,
            'is_recurring' => (int) $request->is_recurring,
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function updateHoliday(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'holidayID' => 'required|integer|exists:tblholiday,holidayID',
            'title' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'holidate' => 'required|date',
            'is_recurring' => 'required|in:0,1',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        DB::table('tblholiday')->where('holidayID', $request->holidayID)->update([
            'holidate' => $request->holidate,
            'status' => $request->status,
            'title' => $request->title,
            'is_recurring' => (int) $request->is_recurring,
        ]);

        return response()->json(['success' => true]);
    }

    public function deleteHoliday(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'holidayID' => 'required|integer|exists:tblholiday,holidayID',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        DB::table('tblholiday')->where('holidayID', $request->holidayID)->delete();

        return response()->json(['success' => true]);
    }

    public function listAnnouncements()
    {
        $rows = DB::table('tblannouncements')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($rows);
    }

    public function storeAnnouncement(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer'],
            'groups' => ['array'], // e.g. ['PH','US']
        ]);

        // Expand groups into user IDs (server-side safety)
        $userIds = collect($data['user_ids'] ?? []);

        $groups = collect($data['groups'] ?? [])
            ->map(fn ($g) => strtoupper(trim($g)))
            ->filter(fn ($g) => in_array($g, ['PH', 'US']))
            ->values();

        if ($groups->isNotEmpty()) {
            $groupUserIds = DB::table('tbluser')
                ->whereIn('accounttype', $groups->all())
                ->pluck('id');
            $userIds = $userIds->merge($groupUserIds);
        }

        // Dedup + sanitize
        $finalUserIds = $userIds->unique()->values()->map(fn ($v) => (int) $v)->all();

        $createdByUserId = session('userid') ?? optional($request->user())->id;
        $createdBy = session('user_name') ?? optional($request->user())->username;

        DB::beginTransaction();
        try {
            // 1) Save announcement history
            $annId = DB::table('tblannouncements')->insertGetId([
                'title' => $data['title'],
                'content' => $data['content'] ?? null,
                'recipients_json' => json_encode($finalUserIds),
                'group_filters_json' => $groups->isNotEmpty() ? $groups->implode(',') : null,
                'created_by' => $createdBy,
                'created_by_user_id' => $createdByUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2) Create a notification for recipients
            $notifId = DB::table('tblnotifications')->insertGetId([
                'module' => 'HR',
                'title' => 'Announcement: '.$data['title'],
                'subtitle' => null,
                'content' => $data['content'] ?? null,
                'severity' => 'info',
                'action_made' => 'announcement_created',
                'link_data' => json_encode([
                    'type' => 'modal',      // or 'redirect' if you have a page
                    'method' => 'GET',
                    'url' => null,
                    'modal_id' => 'announcement-view',
                    'data' => ['announcement_id' => $annId],
                ]),
                'created_at' => now(),
            ]);

            // 3) Assign notification to each recipient
            foreach ($finalUserIds as $uid) {
                DB::table('tblnotificationsuser')->insert([
                    'notif_id' => $notifId,
                    'userid' => $uid,
                    'read_status' => 'unread',
                    'created_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'announcement_id' => $annId,
                'notif_id' => $notifId,
                'recipients' => $finalUserIds,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function acknowledgeAnnouncement(Request $request)
    {
        $request->validate([
            'announcement_id' => 'required|integer|exists:tblannouncements,id',
        ]);

        // ✅ Prefer logged-in user (most reliable)
        $user = auth()->user();

        $username =
            $user->username ??              // if you have "username"
            $user->name ??                  // or "name"
            $user->email ??                 // fallback
            session('user_name') ??         // your old session key
            session('username') ??          // common variant
            session('name') ??              // common variant
            $request->input('username');    // last resort (payload)

        if (! $username) {
            return response()->json([
                'success' => false,
                'message' => 'Username missing (not authenticated / not in session).',
            ], 422);
        }

        $ann = DB::table('tblannouncements')
            ->where('id', $request->announcement_id)
            ->first();

        if (! $ann) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement not found.',
            ], 404);
        }

        $readby = json_decode($ann->readby ?? '[]', true);
        if (! is_array($readby)) {
            $readby = [];
        }

        if (! in_array($username, $readby, true)) {
            $readby[] = $username;

            DB::table('tblannouncements')
                ->where('id', $ann->id)
                ->update([
                    'readby' => json_encode(array_values(array_unique($readby))),
                ]);
        }

        return response()->json([
            'success' => true,
            'announcement_id' => $ann->id,
            'readby' => $readby,
        ]);
    }

    public function saveAnnouncement(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:tblannouncements,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],           // from UI: content -> message
            'start_at' => ['nullable', 'string'],           // 'YYYY-MM-DDTHH:MM' local
            'end_at' => ['nullable', 'string'],
            'save_mode' => ['required', 'in:draft,active'],  // status
            'recipients' => ['nullable'],                    // array of user IDs or []
        ]);

        $userTz = session('usertimezone', 'Asia/Manila');

        // Convert local → UTC for DB
        $startUtc = $data['start_at'] ? Carbon::parse($data['start_at'], $userTz)->setTimezone('UTC') : null;
        $endUtc = $data['end_at'] ? Carbon::parse($data['end_at'], $userTz)->setTimezone('UTC') : null;

        // sanitize recipients to int[]
        $recips = $request->input('recipients', []);
        if (! is_array($recips)) {
            $recips = [];
        }
        $recips = collect($recips)->map(fn ($v) => (int) $v)->unique()->values()->all();

        $row = [
            'title' => $data['title'],
            'content' => $data['message'] ?? null,
            'start_at' => $startUtc,
            'end_at' => $endUtc,
            'is_active' => $data['save_mode'] === 'active' ? 1 : 0,
            'recipients_json' => json_encode($recips),
            'updated_at' => now('UTC'),
        ];

        if (! empty($data['id'])) {
            \DB::table('tblannouncements')->where('id', $data['id'])->update($row);
            $id = (int) $data['id'];
        } else {
            $row['priority'] = 0;
            $row['readby'] = json_encode([]);
            $row['created_at'] = now('UTC');
            $id = \DB::table('tblannouncements')->insertGetId($row);
        }

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function adminListAnnouncements(Request $request)
    {
        $userTz = session('usertimezone', 'Asia/Manila');
        $username = session('user_name') ?? null;

        $status = $request->query('status', 'all');   // all|active|draft
        $q = trim($request->query('q', ''));

        $rows = \DB::table('tblannouncements')
            ->when($status === 'active', fn ($q) => $q->where('is_active', 1))
            ->when($status === 'draft', fn ($q) => $q->where('is_active', 0))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('title', 'like', "%$q%")
                        ->orWhere('content', 'like', "%$q%");
                });
            })
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->orderBy('created_at', 'desc')
            ->get();

        $payload = $rows->map(function ($r) use ($userTz, $username) {
            $readby = is_array($r->readby) ? $r->readby : (json_decode($r->readby, true) ?? []);
            $recipients = json_decode($r->recipients_json, true);

            $startLocal = $r->start_at ? Carbon::parse($r->start_at, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s') : null;
            $endLocal = $r->end_at ? Carbon::parse($r->end_at, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s') : null;

            return [
                'id' => $r->id,
                'title' => $r->title,
                'message' => $r->content,
                'start_at' => $startLocal,
                'end_at' => $endLocal,
                'is_active' => (int) $r->is_active === 1,
                'readby_count' => is_array($readby) ? count($readby) : 0,
                'read_by_me' => $username ? in_array($username, $readby, true) : false,
                // recipients can be [] of user IDs (preferred) — UI maps to names
                'recipients' => is_array($recipients) ? $recipients : [],
            ];
        })->values();

        return response()->json($payload);
    }

    public function toggleAnnouncementActive(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:tblannouncements,id'],
            'make_active' => ['required', 'boolean'],
        ]);

        \DB::table('tblannouncements')
            ->where('id', $data['id'])
            ->update(['is_active' => $data['make_active'] ? 1 : 0, 'updated_at' => now('UTC')]);

        return response()->json(['success' => true]);
    }

    public function dashviewAnnouncement(Request $request)
    {
        $userTz = session('usertimezone', 'Asia/Manila');
        $username = session('user_name') ?? null;
        $userId = session('userid'); // <-- used for recipients gating

        $nowUtc = Carbon::now('UTC');
        $includeAck = (bool) $request->boolean('include_ack', false);

        $rows = \DB::table('tblannouncements')
            ->where('is_active', 1)
            ->where(function ($q) use ($nowUtc) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $nowUtc);
            })
            ->where(function ($q) use ($nowUtc) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $nowUtc);
            })
            ->orderByDesc('priority')
            ->orderBy('start_at', 'desc')
            ->get();

        // recipients gating: if recipients_json not empty, restrict to those including current user id
        if ($userId) {
            $rows = $rows->filter(function ($r) use ($userId) {
                $rec = json_decode($r->recipients_json, true);
                if (is_array($rec) && count($rec) > 0) {
                    return in_array((int) $userId, array_map('intval', $rec), true);
                }

                return true; // empty means "everyone"
            })->values();
        }

        if (! $includeAck && $username) {
            $rows = $rows->reject(function ($r) use ($username) {
                $readby = is_array($r->readby) ? $r->readby : (json_decode($r->readby, true) ?? []);

                return in_array($username, $readby, true);
            })->values();
        }

        $payload = $rows->map(function ($r) use ($userTz) {
            $startLocal = $r->start_at ? Carbon::parse($r->start_at, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s') : null;
            $endLocal = $r->end_at ? Carbon::parse($r->end_at, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s') : null;

            return [
                'id' => $r->id,
                'title' => $r->title,
                'message' => $r->content,
                'start_at' => $startLocal,
                'end_at' => $endLocal,
                'readby' => is_array($r->readby) ? $r->readby : (json_decode($r->readby, true) ?? []),
                'priority' => (int) ($r->priority ?? 0),
            ];
        });

        return response()->json($payload);
    }

    private function dayName(int $dow): string
    {
        return [0 => 'Everyday', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'][$dow] ?? '???';
    }

    private function makeTitle(int $dow, string $start, string $end, bool $overn): string
    {
        $dash = '–';

        return $this->dayName($dow).' '.substr($start, 0, 5).$dash.substr($end, 0, 5).($overn ? ' (+1)' : '');
    }

    private function dbNow(): Carbon
    {
        return Carbon::now('America/Los_Angeles');
    }

    private function maskFromDayOfWeek(?int $dow): int
    {
        if (! $dow || $dow === 0) {
            return 127;
        }                   // legacy 0 ⇒ Everyday
        if ($dow < 1 || $dow > 7) {
            return 127;
        }

        return 1 << ($dow - 1);                                 // 1..7 ⇒ bit
    }

    private function inferDayOfWeekFromMask(int $mask): int
    {
        // if exactly one bit, return its 1..7 index; else 0 (multi/everyday)
        if ($mask === 0) {
            return 0;
        }
        if (($mask & ($mask - 1)) === 0) {                      // power of two
            $bitIndex = (int) log($mask, 2);                     // 0..6

            return $bitIndex + 1;                               // 1..7
        }

        return 0;
    }

    private function parseDaysInput(Request $r, ?int $fallbackDow = 0): array
    {
        // Accept either days_mask OR legacy day_of_week; normalize to both.
        $hasMask = $r->filled('days_mask');
        $mask = $hasMask ? max(0, min(127, (int) $r->input('days_mask'))) : null;

        if ($mask === null) {
            $dow = (int) ($r->input('day_of_week', $fallbackDow));
            $mask = $this->maskFromDayOfWeek($dow);
        }

        if ($mask === 0) {                                      // never store 0
            $mask = 127;
        }

        $dow = $this->inferDayOfWeekFromMask($mask);

        return [$mask, $dow];
    }

    private function makeTitleFromMask(int $mask, string $start, string $end, bool $overn): string
    {
        $names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $list = [];
        for ($i = 0; $i < 7; $i++) {
            if ($mask & (1 << $i)) {
                $list[] = $names[$i];
            }
        }

        $days =
            count($list) === 7 ? 'Everyday' :
            (implode('/', $list));

        return sprintf('%s %s–%s%s', $days, $start, $end, $overn ? ' (+1)' : '');
    }

    public function listTimesched(Request $r)
    {
        $q = DB::table('tbltimesched');

        if ($r->filled('day_of_week')) {
            $dow = (int) $r->input('day_of_week');
            if ($dow >= 1 && $dow <= 7) {
                $bit = 1 << ($dow - 1);
                $q->where(function ($w) use ($dow, $bit) {
                    $w->where('day_of_week', $dow)              // legacy single-day
                        ->orWhereRaw('(COALESCE(days_mask, 0) & ?) <> 0', [$bit]); // any template that includes that day
                });
            } elseif ($dow === 0) {
                // “Everyday” filter: rows that cover all 7 OR legacy 0
                $q->where(function ($w) {
                    $w->where('day_of_week', 0)
                        ->orWhere('days_mask', 127);
                });
            }
        }

        if ($r->filled('is_active')) {
            $q->where('is_active', (int) $r->input('is_active'));
        }

        // Order: group by the first day present (for masks), then by time
        $q->orderByRaw('
        CASE
          WHEN days_mask IS NULL OR days_mask = 0 THEN day_of_week
          WHEN days_mask & 1   THEN 1
          WHEN days_mask & 2   THEN 2
          WHEN days_mask & 4   THEN 3
          WHEN days_mask & 8   THEN 4
          WHEN days_mask & 16  THEN 5
          WHEN days_mask & 32  THEN 6
          WHEN days_mask & 64  THEN 7
          ELSE 0
        END
    ')->orderBy('start_time');

        return response()->json(['success' => true, 'data' => $q->get()]);
    }

    public function createTimesched(Request $r)
    {
        $d = $r->validate([
            'days_mask' => 'nullable|integer|min:0|max:127',     // NEW (optional)
            'day_of_week' => 'nullable|integer|min:0|max:7',     // keep for legacy callers
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'end_next_day' => 'nullable|boolean',
            'unpaid_break_minutes' => 'nullable|integer|min:0|max:600',
            'title' => 'nullable|string|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        // Normalize days
        [$mask, $dow] = $this->parseDaysInput($r);

        $overn = (bool) ($d['end_next_day'] ?? false);
        $s = \Carbon\Carbon::createFromFormat('H:i', $d['start_time']);
        $e = \Carbon\Carbon::createFromFormat('H:i', $d['end_time']);
        if (! $overn && $e->lessThanOrEqualTo($s)) {
            return response()->json(['success' => false, 'error' => 'end_time must be after start_time for same-day'], 422);
        }

        $title = $d['title'] ?? $this->makeTitleFromMask($mask, $d['start_time'], $d['end_time'], $overn);

        $id = DB::table('tbltimesched')->insertGetId([
            'day_of_week' => $dow, // legacy mirror: 1..7 if single, else 0
            'days_mask' => $mask,  // NEW canonical
            'start_time' => $d['start_time'],
            'end_time' => $d['end_time'],
            'end_next_day' => $overn ? 1 : 0,
            'unpaid_break_minutes' => (int) ($d['unpaid_break_minutes'] ?? 60),
            'title' => $title,
            'is_active' => (int) ($d['is_active'] ?? 1),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => $this->dbNow(),
            'updated_at' => $this->dbNow(),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function updateTimesched($id, Request $r)
    {
        $row = DB::table('tbltimesched')->where('timeschedId', $id)->first();
        if (! $row) {
            return response()->json(['success' => false, 'error' => 'not found'], 404);
        }

        $d = $r->validate([
            'days_mask' => 'nullable|integer|min:0|max:127',
            'day_of_week' => 'nullable|integer|min:0|max:7',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'end_next_day' => 'nullable|boolean',
            'unpaid_break_minutes' => 'nullable|integer|min:0|max:600',
            'title' => 'nullable|string|max:120',
            'is_active' => 'nullable|boolean',
            // NEW
            'early_login_mins' => ['nullable', 'integer', 'min:0', 'max:180'],
            'early_clockin_mins' => ['nullable', 'integer', 'min:0', 'max:180'],
            'grace_clockout_mins' => ['nullable', 'integer', 'min:0', 'max:180'],
        ]);

        // Normalize days (fall back to existing when not provided)
        $req = new Request([
            'days_mask' => $d['days_mask'] ?? $row->days_mask,
            'day_of_week' => $d['day_of_week'] ?? $row->day_of_week,
        ]);
        [$mask, $dow] = $this->parseDaysInput($req, (int) $row->day_of_week);

        $start = $d['start_time'] ?? $row->start_time;
        $end = $d['end_time'] ?? $row->end_time;
        $overn = array_key_exists('end_next_day', $d) ? (bool) $d['end_next_day'] : (bool) $row->end_next_day;

        $s = \Carbon\Carbon::createFromFormat('H:i', $start);
        $e = \Carbon\Carbon::createFromFormat('H:i', $end);
        if (! $overn && $e->lessThanOrEqualTo($s)) {
            return response()->json(['success' => false, 'error' => 'end_time must be after start_time for same-day'], 422);
        }

        $payload = [
            'day_of_week' => $dow,
            'days_mask' => $mask,
            'start_time' => $start,
            'end_time' => $end,
            'end_next_day' => $overn ? 1 : 0,
            'unpaid_break_minutes' => (int) ($d['unpaid_break_minutes'] ?? $row->unpaid_break_minutes),
            'title' => $d['title'] ?? $this->makeTitleFromMask($mask, $start, $end, $overn),
            'is_active' => (int) ($d['is_active'] ?? $row->is_active),
            // NEW (preserve existing if not provided)
            'early_login_mins' => array_key_exists('early_login_mins', $d) ? (int) $d['early_login_mins'] : (int) $row->early_login_mins,
            'early_clockin_mins' => array_key_exists('early_clockin_mins', $d) ? (int) $d['early_clockin_mins'] : (int) $row->early_clockin_mins,
            'grace_clockout_mins' => array_key_exists('grace_clockout_mins', $d) ? (int) $d['grace_clockout_mins'] : (int) $row->grace_clockout_mins,

            'updated_by' => Auth::id(),
            'updated_at' => $this->dbNow(),
        ];

        DB::table('tbltimesched')->where('timeschedId', $id)->update($payload);

        return response()->json(['success' => true]);
    }

    public function deleteTimesched($id)
    {
        $ok = DB::table('tbltimesched')->where('timeschedId', $id)->delete();

        return response()->json(['success' => (bool) $ok]);
    }

    public function listUserSched(Request $r)
    {
        $r->validate(['userId' => 'required|integer']);

        $rows = DB::table('tblusersched as us')
            ->join('tbltimesched as ts', 'ts.timeschedId', '=', 'us.schedId')
            ->where('us.userId', $r->integer('userId'))
            ->selectRaw('
            us.userschedId,
            us.userId,
            us.schedId,
            us.schednote,
            us.effective_from,
            us.effective_to,
            us.is_active,
            ts.day_of_week,
            ts.days_mask,             -- NEW
            ts.start_time,
            ts.end_time,
            ts.end_next_day,
            ts.title,
            ts.is_active as sched_active
        ')
            ->orderBy('ts.day_of_week')
            ->orderBy('ts.start_time')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function createUserSched(Request $r)
    {
        $d = $r->validate([
            'userId' => 'required|integer',
            'schedId' => 'required|integer',
            'schednote' => 'nullable|string|max:255',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'sometimes|boolean',   // ⬅️ accept from UI
        ]);

        $id = DB::table('tblusersched')->insertGetId([
            'userId' => (int) $d['userId'],
            'schedId' => (int) $d['schedId'],
            'schednote' => $d['schednote'] ?? null,
            'effective_from' => $d['effective_from'] ?? null,
            'effective_to' => $d['effective_to'] ?? null,
            'is_active' => array_key_exists('is_active', $d) ? (int) $d['is_active'] : 1, // default active
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => \Carbon\Carbon::now('America/Los_Angeles'),
            'updated_at' => \Carbon\Carbon::now('America/Los_Angeles'),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function updateUserSched($id, Request $r)
    {
        $row = DB::table('tblusersched')->where('userschedId', $id)->first();
        if (! $row) {
            return response()->json(['success' => false, 'error' => 'not found'], 404);
        }

        $d = $r->validate([
            'schedId' => 'nullable|integer',
            'schednote' => 'nullable|string|max:255',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date',
            'is_active' => 'nullable|boolean',   // ⬅️ allow toggling
        ]);

        if (
            ! empty($d['effective_from']) && ! empty($d['effective_to']) &&
            \Carbon\Carbon::parse($d['effective_to'])->lt(\Carbon\Carbon::parse($d['effective_from']))
        ) {
            return response()->json(['success' => false, 'error' => 'effective_to must be on/after effective_from'], 422);
        }

        DB::table('tblusersched')->where('userschedId', $id)->update([
            'schedId' => $d['schedId'] ?? $row->schedId,
            'schednote' => $d['schednote'] ?? $row->schednote,
            'effective_from' => array_key_exists('effective_from', $d) ? $d['effective_from'] : $row->effective_from,
            'effective_to' => array_key_exists('effective_to', $d) ? $d['effective_to'] : $row->effective_to,
            'is_active' => array_key_exists('is_active', $d) ? (int) $d['is_active'] : $row->is_active,
            'updated_by' => Auth::id(),
            'updated_at' => \Carbon\Carbon::now('America/Los_Angeles'),
        ]);

        return response()->json(['success' => true]);
    }

    public function deleteUserSched($id)
    {
        $ok = DB::table('tblusersched')->where('userschedId', $id)->delete();

        return response()->json(['success' => (bool) $ok]);
    }

    // Get User Profile Details
    public function getUserProfileDetails(Request $req)
    {
        $uid = $req->user()->id;

        $user = DB::table('tbluser')->where('id', $uid)->first();
        $profile = DB::table('tbluser_profile')->where('user_id', $uid)->first();

        return response()->json([
            'user' => [
                'username' => $user->username ?? null,
                'office_role' => $user->office_role ?? $user->role ?? null,
                'accounttype' => $user->accounttype ?? null,
                'email' => $user->email ?? null,
            ],
            'profile' => [
                'full_name' => $profile->full_name ?? '',
                'work_email' => $profile->work_email ?? ($user->email ?? ''),
                'contact_phone' => $profile->contact_phone ?? '',
                'birthdate' => $profile->birthdate ?? '',
                'address' => $profile->address ?? '',
                'ice_name' => $profile->ice_name ?? '',
                'ice_relationship' => $profile->ice_relationship ?? '',
                'ice_phone' => $profile->ice_phone ?? '',
            ],
        ]);
    }

    // Update User Profile Details
    public function updateUserProfileDetails(Request $req)
    {
        $uid = $req->user()->id;

        // Check if this is the first login (null means not completed yet)
        $firstLoginValue = DB::table('tbluser')->where('id', $uid)->value('first_login');
        $firstLogin = is_null($firstLoginValue);

        // If first login, all required; otherwise keep your current nullable rules
        $rules = $firstLogin ? [
            'full_name' => 'required|string|max:255',
            'work_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:50',
            'birthdate' => 'required|date',
            'address' => 'required|string',
            'ice_name' => 'required|string|max:255',
            'ice_relationship' => 'required|string|max:100',
            'ice_phone' => 'required|string|max:50',
        ] : [
            'full_name' => 'nullable|string|max:255',
            'work_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string',
            'ice_name' => 'nullable|string|max:255',
            'ice_relationship' => 'nullable|string|max:100',
            'ice_phone' => 'nullable|string|max:50',
        ];

        $v = Validator::make($req->all(), $rules);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = [
            'full_name' => $req->input('full_name'),
            'work_email' => $req->input('work_email'),
            'contact_phone' => $req->input('contact_phone'),
            'birthdate' => $req->input('birthdate'),
            'address' => $req->input('address'),
            'ice_name' => $req->input('ice_name'),
            'ice_relationship' => $req->input('ice_relationship'),
            'ice_phone' => $req->input('ice_phone'),
            'updated_at' => now(),
        ];

        DB::transaction(function () use ($uid, $data, $firstLogin) {
            $exists = DB::table('tbluser_profile')->where('user_id', $uid)->exists();

            if ($exists) {
                DB::table('tbluser_profile')->where('user_id', $uid)->update($data);
            } else {
                DB::table('tbluser_profile')->insert($data + [
                    'user_id' => $uid,
                    'created_at' => now(),
                ]);
            }

            // Keep tbluser.email in sync with work_email if provided
            if (! empty($data['work_email'])) {
                DB::table('tbluser')->where('id', $uid)->update([
                    'email' => $data['work_email'],
                    'updated_at' => now(),
                ]);
            }

            // If this was the first-login completion, set the timestamp
            if ($firstLogin) {
                DB::table('tbluser')->where('id', $uid)->update([
                    'first_login' => now(),
                ]);
                Log::info('First login completed, setting first_login timestamp', ['user_id' => $uid]);
            }
        });

        return response()->json(['ok' => true, 'message' => 'Account details updated.']);
    }

    public function getUserProfileDetailsById($userId)
    {
        // Optional: gate this (e.g., only HR/admin)
        // abort_unless(auth()->user() && auth()->user()->office_role === 'admin', 403);

        $user = DB::table('tbluser')->where('id', $userId)->first();
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $profile = DB::table('tbluser_profile')->where('user_id', $userId)->first();

        return response()->json([
            'user' => [
                'username' => $user->username ?? null,
                'office_role' => $user->office_role ?? $user->role ?? null,
                'accounttype' => $user->accounttype ?? null,
                'email' => $user->email ?? null,
            ],
            'profile' => [
                'full_name' => $profile->full_name ?? ($user->username ?? ''),
                'work_email' => $profile->work_email ?? ($user->email ?? ''),
                'contact_phone' => $profile->contact_phone ?? '',
                'birthdate' => $profile->birthdate ?? '',
                'address' => $profile->address ?? '',
                'ice_name' => $profile->ice_name ?? '',
                'ice_relationship' => $profile->ice_relationship ?? '',
                'ice_phone' => $profile->ice_phone ?? '',
            ],
        ]);
    }

    // Module Controller
    private const MODULE_KEYS = [
        'order',
        'unreceived',
        'receiving',
        'labeling',
        'testing',
        'cleaning',
        'packing',
        'stockroom',
        'fnsku',
        'validation',
        'productionarea',
        'fbmorder',
        'returnscanner',
        'notfound',
        'asinoption',
        'houseage',
        'asinlist',
        'printer',
        'humanresource',
        'rts',
    ];

    /** Small sanitizer to mirror how your store_* columns are named */
    private function storeColFromName(string $name): string
    {
        // Keep letters/numbers as-is (case preserved), strip everything else.
        $slug = preg_replace('/[^A-Za-z0-9]/', '', $name);

        return 'store_'.$slug;
    }

    /** Discover all store_* columns currently present on tbluser (no migration needed). */
    private function getUserStoreColumns(): array
    {
        $rows = DB::select("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbluser'
              AND COLUMN_NAME LIKE 'store\\_%'
        ");

        return array_map(fn ($r) => $r->COLUMN_NAME, $rows);
    }

    /** Map { store_col => storename } by checking existing stores. */
    private function mapStoreColsToNames(): array
    {
        $stores = DB::table('tblstores')->select('store_id', 'storename')->get();
        $map = [];
        foreach ($stores as $s) {
            $map[$this->storeColFromName($s->storename)] = $s->storename;
        }

        return $map; // note: may not include legacy store_* columns that don't exist in tblstores
    }

    public function listStores()
    {
        $stores = DB::table('tblstores')
            ->select('store_id as id', 'storename', 'abbreviation')
            ->orderBy('storename')->get();

        return response()->json(['stores' => $stores]);
    }

    /** GET: modules + main_module + store privileges for an employee */
    public function getEmployeePermissions(int $id)
    {
        $user = DB::table('tbluser')->where('id', $id)->first();
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // modules -> booleans
        $modules = [];
        foreach (self::MODULE_KEYS as $k) {
            $modules[$k] = (bool) ($user->$k ?? 0);
        }

        // main_module -> only valid if in list
        $main = in_array(($user->main_module ?? null), self::MODULE_KEYS, true)
            ? $user->main_module
            : null;

        // stores -> from store_* columns set to 1
        $storeCols = $this->getUserStoreColumns();
        $colToName = $this->mapStoreColsToNames();

        $grantedStoreNames = [];
        foreach ($storeCols as $col) {
            if ((int) ($user->$col ?? 0) === 1) {
                // Prefer a name from tblstores mapping; if none, use the column suffix as fallback
                $grantedStoreNames[] = $colToName[$col] ?? preg_replace('/^store_/', '', $col);
            }
        }

        return response()->json([
            'user_id' => $user->id,
            'username' => $user->username,
            'modules' => $modules,     // { order: true, ... }
            'main_module' => $main,        // string|null
            'stores' => $grantedStoreNames, // array of storename strings
        ]);
    }

    /**
     * POST: update modules, main_module, and store privileges.
     * Accepts either:
     *  - "modules": {key: bool}
     *  - "main_module": string|null   (must be in MODULE_KEYS or null)
     *  - "stores": [ "StoreName A", "StoreName B", ... ] (storename strings)
     *
     * No migration. We flip existing store_* columns, leaving unknown store_* columns off unless included.
     */
    public function updateEmployeePermissions(Request $req, int $id)
    {
        $user = DB::table('tbluser')->where('id', $id)->first();
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $data = $req->validate([
            'modules' => ['array'],
            'modules.*' => ['boolean'],
            'main_module' => ['nullable', Rule::in(self::MODULE_KEYS)],
            'stores' => ['array'],
            'stores.*' => ['string'], // storename
        ]);

        $updates = [];

        // 1) Modules: write tinyints
        if (isset($data['modules'])) {
            foreach (self::MODULE_KEYS as $k) {
                if (array_key_exists($k, $data['modules'])) {
                    $updates[$k] = $data['modules'][$k] ? 1 : 0;
                }
            }
        }

        // 2) main_module: enforce single value (or null)
        if (array_key_exists('main_module', $data)) {
            $updates['main_module'] = $data['main_module'] ?: null;
        }

        // 3) Store privileges via existing store_* columns
        if (isset($data['stores'])) {
            $requestedNames = array_values(array_unique(array_map('strval', $data['stores'])));

            // Compute the columns that correspond to requested names
            $wantedCols = [];
            foreach ($requestedNames as $name) {
                $wantedCols[] = $this->storeColFromName($name);
            }
            $wantedCols = array_unique($wantedCols);

            // Flip only columns that actually exist
            $allStoreCols = $this->getUserStoreColumns();
            foreach ($allStoreCols as $col) {
                $updates[$col] = in_array($col, $wantedCols, true) ? 1 : 0;
            }
        }

        // If nothing to change
        if (empty($updates)) {
            return response()->json(['message' => 'No changes'], 200);
        }

        // Write changes
        DB::table('tbluser')->where('id', $id)->update($updates);

        // Return updated snapshot
        return $this->getEmployeePermissions($id);
    }

    public function getPayslips(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        $payslips = \DB::table('tblpayslips')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($payslips);
    }

    public function createPayslip(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'employee_name' => 'required|string',
            'payout_date' => 'required|date',
            'cutoff_from' => 'required|date',
            'cutoff_to' => 'required|date',
            'total_days' => 'required|integer',
            'total_hours' => 'required|numeric',
            'hourly_rate' => 'required|numeric',
            'currency' => 'required|string|max:3',
            'basic_pay' => 'required|numeric',
            'regular_holiday_hours' => 'nullable|numeric',
            'regular_holiday_pay' => 'nullable|numeric',
            'special_holiday_hours' => 'nullable|numeric',
            'special_holiday_pay' => 'nullable|numeric',
            'gross_pay' => 'required|numeric',
            'deductions' => 'nullable|numeric',
            'net_pay' => 'required|numeric',
            'deduction_details' => 'nullable|array',
            'holiday_details' => 'nullable|array',
        ]);

        try {
            $deductionDetailsJson = null;
            if (! empty($validated['deduction_details'])) {
                $deductionDetailsJson = json_encode($validated['deduction_details']);
            }

            $holidayDetailsJson = null;
            if (! empty($validated['holiday_details'])) {
                $holidayDetailsJson = json_encode($validated['holiday_details']);
            }

            // Insert payslip
            $payslipId = \DB::table('tblpayslips')->insertGetId([
                'employee_id' => $validated['employee_id'],
                'employee_name' => $validated['employee_name'],
                'payout_date' => $validated['payout_date'],
                'cutoff_from' => $validated['cutoff_from'],
                'cutoff_to' => $validated['cutoff_to'],
                'total_days' => $validated['total_days'],
                'total_hours' => $validated['total_hours'],
                'hourly_rate' => $validated['hourly_rate'],
                'currency' => $validated['currency'],
                'basic_pay' => $validated['basic_pay'],
                'regular_holiday_hours' => $validated['regular_holiday_hours'] ?? 0,
                'regular_holiday_pay' => $validated['regular_holiday_pay'] ?? 0,
                'special_holiday_hours' => $validated['special_holiday_hours'] ?? 0,
                'special_holiday_pay' => $validated['special_holiday_pay'] ?? 0,
                'gross_pay' => $validated['gross_pay'],
                'deductions' => $validated['deductions'] ?? 0,
                'net_pay' => $validated['net_pay'],
                'deduction_details' => $deductionDetailsJson, // Store as JSON string
                'holiday_details' => $holidayDetailsJson,
                'status' => 'draft',
                'created_by' => auth()->user()->username ?? 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payslip created successfully',
                'payslip_id' => $payslipId,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payslip',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deletePayslip($id)
    {
        try {
            \DB::table('tblpayslips')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payslip deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payslip',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function calculateHoursFromRecord($record)
    {
        try {
            $timeIn = new \DateTime($record['TimeIn']);
            $timeOut = new \DateTime($record['TimeOut']);

            $diff = $timeOut->getTimestamp() - $timeIn->getTimestamp();

            // Subtract break time
            if (! empty($record['shortbreak_start']) && ! empty($record['shortbreak_end'])) {
                $breakStart = new \DateTime($record['shortbreak_start']);
                $breakEnd = new \DateTime($record['shortbreak_end']);
                $diff -= ($breakEnd->getTimestamp() - $breakStart->getTimestamp());
            } elseif (! empty($record['shortbreak_totaltime'])) {
                $diff -= ($record['shortbreak_totaltime'] * 60);
            }

            return round($diff / 3600, 2); // Convert to hours
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getHolidays(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = \DB::table('tblholiday')
            ->select('holidayID', 'holidate', 'status', 'title', 'is_recurring')
            ->where('holidate', '>=', $dateFrom)
            ->where('holidate', '<=', $dateTo);

        $holidays = $query->get();

        return response()->json($holidays);
    }
}
