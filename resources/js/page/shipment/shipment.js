import { eventBus } from "../../components/eventbus";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ShipmentModule",
    data() {
        return {
            shipments: [],
            loading: true,

            // Filters
            selectedStore: "",
            selectedCarrier: "",
            orderByFilter: "desc",

            // Statistics
            stats: {
                total_shipments: 0,
                shipped_today: 0,
                shipped_this_week: 0,
                shipped_this_month: 0,
                by_carrier: [],
                by_store: [],
            },

            // Modals
            showDetailsModal: false,
            showStatsModal: false,
            selectedShipment: null,

            // manual Deliver
            manualDeliverLoading: false,

            //pagination
            currentPage: 1,
            totalRecords: 1,
            perPage: 10,
            first: 0 //paginator internal state
        };
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery || "";
        },
    },
    methods: {
        /**
         * Fetch shipments from API
         */
        async fetchShipments() {
            this.loading = true;

            try {
                console.log("Fetching shipments with params:", {
                    search: this.searchQuery,
                    page: this.currentPage,
                    per_page: this.perPage,
                    store: this.selectedStore,
                    carrier: this.selectedCarrier,
                    order_by: this.orderByFilter,
                });

                const response = await axios.get(
                    `${API_BASE_URL}/api/shipments`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            store: this.selectedStore,
                            carrier: this.selectedCarrier,
                            order_by: this.orderByFilter,
                        },
                        withCredentials: true,
                    },
                );

                console.log("API Response:", response);

                if (response.data && response.data.success) {
                    this.shipments = response.data.data || [];
                    this.totalRecords = response.data.total || 1;

                    console.log("Shipments loaded:", this.shipments.length);
                } else {
                    console.error("Invalid response format:", response.data);
                    this.shipments = [];
                    this.totalRecords = 1;
                }
            } catch (error) {
                console.error("Error fetching shipments:", error);
                this.shipments = [];
                this.totalRecords = 1;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Fetch stores for dropdown
         */
        async fetchStores() {
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/shipments/stores`,
                    {
                        withCredentials: true,
                    },
                );

                if (response.data && response.data.success) {
                    const stores = response.data.stores || [];
                    this.storeOptions = [
                        { label: "All Stores", value: "" },
                        ...stores.map((store) => ({
                            label: store,
                            value: store,
                        })),
                    ];
                }
            } catch (error) {
                console.error("Error fetching stores:", error);
            }
        },

        /**
         * Fetch carriers for dropdown
         */
        async fetchCarriers() {
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/shipments/carriers`,
                    {
                        withCredentials: true,
                    },
                );

                if (response.data && response.data.success) {
                    const carriers = response.data.carriers || [];
                    this.carrierOptions = [
                        { label: "All Carriers", value: "" },
                        ...carriers.map((carrier) => ({
                            label: carrier,
                            value: carrier,
                        })),
                    ];
                }
            } catch (error) {
                console.error("Error fetching carriers:", error);
            }
        },

        /**
         * Fetch statistics
         */
        async fetchStats() {
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/shipments/stats`,
                    {
                        withCredentials: true,
                    },
                );

                if (response.data && response.data.success) {
                    this.stats = response.data.stats;
                }
            } catch (error) {
                console.error("Error fetching stats:", error);
            }
        },

        /**
         * Change store filter
         */
        changeStore() {
            this.currentPage = 1;
            this.fetchShipments();
        },

        /**
         * Change carrier filter
         */
        changeCarrier() {
            this.currentPage = 1;
            this.fetchShipments();
        },

        /**
         * Change order by
         */
        changeOrderBy() {
            this.currentPage = 1;
            this.fetchShipments();
        },

        /**
         * Refresh all data
         */
        async refreshData() {
            await Promise.all([
                this.fetchShipments(),
                this.fetchStats(),
                this.fetchStores(),
                this.fetchCarriers(),
            ]);
        },

        onPageChange(event) {
            this.first = event.first
            this.currentPage = event.page + 1; // convert to 1-based
            this.perPage     = event.rows;
            this.fetchInventory();
        },

        /**
         * View shipment details
         */
        viewDetails(shipment) {
            this.selectedShipment = shipment;
            this.showDetailsModal = true;
        },

        /**
         * Close details modal
         */
        closeDetailsModal() {
            this.showDetailsModal = false;
            this.selectedShipment = null;
        },

        /**
         * Open statistics modal
         */
        openStatsModal() {
            this.showStatsModal = true;
        },

        /**
         * Track package (open tracking URL)
         */
        trackPackage(shipment) {
            if (!shipment.tracking_number) {
                alert("No tracking number available for this shipment.");
                return;
            }

            const carrier = (shipment.carrier || "").toUpperCase();
            let trackingUrl = "";

            if (carrier.includes("UPS")) {
                trackingUrl = `https://www.ups.com/track?tracknum=${shipment.tracking_number}`;
            } else if (carrier.includes("FEDEX")) {
                trackingUrl = `https://www.fedex.com/fedextrack/?trknbr=${shipment.tracking_number}`;
            } else if (carrier.includes("USPS")) {
                trackingUrl = `https://tools.usps.com/go/TrackConfirmAction?tLabels=${shipment.tracking_number}`;
            } else if (carrier.includes("DHL")) {
                trackingUrl = `https://www.dhl.com/en/express/tracking.html?AWB=${shipment.tracking_number}`;
            } else {
                alert(`Tracking URL not available for carrier: ${carrier}`);
                return;
            }

            window.open(trackingUrl, "_blank");
        },

        /**
         * Format date for display
         */
        formatDate(dateStr) {
            if (!dateStr || dateStr === "N/A") return "N/A";
            try {
                const date = new Date(dateStr);
                return (
                    date.toLocaleDateString() +
                    " " +
                    date.toLocaleTimeString([], {
                        hour: "2-digit",
                        minute: "2-digit",
                    })
                );
            } catch (e) {
                return dateStr;
            }
        },

        /**
         * Calculate percentage for statistics
         */
        getPercentage(value, total) {
            if (!total || total === 0) return 0;
            return Math.round((value / total) * 100);
        },

        async manualDeliver(shipment) {
            if (this.manualDeliverLoading) return;

            const productId = shipment?.product_id ?? null;
            const outboundId = shipment?.outboundorderitemid ?? null;

            if (!productId) return alert("Missing product_id.");
            if (!outboundId) return alert("Missing outboundorderitemid.");

            if ((shipment?.order_status || "") !== "Shipped") {
                return alert(
                    `Manual Deliver only allowed for Shipped.\nCurrent: ${shipment?.order_status || "N/A"}`,
                );
            }

            if (
                !confirm(
                    `Mark as DELIVERED?\n\nProductID: ${productId}\nOutboundOrderItemID: ${outboundId}`,
                )
            )
                return;

            this.manualDeliverLoading = true;

            try {
                const resp = await axios.post(
                    `${API_BASE_URL}/api/shipments/manual-deliver`,
                    { product_id: productId, outboundorderitemid: outboundId },
                    { withCredentials: true },
                );

                if (resp?.data?.success) {
                    alert("Manual Deliver completed ✅");
                    await Promise.all([
                        this.fetchShipments(),
                        this.fetchStats(),
                    ]);
                    this.showDetailsModal = false;
                    this.selectedShipment = null;
                } else {
                    alert(resp?.data?.message || "Manual Deliver failed.");
                }
            } catch (e) {
                console.error("manualDeliver error:", e);
                alert(
                    e?.response?.data?.message ||
                        e?.message ||
                        "Manual Deliver error.",
                );
            } finally {
                this.manualDeliverLoading = false;
            }
        },
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.first = 0;
            this.fetchShipments();
        },
    },
    mounted() {
        // Setup axios defaults
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;

        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common["X-CSRF-TOKEN"] =
                token.getAttribute("content");
        }

        // Load Font Awesome if not already loaded
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fontAwesome = document.createElement("link");
            fontAwesome.rel = "stylesheet";
            fontAwesome.href =
                "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css";
            document.head.appendChild(fontAwesome);
        }

        // Initial data load
        this.fetchStores();
        this.fetchCarriers();
        this.fetchStats();
        this.fetchShipments();
    },
};
