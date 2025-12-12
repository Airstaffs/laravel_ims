<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TimeHelper
{
    /**
     * Get user's timezone
     */
    public static function getUserTimezone()
    {
        $userId = Auth::id();

        if (! $userId) {
            return 'UTC';
        }

        $settingJson = DB::table('tbluser')
            ->where('id', $userId)
            ->value('timezone_setting');

        $setting = json_decode($settingJson, true);

        return $setting['usertimezone'] ?? 'UTC';
    }

    /**
     * Format time (e.g., "03:45 PM")
     */
    public static function formatTime($datetime, $timezone = null)
    {
        if (empty($datetime)) {
            return '';
        }

        $timezone = $timezone ?? self::getUserTimezone();

        try {
            return Carbon::parse($datetime)
                ->timezone($timezone)
                ->format('h:i A');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Format time with seconds (e.g., "03:45:22 PM")
     */
    public static function formatTimeWithSeconds($datetime, $timezone = null)
    {
        if (empty($datetime)) {
            return '';
        }

        $timezone = $timezone ?? self::getUserTimezone();

        try {
            return Carbon::parse($datetime)
                ->timezone($timezone)
                ->format('h:i:s A');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Format date (e.g., "Dec 11, 2025")
     */
    public static function formatDate($datetime, $format = 'M d, Y', $timezone = null)
    {
        if (empty($datetime)) {
            return '';
        }

        $timezone = $timezone ?? self::getUserTimezone();

        try {
            return Carbon::parse($datetime)
                ->timezone($timezone)
                ->format($format);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Format date with full month (e.g., "December 11, 2025")
     */
    public static function formatDateLong($datetime, $timezone = null)
    {
        return self::formatDate($datetime, 'F d, Y', $timezone);
    }

    /**
     * Format datetime (e.g., "Dec 11, 2025 03:45 PM")
     */
    public static function formatDateTime($datetime, $format = 'M d, Y h:i A', $timezone = null)
    {
        if (empty($datetime)) {
            return '';
        }

        $timezone = $timezone ?? self::getUserTimezone();

        try {
            return Carbon::parse($datetime)
                ->timezone($timezone)
                ->format($format);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Format datetime with full details
     */
    public static function formatDateTimeLong($datetime, $timezone = null)
    {
        return self::formatDateTime($datetime, 'l, F d, Y h:i A', $timezone);
    }

    /**
     * Calculate hours between two times
     */
    public static function calculateHours($timeIn, $timeOut)
    {
        if (empty($timeIn) || empty($timeOut)) {
            return 'Not calculated';
        }

        try {
            $start = Carbon::parse($timeIn);
            $end = Carbon::parse($timeOut);

            $diff = $start->diff($end);

            $hours = $diff->h + ($diff->days * 24);
            $minutes = $diff->i;

            return sprintf('%dh %dm', $hours, $minutes);
        } catch (\Exception $e) {
            return 'Error';
        }
    }

    /**
     * Format relative time (e.g., "2 hours ago")
     */
    public static function formatRelativeTime($datetime, $timezone = null)
    {
        if (empty($datetime)) {
            return '';
        }

        $timezone = $timezone ?? self::getUserTimezone();

        try {
            return Carbon::parse($datetime)
                ->timezone($timezone)
                ->diffForHumans();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get current time
     */
    public static function getCurrentTime($timezone = null)
    {
        $timezone = $timezone ?? self::getUserTimezone();

        return Carbon::now($timezone)->format('h:i:s A');
    }

    /**
     * Get current date
     */
    public static function getCurrentDate($format = 'M d, Y', $timezone = null)
    {
        $timezone = $timezone ?? self::getUserTimezone();

        return Carbon::now($timezone)->format($format);
    }

    /**
     * Get timezone display name
     */
    public static function getTimezoneDisplay($timezone = null)
    {
        $timezone = $timezone ?? self::getUserTimezone();

        $timezoneNames = [
            'America/Los_Angeles' => 'Pacific Time',
            'America/Denver' => 'Mountain Time',
            'America/Chicago' => 'Central Time',
            'America/New_York' => 'Eastern Time',
            'America/Anchorage' => 'Alaska Time',
            'Pacific/Honolulu' => 'Hawaii Time',
            'Asia/Manila' => 'Philippine Time',
            'Asia/Tokyo' => 'Japan Time',
            'Asia/Shanghai' => 'China Time',
            'Asia/Hong_Kong' => 'Hong Kong Time',
            'Asia/Singapore' => 'Singapore Time',
            'Asia/Seoul' => 'Korea Time',
            'Asia/Bangkok' => 'Thailand Time',
            'Asia/Dubai' => 'UAE Time',
            'Asia/Kolkata' => 'India Time',
            'Europe/London' => 'UK Time',
            'Europe/Paris' => 'Central European Time',
            'Europe/Berlin' => 'Central European Time',
            'Europe/Moscow' => 'Moscow Time',
            'Australia/Sydney' => 'Australian Eastern Time',
            'Australia/Melbourne' => 'Australian Eastern Time',
            'Australia/Perth' => 'Australian Western Time',
            'UTC' => 'UTC',
        ];

        $friendlyName = $timezoneNames[$timezone] ?? $timezone;

        try {
            $abbreviation = Carbon::now($timezone)->format('T');

            return "$friendlyName ($abbreviation)";
        } catch (\Exception $e) {
            return $friendlyName;
        }
    }
}
