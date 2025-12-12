<?php

namespace App\Providers;

use App\Helpers\TimeHelper;
use Illuminate\Support\Facades\Blade; // ✅ Capital "I"
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register TimeHelper as singleton
        $this->app->singleton('timeHelper', function () {
            return new TimeHelper;
        });
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

        // @formatTime($datetime)
        Blade::directive('formatTime', function ($expression) {
            return "<?php echo \App\Helpers\TimeHelper::formatTime($expression); ?>";
        });

        // @formatDate($datetime)
        Blade::directive('formatDate', function ($expression) {
            return "<?php echo \App\Helpers\TimeHelper::formatDate($expression); ?>";
        });

        // @formatDateTime($datetime)
        Blade::directive('formatDateTime', function ($expression) {
            return "<?php echo \App\Helpers\TimeHelper::formatDateTime($expression); ?>";
        });

        // @calculateHours($timeIn, $timeOut)
        Blade::directive('calculateHours', function ($expression) {
            return "<?php echo \App\Helpers\TimeHelper::calculateHours($expression); ?>";
        });

        // @relativeTime($datetime)
        Blade::directive('relativeTime', function ($expression) {
            return "<?php echo \App\Helpers\TimeHelper::formatRelativeTime($expression); ?>";
        });

        // @currentTime
        Blade::directive('currentTime', function () {
            return "<?php echo \App\Helpers\TimeHelper::getCurrentTime(); ?>";
        });

        // @currentDate
        Blade::directive('currentDate', function () {
            return "<?php echo \App\Helpers\TimeHelper::getCurrentDate(); ?>";
        });

        // @timezoneDisplay
        Blade::directive('timezoneDisplay', function () {
            return "<?php echo \App\Helpers\TimeHelper::getTimezoneDisplay(); ?>";
        });
    }
}
