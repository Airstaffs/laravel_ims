/**
 * Vue Plugin for Time Formatter
 * Makes timeFormatter available in all Vue components
 */

import timeFormatter from "../utils/timeFormatter";

export default {
    async install(app, options = {}) {
        // Initialize timezone once during plugin installation
        try {
            await timeFormatter.init();
            console.log("✅ TimeFormatter initialized successfully");
        } catch (error) {
            console.error("❌ TimeFormatter initialization failed:", error);
            // Continue anyway - will use fallback timezone
        }

        // Make timeFormatter instance available
        app.config.globalProperties.$timeFormatter = timeFormatter;

        // Add convenient shorthand methods
        app.config.globalProperties.$formatTime = (datetime, options) => {
            return timeFormatter.formatTime(datetime, options);
        };

        app.config.globalProperties.$formatDate = (datetime, options) => {
            return timeFormatter.formatDate(datetime, options);
        };

        app.config.globalProperties.$formatDateTime = (datetime, options) => {
            return timeFormatter.formatDateTime(datetime, options);
        };

        app.config.globalProperties.$formatDateLong = (datetime) => {
            return timeFormatter.formatDateLong(datetime);
        };

        app.config.globalProperties.$formatDateTimeLong = (datetime) => {
            return timeFormatter.formatDateTimeLong(datetime);
        };

        app.config.globalProperties.$formatTimeWithSeconds = (datetime) => {
            return timeFormatter.formatTimeWithSeconds(datetime);
        };

        app.config.globalProperties.$calculateHours = (timeIn, timeOut) => {
            return timeFormatter.calculateHours(timeIn, timeOut);
        };

        app.config.globalProperties.$formatRelativeTime = (datetime) => {
            return timeFormatter.formatRelativeTime(datetime);
        };

        app.config.globalProperties.$getCurrentTime = () => {
            return timeFormatter.getCurrentTime();
        };

        app.config.globalProperties.$getCurrentDate = () => {
            return timeFormatter.getCurrentDate();
        };

        app.config.globalProperties.$getCurrentDateTime = () => {
            return timeFormatter.getCurrentDateTime();
        };

        app.config.globalProperties.$getTimezoneDisplay = () => {
            return timeFormatter.getTimezoneDisplay();
        };

        // Provide timeFormatter for Composition API
        app.provide("timeFormatter", timeFormatter);
    },
};

// Composition API composable
export function useTimeFormatter() {
    // For Composition API users
    return {
        timeFormatter,
        formatTime: (datetime, options) =>
            timeFormatter.formatTime(datetime, options),
        formatDate: (datetime, options) =>
            timeFormatter.formatDate(datetime, options),
        formatDateTime: (datetime, options) =>
            timeFormatter.formatDateTime(datetime, options),
        formatDateLong: (datetime) => timeFormatter.formatDateLong(datetime),
        formatDateTimeLong: (datetime) =>
            timeFormatter.formatDateTimeLong(datetime),
        formatTimeWithSeconds: (datetime) =>
            timeFormatter.formatTimeWithSeconds(datetime),
        calculateHours: (timeIn, timeOut) =>
            timeFormatter.calculateHours(timeIn, timeOut),
        formatRelativeTime: (datetime) =>
            timeFormatter.formatRelativeTime(datetime),
        getCurrentTime: () => timeFormatter.getCurrentTime(),
        getCurrentDate: () => timeFormatter.getCurrentDate(),
        getCurrentDateTime: () => timeFormatter.getCurrentDateTime(),
        getTimezoneDisplay: () => timeFormatter.getTimezoneDisplay(),
    };
}

// Vue 2 Mixin (if needed)
export const timeFormatterMixin = {
    beforeCreate() {
        if (!this.$timeFormatter) {
            this.$timeFormatter = timeFormatter;
        }
    },
    methods: {
        $formatTime(datetime, options) {
            return timeFormatter.formatTime(datetime, options);
        },
        $formatDate(datetime, options) {
            return timeFormatter.formatDate(datetime, options);
        },
        $formatDateTime(datetime, options) {
            return timeFormatter.formatDateTime(datetime, options);
        },
        $formatDateLong(datetime) {
            return timeFormatter.formatDateLong(datetime);
        },
        $formatDateTimeLong(datetime) {
            return timeFormatter.formatDateTimeLong(datetime);
        },
        $formatTimeWithSeconds(datetime) {
            return timeFormatter.formatTimeWithSeconds(datetime);
        },
        $calculateHours(timeIn, timeOut) {
            return timeFormatter.calculateHours(timeIn, timeOut);
        },
        $formatRelativeTime(datetime) {
            return timeFormatter.formatRelativeTime(datetime);
        },
        $getCurrentTime() {
            return timeFormatter.getCurrentTime();
        },
        $getCurrentDate() {
            return timeFormatter.getCurrentDate();
        },
        $getCurrentDateTime() {
            return timeFormatter.getCurrentDateTime();
        },
        $getTimezoneDisplay() {
            return timeFormatter.getTimezoneDisplay();
        },
    },
};
