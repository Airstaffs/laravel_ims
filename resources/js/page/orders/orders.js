import { eventBus } from "../../components/eventbus";
import "../../../css/modules.css";

import "./orders.css";
import Swal from "sweetalert2";
import { DEFAULT_IMAGE } from "../../constant";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ProductList",
    data() {
        return {
            inventory: [],
            currentPage: 1,
            totalPages: 1,
            perPage: 10, // Default rows per page
            selectAll: false,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,
            defaultImage: DEFAULT_IMAGE,
            // Modal state
            showImageModal: false,
            modalImages: [],
            currentImageIndex: 0,
            showEditModal: false,
            item: {
                // For MaterialType
                materialtype: "",
                carrier: "",
            },
            items: [],
            activeIndex: 0,
            basePath: "/images/thumbnails/",
            loading: false,
            error: null,

            editingQuantity: null,
            tempQuantity: null,

             trackingStatusOptions: [
            { label: 'Delivered', value: 'Delivered' },
            { label: 'Out for Delivery', value: 'Out for Delivery' },
            { label: 'In Transit', value: 'In Transit' },
            { label: 'Pickup', value: 'Pickup' },
            { label: 'Info Received', value: 'InfoReceived' },
            { label: 'Available for Pickup', value: 'AvailableForPickup' },
            { label: 'Exception', value: 'Exception' },
            { label: 'Failed Attempt', value: 'Failed Attempt' },
            { label: 'Expired', value: 'Expired' },
            { label: 'Not Found', value: 'NotFound' },
            { label: 'Unknown', value: 'Unknown' },
            { label: 'Pending', value: 'Pending' },
          ],
        };
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery;
        },
        sortedInventory() {
            if (!this.sortColumn) return this.inventory;
            return [...this.inventory].sort((a, b) => {
                const valueA = a[this.sortColumn];
                const valueB = b[this.sortColumn];

                if (typeof valueA === "number" && typeof valueB === "number") {
                    return this.sortOrder === "asc"
                        ? valueA - valueB
                        : valueB - valueA;
                }

                return this.sortOrder === "asc"
                    ? String(valueA).localeCompare(String(valueB))
                    : String(valueB).localeCompare(String(valueA));
            });
        },

        imageList() {
            return Object.keys(this.item)
                .filter((key) => key.startsWith("img") && this.item[key])
                .map((key) => this.item[key]);
        },
        activeImageUrl() {
            const img = this.imageList?.[this.activeIndex];
            return img ? this.basePath + img : this.defaultImage;
        },
        serialKeys() {
            return Object.keys(this.item).filter((k) =>
                /^serialnumber[a-z]?$/.test(k),
            );
        },
        trackingKeys() {
            return Object.keys(this.item).filter((k) =>
                /^trackingnumber\d*$/.test(k),
            );
        },

        // Safe numeric getters
        qty() {
            return Number(this.item.quantity) || 0;
        },
        price() {
            return Number(this.item.price) || 0;
        },
        discountValue() {
            return Number(this.item.Discount) || 0;
        }, // fixed amount
        taxValue() {
            return Number(this.item.tax) || 0;
        }, // fixed amount
        shipping() {
            return Number(this.item.priceshipping) || 0;
        },
        refund() {
            return Number(this.item.refund) || 0;
        },

        subtotal() {
            return this.qty * this.price;
        },
        unitprice() {
            return this.price / this.qty;
        },
        afterDiscount() {
            return this.price - this.discountValue;
        },
        grandTotalRaw() {
            return (
                this.afterDiscount + this.taxValue + this.shipping - this.refund
            );
        },

        formattedSubtotal() {
            return this.subtotal.toFixed(2);
        },
        formattedUnitprice() {
            return this.unitprice.toFixed(2);
        },
        grandTotal() {
            return this.grandTotalRaw.toFixed(2);
        },

        materialTypes() {
            if (!Array.isArray(this.items)) return []; // safeguard

            return [
                ...new Set(
                    this.items
                        .map((i) => i.materialtype)
                        .filter((t) => t && t.trim() !== ""),
                ),
            ].sort();
        },
        sourceTypes() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.sourceType)
                        .filter((t) => t && t.trim() !== ""),
                ),
            ].sort();
        },
        carrierOptions() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.carrier)
                        .filter((c) => c && c.trim() !== ""),
                ),
            ].sort();
        },
    },
    methods: {

        getTrackingStatusKey(index) {
            return `tracking${index}_status`;
        },

        /**
         * Get tracking delivered date field key
         */
        getTrackingDeliveredDateKey(index) {
            return `tracking${index}_delivered_date`;
        }, 

         getTrackingStatusSeverity(status) {
            const statusMap = {
                'Delivered': 'success',
                'Out for Delivery': 'info',
                'In Transit': 'info',
                'Pickup': 'info',
                'InfoReceived': 'secondary',
                'Expired': 'warning',
                'AvailableForPickup': 'info',
                'Exception': 'danger',
                'Failed Attempt': 'warning',
                'NotFound': 'secondary',
                'Unknown': 'secondary',
                'Pending': 'warning',
            };

            return statusMap[status] || 'secondary';
        },

        /**
         * Get the earliest delivery date from all tracking numbers
         */
        getEarliestDeliveryDate(item) {
            if (!item.tracking_info || item.tracking_info.length === 0) {
                // Fallback to old datedelivered field if exists
                if (item.datedelivered && 
                    item.datedelivered !== '0000-00-00' && 
                    item.datedelivered !== '0000-00-00 00:00:00') {
                    return item.datedelivered;
                }
                return null;
            }

            const dates = item.tracking_info
                .map(t => t.delivered_date)
                .filter(d => d && d !== '0000-00-00' && d !== '0000-00-00 00:00:00')
                .map(d => new Date(d))
                .filter(d => !isNaN(d));

            if (dates.length === 0) return null;

            const earliest = new Date(Math.min(...dates));
            return earliest.toISOString().split('T')[0];
        },

        /**
         * Check if item has multiple delivery dates
         */
        hasMultipleDeliveries(item) {
            if (!item.tracking_info || item.tracking_info.length === 0) {
                return false;
            }

            const deliveredDates = item.tracking_info
                .map(t => t.delivered_date)
                .filter(d => d && d !== '0000-00-00' && d !== '0000-00-00 00:00:00');

            return deliveredDates.length > 1;
        },

        /**
         * Format last checked timestamp
         */
        formatLastChecked(timestamp) {
            if (!timestamp) return '';

            try {
                const date = new Date(timestamp);
                const now = new Date();
                const diffMs = now - date;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);

                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return `${diffMins}m ago`;
                if (diffHours < 24) return `${diffHours}h ago`;
                if (diffDays < 7) return `${diffDays}d ago`;

                return date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    timeZone: this.currentTimezone || 'UTC',
                });
            } catch (error) {
                console.error('Error formatting last checked:', error);
                return timestamp;
            }
        },

        /**
         * Update tracking status for a specific tracking number
         */
        async updateTrackingStatus(productId, trackingIndex, status, deliveredDate = null) {
            try {
                this.loading = true;

                const response = await axios.put(
                    `/api/orders/products/${productId}/tracking-status`,
                    {
                        tracking_index: trackingIndex,
                        status: status,
                        delivered_date: deliveredDate,
                        _token: document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute('content'),
                    }
                );

                if (response.data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Updated!',
                        text: 'Tracking status has been updated successfully.',
                        confirmButtonText: 'OK',
                        timer: 2000,
                    });

                    await this.fetchInventory();
                }
            } catch (error) {
                console.error('Error updating tracking status:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to update tracking status. Please try again.',
                    confirmButtonText: 'OK',
                });
            } finally {
                this.loading = false;
            }
        },

        /**
         * Get delivery date for filter (updated to check all tracking dates)
         */
        getDeliveryDateForFilter(item) {
            // Try to get earliest delivery date from tracking info
            const earliestDate = this.getEarliestDeliveryDate(item);
            if (earliestDate) {
                return earliestDate;
            }

            // Fallback to old delivery_sort_date field
            if (item.delivery_sort_date &&
                item.delivery_sort_date !== '0000-00-00' &&
                item.delivery_sort_date !== '0000-00-00 00:00:00') {
                return item.delivery_sort_date;
            }

            // Check estimated_deliverydate
            if (item.estimated_deliverydate) {
                try {
                    // Extract first date from range format
                    const match = item.estimated_deliverydate.match(/\d{4}-\d{2}-\d{2}/);
                    if (match) return match[0];
                } catch (error) {
                    console.error('Error parsing estimated delivery date:', error);
                }
            }

            return null;
        },

        /**
         * Fixed autoResize method - only resize textareas that exist
         */
        autoResize() {
            this.$nextTick(() => {
                const refNames = [
                    'productTextarea',
                    'descriptionarea',
                    'supplierNotesarea',
                    'employeeNotesarea',
                ];

                refNames.forEach((refName) => {
                    const el = this.$refs[refName];
                    if (el && el.$el) {
                        // PrimeVue component
                        const textarea = el.$el.querySelector('textarea');
                        if (textarea) {
                            textarea.style.height = 'auto';
                            textarea.style.height = textarea.scrollHeight + 'px';
                        }
                    } else if (el && el.tagName === 'TEXTAREA') {
                        // Native textarea
                        el.style.height = 'auto';
                        el.style.height = el.scrollHeight + 'px';
                    }
                });
            });
        },

        openSetAsinModal(item) {
            this.selectedItem = item;
            this.showSetAsinModal = true;
        },

        async handleAsinSelected(asinData) {
            try {
                this.loading = true;

                // Call the setAsin endpoint to update ASINviewer
                const response = await axios.post("/api/orders/set-asin", {
                    ProductID: this.selectedItem.ProductID,
                    ASIN: asinData.ASIN,
                    _token: document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute("content"),
                });

                if (response.data.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Success!",
                        text: `ASIN ${asinData.ASIN} has been set successfully.`,
                        confirmButtonText: "OK",
                        timer: 2000,
                    });

                    await this.fetchInventory();
                }
            } catch (error) {
                console.error("Error setting ASIN:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Failed to set ASIN. Please try again.",
                    confirmButtonText: "OK",
                });
            } finally {
                this.loading = false;
            }
        },

        async removeAsin(item) {
            try {
                const result = await Swal.fire({
                    title: "Remove ASIN?",
                    text: `Are you sure you want to remove ASIN ${item.display_asin || item.ASINviewer} from RT#${item.rtcounter}?`,
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#d33",
                    cancelButtonColor: "#3085d6",
                    confirmButtonText: "Yes, remove it!",
                    cancelButtonText: "Cancel",
                });

                if (result.isConfirmed) {
                    this.loading = true;

                    // Call removeAsin endpoint
                    const response = await axios.post(
                        "/api/orders/remove-asin",
                        {
                            ProductID: item.ProductID,
                            _token: document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                    );

                    if (response.data.success) {
                        await Swal.fire({
                            icon: "success",
                            title: "Removed!",
                            text: "ASIN has been removed successfully.",
                            confirmButtonText: "OK",
                            timer: 2000,
                        });

                        await this.fetchInventory();
                    }
                }
            } catch (error) {
                console.error("Error removing ASIN:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Failed to remove ASIN. Please try again.",
                    confirmButtonText: "OK",
                });
            } finally {
                this.loading = false;
            }
        },
        // === QUANTITY EDITING METHODS ===
        startQuantityEdit(item) {
            this.editingQuantity = item.ProductID;
            this.tempQuantity = item.quantity || 0;

            // Focus the input after Vue updates the DOM
            this.$nextTick(() => {
                const refName = `quantityInput-${item.ProductID}`;
                const input = this.$refs[refName];

                if (input) {
                    // Check if it's an array (multiple refs with same name)
                    const inputElement = Array.isArray(input)
                        ? input[0]
                        : input;

                    // If it's a PrimeVue InputText component
                    if (inputElement?.$el) {
                        const nativeInput =
                            inputElement.$el.querySelector("input");
                        if (nativeInput) {
                            nativeInput.focus();
                            nativeInput.select();
                        }
                    }
                    // If it's a native input
                    else if (inputElement?.tagName === "INPUT") {
                        inputElement.focus();
                        inputElement.select();
                    }
                }
            });
        },

        cancelQuantityEdit() {
            this.editingQuantity = null;
            this.tempQuantity = null;
        },

        async saveQuantity(item) {
            // Don't save if nothing changed
            if (this.tempQuantity === item.quantity) {
                this.cancelQuantityEdit();
                return;
            }

            try {
                const response = await axios.put(
                    `/api/orders/products/${item.ProductID}/quantity`,
                    {
                        quantity: this.tempQuantity,
                        _token: document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute("content"),
                    },
                );

                if (response.data.success) {
                    // Update the item in the inventory array
                    const index = this.inventory.findIndex(
                        (p) => p.ProductID === item.ProductID,
                    );
                    if (index !== -1) {
                        this.inventory[index].quantity = this.tempQuantity;
                    }

                    // Show success message (compact toast)
                    const Toast = Swal.mixin({
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 1500,
                        timerProgressBar: true,
                        width: "300px", // Fixed width
                        padding: "0.75rem", // Smaller padding
                        customClass: {
                            popup: "compact-toast",
                        },
                    });

                    Toast.fire({
                        icon: "success",
                        title: "Quantity updated",
                        text: "", // No additional text
                    });
                }
            } catch (error) {
                console.error("Error updating quantity:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Failed to update quantity. Please try again.",
                    confirmButtonText: "OK",
                });
            } finally {
                this.cancelQuantityEdit();
            }
        },

        handleImageError(event) {
            // If image fails to load, use an inline SVG placeholder
            event.target.src = this.defaultImage;
            event.target.onerror = null; // Prevent infinite error loop
        },

        // Count additional images based on the image fields (img2-img15)
        countAdditionalImages(item) {
            if (!item) return 0;

            let count = 0;
            // Check fields img2 through img15
            for (let i = 2; i <= 15; i++) {
                const fieldName = `img${i}`;
                if (
                    item[fieldName] &&
                    item[fieldName] !== "NULL" &&
                    item[fieldName].trim() !== ""
                ) {
                    count++;
                }
            }

            return count;
        },

        async openImageModal(item) {
            if (!item) return;

            this.item = {};
            this.activeIndex = 0;
            this.ProductTitle = "";

            this.isLoadingImages = true;

            try {
                await this.fetchItems();

                const freshItem = this.items.find(
                    (i) => i.itemnumber === item.itemnumber,
                );
                const itemToUse = freshItem || item;

                console.log("Item to use:", itemToUse);
                console.log("Images found:", this.imageList.length);

                this.item = { ...itemToUse };
                this.ProductTitle = itemToUse.ProductTitle;

                console.log("Final imageList:", this.imageList);

                this.showImageModal = true;

                await this.$nextTick();
                document.body.style.overflow = "hidden";
            } catch (error) {
                console.error("Failed to fetch fresh item data:", error);
                this.openImageModalFallback(item);
            } finally {
                this.isLoadingImages = false;
            }
        },

        openImageModalFallback(item) {
            if (!item) return;

            this.item = { ...item };
            this.activeIndex = 0;
            this.ProductTitle = item.ProductTitle;

            console.log("Fallback imageList:", this.imageList);

            this.showImageModal = true;
            document.body.style.overflow = "hidden";
        },

        closeImageModal() {
            this.showImageModal = false;

            this.item = {};
            this.activeIndex = 0;
            this.ProductTitle = "";

            document.body.style.overflow = "";
        },

        async openEditModal(item) {
            if (!item) return;

            // await this.fetchItems();

            const freshItem = this.items.find(
                (i) => i.itemnumber === item.itemnumber,
            );
            this.item = { ...(freshItem || item) };

            this.showEditModal = true;
            this.autoResize();

            document.body.style.overflow = "hidden";
        },

        closeEditModal() {
            this.showEditModal = false;

            setTimeout(() => {
                document.body.style.overflow = "auto";
            }, 300); // Match with your modal close animation
        },

        autoResize() {
            [
                "productTextarea",
                "descriptionarea",
                "supplierNotesarea",
                "employeeNotesarea",
            ].forEach((refName) => {
                const el = this.$refs[refName];
                if (el) {
                    el.style.height = "auto";
                    el.style.height = el.scrollHeight + "px";
                }
            });
        },

        getLabel(index) {
            // Convert 0 => A, 1 => B, etc.
            return String.fromCharCode(65 + index);
        },

        async fetchItems() {
            this.loading = true;
            try {
                const response = await axios.get("/api/orders/products");
                const payload = response.data;

                // handle both array or wrapped array
                this.items = Array.isArray(payload)
                    ? payload
                    : payload.data || [];
            } catch (err) {
                console.error("Fetch failed:", err);
                this.items = []; // fallback
                this.error = "Failed to load items.";
            } finally {
                this.loading = false;
            }
        },

        onImageErrorMain(event) {
            event.target.src = this.defaultImage;
        },
        onThumbnailError(event, index) {
            event.target.src = this.defaultImage;
        },

        async saveEditModal() {
            this.loading = true;
            try {
                const payload = {
                    ...this.item,
                    _token: document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute("content"),
                };

                const response = await axios.post(
                    "/api/orders/products",
                    payload,
                );
                const updated = response.data.product;

                const index = this.items.findIndex(
                    (p) => p.itemnumber === updated.itemnumber,
                );
                if (index !== -1) {
                    this.items.splice(index, 1, updated);
                } else {
                    this.items.unshift(updated);
                }

                await Swal.fire({
                    icon: "success",
                    title: "Saved!",
                    text: "The order product has been saved successfully.",
                    confirmButtonText: "OK",
                });

                this.closeEditModal();
                await this.fetchInventory();
            } catch (error) {
                console.error("Save failed:", error);

                Swal.fire({
                    icon: "error",
                    title: "Save Failed",
                    text: "An error occurred while saving. Please check the input or try again later.",
                    confirmButtonText: "OK",
                });
            } finally {
                this.loading = false;
            }
        },

        prevImage() {
            if (this.activeIndex > 0) {
                this.activeIndex--;
            } else {
                this.activeIndex = this.imageList.length - 1; // Loop to end
            }
        },

        nextImage() {
            if (this.activeIndex < this.imageList.length - 1) {
                this.activeIndex++;
            } else {
                this.activeIndex = 0; // Loop to start
            }
        },

        // Fetch inventory data from the API
        async fetchInventory() {
            this.loading = true;
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/orders/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            location: "Orders",
                        },
                    },
                );

                this.inventory = response.data.data;
                this.totalPages = response.data.last_page;

                console.log(this.inventory);
            } catch (error) {
                console.error("Error fetching inventory data:", error);
            } finally {
                this.loading = false;
            }
        },

        changePerPage() {
            this.currentPage = 1;
            this.fetchInventory();
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

        toggleAll() {
            this.inventory.forEach((item) => (item.checked = this.selectAll));
        },

        toggleDetails(index) {
            this.expandedRows = {
                ...this.expandedRows,
                [index]: !this.expandedRows[index],
            };
        },

        toggleDetailsVisibility() {
            this.showDetails = !this.showDetails;
        },

        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = column;
                this.sortOrder = "asc";
            }
        },
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchInventory();
        },

        item: {
            immediate: true,
            handler() {
                this.activeIndex = 0;
            },
        },

        "item.ProductTitle": {
            immediate: true,
            handler() {
                this.$nextTick(() => {
                    this.autoResize();
                });
            },
        },
    },
    mounted() {
        this.fetchInventory();

        // Handle keyboard navigation for the modal
        const handleKeyDown = (e) => {
            if (!this.showImageModal) return;

            switch (e.key) {
                case "Escape":
                    this.closeImageModal();
                    break;
                case "ArrowRight":
                    this.nextImage();
                    break;
                case "ArrowLeft":
                    this.prevImage();
                    break;
            }
        };

        window.addEventListener("keydown", handleKeyDown);
        this.handleKeyDown = handleKeyDown; // Store for cleanup

        [...this.serialKeys, ...this.trackingKeys].forEach((key) => {
            if (this.item[key] == null) {
                this.$set(this.item, key, "");
            }
        });

        // For MaterialType
        this.fetchItems();
    },

    beforeDestroy() {
        // Clean up keyboard event listener
        if (this.handleKeyDown) {
            window.removeEventListener("keydown", this.handleKeyDown);
        }
    },
};
