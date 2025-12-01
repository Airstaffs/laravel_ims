import { eventBus } from "../../components/eventBus";
import "../../../css/modules.css";
import "./rts.css";
import Swal from "sweetalert2";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ProductList",
    data() {
        return {
            inventory: [],
            currentPage: 1,
            totalPages: 1,
            perPage: 10,
            selectAll: false,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,
            defaultImage:
                "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4=",

            // Image Modal state
            showImageModal: false,
            regularImages: [],
            capturedImages: [],
            activeTab: "regular",
            currentImageIndex: 0,
            currentImageSet: [],
            ProductTitle: "",

            // Edit Modal state
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
            loading: false,
            error: null,

            // RTS Modal state
            showRTSModal: false,
            rtsCurrentItem: null,
            rtsForm: {
                dateField: "",
                filedInES: false,
                filedInPPL: false,
                testResult: "",
                status: "",
                rtsResult: "",
                refundAmount: "",
                refundDate: "",
                reasonOfReturn: "",
                returnTN: "",
                notes: "",
            },
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
            if (!Array.isArray(this.items)) return [];
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

        storeNames() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.storename)
                        .filter((t) => t && t.trim() !== "")
                ),
            ].sort();
        },

        priorityRanks() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.priorityrank)
                        .filter((t) => t && t.trim() !== "")
                ),
            ].sort();
        },

        validationStatuses() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.validation_status)
                        .filter((t) => t && t.trim() !== "")
                ),
            ].sort();
        },
    },

    methods: {
        // Image handling methods
        handleImageError(event) {
            event.target.src = this.defaultImage;
            event.target.onerror = null;
        },

        isValidImage(path) {
            return path && path !== "NULL" && path.trim() !== "";
        },

        countRegularImages(item) {
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

        countCapturedImages(item) {
            if (!item || !item.capturedImages) return 0;
            let count = 0;
            for (let i = 1; i <= 12; i++) {
                const fieldName = `capturedimg${i}`;
                if (
                    item.capturedImages &&
                    item.capturedImages[fieldName] &&
                    item.capturedImages[fieldName] !== "NULL" &&
                    item.capturedImages[fieldName].trim() !== ""
                ) {
                    count++;
                }
            }
            return count;
        },

        countAllImages(item) {
            return (
                this.countRegularImages(item) + this.countCapturedImages(item)
            );
        },

        // Image modal methods
        openImageModal(item) {
            if (!item) return;

            this.regularImages = [];
            this.capturedImages = [];
            this.currentImageIndex = 0;
            this.ProductTitle = item.ProductTitle;
            const companyFolder = item.company || "Airstaffs";

            for (let i = 1; i <= 15; i++) {
                const fieldName = `img${i}`;
                if (this.isValidImage(item[fieldName])) {
                    const path = `/images/thumbnails/${item[fieldName]}`;
                    this.regularImages.push(path);
                }
            }

            if (
                item.capturedImages &&
                typeof item.capturedImages === "object"
            ) {
                // for (let i = 1; i <= 12; i++) {
                //     const filename = `${item.rtcounter}_img${i}.jpg`;
                //     const path = `/images/product_images/${companyFolder}/${filename}`;
                //     this.capturedImages.push(path);
                // }
                 for (let i = 1; i <= 12; i++) {
                    const field = `${item.rtcounter}_img${i}.jpg`;
                    const value = item.capturedImages[field];
                    //dont include null value
                    if (value && value.trim()) {
                        const path = `/images/product_images/${companyFolder}/${value}`;
                        this.capturedImages.push(path);
                    }
                }
            }

            if (
                this.regularImages.length === 0 &&
                this.capturedImages.length === 0
            ) {
                this.regularImages.push(this.defaultImage);
            }

            this.activeTab = this.regularImages.length ? "regular" : "captured";
            this.currentImageSet =
                this.activeTab === "regular"
                    ? this.regularImages
                    : this.capturedImages;

            this.showImageModal = true;
            document.body.style.overflow = "hidden";
        },

        switchTab(tab) {
            this.activeTab = tab;
            this.currentImageIndex = 0;
            this.currentImageSet =
                tab === "regular" ? this.regularImages : this.capturedImages;
        },

        closeImageModal() {
            this.showImageModal = false;
            this.currentImageSet = [];
            this.regularImages = [];
            this.capturedImages = [];
            document.body.style.overflow = "auto";
        },

        nextImage() {
            if (this.currentImageIndex < this.currentImageSet.length - 1) {
                this.currentImageIndex++;
            } else {
                this.currentImageIndex = 0;
            }
        },

        prevImage() {
            if (this.currentImageIndex > 0) {
                this.currentImageIndex--;
            } else {
                this.currentImageIndex = this.currentImageSet.length - 1;
            }
        },

        // Data fetching methods
        async fetchInventory() {
            this.loading = true;
            try {
                console.log("Fetching inventory...");
                const response = await axios.get(
                    `${API_BASE_URL}/api/rts/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            location: "",
                            include_images: true,
                        },
                    }
                );

                this.inventory = response.data.data;
                this.totalPages = response.data.last_page;
                console.log(
                    "Inventory loaded:",
                    this.inventory.length,
                    "items"
                );
            } catch (error) {
                console.error("Error fetching inventory data:", error);
            } finally {
                this.loading = false;
            }
        },

        async fetchItems() {
            this.loading = true;
            try {
                const response = await axios.get("/api/rts/products");
                const payload = response.data;
                this.items = Array.isArray(payload)
                    ? payload
                    : payload.data || [];
            } catch (err) {
                console.error("Fetch failed:", err);
                this.items = [];
                this.error = "Failed to load items.";
            } finally {
                this.loading = false;
            }
        },

        // Pagination methods
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

        // Table methods
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

        // Edit modal methods
        async openEditModal(item) {
            if (!item) return;
            console.log("Opening edit modal for item:", item);

            const freshItem = this.items.find(
                (i) => i.itemnumber === item.itemnumber
            );
            this.item = { ...(freshItem || item) };
            this.showEditModal = true;
            document.body.style.overflow = "hidden";
        },

        closeEditModal() {
            this.showEditModal = false;
            setTimeout(() => {
                document.body.style.overflow = "auto";
            }, 300);
        },

        EditItem(item) {
            this.openEditModal(item);
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

        async saveEditModal() {
            this.loading = true;

            const errors = [];
            if (!/^RPN\d+$/i.test(this.item.RPN)) {
                errors.push("RPN must start with 'RPN' followed by numbers.");
            }
            if (!/^PRD\d+$/i.test(this.item.PRD)) {
                errors.push("PRD must start with 'PRD' followed by numbers.");
            }
            if (!/^PCN\d+$/i.test(this.item.PCN)) {
                errors.push("PCN must start with 'PCN' followed by numbers.");
            }
            if (!/^BKT\d+$/i.test(this.item.basketnumber)) {
                errors.push(
                    "Basket Number must start with 'BKT' followed by numbers."
                );
            }

            if (errors.length > 0) {
                this.loading = false;
                await Swal.fire({
                    icon: "error",
                    title: "Validation Error",
                    html: errors.join("<br>"),
                    confirmButtonText: "OK",
                });
                return;
            }

            try {
                const payload = {
                    ...this.item,
                    _token: document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute("content"),
                };

                const response = await axios.post("/api/rts/products", payload);
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
                    text: "The product has been saved successfully.",
                    confirmButtonText: "OK",
                });

                this.closeEditModal();
                await this.fetchInventory();
            } catch (error) {
                console.error("Save failed:", error);
                Swal.fire({
                    icon: "error",
                    title: "Save Failed",
                    text: "An error occurred while saving. Please try again.",
                    confirmButtonText: "OK",
                });
            } finally {
                this.loading = false;
            }
        },

        // RTS Modal Methods
        async openRTSModal(item) {
            if (!item) return;

            console.log("Opening RTS modal for item:", item);

            this.rtsCurrentItem = { ...item };
            this.resetRTSForm();

            // Set today's date as default
            const today = new Date().toISOString().split("T")[0];
            this.rtsForm.dateField = today;

            this.showRTSModal = true;
            document.body.style.overflow = "hidden";

            // Fetch existing RTS data for this item
            await this.fetchExistingRTSData(item);

            console.log(
                "RTS Modal should be open now. showRTSModal:",
                this.showRTSModal
            );
        },

        async fetchExistingRTSData(item) {
            if (!item.rtcounter || !item.ProductID) {
                console.log("Missing rtcounter or ProductID, skipping fetch");
                return;
            }

            try {
                console.log("Fetching existing RTS data for:", {
                    rtcounter: item.rtcounter,
                    ProductID: item.ProductID,
                });

                const response = await axios.get(
                    `${API_BASE_URL}/api/rts/get-rts-options`,
                    {
                        params: {
                            rtcounter: item.rtcounter,
                            ProductID: item.ProductID,
                        },
                    }
                );

                if (response.data.success && response.data.data) {
                    const existingData = response.data.data;
                    console.log("Found existing RTS data:", existingData);

                    // Populate the form with existing data
                    this.rtsForm = {
                        dateField:
                            existingData.dateField || this.rtsForm.dateField,
                        filedInES: existingData.filedInES || false,
                        filedInPPL: existingData.filedInPPL || false,
                        testResult: existingData.testResult || "",
                        status: existingData.status || "",
                        rtsResult: existingData.rtsResult || "",
                        refundAmount: existingData.refundAmount || "",
                        refundDate: existingData.refundDate || "",
                        reasonOfReturn: existingData.reasonOfReturn || "",
                        returnTN: existingData.returnTN || "",
                        notes: existingData.notes || "",
                    };

                    console.log(
                        "Updated form with existing data:",
                        this.rtsForm
                    );
                } else {
                    console.log("No existing RTS data found for this item");
                }
            } catch (error) {
                console.error("Error fetching existing RTS data:", error);
                // Don't show error to user for this, just continue with empty form
            }
        },

        closeRTSModal() {
            this.showRTSModal = false;
            this.rtsCurrentItem = null;
            this.resetRTSForm();

            // Force remove modal classes and restore body scroll
            document.body.style.overflow = "auto";
            document.body.classList.remove("modal-open");

            // Force remove any modal backdrops that might be stuck
            const existingBackdrops =
                document.querySelectorAll(".modal-backdrop");
            existingBackdrops.forEach((backdrop) => backdrop.remove());

            console.log("RTS Modal closed, showRTSModal:", this.showRTSModal);
        },

        resetRTSForm() {
            this.rtsForm = {
                dateField: "",
                filedInES: false,
                filedInPPL: false,
                testResult: "",
                status: "",
                rtsResult: "",
                refundAmount: "",
                refundDate: "",
                reasonOfReturn: "",
                returnTN: "",
                notes: "",
            };
        },

        async saveRTSModal() {
            // Validate required fields first
            if (
                !this.rtsForm.dateField ||
                !this.rtsForm.testResult ||
                !this.rtsForm.status ||
                !this.rtsForm.rtsResult
            ) {
                // Close RTS modal first, then show error
                this.closeRTSModal();

                setTimeout(async () => {
                    await Swal.fire({
                        icon: "error",
                        title: "Validation Error",
                        text: "Please fill in all required fields (Date Field, Test Result, Status, and RTS Result).",
                        confirmButtonText: "OK",
                        // Ensure SweetAlert appears on top
                        customClass: {
                            container: "swal2-top-level",
                        },
                        backdrop: true,
                        allowOutsideClick: false,
                    });
                }, 100);
                return;
            }

            this.loading = true;

            try {
                const payload = {
                    rtcounter: this.rtsCurrentItem.rtcounter,
                    ProductID: this.rtsCurrentItem.ProductID,
                    FNSKU: this.rtsCurrentItem.FNSKU,
                    serialnumber: this.rtsCurrentItem.serialnumber,
                    ...this.rtsForm,
                    _token:
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content") || "",
                };

                console.log("RTS Options payload:", payload);

                const response = await axios.post(
                    `${API_BASE_URL}/api/rts/save-rts-options`,
                    payload
                );

                // Close modal first
                this.closeRTSModal();

                if (response.data.success) {
                    // Show success message after modal is closed
                    setTimeout(async () => {
                        await Swal.fire({
                            icon: "success",
                            title: "Saved!",
                            text: "RTS options have been saved successfully.",
                            confirmButtonText: "OK",
                            customClass: {
                                container: "swal2-top-level",
                            },
                            backdrop: true,
                            allowOutsideClick: false,
                        });

                        // Refresh data after success message
                        await this.fetchInventory();
                    }, 100);
                } else {
                    throw new Error(
                        response.data.message || "Unknown error occurred"
                    );
                }
            } catch (error) {
                console.error("Save RTS options failed:", error);

                // Close modal first
                this.closeRTSModal();

                let errorMessage =
                    "An error occurred while saving RTS options. Please try again.";

                if (
                    error.response &&
                    error.response.data &&
                    error.response.data.message
                ) {
                    errorMessage = error.response.data.message;
                } else if (error.message) {
                    errorMessage = error.message;
                }

                // Show error message after modal is closed
                setTimeout(async () => {
                    await Swal.fire({
                        icon: "error",
                        title: "Save Failed",
                        text: errorMessage,
                        confirmButtonText: "OK",
                        customClass: {
                            container: "swal2-top-level",
                        },
                        backdrop: true,
                        allowOutsideClick: false,
                    });
                }, 100);
            } finally {
                this.loading = false;
            }
        },
    },

    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchInventory();
        },
    },

    mounted() {
        console.log("RTS Component mounted");
        this.fetchInventory();

        const handleKeyDown = (e) => {
            if (
                !this.showImageModal &&
                !this.showRTSModal &&
                !this.showEditModal
            )
                return;

            switch (e.key) {
                case "Escape":
                    if (this.showImageModal) {
                        this.closeImageModal();
                    } else if (this.showRTSModal) {
                        this.closeRTSModal();
                    } else if (this.showEditModal) {
                        this.closeEditModal();
                    }
                    break;
                case "ArrowRight":
                    if (this.showImageModal) {
                        this.nextImage();
                    }
                    break;
                case "ArrowLeft":
                    if (this.showImageModal) {
                        this.prevImage();
                    }
                    break;
            }
        };

        window.addEventListener("keydown", handleKeyDown);
        this.handleKeyDown = handleKeyDown;

        this.fetchItems();
    },

    beforeDestroy() {
        if (this.handleKeyDown) {
            window.removeEventListener("keydown", this.handleKeyDown);
        }
        document.body.style.overflow = "auto";
    },
};
