import { eventBus } from "../../components/eventbus";
import "../../../css/modules.css";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ProductList",
    data() {
        return {
            inventory: [],
            loading: true,
            selectAll: false,
            fnsku: "",
            msku: "",
            asin: "",
            grading: "New",
            astitle: "",
            expandedRows: {},
            isInsertFnskuModalVisible: false,
            storeName: "Allrenewed",
            newFnskuData: {
                fnsku: "",
                msku: "",
                asin: "",
                grading: "New",
                astitle: "",
                storeName: "Allrenewed",
            },

            //pagination
            currentPage: 1,
            totalRecords: 0,
            perPage: 10, // Default rows per page
            first: 0 //pagination internal state
        };
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery; // Making search reactive
        },
    },
    methods: {
        async fetchInventory() {
            this.loading = true;
            try {
                const response = await axios.get(`${API_BASE_URL}/api/fnsku/fnsku`, {
                    params: {
                        search: this.searchQuery,
                        page: this.currentPage,
                        per_page: this.perPage
                    },
                });

                this.inventory = response.data.data;
                this.totalRecords = response.data.total;
            } catch (error) {
                console.error("Error fetching inventory data:", error);
            } finally {
                this.loading = false;
            }
        },
          // Pagination
        onPageChange(event) {
            this.first = event.first
            this.currentPage = event.page + 1; // convert to 1-based
            this.perPage     = event.rows;
            this.fetchInventory();
        },
        toggleAll() {
            this.inventory.forEach((item) => (item.checked = this.selectAll));
        },
        toggleDetails(index) {
            // this.$set(this.expandedRows, index, !this.expandedRows[index]);
              this.expandedRows[index] = !this.expandedRows[index];
        },

        showInsertFnskuModal() {
            this.isInsertFnskuModalVisible = true;
            this.newFnskuData = {
                fnsku: "",
                msku: "",
                asin: "",
                grading: "New",
                astitle: "",
                storeName: "Allrenewed",
            };

            // Set focus on FNSKU input
            this.$nextTick(() => {
                if (this.$refs.newFnskuInput) {
                    this.$refs.newFnskuInput.focus();
                }
            });
        },

        hideInsertFnskuModal() {
            this.isInsertFnskuModalVisible = false;
        },

        async saveNewFnsku() {
            // Validate required fields
            if (
                !this.newFnskuData.fnsku ||
                !this.newFnskuData.asin ||
                !this.newFnskuData.astitle
            ) {
                alert("FNSKU, ASIN, and Title are required fields.");
                return;
            }

            try {
                const response = await axios.get(
                    `${API_BASE_URL}/insert-fnsku`,
                    {
                        fnsku: this.newFnskuData.fnsku,
                        msku: this.newFnskuData.msku || null,
                        asin: this.newFnskuData.asin,
                        grading: this.newFnskuData.grading,
                        astitle: this.newFnskuData.astitle,
                        storename: this.newFnskuData.storeName,
                        _token: document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute("content"),
                    }
                );

                if (response.data.success) {
                    alert("FNSKU added successfully!");
                    this.hideInsertFnskuModal();

                    // Refresh FNSKU list if currently viewing the FNSKU modal
                    if (this.isFnskuModalVisible) {
                        this.fetchFnskuList();
                    }
                } else {
                    alert(response.data.message || "Failed to add FNSKU");
                }
            } catch (error) {
                console.error("Error adding FNSKU:", error);
                alert("Failed to add FNSKU. Please try again.");
            }
        },

        focusNext(refName) {
            this.$nextTick(() => {
                if (this.$refs[refName]) {
                    this.$refs[refName].focus();
                }
            });
        },
    },
    watch: {
        searchQuery() {
            this.currentPage = 1; // Reset to first page on search
            this.first = 0
            this.fetchInventory();
        },
    },
    mounted() {
        this.fetchInventory();
    },
};
