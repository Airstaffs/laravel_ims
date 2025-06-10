<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use App\Models\SystemDesign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\UserLogService;
use Carbon\Carbon; // Make sure this is imported
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{

    protected $userLogService;

    public function __construct(UserLogService $userLogService)
    {
        $this->userLogService = $userLogService;
    }

    public function showLoginForm()
    {
        $systemDesign = SystemDesign::first();

        // Store system design settings in session
        if ($systemDesign) {
            session([
                'site_title' => $systemDesign->site_title,
                'theme_color' => $systemDesign->theme_color,
                'logo' => $systemDesign->logo,
            ]);
        }

        return view('login.index', compact('systemDesign'));
    }

    public function authenticate(Request $request)
    {
        // Validate login credentials
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Attempt authentication
        if (Auth::attempt($credentials)) {
            // Regenerate session for security
            $request->session()->regenerate();

            // Get the authenticated user
            $user = Auth::user();

            // Store basic user information
            $this->storeUserSession($user, $request);

            // Store system design settings
            $this->storeSystemDesign($request);

            // Store module permissions
            $this->storeModulePermissions($user, $request);

            // Store store permissions
            $this->storeStorePermissions($user, $request);

            // Log using service
            $this->userLogService->log('User LOGIN');

            return redirect()->back()->with('success', 'Log in successfully');
        }

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->withInput();
    }

    private function storeUserSession($user, $request)
    {
        $request->session()->put([
            'user_name' => $user->username,
            'profile_picture' => $user->profile_picture,
            'userid' => $user->id
        ]);
    }

    private function storeSystemDesign($request)
    {
        $systemDesign = SystemDesign::first();
        if ($systemDesign) {
            $request->session()->put([
                'site_title' => $systemDesign->site_title,
                'theme_color' => $systemDesign->theme_color,
                'logo' => $systemDesign->logo
            ]);
        }
    }

    private function storeModulePermissions($user, $request)
    {
        // Store main module - this is the key change you needed
        $mainModule = $user->main_module;
        if (!empty($mainModule)) {
            $request->session()->put('main_module', $mainModule);
        }

        // Store sub-modules
        $subModules = [
            'order',
            'unreceived',
            'receiving',
            'labeling',
            'testing',
            'cleaning',
            'packing',
            'stockroom',
            'fnsku',
            'validation',
            'productionarea',
            'returnscanner',
            'fbmorder',
            'notfound'
        ];

        $activeSubModules = array_filter($subModules, function ($module) use ($user) {
            return $user->{$module} == 1;
        });

        $request->session()->put('sub_modules', array_values($activeSubModules));
    }

    private function storeStorePermissions($user, $request)
    {
        // Get store columns from database
        $storeColumns = DB::select("SHOW COLUMNS FROM tbluser LIKE 'store_%'");

        // Filter active stores
        $activeStores = array_filter(
            array_map(fn($column) => $column->Field, $storeColumns),
            fn($store) => $user->{$store} == 1
        );

        $request->session()->put('stores', array_values($activeStores));
    }

    public function showSystemDashboard()
    {
        if (Auth::check()) {
            $Allusers = \App\Models\User::all();
            return view('dashboard.Systemdashboard', ['Allusers' => $Allusers]);
        }

        return redirect()->route('login')
            ->with('error', 'Please log in to access the dashboard.');
    }

    public function googlepage()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $email = $googleUser->getEmail();

            // ✅ Restrict to @airstaffs.com domain
            if (!Str::endsWith($email, '@airstaffs.com')) {
                return redirect()->route('login')->with('error', 'Only Airstaffs employee are allowed.');
            }

            // ✅ Extract username
            $username = Str::before($email, '@');

            // ✅ Check if user with this username already exists
            $user = User::where('username', $username)->first();

            if ($user) {
                // ✅ Update existing user info
                $user->update([
                    'email' => $email,
                    'profile_picture' => $googleUser->getAvatar(),
                ]);
            } else {
                // ✅ Create new user
                $user = User::create([
                    'username' => $username,
                    'email' => $email,
                    'profile_picture' => $googleUser->getAvatar(),
                    'password' => bcrypt($username . '1234'),
                ]);
            }

            // Authenticate the user
            Auth::login($user);

            // Store session and permissions
            $this->storeUserSession($user, request());
            $this->storeSystemDesign(request());
            $this->storeModulePermissions($user, request());
            $this->storeStorePermissions($user, request());
            $this->userLogService->log('User LOGIN');

            // Get all users for dashboard
            $Allusers = User::all();

            // ✅ Get the most recent attendance record from tblemployeeclocks
            $lastRecord = DB::table('tblemployeeclocks')
                ->where('userid', $user->id)
                ->orderBy('TimeIn', 'desc')
                ->first();

            // ✅ Also get very last record in general
            $verylastRecord = DB::table('tblemployeeclocks')
                ->where('userid', $user->id)
                ->orderBy('ID', 'desc')
                ->first();

            // ✅ Calculate today's total worked minutes
            $todayMinutes = DB::table('tblemployeeclocks')
                ->where('userid', $user->id)
                ->whereDate('TimeIn', Carbon::today('America/Los_Angeles'))
                ->sum(DB::raw("
                TIMESTAMPDIFF(
                    MINUTE,
                    TimeIn,
                    COALESCE(TimeOut, DATE_SUB(NOW(), INTERVAL 8 HOUR))
                )
            "));

            // ✅ Calculate this week's total worked minutes
            $weekMinutes = DB::table('tblemployeeclocks')
                ->where('userid', $user->id)
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

            // ✅ Format hours
            $todayHoursFormatted = sprintf('%d hrs %02d mins', intdiv($todayMinutes, 60), $todayMinutes % 60);
            $weekHoursFormatted = sprintf('%d hrs %02d mins', intdiv($weekMinutes, 60), $weekMinutes % 60);

            // ✅ Also get current week's attendance records
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
                ->where('tblemployeeclocks.userid', $user->id)
                ->whereBetween('tblemployeeclocks.TimeIn', [
                    Carbon::now('America/Los_Angeles')->startOfWeek(),
                    Carbon::now('America/Los_Angeles')->endOfWeek(),
                ])
                ->orderBy('tblemployeeclocks.TimeIn', 'desc')
                ->get();

            // ✅ Get all employee clock records for the user
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
                ->where('tblemployeeclocks.userid', $user->id)
                ->orderBy('tblemployeeclocks.TimeIn', 'desc')
                ->get();

            // ✅ Redirect to clean dashboard route
            return redirect()->route('dashboard.system')->with('success', 'Logged in with Google successfully.');
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Failed to log in with Google: ' . $e->getMessage());
        }
    }
}
