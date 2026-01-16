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
            currentPage: 1,
            totalPages: 1,
            perPage: 10, // Default rows per page
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
                        .filter(Boolean)
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
                (item) => item.ProductModuleLoc === this.moduleFilter
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

        imageList() {
            const images = [];
            const companyFolder = this.item.company || "Airstaffs";

            // First, check for captured images (capturedimg1-12)
            if (this.item.capturedImages) {
                for (let i = 1; i <= 12; i++) {
                    const fieldName = `capturedimg${i}`;
                    if (
                        this.isValidImage(this.item.capturedImages[fieldName])
                    ) {
                        // Use full path for captured images
                        images.push(
                            `/images/product_images/${companyFolder}/${this.item.capturedImages[fieldName]}`
                        );
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
                .map((key) => this.item[key]);
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
                    const path = `/images/product_images/${companyFolder}/${this.item.capturedImages.serialimg1}`;
                    console.log("📸 Displaying serialimg1:", path);
                    return path;
                }

                if (this.isValidImage(this.item.capturedImages.serialimg2)) {
                    const path = `/images/product_images/${companyFolder}/${this.item.capturedImages.serialimg2}`;
                    console.log("📸 Displaying serialimg2:", path);
                    return path;
                }
            }

            console.log("📸 No serial image found, using default");
            // Fallback to server path or default
            return this.serialImagePath || this.defaultSerialImage;
        },
    },

    methods: {
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
                        transformedData[
                            `img${i}`
                        ] = `/images/product_images/${companyFolder}/${capturedImg}`;
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
                    capturedImagesObj
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
                this.capturedImages.length
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
                    }
                );

                // Process the returned data
                this.inventory = response.data.data;
                this.totalPages = response.data.last_page;
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
                    fnsku.astitle?.toLowerCase().includes(search)
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
                    }
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
                    }
                );

                console.log("Move to Validation response:", response.data);

                if (response.data.success) {
                    // Show success message
                    alert(
                        `Item ${item.rtcounter} successfully moved to Validation`
                    );
                    // Refresh the inventory list
                    this.fetchInventory();
                } else {
                    alert(
                        response.data.message ||
                            "Failed to move item to Validation"
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
                    }
                );

                console.log("Move to Stockroom response:", response.data);

                if (response.data.success) {
                    // Show success message
                    alert(
                        `Item ${item.rtcounter} successfully moved to Stockroom`
                    );
                    // Refresh the inventory list
                    this.fetchInventory();
                } else {
                    alert(
                        response.data.message ||
                            "Failed to move item to Stockroom"
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

        async saveEditModal() {
            this.loading = true;

            // Validate required prefixes if not blank
            const errors = [];

            if (this.item.RPN && !/^RPN\d+$/i.test(this.item.RPN)) {
                errors.push("RPN must start with 'RPN' followed by numbers.");
            }

            if (this.item.PRD && !/^PRD\d+$/i.test(this.item.PRD)) {
                errors.push("PRD must start with 'PRD' followed by numbers.");
            }

            if (this.item.PCN && !/^PCN\d+$/i.test(this.item.PCN)) {
                errors.push("PCN must start with 'PCN' followed by numbers.");
            }

            if (
                this.item.basketnumber &&
                !/^(BKT|SI|ENV)\d+$/i.test(this.item.basketnumber)
            ) {
                errors.push(
                    "Basket/Shelf/Envelope Number must start with 'BKT', 'SI', or 'ENV' followed by numbers."
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

            // Normalize all serial number fields
            [
                "serialnumber",
                "serialnumberb",
                "serialnumberc",
                "serialnumberd",
            ].forEach((field) => {
                if (this.item[field] != null) {
                    this.item[field] =
                        String(this.item[field]).toUpperCase().trim() || null;
                }
            });

            try {
                // If user selected a serial image, upload it first
                if (this.serialImageFile && !this.serialImageUploading) {
                    await this.uploadSerialImage();

                    if (this.serialImageError) {
                        this.loading = false;
                        return;
                    }
                }

                const payload = {
                    ...this.item,
                    _employee_name:
                        this.currentUser?.name ||
                        window.Laravel?.user?.name ||
                        "System",
                    product_id: this.item.ProductID,
                };

                // Include the updated serial image info if available
                if (this.item.capturedImages?.serialimg1) {
                    payload.serial_image = this.item.capturedImages.serialimg1;
                }

                const response = await axios.put(
                    `/api/houseage/products/${this.item.ProductID}`,
                    payload,
                    {
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                            Accept: "application/json",
                            "Content-Type": "application/json",
                        },
                        withCredentials: true,
                    }
                );

                const updated = response.data.product;

                // ✅ Update the item in the items array with fresh data
                const index = this.items.findIndex(
                    (p) => p.ProductID === updated.ProductID
                );

                if (index !== -1) {
                    this.items.splice(index, 1, updated);
                } else {
                    this.items.unshift(updated);
                }

                await Swal.fire({
                    icon: "success",
                    title: "Saved!",
                    html: `
                <p>${
                    response.data.message ||
                    "The houseage product has been updated successfully."
                }</p>
                ${
                    response.data.changes_made
                        ? `<p class="text-muted mt-2"><small>${response.data.changes_made} field(s) changed</small></p>`
                        : ""
                }
                ${
                    this.serialImageFile
                        ? '<p class="text-success mt-2"><small>✓ Serial image uploaded successfully</small></p>'
                        : ""
                }
            `,
                    confirmButtonText: "OK",
                });

                this.closeEditModal();

                // ✅ Refresh inventory to get updated capturedImages
                await this.fetchInventory();
            } catch (error) {
                console.error("Save failed:", {
                    message: error.message,
                    status: error.response?.status,
                    data: error.response?.data,
                });

                let message =
                    "An error occurred while saving. Please check the input or try again later.";
                let title = "Save Failed";

                if (error.response?.status === 422) {
                    const err = error.response.data;

                    // Check for duplicate serial number error
                    if (err?.message?.includes("already assigned")) {
                        title = "Duplicate Serial Number";

                        if (err.duplicate_product) {
                            message = `
                        <div style="text-align: left;">
                            <p><strong>${err.message}</strong></p>
                            <hr>
                            <p><strong>Existing Product Details:</strong></p>
                            <ul style="list-style: none; padding-left: 0;">
                                <li>📦 <strong>RT Counter:</strong> ${
                                    err.duplicate_product.rtcounter || "N/A"
                                }</li>
                                <li>🏷️ <strong>Item Number:</strong> ${
                                    err.duplicate_product.itemnumber || "N/A"
                                }</li>
                                <li>📋 <strong>Title:</strong> ${
                                    err.duplicate_product.ProductTitle || "N/A"
                                }</li>
                                <li>🔢 <strong>Serial:</strong> ${
                                    err.duplicate_product.serialnumber || "N/A"
                                }</li>
                            </ul>
                        </div>
                    `;
                        } else {
                            message = err.message;
                        }
                    } else {
                        // Check serial field errors
                        const serialFields = [
                            "serialnumber",
                            "serialnumberb",
                            "serialnumberc",
                            "serialnumberd",
                        ];

                        for (const field of serialFields) {
                            if (err?.errors?.[field]?.length) {
                                message = err.errors[field].join("\n");
                                break;
                            }
                        }

                        // General error handling
                        if (
                            message ===
                            "An error occurred while saving. Please check the input or try again later."
                        ) {
                            if (
                                typeof err?.message === "string" &&
                                err.message
                            ) {
                                message = err.message;
                            } else if (err?.errors) {
                                message = Object.values(err.errors)
                                    .flat()
                                    .join("\n");
                            }
                        }
                    }
                } else if (error.response?.data?.message) {
                    message = error.response.data.message;
                }

                // Show error with option to view duplicate
                const swalOptions = {
                    icon: "error",
                    title: title,
                    html: message,
                    confirmButtonText: "OK",
                };

                // Add "View Duplicate" button if duplicate exists
                if (
                    error.response?.status === 422 &&
                    error.response?.data?.duplicate_product
                ) {
                    swalOptions.showCancelButton = true;
                    swalOptions.cancelButtonText = "View Duplicate";
                    swalOptions.cancelButtonColor = "#3085d6";
                }

                const result = await Swal.fire(swalOptions);

                // If user clicks "View Duplicate", search for it
                if (
                    result.dismiss === Swal.DismissReason.cancel &&
                    error.response?.data?.duplicate_product
                ) {
                    const duplicateSerial =
                        error.response.data.duplicate_product.serialnumber;

                    this.closeEditModal();

                    setTimeout(() => {
                        const searchInput =
                            document.querySelector("#appsearch input");
                        if (searchInput) {
                            searchInput.value = duplicateSerial;
                            searchInput.dispatchEvent(
                                new Event("input", { bubbles: true })
                            );
                        }
                    }, 500);
                }
            } finally {
                this.loading = false;
            }
        },

        async checkDuplicateSerial(serial) {
            if (!serial) return;

            const token = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content");

            try {
                const response = await fetch(
                    "/api/houseage/check-duplicate-serial",
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                            ...(token ? { "X-CSRF-TOKEN": token } : {}),
                        },
                        body: JSON.stringify({ serial }),
                    }
                );

                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(
                        `HTTP ${response.status}: ${text.slice(0, 200)}`
                    );
                }

                const data = await response.json();

                if (data.duplicate) {
                    Swal.fire({
                        icon: "warning",
                        title: "Duplicate Serial Found",
                        html: `
            <p>This serial already exists.</p>
            <p><b>Product ID:</b> ${data.product_id ?? "N/A"}</p>
            <p><b>Title:</b> ${data.product_title ?? "N/A"}</p>
          `,
                        showCancelButton: true,
                        confirmButtonText: "View Original Item",
                        cancelButtonText: "OK",
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // 1) close the modal
                            if (typeof this.closeEditModal === "function") {
                                this.closeEditModal();
                            } else {
                                // fallback if closeEditModal isn't available
                                this.showEditModal = false;
                            }

                            // 2) after a short delay, set the search box value and trigger input
                            setTimeout(() => {
                                const searchInput =
                                    document.querySelector("#appsearch input");
                                if (searchInput) {
                                    searchInput.value = serial; // use decodeURIComponent(serial) if needed
                                    searchInput.dispatchEvent(
                                        new Event("input", { bubbles: true })
                                    );
                                }
                            }, 500);
                        }
                    });
                }
            } catch (err) {
                console.error("Duplicate check failed:", err);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Something went wrong while checking duplicates.",
                });
            }
        },

        getFirstNonEmptySerial() {
            if (!Array.isArray(this.serialKeys)) return "";
            const i = this.serialKeys.findIndex(
                (k) => (this.item?.[k] ?? "").toString().trim() !== ""
            );
            return i === -1
                ? ""
                : (this.item[this.serialKeys[i]] ?? "").toString().trim();
        },

        async fetchSerialImageIfAny() {
            const serial = this.getFirstNonEmptySerial();
            if (!serial) {
                this.serialImagePath = "";
                return;
            }

            try {
                const { data } = await axios.get("/api/houseage/serial-image", {
                    params: { serial_number: serial },
                });
                this.serialImagePath =
                    data?.exists && data?.url ? data.url : "";
            } catch (e) {
                this.serialImagePath = ""; // fail closed and let default render
            }
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

            // require a serial number (first non-empty serial key)
            const idx = Array.isArray(this.serialKeys)
                ? this.serialKeys.findIndex(
                      (k) => (this.item[k] ?? "").toString().trim() !== ""
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

            Swal.fire({
                title: "Uploading…",
                didOpen: () => Swal.showLoading(),
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
            });

            try {
                const form = new FormData();
                form.append("image", this.serialImageFile);
                form.append("serial_number", serial); // no iteration sent

                if (this.product?.id)
                    form.append("product_id", this.product.id);
                if (this.serialImagePath)
                    form.append("old_path", this.serialImagePath);

                const { data } = await axios.post(
                    "/api/houseage/serial-image",
                    form,
                    { headers: { "Content-Type": "multipart/form-data" } }
                );

                this.serialImagePath = data.url || data.path;

                if (Array.isArray(this.imageList)) {
                    if (data.path) this.imageList.unshift(data.path);
                    else if (data.url) this.imageList.unshift(data.url);
                    this.activeIndex = 0;
                }

                this.serialImageFile = null;
                this.serialImageUrl = "";
                this.$emit("serial-image-updated", this.serialImagePath);

                Swal.close();
                await Swal.fire({
                    icon: "success",
                    title: "Serial image uploaded successfully",
                    // text: `Saved as ${data.filename || "your image"}`,
                    confirmButtonText: "OK",
                });
            } catch (err) {
                this.serialImageError =
                    err?.response?.data?.message || "Upload failed.";
                Swal.close();
                await Swal.fire({
                    icon: "error",
                    title: "Upload failed",
                    text: this.serialImageError,
                });
            } finally {
                this.serialImageUploading = false;
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
            this.fetchInventory();
        },

        item: {
            deep: true,
            handler() {
                this.fetchSerialImageIfAny();
            },
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

    beforeDestroy() {
        // Clean up keyboard event listener
        if (this.handleKeyDown) {
            window.removeEventListener("keydown", this.handleKeyDown);
        }
    },
};
