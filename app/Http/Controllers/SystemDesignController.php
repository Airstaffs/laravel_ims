<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\UserLogService;

class SystemDesignController extends BasetablesController
{
    protected $userLogService;

    public function __construct(UserLogService $userLogService)
    {
        parent::__construct();
        $this->userLogService = $userLogService;
    }

    public function update(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'site_title' => 'required|string|max:255',
            'theme_color' => 'required|string|max:7',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // Ensure table exists
        $this->ensureSystemSettingsTableExists();

        // Get existing system settings from dynamic table
        $systemSettings = DB::table($this->systemSettingsTable)->first();

        $updateData = [
            'site_title' => $request->site_title,
            'theme_color' => $request->theme_color,
            'updated_at' => now()
        ];

        if ($request->hasFile('logo')) {
            // Delete the old logo if it exists
            if ($systemSettings && $systemSettings->logo) {
                Storage::delete($systemSettings->logo);
            }

            // Store the new logo and update the path
            $path = $request->file('logo')->store('logos', 'public');
            $updateData['logo'] = $path;
        }

        if ($systemSettings) {
            // Update existing record
            DB::table($this->systemSettingsTable)
                ->where('id', $systemSettings->id)
                ->update($updateData);
        } else {
            // Create new record
            $updateData['created_at'] = now();
            DB::table($this->systemSettingsTable)->insert($updateData);
        }

        // Get fresh data for session
        $freshSettings = DB::table($this->systemSettingsTable)->first();

        // Update the session with the new values so they reflect immediately
        session([
            'site_title' => $freshSettings->site_title,
            'theme_color' => $freshSettings->theme_color,
            'logo' => $freshSettings->logo,
        ]);

        // Log using service
        $this->userLogService->log('Update the System Design');

        // Return a success response
        return back()->with('success', 'System design updated successfully!');
    }

    /**
     * Get system design data via AJAX
     */
    public function getData()
    {
        $data = $this->getSettingsForView();
        return response()->json($data);
    }

    /**
     * Get system settings for view
     */
    public function getSettingsForView()
    {
        $this->ensureSystemSettingsTableExists();
        $settings = DB::table($this->systemSettingsTable)->first();
        
        if (!$settings) {
            return (object) [
                'site_title' => '',
                'theme_color' => '#007bff',
                'logo' => null
            ];
        }
        
        return $settings;
    }

    /**
     * Ensure system settings table exists
     */
    private function ensureSystemSettingsTableExists()
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable($this->systemSettingsTable)) {
                DB::statement("CREATE TABLE IF NOT EXISTS `{$this->systemSettingsTable}` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `site_title` varchar(255) NOT NULL DEFAULT '',
                    `theme_color` varchar(7) NOT NULL DEFAULT '#007bff',
                    `logo` varchar(255) NULL,
                    `created_at` timestamp NULL DEFAULT NULL,
                    `updated_at` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
        } catch (\Exception $e) {
            // Handle error silently
        }
    }
}