<?php

namespace App\Traits;

use App\Http\Controllers\HistoryTrackingController;

trait TracksHistory
{
    protected $historyController;

    /**
     * Initialize the history controller
     */
    protected function initializeHistoryTracking()
    {
        if (! $this->historyController) {
            $this->historyController = new HistoryTrackingController;
        }
    }

    /**
     * Quick log history helper
     */
    protected function trackHistory(
        string $module,
        string $action,
        ?string $oldLocation = null,
        ?string $newLocation = null,
        ?string $employeeName = null
    ): int {
        $this->initializeHistoryTracking();

        // Truncate long strings to prevent database errors
        // Reduced to 200 characters to fit database column
        $oldLocation = $this->truncateString($oldLocation, 200);
        $newLocation = $this->truncateString($newLocation, 200);

        return $this->historyController->logHistory(
            $module,
            $action,
            $oldLocation,
            $newLocation,
            $employeeName
        );
    }

    /**
     * Truncate string to specified length
     */
    private function truncateString(?string $str, int $maxLength): ?string
    {
        if ($str === null) {
            return null;
        }

        if (mb_strlen($str) <= $maxLength) {
            return $str;
        }

        return mb_substr($str, 0, $maxLength - 3).'...';
    }

    /**
     * Track creation action - shorter format
     */
    protected function trackCreate(string $module, string $identifier, ?string $employeeName = null): int
    {
        return $this->trackHistory(
            $module,
            'Create',
            null,
            $identifier, // Just the identifier, no "Created:" prefix
            $employeeName
        );
    }

    /**
     * Track update action
     */
    protected function trackUpdate(
        string $module,
        string $identifier,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $employeeName = null
    ): int {
        return $this->trackHistory(
            $module,
            'Update',
            $oldValue ?? $identifier,
            $newValue ?? $identifier,
            $employeeName
        );
    }

    /**
     * Track delete action
     */
    protected function trackDelete(string $module, string $identifier, ?string $employeeName = null): int
    {
        return $this->trackHistory(
            $module,
            'Delete',
            $identifier, // Just the identifier
            null,
            $employeeName
        );
    }

    /**
     * Track status change
     */
    protected function trackStatusChange(
        string $module,
        string $identifier,
        string $oldStatus,
        string $newStatus,
        ?string $employeeName = null
    ): int {
        return $this->trackHistory(
            $module,
            'Status Change',
            "{$identifier}: {$oldStatus}",
            "{$identifier}: {$newStatus}",
            $employeeName
        );
    }

    /**
     * Track location/movement change
     */
    protected function trackLocationChange(
        string $module,
        string $identifier,
        string $oldLocation,
        string $newLocation,
        ?string $employeeName = null
    ): int {
        return $this->trackHistory(
            $module,
            'Location Change',
            "{$identifier} from {$oldLocation}",
            "{$identifier} to {$newLocation}",
            $employeeName
        );
    }
}
