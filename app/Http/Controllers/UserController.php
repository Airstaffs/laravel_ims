<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:tbluser,username',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|in:SuperAdmin,SubAdmin,User',
        ]);

        try {
            User::create([
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            return back()->with('success', 'User added successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to add user: ' . $e->getMessage());
            return back()->with('error', 'Failed to add user. Please try again.');
        }
    }
    
    public function updatepassword(Request $request)
    {
        $currentUserId = Auth::user()->id; // Get the current user's ID

        // Validate the request
        $request->validate([
            'password' => 'required|min:6|confirmed',
        ]);

        try {
            // Find the current user by ID
            $user = User::findOrFail($currentUserId);

            // Update the user's password
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            return back()->with('success', 'Password updated successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to update password: ' . $e->getMessage());
            return back()->with('error', 'Failed to update password. Please try again.');
        }
    }

}
