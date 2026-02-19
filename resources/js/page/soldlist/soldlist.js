import { eventBus } from "../../components/eventbus";
import axios from "axios";
import "../../../css/modules.css";
import "./soldlist.css";
import { DEFAULT_IMAGE } from "../../constant";
const API_BASE_URL = import.meta.env.VITE_API_URL || window.location.origin;

export default {
    name: "soldlist",

    data() {
        return {
            inventory: [],
            loading: true,
            selectAll: false,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,
            defaultImage: DEFAULT_IMAGE,
            showImageModal: false,
            modalImages: [],
            currentImageIndex: 0,
            showEditModal: false,
            item: {
                materialtype: "",
                carrier: "",
                storename: "",
                priorityrank: "",
                validation_status: "",
            },
            items: [],
            activeIndex: 0,
            basePath: "/images/thumbnails/",
            error: null,
            ProductTitle: "",
            isLoadingImages: false,

            //pagination
            currentPage: 1,
            totalRecords: 0,
            perPage: 10,
            first: 0 //paginator internal state
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
            return this.basePath + this.imageList[this.activeIndex];
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

        qty() {
            return Number(this.item.quantity) || 0;
        },
        price() {
            return Number(this.item.price) || 0;
        },
        discountValue() {
            return Number(this.item.Discount) || 0;
        },
        taxValue() {
            return Number(this.item.tax) || 0;
        },
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
            return this.qty > 0 ? this.price / this.qty : 0;
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
            if (!Array.isArray(this.items)) return [];
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
        storeNames() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.storename)
                        .filter((t) => t && t.trim() !== ""),
                ),
            ].sort();
        },
        priorityRanks() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.priorityrank)
                        .filter((t) => t && t.trim() !== ""),
                ),
            ].sort();
        },
        validationStatuses() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.validation_status)
                        .filter((t) => t && t.trim() !== ""),
                ),
            ].sort();
        },

        displaySerialImage() {
            return (
                this.serialImageUrl ||
                this.serialImagePath ||
                this.defaultSerialImage
            );
        },
    },

    methods: {
        handleImageError(event) {
            event.target.src = this.defaultImage;
            event.target.onerror = null;
        },

        countAdditionalImages(item) {
            if (!item) return 0;

            let count = 0;
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

                this.item = { ...itemToUse };
                this.ProductTitle = itemToUse.ProductTitle;

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

        prevImage() {
            if (this.activeIndex > 0) {
                this.activeIndex--;
            } else {
                this.activeIndex = this.imageList.length - 1;
            }
        },

        nextImage() {
            if (this.activeIndex < this.imageList.length - 1) {
                this.activeIndex++;
            } else {
                this.activeIndex = 0;
            }
        },

        getDisplayTitle(item) {
            if (!item) return "—";

            // Priority: system_title > internal > AStitle > ProductTitle
            if (item.system_title && item.system_title.trim() !== "") {
                return item.system_title;
            }

            if (item.internal && item.internal.trim() !== "") {
                return item.internal;
            }

            if (item.AStitle && item.AStitle.trim() !== "") {
                return item.AStitle;
            }

            if (item.ProductTitle && item.ProductTitle.trim() !== "") {
                return item.ProductTitle;
            }

            return "—";
        },

        // For FNSKU modal display (uses backend's astitle field)
        getFnskuDisplayTitle(fnskuItem) {
            if (!fnskuItem) return "—";

            // Backend already prioritizes: system_title > internal via COALESCE
            if (fnskuItem.astitle && fnskuItem.astitle.trim() !== "") {
                return fnskuItem.astitle;
            }

            // Fallbacks if astitle is missing
            if (
                fnskuItem.system_title &&
                fnskuItem.system_title.trim() !== ""
            ) {
                return fnskuItem.system_title;
            }

            if (fnskuItem.internal && fnskuItem.internal.trim() !== "") {
                return fnskuItem.internal;
            }

            return "—";
        },

        async fetchInventory() {
            this.loading = true;
            this.error = null;

            try {
                console.log("=== FETCH INVENTORY DEBUG ===");
                console.log("API_BASE_URL:", API_BASE_URL);

                const fullUrl = `${API_BASE_URL}/api/soldlist/products`;
                console.log("Full URL:", fullUrl);
                console.log("Request params:", {
                    search: this.searchQuery,
                    page: this.currentPage,
                    per_page: this.perPage,
                    location: "Soldlist",
                });

                const response = await axios.get(fullUrl, {
                    params: {
                        search: this.searchQuery,
                        page: this.currentPage,
                        per_page: this.perPage,
                        location: "Soldlist",
                    },
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    withCredentials: true,
                });

                console.log("=== API RESPONSE ===");
                console.log("Status:", response.status);
                console.log("Content-Type:", response.headers["content-type"]);

                // Check if we got HTML instead of JSON
                const contentType = response.headers["content-type"];
                if (contentType && contentType.includes("text/html")) {
                    console.error("❌ RECEIVED HTML INSTEAD OF JSON!");
                    console.error(
                        "Response preview:",
                        typeof response.data === "string"
                            ? response.data.substring(0, 200)
                            : response.data,
                    );
                    throw new Error(
                        "API returned HTML instead of JSON. Check your route configuration.",
                    );
                }

                console.log("Response Data:", response.data);

                // Handle different response structures
                if (Array.isArray(response.data)) {
                    this.inventory = response.data;
                    this.totalRecords = response.data.total;
                } else if (response.data.data) {
                    this.inventory = response.data.data || [];
                    this.totalRecords = response.data.total;
                    this.currentPage = response.data.current_page;
                } else {
                    console.error(
                        "Unexpected response structure:",
                        response.data,
                    );
                    this.inventory = [];
                    this.totalRecords = 0;
                }

                console.log(
                    "✅ Inventory loaded:",
                    this.inventory.length,
                    "items",
                );

                if (this.inventory.length === 0) {
                    console.warn("⚠️ No items in inventory array");
                }
            } catch (error) {
                console.error("=== FETCH ERROR ===");
                console.error("Error:", error.message);
                console.error("Response:", error.response);

                if (
                    error.message &&
                    error.message.includes("HTML instead of JSON")
                ) {
                    this.error =
                        "Configuration Error: API route not working correctly.";
                } else if (error.response?.status === 401) {
                    this.error =
                        "Authentication required. Please log in again.";
                } else if (error.response?.status === 419) {
                    this.error = "Session expired. Please refresh the page.";
                } else {
                    this.error = error.response?.data?.message || error.message;
                }

                this.inventory = [];
                this.totalPages = 1;
            } finally {
                this.loading = false;
                console.log("=== FETCH COMPLETE ===");
                console.log("Final inventory count:", this.inventory.length);
            }
        },

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

        async openEditModal(item) {
            if (!item) return;

            const freshItem = this.items.find(
                (i) => i.itemnumber === item.itemnumber,
            );
            this.item = { ...(freshItem || item) };

            this.showEditModal = true;
            document.body.style.overflow = "hidden";

            await this.$nextTick();
        },

        closeEditModal() {
            this.showEditModal = false;
            this.item = {};

            setTimeout(() => {
                document.body.style.overflow = "auto";
            }, 300);
        },

        onImageErrorMain(event) {
            event.target.src = this.defaultImage;
        },

        onThumbnailError(event, index) {
            event.target.src = this.defaultImage;
        },

        autoResize() {
            [
                "productTextarea",
                "descriptionarea",
                "supplierNotesarea",
                "employeeNotesarea",
                "stickerNotesarea",
            ].forEach((refName) => {
                const el = this.$refs[refName];
                if (el) {
                    el.style.height = "auto";
                    el.style.height = el.scrollHeight + "px";
                }
            });
        },

        getLabel(index) {
            return String.fromCharCode(65 + index);
        },

        async fetchItems() {
            try {
                const fullUrl = `${API_BASE_URL}/api/soldlist/products`;
                const response = await axios.get(fullUrl, {
                    params: {
                        location: "Soldlist",
                    },
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    withCredentials: true,
                });

                const payload = response.data;
                this.items = Array.isArray(payload)
                    ? payload
                    : payload.data || [];
                console.log("Items fetched:", this.items.length);
            } catch (err) {
                console.error("Fetch items failed:", err);
                this.items = [];
                this.error = "Failed to load items.";
            }
        },
    },

    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.first = 0
            this.fetchInventory();
        },
    },

    mounted() {
        console.log("=== COMPONENT MOUNTED ===");
        console.log("API_BASE_URL:", API_BASE_URL);
        this.fetchInventory();

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
        this.handleKeyDown = handleKeyDown;
    },

    beforeDestroy() {
        if (this.handleKeyDown) {
            window.removeEventListener("keydown", this.handleKeyDown);
        }
    },
};
