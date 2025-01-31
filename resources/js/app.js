import { createApp } from 'vue';

// Import components
import Orders from './components/orders.vue';
import Labeling from './components/labeling.vue';
import Unreceived from './components/unreceived.vue';
import Cleaning from './components/cleaning.vue';
import Packing from './components/packing.vue';
import Received from './components/received.vue';
import Stockroom from './components/stockroom.vue';
import Testing from './components/testing.vue';
import Validation from './components/validation.vue';

// Create the Vue app
const app = createApp({
    data() {
        return {
            currentComponent: window.defaultComponent,
        };
    },
    mounted() {
        console.log(`Vue app mounted with default component: ${this.currentComponent}`);
        // Load the default content after Vue is mounted
        if (window.defaultComponent) {
            this.loadContent(window.defaultComponent);
        }
    },
    methods: {
        loadContent(module) {
            const allowedModules = window.allowedModules || []; // Get from PHP
            const mainModule = window.mainModule || 'dashboard'; // Get from PHP

            if (!allowedModules.includes(module.toLowerCase()) && 
                module.toLowerCase() !== mainModule.toLowerCase()) {
                alert("You do not have permission to access this module.");
                return;
            }

            // Update Vue component
            this.currentComponent = module.toLowerCase();

            // Update navigation active state
            document.querySelectorAll('.nav .nav-link').forEach(link => 
                link.classList.remove('active')
            );
            const activeLink = document.querySelector(`.nav .nav-link[data-module="${module}"]`);
            if (activeLink) activeLink.classList.add('active');
        }
    },
    components: {
        orders: Orders,
        labeling: Labeling,
        unreceived: Unreceived,
        cleaning: Cleaning,
        packing: Packing,
        received: Received,
        stockroom: Stockroom,
        testing: Testing,
        validation: Validation,
    }
});

// Mount Vue app and store instance
window.appInstance = app.mount('#app');

// Add the loadContent function to window for external access
window.loadContent = (module) => {
    if (window.appInstance) {
        window.appInstance.loadContent(module);
    } else {
        console.error("Vue app instance is not yet available.");
    }
};