<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class RefreshSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Handle only if user is authenticated
        if (Auth::check()) {
            // Get the current lifetime and last activity time
            $lifetime = config('session.lifetime') * 60; // Convert minutes to seconds
            $now = time();
            
            // Set or update last activity time
            if (!$request->session()->has('last_activity')) {
                $request->session()->put('last_activity', $now);
            }
            
            $lastActivity = $request->session()->get('last_activity');
            
            // Check if we're within the session lifetime
            if (($now - $lastActivity) < $lifetime) {
                // Update the last activity timestamp
                // Only update if this is not a heartbeat request to avoid write overhead
                if ($request->path() !== 'keep-alive') {
                    $request->session()->put('last_activity', $now);
                }
                
                // For AJAX requests, we handle session regeneration on the client side
                // For regular requests, we regenerate only occasionally to improve performance
                if (!$request->ajax() && !$request->wantsJson()) {
                    // Only regenerate session every 30 minutes to reduce overhead
                    $regenerationInterval = 30 * 60; // 30 minutes in seconds
                    
                    if (!$request->session()->has('session_regenerated') || 
                        ($now - $request->session()->get('session_regenerated')) > $regenerationInterval) {
                        
                        // Regenerate session ID for security
                        $request->session()->regenerate();
                        $request->session()->put('session_regenerated', $now);
                    }
                }
            } else {
                // Session expired - only log users out on non-AJAX requests
                // For AJAX requests, the frontend will handle expiry
                if (!$request->ajax() && !$request->wantsJson() && $request->path() !== 'keep-alive') {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    
                    // Return a redirect to login only for normal page requests
                    return redirect('/login')->with('message', 'Your session has expired. Please login again.');
                }
            }
        }

        return $next($request);
    }
}