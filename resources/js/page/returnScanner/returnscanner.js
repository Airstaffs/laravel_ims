import { eventBus } from "../../components/eventBus";
import ScannerComponent from "../../components/Scanner.vue";
import { SoundService } from "../../components/Sound_service";
import "../../../css/modules.css";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ReturnScannerModule",
    components: {
        ScannerComponent,
    },
    data() {
        return {
            inventory: [],
            returnHistory: [], // Added this missing property
            stores: [],
            selectedStore: "",
            currentPage: 1,
            totalPages: 1,
            perPage: 10, // Default rows per page
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,
            expandedRows: {},
            serialDropdowns: {}, // Added this missing property

            // Scanner data
            returnId: "",
            serialNumber: "",
            locationInput: "",
            showManualInput: false,

            // For dual serial detection
            dualSerialProduct: false,
            secondSerialNumber: "",
            secondSerialLabel: "",
            showSecondSerialInput: true, // Control visibility of second serial input
            scannedSerialPosition: null, // Track which serial was scanned (primary/secondary)
            
            // Product information
            productId: null,
            fnskuViewer: "",
            asin: "",
            originalProductLocation: "",

            // ReturnID toggle
            showReturnIdField: false,

            // For auto verification
            autoVerifyTimeout: null,
            
            // Default image path and image modal states
            defaultImagePath: "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4=",
            showImageModal: false,
            modalImages: [],
            currentImageIndex: 0
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
                    ? String(valueA || '').localeCompare(String(valueB || ''))
                    : String(valueB || '').localeCompare(String(valueA || ''));
            });
        },
        // Add a computed property to detect mobile
        isMobile() {
            return window.innerWidth <= 768;
        },
        // Check if serial or return ID is provided
        hasIdentifier() {
            return this.serialNumber.trim() !== "" || this.returnId.trim() !== "";
        }
    },
    methods: {
        // Simple image error handler that uses default image
        handleImageError(event) {
            // If image fails to load, use default image
            event.target.src = this.defaultImagePath;
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

            // Add the main image first (img1)
            if (item.img1) {
                const mainImagePath = `/images/thumbnails/${item.img1}`;
                this.modalImages.push(mainImagePath);
            } else {
                // If no main image, use a default or product ID based image
                const defaultPath = `/images/thumbnails/${item.ProductID || 'default.jpg'}`;
                this.modalImages.push(defaultPath);
            }

            // Add additional images if they exist (img2-img15)
            for (let i = 2; i <= 15; i++) {
                const fieldName = `img${i}`;
                if (
                    item[fieldName] &&
                    item[fieldName] !== "NULL" &&
                    item[fieldName].trim() !== ""
                ) {
                    const imagePath = `/images/thumbnails/${item[fieldName]}`;
                    this.modalImages.push(imagePath);
                }
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

        // Open scanner modal
        openScannerModal() {
            this.$refs.scanner.openScannerModal();
        },

        // Toggle ReturnID field
        toggleReturnIdField() {
            this.showReturnIdField = !this.showReturnIdField;

            // If shown, focus on the field
            if (this.showReturnIdField) {
                this.$nextTick(() => {
                    if (this.$refs.returnIdInput) {
                        this.$refs.returnIdInput.focus();
                    }
                });
            }
        },

        // Hide the second serial input and focus on location field
        hideSecondSerial() {
            this.secondSerialNumber = "";
            this.showSecondSerialInput = false;
            
            // Focus on the location input
            this.focusNextField("locationInput");
            
            // Play success sound
            if (SoundService && SoundService.success) {
                SoundService.success();
            }
        },

        // Format date for display
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString() + " " + date.toLocaleTimeString();
            } catch (e) {
                console.error("Error formatting date:", e);
                return 'Invalid Date';
            }
        },

        // Format status for display
        formatStatus(status) {
            if (!status) return 'Unknown';
            const statusMap = {
                pending: "Pending",
                processed: "Processed",
                rejected: "Rejected",
                returned: "Returned",
                missing: "Not Found",
            };
            return statusMap[status] || status;
        },
        
        // Format RT number based on store name
        formatRTNumber(rtCounter, storeName) {
            if (!rtCounter) return 'N/A';
            const paddedCounter = String(rtCounter).padStart(5, '0');
            
            if (storeName === 'RenovarTech') {
                return `RT ${paddedCounter}`;
            } else if (storeName === 'Allrenewed') {
                return `AR ${paddedCounter}`;
            } else {
                // Default format if store doesn't match known patterns
                return `#${paddedCounter}`;
            }
        },

        // View return details
        viewReturnDetails(item) {
            if (!item) return;
            
            // Use defensive coding to handle potentially missing fields
            const rtNumber = item.rtcounter ? this.formatRTNumber(item.rtcounter, item.storename || '') : 'N/A';
            const returnId = item.LPN || 'N/A';
            const returnDate = this.formatDate(item.LPNDATE || null);
            const serial = item.serialnumber || 'N/A';
            const secondSerial = item.serialnumberb || '';
            const location = item.warehouselocation || 'Floor';
            const status = this.formatStatus(item.returnstatus || 'unknown');
            const fnsku = item.FNSKUviewer || 'N/A';
            const asin = item.ASINviewer || 'N/A';
            const buyer = item.BuyerName || item.costumer_name || 'Unknown';
            
            alert(
                `Return Details\n` +
                `RT#: ${rtNumber}\n` +
                `Return ID: ${returnId}\n` +
                `Return Date: ${returnDate}\n` +
                `Serial: ${serial}\n` +
                `${secondSerial ? "Second Serial: " + secondSerial + "\n" : ""}` +
                `Location: ${location}\n` +
                `Status: ${status}\n` +
                `FNSKU: ${fnsku}\n` +
                `ASIN: ${asin}\n` +
                `Buyer: ${buyer}`
            );
        },

        // Store dropdown functions
        async fetchStores() {
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/returns/stores`,
                    {
                        withCredentials: true,
                    }
                );
                this.stores = response.data;
            } catch (error) {
                console.error("Error fetching stores:", error);
            }
        },

        changeStore() {
            this.currentPage = 1;
            this.fetchInventory();
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

        // Inventory selection methods
        toggleAll() {
            this.inventory.forEach((item) => (item.checked = this.selectAll));
        },
        
        toggleDetails(index) {
            // Create a new object for reactivity
            const updatedExpandedRows = { ...this.expandedRows };
            updatedExpandedRows[index] = !updatedExpandedRows[index];
            this.expandedRows = updatedExpandedRows;
        },
        
        toggleDetailsVisibility() {
            this.showDetails = !this.showDetails;
        },

        // Sorting method
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = column;
                this.sortOrder = "asc";
            }
        },

        // Fetch inventory with location = 'Returnlist'
        async fetchInventory() {
            try {
                console.log("Fetching inventory data...");
                const response = await axios.get(
                    `${API_BASE_URL}/api/returns/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            store: this.selectedStore,
                            location: 'Returnlist'
                        },
                        withCredentials: true,
                    }
                );

                console.log("Response received:", response);

                if (!response.data || !response.data.data) {
                    console.warn("Response data or data.data is missing", response);
                    this.inventory = [];
                    this.returnHistory = [];
                    this.totalPages = 1;
                    return;
                }

                // Initialize items with checked property and useDefaultImage flag
                this.inventory = (response.data.data || []).map(item => {
                    return {
                        ...item,
                        checked: false,
                        useDefaultImage: false
                    };
                });
                
                // Set returnHistory from inventory for display in the table
                this.returnHistory = [...this.inventory];
                
                this.totalPages = response.data.last_page || 1;
                
                // Log successful data load
                console.log(`Loaded ${this.inventory.length} return items`);
            } catch (error) {
                console.error("Error fetching inventory data:", error);
                if (error.response) {
                    console.error("Response data:", error.response.data);
                    console.error("Response status:", error.response.status);
                }
                
                // Set empty data on error
                this.inventory = [];
                this.returnHistory = [];
                this.totalPages = 1;
                
                if (SoundService && SoundService.error) {
                    SoundService.error();
                }
            }
        },

        // Check for dual serial based on serial number
        async checkDualSerial() {
            if (!this.serialNumber) return false;

            try {
                // Show loading status
                this.$refs.scanner.startLoading("Checking product...");

                const response = await axios.get(
                    `${API_BASE_URL}/api/returns/check-serial`,
                    {
                        params: { serial: this.serialNumber },
                        withCredentials: true,
                    }
                );

                // Hide loading
                this.$refs.scanner.stopLoading();

                if (response.data.success) {
                    // Store product information
                    this.productId = response.data.productId || null;
                    this.fnskuViewer = response.data.fnskuViewer || "";
                    this.asin = response.data.productInfo?.ASIN || "";
                    this.originalProductLocation = response.data.productInfo?.location || "";
                    this.scannedSerialPosition = response.data.scannedSerialPosition || null;
                    
                    console.log("Product info retrieved:", {
                        productId: this.productId,
                        fnskuViewer: this.fnskuViewer,
                        asin: this.asin,
                        originalLocation: this.originalProductLocation,
                        scannedSerialPosition: this.scannedSerialPosition
                    });
                    
                    // Check if this is a dual serial product
                    if (response.data.isDualSerial) {
                        this.dualSerialProduct = true;
                        this.showSecondSerialInput = true; // Show the second serial input
                        this.secondSerialLabel =
                            response.data.secondSerialLabel || "Second Serial";

                        // If the second serial is already populated from the DB
                        if (response.data.secondSerial) {
                            this.secondSerialNumber = response.data.secondSerial;
                            
                            // Add highlighting class on next tick
                            this.$nextTick(() => {
                                if (this.$refs.secondSerialInput) {
                                    // Add highlight class
                                    this.$refs.secondSerialInput.classList.add('highlight-input');
                                    
                                    // Select all text to make it easy to delete if needed
                                    this.$refs.secondSerialInput.select();
                                    
                                    // Remove highlight class after animation completes
                                    setTimeout(() => {
                                        if (this.$refs.secondSerialInput) {
                                            this.$refs.secondSerialInput.classList.remove('highlight-input');
                                        }
                                    }, 3000);
                                }
                            });
                        }

                        // Play notification sound
                        if (SoundService && SoundService.notification) {
                            SoundService.notification();
                        } else if (SoundService && SoundService.success) {
                            SoundService.success();
                        }

                        // Focus on second serial field
                        this.$nextTick(() => {
                            if (this.$refs.secondSerialInput) {
                                this.$refs.secondSerialInput.focus();
                            }
                        });

                        return true;
                    } else {
                        this.dualSerialProduct = false;
                        this.secondSerialNumber = "";
                        this.showSecondSerialInput = true;
                        return false;
                    }
                } else {
                    // Display the specific error from the API
                    if (response.data.message) {
                        // Use standard error display
                        if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === 'function') {
                            this.$refs.scanner.showScanError(response.data.message);
                        } else if (this.$refs.scanner && typeof this.$refs.scanner.showError === 'function') {
                            this.$refs.scanner.showError(response.data.message);
                        } else {
                            console.error(response.data.message);
                        }
                    }
                    
                    // Reset product information
                    this.productId = null;
                    this.fnskuViewer = "";
                    this.asin = "";
                    this.originalProductLocation = "";
                    this.scannedSerialPosition = null;
                    this.dualSerialProduct = false;
                    this.secondSerialNumber = "";
                    this.showSecondSerialInput = true;
                    return false;
                }
            } catch (error) {
                console.error("Error checking dual serial:", error);
                this.$refs.scanner.stopLoading();
                
                // Reset product information
                this.productId = null;
                this.fnskuViewer = "";
                this.asin = "";
                this.originalProductLocation = "";
                this.scannedSerialPosition = null;
                
                // Use standard error display
                if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === 'function') {
                    this.$refs.scanner.showScanError("Network error checking serial");
                } else if (this.$refs.scanner && typeof this.$refs.scanner.showError === 'function') {
                    this.$refs.scanner.showError("Network error checking serial");
                }
                
                return false;
            }
        },

        // Input field handlers with sound
        async handleReturnIdInput() {
            if (!this.showManualInput && this.returnId.trim().length > 5) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    // Play success sound
                    if (SoundService && SoundService.success) {
                        SoundService.success();
                    }

                    // Focus on serial number field
                    this.focusNextField("serialNumberInput");
                }, 500);
            }
        },

        async handleSerialInput() {
            // Basic validation for serial
            const isValid = /^[a-zA-Z0-9-]+$/.test(this.serialNumber.trim());

            if (!isValid && this.serialNumber.trim() !== "") {
                // Show error for invalid serial
                if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === 'function') {
                    this.$refs.scanner.showScanError("Invalid Serial Number format");
                } else if (this.$refs.scanner && typeof this.$refs.scanner.showError === 'function') {
                    this.$refs.scanner.showError("Invalid Serial Number format");
                }
                
                this.$refs.serialNumberInput.select();
                if (SoundService && SoundService.error) {
                    SoundService.error();
                }
                return;
            }

            // In auto mode with valid input, check for dual serial and proceed
            if (!this.showManualInput && this.serialNumber.trim().length > 5) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(async () => {
                    // First check if this is a dual serial product
                    const isDualSerial = await this.checkDualSerial();

                    if (isDualSerial) {
                        // Already handled in checkDualSerial
                        return;
                    }

                    // Play success sound
                    if (SoundService && SoundService.success) {
                        SoundService.success();
                    }

                    // Focus on location field
                    this.focusNextField("locationInput");
                }, 500);
            }
        },

        async handleSecondSerialInput() {
            // Basic validation for second serial
            const isValid = /^[a-zA-Z0-9-]+$/.test(
                this.secondSerialNumber.trim()
            );

            if (!isValid && this.secondSerialNumber.trim() !== "") {
                // Show error for invalid serial
                if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === 'function') {
                    this.$refs.scanner.showScanError("Invalid Second Serial Number format");
                } else if (this.$refs.scanner && typeof this.$refs.scanner.showError === 'function') {
                    this.$refs.scanner.showError("Invalid Second Serial Number format");
                }
                
                this.$refs.secondSerialInput.select();
                if (SoundService && SoundService.error) {
                    SoundService.error();
                }
                return;
            }

            // In auto mode with valid input, proceed to location
            if (
                !this.showManualInput &&
                this.secondSerialNumber.trim().length > 5
            ) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    // Play success sound
                    if (SoundService && SoundService.success) {
                        SoundService.success();
                    }

                    // Focus on location field
                    this.focusNextField("locationInput");
                }, 500);
            }
        },

        handleLocationInput() {
            // Validate location format
            const locationRegex = /^L\d{3}[A-G]$/i;
            const isValid =
                locationRegex.test(this.locationInput.trim()) ||
                this.locationInput.trim() === "Floor" ||
                this.locationInput.trim() === "L800G";

            if (!isValid && this.locationInput.trim() !== "") {
                if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === 'function') {
                    this.$refs.scanner.showScanError("Invalid Location Format (use L###X, Floor, or L800G)");
                } else if (this.$refs.scanner && typeof this.$refs.scanner.showError === 'function') {
                    this.$refs.scanner.showError("Invalid Location Format (use L###X, Floor, or L800G)");
                }
                
                this.$refs.locationInput.select();
                if (SoundService && SoundService.error) {
                    SoundService.error();
                }
                return;
            }

            // Only in auto mode, process scan after valid location input
            if (
                !this.showManualInput &&
                isValid &&
                this.locationInput.trim().length > 0
            ) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    // Play success sound for valid location
                    if (SoundService && SoundService.success) {
                        SoundService.success();
                    }

                    // Process the scan
                    this.processScan();
                }, 500);
            }
        },

        // Focus the next input field
        focusNextField(fieldRef) {
            this.$nextTick(() => {
                const nextField = this.$refs[fieldRef];
                if (nextField) {
                    nextField.focus();
                }
            });
        },

        // Process scan with validation
        async processScan(scannedCode = null) {
    try {
        // Use either the scanned code or input fields
        let scanSerial, scanSecondSerial, scanLocation, scanReturnId;

        if (scannedCode) {
            // External code passed (from hardware scanner)
            // Determine if it's a return ID, serial, or location based on format
            const isLocation =
                /^L\d{3}[A-G]$/i.test(scannedCode) ||
                scannedCode === "Floor" ||
                scannedCode === "L800G";

            const isReturnId = /^R\d{5,}$/i.test(scannedCode);

            if (isLocation) {
                scanLocation = scannedCode;
                scanSerial = this.serialNumber;
                scanSecondSerial = this.secondSerialNumber;
                scanReturnId = this.returnId;
            } else if (isReturnId) {
                scanReturnId = scannedCode;
                scanSerial = this.serialNumber;
                scanSecondSerial = this.secondSerialNumber;
                scanLocation = this.locationInput;
            } else {
                // Assume it's a serial
                scanSerial = scannedCode;
                scanSecondSerial = this.secondSerialNumber;
                scanLocation = this.locationInput;
                scanReturnId = this.returnId;

                // Check if this is a dual serial product
                await this.checkDualSerial();
            }
        } else {
            // Use the input fields
            scanSerial = this.serialNumber;
            scanSecondSerial = this.secondSerialNumber;
            scanLocation = this.locationInput;
            scanReturnId = this.returnId;
        }

        // Basic validation - need at least a serial
        if (!scanSerial) {
            if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === 'function') {
                this.$refs.scanner.showScanError("Serial Number is required");
            } else if (this.$refs.scanner && typeof this.$refs.scanner.showError === 'function') {
                this.$refs.scanner.showError("Serial Number is required");
            }
            
            if (SoundService && SoundService.error) {
                SoundService.error();
            }
            this.focusNextField("serialNumberInput");
            return;
        }

        // Validate dual serial if detected and second serial input is visible
        if (this.dualSerialProduct && this.showSecondSerialInput && !scanSecondSerial) {
            if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === 'function') {
                this.$refs.scanner.showScanError(`${this.secondSerialLabel} is required for this product`);
            } else if (this.$refs.scanner && typeof this.$refs.scanner.showError === 'function') {
                this.$refs.scanner.showError(`${this.secondSerialLabel} is required for this product`);
            }
            
            if (SoundService && SoundService.error) {
                SoundService.error();
            }
            this.focusNextField("secondSerialInput");
            return;
        }

        // Validate location format if provided
        const locationRegex = /^L\d{3}[A-G]$/i;
        const isValidLocation =
            !scanLocation ||
            locationRegex.test(scanLocation) ||
            scanLocation === "Floor" ||
            scanLocation === "L800G";

        if (!isValidLocation) {
            if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === 'function') {
                this.$refs.scanner.showScanError("Invalid Location Format (use L###X, Floor, or L800G)");
            } else if (this.$refs.scanner && typeof this.$refs.scanner.showError === 'function') {
                this.$refs.scanner.showError("Invalid Location Format (use L###X, Floor, or L800G)");
            }
            
            if (SoundService && SoundService.error) {
                SoundService.error();
            }
            return;
        }

        // Get images from scanner if available
        const imageData = this.$refs.scanner.capturedImages ? 
            this.$refs.scanner.capturedImages.map(img => img.data) : [];

        // Show loading state
        this.$refs.scanner.startLoading("Processing Return Scan");

        // If in single serial mode for a dual serial product, set a flag in scanData
        const singleSerialMode = this.dualSerialProduct && !this.showSecondSerialInput;

        // Track which serials are being used
        const scannedPrimarySerial = this.scannedSerialPosition === 'primary' ? scanSerial : scanSecondSerial;
        const scannedSecondarySerial = this.scannedSerialPosition === 'secondary' ? scanSerial : scanSecondSerial;

        // Send data to server
        const scanData = {
            ReturnId: scanReturnId || null,
            SerialNumber: scanSerial,
            SecondSerial: scanSecondSerial || null,
            Location: scanLocation || "L800G", // Default to Floor if not provided
            Store: this.selectedStore,
            Images: imageData,
            SingleSerialMode: singleSerialMode, // Add this flag for the backend
            ProductID: this.productId,
            FNSKUviewer: this.fnskuViewer,
            OriginalLocation: this.originalProductLocation,
            ScannedSerialPosition: this.scannedSerialPosition,
            ScannedPrimarySerial: scannedPrimarySerial,
            ScannedSecondarySerial: scannedSecondarySerial
        };

        console.log("Sending scan data:", scanData);

        // SIMPLIFIED API CALL - exactly like in Stockroom Scanner
        const response = await axios.post(
            `${API_BASE_URL}/api/returns/process-scan`,
            scanData,
            {
                withCredentials: true,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                }
            }
        );

        // Hide loading
        this.$refs.scanner.stopLoading();

        const data = response.data;

        if (data.success) {
            // Success case
            if (this.$refs.scanner && typeof this.$refs.scanner.showScanSuccess === 'function') {
                this.$refs.scanner.showScanSuccess(data.message || "Return processed successfully");
            } else if (this.$refs.scanner && typeof this.$refs.scanner.showSuccess === 'function') {
                this.$refs.scanner.showSuccess(data.message || "Return processed successfully");
            }
            
            if (SoundService && SoundService.successScan) {
                SoundService.successScan(true);
            }

            // Add to scan history if method exists
            if (this.$refs.scanner && typeof this.$refs.scanner.addSuccessScan === 'function') {
                this.$refs.scanner.addSuccessScan({
                    ReturnId: scanReturnId || "N/A",
                    Serial: scanSerial,
                    SecondSerial: scanSecondSerial || "N/A",
                    Location: scanLocation || "Floor",
                    FNSKU: this.fnskuViewer || "N/A",
                    SingleSerialMode: singleSerialMode
                });
            }

            // Reset fields and dual serial flag
            this.clearScanFields();

            // Refresh inventory
            this.fetchInventory();
        } else {
            // Error case
            if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === 'function') {
                this.$refs.scanner.showScanError(data.message || "Error processing return");
            } else if (this.$refs.scanner && typeof this.$refs.scanner.showError === 'function') {
                this.$refs.scanner.showError(data.message || "Error processing return");
            }
            
            if (SoundService && SoundService.scanRejected) {
                SoundService.scanRejected(true);
            }

            // Add to error scan history if method exists
            if (this.$refs.scanner && typeof this.$refs.scanner.addErrorScan === 'function') {
                this.$refs.scanner.addErrorScan(
                    {
                        ReturnId: scanReturnId || "N/A",
                        Serial: scanSerial,
                        SecondSerial: scanSecondSerial || "N/A",
                        Location: scanLocation || "N/A",
                        FNSKU: this.fnskuViewer || "N/A",
                        SingleSerialMode: singleSerialMode
                    },
                    data.reason || "error"
                );
            }
        }

        // Clear input fields and focus on Return ID or Serial
        this.clearScanFields();
        if (this.showReturnIdField) {
            this.focusNextField("returnIdInput");
        } else {
            this.focusNextField("serialNumberInput");
        }
    } catch (error) {
        // Hide loading
        this.$refs.scanner.stopLoading();

        console.error("Error processing scan:", error);
        
        // If it's a 419 error, show a specific message about CSRF token
        if (error.response && error.response.status === 419) {
            console.error("CSRF token mismatch. Try refreshing the page.");
            if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === 'function') {
                this.$refs.scanner.showScanError("Session expired. Please refresh the page and try again.");
            } else if (this.$refs.scanner && typeof this.$refs.scanner.showError === 'function') {
                this.$refs.scanner.showError("Session expired. Please refresh the page and try again.");
            }
        } else {
            if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === 'function') {
                this.$refs.scanner.showScanError("Network or server error");
            } else if (this.$refs.scanner && typeof this.$refs.scanner.showError === 'function') {
                this.$refs.scanner.showError("Network or server error");
            }
        }
        
        if (SoundService && SoundService.scanRejected) {
            SoundService.scanRejected(true);
        }

        // Add failed scan to history if method exists
        if (this.$refs.scanner && typeof this.$refs.scanner.addErrorScan === 'function') {
            this.$refs.scanner.addErrorScan(
                {
                    ReturnId: this.returnId || "N/A",
                    Serial: this.serialNumber || "",
                    SecondSerial: this.secondSerialNumber || "N/A",
                    Location: this.locationInput || "N/A",
                    FNSKU: this.fnskuViewer || "N/A"
                },
                error.response && error.response.status === 419 ? "session_expired" : "network_error"
            );
        }
    }
},
        // Clear scan fields
        clearScanFields() {
            this.returnId = "";
            this.serialNumber = "";
            this.secondSerialNumber = "";
            this.locationInput = "";
            this.dualSerialProduct = false;
            this.showSecondSerialInput = true; // Reset to show the input by default
            this.productId = null;
            this.fnskuViewer = "";
            this.asin = "";
            this.originalProductLocation = "";
            this.scannedSerialPosition = null;
        },

        // Scanner event handlers
        handleScanProcess() {
            this.processScan();
        },

        handleHardwareScan(scannedCode) {
            // For hardware scanner input, determine the type of code and process accordingly
            this.processScan(scannedCode);
        },

        handleModeChange(event) {
            this.showManualInput = event.manual;
        },

        handleScannerOpened() {
            // Get current mode from scanner component
            this.showManualInput = this.$refs.scanner.showManualInput;

            // Reset fields
            this.clearScanFields();

            // Focus on appropriate field
            this.$nextTick(() => {
                if (this.showReturnIdField && this.$refs.returnIdInput) {
                    this.$refs.returnIdInput.focus();
                } else if (this.$refs.serialNumberInput) {
                    this.$refs.serialNumberInput.focus();
                }
            });
        },

        handleScannerClosed() {
            // Refresh inventory when scanner is closed
            this.fetchInventory();
        },

        handleScannerReset() {
            // Reset fields when scanner is reset
            this.clearScanFields();
        },
        
        // Methods for handling responsiveness
        handleResize() {
            // If we're on mobile and dropdowns are open, we might want to close them
            if (this.isMobile) {
                const hasOpenDropdowns = Object.values(this.serialDropdowns).some(isOpen => isOpen);
                if (hasOpenDropdowns) {
                    this.serialDropdowns = {};
                }
            }
        },
        
        closeDropdownsOnClickOutside(event) {
            // Check if click is outside any dropdown
            const isOutside = !event.target.closest('.serial-dropdown');
            if (isOutside) {
                this.serialDropdowns = {};
            }
        }
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchInventory();
        }
    },
    mounted() {
        // Configure axios
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;

        // Set CSRF token
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common["X-CSRF-TOKEN"] = token.getAttribute("content");
        }

        // Add Font Awesome if not already included
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fontAwesome = document.createElement("link");
            fontAwesome.rel = "stylesheet";
            fontAwesome.href =
                "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css";
            document.head.appendChild(fontAwesome);
        }

        // Fetch stores for dropdown
        this.fetchStores();

        // Fetch initial data
        this.fetchInventory();
        
        // Listen for window resize to update isMobile
        window.addEventListener('resize', this.handleResize);
        
        // Initialize serialDropdowns
        this.inventory.forEach((_, index) => {
            this.$set(this.serialDropdowns, index, false);
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', this.closeDropdownsOnClickOutside);
        
        // Handle keyboard navigation for the image modal
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
    beforeUnmount() {
        // Clean up any timeouts
        if (this.autoVerifyTimeout) {
            clearTimeout(this.autoVerifyTimeout);
        }
        
        // Remove event listeners
        window.removeEventListener('resize', this.handleResize);
        document.removeEventListener('click', this.closeDropdownsOnClickOutside);
        
        // Remove keyboard event listener for image modal
        if (this.handleKeyDown) {
            window.removeEventListener("keydown", this.handleKeyDown);
        }
    },
};