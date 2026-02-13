/**
 * Universal Time Formatter Utility
 * Optimized with caching and request deduplication
 */
class TimeFormatter {
    constructor() {
        this.userTimezone = "UTC";
        this.initialized = false;
        this.geolocationEnabled = false;

        // Cache and optimization
        this.initPromise = null; // Prevent duplicate init calls
        this.timezoneCache = null;
        this.cacheExpiry = null;
        this.CACHE_DURATION = 5 * 60 * 1000; // 5 minutes cache

        // Formatter cache for performance
        this.formatterCache = new Map();

        // Auto-initialize on first use
        this.autoInitialized = false;
    }

    /**
     * Initialize with user's timezone (with deduplication)
     */
    async init() {
        // Return existing promise if init is in progress
        if (this.initPromise) {
            return this.initPromise;
        }

        // Return immediately if already initialized and cache is valid
        if (this.initialized && this.isCacheValid()) {
            return Promise.resolve();
        }

        // Create new initialization promise
        this.initPromise = this._performInit();

        try {
            await this.initPromise;
        } finally {
            this.initPromise = null; // Clear promise after completion
        }
    }

    /**
     * Check if cache is still valid
     */
    isCacheValid() {
        if (!this.cacheExpiry) return false;
        return Date.now() < this.cacheExpiry;
    }

    /**
     * Actual initialization logic
     */
    async _performInit() {
        try {
            const response = await axios.get("/api/timezone/current");
            const data = response.data;

            // Update cache
            this.timezoneCache = data;
            this.cacheExpiry = Date.now() + this.CACHE_DURATION;

            if (data.auto_sync) {
                this.userTimezone = this.detectBrowserTimezone();
                this.geolocationEnabled = true;
                console.log(
                    "🌍 Auto-sync enabled, using browser timezone:",
                    this.userTimezone,
                );
            } else {
                this.userTimezone = data.usertimezone || "UTC";
                console.log("⚙️ Using saved timezone:", this.userTimezone);
            }

            this.initialized = true;
        } catch (error) {
            console.error("Error loading timezone:", error);
            this.userTimezone = this.detectBrowserTimezone();
            console.log("⚠️ Fallback to browser timezone:", this.userTimezone);

            // Set shorter cache for error state
            this.cacheExpiry = Date.now() + 60000; // 1 minute
        }
    }

    /**
     * Lazy initialization - only init when needed
     */
    async ensureInitialized() {
        if (!this.autoInitialized) {
            this.autoInitialized = true;
            await this.init();
        }
    }

    /**
     * Detect browser's timezone (cached)
     */
    detectBrowserTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
        } catch (error) {
            console.error("Error detecting browser timezone:", error);
            return "UTC";
        }
    }

    /**
     * Set timezone manually
     */
    setTimezone(timezone) {
        this.userTimezone = timezone;
        this.initialized = true;
        this.formatterCache.clear(); // Clear formatter cache on timezone change
    }

    /**
     * Get current timezone
     */
    getTimezone() {
        return this.userTimezone;
    }

    /**
     * Get cached formatter or create new one
     */
    _getFormatter(options) {
        const key = JSON.stringify(options);

        if (!this.formatterCache.has(key)) {
            this.formatterCache.set(
                key,
                new Intl.DateTimeFormat("en-US", options),
            );
        }

        return this.formatterCache.get(key);
    }

    /**
     * Format time (e.g., "03:45 PM")
     */
    formatTime(datetime, options = {}) {
        if (!datetime) return "";

        try {
            const date = new Date(datetime);
            const defaultOptions = {
                timeZone: this.userTimezone,
                hour: "2-digit",
                minute: "2-digit",
                hour12: true,
            };

            const mergedOptions = { ...defaultOptions, ...options };
            const formatter = this._getFormatter(mergedOptions);

            return formatter.format(date);
        } catch (error) {
            console.error("Error formatting time:", error);
            return "";
        }
    }

    /**
     * Format time with seconds
     */
    formatTimeWithSeconds(datetime) {
        return this.formatTime(datetime, {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
            hour12: true,
        });
    }

    /**
     * Format date (e.g., "Dec 11, 2025")
     */
    formatDate(datetime, options = {}) {
        if (!datetime) return "";

        try {
            const date = new Date(datetime);
            const defaultOptions = {
                timeZone: this.userTimezone,
                month: "short",
                day: "numeric",
                year: "numeric",
            };

            const mergedOptions = { ...defaultOptions, ...options };
            const formatter = this._getFormatter(mergedOptions);

            return formatter.format(date);
        } catch (error) {
            console.error("Error formatting date:", error);
            return "";
        }
    }

    /**
     * Format date with full month name
     */
    formatDateLong(datetime) {
        return this.formatDate(datetime, {
            month: "long",
            day: "numeric",
            year: "numeric",
        });
    }

    /**
     * Format date and time
     */
    formatDateTime(datetime, options = {}) {
        if (!datetime) return "";

        try {
            const date = new Date(datetime);
            const defaultOptions = {
                timeZone: this.userTimezone,
                month: "short",
                day: "numeric",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
                hour12: true,
            };

            const mergedOptions = { ...defaultOptions, ...options };
            const formatter = this._getFormatter(mergedOptions);

            return formatter.format(date);
        } catch (error) {
            console.error("Error formatting datetime:", error);
            return "";
        }
    }

    /**
     * Format date and time with full details
     */
    formatDateTimeLong(datetime) {
        return this.formatDateTime(datetime, {
            weekday: "long",
            month: "long",
            day: "numeric",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            hour12: true,
        });
    }

    /**
     * Calculate hours difference (optimized)
     */
    calculateHours(timeIn, timeOut) {
        if (!timeIn || !timeOut) return "Not calculated";

        try {
            const diff = new Date(timeOut) - new Date(timeIn);
            const hours = Math.floor(diff / 3600000); // 1000 * 60 * 60
            const minutes = Math.floor((diff % 3600000) / 60000); // 1000 * 60

            return `${hours}h ${minutes}m`;
        } catch (error) {
            console.error("Error calculating hours:", error);
            return "Error";
        }
    }

    /**
     * Get current time
     */
    getCurrentTime() {
        return this.formatTimeWithSeconds(new Date());
    }

    /**
     * Get current date
     */
    getCurrentDate() {
        return this.formatDate(new Date());
    }

    /**
     * Get current date and time
     */
    getCurrentDateTime() {
        return this.formatDateTime(new Date());
    }

    /**
     * Format relative time (optimized)
     */
    formatRelativeTime(datetime) {
        if (!datetime) return "";

        try {
            const diff = Date.now() - new Date(datetime);
            const seconds = Math.floor(diff / 1000);

            if (seconds < 60) return "just now";

            const minutes = Math.floor(seconds / 60);
            if (minutes < 60)
                return `${minutes} minute${minutes > 1 ? "s" : ""} ago`;

            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `${hours} hour${hours > 1 ? "s" : ""} ago`;

            const days = Math.floor(hours / 24);
            if (days < 7) return `${days} day${days > 1 ? "s" : ""} ago`;

            return this.formatDate(datetime);
        } catch (error) {
            console.error("Error formatting relative time:", error);
            return "";
        }
    }

    /**
     * Get timezone display name (cached)
     */
    getTimezoneDisplay() {
        const timezoneNames = {
            "America/Los_Angeles": "Pacific Time",
            "Asia/Manila": "Philippine Time",
            UTC: "UTC",
        };

        try {
            const formatter = this._getFormatter({
                timeZone: this.userTimezone,
                timeZoneName: "short",
            });

            const parts = formatter.formatToParts(new Date());
            const timeZoneName =
                parts.find((part) => part.type === "timeZoneName")?.value || "";
            const friendlyName =
                timezoneNames[this.userTimezone] || this.userTimezone;

            return `${friendlyName} (${timeZoneName})`;
        } catch (error) {
            console.error("Error getting timezone display:", error);
            return this.userTimezone;
        }
    }

    /**
     * Clear cache manually (useful for testing or forced refresh)
     */
    clearCache() {
        this.timezoneCache = null;
        this.cacheExpiry = null;
        this.formatterCache.clear();
        this.initialized = false;
        this.autoInitialized = false;
    }

    /**
     * Refresh timezone data from server
     */
    async refresh() {
        this.clearCache();
        await this.init();
    }
}

// Create singleton instance
const timeFormatter = new TimeFormatter();

// Export for ES6 modules
export default timeFormatter;

// Global availability
if (typeof window !== "undefined") {
    window.timeFormatter = timeFormatter;
}
