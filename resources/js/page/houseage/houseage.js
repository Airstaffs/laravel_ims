import { eventBus } from "../../components/eventbus";
import "../../../css/modules.css";
import "./houseage.css";
import Swal from "sweetalert2";
import copyDetailsModal from "../labeling/modals/copydetails/copydetailsmodal.vue";
import { DEFAULT_IMAGE } from "../../constant";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ProductList",
    components: {
        copyDetailsModal,
    },
    data() {
        return {
            currentUser: null,
            inventory: [],
            selectAll: false,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,
            defaultImage: DEFAULT_IMAGE,

            moduleFilter: "", // Add this for filtering
            availableModules: [], // Add this to store unique module values

            // Modal state
            showImageModal: false,
            regularImages: [], // For regular product images
            capturedImages: [], // For captured images
            activeTab: "regular", // Track which tab is active
            currentImageIndex: 0,
            currentImageSet: [], // The currently displayed image set based on active tab

            // FNSKU Modal properties
            isFnskuModalVisible: false,
            currentItem: null,
            fnskuList: [],
            filteredFnskuList: [],
            fnskuSearch: "",

            showConfirmationModal: false,
            confirmationTitle: "",
            confirmationMessage: "",
            confirmationActionType: "", // 'validation' or 'stockroom'
            currentItemForAction: null, // Store the item to be processed

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
            trackingActiveIndex: 0,
            serialActiveIndex: 0,
            basePath: "/images/thumbnails/",
            loading: false,
            error: null,

            serialImageFile: null,
            serialImageUrl: "", // local preview via FileReader
            serialImagePath: "", // server URL if existing or after upload
            serialImageError: "",
            serialImageUploading: false,
            uploadProgress: 0,
            defaultSerialImage: DEFAULT_IMAGE, // <-- put your placeholder file here

            showCopyDetailsModal: false,
            currentCopyItem: null,

            serialErrors: {},

            //pagination
            totalRecords: 0,
            currentPage: 1,
            perPage: 10,
            first: 0, //for prime vues pagination internal state

            selectedRows: [], //for invoice generation
            showInvoiceModal: false,
        };
    },

    computed: {
        searchQuery() {
            return eventBus.searchQuery;
        },
        // Get unique module locations for the filter dropdown
        uniqueModules() {
            const modules = [
                ...new Set(
                    this.inventory
                        .map((item) => item.ProductModuleLoc)
                        .filter(Boolean),
                ),
            ];
            return modules.sort();
        },

        // Filter inventory based on selected module
        filteredInventory() {
            if (!this.moduleFilter) {
                return this.inventory;
            }
            return this.inventory.filter(
                (item) => item.ProductModuleLoc === this.moduleFilter,
            );
        },

        // Sort the filtered inventory
        sortedInventory() {
            const itemsToSort = this.filteredInventory;

            console.log("🔍 Computing sortedInventory:", {
                filteredCount: itemsToSort.length,
                sortColumn: this.sortColumn,
            });

            if (!this.sortColumn) {
                return itemsToSort;
            }

            return [...itemsToSort].sort((a, b) => {
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
            // Force recalculation by referencing imageRenderKey
            const timestamp = this.imageRenderKey || Date.now();
            const images = [];
            const companyFolder = this.item.company || "Airstaffs";

            // First, check for captured images (capturedimg1-12)
            if (this.item.capturedImages) {
                for (let i = 1; i <= 12; i++) {
                    const fieldName = `capturedimg${i}`;
                    if (
                        this.isValidImage(this.item.capturedImages[fieldName])
                    ) {
                        // Add cache buster to prevent browser caching
                        const imagePath = `/images/product_images/${companyFolder}/${this.item.capturedImages[fieldName]}`;
                        images.push(this.addCacheBuster(imagePath, timestamp));
                    }
                }
            }

            // If we have captured images, return them
            if (images.length > 0) {
                return images;
            }

            // Otherwise, fall back to regular product images (img1-15)
            return Object.keys(this.item)
                .filter((key) => key.startsWith("img") && this.item[key])
                .map((key) => {
                    const imagePath = this.item[key];
                    return this.addCacheBuster(imagePath, timestamp);
                });
        },
        trackingImgList() {
            // Force recalculation by referencing imageRenderKey
            const timestamp = this.imageRenderKey || Date.now();
            const images = [];
            const companyFolder = this.item.company || "Airstaffs";

            // Check both locations for tracking images
            for (let i = 1; i <= 2; i++) {
                const fieldName = `trackingimg${i}`;
                let imageFilename = null;

                // First check in capturedImages object
                if (
                    this.item.capturedImages &&
                    this.item.capturedImages[fieldName]
                ) {
                    imageFilename = this.item.capturedImages[fieldName];
                }
                // Fallback to root level
                else if (this.item[fieldName]) {
                    imageFilename = this.item[fieldName];
                }

                if (imageFilename) {
                    const imagePath = `/images/product_images/${companyFolder}/${imageFilename}`;
                    images.push(this.addCacheBuster(imagePath, timestamp));
                }
            }

            console.log("🚚 Tracking images:", images);
            return images;
        },

        serialImgList() {
            // Force recalculation by referencing imageRenderKey
            const timestamp = this.imageRenderKey || Date.now();
            const images = [];
            const companyFolder = this.item.company || "Airstaffs";

            // Check both locations for serial images
            for (let i = 1; i <= 5; i++) {
                const fieldName = `serialimg${i}`;
                let imageFilename = null;

                // First check in capturedImages object
                if (
                    this.item.capturedImages &&
                    this.item.capturedImages[fieldName]
                ) {
                    imageFilename = this.item.capturedImages[fieldName];
                }
                // Fallback to root level
                else if (this.item[fieldName]) {
                    imageFilename = this.item[fieldName];
                }

                if (imageFilename) {
                    const imagePath = `/images/product_images/${companyFolder}/${imageFilename}`;
                    images.push(this.addCacheBuster(imagePath, timestamp));
                }
            }

            console.log("🔢 Serial images:", images);
            return images;
        },
        activeImageUrl() {
            const currentImage = this.imageList[this.activeIndex];

            // Check if it's already a full path (captured images)
            if (currentImage && currentImage.startsWith("/images/")) {
                return currentImage;
            }

            // Otherwise, use basePath for regular images
            return this.basePath + currentImage;
        },

        activeTrackingImageUrl() {
            const currentImage = this.trackingImgList[this.trackingActiveIndex];

            // Check if it's already a full path (captured images)
            if (currentImage && currentImage.startsWith("/images/")) {
                return currentImage;
            }

            // Otherwise, use basePath for regular images
            return this.basePath + currentImage;
        },

        activeSerialImageUrl() {
            const currentImage = this.serialImgList[this.serialActiveIndex];

            // Check if it's already a full path (captured images)
            if (currentImage && currentImage.startsWith("/images/")) {
                return currentImage;
            }

            // Otherwise, use basePath for regular images
            return this.basePath + currentImage;
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
            // Priority: local preview -> captured serial images -> server path -> default
            if (this.serialImageUrl) {
                console.log("📸 Displaying local preview");
                return this.serialImageUrl;
            }

            // Check for serialimg1 or serialimg2 from capturedImages
            if (this.item && this.item.capturedImages) {
                const companyFolder = this.item.company || "Airstaffs";

                if (this.isValidImage(this.item.capturedImages.serialimg1)) {
                    // ✅ UPDATED: Use new path structure
                    const path = `/images/product_images/${companyFolder}/${this.item.capturedImages.serialimg1}`;
                    console.log("📸 Displaying serialimg1:", path);
                    return path;
                }

                if (this.isValidImage(this.item.capturedImages.serialimg2)) {
                    // ✅ UPDATED: Use new path structure
                    const path = `/images/product_images/${companyFolder}/${this.item.capturedImages.serialimg2}`;
                    console.log("📸 Displaying serialimg2:", path);
                    return path;
                }
            }

            console.log("📸 No serial image found, using default");
            return this.serialImagePath || this.defaultSerialImage;
        },
    },

    methods: {
        onSelectionChange(product, isSelected) {
            if(isSelected){
                this.selectedRows.push(product.ProductID);
            }else{
                //only get the ProductID
                this.selectedRows = this.selectedRows.filter((item) => item !== product.ProductID);
            }

            console.log(this.selectedRows, "Product Id");
        },
        goToSuppliersList() {
            window.loadContent('suppliersList');
        },
        addCacheBuster(url, timestamp = null) {
            if (!url) return url;

            const bust = timestamp || Date.now();
            const separator = url.includes("?") ? "&" : "?";
            const cleanUrl = url.replace(/[?&](t|v|_)=\d+/g, "");

            return `${cleanUrl}${separator}t=${bust}`;
        },

        removeCacheBuster(url) {
            if (!url) return url;
            return url.replace(/[?&](t|v|_)=\d+/g, "").replace(/\?$/, "");
        },
        hasSerialImages() {
            if (!this.item.capturedImages) return false;

            return (
                this.isValidImage(this.item.capturedImages.serialimg1) ||
                this.isValidImage(this.item.capturedImages.serialimg2)
            );
        },

        openCopyDetailsModal(item) {
            if (!item) {
                console.warn("No item provided to copy details modal");
                return;
            }

            console.log("Opening copy details modal for item:", {
                rtcounter: item.rtcounter,
                ProductTitle: item.ProductTitle,
                ASIN: item.ASIN,
            });

            // Set the current item data
            this.currentCopyItem = { ...item };

            // Show the modal
            this.showCopyDetailsModal = true;
        },

        /**
         * Close the Copy Details modal
         */
        closeCopyDetailsModal() {
            console.log("Closing copy details modal");

            this.showCopyDetailsModal = false;
            this.currentCopyItem = null;
        },

        handleImageError(event) {
            // If image fails to load, use an inline SVG placeholder
            event.target.src = this.defaultImage;
            event.target.onerror = null; // Prevent infinite error loop
        },

        isValidImage(path) {
            return path && path !== "NULL" && path.trim() !== "";
        },

        // Helper to validate image fields
        isValidImage(path) {
            return path && path !== "NULL" && path.trim() !== "";
        },

        // Generic image counter for any image type
        countImages(item, type = "all") {
            if (!item) return 0;

            switch (type) {
                case "captured":
                    return this._countCapturedImages(item);

                case "regular":
                    return this._countRegularImages(item);

                case "serial":
                    return this._countSerialImages(item);

                case "all":
                default:
                    // Prioritize captured images
                    const capturedCount = this._countCapturedImages(item);
                    if (capturedCount > 0) return capturedCount;

                    // Fallback to regular images
                    return this._countRegularImages(item);
            }
        },

        /**
         * Internal: Count regular product images (img1-15)
         */
        _countRegularImages(item) {
            if (!item) return 0;

            let count = 0;
            for (let i = 1; i <= 15; i++) {
                if (this.isValidImage(item[`img${i}`])) {
                    count++;
                }
            }
            return count;
        },

        /**
         * Internal: Count captured images (capturedimg1-12)
         */
        _countCapturedImages(item) {
            if (!item?.capturedImages) return 0;

            let count = 0;
            const capturedImagesObj = item.capturedImages;

            // Count capturedimg1-12
            for (let i = 1; i <= 12; i++) {
                if (this.isValidImage(capturedImagesObj[`capturedimg${i}`])) {
                    count++;
                }
            }

            return count;
        },

        /**
         * Internal: Count serial images
         */
        _countSerialImages(item) {
            if (!item?.capturedImages) return 0;

            let count = 0;
            if (this.isValidImage(item.capturedImages.serialimg1)) count++;
            if (this.isValidImage(item.capturedImages.serialimg2)) count++;
            if (this.isValidImage(item.capturedImages.trackingimg1)) count++;
            if (this.isValidImage(item.capturedImages.trackingimg2)) count++;

            return count;
        },

        /**
         * Count all images including serial images
         */
        countAllImages(item) {
            const captured = this._countCapturedImages(item);
            const serial = this._countSerialImages(item);
            const regular = this._countRegularImages(item);

            // Return captured + serial if any captured images exist
            if (captured > 0) {
                return captured + serial;
            }

            // Otherwise return regular images
            return regular;
        },

        /**
         * For backward compatibility - use in templates
         */
        countCapturedImages(item) {
            return (
                this.countImages(item, "captured") +
                this._countSerialImages(item)
            );
        },

        countRegularImages(item) {
            return this.countImages(item, "regular");
        },

        transformDataForGallery(data) {
            if (!data) {
                return {};
            }

            if (data.capturedImages && data.capturedImages.capturedimg1) {
                const transformedData = { ...data };
                const companyFolder = data.company || "Airstaffs";

                for (let i = 1; i <= 12; i++) {
                    const capturedImg = data.capturedImages[`capturedimg${i}`];
                    if (capturedImg) {
                        transformedData[`img${i}`] =
                            `/images/product_images/${companyFolder}/${capturedImg}`;
                    } else {
                        transformedData[`img${i}`] = null;
                    }
                }

                for (let i = 13; i <= 15; i++) {
                    transformedData[`img${i}`] = null;
                }

                return transformedData;
            }

            return data;
        },

        // Open the image modal and prepare images
        openImageModal(item) {
            if (!item) return;

            this.regularImages = [];
            this.capturedImages = [];
            this.currentImageIndex = 0;
            this.ProductTitle = item.ProductTitle;
            const companyFolder = item.company || "Airstaffs";

            console.log("🔍 Opening image modal for item:", {
                ProductID: item.ProductID,
                rtcounter: item.rtcounter,
                company: companyFolder,
                capturedImages: item.capturedImages,
            });

            // Load regular images (img1 - img15)
            for (let i = 1; i <= 15; i++) {
                const fieldName = `img${i}`;
                if (this.isValidImage(item[fieldName])) {
                    const path = `/images/thumbnails/${item[fieldName]}`;
                    this.regularImages.push(path);
                }
            }

            console.log("📸 Regular images loaded:", this.regularImages.length);

            // ✅ FIXED: Load captured images properly
            if (
                item.capturedImages &&
                typeof item.capturedImages === "object"
            ) {
                const capturedImagesObj = item.capturedImages;

                console.log(
                    "🔍 Processing captured images:",
                    capturedImagesObj,
                );

                // Load capturedimg1 - capturedimg12
                for (let i = 1; i <= 12; i++) {
                    const fieldName = `capturedimg${i}`;
                    if (this.isValidImage(capturedImagesObj[fieldName])) {
                        const filename = capturedImagesObj[fieldName];
                        const path = `/images/product_images/${companyFolder}/${filename}`;
                        this.capturedImages.push(path);
                        console.log(`✅ Added captured image ${i}:`, path);
                    }
                }

                // Load serial images (serialimg1 and serialimg2)
                if (this.isValidImage(capturedImagesObj.serialimg1)) {
                    const filename = capturedImagesObj.serialimg1;
                    const path = `/images/product_images/${companyFolder}/${filename}`;
                    this.capturedImages.push(path);
                    console.log("✅ Added serial image 1:", path);
                }

                if (this.isValidImage(capturedImagesObj.serialimg2)) {
                    const filename = capturedImagesObj.serialimg2;
                    const path = `/images/product_images/${companyFolder}/${filename}`;
                    this.capturedImages.push(path);
                    console.log("✅ Added serial image 2:", path);
                }

                // Load tracking images (trackingimg1 and trackingimg2)
                if (this.isValidImage(capturedImagesObj.trackingimg1)) {
                    const filename = capturedImagesObj.trackingimg1;
                    const path = `/images/product_images/${companyFolder}/${filename}`;
                    this.capturedImages.push(path);
                    console.log("✅ Added tracking image 1:", path);
                }

                if (this.isValidImage(capturedImagesObj.trackingimg2)) {
                    const filename = capturedImagesObj.trackingimg2;
                    const path = `/images/product_images/${companyFolder}/${filename}`;
                    this.capturedImages.push(path);
                    console.log("✅ Added tracking image 2:", path);
                }
            }

            console.log(
                "📸 Total captured images loaded:",
                this.capturedImages.length,
            );

            // Fallback if no images exist
            if (
                this.regularImages.length === 0 &&
                this.capturedImages.length === 0
            ) {
                this.regularImages.push(this.defaultImage);
            }

            // Set default active tab
            this.activeTab = this.regularImages.length ? "regular" : "captured";
            this.currentImageSet =
                this.activeTab === "regular"
                    ? this.regularImages
                    : this.capturedImages;

            // Show modal and disable page scrolling
            this.showImageModal = true;
            document.body.style.overflow = "hidden";
        },

        // Method to switch tabs
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

            // Re-enable scrolling
            document.body.style.overflow = "auto";
        },

        nextImage() {
            if (this.currentImageIndex < this.currentImageSet.length - 1) {
                this.currentImageIndex++;
            } else {
                this.currentImageIndex = 0; // Loop back to the first image
            }
        },

        prevImage() {
            if (this.currentImageIndex > 0) {
                this.currentImageIndex--;
            } else {
                this.currentImageIndex = this.currentImageSet.length - 1; // Loop to the last image
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

        // Fetch inventory data from the API
        async fetchInventory() {
            this.loading = true;
            this.error = null; // Clear any previous errors

            try {
                console.log("Fetching inventory with params:", {
                    search: this.searchQuery,
                    page: this.currentPage,
                    per_page: this.perPage,
                    location: "Houseage",
                    include_images: true,
                });

                const response = await axios.get(
                    `${API_BASE_URL}/api/houseage/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            location: "Houseage",
                            include_images: true,
                        },
                        timeout: 30000, // 30 second timeout
                    },
                );

                console.log("✅ Response received:", {
                    status: response.status,
                    dataCount: response.data?.data?.length,
                });

                // ✅ Validate response structure
                if (!response.data) {
                    throw new Error("Invalid response: No data received");
                }

                // ✅ Handle different response structures
                if (Array.isArray(response.data)) {
                    // Direct array response
                    this.inventory = response.data;
                } else if (
                    response.data.data &&
                    Array.isArray(response.data.data)
                ) {
                    // Paginated response
                    this.inventory = response.data.data;
                    this.totalRecords = response.data.total
                    this.currentPage = response.data.current_page;
                } else {
                    throw new Error("Invalid response structure");
                }

                console.log("✅ Inventory updated:", {
                    count: this.inventory.length,
                });

                // ✅ Force Vue to recognize the data change
                this.$forceUpdate();
            } catch (error) {
                console.error("❌ Error fetching inventory data:", {
                    message: error.message,
                    response: error.response?.data,
                    status: error.response?.status,
                });

                this.error = error.message || "Failed to load inventory";
                this.inventory = []; // Clear inventory on error

                // Show error to user
                if (error.response?.status === 500) {
                    await Swal.fire({
                        icon: "error",
                        title: "Server Error",
                        text: "Unable to load inventory. Please contact support.",
                    });
                } else if (error.code === "ECONNABORTED") {
                    await Swal.fire({
                        icon: "error",
                        title: "Request Timeout",
                        text: "The request took too long. Please try again.",
                    });
                }
            } finally {
                this.loading = false;
                console.log("✅ Loading state set to false");
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

        // FNSKU Modal methods - Fixed and improved
        async showFnskuModal(item) {
            console.log("Opening FNSKU modal for item:", item);
            this.currentItem = item;
            this.isFnskuModalVisible = true;
            this.fnskuSearch = item.ASINviewer || ""; // Pre-fill with current ASIN for easier search

            try {
                console.log("Fetching FNSKU list...");
                const response = await axios.get(`${API_BASE_URL}/fnsku-list`);
                console.log("FNSKU list response:", response.data);
                this.fnskuList = response.data;
                this.filterFnskuList(); // Apply initial filter
            } catch (error) {
                console.error("Error fetching FNSKU list:", error);
                alert("Error fetching FNSKU list. Please try again.");
            }
        },

        hideFnskuModal() {
            console.log("Hiding FNSKU modal");
            this.isFnskuModalVisible = false;
            this.currentItem = null;
            this.fnskuList = [];
            this.filteredFnskuList = [];
            this.fnskuSearch = "";
        },

        filterFnskuList() {
            console.log("Filtering FNSKU list with search:", this.fnskuSearch);
            if (!this.fnskuSearch) {
                // If empty search, show matching ASIN first, then everything else
                this.filteredFnskuList = [...this.fnskuList].sort((a, b) => {
                    if (
                        a.ASIN === this.currentItem?.ASINviewer &&
                        b.ASIN !== this.currentItem?.ASINviewer
                    ) {
                        return -1;
                    } else if (
                        a.ASIN !== this.currentItem?.ASINviewer &&
                        b.ASIN === this.currentItem?.ASINviewer
                    ) {
                        return 1;
                    }
                    return 0;
                });
                return;
            }

            const search = this.fnskuSearch.toLowerCase();
            this.filteredFnskuList = this.fnskuList.filter(
                (fnsku) =>
                    fnsku.FNSKU?.toLowerCase().includes(search) ||
                    fnsku.ASIN?.toLowerCase().includes(search) ||
                    fnsku.astitle?.toLowerCase().includes(search),
            );
        },

        async selectFnsku(fnsku) {
            console.log("Selecting FNSKU:", fnsku);
            if (!this.currentItem || !fnsku) return;

            try {
                // Get the CSRF token from the meta tag
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                // Make the request with proper data format and headers
                const response = await axios.post(
                    `${API_BASE_URL}/update-fnsku`,
                    {
                        product_id: this.currentItem.ProductID,
                        fnsku: fnsku.FNSKU,
                        msku: fnsku.MSKU,
                        asin: fnsku.ASIN,
                        grading: fnsku.grading,
                        astitle: fnsku.astitle,
                    },
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    },
                );

                console.log("Update FNSKU response:", response.data);

                if (response.data.success) {
                    alert(`FNSKU updated to ${fnsku.FNSKU}`);
                    this.hideFnskuModal();
                    this.fetchInventory(); // Refresh the data
                } else {
                    alert(response.data.message || "Failed to update FNSKU");
                }
            } catch (error) {
                console.error("Error updating FNSKU:", error);
                alert("Failed to update FNSKU. Please try again.");
            }
        },

        // Add these methods to the methods object in your component
        async moveToValidation(item) {
            if (!item || !item.ProductID) {
                console.error("Invalid item data for moving to Validation");
                return;
            }

            try {
                // Get the CSRF token from the meta tag
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                // Make the request with proper data format and headers
                const response = await axios.post(
                    `${API_BASE_URL}/api/labeling/move-to-validation`,
                    {
                        product_id: item.ProductID,
                        rt_counter: item.rtcounter,
                        current_location: "Labeling",
                        new_location: "Validation",
                    },
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    },
                );

                console.log("Move to Validation response:", response.data);

                if (response.data.success) {
                    // Show success message
                    alert(
                        `Item ${item.rtcounter} successfully moved to Validation`,
                    );
                    // Refresh the inventory list
                    this.fetchInventory();
                } else {
                    alert(
                        response.data.message ||
                            "Failed to move item to Validation",
                    );
                }
            } catch (error) {
                console.error("Error moving item to Validation:", error);
                alert("Failed to move item to Validation. Please try again.");
            }
        },

        async moveToStockroom(item) {
            if (!item || !item.ProductID) {
                console.error("Invalid item data for moving to Stockroom");
                return;
            }

            try {
                // Get the CSRF token from the meta tag
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                // Make the request with proper data format and headers
                const response = await axios.post(
                    `${API_BASE_URL}/api/labeling/move-to-stockroom`,
                    {
                        product_id: item.ProductID,
                        rt_counter: item.rtcounter,
                        current_location: "Labeling",
                        new_location: "Stockroom",
                    },
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    },
                );

                console.log("Move to Stockroom response:", response.data);

                if (response.data.success) {
                    // Show success message
                    alert(
                        `Item ${item.rtcounter} successfully moved to Stockroom`,
                    );
                    // Refresh the inventory list
                    this.fetchInventory();
                } else {
                    alert(
                        response.data.message ||
                            "Failed to move item to Stockroom",
                    );
                }
            } catch (error) {
                console.error("Error moving item to Stockroom:", error);
                alert("Failed to move item to Stockroom. Please try again.");
            }
        },

        // Method to show the validation confirmation
        confirmMoveToValidation(item) {
            this.showConfirmationModal = true;
            this.confirmationTitle = "Move to Validation";
            this.confirmationMessage = `Are you sure you want to move item #${item.rtcounter} from Labeling to Validation?`;
            this.confirmationActionType = "validation";
            this.currentItemForAction = item;

            // Prevent scrolling when modal is open
            document.body.style.overflow = "hidden";
        },

        // Method to show the stockroom confirmation
        confirmMoveToStockroom(item) {
            this.showConfirmationModal = true;
            this.confirmationTitle = "Move to Stockroom";
            this.confirmationMessage = `Are you sure you want to move item #${item.rtcounter} from Labeling to Stockroom?`;
            this.confirmationActionType = "stockroom";
            this.currentItemForAction = item;

            // Prevent scrolling when modal is open
            document.body.style.overflow = "hidden";
        },

        // Method to handle the cancellation
        cancelConfirmation() {
            this.showConfirmationModal = false;
            this.currentItemForAction = null;

            // Re-enable scrolling
            document.body.style.overflow = "auto";
        },

        // Method to confirm and execute the action
        confirmAction() {
            if (!this.currentItemForAction) return;

            if (this.confirmationActionType === "validation") {
                this.moveToValidation(this.currentItemForAction);
            } else if (this.confirmationActionType === "stockroom") {
                this.moveToStockroom(this.currentItemForAction);
            }

            // Close the modal
            this.showConfirmationModal = false;
            this.currentItemForAction = null;

            // Re-enable scrolling
            document.body.style.overflow = "auto";
        },

        async openEditModal(item) {
            if (!item) return;

            // ✅ Simply use the item directly from inventory (it already has capturedImages loaded)
            this.item = JSON.parse(JSON.stringify(item)); // Deep clone to avoid mutations

            // Reset image state when opening (but preserve server images)
            this.resetSerialImage({ clearServer: false });

            this.showEditModal = true;
            document.body.style.overflow = "hidden";

            await this.$nextTick();
        },

        closeEditModal() {
            this.showEditModal = false;

            // Reset image state on close
            this.resetSerialImage({ clearServer: true });

            // Clear the item after animation
            setTimeout(() => {
                this.item = {};
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
            // Convert 0 => A, 1 => B, etc.
            return String.fromCharCode(65 + index);
        },

        async fetchItems() {
            this.loading = true;
            try {
                const response = await axios.get("/api/houseage/products");
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

        getLabel(index) {
            // Convert 0 => A, 1 => B, etc.
            return String.fromCharCode(65 + index);
        },

        async saveEditModal() {
            this.loading = true;

            ["RPN", "PRD", "PCN", "basketnumber"].forEach((k) => {
                if (this.item[k])
                    this.item[k] = String(this.item[k]).toUpperCase().trim();
            });

            if (
                this.item.basketnumber &&
                /^\d+$/.test(this.item.basketnumber)
            ) {
                this.item.basketnumber = `BKT${this.item.basketnumber}`;
            }

            // Validate required prefixes
            const errors = [];
            if (this.item.RPN && !/^RPN\d+$/.test(this.item.RPN))
                errors.push("RPN must start with 'RPN' followed by numbers.");
            if (this.item.PRD && !/^PRD\d+$/.test(this.item.PRD))
                errors.push("PRD must start with 'PRD' followed by numbers.");
            if (this.item.PCN && !/^PCN\d+$/.test(this.item.PCN))
                errors.push("PCN must start with 'PCN' followed by numbers.");
            if (
                this.item.basketnumber &&
                !/^(BKT|SI|ENV)\d+$/i.test(this.item.basketnumber)
            ) {
                errors.push(
                    "Basket/Shelf/Envelope Number must start with 'BKT', 'SI', or 'ENV' followed by numbers.",
                );
            }

            // ============ NEW: Validate Serial Numbers ============
            // Clear all serial errors first
            this.serialErrors = {};

            // Trim all serial values
            this.serialKeys.forEach((key) => {
                if (this.item[key]) {
                    this.item[key] = this.item[key].trim();
                }
            });

            // Get all serial values (non-empty)
            const serialValues = this.serialKeys
                .map((key) => ({
                    key,
                    value: this.item[key]?.trim(),
                }))
                .filter((s) => s.value); // Only non-empty values

            // Check for duplicates within the same product (Serial A = Serial B = Serial C, etc.)
            if (serialValues.length > 1) {
                const duplicateMap = {};

                // Find which values appear more than once
                serialValues.forEach((s) => {
                    if (!duplicateMap[s.value]) {
                        duplicateMap[s.value] = [];
                    }
                    duplicateMap[s.value].push(s.key);
                });

                // Check if any value appears more than once
                const duplicates = Object.entries(duplicateMap).filter(
                    ([value, keys]) => keys.length > 1,
                );

                if (duplicates.length > 0) {
                    duplicates.forEach(([value, keys]) => {
                        const labels = keys
                            .map((k) => {
                                const match = k.match(/serialnumber([a-z]?)/i);
                                return match[1]
                                    ? `Serial ${match[1].toUpperCase()}`
                                    : "Serial";
                            })
                            .join(" and ");

                        const errorMsg = `${labels} cannot have the same value (${value}).`;
                        errors.push(errorMsg);

                        // Mark all duplicate fields
                        keys.forEach((key) => {
                            this.serialErrors[key] = errorMsg;
                        });
                    });
                }
            }

            // Check for duplicates across products (only if no same-product duplicates found)
            if (serialValues.length > 0 && errors.length === 0) {
                try {
                    for (const serialObj of serialValues) {
                        const response = await axios.post(
                            "/api/houseage/check-duplicate-serial",
                            {
                                serial: serialObj.value,
                                current_product_id: this.item.ProductID || null,
                                serial_field: serialObj.key,
                            },
                        );

                        if (response.data.duplicate) {
                            let errorMsg;

                            if (response.data.type === "cross_product") {
                                errorMsg = `Serial number "${serialObj.value}" already exists in another product.`;
                            } else if (response.data.type === "same_product") {
                                // This shouldn't happen since we check client-side first, but handle it anyway
                                errorMsg = response.data.message;
                            }

                            if (errorMsg) {
                                errors.push(errorMsg);
                                this.serialErrors[serialObj.key] = errorMsg;
                                break; // Stop checking after first duplicate found
                            }
                        }
                    }
                } catch (error) {
                    console.error("Serial validation error:", error);
                    if (error.response?.status !== 405) {
                        errors.push(
                            "Failed to validate serial numbers. Please try again.",
                        );
                    }
                }
            }
            // ============ END: Validate Serial Numbers ============

            if (errors.length) {
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

                console.log(
                    "PUT payload:",
                    JSON.parse(JSON.stringify(payload)),
                );

                const response = await axios.put(
                    `/api/houseage/products/${this.item.ProductID}`,
                    payload,
                );
                const updated = response.data.product;

                const index = this.items.findIndex(
                    (p) => p.ProductID === updated.ProductID,
                );
                if (index !== -1) this.items.splice(index, 1, updated);
                else this.items.unshift(updated);

                await Swal.fire({
                    icon: "success",
                    title: "Saved!",
                    text:
                        response.data.message ||
                        "The product has been saved successfully.",
                    confirmButtonText: "OK",
                });

                this.closeEditModal();
                await this.fetchInventory();
            } catch (error) {
                console.error("Save failed:", {
                    message: error.message,
                    status: error.response?.status,
                    data: error.response?.data,
                });

                let message =
                    "An error occurred while saving. Please try again.";
                if (
                    error.response?.status === 422 &&
                    error.response.data?.errors
                ) {
                    message = Object.values(error.response.data.errors)
                        .flat()
                        .join("\n");
                } else if (error.response?.data?.message) {
                    message = error.response.data.message;
                }

                Swal.fire({
                    icon: "error",
                    title: "Save Failed",
                    text: message,
                    confirmButtonText: "OK",
                });
            } finally {
                this.loading = false;
            }
        },

        async checkDuplicateSerial(serial, serialField) {
            // Clear previous error for this field
            this.serialErrors[serialField] = null;

            if (!serial || serial.trim() === "") {
                return;
            }

            const trimmedSerial = serial.trim();

            // Check against other serial fields in the same product
            const otherSerials = this.serialKeys
                .filter((key) => key !== serialField)
                .map((key) => ({
                    key,
                    value: this.item[key]?.trim(),
                }))
                .filter((s) => s.value);

            // Check for duplicate within same product first
            const localDuplicate = otherSerials.find(
                (s) => s.value === trimmedSerial,
            );
            if (localDuplicate) {
                const currentLabel =
                    serialField
                        .match(/serialnumber([a-z]?)/i)?.[1]
                        ?.toUpperCase() || "";
                const duplicateLabel =
                    localDuplicate.key
                        .match(/serialnumber([a-z]?)/i)?.[1]
                        ?.toUpperCase() || "";

                this.serialErrors[serialField] =
                    `Serial ${currentLabel} and Serial ${duplicateLabel} cannot have the same value.`;
                return;
            }

            // Check for duplicates across products
            try {
                const response = await axios.post(
                    "/api/houseage/check-duplicate-serial",
                    {
                        serial: trimmedSerial,
                        current_product_id: this.item.ProductID || null,
                        serial_field: serialField,
                    },
                );

                if (response.data.duplicate) {
                    if (response.data.type === "cross_product") {
                        this.serialErrors[serialField] =
                            "This serial number already exists in another product.";
                    } else if (response.data.type === "same_product") {
                        this.serialErrors[serialField] = response.data.message;
                    }
                }
            } catch (error) {
                if (error.response?.status !== 405) {
                    console.error("Error checking duplicate serial:", error);
                }
            }
        },

        getFirstNonEmptySerial() {
            if (!Array.isArray(this.serialKeys)) return "";
            const i = this.serialKeys.findIndex(
                (k) => (this.item?.[k] ?? "").toString().trim() !== "",
            );
            return i === -1
                ? ""
                : (this.item[this.serialKeys[i]] ?? "").toString().trim();
        },

        onSerialImgError() {
            // If the <img> fails to load for any reason, fall back to default
            this.serialImageUrl = "";
            this.serialImagePath = "";
        },

        onSerialImageSelected(evt) {
            // Always get the file FIRST
            const input = evt?.target ?? this.$refs.serialInput;
            const file = input?.files?.[0] || null;

            if (!file) {
                this.serialImageError = "No file selected.";
                return;
            }

            // Now it's safe to use `file`
            if (!file.type.startsWith("image/")) {
                this.serialImageError = "Please select an image file.";
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                // 5MB
                this.serialImageError = "Max size is 5MB.";
                return;
            }

            this.serialImageError = "";
            this.serialImageFile = file;

            const reader = new FileReader();
            reader.onload = () => (this.serialImageUrl = reader.result);
            reader.readAsDataURL(file);
        },

        async uploadSerialImage() {
            if (!this.serialImageFile) return;

            // Check for product ID (required now)
            if (!this.item?.ProductID) {
                await Swal.fire({
                    icon: "error",
                    title: "Product ID required",
                    text: "Cannot upload serial image without a valid product ID.",
                });
                return;
            }

            // Serial number still required for validation
            const idx = Array.isArray(this.serialKeys)
                ? this.serialKeys.findIndex(
                      (k) => (this.item[k] ?? "").toString().trim() !== "",
                  )
                : -1;

            if (idx === -1) {
                await Swal.fire({
                    icon: "error",
                    title: "Serial number required",
                    text: "Please enter a serial number before saving the image.",
                });
                return;
            }

            const serial = (this.item[this.serialKeys[idx]] ?? "")
                .toString()
                .trim();

            this.serialImageUploading = true;
            this.serialImageError = "";
            this.uploadProgress = 0;

            Swal.fire({
                title: "Uploading…",
                html: '<div class="progress" style="height: 20px;"><div id="upload-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div></div>',
                didOpen: () => Swal.showLoading(),
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
            });

            try {
                const form = new FormData();
                form.append("image", this.serialImageFile);
                form.append("serial_number", serial);

                form.append("product_id", this.item.ProductID);
                const company = this.item?.company || "Airstaffs";
                form.append("company", company);

                if (this.serialImagePath) {
                    form.append("old_path", this.serialImagePath);
                }

                const { data } = await axios.post(
                    "/api/houseage/serial-image",
                    form,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                        onUploadProgress: (progressEvent) => {
                            if (progressEvent.total) {
                                this.uploadProgress = Math.round(
                                    (progressEvent.loaded * 100) /
                                        progressEvent.total,
                                );

                                const progressBar = document.getElementById(
                                    "upload-progress-bar",
                                );
                                if (progressBar) {
                                    progressBar.style.width =
                                        this.uploadProgress + "%";
                                    progressBar.textContent =
                                        this.uploadProgress + "%";
                                }
                            }
                        },
                    },
                );

                // ✅ Update the serial image path immediately
                this.serialImagePath = data.url || data.path;

                // ✅ Update the captured images object with the new serial image
                if (!this.item.capturedImages) {
                    this.item.capturedImages = {};
                }

                const filename = data.filename;
                const serialNumber = data.serial_number || 1;

                if (serialNumber === 1) {
                    this.item.capturedImages.serialimg1 = filename;
                } else if (serialNumber === 2) {
                    this.item.capturedImages.serialimg2 = filename;
                }

                // ✅ NEW: Refetch the complete product data from server
                await this.refetchProductData(this.item.ProductID);

                // Clear the file input and local preview
                this.serialImageFile = null;
                this.serialImageUrl = "";

                if (this.$refs.serialInput) {
                    this.$refs.serialInput.value = "";
                }

                this.$emit("serial-image-updated", this.serialImagePath);

                Swal.close();

                await Swal.fire({
                    icon: "success",
                    title: "Serial image uploaded successfully",
                    confirmButtonText: "OK",
                });
            } catch (err) {
                this.serialImageError =
                    err?.response?.data?.message ||
                    err?.response?.data?.errors?.image?.[0] ||
                    "Upload failed.";

                Swal.close();

                await Swal.fire({
                    icon: "error",
                    title: "Upload failed",
                    html: `
                <p>${this.serialImageError}</p>
                ${
                    err?.response?.data?.errors
                        ? '<p class="text-muted mt-2"><small>' +
                          Object.values(err.response.data.errors)
                              .flat()
                              .join("<br>") +
                          "</small></p>"
                        : ""
                }
            `,
                });
            } finally {
                this.serialImageUploading = false;
                this.uploadProgress = 0;
            }
        },

        /**
         * ✅ Refetch single product data from server
         */
        async refetchProductData(productId) {
            if (!productId) return;

            try {
                console.log(
                    "🔄 Refetching product data for ProductID:",
                    productId,
                );

                const response = await axios.get(
                    `${API_BASE_URL}/api/houseage/products/${productId}`,
                    {
                        params: {
                            include_images: true,
                        },
                    },
                );

                const updatedProduct = response.data;

                console.log("✅ Product data refetched:", updatedProduct);

                // ✅ Update the current item in the modal
                if (updatedProduct) {
                    // Preserve the current item structure but update with fresh data
                    Object.assign(this.item, updatedProduct);

                    // ✅ FIXED: Update in the inventory array (Vue 3 compatible)
                    const index = this.inventory.findIndex(
                        (p) => p.ProductID === productId,
                    );

                    if (index !== -1) {
                        this.inventory[index] = updatedProduct; // Direct assignment
                        console.log("✅ Updated product in inventory array");
                    }

                    // Force Vue to update (not usually needed in Vue 3, but doesn't hurt)
                    this.$forceUpdate();
                }

                return updatedProduct;
            } catch (error) {
                console.error("❌ Error refetching product data:", error);
                // Don't show error to user, just log it
            }
        },

        // ✅ UPDATE: fetchSerialImageIfAny to use product_id instead of serial_number
        async fetchSerialImageIfAny() {
            // ✅ Check for product ID first
            if (!this.item?.ProductID) {
                this.serialImagePath = "";
                return;
            }

            try {
                const company = this.item?.company || "Airstaffs";

                const { data } = await axios.get("/api/houseage/serial-image", {
                    params: {
                        product_id: this.item.ProductID, // ✅ Changed from serial_number
                        company: company,
                    },
                });

                if (data?.exists) {
                    this.serialImagePath = data.url;

                    // ✅ Update capturedImages object with all found serial images
                    if (data.images) {
                        if (!this.item.capturedImages) {
                            this.item.capturedImages = {};
                        }

                        if (data.images.serial1) {
                            this.item.capturedImages.serialimg1 =
                                data.images.serial1.filename;
                        }
                        if (data.images.serial2) {
                            this.item.capturedImages.serialimg2 =
                                data.images.serial2.filename;
                        }
                    }
                } else {
                    this.serialImagePath = "";
                }
            } catch (e) {
                console.error("Error fetching serial image:", e);
                this.serialImagePath = "";
            }
        },

        removeSerialImage() {
            this.serialImageFile = null;
            this.serialImageUrl = "";
            this.serialImageError = "";
        },

        resetSerialImage({ clearServer = false } = {}) {
            this.serialImageFile = null;
            this.serialImageUrl = "";
            this.serialImageError = "";
            this.uploadProgress = 0;
            if (clearServer) this.serialImagePath = "";
        },
    },

    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.first = 0
            this.fetchInventory();
        },

        item: {
            deep: true,
            handler() {
                this.fetchSerialImageIfAny();
            },
        },

        loading(newVal, oldVal) {
            console.log("🔄 Loading state changed:", {
                from: oldVal,
                to: newVal,
            });
        },

        inventory(newVal, oldVal) {
            console.log("📦 Inventory changed:", {
                oldCount: oldVal?.length || 0,
                newCount: newVal?.length || 0,
            });
        },

        sortedInventory(newVal, oldVal) {
            console.log("📊 Sorted inventory changed:", {
                oldCount: oldVal?.length || 0,
                newCount: newVal?.length || 0,
            });
        },
    },

    mounted() {
        this.currentUser = window.Laravel?.user || null;

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

        this.fetchItems();

        this.fetchSerialImageIfAny();
    },

    beforeUnmount() {
        // Use beforeUnmount for Vue 3, beforeDestroy for Vue 2
        // Clean up keyboard event listener
        if (this.handleKeyDown) {
            window.removeEventListener("keydown", this.handleKeyDown);
            this.handleKeyDown = null;
        }

        // ✅ Ensure modals are closed
        this.showImageModal = false;
        this.showEditModal = false;
        this.showCopyDetailsModal = false;
        this.isFnskuModalVisible = false;

        // ✅ Restore body scroll
        document.body.style.overflow = "auto";

        // ✅ Clear any pending timers or intervals
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = null;
        }

        // ✅ Revoke object URLs to prevent memory leaks
        if (this.serialImageUrl && this.serialImageUrl.startsWith("blob:")) {
            URL.revokeObjectURL(this.serialImageUrl);
        }

        // ✅ Clear large data structures
        this.inventory = [];
        this.fnskuList = [];
        this.filteredFnskuList = [];
        this.regularImages = [];
        this.capturedImages = [];
        this.currentImageSet = [];
    },
};
