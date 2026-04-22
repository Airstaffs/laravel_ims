import { eventBus } from "../../components/eventbus";
import "../../../css/modules.css";
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

            //pagination
            currentPage: 1,
            totalRecords: 1,
            perPage: 10, // Default rows per page
            first: 0, //paginator internal state

            showCleaningWorkLog: false,
            cleaningWorkLogItem: null,
            cleaningWorkLogCategories: [],
            cleaningWorkLogValues: {},
            cleaningWorkLogOpenedAt: null,
            savingCleaningWorkLog: false,
            currentUser: "",
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
            // priority: local preview -> server path -> default
            return (
                this.serialImageUrl ||
                this.serialImagePath ||
                this.defaultSerialImage
            );
        },

        cleaningDateTime() {
            if (!this.cleaningWorkLogOpenedAt) return "—";
            return this.cleaningWorkLogOpenedAt.toLocaleString("en-US", {
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
                hour12: true,
            });
        },
    },

    methods: {
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

        // Fetch inventory data from the API
        async fetchInventory() {
            this.loading = true;

            try {
                console.log("Fetching inventory with params:", {
                    search: this.searchQuery,
                    page: this.currentPage,
                    per_page: this.perPage,
                    location: "Cleaning",
                    include_images: true,
                });

                const response = await axios.get(
                    `${API_BASE_URL}/api/cleaning/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            location: "Cleaning",
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
                const response = await axios.get(`${API_BASE_URL}/products`);
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

        // Open Cleaning Work Log dialog
        openCleaningWorkLog(item) {
            this.cleaningWorkLogItem = item;
            this.cleaningWorkLogOpenedAt = new Date();
            this.cleaningWorkLogCategories = this.loadCleaningCategories(
                item.ASINviewer || item.ASIN || item.asin,
            );

            // Pre-fill with defaults then any previously saved values
            const saved = this.loadSavedCleaningValues(item.rtcounter);
            const prefilled = {};
            this.cleaningWorkLogCategories.forEach((cat) => {
                prefilled[cat.name + "__status"] =
                    saved[cat.name + "__status"] ?? "";
                prefilled[cat.name + "__notes"] =
                    saved[cat.name + "__notes"] ?? "";
                (cat.actions || []).forEach((action) => {
                    const key = cat.name + "__action__" + action.title;
                    prefilled[key] = saved[key] ?? false;
                });
            });
            this.cleaningWorkLogValues = prefilled;
            this.showCleaningWorkLog = true;
        },

        // Load merged cleaning categories from localStorage (global + ASIN-specific)
        loadCleaningCategories(asin) {
            if (!asin) return [];

            const parse = (key) => {
                try {
                    const r = localStorage.getItem(key);
                    return r ? JSON.parse(r) : [];
                } catch {
                    return [];
                }
            };

            const globalCats = parse("asin_global_config_cleaning");
            const asinCats = parse(`asin_config_cleaning:${asin}`);

            const markedGlobals = globalCats.map((c) => ({
                ...c,
                _fromGlobal: true,
            }));
            const asinNames = new Set(asinCats.map((c) => c.name));

            return [
                ...markedGlobals.filter((c) => !asinNames.has(c.name)),
                ...asinCats,
            ];
        },

        // Load previously saved values for this rtcounter
        loadSavedCleaningValues(rtcounter) {
            if (!rtcounter) return {};
            try {
                const raw = localStorage.getItem(
                    `cleaning_worklog:${rtcounter}`,
                );
                return raw ? JSON.parse(raw) : {};
            } catch {
                return {};
            }
        },

        // Auto-fill notes placeholder based on selected status + action descriptions
        getCleaningNotePlaceholder(cat, status) {
            if (!status || status === "Not Required")
                return "Notes will auto-fill based on selection...";
            if (status === "Done") return "All tasks completed successfully.";
            if (status === "In Progress")
                return "Describe what is in progress...";
            if (status === "Needs Attention")
                return "Describe what needs attention...";
            return "Notes will auto-fill based on selection...";
        },

        // Auto-fill notes when status changes
        onCleaningStatusChange(cat) {
            const statusKey = cat.name + "__status";
            const notesKey = cat.name + "__notes";
            const status = this.cleaningWorkLogValues[statusKey];

            // Only auto-fill if notes are empty
            if (this.cleaningWorkLogValues[notesKey]) return;

            if (status === "Done") {
                this.cleaningWorkLogValues[notesKey] =
                    "All tasks completed successfully.";
            } else if (status === "Not Required") {
                this.cleaningWorkLogValues[notesKey] =
                    "Not required for this item.";
            }
        },

        // Save cleaning work log — if markDone=true, move to Packaging
        async saveCleaningWorkLog(markDone = false) {
            if (!this.cleaningWorkLogItem?.rtcounter) return;

            this.savingCleaningWorkLog = true;

            const asin =
                this.cleaningWorkLogItem.ASINviewer ||
                this.cleaningWorkLogItem.ASIN ||
                this.cleaningWorkLogItem.asin;

            const cleanedBy =
                this.cleaningWorkLogItem.received_by ||
                this.cleaningWorkLogItem.Username ||
                this.currentUser ||
                null;

            const dateCleaned = this.cleaningWorkLogOpenedAt
                ? this.cleaningWorkLogOpenedAt.toLocaleString("en-US", {
                      year: "numeric",
                      month: "2-digit",
                      day: "2-digit",
                      hour: "2-digit",
                      minute: "2-digit",
                      hour12: true,
                  })
                : null;

            // ── Auto-fill empty statuses as "Done" when marking complete ──────
            if (markDone) {
                const filled = { ...this.cleaningWorkLogValues };

                Object.keys(filled).forEach((key) => {
                    if (key.endsWith("__status") && !filled[key]) {
                        filled[key] = "Done";
                    }
                });

                this.cleaningWorkLogCategories.forEach((cat) => {
                    const statusKey = cat.name + "__status";
                    if (!filled[statusKey]) {
                        filled[statusKey] = "Done";
                    }
                });

                this.cleaningWorkLogValues = filled;
            }
            console.log(
                "After auto-fill:",
                JSON.stringify(this.cleaningWorkLogValues),
            );
            // ──────────────────────────────────────────────────────────────────

            console.log("📤 Saving cleaning work log to DB:", {
                rtcounter: String(this.cleaningWorkLogItem.rtcounter),
                asin,
                cleaned_by: cleanedBy,
                date_cleaned: dateCleaned,
                mark_done: markDone,
                category_values: this.cleaningWorkLogValues,
            });

            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/cleaning/work-log`,
                    {
                        rtcounter: String(this.cleaningWorkLogItem.rtcounter),
                        asin,
                        cleaned_by: cleanedBy,
                        date_cleaned: dateCleaned,
                        mark_done: markDone,
                        category_values: this.cleaningWorkLogValues,
                    },
                );
                console.log("✅ DB save response:", response.data);
            } catch (e) {
                console.error("❌ Failed to save cleaning work log:", e);
                Swal.fire({
                    icon: "error",
                    title: "Save Failed",
                    text:
                        e.response?.data?.message ||
                        "Failed to save work log. Please try again.",
                });
                this.savingCleaningWorkLog = false;
                return;
            }

            const item = { ...this.cleaningWorkLogItem };

            // Reset dialog
            this.showCleaningWorkLog = false;
            this.cleaningWorkLogItem = null;
            this.cleaningWorkLogCategories = [];
            this.cleaningWorkLogValues = {};

            if (markDone) {
                await Swal.fire({
                    icon: "success",
                    title: "Cleaning Complete! ✓",
                    html: `
                        <p>Work log saved successfully.</p>
                        <p>Moving <strong>${this.getDisplayTitle(item)}</strong>
                        to <strong>Validation</strong>.</p>
                    `,
                    confirmButtonText: "OK",
                });
                await this.moveToValidation(item);
            } else {
                Swal.fire({
                    icon: "success",
                    title: "Progress Saved!",
                    text: "Cleaning work log saved. You can continue later.",
                    timer: 1800,
                    showConfirmButton: false,
                });
            }

            this.savingCleaningWorkLog = false;
        },

        // Move to Validation after cleaning done
        async moveToValidation(item) {
            // ✅ was moveToPackaging
            if (!item?.ProductID) return;
            try {
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                Swal.fire({
                    title: "Moving to Validation...", // ✅ was "Moving to Packaging..."
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                });

                const response = await axios.post(
                    `${API_BASE_URL}/api/cleaning/move-to-validation`, // ✅ updated route
                    {
                        product_id: item.ProductID,
                        rt_counter: item.rtcounter,
                        current_location: "Cleaning",
                        new_location: "Validation", // ✅ was "Packaging"
                    },
                    { headers: { "X-CSRF-TOKEN": csrfToken } },
                );

                Swal.close();

                if (response.data.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Moved to Validation!", // ✅
                        text: `Item ${item.rtcounter} successfully moved to Validation.`, // ✅
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
                            "Failed to move item to Validation.", // ✅
                    });
                }
            } catch (error) {
                Swal.close();
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        error.response?.data?.message ||
                        "An error occurred while moving to Validation.", // ✅
                });
            }
        },

        // Move to Packaging after cleaning done
        async moveToPackaging(item) {
            if (!item?.ProductID) return;
            try {
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                Swal.fire({
                    title: "Moving to Packaging...",
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                });

                const response = await axios.post(
                    `${API_BASE_URL}/api/cleaning/move-to-packaging`,
                    {
                        product_id: item.ProductID,
                        rt_counter: item.rtcounter,
                        current_location: "Cleaning",
                        new_location: "Packaging",
                    },
                    { headers: { "X-CSRF-TOKEN": csrfToken } },
                );

                Swal.close();

                if (response.data.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Moved to Packaging!",
                        text: `Item ${item.rtcounter} successfully moved to Packaging.`,
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
                            "Failed to move item to Packaging.",
                    });
                }
            } catch (error) {
                Swal.close();
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        error.response?.data?.message ||
                        "An error occurred while moving to Packaging.",
                });
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
