import { eventBus } from "../../components/eventBus";
import ScannerComponent from "../../components/Scanner.vue";
import { SoundService } from "../../components/Sound_service";
import DetectSerialModal from "./modal-detect/modal-detect.vue";
import "../../../css/modules.css";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ReceivedModule",
    components: {
        ScannerComponent,
        DetectSerialModal, // Import the DetectSerialModal component
    },
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

            // Scanner workflow data
            currentStep: 1, // 1: Tracking, 2: Pass/Fail, 3: First SN, 4: Second SN, 5: PCN, 6: Basket
            trackingNumber: "",
            firstSerialNumber: "",
            secondSerialNumber: "",
            pcnNumber: "", // New PCN field
            basketNumber: "",
            trackingValid: false,
            trackingFound: false,
            productId: "",
            rtcounter: "", // Added rtcounter field
            status: "", // 'pass' or 'fail'

            // For validation
            trackingNumberValid: true,
            basketNumberValid: true,
            pcnNumberValid: true, // New validation field

            // For auto verification
            autoVerifyTimeout: null,
            showManualInput: false, // Track manual mode state

            defaultImage:
                "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4=",
            // Modal state
            showImageModal: false,
            modalImages: [],
            currentImageIndex: 0,
            showDetectSerialModal: false, // State for Detect Serial Numbers modal

            apiResult: {
                step3: { serials: [] },
                step4: { serials: [] },
            },

            showImageModal: false,
            modalImages: [],
            currentImageIndex: 0,
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

            productImages: [], // 👈 Array to hold product thumbnails for Step 2

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
            // priority: local preview -> server path -> default
            return (
                this.serialImageUrl ||
                this.serialImagePath ||
                this.defaultSerialImage
            );
        },
    },
    methods: {

        handleImageError(event) {
            // If image fails to load, use an inline SVG placeholder
            event.target.src = this.defaultImage;
            event.target.onerror = null; // Prevent infinite error loop
        },

        // Open scanner modal method - this will call the scanner component's method
        openScannerModal() {
            this.$refs.scanner.openScannerModal();
        },

        openDetectSerialModal() {
            this.showDetectSerialModal = true;
        },

        closeDetectSerialModal() {
            this.showDetectSerialModal = false;
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

        async openImageModal(item) {
            if (!item) return;

            this.item = {};
            this.activeIndex = 0;
            this.ProductTitle = "";

            this.isLoadingImages = true;

            try {
                await this.fetchItems();

                const freshItem = this.items.find(
                    (i) => i.itemnumber === item.itemnumber
                );
                const itemToUse = freshItem || item;

                console.log("Item to use:", itemToUse);
                console.log("Images found:", this.imageList.length);

                this.item = { ...itemToUse };
                this.ProductTitle = itemToUse.ProductTitle;

                console.log("Final imageList:", this.imageList);

                this.showImageModal = true;

                await this.$nextTick();
                document.body.style.overflow = "hidden";
            } catch (error) {
                console.error("Failed to fetch fresh item data:", error);
                this.openImageModalFallback(item);
            } finally {
                this.isLoadingImages = false;
            }
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

        prevImage() {
            if (this.activeIndex > 0) {
                this.activeIndex--;
            } else {
                this.activeIndex = this.imageList.length - 1; // Loop to end
            }
        },

        nextImage() {
            if (this.activeIndex < this.imageList.length - 1) {
                this.activeIndex++;
            } else {
                this.activeIndex = 0; // Loop to start
            }
        },

        // Fetch inventory data from the API
        async fetchInventory() {
            this.loading = true;
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/received/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            location: "Received",
                        },
                    }
                );

                this.inventory = response.data.data;
                this.totalPages = response.data.last_page;
            } catch (error) {
                console.error("Error fetching inventory data:", error);
                SoundService.error(); // Error sound for fetch failure
            } finally {
                this.loading = false;
            }
        },

        // Handle tracking input with auto verification in auto mode
        handleTrackingInput(event) {
            this.validateTrackingNumber();

            // In auto mode, automatically verify after short delay when typing
            if (
                !this.showManualInput &&
                this.trackingNumberValid &&
                this.trackingNumber.length >= 5
            ) {
                // Clear any existing timeout to avoid multiple calls
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                // Set new timeout for auto verification
                this.autoVerifyTimeout = setTimeout(() => {
                    this.verifyTrackingNumber();
                }, 500); // 500ms delay to let user finish typing
            }
        },

        // Validation method for tracking number
        validateTrackingNumber() {
            // Basic validation - can be enhanced as needed
            this.trackingNumberValid = this.trackingNumber.trim() !== "";
            if (!this.trackingNumberValid) {
                SoundService.error(); // Play error sound for invalid input
            }
            return this.trackingNumberValid;
        },

        // Validation method for basket number
        validateBasketNumber() {
            // Updated regex to support BKT, SH, or ENV prefixes
            const basketRegex = /^(BKT|SH|ENV)\d+$/i;
            this.basketNumberValid = basketRegex.test(this.basketNumber.trim());
            if (!this.basketNumberValid) {
                SoundService.error(); // Play error sound for invalid input
            }
            return this.basketNumberValid;
        },

        // Validation method for PCN
        validatePcnNumber() {
            if (this.pcnNumber.trim() === "N/A") {
                this.pcnNumberValid = true;
                return true;
            }

            const pcnRegex = /^PCN\d+$/i;
            this.pcnNumberValid = pcnRegex.test(this.pcnNumber.trim());
            if (!this.pcnNumberValid) {
                SoundService.error(); // Play error sound for invalid input
            }
            return this.pcnNumberValid;
        },

        // Step 1: Verify tracking number
        async verifyTrackingNumber() {
            this.validateTrackingNumber();

            if (!this.trackingNumberValid) {
                this.$refs.scanner.showScanError(
                    "Please enter a valid tracking number"
                );
                SoundService.error(); // Play error sound for invalid input
                return;
            }

            try {
                // Check if tracking exists in database
                const response = await axios.get(
                    `${API_BASE_URL}/api/received/verify-tracking`,
                    {
                        params: { tracking: this.trackingNumber },
                    }
                );

                if (response.data.found) {
                    // Tracking found in the database
                    this.trackingFound = true;

                    if (response.data.alreadyScanned) {
                        // Item has already been scanned
                        SoundService.alreadyScanned(); // Play already scanned sound

                        // Show warning notification for already scanned item (using our new method)
                        this.$refs.scanner.showScanWarning(
                            `Item already scanned`
                        );

                        // Focus back on tracking input for next scan
                        this.$refs.trackingInput.select();
                        return;
                    }

                    // Store the product ID and rtcounter received from the backend
                    this.productId = response.data.productId;
                    this.rtcounter = response.data.rtcounter; // Store rtcounter
                    // ✅ Load images for scanner (kept for internal scanner use)
                    this.$refs.scanner.loadProductThumbnails(
                        response.data.productDetails
                    );

                    // ✅ Build thumbnails for Step 2 preview
                    const basePath = "/images/thumbnails/";
                    const thumbnails = [];
                    const product = response.data.productDetails;

                    for (let i = 1; i <= 15; i++) {
                        const key = `img${i}`;
                        if (
                            product[key] &&
                            product[key] !== "NULL" &&
                            product[key].trim() !== ""
                        ) {
                            thumbnails.push({
                                src: basePath + product[key],
                                label: `Image ${i}`,
                            });
                        }
                    }

                    // Save thumbnails for Step 2 display
                    this.productImages = thumbnails;

                    // Move to Pass/Fail step
                    this.currentStep = 2;
                    SoundService.success();
                } else {
                    // Tracking not found
                    this.$refs.scanner.showScanError(
                        "Tracking number not found"
                    );
                    this.trackingFound = false;
                    SoundService.notFound(); // Play not found sound for missing
                    this.$refs.trackingInput.select();
                }
            } catch (error) {
                console.error("Error verifying tracking:", error);
                this.$refs.scanner.showScanError(
                    "Error checking tracking number"
                );
                SoundService.error(); // Play error sound for network/server errors
                this.$refs.trackingInput.select();
            }
        },

        // Step 2: Pass or fail the item
        passItem() {
            this.status = "pass";
            this.currentStep = 3; // Move to First Serial Number step
            SoundService.success(); // Success sound for pass action

            // Focus on the first serial number input
            this.$nextTick(() => {
                if (this.$refs.firstSerialInput) {
                    this.$refs.firstSerialInput.focus();
                }
            });
        },

        // For failed items - move to basket number entry but allow image capture
        failItem() {
            this.status = "fail";

            // Add a camera button for failed items
            this.$refs.scanner.showScanSuccess(
                "Item marked for failure - capture images if needed"
            );
            SoundService.error(true); // Error sound with vibration for fail action

            // Go to PCN step first instead of directly to basket
            this.currentStep = 5;

            // Focus on the PCN input
            this.$nextTick(() => {
                if (this.$refs.pcnInput) {
                    this.$refs.pcnInput.focus();
                }
            });
        },

        // Handle first serial number input
        handleFirstSerialInput() {
            // Auto-process in auto mode when input is valid
            if (
                !this.showManualInput &&
                this.firstSerialNumber.trim().length > 5
            ) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    this.processFirstSerial();
                }, 500);
            }
        },

        // Process first serial number
        async processFirstSerial() {
            if (!this.firstSerialNumber.trim()) {
                this.$refs.scanner.showScanError(
                    "Please enter a valid serial number"
                );
                SoundService.error(); // Error sound for invalid input
                this.$refs.firstSerialInput.select();
                return;
            }

            // Capture image for first serial
            //await this.captureSerialImage();
            SoundService.success(); // Success sound after capturing image

            // Move to second serial number step
            this.currentStep = 4;

            // Focus on the second serial number input
            this.$nextTick(() => {
                if (this.$refs.secondSerialInput) {
                    this.$refs.secondSerialInput.focus();
                }
            });
        },

        // Handle second serial number input
        handleSecondSerialInput() {
            // Auto-process in auto mode when input is valid
            if (
                !this.showManualInput &&
                this.secondSerialNumber.trim().length > 5
            ) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    this.processSecondSerial();
                }, 500);
            }
        },

        // Process second serial number
        async processSecondSerial() {
            if (!this.secondSerialNumber.trim()) {
                this.$refs.scanner.showScanError(
                    "Please enter a valid serial number"
                );
                SoundService.error(); // Error sound for invalid input
                this.$refs.secondSerialInput.select();
                return;
            }

            // Capture image for second serial
            //await this.captureSerialImage();
            SoundService.success(); // Success sound after capturing image

            // Move to PCN step
            this.currentStep = 5;

            // Focus on the PCN input
            this.$nextTick(() => {
                if (this.$refs.pcnInput) {
                    this.$refs.pcnInput.focus();
                }
            });
        },

        // Skip second serial number
        skipSecondSerial() {
            this.secondSerialNumber = "N/A"; // Mark as not applicable
            SoundService.success(); // Success sound for skip action

            // Move to PCN step
            this.currentStep = 5;

            // Focus on the PCN input
            this.$nextTick(() => {
                if (this.$refs.pcnInput) {
                    this.$refs.pcnInput.focus();
                }
            });
        },

        // Handle PCN input
        handlePcnInput() {
            // Auto-process in auto mode when input is valid
            if (!this.showManualInput && this.pcnNumber.trim().length > 4) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    this.processPcnNumber();
                }, 500);
            }
        },

        // Process PCN
        async processPcnNumber() {
            if (!this.validatePcnNumber()) {
                this.$refs.scanner.showScanError(
                    "PCN must start with PCN followed by numbers (e.g. PCN12345)"
                );
                SoundService.error(); // Error sound for invalid PCN
                this.$refs.pcnInput.select();
                return;
            }

            try {
                // First validate PCN format locally
                // Then check if PCN is already used in the database
                const pcnResponse = await axios.post(
                    `${API_BASE_URL}/api/received/validate-pcn`,
                    {
                        pcn: this.pcnNumber,
                    }
                );

                if (pcnResponse.data.alreadyUsed) {
                    // PCN already exists in the database
                    this.$refs.scanner.showScanWarning(
                        `${this.pcnNumber} is already in use`
                    );
                    SoundService.PCNalreadyUsed(); // Use the same sound as for already scanned items
                    this.$refs.pcnInput.select();
                    return;
                }

                // Capture image for PCN
                // await this.captureSerialImage();
                SoundService.success(); // Success sound after capturing PCN image

                // Move to basket number step
                this.currentStep = 6;

                // Focus on the basket number input
                this.$nextTick(() => {
                    if (this.$refs.basketInput) {
                        this.$refs.basketInput.focus();
                    }
                });
            } catch (error) {
                console.error("Error validating PCN:", error);
                this.$refs.scanner.showScanError("Error validating PCN");
                SoundService.error();
            }
        },

        // Handle basket number input
        handleBasketInput() {
            // Auto-process in auto mode when input is valid
            if (!this.showManualInput && this.basketNumber.trim().length > 3) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    this.processBasketNumber();
                }, 500);
            }
        },

        // Process basket number
        processBasketNumber() {
            if (!this.validateBasketNumber()) {
                this.$refs.scanner.showScanError(
                    "Basket number must start with BKT, SH, or ENV followed by numbers"
                );
                SoundService.error(); // Error sound for invalid basket
                this.$refs.basketInput.select();
                return;
            }

            // Submit data based on status (pass or fail)
            if (this.status === "fail") {
                this.submitFailedItem();
            } else {
                this.submitScanData();
            }
        },

        // Capture image of serial number
        async captureSerialImage() {
            // Use scanner component to capture image
            if (this.$refs.scanner && this.$refs.scanner.captureFromScanner) {
                try {
                    await this.$refs.scanner.captureFromScanner();
                    return true;
                } catch (error) {
                    console.error("Error capturing image:", error);
                    SoundService.error(); // Error sound for capture failure
                    return false;
                }
            }
            return false;
        },

        // Submit failed item data
        async submitFailedItem() {
            try {
                // Make sure we have a basket number
                if (!this.validateBasketNumber()) {
                    this.$refs.scanner.showScanError(
                        "Basket number must start with BKT, SH, or ENV followed by numbers"
                    );
                    SoundService.error(); // Error vibration for invalid basket
                    return;
                }

                if (!this.validatePcnNumber()) {
                    this.$refs.scanner.showScanError(
                        "PCN must start with PCN followed by numbers (e.g. PCN12345)"
                    );
                    SoundService.error(); // Error vibration for invalid PCN
                    return;
                }
                //loading animation
                this.$refs.scanner.startLoading("Processing Data");
                // Get images from scanner component
                const images = this.$refs.scanner.capturedImages.map(
                    (img) => img.data
                );
                const csrfToken = document.querySelector(
                    'meta[name="csrf-token"]'
                ).content;

                const failData = {
                    _token: csrfToken,
                    trackingNumber: this.trackingNumber,
                    status: "fail",
                    pcnNumber: this.pcnNumber, // Include PCN field
                    basketNumber: this.basketNumber,
                    productId: this.productId,
                    rtcounter: this.rtcounter, // Include rtcounter
                    Images: images,
                };

                const response = await axios.post(
                    `${API_BASE_URL}/api/received/process-scan`,
                    failData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    }
                );

                if (response.data.success) {
                    //clear fetch delivered item image
                    this.$refs.scanner.clearProductThumbnails();
                    //stop loading animation
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanSuccess("Item marked as failed");
                    SoundService.successScan(true); // Use successscan sound for final submission

                    // Add to scan history
                    this.$refs.scanner.addSuccessScan({
                        trackingnumber: this.trackingNumber,
                        status: "fail",
                        pcn: this.pcnNumber,
                        basket: this.basketNumber,
                    });

                    // Clear all captured images if requested by the server
                    if (response.data.clearImages) {
                        this.$refs.scanner.capturedImages = [];
                    }

                    // Reset scanner state
                    this.resetScannerState();

                    // Refresh inventory
                    this.fetchInventory();
                } else {
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanError(
                        response.data.message || "Error processing scan"
                    );
                    SoundService.scanRejected(true); // Use scanrejected sound for submission error
                }
            } catch (error) {
                console.error("Error submitting failed item:", error);
                SoundService.scanRejected(true); // Use scanrejected sound for submission error

                // Enhanced error handling
                if (error.response && error.response.status === 422) {
                    console.log("Validation errors:", error.response.data);
                    if (error.response.data.errors) {
                        const errorMessages = [];
                        Object.keys(error.response.data.errors).forEach(
                            (field) => {
                                errorMessages.push(
                                    `${field}: ${error.response.data.errors[
                                        field
                                    ].join(", ")}`
                                );
                            }
                        );
                        const errorMsg = errorMessages.join("\n");
                        this.$refs.scanner.showScanError(
                            `Validation error: ${errorMsg}`
                        );
                    } else {
                        this.$refs.scanner.showScanError(
                            "Validation failed. Please check your inputs."
                        );
                    }
                } else {
                    this.$refs.scanner.showScanError("Network or server error");
                }
            }
        },

        // Submit complete scan data
        async submitScanData() {
            try {
                // Get CSRF token
                const csrfToken = document.querySelector(
                    'meta[name="csrf-token"]'
                ).content;
                //loading animation
                this.$refs.scanner.startLoading("Processing Data...");
                // Create data without images first
                const scanData = {
                    _token: csrfToken,
                    trackingNumber: this.trackingNumber,
                    status: "pass",
                    firstSerialNumber: this.firstSerialNumber,
                    secondSerialNumber: this.secondSerialNumber,
                    pcnNumber: this.pcnNumber,
                    basketNumber: this.basketNumber,
                    productId: this.productId,
                    rtcounter: this.rtcounter,
                    // No images in this initial request
                };

                // Debug: Log the data being sent
                console.log("Submitting scan data (without images):", scanData);

                // Send data to API
                const response = await axios.post(
                    `${API_BASE_URL}/api/received/process-scan`,
                    scanData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    }
                );

                if (response.data.success) {
                    // If basic data submission was successful, now upload images one by one
                    const images = this.$refs.scanner.capturedImages.map(
                        (img) => img.data
                    );
                    if (images.length > 0) {
                        const hasSerialTwo = this.secondSerialNumber !== "N/A";
                        const hasPcn = this.pcnNumber !== "N/A";

                        // Upload each image separately
                        // Upload each image separately
                        for (let i = 0; i < images.length; i++) {
                            try {
                                // Determine step and serial flags
                                let isSerial = false;
                                let serialIndex = 0;

                                // 👇 Step 3 and 4 are serial images (you can track these via currentStep stored in img.step)
                                const imgStep =
                                    this.$refs.scanner.capturedImages[i]
                                        ?.step || 0;

                                if (imgStep === 3) {
                                    isSerial = true;
                                    serialIndex = 1;
                                } else if (imgStep === 4) {
                                    isSerial = true;
                                    serialIndex = 2;
                                }

                                // Upload image with serial info
                                const imageResponse = await axios.post(
                                    `${API_BASE_URL}/api/images/upload`,
                                    {
                                        _token: csrfToken,
                                        productId: this.productId,
                                        imageIndex: i,
                                        imageData: images[i],
                                        isSerial: isSerial,
                                        serialIndex: serialIndex,
                                    },
                                    {
                                        withCredentials: true,
                                        headers: {
                                            "Content-Type": "application/json",
                                            Accept: "application/json",
                                            "X-CSRF-TOKEN": csrfToken,
                                        },
                                    }
                                );

                                console.log(
                                    `Image ${i} uploaded:`,
                                    imageResponse.data
                                );
                            } catch (imageError) {
                                console.error(
                                    `Error uploading image ${i}:`,
                                    imageError
                                );
                            }
                        }
                    }
                    //clear fetch delivered item image
                    this.$refs.scanner.clearProductThumbnails();
                    //stop loading animation
                    this.$refs.scanner.stopLoading();
                    // Show success notification
                    this.$refs.scanner.showScanSuccess(
                        "Item received successfully"
                    );
                    SoundService.successScan(true);

                    // Add to scan history
                    this.$refs.scanner.addSuccessScan({
                        Trackingnumber: this.trackingNumber,
                        FirstSN: this.firstSerialNumber,
                        SecondSN: this.secondSerialNumber,
                        PCN: this.pcnNumber,
                        Basket: this.basketNumber, // Fixed property name
                    });
                    // Clear captured images
                    this.$refs.scanner.capturedImages = [];

                    // Reset workflow
                    this.resetScannerState();

                    // Refresh inventory
                    this.fetchInventory();
                } else {
                    this.$refs.scanner.stopLoading();
                    // Show error notification
                    this.$refs.scanner.showScanError(
                        response.data.message || "Error processing scan"
                    );
                    SoundService.scanRejected(true);

                    // Add to error scan history
                    this.$refs.scanner.addErrorScan(
                        {
                            Trackingnumber: this.trackingNumber,
                            FirstSN: this.firstSerialNumber,
                            SecondSN: this.secondSerialNumber,
                            PCN: this.pcnNumber,
                            Basket: this.basketNumber, // Fixed property name
                        },
                        response.data.reason || "error"
                    );
                }
            } catch (error) {
                console.error("Error submitting scan:", error);
                SoundService.scanRejected(true);

                // Enhanced error handling for validation errors
                if (error.response && error.response.status === 422) {
                    console.log("Validation errors:", error.response.data);
                    if (error.response.data.errors) {
                        const errorMessages = [];
                        Object.keys(error.response.data.errors).forEach(
                            (field) => {
                                errorMessages.push(
                                    `${field}: ${error.response.data.errors[
                                        field
                                    ].join(", ")}`
                                );
                            }
                        );
                        const errorMsg = errorMessages.join("\n");
                        this.$refs.scanner.showScanError(
                            `Validation error: ${errorMsg}`
                        );
                    } else {
                        this.$refs.scanner.showScanError(
                            "Validation failed. Please check your inputs."
                        );
                    }
                } else if (error.response && error.response.status === 403) {
                    this.$refs.scanner.showScanError(
                        "Permission denied. Please try again or contact support."
                    );
                } else {
                    this.$refs.scanner.showScanError("Network or server error");
                }
            }
        },

        // Reset scanner state
        resetScannerState() {
            // Reset all data
            this.currentStep = 1;
            this.trackingNumber = "";
            this.firstSerialNumber = "";
            this.secondSerialNumber = "";
            this.pcnNumber = ""; // Reset PCN
            this.basketNumber = "";
            this.trackingValid = false;
            this.trackingFound = false;
            this.productId = "";
            this.rtcounter = ""; // Reset rtcounter
            this.status = "";

            // 🧼 Also reset uploader
            this.resetUploader();

            // Clear the product thumbnails
            if (
                this.$refs.scanner &&
                this.$refs.scanner.clearProductThumbnails
            ) {
                this.$refs.scanner.clearProductThumbnails();
            }

            // Clear any pending auto-verify timeouts
            if (this.autoVerifyTimeout) {
                clearTimeout(this.autoVerifyTimeout);
                this.autoVerifyTimeout = null;
            }

            // Focus back on tracking input
            this.$nextTick(() => {
                if (this.$refs.trackingInput) {
                    this.$refs.trackingInput.focus();
                }
            });
        },

        // Scanner event handlers
        handleScanProcess() {
            // Process based on current step
            switch (this.currentStep) {
                case 1:
                    this.verifyTrackingNumber();
                    break;
                case 3:
                    this.processFirstSerial();
                    break;
                case 4:
                    this.processSecondSerial();
                    break;
                case 5:
                    this.processPcnNumber(); // Handle PCN scan
                    break;
                case 6:
                    this.processBasketNumber();
                    break;
            }
        },

        // Handle hardware scanner input
        handleHardwareScan(scannedCode) {
            // Determine which step we're on and handle the scan accordingly
            switch (this.currentStep) {
                case 1:
                    this.trackingNumber = scannedCode;
                    this.verifyTrackingNumber();
                    break;
                case 3:
                    this.firstSerialNumber = scannedCode;
                    this.processFirstSerial();
                    break;
                case 4:
                    this.secondSerialNumber = scannedCode;
                    this.processSecondSerial();
                    break;
                case 5:
                    this.pcnNumber = scannedCode; // Scan PCN
                    this.processPcnNumber();
                    break;
                case 6:
                    this.basketNumber = scannedCode;
                    this.processBasketNumber();
                    break;
            }
        },

        // Handle mode changes
        handleModeChange(event) {
            this.showManualInput = event.manual;
        },

        // Scanner opened event
        handleScannerOpened() {
            console.log("Scanner opened");
            // Get current mode from scanner component
            this.showManualInput = this.$refs.scanner.showManualInput;
            this.resetScannerState();
        },

        // Scanner closed event
        handleScannerClosed() {
            console.log("Scanner closed");
            this.fetchInventory();
            this.resetUploader(); // 🧼 clear uploader
        },

        // Scanner reset event
        handleScannerReset() {
            console.log("Scanner reset");
            this.resetScannerState();
            this.resetUploader(); // 🧼 clear uploader

        },

        // Pagination methods
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
            this.$set(this.expandedRows, index, !this.expandedRows[index]);
        },

        // Sort column
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = column;
                this.sortOrder = "asc";
            }
        },

        // Add the missing method for toggleDetailsVisibility
        toggleDetailsVisibility() {
            this.showDetails = !this.showDetails;
        },

        // Change rows per page
        changePerPage() {
            this.currentPage = 1;
            this.fetchInventory();
        },
        // 🧼 Reset uploader state (for Steps 3 & 4)

        resetUploader() {
            this.imageUrl = null;
            this.croppedImage = null;
            this.isDragging = false;
            this.apiResult.step3.serials = [];
            this.apiResult.step4.serials = [];
        },

        saveSerial(serialText, index) {
            if (this.currentStep === 3) {
                // 🧠 Save first serial
                this.firstSerialNumber = serialText;
                this.$refs.scanner.showScanSuccess(
                    `✅ Saved Serial #1: ${serialText}`
                );

                // 🧹 Clear OCR results
                // this.apiResult = { serials: [] };
                // this.apiResult.step3.serials = [];
                this.apiResult.step3.serials.splice(0);

                // 🧼 Reset uploader state for next upload
                // 🧼 Reset uploader
                this.resetUploader();

                // 🧹 Reset API result for step 3
                // this.apiResult.step3 = { serials: [] };

                // 🚀 Act as if user pressed Enter
                this.$nextTick(() => {
                    if (typeof this.processFirstSerial === "function") {
                        this.processFirstSerial();
                    }
                });
            } else if (this.currentStep === 4) {
                // 🧠 Save second serial
                this.secondSerialNumber = serialText;
                this.$refs.scanner.showScanSuccess(
                    `✅ Saved Serial #2: ${serialText}`
                );

                // 🧹 Clear OCR results
                // this.apiResult = { serials: [] };
                // this.apiResult.step4 = { serials: [] };
                // this.apiResult.step4.serials = [];
                this.apiResult.step4.serials.splice(0);

                // 🧼 Reset uploader state for next upload
                // 🧼 Reset uploader
                this.resetUploader();

                // 🚀 Act as if user pressed Enter
                this.$nextTick(() => {
                    if (typeof this.processSecondSerial === "function") {
                        this.processSecondSerial();
                    }
                });
            }
        },
        openImageModalFromPreview(index) {
            // Populate modalImages with productImages src values
            this.modalImages = this.productImages.map((img) => img.src);
            this.currentImageIndex = index;
            this.showImageModal = true;

            document.body.style.overflow = "hidden"; // Prevent scroll
        },

        async openEditModal(item) {
            if (!item) return;

            console.log(item);

            const freshItem = this.items.find(
                (i) => i.itemnumber === item.itemnumber
            );
            this.item = { ...(freshItem || item) };

            this.showEditModal = true;
            document.body.style.overflow = "hidden";

            // If you want to proactively load any existing serial image for this item:
            await this.$nextTick();
            await this.fetchSerialImageIfAny?.(); // safe if you added this earlier
        },

        closeEditModal() {
            this.showEditModal = false;

            // Reset image state on close too
            this.resetSerialImage({ clearServer: true });

            setTimeout(() => {
                document.body.style.overflow = "auto";
            }, 300); // match your animation
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
                const response = await axios.get("/api/unreceived/products");
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
        // ---------------------
        // 📸 Image Upload Section (for Step 3 and Step 4)
        // ---------------------
        triggerFileInput() {
        this.$refs.fileInput.click();
        },

        onFileChange(event) {
        const file = event.target.files[0];
        if (file) {
            this.handleImageUpload(file);
        }
        },

        handleDrop(event) {
        this.isDragging = false;
        const file = event.dataTransfer.files[0];
        if (file) {
            this.handleImageUpload(file);
        }
        },

        async handleImageUpload(file) {
        if (!file.type.startsWith("image/")) {
            alert("Please upload a valid image file.");
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            this.imageUrl = e.target.result;
        };
        reader.readAsDataURL(file);

        const formData = new FormData();
        // ✅ Match scanner logic
        formData.append("file", file, file.name || "upload.jpg");

        const baseURL =
            window.location.hostname === "localhost" ||
            window.location.hostname === "127.0.0.1"
            ? "http://127.0.0.1:8001"
            : "/fastapi";

        this.loading = true;
        try {
            const response = await fetch(`${baseURL}/detect`, {
            method: "POST",
            body: formData,
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();
            console.log("🔍 Serial detection result:", result);

            // ✅ Save to correct step result
            if (this.currentStep === 3) {
            this.apiResult.step3 = result;
            } else if (this.currentStep === 4) {
            this.apiResult.step4 = result;
            }

            // ✅ Auto-fill detected serial if found
            if (result.serials && result.serials.length > 0) {
            const detectedSerial = result.serials[0];
            if (this.currentStep === 3) {
                this.firstSerialNumber = detectedSerial;
                this.$refs.scanner?.showScanSuccess?.(`✅ Serial #1 detected: ${detectedSerial}`);
            } else if (this.currentStep === 4) {
                this.secondSerialNumber = detectedSerial;
                this.$refs.scanner?.showScanSuccess?.(`✅ Serial #2 detected: ${detectedSerial}`);
            }
            } else {
            this.$refs.scanner?.showScanWarning?.("⚠️ No serials detected in image.");
            }

        } catch (error) {
            console.error("Upload error:", error);
            this.$refs.scanner?.showScanError?.("❌ Failed to detect serial number.");
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
        axios.defaults.baseURL = window.location.origin;
        this.fetchInventory();
    },
};
