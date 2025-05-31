import { eventBus } from "../../components/eventBus";
import "../../../css/modules.css";
import "./labeling.css";
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
            isProcessing: false, // Add this missing property
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
            currentItem: null,
            fnskuList: [],
            filteredFnskuList: [],
            fnskuSearch: "",
            isSearching: false, // NEW: Add loading state for search

            showConfirmationModal: false,
            confirmationTitle: "",
            confirmationMessage: "",
            confirmationActionType: "", // 'validation' or 'stockroom'
            currentItemForAction: null, // Store the item to be processed
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
    },
    methods: {
        handleImageError(event) {
            // If image fails to load, use an inline SVG placeholder
            event.target.src = this.defaultImage;
            event.target.onerror = null; // Prevent infinite error loop
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

        // Count all images (regular + captured)
        countAllImages(item) {
            return (
                this.countRegularImages(item) + this.countCapturedImages(item)
            );
        },

        // Open image modal with all available images in separate categories
        openImageModal(item) {
            if (!item) return;

            console.log("Opening modal for item:", item);

            // Reset modal state
            this.regularImages = [];
            this.capturedImages = [];
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

            // If no images were found in either category, add a default image to regularImages
            if (
                this.regularImages.length === 0 &&
                this.capturedImages.length === 0
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

        // FNSKU Modal methods - Updated with server-side search and loading animation
        async showFnskuModal(item) {
            console.log("Opening FNSKU modal for item:", item);
            this.currentItem = item;
            this.isFnskuModalVisible = true;
            this.fnskuSearch = item.ASINviewer || ""; // Pre-fill with current ASIN for easier search
            this.isSearching = true; // Start loading

            try {
                console.log("Fetching FNSKU list...");
                // Use the FNSKU endpoint with limit parameter and exclude assigned FNSKUs
                const response = await axios.get(`${API_BASE_URL}/fnsku-list`, {
                    params: {
                        limit: 100, // Limit to 100 records initially
                        search: this.fnskuSearch, // Send initial search if ASIN is pre-filled
                        exclude_assigned: true // NEW: Exclude already assigned FNSKUs
                    }
                });
                console.log("FNSKU list response:", response.data);
                
                // Handle new response structure
                if (response.data.data) {
                    this.fnskuList = response.data.data;
                } else {
                    // Fallback for old response structure
                    this.fnskuList = response.data;
                }
                
                this.filterFnskuList(); // Apply initial filter
            } catch (error) {
                console.error("Error fetching FNSKU list:", error);
                alert("Error fetching FNSKU list. Please try again.");
            } finally {
                this.isSearching = false; // Stop loading
            }
        },

        // Updated filterFnskuList method with server-side search and loading animation
        async filterFnskuList() {
            console.log("Filtering FNSKU list with search:", this.fnskuSearch);
            
            // If search is empty, show current list sorted by matching ASIN
            if (!this.fnskuSearch || this.fnskuSearch.trim() === '') {
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

            // For search terms longer than 2 characters, use server-side search
            if (this.fnskuSearch.trim().length > 2) {
                this.isSearching = true; // Start loading for search
                
                try {
                    const response = await axios.get(`${API_BASE_URL}/fnsku-list`, {
                        params: {
                            limit: 100,
                            search: this.fnskuSearch.trim(),
                            exclude_assigned: true // NEW: Always exclude assigned FNSKUs
                        }
                    });
                    
                    if (response.data.data) {
                        this.filteredFnskuList = response.data.data;
                    } else {
                        this.filteredFnskuList = response.data;
                    }
                } catch (error) {
                    console.error("Error searching FNSKU list:", error);
                    // Fallback to client-side filtering
                    this.clientSideFilter();
                } finally {
                    this.isSearching = false; // Stop loading
                }
            } else {
                // For short search terms, use client-side filtering
                this.clientSideFilter();
            }
        },

        // Updated hideFnskuModal to reset loading state
        hideFnskuModal() {
            console.log("Hiding FNSKU modal");
            this.isFnskuModalVisible = false;
            this.currentItem = null;
            this.fnskuList = [];
            this.filteredFnskuList = [];
            this.fnskuSearch = "";
            this.isSearching = false; // Reset loading state
        },

        // Helper method for client-side filtering
        clientSideFilter() {
            const search = this.fnskuSearch.toLowerCase();
            this.filteredFnskuList = this.fnskuList.filter(
                (fnsku) =>
                    fnsku.FNSKU?.toLowerCase().includes(search) ||
                    fnsku.ASIN?.toLowerCase().includes(search) ||
                    fnsku.astitle?.toLowerCase().includes(search) ||
                    fnsku.grading?.toLowerCase().includes(search)
            );
        },

        // Updated selectFnsku method with better validation
        async selectFnsku(fnsku) {
            console.log("Selecting FNSKU - Full object:", fnsku);
            
            // Debug: Log each property individually
            console.log("FNSKU properties:", {
                FNSKU: fnsku.FNSKU,
                MSKU: fnsku.MSKU, 
                ASIN: fnsku.ASIN,
                grading: fnsku.grading
            });
            
            if (!this.currentItem || !fnsku) {
                console.error("Missing currentItem or fnsku object");
                return;
            }

            // Check for required fields and provide meaningful error messages
            const requiredFields = ['FNSKU', 'MSKU', 'grading'];
            const missingFields = [];
            
            requiredFields.forEach(field => {
                if (!fnsku[field] || fnsku[field] === null || fnsku[field] === undefined || fnsku[field].toString().trim() === '') {
                    missingFields.push(field);
                }
            });
            
            if (missingFields.length > 0) {
                console.error("Missing required fields:", missingFields);
                console.error("FNSKU object:", fnsku);
                alert(`Cannot select this FNSKU. Missing required fields: ${missingFields.join(', ')}`);
                return;
            }

            try {
                // Get the CSRF token from the meta tag
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                // Prepare the payload with clean data
                const payload = {
                    product_id: this.currentItem.ProductID,
                    fnsku: fnsku.FNSKU.toString().trim(),
                    msku: fnsku.MSKU.toString().trim(),
                    asin: (fnsku.ASIN || '').toString().trim(),
                    grading: fnsku.grading.toString().trim()
                };

                console.log("Sending payload:", payload);

                // Make the request with proper data format and headers
                const response = await axios.post(
                    `${API_BASE_URL}/update-fnsku`,
                    payload,
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                            "Content-Type": "application/json",
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
                
                // More detailed error handling
                if (error.response && error.response.data) {
                    console.error("Server response:", error.response.data);
                    if (error.response.data.errors) {
                        const errorMessages = Object.values(error.response.data.errors).flat();
                        alert("Validation errors:\n" + errorMessages.join("\n"));
                    } else {
                        alert(error.response.data.message || "Failed to update FNSKU. Please try again.");
                    }
                } else {
                    alert("Failed to update FNSKU. Please try again.");
                }
            }
        },

        // Debug method for FNSKU data
        debugFnskuData() {
            console.log("=== FNSKU Data Debug ===");
            console.log("Total FNSKU records:", this.fnskuList.length);
            
            // Check first few records
            this.fnskuList.slice(0, 5).forEach((fnsku, index) => {
                console.log(`FNSKU ${index + 1}:`, {
                    FNSKU: fnsku.FNSKU,
                    MSKU: fnsku.MSKU,
                    ASIN: fnsku.ASIN,
                    grading: fnsku.grading,
                    Units: fnsku.Units
                });
            });
            
            // Find records with missing required fields
            const problematicRecords = this.fnskuList.filter(fnsku => 
                !fnsku.FNSKU || !fnsku.MSKU || !fnsku.grading ||
                fnsku.FNSKU === null || fnsku.MSKU === null || fnsku.grading === null
            );
            
            if (problematicRecords.length > 0) {
                console.warn("Found records with missing required fields:", problematicRecords);
            }
            
            console.log("=== End Debug ===");
        },

        // Updated moveToValidation method with better error handling
       // Updated moveToValidation method with FNSKU validation handling
async moveToValidation(item) {
    console.log("=== MOVE TO VALIDATION DEBUG ===");
    console.log("Item data:", item);
    console.log("API_BASE_URL:", API_BASE_URL);
    console.log("Full URL will be:", `${API_BASE_URL}/api/labeling/move-to-validation`);
    
    if (!item || !item.ProductID) {
        console.error("Invalid item data for moving to Validation");
        alert("Invalid item data. Please refresh and try again.");
        return;
    }

    // Prevent multiple simultaneous requests
    if (this.isProcessing) {
        console.log("Already processing a request...");
        return;
    }

    this.isProcessing = true;

    try {
        console.log("Moving item to Validation:", {
            ProductID: item.ProductID,
            rtcounter: item.rtcounter
        });

        // Get the CSRF token from the meta tag
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");

        console.log("CSRF Token found:", !!csrfToken);
        console.log("CSRF Token value:", csrfToken ? csrfToken.substring(0, 10) + "..." : "null");

        if (!csrfToken) {
            throw new Error("CSRF token not found. Please refresh the page.");
        }

        // Prepare the payload
        const payload = {
            product_id: item.ProductID,
            rt_counter: item.rtcounter,
            current_location: "Labeling"
        };

        console.log("Sending payload:", payload);

        const requestUrl = `${API_BASE_URL}/api/labeling/move-to-validation`;
        console.log("Request URL:", requestUrl);

        const requestOptions = {
            headers: {
                "X-CSRF-TOKEN": csrfToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
        };

        console.log("Request options:", requestOptions);

        // Make the request with proper data format and headers
        const response = await axios.post(requestUrl, payload, requestOptions);

        console.log("Move to Validation response status:", response.status);
        console.log("Move to Validation response headers:", response.headers);
        console.log("Move to Validation response data:", response.data);

        if (response.data && response.data.success) {
            // Show success message
            alert(`Item ${item.rtcounter} successfully moved to Validation`);
            // Refresh the inventory list
            await this.fetchInventory();
        } else {
            const errorMessage = response.data?.message || "Failed to move item to Validation";
            console.error("Backend returned error:", errorMessage);
            console.error("Full response data:", response.data);
            alert(errorMessage);
        }
    } catch (error) {
        console.error("=== MOVE TO VALIDATION ERROR ===");
        console.error("Error object:", error);
        console.error("Error message:", error.message);
        
        let errorMessage = "Failed to move item to Validation. Please try again.";
        
        if (error.response) {
            // Server responded with error status
            console.error("Server response status:", error.response.status);
            console.error("Server response headers:", error.response.headers);
            console.error("Server response data:", error.response.data);
            console.error("Server response config:", error.response.config);
            
            if (error.response.status === 422) {
                // Validation error - check if it's missing FNSKU fields
                const responseData = error.response.data;
                
                if (responseData.requires_fnsku_setup) {
                    // Special handling for missing FNSKU/MSKU/ASIN
                    const missingFields = responseData.missing_fields || [];
                    const missingFieldsText = missingFields.join(', ');
                    
                    // Show specific error message and offer to open FNSKU modal
                    const userChoice = confirm(
                        `Cannot move to Validation.\n\n` +
                        `Missing required fields: ${missingFieldsText}\n\n` +
                        `Would you like to set up the FNSKU now?`
                    );
                    
                    if (userChoice) {
                        // Open the FNSKU modal for this item
                        this.showFnskuModal(item);
                    }
                    
                    return; // Exit early, don't show generic error
                } else {
                    // Regular validation errors
                    const errors = responseData?.errors;
                    if (errors) {
                        const errorMessages = Object.values(errors).flat();
                        errorMessage = "Validation errors:\n" + errorMessages.join("\n");
                    } else {
                        errorMessage = responseData?.message || "Validation failed";
                    }
                }
            } else if (error.response.status === 404) {
                errorMessage = "API endpoint not found. Please check your routes.";
                console.error("404 Error - Route not found. Check if route is properly defined.");
                console.error("Attempted URL:", `${API_BASE_URL}/api/labeling/move-to-validation`);
            } else if (error.response.status === 405) {
                errorMessage = "Method not allowed. Check if POST method is supported.";
                console.error("405 Error - Method not allowed");
            } else if (error.response.status === 419) {
                errorMessage = "CSRF token mismatch. Please refresh the page.";
                console.error("419 Error - CSRF token mismatch");
            } else if (error.response.status === 500) {
                errorMessage = error.response.data?.message || "Server error occurred.";
                console.error("500 Error - Internal server error");
            } else {
                errorMessage = error.response.data?.message || `Server error (${error.response.status})`;
            }
        } else if (error.request) {
            // Request was made but no response received
            console.error("No response received from server");
            console.error("Request details:", error.request);
            errorMessage = "No response from server. Please check your connection.";
        } else {
            // Something else happened
            console.error("Request setup error:", error.message);
            errorMessage = `Request error: ${error.message}`;
        }
        
        console.error("Final error message:", errorMessage);
        alert(errorMessage);
    } finally {
        this.isProcessing = false;
        console.log("=== END MOVE TO VALIDATION DEBUG ===");
    }
},

    async moveToStockroom(item) {
    console.log("=== MOVE TO STOCKROOM DEBUG ===");
    console.log("Item data:", item);
    console.log("API_BASE_URL:", API_BASE_URL);
    console.log("Full URL will be:", `${API_BASE_URL}/api/labeling/move-to-stockroom`);
    
    if (!item || !item.ProductID) {
        console.error("Invalid item data for moving to Stockroom");
        alert("Invalid item data. Please refresh and try again.");
        return;
    }

    if (this.isProcessing) {
        console.log("Already processing a request...");
        return;
    }

    this.isProcessing = true;

    try {
        console.log("Moving item to Stockroom:", {
            ProductID: item.ProductID,
            rtcounter: item.rtcounter
        });

        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");

        console.log("CSRF Token found:", !!csrfToken);
        console.log("CSRF Token value:", csrfToken ? csrfToken.substring(0, 10) + "..." : "null");

        if (!csrfToken) {
            throw new Error("CSRF token not found. Please refresh the page.");
        }

        const payload = {
            product_id: item.ProductID,
            rt_counter: item.rtcounter,
            current_location: "Labeling"
        };

        console.log("Sending payload:", payload);

        const requestUrl = `${API_BASE_URL}/api/labeling/move-to-stockroom`;
        console.log("Request URL:", requestUrl);

        const requestOptions = {
            headers: {
                "X-CSRF-TOKEN": csrfToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
        };

        console.log("Request options:", requestOptions);

        const response = await axios.post(requestUrl, payload, requestOptions);

        console.log("Move to Stockroom response status:", response.status);
        console.log("Move to Stockroom response headers:", response.headers);
        console.log("Move to Stockroom response data:", response.data);

        if (response.data && response.data.success) {
            alert(`Item ${item.rtcounter} successfully moved to Stockroom`);
            await this.fetchInventory();
        } else {
            const errorMessage = response.data?.message || "Failed to move item to Stockroom";
            console.error("Backend returned error:", errorMessage);
            console.error("Full response data:", response.data);
            alert(errorMessage);
        }
    } catch (error) {
        console.error("=== MOVE TO STOCKROOM ERROR ===");
        console.error("Error object:", error);
        console.error("Error message:", error.message);
        
        let errorMessage = "Failed to move item to Stockroom. Please try again.";
        
        if (error.response) {
            console.error("Server response status:", error.response.status);
            console.error("Server response headers:", error.response.headers);
            console.error("Server response data:", error.response.data);
            console.error("Server response config:", error.response.config);
            
            if (error.response.status === 422) {
                // Validation error - check if it's missing FNSKU fields
                const responseData = error.response.data;
                
                if (responseData.requires_fnsku_setup) {
                    // Special handling for missing FNSKU/MSKU/ASIN
                    const missingFields = responseData.missing_fields || [];
                    const missingFieldsText = missingFields.join(', ');
                    
                    // Show specific error message and offer to open FNSKU modal
                    const userChoice = confirm(
                        `Cannot move to Stockroom.\n\n` +
                        `Missing required fields: ${missingFieldsText}\n\n` +
                        `Would you like to set up the FNSKU now?`
                    );
                    
                    if (userChoice) {
                        // Open the FNSKU modal for this item
                        this.showFnskuModal(item);
                    }
                    
                    return; // Exit early, don't show generic error
                } else {
                    // Regular validation errors
                    const errors = responseData?.errors;
                    if (errors) {
                        const errorMessages = Object.values(errors).flat();
                        errorMessage = "Validation errors:\n" + errorMessages.join("\n");
                    } else {
                        errorMessage = responseData?.message || "Validation failed";
                    }
                }
            } else if (error.response.status === 404) {
                errorMessage = "API endpoint not found. Please check your routes.";
                console.error("404 Error - Route not found. Check if route is properly defined.");
                console.error("Attempted URL:", `${API_BASE_URL}/api/labeling/move-to-stockroom`);
            } else if (error.response.status === 405) {
                errorMessage = "Method not allowed. Check if POST method is supported.";
                console.error("405 Error - Method not allowed");
            } else if (error.response.status === 419) {
                errorMessage = "CSRF token mismatch. Please refresh the page.";
                console.error("419 Error - CSRF token mismatch");
            } else if (error.response.status === 500) {
                errorMessage = error.response.data?.message || "Server error occurred.";
                console.error("500 Error - Internal server error");
            } else {
                errorMessage = error.response.data?.message || `Server error (${error.response.status})`;
            }
        } else if (error.request) {
            console.error("No response received from server");
            console.error("Request details:", error.request);
            errorMessage = "No response from server. Please check your connection.";
        } else {
            console.error("Request setup error:", error.message);
            errorMessage = `Request error: ${error.message}`;
        }
        
        console.error("Final error message:", errorMessage);
        alert(errorMessage);
    } finally {
        this.isProcessing = false;
        console.log("=== END MOVE TO STOCKROOM DEBUG ===");
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