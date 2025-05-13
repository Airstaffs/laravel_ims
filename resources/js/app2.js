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
            this.safeComponentUpdate(this.currentComponent);
            console.log('App mounted with component:', this.currentComponent);
        }
    },
    methods: {
        // Map a navigation name to its component name
        mapToComponentName(navName) {
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
    components: {
        searching: Searching,
    },
});

// Mount the Searching app separately
searchApp.mount("#appsearch");