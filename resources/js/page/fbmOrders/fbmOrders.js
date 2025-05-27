import { eventBus } from "../../components/eventBus";
import "../../../css/modules.css";
import "./fbmOrders.css";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "FbmOrderModule",
    components: {},
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

        // UPDATED: Open Auto Dispense Modal with automatic dispensing
        autoDispense(order) {
            // Get items that need dispensing
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

            // Show confirmation dialog
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

        // NEW: Perform standalone auto dispense (from main table view)
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

                console.log("Standalone auto dispense response:", response);

                if (response.data && response.data.success) {
                    alert(
                        `Auto-dispensing completed successfully!\n\nDispensed ${response.data.dispensed_count} products across ${response.data.items_processed} items.`
                    );

                    // Refresh the orders list
                    await this.fetchOrders();

                    // If details modal is open for this order, refresh it
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

        // Close Auto Dispense Modal
        closeAutoDispenseModal() {
            this.showAutoDispenseModal = false;
            this.autoDispenseOrder = null;
            this.dispenseProducts = [];
            this.selectedDispenseProducts = {};
        },

        // Load matching products for dispense
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

                console.log("Auto Dispense Request Data:", requestData);

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

                console.log("Matching products response:", response);

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
                    console.error(
                        "Failed to find matching products:",
                        response.data
                    );
                    this.dispenseProducts = [];
                }
            } catch (error) {
                console.error("Error loading matching products:", error);
                console.error(
                    "Error details:",
                    error.response ? error.response.data : error.message
                );
                this.dispenseProducts = [];
            } finally {
                this.loadingDispenseProducts = false;
            }
        },

        // Select a product for dispense
        selectDispenseProduct(itemId, slotIndex, product) {
            console.log(
                `Selected product: ${product.title} (${product.condition}) for item ID: ${itemId}, slot: ${slotIndex}`
            );

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

        // Confirm Auto Dispense
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

                console.log("Confirming dispense with items:", dispenseItems);

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

                console.log("Dispense response:", response);

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

        // Cancel Dispense for an order - UPDATED for modal refresh
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

                console.log("Canceling dispense for items:", itemIds);

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

                console.log("Cancel dispense response:", response);

                if (response.data && response.data.success) {
                    alert("Dispense canceled successfully");

                    // If this is being called from the process modal, refresh modal content
                    if (
                        this.currentProcessOrder &&
                        this.currentProcessOrder.outboundorderid ===
                            order.outboundorderid
                    ) {
                        await this.refreshCurrentProcessOrderForModal();
                    } else {
                        // Regular refresh for other contexts
                        await this.fetchOrders();

                        // Update selected order in details modal if open
                        if (
                            this.selectedOrder &&
                            this.selectedOrder.outboundorderid ===
                                order.outboundorderid
                        ) {
                            const updatedOrders = this.orders.filter(
                                (o) =>
                                    o.outboundorderid ===
                                    this.selectedOrder.outboundorderid
                            );
                            if (updatedOrders.length > 0) {
                                this.selectedOrder = { ...updatedOrders[0] };
                            }
                        }
                    }
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

        // INTEGRATED AUTO DISPENSE IN PROCESS MODAL

        // ENHANCED: Start auto dispense within the process modal with automatic selection
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

            // Load and automatically dispense products
            await this.loadAndAutoDispenseProducts(itemsToDispense);
        },

        // NEW: Load products and automatically dispense them
        async loadAndAutoDispenseProducts(itemIds) {
            this.loadingDispenseProducts = true;

            try {
                const requestData = {
                    order_id: this.currentProcessOrder.outboundorderid,
                    item_ids: itemIds,
                };

                console.log("Auto Dispense Request Data:", requestData);

                // First, find what products can be dispensed
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

                console.log("Find products response:", findResponse);

                if (
                    findResponse.data &&
                    findResponse.data.success &&
                    findResponse.data.data.length > 0
                ) {
                    const dispenseData = findResponse.data.data;

                    // Show what will be dispensed to the user
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
                        // Proceed with automatic dispensing
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

        // NEW: Perform the actual automatic dispensing
        async performAutoDispense(itemIds) {
            try {
                const requestData = {
                    order_id: this.currentProcessOrder.outboundorderid,
                    item_ids: itemIds,
                };

                console.log("Performing auto dispense:", requestData);

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

                console.log("Auto dispense response:", response);

                if (response.data && response.data.success) {
                    alert(
                        `Auto-dispensing completed successfully!\n\nDispensed ${response.data.dispensed_count} products across ${response.data.items_processed} items.`
                    );

                    // Exit auto dispense mode
                    this.processingAutoDispense = false;
                    this.dispenseProducts = [];
                    this.selectedDispenseProducts = {};

                    // Refresh the modal content to show dispensed products
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

        // Load matching products for items in process modal
        async loadDispenseProductsForProcess(itemIds) {
            this.loadingDispenseProducts = true;

            try {
                const requestData = {
                    order_id: this.currentProcessOrder.outboundorderid,
                    item_ids: itemIds,
                };

                console.log(
                    "Auto Dispense in Process Request Data:",
                    requestData
                );

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

                console.log("Matching products response:", response);

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
                    console.error(
                        "Failed to find matching products:",
                        response.data
                    );
                    this.dispenseProducts = [];
                }
            } catch (error) {
                console.error("Error loading matching products:", error);
                this.dispenseProducts = [];
            } finally {
                this.loadingDispenseProducts = false;
            }
        },

        // Cancel auto dispense process
        cancelAutoDispenseProcess() {
            this.processingAutoDispense = false;
            this.dispenseProducts = [];
            this.selectedDispenseProducts = {};
        },

        // FIXED: Confirm auto dispense in process modal with proper refresh
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

                console.log("Confirming dispense with items:", dispenseItems);

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

                console.log("Dispense response:", response);

                if (response.data && response.data.success) {
                    alert("Items dispensed successfully");

                    // Exit auto dispense mode FIRST
                    this.processingAutoDispense = false;
                    this.dispenseProducts = [];
                    this.selectedDispenseProducts = {};

                    // CRITICAL: Force immediate refresh of the process modal content
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

        // NEW: Dedicated method to refresh process modal content
        async refreshCurrentProcessOrderForModal() {
            if (!this.currentProcessOrder) return;

            try {
                console.log(
                    "Refreshing process modal content for order:",
                    this.currentProcessOrder.outboundorderid
                );

                // Get fresh data from the main orders list (which has all the dispensed info)
                await this.fetchOrders();

                // Find the updated order in the main list
                const updatedOrder = this.orders.find(
                    (o) =>
                        o.outboundorderid ===
                        this.currentProcessOrder.outboundorderid
                );

                if (updatedOrder) {
                    console.log(
                        "Found updated order in main list:",
                        updatedOrder
                    );

                    // Update the current process order with the fresh data
                    this.currentProcessOrder = {
                        ...updatedOrder,
                        checked: this.currentProcessOrder.checked || false,
                    };

                    // Reset selectedItems to include all items
                    this.selectedItems = this.currentProcessOrder.items
                        ? this.currentProcessOrder.items.map(
                              (item) => item.outboundorderitemid
                          )
                        : [];

                    // Update dispense items selection
                    this.initializeDispenseItems();

                    // If details modal is open for this order, update it too
                    if (
                        this.selectedOrder &&
                        this.selectedOrder.outboundorderid ===
                            this.currentProcessOrder.outboundorderid
                    ) {
                        this.selectedOrder = { ...this.currentProcessOrder };
                    }

                    // Force Vue reactivity update
                    this.$nextTick(() => {
                        this.$forceUpdate();
                    });

                    console.log("Process modal content refreshed successfully");
                } else {
                    console.error("Could not find updated order in main list");
                }
            } catch (error) {
                console.error("Error refreshing process modal content:", error);
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

        // Process selected orders using persistentSelectedOrderIds
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

        // Fetch an order by ID for processing
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

        // Generate packing slip
        async generatePackingSlip(orderId) {
            try {
                console.log("Generating packing slip for:", orderId);
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/packing-slip`,
                    {
                        order_id: orderId,
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

                console.log("Packing slip response:", response);

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

        // Print shipping label
        async printShippingLabel(orderId) {
            try {
                console.log("Printing shipping label for:", orderId);
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/shipping-label`,
                    {
                        order_id: orderId,
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

                console.log("Shipping label response:", response);

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

        // Confirm and cancel order
        confirmCancelOrder(orderId) {
            if (confirm("Are you sure you want to cancel this order?")) {
                this.cancelOrder(orderId);
            }
        },

        // Cancel order
        async cancelOrder(orderId) {
            try {
                console.log("Canceling order:", orderId);
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/cancel`,
                    {
                        order_id: orderId,
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

                console.log("Cancel response:", response);

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

    // Mark product as not found and auto-select replacement
async markProductNotFound(productId, item) {
    if (!confirm(`Mark this product as "Not Found" and automatically select a replacement?\n\nThis will:\n1. Mark the current product as not found\n2. Remove it from this order\n3. Automatically select a new product if available`)) {
        return;
    }

    try {
        let orderId = this.getCurrentOrderId();

        // If no order ID found in context, try to find it from the orders array
        if (!orderId && item && item.outboundorderitemid) {
            console.log('No order ID in context, searching in orders array...');
            for (const order of this.orders) {
                if (order.items && order.items.some(orderItem => orderItem.outboundorderitemid === item.outboundorderitemid)) {
                    orderId = order.outboundorderid;
                    console.log('Found order ID from orders array:', orderId);
                    break;
                }
            }
        }

        if (!orderId) {
            alert('Unable to determine order ID. Please try again.');
            return;
        }

        const requestData = {
            product_id: productId,
            item_id: item.outboundorderitemid,
            order_id: orderId
        };

        console.log('Marking product as not found:', requestData);

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

        console.log('Mark not found response:', response);

        if (response.data && response.data.success) {
            let message = 'Product marked as "Not Found" successfully.';

            if (response.data.replacement_found) {
                message += `\n\nReplacement product automatically selected:\n• ${response.data.replacement_details.title}\n• Location: ${response.data.replacement_details.warehouseLocation}`;
            } else {
                message += '\n\nNo replacement product was found in inventory.';
            }

            alert(message);

            // Refresh the current context
            await this.refreshCurrentContext();
        } else {
            alert(`Error: ${response.data.message || 'Failed to mark product as not found'}`);
        }
    } catch (error) {
        console.error('Error marking product as not found:', error);
        alert('Failed to mark product as not found. Please try again.');
    }
},
// Get current order ID based on context
getCurrentOrderId() {
    console.log('Getting current order ID...');
    console.log('currentProcessOrder:', this.currentProcessOrder);
    console.log('selectedOrder:', this.selectedOrder);
    console.log('autoDispenseOrder:', this.autoDispenseOrder);

    if (this.currentProcessOrder) {
        console.log('Using currentProcessOrder ID:', this.currentProcessOrder.outboundorderid);
        return this.currentProcessOrder.outboundorderid;
    } else if (this.selectedOrder) {
        console.log('Using selectedOrder ID:', this.selectedOrder.outboundorderid);
        return this.selectedOrder.outboundorderid;
    } else if (this.autoDispenseOrder) {
        console.log('Using autoDispenseOrder ID:', this.autoDispenseOrder.outboundorderid);
        return this.autoDispenseOrder.outboundorderid;
    }

    console.log('No order context found, returning null');
    return null;
},

// Refresh current context after marking product as not found
async refreshCurrentContext() {
    try {
        // If we're in the process modal
        if (this.currentProcessOrder) {
            await this.refreshCurrentProcessOrderForModal();
        }
        // If we're in the details modal
        else if (this.selectedOrder) {
            const orderId = this.selectedOrder.outboundorderid;
            await this.fetchOrders();
            const updatedOrder = this.orders.find(o => o.outboundorderid === orderId);
            if (updatedOrder) {
                this.selectedOrder = { ...updatedOrder };
            }
        }
        // If we're in auto dispense modal
        else if (this.autoDispenseOrder) {
            const orderId = this.autoDispenseOrder.outboundorderid;
            await this.fetchOrders();
            const updatedOrder = this.orders.find(o => o.outboundorderid === orderId);
            if (updatedOrder) {
                this.autoDispenseOrder = { ...updatedOrder };
            }
        }
        // General refresh
        else {
            await this.fetchOrders();
        }

        // Update dispense items selection
        this.initializeDispenseItems();
    } catch (error) {
        console.error('Error refreshing context:', error);
    }
},

// Updated getDispensedProductsDisplay method to include title and asin
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
                title: item.title || 'N/A',
                asin: item.asin || 'N/A',
                warehouseLocation: item.warehouseLocation || '',
                serialNumber: item.serialNumber || '',
                rtCounter: item.rtCounter || '',
                FNSKU: item.FNSKU || '',
            },
        ];
    }

    return [];
},

        // Print shipping labels for selected orders
        printShippingLabels() {
            const selectedOrderIds = this.persistentSelectedOrderIds;

            if (selectedOrderIds.length === 0) {
                alert("Please select at least one order to print labels");
                return;
            }

            alert(`Printing labels for ${selectedOrderIds.length} orders...`);

            selectedOrderIds.forEach((id) => this.printShippingLabel(id));
        },

        // Generate packing slips for selected orders
        generatePackingSlips() {
            const selectedOrderIds = this.persistentSelectedOrderIds;

            if (selectedOrderIds.length === 0) {
                alert(
                    "Please select at least one order to generate packing slips"
                );
                return;
            }

            alert(
                `Generating packing slips for ${selectedOrderIds.length} orders...`
            );

            selectedOrderIds.forEach((id) => this.generatePackingSlip(id));
        },
    },
    watch: {
        // Watch for global search changes
        searchQuery() {
            this.currentPage = 1;
            this.fetchOrders();
        },
    },
    mounted() {
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;

        // Set CSRF token
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common["X-CSRF-TOKEN"] =
                token.getAttribute("content");
        }

        // Add Font Awesome if not already included
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fontAwesome = document.createElement("link");
            fontAwesome.rel = "stylesheet";
            fontAwesome.href =
                "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css";
            document.head.appendChild(fontAwesome);
        }

        // Fetch stores for dropdown
        this.fetchStores();

        // Fetch initial data
        this.fetchOrders();

        // Initialize dispense items selection
        this.initializeDispenseItems();
    },
};
