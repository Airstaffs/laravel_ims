<?php

namespace App\Http\Controllers;

use App\Services\TwilioService;
use App\Services\UserLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class AttendanceController extends Controller
{
    protected $userLogService;
    protected $twilioService;

    public function __construct(UserLogService $userLogService, TwilioService $twilioService)
    {
        $this->userLogService = $userLogService;
        $this->twilioService = $twilioService;
    }

    // ============================================================
    // UTF-8 SAFETY HELPERS
    // ============================================================

    /**
     * Recursively clean all strings in a value to valid UTF-8.
     * This prevents "Malformed UTF-8" errors during json_encode.
     */
    private function deepCleanUtf8($value)
    {
        if (is_string($value)) {
            // Remove invalid UTF-8 sequences
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            // Strip null bytes
            $value = str_replace("\0", '', $value);
            return $value;
        }

        if (is_array($value)) {
            return array_map([$this, 'deepCleanUtf8'], $value);
        }

        if (is_object($value)) {
            foreach (get_object_vars($value) as $k => $v) {
                $value->$k = $this->deepCleanUtf8($v);
            }
            return $value;
        }

        return $value;
    }

    /**
     * Return a JSON response with all strings sanitized to valid UTF-8.
     * Use this instead of response()->json() throughout this controller.
     */
    private function safeJsonResponse(array $data, int $status = 200): \Illuminate\Http\JsonResponse
    {
        $cleaned = $this->deepCleanUtf8($data);

        return response()->json(
            $cleaned,
            $status,
            [],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * Safely convert a possibly-null string to clean UTF-8.
     */
    private function safeStr(?string $value): string
    {
        if ($value === null) return '';
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    /**
     * Safely get exception message as clean UTF-8.
     */
    private function safeExceptionMessage(\Exception $e): string
    {
        return mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
    }

    // ============================================================
    // ATTENDANCE PAGE (Blade view)
    // ============================================================

    public function attendance()
    {
        $currentUserId = Auth::user()->id;

        $employeeClocks = DB::table('tblemployeeclocks')
            ->join('tbluser', 'tblemployeeclocks.userid', '=', 'tbluser.id')
            ->select(
                'tblemployeeclocks.ID as clock_id',
                'tblemployeeclocks.userid as user_id',
                'tblemployeeclocks.Employee as employee_name',
                'tblemployeeclocks.TimeIn as time_in',
                'tblemployeeclocks.TimeOut as time_out',
                'tbluser.username as user_name',
                'tblemployeeclocks.Notes as notes_'
            )
            ->where('tblemployeeclocks.userid', $currentUserId)
            ->orderBy('tblemployeeclocks.TimeIn', 'desc')
            ->get();

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
            ->where('tblemployeeclocks.userid', $currentUserId)
            ->whereBetween('tblemployeeclocks.TimeIn', [
                Carbon::now('America/Los_Angeles')->startOfWeek(),
                Carbon::now('America/Los_Angeles')->endOfWeek(),
            ])
            ->orderBy('tblemployeeclocks.TimeIn', 'desc')
            ->get();

        $lastRecord = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereDate('TimeIn', Carbon::today('America/Los_Angeles'))
            ->orderBy('ID', 'desc')
            ->first();

        $verylastRecord = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->orderBy('ID', 'desc')
            ->first();

        $todayHours = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereDate('TimeIn', Carbon::today('America/Los_Angeles'))
            ->sum(DB::raw('
                TIMESTAMPDIFF(MINUTE, TimeIn, COALESCE(TimeOut, DATE_SUB(NOW(), INTERVAL 8 HOUR)))
            '));

        $weekHours = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereBetween('TimeIn', [
                Carbon::now('America/Los_Angeles')->startOfWeek(),
                Carbon::now('America/Los_Angeles')->endOfWeek(),
            ])
            ->sum(DB::raw('
                TIMESTAMPDIFF(MINUTE, TimeIn, COALESCE(TimeOut, DATE_SUB(NOW(), INTERVAL 8 HOUR)))
            '));

        $todayHoursFormatted = sprintf('%d hrs %02d mins', intdiv($todayHours, 60), $todayHours % 60);
        $weekHoursFormatted = sprintf('%d hrs %02d mins', intdiv($weekHours, 60), $weekHours % 60);

        return view(
            'dashboard.Systemdashboard',
            compact('employeeClocks', 'lastRecord', 'verylastRecord', 'todayHoursFormatted', 'weekHoursFormatted', 'employeeClocksThisweek')
        );
    }

    // ============================================================
    // DAY STATUS RESOLVER
    // ============================================================

    private function resolveDayStatusLA(Carbon $nowLA): array
    {
        $laDate = $nowLA->toDateString();
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
            'status'       => $this->safeStr($holiday->status ?? 'Normal'),
            'holidayID'    => $holiday->holidayID ?? null,
            'holidayTitle' => $this->safeStr($holiday->title ?? ''),
            'date'         => $laDate,
        ];
    }

    // ============================================================
    // EMAIL NOTIFICATION
    // ============================================================

    public function sendClockinMail($user, $currentDatetimeStr, $action)
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'jundell@airstaffs.com';
            $mail->Password = 'scjjcpxcmwyjwegh';
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

    // ============================================================
    // CLOCK IN
    // ============================================================

    public function clockIn(Request $request)
    {
        try {
            $uid   = Auth::id();
            $uname = $this->safeStr(Auth::user()->username);
            $tz    = 'America/Los_Angeles';
            $now   = Carbon::now($tz);

            $currentDatetimeStr = $now->format('Y-m-d H:i:s');

            // 1) Block if already clocked-in (no TimeOut yet)
            $open = DB::table('tblemployeeclocks')
                ->where('userid', $uid)
                ->whereNull('TimeOut')
                ->orderBy('ID', 'desc')
                ->first();

            if ($open) {
                return $this->safeJsonResponse([
                    'success' => false,
                    'message' => 'You already have an open clock-in. Please clock out first.',
                ], 409);
            }

            // 2) Resolve calendar
            $dowToday     = (int) $now->isoWeekday();
            $dowYesterday = (int) $now->copy()->subDay()->isoWeekday();
            $today        = $now->toDateString();
            $yesterday    = $now->copy()->subDay()->toDateString();

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
                    'us.early_login_mins   as us_early_login_mins',
                    'us.early_clockin_mins as us_early_clockin_mins',
                    'us.grace_clockout_mins as us_grace_clockout_mins',
                    'ts.day_of_week',
                    'ts.days_mask',
                    'ts.start_time',
                    'ts.end_time',
                    'ts.end_next_day',
                    'ts.title',
                    'ts.early_login_mins   as ts_early_login_mins',
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
                ->orderBy('ts.start_time')
                ->get();

            if ($links->isEmpty()) {
                return $this->safeJsonResponse([
                    'success' => false,
                    'message' => 'You have no schedule assigned for today.',
                ], 403);
            }

            $maskHas = function (?int $mask, int $dow) {
                $mask = (int) ($mask ?? 0);
                if ($mask <= 0) return false;
                $bit = 1 << ($dow - 1);
                return ($mask & $bit) !== 0;
            };

            $resolveMins = function ($userVal, $tmplVal, $default = 5) {
                if (is_numeric($userVal)) return (int) $userVal;
                if (is_numeric($tmplVal)) return (int) $tmplVal;
                return (int) $default;
            };

            $match     = null;
            $win       = null;
            $effective = [
                'early_clockin_mins'  => null,
                'early_login_mins'    => null,
                'grace_clockout_mins' => null,
            ];

            $tooEarly = null;

            foreach ($links as $r) {
                $hasMask      = (int) ($r->days_mask ?? 0) > 0;
                $isEveryLegacy = ((int) $r->day_of_week) === 0;

                $effEarlyClockIn = $resolveMins($r->us_early_clockin_mins, $r->ts_early_clockin_mins, 5);
                $effEarlyLogin   = $resolveMins($r->us_early_login_mins,   $r->ts_early_login_mins,   0);
                $effGraceOut     = $resolveMins($r->us_grace_clockout_mins, $r->ts_grace_clockout_mins, 0);

                // ---- Case A: today-anchored window
                $todayMatchesDay =
                    ($hasMask && $maskHas((int) $r->days_mask, $dowToday)) ||
                    (!$hasMask && (((int) $r->day_of_week === $dowToday) || $isEveryLegacy));

                if ($todayMatchesDay) {
                    $start = Carbon::parse($today . ' ' . $r->start_time, $tz);
                    $end   = Carbon::parse($today . ' ' . $r->end_time,   $tz);
                    if ((int) $r->end_next_day === 1) $end->addDay();

                    $startWithEarly = $start->copy()->subMinutes($effEarlyClockIn);

                    if ($now->between($startWithEarly, $end, true)) {
                        $match = $r;
                        $win   = (object) ['start' => $start, 'startGrace' => $startWithEarly, 'end' => $end];
                        $effective['early_clockin_mins']  = $effEarlyClockIn;
                        $effective['early_login_mins']    = $effEarlyLogin;
                        $effective['grace_clockout_mins'] = $effGraceOut;
                        break;
                    } elseif ($now->lt($startWithEarly)) {
                        if (!$tooEarly || $startWithEarly->lt($tooEarly['allowedAt'])) {
                            $tooEarly = [
                                'title'     => $this->safeStr($r->title),
                                'allowedAt' => $startWithEarly,
                                'start'     => $start,
                                'end'       => $end,
                                'mins'      => $effEarlyClockIn,
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
                        $endY   = Carbon::parse($yesterday . ' ' . $r->end_time,   $tz)->addDay();

                        $startYWithEarly = $startY->copy()->subMinutes($effEarlyClockIn);

                        if ($now->between($startYWithEarly, $endY, true)) {
                            $match = $r;
                            $win   = (object) ['start' => $startY, 'startGrace' => $startYWithEarly, 'end' => $endY];
                            $effective['early_clockin_mins']  = $effEarlyClockIn;
                            $effective['early_login_mins']    = $effEarlyLogin;
                            $effective['grace_clockout_mins'] = $effGraceOut;
                            break;
                        }
                    }
                }
            }

            if (!$match) {
                if ($tooEarly) {
                    return $this->safeJsonResponse([
                        'success' => false,
                        'message' => 'Too early to clock in. Earliest allowed: ' .
                            $tooEarly['allowedAt']->format('h:i A') .
                            ' (LA). Your shift: ' . $tooEarly['start']->format('h:i A') .
                            ' – ' . $tooEarly['end']->format('h:i A') .
                            ($tooEarly['title'] ? (' • ' . $tooEarly['title']) : ''),
                        'meta' => [
                            'allowedAt' => $tooEarly['allowedAt']->toDateTimeString(),
                            'schedule'  => [
                                'start' => $tooEarly['start']->toDateTimeString(),
                                'end'   => $tooEarly['end']->toDateTimeString(),
                                'title' => $tooEarly['title'],
                            ],
                            'effective' => [
                                'early_clockin_mins' => $tooEarly['mins'],
                            ],
                        ],
                    ], 403);
                }

                return $this->safeJsonResponse([
                    'success' => false,
                    'message' => 'You are outside your scheduled window right now.',
                ], 403);
            }

            // Early/Late metrics
            $isEarly       = $now->lt($win->start) && $now->gte($win->startGrace);
            $earlyByMinutes = $isEarly ? $now->diffInMinutes($win->start, false) : 0;
            $isLate        = $now->gt($win->start);
            $lateByMinutes  = $isLate ? $win->start->diffInMinutes($now, false) : 0;

            // LA calendar only
            $day = $this->resolveDayStatusLA($now);

            // Sanitize the title before using in Notes
            $safeTitle = $this->safeStr($match->title ?? '');

            // Create clock-in
            DB::table('tblemployeeclocks')->insert([
                'userid'     => $uid,
                'Employee'   => $uname,
                'DateToday'  => $now->toDateString(),
                'TimeIn'     => $now,
                'day_status' => $day['status'],
                'holidayID'  => $day['holidayID'],
                'schedId'    => $match->schedId ?? null,
                'Notes'      => ($safeTitle ? ('Matched schedule: ' . $safeTitle . ' • ') : '')
                    . 'early_clockin_mins=' . $effective['early_clockin_mins'],
            ]);

            $this->userLogService->log('Clockin');
            $this->sendClockinMail($uname, $currentDatetimeStr, 'Clock In');

            // SMS
            $phone = '+19163705657';
            $smsResult = null;
            $smsData   = null;

            if ($phone) {
                $smsBody = "Hi {$uname} clocked in at " . $now->format('h:i A') . ' (LA). Test Value Rawr';
                $smsResult = $this->twilioService->sendSystemSms($phone, $smsBody);

                if ($smsResult !== null) {
                    $encoded = json_encode($smsResult, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                    $smsData = $encoded !== false ? json_decode($encoded, true) : null;
                }
            }

            return $this->safeJsonResponse([
                'success' => true,
                'message' => 'Clocked in at ' . $now->format('h:i A') . ' (LA) • ' . ($day['holidayTitle'] ?? ''),
                'meta'    => [
                    'holiday'  => $day['holidayTitle'] ?? '',
                    'date'     => $day['date'] ?? '',
                    'schedule' => [
                        'start'      => $win->start->toDateTimeString(),
                        'startGrace' => $win->startGrace->toDateTimeString(),
                        'end'        => $win->end->toDateTimeString(),
                        'title'      => $safeTitle,
                        'schedId'    => $match->schedId ?? null,
                    ],
                    'effective'      => $effective,
                    'early'          => $isEarly,
                    'earlyByMinutes' => $earlyByMinutes,
                    'late'           => $isLate,
                    'lateByMinutes'  => $lateByMinutes,
                    'Data'           => $smsData,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('ClockIn error: ' . $e->getMessage());

            return $this->safeJsonResponse([
                'success' => false,
                'message' => 'Clock-in failed: ' . $this->safeExceptionMessage($e),
            ], 500);
        }
    }

    // ============================================================
    // CLOCK OUT
    // ============================================================

    public function clockOut(Request $request)
    {
        try {
            $uid = Auth::id();
            $tz  = 'America/Los_Angeles';
            $now = Carbon::now($tz);

            // 1) Find the open clock-in
            $open = DB::table('tblemployeeclocks')
                ->where('userid', $uid)
                ->whereNotNull('TimeIn')
                ->whereNull('TimeOut')
                ->orderByDesc('ID')
                ->first();

            if (!$open) {
                return $this->safeJsonResponse([
                    'success' => false,
                    'message' => 'No open clock-in record found.',
                ], 400);
            }

            $timeIn     = Carbon::parse($open->TimeIn, $tz);
            $anchorDate = $timeIn->toDateString();
            $dowAnchor  = (int) $timeIn->isoWeekday();

            $maskHas = function ($mask, $dow) {
                $m = (int) ($mask ?? 0);
                return $m > 0 && (($m & (1 << ($dow - 1))) !== 0);
            };

            $resolveMins = function ($userVal, $tmplVal, $default = 0) {
                if (is_numeric($userVal)) return (int) $userVal;
                if (is_numeric($tmplVal)) return (int) $tmplVal;
                return (int) $default;
            };

            // 2) Load links effective on the anchor date
            $links = DB::table('tblusersched as us')
                ->join('tbltimesched as ts', 'ts.timeschedId', '=', 'us.schedId')
                ->select(
                    'us.userschedId',
                    'us.userId',
                    'us.schedId',
                    'us.effective_from',
                    'us.effective_to',
                    'us.is_active',
                    'us.early_login_mins   as us_early_login_mins',
                    'us.early_clockin_mins as us_early_clockin_mins',
                    'us.grace_clockout_mins as us_grace_clockout_mins',
                    'ts.timeschedId',
                    'ts.day_of_week',
                    'ts.days_mask',
                    'ts.start_time',
                    'ts.end_time',
                    'ts.end_next_day',
                    'ts.title',
                    'ts.early_login_mins   as ts_early_login_mins',
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
            $matchedSched = null;
            $effective    = [
                'early_login_mins'    => null,
                'early_clockin_mins'  => null,
                'grace_clockout_mins' => null,
            ];

            if (!empty($open->schedId)) {
                $linkForId = $links->first(fn($r) => (int) $r->schedId === (int) $open->schedId);
                if ($linkForId) {
                    $start = Carbon::parse($anchorDate . ' ' . $linkForId->start_time, $tz);
                    $end   = Carbon::parse($anchorDate . ' ' . $linkForId->end_time,   $tz);
                    if ((int) $linkForId->end_next_day === 1) $end->addDay();

                    $matchedSched = (object) [
                        'start' => $start,
                        'end'   => $end,
                        'title' => $this->safeStr($linkForId->title),
                        'id'    => $linkForId->schedId,
                    ];

                    $effective['early_login_mins']    = $resolveMins($linkForId->us_early_login_mins,   $linkForId->ts_early_login_mins,   0);
                    $effective['early_clockin_mins']  = $resolveMins($linkForId->us_early_clockin_mins, $linkForId->ts_early_clockin_mins, 5);
                    $effective['grace_clockout_mins'] = $resolveMins($linkForId->us_grace_clockout_mins, $linkForId->ts_grace_clockout_mins, 180);
                } else {
                    $sched = DB::table('tbltimesched')->where('timeschedId', $open->schedId)->first();
                    if ($sched) {
                        $start = Carbon::parse($anchorDate . ' ' . $sched->start_time, $tz);
                        $end   = Carbon::parse($anchorDate . ' ' . $sched->end_time,   $tz);
                        if ((int) $sched->end_next_day === 1) $end->addDay();

                        $matchedSched = (object) [
                            'start' => $start,
                            'end'   => $end,
                            'title' => $this->safeStr($sched->title),
                            'id'    => $sched->timeschedId,
                        ];

                        $effective['early_login_mins']    = is_numeric($sched->early_login_mins)    ? (int) $sched->early_login_mins    : 0;
                        $effective['early_clockin_mins']  = is_numeric($sched->early_clockin_mins)  ? (int) $sched->early_clockin_mins  : 5;
                        $effective['grace_clockout_mins'] = is_numeric($sched->grace_clockout_mins) ? (int) $sched->grace_clockout_mins : 180;
                    }
                }
            }

            // Fallback: infer schedule by day/mask
            if (!$matchedSched) {
                foreach ($links as $r) {
                    $hasMask       = ((int) ($r->days_mask ?? 0) > 0);
                    $isEveryLegacy = ((int) $r->day_of_week) === 0;

                    $dayOk = ($hasMask && $maskHas($r->days_mask, $dowAnchor))
                        || (!$hasMask && ($isEveryLegacy || (int) $r->day_of_week === $dowAnchor));
                    if (!$dayOk) continue;

                    $schedStart = Carbon::parse($anchorDate . ' ' . $r->start_time, $tz);
                    $schedEnd   = Carbon::parse($anchorDate . ' ' . $r->end_time,   $tz);
                    if ((int) $r->end_next_day === 1) $schedEnd->addDay();

                    if ($timeIn->between($schedStart, $schedEnd, true)) {
                        $matchedSched = (object) [
                            'start' => $schedStart,
                            'end'   => $schedEnd,
                            'title' => $this->safeStr($r->title),
                            'id'    => $r->schedId,
                        ];
                        $effective['early_login_mins']    = $resolveMins($r->us_early_login_mins,   $r->ts_early_login_mins,   0);
                        $effective['early_clockin_mins']  = $resolveMins($r->us_early_clockin_mins, $r->ts_early_clockin_mins, 5);
                        $effective['grace_clockout_mins'] = $resolveMins($r->us_grace_clockout_mins, $r->ts_grace_clockout_mins, 180);
                        break;
                    }
                }
            }

            // 4) Compute grace, caps, early/OT info
            $GRACE_DEFAULT = 180;
            $effGrace      = $matchedSched ? ($effective['grace_clockout_mins'] ?? $GRACE_DEFAULT) : $GRACE_DEFAULT;

            $earlyOutMins = null;
            $overTimeMins = null;
            $autoCutoff   = null;

            if ($matchedSched) {
                $delta = $now->diffInMinutes($matchedSched->end, false);
                if ($delta > 0)       $earlyOutMins = $delta;
                elseif ($delta < 0)   $overTimeMins = abs($delta);
                $autoCutoff = $matchedSched->end->copy()->addMinutes($effGrace);
            }

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

            // 6) Build notes and update
            $notes = [];

            if ($matchedSched) {
                if ($earlyOutMins !== null && $earlyOutMins > 0)
                    $notes[] = "Early clock-out ({$earlyOutMins} min before scheduled end)";
                if ($overTimeMins !== null && $overTimeMins > 0)
                    $notes[] = "Overtime (+{$overTimeMins} min beyond scheduled end)";
                if ($isAuto)
                    $notes[] = "IMS: Auto Clockout (exceeded scheduled window + {$effGrace} min grace)";
            } else {
                if ($isAuto)
                    $notes[] = "IMS: Auto Clockout (no schedule; exceeded hard cap {$HARD_MAX_SHIFT_MINUTES} min)";
            }

            $update = ['TimeOut' => $now];

            if (!empty($notes)) {
                $joined = implode(' • ', $notes);
                // Sanitize before inserting into DB
                $joined = $this->safeStr($joined);
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

            $uname              = $this->safeStr(Auth::user()->username);
            $currentDatetimeStr = $now->format('Y-m-d H:i:s');
            $this->sendClockinMail($uname, $currentDatetimeStr, 'Clock Out');

            return $this->safeJsonResponse([
                'success' => true,
                'message' => 'Clocked out at ' . $now->format('h:i A') . ' (LA)' . ($isAuto ? ' • Auto-clockout noted' : ''),
                'meta'    => [
                    'scheduledStart'    => $matchedSched ? $matchedSched->start->toDateTimeString() : null,
                    'scheduledEnd'      => $matchedSched ? $matchedSched->end->toDateTimeString()   : null,
                    'effective'         => $effective,
                    'graceMinutesUsed'  => $effGrace,
                    'hardMaxMinutes'    => $HARD_MAX_SHIFT_MINUTES,
                    'earlyOutMins'      => $earlyOutMins,
                    'overTimeMins'      => $overTimeMins,
                    'auto'              => $isAuto,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('ClockOut error: ' . $e->getMessage());

            return $this->safeJsonResponse([
                'success' => false,
                'message' => 'Clock-out failed: ' . $this->safeExceptionMessage($e),
            ], 500);
        }
    }

    // ============================================================
    // AUTO CLOCK OUT
    // ============================================================

    public function autoClockOut(Request $request)
    {
        try {
            $userId = Auth::id();
            $tz = 'America/Los_Angeles';
            $now = Carbon::now($tz);

            $validated = $request->validate([
                'last_clock_in' => 'required|date',
            ]);

            $lastClockInDate = Carbon::parse($validated['last_clock_in'], $tz);

            $openRecord = DB::table('tblemployeeclocks')
                ->where('userid', $userId)
                ->whereNull('TimeOut')
                ->whereDate('TimeIn', $lastClockInDate->toDateString())
                ->orderBy('ID', 'desc')
                ->first();

            if (!$openRecord) {
                return $this->safeJsonResponse([
                    'success' => false,
                    'message' => 'No open clock-in record found for the specified date.',
                ], 404);
            }

            $timeIn = Carbon::parse($openRecord->TimeIn, $tz);

            $timeInDate = $timeIn->copy()->startOfDay();
            $todayDate = $now->copy()->startOfDay();

            if ($timeInDate->greaterThanOrEqualTo($todayDate)) {
                return $this->safeJsonResponse([
                    'success' => false,
                    'message' => 'The clock-in record is from today. Auto clock-out only applies to previous days.',
                ], 400);
            }

            $autoClockOutTime = $timeIn->copy();

            $autoNote = 'IMS: Auto Clock-out applied. TimeOut set to match TimeIn at ' . $autoClockOutTime->format('Y-m-d h:i A');

            $existingNotes = $this->safeStr($openRecord->Notes ?? '');
            $existingSystemNotes = $this->safeStr($openRecord->systemNotes ?? '');

            $newNotes = $existingNotes
                ? $existingNotes . ' | ' . $autoNote
                : $autoNote;

            $newSystemNotes = $existingSystemNotes
                ? $existingSystemNotes . "\n" . $autoNote
                : $autoNote;

            DB::table('tblemployeeclocks')
                ->where('ID', $openRecord->ID)
                ->update([
                    'TimeOut' => $autoClockOutTime,
                    'Notes' => $newNotes,
                    'systemNotes' => $newSystemNotes,
                ]);

            $this->userLogService->log('Auto Clockout: Record ID ' . $openRecord->ID . ' from ' . $timeIn->format('Y-m-d'));

            $uname = $this->safeStr(Auth::user()->username);
            $this->sendClockinMail($uname, $autoClockOutTime->format('Y-m-d H:i:s'), 'Auto Clock Out');

            return $this->safeJsonResponse([
                'success' => true,
                'message' => 'Successfully auto-clocked out from previous day.',
                'time_in' => $timeIn->toIso8601String(),
                'time_out' => $autoClockOutTime->toIso8601String(),
                'date' => $timeIn->toDateString(),
                'attendance_id' => $openRecord->ID,
            ]);

        } catch (\Exception $e) {
            \Log::error('AutoClockOut error: ' . $e->getMessage());

            return $this->safeJsonResponse([
                'success' => false,
                'message' => 'Auto clock-out failed: ' . $this->safeExceptionMessage($e),
            ], 500);
        }
    }

    // ============================================================
    // COMPUTED HOURS / UPDATE HOURS
    // ============================================================

    public function updateComputedHours(Request $request)
    {
        $timeIn = Carbon::parse($request->timeIn)->setTimezone('America/Los_Angeles');
        $timeOut = $request->timeOut
            ? Carbon::parse($request->timeOut)->setTimezone('America/Los_Angeles')
            : now()->setTimezone('America/Los_Angeles')->subHours(8);

        $totalMinutes = $timeIn->diffInMinutes($timeOut);
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return $this->safeJsonResponse([
            'hours' => $hours,
            'minutes' => $minutes,
            'message' => !$request->timeOut ? 'Calculated until now' : null,
        ]);
    }

    public function updateHours()
    {
        $currentUserId = Auth::user()->id;

        $todayHours = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereDate('TimeIn', Carbon::today('America/Los_Angeles'))
            ->sum(DB::raw('
                TIMESTAMPDIFF(MINUTE, TimeIn, COALESCE(TimeOut, DATE_SUB(NOW(), INTERVAL 8 HOUR)))
            '));

        $weekHours = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereBetween('TimeIn', [
                Carbon::now('America/Los_Angeles')->startOfWeek(),
                Carbon::now('America/Los_Angeles')->endOfWeek(),
            ])
            ->sum(DB::raw('
                TIMESTAMPDIFF(MINUTE, TimeIn, COALESCE(TimeOut, DATE_SUB(NOW(), INTERVAL 8 HOUR)))
            '));

        $todayHoursFormatted = sprintf('%d hrs %02d mins', intdiv($todayHours, 60), $todayHours % 60);
        $weekHoursFormatted = sprintf('%d hrs %02d mins', intdiv($weekHours, 60), $weekHours % 60);

        return $this->safeJsonResponse([
            'todayHours' => $todayHoursFormatted,
            'weekHours' => $weekHoursFormatted,
        ]);
    }

    // ============================================================
    // FILTER ATTENDANCE (AJAX)
    // ============================================================

    public function filterAttendanceAjax(Request $request)
    {
        $currentUserId = Auth::user()->id;

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

        if ($startDate) {
            $query->whereDate('tblemployeeclocks.TimeIn', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('tblemployeeclocks.TimeIn', '<=', $endDate);
        }

        $employeeClocks = $query->limit(10)->get();

        return $this->safeJsonResponse([
            'employeeClocks' => $employeeClocks,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    // ============================================================
    // UPDATE NOTES
    // ============================================================

    public function updateNotes(Request $request, $id)
    {
        $validatedData = $request->validate([
            'notes' => 'required|string|max:255',
        ]);

        $updated = DB::table('tblemployeeclocks')
            ->where('ID', $id)
            ->update(['Notes' => $validatedData['notes']]);

        if ($updated) {
            $this->userLogService->log('Save user time clock notes');

            return $this->safeJsonResponse([
                'success' => true,
                'message' => 'Notes updated successfully.',
            ]);
        } else {
            return $this->safeJsonResponse([
                'success' => false,
                'message' => 'Failed to update notes.',
            ]);
        }
    }

    // ============================================================
    // BREAK SYSTEM CONSTANTS & HELPERS
    // ============================================================

    private const LA_TZ = 'America/Los_Angeles';

    private function getOpenClock(int $userId)
    {
        return DB::table('tblemployeeclocks')
            ->where('userid', $userId)
            ->whereNull('TimeOut')
            ->orderByDesc('TimeIn')
            ->first();
    }

    private function getOpenClockForUpdate(int $userId)
    {
        return DB::table('tblemployeeclocks')
            ->where('userid', $userId)
            ->whereNull('TimeOut')
            ->orderByDesc('TimeIn')
            ->lockForUpdate()
            ->first();
    }

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

        if (!$link) return 0;

        $ts = DB::table('tbltimesched')->where('timeschedId', $link->schedId)->first();
        if (!$ts) return 0;

        $dow = (int) $shiftDateLA->dayOfWeekIso;
        if ((int) $ts->day_of_week !== 0 && (int) $ts->day_of_week !== $dow) {
            return 0;
        }

        return (int) $ts->unpaid_break_minutes;
    }

    private function currentBreakElapsedSeconds($clock, Carbon $nowLA): int
    {
        if (!$clock || !$clock->shortbreak_start || ($clock->shortbreak_status ?? null) !== 'on_break') {
            return 0;
        }
        $start = Carbon::parse($clock->shortbreak_start, self::LA_TZ);
        return max(0, $start->diffInSeconds($nowLA));
    }

    // ============================================================
    // BREAK STATUS
    // ============================================================

    public function status(Request $request)
    {
        try {
            $userId = Auth::id();
            $nowLA = Carbon::now(self::LA_TZ);

            $clock = $this->getOpenClock($userId);
            if (!$clock) {
                return $this->safeJsonResponse([
                    'hasOpenClock' => false,
                    'message' => 'No open shift.',
                ]);
            }

            // Break computation
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
                    if (!$row) return;

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

                $clock = $this->getOpenClock($userId);
                $usedMinutes = (float) ($clock->shortbreak_totaltime ?? 0.0);
                $elapsedMin = 0.0;
            }

            // Auto-clockout guardrail
            $NOTIFY_BEFORE_MIN = 30;
            $GRACE_MIN_AFTER = 180;

            $timeIn = Carbon::parse($clock->TimeIn, self::LA_TZ);
            $anchorDay = (int) $timeIn->isoWeekday();
            $anchorDateStr = $timeIn->toDateString();

            $links = DB::table('tblusersched as us')
                ->join('tbltimesched as ts', 'ts.timeschedId', '=', 'us.schedId')
                ->select(
                    'us.userschedId', 'us.userId', 'us.effective_from', 'us.effective_to', 'us.is_active',
                    'ts.timeschedId', 'ts.day_of_week', 'ts.days_mask',
                    'ts.start_time', 'ts.end_time', 'ts.end_next_day', 'ts.title'
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

            $match = null;

            foreach ($links as $r) {
                $hasMask = ((int) ($r->days_mask ?? 0) > 0);
                $isEveryLegacy = ((int) $r->day_of_week) === 0;

                $dayOk = ($hasMask && $maskHas($r->days_mask, $anchorDay))
                    || (!$hasMask && ($isEveryLegacy || (int) $r->day_of_week === $anchorDay));

                if (!$dayOk) continue;

                $schedStart = Carbon::parse($anchorDateStr . ' ' . $r->start_time, self::LA_TZ);
                $schedEnd = Carbon::parse($anchorDateStr . ' ' . $r->end_time, self::LA_TZ);
                if ((int) $r->end_next_day === 1) $schedEnd->addDay();

                if ($timeIn->between($schedStart, $schedEnd, true)) {
                    $match = (object) [
                        'start' => $schedStart,
                        'end' => $schedEnd,
                        'title' => $this->safeStr($r->title),
                        'timeschedId' => $r->timeschedId ?? null,
                    ];
                    break;
                }
            }

            if ($match) {
                $scheduledDurationMin = max(1, (int) $match->start->diffInMinutes($match->end));
            } else {
                $scheduledDurationMin = 8 * 60;
            }

            $capMoment = ($match ? $match->end->copy() : $timeIn->copy()->addMinutes($scheduledDurationMin))
                ->addMinutes($GRACE_MIN_AFTER);

            $minsToCap = (int) ceil(($capMoment->getTimestamp() - $nowLA->getTimestamp()) / 60);

            // Heads-up notification
            if ($minsToCap > 0 && $minsToCap <= $NOTIFY_BEFORE_MIN) {
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
                            'modal_id' => 'announcement-view',
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

            // Auto-clockout at/after cap
            $autoClockedOut = false;
            if ($minsToCap <= 0) {
                $safeNote = $this->safeStr('IMS: Auto Clockout (status watchdog)');
                $quotedNote = DB::getPdo()->quote($safeNote);

                $update = [
                    'TimeOut' => $nowLA,
                    'systemNotes' => DB::raw(
                        "CASE WHEN systemNotes IS NULL OR systemNotes = '' 
                          THEN {$quotedNote}
                          ELSE CONCAT(systemNotes, '\n', {$quotedNote})
                     END"
                    ),
                ];

                DB::table('tblemployeeclocks')->where('ID', $clock->ID)->update($update);
                $this->userLogService->log('Clockout');
                $autoClockedOut = true;

                $clock = $this->getOpenClock($userId);
            }

            return $this->safeJsonResponse([
                'hasOpenClock' => !$autoClockedOut,
                'status' => $clock ? ($clock->shortbreak_status ?? 'idle') : 'idle',
                'allowedMin' => (float) $allowed,
                'usedMin' => (float) $usedMinutes + $elapsedMin,
                'remainingMin' => max(0.0, (float) $allowed - ((float) $usedMinutes + $elapsedMin)),
                'onBreakSince' => $clock->shortbreak_start ?? null,
                'lastBreakEnd' => $clock->shortbreak_end ?? null,
                'serverNow' => $nowLA->toIso8601String(),
                'autoClockout' => $autoClockedOut,
                'capAt' => $capMoment->toIso8601String(),
                'minsToCap' => $minsToCap,
                'schedWindow' => $match ? [
                    'start' => $match->start->toIso8601String(),
                    'end' => $match->end->toIso8601String(),
                    'title' => $match->title,
                ] : null,
            ]);

        } catch (\Exception $e) {
            \Log::error('Status error: ' . $e->getMessage());

            return $this->safeJsonResponse([
                'success' => false,
                'message' => 'Status check failed: ' . $this->safeExceptionMessage($e),
            ], 500);
        }
    }

    // ============================================================
    // BREAK START
    // ============================================================

    public function start(Request $request)
    {
        $userId = Auth::id();

        return DB::transaction(function () use ($userId) {
            $nowLA = Carbon::now(self::LA_TZ);
            $row = $this->getOpenClockForUpdate($userId);
            if (!$row) {
                return $this->safeJsonResponse(['error' => 'No open shift.'], 422);
            }

            if (($row->shortbreak_status ?? null) === 'on_break') {
                return $this->safeJsonResponse(['error' => 'Already on break.'], 409);
            }

            $shiftDateLA = Carbon::parse($row->DateToday, self::LA_TZ);
            $allowed = $this->resolveAllowedBreakMinutes($userId, $shiftDateLA);
            $used = (float) ($row->shortbreak_totaltime ?? 0.0);

            if ($used >= $allowed) {
                return $this->safeJsonResponse(['error' => 'No break time remaining.'], 409);
            }

            DB::table('tblemployeeclocks')->where('ID', $row->ID)->update([
                'shortbreak_start' => $nowLA,
                'shortbreak_status' => 'on_break',
            ]);

            return $this->safeJsonResponse(['ok' => true]);
        });
    }

    // ============================================================
    // BREAK END
    // ============================================================

    public function end(Request $request)
    {
        $userId = Auth::id();

        return DB::transaction(function () use ($userId) {
            $nowLA = Carbon::now(self::LA_TZ);
            $row = $this->getOpenClockForUpdate($userId);
            if (!$row) {
                return $this->safeJsonResponse(['error' => 'No open shift.'], 422);
            }

            if (($row->shortbreak_status ?? null) !== 'on_break' || !$row->shortbreak_start) {
                return $this->safeJsonResponse(['error' => 'Not currently on break.'], 409);
            }

            $shiftDateLA = Carbon::parse($row->DateToday, self::LA_TZ);
            $allowed = $this->resolveAllowedBreakMinutes($userId, $shiftDateLA);

            $priorMin = (float) ($row->shortbreak_totaltime ?? 0.0);
            $elapsedSec = max(0, Carbon::parse($row->shortbreak_start, self::LA_TZ)->diffInSeconds($nowLA));
            $elapsedMin = $elapsedSec / 60.0;

            $newTotal = $priorMin + $elapsedMin;
            $clamped = min($newTotal, (float) $allowed);

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

            return $this->safeJsonResponse(['ok' => true]);
        });
    }

    // ============================================================
    // SCHEDULE MONTH
    // ============================================================

    public function month(Request $req)
    {
        try {
            $userId = Auth::id();
            $ym = $req->query('ym');
            if (!$ym || !preg_match('/^\d{4}-\d{2}$/', $ym)) {
                return $this->safeJsonResponse(['error' => 'Invalid ym'], 400);
            }

            [$year, $month] = array_map('intval', explode('-', $ym));
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = (clone $start)->endOfMonth()->endOfDay();

            $now = Carbon::now();
            $todayDate = $now->toDateString();
            $LATE_GRACE_MINUTES = 5;

            $earliestClockDate = DB::table('tblemployeeclocks')
                ->where('userid', $userId)
                ->selectRaw('MIN(COALESCE(DateToday, DATE(TimeIn))) as d')
                ->value('d');

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
                        'title',
                    ]);
                foreach ($blocks as $b) {
                    $bySched[$b->schedId] = $b;
                }
            }

            $rawHolidays = DB::table('tblholiday')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('holidate', [$start->toDateString(), $end->toDateString()])
                        ->orWhere('is_recurring', 1);
                })
                ->get(['holidate', 'status', 'title', 'is_recurring']);

            $holidayAbs = [];
            $holidayByMD = [];
            foreach ($rawHolidays as $h) {
                if ($h->is_recurring) {
                    $md = Carbon::parse($h->holidate)->format('m-d');
                    $holidayByMD[$md][] = [
                        'date' => $this->safeStr($h->holidate),
                        'status' => $this->safeStr($h->status),
                        'title' => $this->safeStr($h->title),
                        'recurring' => true,
                    ];
                } else {
                    $iso = Carbon::parse($h->holidate)->toDateString();
                    $holidayAbs[$iso][] = [
                        'date' => $iso,
                        'status' => $this->safeStr($h->status),
                        'title' => $this->safeStr($h->title),
                        'recurring' => false,
                    ];
                }
            }

            $firstIns = DB::table('tblemployeeclocks')
                ->where('userid', $userId)
                ->whereBetween(
                    DB::raw('COALESCE(DateToday, DATE(TimeIn))'),
                    [$start->toDateString(), $end->toDateString()]
                )
                ->selectRaw('COALESCE(DateToday, DATE(TimeIn)) as d, MIN(TimeIn) as first_in')
                ->groupBy('d')
                ->pluck('first_in', 'd');

            $byDate = [];
            $bitByIsoDow = [1 => 1, 2 => 2, 3 => 4, 4 => 8, 5 => 16, 6 => 32, 7 => 64];

            foreach (\Carbon\CarbonPeriod::create($start, '1 day', $end) as $d) {
                $iso = $d->toDateString();
                $md = $d->format('m-d');
                $bit = $bitByIsoDow[$d->isoWeekday()];

                $hols = array_merge($holidayAbs[$iso] ?? [], $holidayByMD[$md] ?? []);
                $holiday_full = $hols
                    ? ('Holiday: ' . implode(' / ', array_map(
                        fn ($h) => ($h['status'] ? ($h['status'] . ': ') : '') . $h['title'],
                        $hols
                    )))
                    : '';

                $active = $links->first(function ($lnk) use ($d) {
                    $fromOk = !$lnk->effective_from || Carbon::parse($lnk->effective_from)->startOfDay() <= $d;
                    $toOk = !$lnk->effective_to || Carbon::parse($lnk->effective_to)->endOfDay() >= $d;
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

                        $start12 = Carbon::createFromFormat('H:i:s', (string) $row->start_time)->format('g:i A');
                        $end12 = Carbon::createFromFormat('H:i:s', (string) $row->end_time)->format('g:i A');
                        $end12 .= ((int) $row->end_next_day === 1) ? ' (+1)' : '';

                        $entries[] = [
                            'start' => $start12,
                            'end' => $end12,
                            'name' => $this->safeStr($row->title ?: 'Shift'),
                            'notes' => '',
                            'next_day' => (bool) $row->end_next_day,
                        ];

                        $scheduledStartDT = Carbon::parse($iso . ' ' . $row->start_time);
                        $scheduledEndDT = Carbon::parse($iso . ' ' . $row->end_time);
                        if ((int) $row->end_next_day === 1) {
                            $scheduledEndDT->addDay();
                        }
                    }
                }

                $timeLabel = '—';
                if ($entries) {
                    $timeLabel = count($entries) === 1
                        ? ($entries[0]['start'] . '–' . $entries[0]['end'])
                        : (count($entries) . ' shifts');
                }
                $label = $timeLabel;

                $status = null;
                $firstIn = isset($firstIns[$iso]) ? Carbon::parse($firstIns[$iso]) : null;
                $isFutureDay = $d->gt($todayDate);
                $isBeforeEarliest = $earliestClockDate ? $d->lt(Carbon::parse($earliestClockDate)) : false;
                $isToday = $iso === $todayDate;

                if ($isScheduledDay) {
                    if ($firstIn) {
                        $lateThreshold = $scheduledStartDT ? $scheduledStartDT->copy()->addMinutes($LATE_GRACE_MINUTES) : null;
                        $status = ($lateThreshold && $firstIn->greaterThan($lateThreshold)) ? 'late' : 'present';
                    } else {
                        if ($isFutureDay) {
                            $status = null;
                        } elseif ($isBeforeEarliest) {
                            $status = null;
                        } elseif ($isToday && $scheduledStartDT && $scheduledEndDT) {
                            if ($now->lt($scheduledStartDT)) {
                                $status = null;
                            } elseif ($now->between($scheduledStartDT, $scheduledEndDT)) {
                                $status = 'late';
                            } else {
                                $status = 'absent';
                            }
                        } else {
                            $status = 'absent';
                        }
                    }
                } else {
                    if ($firstIn) {
                        $status = 'present';
                    } else {
                        $status = null;
                    }
                }

                $byDate[$iso] = [
                    'label' => $label,
                    'holiday_full' => $this->safeStr($holiday_full),
                    'holidays' => $hols,
                    'entries' => $entries,
                    'status' => $status,
                ];
            }

            return $this->safeJsonResponse(['ym' => $ym, 'byDate' => $byDate]);

        } catch (\Exception $e) {
            \Log::error('Month schedule error: ' . $e->getMessage());

            return $this->safeJsonResponse([
                'success' => false,
                'message' => 'Failed to load schedule: ' . $this->safeExceptionMessage($e),
            ], 500);
        }
    }

    // ============================================================
    // GET PROFILE DATA (API)
    // ============================================================

    public function getProfileData(Request $request)
    {
        try {
            $currentUserId = Auth::id();
            $tz = 'America/Los_Angeles';
            $now = Carbon::now($tz);

            $employeeClocksThisweek = DB::table('tblemployeeclocks')
                ->join('tbluser', 'tblemployeeclocks.userid', '=', 'tbluser.id')
                ->select(
                    'tblemployeeclocks.ID as id',
                    'tblemployeeclocks.userid',
                    'tblemployeeclocks.Employee',
                    'tblemployeeclocks.TimeIn as timeIn',
                    'tblemployeeclocks.TimeOut as timeOut',
                    'tblemployeeclocks.Notes as notes',
                    'tbluser.username'
                )
                ->where('tblemployeeclocks.userid', $currentUserId)
                ->whereBetween('tblemployeeclocks.TimeIn', [
                    Carbon::now($tz)->startOfWeek(),
                    Carbon::now($tz)->endOfWeek(),
                ])
                ->orderBy('tblemployeeclocks.TimeIn', 'desc')
                ->get();

            $lastRecord = DB::table('tblemployeeclocks')
                ->where('userid', $currentUserId)
                ->orderBy('ID', 'desc')
                ->first();

            $verylastRecord = DB::table('tblemployeeclocks')
                ->where('userid', $currentUserId)
                ->orderBy('ID', 'desc')
                ->first();

            $todayHours = DB::table('tblemployeeclocks')
                ->where('userid', $currentUserId)
                ->whereDate('TimeIn', Carbon::today($tz))
                ->sum(DB::raw('
                    TIMESTAMPDIFF(MINUTE, TimeIn, COALESCE(TimeOut, DATE_SUB(NOW(), INTERVAL 8 HOUR)))
                '));

            $weekHours = DB::table('tblemployeeclocks')
                ->where('userid', $currentUserId)
                ->whereBetween('TimeIn', [
                    Carbon::now($tz)->startOfWeek(),
                    Carbon::now($tz)->endOfWeek(),
                ])
                ->sum(DB::raw('
                    TIMESTAMPDIFF(MINUTE, TimeIn, COALESCE(TimeOut, DATE_SUB(NOW(), INTERVAL 8 HOUR)))
                '));

            $todayHoursFormatted = sprintf('%d hrs %02d mins', intdiv($todayHours, 60), $todayHours % 60);
            $weekHoursFormatted = sprintf('%d hrs %02d mins', intdiv($weekHours, 60), $weekHours % 60);

            $canClockIn = !$lastRecord || ($lastRecord && $lastRecord->TimeIn && $lastRecord->TimeOut);
            $canClockOut = $lastRecord && $lastRecord->TimeIn && !$lastRecord->TimeOut;

            $hasPreviousDayOpenRecord = false;
            if ($verylastRecord && $verylastRecord->TimeIn && !$verylastRecord->TimeOut) {
                $lastTimeIn = Carbon::parse($verylastRecord->TimeIn, $tz);
                $lastTimeInDate = $lastTimeIn->copy()->startOfDay();
                $todayDate = $now->copy()->startOfDay();

                if ($lastTimeInDate->lessThan($todayDate)) {
                    $hasPreviousDayOpenRecord = true;
                }
            }

            return $this->safeJsonResponse([
                'records' => $employeeClocksThisweek,
                'todayHours' => $todayHoursFormatted,
                'weekHours' => $weekHoursFormatted,
                'lastRecordTimeIn' => $verylastRecord ? Carbon::parse($verylastRecord->TimeIn)->toIso8601String() : '',
                'canClockIn' => $canClockIn,
                'canClockOut' => $canClockOut,
                'hasPreviousDayOpenRecord' => $hasPreviousDayOpenRecord,
            ]);

        } catch (\Exception $e) {
            \Log::error('GetProfileData error: ' . $e->getMessage());

            return $this->safeJsonResponse([
                'success' => false,
                'message' => 'Failed to load profile data: ' . $this->safeExceptionMessage($e),
            ], 500);
        }
    }

    // ============================================================
    // GET ALL USERS ATTENDANCE TODAY
    // ============================================================

    public function getAllUsersAttendanceToday(Request $request)
    {
        try {
            $validated = $request->validate([
                'status' => 'nullable|string|in:clocked_in,clocked_out,absent,',
                'account' => 'nullable|string|in:PH,US,',
            ]);

            $tz = 'America/Los_Angeles';
            $todayUS = Carbon::today($tz)->toDateString();

            $query = DB::table('tblemployeeclocks')
                ->join('tbluser', 'tblemployeeclocks.userid', '=', 'tbluser.id')
                ->select(
                    'tblemployeeclocks.ID as id',
                    'tblemployeeclocks.userid',
                    'tblemployeeclocks.Employee as employee_name',
                    'tblemployeeclocks.TimeIn as time_in',
                    'tblemployeeclocks.TimeOut as time_out',
                    'tblemployeeclocks.Notes as notes',
                    'tblemployeeclocks.schedID as scheduleid',
                    'tblemployeeclocks.DateToday as date_today',
                    'tbluser.username',
                    'tbluser.accounttype',
                    'tbluser.profile_picture',
                    DB::raw('TIMESTAMPDIFF(MINUTE, tblemployeeclocks.TimeIn, tblemployeeclocks.TimeOut) as duration_minutes'),
                    DB::raw('DATE(tblemployeeclocks.TimeIn) as date')
                )
                ->whereRaw('DATE(tblemployeeclocks.TimeIn) = ?', [$todayUS]);

            if (!empty($validated['account'])) {
                $query->where('tbluser.accounttype', $validated['account']);
            }

            $attendanceRecords = $query->orderBy('tblemployeeclocks.TimeIn', 'desc')->get();

            $formattedData = $attendanceRecords->map(function ($record) use ($tz) {
                $timeIn = $record->time_in ? Carbon::parse($record->time_in, 'UTC')->timezone($tz) : null;
                $timeOut = $record->time_out ? Carbon::parse($record->time_out, 'UTC')->timezone($tz) : null;

                $status = 'clocked_out';
                if ($timeIn && !$timeOut) {
                    $status = 'clocked_in';
                } elseif (!$timeIn) {
                    $status = 'absent';
                }

                $duration = null;
                $durationFormatted = null;

                if ($timeIn && $timeOut && $record->duration_minutes) {
                    $duration = $record->duration_minutes;
                    $hours = floor($duration / 60);
                    $minutes = $duration % 60;

                    if ($hours > 0 && $minutes > 0) {
                        $durationFormatted = "{$hours}h {$minutes}m";
                    } elseif ($hours > 0) {
                        $durationFormatted = "{$hours}h";
                    } else {
                        $durationFormatted = "{$minutes}m";
                    }
                }

                return [
                    'id' => $record->id,
                    'userid' => $record->userid,
                    'employee_name' => $this->safeStr($record->employee_name),
                    'username' => $this->safeStr($record->username),
                    'profile_picture' => $record->profile_picture,
                    'accounttype' => $record->accounttype,
                    'scheduleid' => $record->scheduleid,
                    'time_in' => $record->time_in,
                    'time_out' => $record->time_out,
                    'time_in_full' => $timeIn ? $timeIn->format('m-d-Y H:i:s') : null,
                    'time_out_full' => $timeOut ? $timeOut->format('m-d-Y H:i:s') : null,
                    'duration_minutes' => $duration,
                    'duration' => $durationFormatted,
                    'notes' => $this->safeStr($record->notes),
                    'status' => $status,
                    'date' => $record->date,
                    'date_today' => $record->date_today,
                ];
            });

            $latestRecordsByUser = $formattedData->groupBy('userid')->map(function ($userRecords) {
                return $userRecords->first();
            });

            $currentlyClockedInCount = $latestRecordsByUser->where('status', 'clocked_in')->count();
            $currentlyClockedOutCount = $latestRecordsByUser->where('status', 'clocked_out')->count();

            if (!empty($validated['status'])) {
                $formattedData = $formattedData->where('status', $validated['status']);
            }

            return $this->safeJsonResponse([
                'success' => true,
                'data' => [
                    'records' => $formattedData->values(),
                    'summary' => [
                        'total' => $latestRecordsByUser->count(),
                        'clocked_in' => $currentlyClockedInCount,
                        'clocked_out' => $currentlyClockedOutCount,
                    ],
                ],
                'message' => "Today's US Attendance ($todayUS) retrieved successfully",
            ]);

        } catch (\Exception $e) {
            Log::error('Attendance Error: ' . $e->getMessage());

            return $this->safeJsonResponse([
                'success' => false,
                'message' => 'Failed to retrieve records: ' . $this->safeExceptionMessage($e),
            ], 500);
        }
    }

    // ============================================================
    // LEGACY HELPER (kept for compatibility)
    // ============================================================

    private function quote(string $s): string
    {
        return DB::getPdo()->quote($s);
    }
}