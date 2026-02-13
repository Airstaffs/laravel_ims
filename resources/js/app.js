import { createApp } from "vue";

import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";

// Add PrimeVue CSS
import "primeicons/primeicons.css";

import axios from "axios";

import Swal from "sweetalert2";
window.Swal = Swal;

// ⭐ IMPORT TIME FORMATTER
import timeFormatter from "./utils/timeFormatter";
import timeFormatterPlugin from "./plugins/timeFormatterPlugin";

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

// ⭐ Idle detection configuration
const IDLE_CONFIG = {
    SESSION_LIFETIME: 8 * 60 * 60 * 1000, // 8 hours
    WARNING_BEFORE_EXPIRY: 10 * 60 * 1000, // 10 minutes
    MAX_IDLE_TIME: 30 * 60 * 1000, // 30 minutes = idle
    TOKEN_REFRESH_ON_ACTIVITY: true, // Refresh token when user returns
    ACTIVITY_DEBOUNCE: 1000, // Debounce activity updates to 1 second
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
// UTILITY: DEBOUNCE
// ============================================

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
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
    sessionStorage.setItem("csrf_token_backup", token);
    sessionStorage.setItem("csrf_token_timestamp", Date.now().toString());
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

            sessionStorage.setItem("csrf_token_backup", newToken);
            sessionStorage.setItem(
                "csrf_token_timestamp",
                Date.now().toString(),
            );

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
        'meta[name="csrf-token"]',
    )?.content;
    const backupToken = sessionStorage.getItem("csrf_token_backup");
    const axiosToken = axios.defaults.headers.common["X-CSRF-TOKEN"];

    const tokens = {
        meta: metaToken,
        sessionStorage: backupToken,
        axios: axiosToken,
    };
    const allMatch = metaToken === backupToken && backupToken === axiosToken;

    if (!allMatch) {
        console.warn("⚠️ CSRF tokens out of sync");
    }

    return { valid: !!metaToken, synchronized: allMatch, tokens };
}

function showSessionExpiredNotification() {
    const lastShown = sessionStorage.getItem(
        "last_session_expired_notification",
    );
    const now = Date.now();

    if (lastShown && now - parseInt(lastShown) < 30000) {
        return;
    }

    sessionStorage.setItem("last_session_expired_notification", now.toString());

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
    setInterval(
        () => {
            const validation = validateCsrfToken();

            if (!validation.valid) {
                console.error("❌ CSRF token validation failed");
                refreshCsrf().catch(console.error);
            } else if (!validation.synchronized) {
                console.warn("⚠️ CSRF tokens out of sync, synchronizing...");
                const metaToken = document.querySelector(
                    'meta[name="csrf-token"]',
                )?.content;
                if (metaToken) {
                    axios.defaults.headers.common["X-CSRF-TOKEN"] = metaToken;
                    sessionStorage.setItem("csrf_token_backup", metaToken);
                }
            }
        },
        intervalMinutes * 60 * 1000,
    );
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
                    "Session appears to be invalid. Consider reloading the page.",
                );

                if (
                    status === 419 &&
                    !sessionStorage.getItem("csrf_error_shown")
                ) {
                    sessionStorage.setItem("csrf_error_shown", "true");
                    setTimeout(() => {
                        if (
                            confirm(
                                "Your session may have expired. Would you like to reload the page?",
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
                `Retrying request (attempt ${originalRequest.__retryCount})`,
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
                    refreshError,
                );
                return Promise.reject(error);
            }
        }

        return Promise.reject(error);
    },
);

axios.interceptors.request.use(
    (config) => {
        const hasToken =
            config.headers?.["X-CSRF-TOKEN"] ||
            config.headers?.common?.["X-CSRF-TOKEN"];

        if (!hasToken) {
            const metaToken = document.querySelector(
                'meta[name="csrf-token"]',
            )?.content;
            const backupToken = sessionStorage.getItem("csrf_token_backup");
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
    (error) => Promise.reject(error),
);

// ============================================
// ⭐ IDLE DETECTION & MANAGEMENT
// ============================================

let lastActivityTime = Date.now();
let lastHeartbeat = Date.now();
let sessionStartTime = Date.now();
let heartbeatTimer = null;
let isUserIdle = false;

sessionStorage.setItem("session_start_time", sessionStartTime.toString());

// Debounced activity update
const updateActivity = debounce(() => {
    const now = Date.now();
    const wasIdle = isUserIdle;

    lastActivityTime = now;
    isUserIdle = false;

    // If user was idle and now active, refresh token
    if (wasIdle && IDLE_CONFIG.TOKEN_REFRESH_ON_ACTIVITY) {
        logSession("👤 User returned from idle, refreshing token...");
        refreshTokenAfterIdle();
    }

    sessionStorage.setItem("last_activity_time", now.toString());
}, IDLE_CONFIG.ACTIVITY_DEBOUNCE);

// Listen for user activity (document level - single listener)
const activityEvents = [
    "mousedown",
    "keypress",
    "scroll",
    "touchstart",
    "click",
];

activityEvents.forEach((event) => {
    document.addEventListener(event, updateActivity, {
        passive: true,
        capture: true,
    });
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

// Check idle state every 5 minutes (optimized from 1 minute)
setInterval(checkIdleState, 5 * 60 * 1000);

async function refreshTokenAfterIdle() {
    try {
        const sessionAge =
            Date.now() -
            parseInt(
                sessionStorage.getItem("session_start_time") || Date.now(),
            );

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
                    awayTime / 60000,
                )} minutes, refreshing...`,
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
            },
        );

        logSession("✅ Session kept alive successfully", response.data);
        sessionStorage.setItem("last_session_ping", Date.now().toString());
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
import FBMorders from "./page/fbmOrders/fbmOrders.vue";
import Shipment from "./page/shipment/shipment.vue";
import Notfound from "./page/notfound/notfound.vue";
import Houseage from "./page/houseage/houseage.vue";
import ASINList from "./page/asinlist/asinlist.vue";
import PrinterModule from "./page/printer/printer.vue";
import HumanResource from "./page/hr/hr.vue";
import RTS from "./page/rts/rts.vue";
import Kanban from "./page/kanban/kanban.vue";
import Training from "./page/aiTraining/training.vue";
import History from "./page/history/history.vue";
import Soldlist from "./page/soldlist/soldlist.vue";
import Returnedlist from "./page/returnlist/returnlist.vue";
import AuxiliaryLabel from "./page/auxiliary/auxiliary.vue";
import Reconciliation from "./page/reconciliation/reconciliation.vue";
import InventoryStatistics from "./page/inventoryStatistics/inventoryStatistics.vue";
import FbaInboundShipment from "./components/Stockroom/fba_inbound/fba_inbound_shipment.vue";

import Navbar from "./components/Navbar/Navbar.vue";
import Login from "./components/Login/Login.vue";

// ============================================
// LOGIN APP WITH PRIMEVUE
// ============================================

if (document.getElementById("login-app")) {
    const loginApp = createApp({});

    // Register Login component
    loginApp.component("LoginComponent", Login);

    // Use PrimeVue with same config as main app
    loginApp.use(PrimeVue, {
        theme: {
            preset: Aura,
            options: {
                darkModeSelector: false,
            },
        },
    });

    // Use time formatter plugin
    loginApp.use(timeFormatterPlugin);

    // Mount the login app
    loginApp.mount("#login-app");

    console.log("✅ Login app mounted");
}

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
    Shipment: "shipment",
    printer: "printer",
    Printer: "printer",
    "Human Resource": "humanresource",
    Training: "training",
    RTS: "rts",
    Kanban: "kanban",
    History: "history",
    "Sold Items": "soldlist",
    "Returned Items": "returnlist",
    "Auxiliary Label": "auxiliary",
    "Inventory Statistics": "inventorystatistics",
    "Reconciliation": "reconciliation",
};

// ============================================
// SESSION MIXIN (Simplified - no duplicate listeners)
// ============================================

const sessionMixin = {
    methods: {
        extendSession() {
            keepSessionAlive();
        },
    },
};

// ============================================
// CREATE VUE APP
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
                componentMapping,
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
                getKanbanNotif();
                return;
            }

            // ADD THIS: Handle History directly without permission check
            if (navName === "history") {
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
                                "Printer modal not available. Please check configuration.",
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
                navName === "history" ||
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
                    `Mapping from nav "${navName}" to component "${componentName}"`,
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
                                    `Successfully loaded async component: ${name}`,
                                );
                                this.$options.components[name] = module.default;
                                this.safeComponentUpdate(name, originalNavName);
                            })
                            .catch((err) => {
                                console.error(
                                    `Failed to load async component "${name}":`,
                                    err,
                                );
                                alert(
                                    `Failed to load ${name} component. Please try again.`,
                                );
                                logSession(
                                    `Staying on current component due to load failure`,
                                );
                            });
                        return;
                    }

                    console.warn(
                        `Component "${name}" not registered and no async loader found.`,
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
                        `Component updated to: ${name}, Nav highlight: ${navName}`,
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

        // ============================================
        // KANBAN NOTIFICATION
        // ============================================
        getKanbanNotif() {
            const user = ref(window.user || {});

            fetch("/user/kanban/notification", {
                method: "POST",
                body: JSON.stringify({
                    userId: user.id,
                }),
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute("content"),
                },
            })
                .then((response) => response.json())
                .then((data) => {
                    console.log(
                        data.mentionedCount || 0,
                        "data.mentionedCount || 0;",
                    );
                    window.kanbanMentionedCount = data.mentionedCount || 0;

                    if (data.mentionedCount > 0) {
                        ["kanbanNotifMobile", "kanbanNotifDesktop"].forEach(
                            (id) => {
                                const el = document.getElementById(id);
                                if (el) {
                                    el.style.display = "inline";
                                    el.textContent = data.mentionedCount;
                                }
                            },
                        );
                    }
                })
                .catch((error) =>
                    console.error("Error fetching notifications:", error),
                );
        },
    },
    components: {
        order: Order,
        labeling: Labeling,
        unreceived: Unreceived,
        cleaning: Cleaning,
        packing: Packing,
        receiving: Receiving,
        reconciliation: Reconciliation,
        stockroom: Stockroom,
        testing: Testing,
        validation: Validation,
        productionarea: ProductionArea,
        returnscanner: ReturnScanner,
        fnsku: FNSKU,
        fbashipmentinbound: FbaInboundShipment,
        fbmorder: FBMorders,
        shipment: Shipment,
        notfound: Notfound,
        houseage: Houseage,
        asinlist: ASINList,
        printer: PrinterModule,
        humanresource: HumanResource,
        rts: RTS,
        training: Training,
        kanban: Kanban,
        history: History,
        soldlist: Soldlist,
        returnlist: Returnedlist,
        auxiliary: AuxiliaryLabel,
        inventorystatistics: InventoryStatistics,
        reconciliation: Reconciliation,
    },
});

// ============================================
// PRIMEVUE SETUP FOR BLADE APPS
// ============================================

import PrimeVue from "primevue/config";
import Aura from "@primevue/themes/aura";
import ToastService from "primevue/toastservice";
import Tooltip from "primevue/tooltip";

// ⭐ REGISTER TIME FORMATTER PLUGIN (initializes automatically)
await app.use(timeFormatterPlugin);

// ⭐ EXPOSE GLOBALLY (plugin already initialized it)
window.timeFormatter = timeFormatter;

// Configure main app with PrimeVue
app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: false,
        },
    },
});
app.use(ToastService);
app.directive("tooltip", Tooltip);

// Mount main app
window.appInstance = app.mount("#app");

console.log(
    "✅ TimeFormatter ready with timezone:",
    timeFormatter.getTimezone(),
);

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
        // Activity already tracked at document level
        logSession("Search app mounted");
    },
});

searchApp.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: false,
        },
    },
});
searchApp.use(ToastService);
// ⭐ REGISTER TIME FORMATTER FOR SEARCH APP
searchApp.use(timeFormatterPlugin);

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
    // ⭐ REGISTER TIME FORMATTER FOR NAVBAR APP
    navbarApp.use(timeFormatterPlugin);

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

    getKanbanNotif();
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
                1000,
            );
        });

        document.body.appendChild(indicator);
    }
}

// AI App (if exists)
if (document.getElementById("ai-app")) {
    const aiApp = createApp({});
    aiApp.component("training", Training);
    // ⭐ REGISTER TIME FORMATTER FOR AI APP
    aiApp.use(timeFormatterPlugin);
    aiApp.mount("#ai-app");
}

// Log successful initialization
console.log("✅ CSRF Handler loaded");
console.log("✅ Idle Handler loaded");
console.log("✅ Session Management loaded");
console.log("✅ Activity tracking optimized (debounced + document-level)");

function showPrinterModal() {
    console.log("Opening printer modal...");

    // Check if container exists, if not create it
    let container = document.getElementById("printer-app-container");
    if (!container) {
        container = document.createElement("div");
        container.id = "printer-app-container";
        document.body.appendChild(container);
    }

    // Load the printer Vue component if not already loaded
    if (!window.printerApp) {
        loadPrinterComponent();
    }
}

function loadPrinterComponent() {
    console.log("Loading printer component...");

    function checkReady() {
        if (!window.appInstance) {
            console.log("Main app not ready yet...");
            return false;
        }

        if (!window.createApp) {
            console.log("createApp not available yet...");
            return false;
        }

        if (!window.appInstance.$options.components.printer) {
            console.log("Printer component not registered yet...");
            return false;
        }

        return true;
    }

    if (!checkReady()) {
        setTimeout(loadPrinterComponent, 200);
        return;
    }

    const printerComponent = window.appInstance.$options.components.printer;
    createPrinterApp(printerComponent);
}

function createPrinterApp(PrinterComponent) {
    console.log("Creating printer app with component");

    const createApp = window.createApp;

    if (!createApp) {
        console.error("createApp function not available");
        return;
    }

    try {
        window.printerApp = createApp(PrinterComponent);

        if (
            window.appInstance &&
            window.appInstance.config &&
            window.appInstance.config.globalProperties
        ) {
            const globalProps = window.appInstance.config.globalProperties;
            Object.keys(globalProps).forEach((key) => {
                if (key !== "$el" && key !== "$root") {
                    window.printerApp.config.globalProperties[key] =
                        globalProps[key];
                }
            });
        }

        window.printerApp.mount("#printer-app-container");
        console.log("Printer app mounted successfully");
    } catch (error) {
        console.error("Failed to mount printer app:", error);
    }
}

function cleanupPrinterApp() {
    console.log("Cleaning up printer app...");
    if (window.printerApp) {
        try {
            window.printerApp.unmount();
        } catch (error) {
            console.error("Error unmounting printer app:", error);
        }
        window.printerApp = null;
        const container = document.getElementById("printer-app-container");
        if (container) {
            container.innerHTML = "";
        }
    }
}

// Expose globally
window.showPrinterModal = showPrinterModal;
window.cleanupPrinterApp = cleanupPrinterApp;
