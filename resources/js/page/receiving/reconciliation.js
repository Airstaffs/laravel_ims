export default {
    data() {
        return {
            showReconciliationDebug: false,
            reconciliationRows: [],
            reconciliationLoading: false,
        };
    },

    computed: {
        filteredReconciliationRows() {
            // safety guard
            if (!Array.isArray(this.reconciliationRows)) {
                return [];
            }

            // optional filter by active tracking
            if (!this.trackingNumber) {
                return this.reconciliationRows;
            }

            return this.reconciliationRows.filter(
                row => row.tracking_number === this.trackingNumber
            );
        }
    },

    methods: {
        async fetchReconciliationDebug() {
            this.reconciliationLoading = true;

            try {
                const response = await axios.get('/debug/reconciliation');

                if (response.data.success) {
                    this.reconciliationRows = response.data.data;
                }
            } catch (error) {
                console.error('❌ Failed to fetch reconciliation data', error);
            } finally {
                this.reconciliationLoading = false;
            }
        },

        toggleReconciliationDebug() {
            this.showReconciliationDebug = !this.showReconciliationDebug;

            if (this.showReconciliationDebug) {
                this.fetchReconciliationDebug();
            }
        },

        resolveReconciliationImage(path) {
            return `/images/product_images/Airstaffs/${path}`;
        },

        onDebugImageError(event) {
            event.target.src = this.defaultImage;
        }
    }
};
