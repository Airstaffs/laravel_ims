<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class DashboardController extends Controller
{
    /**
     * Show the system dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function showSystemDashboard()
    {
        // Ensure the user is authenticated
        if (Auth::check()) {
            $users = User::all(); // Fetch all users
            return view('dashboard.Systemdashboard', compact('users')); // Pass users to the view
        } else {
            return redirect()->route('login')->with('error', 'Please log in to access the dashboard.');
        }
    }
}
