<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Services\UserLogService;

class AttendanceController extends Controller
{

    protected $userLogService;

    public function __construct(UserLogService $userLogService)
    {
        $this->userLogService = $userLogService;
    }

    public function attendance()
    {
        // Get the current user's ID from the session or Auth
        $currentUserId = Auth::user()->id;

        // Query the attendance data for the logged-in user, ordered by TimeIn
        $employeeClocks = DB::table('tblemployeeclocks')
            ->join('tbluser', 'tblemployeeclocks.userid', '=', 'tbluser.id')
            ->select(
                'tblemployeeclocks.ID as clock_id', // Alias for ID
                'tblemployeeclocks.userid as user_id', // Alias for userid
                'tblemployeeclocks.Employee as employee_name', // Alias for Employee
                'tblemployeeclocks.TimeIn as time_in', // Alias for TimeIn
                'tblemployeeclocks.TimeOut as time_out', // Alias for TimeOut
                'tbluser.username as user_name',
                'tblemployeeclocks.Notes as notes_'
            )
            ->where('tblemployeeclocks.userid', $currentUserId) // Filter by the current user's ID
            ->orderBy('tblemployeeclocks.TimeIn', 'desc') // Order by TimeIn (descending)
            ->get();


        // Query the attendance data for the logged-in user, where TimeIn is in the current week
        $employeeClocksThisweek = DB::table('tblemployeeclocks')
            ->join('tbluser', 'tblemployeeclocks.userid', '=', 'tbluser.id')
            ->select(
                'tblemployeeclocks.ID as ID',
                'tblemployeeclocks.userid',
                'tblemployeeclocks.Employee',
                'TimeIn',
                'TimeOut',
                'Notes',
                'tbluser.username'
            )
            ->where('tblemployeeclocks.userid', $currentUserId) // Filter by the current user's ID
            ->whereBetween('tblemployeeclocks.TimeIn', [
                Carbon::now('America/Los_Angeles')->startOfWeek(),
                Carbon::now('America/Los_Angeles')->endOfWeek(),
            ]) // Filter records where TimeIn is this week
            ->orderBy('tblemployeeclocks.TimeIn', 'desc') // Order by TimeIn (descending)
            ->get();

        // Fetch the most recent clock-in record for today with no clock-out
        $lastRecord = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereDate('TimeIn', Carbon::today('America/Los_Angeles')) // Check if TimeIn is today
            ->orderBy('ID', 'desc') // Get the most recent record
            ->first(); // Retrieve only the last record

        $verylastRecord = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->orderBy('ID', 'desc') // Get the most recent record
            ->first(); // Retrieve the most recent record

        // Calculate Today's Hours
        $todayHours = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereDate('TimeIn', Carbon::today('America/Los_Angeles'))
            ->sum(DB::raw("
            TIMESTAMPDIFF(
                MINUTE,
                TimeIn,
                COALESCE(TimeOut, DATE_SUB(NOW(), INTERVAL 8 HOUR))
            )
        "));

        // Calculate This Week's Hours
        $weekHours = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereBetween('TimeIn', [
                Carbon::now('America/Los_Angeles')->startOfWeek(),
                Carbon::now('America/Los_Angeles')->endOfWeek(),
            ])
            ->sum(DB::raw("
                TIMESTAMPDIFF(
                    MINUTE,
                    TimeIn,
                    COALESCE(TimeOut, DATE_SUB(NOW(), INTERVAL 8 HOUR))
                )
            "));


        // Format hours as H:mm
        $todayHoursFormatted = sprintf('%d hrs %02d mins', intdiv($todayHours, 60), $todayHours % 60);
        $weekHoursFormatted = sprintf('%d hrs %02d mins', intdiv($weekHours, 60), $weekHours % 60);

        // Pass the data to the Blade view
        return view(
            'dashboard.Systemdashboard',
            compact('employeeClocks', 'lastRecord', 'verylastRecord', 'todayHoursFormatted', 'weekHoursFormatted', 'employeeClocksThisweek')
        );
    }

    private function resolveDayStatusLA(Carbon $nowLA): array
    {
        $laDate = $nowLA->toDateString();          // YYYY-MM-DD (LA)
        $mmdd = $nowLA->format('m-d');

        $holiday = DB::table('tblholiday')
            ->where('holidate', $laDate)
            ->orWhere(function ($q) use ($mmdd) {
                $q->where('is_recurring', 1)
                    ->whereRaw('DATE_FORMAT(holidate, "%m-%d") = ?', [$mmdd]);
            })
            ->orderByRaw("
            CASE status
              WHEN 'Regular Holiday' THEN 1
              WHEN 'Special Holiday' THEN 2
              ELSE 99
            END
        ")
            ->first();

        return [
            'status' => $holiday ? ($holiday->status ?? 'Normal') : 'Normal',
            'holidayID' => $holiday->holidayID ?? null,
            'holidayTitle' => $holiday->title ?? null,
            'date' => $laDate,
        ];
    }

    public function clockIn(Request $request)
    {
        $uid = Auth::id();
        $uname = Auth::user()->username;
        $tz = 'America/Los_Angeles';
        $now = \Carbon\Carbon::now($tz);

        // 1) Block if already clocked-in (no TimeOut yet)
        $open = DB::table('tblemployeeclocks')
            ->where('userid', $uid)
            ->whereNull('TimeOut')
            ->orderBy('ID', 'desc')
            ->first();

        if ($open) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an open clock-in. Please clock out first.',
            ], 409);
        }

        // 2) Find active schedule links covering today (and yesterday for overnight)
        $dowToday = (int) $now->isoWeekday();          // 1=Mon..7=Sun
        $dowYesterday = (int) $now->copy()->subDay()->isoWeekday();
        $today = $now->toDateString();              // YYYY-MM-DD
        $yesterday = $now->copy()->subDay()->toDateString();

        $links = DB::table('tblusersched as us')
            ->join('tbltimesched as ts', 'ts.timeschedId', '=', 'us.schedId')
            ->select(
                'us.userschedId',
                'us.userId',
                'us.schedId',
                'us.effective_from',
                'us.effective_to',
                'us.is_active',
                'ts.day_of_week',
                'ts.start_time',
                'ts.end_time',
                'ts.end_next_day',
                'ts.title'
            )
            ->where('us.userId', $uid)
            ->where('us.is_active', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('us.effective_from')->orWhere('us.effective_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('us.effective_to')->orWhere('us.effective_to', '>=', $today);
            })
            // consider schedules for today, yesterday (for overnight), and Everyday(0)
            ->whereIn('ts.day_of_week', [0, $dowToday, $dowYesterday])
            ->orderBy('ts.day_of_week')
            ->get();

        if ($links->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'You have no schedule assigned for today.',
            ], 403);
        }

        // 3) Check if NOW is inside any window
        $match = null;
        foreach ($links as $r) {
            // Helper to build LA datetime from anchor date + time string
            $startToday = \Carbon\Carbon::parse($today . ' ' . $r->start_time, $tz);
            $endToday = \Carbon\Carbon::parse($today . ' ' . $r->end_time, $tz);
            if ((int) $r->end_next_day === 1)
                $endToday->addDay();

            // Case A: schedule for today (or Everyday)
            if ((int) $r->day_of_week === 0 || (int) $r->day_of_week === $dowToday) {
                if ($now->between($startToday, $endToday, true)) {
                    $match = $r;
                    break;
                }
            }

            // Case B: overnight schedule anchored yesterday (or Everyday)
            if ((int) $r->end_next_day === 1 && ((int) $r->day_of_week === 0 || (int) $r->day_of_week === $dowYesterday)) {
                $startY = \Carbon\Carbon::parse($yesterday . ' ' . $r->start_time, $tz);
                $endY = \Carbon\Carbon::parse($yesterday . ' ' . $r->end_time, $tz)->addDay();
                if ($now->between($startY, $endY, true)) {
                    $match = $r;
                    break;
                }
            }
        }

        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'You are outside your scheduled window right now.',
            ], 403);
        }

        // LA calendar only
        $day = $this->resolveDayStatusLA($now);

        // 4) Create clock-in
        DB::table('tblemployeeclocks')->insert([
            'userid' => $uid,
            'Employee' => $uname,
            'DateToday' => $now->toDateString(),   // LA date
            'TimeIn' => $now,                   // LA datetime
            'day_status' => $day['status'],         // Normal | Regular Holiday | Special Holiday
            'holidayID' => $day['holidayID'],      // nullable
            'Notes' => $match->title ? ('Matched schedule: ' . $match->title) : null,
        ]);

        $this->userLogService->log('Clockin');

        return response()->json([
            'success' => true,
            'message' => 'Clocked in at ' . $now->format('h:i A') . ' (LA) • ' . $day['holidayTitle'],
            'meta' => ['holiday' => $day['holidayTitle'], 'date' => $day['date']],
        ]);
    }

    public function clockOut(Request $request)
    {
        $currentUserId = Auth::user()->id;
        $currentDateTime = Carbon::now('America/Los_Angeles');

        $lastRecord = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereDate('TimeIn', Carbon::today('America/Los_Angeles'))
            ->whereNotNull('TimeIn')
            ->whereNull('TimeOut')
            ->orderBy('ID', 'desc')
            ->first();

        if ($lastRecord) {
            DB::table('tblemployeeclocks')
                ->where('ID', $lastRecord->ID)
                ->update(['TimeOut' => $currentDateTime]);

            $this->userLogService->log('Clockout');

            return response()->json([
                'success' => true,
                'message' => 'Clocked out successfully at ' . $currentDateTime->format('h:i A'),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No valid clock-in record found for today.',
            ], 400);
        }
    }

    public function autoClockOut(Request $request)
    {
        $currentUserId = Auth::user()->id;
        $timezone = 'America/Los_Angeles';

        // Get all unclosed records
        $unclosedRecords = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereNotNull('TimeIn')
            ->whereNull('TimeOut')
            ->orderBy('ID', 'asc')
            ->get();

        if ($unclosedRecords->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No unclosed clock-in records found.',
            ]);
        }

        $updatedCount = 0;
        foreach ($unclosedRecords as $record) {
            $timeIn = \Carbon\Carbon::parse($record->TimeIn)->setTimezone($timezone);
            $now = now()->setTimezone($timezone);

            // Only close records older than 8 hours
            if ($timeIn->diffInHours($now) >= 8) {
                DB::table('tblemployeeclocks')
                    ->where('ID', $record->ID)
                    ->update([
                        'TimeOut' => $record->TimeIn,
                        'Notes' => 'System Auto Clock-out applied. TimeOut matched TimeIn at ' . $record->TimeIn,
                    ]);

                $this->userLogService->log("Auto Clockout: User ID {$currentUserId} clocked out record ID {$record->ID} at {$record->TimeIn}");
                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} record(s) auto clocked out successfully.",
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No eligible records found (less than 8 hours old).',
        ]);
    }

    public function updateComputedHours(Request $request)
    {
        // Parse TimeIn and TimeOut values
        $timeIn = Carbon::parse($request->timeIn)->setTimezone('America/Los_Angeles');
        $timeOut = $request->timeOut
            ? Carbon::parse($request->timeOut)->setTimezone('America/Los_Angeles')
            : now()->setTimezone('America/Los_Angeles')->subHours(8);

        // Calculate total hours and minutes worked
        $totalMinutes = $timeIn->diffInMinutes($timeOut);
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        // Return as JSON
        return response()->json([
            'hours' => $hours,
            'minutes' => $minutes,
            'message' => !$request->timeOut ? 'Calculated until now' : null,
        ]);
    }

    public function updateHours()
    {
        // Get the current user's ID
        $currentUserId = Auth::user()->id;

        // Calculate Today's Hours
        $todayHours = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereDate('TimeIn', Carbon::today('America/Los_Angeles'))
            ->sum(DB::raw("
                TIMESTAMPDIFF(
                    MINUTE,
                    TimeIn,
                    COALESCE(TimeOut, DATE_SUB(NOW(), INTERVAL 8 HOUR))
                )
            "));

        // Calculate This Week's Hours
        $weekHours = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereBetween('TimeIn', [
                Carbon::now('America/Los_Angeles')->startOfWeek(),
                Carbon::now('America/Los_Angeles')->endOfWeek(),
            ])
            ->sum(DB::raw("
                TIMESTAMPDIFF(
                    MINUTE,
                    TimeIn,
                    COALESCE(TimeOut, DATE_SUB(NOW(), INTERVAL 8 HOUR))
                )
            "));

        // Format hours as H:mm
        $todayHoursFormatted = sprintf('%d hrs %02d mins', intdiv($todayHours, 60), $todayHours % 60);
        $weekHoursFormatted = sprintf('%d hrs %02d mins', intdiv($weekHours, 60), $weekHours % 60);

        // Return as JSON
        return response()->json([
            'todayHours' => $todayHoursFormatted,
            'weekHours' => $weekHoursFormatted,
        ]);
    }

    public function filterAttendanceAjax(Request $request)
    {
        $currentUserId = Auth::user()->id;

        // Get date range or default to null
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = DB::table('tblemployeeclocks')
            ->join('tbluser', 'tblemployeeclocks.userid', '=', 'tbluser.id')
            ->select(
                'tblemployeeclocks.ID as clock_id',
                'tblemployeeclocks.userid as user_id',
                'tblemployeeclocks.Employee as employee_name',
                'tblemployeeclocks.TimeIn as time_in',
                'tblemployeeclocks.TimeOut as time_out',
                'tbluser.username as user_name'
            )
            ->where('tblemployeeclocks.userid', $currentUserId)
            ->orderBy('tblemployeeclocks.TimeIn', 'desc');

        // Apply date range if provided
        if ($startDate) {
            $query->whereDate('tblemployeeclocks.TimeIn', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('tblemployeeclocks.TimeIn', '<=', $endDate);
        }

        // Default to limit 10 rows if no range is provided
        $employeeClocks = $query->limit(10)->get();

        return response()->json([
            'employeeClocks' => $employeeClocks,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function updateNotes(Request $request, $id)
    {
        $validatedData = $request->validate([
            'notes' => 'required|string|max:255',
        ]);

        $updated = DB::table('tblemployeeclocks')
            ->where('ID', $id)
            ->update(['Notes' => $validatedData['notes']]);

        if ($updated) {

            // Log using service
            $this->userLogService->log('Save user time clock notes');

            return response()->json([
                'success' => true,
                'message' => 'Notes updated successfully.',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notes.',
            ]);
        }
    }



    private const LA_TZ = 'America/Los_Angeles';

    /** Open clock without lock (for reads). */
    private function getOpenClock(int $userId)
    {
        return DB::table('tblemployeeclocks')
            ->where('userid', $userId)
            ->whereNull('TimeOut')
            ->orderByDesc('TimeIn')
            ->first();
    }

    /** Open clock locked for updates (inside a transaction). */
    private function getOpenClockForUpdate(int $userId)
    {
        return DB::table('tblemployeeclocks')
            ->where('userid', $userId)
            ->whereNull('TimeOut')
            ->orderByDesc('TimeIn')
            ->lockForUpdate()
            ->first();
    }

    /** Get allowed unpaid break minutes from the linked schedule for the shift date. */
    private function resolveAllowedBreakMinutes(int $userId, Carbon $shiftDateLA): int
    {
        $link = DB::table('tblusersched')
            ->where('userId', $userId)
            ->where('is_active', 1)
            ->where(function ($q) use ($shiftDateLA) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $shiftDateLA->toDateString());
            })
            ->where(function ($q) use ($shiftDateLA) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $shiftDateLA->toDateString());
            })
            ->orderByDesc('userschedId')
            ->first();

        if (!$link)
            return 0;

        $ts = DB::table('tbltimesched')->where('timeschedId', $link->schedId)->first();
        if (!$ts)
            return 0;

        // Enforce day-of-week match: 0=Everyday, 1=Mon..7=Sun
        $dow = (int) $shiftDateLA->dayOfWeekIso; // 1-7
        if ((int) $ts->day_of_week !== 0 && (int) $ts->day_of_week !== $dow) {
            return 0; // template not for this day
        }

        return (int) $ts->unpaid_break_minutes;
    }

    /** Seconds elapsed for an ongoing break (server clock). */
    private function currentBreakElapsedSeconds($clock, Carbon $nowLA): int
    {
        if (!$clock || !$clock->shortbreak_start || ($clock->shortbreak_status ?? null) !== 'on_break')
            return 0;
        $start = Carbon::parse($clock->shortbreak_start, self::LA_TZ);
        return max(0, $start->diffInSeconds($nowLA));
    }

    /** GET: status snapshot (also auto-clamps if user exceeded allowance). */
    public function status(Request $request)
    {
        $userId = Auth::id();
        $nowLA = Carbon::now(self::LA_TZ);

        $clock = $this->getOpenClock($userId);
        if (!$clock) {
            return response()->json(['hasOpenClock' => false, 'message' => 'No open shift.']);
        }

        $shiftDateLA = Carbon::parse($clock->DateToday, self::LA_TZ);
        $allowed = $this->resolveAllowedBreakMinutes($userId, $shiftDateLA);

        $usedMinutes = (float) ($clock->shortbreak_totaltime ?? 0.0);
        $elapsedSec = $this->currentBreakElapsedSeconds($clock, $nowLA);
        $elapsedMin = $elapsedSec / 60.0;
        $remaining = max(0.0, $allowed - $usedMinutes - $elapsedMin);

        // Auto-end if over cap (fool-proof)
        if (($clock->shortbreak_status ?? 'idle') === 'on_break' && $remaining <= 0.0) {
            DB::transaction(function () use ($userId, $allowed) {
                $nowLA = Carbon::now(self::LA_TZ);

                $row = $this->getOpenClockForUpdate($userId);
                if (!$row)
                    return;

                $prior = (float) ($row->shortbreak_totaltime ?? 0.0);
                $capMin = max(0.0, $allowed - $prior);
                $end = Carbon::parse($row->shortbreak_start, self::LA_TZ)->copy()
                    ->addSeconds((int) round($capMin * 60));

                DB::table('tblemployeeclocks')
                    ->where('ID', $row->ID)
                    ->update([
                        'shortbreak_end' => $end,
                        'shortbreak_totaltime' => $allowed,
                        'shortbreak_status' => 'done',
                        'systemNotes' => trim(($row->systemNotes ?? '') . ' [auto-end break at allowance]'),
                    ]);
            });

            // Refresh snapshot
            $clock = $this->getOpenClock($userId);
            $usedMinutes = (float) ($clock->shortbreak_totaltime ?? 0.0);
            $elapsedMin = 0.0;
        }

        return response()->json([
            'hasOpenClock' => true,
            'status' => $clock->shortbreak_status ?? 'idle', // idle | on_break | done
            'allowedMin' => (float) $allowed,
            'usedMin' => (float) $usedMinutes + $elapsedMin,
            'remainingMin' => max(0.0, (float) $allowed - ((float) $usedMinutes + $elapsedMin)),
            'onBreakSince' => $clock->shortbreak_start,
            'lastBreakEnd' => $clock->shortbreak_end,
            'serverNow' => $nowLA->toIso8601String(),
        ]);
    }

    /** POST: start break */
    public function start(Request $request)
    {
        $userId = Auth::id();
        return DB::transaction(function () use ($userId) {
            $nowLA = Carbon::now(self::LA_TZ);
            $row = $this->getOpenClockForUpdate($userId);
            if (!$row)
                return response()->json(['error' => 'No open shift.'], 422);

            if (($row->shortbreak_status ?? null) === 'on_break') {
                return response()->json(['error' => 'Already on break.'], 409);
            }

            $shiftDateLA = Carbon::parse($row->DateToday, self::LA_TZ);
            $allowed = $this->resolveAllowedBreakMinutes($userId, $shiftDateLA);
            $used = (float) ($row->shortbreak_totaltime ?? 0.0);

            if ($used >= $allowed) {
                return response()->json(['error' => 'No break time remaining.'], 409);
            }

            DB::table('tblemployeeclocks')->where('ID', $row->ID)->update([
                'shortbreak_start' => $nowLA,
                'shortbreak_status' => 'on_break',
            ]);

            return response()->json(['ok' => true]);
        });
    }

    /** POST: end break */
    public function end(Request $request)
    {
        $userId = Auth::id();
        return DB::transaction(function () use ($userId) {
            $nowLA = Carbon::now(self::LA_TZ);
            $row = $this->getOpenClockForUpdate($userId);
            if (!$row)
                return response()->json(['error' => 'No open shift.'], 422);

            if (($row->shortbreak_status ?? null) !== 'on_break' || !$row->shortbreak_start) {
                return response()->json(['error' => 'Not currently on break.'], 409);
            }

            $shiftDateLA = Carbon::parse($row->DateToday, self::LA_TZ);
            $allowed = $this->resolveAllowedBreakMinutes($userId, $shiftDateLA);

            $priorMin = (float) ($row->shortbreak_totaltime ?? 0.0);
            $elapsedSec = max(0, Carbon::parse($row->shortbreak_start, self::LA_TZ)->diffInSeconds($nowLA));
            $elapsedMin = $elapsedSec / 60.0;

            $newTotal = $priorMin + $elapsedMin;
            $clamped = min($newTotal, (float) $allowed);

            // If clamped, compute effective end = start + (clamped - prior)
            $effectiveEnd = $nowLA;
            if ($clamped < $newTotal) {
                $extraMin = max(0.0, $clamped - $priorMin);
                $effectiveEnd = Carbon::parse($row->shortbreak_start, self::LA_TZ)
                    ->copy()->addSeconds((int) round($extraMin * 60));
            }

            DB::table('tblemployeeclocks')->where('ID', $row->ID)->update([
                'shortbreak_end' => $effectiveEnd,
                'shortbreak_totaltime' => $clamped,
                'shortbreak_status' => ($clamped >= (float) $allowed) ? 'done' : 'idle',
            ]);

            return response()->json(['ok' => true]);
        });
    }


}
