import { createApp } from "vue";

import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";

import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";

// Add PrimeVue CSS
import "primeicons/primeicons.css";

import axios from "axios";

import Swal from "sweetalert2";
window.Swal = Swal;

// ============================================
// CONFIGURATION
// ============================================

const CSRF_CONFIG = {
    DEBUG: import.meta.env.DEV ?? false,
    MAX_RETRY_ATTEMPTS: 2,
    TOKEN_REFRESH_ENDPOINT: "/csrf-token",
    KEEP_ALIVE_ENDPOINT: "/keep-alive",
};

const SESSION_DEBUG = import.meta.env.DEV ?? false;
const SESSION_HEARTBEAT_INTERVAL = 5 * 60 * 1000; // 5 minutes
const SESSION_ALWAYS_REFRESH = true;

// ⭐ NEW: Idle detection configuration
const IDLE_CONFIG = {
    SESSION_LIFETIME: 8 * 60 * 60 * 1000, // 8 hours
    WARNING_BEFORE_EXPIRY: 10 * 60 * 1000, // 10 minutes
    MAX_IDLE_TIME: 30 * 60 * 1000, // 30 minutes = idle
    TOKEN_REFRESH_ON_ACTIVITY: true, // Refresh token when user returns
};

// ============================================
// LOGGING HELPERS
// ============================================

function logCsrf(message, data) {
    if (CSRF_CONFIG.DEBUG) {
        console.log(`[CSRF] ${message}`, data || "");
    }
}

function logSession(message, data) {
    if (SESSION_DEBUG) {
        if (data) {
            console.log(`[Session] ${message}`, data);
        } else {
            console.log(`[Session] ${message}`);
        }
    }
}

// ============================================
// AXIOS INITIALIZATION
// ============================================

window.axios = axios;
axios.defaults.withCredentials = true;
axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

const tokenMeta = document.head.querySelector('meta[name="csrf-token"]');
if (tokenMeta) {
    const token = tokenMeta.content;
    axios.defaults.headers.common["X-CSRF-TOKEN"] = token;
    localStorage.setItem("csrf_token_backup", token);
    localStorage.setItem("csrf_token_timestamp", Date.now().toString());
    logCsrf("Initial CSRF token loaded");
} else {
    console.error("❌ CSRF meta tag missing from page head");
}

// ============================================
// CSRF TOKEN MANAGEMENT
// ============================================

let csrfRefreshPromise = null;
let refreshAttempts = 0;

async function refreshCsrf() {
    if (csrfRefreshPromise) {
        logCsrf("Using existing refresh promise");
        return csrfRefreshPromise;
    }

    if (refreshAttempts >= CSRF_CONFIG.MAX_RETRY_ATTEMPTS) {
        console.error("❌ Max CSRF refresh attempts reached");
        refreshAttempts = 0;
        return Promise.reject(new Error("Max refresh attempts exceeded"));
    }

    refreshAttempts++;
    logCsrf(`Refreshing CSRF token (attempt ${refreshAttempts})`);

    csrfRefreshPromise = axios
        .get(CSRF_CONFIG.TOKEN_REFRESH_ENDPOINT, {
            params: { t: Date.now() },
            headers: {
                "Cache-Control": "no-cache",
                Pragma: "no-cache",
            },
        })
        .then(({ data }) => {
            const newToken = data?.token;
            if (!newToken) {
                throw new Error("No token in refresh response");
            }

            axios.defaults.headers.common["X-CSRF-TOKEN"] = newToken;
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.setAttribute("content", newToken);

            localStorage.setItem("csrf_token_backup", newToken);
            localStorage.setItem("csrf_token_timestamp", Date.now().toString());

            logCsrf("✅ CSRF token refreshed successfully");
            refreshAttempts = 0;
            return newToken;
        })
        .catch((error) => {
            console.error("❌ CSRF token refresh failed:", error);
            throw error;
        })
        .finally(() => {
            csrfRefreshPromise = null;
        });

    return csrfRefreshPromise;
}

function validateCsrfToken() {
    const metaToken = document.querySelector(
        'meta[name="csrf-token"]'
    )?.content;
    const backupToken = localStorage.getItem("csrf_token_backup");
    const axiosToken = axios.defaults.headers.common["X-CSRF-TOKEN"];

    const tokens = {
        meta: metaToken,
        localStorage: backupToken,
        axios: axiosToken,
    };
    const allMatch = metaToken === backupToken && backupToken === axiosToken;

    if (!allMatch) {
        console.warn("⚠️ CSRF tokens out of sync");
    }

    return { valid: !!metaToken, synchronized: allMatch, tokens };
}

function showSessionExpiredNotification() {
    const lastShown = localStorage.getItem("last_session_expired_notification");
    const now = Date.now();

    if (lastShown && now - parseInt(lastShown) < 30000) {
        return;
    }

    localStorage.setItem("last_session_expired_notification", now.toString());

    if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
        let modal = document.getElementById("csrf-expired-modal");

        if (!modal) {
            const modalHTML = `
                <div class="modal fade" id="csrf-expired-modal" tabindex="-1" data-bs-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">Session Expired</h5>
                            </div>
                            <div class="modal-body">
                                <p>Your session has expired. Please refresh the page to continue.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" onclick="window.location.reload()">
                                    Refresh Page
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML("beforeend", modalHTML);
            modal = document.getElementById("csrf-expired-modal");
        }

        new bootstrap.Modal(modal).show();
    } else {
        if (
            confirm("Your session has expired. Click OK to refresh the page.")
        ) {
            window.location.reload();
        }
    }
}

function startTokenHealthCheck(intervalMinutes = 10) {
    setInterval(() => {
        const validation = validateCsrfToken();

        if (!validation.valid) {
            console.error("❌ CSRF token validation failed");
            refreshCsrf().catch(console.error);
        } else if (!validation.synchronized) {
            console.warn("⚠️ CSRF tokens out of sync, synchronizing...");
            const metaToken = document.querySelector(
                'meta[name="csrf-token"]'
            )?.content;
            if (metaToken) {
                axios.defaults.headers.common["X-CSRF-TOKEN"] = metaToken;
                localStorage.setItem("csrf_token_backup", metaToken);
            }
        }
    }, intervalMinutes * 60 * 1000);
}

// ============================================
// AXIOS INTERCEPTORS
// ============================================

axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;
        const status = error.response?.status;

        if ((status === 419 || status === 401) && originalRequest) {
            originalRequest.__retryCount = originalRequest.__retryCount || 0;

            if (
                originalRequest.__retryCount >= CSRF_CONFIG.MAX_RETRY_ATTEMPTS
            ) {
                logCsrf(`❌ Max retries exceeded for request`);
                console.error(
                    "Session appears to be invalid. Consider reloading the page."
                );

                if (
                    status === 419 &&
                    !sessionStorage.getItem("csrf_error_shown")
                ) {
                    sessionStorage.setItem("csrf_error_shown", "true");
                    setTimeout(() => {
                        if (
                            confirm(
                                "Your session may have expired. Would you like to reload the page?"
                            )
                        ) {
                            window.location.reload();
                        }
                    }, 500);
                }

                return Promise.reject(error);
            }

            originalRequest.__retryCount++;
            logCsrf(
                `Retrying request (attempt ${originalRequest.__retryCount})`
            );

            try {
                if (originalRequest.__retryCount > 1) {
                    await new Promise((resolve) => setTimeout(resolve, 500));
                }

                await refreshCsrf();
                originalRequest.headers["X-CSRF-TOKEN"] =
                    axios.defaults.headers.common["X-CSRF-TOKEN"];

                return axios(originalRequest);
            } catch (refreshError) {
                console.error(
                    "❌ Failed to refresh token and retry request:",
                    refreshError
                );
                return Promise.reject(error);
            }
        }

        return Promise.reject(error);
    }
);

axios.interceptors.request.use(
    (config) => {
        const hasToken =
            config.headers?.["X-CSRF-TOKEN"] ||
            config.headers?.common?.["X-CSRF-TOKEN"];

        if (!hasToken) {
            const metaToken = document.querySelector(
                'meta[name="csrf-token"]'
            )?.content;
            const backupToken = localStorage.getItem("csrf_token_backup");
            const token = metaToken || backupToken;

            if (token) {
                config.headers = config.headers || {};
                config.headers["X-CSRF-TOKEN"] = token;
            } else {
                console.warn("⚠️ No CSRF token available for request");
            }
        }

        return config;
    },
    (error) => Promise.reject(error)
);

// ============================================
// ⭐ NEW: IDLE DETECTION & MANAGEMENT
// ============================================

let lastActivityTime = Date.now();
let lastHeartbeat = Date.now();
let sessionStartTime = Date.now();
let heartbeatTimer = null;
let isUserIdle = false;

localStorage.setItem("session_start_time", sessionStartTime.toString());

function updateActivity() {
    const now = Date.now();
    const wasIdle = isUserIdle;

    lastActivityTime = now;
    isUserIdle = false;

    // If user was idle and now active, refresh token
    if (wasIdle && IDLE_CONFIG.TOKEN_REFRESH_ON_ACTIVITY) {
        logSession("👤 User returned from idle, refreshing token...");
        refreshTokenAfterIdle();
    }

    localStorage.setItem("last_activity_time", now.toString());
}

// Listen for all user activity
const activityEvents = [
    "mousedown",
    "mousemove",
    "keypress",
    "scroll",
    "touchstart",
    "click",
];
activityEvents.forEach((event) => {
    document.addEventListener(event, updateActivity, { passive: true });
});

function checkIdleState() {
    const now = Date.now();
    const idleTime = now - lastActivityTime;

    if (idleTime > IDLE_CONFIG.MAX_IDLE_TIME && !isUserIdle) {
        isUserIdle = true;
        logSession("😴 User is idle");

        // Stop heartbeat when idle to save resources
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
    }
}

// Check idle state every minute
setInterval(checkIdleState, 60 * 1000);

async function refreshTokenAfterIdle() {
    try {
        const sessionAge =
            Date.now() -
            parseInt(localStorage.getItem("session_start_time") || Date.now());

        if (sessionAge > IDLE_CONFIG.SESSION_LIFETIME * 0.95) {
            console.warn("⚠️ Session is about to expire, reloading page...");
            showSessionExpiredNotification();
            return;
        }

        await refreshCsrf();
        logSession("✅ Token refreshed after idle");

        startHeartbeat();
        await keepSessionAlive();
    } catch (error) {
        console.error("❌ Failed to refresh after idle:", error);

        if (error.response?.status === 401 || error.response?.status === 419) {
            showSessionExpiredNotification();
        }
    }
}

function startHeartbeat() {
    if (heartbeatTimer) {
        clearInterval(heartbeatTimer);
    }

    heartbeatTimer = setInterval(async () => {
        const now = Date.now();
        const idleTime = now - lastActivityTime;

        // Only send heartbeat if user was active recently
        if (idleTime < IDLE_CONFIG.MAX_IDLE_TIME) {
            try {
                await keepSessionAlive();
                lastHeartbeat = now;
                logSession("💓 Heartbeat sent");
            } catch (error) {
                console.error("❌ Heartbeat failed:", error);

                if (error.response?.status === 419) {
                    logSession("🔄 Attempting recovery...");
                    await refreshTokenAfterIdle();
                }
            }
        } else {
            logSession("😴 User idle, skipping heartbeat");
        }
    }, SESSION_HEARTBEAT_INTERVAL);
}

// Handle page visibility (tab switching)
document.addEventListener("visibilitychange", async () => {
    if (document.visibilityState === "visible") {
        logSession("👁️ Tab visible again");

        const now = Date.now();
        const awayTime = now - lastActivityTime;

        if (awayTime > IDLE_CONFIG.MAX_IDLE_TIME) {
            logSession(
                `🔄 Was away for ${Math.round(
                    awayTime / 60000
                )} minutes, refreshing...`
            );
            await refreshTokenAfterIdle();
        } else {
            updateActivity();
        }
    }
});

// ============================================
// SESSION MANAGEMENT
// ============================================

async function keepSessionAlive(forceRefresh = false) {
    logSession("Keeping session alive" + (forceRefresh ? " (forced)" : ""));

    try {
        if (forceRefresh) {
            await refreshCsrf();
        }

        const response = await axios.post(
            CSRF_CONFIG.KEEP_ALIVE_ENDPOINT,
            {
                timestamp: Date.now(),
            },
            {
                headers: { "Cache-Control": "no-cache" },
            }
        );

        logSession("✅ Session kept alive successfully", response.data);
        localStorage.setItem("last_session_ping", Date.now().toString());
        updateSessionStatus("active");

        return response.data;
    } catch (error) {
        console.error("❌ Session keep-alive failed:", error);
        updateSessionStatus("warning");

        if (error.response) {
            const status = error.response.status;
            if (status === 419 || status === 401) {
                logSession("Session expired, interceptor will retry");
            } else if (status === 429) {
                console.warn("⚠️ Rate limit hit for keep-alive");
            }
        }

        throw error;
    }
}

function updateSessionStatus(status) {
    const indicator = document.getElementById("session-status");
    if (indicator) {
        indicator.className = `session-indicator session-${status}`;
        indicator.title = `Session: ${status}`;
    }
}

// ============================================
// COMPONENT IMPORTS
// ============================================

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
import FbaInboundShipment from "./components/Stockroom/fba_inbound/fba_inbound_shipment.vue";
import FBMorders from "./page/fbmOrders/fbmOrders.vue";
import Notfound from "./page/notfound/notfound.vue";
import Houseage from "./page/houseage/houseage.vue";
import ASINList from "./page/asinlist/asinlist.vue";
import PrinterModule from "./page/printer/printer.vue";
import HumanResource from "./page/hr/hr.vue";
import RTS from "./page/rts/rts.vue";
import Training from "./page/aiTraining/training.vue";
import Kanban from "./page/kanban/kanban.vue";

import Navbar from "./components/Navbar/Navbar.vue";

const asyncComponentMap = {
    printcustominvoice: () =>
        import("./page/stockroom/print_invoice/print_custom_invoice.vue"),
    mskucreation: () =>
        import("./page/asinoption/fnskucreation/creation_msku.vue"),
    scheduling: () => import("./page/hr/components/scheduling.vue"),
};

window.asyncComponentMap = asyncComponentMap;

const componentMapping = {
    received: "receiving",
    "return scanner": "returnscanner",
    returnscanner: "returnscanner",
    return_scanner: "returnscanner",
    order: "order",
    fbashipmentinbound: "fbashipmentinbound",
    fbashipment: "fbashipmentinbound",
    fba: "fbashipmentinbound",
    "fbm order": "fbmorder",
    "FBM Order": "fbmorder",
    "ASIN List": "asinlist",
    printer: "printer",
    Printer: "printer",
    "Human Resource": "humanresource",
    Training: "training",
    RTS: "rts",
    Kanban: "kanban",
};

// ============================================
// SESSION MIXIN (Updated to use new activity tracking)
// ============================================

const sessionMixin = {
    mounted() {
        this.$nextTick(() => {
            if (this.$el && this.$el.addEventListener) {
                const activityHandler = () => {
                    updateActivity(); // Use new activity tracker
                };

                this.$el.addEventListener("click", activityHandler);
                this.$el.addEventListener("keydown", activityHandler);

                this._sessionActivityHandler = activityHandler;
                this._sessionElement = this.$el;
            }
        });
    },
    beforeUnmount() {
        if (this._sessionActivityHandler && this._sessionElement) {
            this._sessionElement.removeEventListener(
                "click",
                this._sessionActivityHandler
            );
            this._sessionElement.removeEventListener(
                "keydown",
                this._sessionActivityHandler
            );
        }
    },
    methods: {
        extendSession() {
            keepSessionAlive();
        },
    },
};

// ============================================
// CREATE VUE APP (Simplified - removed duplicate activity tracking)
// ============================================

const app = createApp({
    mixins: [sessionMixin],
    data() {
        return {
            currentComponent: window.defaultComponent,
            collapses: {},
        };
    },
    mounted() {
        if (this.currentComponent) {
            this.safeComponentUpdate(this.currentComponent);
            logSession("App mounted with component:", this.currentComponent);
        }

        // Start heartbeat
        startHeartbeat();

        // Handle tab visibility
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "visible") {
                logSession("Tab visible - extending session");
                this.extendSession();
            }
        });
    },
    methods: {
        mapToComponentName(navName) {
            updateActivity();

            if (componentMapping[navName]) {
                return componentMapping[navName];
            }

            const transformed = navName.replace(/\s+/g, "").toLowerCase();

            if (this.$options.components[transformed]) {
                return transformed;
            }

            return navName;
        },

        getNavigationName(componentName) {
            for (const [navName, compName] of Object.entries(
                componentMapping
            )) {
                if (compName === componentName) {
                    return navName;
                }
            }
            return componentName;
        },

        loadContent(module) {
            updateActivity();
            this.extendSession();

            const navName = String(module).toLowerCase();

            // Handle Kanban directly without permission check
            if (navName === "kanban") {
                const componentName = this.mapToComponentName(navName);
                this.safeComponentUpdate(componentName, navName);
                return;
            }

            // Handle Printer modal
            if (navName === "printer") {
                if (typeof showPrinterModal === "function") {
                    showPrinterModal();
                } else {
                    console.error("showPrinterModal function not found");
                    setTimeout(() => {
                        if (typeof showPrinterModal === "function") {
                            showPrinterModal();
                        } else {
                            alert(
                                "Printer modal not available. Please check configuration."
                            );
                        }
                    }, 100);
                }
                return;
            }

            // Handle ASIN Option modal
            if (navName === "asinoption") {
                if (typeof showAsinOptionModal === "function") {
                    showAsinOptionModal();
                } else {
                    console.error("showAsinOptionModal function not found");
                }
                return;
            }

            // Permission check for other modules
            const allowedModules = window.allowedModules
                ? window.allowedModules.map((m) => m.toLowerCase())
                : [];
            const mainModule = window.mainModule
                ? window.mainModule.toLowerCase()
                : "";
            const customModules = window.customModules
                ? window.customModules.map((m) => m.toLowerCase())
                : [];

            const hasAccess =
                navName === "fbashipmentinbound" ||
                allowedModules.includes(navName) ||
                navName === mainModule ||
                customModules.includes(navName);

            logSession("Checking permissions:", {
                requested: navName,
                main: mainModule,
                allowed: allowedModules,
                custom: customModules,
                hasAccess,
            });

            if (hasAccess) {
                const componentName = this.mapToComponentName(navName);
                logSession(
                    `Mapping from nav "${navName}" to component "${componentName}"`
                );
                this.safeComponentUpdate(componentName, navName);
            } else {
                alert("You do not have permission to access this module.");
            }
        },

        safeComponentUpdate(componentName, originalNavName = null) {
            try {
                updateActivity();
                const name = String(componentName).toLowerCase();

                if (!this.$options.components[name]) {
                    if (asyncComponentMap[name]) {
                        logSession(`Loading async component: ${name}`);
                        this.currentComponent = "loading";

                        asyncComponentMap[name]()
                            .then((module) => {
                                logSession(
                                    `Successfully loaded async component: ${name}`
                                );
                                this.$options.components[name] = module.default;
                                this.safeComponentUpdate(name, originalNavName);
                            })
                            .catch((err) => {
                                console.error(
                                    `Failed to load async component "${name}":`,
                                    err
                                );
                                alert(
                                    `Failed to load ${name} component. Please try again.`
                                );
                                logSession(
                                    `Staying on current component due to load failure`
                                );
                            });
                        return;
                    }

                    console.warn(
                        `Component "${name}" not registered and no async loader found.`
                    );
                    return;
                }

                if (this.currentComponent === name) {
                    logSession(`Already on component: ${name}`);
                    return;
                }

                logSession(`Switching to component: ${name}`);
                this.currentComponent = name;

                this.$nextTick(() => {
                    const navName =
                        originalNavName || this.getNavigationName(name);
                    this.updateActiveState(navName);
                    logSession(
                        `Component updated to: ${name}, Nav highlight: ${navName}`
                    );
                });
            } catch (err) {
                console.error("Error switching component:", err);
            }
        },

        forceUpdate(moduleName) {
            const navName = String(moduleName).toLowerCase();
            const componentName = this.mapToComponentName(navName);
            this.safeComponentUpdate(componentName, navName);
        },

        updateActiveState(moduleName) {
            document.querySelectorAll(".nav .nav-link").forEach((link) => {
                const linkModule = link.getAttribute("data-module");
                if (
                    linkModule &&
                    linkModule.toLowerCase() === moduleName.toLowerCase()
                ) {
                    link.classList.add("active");
                } else {
                    link.classList.remove("active");
                }
            });
        },

        toggleCollapse(id) {
            updateActivity();
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
        notfound: Notfound,
        houseage: Houseage,
        asinlist: ASINList,
        printer: PrinterModule,
        humanresource: HumanResource,
        rts: RTS,
        training: Training,
        kanban: Kanban,
    },
});

// Global activity tracking mixin
app.mixin({
    mounted() {
        this.$nextTick(() => {
            const eventHandlers = [
                "click",
                "keydown",
                "mousedown",
                "touchstart",
            ];
            const activityHandler = () => {
                updateActivity(); // Use new unified activity tracker
            };

            if (this.$el && this.$el.addEventListener) {
                eventHandlers.forEach((event) => {
                    this.$el.addEventListener(event, activityHandler, {
                        passive: true,
                    });
                });

                this._sessionEvents = eventHandlers;
                this._sessionHandler = activityHandler;
                this._sessionElement = this.$el;
            }
        });
    },
    beforeUnmount() {
        if (
            this._sessionHandler &&
            this._sessionElement &&
            this._sessionEvents
        ) {
            this._sessionEvents.forEach((event) => {
                this._sessionElement.removeEventListener(
                    event,
                    this._sessionHandler
                );
            });
        }
    },
});

// ============================================
// PRIMEVUE SETUP FOR BLADE APPS
// ============================================

import PrimeVue from "primevue/config";
import Aura from "@primevue/themes/aura";
import ToastService from "primevue/toastservice";
import Tooltip from "primevue/tooltip";

// Configure main app with PrimeVue
app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: false, // Keep it light to match Bootstrap
        },
    },
});
app.use(ToastService);
app.directive("tooltip", Tooltip);

// Mount main app
window.appInstance = app.mount("#app");

// ============================================
// EXPOSE GLOBALLY
// ============================================

window.Vue = { createApp };
window.createApp = createApp;
window.loadContent = (module) => {
    if (window.appInstance) {
        window.appInstance.loadContent(module);
    }
};
window.forceComponentUpdate = (module) => {
    if (window.appInstance) {
        window.appInstance.forceUpdate(module);
    }
};

// CSRF Handler API
window.csrfHandler = {
    refresh: refreshCsrf,
    validate: validateCsrfToken,
    keepAlive: keepSessionAlive,
    startHealthCheck: startTokenHealthCheck,
};
window.keepSessionAlive = keepSessionAlive;
window.refreshCsrf = refreshCsrf;

// Idle Handler API
window.idleHandler = {
    updateActivity,
    checkIdleState,
    refreshTokenAfterIdle,
    getIdleTime: () => Date.now() - lastActivityTime,
    getSessionAge: () => Date.now() - sessionStartTime,
};

// ============================================
// SEARCH APP WITH PRIMEVUE
// ============================================

const searchApp = createApp({
    mixins: [sessionMixin],
    components: {
        searching: Searching,
        navbar: Navbar,
    },
    mounted() {
        this.$nextTick(() => {
            const searchElement = document.getElementById("appsearch");
            if (searchElement) {
                ["input", "focus", "click"].forEach((event) => {
                    searchElement.addEventListener(event, () => {
                        updateActivity();
                    });
                });
            }
        });
    },
});

// Add PrimeVue to search app too
searchApp.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: false,
        },
    },
});
searchApp.use(ToastService);

searchApp.mount("#appsearch");

if (document.getElementById("navbar-app")) {
    const navbarApp = createApp({
        components: { navbar: Navbar },
    });

    navbarApp.use(PrimeVue, {
        theme: {
            preset: Aura,
            options: {
                darkModeSelector: false,
            },
        },
    });

    navbarApp.mount("#navbar-app");
}

// ============================================
// DOCUMENT READY INITIALIZATION
// ============================================

document.addEventListener("DOMContentLoaded", function () {
    // Validate and start health check
    validateCsrfToken();
    startTokenHealthCheck(10);

    // Create session status indicator
    createSessionIndicator();

    // Initialize activity tracking
    updateActivity();

    // Start heartbeat
    startHeartbeat();

    // Initial session keep-alive
    keepSessionAlive();

    logSession("✅ App initialized successfully");
});

function createSessionIndicator() {
    if (SESSION_DEBUG) {
        const indicator = document.createElement("div");
        indicator.id = "session-status";
        indicator.className = "session-indicator session-init";
        indicator.title = "Session Status";
        indicator.innerHTML = "●";
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

        const style = document.createElement("style");
        style.textContent = `
            .session-indicator.session-active { background-color: #28a745; }
            .session-indicator.session-warning { background-color: #ffc107; }
            .session-indicator.session-error { background-color: #dc3545; }
        `;
        document.head.appendChild(style);

        indicator.addEventListener("click", () => {
            keepSessionAlive();
            indicator.classList.add("session-active");
            setTimeout(
                () => indicator.classList.remove("session-active"),
                1000
            );
        });

        document.body.appendChild(indicator);
    }
}

// AI App (if exists)
if (document.getElementById("ai-app")) {
    const aiApp = createApp({});
    aiApp.component("training", Training);
    aiApp.mount("#ai-app");
}

// Log successful initialization
console.log("✅ CSRF Handler loaded");
console.log("✅ Idle Handler loaded");
console.log("✅ Session Management loaded");
