<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SystemDesign;
use Illuminate\Support\Facades\DB;
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
        $request->session()->put('userid', $user->id); // Assuming 'profile_picture' is the field in the user table\

            // Fetch the user's main module and store it in the session
            $mainModule = $user->main_module; // Assuming `main_module` exists in the user table
            $request->session()->put('main_module', $mainModule);
    
            // Fetch sub-modules and store active ones in the session
            $subModules = ['order', 'unreceived', 'receiving', 'labeling', 'testing', 'cleaning', 'packing', 'stockroom'];
            $activeSubModules = [];
            foreach ($subModules as $module) {
                if ($user->{$module} == 1) { // Check if the sub-module is enabled for the user
                    $activeSubModules[] = $module;
                }
            }
            $request->session()->put('sub_modules', $activeSubModules);
    
            // Fetch store columns and store active stores in the session
            $storeColumns = DB::select("SHOW COLUMNS FROM tbluser LIKE 'store_%'");
            $storeColumns = array_map(fn($column) => $column->Field, $storeColumns);
    
            $activeStores = [];
            foreach ($storeColumns as $storeColumn) {
                if ($user->{$storeColumn} == 1) { // Check if the store is enabled for the user
                    $activeStores[] = $storeColumn;
                }
            }
            $request->session()->put('stores', $activeStores);

       
        //return redirect()->intended('/dashboard/Systemdashboard')->with('success', 'Login successful!');
        return redirect()->back()->with('success', 'Log in successfully');
    }
        
    // If authentication fails, redirect back with an error
    return back()->withErrors([
        'username' => 'The provided credentials do not match our records.',
    ])->withInput();
    }
   

   public function showSystemDashboard()
   {
    if (Auth::check()) {
        // Fetch all users from the database
        $Allusers = \App\Models\User::all();

        // Pass users to the view
        return view('dashboard.Systemdashboard', ['Allusers' => $Allusers]);
    } else {
        return redirect()->route('login')->with('error', 'Please log in to access the dashboard.');
    }
  }


}
