/**
 * Vue Plugin for Time Formatter
 * Makes timeFormatter available in all Vue components
 */

import timeFormatter from "../utils/timeformatter";

export default {
    install(app) {
        // Make timeFormatter available as $timeFormatter in all components
        app.config.globalProperties.$timeFormatter = timeFormatter;

        // Add convenient methods directly to Vue instance
        app.config.globalProperties.$formatTime = (datetime, options) => {
            return timeFormatter.formatTime(datetime, options);
        };

        app.config.globalProperties.$formatDate = (datetime, options) => {
            return timeFormatter.formatDate(datetime, options);
        };

        app.config.globalProperties.$formatDateTime = (datetime, options) => {
            return timeFormatter.formatDateTime(datetime, options);
        };

        app.config.globalProperties.$calculateHours = (timeIn, timeOut) => {
            return timeFormatter.calculateHours(timeIn, timeOut);
        };

        app.config.globalProperties.$formatRelativeTime = (datetime) => {
            return timeFormatter.formatRelativeTime(datetime);
        };

        // Initialize timezone on app mount
        timeFormatter.init();
    },
};

// For Vue 2 (if needed)
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
        $calculateHours(timeIn, timeOut) {
            return timeFormatter.calculateHours(timeIn, timeOut);
        },
        $formatRelativeTime(datetime) {
            return timeFormatter.formatRelativeTime(datetime);
        },
    },
};
