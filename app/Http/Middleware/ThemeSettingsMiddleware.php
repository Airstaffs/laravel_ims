<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SystemDesign;

class ThemeSettingsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Fetch the system design settings from the database to get the latest values
        $systemDesign = SystemDesign::first();  // Or use caching for better performance

        // Share the settings with all views
        view()->share('systemDesign', $systemDesign);

        // Continue processing the request
        return $next($request);
    }
}