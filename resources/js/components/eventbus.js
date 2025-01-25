import { reactive } from 'vue';

export const eventBus = reactive({
  searchQuery: '', // Shared state for search query
  updateSearchQuery(query) {
    this.searchQuery = query; // Update the search query
  },
});
