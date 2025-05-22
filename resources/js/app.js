import { createApp } from "vue";

// Import Bootstrap CSS & JS
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";

import axios from "axios";

import "../css/app.css";

// Import components
import Stockroom from "./page/stockroom/stockroom.vue";
import Cleaning from "./page/cleaning/cleaning.vue";
import FNSKU from "./page/fnsku/fnsku.vue";
import Labeling from "./page/labeling/labeling.vue";
import Order from "./page/orders/orders.vue";
import Packing from "./page/packing/packing.vue";
import Receiving from "./page/receiving/receiving.vue";
import Testing from "./page/testing/testing.vue";
import Searching from "./page/searching/searching.vue";
import Unreceived from "./page/unreceived/unreceived.vue";
import Validation from "./page/validation/validation.vue";
import ProductionArea from "./page/production/production.vue";
import ReturnScanner from "./page/returnScanner/returnscanner.vue";
import FbaInboundShipment from "./components/Stockroom/fba_inbound_shipment.vue";
import FBMorders from "./page/fbmOrders/fbmOrders.vue";

// Session management configuration
const SESSION_DEBUG = true; // Set to false in production
const SESSION_HEARTBEAT_INTERVAL = 5 * 60 * 1000; // 5 minutes
const SESSION_ALWAYS_REFRESH = true; // Always refresh even during inactivity

// manual routing
const asyncComponentMap = {
    'printcustominvoice': () => import('./page/stockroom/print_invoice/print_custom_invoice.vue')
};

// Session logging helper
function logSession(message, data) {
    if (SESSION_DEBUG) {
        if (data) {
            console.log(`[Session] ${message}`, data);
        } else {
            console.log(`[Session] ${message}`);
        }
    }
}

// Include CSRF token in all requests
axios.defaults.withCredentials = true;

// Get CSRF token from meta tag
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common["X-CSRF-TOKEN"] = token.content;
    // Store in localStorage as backup
    localStorage.setItem('csrf_token_backup', token.content);
} else {
    console.error(
        "CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token"
    );
}

// Get the latest token from any available source
function getLatestToken() {
    // Try meta tag first
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.content;
    // Fall back to localStorage if meta tag is missing
    return metaToken || localStorage.getItem('csrf_token_backup');
}

// Session Management - Setup axios interceptors for automatic token refresh and session handling
axios.interceptors.response.use(
    response => response,
    async error => {
        // Check if error is due to CSRF token mismatch or session expiration
        if (error.response && (error.response.status === 419 || error.response.status === 401)) {
            logSession('Session token issue detected:', error.response.status);

            try {
                // Try to refresh the CSRF token
                const response = await axios.get('/csrf-token');
                if (response.data && response.data.token) {
                    // Update the CSRF token
                    const newToken = response.data.token;
                    document.querySelector('meta[name="csrf-token"]').setAttribute('content', newToken);
                    axios.defaults.headers.common["X-CSRF-TOKEN"] = newToken;

                    // Store in localStorage as backup
                    localStorage.setItem('csrf_token_backup', newToken);
                    localStorage.setItem('last_token_refresh', Date.now().toString());

                    logSession('Token refreshed successfully, retrying request');

                    // Retry the original request with new token
                    const originalRequest = error.config;
                    return axios(originalRequest);
                }
            } catch (refreshError) {
                logSession('Failed to refresh token:', refreshError);

                // If we couldn't refresh the token, the session is likely expired
                if (window.sessionManager && typeof window.sessionManager.handleExpiry === 'function') {
                    window.sessionManager.handleExpiry();
                } else {
                    // Check if we've tried refreshing recently to avoid reload loops
                    const lastRefresh = localStorage.getItem('last_refresh_attempt');
                    const now = Date.now();

                    if (!lastRefresh || (now - parseInt(lastRefresh)) > 30000) { // 30 seconds
                        localStorage.setItem('last_refresh_attempt', now.toString());

                        // Fallback if session manager not initialized
                        if (confirm('Your session has expired. Click OK to reload and login again.')) {
                            window.location.href = '/login';
                        }
                    }
                }
            }
        }

        return Promise.reject(error);
    }
);

// Create component mapping for navigation to component names
const componentMapping = {
    // Define any special cases here (nav name -> component name)
    "received": "receiving",
    "return scanner": "returnscanner",
    "returnscanner": "returnscanner", // Add explicit mapping
    "return_scanner": "returnscanner",
    "order": "order",
    "fbashipmentinbound": "fbashipmentinbound",
    "fbashipment": "fbashipmentinbound", // Just in case another name variant is used
    "fba": "fbashipmentinbound", 
    "fbm order":"fbmorder",
    "FBM Order":"fbmorder"// Just in case another name variant is used
    // Add more mappings as needed
};

// Session management mixin
const sessionMixin = {
    mounted() {
        // Start tracking activity in this component
        this.$nextTick(() => {
            if (this.$el && this.$el.addEventListener) {
                const activityHandler = () => {
                    if (window.sessionManager) {
                        window.sessionManager.activityDetected();
                    } else {
                        keepSessionAlive();
                    }
                };

                // Add activity listeners to component element
                this.$el.addEventListener('click', activityHandler);
                this.$el.addEventListener('keydown', activityHandler);

                // Store for cleanup
                this._sessionActivityHandler = activityHandler;
                this._sessionElement = this.$el;
            }
        });
    },
    beforeUnmount() {
        // Clean up event listeners
        if (this._sessionActivityHandler && this._sessionElement) {
            this._sessionElement.removeEventListener('click', this._sessionActivityHandler);
            this._sessionElement.removeEventListener('keydown', this._sessionActivityHandler);
        }
    },
    methods: {
        // Session extending helper
        extendSession() {
            if (window.sessionManager) {
                window.sessionManager.extendSession();
            } else {
                keepSessionAlive();
            }
        }
    }
};

// Enhanced keep-alive function with token refresh and robust error handling
function keepSessionAlive() {
    logSession('Keeping session alive via manual ping');

    // First try to refresh the CSRF token
    axios.get('/csrf-token')
        .then(tokenResponse => {
            if (tokenResponse.data && tokenResponse.data.token) {
                // Update token
                const newToken = tokenResponse.data.token;
                document.querySelector('meta[name="csrf-token"]').setAttribute('content', newToken);
                axios.defaults.headers.common["X-CSRF-TOKEN"] = newToken;

                // Store in localStorage as backup
                localStorage.setItem('csrf_token_backup', newToken);
                localStorage.setItem('last_token_refresh', Date.now().toString());

                logSession('Token refreshed, sending keep-alive request');

                // Now send the keep-alive request with the fresh token
                return axios.get('/keep-alive', {
                    headers: {
                        'X-CSRF-TOKEN': newToken
                    }
                });
            }

            logSession('No token in response, falling back to regular keep-alive');
            return axios.get('/keep-alive');
        })
        .then(response => {
            logSession('Session kept alive successfully', response.data);

            // Update session status indicators
            updateSessionStatus('active');

            // Update session timestamp in localStorage
            localStorage.setItem('last_session_ping', Date.now().toString());
        })
        .catch(error => {
            console.error('Session keep-alive failed:', error);
            updateSessionStatus('warning');

            // If this is a token error, try one more approach
            if (error.response && (error.response.status === 401 || error.response.status === 419)) {
                // Check if we've already tried refreshing recently to avoid reload loops
                const lastRefresh = localStorage.getItem('last_refresh_attempt');
                const now = Date.now();

                if (!lastRefresh || (now - parseInt(lastRefresh)) > 30000) { // 30 seconds
                    localStorage.setItem('last_refresh_attempt', now.toString());

                    logSession('Session expired despite refresh attempt, will try page reload');

                    // Only reload if confirmed or page is not visible (background tab)
                    if (document.visibilityState !== 'visible' || confirm('Session expired. Reload page to refresh?')) {
                        window.location.reload();
                    }
                }
            }
        });
}

// Update session status indicator (if present in DOM)
function updateSessionStatus(status) {
    const indicator = document.getElementById('session-status');
    if (indicator) {
        indicator.className = `session-indicator session-${status}`;
        indicator.title = `Session: ${status}`;
    }
}

// Create Vue app
const app = createApp({
    mixins: [sessionMixin], // Apply session management to main app
    data() {
        return {
            currentComponent: window.defaultComponent,
            collapses: {},
            lastActivityTime: Date.now(),
            sessionHeartbeatTimer: null
        };
    },
    mounted() {
        if (this.currentComponent) {
            this.safeComponentUpdate(this.currentComponent);
            logSession('App mounted with component:', this.currentComponent);
        }

        // Setup session heartbeat
        this.startSessionHeartbeat();

        // Listen for visibility changes (tab switching)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                logSession('Tab visible - extending session');
                this.extendSession();
            }
        });
    },
    beforeUnmount() {
        // Clean up heartbeat timer
        if (this.sessionHeartbeatTimer) {
            clearInterval(this.sessionHeartbeatTimer);
        }
    },
    methods: {
        // Begin session heartbeat - modified to always refresh
        startSessionHeartbeat() {
            // Send heartbeat every 5 minutes (300000 ms)
            this.sessionHeartbeatTimer = setInterval(() => {
                if (SESSION_ALWAYS_REFRESH) {
                    // Always send heartbeat, even during inactivity
                    keepSessionAlive();
                    // Record this as activity to maintain continuous session
                    this.lastActivityTime = Date.now();
                    logSession('Heartbeat sent (continuous mode)');
                } else {
                    // Original behavior - only if active recently
                    const idleTime = Date.now() - this.lastActivityTime;
                    if (idleTime < 10 * 60 * 1000) {
                        keepSessionAlive();
                    } else {
                        logSession('User inactive for over 10 minutes, skipping heartbeat');
                    }
                }
            }, SESSION_HEARTBEAT_INTERVAL);

            // Initial heartbeat
            keepSessionAlive();
        },

        // Map a navigation name to its component name
        mapToComponentName(navName) {
            // Record activity
            this.lastActivityTime = Date.now();

            // First check our explicit mappings
            if (componentMapping[navName]) {
                return componentMapping[navName];
            }

            // Handle common transformations automatically
            // E.g., "production area" → "productionarea"
            const transformed = navName.replace(/\s+/g, '').toLowerCase();

            // Check if we've got a registered component with this name
            if (this.$options.components[transformed]) {
                return transformed;
            }

            // If nothing else works, return the original name
            return navName;
        },

        // Get navigation name from component name (for active state)
        getNavigationName(componentName) {
            // Look through our mapping and find the navigation name
            for (const [navName, compName] of Object.entries(componentMapping)) {
                if (compName === componentName) {
                    return navName;
                }
            }

            // If no mapping found, return the component name
            return componentName;
        },

        loadContent(module) {
            this.lastActivityTime = Date.now();
            this.extendSession();

            const navName = String(module).toLowerCase();
            const allowedModules = window.allowedModules ? window.allowedModules.map(m => m.toLowerCase()) : [];
            const mainModule = window.mainModule ? window.mainModule.toLowerCase() : '';
            const customModules = window.customModules ? window.customModules.map(m => m.toLowerCase()) : [];

            const hasAccess = 
                navName === "fbashipmentinbound" ||
                allowedModules.includes(navName) ||
                navName === mainModule ||
                customModules.includes(navName); // ✅ NEW check

            logSession('Checking permissions:', {
                requested: navName,
                main: mainModule,
                allowed: allowedModules,
                custom: customModules,
                hasAccess
            });

            if (hasAccess) {
                const componentName = this.mapToComponentName(navName);
                logSession(`Mapping from nav "${navName}" to component "${componentName}"`);
                this.safeComponentUpdate(componentName, navName);
            } else {
                alert("You do not have permission to access this module.");
            }
        },

        // A safer component update method
        safeComponentUpdate(componentName, originalNavName = null) {
            try {
                // Record activity when switching components
                this.lastActivityTime = Date.now();

                // Make sure componentName is a string and lowercase
                const name = String(componentName).toLowerCase();

                // Check if we've got a registered component with this name
                /*
                if (!this.$options.components[name]) {
                    console.warn(`Component "${name}" not registered!`);
                    return;
                }
                */

                if (!this.$options.components[name]) {
                    if (asyncComponentMap[name]) {
                        logSession(`Loading async component: ${name}`);
                        asyncComponentMap[name]().then(module => {
                            this.$options.components[name] = module.default;
                            this.safeComponentUpdate(name); // Try again after registering
                        }).catch(err => {
                            console.error(`Failed to load async component "${name}":`, err);
                        });
                        return;
                    }

                    console.warn(`Component "${name}" not registered and no async loader found.`);
                    return;
                }

                // Check if we're already on this component
                if (this.currentComponent === name) {
                    logSession(`Already on component: ${name}`);
                    return;
                }

                logSession(`Switching to component: ${name}`);

                // Set the component name
                this.currentComponent = name;

                // Update active state in the UI
                this.$nextTick(() => {
                    // Use the original navigation name if provided, otherwise find it
                    const navName = originalNavName || this.getNavigationName(name);
                    this.updateActiveState(navName);
                    logSession(`Component updated to: ${name}, Nav highlight: ${navName}`);
                });
            } catch (err) {
                console.error('Error switching component:', err);
            }
        },

        // For backward compatibility
        forceUpdate(moduleName) {
            const navName = String(moduleName).toLowerCase();
            const componentName = this.mapToComponentName(navName);
            this.safeComponentUpdate(componentName, navName);
        },

        updateActiveState(moduleName) {
            document.querySelectorAll(".nav .nav-link").forEach((link) => {
                const linkModule = link.getAttribute("data-module");
                if (linkModule && linkModule.toLowerCase() === moduleName.toLowerCase()) {
                    link.classList.add("active");
                } else {
                    link.classList.remove("active");
                }
            });
        },

        // Bootstrap Collapse Logic
        toggleCollapse(id) {
            // Record user activity
            this.lastActivityTime = Date.now();

            const element = document.getElementById(id);
            if (!element) return;

            if (!this.collapses[id]) {
                this.collapses[id] = new bootstrap.Collapse(element, {
                    toggle: false,
                });
            }
            this.collapses[id].toggle();
        },
    },
    components: {
        order: Order,
        labeling: Labeling,
        unreceived: Unreceived,
        cleaning: Cleaning,
        packing: Packing,
        receiving: Receiving,
        stockroom: Stockroom,
        testing: Testing,
        validation: Validation,
        productionarea: ProductionArea,
        returnscanner: ReturnScanner,
        fnsku: FNSKU,
        fbashipmentinbound: FbaInboundShipment,
        fbmorder: FBMorders,
    },
});

// Apply session mixin to all components globally
app.mixin({
    mounted() {
        // Track user activity in all components
        this.$nextTick(() => {
            const eventHandlers = ['click', 'keydown', 'mousedown', 'touchstart'];
            const activityHandler = () => {
                // Update last activity time in the main app instance
                if (window.appInstance && window.appInstance.lastActivityTime) {
                    window.appInstance.lastActivityTime = Date.now();
                }
            };

            // Only add listeners if we have an actual DOM element
            if (this.$el && this.$el.addEventListener) {
                eventHandlers.forEach(event => {
                    this.$el.addEventListener(event, activityHandler, { passive: true });
                });

                // Store handlers for cleanup
                this._sessionEvents = eventHandlers;
                this._sessionHandler = activityHandler;
                this._sessionElement = this.$el;
            }
        });
    },
    beforeUnmount() {
        // Clean up event listeners
        if (this._sessionHandler && this._sessionElement && this._sessionEvents) {
            this._sessionEvents.forEach(event => {
                this._sessionElement.removeEventListener(event, this._sessionHandler);
            });
        }
    }
});

// Mount the main app
window.appInstance = app.mount("#app");

// Expose component loading function globally
window.loadContent = (module) => {
    if (window.appInstance) {
        window.appInstance.loadContent(module);
    }
};

// For backward compatibility
window.forceComponentUpdate = (module) => {
    if (window.appInstance) {
        window.appInstance.forceUpdate(module);
    }
};

// Create a separate Vue instance for Searching Component
const searchApp = createApp({
    mixins: [sessionMixin], // Apply session management to search app
    components: {
        searching: Searching,
    },
    mounted() {
        // Listen for search interactions to keep session alive
        this.$nextTick(() => {
            const searchElement = document.getElementById('appsearch');
            if (searchElement) {
                ['input', 'focus', 'click'].forEach(event => {
                    searchElement.addEventListener(event, () => {
                        if (window.appInstance && window.appInstance.lastActivityTime) {
                            window.appInstance.lastActivityTime = Date.now();
                        }
                    });
                });
            }
        });
    }
});

// Mount the Searching app separately
searchApp.mount("#appsearch");

// Initialize session keep-alive mechanism outside Vue
document.addEventListener('DOMContentLoaded', function() {
    // Create session status indicator in footer if needed
    createSessionIndicator();

    // Global activity monitoring for the entire page
    const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];

    function globalActivityHandler() {
        if (window.appInstance && window.appInstance.lastActivityTime) {
            window.appInstance.lastActivityTime = Date.now();
        }
    }

    // Add listeners to document
    activityEvents.forEach(event => {
        document.addEventListener(event, globalActivityHandler, { passive: true });
    });

    // Session warning mechanism
    let sessionWarningTimeout = null;
    const sessionLifetime = 8 * 60 * 60 * 1000; // 8 hours in milliseconds
    const warningTime = 30 * 60 * 1000; // 30 minutes before expiry

    function scheduleSessionWarning() {
        if (sessionWarningTimeout) {
            clearTimeout(sessionWarningTimeout);
        }

        sessionWarningTimeout = setTimeout(() => {
            showSessionWarning();
        }, sessionLifetime - warningTime);
    }

    function showSessionWarning() {
        // If user is still active, just reschedule the warning
        if (window.appInstance &&
            window.appInstance.lastActivityTime &&
            (Date.now() - window.appInstance.lastActivityTime) < warningTime) {
            keepSessionAlive();
            scheduleSessionWarning();
            return;
        }

        // Check if Bootstrap is available for modal
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
            let warningModal = document.getElementById('session-warning-modal');

            if (!warningModal) {
                // Create modal element if it doesn't exist
                const modalHTML = `
                <div class="modal fade" id="session-warning-modal" tabindex="-1" aria-labelledby="sessionWarningModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title" id="sessionWarningModalLabel">Session Expiring Soon</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Your session will expire soon due to inactivity.</p>
                                <p>Do you want to continue working?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Log Out</button>
                                <button type="button" class="btn btn-primary" id="extend-session-btn">Continue Working</button>
                            </div>
                        </div>
                    </div>
                </div>`;

                const modalContainer = document.createElement('div');
                modalContainer.innerHTML = modalHTML;
                document.body.appendChild(modalContainer.firstChild);

                warningModal = document.getElementById('session-warning-modal');

                // Add event listener for continue button
                document.getElementById('extend-session-btn').addEventListener('click', () => {
                    keepSessionAlive();
                    scheduleSessionWarning();

                    const modalInstance = bootstrap.Modal.getInstance(warningModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                });
            }

            // Show the modal
            const modal = new bootstrap.Modal(warningModal);
            modal.show();
        } else {
            // Fallback if Bootstrap is not available
            const response = confirm("Your session will expire soon due to inactivity. Click OK to continue working.");
            if (response) {
                keepSessionAlive();
                scheduleSessionWarning();
            }
        }
    }

    // Create optional session indicator
    function createSessionIndicator() {
        if (SESSION_DEBUG) {
            // Only create in debug mode
            const indicator = document.createElement('div');
            indicator.id = 'session-status';
            indicator.className = 'session-indicator session-init';
            indicator.title = 'Session Status';
            indicator.innerHTML = '●';
            indicator.style.cssText = `
                position: fixed;
                bottom: 10px;
                right: 10px;
                z-index: 9999;
                font-size: 14px;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                text-align: center;
                line-height: 20px;
                cursor: pointer;
                opacity: 0.8;
                color: transparent;
                text-shadow: 0 0 0 #fff;
                background-color: #aaa;
            `;

            // Add styles for different states
            const style = document.createElement('style');
            style.textContent = `
                .session-indicator.session-active { background-color: #28a745; }
                .session-indicator.session-warning { background-color: #ffc107; }
                .session-indicator.session-error { background-color: #dc3545; }
            `;
            document.head.appendChild(style);

            // Add click handler to force session refresh
            indicator.addEventListener('click', () => {
                keepSessionAlive();
                indicator.classList.add('session-active');
                setTimeout(() => {
                    indicator.classList.remove('session-active');
                }, 1000);
            });

            document.body.appendChild(indicator);
        }
    }

    // Initial scheduling
    scheduleSessionWarning();

    // Initial session keep-alive
    keepSessionAlive();

    // Expose for external access by components
    window.keepSessionAlive = keepSessionAlive;

    // Start background periodic token refresh for very long idle periods
    // This is in addition to the main heartbeat and ensures tokens stay fresh even during long inactivity
    if (SESSION_ALWAYS_REFRESH) {
        setInterval(() => {
            logSession('Background token refresh running');

            // Try to silently refresh the token
            fetch('/csrf-token', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.token) {
                    // Update token in DOM
                    const metaToken = document.querySelector('meta[name="csrf-token"]');
                    if (metaToken) {
                        metaToken.setAttribute('content', data.token);
                    }

                    // Update axios headers
                    axios.defaults.headers.common["X-CSRF-TOKEN"] = data.token;

                    // Store in localStorage
                    localStorage.setItem('csrf_token_backup', data.token);
                    localStorage.setItem('last_background_refresh', Date.now().toString());

                    logSession('Background token refresh successful');
                }
            })
            .catch(error => {
                console.error('Background token refresh failed:', error);
            });
        }, 20 * 60 * 1000); // Every 20 minutes
    }
});
