<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class LoginController extends Controller
{
    /**
     * Display the login form.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('login.index'); // Ensure this matches your blade template name
    }

    /**
     * Handle login authentication.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authenticate(Request $request){
        // Validate the login credentials
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Attempt to log the user in
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate(); // Regenerate session to prevent fixation

            $user = Auth::user();
            $request->session()->put('user_name', $user->username); // Assuming 'username' is the field in the user table
            $request->session()->put('profile_picture', $user->profile_picture); // Assuming 'profile_picture' is the field in the user table
            return redirect()->intended('/dashboard/Systemdashboard')->with('success', 'Login successful!'); // Redirect to a secure area
        }

        // If authentication fails, redirect back with an error
        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->withInput();
    }
}