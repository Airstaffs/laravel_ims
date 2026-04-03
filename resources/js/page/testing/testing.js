import { eventBus } from "../../components/eventbus";
import "../../../css/modules.css";
import "./testing.css";
import { DEFAULT_IMAGE } from "../../constant";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ProductList",
    data() {
        return {
            inventory: [],
            loading: true,
            selectAll: false,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,
            defaultImage: DEFAULT_IMAGE,
            ProductTitle: "",
            isLoadingImages: false,
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
            error: null,

            //pagination
            currentPage: 1,
            totalRecords: 1,
            perPage: 10,

            first: 0,

            regularImages: [],
            capturedImages: [],
            activeTab: "regular",
            currentImageSet: [],

            showTestingWorkLog: false,
            testingWorkLogItem: null,
            testingWorkLogFields: [],
            testingWorkLogValues: {},
            testingWorkLogOpenedAt: null,
            currentUser: "",
            testResult: null,
        };
    },

    computed: {
        searchQuery() {
            return eventBus.searchQuery;
        },
        sortedInventory() {
            if (!this.inventory || !Array.isArray(this.inventory)) {
                return [];
            }

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
    },

    methods: {
        handleImageError(event) {
            event.target.src = this.defaultImage;
            event.target.onerror = null;
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
            if (!item || !item.capturedImages) return 0;

            console.log("🔍 Counting captured images for item:", {
                ProductID: item.ProductID,
                capturedImages: item.capturedImages,
            });

            let count = 0;
            const capturedImagesObj = item.capturedImages;

            // Check both capturedimg1-12 AND serialimg1-2
            for (let i = 1; i <= 12; i++) {
                const fieldName = `capturedimg${i}`;
                if (this.isValidImage(capturedImagesObj[fieldName])) {
                    count++;
                }
            }

            // Also check serial images
            if (this.isValidImage(capturedImagesObj.serialimg1)) count++;
            if (this.isValidImage(capturedImagesObj.serialimg2)) count++;

            console.log("🔍 Total captured images found:", count);
            return count;
        },

        // Count all images (regular + captured)
        countAllImages(item) {
            if (!item) {
                return 0;
            }

            // If captured images exist, count them
            if (item.capturedImages) {
                let capturedCount = 0;
                const capturedImagesObj = item.capturedImages;

                // Count capturedimg1-12
                for (let i = 1; i <= 12; i++) {
                    const fieldName = `capturedimg${i}`;
                    if (this.isValidImage(capturedImagesObj[fieldName])) {
                        capturedCount++;
                    }
                }

                // If we have captured images, return that count
                if (capturedCount > 0) {
                    return capturedCount;
                }
            }

            // Otherwise count regular product images (fallback)
            return this.countRegularImages(item);
        },

        transformDataForGallery(data) {
            if (!data) {
                return {};
            }

            if (data.capturedImages && data.capturedImages.capturedimg1) {
                const transformedData = { ...data };
                const companyFolder = data.company || "Airstaffs";

                for (let i = 1; i <= 12; i++) {
                    const capturedImg = data.capturedImages[`capturedimg${i}`];
                    if (capturedImg) {
                        transformedData[`img${i}`] =
                            `/images/product_images/${companyFolder}/${capturedImg}`;
                    } else {
                        transformedData[`img${i}`] = null;
                    }
                }

                for (let i = 13; i <= 15; i++) {
                    transformedData[`img${i}`] = null;
                }

                return transformedData;
            }

            return data;
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

        openImageModal(item) {
            if (!item) return;

            this.regularImages = [];
            this.capturedImages = [];
            this.currentImageIndex = 0;
            this.ProductTitle = item.ProductTitle;
            const companyFolder = item.company || "Airstaffs";

            console.log("🔍 Opening image modal for item:", {
                ProductID: item.ProductID,
                rtcounter: item.rtcounter,
                company: companyFolder,
                capturedImages: item.capturedImages,
            });

            // Load regular images (img1 - img15)
            for (let i = 1; i <= 15; i++) {
                const fieldName = `img${i}`;
                if (this.isValidImage(item[fieldName])) {
                    const path = `/images/thumbnails/${item[fieldName]}`;
                    this.regularImages.push(path);
                }
            }

            console.log("📸 Regular images loaded:", this.regularImages.length);

            // ✅ FIXED: Load captured images properly
            if (
                item.capturedImages &&
                typeof item.capturedImages === "object"
            ) {
                const capturedImagesObj = item.capturedImages;

                console.log(
                    "🔍 Processing captured images:",
                    capturedImagesObj,
                );

                // Load capturedimg1 - capturedimg12
                for (let i = 1; i <= 12; i++) {
                    const fieldName = `capturedimg${i}`;
                    if (this.isValidImage(capturedImagesObj[fieldName])) {
                        const filename = capturedImagesObj[fieldName];
                        const path = `/images/product_images/${companyFolder}/${filename}`;
                        this.capturedImages.push(path);
                        console.log(`✅ Added captured image ${i}:`, path);
                    }
                }

                // Load serial images (serialimg1 and serialimg2)
                if (this.isValidImage(capturedImagesObj.serialimg1)) {
                    const filename = capturedImagesObj.serialimg1;
                    const path = `/images/product_images/${companyFolder}/${filename}`;
                    this.capturedImages.push(path);
                    console.log("✅ Added serial image 1:", path);
                }

                if (this.isValidImage(capturedImagesObj.serialimg2)) {
                    const filename = capturedImagesObj.serialimg2;
                    const path = `/images/product_images/${companyFolder}/${filename}`;
                    this.capturedImages.push(path);
                    console.log("✅ Added serial image 2:", path);
                }
            }

            console.log(
                "📸 Total captured images loaded:",
                this.capturedImages.length,
            );

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

        nextImage() {
            if (this.currentImageIndex < this.currentImageSet.length - 1) {
                this.currentImageIndex++;
            } else {
                this.currentImageIndex = 0;
            }
        },

        prevImage() {
            if (this.currentImageIndex > 0) {
                this.currentImageIndex--;
            } else {
                this.currentImageIndex = this.currentImageSet.length - 1;
            }
        },

        getDisplayTitle(item) {
            if (!item) return "—";

            // Priority: system_title > internal > AStitle > ProductTitle
            if (item.system_title && item.system_title.trim() !== "") {
                return item.system_title;
            }

            if (item.internal && item.internal.trim() !== "") {
                return item.internal;
            }

            if (item.AStitle && item.AStitle.trim() !== "") {
                return item.AStitle;
            }

            if (item.ProductTitle && item.ProductTitle.trim() !== "") {
                return item.ProductTitle;
            }

            return "—";
        },

        // For FNSKU modal display (uses backend's astitle field)
        getFnskuDisplayTitle(fnskuItem) {
            if (!fnskuItem) return "—";

            // Backend already prioritizes: system_title > internal via COALESCE
            if (fnskuItem.astitle && fnskuItem.astitle.trim() !== "") {
                return fnskuItem.astitle;
            }

            // Fallbacks if astitle is missing
            if (
                fnskuItem.system_title &&
                fnskuItem.system_title.trim() !== ""
            ) {
                return fnskuItem.system_title;
            }

            if (fnskuItem.internal && fnskuItem.internal.trim() !== "") {
                return fnskuItem.internal;
            }

            return "—";
        },

        // ✅ Updated to match received.js pattern
        async fetchInventory() {
            this.loading = true;

            try {
                console.log("Fetching inventory with params:", {
                    search: this.searchQuery,
                    page: this.currentPage,
                    per_page: this.perPage,
                    location: "Testing",
                    include_images: true,
                });

                const response = await axios.get(
                    `${API_BASE_URL}/api/testing/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            location: "Testing",
                            include_images: true,
                        },
                    },
                );

                console.log("API Response:", response.data); // ADD THIS
                console.log("Data array:", response.data.data); // ADD THIS
                console.log("Data count:", response.data.data?.length); // ADD THIS

                // Process the returned data
                this.inventory = response.data.data;
                this.totalRecords = response.data.total;

                // Debug first item
                if (this.inventory.length > 0) {
                    console.log("First item structure:", this.inventory[0]);
                    if (this.inventory[0].capturedImages) {
                        console.log(
                            "First item capturedImages:",
                            this.inventory[0].capturedImages,
                        );
                    }
                }
            } catch (error) {
                console.error("Error fetching inventory data:", error);
                console.error("Error response:", error.response); // ADD THIS
            } finally {
                this.loading = false;
            }
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
            if (this.fetchSerialImageIfAny) {
                await this.fetchSerialImageIfAny();
            }
        },

        closeEditModal() {
            this.showEditModal = false;

            if (this.resetSerialImage) {
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
                const response = await axios.get(
                    `${API_BASE_URL}/api/testing/products`,
                    {
                        params: {
                            location: "Received",
                            per_page: 999, // Get all items for dropdown filters
                        },
                    },
                );
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

        // Select PASS or FAIL — auto-fills field values on PASS
        selectTestResult(result) {
            this.testResult = result;

            if (result === "pass") {
                // Auto-fill all fields with their defaultValue or first option
                this.testingWorkLogFields.forEach((f) => {
                    if (
                        f.type === "Dropdown/Select" &&
                        f.hasOptions &&
                        f.options?.length
                    ) {
                        // Use first option as the "OK/Good" default
                        this.testingWorkLogValues[f.label] =
                            f.options[0]?.value ?? f.defaultValue ?? "";
                    } else if (f.type === "Checkbox") {
                        this.testingWorkLogValues[f.label] = true;
                    } else {
                        this.testingWorkLogValues[f.label] =
                            f.defaultValue || "OK";
                    }
                });
            } else {
                // FAIL — clear all values so worker selects manually
                this.testingWorkLogFields.forEach((f) => {
                    this.testingWorkLogValues[f.label] = "";
                });
            }
        },

        // Open the Testing Work Log dialog for a given item
        openTestingWorkLog(item) {
            this.testingWorkLogItem = item;
            this.testingWorkLogFields = this.loadTestingFields(
                item.ASINviewer || item.ASIN || item.asin,
            );

            // Snapshot current datetime when dialog opens
            this.testingWorkLogOpenedAt = new Date();

            // Pre-fill: defaults first, then any previously saved values
            const saved = this.loadSavedTestingValues(item.rtcounter);
            const prefilled = {};
            this.testingWorkLogFields.forEach((f) => {
                prefilled[f.label] = saved[f.label] ?? f.defaultValue ?? "";
            });
            this.testingWorkLogValues = prefilled;

            this.testResult = null; // reset decision
            this.showTestingWorkLog = true;
        },

        // Load merged testing fields from localStorage (global + ASIN-specific)
        loadTestingFields(asin) {
            if (!asin) return [];

            const parse = (key) => {
                try {
                    const r = localStorage.getItem(key);
                    return r ? JSON.parse(r) : [];
                } catch {
                    return [];
                }
            };

            const globalFields = parse("asin_global_config_testing");
            const asinFields = parse(`asin_config_testing:${asin}`);

            // Mark globals, merge — ASIN label overrides global of same name
            const markedGlobals = globalFields.map((f) => ({
                ...f,
                _fromGlobal: true,
            }));
            const asinLabels = new Set(asinFields.map((f) => f.label));

            return [
                ...markedGlobals.filter((f) => !asinLabels.has(f.label)),
                ...asinFields,
            ];
        },

        // Load previously saved values for this rtcounter from localStorage
        loadSavedTestingValues(rtcounter) {
            if (!rtcounter) return {};
            try {
                const raw = localStorage.getItem(
                    `testing_worklog:${rtcounter}`,
                );
                return raw ? JSON.parse(raw) : {};
            } catch {
                return {};
            }
        },

        // Save current form values to localStorage
        async saveTestingWorkLog() {
            if (!this.testingWorkLogItem?.rtcounter) return;

            // Validate required fields
            const missing = this.testingWorkLogFields.filter(
                (f) => f.required && !this.testingWorkLogValues[f.label],
            );
            if (missing.length) {
                Swal.fire({
                    icon: "warning",
                    title: "Required Fields Missing",
                    text: `Please fill in: ${missing.map((f) => f.label).join(", ")}`,
                });
                return;
            }

            // Must select PASS or FAIL
            if (!this.testResult) {
                Swal.fire({
                    icon: "warning",
                    title: "Select Test Result",
                    text: "Please select PASS or FAIL before saving.",
                });
                return;
            }

            // Save to localStorage
            const payload = {
                ...this.testingWorkLogValues,
                __testResult: this.testResult,
                __dateTested: this.testingWorkLogOpenedAt
                    ? this.testingWorkLogOpenedAt.toLocaleString("en-US", {
                          year: "numeric",
                          month: "2-digit",
                          day: "2-digit",
                          hour: "2-digit",
                          minute: "2-digit",
                          hour12: true,
                      })
                    : null,
                __tester:
                    this.testingWorkLogItem.received_by ||
                    this.testingWorkLogItem.Username ||
                    this.currentUser ||
                    null,
            };

            localStorage.setItem(
                `testing_worklog:${this.testingWorkLogItem.rtcounter}`,
                JSON.stringify(payload),
            );

            // Capture before resetting
            const item = { ...this.testingWorkLogItem };
            const testResult = this.testResult;

            // Reset dialog
            this.showTestingWorkLog = false;
            this.testingWorkLogItem = null;
            this.testingWorkLogFields = [];
            this.testingWorkLogValues = {};
            this.testResult = null;

            // ── Move directly without waiting for Swal ─────────────────────
            if (testResult === "pass") {
                await Swal.fire({
                    icon: "success",
                    title: "All Tests Passed! ✓",
                    html: `
                <p>Work log saved successfully.</p>
                <p>Moving <strong>${this.getDisplayTitle(item)}</strong>
                to <strong>Cleaning</strong>.</p>
            `,
                    confirmButtonText: "OK",
                });
                console.log(
                    "✅ PASS — calling moveToCleaning with item:",
                    item.ProductID,
                );
                await this.moveToCleaning(item);
            } else {
                await Swal.fire({
                    icon: "warning",
                    title: "Tests Failed ✗",
                    html: `
                <p>Work log saved successfully.</p>
                <p>Moving <strong>${this.getDisplayTitle(item)}</strong>
                to <strong>Repair</strong>.</p>
            `,
                    confirmButtonText: "OK",
                });
                console.log(
                    "✅ FAIL — calling moveToRepair with item:",
                    item.ProductID,
                );
                await this.moveToRepair(item);
            }
        },

        // Get the pre-typed note for the currently selected option value
        getPreTypedNote(field, selectedValue) {
            if (
                !field.preTypedNotes ||
                !field.options?.length ||
                !selectedValue
            )
                return null;
            const opt = field.options.find((o) => o.value === selectedValue);
            return opt?.hasNote ? opt.note : null;
        },

        // Get the pre-typed note for the currently selected option value
        getPreTypedNote(field, selectedValue) {
            if (
                !field.preTypedNotes ||
                !field.options?.length ||
                !selectedValue
            )
                return null;
            const opt = field.options.find((o) => o.value === selectedValue);
            return opt?.hasNote ? opt.note : null;
        },

        // ── CLEANING ──────────────────────────────────────────────────
        async confirmMoveToCleaning(item = null) {
            // Called from saveTestingWorkLog — use the passed item directly
            if (item && item.ProductID) {
                await this.moveToCleaning(item);
                return;
            }

            // Called from the condition modal flow — use moveItemDetails
            if (!this.moveItemDetails) return;

            this.movingItem = true;
            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/testing/move-to-cleaning`,
                    {
                        item_number: this.moveItemDetails.itemnumber,
                        product_id: String(this.moveItemDetails.ProductID),
                        rt_counter: this.moveItemDetails.rtcounter,
                        current_location: "Testing",
                    },
                );

                if (response.data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Moved!",
                        text: "Item moved to Cleaning & Prepping module successfully",
                        timer: 2000,
                        showConfirmButton: false,
                    });
                    this.showMoveConfirmation = false;
                    this.moveItemDetails = null;
                    await this.fetchInventory();
                }
            } catch (error) {
                const errorMessage = error.response?.data?.errors
                    ? Object.values(error.response.data.errors)
                          .flat()
                          .join("\n")
                    : error.response?.data?.message ||
                      "Failed to move item to Cleaning module";

                Swal.fire({
                    icon: "error",
                    title: "Error Moving Item",
                    text: errorMessage,
                    confirmButtonText: "OK",
                });
            } finally {
                this.movingItem = false;
            }
        },

        async moveToCleaning(item) {
            if (!item || !item.ProductID) {
                await Swal.fire({
                    icon: "error",
                    title: "Invalid Item",
                    text: "The selected item does not have a valid Product ID.",
                });
                return;
            }

            try {
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                Swal.fire({
                    title: "Moving to Cleaning...",
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                });

                const response = await axios.post(
                    `${API_BASE_URL}/api/testing/move-to-cleaning`, // ← testing endpoint
                    {
                        product_id: item.ProductID,
                        rt_counter: item.rtcounter,
                        current_location: "Testing", // ← correct location
                        new_location: "Cleaning",
                    },
                    { headers: { "X-CSRF-TOKEN": csrfToken } },
                );

                Swal.close();

                if (response.data.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Moved to Cleaning!",
                        text: `Item ${item.rtcounter} successfully moved to Cleaning.`,
                        confirmButtonText: "OK",
                    });
                    if (typeof this.fetchInventory === "function")
                        this.fetchInventory();
                } else {
                    await Swal.fire({
                        icon: "warning",
                        title: "Failed",
                        text:
                            response.data.message ||
                            "Failed to move item to Cleaning.",
                    });
                }
            } catch (error) {
                console.error("Error moving item to Cleaning:", error);
                Swal.close();

                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        error.response?.data?.message ||
                        "An error occurred while moving the item to Cleaning. Please try again.",
                });
            }
        },

        // ── REPAIR ────────────────────────────────────────────────────
        async confirmMoveToRepair(item) {
            if (!item || !item.ProductID) {
                await Swal.fire({
                    icon: "error",
                    title: "Invalid Item",
                    text: "The selected item does not have a valid Product ID.",
                });
                return;
            }

            const result = await Swal.fire({
                title: "Confirm Move to Repair",
                html: `
            <p>Are you sure you want to move
            <strong>${this.getDisplayTitle(item)}</strong>
            to <strong>Repair</strong>?</p>
            <ul style="text-align:left">
                <li><strong>RT Counter:</strong> ${item.rtcounter || "N/A"}</li>
                <li><strong>ASIN:</strong> ${item.ASINviewer || item.ASIN || "—"}</li>
                <li><strong>FNSKU:</strong> ${item.FNSKUviewer || item.FNSKU || "—"}</li>
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
                await this.moveToRepair(item);
            }
        },

        async moveToRepair(item) {
            if (!item || !item.ProductID) {
                await Swal.fire({
                    icon: "error",
                    title: "Invalid Item",
                    text: "The selected item does not have a valid Product ID.",
                });
                return;
            }

            try {
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                Swal.fire({
                    title: "Moving to Repair...",
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                });

                const response = await axios.post(
                    `${API_BASE_URL}/api/testing/move-to-repair`, // ← testing endpoint
                    {
                        product_id: item.ProductID,
                        rt_counter: item.rtcounter,
                        current_location: "Testing", // ← correct location
                        new_location: "Repair",
                    },
                    { headers: { "X-CSRF-TOKEN": csrfToken } },
                );

                Swal.close();

                if (response.data.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Moved to Repair!",
                        text: `Item ${item.rtcounter} successfully moved to Repair.`,
                        confirmButtonText: "OK",
                    });
                    if (typeof this.fetchInventory === "function")
                        this.fetchInventory();
                } else {
                    await Swal.fire({
                        icon: "warning",
                        title: "Failed",
                        text:
                            response.data.message ||
                            "Failed to move item to Repair.",
                    });
                }
            } catch (error) {
                console.error("Error moving item to Repair:", error);
                Swal.close();

                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        error.response?.data?.message ||
                        "An error occurred while moving the item to Repair. Please try again.",
                });
            }
        },
    },

    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.first = 0;
            this.fetchInventory();
        },
    },

    mounted() {
        this.fetchInventory();

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
        this.handleKeyDown = handleKeyDown;
    },

    beforeDestroy() {
        if (this.handleKeyDown) {
            window.removeEventListener("keydown", this.handleKeyDown);
        }
    },
};
