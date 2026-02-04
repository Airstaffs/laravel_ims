import { eventBus } from "../../components/eventbus";
import "../../../css/modules.css";
import "./validation.css";
import Swal from "sweetalert2";
import { DEFAULT_IMAGE } from "../../constant";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ProductList",
    data() {
        return {
            inventory: [],
            loading: true,
            currentPage: 1,
            totalPages: 1,
            perPage: 10, // Default rows per page
            selectAll: false,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,
            defaultImage: DEFAULT_IMAGE,

            validationStatusFilter: "", // Add this for filtering

            // Modal state
            showImageModal: false,
            regularImages: [], // For regular product images
            capturedImages: [], // For captured images
            asinImages: [], // For ASIN images
            activeTab: "regular", // Track which tab is active
            currentImageIndex: 0,
            currentImageSet: [], // The currently displayed image set based on active tab

            showConfirmationModal: false,
            confirmationTitle: "",
            confirmationMessage: "",
            confirmationActionType: "", // 'validation' or 'stockroom'
            currentItemForAction: null, // Store the item to be processed

            // New validation modal properties
            showValidationModal: false,
            currentValidationItem: null,
            validationNotes: "",
            isProcessingValidation: false,
            validationErrors: null,

            // ASIN related properties
            currentValidationItemAsinImages: [],
            currentValidationItemAsinLoaded: false,

            // Validation tabs
            validationActiveTab: "product",

            // Validation confirmation properties
            validationConfirmationTitle: "",
            validationConfirmationMessage: "",
            validationConfirmationType: "", // 'valid' or 'invalid'
            showValidationConfirmationModal: false,

            isProcessing: false,

            items: [],
            item: {},
            ASIN: "",
            activeIndex: 0,
            activeAsinIndex: 0,
            basePath: "/images/thumbnails/",
            asinBasePath: "/images/asinimg/",
            asinImageCount: 2,
            loading: false,
            error: null,

            activeCapturedIndex: 0,
        };
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery;
        },

        // Get unique validation statuses for the filter dropdown
        uniqueValidationStatuses() {
            const statuses = [
                ...new Set(
                    this.inventory
                        .map((item) => item.validation_status)
                        .filter(Boolean),
                ),
            ];
            return statuses.sort();
        },

        // Filter inventory based on selected validation status
        filteredInventory() {
            if (!this.validationStatusFilter) {
                return this.inventory;
            }
            return this.inventory.filter(
                (item) =>
                    item.validation_status === this.validationStatusFilter,
            );
        },

        // Sort the filtered inventory
        sortedInventory() {
            const itemsToSort = this.filteredInventory; // Use filtered inventory instead of this.inventory

            if (!this.sortColumn) return itemsToSort;

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

        hasValidationCapturedImages() {
            if (
                !this.currentValidationItem ||
                !this.currentValidationItem.capturedImages
            )
                return false;

            for (let i = 1; i <= 12; i++) {
                const fieldName = `capturedimg${i}`;
                if (
                    this.currentValidationItem.capturedImages[fieldName] &&
                    this.currentValidationItem.capturedImages[fieldName] !==
                        "NULL" &&
                    this.currentValidationItem.capturedImages[
                        fieldName
                    ].trim() !== ""
                ) {
                    return true;
                }
            }

            return false;
        },

        imageList() {
            return Object.keys(this.item)
                .filter((key) => key.startsWith("img") && this.item[key])
                .map((key) => this.item[key]);
        },

        asinImageList() {
            if (!this.item || !this.item.ASIN) return [];

            if (
                this.item.asinImages &&
                Array.isArray(this.item.asinImages) &&
                this.item.asinImages.length > 0
            ) {
                return this.item.asinImages.map(
                    (filename) => `/images/asinimg/${filename}`,
                );
            }

            const images = [];
            for (let i = 0; i < this.asinImageCount; i++) {
                images.push(`/images/asinimg/${this.item.ASIN}_${i}.jpg`);
            }

            return images;
        },

        activeAsinImageUrl() {
            if (!this.asinImageList.length) return this.defaultImage;
            return (
                this.asinImageList[this.activeAsinIndex] || this.defaultImage
            );
        },

        activeImageUrl() {
            return this.basePath + this.imageList[this.activeIndex];
        },

        capturedImageList() {
            if (!this.item || !this.item.capturedImages) {
                return [this.defaultImage];
            }

            const images = [];
            const companyFolder = this.item.company || "Airstaffs";
            const capturedImagesObj = this.item.capturedImages;

            // Add capturedimg1 through capturedimg12
            for (let i = 1; i <= 12; i++) {
                const fieldName = `capturedimg${i}`;
                if (this.isValidImage(capturedImagesObj[fieldName])) {
                    const filename = capturedImagesObj[fieldName];
                    images.push(
                        `/images/product_images/${companyFolder}/${filename}`,
                    );
                }
            }

            // Add serial images
            if (this.isValidImage(capturedImagesObj.serialimg1)) {
                const filename = capturedImagesObj.serialimg1;
                images.push(
                    `/images/product_images/${companyFolder}/${filename}`,
                );
            }

            if (this.isValidImage(capturedImagesObj.serialimg2)) {
                const filename = capturedImagesObj.serialimg2;
                images.push(
                    `/images/product_images/${companyFolder}/${filename}`,
                );
            }

            // ✅ Return default image if no captured images found
            if (images.length === 0) {
                return [this.defaultImage];
            }

            return images;
        },

        activeCapturedImageUrl() {
            if (this.capturedImageList.length === 0) {
                return this.defaultImage;
            }
            return (
                this.capturedImageList[this.activeCapturedIndex] ||
                this.defaultImage
            );
        },
    },
    methods: {
        handleImageError(event) {
            // If image fails to load, use an inline SVG placeholder
            event.target.src = this.defaultImage;
            event.target.onerror = null; // Prevent infinite error loop
        },

        // Helper to validate image fields
        isValidImage(path) {
            return path && path !== "NULL" && path.trim() !== "";
        },

        // Generic image counter for any image type
        countImages(item, prefix, start, end, container = null) {
            if (!item) return 0;
            const source = container ? item[container] : item;
            if (!source) return 0;

            let count = 0;
            for (let i = start; i <= end; i++) {
                const fieldName = `${prefix}${i}`;
                if (this.isValidImage(source[fieldName])) {
                    count++;
                }
            }
            return count;
        },

        // Count regular images (img2 - img15)
        countRegularImages(item) {
            return this.countImages(item, "img", 2, 15);
        },

        // Count captured images (capturedimg1 - capturedimg12)
        countCapturedImages(item) {
            if (!item || !item.capturedImages) return 0;

            console.log("🔍 Counting captured images for item:", {
                ProductID: item.ProductID,
                capturedImages: item.capturedImages,
            });

            let count = 0;
            const capturedImagesObj = item.capturedImages;

            // Check both capturedimg1-12 AND serialimg1-2
            for (let i = 1; i <= 12; i++) {
                const fieldName = `capturedimg${i}`;
                if (this.isValidImage(capturedImagesObj[fieldName])) {
                    count++;
                }
            }

            // Also check serial images
            if (this.isValidImage(capturedImagesObj.serialimg1)) count++;
            if (this.isValidImage(capturedImagesObj.serialimg2)) count++;

            console.log("🔍 Total captured images found:", count);
            return count;
        },

        // Count all images (regular + captured)
        countAllImages(item) {
            if (!item) {
                return 0;
            }

            // If captured images exist, count them
            if (item.capturedImages) {
                let capturedCount = 0;
                const capturedImagesObj = item.capturedImages;

                // Count capturedimg1-12
                for (let i = 1; i <= 12; i++) {
                    const fieldName = `capturedimg${i}`;
                    if (this.isValidImage(capturedImagesObj[fieldName])) {
                        capturedCount++;
                    }
                }

                // If we have captured images, return that count
                if (capturedCount > 0) {
                    return capturedCount;
                }
            }

            // Otherwise count regular product images (fallback)
            return this.countRegularImages(item);
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
                    // First image uses rtcounter, rest use item[fieldName]
                    const filename =
                        i === 1 ? `${item.rtcounter}.jpg` : item[fieldName];
                    const path = `/images/thumbnails/${filename}`;

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

            try {
                console.log("Fetching inventory with params:", {
                    search: this.searchQuery,
                    page: this.currentPage,
                    per_page: this.perPage,
                    location: "Validation",
                    include_images: true,
                });

                const response = await axios.get(
                    `${API_BASE_URL}/api/validation/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            location: "Validation",
                            include_images: true,
                        },
                    },
                );

                console.log("API Response:", response.data);
                console.log("First item:", response.data.data[0]);

                // 🔍 CHECK THIS - Does capturedImages exist?
                if (response.data.data[0]) {
                    console.log(
                        "First item capturedImages:",
                        response.data.data[0].capturedImages,
                    );
                }

                this.inventory = response.data.data;
                this.totalPages = response.data.last_page;
            } catch (error) {
                console.error("Error fetching inventory data:", error);
                console.error("Error response:", error.response);
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

        // Add these methods to the methods object in your component
        async moveToLabeling(item) {
            if (!item || !item.ProductID) {
                console.error("Invalid item data for moving to Validation");
                return;
            }

            try {
                this.isProcessing = true;
                // Get the CSRF token from the meta tag
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                // Make the request with proper data format and headers
                const response = await axios.post(
                    `${API_BASE_URL}/api/validation/move-to-validation`,
                    {
                        product_id: item.ProductID,
                        rt_counter: item.rtcounter,
                        current_location: "Validation",
                        new_location: "Labeling",
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
            } finally {
                this.isProcessing = false;
            }
        },

        async moveToStockroom(item) {
            if (!item || !item.ProductID) {
                console.error("Invalid item data for moving to Stockroom");
                return;
            }

            try {
                this.isProcessing = true;
                // Get the CSRF token from the meta tag
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                // Make the request with proper data format and headers
                const response = await axios.post(
                    `${API_BASE_URL}/api/validation/move-to-stockroom`,
                    {
                        product_id: item.ProductID,
                        rt_counter: item.rtcounter,
                        current_location: "Validation",
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
            } finally {
                this.isProcessing = false;
            }
        },

        // Method to show the validation confirmation
        confirmMoveToLabeling(item) {
            this.showConfirmationModal = true;
            this.confirmationTitle = "Move to Labeling";
            this.confirmationMessage = `Are you sure you want to move item #${item.rtcounter} from Validation to Labeling ?`;
            this.confirmationActionType = "labeling";
            this.currentItemForAction = item;

            // Prevent scrolling when modal is open
            document.body.style.overflow = "hidden";
        },

        // Method to show the stockroom confirmation
        confirmMoveToStockroom(item) {
            this.showConfirmationModal = true;
            this.confirmationTitle = "Move to Stockroom";
            this.confirmationMessage = `Are you sure you want to move item #${item.rtcounter} from Validation to Stockroom?`;
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

            if (this.confirmationActionType === "labeling") {
                this.moveToLabeling(this.currentItemForAction);
            } else if (this.confirmationActionType === "stockroom") {
                this.moveToStockroom(this.currentItemForAction);
            }

            // Close the modal
            this.showConfirmationModal = false;
            this.currentItemForAction = null;

            // Re-enable scrolling
            document.body.style.overflow = "auto";
        },

        // Open the validation modal
        async openValidationModal(item) {
            if (!item) return;

            await this.fetchItems();

            const freshItem = this.items.find(
                (i) => i.itemnumber === item.itemnumber,
            );

            this.item = { ...(freshItem || item) };
            this.ASIN = this.item.ASIN || "";

            this.currentValidationItem = this.item;
            this.showValidationModal = true;

            document.body.style.overflow = "hidden";
        },

        // Close the validation modal
        closeValidationModal() {
            this.currentValidationItem = null;
            this.showValidationModal = false;
            document.body.style.overflow = "";
        },

        async fetchItems() {
            this.loading = true;
            try {
                const response = await axios.get("/api/validation/products", {
                    params: {
                        include_images: true,
                    },
                });
                const payload = response.data;

                this.items = Array.isArray(payload)
                    ? payload
                    : payload.data || [];

                console.log("Fetched items:", this.items);
                console.log(
                    "First item capturedImages:",
                    this.items[0]?.capturedImages,
                );
            } catch (err) {
                console.error("Fetch failed:", err);
                this.items = [];
                this.error = "Failed to load items.";
            } finally {
                this.loading = false;
            }
        },

        // Cancel the confirmation
        cancelConfirmation() {
            console.log("Canceling confirmation");
            this.showConfirmationModal = false;
            this.confirmationActionType = "";

            // Don't reset body overflow since we still have the validation modal open
            // The validation modal will handle this when it's closed
        },

        async confirmMarkAsValid() {
            const result = await Swal.fire({
                title: "Are you sure?",
                text: "Do you want to mark this item as VALIDATED?",
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#28a745", // green
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, validate it!",
            });

            if (result.isConfirmed) {
                this.markAsValid();
            }
        },

        async markAsValid() {
            if (!this.currentValidationItem) return;

            try {
                this.isProcessingValidation = true;
                this.showConfirmationModal = false;

                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                const response = await axios.post(
                    `${API_BASE_URL}/api/validation/validate`,
                    {
                        product_id: this.currentValidationItem.ProductID,
                        rt_counter: this.currentValidationItem.rtcounter,
                        status: "validated",
                        notes: this.validationNotes,
                    },
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    },
                );

                console.log("Validation response:", response.data);

                if (response.data.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Validated!",
                        text: `Item ${this.currentValidationItem.rtcounter} has been validated successfully.`,
                        confirmButtonColor: "#3085d6",
                        confirmButtonText: "OK",
                    });

                    this.closeValidationModal();
                    this.fetchInventory();
                } else {
                    this.validationErrors =
                        response.data.message || "Failed to validate item";
                }
            } catch (error) {
                console.error("Error validating item:", error);
                this.validationErrors =
                    "Failed to validate item. Please try again.";
            } finally {
                this.isProcessingValidation = false;
            }
        },

        async confirmMarkAsInvalid() {
            const result = await Swal.fire({
                title: "Mark as Invalid?",
                text: "This will mark the item as INVALID. Proceed?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#dc3545", // red
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Yes, mark as invalid",
            });

            if (result.isConfirmed) {
                this.markAsInvalid();
            }
        },

        async markAsInvalid() {
            if (!this.currentValidationItem) {
                console.warn("markAsInvalid: No currentValidationItem found");
                return;
            }

            try {
                console.log("markAsInvalid: Start");
                this.isProcessingValidation = true;
                this.showConfirmationModal = false;

                // Set location
                this.currentValidationItem.ProductModuleLoc = "Labeling";
                console.log(
                    "markAsInvalid: Set ProductModuleLoc to 'Labeling'",
                );

                // Get CSRF token
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                console.log("markAsInvalid: Sending payload", {
                    product_id: this.currentValidationItem.ProductID,
                    rt_counter: this.currentValidationItem.rtcounter,
                    status: "invalid",
                    notes: this.validationNotes,
                    ProductModuleLoc:
                        this.currentValidationItem.ProductModuleLoc,
                });

                // Send request
                const response = await axios.post(
                    `${API_BASE_URL}/api/validation/validate`,
                    {
                        product_id: this.currentValidationItem.ProductID,
                        rt_counter: this.currentValidationItem.rtcounter,
                        status: "invalid",
                        notes: this.validationNotes,
                        ProductModuleLoc:
                            this.currentValidationItem.ProductModuleLoc,
                    },
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    },
                );

                console.log("markAsInvalid: Server response", response.data);

                if (response.data.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Marked as Invalid",
                        text: `Item ${this.currentValidationItem.rtcounter} has been marked as invalid.`,
                        confirmButtonColor: "#3085d6",
                        confirmButtonText: "OK",
                    });

                    console.log(
                        "markAsInvalid: Success, closing modal and refreshing inventory",
                    );
                    this.closeValidationModal();
                    this.fetchInventory();
                } else {
                    console.warn(
                        "markAsInvalid: Server returned failure",
                        response.data,
                    );
                    this.validationErrors =
                        response.data.message ||
                        "Failed to mark item as invalid";
                }
            } catch (error) {
                console.error("markAsInvalid: Request failed", error);
                this.validationErrors =
                    "Failed to mark item as invalid. Please try again.";
            } finally {
                this.isProcessingValidation = false;
                console.log("markAsInvalid: End");
            }
        },

        onImageErrorMain(event) {
            event.target.src = this.defaultImage;
        },
        onThumbnailError(event, index) {
            event.target.src = this.defaultImage;
        },

        goToHouseage(serial) {
            // Load the Houseage module
            window.loadContent("houseage");

            // Wait for component render, then set search field
            setTimeout(() => {
                const searchInput = document.querySelector("#appsearch input");
                if (searchInput) {
                    searchInput.value = serial; // or decodeURIComponent if needed
                    // Trigger an input event if Vue watches this field
                    searchInput.dispatchEvent(
                        new Event("input", { bubbles: true }),
                    );
                }
            }, 500); // Adjust delay if needed for your load speed

            highlightNavLink(/* optional */);
            closeSidebar();
        },
    },

    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchInventory();
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

        this.currentValidationItem = this.item || null;
    },

    beforeDestroy() {
        // Clean up keyboard event listener
        if (this.handleKeyDown) {
            window.removeEventListener("keydown", this.handleKeyDown);
        }
    },
};
