<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function showAttendanceWidget()
    {
        //$userId = Auth::id(); // Get the authenticated user's ID
        $userId = 1; // Replace with the actual user ID


        // Fetch the latest attendance record for the user
        $attendance = Attendance::where('userid', $userId)
            ->orderBy('Timein', 'desc')
            ->first();

        // Return the view with the attendance data
        return view('dashboard', compact('attendance'));
    }
}
