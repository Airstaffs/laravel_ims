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

// Create Vue app
const app = createApp({
    data() {
        return {
            currentComponent: window.defaultComponent,
            collapses: {},
        };
    },
    mounted() {
        if (this.currentComponent) {
            this.forceUpdate(this.currentComponent);
        }
    },
    methods: {
        loadContent(module) {
            const moduleName = module;
            const allowedModules = window.allowedModules || [];
            const mainModule = window.mainModule;

            // ✅ Allow fbashipmentinbound regardless of permission
            if (
                moduleName != "fbashipmentinbound" &&
                !allowedModules.includes(moduleName) &&
                moduleName !== mainModule
            ) {
                alert("You do not have permission to access this module.");
                return;
            }

            this.forceUpdate(moduleName);
        },
        forceUpdate(moduleName) {
            this.currentComponent = null;
            this.$nextTick(() => {
                this.currentComponent = moduleName;
                this.updateActiveState(moduleName);
            });
        },
        updateActiveState(moduleName) {
            document.querySelectorAll(".nav .nav-link").forEach((link) => {
                if (link.getAttribute("data-module") === moduleName) {
                    link.classList.add("active");
                } else {
                    link.classList.remove("active");
                }
            });
        },

        // ✅ Improved Bootstrap Collapse Logic
        toggleCollapse(id) {
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
        fnsku: FNSKU,
        fbashipmentinbound: FbaInboundShipment,
    },
});

// Mount the main app
window.appInstance = app.mount("#app");

window.loadContent = (module) => {
    if (window.appInstance) {
        window.appInstance.loadContent(module);
    }
};

window.forceComponentUpdate = (module) => {
    if (window.appInstance) {
        window.appInstance.forceUpdate(module.toLowerCase());
    }
};

// Create a separate Vue instance for Searching Component
const searchApp = createApp({
    components: {
        searching: Searching,
    },
});

// Mount the Searching app separately
searchApp.mount("#appsearch");
