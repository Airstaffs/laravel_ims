<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreventBackHistory
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $response = $next($request);

        // Add comprehensive cache prevention headers
        return $response->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate, private')
                       ->header('Pragma', 'no-cache')
                       ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT')
                       ->header('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT')
                       ->header('X-Frame-Options', 'DENY')
                       ->header('X-Content-Type-Options', 'nosniff')
                       ->header('Referrer-Policy', 'no-referrer')
                       ->header('Feature-Policy', 'camera \'none\'; microphone \'none\'; geolocation \'none\'');
    }
}