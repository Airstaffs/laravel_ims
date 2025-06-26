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
use Carbon\Carbon;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    protected $userLogService;

    public function __construct(UserLogService $userLogService)
    {
        $this->userLogService = $userLogService;
    }

    public function showLoginForm()
    {
        // If user is already authenticated, redirect to dashboard
        if (Auth::check()) {
            return redirect()->route('dashboard.system');
        }

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
        try {
            // Validate login credentials
            $credentials = $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            // Attempt authentication with both username and email
            $loginField = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            $attemptCredentials = [
                $loginField => $credentials['username'],
                'password' => $credentials['password']
            ];

            // Attempt authentication
            if (Auth::attempt($attemptCredentials, $request->filled('remember'))) {
                // Regenerate session for security
                $request->session()->regenerate();

                // Get the authenticated user
                $user = Auth::user();

                // Store user data in session
                $this->storeUserSession($user, $request);
                $this->storeSystemDesign($request);
                $this->storeModulePermissions($user, $request);
                $this->storeStorePermissions($user, $request);

                // Log the login
                try {
                    $this->userLogService->log('User LOGIN');
                } catch (\Exception $e) {
                    Log::warning('Failed to log user login: ' . $e->getMessage());
                }

                // FIXED: Set success message for dashboard (not login page)
                // This will be displayed on the dashboard page after redirect
                $request->session()->flash('login_success', 'Welcome back, ' . $user->username . '!');

                // Redirect to dashboard
                return redirect()->route('dashboard.system');
            }

            // Authentication failed
            return back()->withErrors([
                'username' => 'The provided credentials do not match our records.',
            ])->withInput($request->only('username'));

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return back()->with('error', 'An error occurred during login. Please try again.')->withInput();
        }
    }

    private function storeUserSession($user, $request)
    {
        $timezoneSetting = json_decode($user->timezone_setting, true);
        $autoSync = $timezoneSetting['auto_sync'] ?? true;

        // Detect user's timezone using IP (or JS or fallback)
        $detectedTimezone = $this->detectTimezoneFromRequest($request);

        if ($autoSync && $detectedTimezone) {
            // Update the user's timezone in DB if auto_sync is true
            $timezoneSetting['usertimezone'] = $detectedTimezone;

            DB::table('tbluser')->where('id', $user->id)->update([
                'timezone_setting' => json_encode($timezoneSetting)
            ]);
        }

        // Store timezone in session
        $request->session()->put([
            'user_name' => $user->username,
            'profile_picture' => $user->profile_picture,
            'userid' => $user->id,
            'usertimezone' => $timezoneSetting['usertimezone'] ?? 'America/Los_Angeles'
        ]);
    }

    private function detectTimezoneFromRequest(Request $request)
    {
        // Prefer form input first, fallback to header or default
        return $request->input('timezone') ?? $request->header('X-Timezone') ?? date_default_timezone_get();
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
        // Store main module
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
            'notfound',
            'asinoption',
            'houseage'
        ];

        $activeSubModules = array_filter($subModules, function ($module) use ($user) {
            return $user->{$module} == 1;
        });

        $request->session()->put('sub_modules', array_values($activeSubModules));
    }

    private function storeStorePermissions($user, $request)
    {
        try {
            // Get store columns from database
            $storeColumns = DB::select("SHOW COLUMNS FROM tbluser LIKE 'store_%'");

            // Filter active stores
            $activeStores = array_filter(
                array_map(fn($column) => $column->Field, $storeColumns),
                fn($store) => $user->{$store} == 1
            );

            $request->session()->put('stores', array_values($activeStores));
        } catch (\Exception $e) {
            Log::warning('Failed to store store permissions: ' . $e->getMessage());
            $request->session()->put('stores', []);
        }
    }

    public function showSystemDashboard()
    {
        if (!Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Please log in to access the dashboard.');
        }

        try {
            $Allusers = User::all();

            // Get additional data for dashboard
            $user = Auth::user();

            // Get the most recent attendance record
            $lastRecord = DB::table('tblemployeeclocks')
                ->where('userid', $user->id)
                ->orderBy('TimeIn', 'desc')
                ->first();

            // Get very last record in general
            $verylastRecord = DB::table('tblemployeeclocks')
                ->where('userid', $user->id)
                ->orderBy('ID', 'desc')
                ->first();

            // Calculate today's total worked minutes
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

            // Calculate this week's total worked minutes
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

            // Format hours
            $todayHoursFormatted = sprintf('%d hrs %02d mins', intdiv($todayMinutes, 60), $todayMinutes % 60);
            $weekHoursFormatted = sprintf('%d hrs %02d mins', intdiv($weekMinutes, 60), $weekMinutes % 60);

            // Get current week's attendance records
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

            // Get all employee clock records for the user
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

            return view('dashboard.Systemdashboard', compact(
                'Allusers',
                'lastRecord',
                'verylastRecord',
                'todayHoursFormatted',
                'weekHoursFormatted',
                'employeeClocksThisweek',
                'employeeClocks'
            ));

        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            return redirect()->route('login')
                ->with('error', 'Unable to load dashboard. Please try again.');
        }
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

            // Restrict to @airstaffs.com domain
            if (!Str::endsWith($email, '@airstaffs.com')) {
                return redirect()->route('login')->with('error', 'Only Airstaffs employees are allowed.');
            }

            // Extract username
            $username = Str::ucfirst(Str::before($email, '@'));

            // Check if user with this username already exists
            $user = User::where('username', $username)->first();

            if ($user) {
                // Update existing user info
                $user->update([
                    'email' => $email,
                    'profile_picture' => $googleUser->getAvatar(),
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'username' => $username,
                    'email' => $email,
                    'profile_picture' => $googleUser->getAvatar(),
                    'password' => bcrypt($username . '1234'),
                ]);
            }

            // Authenticate the user
            Auth::login($user);

            // Regenerate session for security
            request()->session()->regenerate();

            // Store session and permissions
            $this->storeUserSession($user, request());
            $this->storeSystemDesign(request());
            $this->storeModulePermissions($user, request());
            $this->storeStorePermissions($user, request());

            try {
                $this->userLogService->log('User LOGIN via Google');
            } catch (\Exception $e) {
                Log::warning('Failed to log Google login: ' . $e->getMessage());
            }

            // FIXED: Set success message for dashboard (Google login)
            request()->session()->flash('login_success', 'Welcome back, ' . $user->username . '! (Google Login)');

            // Redirect to dashboard
            return redirect()->route('dashboard.system');

        } catch (\Exception $e) {
            Log::error('Google login error: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Failed to log in with Google. Please try again.');
        }
    }

    public function logout(Request $request)
    {
        try {
            // Log the logout before clearing session
            if (Auth::check()) {
                $this->userLogService->log('User LOGOUT');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to log user logout: ' . $e->getMessage());
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // FIXED: Use logout_success instead of success to avoid audio confusion
        return redirect()->route('login')->with('logout_success', 'You have been logged out successfully.');
    }
}