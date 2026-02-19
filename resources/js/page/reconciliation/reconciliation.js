import axios from "axios";

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    data() {
        return {
            inventory: [],
            loading: false,

            //pagination
            currentPage: 1,
            totalRecords: 0,
            perPage: 10,
            first: 0
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
                this.totalRecords = response.data.total;
            } catch (error) {
                console.error("Error fetching reconciliation data:", error);
            } finally {
                this.loading = false;
            }
        },

         onPageChange(event) {
            this.first = event.first
            this.currentPage = event.page + 1; // convert to 1-based
            this.perPage     = event.rows;
            this.fetchInventory();
        },
    },

    mounted() {
        this.fetchInventory();
    },
};
