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
    const DB_TZ = 'America/Los_Angeles';

    private function maskHas(?int $mask, int $isoDow): bool
    {
        $m = (int) ($mask ?? 0);
        return $m > 0 && (($m & (1 << ($isoDow - 1))) !== 0);
    }

    private function checkLoginWindow(int $userId, string $tz): array
    {
        $now = \Carbon\Carbon::now($tz);
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();
        $dowToday = (int) $now->isoWeekday();            // 1..7
        $dowYest = (int) $now->copy()->subDay()->isoWeekday();

        // Load active links for *today* (effective range), join timesched
        $links = DB::table('tblusersched as us')
            ->join('tbltimesched as ts', 'ts.timeschedId', '=', 'us.schedId')
            ->select(
                'us.userschedId',
                'us.userId',
                'us.schedId',
                'us.effective_from',
                'us.effective_to',
                'us.is_active',
                'us.early_login_mins  as us_early_login_mins',
                'ts.timeschedId',
                'ts.day_of_week',
                'ts.days_mask',
                'ts.start_time',
                'ts.end_time',
                'ts.end_next_day',
                'ts.title',
                'ts.early_login_mins  as ts_early_login_mins'
            )
            ->where('us.userId', $userId)
            ->where('us.is_active', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('us.effective_from')->orWhere('us.effective_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('us.effective_to')->orWhere('us.effective_to', '>=', $today);
            })
            ->where('ts.is_active', 1)
            ->orderBy('ts.start_time')
            ->get();

        if ($links->isEmpty()) {
            return ['allowed' => false, 'message' => 'You have no schedule assigned for today.'];
        }

        // helper: resolve early_login_mins (user override > template > 0)
        $resolveLoginMins = function ($userVal, $tmplVal) {
            if (is_numeric($userVal))
                return (int) $userVal;
            if (is_numeric($tmplVal))
                return (int) $tmplVal;
            return 0;
        };

        $tooEarlyEarliest = null; // store earliest "allowed at" to show a friendly message

        foreach ($links as $r) {
            $hasMask = ((int) ($r->days_mask ?? 0) > 0);
            $isEveryLegacy = ((int) $r->day_of_week) === 0; // legacy "everyday"

            $loginGrace = $resolveLoginMins($r->us_early_login_mins, $r->ts_early_login_mins);

            // ---- A) Today-anchored window
            $todayOk = ($hasMask && $this->maskHas($r->days_mask, $dowToday))
                || (!$hasMask && ($isEveryLegacy || (int) $r->day_of_week === $dowToday));

            if ($todayOk) {
                $start = Carbon::parse($today . ' ' . $r->start_time, $tz);
                $end = Carbon::parse($today . ' ' . $r->end_time, $tz);
                if ((int) $r->end_next_day === 1)
                    $end->addDay();

                $allowedFrom = $start->copy()->subMinutes($loginGrace);

                if ($now->between($allowedFrom, $end, true)) {
                    return ['allowed' => true, 'message' => null];
                }
                if ($now->lt($allowedFrom)) {
                    if (!$tooEarlyEarliest || $allowedFrom->lt($tooEarlyEarliest)) {
                        $tooEarlyEarliest = $allowedFrom;
                    }
                }
            }

            // ---- B) Overnight window (yesterday-anchored)
            if ((int) $r->end_next_day === 1) {
                $yOk = ($hasMask && $this->maskHas($r->days_mask, $dowYest))
                    || (!$hasMask && ($isEveryLegacy || (int) $r->day_of_week === $dowYest));

                if ($yOk) {
                    $startY = Carbon::parse($yesterday . ' ' . $r->start_time, $tz);
                    $endY = Carbon::parse($yesterday . ' ' . $r->end_time, $tz)->addDay();

                    $allowedFromY = $startY->copy()->subMinutes($loginGrace);

                    if ($now->between($allowedFromY, $endY, true)) {
                        return ['allowed' => true, 'message' => null];
                    }
                    // too-early for a *yesterday* window doesn’t apply (it started in the past)
                }
            }
        }

        // If we reached here, no window currently allows login
        if ($tooEarlyEarliest) {
            return [
                'allowed' => false,
                'message' => 'Too early to log in. Earliest allowed time: ' . $tooEarlyEarliest->format('h:i A') . '.'
            ];
        }

        return [
            'allowed' => false,
            'message' => 'You are outside your scheduled login time window.'
        ];
    }

    /** Enforce the schedule gate unless SuperAdmin. Returns RedirectResponse|null. */
    private function enforceScheduleGateOrBypass(User $user, Request $request)
    {
        // Pull latest role from DB to be safe
        $role = DB::table('tbluser')->where('id', $user->id)->value('role');
        if (is_string($role) && strcasecmp($role, 'SuperAdmin') === 0) {
            return null; // bypass for SuperAdmin
        }

        $tz = $this->detectTimezoneFromRequest($request); // same logic you use elsewhere
        $gate = $this->checkLoginWindow($user->id, $tz);

        if ($gate['allowed']) {
            return null; // proceed normally
        }

        // If not allowed — log out and redirect to login page with message
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors([
                'username' => $gate['message'] ?? 'Login not allowed right now.',
            ]);
    }


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

                if ($resp = $this->enforceScheduleGateOrBypass($user, $request)) {
                    return $resp; // blocked (too early / no schedule / outside window)
                }

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

                $firstLogin = \DB::table('tbluser')->where('username', $user->username)->value('first_login');


                if (is_null($firstLogin) || (int) $firstLogin === 1) {
                    return redirect()->route('account.complete.view');
                }

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

    private function validateTz(?string $tz): ?string
    {
        return ($tz && in_array($tz, \DateTimeZone::listIdentifiers(), true)) ? $tz : null;
    }

    /** Hard defaults for the setting */
    private function defaultTimezoneSetting(): array
    {
        return ['usertimezone' => 'America/Los_Angeles', 'auto_sync' => true];
    }

    /** Detect timezone from request; fall back to LA */
    private function detectTimezoneFromRequest(Request $request): string
    {
        $candidates = [
            $request->input('timezone'),
            $request->header('X-Timezone'),
        ];

        foreach ($candidates as $tz) {
            if ($valid = $this->validateTz($tz)) {
                return $valid;
            }
        }
        // HARD DEFAULT if nothing valid
        return 'America/Los_Angeles';
    }

    private function storeUserSession($user, Request $request)
    {
        // Parse existing JSON (could be null/invalid)
        $existing = json_decode($user->timezone_setting ?? '', true);
        if (!is_array($existing))
            $existing = [];

        // Merge with defaults to ensure both keys exist
        $setting = array_merge($this->defaultTimezoneSetting(), $existing);

        // Ensure values are valid types
        $setting['auto_sync'] = (bool) ($setting['auto_sync'] ?? true);
        $setting['usertimezone'] = $this->validateTz($setting['usertimezone']) ?? 'America/Los_Angeles';

        // Detect current tz (or LA) and update if auto_sync is ON
        $detected = $this->detectTimezoneFromRequest($request);
        $originalJson = json_encode($setting);

        if ($setting['auto_sync'] === true) {
            if ($detected !== $setting['usertimezone']) {
                $setting['usertimezone'] = $detected;
            }
        }

        // If keys were missing before OR value changed, persist back to DB
        $needsUpdate = ($originalJson !== json_encode($setting))
            || !array_key_exists('usertimezone', $existing)
            || !array_key_exists('auto_sync', $existing);

        if ($needsUpdate) {
            DB::table('tbluser')->where('id', $user->id)->update([
                'timezone_setting' => json_encode($setting),
            ]);
        }

        // Session always carries a valid TZ (LA if all else fails)
        $request->session()->put([
            'user_name' => $user->username,
            'profile_picture' => $user->profile_picture,
            'userid' => $user->id,
            'usertimezone' => $setting['usertimezone'],
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
            'rts',
            'returnscanner',
            'fbmorder',
            'notfound',
            'asinoption',
            'houseage',
            'asinlist',
            'printer',
            'humanresource',
            'announcement',
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

            if ($resp = $this->enforceScheduleGateOrBypass($user, request())) {
                return $resp;
            }

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

            $firstLogin = \DB::table('tbluser')->where('id', $user->id)->value('first_login');
            if (is_null($firstLogin) || (int) $firstLogin === 1) {
                return redirect()->route('account.complete.view');
            }

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
