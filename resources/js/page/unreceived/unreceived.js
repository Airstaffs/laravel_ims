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
            perPage: 10,
            selectAll: false,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,

            // Simplified scanner workflow - only tracking needed
            trackingNumber: "",
            trackingValid: false,
            trackingFound: false,
            productId: "",
            rtcounter: "",
            itemStatus: "", // Added for item status

            // For validation
            trackingNumberValid: true,

            // For auto verification
            autoVerifyTimeout: null,
            showManualInput: false,

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
            event.target.src = this.defaultImage;
            event.target.onerror = null;
        },

        openScannerModal() {
            this.$refs.scanner.openScannerModal();
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
                SoundService.error();
            } finally {
                this.loading = false;
            }
        },

        // Simplified tracking input handler
        handleTrackingInput(event) {
            this.validateTrackingNumber();

            // Auto verify after short delay when typing
            if (this.trackingNumberValid && this.trackingNumber.length >= 5) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    this.verifyAndProcessTracking();
                }, 500);
            }
        },

        validateTrackingNumber() {
            this.trackingNumberValid = this.trackingNumber.trim() !== "";
            if (!this.trackingNumberValid) {
                SoundService.error();
            }
            return this.trackingNumberValid;
        },

        // Combined verification and processing
        async verifyAndProcessTracking() {
            this.validateTrackingNumber();

            if (!this.trackingNumberValid) {
                this.$refs.scanner.showScanError(
                    "Please enter a valid tracking number"
                );
                SoundService.error();
                return;
            }

            // Start loading animation
            this.$refs.scanner.startLoading("Processing tracking number...");

            try {
                // Check if tracking exists in database
                const response = await axios.get(
                    `${API_BASE_URL}/api/unreceived/verify-tracking`,
                    {
                        params: { tracking: this.trackingNumber },
                    }
                );

                if (response.data.found) {
                    if (response.data.alreadyScanned) {
                        this.$refs.scanner.stopLoading();
                        SoundService.alreadyScanned();
                        this.$refs.scanner.showScanWarning(
                            `Item already scanned`
                        );
                        this.$refs.trackingInput.select();
                        return;
                    }

                    // Store product information including item status
                    this.productId = response.data.productId;
                    this.rtcounter = response.data.rtcounter;
                    this.itemStatus = response.data.itemStatus; // Store item status

                    // Immediately process the scan with auto-generated RPN and PRD
                    await this.processAutoScan();
                } else {
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanError(
                        "Tracking number not found in orders"
                    );
                    this.trackingFound = false;
                    SoundService.notFound();
                    this.$refs.trackingInput.select();
                }
            } catch (error) {
                this.$refs.scanner.stopLoading();
                console.error("Error verifying tracking:", error);
                this.$refs.scanner.showScanError(
                    "Error checking tracking number"
                );
                SoundService.error();
                this.$refs.trackingInput.select();
            }
        },

        // Auto process scan with generated RPN and PRD
        async processAutoScan() {
            try {
                if (!this.productId) {
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanError(
                        "Missing product ID, please try again"
                    );
                    SoundService.error();
                    return;
                }

                // Update loading message
                this.$refs.scanner.startLoading("Processing scan...");

                const csrfToken = document.querySelector(
                    'meta[name="csrf-token"]'
                ).content;

                // Auto-generate today's date for PRD
                const todayDate = this.todayDate();

                const scanData = {
                    _token: csrfToken,
                    trackingNumber: this.trackingNumber,
                    productId: this.productId,
                    rtcounter: this.rtcounter,
                    prdDate: todayDate, // Auto-generated
                    autoGenerate: true, // Flag to indicate auto-generation
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
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    }
                );

                const data = response.data;

                this.$refs.scanner.stopLoading();

                if (data.success) {
                    // Show success notification with item status
                    const successMessage = `${data.item} - Status: ${
                        this.itemStatus || "Unknown"
                    }`;
                    this.$refs.scanner.showScanSuccess(successMessage);
                    SoundService.successScan(true);

                    // Format PRD for display
                    const prdFormatted = data.prdGenerated || "PRD";

                    // Add to scan history with item status and CSS class
                    const statusDisplay = this.itemStatus || "Unknown";
                    const statusClass =
                        statusDisplay.toLowerCase() === "working"
                            ? "status-working"
                            : "status-error";

                    this.$refs.scanner.addSuccessScan({
                        Trackingnumber: this.trackingNumber,
                        RPN: data.rpnGenerated || "Auto-generated",
                        PRD: prdFormatted,
                        Status: statusDisplay,
                        StatusClass: statusClass, // Add CSS class for styling
                    });

                    // Reset and refresh
                    this.resetScannerState();
                    this.fetchInventory();
                } else {
                    this.$refs.scanner.showScanError(
                        data.message || "Error processing scan"
                    );
                    SoundService.scanRejected(true);

                    // Add to error scan history with CSS class
                    const statusDisplay = this.itemStatus || "Unknown";
                    const statusClass =
                        statusDisplay.toLowerCase() === "working"
                            ? "status-working"
                            : "status-error";

                    this.$refs.scanner.addErrorScan(
                        {
                            Trackingnumber: this.trackingNumber,
                            RPN: "Failed",
                            PRD: "Failed",
                            Status: statusDisplay,
                            StatusClass: statusClass, // Add CSS class for styling
                        },
                        data.reason || "error"
                    );

                    this.$nextTick(() => {
                        if (this.$refs.trackingInput) {
                            this.$refs.trackingInput.select();
                        }
                    });
                }
            } catch (error) {
                this.$refs.scanner.stopLoading();
                console.error("Error processing scan:", error);
                this.$refs.scanner.showScanError("Network or server error");
                SoundService.scanRejected(true);

                this.$nextTick(() => {
                    if (this.$refs.trackingInput) {
                        this.$refs.trackingInput.select();
                    }
                });
            }
        },

        // Reset scanner state
        resetScannerState() {
            this.trackingNumber = "";
            this.trackingFound = false;
            this.productId = "";
            this.rtcounter = "";
            this.itemStatus = "";

            if (this.autoVerifyTimeout) {
                clearTimeout(this.autoVerifyTimeout);
                this.autoVerifyTimeout = null;
            }

            this.$nextTick(() => {
                if (this.$refs.trackingInput) {
                    this.$refs.trackingInput.focus();
                }
            });
        },

        // Scanner event handlers
        handleScanProcess() {
            this.verifyAndProcessTracking();
        },

        handleHardwareScan(scannedCode) {
            this.trackingNumber = scannedCode;
            this.verifyAndProcessTracking();
        },

        handleScannerOpened() {
            console.log("Scanner opened");
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

        handleModeChange(event) {
            this.showManualInput = event.manual;
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

        changePerPage() {
            this.currentPage = 1;
            this.fetchInventory();
        },

        toggleAll() {
            this.inventory.forEach((item) => (item.checked = this.selectAll));
        },

        toggleDetails(index) {
            this.$set(this.expandedRows, index, !this.expandedRows[index]);
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
