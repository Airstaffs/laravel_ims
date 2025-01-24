import { createApp } from 'vue';
// Import your components
import Orders from './components/orders.vue';
import Labelling from './components/labelling.vue';
import Unreceived from './components/Unreceived.vue';
import Cleaning from './components/cleaning.vue';
import Packing from './components/packing.vue';
import Received from './components/received.vue';
import Stockroom from './components/stockroom.vue';
import Testing from './components/testing.vue';
import Validation from './components/validation.vue';
import Searching from './components/searching.vue';  // Import searching component

// Create the main app for the orders and other components
const app = createApp({
  data() {
    return {
      currentComponent: '', // Default component to display
    };
  },
  components: {
    orders: Orders,
    labelling: Labelling,
    unreceived: Unreceived,
    cleaning: Cleaning,
    packing: Packing,
    received: Received,
    stockroom: Stockroom,
    testing: Testing,
    validation: Validation,
  },
});

// Mount the main app on #app div
app.mount('#app');

// Create a separate app for the searching component
const searchApp = createApp({
  components: {
    searching: Searching,  // Register searching component
  },
});

// Mount the searching app on #appsearch div
searchApp.mount('#appsearch');
