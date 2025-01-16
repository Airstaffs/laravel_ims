<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class AttendanceController extends Controller
{
    public function attendance()
        {
            // Get the current user's ID from the session or Auth
            $currentUserId = Auth::user()->id;

            // Query the attendance data for the logged-in user, ordered by TimeIn
            $employeeClocks = DB::table('tblemployeeclocks')
                ->join('tbluser', 'tblemployeeclocks.userid', '=', 'tbluser.id')
                ->select('tblemployeeclocks.ID', 'tblemployeeclocks.userid', 'tblemployeeclocks.Employee', 'tblemployeeclocks.TimeIn', 'tblemployeeclocks.TimeOut', 'tbluser.username')
                ->where('tblemployeeclocks.userid', $currentUserId) // Filter by the current user's ID
                ->orderBy('tblemployeeclocks.TimeIn', 'desc') // Order by TimeIn (descending or ascending)
                ->get();

            // Fetch the most recent clock-in record for today with no clock-out
            $lastRecord = DB::table('tblemployeeclocks')
            ->where('userid', $currentUserId)
            ->whereDate('TimeIn', Carbon::today('America/Los_Angeles')) // Ensure TimeIn is today's date
            ->orderBy('ID', 'desc') // Get the most recent record
            ->first(); // Retrieve only the last record

            // Pass the data to the Blade view
    return view('dashboard/Systemdashboard', compact('employeeClocks', 'lastRecord'));
        }


    public function clockIn(Request $request)
        {
            // Get the current user's ID
            $currentUserId = Auth::user()->id;
            $currentUsername = Auth::user()->username;

            // Get the current date and time
            $currentDateTime = Carbon::now('America/Los_Angeles');

            // Insert into the tblemployeeclocks table
            DB::table('tblemployeeclocks')->insert([
                'userid' => $currentUserId,
                'Employee' => $currentUsername,
                'TimeIn' => $currentDateTime,
            ]);

            // Redirect back with a success message
            return redirect()->back()->with('success', 'Clocked in successfully at ' . $currentDateTime->format('h:i A'));
        }
    
    public function clockOut(Request $request)
        {
            // Get the current user's ID
            $currentUserId = Auth::user()->id;
        
            // Get the current date and time
            $currentDateTime = Carbon::now('America/Los_Angeles');
        
            // Get the last record for the current user with today's TimeIn and null TimeOut
            $lastRecord = DB::table('tblemployeeclocks')
                ->where('userid', $currentUserId)
                ->whereDate('TimeIn', Carbon::today('America/Los_Angeles')) // Ensure TimeIn is today's date
                ->whereNotNull('TimeIn') // Ensure TimeIn is not null
                ->whereNull('TimeOut') // Ensure TimeOut is null (no clock-out yet)
                ->orderBy('ID', 'desc') // Get the most recent record
                ->first(); // Retrieve only the last record
        
            if ($lastRecord) {
                // Update the TimeOut field for the last record
                DB::table('tblemployeeclocks')
                    ->where('ID', $lastRecord->ID) // Update only the last record by ID
                    ->update(['TimeOut' => $currentDateTime]);
        
                // Redirect back with a success message
                return redirect()->back()->with('success', 'Clocked out successfully at ' . $currentDateTime->format('h:i A'));
            } else {
                // If no valid record found, return an error message
                return redirect()->back()->with('error', 'No valid clock-in record found for today to clock out.');
            }
        }
        


}

