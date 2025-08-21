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
    private const DB_TZ = 'America/Los_Angeles';

    public function getEmployees()
    {
        $today = date('Y-m-d');

        $employees = \DB::table('tbluser as u')
            ->select(
                'u.id',
                'u.username',
                \DB::raw('u.username as name'),
                \DB::raw('u.office_role as position'),
                'u.accounttype',  // ✅ include account type
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
     * 
     **/
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
        $year = (int) ($request->input('year') ?: Carbon::now()->year);

        $rows = DB::table('tblholiday')
            ->select('holidayID', 'holidate', 'status', 'title', 'is_recurring')
            ->orderBy('holidate', 'asc')
            ->get();

        // Expand display_date for UI based on selected year if recurring
        $items = $rows->map(function ($r) use ($year) {
            $md = Carbon::parse($r->holidate)->format('m-d');
            $displayDate = $r->is_recurring ? Carbon::createFromFormat('Y-m-d', $year . '-' . $md)->toDateString() : $r->holidate;
            return [
                'holidayID' => (int) $r->holidayID,
                'holidate' => $r->holidate,
                'display_date' => $displayDate,
                'status' => $r->status,
                'title' => $r->title,
                'is_recurring' => (int) $r->is_recurring
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
            'is_recurring' => 'required|in:0,1'
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $id = DB::table('tblholiday')->insertGetId([
            'holidate' => $request->holidate,
            'status' => $request->status,
            'title' => $request->title,
            'is_recurring' => (int) $request->is_recurring
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
            'is_recurring' => 'required|in:0,1'
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        DB::table('tblholiday')->where('holidayID', $request->holidayID)->update([
            'holidate' => $request->holidate,
            'status' => $request->status,
            'title' => $request->title,
            'is_recurring' => (int) $request->is_recurring
        ]);

        return response()->json(['success' => true]);
    }

    public function deleteHoliday(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'holidayID' => 'required|integer|exists:tblholiday,holidayID'
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
            ->map(fn($g) => strtoupper(trim($g)))
            ->filter(fn($g) => in_array($g, ['PH', 'US']))
            ->values();

        if ($groups->isNotEmpty()) {
            $groupUserIds = DB::table('tbluser')
                ->whereIn('accounttype', $groups->all())
                ->pluck('id');
            $userIds = $userIds->merge($groupUserIds);
        }

        // Dedup + sanitize
        $finalUserIds = $userIds->unique()->values()->map(fn($v) => (int) $v)->all();

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
                'title' => 'Announcement: ' . $data['title'],
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

        $username = $request->input('username', session('user_name'));
        if (!$username) {
            return response()->json([
                'success' => false,
                'message' => 'Username missing (session or payload).',
            ], 422);
        }

        $ann = DB::table('tblannouncements')->where('id', $request->announcement_id)->first();
        if (!$ann) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement not found.',
            ], 404);
        }

        // ----- Option A: Simple read-modify-write (good enough for low contention) -----
        $readby = is_array($ann->readby) ? $ann->readby : (json_decode($ann->readby, true) ?? []);
        if (!in_array($username, $readby, true)) {
            $readby[] = $username;
            DB::table('tblannouncements')
                ->where('id', $ann->id)
                ->update(['readby' => json_encode(array_values(array_unique($readby)))]);
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
        $startUtc = $data['start_at'] ? \Carbon\Carbon::parse($data['start_at'], $userTz)->setTimezone('UTC') : null;
        $endUtc = $data['end_at'] ? \Carbon\Carbon::parse($data['end_at'], $userTz)->setTimezone('UTC') : null;

        // sanitize recipients to int[]
        $recips = $request->input('recipients', []);
        if (!is_array($recips))
            $recips = [];
        $recips = collect($recips)->map(fn($v) => (int) $v)->unique()->values()->all();

        $row = [
            'title' => $data['title'],
            'content' => $data['message'] ?? null,
            'start_at' => $startUtc,
            'end_at' => $endUtc,
            'is_active' => $data['save_mode'] === 'active' ? 1 : 0,
            'recipients_json' => json_encode($recips),
            'updated_at' => now('UTC'),
        ];

        if (!empty($data['id'])) {
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
            ->when($status === 'active', fn($q) => $q->where('is_active', 1))
            ->when($status === 'draft', fn($q) => $q->where('is_active', 0))
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

            $startLocal = $r->start_at ? \Carbon\Carbon::parse($r->start_at, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s') : null;
            $endLocal = $r->end_at ? \Carbon\Carbon::parse($r->end_at, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s') : null;

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

        $nowUtc = \Carbon\Carbon::now('UTC');
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

        if (!$includeAck && $username) {
            $rows = $rows->reject(function ($r) use ($username) {
                $readby = is_array($r->readby) ? $r->readby : (json_decode($r->readby, true) ?? []);
                return in_array($username, $readby, true);
            })->values();
        }

        $payload = $rows->map(function ($r) use ($userTz) {
            $startLocal = $r->start_at ? \Carbon\Carbon::parse($r->start_at, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s') : null;
            $endLocal = $r->end_at ? \Carbon\Carbon::parse($r->end_at, 'UTC')->setTimezone($userTz)->format('Y-m-d H:i:s') : null;

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

    public function createSchedule(Request $req)
    {

        $data = $req->validate([
            'user_id' => 'required|integer',
            'work_date' => 'required|date',           // local date (of the EMPLOYEE)
            'shift_id' => 'nullable|integer',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'end_next_day' => 'nullable|boolean',
            'unpaid_break_minutes' => 'nullable|integer|min:0|max:600',
            'paid_break_minutes' => 'nullable|integer|min:0|max:600',
            'notes' => 'nullable|string|max:255',
        ]);

        // resolve from preset or custom (as you already do) → $start, $end, $overn, $unpaid, $paid

        $userTz = $this->tzForUser((int) $data['user_id']);

        // validate window in LOCAL tz (user’s semantics)
        $sLocal = Carbon::parse($data['work_date'] . ' ' . ($start ?? '00:00'), $userTz);
        $eLocal = Carbon::parse($data['work_date'] . ' ' . ($end ?? '00:00'), $userTz);
        if (!($overn ?? false) && $eLocal->lessThanOrEqualTo($sLocal)) {
            return response()->json(['success' => false, 'error' => 'End must be after start (local).'], 422);
        }
        if ($overn ?? false)
            $eLocal->addDay();
        $duration = $sLocal->diffInMinutes($eLocal);
        if ((($unpaid ?? 0) + ($paid ?? 0)) > $duration) {
            return response()->json(['success' => false, 'error' => 'Breaks exceed duration.'], 422);
        }

        // convert LOCAL → LA for storage
        $la = $this->toLaFields($data['work_date'], $start, $end, (bool) ($overn ?? false), $userTz);

        DB::table('tbluserschedule')->insert([
            'user_id' => $data['user_id'],
            'work_date' => $la['work_date_la'],
            'shift_id' => $data['shift_id'] ?? null,
            'start_time' => $la['start_time_la'],
            'end_time' => $la['end_time_la'],
            'end_next_day' => $la['end_next_day_la'] ? 1 : 0,
            'unpaid_break_minutes' => (int) ($unpaid ?? 0),
            'paid_break_minutes' => (int) ($paid ?? 0),
            'status' => 'Scheduled',
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => now(DB_TZ),
            'updated_at' => now(DB_TZ),
        ]);

        $row = DB::table('tbluserschedule')
            ->where('user_id', $data['user_id'])
            ->where('work_date', $la['work_date_la'])
            ->orderByDesc('userschedule_id')->first();

        // decorate for viewer (whoever is logged in)
        $viewerTz = session('usertimezone', 'Asia/Manila');
        $display = $this->fromLaForDisplay($row, $viewerTz);

        return response()->json(['success' => true, 'data' => $row, 'display' => $display]);
    }

    public function listSchedules(Request $req)
    {
        $req->validate([
            'user_id' => 'nullable|integer',
            'from' => 'nullable|date',  // LOCAL date to the viewer/user context
            'to' => 'nullable|date|after_or_equal:from',
            'status' => 'nullable|in:Scheduled,Cancelled',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        // decide whose TZ to use for the filter: if querying a specific user, use that user’s tz; else viewer’s tz
        $filterTz = $req->filled('user_id')
            ? $this->tzForUser((int) $req->integer('user_id'))
            : session('usertimezone', 'Asia/Manila');

        [$fromLA, $toLA] = $this->userRangeToLaDates($req->input('from'), $req->input('to'), $filterTz);

        $q = DB::table('tbluserschedule as s')
            ->leftJoin('tbluser as u', 'u.id', '=', 's.user_id')
            ->leftJoin('tblshift as sh', 'sh.shift_id', '=', 's.shift_id')
            ->select('s.*', 'u.username', 'u.name', 'sh.name as shift_name');

        if ($req->filled('user_id'))
            $q->where('s.user_id', $req->integer('user_id'));
        if ($fromLA)
            $q->where('s.work_date', '>=', $fromLA);
        if ($toLA)
            $q->where('s.work_date', '<=', $toLA);
        if ($req->filled('status'))
            $q->where('s.status', $req->input('status'));

        $q->orderBy('s.work_date')->orderBy('s.start_time');

        $page = $q->paginate((int) $req->input('per_page', 50));

        // decorate each row for display in the viewer’s TZ
        $viewerTz = session('usertimezone', 'Asia/Manila');
        $page->getCollection()->transform(function ($row) use ($viewerTz) {
            return (object) array_merge((array) $row, $this->fromLaForDisplay($row, $viewerTz));
        });

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function updateSchedule($id, Request $req)
    {
        $data = $req->validate([
            'shift_id' => 'nullable|integer',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'end_next_day' => 'nullable|boolean',
            'unpaid_break_minutes' => 'nullable|integer|min:0|max:600',
            'paid_break_minutes' => 'nullable|integer|min:0|max:600',
            'status' => 'nullable|in:Scheduled,Cancelled',
            'notes' => 'nullable|string|max:255',
        ]);

        $row = DB::table('tbluserschedule')->where('userschedule_id', $id)->first();
        if (!$row) {
            return response()->json(['success' => false, 'error' => 'Schedule not found.'], 404);
        }

        // Pull preset if provided
        $preset = null;
        if (!empty($data['shift_id'])) {
            $preset = DB::table('tblshift')->where('shift_id', $data['shift_id'])->first();
        }

        // Resolve effective values (existing row as base -> preset -> payload override)
        $start = $data['start_time'] ?? ($preset->start_time ?? $row->start_time);
        $end = $data['end_time'] ?? ($preset->end_time ?? $row->end_time);
        $overn = array_key_exists('end_next_day', $data)
            ? (bool) $data['end_next_day']
            : (isset($preset->end_next_day) ? (bool) $preset->end_next_day : (bool) $row->end_next_day);

        $unpaid = $data['unpaid_break_minutes'] ?? ($preset->unpaid_break_minutes ?? $row->unpaid_break_minutes);
        $paid = $data['paid_break_minutes'] ?? ($preset->paid_break_minutes ?? $row->paid_break_minutes);

        // Validate window + breaks
        $s = Carbon::createFromFormat('H:i', $start);
        $e = Carbon::createFromFormat('H:i', $end);
        if (!$overn && $e->lessThanOrEqualTo($s)) {
            return response()->json(['success' => false, 'error' => 'End must be after start for same-day shift.'], 422);
        }
        $duration = $overn ? $s->diffInMinutes($e->copy()->addDay()) : $s->diffInMinutes($e);
        if (($unpaid + $paid) > $duration) {
            return response()->json(['success' => false, 'error' => 'Break minutes exceed shift duration.'], 422);
        }

        $payload = [
            'shift_id' => $data['shift_id'] ?? $row->shift_id,
            'start_time' => $start,
            'end_time' => $end,
            'end_next_day' => $overn ? 1 : 0,
            'unpaid_break_minutes' => $unpaid,
            'paid_break_minutes' => $paid,
            'status' => $data['status'] ?? $row->status,
            'notes' => $data['notes'] ?? $row->notes,
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ];

        DB::table('tbluserschedule')->where('userschedule_id', $id)->update($payload);

        $updated = DB::table('tbluserschedule')->where('userschedule_id', $id)->first();
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function deleteSchedule($id)
    {
        $deleted = DB::table('tbluserschedule')->where('userschedule_id', $id)->delete();
        return response()->json(['success' => (bool) $deleted]);
    }

    public function upsertSchedulesRange(Request $req)
    {
        $data = $req->validate([
            'user_id' => 'required|integer',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'days' => 'required|array', // keys: Mon..Sun
        ]);

        $dowKeys = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        foreach ($dowKeys as $k) {
            if (!array_key_exists($k, $data['days'])) {
                return response()->json(['success' => false, 'error' => "Missing days.$k"], 422);
            }
        }

        // Preload referenced shift presets once
        $shiftIds = collect($data['days'])->pluck('shift_id')->filter()->unique()->values();
        $presets = $shiftIds->isEmpty()
            ? collect()
            : DB::table('tblshift')->whereIn('shift_id', $shiftIds)->get()->keyBy('shift_id');

        $cursor = \Carbon\Carbon::parse($data['from']);
        $end = \Carbon\Carbon::parse($data['to']);

        DB::beginTransaction();
        try {
            while ($cursor->lte($end)) {
                $dow = $cursor->format('D'); // Mon/Tue/...
                $rule = $data['days'][$dow] ?? null;

                if ($rule && empty($rule['off'])) {
                    // Resolve effective values from preset + overrides
                    $preset = null;
                    if (!empty($rule['shift_id'])) {
                        $preset = $presets->get($rule['shift_id']);
                    }

                    $start = $rule['start_time'] ?? ($preset->start_time ?? null);
                    $endT = $rule['end_time'] ?? ($preset->end_time ?? null);
                    $overn = array_key_exists('end_next_day', $rule)
                        ? (bool) $rule['end_next_day']
                        : (bool) ($preset->end_next_day ?? false);

                    $unpaid = $rule['unpaid_break_minutes'] ?? ($preset->unpaid_break_minutes ?? 0);
                    $paid = $rule['paid_break_minutes'] ?? ($preset->paid_break_minutes ?? 0);

                    if ($start && $endT) {
                        $s = \Carbon\Carbon::createFromFormat('H:i', $start);
                        $e = \Carbon\Carbon::createFromFormat('H:i', $endT);
                        if (!$overn && $e->lessThanOrEqualTo($s)) {
                            // skip invalid window
                        } else {
                            $duration = $overn ? $s->diffInMinutes($e->copy()->addDay()) : $s->diffInMinutes($e);
                            if (($unpaid + $paid) <= $duration) {
                                // “replace” semantics (update-if-exists else insert) WITHOUT UNIQUE key
                                $exists = DB::table('tbluserschedule')
                                    ->where('user_id', $data['user_id'])
                                    ->where('work_date', $cursor->toDateString())
                                    ->lockForUpdate()
                                    ->orderByDesc('userschedule_id')
                                    ->first();

                                $payload = [
                                    'shift_id' => $rule['shift_id'] ?? ($preset->shift_id ?? null),
                                    'start_time' => $start,
                                    'end_time' => $endT,
                                    'end_next_day' => $overn ? 1 : 0,
                                    'unpaid_break_minutes' => $unpaid,
                                    'paid_break_minutes' => $paid,
                                    'status' => 'Scheduled',
                                    'updated_by' => \Auth::id(),
                                    'updated_at' => now(),
                                ];

                                if ($exists) {
                                    DB::table('tbluserschedule')->where('userschedule_id', $exists->userschedule_id)->update($payload);
                                } else {
                                    DB::table('tbluserschedule')->insert([
                                        'user_id' => $data['user_id'],
                                        'work_date' => $cursor->toDateString(),
                                        'notes' => $rule['notes'] ?? null,
                                        'created_by' => \Auth::id(),
                                        'created_at' => now(),
                                    ] + $payload);
                                }
                            }
                        }
                    }
                }

                $cursor->addDay();
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Applied schedules.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Get the target user's timezone (prefer session for self, else read from tbluser). */
    private function tzForUser(?int $userId = null): string
    {
        if (!$userId || (session()->has('userid') && session('userid') === $userId)) {
            return session('usertimezone', 'America/Los_Angeles'); // your app sets this already
        }
        $json = DB::table('tbluser')->where('id', $userId)->value('timezone_setting');
        return optional(json_decode($json, true))['usertimezone'] ?? 'America/Los_Angeles';
    }

    /** Convert a LOCAL day/time window (in user TZ) → LA date/time pieces for storage. */
    private function toLaFields(string $localDate, string $startHHmm, string $endHHmm, bool $endNextDayLocal, string $userTz): array
    {
        $startLocal = Carbon::parse("$localDate $startHHmm", $userTz);
        $endLocal = Carbon::parse("$localDate $endHHmm", $userTz);
        if ($endNextDayLocal)
            $endLocal->addDay();

        $startLA = $startLocal->clone()->setTimezone(DB_TZ);
        $endLA = $endLocal->clone()->setTimezone(DB_TZ);

        return [
            'work_date_la' => $startLA->toDateString(),            // DATE to store
            'start_time_la' => $startLA->format('H:i:s'),           // TIME to store
            'end_time_la' => $endLA->format('H:i:s'),             // TIME to store
            'end_next_day_la' => $endLA->toDateString() !== $startLA->toDateString(), // recompute
            'start_dt_la' => $startLA,                            // for overlap checks
            'end_dt_la' => $endLA,
        ];
    }

    /** Convert LA stored row back to DISPLAY (viewer’s) timezone. */
    private function fromLaForDisplay(object $row, string $viewerTz): array
    {
        $startLA = Carbon::parse("{$row->work_date} {$row->start_time}", DB_TZ);
        $endLA = Carbon::parse("{$row->work_date} {$row->end_time}", DB_TZ);
        if ((int) $row->end_next_day === 1)
            $endLA->addDay();

        $startLocal = $startLA->clone()->setTimezone($viewerTz);
        $endLocal = $endLA->clone()->setTimezone($viewerTz);

        return [
            'display_date' => $startLocal->toDateString(),
            'display_start_time' => $startLocal->format('H:i'),
            'display_end_time' => $endLocal->format('H:i'),
            'display_tz' => $viewerTz,
        ];
    }

    /** Convert a user-local date filter → LA date range (inclusive). */
    private function userRangeToLaDates(?string $fromDate, ?string $toDate, string $tz): array
    {
        if (!$fromDate && !$toDate)
            return [null, null];
        $fromDT = Carbon::parse(($fromDate ?? $toDate) . ' 00:00:00', $tz)->setTimezone(DB_TZ);
        $toDT = Carbon::parse(($toDate ?? $fromDate) . ' 23:59:59', $tz)->setTimezone(DB_TZ);
        if ($toDT->lt($fromDT))
            [$fromDT, $toDT] = [$toDT, $fromDT];
        return [$fromDT->toDateString(), $toDT->toDateString()];
    }




}
