import { eventBus } from "../../components/eventbus";

export default {
    name: "searching",
    data() {
        return {
            searchQuery: "",
        };
    },
    methods: {
        onSearch() {
            eventBus.updateSearchQuery(this.searchQuery); // Update the global search query
        },
    },
};
