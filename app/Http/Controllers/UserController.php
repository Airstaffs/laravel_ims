<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
}
