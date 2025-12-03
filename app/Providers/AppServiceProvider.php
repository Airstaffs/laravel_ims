<?php

namespace App\Providers;

use App\Services\PrintLabelService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route; // ✅ Capital "I"

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $apiRoutes = base_path('routes/api.php');

        if (file_exists($apiRoutes)) {
            Route::middleware('api')
                ->prefix('api')
                ->group($apiRoutes);
        }
    }
}
