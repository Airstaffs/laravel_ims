import { eventBus } from "../../components/eventBus";
import "../../../css/modules.css";
import "./orders.css";
import Swal from "sweetalert2";

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
            defaultImage:
                "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4=",
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
                /^serialnumber[a-z]?$/.test(k)
            );
        },
        trackingKeys() {
            return Object.keys(this.item).filter((k) =>
                /^trackingnumber\d*$/.test(k)
            );
        },
        formattedSubtotal() {
            const total = parseFloat(this.item.TOTAL) || 0;
            const quantity = parseFloat(this.item.quantity) || 0;
            return (total * quantity).toFixed(2);
        },
        grandTotal() {
            const subtotal = this.formattedSubtotal;
            const discount = parseFloat(this.item.discount) || 0;
            return (subtotal - discount).toFixed(2);
        },
        unitPrice() {
            const quantity = parseFloat(this.item.quantity);
            if (!quantity || quantity === 0) return 0;

            return (this.formattedSubtotal / quantity).toFixed(2);
        },

        materialTypes() {
            if (!Array.isArray(this.items)) return []; // safeguard

            return [
                ...new Set(
                    this.items
                        .map((i) => i.materialtype)
                        .filter((t) => t && t.trim() !== "")
                ),
            ].sort();
        },
        sourceTypes() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.sourceType)
                        .filter((t) => t && t.trim() !== "")
                ),
            ].sort();
        },
        carrierOptions() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.carrier)
                        .filter((c) => c && c.trim() !== "")
                ),
            ].sort();
        },
    },
    methods: {
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

        // Open image modal with all available images from img1-img15 fields
        openImageModal(item) {
            if (!item) return;

            // Reset modal state
            this.modalImages = [];
            this.currentImageIndex = 0;
            this.ProductTitle = item.ProductTitle;

            // Image field names in your data (img1 through img15)
            const imageFields = [
                "img2",
                "img3",
                "img4",
                "img5",
                "img6",
                "img7",
                "img8",
                "img9",
                "img10",
                "img11",
                "img12",
                "img13",
                "img14",
                "img15",
            ];

            // Loop through all possible image fields and add non-empty ones
            imageFields.forEach((field) => {
                if (
                    item[field] &&
                    item[field] !== "NULL" &&
                    item[field].trim() !== ""
                ) {
                    // Use the direct image field value as the path
                    const imagePath = `/images/thumbnails/${item[field]}`;
                    this.modalImages.push(imagePath);
                }
            });

            // If no images were found, add a default image
            if (this.modalImages.length === 0) {
                const defaultPath = `/images/thumbnails/${item.ProductID}.jpg`;
                this.modalImages.push(defaultPath);
            }

            // Show the modal
            this.showImageModal = true;

            // Prevent scrolling when modal is open
            document.body.style.overflow = "hidden";
        },

        closeImageModal() {
            this.showImageModal = false;
            this.modalImages = [];

            // Re-enable scrolling
            document.body.style.overflow = "auto";
        },

        openEditModal(item) {
            if (!item) return;

            this.item = { ...item };
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
                const payload = { ...this.item };
                const response = await axios.post(
                    "/api/orders/products",
                    payload
                );
                const updated = response.data.product;

                const index = this.items.findIndex(
                    (p) => p.itemnumber === updated.itemnumber
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

                this.showModal = false;
                window.location.reload();
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

        nextImage() {
            if (this.currentImageIndex < this.modalImages.length - 1) {
                this.currentImageIndex++;
            } else {
                this.currentImageIndex = 0; // Loop back to the first image
            }
        },

        prevImage() {
            if (this.currentImageIndex > 0) {
                this.currentImageIndex--;
            } else {
                this.currentImageIndex = this.modalImages.length - 1; // Loop to the last image
            }
        },

        // Fetch inventory data from the API
        async fetchInventory() {
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
                    }
                );

                this.inventory = response.data.data;
                this.totalPages = response.data.last_page;

                console.log(this.inventory);
            } catch (error) {
                console.error("Error fetching inventory data:", error);
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
