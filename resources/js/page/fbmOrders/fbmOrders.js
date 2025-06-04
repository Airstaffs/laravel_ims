import { eventBus } from "../../components/eventBus";
import "../../../css/modules.css";
import "./fbmOrders.css";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "FbmOrderModule",
    components: {
        // REMOVED ALL COMPONENT REFERENCES - USING INLINE MODALS ONLY
    },
    data() {
        return {
            apiBaseUrl: window.location.origin,
            orders: [],
            loading: true,
            currentPage: 1,
            totalPages: 1,
            perPage: 10,
            selectAll: false,

            // For sorting and filtering
            sortColumn: "purchase_date",
            sortOrder: "desc",

            // Store filter
            stores: [],
            selectedStore: "",
            statusFilter: "",

            // For order details modal
            showOrderDetailsModal: false,
            selectedOrder: null,

            // For process modal
            showProcessModal: false,
            currentProcessOrder: null,
            selectedItems: [],
            processData: {
                shipmentType: "Standard",
                trackingNumber: "",
                notes: "",
            },
            isProcessing: false,

            // For auto dispense (standalone and in process modal)
            showAutoDispenseModal: false,
            autoDispenseOrder: null,
            dispenseProducts: [],
            selectedDispenseProducts: {},
            loadingDispenseProducts: false,
            processingAutoDispense: false,

            // For persistent order selection across pagination
            persistentSelectedOrderIds: [],
            dispenseItemsSelected: [],

            // for shipment-label modal
            showShipmentLabelModal: false,
            selectedShipmentData: null,

            // for workHistory modal
            showWorkHistoryModal: false,
            workHistory: null,
            error: null,

            // Enhanced work history filters and stats
            workHistoryFilters: {
                sortBy: "purchase_date",
                startDate: "2024-05-20T05:49",
                endDate: "2025-06-03T05:49",
                userId: "all",
                lateOrders: "",
                searchQuery: "",
                carrierFilter: "",
                storeFilter: "",
            },
            workHistoryStats: {
                totalOrders: 0,
            },
        };
    },
    computed: {
        // For global search
        searchQuery() {
            return eventBus.searchQuery || "";
        },

        // Check if any orders are selected
        hasSelectedOrders() {
            return this.persistentSelectedOrderIds.length > 0;
        },

        // Form validation for processing
        isProcessFormValid() {
            return (
                this.selectedItems.length > 0 &&
                this.processData.trackingNumber.trim() !== "" &&
                this.processData.shipmentType !== ""
            );
        },

        // Check if we can confirm auto dispense
        canConfirmDispense() {
            return Object.keys(this.selectedDispenseProducts).length > 0;
        },

        // Check if current order has items without product_id assigned
        currentOrderHasUnassignedItems() {
            if (!this.currentProcessOrder || !this.currentProcessOrder.items)
                return false;
            return this.currentProcessOrder.items.some(
                (item) => !this.isItemDispensed(item)
            );
        },

        // NEW: Check if current order has any dispensed items (for Cancel Dispense button)
        currentOrderHasDispensedItems() {
            if (!this.currentProcessOrder || !this.currentProcessOrder.items)
                return false;
            return this.currentProcessOrder.items.some((item) =>
                this.isItemDispensed(item)
            );
        },

        // Check if an order can be selected (has dispensed items)
        canSelectOrder() {
            return (order) => {
                return this.hasDispensedItems(order);
            };
        },

        // Get only valid dispensed items
        validDispenseItems() {
            return this.dispenseItemsSelected.filter((itemId) => {
                let foundItem = null;
                this.orders.forEach((order) => {
                    if (order.items) {
                        const item = order.items.find(
                            (i) => i.outboundorderitemid === itemId
                        );
                        if (item) {
                            foundItem = item;
                        }
                    }
                });

                return foundItem && this.isItemDispensed(foundItem);
            });
        },
    },
    methods: {
        // for shipment-label modal
        //________________________________________________________________________________
        openShipmentLabelModal() {
            this.showShipmentLabelModal = true;
        },
        closeShipmentLabelModal() {
            this.showShipmentLabelModal = false;
        },

        // WORK HISTORY METHODS - FIXED VERSION
        openWorkHistoryModal() {
            console.log("🚀 Opening work history modal..."); // DEBUG
            this.showWorkHistoryModal = true;
            this.fetchWorkHistory();

            // Force DOM update and ensure modal visibility
            this.$nextTick(() => {
                const modal = document.querySelector(".modal.workHistory");
                if (modal) {
                    modal.classList.add("show");
                    modal.style.display = "flex";
                    console.log("✅ Modal should now be visible");
                } else {
                    console.error("❌ Modal element not found in DOM");
                }
            });
        },

        closeWorkHistoryModal() {
            console.log("🔒 Closing work history modal...");
            this.showWorkHistoryModal = false;

            // Also force hide via DOM manipulation
            this.$nextTick(() => {
                const modal = document.querySelector(".modal.workHistory");
                if (modal) {
                    modal.classList.remove("show");
                    modal.style.display = "none";
                }
            });
        },

        async fetchWorkHistory() {
            console.log("🔄 fetchWorkHistory called - using POST method"); // DEBUG LINE
            this.loading = true;
            this.error = null;
            try {
                // Use the enhanced payload format
                const payload = {
                    user_id: this.workHistoryFilters.userId,
                    start_date: this.workHistoryFilters.startDate
                        ? this.formatDateForAPI(
                              this.workHistoryFilters.startDate
                          )
                        : "2024-05-20",
                    end_date: this.workHistoryFilters.endDate
                        ? this.formatDateForAPI(this.workHistoryFilters.endDate)
                        : "2025-06-01",
                    sort_by: this.workHistoryFilters.sortBy,
                    sort_order: "DESC",
                    search_query: this.workHistoryFilters.searchQuery || "",
                    late_orders: this.workHistoryFilters.lateOrders || "",
                    carrier_filter: this.workHistoryFilters.carrierFilter || "",
                    store_filter: this.workHistoryFilters.storeFilter || "",
                };

                console.log(
                    "Sending work history request with payload:",
                    payload
                );

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/work-history`,
                    payload,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                console.log("Work history response:", response);

                // Handle the response based on your controller's actual return format
                if (response.data && response.data.success) {
                    // Your controller returns 'history' not 'data'
                    this.workHistory = response.data.history;
                    this.workHistoryStats.totalOrders = this.workHistory
                        ? this.workHistory.length
                        : 0;

                    // Show success message if available
                    if (response.data.message) {
                        console.log(
                            "Work history message:",
                            response.data.message
                        );
                    }
                } else {
                    this.workHistory = response.data;
                    this.workHistoryStats.totalOrders = Array.isArray(
                        this.workHistory
                    )
                        ? this.workHistory.length
                        : 0;
                }
            } catch (err) {
                this.error = "Failed to load work history.";
                console.error("Work history fetch error:", err);

                // More detailed error logging
                if (err.response) {
                    console.error("Error response:", err.response.data);
                    console.error("Error status:", err.response.status);
                }
            } finally {
                this.loading = false;
            }
        },

        // Format date for API (convert from datetime-local to YYYY-MM-DD)
        formatDateForAPI(dateTimeString) {
            if (!dateTimeString) return "";
            return dateTimeString.split("T")[0];
        },

        // Format date for work history table display
        formatWorkDate(dateStr) {
            if (!dateStr || dateStr === "N/A") return "N/A";
            try {
                const date = new Date(dateStr);
                return (
                    date.toLocaleDateString("en-US", {
                        month: "2-digit",
                        day: "2-digit",
                        year: "numeric",
                    }) +
                    " " +
                    date.toLocaleTimeString("en-US", {
                        hour: "2-digit",
                        minute: "2-digit",
                        hour12: true,
                    })
                );
            } catch (e) {
                return dateStr;
            }
        },

        // Export work history functionality
        async exportWorkHistory() {
            try {
                const payload = {
                    user_id: this.workHistoryFilters.userId,
                    start_date: this.workHistoryFilters.startDate
                        ? this.formatDateForAPI(
                              this.workHistoryFilters.startDate
                          )
                        : "2024-05-20",
                    end_date: this.workHistoryFilters.endDate
                        ? this.formatDateForAPI(this.workHistoryFilters.endDate)
                        : "2025-06-01",
                    sort_by: this.workHistoryFilters.sortBy,
                    sort_order: "DESC",
                    search_query: this.workHistoryFilters.searchQuery || "",
                    late_orders: this.workHistoryFilters.lateOrders || "",
                    carrier_filter: this.workHistoryFilters.carrierFilter || "",
                    store_filter: this.workHistoryFilters.storeFilter || "",
                    export: true,
                };

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/work-history/export`,
                    payload,
                    {
                        responseType: "blob",
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                // Create download link
                const url = window.URL.createObjectURL(
                    new Blob([response.data])
                );
                const link = document.createElement("a");
                link.href = url;
                link.setAttribute(
                    "download",
                    `work-history-${new Date().toISOString().split("T")[0]}.csv`
                );
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);

                alert("Work history exported successfully!");
            } catch (error) {
                console.error("Error exporting work history:", error);
                alert("Failed to export work history. Please try again.");
            }
        },

        // Helper methods for exact data display matching the screenshot
        getMainDate(orderInfo) {
            if (
                !orderInfo.datecreatedsheesh ||
                orderInfo.datecreatedsheesh === "N/A"
            ) {
                return "N/A";
            }
            try {
                const date = new Date(orderInfo.datecreatedsheesh);
                return date.toLocaleDateString("en-US", {
                    month: "2-digit",
                    day: "2-digit",
                    year: "numeric",
                });
            } catch (e) {
                return "N/A";
            }
        },

        getSubDate(orderInfo) {
            if (
                !orderInfo.purchaselabeldate ||
                orderInfo.purchaselabeldate === "N/A"
            ) {
                return "";
            }
            try {
                const date = new Date(orderInfo.purchaselabeldate);
                return date.toLocaleTimeString("en-US", {
                    hour: "2-digit",
                    minute: "2-digit",
                    hour12: true,
                });
            } catch (e) {
                return "";
            }
        },

        getCarrierClass(carrier) {
            if (!carrier || carrier === "N/A") {
                return "carrier-na";
            }
            const carrierUpper = carrier.toString().toUpperCase();
            if (carrierUpper.includes("UPS")) {
                return "carrier-ups";
            } else if (
                carrierUpper.includes("FEDEX") ||
                carrierUpper.includes("FEDX")
            ) {
                return "carrier-fedex";
            } else if (carrierUpper.includes("USPS")) {
                return "carrier-usps";
            } else if (carrierUpper.includes("DHL")) {
                return "carrier-dhl";
            }
            return "carrier-other";
        },

        getCarrierText(carrier) {
            if (!carrier || carrier === "N/A") {
                return "N/A";
            }
            const carrierUpper = carrier.toString().toUpperCase();
            if (carrierUpper.includes("UPS")) {
                return "USPS";
            } else if (
                carrierUpper.includes("FEDEX") ||
                carrierUpper.includes("FEDX")
            ) {
                return "FEDEX";
            } else if (carrierUpper.includes("USPS")) {
                return "USPS";
            } else if (carrierUpper.includes("DHL")) {
                return "DHL";
            }
            return carrier;
        },

        getDeliveryStatus(orderInfo) {
            if (
                !orderInfo.datedeliveredsheesh ||
                orderInfo.datedeliveredsheesh === "N/A"
            ) {
                return "N/A";
            }
            return "N/A"; // Based on screenshot, most show N/A
        },

        getDeliverySubDate(orderInfo) {
            return "N/A"; // Based on screenshot
        },

        getDispensedStatus(orderInfo) {
            return "N/A"; // Based on screenshot, most show N/A
        },

        getRemarks(orderInfo) {
            return "N/A"; // Based on screenshot, most show N/A
        },

        PurchaseShippingLabel() {
            if (this.dispenseItemsSelected.length === 0) {
                alert("Please select items first.");
                return;
            }

            const itemIds = this.dispenseItemsSelected.join(",");
            axios
                .get("api/fbm-orders/shipping-label-selected-items", {
                    params: { itemIds },
                })
                .then((response) => {
                    this.selectedShipmentData = response.data; // Store result
                    this.openShipmentLabelModal();
                })
                .catch((error) => {
                    alert("Failed to fetch shipment info.");
                    console.error(error);
                });
        },
        handleShipmentLabelSubmit(data) {
            console.log("Submitted shipment label data:", data);
            this.closeShipmentLabelModal();
        },
        //________________________________________________________________________________

        // Normalize store name for consistent comparison
        normalizeStoreName(storeName) {
            if (!storeName) return "";
            return storeName.toLowerCase().replace(/[\s\-_]+/g, "");
        },

        // Format date for display
        formatDate(dateStr) {
            if (!dateStr) return "N/A";
            const date = new Date(dateStr);
            return date.toLocaleString();
        },

        // Format address for display
        formatAddress(address, fullFormat = false) {
            if (!address) return "N/A";

            if (fullFormat) {
                return address.split(", ").join("\n");
            }

            return address;
        },

        // Get CSS class for status badges
        getStatusClass(status) {
            switch (status) {
                case "Pending":
                    return "status-badge status-pending";
                case "Unshipped":
                    return "status-badge status-pending";
                case "Shipped":
                    return "status-badge status-shipped";
                case "Canceled":
                    return "status-badge status-canceled";
                default:
                    return "status-badge";
            }
        },

        // HELPER METHODS FOR NULL CHECKING
        hasTrackingNumber(order) {
            return (
                order &&
                order.items &&
                Array.isArray(order.items) &&
                order.items.some((item) => item.tracking_number)
            );
        },

        getShipStatus(order) {
            if (!order || !order.items || !Array.isArray(order.items)) {
                return "Not Shipped";
            }
            return order.items.some((item) => item.tracking_number)
                ? "Shipped"
                : "Not Shipped";
        },

        getTrackingStatus(order) {
            if (!order || !order.items || !Array.isArray(order.items)) {
                return "Not Available";
            }
            const trackedItem = order.items.find(
                (item) => item.tracking_status
            );
            return trackedItem ? trackedItem.tracking_status : "Not Available";
        },

        formatShipByDate(date) {
            if (!date) return "N/A";
            return this.formatDate(date);
        },

        formatDeliveryDate(date) {
            if (!date) return "N/A";
            return this.formatDate(date);
        },

        // Check if order has any dispensed items
        hasDispensedItems(order) {
            if (!order || !order.items || !Array.isArray(order.items)) {
                return false;
            }

            return order.items.some((item) => {
                return (
                    item.product_id ||
                    (item.dispensed_products &&
                        item.dispensed_products.length > 0) ||
                    (item.dispensed_count && item.dispensed_count > 0)
                );
            });
        },

        // Check if a specific item is dispensed
        isItemDispensed(item) {
            if (!item) return false;

            return (
                item.product_id ||
                (item.dispensed_products &&
                    item.dispensed_products.length > 0) ||
                (item.dispensed_count && item.dispensed_count > 0)
            );
        },

        // Get dispensed product count for an item
        getDispensedProductCount(item) {
            if (!item) return 0;

            if (item.dispensed_count !== undefined) {
                return item.dispensed_count;
            }

            if (
                item.dispensed_products &&
                Array.isArray(item.dispensed_products)
            ) {
                return item.dispensed_products.length;
            }

            return item.product_id ? 1 : 0;
        },

        // Get dispensed products details for display
        getDispensedProductsDisplay(item) {
            if (!this.isItemDispensed(item)) return [];

            if (
                item.dispensed_products &&
                Array.isArray(item.dispensed_products)
            ) {
                return item.dispensed_products;
            }

            if (item.product_id) {
                return [
                    {
                        product_id: item.product_id,
                        title: item.title || "N/A",
                        asin: item.asin || "N/A",
                        warehouseLocation: item.warehouseLocation || "",
                        serialNumber: item.serialNumber || "",
                        rtCounter: item.rtCounter || "",
                        FNSKU: item.FNSKU || "",
                    },
                ];
            }

            return [];
        },

        // Get condition display text
        getConditionDisplay(item) {
            if (!item) return "N/A";

            if (item.condition) return item.condition;
            if (item.ordered_condition) return item.ordered_condition;

            const conditionId = item.ConditionId || "";
            const subtypeId = item.ConditionSubtypeId || "";

            return `${conditionId}${subtypeId}`;
        },

        // Check if a product's condition is valid for the item's condition
        isConditionValid(itemCondition, productCondition, storeName) {
            const normalizedStore = this.normalizeStoreName(storeName);

            if (normalizedStore === "allrenewed") {
                const conditionHierarchy = {
                    "Refurbished - Excellent": 3,
                    "Refurbished - Good": 2,
                    "Refurbished - Acceptable": 1,
                };

                const itemRank = conditionHierarchy[itemCondition] || 0;
                const productRank = conditionHierarchy[productCondition] || 0;

                return productRank >= itemRank;
            }

            return itemCondition === productCondition;
        },

        // Format store-specific condition
        formatStoreSpecificCondition(
            conditionId,
            conditionSubtypeId,
            storeName
        ) {
            const normalizedStore = this.normalizeStoreName(storeName);

            if (normalizedStore === "allrenewed") {
                const combinedCondition = conditionId + conditionSubtypeId;

                switch (combinedCondition) {
                    case "NewNew":
                        return "Refurbished - Excellent";
                    case "NewGood":
                        return "Refurbished - Good";
                    case "NewAcceptable":
                        return "Refurbished - Acceptable";
                    default:
                        return combinedCondition;
                }
            }

            return conditionId + conditionSubtypeId;
        },

        // Open scanner modal method
        openScannerModal() {
            if (this.$refs.scanner) {
                this.$refs.scanner.openScannerModal();
            }
        },

        // Initialize dispenseItemsSelected on component mount
        initializeDispenseItems() {
            this.dispenseItemsSelected = [];

            this.orders.forEach((order) => {
                if (order.items) {
                    order.items.forEach((item) => {
                        if (this.isItemDispensed(item)) {
                            this.dispenseItemsSelected.push(
                                item.outboundorderitemid
                            );
                        }
                    });
                }
            });
        },

        // Handle order checkbox change event
        handleOrderCheckChange(order) {
            if (!this.hasDispensedItems(order)) {
                order.checked = false;
                return;
            }

            if (order.checked) {
                if (
                    !this.persistentSelectedOrderIds.includes(
                        order.outboundorderid
                    )
                ) {
                    this.persistentSelectedOrderIds.push(order.outboundorderid);
                }
            } else {
                this.persistentSelectedOrderIds =
                    this.persistentSelectedOrderIds.filter(
                        (id) => id !== order.outboundorderid
                    );
            }
        },

        // Fetch orders from the API with persistent selection
        async fetchOrders() {
            this.loading = true;

            try {
                console.log("Fetching orders with params:", {
                    search: this.searchQuery,
                    page: this.currentPage,
                    per_page: this.perPage,
                    store: this.selectedStore,
                    status: this.statusFilter,
                    sort_column: this.sortColumn,
                    sort_order: this.sortOrder,
                });

                const response = await axios.get(
                    `${API_BASE_URL}/api/fbm-orders`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            store: this.selectedStore,
                            status: this.statusFilter,
                            sort_column: this.sortColumn,
                            sort_order: this.sortOrder,
                        },
                        withCredentials: true,
                    }
                );

                console.log("API Response:", response);

                if (response.data && response.data.success) {
                    this.orders = (response.data.data || []).map((order) => {
                        const processedItems = Array.isArray(order.items)
                            ? order.items.map((item) => {
                                  return item;
                              })
                            : [];

                        const isChecked =
                            this.persistentSelectedOrderIds.includes(
                                order.outboundorderid
                            );

                        return {
                            ...order,
                            checked: isChecked,
                            items: processedItems,
                        };
                    });

                    console.log(
                        "Processed orders with dispensed items:",
                        this.orders
                    );

                    this.totalPages = response.data.last_page || 1;

                    this.initializeDispenseItems();
                } else {
                    console.error("Invalid response format:", response.data);
                    this.orders = [];
                    this.totalPages = 1;
                }
            } catch (error) {
                console.error("Error fetching orders:", error);
                this.orders = [];
                this.totalPages = 1;
            } finally {
                this.loading = false;
            }
        },

        // Fetch stores for dropdown
        async fetchStores() {
            try {
                console.log("Fetching stores");
                const response = await axios.get(
                    `${API_BASE_URL}/api/fbm-orders/stores`,
                    {
                        withCredentials: true,
                    }
                );
                console.log("Stores response:", response);
                this.stores = response.data || [];
            } catch (error) {
                console.error("Error fetching stores:", error);
                this.stores = [];
            }
        },

        // Change store filter and clear selections
        changeStore() {
            this.currentPage = 1;
            this.clearAllSelections();
            this.fetchOrders();
        },

        // Change status filter and clear selections
        changeStatusFilter() {
            this.currentPage = 1;
            this.clearAllSelections();
            this.fetchOrders();
        },

        // Refresh data
        refreshData() {
            this.fetchOrders();
        },

        // Pagination methods
        changePerPage() {
            this.currentPage = 1;
            this.fetchOrders();
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchOrders();
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchOrders();
            }
        },

        // Toggle select all orders
        toggleAll() {
            const newValue = this.selectAll;

            this.orders.forEach((order) => {
                if (this.hasDispensedItems(order)) {
                    order.checked = newValue;
                    this.handleOrderCheckChange(order);
                } else {
                    order.checked = false;
                }
            });
        },

        // Clear all selections
        clearAllSelections() {
            this.persistentSelectedOrderIds = [];
            this.selectAll = false;
            this.orders.forEach((order) => {
                order.checked = false;
            });
        },

        // Sorting method
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = column;
                this.sortOrder = "asc";
            }

            this.fetchOrders();
        },

        // Open order details modal
        viewOrderDetails(order) {
            this.selectedOrder = order;
            this.showOrderDetailsModal = true;
        },

        // Close order details modal
        closeOrderDetailsModal() {
            this.showOrderDetailsModal = false;
            this.selectedOrder = null;
        },

        // Process modal functions
        openProcessModal(order) {
            this.currentProcessOrder = order;
            this.selectedItems =
                order && order.items && Array.isArray(order.items)
                    ? order.items.map((item) => item.outboundorderitemid)
                    : [];
            this.resetProcessData();
            this.showProcessModal = true;
        },

        // Open process modal from details
        openProcessModalFromDetails(order) {
            this.closeOrderDetailsModal();
            this.openProcessModal(order);
        },

        // Reset process form data
        resetProcessData() {
            this.processData = {
                shipmentType: "Standard",
                trackingNumber: "",
                notes: "",
            };
        },

        // Close process modal
        closeProcessModal() {
            this.showProcessModal = false;
            this.currentProcessOrder = null;
            this.selectedItems = [];
            this.processingAutoDispense = false;
        },

        // Submit process order
        async submitProcessOrder() {
            if (!this.isProcessFormValid) return;

            try {
                this.isProcessing = true;

                const processData = {
                    order_id: this.currentProcessOrder.outboundorderid,
                    item_ids: this.selectedItems,
                    shipment_type: this.processData.shipmentType,
                    tracking_number: this.processData.trackingNumber,
                    notes: this.processData.notes,
                };

                console.log("Processing order with data:", processData);

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/process`,
                    processData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                console.log("Process response:", response);

                if (response.data && response.data.success) {
                    alert("Order processed successfully");
                    this.closeProcessModal();
                    this.fetchOrders();
                } else {
                    alert(
                        `Error: ${
                            response.data.message || "Failed to process order"
                        }`
                    );
                }
            } catch (error) {
                console.error("Error processing order:", error);
                alert("Failed to process order. Please try again.");
            } finally {
                this.isProcessing = false;
            }
        },

        // Auto Dispense Functions
        autoDispense(order) {
            const itemsNeedingDispense = order.items.filter((item) => {
                const dispensedCount = this.getDispensedProductCount(item);
                return dispensedCount < item.quantity_ordered;
            });

            if (itemsNeedingDispense.length === 0) {
                alert("All items in this order are already fully dispensed.");
                return;
            }

            const itemIds = itemsNeedingDispense.map(
                (item) => item.outboundorderitemid
            );

            let message = `Auto-dispense products for ${itemsNeedingDispense.length} item(s) in this order?\n\n`;
            message += "Items to dispense:\n";
            itemsNeedingDispense.forEach((item) => {
                const dispensedCount = this.getDispensedProductCount(item);
                const remaining = item.quantity_ordered - dispensedCount;
                message += `• ${item.platform_title} (${remaining} needed)\n`;
            });

            if (confirm(message)) {
                this.performStandaloneAutoDispense(
                    order.outboundorderid,
                    itemIds
                );
            }
        },

        async performStandaloneAutoDispense(orderId, itemIds) {
            try {
                const requestData = {
                    order_id: orderId,
                    item_ids: itemIds,
                };

                console.log("Standalone auto dispense request:", requestData);

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/auto-dispense`,
                    requestData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data && response.data.success) {
                    alert(
                        `Auto-dispensing completed successfully!\n\nDispensed ${response.data.dispensed_count} products across ${response.data.items_processed} items.`
                    );

                    await this.fetchOrders();

                    if (
                        this.selectedOrder &&
                        this.selectedOrder.outboundorderid === orderId
                    ) {
                        const updatedOrder = this.orders.find(
                            (o) => o.outboundorderid === orderId
                        );
                        if (updatedOrder) {
                            this.selectedOrder = { ...updatedOrder };
                        }
                    }
                } else {
                    alert(
                        `Error in auto-dispensing: ${
                            response.data.message || "Unknown error"
                        }`
                    );
                }
            } catch (error) {
                console.error("Error in standalone auto dispense:", error);
                alert("Failed to perform auto-dispensing. Please try again.");
            }
        },

        // More methods continue here...
        closeAutoDispenseModal() {
            this.showAutoDispenseModal = false;
            this.autoDispenseOrder = null;
            this.dispenseProducts = [];
            this.selectedDispenseProducts = {};
        },

        async cancelDispense(order) {
            if (!this.hasDispensedItems(order)) return;

            if (
                !confirm(
                    "Are you sure you want to cancel dispense for this order?"
                )
            ) {
                return;
            }

            try {
                const itemIds = order.items
                    .filter((item) => this.isItemDispensed(item))
                    .map((item) => item.outboundorderitemid);

                if (itemIds.length === 0) return;

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/cancel-dispense`,
                    {
                        order_id: order.outboundorderid,
                        item_ids: itemIds,
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data && response.data.success) {
                    alert("Dispense canceled successfully");
                    await this.fetchOrders();
                } else {
                    alert(
                        `Error: ${
                            response.data.message || "Failed to cancel dispense"
                        }`
                    );
                }
            } catch (error) {
                console.error("Error canceling dispense:", error);
                alert("Failed to cancel dispense. Please try again.");
            }
        },

        // Add remaining methods...
        async startAutoDispenseInProcess() {
            this.processingAutoDispense = true;
            this.dispenseProducts = [];
            this.selectedDispenseProducts = {};

            const itemsToDispense = this.currentProcessOrder.items
                .filter((item) => {
                    const dispensedCount = this.getDispensedProductCount(item);
                    return dispensedCount < item.quantity_ordered;
                })
                .map((item) => item.outboundorderitemid);

            if (itemsToDispense.length === 0) {
                alert("All items are already fully dispensed.");
                this.processingAutoDispense = false;
                return;
            }

            await this.loadAndAutoDispenseProducts(itemsToDispense);
        },

        cancelAutoDispenseProcess() {
            this.processingAutoDispense = false;
            this.dispenseProducts = [];
            this.selectedDispenseProducts = {};
        },

        selectDispenseProduct(itemId, slotIndex, product) {
            const key = `${itemId}-${slotIndex}`;
            const updatedSelection = { ...this.selectedDispenseProducts };

            if (
                updatedSelection[key] &&
                updatedSelection[key].ProductID === product.ProductID
            ) {
                delete updatedSelection[key];
            } else {
                updatedSelection[key] = product;
            }

            this.selectedDispenseProducts = updatedSelection;
        },

        async confirmAutoDispenseInProcess() {
            if (Object.keys(this.selectedDispenseProducts).length === 0) return;

            try {
                const dispenseItems = Object.entries(
                    this.selectedDispenseProducts
                ).map(([key, product]) => {
                    const itemId = parseInt(key.split("-")[0]);
                    return {
                        item_id: itemId,
                        product_id: product.ProductID,
                    };
                });

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/dispense`,
                    {
                        order_id: this.currentProcessOrder.outboundorderid,
                        dispense_items: dispenseItems,
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data && response.data.success) {
                    alert("Items dispensed successfully");
                    this.processingAutoDispense = false;
                    this.dispenseProducts = [];
                    this.selectedDispenseProducts = {};
                    await this.refreshCurrentProcessOrderForModal();
                } else {
                    alert(
                        `Error: ${
                            response.data.message || "Failed to dispense items"
                        }`
                    );
                }
            } catch (error) {
                console.error("Error confirming dispense:", error);
                alert("Failed to dispense items. Please try again.");
            }
        },

        async refreshCurrentProcessOrderForModal() {
            if (!this.currentProcessOrder) return;

            try {
                await this.fetchOrders();

                const updatedOrder = this.orders.find(
                    (o) =>
                        o.outboundorderid ===
                        this.currentProcessOrder.outboundorderid
                );

                if (updatedOrder) {
                    this.currentProcessOrder = {
                        ...updatedOrder,
                        checked: this.currentProcessOrder.checked || false,
                    };

                    this.selectedItems = this.currentProcessOrder.items
                        ? this.currentProcessOrder.items.map(
                              (item) => item.outboundorderitemid
                          )
                        : [];

                    this.initializeDispenseItems();

                    if (
                        this.selectedOrder &&
                        this.selectedOrder.outboundorderid ===
                            this.currentProcessOrder.outboundorderid
                    ) {
                        this.selectedOrder = { ...this.currentProcessOrder };
                    }

                    this.$nextTick(() => {
                        this.$forceUpdate();
                    });
                }
            } catch (error) {
                console.error("Error refreshing process modal content:", error);
            }
        },

        processSelectedOrders() {
            const selectedOrderIds = this.persistentSelectedOrderIds;

            if (selectedOrderIds.length === 0) {
                alert("Please select at least one order to process");
                return;
            }

            const visibleSelectedOrder = this.orders.find((order) =>
                selectedOrderIds.includes(order.outboundorderid)
            );

            if (visibleSelectedOrder) {
                this.openProcessModal(visibleSelectedOrder);
            } else {
                this.fetchSelectedOrderForProcessing(selectedOrderIds[0]);
            }
        },

        async fetchSelectedOrderForProcessing(orderId) {
            try {
                this.loading = true;

                const response = await axios.get(
                    `${API_BASE_URL}/api/fbm-orders/detail`,
                    {
                        params: { order_id: orderId },
                        withCredentials: true,
                    }
                );

                if (response.data && response.data.success) {
                    const order = response.data.data;
                    const processedOrder = {
                        ...order,
                        checked: true,
                    };
                    this.openProcessModal(processedOrder);
                } else {
                    alert(
                        "Could not fetch the selected order. Please try again."
                    );
                }
            } catch (error) {
                console.error("Error fetching order for processing:", error);
                alert("Error fetching the selected order. Please try again.");
            } finally {
                this.loading = false;
            }
        },

        async generatePackingSlip(orderId) {
            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/packing-slip`,
                    { order_id: orderId },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data && response.data.success) {
                    alert("Packing slip generated successfully");
                    if (response.data.pdf_url) {
                        window.open(response.data.pdf_url, "_blank");
                    }
                } else {
                    alert(
                        `Error: ${
                            response.data.message ||
                            "Failed to generate packing slip"
                        }`
                    );
                }
            } catch (error) {
                console.error("Error generating packing slip:", error);
                alert("Failed to generate packing slip. Please try again.");
            }
        },

        async printShippingLabel(orderId) {
            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/shipping-label`,
                    { order_id: orderId },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data && response.data.success) {
                    alert("Shipping label generated successfully");
                    if (response.data.label_url) {
                        window.open(response.data.label_url, "_blank");
                    }
                } else {
                    alert(
                        `Error: ${
                            response.data.message ||
                            "Failed to generate shipping label"
                        }`
                    );
                }
            } catch (error) {
                console.error("Error generating shipping label:", error);
                alert("Failed to generate shipping label. Please try again.");
            }
        },

        confirmCancelOrder(orderId) {
            if (confirm("Are you sure you want to cancel this order?")) {
                this.cancelOrder(orderId);
            }
        },

        async cancelOrder(orderId) {
            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/cancel`,
                    { order_id: orderId },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data && response.data.success) {
                    alert("Order canceled successfully");
                    this.closeOrderDetailsModal();
                    this.fetchOrders();
                } else {
                    alert(
                        `Error: ${
                            response.data.message || "Failed to cancel order"
                        }`
                    );
                }
            } catch (error) {
                console.error("Error canceling order:", error);
                alert("Failed to cancel order. Please try again.");
            }
        },

        async markProductNotFound(productId, item) {
            if (
                !confirm(
                    `Mark this product as "Not Found" and automatically select a replacement?\n\nThis will:\n1. Mark the current product as not found\n2. Remove it from this order\n3. Automatically select a new product if available`
                )
            ) {
                return;
            }

            try {
                let orderId = this.getCurrentOrderId();

                if (!orderId && item && item.outboundorderitemid) {
                    for (const order of this.orders) {
                        if (
                            order.items &&
                            order.items.some(
                                (orderItem) =>
                                    orderItem.outboundorderitemid ===
                                    item.outboundorderitemid
                            )
                        ) {
                            orderId = order.outboundorderid;
                            break;
                        }
                    }
                }

                if (!orderId) {
                    alert("Unable to determine order ID. Please try again.");
                    return;
                }

                const requestData = {
                    product_id: productId,
                    item_id: item.outboundorderitemid,
                    order_id: orderId,
                };

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/mark-not-found`,
                    requestData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data && response.data.success) {
                    let message = 'Product marked as "Not Found" successfully.';

                    if (response.data.replacement_found) {
                        message += `\n\nReplacement product automatically selected:\n• ${response.data.replacement_details.title}\n• Location: ${response.data.replacement_details.warehouseLocation}`;
                    } else {
                        message +=
                            "\n\nNo replacement product was found in inventory.";
                    }

                    alert(message);
                    await this.refreshCurrentContext();
                } else {
                    alert(
                        `Error: ${
                            response.data.message ||
                            "Failed to mark product as not found"
                        }`
                    );
                }
            } catch (error) {
                console.error("Error marking product as not found:", error);
                alert("Failed to mark product as not found. Please try again.");
            }
        },

        getCurrentOrderId() {
            if (this.currentProcessOrder) {
                return this.currentProcessOrder.outboundorderid;
            } else if (this.selectedOrder) {
                return this.selectedOrder.outboundorderid;
            } else if (this.autoDispenseOrder) {
                return this.autoDispenseOrder.outboundorderid;
            }
            return null;
        },

        async refreshCurrentContext() {
            try {
                if (this.currentProcessOrder) {
                    await this.refreshCurrentProcessOrderForModal();
                } else if (this.selectedOrder) {
                    const orderId = this.selectedOrder.outboundorderid;
                    await this.fetchOrders();
                    const updatedOrder = this.orders.find(
                        (o) => o.outboundorderid === orderId
                    );
                    if (updatedOrder) {
                        this.selectedOrder = { ...updatedOrder };
                    }
                } else {
                    await this.fetchOrders();
                }
                this.initializeDispenseItems();
            } catch (error) {
                console.error("Error refreshing context:", error);
            }
        },

        printShippingLabels() {
            const selectedOrderIds = this.persistentSelectedOrderIds;
            if (selectedOrderIds.length === 0) {
                alert("Please select at least one order to print labels");
                return;
            }
            selectedOrderIds.forEach((id) => this.printShippingLabel(id));
        },

        generatePackingSlips() {
            const selectedOrderIds = this.persistentSelectedOrderIds;
            if (selectedOrderIds.length === 0) {
                alert(
                    "Please select at least one order to generate packing slips"
                );
                return;
            }
            selectedOrderIds.forEach((id) => this.generatePackingSlip(id));
        },

        // Additional missing methods that might have been in your original code:

        async loadMatchingProducts() {
            if (!this.autoDispenseOrder) return;

            this.loadingDispenseProducts = true;

            try {
                const itemIds = this.autoDispenseOrder.items
                    .filter((item) => {
                        const dispensedCount =
                            this.getDispensedProductCount(item);
                        return dispensedCount < item.quantity_ordered;
                    })
                    .map((item) => item.outboundorderitemid);

                if (itemIds.length === 0) {
                    this.dispenseProducts = [];
                    this.loadingDispenseProducts = false;
                    return;
                }

                const requestData = {
                    order_id: this.autoDispenseOrder.outboundorderid,
                    item_ids: itemIds,
                };

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/find-dispense-products`,
                    requestData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data && response.data.success) {
                    this.dispenseProducts = response.data.data || [];

                    this.dispenseProducts.forEach((item) => {
                        if (
                            item.matching_products &&
                            item.matching_products.length > 0 &&
                            item.quantity_remaining > 0
                        ) {
                            const neededCount = Math.min(
                                item.quantity_remaining,
                                item.matching_products.length
                            );

                            for (let i = 0; i < neededCount; i++) {
                                const product = item.matching_products[i];
                                const key = `${item.item_id}-${i}`;
                                this.selectedDispenseProducts[key] = product;
                            }
                        }
                    });
                } else {
                    this.dispenseProducts = [];
                }
            } catch (error) {
                console.error("Error loading matching products:", error);
                this.dispenseProducts = [];
            } finally {
                this.loadingDispenseProducts = false;
            }
        },

        async confirmAutoDispense() {
            if (Object.keys(this.selectedDispenseProducts).length === 0) return;

            try {
                const dispenseItems = Object.entries(
                    this.selectedDispenseProducts
                ).map(([key, product]) => {
                    const itemId = parseInt(key.split("-")[0]);
                    return {
                        item_id: itemId,
                        product_id: product.ProductID,
                    };
                });

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/dispense`,
                    {
                        order_id: this.autoDispenseOrder.outboundorderid,
                        dispense_items: dispenseItems,
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data && response.data.success) {
                    alert("Items dispensed successfully");
                    this.closeAutoDispenseModal();
                    await this.fetchOrders();
                } else {
                    alert(
                        `Error: ${
                            response.data.message || "Failed to dispense items"
                        }`
                    );
                }
            } catch (error) {
                console.error("Error confirming dispense:", error);
                alert("Failed to dispense items. Please try again.");
            }
        },

        getDispenseCount(item) {
            if (!item) return "";
            const dispensed = this.getDispensedProductCount(item);
            const ordered = item.quantity_ordered || 0;
            return `${dispensed}/${ordered}`;
        },

        async loadAndAutoDispenseProducts(itemIds) {
            this.loadingDispenseProducts = true;

            try {
                const requestData = {
                    order_id: this.currentProcessOrder.outboundorderid,
                    item_ids: itemIds,
                };

                const findResponse = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/find-dispense-products`,
                    requestData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (
                    findResponse.data &&
                    findResponse.data.success &&
                    findResponse.data.data.length > 0
                ) {
                    const dispenseData = findResponse.data.data;

                    let dispenseMessage =
                        "The following products will be auto-dispensed:\n\n";
                    let totalItemsToDispense = 0;

                    dispenseData.forEach((item) => {
                        if (
                            item.auto_selected_products &&
                            item.auto_selected_products.length > 0
                        ) {
                            dispenseMessage += `${item.ordered_item.platform_title}\n`;
                            dispenseMessage += `  - Quantity needed: ${item.quantity_remaining}\n`;
                            dispenseMessage += `  - Products selected: ${item.auto_selected_products.length}\n`;

                            item.auto_selected_products.forEach((product) => {
                                dispenseMessage += `    • Product ID: ${
                                    product.ProductID
                                } (${
                                    product.warehouseLocation || "No location"
                                })\n`;
                                totalItemsToDispense++;
                            });
                            dispenseMessage += "\n";
                        }
                    });

                    if (totalItemsToDispense === 0) {
                        alert(
                            "No products available for auto-dispensing at this time."
                        );
                        this.processingAutoDispense = false;
                        this.loadingDispenseProducts = false;
                        return;
                    }

                    dispenseMessage += `Total products to dispense: ${totalItemsToDispense}\n\nProceed with auto-dispensing?`;

                    if (confirm(dispenseMessage)) {
                        await this.performAutoDispense(itemIds);
                    } else {
                        this.processingAutoDispense = false;
                    }
                } else {
                    alert(
                        "No matching products found in inventory for auto-dispensing."
                    );
                    this.processingAutoDispense = false;
                }
            } catch (error) {
                console.error("Error in auto dispense:", error);
                alert(
                    "Error finding products for auto-dispensing. Please try again."
                );
                this.processingAutoDispense = false;
            } finally {
                this.loadingDispenseProducts = false;
            }
        },

        async performAutoDispense(itemIds) {
            try {
                const requestData = {
                    order_id: this.currentProcessOrder.outboundorderid,
                    item_ids: itemIds,
                };

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/auto-dispense`,
                    requestData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data && response.data.success) {
                    alert(
                        `Auto-dispensing completed successfully!\n\nDispensed ${response.data.dispensed_count} products across ${response.data.items_processed} items.`
                    );

                    this.processingAutoDispense = false;
                    this.dispenseProducts = [];
                    this.selectedDispenseProducts = {};

                    await this.refreshCurrentProcessOrderForModal();
                } else {
                    alert(
                        `Error in auto-dispensing: ${
                            response.data.message || "Unknown error"
                        }`
                    );
                    this.processingAutoDispense = false;
                }
            } catch (error) {
                console.error("Error performing auto dispense:", error);
                alert("Failed to perform auto-dispensing. Please try again.");
                this.processingAutoDispense = false;
            }
        },

        async loadDispenseProductsForProcess(itemIds) {
            this.loadingDispenseProducts = true;

            try {
                const requestData = {
                    order_id: this.currentProcessOrder.outboundorderid,
                    item_ids: itemIds,
                };

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/find-dispense-products`,
                    requestData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data && response.data.success) {
                    this.dispenseProducts = response.data.data || [];
                    this.selectedDispenseProducts = {};

                    this.dispenseProducts.forEach((item) => {
                        if (
                            item.matching_products &&
                            item.matching_products.length > 0
                        ) {
                            const availableProducts = Math.min(
                                item.quantity_remaining,
                                item.matching_products.length
                            );

                            for (let i = 0; i < availableProducts; i++) {
                                const key = `${item.item_id}-${i}`;
                                this.selectedDispenseProducts[key] =
                                    item.matching_products[i];
                            }
                        }
                    });
                } else {
                    this.dispenseProducts = [];
                }
            } catch (error) {
                console.error("Error loading matching products:", error);
                this.dispenseProducts = [];
            } finally {
                this.loadingDispenseProducts = false;
            }
        },

        async refreshCurrentProcessOrder() {
            if (!this.currentProcessOrder) return;

            try {
                console.log(
                    "Refreshing current process order data for ID:",
                    this.currentProcessOrder.outboundorderid
                );

                // Use the detail endpoint to get comprehensive, up-to-date data
                const response = await axios.get(
                    `${API_BASE_URL}/api/fbm-orders/detail`,
                    {
                        params: {
                            order_id: this.currentProcessOrder.outboundorderid,
                        },
                        withCredentials: true,
                    }
                );

                console.log("Refresh response:", response);

                if (response.data && response.data.success) {
                    const updatedOrder = response.data.data;

                    // Update the current process order with fresh data
                    this.currentProcessOrder = {
                        ...updatedOrder,
                        checked: this.currentProcessOrder.checked || false,
                    };

                    console.log(
                        "Updated current process order:",
                        this.currentProcessOrder
                    );

                    // Also update the corresponding order in the main orders array
                    const orderIndex = this.orders.findIndex(
                        (o) =>
                            o.outboundorderid ===
                            this.currentProcessOrder.outboundorderid
                    );
                    if (orderIndex !== -1) {
                        this.orders[orderIndex] = {
                            ...this.currentProcessOrder,
                            checked: this.orders[orderIndex].checked || false,
                        };
                        console.log(
                            "Updated order in main list at index:",
                            orderIndex
                        );
                    }

                    // Reset selectedItems to include all items
                    this.selectedItems = this.currentProcessOrder.items.map(
                        (item) => item.outboundorderitemid
                    );

                    // Update dispense items selection to reflect newly dispensed items
                    this.initializeDispenseItems();

                    console.log("Process order refresh completed successfully");
                } else {
                    console.error(
                        "Failed to refresh order data:",
                        response.data
                    );
                    // Don't throw error, just log it and continue with existing data
                }
            } catch (error) {
                console.error("Error refreshing order data:", error);

                // Instead of failing completely, let's try to refresh from the main orders list
                console.log("Attempting to refresh from main orders list...");
                try {
                    await this.fetchOrders();

                    // Find the updated order in the main list
                    const updatedOrder = this.orders.find(
                        (o) =>
                            o.outboundorderid ===
                            this.currentProcessOrder.outboundorderid
                    );
                    if (updatedOrder) {
                        this.currentProcessOrder = {
                            ...updatedOrder,
                            checked: this.currentProcessOrder.checked || false,
                        };
                        console.log(
                            "Successfully refreshed from main orders list"
                        );
                    }
                } catch (fallbackError) {
                    console.error(
                        "Fallback refresh also failed:",
                        fallbackError
                    );
                    // At this point we'll just continue with the existing data
                    console.log("Continuing with existing data...");
                }
            }
        },
    },

    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchOrders();
        },
    },

    mounted() {
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;

        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common["X-CSRF-TOKEN"] =
                token.getAttribute("content");
        }

        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fontAwesome = document.createElement("link");
            fontAwesome.rel = "stylesheet";
            fontAwesome.href =
                "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css";
            document.head.appendChild(fontAwesome);
        }

        this.fetchStores();
        this.fetchOrders();
        this.initializeDispenseItems();
    },
};
