<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SystemDesign;
class LoginController extends Controller
{
    /**
     * Display the login form.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        $systemDesign = SystemDesign::first();

    // Store the system design settings in the session
    if ($systemDesign) {
        session([
            'site_title' => $systemDesign->site_title,
            'theme_color' => $systemDesign->theme_color,
            'logo' => $systemDesign->logo,
        ]);
    }

    // Return the login view, passing in system design settings
    return view('login.index', compact('systemDesign')); // Pass systemDesign to the view if needed
    }

    /**
     * Handle login authentication.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authenticate(Request $request){
        // Validate the login credentials
        // Validate the login credentials
    $credentials = $request->validate([
        'username' => 'required|string',
        'password' => 'required|string',
    ]);

    // Attempt to log the user in
    if (Auth::attempt($credentials)) {
        $request->session()->regenerate(); // Regenerate session to prevent fixation

        // Retrieve system design settings from the database
        $systemDesign = SystemDesign::first();

        // Store the system settings in the session
        if ($systemDesign) {
            $request->session()->put('site_title', $systemDesign->site_title);
            $request->session()->put('theme_color', $systemDesign->theme_color);
            $request->session()->put('logo', $systemDesign->logo);
        }

        // Retrieve the logged-in user
        $user = Auth::user();
        $request->session()->put('user_name', $user->username); // Assuming 'username' is the field in the user table
        $request->session()->put('profile_picture', $user->profile_picture); // Assuming 'profile_picture' is the field in the user table
        $request->session()->put('userid', $user->id); // Assuming 'profile_picture' is the field in the user table

        return redirect()->intended('/dashboard/Systemdashboard')->with('success', 'Login successful!');
    }

    // If authentication fails, redirect back with an error
    return back()->withErrors([
        'username' => 'The provided credentials do not match our records.',
    ])->withInput();
    }
}