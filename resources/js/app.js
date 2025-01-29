import { createApp } from 'vue';

// Import all components
import Orders from './components/orders.vue';
import Labeling from './components/labeling.vue';
import Unreceived from './components/Unreceived.vue';
import Cleaning from './components/cleaning.vue';
import Packing from './components/packing.vue';
import Received from './components/received.vue';
import Stockroom from './components/stockroom.vue';
import Testing from './components/testing.vue';
import Validation from './components/validation.vue';

// Create the main application
const app = createApp({
    data() {
        return {
            currentComponent: window.defaultComponent || 'dashboard', // Default module from PHP
        };
    },
    mounted() {
        // Log to confirm that Vue has mounted and is using the correct defaultComponent
        console.log(`Vue app mounted with default component: ${this.currentComponent}`);
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

// Mount Vue app to the DOM
document.addEventListener('DOMContentLoaded', () => {
    console.log("DOM loaded, mounting Vue app...");
    app.mount('#app'); // Ensure this matches your element in HTML
});
