<template>
    <div>
      <input
        type="text"
        v-model="searchQuery"
        @input="onSearch"
        placeholder="Search inventory..."
      />
    </div>
  </template>
  
  <script>
  import { eventBus } from './eventBus'; // Import the event bus
  
  export default {
    name: 'searching',
    data() {
      return {
        searchQuery: '',
      };
    },
    methods: {
      onSearch() {
        eventBus.updateSearchQuery(this.searchQuery); // Update the global search query
      },
    },
  };
  </script>
  
  <style scoped>
  input {
    margin-bottom: 5px;
    padding: 10px;
    width: 400px;
    border: 1px solid #ccc;
    border-radius: 4px;
  }
  </style>
  