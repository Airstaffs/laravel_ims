/**
 * Universal Time Formatter Utility
 * Can be used in Vue components, vanilla JS, and exposed to Blade templates
 */

class TimeFormatter {
    constructor() {
        this.userTimezone = "UTC";
        this.initialized = false;
        this.geolocationEnabled = false;
    }

    /**
     * Initialize with user's timezone
     */
    async init() {
        if (this.initialized) return;

        try {
            const response = await axios.get("/api/timezone/current");
            const data = response.data;

            // Check if auto_sync is enabled
            if (data.auto_sync) {
                // Use browser's detected timezone
                this.userTimezone = this.detectBrowserTimezone();
                this.geolocationEnabled = true;
                console.log(
                    "🌍 Auto-sync enabled, using browser timezone:",
                    this.userTimezone
                );
            } else {
                // Use saved timezone
                this.userTimezone = data.usertimezone || "UTC";
                console.log("⚙️ Using saved timezone:", this.userTimezone);
            }

            this.initialized = true;
        } catch (error) {
            console.error("Error loading timezone:", error);
            // Fallback to browser timezone
            this.userTimezone = this.detectBrowserTimezone();
            console.log("⚠️ Fallback to browser timezone:", this.userTimezone);
        }
    }

    /**
     * Detect browser's timezone using Intl API
     */
    detectBrowserTimezone() {
        try {
            // Use Intl.DateTimeFormat to get the browser's timezone
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            return timezone || "UTC";
        } catch (error) {
            console.error("Error detecting browser timezone:", error);
            return "UTC";
        }
    }

    /**
     * Get timezone from geolocation (optional enhancement)
     * This requires a geolocation API service
     */
    async getTimezoneFromGeolocation() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error("Geolocation not supported"));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    try {
                        // You can use a service like GeoNames or TimeZoneDB
                        // For now, we'll just use the browser's timezone
                        const timezone = this.detectBrowserTimezone();
                        resolve(timezone);
                    } catch (error) {
                        reject(error);
                    }
                },
                (error) => {
                    console.warn("Geolocation permission denied:", error);
                    // Fallback to browser timezone
                    resolve(this.detectBrowserTimezone());
                }
            );
        });
    }

    /**
     * Set timezone manually
     */
    setTimezone(timezone) {
        this.userTimezone = timezone;
        this.initialized = true;
    }

    /**
     * Get current timezone
     */
    getTimezone() {
        return this.userTimezone;
    }

    /**
     * Format time (e.g., "03:45 PM")
     * @param {string|Date} datetime - Date string or Date object
     * @param {object} options - Optional formatting options
     * @returns {string}
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
            return date.toLocaleTimeString("en-US", mergedOptions);
        } catch (error) {
            console.error("Error formatting time:", error);
            return "";
        }
    }

    /**
     * Format time with seconds (e.g., "03:45:22 PM")
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
     * @param {string|Date} datetime
     * @param {object} options - Optional formatting options
     * @returns {string}
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
            return date.toLocaleDateString("en-US", mergedOptions);
        } catch (error) {
            console.error("Error formatting date:", error);
            return "";
        }
    }

    /**
     * Format date with full month name (e.g., "December 11, 2025")
     */
    formatDateLong(datetime) {
        return this.formatDate(datetime, {
            month: "long",
            day: "numeric",
            year: "numeric",
        });
    }

    /**
     * Format date and time (e.g., "Dec 11, 2025 03:45 PM")
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
            return date.toLocaleString("en-US", mergedOptions);
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
     * Calculate hours difference between two times
     * @param {string|Date} timeIn
     * @param {string|Date} timeOut
     * @returns {string} - Formatted as "8h 45m"
     */
    calculateHours(timeIn, timeOut) {
        if (!timeIn || !timeOut) return "Not calculated";

        try {
            const start = new Date(timeIn);
            const end = new Date(timeOut);
            const diff = end - start;

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

            return `${hours}h ${minutes}m`;
        } catch (error) {
            console.error("Error calculating hours:", error);
            return "Error";
        }
    }

    /**
     * Get current time in user's timezone
     */
    getCurrentTime() {
        const now = new Date();
        return this.formatTimeWithSeconds(now);
    }

    /**
     * Get current date in user's timezone
     */
    getCurrentDate() {
        const now = new Date();
        return this.formatDate(now);
    }

    /**
     * Get current date and time
     */
    getCurrentDateTime() {
        const now = new Date();
        return this.formatDateTime(now);
    }

    /**
     * Format relative time (e.g., "2 hours ago", "in 5 minutes")
     */
    formatRelativeTime(datetime) {
        if (!datetime) return "";

        try {
            const date = new Date(datetime);
            const now = new Date();
            const diff = now - date;
            const seconds = Math.floor(diff / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);

            if (seconds < 60) return "just now";
            if (minutes < 60)
                return `${minutes} minute${minutes > 1 ? "s" : ""} ago`;
            if (hours < 24) return `${hours} hour${hours > 1 ? "s" : ""} ago`;
            if (days < 7) return `${days} day${days > 1 ? "s" : ""} ago`;

            return this.formatDate(datetime);
        } catch (error) {
            console.error("Error formatting relative time:", error);
            return "";
        }
    }

    /**
     * Get timezone display name
     */
    getTimezoneDisplay() {
        const timezoneNames = {
            "America/Los_Angeles": "Pacific Time",
            "Asia/Manila": "Philippine Time",
            UTC: "UTC",
        };

        try {
            const now = new Date();
            const formatter = new Intl.DateTimeFormat("en-US", {
                timeZone: this.userTimezone,
                timeZoneName: "short",
            });

            const parts = formatter.formatToParts(now);
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
}

// Create singleton instance
const timeFormatter = new TimeFormatter();

// Export for ES6 modules
export default timeFormatter;

// Also make it globally available for non-module scripts
if (typeof window !== "undefined") {
    window.timeFormatter = timeFormatter;
}
