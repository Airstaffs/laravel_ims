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




}
