<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

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
        $startUtc = $data['start_at'] ? Carbon::parse($data['start_at'], $userTz)->setTimezone('UTC') : null;
        $endUtc = $data['end_at'] ? Carbon::parse($data['end_at'], $userTz)->setTimezone('UTC') : null;

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

        if (!$includeAck && $username) {
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
        return $this->dayName($dow) . ' ' . substr($start, 0, 5) . $dash . substr($end, 0, 5) . ($overn ? ' (+1)' : '');
    }
    private function dbNow(): Carbon
    {
        return Carbon::now('America/Los_Angeles');
    }

    public function listTimesched(Request $r)
    {
        $q = DB::table('tbltimesched');
        if ($r->filled('day_of_week'))
            $q->where('day_of_week', (int) $r->input('day_of_week'));
        if ($r->filled('is_active'))
            $q->where('is_active', (int) $r->input('is_active'));
        $q->orderBy('day_of_week')->orderBy('start_time');
        return response()->json(['success' => true, 'data' => $q->get()]);
    }

    public function createTimesched(Request $r)
    {
        $d = $r->validate([
            'day_of_week' => 'required|integer|min:0|max:7',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'end_next_day' => 'nullable|boolean',
            'unpaid_break_minutes' => 'nullable|integer|min:0|max:600',
            'title' => 'nullable|string|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        $overn = (bool) ($d['end_next_day'] ?? false);
        $s = \Carbon\Carbon::createFromFormat('H:i', $d['start_time']);
        $e = \Carbon\Carbon::createFromFormat('H:i', $d['end_time']);
        if (!$overn && $e->lessThanOrEqualTo($s)) {
            return response()->json(['success' => false, 'error' => 'end_time must be after start_time for same-day'], 422);
        }
        $title = $d['title'] ?? $this->makeTitle((int) $d['day_of_week'], $d['start_time'], $d['end_time'], $overn);

        $id = DB::table('tbltimesched')->insertGetId([
            'day_of_week' => (int) $d['day_of_week'],
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
        if (!$row)
            return response()->json(['success' => false, 'error' => 'not found'], 404);

        $d = $r->validate([
            'day_of_week' => 'nullable|integer|min:0|max:7',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'end_next_day' => 'nullable|boolean',
            'unpaid_break_minutes' => 'nullable|integer|min:0|max:600',
            'title' => 'nullable|string|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        $dow = (int) ($d['day_of_week'] ?? $row->day_of_week);
        $start = $d['start_time'] ?? $row->start_time;
        $end = $d['end_time'] ?? $row->end_time;
        $overn = array_key_exists('end_next_day', $d) ? (bool) $d['end_next_day'] : (bool) $row->end_next_day;

        $s = \Carbon\Carbon::createFromFormat('H:i', $start);
        $e = \Carbon\Carbon::createFromFormat('H:i', $end);
        if (!$overn && $e->lessThanOrEqualTo($s)) {
            return response()->json(['success' => false, 'error' => 'end_time must be after start_time for same-day'], 422);
        }

        $payload = [
            'day_of_week' => $dow,
            'start_time' => $start,
            'end_time' => $end,
            'end_next_day' => $overn ? 1 : 0,
            'unpaid_break_minutes' => (int) ($d['unpaid_break_minutes'] ?? $row->unpaid_break_minutes),
            'title' => $d['title'] ?? $this->makeTitle($dow, $start, $end, $overn),
            'is_active' => (int) ($d['is_active'] ?? $row->is_active),
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
            ->selectRaw("
            us.userschedId,
            us.userId,
            us.schedId,
            us.schednote,
            us.effective_from,
            us.effective_to,
            us.is_active,               
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            ts.end_next_day,
            ts.title,
            ts.is_active as sched_active
        ")
            ->orderBy('ts.day_of_week')->orderBy('ts.start_time')
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
        if (!$row)
            return response()->json(['success' => false, 'error' => 'not found'], 404);

        $d = $r->validate([
            'schedId' => 'nullable|integer',
            'schednote' => 'nullable|string|max:255',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date',
            'is_active' => 'nullable|boolean',   // ⬅️ allow toggling
        ]);

        if (
            !empty($d['effective_from']) && !empty($d['effective_to']) &&
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
}
