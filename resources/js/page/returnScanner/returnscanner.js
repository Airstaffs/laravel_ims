import { eventBus } from "../../components/eventBus";
import ScannerComponent from "../../components/Scanner.vue";
import { SoundService } from "../../components/Sound_service";
import "../../../css/modules.css";
import "./returnscanner.css";
import { DEFAULT_IMAGE } from "../../constant";
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
            loading: true,
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
            defaultImagePath: DEFAULT_IMAGE,
            showImageModal: false,
            modalImages: [],
            currentImageIndex: 0,
            viewDetailsModal: false,
            item: {},
            basePath: "/images/thumbnails/",

            currentCaptureStep: 0, // 0 = no capture, 1 = first serial, 2 = second serial
            capturedImagesForSerial1: [],
            capturedImagesForSerial2: [],
            maxImagesPerSerial: 12,
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
                    ? String(valueA || "").localeCompare(String(valueB || ""))
                    : String(valueB || "").localeCompare(String(valueA || ""));
            });
        },
        // Add a computed property to detect mobile
        isMobile() {
            return window.innerWidth <= 768;
        },
        // Check if serial or return ID is provided
        hasIdentifier() {
            return (
                this.serialNumber.trim() !== "" || this.returnId.trim() !== ""
            );
        },
    },
    methods: {

        
        // Simple image error handler that uses default image
        handleImageError(event) {
            // If image fails to load, use default image
            event.target.src = this.defaultImagePath;
            event.target.onerror = null; // Prevent infinite error loop
        },

        onImageErrorMain(event) {
            event.target.src = this.defaultImagePath;
        },
        onThumbnailError(event, index) {
            event.target.src = this.defaultImagePath;
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
                // const mainImagePath = `/images/thumbnails/${item.img1}`;
                 const mainImagePath = item.img1;
                this.modalImages.push(mainImagePath);
            } else {
                // If no main image, use a default or product ID based image
                const defaultPath = `/images/thumbnails/${
                    item.ProductID || "default.jpg"
                }`;
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
                    this.modalImages.push(item[fieldName]);
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

        handleShowDetailsModal(item) {
            this.item = item
            this.viewDetailsModal = true
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

            // If we're hiding the ReturnID field, also clear its value
            if (!this.showReturnIdField) {
                this.returnId = ""; // Clear the ReturnID value when hiding
                console.log("ReturnID field hidden and value cleared");
            } else {
                // If shown, focus on the field
                this.$nextTick(() => {
                    if (this.$refs.returnIdInput) {
                        this.$refs.returnIdInput.focus();
                    }
                });
            }
        },

        clearReturnId() {
            this.returnId = "";
            this.$refs.returnIdInput.focus();

            // Play a click sound if available
            if (SoundService && SoundService.click) {
                SoundService.click();
            } else if (SoundService && SoundService.success) {
                SoundService.success();
            }
        },

        // Hide the second serial input and focus on location field
  hideSecondSerial() {
    console.log('\n❌ ========== HIDE SECOND SERIAL (X button clicked) ==========');
    console.log('User clicked X to skip second serial');
    
    // Clear second serial number
    this.secondSerialNumber = "";
    
    // Clear any captured images for serial 2
    this.capturedImagesForSerial2 = [];
    
    // Hide the second serial input
    this.showSecondSerialInput = false;
    
    // ✅ CRITICAL: Set currentCaptureStep to 0 to show location input
    this.currentCaptureStep = 0;
    
    console.log('✅ Second serial cleared, moving to location input');
    console.log('Current state:', {
        currentCaptureStep: this.currentCaptureStep,
        showSecondSerialInput: this.showSecondSerialInput,
        serial1Images: this.capturedImagesForSerial1.length,
        serial2Images: this.capturedImagesForSerial2.length
    });
    
    // Focus on the location input
    this.$nextTick(() => {
        if (this.$refs.locationInput) {
            this.$refs.locationInput.focus();
            console.log('✅ Focused on location input');
        }
    });

    // Play success sound
    if (SoundService && SoundService.success) {
        SoundService.success();
    }
    
    console.log('========== END HIDE SECOND SERIAL ==========\n');
},

        // Format date for display
        formatDate(dateString) {
            if (!dateString) return "N/A";
            try {
                const date = new Date(dateString);
                return (
                    date.toLocaleDateString() + " " + date.toLocaleTimeString()
                );
            } catch (e) {
                console.error("Error formatting date:", e);
                return "Invalid Date";
            }
        },

        // Format status for display
        formatStatus(status) {
            if (!status) return "Unknown";
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
            if (!rtCounter) return "N/A";
            const paddedCounter = String(rtCounter).padStart(5, "0");

            if (storeName === "RenovarTech") {
                return `RT ${paddedCounter}`;
            } else if (storeName === "Allrenewed") {
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
            const rtNumber = item.rtcounter
                ? this.formatRTNumber(item.rtcounter, item.storename || "")
                : "N/A";
            const returnId = item.LPN || "N/A";
            const returnDate = this.formatDate(item.LPNDATE || null);
            const serial = item.serialnumber || "N/A";
            const secondSerial = item.serialnumberb || "";
            const location = item.warehouselocation || "Floor";
            const status = this.formatStatus(item.returnstatus || "unknown");
            const fnsku = item.FNSKUviewer || "N/A";
            const asin = item.ASINviewer || "N/A";
            const buyer = item.BuyerName || item.costumer_name || "Unknown";

            alert(
                `Return Details\n` +
                    `RT#: ${rtNumber}\n` +
                    `Return ID: ${returnId}\n` +
                    `Return Date: ${returnDate}\n` +
                    `Serial: ${serial}\n` +
                    `${
                        secondSerial
                            ? "Second Serial: " + secondSerial + "\n"
                            : ""
                    }` +
                    `Location: ${location}\n` +
                    `Status: ${status}\n` +
                    `FNSKU: ${fnsku}\n` +
                    `ASIN: ${asin}\n` +
                    `Buyer: ${buyer}`
            );
        },

        // Store dropdown functions
        async fetchStores() {
            this.loading = true;
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
            } finally {
                this.loading = false;
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
            this.loading = true;
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
                            location: "Returnlist",
                        },
                        withCredentials: true,
                    }
                );

                console.log("Response received:", response);

                if (!response.data || !response.data.data) {
                    console.warn(
                        "Response data or data.data is missing",
                        response
                    );
                    this.inventory = [];
                    this.returnHistory = [];
                    this.totalPages = 1;
                    return;
                }

                // Initialize items with checked property and useDefaultImage flag
                this.inventory = (response.data.data || []).map((item) => {
                    return {
                        ...item,
                        checked: false,
                        useDefaultImage: false,
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
            } finally {
                this.loading = false;
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
            this.originalProductLocation =
                response.data.productInfo?.location || "";
            this.scannedSerialPosition =
                response.data.scannedSerialPosition || null;

            console.log("Product info retrieved:", {
                productId: this.productId,
                fnskuViewer: this.fnskuViewer,
                asin: this.asin,
                originalLocation: this.originalProductLocation,
                scannedSerialPosition: this.scannedSerialPosition,
            });

            // ✅ FIXED: Check if this is a dual serial product AND second serial is valid (not "N/A")
            const hasValidSecondSerial = response.data.secondSerial && 
                                       response.data.secondSerial.trim() !== "" &&
                                       response.data.secondSerial.toUpperCase() !== "N/A";

            if (response.data.isDualSerial && hasValidSecondSerial) {
                this.dualSerialProduct = true;
                this.showSecondSerialInput = true; // Show the second serial input
                this.secondSerialLabel =
                    response.data.secondSerialLabel || "Second Serial";

                // If the second serial is already populated from the DB
                this.secondSerialNumber = response.data.secondSerial;

                // Add highlighting class on next tick
                this.$nextTick(() => {
                    if (this.$refs.secondSerialInput) {
                        // Add highlight class
                        this.$refs.secondSerialInput.classList.add(
                            "highlight-input"
                        );

                        // Select all text to make it easy to delete if needed
                        this.$refs.secondSerialInput.select();

                        // Remove highlight class after animation completes
                        setTimeout(() => {
                            if (this.$refs.secondSerialInput) {
                                this.$refs.secondSerialInput.classList.remove(
                                    "highlight-input"
                                );
                            }
                        }, 3000);
                    }
                });

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
                // ✅ Not a dual serial product OR second serial is "N/A"
                this.dualSerialProduct = false;
                this.secondSerialNumber = "";
                this.showSecondSerialInput = true;
                return false;
            }
        } else {
            // Display the specific error from the API
            if (response.data.message) {
                // Use standard error display
                if (
                    this.$refs.scanner &&
                    typeof this.$refs.scanner.showScanError ===
                        "function"
                ) {
                    this.$refs.scanner.showScanError(
                        response.data.message
                    );
                } else if (
                    this.$refs.scanner &&
                    typeof this.$refs.scanner.showError === "function"
                ) {
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
        if (
            this.$refs.scanner &&
            typeof this.$refs.scanner.showScanError === "function"
        ) {
            this.$refs.scanner.showScanError(
                "Network error checking serial"
            );
        } else if (
            this.$refs.scanner &&
            typeof this.$refs.scanner.showError === "function"
        ) {
            this.$refs.scanner.showError(
                "Network error checking serial"
            );
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


            // ✅ NEW: Proceed to image capture step
proceedToImageCapture(serialNumber) {
    if (serialNumber === 1) {
        if (!this.serialNumber.trim()) {
            this.$refs.scanner.showScanError("Please enter a serial number first");
            if (SoundService && SoundService.error) {
                SoundService.error();
            }
            return;
        }
        console.log('🎬 Starting capture for Serial 1');
        this.currentCaptureStep = 1;
        if (SoundService && SoundService.success) {
            SoundService.success();
        }
    } else if (serialNumber === 2) {
        if (!this.secondSerialNumber.trim()) {
            this.$refs.scanner.showScanError("Please enter second serial number first");
            if (SoundService && SoundService.error) {
                SoundService.error();
            }
            return;
        }
        console.log('🎬 Starting capture for Serial 2');
        this.currentCaptureStep = 2; // ✅ Set to 2 for second serial
        if (SoundService && SoundService.success) {
            SoundService.success();
        }
    }
},

    // ✅ NEW: Skip image capture for a serial
skipImageCapture(serialNumber) {
    console.log(`\n⏭️ ========== SKIP IMAGE CAPTURE: Serial ${serialNumber} ==========`);
    
    if (serialNumber === 1) {
        console.log('⏭️ Skipping images for Serial 1');
        
        // Clear any captured images for serial 1
        this.capturedImagesForSerial1 = [];
        
        // Also clear scanner images
        if (this.$refs.scanner) {
            this.$refs.scanner.capturedImages = [];
        }
        
        // If dual serial, move to second serial input
        if (this.dualSerialProduct && this.showSecondSerialInput) {
            this.currentCaptureStep = -1; // Special state for second serial input
            
            if (this.$refs.scanner) {
                this.$refs.scanner.showScanSuccess('Skipped images for first serial');
            }
            console.log('➡️ Moving to second serial input (currentCaptureStep = -1)');
            
            setTimeout(() => {
                if (this.$refs.secondSerialInput) {
                    this.$refs.secondSerialInput.focus();
                    // ✅ Select all text so user can scan over it
                    this.$refs.secondSerialInput.select();
                    console.log('✅ Focused and selected text in second serial input');
                }
            }, 100);
        } else {
            // Single serial - go to location
            this.currentCaptureStep = 0;
            
            if (this.$refs.scanner) {
                this.$refs.scanner.showScanSuccess('Skipped images');
            }
            console.log('➡️ Moving to location input (currentCaptureStep = 0)');
            
            this.$nextTick(() => {
                if (this.$refs.locationInput) {
                    this.$refs.locationInput.focus();
                    console.log('✅ Focused on location input');
                }
            });
        }
        
    } else if (serialNumber === 2) {
        console.log('⏭️ Skipping images for Serial 2');
        
        // Clear any captured images for serial 2
        this.capturedImagesForSerial2 = [];
        
        // Clear scanner images
        if (this.$refs.scanner) {
            this.$refs.scanner.capturedImages = [];
        }
        
        // Always go to location after serial 2
        this.currentCaptureStep = 0;
        
        if (this.$refs.scanner) {
            this.$refs.scanner.showScanSuccess('Skipped images for second serial');
        }
        console.log('➡️ Moving to location input (currentCaptureStep = 0)');
        
        this.$nextTick(() => {
            if (this.$refs.locationInput) {
                this.$refs.locationInput.focus();
                console.log('✅ Focused on location input');
            }
        });
    }
    
    console.log('📊 State after skip:', {
        currentCaptureStep: this.currentCaptureStep,
        serial1Images: this.capturedImagesForSerial1.length,
        serial2Images: this.capturedImagesForSerial2.length
    });
    console.log('========== END SKIP IMAGE CAPTURE ==========\n');
    
    if (SoundService && SoundService.success) {
        SoundService.success();
    }
},

    

finishImageCapture(serialNumber) {
    console.log(`\n🎬 ========== FINISH IMAGE CAPTURE: Serial ${serialNumber} ==========`);
    console.log(`Scanner has ${this.$refs.scanner.capturedImages.length} images BEFORE processing`);
    console.log(`Current state: currentCaptureStep=${this.currentCaptureStep}, dualSerial=${this.dualSerialProduct}`);
    
    if (serialNumber === 1) {
        // Store the first serial number
        const firstSerial = this.serialNumber;
        console.log(`📌 Processing Serial 1: "${firstSerial}"`);
        
        // ✅ FIXED: Handle case where no images were captured
        if (this.$refs.scanner.capturedImages.length === 0) {
            console.log('⚠️ No images captured for Serial 1');
            this.capturedImagesForSerial1 = [];
        } else {
            // Map images and ensure serial is set
            this.capturedImagesForSerial1 = this.$refs.scanner.capturedImages.map((img, idx) => {
                console.log(`  Processing Scanner Image ${idx + 1}:`, {
                    hasData: !!img.data,
                    dataLength: img.data ? img.data.length : 0,
                    existingSerial: img.serial,
                    usingSerial: img.serial || firstSerial
                });
                
                return { 
                    ...img, 
                    serialIndex: 1,
                    serial: img.serial || firstSerial
                };
            });
            
            console.log('✅ Stored images for Serial 1:', {
                serial: firstSerial,
                count: this.capturedImagesForSerial1.length
            });
        }
        
        // Clear scanner images
        this.$refs.scanner.capturedImages = [];
        console.log('🧹 Cleared scanner images');
        
        // ✅ Move to appropriate next step
        if (this.dualSerialProduct && this.showSecondSerialInput) {
            this.currentCaptureStep = -1; // Special state: waiting for second serial capture to start
            
            const message = this.capturedImagesForSerial1.length > 0
                ? `${this.capturedImagesForSerial1.length} images captured for first serial.`
                : 'No images captured for first serial. Moving to second serial.';
            
            this.$refs.scanner.showScanSuccess(message);
            console.log('➡️ Moving to second serial input (currentCaptureStep = -1)');
            
            // Focus on second serial input after a short delay
            setTimeout(() => {
                if (this.$refs.secondSerialInput) {
                    this.$refs.secondSerialInput.focus();
                    // ✅ Select all text so user can easily scan over it or change it
                    this.$refs.secondSerialInput.select();
                    console.log('✅ Focused and selected text in second serial input');
                }
            }, 150);
        } else {
            // Single serial mode - reset to 0 and go to location
            this.currentCaptureStep = 0;
            
            const message = this.capturedImagesForSerial1.length > 0
                ? `${this.capturedImagesForSerial1.length} images captured`
                : 'No images captured. Moving to location.';
            
            this.$refs.scanner.showScanSuccess(message);
            console.log('➡️ Moving to location input (currentCaptureStep = 0, single serial mode)');
            
            this.$nextTick(() => {
                if (this.$refs.locationInput) {
                    this.$refs.locationInput.focus();
                    console.log('✅ Focused on location input');
                }
            });
        }
        
    } else if (serialNumber === 2) {
        // ✅ Store the second serial number FIRST
        const secondSerial = this.secondSerialNumber;
        console.log(`📌 Processing Serial 2: "${secondSerial}"`);
        console.log(`📸 Scanner has ${this.$refs.scanner.capturedImages.length} images to process`);
        
        // ✅ FIXED: Handle case where no images were captured
        if (this.$refs.scanner.capturedImages.length === 0) {
            console.log('⚠️ No images captured for Serial 2');
            this.capturedImagesForSerial2 = [];
        } else {
            // Map images and ensure serial is set
            this.capturedImagesForSerial2 = this.$refs.scanner.capturedImages.map((img, idx) => {
                console.log(`  Processing Scanner Image ${idx + 1} for Serial 2:`, {
                    hasData: !!img.data,
                    dataLength: img.data ? img.data.length : 0,
                    existingSerial: img.serial,
                    usingSerial: img.serial || secondSerial
                });
                
                return { 
                    ...img, 
                    serialIndex: 2,
                    serial: img.serial || secondSerial
                };
            });
            
            console.log('✅ Stored images for Serial 2:', {
                serial: secondSerial,
                count: this.capturedImagesForSerial2.length
            });
        }
        
        // Clear scanner images AFTER processing
        this.$refs.scanner.capturedImages = [];
        console.log('🧹 Cleared scanner images');
        
        // ✅ NOW reset currentCaptureStep to 0 and navigate to location
        this.currentCaptureStep = 0;
        
        const message = this.capturedImagesForSerial2.length > 0
            ? `${this.capturedImagesForSerial2.length} images captured for second serial`
            : 'No images captured for second serial. Moving to location.';
        
        this.$refs.scanner.showScanSuccess(message);
        console.log('➡️ Moving to location input (currentCaptureStep = 0)');
        
        this.$nextTick(() => {
            if (this.$refs.locationInput) {
                this.$refs.locationInput.focus();
                console.log('✅ Focused on location input');
            }
        });
    }
    
    console.log('========== END FINISH IMAGE CAPTURE ==========\n');
    console.log('📊 Current State:', {
        serial1ImagesCount: this.capturedImagesForSerial1.length,
        serial2ImagesCount: this.capturedImagesForSerial2.length,
        scannerImagesCount: this.$refs.scanner.capturedImages.length,
        currentCaptureStep: this.currentCaptureStep
    });
    
    if (SoundService && SoundService.success) {
        SoundService.success();
    }
},

         async handleSerialInput() {
        const isValid = /^[a-zA-Z0-9-]+$/.test(this.serialNumber.trim());

        if (!isValid && this.serialNumber.trim() !== "") {
            if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === "function") {
                this.$refs.scanner.showScanError("Invalid Serial Number format");
            }
            this.$refs.serialNumberInput.select();
            if (SoundService && SoundService.error) {
                SoundService.error();
            }
            return;
        }

        // In auto mode with valid input, check for dual serial and proceed to image capture
        if (!this.showManualInput && this.serialNumber.trim().length > 5) {
            if (this.autoVerifyTimeout) {
                clearTimeout(this.autoVerifyTimeout);
            }

            this.autoVerifyTimeout = setTimeout(async () => {
                const isDualSerial = await this.checkDualSerial();

                if (SoundService && SoundService.success) {
                    SoundService.success();
                }

                // ✅ Go to image capture for first serial
                this.proceedToImageCapture(1);
            }, 500);
        }
    },

        async handleSecondSerialInput() {
        const isValid = /^[a-zA-Z0-9-]+$/.test(this.secondSerialNumber.trim());

        if (!isValid && this.secondSerialNumber.trim() !== "") {
            if (this.$refs.scanner && typeof this.$refs.scanner.showScanError === "function") {
                this.$refs.scanner.showScanError("Invalid Second Serial Number format");
            }
            this.$refs.secondSerialInput.select();
            if (SoundService && SoundService.error) {
                SoundService.error();
            }
            return;
        }

        // In auto mode with valid input, proceed to image capture
        if (!this.showManualInput && this.secondSerialNumber.trim().length > 5) {
            if (this.autoVerifyTimeout) {
                clearTimeout(this.autoVerifyTimeout);
            }

            this.autoVerifyTimeout = setTimeout(() => {
                if (SoundService && SoundService.success) {
                    SoundService.success();
                }

                // ✅ Go to image capture for second serial
                this.proceedToImageCapture(2);
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
                if (
                    this.$refs.scanner &&
                    typeof this.$refs.scanner.showScanError === "function"
                ) {
                    this.$refs.scanner.showScanError(
                        "Invalid Location Format (use L###X, Floor, or L800G)"
                    );
                } else if (
                    this.$refs.scanner &&
                    typeof this.$refs.scanner.showError === "function"
                ) {
                    this.$refs.scanner.showError(
                        "Invalid Location Format (use L###X, Floor, or L800G)"
                    );
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
             console.log('\n🚀 ========== PROCESS SCAN STARTED ==========');
    console.log('Current State Before Processing:', {
        serialNumber: this.serialNumber,
        secondSerialNumber: this.secondSerialNumber,
        locationInput: this.locationInput,
        dualSerialProduct: this.dualSerialProduct,
        capturedImagesForSerial1Length: this.capturedImagesForSerial1.length,
        capturedImagesForSerial2Length: this.capturedImagesForSerial2.length,
        capturedImagesForSerial1Data: this.capturedImagesForSerial1.map((img, i) => ({
            index: i + 1,
            serial: img.serial,
            hasData: !!img.data,
            dataLength: img.data ? img.data.length : 0
        })),
        capturedImagesForSerial2Data: this.capturedImagesForSerial2.map((img, i) => ({
            index: i + 1,
            serial: img.serial,
            hasData: !!img.data,
            dataLength: img.data ? img.data.length : 0
        }))
    });

    try {
        // Use either the scanned code or input fields
        let scanSerial, scanSecondSerial, scanLocation, scanReturnId;
        scanReturnId = this.showReturnIdField ? this.returnId : null;

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
            if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showScanError === "function"
            ) {
                this.$refs.scanner.showScanError(
                    "Serial Number is required"
                );
            } else if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showError === "function"
            ) {
                this.$refs.scanner.showError(
                    "Serial Number is required"
                );
            }

            if (SoundService && SoundService.error) {
                SoundService.error();
            }
            this.focusNextField("serialNumberInput");
            return;
        }

        // Validate dual serial if detected and second serial input is visible
        if (
            this.dualSerialProduct &&
            this.showSecondSerialInput &&
            !scanSecondSerial
        ) {
            if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showScanError === "function"
            ) {
                this.$refs.scanner.showScanError(
                    `${this.secondSerialLabel} is required for this product`
                );
            } else if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showError === "function"
            ) {
                this.$refs.scanner.showError(
                    `${this.secondSerialLabel} is required for this product`
                );
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
            if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showScanError === "function"
            ) {
                this.$refs.scanner.showScanError(
                    "Invalid Location Format (use L###X, Floor, or L800G)"
                );
            } else if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showError === "function"
            ) {
                this.$refs.scanner.showError(
                    "Invalid Location Format (use L###X, Floor, or L800G)"
                );
            }

            if (SoundService && SoundService.error) {
                SoundService.error();
            }
            return;
        }

    // ✅ Prepare images data with serial index mapping
          const imageData = [];

            console.log('🎬 ========== IMAGE DATA PREPARATION ==========');
            console.log('📦 Preparing images:', {
                serial1Count: this.capturedImagesForSerial1.length,
                serial2Count: this.capturedImagesForSerial2.length,
                scanSerial: scanSerial,
                scanSecondSerial: scanSecondSerial
            });

            console.log('📸 capturedImagesForSerial1:', this.capturedImagesForSerial1);
            console.log('📸 capturedImagesForSerial2:', this.capturedImagesForSerial2);

            // ✅ Add images for first serial
            if (this.capturedImagesForSerial1.length > 0) {
                console.log(`Processing ${this.capturedImagesForSerial1.length} images for Serial 1: "${scanSerial}"`);
                
                this.capturedImagesForSerial1.forEach((img, index) => {
                    const imageEntry = {
                        data: img.data,
                        serialIndex: 1,
                        serial: scanSerial // Use the scanSerial from processScan
                    };
                    imageData.push(imageEntry);
                    console.log(`  ✅ Image ${index + 1} added for Serial 1 (${scanSerial}):`, {
                        hasData: !!imageEntry.data,
                        dataLength: imageEntry.data ? imageEntry.data.length : 0
                    });
                });
            } else {
                console.log('⚠️ No images for Serial 1');
            }

            // ✅ Add images for second serial (if exists)
            if (scanSecondSerial && scanSecondSerial !== "N/A" && scanSecondSerial.trim() !== "") {
                if (this.capturedImagesForSerial2.length > 0) {
                    console.log(`Processing ${this.capturedImagesForSerial2.length} images for Serial 2: "${scanSecondSerial}"`);
                    
                    this.capturedImagesForSerial2.forEach((img, index) => {
                        const imageEntry = {
                            data: img.data,
                            serialIndex: 2,
                            serial: scanSecondSerial // Use the scanSecondSerial from processScan
                        };
                        imageData.push(imageEntry);
                        console.log(`  ✅ Image ${index + 1} added for Serial 2 (${scanSecondSerial}):`, {
                            hasData: !!imageEntry.data,
                            dataLength: imageEntry.data ? imageEntry.data.length : 0
                        });
                    });
                } else {
                    console.warn('⚠️ No images captured for Serial 2 despite being dual serial');
                }
            } else {
                console.log('ℹ️ No second serial to process');
            }

            console.log('📊 Final Image Data Summary:', {
                totalImagesInArray: imageData.length,
                serial1Count: imageData.filter(i => i.serialIndex === 1).length,
                serial2Count: imageData.filter(i => i.serialIndex === 2).length,
                imagesBreakdown: imageData.map((img, i) => ({
                    index: i + 1,
                    serial: img.serial,
                    serialIndex: img.serialIndex,
                    hasData: !!img.data,
                    dataPreview: img.data ? img.data.substring(0, 50) + '...' : 'NO DATA'
                }))
            });
            console.log('========== END IMAGE DATA PREPARATION ==========\n');
        // Show loading state
        this.$refs.scanner.startLoading("Processing Return Scan");

        // If in single serial mode for a dual serial product, set a flag in scanData
        const singleSerialMode =
            this.dualSerialProduct && !this.showSecondSerialInput;

        // Track which serials are being used
        const scannedPrimarySerial =
            this.scannedSerialPosition === "primary"
                ? scanSerial
                : scanSecondSerial;
        const scannedSecondarySerial =
            this.scannedSerialPosition === "secondary"
                ? scanSerial
                : scanSecondSerial;

        // Send data to server
        const scanData = {
            ReturnId: scanReturnId || null,
            SerialNumber: scanSerial,
            SecondSerial: scanSecondSerial || null,
            Location: scanLocation || "L800G",
            Store: this.selectedStore,
            Images: imageData, // ✅ Include captured images with serial mapping
            SingleSerialMode: singleSerialMode,
            ProductID: this.productId,
            FNSKUviewer: this.fnskuViewer,
            OriginalLocation: this.originalProductLocation,
            ScannedSerialPosition: this.scannedSerialPosition,
            ScannedPrimarySerial: scannedPrimarySerial,
            ScannedSecondarySerial: scannedSecondarySerial,
        };

        console.log("Sending scan data:", scanData);

        // API CALL
        const response = await axios.post(
            `${API_BASE_URL}/api/returns/process-scan`,
            scanData,
            {
                withCredentials: true,
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content,
                },
            }
        );

        // Hide loading
        this.$refs.scanner.stopLoading();

        const data = response.data;

        if (data.success) {
            // Success case
            if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showScanSuccess === "function"
            ) {
                this.$refs.scanner.showScanSuccess(
                    data.message || "Return processed successfully"
                );
            } else if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showSuccess === "function"
            ) {
                this.$refs.scanner.showSuccess(
                    data.message || "Return processed successfully"
                );
            }

            if (SoundService && SoundService.successScan) {
                SoundService.successScan(true);
            }

            // ✅ Upload images to the newly created products
            if (data.createdItems && data.createdItems.length > 0 && imageData.length > 0) {
                console.log(`🔄 Uploading ${imageData.length} images to ${data.createdItems.length} products...`);
                
                try {
                    await this.uploadImagesToProducts(data.createdItems, imageData);
                    console.log('✅ All images uploaded successfully');
                } catch (uploadError) {
                    console.error('⚠️ Some images failed to upload:', uploadError);
                    // Don't fail the whole operation if images fail
                }
            }

            // Add to scan history if method exists
            if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.addSuccessScan === "function"
            ) {
                this.$refs.scanner.addSuccessScan({
                    ReturnID: scanReturnId || "N/A",
                    Serial: scanSerial,
                    SecondSerial: scanSecondSerial || "N/A",
                    Location: scanLocation || "Floor",
                    FNSKU: this.fnskuViewer || "N/A",
                    SingleSerialMode: singleSerialMode,
                    ImagesUploaded: imageData.length
                });
            }

            // Reset fields and dual serial flag
            this.clearScanFields();

            // Refresh inventory
            this.fetchInventory();
        } else {
            // Error case
            if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showScanError === "function"
            ) {
                this.$refs.scanner.showScanError(
                    data.message || "Error processing return"
                );
            } else if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showError === "function"
            ) {
                this.$refs.scanner.showError(
                    data.message || "Error processing return"
                );
            }

            if (SoundService && SoundService.scanRejected) {
                SoundService.scanRejected(true);
            }

            // Add to error scan history if method exists
            if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.addErrorScan === "function"
            ) {
                this.$refs.scanner.addErrorScan(
                    {
                        ReturnID: scanReturnId || "N/A",
                        Serial: scanSerial,
                        SecondSerial: scanSecondSerial || "N/A",
                        Location: scanLocation || "N/A",
                        FNSKU: this.fnskuViewer || "N/A",
                        SingleSerialMode: singleSerialMode,
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
            console.error(
                "CSRF token mismatch. Try refreshing the page."
            );
            if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showScanError === "function"
            ) {
                this.$refs.scanner.showScanError(
                    "Session expired. Please refresh the page and try again."
                );
            } else if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showError === "function"
            ) {
                this.$refs.scanner.showError(
                    "Session expired. Please refresh the page and try again."
                );
            }
        } else {
            if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showScanError === "function"
            ) {
                this.$refs.scanner.showScanError(
                    "Network or server error"
                );
            } else if (
                this.$refs.scanner &&
                typeof this.$refs.scanner.showError === "function"
            ) {
                this.$refs.scanner.showError("Network or server error");
            }
        }

        if (SoundService && SoundService.scanRejected) {
            SoundService.scanRejected(true);
        }

        // Add failed scan to history if method exists
        if (
            this.$refs.scanner &&
            typeof this.$refs.scanner.addErrorScan === "function"
        ) {
            this.$refs.scanner.addErrorScan(
                {
                    ReturnID: this.returnId || "N/A",
                    Serial: this.serialNumber || "",
                    SecondSerial: this.secondSerialNumber || "N/A",
                    Location: this.locationInput || "N/A",
                    FNSKU: this.fnskuViewer || "N/A",
                },
                error.response && error.response.status === 419
                    ? "session_expired"
                    : "network_error"
            );
        }
    }
},

// ✅ NEW: Upload images to created products
// ✅ ENHANCED: Upload images to created products with detailed logging
async uploadImagesToProducts(createdItems, imageData) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    
    console.log('🚀 ========== STARTING IMAGE UPLOAD ==========');
    console.log('📋 Upload Summary:', {
        totalCreatedItems: createdItems.length,
        totalImageData: imageData.length,
        csrfTokenExists: !!csrfToken
    });
    
    console.log('📦 Created Items Details:');
    createdItems.forEach((item, idx) => {
        console.log(`  Item ${idx + 1}:`, {
            id: item.id,
            serial: item.serial,
            rt: item.rt,
            location: item.location
        });
    });
    
    console.log('🖼️ Image Data Details:');
    imageData.forEach((img, idx) => {
        console.log(`  Image ${idx + 1}:`, {
            serial: img.serial,
            serialIndex: img.serialIndex,
            dataLength: img.data ? img.data.substring(0, 50) + '...' : 'NO DATA'
        });
    });
    
    // Process each created item
    for (let itemIdx = 0; itemIdx < createdItems.length; itemIdx++) {
        const item = createdItems[itemIdx];
        
        console.log(`\n🔍 ========== Processing Item ${itemIdx + 1}/${createdItems.length} ==========`);
        console.log(`ProductID: ${item.id}, Serial: ${item.serial}`);
        
        // Filter images for this specific serial
        const itemImages = imageData.filter(img => {
            const serialMatch = img.serial === item.serial;
            console.log(`  Comparing: img.serial="${img.serial}" vs item.serial="${item.serial}" = ${serialMatch}`);
            return serialMatch;
        });
        
        console.log(`✅ Found ${itemImages.length} images for ProductID ${item.id} (Serial: ${item.serial})`);
        
        if (itemImages.length === 0) {
            console.warn(`⚠️ SKIPPING: No images found for ProductID ${item.id} with serial "${item.serial}"`);
            continue;
        }
        
        console.log(`📤 Starting upload of ${itemImages.length} images to ProductID ${item.id}`);
        
        // Upload each image for this item
        for (let i = 0; i < Math.min(itemImages.length, 12); i++) {
            console.log(`\n  📸 Uploading Image ${i + 1}/${itemImages.length} to ProductID ${item.id}`);
            
            try {
                const uploadPayload = {
                    _token: csrfToken,
                    productId: item.id,
                    imageIndex: i,
                    imageData: itemImages[i].data,
                    isSerial: false,
                    serialIndex: 0
                };
                
                console.log(`  📤 Upload payload:`, {
                    productId: uploadPayload.productId,
                    imageIndex: uploadPayload.imageIndex,
                    isSerial: uploadPayload.isSerial,
                    serialIndex: uploadPayload.serialIndex,
                    dataLength: uploadPayload.imageData ? uploadPayload.imageData.substring(0, 50) + '...' : 'NO DATA'
                });
                
                const imageResponse = await axios.post(
                    `${API_BASE_URL}/api/images/upload`,
                    uploadPayload,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    }
                );

                console.log(`  ✅ SUCCESS: Image ${i + 1} uploaded to ProductID ${item.id}:`, imageResponse.data);
                
            } catch (imageError) {
                console.error(`  ❌ FAILED: Image ${i + 1} upload to ProductID ${item.id} failed:`, {
                    error: imageError.message,
                    response: imageError.response?.data,
                    status: imageError.response?.status
                });
                // Continue with next image even if one fails
            }
        }
        
        console.log(`✅ Completed processing ProductID ${item.id}`);
    }
    
    console.log('\n✅ ========== ALL IMAGE UPLOADS COMPLETED ==========\n');
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

            this.capturedImagesForSerial1 = [];
            this.capturedImagesForSerial2 = [];
            this.currentCaptureStep = 0;
            
            // ✅ FIXED: Clear scanner images
            if (this.$refs.scanner) {
                this.$refs.scanner.capturedImages = [];
            }
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
                const hasOpenDropdowns = Object.values(
                    this.serialDropdowns
                ).some((isOpen) => isOpen);
                if (hasOpenDropdowns) {
                    this.serialDropdowns = {};
                }
            }
        },

        closeDropdownsOnClickOutside(event) {
            // Check if click is outside any dropdown
            const isOutside = !event.target.closest(".serial-dropdown");
            if (isOutside) {
                this.serialDropdowns = {};
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
        // Configure axios
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;

        // Set CSRF token
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common["X-CSRF-TOKEN"] =
                token.getAttribute("content");
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
        window.addEventListener("resize", this.handleResize);

        // Initialize serialDropdowns
        this.inventory.forEach((_, index) => {
            this.$set(this.serialDropdowns, index, false);
        });

        // Close dropdowns when clicking outside
        document.addEventListener("click", this.closeDropdownsOnClickOutside);

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
        window.removeEventListener("resize", this.handleResize);
        document.removeEventListener(
            "click",
            this.closeDropdownsOnClickOutside
        );

        // Remove keyboard event listener for image modal
        if (this.handleKeyDown) {
            window.removeEventListener("keydown", this.handleKeyDown);
        }
    },
};
