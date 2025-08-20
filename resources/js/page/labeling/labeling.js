import { eventBus } from "../../components/eventBus";
import "../../../css/modules.css";
import "./labeling.css";
import Swal from "sweetalert2";
// Import the splitting modal component
import splittingModal from "./modals/splitting/splittingModal.vue";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ProductList",
    components: {
        splittingModal,
    },
    data() {
        return {
            inventory: [],
            isProcessing: false,
            loading: true,
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
            activeTab: "regular", // Track which tab is active
            currentImageIndex: 0,
            currentImageSet: [], // The currently displayed image set based on active tab

            // FNSKU Modal properties
            isFnskuModalVisible: false,
            showConfirmationModal: false,
            confirmationTitle: "",
            confirmationMessage: "",
            confirmationActionType: "", // 'validation' or 'stockroom'
            currentItemForAction: null, // Store the item to be processed

            showEditModal: false,
            item: {
                priorityrank: "",
            },
            items: [],
            activeIndex: 0,
            basePath: "/images/thumbnails",
            error: null,
            selectedImage: null,

            fnskuSearch: "",
            fnskuExact: "",
            selectedStore: "",
            selectedGrading: "",
            fnskuList: [],
            filteredFnskuList: [],
            isSearching: false,
            currentItem: null,
            showFilters: false,

            // Split Modal properties - FIXED: Explicitly set to false
            // Split Modal properties - Now only need these two
            showSplitModal: false,
            currentSplitItem: null,
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
                /^serialnumber[a-z]?$/.test(k)
            );
        },
        trackingKeys() {
            return Object.keys(this.item).filter((k) =>
                /^trackingnumber\d*$/.test(k)
            );
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

        productImages() {
            if (!this.currentItem) return [];
            const images = [];
            for (let i = 1; i <= 15; i++) {
                const key = `img${i}`;
                if (this.currentItem[key]) {
                    images.push(`/images/thumbnails/${this.currentItem[key]}`);
                }
            }
            return images;
        },
        mainImage() {
            return this.productImages.length > 0 ? this.productImages[0] : null;
        },

        uniqueStores() {
            return [
                ...new Set(
                    this.fnskuList.map((f) => f.storename).filter(Boolean)
                ),
            ];
        },

        gradingOptions() {
            return [
                { label: "New", value: "New" },
                { label: "Used - Like New", value: "UsedLikeNew" },
                { label: "Used - Very Good", value: "UsedVeryGood" },
                { label: "Used - Good", value: "UsedGood" },
                { label: "Used - Acceptable", value: "UsedAcceptable" },
            ];
        },

        validFnskuList() {
            return this.filteredFnskuList.map((fnsku) => {
                return {
                    ...fnsku,
                    hasBeenUsed: fnsku.Units < 11,
                    timesUsed: 11 - fnsku.Units,
                    nextFnskuToUse: this.getNextFnskuToUse(fnsku),
                };
            });
        },

        qty() {
            return Number(this.item.quantity) || 0;
        },
        price() {
            return Number(this.item.price) || 0;
        },
        discountValue() {
            return Number(this.item.discount) || 0;
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
    },
    methods: {
        /**
         * Calculate what FNSKU will actually be assigned (with prefix if needed)
         */
        getNextFnskuToUse(fnsku) {
            const timesUsed = 11 - fnsku.Units;

            if (timesUsed === 0) {
                return fnsku.FNSKU; // First use - original FNSKU
            } else {
                return `C${timesUsed}${fnsku.FNSKU}`; // Add prefix
            }
        },

        /**
         * Get usage badge class based on usage count - FIXED TYPO
         */
        getUsageBadgeClass(fnsku) {
            const timesUsed = 11 - fnsku.Units;

            if (timesUsed === 0) return "bg-success";
            if (timesUsed <= 5) return "bg-warning"; // ✅ FIXED: timesUsed (was timesUsused)
            return "bg-danger";
        },

        /**
         * Get usage text for display
         */
        getUsageText(fnsku) {
            const timesUsed = 11 - fnsku.Units;

            if (timesUsed === 0) return "First use";
            if (timesUsed === 1) return "Used 1 time";
            return `Used ${timesUsed} times`;
        },
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
            return this.countImages(
                item,
                "capturedimg",
                1,
                12,
                "capturedImages"
            );
        },

        // Count all images (regular + captured)
        countAllImages(item) {
            return (
                this.countRegularImages(item) + this.countCapturedImages(item)
            );
        },

        // Open the image modal and prepare images
        openImageModal(item) {
            if (!item) return;

            this.regularImages = [];
            this.capturedImages = [];
            this.currentImageIndex = 0;
            this.ProductTitle = item.ProductTitle;
            const companyFolder = item.company || "Airstaffs";

            // Load regular images (img1 - img15)
            for (let i = 1; i <= 15; i++) {
                const fieldName = `img${i}`;
                if (this.isValidImage(item[fieldName])) {
                    const path = `/images/thumbnails/${item[fieldName]}`;
                    this.regularImages.push(path);
                }
            }

            // Load captured images (capturedimg1 - capturedimg12)
            if (
                item.capturedImages &&
                typeof item.capturedImages === "object"
            ) {
                for (let i = 1; i <= 12; i++) {
                    const filename = `${item.rtcounter}_img${i}.jpg`;
                    const path = `/images/product_images/${companyFolder}/${filename}`;
                    this.capturedImages.push(path);
                }
            }

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

        // Fetch inventory data from the API
        async fetchInventory() {
            this.loading = true;

            try {
                console.log("Fetching inventory with params:", {
                    search: this.searchQuery,
                    page: this.currentPage,
                    per_page: this.perPage,
                    location: "Labeling",
                    include_images: true,
                });

                const response = await axios.get(
                    `${API_BASE_URL}/api/labeling/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            location: "Labeling",
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

        // Show FNSKU Modal
        async showFnskuModal(item) {
            console.log("=== FNSKU MODAL DEBUG START ===");
            console.log("Opening FNSKU modal for item:", item);

            this.currentItem = item;
            this.isFnskuModalVisible = true;
            this.fnskuSearch = item.ASINviewer || "";
            this.isSearching = true;

            // Reset all filters
            this.fnskuExact = "";
            this.selectedStore = "";
            this.selectedGrading = "";

            try {
                console.log("API_BASE_URL:", API_BASE_URL);
                console.log(
                    "Making request to:",
                    `${API_BASE_URL}/api/fnsku/fnsku-list`
                );

                // FIXED: Use consistent API endpoint and parameters
                const response = await axios.get(
                    `${API_BASE_URL}/api/fnsku/fnsku-list`,
                    {
                        params: {
                            search: this.fnskuSearch || "",
                            store: this.selectedStore || "",
                            grading: this.selectedGrading || "",
                            fnsku: this.fnskuExact || "",
                            limit: 100,
                            exclude_assigned: false, // SET TO FALSE FOR TESTING - this allows you to see used FNSKUs
                        },
                        withCredentials: true,
                    }
                );

                console.log("FNSKU API Response:", response);
                console.log("Response data:", response.data);

                if (response.data && response.data.data) {
                    // Filter out any empty FNSKUs on the frontend as well (extra safety)
                    const validFnskus = response.data.data.filter(
                        (fnsku) =>
                            fnsku.FNSKU &&
                            fnsku.FNSKU.trim() !== "" &&
                            fnsku.FNSKU !== "NULL" &&
                            fnsku.ASIN &&
                            fnsku.ASIN.trim() !== "" &&
                            fnsku.ASIN !== "NULL"
                    );

                    this.fnskuList = validFnskus;
                    this.filteredFnskuList = validFnskus;

                    console.log(
                        "FNSKU List loaded:",
                        this.fnskuList.length,
                        "items"
                    );
                    console.log(
                        "Filtered out empty FNSKUs, remaining:",
                        validFnskus.length
                    );
                    console.log("First few items:", this.fnskuList.slice(0, 3));

                    // Apply initial filtering if there's a search term
                    if (this.fnskuSearch) {
                        this.filterFnskuList();
                    }
                } else {
                    console.error("Invalid response format:", response.data);
                    this.fnskuList = [];
                    this.filteredFnskuList = [];
                }
            } catch (error) {
                console.error("=== FNSKU API ERROR ===");
                console.error("Error object:", error);
                console.error("Error response:", error.response);
                console.error("Error message:", error.message);

                if (error.response) {
                    console.error("Error status:", error.response.status);
                    console.error("Error data:", error.response.data);

                    if (error.response.status === 404) {
                        alert(
                            "FNSKU API endpoint not found. Check your routes."
                        );
                    } else if (error.response.status === 500) {
                        alert(
                            "Server error while loading FNSKUs. Check server logs."
                        );
                    } else {
                        alert(
                            `HTTP ${error.response.status}: ${
                                error.response.data?.message || "Unknown error"
                            }`
                        );
                    }
                } else if (error.request) {
                    alert("Network error - cannot reach server");
                } else {
                    alert("Request configuration error: " + error.message);
                }

                this.fnskuList = [];
                this.filteredFnskuList = [];
            } finally {
                this.isSearching = false;
                console.log("=== FNSKU MODAL DEBUG END ===");
            }
        },

        // UPDATED filterFnskuList method with better filtering
        filterFnskuList() {
            const asinPriority = this.currentItem?.ASINviewer;
            const search = this.fnskuSearch.toLowerCase().trim();
            const fnskuOnly = this.fnskuExact.toLowerCase().trim();
            const selectedStore = this.selectedStore;
            const selectedGrading = this.selectedGrading;

            console.log("Filtering with:", {
                search,
                fnskuOnly,
                selectedStore,
                selectedGrading,
            });
            console.log("Original list length:", this.fnskuList.length);

            this.filteredFnskuList = this.fnskuList.filter((fnsku) => {
                // Skip if FNSKU or ASIN is empty/null
                if (
                    !fnsku.FNSKU ||
                    fnsku.FNSKU.trim() === "" ||
                    fnsku.FNSKU === "NULL"
                ) {
                    return false;
                }

                if (
                    !fnsku.ASIN ||
                    fnsku.ASIN.trim() === "" ||
                    fnsku.ASIN === "NULL"
                ) {
                    return false;
                }

                const matchesGeneral =
                    !search ||
                    fnsku.ASIN?.toLowerCase().includes(search) ||
                    fnsku.astitle?.toLowerCase().includes(search);

                const matchesFnskuOnly =
                    !fnskuOnly ||
                    fnsku.FNSKU?.toLowerCase().includes(fnskuOnly);

                const matchesStore =
                    !selectedStore || fnsku.storename === selectedStore;

                const matchesGrading =
                    !selectedGrading || fnsku.grading === selectedGrading;

                return (
                    matchesGeneral &&
                    matchesFnskuOnly &&
                    matchesStore &&
                    matchesGrading
                );
            });

            console.log("Filtered list length:", this.filteredFnskuList.length);

            // Sort with ASIN priority
            this.filteredFnskuList.sort((a, b) => {
                if (a.ASIN === asinPriority && b.ASIN !== asinPriority)
                    return -1;
                if (a.ASIN !== asinPriority && b.ASIN === asinPriority)
                    return 1;
                return 0;
            });
        },

        // IMPROVED hideFnskuModal to ensure cleanup
        hideFnskuModal() {
            console.log("Hiding FNSKU modal and cleaning up...");

            this.isFnskuModalVisible = false;
            this.currentItem = null;
            this.fnskuList = [];
            this.filteredFnskuList = [];
            this.fnskuSearch = "";
            this.fnskuExact = "";
            this.selectedStore = "";
            this.selectedGrading = "";
            this.isUpdatingFnsku = false;

            // Re-enable body scroll
            document.body.style.overflow = "auto";
        },

        // Select and save the chosen FNSKU
        async selectFnsku(fnsku) {
            console.log("=== FNSKU SELECTION START ===");
            console.log(
                "Selecting FNSKU:",
                fnsku.FNSKU,
                "for product:",
                this.currentItem?.ProductID
            );

            if (!this.currentItem || !this.currentItem.ProductID) {
                console.error("No current item selected for FNSKU assignment");
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "No item selected for FNSKU assignment",
                });
                return;
            }

            // Show confirmation with prefix info
            try {
                // First check what FNSKU will actually be assigned (with prefix)
                const availabilityResponse = await axios.get(
                    `${API_BASE_URL}/api/fnsku/availability`,
                    {
                        params: { fnsku: fnsku.FNSKU },
                        withCredentials: true,
                    }
                );

                let confirmMessage = `Assign FNSKU ${fnsku.FNSKU} to this product?`;

                if (
                    availabilityResponse.data.success &&
                    availabilityResponse.data.fnsku_info
                ) {
                    const info = availabilityResponse.data.fnsku_info;
                    const actualFnsku = info.next_fnsku_to_use;
                    const remainingAfter = info.units_after_use;

                    if (actualFnsku !== fnsku.FNSKU) {
                        confirmMessage =
                            `This FNSKU has been used ${info.times_used} time(s) already.\n` +
                            `The actual FNSKU that will be assigned is: ${actualFnsku}\n` +
                            `${remainingAfter} units will remain after this assignment.\n\n` +
                            `Do you want to proceed?`;
                    } else {
                        confirmMessage =
                            `Assign FNSKU ${fnsku.FNSKU} to this product?\n` +
                            `This is the first use of this FNSKU.\n` +
                            `${remainingAfter} units will remain after this assignment.`;
                    }
                }

                const confirmResult = await Swal.fire({
                    title: "Confirm Assignment",
                    text: confirmMessage,
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonText: "Yes",
                    cancelButtonText: "No",
                });

                if (!confirmResult.isConfirmed) {
                    return;
                }
            } catch (error) {
                console.warn("Could not fetch FNSKU availability info:", error);
                // Continue with basic confirmation
                const confirmResult = await Swal.fire({
                    title: "Confirm Assignment",
                    text: `Assign FNSKU ${fnsku.FNSKU} to this product?`,
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonText: "Yes",
                    cancelButtonText: "No",
                });

                if (!confirmResult.isConfirmed) {
                    return;
                }
            }

            try {
                this.isUpdatingFnsku = true;

                console.log("Sending FNSKU update request...");

                const response = await axios.post(
                    `${API_BASE_URL}/api/fnsku/update-fnsku`,
                    {
                        product_id: this.currentItem.ProductID,
                        fnsku: fnsku.FNSKU, // Send the base FNSKU
                        msku: fnsku.MSKU,
                        asin: fnsku.ASIN,
                        grading: fnsku.grading,
                    },
                    {
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN":
                                document.querySelector(
                                    'meta[name="csrf-token"]'
                                )?.content || "",
                        },
                        withCredentials: true,
                    }
                );

                console.log("=== FNSKU UPDATE RESPONSE ===");
                console.log("Status:", response.status);
                console.log("Response data:", response.data);

                // FIXED: Better response handling
                if (
                    response.status === 200 &&
                    response.data &&
                    response.data.success
                ) {
                    console.log("✅ FNSKU update successful");

                    const details = response.data.details || {};
                    const actualFnskuAssigned =
                        details.actual_fnsku_assigned || fnsku.FNSKU;
                    const remainingUnits = details.remaining_units || "Unknown";

                    // Update current item in UI
                    this.currentItem.FNSKUviewer = actualFnskuAssigned;
                    this.currentItem.FNSKU = actualFnskuAssigned;

                    const itemIndex = this.inventory.findIndex(
                        (item) =>
                            item.ProductID === this.currentItem.ProductID ||
                            item.rtcounter === this.currentItem.rtcounter
                    );

                    if (itemIndex !== -1) {
                        this.inventory[itemIndex].FNSKUviewer =
                            actualFnskuAssigned;
                        this.inventory[itemIndex].FNSKU = actualFnskuAssigned;
                        this.$forceUpdate();
                    }

                    // Success message
                    let successMessage = `✅ FNSKU updated successfully!\n`;
                    successMessage += `Base FNSKU: ${
                        details.new_base_fnsku || fnsku.FNSKU
                    }\n`;

                    if (actualFnskuAssigned !== fnsku.FNSKU) {
                        successMessage += `Actual FNSKU assigned: ${actualFnskuAssigned}\n`;
                        successMessage += `(This FNSKU has a prefix because it's been used before)\n`;
                    }

                    successMessage += `Units remaining: ${remainingUnits}`;
                    await Swal.fire({
                        icon: "success",
                        title: "Success",
                        text: successMessage,
                    });

                    // 🔄 Refresh inventory after success
                    await this.fetchInventory();

                    // Close the modal
                    this.hideFnskuModal();
                } else {
                    // Handle cases where response is not what we expect
                    console.error(
                        "❌ Unexpected response format:",
                        response.data
                    );

                    // Check if it's still a success but different format
                    if (response.status === 200) {
                        console.log(
                            "⚠️ Update might be successful but response format unexpected"
                        );
                        await Swal.fire({
                            icon: "warning",
                            title: "Success",
                            text: "FNSKU might have been updated. Please check the table.",
                        });
                        this.hideFnskuModal();

                        // Try to refresh just this item
                        setTimeout(() => {
                            this.fetchInventory();
                        }, 1000);
                    } else {
                        await Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Failed to update FNSKU: Unexpected response format",
                        });
                    }
                }
            } catch (error) {
                console.error("=== FNSKU UPDATE ERROR ===");
                console.error("Error object:", error);
                console.error("Error response:", error.response);

                // IMPROVED: Better error handling
                if (error.response) {
                    console.error("Response status:", error.response.status);
                    console.error("Response data:", error.response.data);

                    if (error.response.status === 200) {
                        // Sometimes Laravel returns 200 but axios treats it as error
                        console.log(
                            "🤔 Status 200 but treated as error - checking response..."
                        );

                        if (
                            error.response.data &&
                            error.response.data.success
                        ) {
                            // It's actually successful!
                            console.log(
                                "✅ Actually successful! Updating UI..."
                            );

                            const details = error.response.data.details || {};
                            const actualFnskuAssigned =
                                details.actual_fnsku_assigned || fnsku.FNSKU;

                            // Update UI
                            this.currentItem.FNSKUviewer = actualFnskuAssigned;

                            const itemIndex = this.inventory.findIndex(
                                (item) =>
                                    item.ProductID ===
                                    this.currentItem.ProductID
                            );
                            if (itemIndex !== -1) {
                                this.inventory[itemIndex].FNSKUviewer =
                                    actualFnskuAssigned;
                                this.inventory[itemIndex].FNSKU =
                                    actualFnskuAssigned;
                                this.$forceUpdate();
                            }

                            await Swal.fire({
                                icon: "success",
                                title: "Success",
                                text: "FNSKU updated successfully!",
                            });
                            this.hideFnskuModal();
                            return;
                        }
                    }

                    // Handle other error statuses
                    let errorMessage = "Failed to update FNSKU: ";
                    if (error.response.data && error.response.data.message) {
                        errorMessage += error.response.data.message;
                    } else {
                        errorMessage += `HTTP ${error.response.status} error`;
                    }

                    await Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: errorMessage,
                    });
                } else if (error.request) {
                    console.error("Network error:", error.request);
                    await Swal.fire({
                        icon: "error",
                        title: "Network Error",
                        text: "Could not reach server",
                    });
                } else {
                    console.error(
                        "Request configuration error:",
                        error.message
                    );
                    await Swal.fire({
                        icon: "error",
                        title: "Request Error",
                        text: `Request configuration error: ${error.message}`,
                    });
                }
            } finally {
                this.isUpdatingFnsku = false;
                console.log("=== FNSKU SELECTION END ===");
            }
        },

        // ADD method to refresh single item instead of entire table
        async refreshSingleItem(productId) {
            try {
                console.log("Refreshing single item:", productId);

                const response = await axios.get(
                    `${API_BASE_URL}/api/labeling/product/${productId}`,
                    {
                        withCredentials: true,
                    }
                );

                if (response.data && response.data.success) {
                    const updatedItem = response.data.data;

                    // Find and update the item in inventory
                    const itemIndex = this.inventory.findIndex(
                        (item) => item.ProductID === productId
                    );

                    if (itemIndex !== -1) {
                        this.inventory[itemIndex] = {
                            ...this.inventory[itemIndex],
                            ...updatedItem,
                        };
                        this.$forceUpdate();
                        console.log("✅ Single item refreshed successfully");
                    }
                }
            } catch (error) {
                console.error("Error refreshing single item:", error);
                // Fallback to full refresh
                this.fetchInventory();
            }
        },

        /**
         * Enhanced FNSKU list fetching that accounts for prefixed FNSKUs
         */
        async fetchFnskuList() {
            try {
                this.isSearching = true;

                const params = {
                    search: this.fnskuSearch || "",
                    store: this.selectedStore || "",
                    grading: this.selectedGrading || "",
                    fnsku: this.fnskuExact || "",
                    limit: 100,
                    // NEW: Only exclude the current item's specific FNSKU
                    exclude_current_fnsku:
                        this.currentItem?.FNSKUviewer ||
                        this.currentItem?.FNSKU ||
                        "",
                    current_product_id: this.currentItem?.ProductID || "",
                };

                // Remove empty parameters
                Object.keys(params).forEach((key) => {
                    if (
                        params[key] === "" ||
                        params[key] === null ||
                        params[key] === undefined
                    ) {
                        delete params[key];
                    }
                });

                console.log("Fetching FNSKU list with params:", params);

                const response = await axios.get(
                    `${API_BASE_URL}/api/fnsku/fnsku-list`,
                    {
                        params: params,
                        withCredentials: true,
                    }
                );

                if (response.data && response.data.data) {
                    // Filter out any empty FNSKUs on the frontend
                    const validFnskus = response.data.data.filter(
                        (fnsku) =>
                            fnsku.FNSKU &&
                            fnsku.FNSKU.trim() !== "" &&
                            fnsku.FNSKU !== "NULL" &&
                            fnsku.ASIN &&
                            fnsku.ASIN.trim() !== "" &&
                            fnsku.ASIN !== "NULL"
                    );

                    this.fnskuList = validFnskus;
                    this.filteredFnskuList = validFnskus;

                    console.log(
                        "FNSKU List loaded:",
                        this.fnskuList.length,
                        "items"
                    );
                    console.log(
                        "Excluded current item's FNSKU:",
                        params.exclude_current_fnsku
                    );

                    // Apply initial filtering if there's a search term
                    if (
                        this.fnskuSearch ||
                        this.selectedStore ||
                        this.selectedGrading ||
                        this.fnskuExact
                    ) {
                        this.filterFnskuList();
                    }
                } else {
                    console.error("Invalid response format:", response.data);
                    this.fnskuList = [];
                    this.filteredFnskuList = [];
                }
            } catch (error) {
                console.error("Error fetching FNSKU list:", error);
                this.fnskuList = [];
                this.filteredFnskuList = [];

                if (error.response && error.response.status === 500) {
                    alert(
                        "Server error while loading FNSKUs. Please try again later."
                    );
                }
            } finally {
                this.isSearching = false;
            }
        },

        async showFnskuAvailabilityInfo(fnsku) {
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/fnsku/availability`,
                    {
                        params: { fnsku: fnsku.FNSKU },
                        withCredentials: true,
                    }
                );

                if (response.data.success && response.data.fnsku_info) {
                    const info = response.data.fnsku_info;

                    let message = `FNSKU: ${info.base_fnsku}\n`;
                    message += `Times used: ${info.times_used}\n`;
                    message += `Current units: ${info.remaining_units}\n`;
                    message += `Next FNSKU to use: ${info.next_fnsku_to_use}\n`;
                    message += `Units after use: ${info.units_after_use}\n`;
                    message += `ASIN: ${info.asin}\n`;
                    message += `Condition: ${info.grading}\n`;
                    message += `Store: ${info.storename}`;

                    alert(message);
                } else {
                    alert("FNSKU availability information not available");
                }
            } catch (error) {
                console.error("Error fetching FNSKU availability:", error);
                alert("Error fetching FNSKU availability information");
            }
        },

        /**
         * Add a method to display FNSKU prefix information in the UI
         */
        displayFnskuInfo(fnsku) {
            // You can modify your FNSKU table to show additional info
            // This would be useful to display in the FNSKU selection modal

            // Check if this FNSKU has been used (has remaining units < 11)
            const hasBeenUsed = fnsku.Units < 11;
            const timesUsed = 11 - fnsku.Units;

            return {
                ...fnsku,
                hasBeenUsed,
                timesUsed,
                nextFnskuWillBe:
                    timesUsed === 0
                        ? fnsku.FNSKU
                        : `C${timesUsed}${fnsku.FNSKU}`,
                usageInfo: hasBeenUsed
                    ? `Used ${timesUsed} times`
                    : "First use",
            };
        },

        /**
         * Method to validate FNSKU before assignment
         */
        async validateFnskuBeforeAssignment(fnsku) {
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/fnsku/availability`,
                    {
                        params: { fnsku: fnsku.FNSKU },
                        withCredentials: true,
                    }
                );

                return response.data.success && response.data.available;
            } catch (error) {
                console.error("Error validating FNSKU:", error);
                return false;
            }
        },

        async confirmMoveToValidation(item) {
            if (!item || !item.ProductID) {
                await Swal.fire({
                    icon: "error",
                    title: "Invalid Item",
                    text: "The selected item does not have a valid Product ID.",
                });
                return;
            }

            const result = await Swal.fire({
                title: "Confirm Move to Validation",
                html: `
            <p>Are you sure you want to move 
            <strong>${item.ProductTitle || "—"}</strong>
            to <strong>Validation</strong>?</p>
            <ul style="text-align:left">
                <li><strong>RT Counter:</strong> ${item.rtcounter || "N/A"}</li>
                <li><strong>ASIN:</strong> ${item.ASIN || "—"}</li>
                <li><strong>FNSKU:</strong> ${item.FNSKU || "—"}</li>
                <li><strong>Serial:</strong> ${item.serialnumber || "—"}</li>
            </ul>
        `,
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Yes, move it",
                cancelButtonText: "Cancel",
                reverseButtons: true,
            });

            if (result.isConfirmed) {
                this.moveToValidation(item);
            }
        },

        async moveToValidation(item) {
            if (!item || !item.ProductID) {
                await Swal.fire({
                    icon: "error",
                    title: "Missing Product ID",
                    text: "ProductID is required to move this item to Validation.",
                });
                return;
            }

            const idFields = {
                ASIN: item?.ASIN,
                FNSKU: item?.FNSKU,
                "Serial Number": item?.serialnumber,
                "Basket Number": item?.BasketNumber,
                RPN: item?.RPN,
                PRD: item?.PRD,
                PCN: item?.PCN,
            };

            const isFilled = (v) =>
                v !== undefined && v !== null && String(v).trim() !== "";

            const allKeys = Object.keys(idFields);
            const filledFields = allKeys.filter((k) => isFilled(idFields[k]));
            const missingFields = allKeys.filter((k) => !isFilled(idFields[k]));

            if (filledFields.length === 0) {
                await Swal.fire({
                    icon: "error",
                    title: "Missing Required Fields",
                    html: `<p>Please provide at least one identification field.</p>
                   <ul style="text-align:left;margin:0;padding-left:1.2rem;">
                     ${missingFields.map((f) => `<li>${f}</li>`).join("")}
                   </ul>`,
                });
                return;
            }

            if (missingFields.length > 0) {
                await Swal.fire({
                    icon: "error", // use "error" instead of "info"
                    title: "Some Identification Fields Are Missing",
                    html: `<p>Please fill in all required identification fields before proceeding.</p>
                   <ul style="text-align:left;margin:0;padding-left:1.2rem;">
                     ${missingFields.map((f) => `<li>${f}</li>`).join("")}
                   </ul>`,
                    confirmButtonText: "OK",
                });
                return;
            }

            try {
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                Swal.fire({
                    title: "Moving to Validation...",
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                });

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

                Swal.close();

                if (response.data.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Success",
                        text: `Item ${item.rtcounter} successfully moved to Validation.`,
                        confirmButtonText: "OK",
                    });
                    if (this && typeof this.fetchInventory === "function") {
                        this.fetchInventory();
                    }
                } else {
                    await Swal.fire({
                        icon: "warning",
                        title: "Failed",
                        text:
                            response.data.message ||
                            "Failed to move item to Validation.",
                    });
                    return;
                }
            } catch (error) {
                console.error("Error moving item to Validation:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "An error occurred while moving the item. Please try again.",
                });
                return;
            }
        },

        async confirmMoveToStockroom(item) {
            if (!item || !item.ProductID) {
                await Swal.fire({
                    icon: "error",
                    title: "Invalid Item",
                    text: "The selected item does not have a valid Product ID.",
                });
                return;
            }

            const result = await Swal.fire({
                title: "Confirm Move to Stockroom",
                html: `
            <p>Are you sure you want to move 
            <strong>${item.ProductTitle || "—"}</strong>
            to the <strong>Stockroom</strong>?</p>
            <ul style="text-align:left">
                <li><strong>RT Counter:</strong> ${item.rtcounter || "N/A"}</li>
                <li><strong>ASIN:</strong> ${item.ASIN || "—"}</li>
                <li><strong>FNSKU:</strong> ${item.FNSKU || "—"}</li>
                <li><strong>Serial:</strong> ${item.serialnumber || "—"}</li>
            </ul>
        `,
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Yes, move it",
                cancelButtonText: "Cancel",
                reverseButtons: true,
            });

            if (result.isConfirmed) {
                this.moveToStockroom(item);
            }
        },

        async moveToStockroom(item) {
            if (!item || !item.ProductID) {
                await Swal.fire({
                    icon: "error",
                    title: "Invalid Item",
                    text: "The selected item does not have a valid Product ID.",
                });
                return;
            }

            // --- Identification fields check (only these four) ---
            const idFields = {
                "RT Counter": item?.rtcounter,
                ASIN: item?.ASIN,
                FNSKU: item?.FNSKU,
                "Serial Number": item?.serialnumber,
            };

            const isFilled = (v) =>
                v !== undefined && v !== null && String(v).trim() !== "";

            const missingFields = Object.keys(idFields).filter(
                (k) => !isFilled(idFields[k])
            );

            // ❌ Stop if any are missing
            if (missingFields.length > 0) {
                await Swal.fire({
                    icon: "error",
                    title: "Missing Required Fields",
                    html: `<p>Please fill in all required fields before proceeding:</p>
                   <ul style="text-align:left;margin:0;padding-left:1.2rem;">
                     ${missingFields.map((f) => `<li>${f}</li>`).join("")}
                   </ul>`,
                });
                return; // stop execution
            }

            try {
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                Swal.fire({
                    title: "Moving to Stockroom...",
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                });

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

                Swal.close();

                if (response.data.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Success",
                        text: `Item ${item.rtcounter} successfully moved to Stockroom.`,
                        confirmButtonText: "OK",
                    });
                    if (this && typeof this.fetchInventory === "function") {
                        this.fetchInventory();
                    }
                } else {
                    await Swal.fire({
                        icon: "warning",
                        title: "Failed",
                        text:
                            response.data.message ||
                            "Failed to move item to Stockroom.",
                    });
                }
            } catch (error) {
                console.error("Error moving item to Stockroom:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "An error occurred while moving the item to Stockroom. Please try again.",
                });
            }
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

            const freshItem = this.items.find(
                (i) => i.itemnumber === item.itemnumber
            );
            this.item = { ...(freshItem || item) };

            console.log(this.item);

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
                !/^BKT\d+$/.test(this.item.basketnumber)
            )
                errors.push(
                    "Basket Number must start with 'BKT' followed by numbers."
                );

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
                    "POST payload:",
                    JSON.parse(JSON.stringify(payload))
                );

                const response = await axios.post(
                    "/api/labeling/products",
                    payload
                );
                const updated = response.data.product;

                const index = this.items.findIndex(
                    (p) => p.ProductID === updated.ProductID
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

        getImageSrc(asin, index) {
            return `/images/asinimg/${asin}_${index}.png`;
        },
        setDefaultImage(event) {
            event.target.src = this.defaultImage;
        },

        getGradingLabel(grading) {
            const gradingMap = {
                New: "New",
                UsedLikeNew: "Used - Like New",
                UsedVeryGood: "Used - Very Good",
                UsedGood: "Used - Good",
                UsedAcceptable: "Used - Acceptable",
            };
            return gradingMap[grading] || grading;
        },

        canSplit(item) {
            console.log("🔍 canSplit called for item:", {
                ProductID: item.ProductID,
                ProductTitle: item.ProductTitle,
                quantity: item.quantity,
                price: item.price,
                priceshipping: item.priceshipping,
                tax: item.tax, // ✅ ADDED: tax field
            });

            const quantity = parseInt(item.quantity) || 0;
            console.log("🔍 Quantity parsed:", quantity);

            const result = quantity > 1;
            console.log("🔍 Can split result:", result);

            return result;
        },

        /**
         * Show split confirmation dialog and open modal
         */
        confirmSplitItem(item) {
            console.log("🚀 SPLIT BUTTON CLICKED!");
            console.log("🔍 Item received:", {
                ProductID: item.ProductID,
                ProductTitle: item.ProductTitle,
                quantity: item.quantity,
                price: item.price,
                priceshipping: item.priceshipping,
                tax: item.tax, // ✅ ADDED: tax field
            });

            // Check quantity first
            const canSplitResult = this.canSplit(item);
            console.log("🔍 canSplit result:", canSplitResult);

            if (!canSplitResult) {
                console.log("❌ Cannot split - quantity too low");
                Swal.fire({
                    icon: "warning",
                    title: "Cannot Split",
                    text: "This item cannot be split because the quantity is 1 or less.",
                });
                return;
            }

            // Check if we have a valid price from ALL THREE fields
            const totalPrice = this.getTotalPrice(item);
            console.log("🔍 Total price found from all fields:", totalPrice);

            // If no price, show confirmation but allow to proceed
            if (totalPrice <= 0) {
                console.log("⚠️ No price found - showing confirmation");
                Swal.fire({
                    icon: "warning",
                    title: "No Price Set",
                    html: `
                <p>This item has no price, shipping price, or tax set.</p>
                <p><strong>Do you still want to proceed with the split?</strong></p>
                <p class="text-muted small">Each split item will have $0.00 in all price fields.</p>
            `,
                    showCancelButton: true,
                    confirmButtonText: "Yes, Split Anyway",
                    cancelButtonText: "Cancel",
                    reverseButtons: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.openSplitModal(item);
                    }
                });
                return;
            }

            // Normal case with valid price
            this.openSplitModal(item);
        },

        /**
         * Open the split modal
         */
        openSplitModal(item) {
            console.log("✅ Opening split modal");

            // Set the modal data
            this.currentSplitItem = { ...item };
            console.log("🔍 currentSplitItem set to:", {
                ProductID: this.currentSplitItem.ProductID,
                quantity: this.currentSplitItem.quantity,
            });

            // Show the modal
            this.showSplitModal = true;
            console.log(
                "🔍 After setting: showSplitModal =",
                this.showSplitModal
            );
        },

        /**
         * Close the split modal
         */
        closeSplitModal() {
            console.log("🚀 CLOSE SPLIT MODAL CALLED");

            this.showSplitModal = false;
            this.currentSplitItem = null;

            console.log(
                "🔍 After close: showSplitModal =",
                this.showSplitModal
            );
        },

        /**
         * Handle successful split - refresh inventory
         */
        async onSplitSuccess() {
            console.log(
                "🔍 Split success event received - refreshing inventory"
            );
            await this.fetchInventory();
        },

        /**
         * Helper method to get COMBINED total price from both fields
         */
        getTotalPrice(item) {
            if (!item) return 0;

            const price = parseFloat(item.price) || 0;
            const priceshipping = parseFloat(item.priceshipping) || 0;
            const tax = parseFloat(item.tax) || 0; // ✅ This should be included
            const total = price + priceshipping + tax;

            console.log("🔍 getTotalPrice - Combined from all three fields:", {
                price: price,
                priceshipping: priceshipping,
                tax: tax, // ✅ This should be included
                total: total,
            });

            return total;
        },
    },

    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchInventory();
        },

        currentItem() {
            this.selectedImage = this.mainImage;
        },

        // Add debug watcher for split modal
        showSplitModal(newVal, oldVal) {
            console.log("🔍 showSplitModal changed:", oldVal, "->", newVal);
            if (newVal && !this.currentSplitItem) {
                console.log("⚠️ Modal shown but no currentSplitItem!");
            }
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

        // Debug: Log initial state
        console.log(
            "🔍 Component mounted. showSplitModal initial state:",
            this.showSplitModal
        );
    },

    beforeDestroy() {
        // Clean up keyboard event listener
        if (this.handleKeyDown) {
            window.removeEventListener("keydown", this.handleKeyDown);
        }
    },
};
