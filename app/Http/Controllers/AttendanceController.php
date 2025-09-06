<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
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

    public function sendClockinMail($user, $currentDatetimeStr, $action)
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
            $mail->Body = "<span style='color: green; text-transform: uppercase;'>$user</span> $action $currentDatetimeStr";

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

        $currentDatetimeStr = $now->format('Y-m-d H:i:s');

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

        // 3) Load active links (+ mins fields)
        $links = DB::table('tblusersched as us')
            ->join('tbltimesched as ts', 'ts.timeschedId', '=', 'us.schedId')
            ->select(
                'us.userschedId',
                'us.userId',
                'us.schedId',
                'us.effective_from',
                'us.effective_to',
                'us.is_active',
                // user overrides
                'us.early_login_mins  as us_early_login_mins',
                'us.early_clockin_mins as us_early_clockin_mins',
                'us.grace_clockout_mins as us_grace_clockout_mins',
                // schedule/template
                'ts.day_of_week',
                'ts.days_mask',
                'ts.start_time',
                'ts.end_time',
                'ts.end_next_day',
                'ts.title',
                // template defaults
                'ts.early_login_mins  as ts_early_login_mins',
                'ts.early_clockin_mins as ts_early_clockin_mins',
                'ts.grace_clockout_mins as ts_grace_clockout_mins'
            )
            ->where('us.userId', $uid)
            ->where('us.is_active', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('us.effective_from')->orWhere('us.effective_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('us.effective_to')->orWhere('us.effective_to', '>=', $today);
            })
            ->orderBy('ts.start_time') // earliest first
            ->get();

        if ($links->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'You have no schedule assigned for today.',
            ], 403);
        }

        $maskHas = function (int $mask = null, int $dow) {
            $mask = (int) ($mask ?? 0);
            if ($mask <= 0)
                return false;
            $bit = 1 << ($dow - 1);            // Mon=1 -> bit 0
            return (($mask & $bit) !== 0);
        };

        // user override > template default > hard default
        $resolveMins = function ($userVal, $tmplVal, $default = 5) {
            if (is_numeric($userVal))
                return (int) $userVal;
            if (is_numeric($tmplVal))
                return (int) $tmplVal;
            return (int) $default;
        };

        $match = null;
        $win = null;
        $effective = [
            'early_clockin_mins' => null,
            'early_login_mins' => null,
            'grace_clockout_mins' => null,
        ];

        // If user is TOO EARLY for a valid window, remember the first allowed time so we can message it nicely.
        $tooEarly = null; // ['title'=>..., 'allowedAt'=>Carbon, 'start'=>Carbon, 'end'=>Carbon, 'mins'=>int]

        foreach ($links as $r) {
            $hasMask = (int) ($r->days_mask ?? 0) > 0;
            $isEveryLegacy = ((int) $r->day_of_week) === 0;

            $effEarlyClockIn = $resolveMins($r->us_early_clockin_mins, $r->ts_early_clockin_mins, 5);
            $effEarlyLogin = $resolveMins($r->us_early_login_mins, $r->ts_early_login_mins, 0);
            $effGraceOut = $resolveMins($r->us_grace_clockout_mins, $r->ts_grace_clockout_mins, 0);

            // ---- Case A: today-anchored window
            $todayMatchesDay =
                ($hasMask && $maskHas((int) $r->days_mask, $dowToday)) ||
                (!$hasMask && (((int) $r->day_of_week === $dowToday) || $isEveryLegacy));

            if ($todayMatchesDay) {
                $start = Carbon::parse($today . ' ' . $r->start_time, $tz);
                $end = Carbon::parse($today . ' ' . $r->end_time, $tz);
                if ((int) $r->end_next_day === 1)
                    $end->addDay();

                $startWithEarly = $start->copy()->subMinutes($effEarlyClockIn);

                if ($now->between($startWithEarly, $end, true)) {
                    $match = $r;
                    $win = (object) ['start' => $start, 'startGrace' => $startWithEarly, 'end' => $end];
                    $effective['early_clockin_mins'] = $effEarlyClockIn;
                    $effective['early_login_mins'] = $effEarlyLogin;
                    $effective['grace_clockout_mins'] = $effGraceOut;
                    break;
                } elseif ($now->lt($startWithEarly)) {
                    // too early for this valid window → keep the soonest allowed
                    if (!$tooEarly || $startWithEarly->lt($tooEarly['allowedAt'])) {
                        $tooEarly = [
                            'title' => $r->title,
                            'allowedAt' => $startWithEarly,
                            'start' => $start,
                            'end' => $end,
                            'mins' => $effEarlyClockIn,
                        ];
                    }
                }
            }

            // ---- Case B: overnight window anchored yesterday
            if ((int) $r->end_next_day === 1) {
                $yesterdayMatchesDay =
                    ($hasMask && $maskHas((int) $r->days_mask, $dowYesterday)) ||
                    (!$hasMask && (((int) $r->day_of_week === $dowYesterday) || $isEveryLegacy));

                if ($yesterdayMatchesDay) {
                    $startY = Carbon::parse($yesterday . ' ' . $r->start_time, $tz);
                    $endY = Carbon::parse($yesterday . ' ' . $r->end_time, $tz)->addDay();

                    $startYWithEarly = $startY->copy()->subMinutes($effEarlyClockIn);

                    if ($now->between($startYWithEarly, $endY, true)) {
                        $match = $r;
                        $win = (object) ['start' => $startY, 'startGrace' => $startYWithEarly, 'end' => $endY];
                        $effective['early_clockin_mins'] = $effEarlyClockIn;
                        $effective['early_login_mins'] = $effEarlyLogin;
                        $effective['grace_clockout_mins'] = $effGraceOut;
                        break;
                    }
                    // “too-early” doesn’t apply for yesterday-anchored windows (it’s in the past)
                }
            }
        }

        // No matching open window right now
        if (!$match) {
            if ($tooEarly) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too early to clock in. Earliest allowed: ' .
                        $tooEarly['allowedAt']->format('h:i A') .
                        ' (LA). Your shift: ' . $tooEarly['start']->format('h:i A') .
                        ' – ' . $tooEarly['end']->format('h:i A') .
                        ($tooEarly['title'] ? (' • ' . $tooEarly['title']) : ''),
                    'meta' => [
                        'allowedAt' => $tooEarly['allowedAt']->toDateTimeString(),
                        'schedule' => [
                            'start' => $tooEarly['start']->toDateTimeString(),
                            'end' => $tooEarly['end']->toDateTimeString(),
                            'title' => $tooEarly['title'],
                        ],
                        'effective' => [
                            'early_clockin_mins' => $tooEarly['mins'],
                        ],
                    ],
                ], 403);
            }

            return response()->json([
                'success' => false,
                'message' => 'You are outside your scheduled window right now.',
            ], 403);
        }

        // Early/Late metrics
        $isEarly = $now->lt($win->start) && $now->gte($win->startGrace);
        $earlyByMinutes = $isEarly ? $now->diffInMinutes($win->start, false) : 0;

        // NOTE: no late-grace for clock-in (by design). Add a field later if needed.
        $isLate = $now->gt($win->start);
        $lateByMinutes = $isLate ? $win->start->diffInMinutes($now, false) : 0;

        // LA calendar only
        $day = $this->resolveDayStatusLA($now);

        // Create clock-in
        DB::table('tblemployeeclocks')->insert([
            'userid' => $uid,
            'Employee' => $uname,
            'DateToday' => $now->toDateString(),
            'TimeIn' => $now,
            'day_status' => $day['status'],
            'holidayID' => $day['holidayID'],
            'schedId' => $match->schedId ?? null,
            'Notes' => ($match->title ? ('Matched schedule: ' . $match->title . ' • ') : '')
                . 'early_clockin_mins=' . $effective['early_clockin_mins'],
        ]);

        $this->userLogService->log('Clockin');
        $this->sendClockinMail($uname, $currentDatetimeStr, "Clock In");

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
                'effective' => $effective, // echoes mins used (from DB)
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
    $tz  = 'America/Los_Angeles';
    $now = \Carbon\Carbon::now($tz);

    // 1) Find the open clock-in
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

    $timeIn     = \Carbon\Carbon::parse($open->TimeIn, $tz);
    $anchorDate = $timeIn->toDateString();            // day the user clocked in
    $dowAnchor  = (int) $timeIn->isoWeekday();        // 1..7

    $maskHas = function ($mask, $dow) {
        $m = (int) ($mask ?? 0);
        return $m > 0 && (($m & (1 << ($dow - 1))) !== 0);
    };

    // user override > template > default
    $resolveMins = function ($userVal, $tmplVal, $default = 0) {
        if (is_numeric($userVal)) return (int)$userVal;
        if (is_numeric($tmplVal)) return (int)$tmplVal;
        return (int)$default;
    };

    // 2) Load links effective on the anchor date (so mid-shift changes don’t break)
    $links = DB::table('tblusersched as us')
        ->join('tbltimesched as ts', 'ts.timeschedId', '=', 'us.schedId')
        ->select(
            'us.userschedId','us.userId','us.schedId','us.effective_from','us.effective_to','us.is_active',
            'us.early_login_mins  as us_early_login_mins',
            'us.early_clockin_mins as us_early_clockin_mins',
            'us.grace_clockout_mins as us_grace_clockout_mins',
            'ts.timeschedId','ts.day_of_week','ts.days_mask','ts.start_time','ts.end_time','ts.end_next_day','ts.title',
            'ts.early_login_mins  as ts_early_login_mins',
            'ts.early_clockin_mins as ts_early_clockin_mins',
            'ts.grace_clockout_mins as ts_grace_clockout_mins'
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

    // 3) Reconstruct the scheduled window
    $matchedSched = null;  // {start, end, title, id}
    $effective = [
        'early_login_mins'    => null,
        'early_clockin_mins'  => null,
        'grace_clockout_mins' => null,
    ];

    // Prefer the exact schedId stored on clock-in (keeps overrides)
    if (!empty($open->schedId)) {
        $linkForId = $links->first(fn($r) => (int)$r->schedId === (int)$open->schedId);
        if ($linkForId) {
            $start = \Carbon\Carbon::parse($anchorDate.' '.$linkForId->start_time, $tz);
            $end   = \Carbon\Carbon::parse($anchorDate.' '.$linkForId->end_time,   $tz);
            if ((int)$linkForId->end_next_day === 1) $end->addDay();

            $matchedSched = (object)[
                'start' => $start,
                'end'   => $end,
                'title' => $linkForId->title,
                'id'    => $linkForId->schedId,
            ];

            $effective['early_login_mins']    = $resolveMins($linkForId->us_early_login_mins,   $linkForId->ts_early_login_mins,   0);
            $effective['early_clockin_mins']  = $resolveMins($linkForId->us_early_clockin_mins, $linkForId->ts_early_clockin_mins, 5);
            $effective['grace_clockout_mins'] = $resolveMins($linkForId->us_grace_clockout_mins,$linkForId->ts_grace_clockout_mins,180);
        } else {
            // Template only (no overrides)
            $sched = DB::table('tbltimesched')->where('timeschedId', $open->schedId)->first();
            if ($sched) {
                $start = \Carbon\Carbon::parse($anchorDate.' '.$sched->start_time, $tz);
                $end   = \Carbon\Carbon::parse($anchorDate.' '.$sched->end_time,   $tz);
                if ((int)$sched->end_next_day === 1) $end->addDay();

                $matchedSched = (object)[
                    'start' => $start,
                    'end'   => $end,
                    'title' => $sched->title,
                    'id'    => $sched->timeschedId,
                ];

                $effective['early_login_mins']    = is_numeric($sched->early_login_mins)    ? (int)$sched->early_login_mins    : 0;
                $effective['early_clockin_mins']  = is_numeric($sched->early_clockin_mins)  ? (int)$sched->early_clockin_mins  : 5;
                $effective['grace_clockout_mins'] = is_numeric($sched->grace_clockout_mins) ? (int)$sched->grace_clockout_mins : 180;
            }
        }
    }

    // Fallback: infer the schedule by day/mask that contained TimeIn
    if (!$matchedSched) {
        foreach ($links as $r) {
            $hasMask      = ((int)($r->days_mask ?? 0) > 0);
            $isEveryLegacy= ((int)$r->day_of_week) === 0;

            $dayOk = ($hasMask && $maskHas($r->days_mask, $dowAnchor))
                  || (!$hasMask && ($isEveryLegacy || (int)$r->day_of_week === $dowAnchor));
            if (!$dayOk) continue;

            $schedStart = \Carbon\Carbon::parse($anchorDate.' '.$r->start_time, $tz);
            $schedEnd   = \Carbon\Carbon::parse($anchorDate.' '.$r->end_time,   $tz);
            if ((int)$r->end_next_day === 1) $schedEnd->addDay();

            if ($timeIn->between($schedStart, $schedEnd, true)) {
                $matchedSched = (object)[
                    'start' => $schedStart,
                    'end'   => $schedEnd,
                    'title' => $r->title,
                    'id'    => $r->schedId,
                ];
                $effective['early_login_mins']    = $resolveMins($r->us_early_login_mins,   $r->ts_early_login_mins,   0);
                $effective['early_clockin_mins']  = $resolveMins($r->us_early_clockin_mins, $r->ts_early_clockin_mins, 5);
                $effective['grace_clockout_mins'] = $resolveMins($r->us_grace_clockout_mins,$r->ts_grace_clockout_mins,180);
                break;
            }
        }
    }

    // 4) Compute grace, caps, and early/OT info
    $GRACE_DEFAULT = 180; // mins, only if no schedule
    $effGrace = $matchedSched ? ($effective['grace_clockout_mins'] ?? $GRACE_DEFAULT) : $GRACE_DEFAULT;

    // If we found a schedule, compute deltas w.r.t. scheduled end
    $earlyOutMins = null;  // positive means minutes early
    $overTimeMins = null;  // positive means minutes over (beyond end)
    $autoCutoff   = null;  // end + grace

    if ($matchedSched) {
        $delta = $now->diffInMinutes($matchedSched->end, false); // >0 before end; <0 after end
        if ($delta > 0) {
            $earlyOutMins = $delta;               // user leaves early
        } elseif ($delta < 0) {
            $overTimeMins = abs($delta);          // user leaves after end
        }
        $autoCutoff = $matchedSched->end->copy()->addMinutes($effGrace);
    }

    // Dynamic hard cap when there is no schedule (TimeIn + 8h + grace by default)
    $scheduledDurationMinutes = $matchedSched
        ? $matchedSched->start->diffInMinutes($matchedSched->end, false)
        : (8 * 60);
    $HARD_MAX_SHIFT_MINUTES = $scheduledDurationMinutes + $effGrace;

    // 5) Decide auto-clockout
    $isAuto = false;
    if ($matchedSched) {
        $isAuto = $now->greaterThan($autoCutoff);
    } else {
        $isAuto = $timeIn->diffInMinutes($now) > $HARD_MAX_SHIFT_MINUTES;
    }

    // 6) Build notes (early/OT/auto) and update
    $notes = [];

    if ($matchedSched) {
        if ($earlyOutMins !== null && $earlyOutMins > 0) {
            $notes[] = "Early clock-out ({$earlyOutMins} min before scheduled end)";
        }
        if ($overTimeMins !== null && $overTimeMins > 0) {
            $notes[] = "Overtime (+{$overTimeMins} min beyond scheduled end)";
        }
        if ($isAuto) {
            $notes[] = "IMS: Auto Clockout (exceeded scheduled window + {$effGrace} min grace)";
        }
    } else {
        if ($isAuto) {
            $notes[] = "IMS: Auto Clockout (no schedule; exceeded hard cap {$HARD_MAX_SHIFT_MINUTES} min)";
        }
    }

    $update = ['TimeOut' => $now];

    if (!empty($notes)) {
        $joined = implode(' • ', $notes);
        $quoted = DB::getPdo()->quote($joined);
        $update['systemNotes'] = DB::raw(
            "CASE WHEN systemNotes IS NULL OR systemNotes = '' 
                  THEN {$quoted}
                  ELSE CONCAT(systemNotes, '\n', {$quoted})
             END"
        );
    }

    DB::table('tblemployeeclocks')->where('ID', $open->ID)->update($update);

    $this->userLogService->log('Clockout');

    $uname = Auth::user()->username;
    $currentDatetimeStr = $now->format('Y-m-d H:i:s');
    $this->sendClockinMail($uname, $currentDatetimeStr, "Clock Out");

    return response()->json([
        'success' => true,
        'message' => 'Clocked out at ' . $now->format('h:i A') . ' (LA)' . ($isAuto ? ' • Auto-clockout noted' : ''),
        'meta' => [
            'scheduledStart' => $matchedSched ? $matchedSched->start->toDateTimeString() : null,
            'scheduledEnd'   => $matchedSched ? $matchedSched->end->toDateTimeString()   : null,
            'effective' => $effective,            // echo mins that affected behavior
            'graceMinutesUsed' => $effGrace,
            'hardMaxMinutes'   => $HARD_MAX_SHIFT_MINUTES,
            'earlyOutMins'     => $earlyOutMins,
            'overTimeMins'     => $overTimeMins,
            'auto'             => $isAuto,
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

    public function month(Request $req)
    {
        $userId = Auth::id();
        $ym = $req->query('ym'); // YYYY-MM
        if (!$ym || !preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return response()->json(['error' => 'Invalid ym'], 400);
        }

        [$year, $month] = array_map('intval', explode('-', $ym));
        $start = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        $now = \Carbon\Carbon::now();                 // server TZ
        $todayDate = $now->toDateString();
        $LATE_GRACE_MINUTES = 5;                      // tweak if needed

        // ---- earliest clock-in date (limit 'before') ----
        $earliestClockDate = DB::table('tblemployeeclocks')
            ->where('userid', $userId)
            ->selectRaw('MIN(COALESCE(DateToday, DATE(TimeIn))) as d')
            ->value('d'); // 'YYYY-MM-DD' or null

        // ---- user schedule links overlapping this month ----
        $links = DB::table('tblusersched as us')
            ->where('us.userId', $userId)
            ->where('us.is_active', 1)
            ->where(function ($q) use ($end) {
                $q->whereNull('us.effective_from')
                    ->orWhere('us.effective_from', '<=', $end->toDateString());
            })
            ->where(function ($q) use ($start) {
                $q->whereNull('us.effective_to')
                    ->orWhere('us.effective_to', '>=', $start->toDateString());
            })
            ->orderByDesc('us.userschedId')
            ->get(['us.userschedId', 'us.schedId', 'us.effective_from', 'us.effective_to']);

        $schedIds = $links->pluck('schedId')->unique()->values();

        // timesched rows
        $bySched = [];
        if ($schedIds->isNotEmpty()) {
            $blocks = DB::table('tbltimesched')
                ->whereIn('timeschedId', $schedIds)
                ->where('is_active', 1)
                ->get([
                    'timeschedId as schedId',
                    'days_mask',
                    'start_time',
                    'end_time',
                    'end_next_day',
                    'title'
                ]);
            foreach ($blocks as $b)
                $bySched[$b->schedId] = $b;
        }

        // holidays
        $rawHolidays = DB::table('tblholiday')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('holidate', [$start->toDateString(), $end->toDateString()])
                    ->orWhere('is_recurring', 1);
            })
            ->get(['holidate', 'status', 'title', 'is_recurring']);

        $holidayAbs = [];  // YYYY-MM-DD => [...]
        $holidayByMD = []; // MM-DD => [...]
        foreach ($rawHolidays as $h) {
            if ($h->is_recurring) {
                $md = \Carbon\Carbon::parse($h->holidate)->format('m-d');
                $holidayByMD[$md][] = [
                    'date' => $h->holidate,
                    'status' => $h->status,
                    'title' => $h->title,
                    'recurring' => true,
                ];
            } else {
                $iso = \Carbon\Carbon::parse($h->holidate)->toDateString();
                $holidayAbs[$iso][] = [
                    'date' => $iso,
                    'status' => $h->status,
                    'title' => $h->title,
                    'recurring' => false,
                ];
            }
        }

        // first TimeIn per day for this month
        $firstIns = DB::table('tblemployeeclocks')
            ->where('userid', $userId)
            ->whereBetween(
                DB::raw('COALESCE(DateToday, DATE(TimeIn))'),
                [$start->toDateString(), $end->toDateString()]
            )
            ->selectRaw('COALESCE(DateToday, DATE(TimeIn)) as d, MIN(TimeIn) as first_in')
            ->groupBy('d')
            ->pluck('first_in', 'd'); // 'YYYY-MM-DD' => 'YYYY-MM-DD HH:MM:SS'

        $byDate = [];
        $bitByIsoDow = [1 => 1, 2 => 2, 3 => 4, 4 => 8, 5 => 16, 6 => 32, 7 => 64];

        foreach (\Carbon\CarbonPeriod::create($start, '1 day', $end) as $d) {
            $iso = $d->toDateString();
            $md = $d->format('m-d');
            $bit = $bitByIsoDow[$d->isoWeekday()];

            // holidays
            $hols = array_merge($holidayAbs[$iso] ?? [], $holidayByMD[$md] ?? []);
            $holiday_full = $hols
                ? ('Holiday: ' . implode(' / ', array_map(
                    fn($h) => ($h['status'] ? ($h['status'] . ': ') : '') . $h['title'],
                    $hols
                )))
                : '';

            // active link for this date
            $active = $links->first(function ($lnk) use ($d) {
                $fromOk = !$lnk->effective_from || \Carbon\Carbon::parse($lnk->effective_from)->startOfDay() <= $d;
                $toOk = !$lnk->effective_to || \Carbon\Carbon::parse($lnk->effective_to)->endOfDay() >= $d;
                return $fromOk && $toOk;
            });

            $entries = [];
            $scheduledStartDT = null;
            $scheduledEndDT = null;
            $isScheduledDay = false;

            if ($active && isset($bySched[$active->schedId])) {
                $row = $bySched[$active->schedId];
                $mask = (int) ($row->days_mask ?? 0);

                if (($mask & $bit) !== 0) {
                    $isScheduledDay = true;

                    // AM/PM for UI
                    $start12 = \Carbon\Carbon::createFromFormat('H:i:s', (string) $row->start_time)->format('g:i A');
                    $end12 = \Carbon\Carbon::createFromFormat('H:i:s', (string) $row->end_time)->format('g:i A');
                    $end12 .= ((int) $row->end_next_day === 1) ? ' (+1)' : '';

                    $entries[] = [
                        'start' => $start12,
                        'end' => $end12,
                        'name' => $row->title ?: 'Shift',
                        'notes' => '',
                        'next_day' => (bool) $row->end_next_day,
                    ];

                    // schedule window anchored to this date
                    $scheduledStartDT = \Carbon\Carbon::parse($iso . ' ' . $row->start_time);
                    $scheduledEndDT = \Carbon\Carbon::parse($iso . ' ' . $row->end_time);
                    if ((int) $row->end_next_day === 1) {
                        $scheduledEndDT->addDay();
                    }
                }
            }

            // compact cell label (keep short; no long holiday titles)
            $timeLabel = '—';
            if ($entries) {
                $timeLabel = count($entries) === 1
                    ? ($entries[0]['start'] . '–' . $entries[0]['end'])
                    : (count($entries) . ' shifts');
            }
            $label = $timeLabel;

            // attendance status
            $status = null;
            $firstIn = isset($firstIns[$iso]) ? \Carbon\Carbon::parse($firstIns[$iso]) : null;
            $isFutureDay = $d->gt($todayDate);
            $isBeforeEarliest = $earliestClockDate ? $d->lt(\Carbon\Carbon::parse($earliestClockDate)) : false;
            $isToday = $iso === $todayDate;

            if ($isScheduledDay) {
                if ($firstIn) {
                    $lateThreshold = $scheduledStartDT ? $scheduledStartDT->copy()->addMinutes($LATE_GRACE_MINUTES) : null;
                    $status = ($lateThreshold && $firstIn->greaterThan($lateThreshold)) ? 'late' : 'present';
                } else {
                    if ($isFutureDay) {
                        // future: never absent/late
                        $status = null;
                    } elseif ($isBeforeEarliest) {
                        // before user's first ever clock: don't mark absent
                        $status = null;
                    } elseif ($isToday && $scheduledStartDT && $scheduledEndDT) {
                        if ($now->lt($scheduledStartDT)) {
                            // before window starts -> no dot yet
                            $status = null;
                        } elseif ($now->between($scheduledStartDT, $scheduledEndDT)) {
                            // inside window with no clock-in -> constant late
                            $status = 'late';
                        } else {
                            // after window ends with no clock-in -> absent
                            $status = 'absent';
                        }
                    } else {
                        // past scheduled day with no clock-in -> absent
                        $status = 'absent';
                    }
                }
            } else {
                // not scheduled
                if ($firstIn) {
                    $status = 'present'; // unscheduled work
                } else {
                    // never show absent for non-scheduled days
                    $status = null;
                }
            }

            $byDate[$iso] = [
                'label' => $label,
                'holiday_full' => $holiday_full,
                'holidays' => $hols,
                'entries' => $entries,
                'status' => $status, // present | late | absent | null
            ];
        }

        return response()->json(['ym' => $ym, 'byDate' => $byDate]);
    }




}
