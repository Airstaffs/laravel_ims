import { eventBus } from '../../components/eventbus';

export default {
  name: "searching",

  data() {
    return {
      searchQuery: "",
      searchTimer: null,     // Timer for search debounce
      highlightTimer: null,  // Timer for highlight debounce
      delay: 3000,           // Delay in ms for both search and highlight
    };
  },

  mounted() {
    const params = new URLSearchParams(window.location.search);
    const search = params.get("search");

    if (search) {
      this.searchQuery = search;

      // Perform search immediately on mount
      this.performSearch();

      // Highlight input after mount
      this.$nextTick(() => {
        this.highlightSearchText();
      });
    }
  },

  methods: {
    onSearch() {
      // Clear previous search timer
      if (this.searchTimer) clearTimeout(this.searchTimer);

      // Set new timer for debounced search
      this.searchTimer = setTimeout(() => {
        this.performSearch();
      }, 1000);
    },

    performSearch() {
      // Update URL params
      const params = new URLSearchParams(window.location.search);
      if (this.searchQuery.trim()) {
        params.set("search", this.searchQuery);
      } else {
        params.delete("search");
      }

      window.history.replaceState(
        {},
        "",
        `${window.location.pathname}?${params.toString()}`
      );

      // Update global search
      eventBus.updateSearchQuery(this.searchQuery);

      // Delay highlighting separately
      if (this.highlightTimer) clearTimeout(this.highlightTimer);
      this.highlightTimer = setTimeout(() => {
        this.highlightSearchText();
      }, this.delay);
    },

    clearSearch() {
      this.searchQuery = "";
      this.onSearch();

      this.$nextTick(() => {
        if (this.$refs.searchInput) {
          this.$refs.searchInput.$el.focus();
        }
      });
    },

    highlightSearchText() {
      if (this.$refs.searchInput && this.searchQuery) {
        this.$refs.searchInput.$el.select();
      }
    },
  },

  beforeUnmount() {
    if (this.searchTimer) clearTimeout(this.searchTimer);
    if (this.highlightTimer) clearTimeout(this.highlightTimer);
  },
};
