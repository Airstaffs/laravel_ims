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
            selectAll: false,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,

            // Scanner workflow data
            currentStep: 1,
            trackingNumber: "",
            // firstSerialNumber: "",
            // secondSerialNumber: "",
            serialNumbers: ["", "", "", "", ""],
            serialCount: 1, // first serial already active
            maxSerials: 5,

            pcnNumber: "",
            basketNumber: "",
            trackingValid: false,
            trackingFound: false,
            productId: "",
            rtcounter: "",
            status: "",

            // 🔥 NEW — OCR toggle
            useAiDetection: false,

            existingTrackingImgs: { trackingimg1: null, trackingimg2: null },

            // ✅ NEW: Track quantity info for splitting
            originalQuantity: 1,
            remainingQuantity: 1,

            // For validation
            trackingNumberValid: true,
            basketNumberValid: true,
            pcnNumberValid: true,

            // 🔥 NEW — rescan support
            trackingSource: null, // 'Received' | 'Labeling' | 'Validation' | 'Reconciliation'
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

            //pagination
            currentPage: 1,
            perPage: 10,
            totalRecords: 0,
            first: 0,

            passFailResult: null,
            checklist: {
                correctOnOrder: "yes",
                condition: "good",
                conditionNotes: "",
            },
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
                /^serialnumber[a-z]?$/.test(k),
            );
        },
        trackingKeys() {
            return Object.keys(this.item).filter((k) =>
                /^trackingnumber\d*$/.test(k),
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
                        .filter((t) => t && t.trim() !== ""),
                ),
            ].sort();
        },
        sourceTypes() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.sourceType)
                        .filter((t) => t && t.trim() !== ""),
                ),
            ].sort();
        },
        carrierOptions() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.carrier)
                        .filter((c) => c && c.trim() !== ""),
                ),
            ].sort();
        },
        storeNames() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.storename)
                        .filter((t) => t && t.trim() !== ""),
                ),
            ].sort();
        },
        priorityRanks() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.priorityrank)
                        .filter((t) => t && t.trim() !== ""),
                ),
            ].sort();
        },
        validationStatuses() {
            if (!Array.isArray(this.items)) return [];
            return [
                ...new Set(
                    this.items
                        .map((i) => i.validation_status)
                        .filter((t) => t && t.trim() !== ""),
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
            const images = this.$refs.scanner?.capturedImages || [];
            const capturedTracking = images.some((i) => i.step === 1);

            const reused = !!(
                this.existingTrackingImgs.trackingimg1 ||
                this.existingTrackingImgs.trackingimg2
            );

            return capturedTracking || reused;
        },

        hasTrackingImage() {
            return this.$refs.scanner?.capturedImages?.length > 0;
        },

        checklistComplete() {
            const c = this.checklist;
            return (
                c.correctOnOrder !== null &&
                c.condition !== null &&
                (c.condition === "good" || c.conditionNotes.trim() !== "")
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
                    (i) => i.itemnumber === item.itemnumber,
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
                            per_page: this.perPage,
                            location: "Received",
                        },
                    },
                );

                this.inventory = response.data.data;
                this.totalRecords = response.data.total;
            } catch (error) {
                console.error("Error fetching inventory data:", error);
                SoundService.error();
            } finally {
                this.loading = false;
            }
        },

        handleTrackingInput(event) {
            this.validateTrackingNumber();

            const isManual = this.$refs.scanner?.showManualInput;

            if (
                !isManual &&
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
            this.existingTrackingImgs = imgs || {
                trackingimg1: null,
                trackingimg2: null,
            };
        },

        validateBasketNumber() {
            const basketRegex = /^(BKT\d+|S[I-Z]\d+|ENV\d+)$/i;
            this.basketNumberValid = basketRegex.test(this.basketNumber.trim());
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
                this.$refs.scanner.showScanError(
                    "Please enter a valid tracking number",
                );
                SoundService.error();
                return;
            }

            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/received/verify-tracking`,
                    { params: { tracking: this.trackingNumber } },
                );

                if (!response.data.found) {
                    this.$refs.scanner.showScanError(
                        "Tracking number not found",
                    );
                    this.trackingFound = false;
                    SoundService.notFound();
                    this.$refs.trackingInput.select();
                    return;
                }

                // 🚫 BLOCK if already processed in another module
                if (response.data.alreadyScanned) {
                    this.$refs.scanner.showScanWarning(
                        "⚠️ Tracking already processed in another module.",
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
                this.$refs.scanner.loadProductThumbnails(
                    response.data.productDetails,
                );

                this.applyTrackingResponse(
                    response.data,
                    response.data.moduleLocation,
                );

                // 🔴 MUST CAPTURE TRACKING IMAGE FIRST
                if (response.data.requireTrackingImage) {
                    this.$refs.scanner.showScanSuccess(
                        "Please capture tracking number image to continue.",
                    );
                    this.currentStep = 1;
                    SoundService.success();
                    return;
                }

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
                        : "Tracking found! Quantity: 1",
                );

                // ✅ REQUIRED
                this.currentStep = 2;
                SoundService.success();
            } catch (error) {
                console.error("Error verifying tracking:", error);
                this.$refs.scanner.showScanError(
                    "Error checking tracking number",
                );
                SoundService.error();
                this.$refs.trackingInput.select();
            }
        },

        applyTrackingResponse(data, source) {
            this.trackingSource = source;

            if (source === "Reconciliation") {
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
                        step: 1,
                    });
                }

                if (data.trackingImages.trackingimg2) {
                    images.push({
                        src: basePath + data.trackingImages.trackingimg2,
                        reused: true,
                        step: 1,
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
                    "📸 Please capture tracking image to continue",
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
                    "Please capture at least one tracking image.",
                );
                return;
            }

            this.currentStep = 2;
        },

        passItem() {
            if (!this.$refs.scanner?.capturedImages?.length) {
                this.$refs.scanner?.showScanWarning(
                    "⚠️ Please capture at least one image before passing.",
                );
                return;
            }
            this.status = "pass";
            this.passFailResult = "pass";
            this.currentStep = 3;
            SoundService.success();
        },

        failItem() {
            if (!this.$refs.scanner?.capturedImages?.length) {
                this.$refs.scanner?.showScanWarning(
                    "⚠️ Please capture at least one image before failing.",
                );
                return;
            }
            this.status = "fail";
            this.passFailResult = "fail";
            this.checklist.correctOnOrder = "no";
            this.$refs.scanner.showScanSuccess(
                "Item marked for failure - capture images if needed",
            );
            SoundService.error(true);
            this.currentStep = 3;
        },

        proceedFromChecklist() {
            if (this.status === "fail") {
                this.currentStep = 9;
                this.$nextTick(() => this.$refs.pcnInput?.focus());
            } else {
                this.currentStep = 4;
                this.$nextTick(() => this.$refs.serialInput1?.focus());
            }
        },

        async processSerial() {
            const isManual = this.$refs.scanner?.showManualInput;
            if (isManual && !this._manualTrigger) return;

            const step = this.currentStep;
            if (step < 4 || step > 8) return;

            const idx = step - 4;
            const serial = (this.serialNumbers[idx] || "").trim();

            if (step === 4 && !serial) {
                this.$refs.scanner.showScanError(
                    "First serial number is required.",
                );
                SoundService.error();
                return;
            }

            if (step >= 5 && !serial) {
                this.$refs.scanner.showScanWarning(
                    "Enter a serial number or press Skip.",
                );
                SoundService.error();
                return;
            }

            if (this.isInvalidSerial(serial)) {
                this.$refs.scanner.showScanError(
                    "Please enter a valid serial number",
                );
                SoundService.error();
                return;
            }

            if (serial === this.trackingNumber) {
                this.$refs.scanner.showScanError(
                    "Tracking number cannot be used as serial number",
                );
                SoundService.error();
                return;
            }

            const duplicateLocal = this.serialNumbers
                .filter((s, i) => i !== idx)
                .some((s) => (s || "").trim() === serial);

            if (duplicateLocal) {
                this.$refs.scanner.showScanError(
                    "This serial number is already used in this scan.",
                );
                this.serialNumbers[idx] = "";
                SoundService.error();
                return;
            }

            const hasImage = (this.$refs.scanner?.capturedImages || []).some(
                (img) => Number(img.step) === Number(step),
            );

            if (!hasImage) {
                this.$refs.scanner.showScanWarning(
                    "📸 Please capture serial image before continuing.",
                );
                this.serialNumbers[idx] = "";
                SoundService.error();
                return;
            }

            try {
                const csrfToken = document.querySelector(
                    'meta[name="csrf-token"]',
                ).content;
                const response = await axios.post(
                    `${API_BASE_URL}/api/received/validate-serial`,
                    { serial, productId: this.productId },
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                            Accept: "application/json",
                        },
                    },
                );

                if (!response.data.valid) {
                    SoundService.error();
                    const existingInfo = response.data.existingRecord
                        ? `<div class="swal-detail-block">
                    <p><strong>RT#:</strong> ${response.data.existingRecord.rtcounter ?? "—"}</p>
                    <p><strong>Product:</strong> ${response.data.existingRecord.ProductTitle ?? "—"}</p>
                    <p><strong>Location:</strong> ${response.data.existingRecord.ProductModuleLoc ?? "—"}</p>
                   </div>`
                        : "";

                    const result = await Swal.fire({
                        icon: "warning",
                        title: "Duplicate Serial Detected",
                        html: `<p>Serial <strong>${serial}</strong> already exists in the system.</p>
                       ${existingInfo}
                       <p class="mt-2 text-muted">Do you want to use it anyway?</p>`,
                        showCancelButton: true,
                        confirmButtonText: "Yes, use it",
                        cancelButtonText: "No, re-scan",
                        confirmButtonColor: "#f59e0b",
                        cancelButtonColor: "#6b7280",
                        reverseButtons: true,
                        customClass: { popup: "swal-scanner-popup" },
                    });

                    if (!result.isConfirmed) {
                        this.serialNumbers[idx] = "";
                        this.$nextTick(() => {
                            const ref = `serialInput${idx + 1}`;
                            this.$refs[ref]?.focus?.();
                            this.$refs[ref]?.select?.();
                        });
                        return;
                    }
                }
            } catch (error) {
                this.$refs.scanner.showScanError(
                    "Error verifying serial number.",
                );
                SoundService.error();
                return;
            }

            SoundService.success();
            this.$refs.scanner.showScanSuccess(
                `Serial #${idx + 1} saved. Proceeding...`,
            );

            if (this._serialDelayLock) return;
            this._serialDelayLock = true;

            setTimeout(() => {
                if (step < 8) {
                    this.currentStep = step + 1;
                    this.$nextTick(() => {
                        const nextRef = `serialInput${idx + 2}`;
                        this.$refs[nextRef]?.focus?.();
                    });
                } else {
                    this.currentStep = 9;
                    this.$nextTick(() => this.$refs.pcnInput?.focus?.());
                }
                this._serialDelayLock = false;
            }, 1500);
        },

        skipSerialStep() {
            const step = this.currentStep;

            if (step === 4) {
                this.$refs.scanner.showScanWarning(
                    "You cannot skip the first serial.",
                );
                return;
            }

            if (step < 5 || step > 8) return;

            const idx = step - 4;

            const serialValue = (this.serialNumbers[idx] || "").trim();
            const hasImage = (this.$refs.scanner?.capturedImages || []).some(
                (img) => Number(img.step) === Number(step),
            );

            if (hasImage && !serialValue) {
                this.$refs.scanner.showScanWarning(
                    "You captured a serial image. Please scan the serial or delete the image before skipping.",
                );
                SoundService.error();
                return;
            }

            if (serialValue && !hasImage) {
                this.$refs.scanner.showScanWarning(
                    "Serial entered but no image captured. Please capture image first.",
                );
                SoundService.error();
                return;
            }

            for (let i = idx; i < 5; i++) {
                this.serialNumbers[i] = "";
            }

            this.$refs.scanner.capturedImages =
                this.$refs.scanner.capturedImages.filter(
                    (img) => img.step < step,
                );

            for (let i = step; i <= 8; i++) {
                if (this.apiResult?.[`step${i}`]) {
                    delete this.apiResult[`step${i}`];
                }
            }

            this.serialCount = idx;
            this.$refs.scanner.showScanSuccess(
                "Skipped remaining serials. Proceeding to PCN.",
            );
            SoundService.success();

            this.currentStep = 9;
            this.$nextTick(() => this.$refs.pcnInput?.focus?.());
        },

        goBackToSecondSerialChoice() {
            this.secondSerialNumber = "";
            this.showSecondSerialInput = false;
        },

        handlePcnInput() {
            const isManual = this.$refs.scanner?.showManualInput;
            if (!isManual && this.pcnNumber.trim().length > 4) {
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
                    "PCN must start with PCN followed by numbers (e.g. PCN12345)",
                );
                SoundService.error();
                this.$refs.pcnInput.select();
                return;
            }

            try {
                const pcnResponse = await axios.post(
                    `${API_BASE_URL}/api/received/validate-pcn`,
                    { pcn: this.pcnNumber },
                );

                if (pcnResponse.data.alreadyUsed) {
                    // ─── SWAL duplicate PCN prompt ───────────────────────────────
                    SoundService.PCNalreadyUsed();

                    const existingInfo = pcnResponse.data.existingRecord
                        ? `<div class="swal-detail-block">
                    <p><strong>RT#:</strong> ${pcnResponse.data.existingRecord.rtcounter ?? "—"}</p>
                    <p><strong>Product:</strong> ${pcnResponse.data.existingRecord.ProductTitle ?? "—"}</p>
                    <p><strong>Location:</strong> ${pcnResponse.data.existingRecord.ProductModuleLoc ?? "—"}</p>
                   </div>`
                        : "";

                    const result = await Swal.fire({
                        icon: "warning",
                        title: "PCN Already in Use",
                        html: `
                    <p>PCN <strong>${this.pcnNumber}</strong> is already assigned to another item.</p>
                    ${existingInfo}
                    <p class="mt-2 text-muted">Do you want to use it anyway?</p>
                `,
                        showCancelButton: true,
                        confirmButtonText: "Yes, use it",
                        cancelButtonText: "No, re-scan",
                        confirmButtonColor: "#f59e0b",
                        cancelButtonColor: "#6b7280",
                        reverseButtons: true,
                        customClass: {
                            popup: "swal-scanner-popup",
                        },
                    });

                    if (!result.isConfirmed) {
                        // User chose to re-scan — clear and refocus
                        this.pcnNumber = "";
                        this.$nextTick(() => {
                            this.$refs.pcnInput?.focus?.();
                            this.$refs.pcnInput?.select?.();
                        });
                        return;
                    }

                    // User confirmed override — fall through
                    // ─────────────────────────────────────────────────────────────
                }

                SoundService.success();
                this.currentStep = 10;
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
            const isManual = this.$refs.scanner?.showManualInput;

            if (!isManual && this.basketNumber.trim().length > 3) {
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
                    "Basket number must start with BKT, S[I-Z], or ENV followed by numbers",
                );
                //    SoundService.error(); // ✅ Just use regular error sound here
                this.$refs.basketInput.select();
                return;
            }

            // ✅ NO SOUND HERE - let submit functions handle success/error sounds
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
                        "Basket number must start with BKT, S[I-Z], or ENV followed by numbers",
                    );
                    SoundService.error();
                    return;
                }

                if (!this.validatePcnNumber()) {
                    this.$refs.scanner.showScanError(
                        "PCN must start with PCN followed by numbers (e.g. PCN12345)",
                    );
                    SoundService.error();
                    return;
                }

                this.$refs.scanner.startLoading("Processing Data");

                const csrfToken = document.querySelector(
                    'meta[name="csrf-token"]',
                ).content;

                const failData = {
                    _token: csrfToken,
                    trackingNumber: this.trackingNumber,
                    status: "fail",
                    pcnNumber: this.pcnNumber,
                    basketNumber: this.basketNumber,
                    productId: this.productId,
                    rtcounter: this.rtcounter,
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
                    },
                );

                if (response.data.success) {
                    try {
                        await axios.post(
                            `${API_BASE_URL}/api/received/record-checklist`,
                            {
                                trackingNumber: this.trackingNumber,
                                serialNumbers: [null, null, null, null, null],
                                passFailResult: "fail",
                                correctOnOrder: this.checklist.correctOnOrder,
                                condition: this.checklist.condition,
                                conditionNotes:
                                    this.checklist.conditionNotes ?? null,
                                productId: this.productId,
                                rtcounter: this.rtcounter,
                            },
                            {
                                headers: {
                                    "X-CSRF-TOKEN": csrfToken,
                                    Accept: "application/json",
                                },
                            },
                        );
                        console.log("✅ Checklist recorded for failed item");
                    } catch (checklistError) {
                        // Non-blocking — scan flow continues even if checklist fails
                        console.warn(
                            "⚠️ Checklist record failed:",
                            checklistError.response?.data,
                        );
                    }

                    // ✅ Upload captured images for the failed item
                    const capturedImages = this.getUploadableImages();

                    if (capturedImages.length > 0) {
                        const stepCounters = {
                            1: 0,
                            2: 0,
                            3: 0,
                            4: 0,
                            5: 0,
                            6: 0,
                            7: 0,
                        };

                        for (let i = 0; i < capturedImages.length; i++) {
                            try {
                                const img = capturedImages[i];
                                const imgStep = img.step;
                                const stepIndex = stepCounters[imgStep] ?? 0;
                                stepCounters[imgStep] = stepIndex + 1;

                                await axios.post(
                                    `${API_BASE_URL}/api/images/upload`,
                                    {
                                        _token: csrfToken,
                                        productId: this.productId,
                                        imageIndex: stepIndex,
                                        imageData: img.data,
                                        step: imgStep,
                                        isSerial: false,
                                        serialIndex: 0,
                                    },
                                    {
                                        withCredentials: true,
                                        headers: {
                                            "Content-Type": "application/json",
                                            Accept: "application/json",
                                            "X-CSRF-TOKEN": csrfToken,
                                        },
                                    },
                                );

                                console.log(
                                    `✅ Failed item image uploaded (step ${imgStep}, index ${stepIndex})`,
                                );
                            } catch (imageError) {
                                console.error(
                                    "❌ Error uploading image for failed item",
                                    imageError.response?.data ||
                                        imageError.message,
                                );
                            }
                        }
                    } else {
                        console.log("⚠️ No images to upload for failed item");
                    }

                    this.$refs.scanner.clearProductThumbnails();
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanSuccess("Item marked as failed");
                    SoundService.successScan(true);

                    this.$refs.scanner.addSuccessScan({
                        Trackingnumber: this.trackingNumber,
                        Serials: ["FAILED"],
                        PCN: this.pcnNumber,
                        Basket: this.basketNumber,
                    });

                    this.$refs.scanner.capturedImages = [];
                    this.$refs.scanner.setExistingTrackingImages([]);

                    this.resetScannerState();
                    this.fetchInventory();
                } else {
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanError(
                        response.data.message || "Error processing scan",
                    );
                    SoundService.scanRejected(true);
                }
            } catch (error) {
                console.error("Error submitting failed item:", error);
                SoundService.scanRejected(true);

                if (error.response?.status === 422) {
                    if (error.response.data.errors) {
                        const errorMessages = [];
                        Object.keys(error.response.data.errors).forEach(
                            (field) => {
                                errorMessages.push(
                                    `${field}: ${error.response.data.errors[field].join(", ")}`,
                                );
                            },
                        );
                        this.$refs.scanner.showScanError(
                            `Validation error: ${errorMessages.join("\n")}`,
                        );
                    } else {
                        this.$refs.scanner.showScanError(
                            "Validation failed. Please check your inputs.",
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
                    'meta[name="csrf-token"]',
                ).content;

                this.$refs.scanner.startLoading("Processing Data...");

                const cleanedSerials = (this.serialNumbers || [])
                    .map((s) => String(s || "").trim())
                    .filter(Boolean)
                    .slice(0, 5);

                if (!cleanedSerials.length) {
                    this.$refs.scanner.showScanError(
                        "First serial number is required.",
                    );
                    this.$refs.scanner.stopLoading();
                    SoundService.error();
                    return;
                }

                const scanData = {
                    _token: csrfToken,
                    trackingNumber: this.trackingNumber,
                    status: "pass",
                    serialNumbers: cleanedSerials,
                    pcnNumber: this.pcnNumber,
                    basketNumber: this.basketNumber,
                    productId: this.productId,
                    rtcounter: this.rtcounter,
                    isRescan: this.isRescan,
                    trackingSource: this.trackingSource,
                };

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
                    },
                );

                if (response.data.success) {
                    if (response.data.newProductId) {
                        this.productId = response.data.newProductId;
                    }

                    const wasSplit = response.data.wasSplit || false;
                    const remainingQty = response.data.remainingQuantity || 0;
                    const targetProductId =
                        response.data.newProductId || this.productId;

                    // ✅ Update checklist record with collected serials
                    try {
                        await axios.post(
                            `${API_BASE_URL}/api/received/record-checklist`,
                            {
                                trackingNumber: this.trackingNumber,
                                serialNumbers: this.serialNumbers,
                                passFailResult: this.passFailResult,
                                correctOnOrder: this.checklist.correctOnOrder,
                                condition: this.checklist.condition,
                                conditionNotes:
                                    this.checklist.conditionNotes ?? null,
                                productId: this.productId,
                                rtcounter: this.rtcounter,
                            },
                            {
                                headers: {
                                    "X-CSRF-TOKEN": csrfToken,
                                    Accept: "application/json",
                                },
                            },
                        );
                        console.log("✅ Checklist updated with serials");
                    } catch (checklistError) {
                        // Non-blocking — scan flow continues even if checklist update fails
                        console.warn(
                            "⚠️ Checklist serial update failed:",
                            checklistError.response?.data,
                        );
                    }

                    // ✅ Per-step counters (steps 4–8 are serials now)
                    const stepCounters = {
                        1: 0, // tracking
                        2: 0, // product
                        4: 0, // serial 1
                        5: 0, // serial 2
                        6: 0, // serial 3
                        7: 0, // serial 4
                        8: 0, // serial 5
                    };

                    const capturedImages = this.getUploadableImages();

                    if (capturedImages.length > 0) {
                        for (let i = 0; i < capturedImages.length; i++) {
                            try {
                                const img = capturedImages[i];
                                const imgStep = img.step;
                                const stepIndex = stepCounters[imgStep] ?? 0;
                                stepCounters[imgStep] = stepIndex + 1;

                                let isSerial = false;
                                let serialIndex = 0;

                                // ✅ Serial steps are now 4–8
                                if (imgStep >= 4 && imgStep <= 8) {
                                    isSerial = true;
                                    serialIndex = imgStep - 3; // 4→1, 5→2, 6→3, 7→4, 8→5
                                }

                                const imageResponse = await axios.post(
                                    `${API_BASE_URL}/api/images/upload`,
                                    {
                                        _token: csrfToken,
                                        productId: targetProductId,
                                        imageIndex: stepIndex,
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
                                    },
                                );

                                console.log(
                                    `✅ Image uploaded (step ${imgStep}, index ${stepIndex})`,
                                    imageResponse.data,
                                );
                            } catch (imageError) {
                                console.error(
                                    "❌ Error uploading image",
                                    imageError.response?.data ||
                                        imageError.message,
                                );
                            }
                        }
                    } else {
                        console.log("⚠️ No images captured to upload");
                    }

                    this.$refs.scanner.clearProductThumbnails();
                    this.$refs.scanner.stopLoading();

                    if (wasSplit) {
                        this.$refs.scanner.showScanSuccess(
                            `Item processed! ${remainingQty} remaining in batch`,
                        );
                        this.remainingQuantity = remainingQty;
                    } else {
                        this.$refs.scanner.showScanSuccess(
                            "Item received successfully",
                        );
                    }

                    SoundService.successScan(true);

                    this.$refs.scanner.addSuccessScan({
                        Trackingnumber: this.trackingNumber,
                        Serials: cleanedSerials,
                        PCN: this.pcnNumber,
                        Basket: this.basketNumber,
                    });

                    this.$refs.scanner.capturedImages = [];

                    this.resetScannerState();
                    this.fetchInventory();
                } else {
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanError(
                        response.data.message || "Error processing scan",
                    );

                    this.$refs.scanner.addErrorScan(
                        {
                            Trackingnumber: this.trackingNumber,
                            FirstSN: this.serialNumbers[0],
                            SecondSN: this.serialNumbers[1],
                            PCN: this.pcnNumber,
                            Basket: this.basketNumber,
                        },
                        response.data.reason || "error",
                    );
                }
            } catch (error) {
                console.error("Error submitting scan:", error);
                SoundService.scanRejected(true);

                if (error.response?.status === 422) {
                    if (error.response.data.errors) {
                        const errorMessages = [];
                        Object.keys(error.response.data.errors).forEach(
                            (field) => {
                                errorMessages.push(
                                    `${field}: ${error.response.data.errors[field].join(", ")}`,
                                );
                            },
                        );
                        this.$refs.scanner.showScanError(
                            `Validation error: ${errorMessages.join("\n")}`,
                        );
                    } else {
                        this.$refs.scanner.showScanError(
                            "Validation failed. Please check your inputs.",
                        );
                    }
                } else if (error.response?.status === 403) {
                    this.$refs.scanner.showScanError(
                        "Permission denied. Please try again or contact support.",
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

            return this.$refs.scanner.capturedImages.filter((img) => {
                // ❌ skip reused images
                if (img.reused) return false;

                // ❌ skip non-base64 images
                if (typeof img.data !== "string") return false;
                if (!img.data.startsWith("data:image")) return false;

                // ✅ safe to upload
                return true;
            });
        },

        resetScannerState() {
            this.currentStep = 1;
            this.trackingNumber = "";
            this.serialNumbers = ["", "", "", "", ""];
            this.serialCount = 1;
            this.pcnNumber = "";
            this.basketNumber = "";
            this.trackingValid = false;
            this.trackingFound = false;
            this.productId = "";
            this.rtcounter = "";
            this.status = "";
            this.showSecondSerialInput = false;
            this.useAiDetection = false;

            // ✅ Reset quantity tracking
            this.originalQuantity = 1;
            this.remainingQuantity = 1;

            this.trackingSource = null;
            this.isRescan = false;
            this.requireTrackingImage = false;

            this.resetUploader();

            if (this.$refs.scanner) {
                // ✅ Clear all scanner internal states
                this.$refs.scanner.capturedImages = [];
                this.$refs.scanner.clearProductThumbnails?.();

                // ✅ Clear existing tracking images
                if (
                    typeof this.$refs.scanner.setExistingTrackingImages ===
                    "function"
                ) {
                    this.$refs.scanner.setExistingTrackingImages([]);
                }

                // ✅ Reset scanner step indicator if it has one
                if (
                    typeof this.$refs.scanner.resetStepIndicator === "function"
                ) {
                    this.$refs.scanner.resetStepIndicator();
                }
            }

            // ✅ Reset existing tracking images local state
            this.existingTrackingImgs = {
                trackingimg1: null,
                trackingimg2: null,
            };

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

        addChecklistLog(cleanedSerials = []) {
            const csrfToken = document.querySelector(
                'meta[name="csrf-token"]',
            ).content;

            // Non-blocking fire-and-forget
            axios
                .post(
                    `${API_BASE_URL}/api/received/record-checklist`,
                    {
                        trackingNumber: this.trackingNumber,
                        serialNumbers: cleanedSerials.length
                            ? cleanedSerials
                            : [null, null, null, null, null],
                        passFailResult: this.passFailResult,
                        correctOnOrder: this.checklist.correctOnOrder,
                        condition: this.checklist.condition,
                        conditionNotes: this.checklist.conditionNotes ?? null,
                        productId: this.productId,
                        rtcounter: this.rtcounter,
                        // ── Extra fields from saved action ──
                        pcnNumber: this.pcnNumber,
                        basketNumber: this.basketNumber,
                        productTitle: this.productTitle ?? null,
                        asin: this.asin ?? null,
                    },
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                            Accept: "application/json",
                        },
                    },
                )
                .then(() => console.log("✅ Checklist log saved"))
                .catch((err) =>
                    console.warn(
                        "⚠️ Checklist log failed:",
                        err.response?.data,
                    ),
                );
        },

        handleSerialTyping() {
            const isManual = this.$refs.scanner?.showManualInput;
            const idx = this.currentStep - 4;
            const value = (this.serialNumbers[idx] || "").trim();

            if (!isManual && value.length > 5) {
                if (this.autoVerifyTimeout)
                    clearTimeout(this.autoVerifyTimeout);
                this.autoVerifyTimeout = setTimeout(() => {
                    this.processSerial();
                }, 500);
            }
        },

        handleScanProcess() {
            switch (this.currentStep) {
                case 1:
                    this.verifyTrackingNumber();
                    break;
                case 4:
                case 5:
                case 6:
                case 7:
                case 8:
                    this.processSerial();
                    break;
                case 9:
                    this.processPcnNumber();
                    break;
                case 10:
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
                case 4:
                case 5:
                case 6:
                case 7:
                case 8: {
                    const isManual = this.$refs.scanner?.showManualInput;
                    this.serialNumbers[this.currentStep - 4] = scannedCode;
                    if (!isManual) this.processSerial();
                    break;
                }
                case 9:
                    this.pcnNumber = scannedCode;
                    this.processPcnNumber();
                    break;
                case 10:
                    this.basketNumber = scannedCode;
                    this.processBasketNumber();
                    break;
            }
        },

        handleModeChange(event) {
            this.showManualInput = event.manual;
            let refName = null;

            switch (this.currentStep) {
                case 1:
                    refName = "trackingInput";
                    break;
                case 4:
                case 5:
                case 6:
                case 7:
                case 8:
                    refName = `serialInput${this.currentStep - 3}`;
                    break;
                case 9:
                    refName = "pcnInput";
                    break;
                case 10:
                    refName = "basketInput";
                    break;
            }

            if (refName && this.$refs[refName]) {
                this.$refs[refName].focus();
            }
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

        onPageChange(event) {
            this.first = event.first;
            this.currentPage = event.page + 1; // convert to 1-based
            this.perPage = event.rows;
            this.fetchInventory();
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

        resetUploader() {
            this.imageUrl = null;
            this.croppedImage = null;
            this.isDragging = false;

            for (let s = 3; s <= 7; s++) {
                this.apiResult["step" + s] = { serials: [] };
            }
        },

        async saveSerial(detectedSerial) {
            const step = this.currentStep;
            if (step < 4 || step > 8) return;

            const idx = step - 4;
            this.serialNumbers[idx] = (detectedSerial || "").trim();
            await this.$nextTick();
            await this.processSerial();
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
                (i) => i.itemnumber === item.itemnumber,
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

            // Convert to base64
            const reader = new FileReader();
            reader.onload = (e) => {
                this.imageUrl = e.target.result;

                // Register image into scanner
                if (this.$refs.scanner) {
                    this.$refs.scanner.capturedImages.push({
                        data: e.target.result,
                        timestamp: new Date().toLocaleTimeString(),
                        step: this.currentStep,
                    });
                }
            };
            reader.readAsDataURL(file);

            // 🚫 If AI disabled, stop here
            if (!this.useAiDetection) {
                this.$refs.scanner?.showScanSuccess(
                    "📸 Image captured (AI OFF)",
                );
                return;
            }

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

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log("🔍 Serial detection result:", result);

                // ✅ Dynamic step storage
                this.apiResult["step" + this.currentStep] = result;

                if (result.serials && result.serials.length > 0) {
                    const detectedSerial =
                        result.serials[0].text || result.serials[0];

                    const index = this.currentStep - 4;

                    // ✅ Store in serialNumbers array
                    this.serialNumbers[index] = detectedSerial;

                    this.$refs.scanner?.showScanSuccess(
                        `✅ Serial #${index + 1} detected: ${detectedSerial}`,
                    );
                } else {
                    this.$refs.scanner?.showScanWarning(
                        "⚠️ No serials detected in image.",
                    );
                }
            } catch (error) {
                console.error("Upload error:", error);
                this.$refs.scanner?.showScanError(
                    "❌ Failed to detect serial number.",
                );
            } finally {
                this.loading = false;
            }
        },

        isInvalidSerial(serial) {
            const RESTRICTED_PREFIXES = ["SI", "BKT", "RPN"];

            const startsWithRestrictedPrefix = new RegExp(
                `^(${RESTRICTED_PREFIXES.join("|")})`,
                "i",
            );

            const lettersOnlyRegex = /^[A-Z]+$/i;

            const value = serial.trim();

            console.log(
                startsWithRestrictedPrefix.test(value) ||
                    lettersOnlyRegex.test(value),
            );

            return (
                startsWithRestrictedPrefix.test(value) ||
                lettersOnlyRegex.test(value)
            );
        },

        async handleImageUploadFromCamera(imageData) {
            if (!this.useAiDetection) return;

            const blob = await (await fetch(imageData)).blob();
            const file = new File([blob], "camera.jpg", { type: "image/jpeg" });

            await this.handleImageUpload(file);
        },
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.first = 0;
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
