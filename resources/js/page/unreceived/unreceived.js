import { eventBus } from "../../components/eventBus";
import ScannerComponent from "../../components/Scanner.vue";
import { SoundService } from "../../components/Sound_service";
import "../../../css/modules.css";
import "./unreceived.css";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "UnreceivedModule",
    components: {
        ScannerComponent,
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
            currentStep: 1, // 1: Tracking, 2: RPN, 3: PRD
            trackingNumber: "",
            rpnNumber: "",
            prdDate: "",
            trackingValid: false,
            trackingFound: false,
            productId: "",
            rtcounter: "", // Added rtcounter field

            // For validation
            trackingNumberValid: true,

            // For auto verification
            autoVerifyTimeout: null,
            showManualInput: false, // Track manual mode state

            defaultImage:
                "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4=",
            // Modal state
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

            serialImageFile: null,
            serialImageUrl: "", // local preview via FileReader
            serialImagePath: "", // server URL if existing or after upload
            serialImageError: "",
            serialImageUploading: false,
            uploadProgress: 0,
            defaultSerialImage:
                "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4=", // <-- put your placeholder file here
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

            // Image field names in your data (img1 through img15)
            const imageFields = [
                "img2",
                "img3",
                "img4",
                "img5",
                "img6",
                "img7",
                "img8",
                "img9",
                "img10",
                "img11",
                "img12",
                "img13",
                "img14",
                "img15",
            ];

            // Loop through all possible image fields and add non-empty ones
            imageFields.forEach((field) => {
                if (
                    item[field] &&
                    item[field] !== "NULL" &&
                    item[field].trim() !== ""
                ) {
                    // Use the direct image field value as the path
                    const imagePath = `/images/thumbnails/${item[field]}`;
                    this.modalImages.push(imagePath);
                }
            });

            // If no images were found, add a default image
            if (this.modalImages.length === 0) {
                const defaultPath = `/images/thumbnails/${item.ProductID}.jpg`;
                this.modalImages.push(defaultPath);
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

        // Format today's date as YYYY-MM-DD for date input min attribute
        todayDate() {
            const today = new Date();
            return today.toISOString().split("T")[0];
        },

        async fetchInventory() {
            this.loading = true;
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/unreceived/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            location: "Orders",
                        },
                    }
                );

                this.inventory = response.data.data;
                this.totalPages = response.data.last_page;
            } catch (error) {
                console.error("Error fetching inventory data:", error);
                SoundService.error(); // Error vibration for fetch failure
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
                SoundService.error(); // Error vibration for invalid input
            }
            return this.trackingNumberValid;
        },

        // Handle PRD date change - auto submit in auto mode
        handlePrdDateChange(event) {
            // In auto mode, when date is selected, automatically submit
            if (!this.showManualInput && this.prdDate) {
                SoundService.success(); // Success sound for date selection
                this.submitScan();
            }
        },

        // Handle Today button click - set today's date and auto submit in auto mode
        handleTodayButtonClick() {
            this.prdDate = this.todayDate();
            SoundService.success(); // Success sound for today button

            // In auto mode, automatically submit
            if (!this.showManualInput) {
                setTimeout(() => {
                    this.submitScan();
                }, 100); // Small delay to ensure prdDate is set
            }
        },

        // Mode change handler
        handleModeChange(event) {
            this.showManualInput = event.manual;

            // When switching modes, clear the PRD field if we're in step 3
            if (this.currentStep === 3) {
                this.prdDate = "";
            }
        },

        // Step navigation
        async verifyTrackingNumber() {
            // Tracking found in the database
            this.validateTrackingNumber();

            if (!this.trackingNumberValid) {
                this.$refs.scanner.showScanError(
                    "Please enter a valid tracking number"
                );
                SoundService.error(); // Error vibration for invalid tracking
                return;
            }

            try {
                // Check if tracking exists in database
                const response = await axios.get(
                    `${API_BASE_URL}/api/unreceived/verify-tracking`,
                    {
                        params: { tracking: this.trackingNumber },
                    }
                );

                if (response.data.found) {
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

                    // Get next RPN number from backend
                    const rpnResponse = await axios.get(
                        `${API_BASE_URL}/api/unreceived/get-next-rpn`
                    );
                    this.rpnNumber =
                        rpnResponse.data.rpn ||
                        `RPN${Math.floor(Math.random() * 100000)}`; // Fallback for testing

                    // Move to RPN step
                    this.currentStep = 2;
                    SoundService.success(); // Success sound for found tracking
                } else {
                    // Tracking not found
                    this.$refs.scanner.showScanError(
                        "Tracking number not found in orders"
                    );
                    this.trackingFound = false;
                    SoundService.notFound(); // Not found sound for missing tracking
                    this.$refs.trackingInput.select();
                }
            } catch (error) {
                console.error("Error verifying tracking:", error);
                this.$refs.scanner.showScanError(
                    "Error checking tracking number"
                );
                SoundService.error(); // Error vibration for network/server error
                this.$refs.trackingInput.select();
            }
        },

        // Move from RPN to PRD step
        goToNextStep() {
            if (this.currentStep === 2) {
                this.currentStep = 3;
                SoundService.success(); // Success sound for next step

                // In auto mode, don't set a default date - wait for user to select
                if (this.showManualInput) {
                    this.prdDate = this.todayDate(); // Only set default in manual mode
                } else {
                    this.prdDate = ""; // Keep it blank in auto mode
                }
            }
        },

        // Set today's date for PRD
        setTodayDate() {
            this.prdDate = this.todayDate();
            SoundService.success(); // Success sound for today date
        },

        // Submit the scan data
        async submitScan() {
            if (!this.prdDate) {
                this.$refs.scanner.showScanError("Please select a PRD date");
                SoundService.error(); // Error vibration for missing date
                return;
            }

            if (!this.productId) {
                this.$refs.scanner.showScanError(
                    "Missing product ID, please verify tracking number again"
                );
                SoundService.error(); // Error vibration for missing product ID
                return;
            }

            //loading animation
            this.$refs.scanner.startLoading("Processing Data");

            try {
                // Prepare the scan data
                const csrfToken = document.querySelector(
                    'meta[name="csrf-token"]'
                ).content;

                const scanData = {
                    _token: csrfToken, // Add CSRF token here
                    trackingNumber: this.trackingNumber,
                    rpnNumber: this.rpnNumber,
                    prdDate: this.prdDate,
                    productId: this.productId,
                    rtcounter: this.rtcounter,
                };

                // Get images from scanner component
                const images = this.$refs.scanner.capturedImages.map(
                    (img) => img.data
                );

                // Send data to API
                const response = await axios.post(
                    `${API_BASE_URL}/api/unreceived/process-scan`,
                    {
                        ...scanData,
                        Images: images,
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": csrfToken, // Also add it to headers
                        },
                    }
                );

                const data = response.data;

                if (data.success) {
                    //stop loading animation
                    this.$refs.scanner.stopLoading();
                    // Show success notification
                    this.$refs.scanner.showScanSuccess(
                        data.item || "Item received successfully"
                    );
                    SoundService.successScan(true); // Play special success sound with

                    const dateParts = this.prdDate.split("-");
                    const prdFormatted =
                        dateParts.length === 3
                            ? `PRD${dateParts[1]}${
                                  dateParts[2]
                              }${dateParts[0].substring(2)}`
                            : "PRD";

                    // Add to scan history with detailed information
                    this.$refs.scanner.addSuccessScan({
                        Trackingnumber: this.trackingNumber,
                        RPN: this.rpnNumber,
                        PRD: prdFormatted,
                    });

                    // Reset workflow
                    this.resetScannerState();

                    // Refresh inventory
                    this.fetchInventory();
                } else {
                    // Show error notification
                    this.$refs.scanner.showScanError(
                        data.message || "Error processing scan"
                    );
                    SoundService.scanRejected(true); // Play special error sound with vibration

                    const dateParts = this.prdDate.split("-");
                    const prdFormatted =
                        dateParts.length === 3
                            ? `PRD${dateParts[1]}${
                                  dateParts[2]
                              }${dateParts[0].substring(2)}`
                            : "PRD";

                    // Add to error scan history with detailed information
                    this.$refs.scanner.addErrorScan(
                        {
                            Trackingnumber: this.trackingNumber,
                            RPN: this.rpnNumber,
                            PRD: prdFormatted,
                        },
                        data.reason || "error"
                    );

                    // Auto-select the tracking input text for quick rescanning
                    this.$nextTick(() => {
                        if (
                            this.currentStep === 1 &&
                            this.$refs.trackingInput
                        ) {
                            this.$refs.trackingInput.select(); // Select all text in tracking input
                        } else if (this.currentStep === 3) {
                            // For date inputs, we might need a different approach
                            const dateInput =
                                document.querySelector(".date-input");
                            if (dateInput) dateInput.focus();
                        }
                    });
                }
            } catch (error) {
                console.error("Error submitting scan:", error);
                this.$refs.scanner.showScanError("Network or server error");
                SoundService.scanRejected(true); // Play special error sound with vibration

                // Auto-select the tracking input text for quick rescanning
                this.$nextTick(() => {
                    if (this.currentStep === 1 && this.$refs.trackingInput) {
                        this.$refs.trackingInput.select(); // Select all text in tracking input
                    }
                });
            }
        },

        // Reset scanner state
        resetScannerState() {
            // Reset the scanner workflow to initial state
            this.currentStep = 1;
            this.trackingNumber = "";
            this.rpnNumber = "";
            this.prdDate = "";
            this.trackingFound = false;
            this.productId = "";
            this.rtcounter = ""; // Reset rtcounter

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
            if (this.currentStep === 1) {
                this.verifyTrackingNumber();
            } else if (this.currentStep === 3) {
                this.submitScan();
            }
        },

        handleHardwareScan(scannedCode) {
            // For hardware scanner, assume it's always a tracking number
            if (this.currentStep === 1) {
                this.trackingNumber = scannedCode;
                this.verifyTrackingNumber();
            }
        },

        handleScannerOpened() {
            console.log("Scanner opened");
            // Get current mode from scanner component
            this.showManualInput = this.$refs.scanner.showManualInput;
            this.resetScannerState();
        },

        handleScannerClosed() {
            console.log("Scanner closed");
            this.fetchInventory();
        },

        handleScannerReset() {
            console.log("Scanner reset");
            this.resetScannerState();
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

        // Add the missing method for toggleDetailsVisibility
        toggleDetailsVisibility() {
            this.showDetails = !this.showDetails;
        },

        async openEditModal(item) {
            if (!item) return;

            console.log(item);

            const freshItem = this.items.find(
                (i) => i.itemnumber === item.itemnumber
            );
            this.item = { ...(freshItem || item) };

            // Reset image state when opening
            this.resetSerialImage({ clearServer: true });

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
                !/^BKT\d+$/i.test(this.item.basketnumber)
            ) {
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

            // Normalize and duplicate-check serial number
            if (this.item.serialnumber != null) {
                this.item.serialnumber =
                    String(this.item.serialnumber).toUpperCase().trim() || null;
            }

            if (this.item.serialnumber) {
                const sn = this.item.serialnumber;
                const dup = this.items.find(
                    (p) =>
                        (p.serialnumber || "").toUpperCase().trim() === sn &&
                        p.itemnumber !== this.item.itemnumber
                );
                if (dup) {
                    this.loading = false;
                    await Swal.fire({
                        icon: "error",
                        title: "Duplicate Serial Number",
                        text: `The serial number "${sn}" is already used by another product.`,
                        confirmButtonText: "OK",
                    });
                    return;
                }
            }

            try {
                // 🔼 NEW: If user selected a serial image, upload it first.
                if (this.serialImageFile && !this.serialImageUploading) {
                    await this.uploadSerialImage();

                    // If the upload function set an error, abort the save.
                    if (this.serialImageError) {
                        this.loading = false;
                        return; // The uploadSerialImage already showed an alert.
                    }
                }

                const payload = {
                    ...this.item,
                    // Optional: include the stored path from the upload (rename to what your API expects)
                    ...(this.serialImagePath
                        ? { serial_image: this.serialImagePath }
                        : {}),
                    _token: document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute("content"),
                };

                const response = await axios.post(
                    "/api/unreceived/products",
                    payload
                );
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
                    text:
                        response.data.message ||
                        "The unreceived product has been saved successfully.",
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
                    "An error occurred while saving. Please check the input or try again later.";

                if (error.response?.status === 422) {
                    const err = error.response.data;
                    if (err?.errors?.serialnumber?.length) {
                        message = err.errors.serialnumber.join("\n");
                    } else if (
                        typeof err?.message === "string" &&
                        err.message
                    ) {
                        message = err.message;
                    } else if (err?.errors) {
                        message = Object.values(err.errors).flat().join("\n");
                    }
                } else if (error.response?.data?.message) {
                    message = error.response.data.message;
                }

                await Swal.fire({
                    icon: "error",
                    title: "Save Failed",
                    text: message,
                    confirmButtonText: "OK",
                });
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
                    "/api/unreceived/check-duplicate-serial",
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
                const { data } = await axios.get(
                    "/api/unreceived/serial-image",
                    {
                        params: { serial_number: serial },
                    }
                );
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
                    "/api/unreceived/serial-image",
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
        axios.defaults.baseURL = window.location.origin;
        this.fetchInventory();
        this.fetchSerialImageIfAny();
    },
};
