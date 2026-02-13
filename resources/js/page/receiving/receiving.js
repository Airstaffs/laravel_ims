import { eventBus } from "../../components/eventbus";
import ScannerComponent from "../../components/Scanner.vue";
import { SoundService } from "../../components/Sound_service";
import DetectSerialModal from "./modal-detect/modal-detect.vue";
import "../../../css/modules.css";
import { DEFAULT_IMAGE } from "../../constant";

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ReceivedModule",
    components: {
        ScannerComponent,
        DetectSerialModal,
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

            // Scanner workflow data
            currentStep: 1,
            trackingNumber: "",
            firstSerialNumber: "",
            secondSerialNumber: "",
            pcnNumber: "",
            basketNumber: "",
            trackingValid: false,
            trackingFound: false,
            productId: "",
            rtcounter: "",
            status: "",

            existingTrackingImgs: { trackingimg1: null, trackingimg2: null },
            
            // ✅ NEW: Track quantity info for splitting
            originalQuantity: 1,
            remainingQuantity: 1,

            // For validation
            trackingNumberValid: true,
            basketNumberValid: true,
            pcnNumberValid: true,

            // 🔥 NEW — rescan support
            trackingSource: null,      // 'Received' | 'Labeling' | 'Validation' | 'Reconciliation'
            isRescan: false,
            requireTrackingImage: false,

            // For auto verification
            autoVerifyTimeout: null,
            showManualInput: false,

            defaultImage: DEFAULT_IMAGE,
            
            // Modal state
            showImageModal: false,
            modalImages: [],
            currentImageIndex: 0,
            showDetectSerialModal: false,

            apiResult: {
                step3: { serials: [] },
                step4: { serials: [] },
            },

            showSecondSerialInput: false,

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
            error: null,

            productImages: [],
            
            // Image upload
            imageUrl: null,
            croppedImage: null,
            isDragging: false,
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

        qty() {
            return Number(this.item.quantity) || 0;
        },
        price() {
            return Number(this.item.price) || 0;
        },
        discountValue() {
            return Number(this.item.Discount) || 0;
        },
        taxValue() {
            return Number(this.item.tax) || 0;
        },
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
            return (
                this.serialImageUrl ||
                this.serialImagePath ||
                this.defaultSerialImage
            );
        },
        hasTrackingImages() {
            // if user captured tracking images OR existing ones from DB
            const capturedTracking = this.capturedImages?.some(i => i.step === 1);
            const reused = !!(this.existingTrackingImgs.trackingimg1 || this.existingTrackingImgs.trackingimg2);
            return capturedTracking || reused;
        },
        hasTrackingImage() {
            return this.$refs.scanner?.capturedImages?.length > 0;
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

        openDetectSerialModal() {
            this.showDetectSerialModal = true;
        },

        closeDetectSerialModal() {
            this.showDetectSerialModal = false;
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
                this.activeIndex = this.imageList.length - 1;
            }
        },

        nextImage() {
            if (this.activeIndex < this.imageList.length - 1) {
                this.activeIndex++;
            } else {
                this.activeIndex = 0;
            }
        },

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
                SoundService.error();
            } finally {
                this.loading = false;
            }
        },

        handleTrackingInput(event) {
            this.validateTrackingNumber();

            if (
                !this.showManualInput &&
                this.trackingNumberValid &&
                this.trackingNumber.length >= 5
            ) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    this.verifyTrackingNumber();
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

        setExistingTrackingImages(imgs) {
            this.existingTrackingImgs = imgs || { trackingimg1: null, trackingimg2: null };
        },

        validateBasketNumber() {
            const basketRegex = /^(BKT|SI|ENV)\d+$/i;
            this.basketNumberValid = basketRegex.test(this.basketNumber.trim());
            if (!this.basketNumberValid) {
                SoundService.error();
            }
            return this.basketNumberValid;
        },

        validatePcnNumber() {
            if (this.pcnNumber.trim() === "N/A") {
                this.pcnNumberValid = true;
                return true;
            }

            const pcnRegex = /^PCN\d+$/i;
            this.pcnNumberValid = pcnRegex.test(this.pcnNumber.trim());
            if (!this.pcnNumberValid) {
                SoundService.error();
            }
            return this.pcnNumberValid;
        },

        async verifyTrackingNumber() {
            this.validateTrackingNumber();

            if (!this.trackingNumberValid) {
                this.$refs.scanner.showScanError("Please enter a valid tracking number");
                SoundService.error();
                return;
            }

            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/received/verify-tracking`,
                    { params: { tracking: this.trackingNumber } }
                );

                if (!response.data.found) {
                    this.$refs.scanner.showScanError("Tracking number not found");
                    this.trackingFound = false;
                    SoundService.notFound();
                    this.$refs.trackingInput.select();
                    return;
                }

                // 🚫 BLOCK if already processed in another module
                if (response.data.alreadyScanned) {
                    this.$refs.scanner.showScanWarning(
                        "⚠️ Tracking already processed in another module."
                    );

                    SoundService.alreadyScanned(true);

                    // 🔥 Reset critical states
                    this.trackingFound = false;
                    this.productId = null;
                    this.rtcounter = null;
                    this.currentStep = 1;

                    // optional: re-focus input
                    this.$nextTick(() => {
                        this.$refs.trackingInput?.select();
                    });

                    return; // 🛑 HARD STOP
                }


                // ✅ always set critical state FIRST
                this.trackingFound = true;
                this.productId = response.data.productId;
                this.rtcounter = response.data.rtcounter;
                this.originalQuantity = response.data.quantity || 1;
                this.remainingQuantity = this.originalQuantity;
                this.$refs.scanner.loadProductThumbnails(response.data.productDetails);

                this.applyTrackingResponse(response.data, response.data.moduleLocation);

                // 🔴 MUST CAPTURE TRACKING IMAGE FIRST
                if (response.data.requireTrackingImage) {
                    this.$refs.scanner.showScanSuccess(
                        "Please capture tracking number image to continue."
                    );
                    this.currentStep = 1;
                    SoundService.success();
                    return;
                }

                // 🔴 FULLY processed → try rescan flow
                // if (response.data.alreadyScanned) {
                //     await this.verifyTrackingRescan();
                //     return;
                // }


                // 🟢 Normal / partial flow
                // this.applyTrackingResponse(response.data, response.data.moduleLocation);


                // 🟢 NORMAL Received flow
                this.originalQuantity = response.data.quantity || 1;
                this.remainingQuantity = this.originalQuantity;

                const basePath = "/images/thumbnails/";
                this.productImages = [];

                const product = response.data.productDetails || {};
                for (let i = 1; i <= 15; i++) {
                    const key = `img${i}`;
                    if (product[key] && product[key] !== "NULL") {
                        this.productImages.push({
                            src: basePath + product[key],
                            label: `Image ${i}`,
                        });
                    }
                }

                this.$refs.scanner.showScanSuccess(
                    this.originalQuantity > 1
                        ? `Tracking found! Quantity: ${this.originalQuantity} (will process 1 by 1)`
                        : "Tracking found! Quantity: 1"
                );

                // ✅ REQUIRED
                this.currentStep = 2;
                SoundService.success();

            } catch (error) {
                console.error("Error verifying tracking:", error);
                this.$refs.scanner.showScanError("Error checking tracking number");
                SoundService.error();
                this.$refs.trackingInput.select();
            }
        },

        applyTrackingResponse(data, source) {
            this.trackingSource = source;

            if (source === 'Reconciliation') {
                this.isRescan = true;
            }

            this.productId = data.productId || null;
            this.rtcounter = data.rtcounter || null;
            this.originalQuantity = data.quantity || 1;
            this.remainingQuantity = this.originalQuantity;

            // 🔥 Tracking image handling
            if (data.reuseTrackingImages && data.trackingImages) {

                const basePath = "/images/thumbnails/";
                const images = [];

                if (data.trackingImages.trackingimg1) {
                    images.push({
                        src: basePath + data.trackingImages.trackingimg1,
                        reused: true,
                        step: 1
                    });
                }

                if (data.trackingImages.trackingimg2) {
                    images.push({
                        src: basePath + data.trackingImages.trackingimg2,
                        reused: true,
                        step: 1
                    });
                }

                if (images.length > 0) {
                    this.$refs.scanner.setExistingTrackingImages(images);
                }
            }

            // 🔴 Require capture if missing
            this.requireTrackingImage = !!data.requireTrackingImage;
            this.hasTrackingImage = !!data.hasTrackingImage;

            // 🚫 HARD BLOCK — no product images until tracking image exists
            if (this.requireTrackingImage) {
                this.$refs.scanner.showScanWarning(
                    "📸 Please capture tracking image to continue"
                );
            }

            // ✅ Move to Step 2 (Product Images)
            if (this.requireTrackingImage && !this.hasTrackingImage) {
                this.currentStep = 1; // stay on tracking step
                return;
            }

            this.currentStep = 2;

            SoundService.success();
        },

        proceedToPassFail() {
            if (!this.hasTrackingImages) {
            this.$refs.scanner?.showScanWarning(
                'Please capture at least one tracking image.'
            );
            return;
            }

            this.currentStep = 2;
        },

        passItem() {
            // 🚫 HARD BLOCK: Require at least 1 captured image
            if (!this.$refs.scanner?.capturedImages?.length) {
                this.$refs.scanner?.showScanWarning(
                    '⚠️ Please capture at least one image before passing.'
                );
                return;
            }

            // ✅ Existing logic (unchanged)
            this.status = "pass";
            this.currentStep = 3;
            SoundService.success();

            this.$nextTick(() => {
                if (this.$refs.firstSerialInput) {
                    this.$refs.firstSerialInput.focus();
                }
            });
        },


        failItem() {
            // 🚫 HARD BLOCK: Require at least 1 captured image
            if (!this.$refs.scanner?.capturedImages?.length) {
                this.$refs.scanner?.showScanWarning(
                    '⚠️ Please capture at least one image before failing.'
                );
                return;
            }

            // ✅ Existing logic (unchanged)
            this.status = "fail";

            this.$refs.scanner.showScanSuccess(
                "Item marked for failure - capture images if needed"
            );
            SoundService.error(true);

            this.currentStep = 5;

            this.$nextTick(() => {
                if (this.$refs.pcnInput) {
                    this.$refs.pcnInput.focus();
                }
            });
        },

        handleFirstSerialInput() {
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

        async processFirstSerial() {
            const trimmedSerial = this.firstSerialNumber.trim()

            //check if the serial number is valid
            if (!trimmedSerial || this.isInvalidSerial(trimmedSerial)) {
                this.$refs.scanner.showScanError(
                    "Please enter a valid serial number"
                );
                SoundService.error();
                this.$refs.firstSerialInput.select();
                return;
            }

             //check if the serial number is same as the tracking number
            if(trimmedSerial === this.trackingNumber) {
                this.$refs.scanner.showScanError(
                    "Tracking number cannot use as serial number"
                );
                SoundService.error();
                this.$refs.secondSerialInput.select();
                return;
            }


            SoundService.success();

            this.showSecondSerialInput = false;
            this.currentStep = 4;

            this.$nextTick(() => {
                if (this.$refs.secondSerialInput) {
                    this.$refs.secondSerialInput.focus();
                }
            });
        },

        handleSecondSerialInput() {
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

        async processSecondSerial() {
            const trimmedSerial = this.secondSerialNumber.trim()

            //check if the serial number is valid
            if (!trimmedSerial || this.isInvalidSerial(trimmedSerial)) {
                this.$refs.scanner.showScanError(
                    "Please enter a valid serial number"
                );
                SoundService.error();
                this.$refs.secondSerialInput.select();
                return;
            }

            //check if the serial number is same as the tracking number
            if(trimmedSerial === this.trackingNumber) {
                this.$refs.scanner.showScanError(
                    "Tracking number cannot use as serial number"
                );
                SoundService.error();
                this.$refs.secondSerialInput.select();
                return;
            }

            SoundService.success();

            this.currentStep = 5;

            this.$nextTick(() => {
                if (this.$refs.pcnInput) {
                    this.$refs.pcnInput.focus();
                }
            });
        },

        skipSecondSerial() {
            this.secondSerialNumber = "N/A";
            SoundService.success();

            this.currentStep = 5;

            this.$nextTick(() => {
                if (this.$refs.pcnInput) {
                    this.$refs.pcnInput.focus();
                }
            });
        },

        goBackToSecondSerialChoice() {
            this.secondSerialNumber = '';
            this.showSecondSerialInput = false;
        },

        handlePcnInput() {
            if (!this.showManualInput && this.pcnNumber.trim().length > 4) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    this.processPcnNumber();
                }, 500);
            }
        },

        async processPcnNumber() {
            if (!this.validatePcnNumber()) {
                this.$refs.scanner.showScanError(
                    "PCN must start with PCN followed by numbers (e.g. PCN12345)"
                );
                SoundService.error();
                this.$refs.pcnInput.select();
                return;
            }

            try {
                const pcnResponse = await axios.post(
                    `${API_BASE_URL}/api/received/validate-pcn`,
                    {
                        pcn: this.pcnNumber,
                    }
                );

                if (pcnResponse.data.alreadyUsed) {
                    this.$refs.scanner.showScanWarning(
                        `${this.pcnNumber} is already in use`
                    );
                    SoundService.PCNalreadyUsed();
                    this.$refs.pcnInput.select();
                    return;
                }

                SoundService.success();

                this.currentStep = 6;

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

        handleBasketInput() {
            if (!this.showManualInput && this.basketNumber.trim().length > 3) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    this.processBasketNumber();
                }, 500);
            }
        },

        processBasketNumber() {
            if (!this.validateBasketNumber()) {
                this.$refs.scanner.showScanError(
                    "Basket number must start with BKT, SI, or ENV followed by numbers"
                );
          //      SoundService.error();
                this.$refs.basketInput.select();
                return;
            }

            if (this.status === "fail") {
                this.submitFailedItem();
            } else {
                this.submitScanData();
            }
        },

        async captureSerialImage() {
            if (this.$refs.scanner && this.$refs.scanner.captureFromScanner) {
                try {
                    await this.$refs.scanner.captureFromScanner();
                    return true;
                } catch (error) {
                    console.error("Error capturing image:", error);
                    SoundService.error();
                    return false;
                }
            }
            return false;
        },

        async submitFailedItem() {
            try {
                if (!this.validateBasketNumber()) {
                    this.$refs.scanner.showScanError(
                        "Basket number must start with BKT, SI, or ENV followed by numbers"
                    );
                    SoundService.error();
                    return;
                }

                if (!this.validatePcnNumber()) {
                    this.$refs.scanner.showScanError(
                        "PCN must start with PCN followed by numbers (e.g. PCN12345)"
                    );
                    SoundService.error();
                    return;
                }

                this.$refs.scanner.startLoading("Processing Data");

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
                    pcnNumber: this.pcnNumber,
                    basketNumber: this.basketNumber,
                    productId: this.productId,
                    rtcounter: this.rtcounter,
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
                    this.$refs.scanner.clearProductThumbnails();
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanSuccess("Item marked as failed");
                    SoundService.successScan(true);

                    this.$refs.scanner.addSuccessScan({
                        trackingnumber: this.trackingNumber,
                        status: "fail",
                        pcn: this.pcnNumber,
                        basket: this.basketNumber,
                    });

                    if (response.data.clearImages) {
                        this.$refs.scanner.capturedImages = [];
                    }

                    this.resetScannerState();
                    this.fetchInventory();
                } else {
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanError(
                        response.data.message || "Error processing scan"
                    );
                    SoundService.scanRejected(true);
                }
            } catch (error) {
                console.error("Error submitting failed item:", error);
                SoundService.scanRejected(true);

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

        async submitScanData() {
            try {
                const csrfToken = document.querySelector(
                    'meta[name="csrf-token"]'
                ).content;

                this.$refs.scanner.startLoading("Processing Data...");

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

                    // 🔥 NEW
                    isRescan: this.isRescan,
                    trackingSource: this.trackingSource,
                };

                console.log("Submitting scan data (without images):", scanData);

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
                    if (response.data.newProductId) {
                        this.productId = response.data.newProductId; // 🔥 CRITICAL FIX
                    }
                    // ✅ Handle split response
                    const wasSplit = response.data.wasSplit || false;
                    const remainingQty = response.data.remainingQuantity || 0;
                    // ✅ IMPORTANT: For split items, ALWAYS use newProductId. For non-split, use original
                    // const targetProductId = wasSplit ? response.data.newProductId : this.productId;
                    const targetProductId = response.data.newProductId || this.productId;
                    
                    console.log('🎯 Target Product ID for images:', {
                        wasSplit,
                        originalProductId: this.productId,
                        newProductId: response.data.newProductId,
                        targetProductId: targetProductId,
                        remainingQty
                    });

                                        
                    console.log('🔍 Processing images for:', {
                        wasSplit,
                        originalProductId: this.productId,
                        targetProductId: targetProductId,
                        remainingQty
                    });

                    // Upload images to the new/split product
                    // const capturedImages = this.$refs.scanner.capturedImages;
                    const capturedImages = this.getUploadableImages();

                    // ✅ Per-step counters
                    const stepCounters = {
                    1: 0, // tracking
                    2: 0, // product
                    3: 0, // serial 1
                    4: 0, // serial 2
                    };

                    if (capturedImages.length > 0) {
                        for (let i = 0; i < capturedImages.length; i++) {
                            try {
                            const img = capturedImages[i];
                            const imgStep = img.step;

                            // ✅ step-based index
                            const stepIndex = stepCounters[imgStep] ?? 0;
                            stepCounters[imgStep] = stepIndex + 1;

                            let isSerial = false;
                            let serialIndex = 0;

                            if (imgStep === 3) {
                                isSerial = true;
                                serialIndex = 1;
                            } else if (imgStep === 4) {
                                isSerial = true;
                                serialIndex = 2;
                            }

                            console.log('📤 Uploading image', {
                                productId: targetProductId,
                                step: imgStep,
                                stepIndex,
                            });

                            const imageResponse = await axios.post(
                                `${API_BASE_URL}/api/images/upload`,
                                {
                                _token: csrfToken,
                                productId: targetProductId,
                                imageIndex: stepIndex,   // ✅ FIX
                                imageData: img.data,
                                step: imgStep,
                                isSerial,
                                serialIndex,
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
                                `✅ Image uploaded (step ${imgStep}, index ${stepIndex})`,
                                imageResponse.data
                            );
                            } catch (imageError) {
                            console.error(
                                `❌ Error uploading image`,
                                imageError.response?.data || imageError.message
                            );
                            }
                        }
                    }
                    else {
                        console.log('⚠️ No images captured to upload');
                    }

                    this.$refs.scanner.clearProductThumbnails();
                    this.$refs.scanner.stopLoading();

                    // ✅ Show appropriate message
                    if (wasSplit) {
                        this.$refs.scanner.showScanSuccess(
                            `Item processed! ${remainingQty} remaining in batch`
                        );
                        this.remainingQuantity = remainingQty;
                    } else {
                        this.$refs.scanner.showScanSuccess(
                            "Item received successfully"
                        );
                    }

                    SoundService.successScan(true);

                    this.$refs.scanner.addSuccessScan({
                        Trackingnumber: this.trackingNumber,
                        FirstSN: this.firstSerialNumber,
                        SecondSN: this.secondSerialNumber,
                        PCN: this.pcnNumber,
                        Basket: this.basketNumber,
                    });

                    this.$refs.scanner.capturedImages = [];

                    this.resetScannerState();
                    this.fetchInventory();
                } else {
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanError(
                        response.data.message || "Error processing scan"
                    );
                    SoundService.scanRejected(true);

                    this.$refs.scanner.addErrorScan(
                        {
                            Trackingnumber: this.trackingNumber,
                            FirstSN: this.firstSerialNumber,
                            SecondSN: this.secondSerialNumber,
                            PCN: this.pcnNumber,
                            Basket: this.basketNumber,
                        },
                        response.data.reason || "error"
                    );
                }
            } catch (error) {
                console.error("Error submitting scan:", error);
                SoundService.scanRejected(true);

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

        getUploadableImages() {
            if (!this.$refs.scanner?.capturedImages) {
                return [];
            }

            return this.$refs.scanner.capturedImages.filter(img => {
                // ❌ skip reused images
                if (img.reused) return false;

                // ❌ skip non-base64 images
                if (typeof img.data !== 'string') return false;
                if (!img.data.startsWith('data:image')) return false;

                // ✅ safe to upload
                return true;
            });
        },

        resetScannerState() {
            this.currentStep = 1;
            this.trackingNumber = "";
            this.firstSerialNumber = "";
            this.secondSerialNumber = "";
            this.pcnNumber = "";
            this.basketNumber = "";
            this.trackingValid = false;
            this.trackingFound = false;
            this.productId = "";
            this.rtcounter = "";
            this.status = "";
            this.showSecondSerialInput = false;
            
            // ✅ Reset quantity tracking
            this.originalQuantity = 1;
            this.remainingQuantity = 1;

            this.trackingSource = null;
            this.isRescan = false;
            this.requireTrackingImage = false;

            this.resetUploader();

            if (
                this.$refs.scanner &&
                this.$refs.scanner.clearProductThumbnails
            ) {
                this.$refs.scanner.clearProductThumbnails();
            }

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

        handleScanProcess() {
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
                    this.processPcnNumber();
                    break;
                case 6:
                    this.processBasketNumber();
                    break;
            }
        },

        handleHardwareScan(scannedCode) {
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
                    this.pcnNumber = scannedCode;
                    this.processPcnNumber();
                    break;
                case 6:
                    this.basketNumber = scannedCode;
                    this.processBasketNumber();
                    break;
            }
        },

        handleModeChange(event) {
            this.showManualInput = event.manual;
        },

        handleScannerOpened() {
            console.log("Scanner opened");
            this.showManualInput = this.$refs.scanner.showManualInput;
            this.resetScannerState();
        },

        handleScannerClosed() {
            console.log("Scanner closed");
            this.fetchInventory();
            this.resetUploader();
        },

        handleScannerReset() {
            console.log("Scanner reset");
            this.resetScannerState();
            this.resetUploader();
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
            this.$set(this.expandedRows, index, !this.expandedRows[index]);
        },

        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = column;
                this.sortOrder = "asc";
            }
        },

        toggleDetailsVisibility() {
            this.showDetails = !this.showDetails;
        },

        changePerPage() {
            this.currentPage = 1;
            this.fetchInventory();
        },

        resetUploader() {
            this.imageUrl = null;
            this.croppedImage = null;
            this.isDragging = false;
            this.apiResult.step3.serials = [];
            this.apiResult.step4.serials = [];
        },

        saveSerial(serialText, index) {
            if (this.currentStep === 3) {
                this.firstSerialNumber = serialText;
                this.$refs.scanner.showScanSuccess(
                    `✅ Saved Serial #1: ${serialText}`
                );

                this.apiResult.step3.serials.splice(0);
                this.resetUploader();

                this.$nextTick(() => {
                    if (typeof this.processFirstSerial === "function") {
                        this.processFirstSerial();
                    }
                });
            } else if (this.currentStep === 4) {
                this.secondSerialNumber = serialText;
                this.$refs.scanner.showScanSuccess(
                    `✅ Saved Serial #2: ${serialText}`
                );

                this.apiResult.step4.serials.splice(0);
                this.resetUploader();

                this.$nextTick(() => {
                    if (typeof this.processSecondSerial === "function") {
                        this.processSecondSerial();
                    }
                });
            }
        },

        openImageModalFromPreview(index) {
            this.modalImages = this.productImages.map((img) => img.src);
            this.currentImageIndex = index;
            this.showImageModal = true;

            document.body.style.overflow = "hidden";
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

            await this.$nextTick();
            if (typeof this.fetchSerialImageIfAny === "function") {
                await this.fetchSerialImageIfAny();
            }
        },

        closeEditModal() {
            this.showEditModal = false;

            if (typeof this.resetSerialImage === "function") {
                this.resetSerialImage({ clearServer: true });
            }

            setTimeout(() => {
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
            return String.fromCharCode(65 + index);
        },

        async fetchItems() {
            this.loading = true;
            try {
                const response = await axios.get("/api/unreceived/products");
                const payload = response.data;

                this.items = Array.isArray(payload)
                    ? payload
                    : payload.data || [];
            } catch (err) {
                console.error("Fetch failed:", err);
                this.items = [];
                this.error = "Failed to load items.";
            } finally {
                this.loading = false;
            }
        },

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

                if (!response.ok)
                    throw new Error(`HTTP error! status: ${response.status}`);

                const result = await response.json();
                console.log("🔍 Serial detection result:", result);

                if (this.currentStep === 3) {
                    this.apiResult.step3 = result;
                } else if (this.currentStep === 4) {
                    this.apiResult.step4 = result;
                }

                if (result.serials && result.serials.length > 0) {
                    const detectedSerial = result.serials[0].text || result.serials[0];
                    if (this.currentStep === 3) {
                        this.firstSerialNumber = detectedSerial;
                        if (this.$refs.scanner && this.$refs.scanner.showScanSuccess) {
                            this.$refs.scanner.showScanSuccess(
                                `✅ Serial #1 detected: ${detectedSerial}`
                            );
                        }
                    } else if (this.currentStep === 4) {
                        this.secondSerialNumber = detectedSerial;
                        if (this.$refs.scanner && this.$refs.scanner.showScanSuccess) {
                            this.$refs.scanner.showScanSuccess(
                                `✅ Serial #2 detected: ${detectedSerial}`
                            );
                        }
                    }
                } else {
                    if (this.$refs.scanner && this.$refs.scanner.showScanWarning) {
                        this.$refs.scanner.showScanWarning(
                            "⚠️ No serials detected in image."
                        );
                    }
                }
            } catch (error) {
                console.error("Upload error:", error);
                if (this.$refs.scanner && this.$refs.scanner.showScanError) {
                    this.$refs.scanner.showScanError(
                        "❌ Failed to detect serial number."
                    );
                }
            } finally {
                this.loading = false;
            }
        },
        isInvalidSerial(serial) {
            const RESTRICTED_PREFIXES = ['SI', 'BKT', 'RPN'];

            const startsWithRestrictedPrefix = new RegExp(
                `^(${RESTRICTED_PREFIXES.join('|')})`,
                'i'
            );

            const lettersOnlyRegex = /^[A-Z]+$/i;

            const value = serial.trim();

            console.log( startsWithRestrictedPrefix.test(value) ||
                lettersOnlyRegex.test(value))

            return (
                startsWithRestrictedPrefix.test(value) ||
                lettersOnlyRegex.test(value)
            );
        }
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchInventory();
        },
        showSecondSerialInput(val) {
            if (val) {
            this.$nextTick(() => {
                this.$refs.secondSerialInput?.focus();
            });
            }
        },
    },
    mounted() {
        axios.defaults.baseURL = window.location.origin;
        this.fetchInventory();
    },
};