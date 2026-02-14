import axios from "axios";

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    data() {
        return {
            inventory: [],
            loading: false,
            currentPage: 1,
            totalPages: 1,
            perPage: 10,
        };
    },

    methods: {
        async fetchInventory() {
            this.loading = true;

            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/reconciliation/products`,
                    {
                        params: {
                            page: this.currentPage,
                            per_page: this.perPage,
                        },
                    }
                );

                this.inventory = response.data.data || [];
                this.totalPages = response.data.last_page || 1;
            } catch (error) {
                console.error("Error fetching reconciliation data:", error);
            } finally {
                this.loading = false;
            }
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchInventory();
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchInventory();
            }
        },
    },

    mounted() {
        this.fetchInventory();
    },
};
