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
            error: null,

            // pagination
            currentPage: 1,
            totalRecords: 1,
            perPage: 10,
            first: 0,

            showRepairWorkLog: false,
            repairWorkLogItem: null,
            repairWorkLogCategories: [],
            repairWorkLogValues: {},
            repairWorkLogOpenedAt: null,
            savingRepairWorkLog: false,
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

        repairDateTime() {
            if (!this.repairWorkLogOpenedAt) return "—";
            return this.repairWorkLogOpenedAt.toLocaleString("en-US", {
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
            event.target.src = this.defaultImage;
            event.target.onerror = null;
        },

        isValidImage(path) {
            return path && path !== "NULL" && path.trim() !== "";
        },

        countImages(item, prefix, start, end, container = null) {
            if (!item) return 0;
            const source = container ? item[container] : item;
            if (!source) return 0;
            let count = 0;
            for (let i = start; i <= end; i++) {
                if (this.isValidImage(source[`${prefix}${i}`])) count++;
            }
            return count;
        },

        countRegularImages(item) {
            return this.countImages(item, "img", 2, 15);
        },

        countCapturedImages(item) {
            if (!item || !item.capturedImages) return 0;
            let count = 0;
            const c = item.capturedImages;
            for (let i = 1; i <= 12; i++) {
                if (this.isValidImage(c[`capturedimg${i}`])) count++;
            }
            if (this.isValidImage(c.serialimg1)) count++;
            if (this.isValidImage(c.serialimg2)) count++;
            return count;
        },

        countAllImages(item) {
            if (!item) return 0;
            if (item.capturedImages) {
                let capturedCount = 0;
                const c = item.capturedImages;
                for (let i = 1; i <= 12; i++) {
                    if (this.isValidImage(c[`capturedimg${i}`]))
                        capturedCount++;
                }
                if (capturedCount > 0) return capturedCount;
            }
            return this.countRegularImages(item);
        },

        transformDataForGallery(data) {
            if (!data) return {};
            if (data.capturedImages && data.capturedImages.capturedimg1) {
                const transformedData = { ...data };
                const companyFolder = data.company || "Airstaffs";
                for (let i = 1; i <= 12; i++) {
                    const capturedImg = data.capturedImages[`capturedimg${i}`];
                    transformedData[`img${i}`] = capturedImg
                        ? `/images/product_images/${companyFolder}/${capturedImg}`
                        : null;
                }
                for (let i = 13; i <= 15; i++)
                    transformedData[`img${i}`] = null;
                return transformedData;
            }
            return data;
        },

        countAdditionalImages(item) {
            if (!item) return 0;
            let count = 0;
            for (let i = 2; i <= 15; i++) {
                const v = item[`img${i}`];
                if (v && v !== "NULL" && v.trim() !== "") count++;
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

            for (let i = 1; i <= 15; i++) {
                const fieldName = `img${i}`;
                if (this.isValidImage(item[fieldName])) {
                    this.regularImages.push(
                        `/images/thumbnails/${item[fieldName]}`,
                    );
                }
            }

            if (
                item.capturedImages &&
                typeof item.capturedImages === "object"
            ) {
                const c = item.capturedImages;
                for (let i = 1; i <= 12; i++) {
                    if (this.isValidImage(c[`capturedimg${i}`])) {
                        this.capturedImages.push(
                            `/images/product_images/${companyFolder}/${c[`capturedimg${i}`]}`,
                        );
                    }
                }
                if (this.isValidImage(c.serialimg1)) {
                    this.capturedImages.push(
                        `/images/product_images/${companyFolder}/${c.serialimg1}`,
                    );
                }
                if (this.isValidImage(c.serialimg2)) {
                    this.capturedImages.push(
                        `/images/product_images/${companyFolder}/${c.serialimg2}`,
                    );
                }
            }

            if (
                this.regularImages.length === 0 &&
                this.capturedImages.length === 0
            ) {
                this.regularImages.push(this.defaultImage);
            }

            this.activeTab = this.regularImages.length ? "regular" : "captured";
            this.currentImageSet =
                this.activeTab === "regular"
                    ? this.regularImages
                    : this.capturedImages;

            this.showImageModal = true;
            document.body.style.overflow = "hidden";
        },

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
            if (item.system_title && item.system_title.trim() !== "")
                return item.system_title;
            if (item.internal && item.internal.trim() !== "")
                return item.internal;
            if (item.AStitle && item.AStitle.trim() !== "") return item.AStitle;
            if (item.ProductTitle && item.ProductTitle.trim() !== "")
                return item.ProductTitle;
            return "—";
        },

        getFnskuDisplayTitle(fnskuItem) {
            if (!fnskuItem) return "—";
            if (fnskuItem.astitle && fnskuItem.astitle.trim() !== "")
                return fnskuItem.astitle;
            if (fnskuItem.system_title && fnskuItem.system_title.trim() !== "")
                return fnskuItem.system_title;
            if (fnskuItem.internal && fnskuItem.internal.trim() !== "")
                return fnskuItem.internal;
            return "—";
        },

        async fetchInventory() {
            this.loading = true;
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/repair/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            location: "Repair",
                            include_images: true,
                        },
                    },
                );
                this.inventory = response.data.data;
                this.totalRecords = response.data.total;
            } catch (error) {
                console.error("Error fetching repair inventory:", error);
            } finally {
                this.loading = false;
            }
        },

        onPageChange(event) {
            this.first = event.first;
            this.currentPage = event.page + 1;
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
            const freshItem = this.items.find(
                (i) => i.itemnumber === item.itemnumber,
            );
            this.item = { ...(freshItem || item) };
            this.showEditModal = true;
            document.body.style.overflow = "hidden";
            await this.$nextTick();
            await this.fetchSerialImageIfAny?.();
        },

        closeEditModal() {
            this.showEditModal = false;
            this.resetSerialImage?.({ clearServer: true });
            setTimeout(() => {
                document.body.style.overflow = "auto";
            }, 300);
        },

        onImageErrorMain(event) {
            event.target.src = this.defaultImage;
        },
        onThumbnailError(event) {
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
                const response = await axios.get(`${API_BASE_URL}/products`);
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

        // ── Repair Work Log ───────────────────────────────────────────────

        openRepairWorkLog(item) {
            this.repairWorkLogItem = item;
            this.repairWorkLogOpenedAt = new Date();
            this.repairWorkLogCategories = this.loadRepairCategories(
                item.ASINviewer || item.ASIN || item.asin,
            );

            const saved = this.loadSavedRepairValues(item.rtcounter);
            const prefilled = {};
            this.repairWorkLogCategories.forEach((cat) => {
                prefilled[cat.name + "__status"] =
                    saved[cat.name + "__status"] ?? "";
                prefilled[cat.name + "__notes"] =
                    saved[cat.name + "__notes"] ?? "";
                (cat.actions || []).forEach((action) => {
                    const key = cat.name + "__action__" + action.title;
                    prefilled[key] = saved[key] ?? false;
                });
            });
            this.repairWorkLogValues = prefilled;
            this.showRepairWorkLog = true;
        },

        loadRepairCategories(asin) {
            if (!asin) return [];
            const parse = (key) => {
                try {
                    const r = localStorage.getItem(key);
                    return r ? JSON.parse(r) : [];
                } catch {
                    return [];
                }
            };
            const globalCats = parse("asin_global_config_repair");
            const asinCats = parse(`asin_config_repair:${asin}`);
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

        loadSavedRepairValues(rtcounter) {
            if (!rtcounter) return {};
            try {
                const raw = localStorage.getItem(`repair_worklog:${rtcounter}`);
                return raw ? JSON.parse(raw) : {};
            } catch {
                return {};
            }
        },

        getRepairNotePlaceholder(cat, status) {
            if (!status || status === "Not Required")
                return "Notes will auto-fill based on selection...";
            if (status === "Done") return "All tasks completed successfully.";
            if (status === "In Progress")
                return "Describe what is in progress...";
            if (status === "Needs Attention")
                return "Describe what needs attention...";
            return "Notes will auto-fill based on selection...";
        },

        onRepairStatusChange(cat) {
            const statusKey = cat.name + "__status";
            const notesKey = cat.name + "__notes";
            const status = this.repairWorkLogValues[statusKey];
            if (this.repairWorkLogValues[notesKey]) return;
            if (status === "Done") {
                this.repairWorkLogValues[notesKey] =
                    "All tasks completed successfully.";
            } else if (status === "Not Required") {
                this.repairWorkLogValues[notesKey] =
                    "Not required for this item.";
            }
        },

        async saveRepairWorkLog(markDone = false) {
            if (!this.repairWorkLogItem?.rtcounter) return;

            this.savingRepairWorkLog = true;

            const asin =
                this.repairWorkLogItem.ASINviewer ||
                this.repairWorkLogItem.ASIN ||
                this.repairWorkLogItem.asin;

            const repairedBy =
                this.repairWorkLogItem.received_by ||
                this.repairWorkLogItem.Username ||
                this.currentUser ||
                null;

            const dateRepaired = this.repairWorkLogOpenedAt
                ? this.repairWorkLogOpenedAt.toLocaleString("en-US", {
                      year: "numeric",
                      month: "2-digit",
                      day: "2-digit",
                      hour: "2-digit",
                      minute: "2-digit",
                      hour12: true,
                  })
                : null;

            if (markDone) {
                const filled = { ...this.repairWorkLogValues };
                Object.keys(filled).forEach((key) => {
                    if (key.endsWith("__status") && !filled[key])
                        filled[key] = "Done";
                });
                this.repairWorkLogCategories.forEach((cat) => {
                    const statusKey = cat.name + "__status";
                    if (!filled[statusKey]) filled[statusKey] = "Done";
                });
                this.repairWorkLogValues = filled;
            }

            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/repair/work-log`,
                    {
                        rtcounter: String(this.repairWorkLogItem.rtcounter),
                        asin,
                        repaired_by: repairedBy,
                        date_repaired: dateRepaired,
                        mark_done: markDone,
                        category_values: this.repairWorkLogValues,
                    },
                );
                console.log("✅ Repair work log saved:", response.data);
            } catch (e) {
                console.error("❌ Failed to save repair work log:", e);
                Swal.fire({
                    icon: "error",
                    title: "Save Failed",
                    text:
                        e.response?.data?.message ||
                        "Failed to save work log. Please try again.",
                });
                this.savingRepairWorkLog = false;
                return;
            }

            const item = { ...this.repairWorkLogItem };
            this.showRepairWorkLog = false;
            this.repairWorkLogItem = null;
            this.repairWorkLogCategories = [];
            this.repairWorkLogValues = {};

            if (markDone) {
                await Swal.fire({
                    icon: "success",
                    title: "Repair Complete! ✓",
                    html: `<p>Work log saved successfully.</p>
                           <p>Moving <strong>${this.getDisplayTitle(item)}</strong> to <strong>Cleaning</strong>.</p>`,
                    confirmButtonText: "OK",
                });
                await this.moveToCleaning(item);
            } else {
                Swal.fire({
                    icon: "success",
                    title: "Progress Saved!",
                    text: "Repair work log saved. You can continue later.",
                    timer: 1800,
                    showConfirmButton: false,
                });
            }

            this.savingRepairWorkLog = false;
        },

        async moveToCleaning(item) {
            if (!item?.ProductID) return;
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
                    `${API_BASE_URL}/api/repair/move-to-cleaning`,
                    {
                        product_id: item.ProductID,
                        rt_counter: item.rtcounter,
                        current_location: "Repair",
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
                Swal.close();
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        error.response?.data?.message ||
                        "An error occurred while moving to Cleaning.",
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
