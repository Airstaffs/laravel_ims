import { createApp } from "vue";

// Import Bootstrap CSS & JS
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";

import axios from "axios";

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

// Include CSRF token in all requests
axios.defaults.withCredentials = true;

// Get CSRF token from meta tag
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common["X-CSRF-TOKEN"] = token.content;
} else {
    console.error(
        "CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token"
    );
}

// Session Management - Setup axios interceptors for automatic token refresh and session handling
axios.interceptors.response.use(
    response => response,
    async error => {
        // Check if error is due to CSRF token mismatch or session expiration
        if (error.response && (error.response.status === 419 || error.response.status === 401)) {
            console.warn('Session token issue detected:', error.response.status);
            
            try {
                // Try to refresh the CSRF token
                const response = await axios.get('/csrf-token');
                if (response.data && response.data.token) {
                    // Update the CSRF token
                    const newToken = response.data.token;
                    document.querySelector('meta[name="csrf-token"]').setAttribute('content', newToken);
                    axios.defaults.headers.common["X-CSRF-TOKEN"] = newToken;
                    
                    // Retry the original request with new token
                    const originalRequest = error.config;
                    return axios(originalRequest);
                }
            } catch (refreshError) {
                console.error('Failed to refresh token:', refreshError);
                
                // If we couldn't refresh the token, the session is likely expired
                if (window.sessionManager && typeof window.sessionManager.handleExpiry === 'function') {
                    window.sessionManager.handleExpiry();
                } else {
                    // Fallback if session manager not initialized
                    if (confirm('Your session has expired. Click OK to reload and login again.')) {
                        window.location.href = '/login';
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
    "fba": "fbashipmentinbound", // Just in case another name variant is used
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

// Simple keep-alive function if session manager isn't available
function keepSessionAlive() {
    console.log('Keeping session alive via manual ping');
    axios.get('/keep-alive')
        .then(response => {
            console.log('Session kept alive:', response.data);
        })
        .catch(error => {
            console.error('Session keep-alive failed:', error);
            if (error.response && (error.response.status === 401 || error.response.status === 419)) {
                // Session expired
                alert('Your session has expired. You will be redirected to login.');
                window.location.href = '/login';
            }
        });
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
            console.log('App mounted with component:', this.currentComponent);
        }
        
        // Setup session heartbeat
        this.startSessionHeartbeat();
        
        // Listen for visibility changes (tab switching)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                console.log('Tab visible - extending session');
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
        // Begin session heartbeat
        startSessionHeartbeat() {
            // Send heartbeat every 5 minutes (300000 ms)
            this.sessionHeartbeatTimer = setInterval(() => {
                const idleTime = Date.now() - this.lastActivityTime;
                
                // Only send heartbeat if user was active in the last 10 minutes
                if (idleTime < 10 * 60 * 1000) {
                    keepSessionAlive();
                } else {
                    console.log('User inactive for over 10 minutes, skipping heartbeat');
                }
            }, 5 * 60 * 1000); // 5 minutes
            
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
            // Record activity and extend session when navigating
            this.lastActivityTime = Date.now();
            this.extendSession();
            
            // Convert to lowercase for consistent comparison
            const navName = String(module).toLowerCase();
            
            // Get permissions from window variables
            const allowedModules = window.allowedModules ? 
                window.allowedModules.map(m => m.toLowerCase()) : [];
            const mainModule = window.mainModule ? 
                window.mainModule.toLowerCase() : '';
                
            console.log('Checking permissions:', {
                requested: navName,
                main: mainModule,
                allowed: allowedModules,
                hasAccess: navName === 'fbashipmentinbound' || 
                          navName === mainModule || 
                          allowedModules.includes(navName)
            });
        
            // Allow access if user has permission
            if (
                navName === "fbashipmentinbound" ||
                allowedModules.includes(navName) ||
                navName === mainModule
            ) {
                // Map the navigation name to component name
                const componentName = this.mapToComponentName(navName);
                console.log(`Mapping from nav "${navName}" to component "${componentName}"`);
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
                if (!this.$options.components[name]) {
                    console.warn(`Component "${name}" not registered!`);
                    return;
                }
                
                // Check if we're already on this component
                if (this.currentComponent === name) {
                    console.log(`Already on component: ${name}`);
                    return;
                }
                
                console.log(`Switching to component: ${name}`);
                
                // Set the component name
                this.currentComponent = name;
                
                // Update active state in the UI
                this.$nextTick(() => {
                    // Use the original navigation name if provided, otherwise find it
                    const navName = originalNavName || this.getNavigationName(name);
                    this.updateActiveState(navName);
                    console.log(`Component updated to: ${name}, Nav highlight: ${navName}`);
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
    
    // Initial scheduling
    scheduleSessionWarning();
    
    // Initial session keep-alive
    keepSessionAlive();
    
    // Expose for external access by components
    window.keepSessionAlive = keepSessionAlive;
});