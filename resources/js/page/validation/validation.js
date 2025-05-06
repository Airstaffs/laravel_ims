import { eventBus } from "../../components/eventBus";
import "../../../css/modules.css";
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

        // Count additional images based on the image fields (img2-img15)
        countRegularImages(item) {
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

        countCapturedImages(item) {
            if (!item || !item.capturedImages) return 0;

            // For debugging
            console.log("Checking capturedImages:", item.capturedImages);

            let count = 0;
            // Check capturedimg1 through capturedimg12
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

        // Count all images (regular + captured + ASIN)
        countAllImages(item) {
            return (
                this.countRegularImages(item) +
                this.countCapturedImages(item) +
                (item.asin ? 1 : 0)
            );
        },

        // Open image modal with all available images in separate categories
        async openImageModal(item) {
            if (!item) return;

            console.log("Opening modal for item:", item);

            // Reset modal state
            this.regularImages = [];
            this.capturedImages = [];
            this.asinImages = [];
            this.currentImageIndex = 0;

            // First collect regular images (img1-img15)
            if (item.img1 && item.img1 !== "NULL" && item.img1.trim() !== "") {
                const mainImagePath = `/images/thumbnails/${item.img1}`;
                this.regularImages.push(mainImagePath);
                console.log("Added main image:", mainImagePath);
            }

            // Add regular additional images
            for (let i = 2; i <= 15; i++) {
                const fieldName = `img${i}`;
                if (
                    item[fieldName] &&
                    item[fieldName] !== "NULL" &&
                    item[fieldName].trim() !== ""
                ) {
                    const imagePath = `/images/thumbnails/${item[fieldName]}`;
                    this.regularImages.push(imagePath);
                    console.log("Added additional image:", imagePath);
                }
            }

            // Get company folder for captured image paths
            const companyFolder = item.company || "Airstaffs";

            // Then collect captured images if available
            if (item.capturedImages) {
                console.log(
                    "Processing captured images data:",
                    item.capturedImages
                );

                // Check if capturedImages is empty or not a proper object
                const hasCapturedImages =
                    typeof item.capturedImages === "object" &&
                    Object.keys(item.capturedImages).length > 0;

                if (hasCapturedImages) {
                    for (let i = 1; i <= 12; i++) {
                        const fieldName = `capturedimg${i}`;
                        if (
                            item.capturedImages[fieldName] &&
                            item.capturedImages[fieldName] !== "NULL" &&
                            item.capturedImages[fieldName].trim() !== ""
                        ) {
                            // Use the exact path based on your server structure
                            const imagePath = `/images/product_images/${companyFolder}/${item.capturedImages[fieldName]}`;
                            console.log(
                                `Adding captured image path: ${imagePath}`
                            );
                            this.capturedImages.push(imagePath);
                        }
                    }
                } else {
                    console.log(
                        "Captured images object exists but is empty or invalid"
                    );
                }
            } else {
                console.log("No captured images data found for item:", item);
            }

            // If no images were found in any category, add a default image to regularImages
            if (
                this.regularImages.length === 0 &&
                this.capturedImages.length === 0 &&
                this.asinImages.length === 0
            ) {
                const defaultPath = this.defaultImage;
                this.regularImages.push(defaultPath);
                console.log("No images found, using default:", defaultPath);
            }

            // Set initial tab based on which images are available
            if (this.regularImages.length > 0) {
                this.activeTab = "regular";
                this.currentImageSet = this.regularImages;
            } else if (this.capturedImages.length > 0) {
                this.activeTab = "captured";
                this.currentImageSet = this.capturedImages;
            } else if (this.asinImages.length > 0) {
                this.activeTab = "asin";
                this.currentImageSet = this.asinImages;
            }

            // Show the modal
            this.showImageModal = true;

            // Prevent scrolling when modal is open
            document.body.style.overflow = "hidden";
        },

        // Method to switch tabs
        switchTab(tab) {
            this.activeTab = tab;
            this.currentImageIndex = 0;

            if (tab === "regular") {
                this.currentImageSet = this.regularImages;
            } else if (tab === "captured") {
                this.currentImageSet = this.capturedImages;
            } else if (tab === "asin") {
                this.currentImageSet = this.asinImages;
            }
        },

        // Method to switch tabs in validation modal
        switchValidationTab(tab) {
            this.validationActiveTab = tab;

            // If switching to ASIN tab, load ASIN images if not loaded already
            if (
                tab === "asin" &&
                !this.currentValidationItemAsinLoaded &&
                this.currentValidationItem
            ) {
                this.loadAsinImagesForValidation();
            }
        },

        // Load ASIN images for validation modal
        async loadAsinImagesForValidation() {
            if (
                !this.currentValidationItem ||
                !this.currentValidationItem.FNSKUviewer
            )
                return;

            this.currentValidationItemAsinLoaded = false;
            this.currentValidationItemAsinImages = [];

            try {
                // Add the ASIN image
                const asinImagePath = `/images/asinimg/${this.currentValidationItem.asin}.png`;
                this.currentValidationItemAsinImages.push(asinImagePath);
            } catch (error) {
                console.error(
                    "Error loading ASIN images for validation:",
                    error
                );
            } finally {
                this.currentValidationItemAsinLoaded = true;
            }
        },

        closeImageModal() {
            this.showImageModal = false;
            this.currentImageSet = [];
            this.regularImages = [];
            this.capturedImages = [];
            this.asinImages = [];

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

        // Fetch inventory data from the API
        async fetchInventory() {
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
                    }
                );

                console.log("API Response:", response.data);

                // Process the returned data
                this.inventory = response.data.data;
                this.totalPages = response.data.last_page;

                // Debug first item to see structure
                if (this.inventory.length > 0) {
                    console.log("First item structure:", this.inventory[0]);
                    if (this.inventory[0].capturedImages) {
                        console.log(
                            "First item capturedImages:",
                            this.inventory[0].capturedImages
                        );
                    }
                }
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
            this.currentValidationItem = item;
            this.validationNotes = "";
            this.validationErrors = null;
            this.validationActiveTab = "product";
            this.currentValidationItemAsinLoaded = false;
            this.currentValidationItemAsinImages = [];
            this.showValidationModal = true;

            // Prevent scrolling when modal is open
            document.body.style.overflow = "hidden";
        },

        // Close the validation modal
        closeValidationModal() {
            this.showValidationModal = false;
            this.currentValidationItem = null;
            this.validationNotes = "";
            this.validationErrors = null;
            this.currentValidationItemAsinImages = [];
            this.currentValidationItemAsinLoaded = false;

            // Re-enable scrolling
            document.body.style.overflow = "auto";
        },

        // Open confirm dialog for valid
        confirmMarkAsValid() {
            if (!this.currentValidationItem) return;

            this.showConfirmationModal = true;
            this.confirmationTitle = "Confirm Validation";
            this.confirmationMessage = `Are you sure you want to mark item #${this.currentValidationItem.rtcounter} as VALID?`;
            this.confirmationActionType = "valid";

            // Prevent scrolling when confirmation modal is open
            document.body.style.overflow = "hidden";
        },

        // Open confirm dialog for invalid
        confirmMarkAsInvalid() {
            if (!this.currentValidationItem) return;

            // Check if notes are provided for invalid items
            /*if (!this.validationNotes.trim()) {
        this.validationErrors = 'Please provide notes explaining why this item is invalid';
        return;
      }*/

            this.showConfirmationModal = true;
            this.confirmationTitle = "Confirm Invalidation";
            this.confirmationMessage = `Are you sure you want to mark item #${this.currentValidationItem.rtcounter} as INVALID?`;
            this.confirmationActionType = "invalid";

            // Prevent scrolling when confirmation modal is open
            document.body.style.overflow = "hidden";
        },

        // Cancel the confirmation
        cancelConfirmation() {
            console.log("Canceling confirmation");
            this.showConfirmationModal = false;
            this.confirmationActionType = "";

            // Don't reset body overflow since we still have the validation modal open
            // The validation modal will handle this when it's closed
        },

        // Mark item as valid after confirmation
        async markAsValid() {
            if (!this.currentValidationItem) return;

            try {
                this.isProcessingValidation = true;
                this.showConfirmationModal = false; // Close confirmation dialog

                // Get the CSRF token from the meta tag
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                // Make the API request to validate the item
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
                    }
                );

                console.log("Validation response:", response.data);

                if (response.data.success) {
                    // Show success message
                    alert(
                        `Item ${this.currentValidationItem.rtcounter} has been validated successfully`
                    );
                    // Close the modal
                    this.closeValidationModal();
                    // Refresh the inventory list
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

        // Mark item as invalid after confirmation
        async markAsInvalid() {
            if (!this.currentValidationItem) return;

            try {
                this.isProcessingValidation = true;
                this.showConfirmationModal = false; // Close confirmation dialog

                // Get the CSRF token from the meta tag
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                // Make the API request to mark the item as invalid
                const response = await axios.post(
                    `${API_BASE_URL}/api/validation/validate`,
                    {
                        product_id: this.currentValidationItem.ProductID,
                        rt_counter: this.currentValidationItem.rtcounter,
                        status: "invalid",
                        notes: this.validationNotes,
                    },
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    }
                );

                console.log("Invalidation response:", response.data);

                if (response.data.success) {
                    // Show success message
                    alert(
                        `Item ${this.currentValidationItem.rtcounter} has been marked as invalid`
                    );
                    // Close the modal
                    this.closeValidationModal();
                    // Refresh the inventory list
                    this.fetchInventory();
                } else {
                    this.validationErrors =
                        response.data.message ||
                        "Failed to mark item as invalid";
                }
            } catch (error) {
                console.error("Error marking item as invalid:", error);
                this.validationErrors =
                    "Failed to mark item as invalid. Please try again.";
            } finally {
                this.isProcessingValidation = false;
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
    },

    beforeDestroy() {
        // Clean up keyboard event listener
        if (this.handleKeyDown) {
            window.removeEventListener("keydown", this.handleKeyDown);
        }
    },
};
