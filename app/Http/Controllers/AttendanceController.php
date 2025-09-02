<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

    public function sendClockinMail($user, $currentDatetimeStr)
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'jundell@airstaffs.com';
            $mail->Password = 'scjjcpxcmwyjwegh'; // Gmail app password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('jundell@airstaffs.com', 'IMS Administrator');
            $mail->isHTML(true);
            $mail->Subject = "$user Clockin $currentDatetimeStr";
            $mail->Body = "<span style='color: green; text-transform: uppercase;'>$user</span> Clockin $currentDatetimeStr";

            $recipients = \DB::table('tblrecipients')->pluck('email')->toArray();
            if (empty($recipients)) {
                $mail->addAddress('jundell@airstaffs.com');
            } else {
                foreach ($recipients as $email) {
                    $mail->addAddress($email);
                }
            }

            $mail->send();
            return response()->json(['success' => true, 'message' => 'Emails sent']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => "Mailer Error: {$mail->ErrorInfo}"]);
        }
    }

    public function clockIn(Request $request)
    {
        $uid = Auth::id();
        $uname = Auth::user()->username;
        $tz = 'America/Los_Angeles';
        $now = Carbon::now($tz);

        // Format the datetime string
        $currentDatetimeStr = $now->format('Y-m-d H:i:s');

        $this->sendClockinMail($uname, $currentDatetimeStr);

        // --- config ---
        $EARLY_GRACE_MINUTES = 5;   // allow clock-in up to 5 minutes before scheduled start

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

        // 2) Resolve calendar
        $dowToday = (int) $now->isoWeekday();           // 1=Mon..7=Sun
        $dowYesterday = (int) $now->copy()->subDay()->isoWeekday();
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();

        // 3) Load active links (don’t pre-filter by day; we’ll match with mask/legacy below)
        $links = DB::table('tblusersched as us')
            ->join('tbltimesched as ts', 'ts.timeschedId', '=', 'us.schedId')
            ->select(
                'us.userschedId',
                'us.userId',
                'us.schedId',                 // keep the template id
                'us.effective_from',
                'us.effective_to',
                'us.is_active',
                'ts.day_of_week',
                'ts.days_mask',               // bit mask (Mon=1..Sun=64)
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
            ->orderBy('ts.start_time')
            ->get();

        if ($links->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'You have no schedule assigned for today.',
            ], 403);
        }

        // helper: mask contains weekday? (1..7)
        $maskHas = function ($mask, $dow) {
            $mask = (int) ($mask ?? 0);
            if ($mask <= 0)
                return false;
            $bit = 1 << ($dow - 1);
            return (($mask & $bit) !== 0);
        };

        // 4) Find a matching window (with early grace)
        $match = null;           // the matched schedule row
        $win = null;           // computed window we matched (start/end with early grace)
        foreach ($links as $r) {
            $hasMask = (int) ($r->days_mask ?? 0) > 0;
            $isEveryLegacy = ((int) $r->day_of_week) === 0;

            // --- Case A: today-anchored ---
            $todayMatchesDay =
                ($hasMask && $maskHas($r->days_mask, $dowToday))
                || (!$hasMask && ((int) $r->day_of_week === $dowToday || $isEveryLegacy));

            if ($todayMatchesDay) {
                $start = Carbon::parse($today . ' ' . $r->start_time, $tz);
                $end = Carbon::parse($today . ' ' . $r->end_time, $tz);
                if ((int) $r->end_next_day === 1)
                    $end->addDay();

                $startWithEarlyGrace = $start->copy()->subMinutes($EARLY_GRACE_MINUTES);

                if ($now->between($startWithEarlyGrace, $end, true)) {
                    $match = $r;
                    $win = (object) ['start' => $start, 'startGrace' => $startWithEarlyGrace, 'end' => $end];
                    break;
                }
            }

            // --- Case B: overnight anchored yesterday ---
            if ((int) $r->end_next_day === 1) {
                $yesterdayMatchesDay =
                    ($hasMask && $maskHas($r->days_mask, $dowYesterday))
                    || (!$hasMask && ((int) $r->day_of_week === $dowYesterday || $isEveryLegacy));

                if ($yesterdayMatchesDay) {
                    $startY = Carbon::parse($yesterday . ' ' . $r->start_time, $tz);
                    $endY = Carbon::parse($yesterday . ' ' . $r->end_time, $tz)->addDay();

                    $startYWithEarlyGrace = $startY->copy()->subMinutes($EARLY_GRACE_MINUTES);

                    if ($now->between($startYWithEarlyGrace, $endY, true)) {
                        $match = $r;
                        $win = (object) ['start' => $startY, 'startGrace' => $startYWithEarlyGrace, 'end' => $endY];
                        break;
                    }
                }
            }
        }



        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'You are outside your scheduled window right now.',
            ], 403);
        }

        // 5) Early/Late metrics (informational; you can decide later what to do with them)
        $isEarly = $now->lt($win->start) && $now->gte($win->startGrace);
        $earlyByMinutes = $isEarly ? $now->diffInMinutes($win->start, false) : 0;

        $isLate = $now->gt($win->start); // no late grace yet; adjust here later if you want
        $lateByMinutes = $isLate ? $win->start->diffInMinutes($now, false) : 0;

        // LA calendar only
        $day = $this->resolveDayStatusLA($now);

        // 6) Create clock-in (store schedId so clockOut can use it)
        DB::table('tblemployeeclocks')->insert([
            'userid' => $uid,
            'Employee' => $uname,
            'DateToday' => $now->toDateString(),     // LA date
            'TimeIn' => $now,                     // LA datetime
            'day_status' => $day['status'],           // Normal | Regular Holiday | Special Holiday
            'holidayID' => $day['holidayID'],        // nullable
            'schedId' => $match->schedId ?? null,  // <-- important for clockOut
            'Notes' => $match->title ? ('Matched schedule: ' . $match->title) : null,
            // don't write systemNotes for early/late yet—you're planning to handle that policy later
        ]);

        $this->userLogService->log('Clockin');

        return response()->json([
            'success' => true,
            'message' => 'Clocked in at ' . $now->format('h:i A') . ' (LA) • ' . $day['holidayTitle'],
            'meta' => [
                'holiday' => $day['holidayTitle'],
                'date' => $day['date'],
                'schedule' => [
                    'start' => $win->start->toDateTimeString(),
                    'startGrace' => $win->startGrace->toDateTimeString(),
                    'end' => $win->end->toDateTimeString(),
                    'title' => $match->title,
                    'schedId' => $match->schedId ?? null,
                ],
                'early' => $isEarly,
                'earlyByMinutes' => $earlyByMinutes,
                'late' => $isLate,
                'lateByMinutes' => $lateByMinutes,
            ],
        ]);
    }

    public function clockOut(Request $request)
    {
        $uid = Auth::id();
        $tz = 'America/Los_Angeles';
        $now = \Carbon\Carbon::now($tz);

        // 1) Find the open clock-in (no TimeOut), newest first — no date restriction (handles overnights)
        $open = DB::table('tblemployeeclocks')
            ->where('userid', $uid)
            ->whereNotNull('TimeIn')
            ->whereNull('TimeOut')
            ->orderByDesc('ID')
            ->first();

        if (!$open) {
            return response()->json([
                'success' => false,
                'message' => 'No open clock-in record found.',
            ], 400);
        }

        $timeIn = \Carbon\Carbon::parse($open->TimeIn, $tz);
        $anchorDate = $timeIn->toDateString();         // day the user actually clocked in
        $dowAnchor = (int) $timeIn->isoWeekday();     // 1..7 (Mon..Sun)

        // 2) Load active schedule links that were effective on the anchorDate.
        //    We don't pre-filter by day; we match using mask+legacy below.
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
                'ts.days_mask',
                'ts.start_time',
                'ts.end_time',
                'ts.end_next_day',
                'ts.title'
            )
            ->where('us.userId', $uid)
            ->where('us.is_active', 1)
            ->where(function ($q) use ($anchorDate) {
                $q->whereNull('us.effective_from')->orWhere('us.effective_from', '<=', $anchorDate);
            })
            ->where(function ($q) use ($anchorDate) {
                $q->whereNull('us.effective_to')->orWhere('us.effective_to', '>=', $anchorDate);
            })
            ->orderBy('ts.start_time')
            ->get();

        // helper: does a mask contain weekday? (1..7)
        $maskHas = function ($mask, $dow) {
            $m = (int) ($mask ?? 0);
            return $m > 0 && (($m & (1 << ($dow - 1))) !== 0);
        };

        // 3) Prefer exact schedule via schedId stored at clock-in; else attempt to match by day/mask and TimeIn window.
        $matchedSched = null;

        // (A) If we stored schedId at clock-in, use that directly.
        if (!empty($open->schedId)) {
            $sched = DB::table('tbltimesched')->where('timeschedId', $open->schedId)->first();
            if ($sched) {
                $start = \Carbon\Carbon::parse($anchorDate . ' ' . $sched->start_time, $tz);
                $end = \Carbon\Carbon::parse($anchorDate . ' ' . $sched->end_time, $tz);
                if ((int) $sched->end_next_day === 1)
                    $end->addDay();

                $matchedSched = (object) [
                    'start' => $start,
                    'end' => $end,
                    'title' => $sched->title,
                    'id' => $sched->timeschedId,
                ];
            }
        }

        // (B) Otherwise, try to infer the schedule by checking which window contains the TimeIn on the anchor date.
        if (!$matchedSched) {
            foreach ($links as $r) {
                $hasMask = ((int) ($r->days_mask ?? 0) > 0);
                $isEveryLegacy = ((int) $r->day_of_week) === 0;

                $dayOk = ($hasMask && $maskHas($r->days_mask, $dowAnchor))
                    || (!$hasMask && ($isEveryLegacy || (int) $r->day_of_week === $dowAnchor));

                if (!$dayOk)
                    continue;

                $schedStart = \Carbon\Carbon::parse($anchorDate . ' ' . $r->start_time, $tz);
                $schedEnd = \Carbon\Carbon::parse($anchorDate . ' ' . $r->end_time, $tz);
                if ((int) $r->end_next_day === 1)
                    $schedEnd->addDay();

                if ($timeIn->between($schedStart, $schedEnd, true)) {
                    $matchedSched = (object) [
                        'start' => $schedStart,
                        'end' => $schedEnd,
                        'title' => $r->title,
                        'id' => $r->schedId,
                    ];
                    break;
                }
            }
        }

        // 4) Compute scheduled duration and dynamic caps
        //    Grace is how long we allow beyond scheduled end before considering auto-clockout.
        $GRACE_MINUTES_AFTER_SCHEDULE = 180; // 3h (keep this in sync with your notifier)

        // Scheduled duration in minutes (from the matched schedule, or from the concrete schedule row if present)
        $scheduledDurationMinutes = 0;

        if ($matchedSched) {
            $scheduledDurationMinutes = $matchedSched->start->diffInMinutes($matchedSched->end, false);
        } else {
            // Last fallback if we truly cannot identify a schedule: assume 8h base shift.
            $scheduledDurationMinutes = 8 * 60;
        }

        // Dynamic hard max (minutes) = scheduled duration + grace
        $HARD_MAX_SHIFT_MINUTES = $scheduledDurationMinutes + $GRACE_MINUTES_AFTER_SCHEDULE;

        // 5) Decide if this clock-out is beyond allowed range (auto-clockout)
        $isAuto = false;

        if ($matchedSched) {
            $scheduledEndWithGrace = $matchedSched->end->copy()->addMinutes($GRACE_MINUTES_AFTER_SCHEDULE);
            // If current time is beyond scheduled end + grace → mark as auto
            $isAuto = $now->greaterThan($scheduledEndWithGrace);
        } else {
            // No matched schedule: enforce a sanity cap relative to TimeIn
            $isAuto = $timeIn->diffInMinutes($now) > $HARD_MAX_SHIFT_MINUTES;
        }

        // 6) Apply update (append system note if auto)
        $update = ['TimeOut' => $now];

        if ($isAuto) {
            $note = $matchedSched
                ? 'IMS: Auto Clockout (exceeded scheduled window)'
                : 'IMS: Auto Clockout (no schedule; exceeded hard cap)';

            // Safely append a newline-delimited note; keep existing content if any.
            $quoted = DB::getPdo()->quote($note);
            $update['systemNotes'] = DB::raw(
                "CASE WHEN systemNotes IS NULL OR systemNotes = '' 
                  THEN {$quoted}
                  ELSE CONCAT(systemNotes, '\n', {$quoted})
             END"
            );
        }

        DB::table('tblemployeeclocks')->where('ID', $open->ID)->update($update);

        $this->userLogService->log('Clockout');

        return response()->json([
            'success' => true,
            'message' => 'Clocked out at ' . $now->format('h:i A') . ' (LA)' . ($isAuto ? ' • Auto-clockout noted' : ''),
            'meta' => [
                'scheduledStart' => $matchedSched ? $matchedSched->start->toDateTimeString() : null,
                'scheduledEnd' => $matchedSched ? $matchedSched->end->toDateTimeString() : null,
                'graceMinutes' => $GRACE_MINUTES_AFTER_SCHEDULE,
                'hardMaxMinutes' => $HARD_MAX_SHIFT_MINUTES,
                'auto' => $isAuto,
            ],
        ]);
    }

    /**
     * Tiny helper to safely quote a string for DB::raw() usage.
     * If you already use bindings, you can refactor to bindings instead.
     */
    private function quote(string $s): string
    {
        return DB::getPdo()->quote($s);
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

        // ------------------------------
        // 0) Fetch open clock (if none, short-circuit)
        // ------------------------------
        $clock = $this->getOpenClock($userId);
        if (!$clock) {
            return response()->json([
                'hasOpenClock' => false,
                'message' => 'No open shift.',
            ]);
        }

        // ------------------------------
        // 1) Break computation (existing behavior)
        // ------------------------------
        $shiftDateLA = Carbon::parse($clock->DateToday, self::LA_TZ);
        $allowed = $this->resolveAllowedBreakMinutes($userId, $shiftDateLA);

        $usedMinutes = (float) ($clock->shortbreak_totaltime ?? 0.0);
        $elapsedSec = $this->currentBreakElapsedSeconds($clock, $nowLA);
        $elapsedMin = $elapsedSec / 60.0;
        $remaining = max(0.0, $allowed - $usedMinutes - $elapsedMin);

        // Auto-end break if already over allowance
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

            // Refresh snapshot for response consistency
            $clock = $this->getOpenClock($userId);
            $usedMinutes = (float) ($clock->shortbreak_totaltime ?? 0.0);
            $elapsedMin = 0.0;
        }

        // ------------------------------
        // 2) Auto-clockout guardrail (dynamic, mask-aware)
        // ------------------------------
        // Config: heads-up window & grace (keep consistent with your clockOut)
        $NOTIFY_BEFORE_MIN = 30;       // heads-up before auto clockout
        $GRACE_MIN_AFTER = 180;      // same grace used in clockOut()

        // Find the schedule that covers this open clock's TimeIn (mask-aware)
        $timeIn = Carbon::parse($clock->TimeIn, self::LA_TZ);
        $anchorDay = (int) $timeIn->isoWeekday();    // 1..7
        $anchorDateStr = $timeIn->toDateString();

        $links = DB::table('tblusersched as us')
            ->join('tbltimesched as ts', 'ts.timeschedId', '=', 'us.schedId')
            ->select(
                'us.userschedId',
                'us.userId',
                'us.effective_from',
                'us.effective_to',
                'us.is_active',
                'ts.timeschedId',
                'ts.day_of_week',
                'ts.days_mask',
                'ts.start_time',
                'ts.end_time',
                'ts.end_next_day',
                'ts.title'
            )
            ->where('us.userId', $userId)
            ->where('us.is_active', 1)
            ->where(function ($q) use ($anchorDateStr) {
                $q->whereNull('us.effective_from')->orWhere('us.effective_from', '<=', $anchorDateStr);
            })
            ->where(function ($q) use ($anchorDateStr) {
                $q->whereNull('us.effective_to')->orWhere('us.effective_to', '>=', $anchorDateStr);
            })
            ->orderBy('ts.start_time')
            ->get();

        $maskHas = function ($mask, $dow) {
            $m = (int) ($mask ?? 0);
            return $m > 0 && (($m & (1 << ($dow - 1))) !== 0);
        };

        $match = null;  // { start: Carbon, end: Carbon, title: string, timeschedId: int|null }

        foreach ($links as $r) {
            $hasMask = ((int) ($r->days_mask ?? 0) > 0);
            $isEveryLegacy = ((int) $r->day_of_week) === 0;

            $dayOk = ($hasMask && $maskHas($r->days_mask, $anchorDay))
                || (!$hasMask && ($isEveryLegacy || (int) $r->day_of_week === $anchorDay));

            if (!$dayOk)
                continue;

            $schedStart = Carbon::parse($anchorDateStr . ' ' . $r->start_time, self::LA_TZ);
            $schedEnd = Carbon::parse($anchorDateStr . ' ' . $r->end_time, self::LA_TZ);
            if ((int) $r->end_next_day === 1)
                $schedEnd->addDay();

            if ($timeIn->between($schedStart, $schedEnd, true)) {
                $match = (object) [
                    'start' => $schedStart,
                    'end' => $schedEnd,
                    'title' => $r->title,
                    'timeschedId' => $r->timeschedId ?? null,
                ];
                break;
            }
        }

        // Compute schedule duration (minutes)
        if ($match) {
            $scheduledDurationMin = max(1, (int) $match->start->diffInMinutes($match->end));
        } else {
            // No schedule found -> fallback to 8 hours
            $scheduledDurationMin = 8 * 60;
        }

        // Hard cap moment = scheduled end + grace
        $capMoment = ($match ? $match->end->copy() : $timeIn->copy()->addMinutes($scheduledDurationMin))
            ->addMinutes($GRACE_MIN_AFTER);

        $minsToCap = (int) ceil(($capMoment->getTimestamp() - $nowLA->getTimestamp()) / 60);

        // 2a) Heads-up notification when close to auto-clockout (only once)
        if ($minsToCap > 0 && $minsToCap <= $NOTIFY_BEFORE_MIN) {
            // dedupe: check if we've already sent one for this open clock id
            $existingNotif = DB::table('tblnotifications as n')
                ->join('tblnotificationsuser as nu', 'nu.notif_id', '=', 'n.id')
                ->where('nu.userid', $userId)
                ->where('n.action_made', 'auto_clockout_soon')
                ->whereDate('n.created_at', Carbon::now('UTC')->toDateString())
                ->where('n.link_data', 'like', '%"clock_id":' . ((int) $clock->ID) . '%')
                ->exists();

            if (!$existingNotif) {
                $notifId = DB::table('tblnotifications')->insertGetId([
                    'module' => 'HR',
                    'title' => 'You will be auto-clocked out soon',
                    'subtitle' => null,
                    'content' => 'You have about ' . $minsToCap . ' minute(s) before auto clockout.',
                    'severity' => 'warning',
                    'action_made' => 'auto_clockout_soon',
                    'link_data' => json_encode([
                        'type' => 'modal',
                        'method' => 'GET',
                        'url' => null,
                        'modal_id' => 'announcement-view', // harmless; you can change to a HR modal if you have one
                        'data' => ['clock_id' => (int) $clock->ID],
                    ]),
                    'created_at' => now('UTC'),
                ]);

                DB::table('tblnotificationsuser')->insert([
                    'notif_id' => $notifId,
                    'userid' => $userId,
                    'read_status' => 'unread',
                    'created_at' => now('UTC'),
                ]);
            }
        }

        // 2b) Auto-clockout at/after cap
        $autoClockedOut = false;
        if ($minsToCap <= 0) {
            $update = [
                'TimeOut' => $nowLA,
                'systemNotes' => DB::raw(
                    "CASE WHEN systemNotes IS NULL OR systemNotes = '' 
                      THEN 'IMS: Auto Clockout (status watchdog)'
                      ELSE CONCAT(systemNotes, '\n','IMS: Auto Clockout (status watchdog)')
                 END"
                ),
            ];

            DB::table('tblemployeeclocks')->where('ID', $clock->ID)->update($update);
            $this->userLogService->log('Clockout'); // keep audit trail consistent
            $autoClockedOut = true;

            // refresh to reflect updated state if you want to echo it back
            $clock = $this->getOpenClock($userId); // will likely be null now
        }

        // ------------------------------
        // 3) Response
        // ------------------------------
        return response()->json([
            'hasOpenClock' => !$autoClockedOut, // if auto-clocked out, no open shift anymore
            'status' => $clock ? ($clock->shortbreak_status ?? 'idle') : 'idle',
            'allowedMin' => (float) $allowed,
            'usedMin' => (float) $usedMinutes + $elapsedMin,
            'remainingMin' => max(0.0, (float) $allowed - ((float) $usedMinutes + $elapsedMin)),
            'onBreakSince' => $clock->shortbreak_start ?? null,
            'lastBreakEnd' => $clock->shortbreak_end ?? null,
            'serverNow' => $nowLA->toIso8601String(),

            // New meta for the guardrail:
            'autoClockout' => $autoClockedOut,
            'capAt' => $capMoment->toIso8601String(),
            'minsToCap' => $minsToCap,
            'schedWindow' => $match ? [
                'start' => $match->start->toIso8601String(),
                'end' => $match->end->toIso8601String(),
                'title' => $match->title,
            ] : null,
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
