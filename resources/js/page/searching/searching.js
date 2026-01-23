import { eventBus } from '../../components/eventbus';

export default {
  name: "searching",

  data() {
    return {
      searchQuery: "",
      highlightTimer: null,
    };
  },

  mounted() {
    const params = new URLSearchParams(window.location.search);
    const search = params.get("search");

    if (search) {
      this.searchQuery = search;
      this.onSearch();
      
      // Highlight the text after component is fully mounted
      this.$nextTick(() => {
        this.highlightSearchText();
      });
    }
  },

  methods: {
    onSearch() {
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
      
      // Clear previous timer
      if (this.highlightTimer) {
        clearTimeout(this.highlightTimer);
      }
      
      // Highlight text after user stops typing (500ms delay)
      this.highlightTimer = setTimeout(() => {
        this.highlightSearchText();
      }, 500);
    },

    clearSearch() {
      this.searchQuery = "";
      this.onSearch();
      
      // Focus back on input after clearing
      this.$nextTick(() => {
        if (this.$refs.searchInput) {
          this.$refs.searchInput.$el.focus();
        }
      });
    },

    highlightSearchText() {
      if (this.$refs.searchInput && this.searchQuery) {
        // PrimeVue InputText uses $el to access the native input
        this.$refs.searchInput.$el.select();
      }
    },
  },

  beforeUnmount() {
    // Clean up timer when component is destroyed
    if (this.highlightTimer) {
      clearTimeout(this.highlightTimer);
    }
  },
};