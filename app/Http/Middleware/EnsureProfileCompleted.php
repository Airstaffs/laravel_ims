<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnsureProfileCompleted
{
    public function handle($request, Closure $next)
    {
        // Must be logged in first
        if (!Auth::check()) {
            return $next($request);
        }

        // Skip assets and public files early
        $path = $request->path(); // no leading slash
        if ($this->isPublicPath($path)) {
            return $next($request);
        }

        // Allow these named routes while completing the profile
        $allowedRouteNames = [
            // profile completion view + the HR JSON endpoints you already have
            'account.complete.view',
            'account.details',
            'account.update-details',

            // login/logout/auth routes you may have named
            'login',
            'logout',
            'password.request',
            'password.email',
            'password.reset',
            'password.update',
            'verification.notice',
            'verification.verify',

            // socialite
            // e.g. 'google.login', 'google.callback' if you named them
        ];

        if ($request->route() && $request->routeIs($allowedRouteNames)) {
            return $next($request);
        }

        // Also allow XHR to the account endpoints by path (defensive)
        if (Str::startsWith($path, ['hr/account/details', 'hr/account/update-details'])) {
            return $next($request);
        }

        // Determine first-login state (treat NULL as first login)
        $user = Auth::user();
        $val = DB::table('tbluser')->where('id', $user->id)->value('first_login');
        $firstLogin = is_null($val) ? true : ((int)$val === 1);

        // DEBUG (optional): uncomment to verify what's happening
        // \Log::debug('EnsureProfileCompleted', ['uid' => $user->id, 'first_login_raw' => $val, 'first_login' => $firstLogin, 'path' => $path]);

        if ($firstLogin) {
            return redirect()
                ->route('account.complete.view')
                ->with('error', 'Please complete your account profile to continue.');
        }

        return $next($request);
    }

    private function isPublicPath(string $path): bool
    {
        // Skip common public/asset paths (tweak for your project)
        return Str::startsWith($path, [
            '_debugbar', 'storage', 'images', 'img', 'css', 'js', 'fonts',
            'vendor', 'build', 'favicon', 'robots.txt', 'manifest.json',
            'auth/google', // Socialite redirect endpoint (path-based)
            'login',       // avoid loops on login page
        ]);
    }
}
