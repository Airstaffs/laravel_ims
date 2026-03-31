import { eventBus } from "../../components/eventbus";
import Swal from "sweetalert2";

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "AsinViewerModule",
    // Data properties
    data() {
        return {
            asinData: [],
            loading: true,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",

            // Store filter
            stores: [],
            selectedStore: "",

            // Color options
            colorOptions: [
                "Black",
                "White",
                "Gray",
                "Blue",
                "Green",
                "Red",
                "Yellow",
            ],

            // For ASIN details modal
            showAsinDetailsModal: false,
            selectedAsin: null,
            enlargeImage: false,

            // For instruction card modal
            showInstructionCardModal: false,

            // For ASIN image management modal
            showAsinImageModal: false,

            // Quantity Inside
            savingQuantityFor: null,
            savingColorFor: null,

            // For bulk instruction card upload modal
            showBulkInstructionCardModal: false,
            bulkUploadData: {
                asinList: "",
                files: { card1: null, card2: null, card3: null },
                uploading: false,
                uploadResults: { success: [], failed: [], skipped: [] },
            },

            // Edit mode
            editMode: false,
            editedAsin: {},

            // Image upload
            instructionCardUploading: false,
            instructionCardUrls: {},

            // User manual upload
            userManualUploading: false,
            userManualUrls: {},

            // ASIN image upload
            asinImageUploading: false,
            asinImageUrls: {},

            // Vector image upload
            vectorImageUploading: false,
            vectorImageUrls: {},

            // Saving states
            savingRelatedAsins: false,
            savingAsinDetails: false,
            savingDefaultDimensions: false,

            // Image handling
            defaultImagePath: "/images/default-product.png",
            isLoading: false,
            imageCacheBuster: {},

            // Pagination
            currentPage: 1,
            totalRecords: 0,
            perPage: 10,

            // ── Per-ASIN Config ──────────────────────────────────────
            showASINConfig: false,
            selectedConfig: {},
            labelingFields: [],
            testingFields: [],
            repairFields: [],
            cleaningFields: [],
            packagingImage: null,
            packagingComponents: [],
            boxSpecs: { size: "", type: "", weight: "", materials: "" },

            labelingCollapsed: false,
            testingCollapsed: false,
            repairCollapsed: false,
            cleaningCollapsed: false,
            packagingCollapsed: false,
            guideCollapsed: false,

            savingAll: false,
            publishing: false,

            // Field type options (shared by per-ASIN and global config)
            fieldTypeOptions: [
                "Text",
                "Number",
                "Dropdown/Select",
                "Checkbox",
                "Date",
                "Textarea",
            ],

            // ── Global Config ────────────────────────────────────────
            showGlobalConfig: false,
            savingGlobalConfig: false,

            globalLabelingCollapsed: false,
            globalTestingCollapsed: false,
            globalRepairCollapsed: false,
            globalCleaningCollapsed: false,
            globalPackagingCollapsed: false,

            globalLabelingFields: [],
            globalTestingFields: [],
            globalRepairFields: [],
            globalCleaningFields: [],
            globalPackagingComponents: [],
            globalBoxSpecs: { size: "", type: "", weight: "", materials: "" },

            // Inherited preview toggle (inside per-ASIN config dialog)
            showInheritedPreview: false,
        };
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery;
        },

        sortedAsinData() {
            if (!this.sortColumn) return this.asinData;
            return [...this.asinData].sort((a, b) => {
                const vA = a[this.sortColumn];
                const vB = b[this.sortColumn];
                if (typeof vA === "number" && typeof vB === "number") {
                    return this.sortOrder === "asc" ? vA - vB : vB - vA;
                }
                return this.sortOrder === "asc"
                    ? String(vA).localeCompare(String(vB))
                    : String(vB).localeCompare(String(vA));
            });
        },

        isMobile() {
            return window.innerWidth <= 768;
        },

        storeOptions() {
            return [
                { value: "", label: "All Stores" },
                ...this.stores.map((s) => ({ value: s, label: s })),
            ];
        },

        // ── Global Config computed ───────────────────────────────
        /** True when any global section has at least one entry */
        hasGlobalConfig() {
            return (
                this.globalLabelingFields.length > 0 ||
                this.globalTestingFields.length > 0 ||
                this.globalRepairFields.length > 0 ||
                this.globalCleaningFields.length > 0 ||
                this.globalPackagingComponents.length > 0 ||
                !!this.globalBoxSpecs.size
            );
        },

        /** Total inherited item count shown in the per-ASIN banner */
        globalInheritedCount() {
            return (
                this.globalLabelingFields.length +
                this.globalTestingFields.length +
                this.globalRepairFields.length +
                this.globalCleaningFields.length +
                this.globalPackagingComponents.length
            );
        },
    },
    methods: {
        // ── Image helpers ────────────────────────────────────────
        forceImageRefresh(asin, imageType, cardSlot = null) {
            const cacheBuster = Date.now();
            let keys = [];
            if (imageType === "instruction_card" && cardSlot) {
                keys.push(`${asin}_card${cardSlot}`);
            } else if (imageType === "instruction_card_main") {
                keys.push(`${asin}_card1`, `${asin}_card2`, `${asin}_card3`);
            } else if (imageType === "main") {
                keys.push(`${asin}_main`);
            } else if (imageType === "vector") {
                keys.push(`${asin}_vector`);
            }
            keys.forEach((key) => {
                this.imageCacheBuster[key] = cacheBuster;
            });
            this.$nextTick(() => {
                this.$forceUpdate();
            });
        },

        getImagePath(asin) {
            if (!asin) return this.defaultImagePath;
            const cb = this.imageCacheBuster[`${asin}_main`] || "";
            const cp = cb ? `?t=${cb}` : "";
            if (this.asinImageUrls[asin])
                return `${this.asinImageUrls[asin]}${cp}`;
            if (this.selectedAsin?.ASIN === asin) {
                if (this.selectedAsin.asinimg)
                    return `${window.location.origin}/images/asinimg/${this.selectedAsin.asinimg}${cp}`;
                if (this.selectedAsin.asin_image_url)
                    return `${this.selectedAsin.asin_image_url}${cp}`;
            }
            const d = this.asinData.find((i) => i.ASIN === asin);
            if (d) {
                if (d.asinimg)
                    return `${window.location.origin}/images/asinimg/${d.asinimg}${cp}`;
                if (d.asin_image_url) return `${d.asin_image_url}${cp}`;
            }
            return `/images/asinimg/${asin}_0.webp${cp}`;
        },

        getInstructionCardPath(asin, cardSlot = 1) {
            if (!asin) return this.defaultImagePath;
            const cb = this.imageCacheBuster[`${asin}_card${cardSlot}`] || "";
            const cp = cb ? `?t=${cb}` : "";
            const uploadKey = `${asin}_card${cardSlot}`;
            if (this.instructionCardUrls[uploadKey])
                return `${this.instructionCardUrls[uploadKey]}${cp}`;
            const src =
                this.selectedAsin?.ASIN === asin ? this.selectedAsin : null;
            const fallback = this.asinData.find((i) => i.ASIN === asin);
            for (const obj of [src, fallback]) {
                if (!obj) continue;
                if (cardSlot === 1 && obj.instructioncard)
                    return `${window.location.origin}/images/instructioncard/${obj.instructioncard}${cp}`;
                if (cardSlot === 2 && obj.instructioncard2)
                    return `${window.location.origin}/images/instructioncard/${obj.instructioncard2}${cp}`;
                if (cardSlot === 3 && obj.instructioncard3)
                    return `${window.location.origin}/images/instructioncard/${obj.instructioncard3}${cp}`;
                const cardUrl = obj.instruction_card_urls?.[`card${cardSlot}`];
                if (cardUrl) return `${cardUrl}${cp}`;
            }
            return this.defaultImagePath;
        },

        getMainInstructionCardPath(asin) {
            for (const slot of [1, 2, 3]) {
                const p = this.getInstructionCardPath(asin, slot);
                if (p !== this.defaultImagePath) return p;
            }
            return this.defaultImagePath;
        },

        getVectorImagePath(asin) {
            if (!asin) return null;
            const cb = this.imageCacheBuster[`${asin}_vector`] || "";
            const cp = cb ? `?t=${cb}` : "";
            if (this.vectorImageUrls[asin])
                return `${this.vectorImageUrls[asin]}${cp}`;
            const src =
                this.selectedAsin?.ASIN === asin ? this.selectedAsin : null;
            const fallback = this.asinData.find((i) => i.ASIN === asin);
            for (const obj of [src, fallback]) {
                if (!obj) continue;
                if (obj.vectorimage)
                    return `${window.location.origin}/images/asinvectorsimg/${obj.vectorimage}${cp}`;
                if (obj.vector_image_url) return `${obj.vector_image_url}${cp}`;
            }
            return null;
        },

        getMainAsinImagePath(asin) {
            const p = this.getImagePath(asin);
            return p !== this.defaultImagePath ? p : this.defaultImagePath;
        },

        getMainVectorImagePath(asin) {
            return (
                this.getVectorImagePath(asin) || this.createDefaultVectorSVG()
            );
        },

        hasVectorImage(asin) {
            return this.getVectorImagePath(asin) !== null;
        },

        getUserManualPath(asin) {
            if (!asin) return null;
            if (this.userManualUrls[asin]) return this.userManualUrls[asin];
            const src =
                this.selectedAsin?.ASIN === asin ? this.selectedAsin : null;
            const fallback = this.asinData.find((i) => i.ASIN === asin);
            for (const obj of [src, fallback]) {
                if (!obj) continue;
                if (obj.usermanuallink)
                    return `${window.location.origin}/images/usermanual/${obj.usermanuallink}`;
                if (obj.user_manual_url) return obj.user_manual_url;
            }
            return null;
        },

        hasUserManual(asin) {
            return this.getUserManualPath(asin) !== null;
        },

        hasInstructionCard(asin, cardSlot) {
            return (
                this.getInstructionCardPath(asin, cardSlot) !==
                this.defaultImagePath
            );
        },

        handleImageError(event, item) {
            event.target.src = this.defaultImagePath;
            if (item) item.useDefaultImage = true;
        },

        handleInstructionCardError(event) {
            event.target.src = this.defaultImagePath;
            event.target.style.opacity = "0.5";
        },

        createDefaultImageSVG() {
            return `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' fill='%23f0f0f0'/%3E%3Ctext x='50' y='50' text-anchor='middle' dy='0.3em' font-family='Arial' font-size='12' fill='%23999'%3ENo Image%3C/text%3E%3C/svg%3E`;
        },

        createDefaultVectorSVG() {
            return `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' fill='%23f8f9fa' stroke='%23dee2e6'/%3E%3Cpath d='M30 30h40v40H30z' fill='none' stroke='%236f42c1' stroke-width='2'/%3E%3Cpath d='M35 35l30 30M65 35l-30 30' stroke='%236f42c1' stroke-width='1'/%3E%3Ctext x='50' y='85' text-anchor='middle' font-family='Arial' font-size='8' fill='%23999'%3ENo Vector%3C/text%3E%3C/svg%3E`;
        },

        // ── Store management ─────────────────────────────────────
        async fetchStores() {
            try {
                const res = await axios.get(
                    `${API_BASE_URL}/api/asinlist/stores`,
                    {
                        withCredentials: true,
                    },
                );
                this.stores = res.data;
            } catch {
                this.stores = [];
            }
        },

        changeStore() {
            this.currentPage = 1;
            this.fetchAsinData();
        },

        // ── Data fetching ────────────────────────────────────────
        async fetchAsinData() {
            this.loading = true;
            try {
                const res = await axios.get(
                    `${API_BASE_URL}/api/asinlist/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            store: this.selectedStore,
                        },
                        withCredentials: true,
                    },
                );
                this.asinData = (res.data.data || []).map((item) => ({
                    ...item,
                    useDefaultImage: false,
                    fnskus: item.fnskus || [],
                }));
                this.totalRecords = res.data.total || 1;
            } catch {
                this.asinData = [];
            } finally {
                this.loading = false;
            }
        },

        // ── Pagination ───────────────────────────────────────────
        onPageChange(event) {
            this.first = event.first;
            this.currentPage = event.page + 1;
            this.perPage = event.rows;
            this.fetchAsinData();
        },

        // ── UI ───────────────────────────────────────────────────
        toggleDetails(index) {
            const updated = { ...this.expandedRows };
            updated[index] = !updated[index];
            this.expandedRows = updated;
        },

        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = column;
                this.sortOrder = "asc";
            }
        },

        handleResize() {
            this.$forceUpdate();
        },

        url(path) {
            if (!path) return this.defaultImagePath;
            if (path.startsWith("http")) return path;
            return `${window.location.origin}/${path}`;
        },

        // ── ASIN Details Modal ───────────────────────────────────
        viewAsinDetails(item) {
            this.selectedAsin = item;
            this.fnskuLimit = item.asin_limit || 0;
            this.showAsinDetailsModal = true;
        },

        closeAsinDetailsModal() {
            this.showAsinDetailsModal = false;
            this.selectedAsin = null;
            this.editMode = false;
            this.editedAsin = {};
            this.instructionCardUploading = false;
            this.userManualUploading = false;
            this.asinImageUploading = false;
            this.vectorImageUploading = false;
            this.savingAsinDetails = false;
            this.savingRelatedAsins = false;
            this.savingDefaultDimensions = false;
        },

        openInstructionCardModal() {
            this.showInstructionCardModal = true;
        },
        closeInstructionCardModal() {
            this.showInstructionCardModal = false;
            this.instructionCardUploading = false;
        },

        openAsinImageModal() {
            this.showAsinImageModal = true;
        },
        closeAsinImageModal() {
            this.showAsinImageModal = false;
            this.asinImageUploading = false;
            this.vectorImageUploading = false;
        },

        openBulkInstructionCardModal() {
            this.showBulkInstructionCardModal = true;
            this.resetBulkUploadData();
        },

        closeBulkInstructionCardModal() {
            this.showBulkInstructionCardModal = false;
            this.resetBulkUploadData();
        },

        resetBulkUploadData() {
            this.bulkUploadData = {
                asinList: "",
                files: { card1: null, card2: null, card3: null },
                uploading: false,
                uploadResults: { success: [], failed: [], skipped: [] },
            };
        },

        // ── Edit Mode ────────────────────────────────────────────
        toggleEditMode() {
            this.editMode = !this.editMode;
            if (this.editMode) {
                this.editedAsin = {
                    ASIN: this.selectedAsin.ASIN,
                    EAN: this.selectedAsin.EAN || "",
                    UPC: this.selectedAsin.UPC || "",
                    instructionlink: this.selectedAsin.instructionlink || "",
                    metakeyword: this.selectedAsin.metakeyword || "",
                    TRANSPARENCY_QR_STATUS:
                        this.selectedAsin.TRANSPARENCY_QR_STATUS || "",
                    QuantityInside: this.selectedAsin.QuantityInside || null,
                    system_title: this.selectedAsin.system_title || "",
                    ParentAsin: this.selectedAsin.ParentAsin || "",
                    CousinASIN: this.selectedAsin.CousinASIN || "",
                    UpgradeASIN: this.selectedAsin.UpgradeASIN || "",
                    GrandASIN: this.selectedAsin.GrandASIN || "",
                    asin_limit: this.selectedAsin.asin_limit || 0,
                    def_length: this.selectedAsin.white_length || "",
                    def_width: this.selectedAsin.white_width || "",
                    def_height: this.selectedAsin.white_height || "",
                    def_weight: this.selectedAsin.white_value || "",
                    def_weight_unit: this.selectedAsin.white_unit || "",
                };
            } else {
                this.editedAsin = {};
            }
        },

        // ── ASIN Details Save ────────────────────────────────────
        async saveAsinDetails() {
            if (!this.editedAsin.ASIN) {
                alert("ASIN is required");
                return;
            }
            this.savingAsinDetails = true;
            try {
                const res = await axios.post(
                    `${API_BASE_URL}/api/asinlist/update-asin-details`,
                    {
                        asin: this.editedAsin.ASIN,
                        ean: this.editedAsin.EAN || null,
                        upc: this.editedAsin.UPC || null,
                        instruction_link:
                            this.editedAsin.instructionlink || null,
                        metakeyword: this.editedAsin.metakeyword || null,
                        transparency_qr_status:
                            this.editedAsin.TRANSPARENCY_QR_STATUS || null,
                        quantity_inside: this.editedAsin.QuantityInside || null,
                        system_title: this.editedAsin.system_title || null,
                        asin_limit: this.editedAsin.asin_limit || 0,
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                        },
                    },
                );
                if (res.data.success) {
                    Object.assign(this.selectedAsin, {
                        EAN: this.editedAsin.EAN,
                        UPC: this.editedAsin.UPC,
                        instructionlink: this.editedAsin.instructionlink,
                        metakeyword: this.editedAsin.metakeyword,
                        TRANSPARENCY_QR_STATUS:
                            this.editedAsin.TRANSPARENCY_QR_STATUS,
                        QuantityInside: this.editedAsin.QuantityInside,
                        system_title: this.editedAsin.system_title,
                        asin_limit: this.editedAsin.asin_limit,
                        display_title:
                            this.editedAsin.system_title ||
                            this.selectedAsin.AStitle,
                    });
                    const idx = this.asinData.findIndex(
                        (i) => i.ASIN === this.editedAsin.ASIN,
                    );
                    if (idx !== -1)
                        Object.assign(this.asinData[idx], {
                            ...this.editedAsin,
                            display_title:
                                this.editedAsin.system_title ||
                                this.asinData[idx].AStitle,
                        });
                    alert("ASIN details updated successfully");
                } else {
                    throw new Error(
                        res.data.message || "Failed to update ASIN details",
                    );
                }
            } catch (e) {
                alert(
                    "Failed to update ASIN details: " +
                        (e.response?.data?.message || e.message),
                );
            } finally {
                this.savingAsinDetails = false;
            }
        },

        // ── Default Dimensions Save ──────────────────────────────
        async saveDefaultDimensions() {
            if (!this.editedAsin.ASIN) {
                alert("ASIN is required");
                return;
            }
            this.savingDefaultDimensions = true;
            try {
                const res = await axios.post(
                    `${API_BASE_URL}/api/asinlist/update-default-dimensions`,
                    {
                        asin: this.editedAsin.ASIN,
                        def_length: this.editedAsin.def_length || null,
                        def_width: this.editedAsin.def_width || null,
                        def_height: this.editedAsin.def_height || null,
                        def_weight: this.editedAsin.def_weight || null,
                        def_weight_unit:
                            this.editedAsin.def_weight_unit || null,
                    },
                    { withCredentials: true },
                );
                if (res.data.success) {
                    Object.assign(this.selectedAsin, {
                        white_length: this.editedAsin.def_length,
                        white_width: this.editedAsin.def_width,
                        white_height: this.editedAsin.def_height,
                        white_value: this.editedAsin.def_weight,
                        white_unit: this.editedAsin.def_weight_unit,
                    });
                    const idx = this.asinData.findIndex(
                        (i) => i.ASIN === this.editedAsin.ASIN,
                    );
                    if (idx !== -1)
                        Object.assign(this.asinData[idx], {
                            white_length: this.editedAsin.def_length,
                            white_width: this.editedAsin.def_width,
                            white_height: this.editedAsin.def_height,
                            white_value: this.editedAsin.def_weight,
                            white_unit: this.editedAsin.def_weight_unit,
                        });
                    alert("Default dimensions updated successfully");
                } else {
                    throw new Error(
                        res.data.message ||
                            "Failed to update default dimensions",
                    );
                }
            } catch (e) {
                alert(
                    "Failed to update default dimensions: " +
                        (e.response?.data?.message || e.message),
                );
            } finally {
                this.savingDefaultDimensions = false;
            }
        },

        // ── Related ASINs Save ───────────────────────────────────
        async saveRelatedAsins() {
            this.savingRelatedAsins = true;
            try {
                const res = await axios.post(
                    `${API_BASE_URL}/api/asinlist/update-related-asins`,
                    {
                        asin: this.editedAsin.ASIN,
                        parent_asin: this.editedAsin.ParentAsin || null,
                        cousin_asin: this.editedAsin.CousinASIN || null,
                        upgrade_asin: this.editedAsin.UpgradeASIN || null,
                        grand_asin: this.editedAsin.GrandASIN || null,
                    },
                    { withCredentials: true },
                );
                if (res.data.success) {
                    Object.assign(this.selectedAsin, {
                        ParentAsin: this.editedAsin.ParentAsin,
                        CousinASIN: this.editedAsin.CousinASIN,
                        UpgradeASIN: this.editedAsin.UpgradeASIN,
                        GrandASIN: this.editedAsin.GrandASIN,
                    });
                    const idx = this.asinData.findIndex(
                        (i) => i.ASIN === this.editedAsin.ASIN,
                    );
                    if (idx !== -1)
                        Object.assign(this.asinData[idx], {
                            ParentAsin: this.editedAsin.ParentAsin,
                            CousinASIN: this.editedAsin.CousinASIN,
                            UpgradeASIN: this.editedAsin.UpgradeASIN,
                            GrandASIN: this.editedAsin.GrandASIN,
                        });
                    alert("Related ASINs updated successfully");
                } else {
                    throw new Error(
                        res.data.message || "Failed to update related ASINs",
                    );
                }
            } catch {
                alert("Failed to update related ASINs");
            } finally {
                this.savingRelatedAsins = false;
            }
        },

        // ── Instruction Card Upload ──────────────────────────────
        async handleInstructionCardUpload(event, cardSlot) {
            const file = event.target.files[0];
            if (!file) return;
            if (!file.type.startsWith("image/")) {
                alert("Please select an image file");
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert("File size must be less than 5MB");
                return;
            }
            this.instructionCardUploading = cardSlot;
            try {
                const fd = new FormData();
                fd.append("instruction_card", file);
                fd.append("asin", this.selectedAsin.ASIN);
                fd.append("card_slot", cardSlot);
                const res = await axios.post(
                    `${API_BASE_URL}/api/asinlist/upload-instruction-card`,
                    fd,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                        withCredentials: true,
                    },
                );
                if (res.data.success) {
                    const uploadKey = `${this.selectedAsin.ASIN}_card${cardSlot}`;
                    this.instructionCardUrls[uploadKey] = res.data.file_url;
                    const col =
                        cardSlot === 1
                            ? "instructioncard"
                            : cardSlot === 2
                              ? "instructioncard2"
                              : "instructioncard3";
                    this.selectedAsin[col] = res.data.filename;
                    const idx = this.asinData.findIndex(
                        (i) => i.ASIN === this.selectedAsin.ASIN,
                    );
                    if (idx !== -1) this.asinData[idx][col] = res.data.filename;
                    this.forceImageRefresh(
                        this.selectedAsin.ASIN,
                        "instruction_card",
                        cardSlot,
                    );
                    this.forceImageRefresh(
                        this.selectedAsin.ASIN,
                        "instruction_card_main",
                    );
                    alert(`Instruction card ${cardSlot} uploaded successfully`);
                } else {
                    throw new Error(
                        res.data.message || "Failed to upload instruction card",
                    );
                }
            } catch (e) {
                alert(
                    "Failed to upload instruction card: " +
                        (e.response?.data?.message || e.message),
                );
            } finally {
                this.instructionCardUploading = false;
                event.target.value = "";
            }
        },

        // ── User Manual Upload ───────────────────────────────────
        async handleUserManualUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (file.type !== "application/pdf") {
                alert("Please select a PDF file");
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                alert("File size must be less than 10MB");
                return;
            }
            this.userManualUploading = true;
            try {
                const fd = new FormData();
                fd.append("user_manual", file);
                fd.append("asin", this.selectedAsin.ASIN);
                const res = await axios.post(
                    `${API_BASE_URL}/api/asinlist/upload-user-manual`,
                    fd,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                        withCredentials: true,
                    },
                );
                if (res.data.success) {
                    this.userManualUrls[this.selectedAsin.ASIN] =
                        res.data.file_url;
                    this.selectedAsin.usermanuallink = res.data.filename;
                    this.selectedAsin.user_manual_url = res.data.file_url;
                    const idx = this.asinData.findIndex(
                        (i) => i.ASIN === this.selectedAsin.ASIN,
                    );
                    if (idx !== -1) {
                        this.asinData[idx].usermanuallink = res.data.filename;
                        this.asinData[idx].user_manual_url = res.data.file_url;
                    }
                    this.$forceUpdate();
                    alert("User manual uploaded successfully");
                } else {
                    throw new Error(
                        res.data.message || "Failed to upload user manual",
                    );
                }
            } catch (e) {
                alert(
                    "Failed to upload user manual: " +
                        (e.response?.data?.message || e.message),
                );
            } finally {
                this.userManualUploading = false;
                event.target.value = "";
            }
        },

        // ── ASIN Image Upload ────────────────────────────────────
        async handleAsinImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (!file.type.startsWith("image/")) {
                alert("Please select an image file");
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert("File size must be less than 5MB");
                return;
            }
            this.asinImageUploading = true;
            try {
                let uploadFile = file;
                try {
                    if (this.isWebPSupported()) {
                        const blob = await this.convertToWebP(file, {
                            quality: 0.85,
                            maxWidth: 1920,
                        });
                        const name = file.name.replace(
                            /\.(png|jpe?g|gif|bmp)$/i,
                            ".webp",
                        );
                        uploadFile = new File([blob], name, {
                            type: "image/webp",
                        });
                    }
                } catch {
                    /* fallback to original */
                }
                const fd = new FormData();
                fd.append("asin_image", uploadFile);
                fd.append("asin", this.selectedAsin.ASIN);
                const res = await axios.post(
                    `${API_BASE_URL}/api/asinlist/upload-asin-image`,
                    fd,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                        withCredentials: true,
                    },
                );
                if (res.data.success) {
                    this.asinImageUrls[this.selectedAsin.ASIN] =
                        res.data.file_url;
                    this.selectedAsin.asinimg = res.data.filename;
                    this.selectedAsin.asin_image_url = res.data.file_url;
                    const idx = this.asinData.findIndex(
                        (i) => i.ASIN === this.selectedAsin.ASIN,
                    );
                    if (idx !== -1) {
                        this.asinData[idx].asinimg = res.data.filename;
                        this.asinData[idx].asin_image_url = res.data.file_url;
                    }
                    this.forceImageRefresh(this.selectedAsin.ASIN, "main");
                    alert("ASIN image uploaded successfully");
                } else {
                    throw new Error(
                        res.data.message || "Failed to upload ASIN image",
                    );
                }
            } catch (e) {
                alert(
                    "Failed to upload ASIN image: " +
                        (e.response?.data?.message || e.message),
                );
            } finally {
                this.asinImageUploading = false;
                event.target.value = "";
            }
        },

        // ── Vector Image Upload ──────────────────────────────────
        async handleVectorImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (!["image/png", "image/jpg", "image/jpeg"].includes(file.type)) {
                alert("Please select a PNG or JPG image file");
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert("File size must be less than 5MB");
                return;
            }
            this.vectorImageUploading = true;
            try {
                const fd = new FormData();
                fd.append("vector_image", file);
                fd.append("asin", this.selectedAsin.ASIN);
                const res = await axios.post(
                    `${API_BASE_URL}/api/asinlist/upload-vector-image`,
                    fd,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                        withCredentials: true,
                    },
                );
                if (res.data.success) {
                    this.vectorImageUrls[this.selectedAsin.ASIN] =
                        res.data.file_url;
                    this.selectedAsin.vectorimage = res.data.filename;
                    this.selectedAsin.vector_image_url = res.data.file_url;
                    const idx = this.asinData.findIndex(
                        (i) => i.ASIN === this.selectedAsin.ASIN,
                    );
                    if (idx !== -1) {
                        this.asinData[idx].vectorimage = res.data.filename;
                        this.asinData[idx].vector_image_url = res.data.file_url;
                    }
                    this.forceImageRefresh(this.selectedAsin.ASIN, "vector");
                    alert("Vector image uploaded successfully");
                } else {
                    throw new Error(
                        res.data.message || "Failed to upload vector image",
                    );
                }
            } catch (e) {
                alert(
                    "Failed to upload vector image: " +
                        (e.response?.data?.message || e.message),
                );
            } finally {
                this.vectorImageUploading = false;
                event.target.value = "";
            }
        },

        // ── Bulk Instruction Card Upload ─────────────────────────
        getFilePreviewUrl(file) {
            return file ? URL.createObjectURL(file) : null;
        },

        removeBulkFile(cardSlot) {
            this.bulkUploadData.files[cardSlot] = null;
            const ref = this.$refs[`bulkFileUploadCard${cardSlot.slice(-1)}`];
            if (ref) ref.value = "";
        },

        formatFileSize(bytes) {
            if (bytes === 0) return "0 Bytes";
            const k = 1024,
                sizes = ["Bytes", "KB", "MB", "GB"];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return (
                parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i]
            );
        },

        handleBulkFileSelect(event, cardSlot) {
            const file = event.target.files[0];
            if (!file) return;
            if (!file.type.startsWith("image/")) {
                alert("Please select an image file");
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert("File size must be less than 5MB");
                return;
            }
            this.bulkUploadData.files[cardSlot] = file;
        },

        validateBulkUpload() {
            if (!this.bulkUploadData.asinList.trim()) {
                alert("Please enter at least one ASIN");
                return false;
            }
            if (
                !this.bulkUploadData.files.card1 &&
                !this.bulkUploadData.files.card2 &&
                !this.bulkUploadData.files.card3
            ) {
                alert("Please select at least one instruction card image");
                return false;
            }
            return true;
        },

        getSelectedCardCount() {
            return [
                this.bulkUploadData.files.card1,
                this.bulkUploadData.files.card2,
                this.bulkUploadData.files.card3,
            ].filter(Boolean).length;
        },

        parseAsinList() {
            return this.bulkUploadData.asinList
                .split(",")
                .map((a) => a.trim().toUpperCase())
                .filter(Boolean)
                .filter((a, i, arr) => arr.indexOf(a) === i);
        },

        async processBulkInstructionCardUpload() {
            if (!this.validateBulkUpload()) return;
            const asinList = this.parseAsinList();
            if (!asinList.length) {
                alert("No valid ASINs found");
                return;
            }
            if (asinList.length > 50) {
                alert("Maximum 50 ASINs allowed per bulk upload");
                return;
            }
            const cnt = this.getSelectedCardCount();
            if (
                !confirm(`Upload ${cnt} card(s) to ${asinList.length} ASIN(s)?`)
            )
                return;
            this.bulkUploadData.uploading = true;
            this.bulkUploadData.uploadResults = {
                success: [],
                failed: [],
                skipped: [],
            };
            try {
                const fd = new FormData();
                fd.append("asin_list", asinList.join(","));
                let fi = 0;
                for (const slot of ["card1", "card2", "card3"]) {
                    if (this.bulkUploadData.files[slot]) {
                        fd.append(
                            `instruction_cards[${fi++}]`,
                            this.bulkUploadData.files[slot],
                        );
                    }
                }
                const res = await axios.post(
                    `${API_BASE_URL}/api/asinlist/bulk-upload-instruction-cards`,
                    fd,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                        withCredentials: true,
                    },
                );
                if (res.data.success) {
                    this.bulkUploadData.uploadResults = res.data.results;
                    res.data.results.success.forEach((r) => {
                        if (
                            this.asinData.findIndex(
                                (i) => i.ASIN === r.asin,
                            ) !== -1
                        )
                            this.fetchAsinData();
                        for (let s = 1; s <= 3; s++) {
                            if (this.bulkUploadData.files[`card${s}`])
                                this.forceImageRefresh(
                                    r.asin,
                                    "instruction_card",
                                    s,
                                );
                        }
                        this.forceImageRefresh(r.asin, "instruction_card_main");
                    });
                    this.showBulkUploadResults();
                } else {
                    throw new Error(res.data.message || "Bulk upload failed");
                }
            } catch (e) {
                alert(
                    "Bulk upload failed: " +
                        (e.response?.data?.message || e.message),
                );
                this.bulkUploadData.uploadResults.failed = [
                    {
                        asin: "ALL",
                        errors: [e.response?.data?.message || e.message],
                    },
                ];
            } finally {
                this.bulkUploadData.uploading = false;
            }
        },

        showBulkUploadResults() {
            const { success, failed, skipped } =
                this.bulkUploadData.uploadResults;
            let msg = `Bulk Upload Complete!\n\n✅ Success: ${success.length}\n❌ Failed: ${failed.length}\n⚠️ Skipped: ${skipped.length}`;
            if (success.length)
                msg +=
                    `\n\nSuccessful:\n` +
                    success.map((i) => `• ${i.asin}: ${i.cards}`).join("\n");
            if (failed.length)
                msg +=
                    `\n\nFailed:\n` +
                    failed
                        .map((i) => `• ${i.asin}: ${i.errors.join(", ")}`)
                        .join("\n");
            if (skipped.length)
                msg +=
                    `\n\nSkipped:\n` + skipped.map((i) => `• ${i}`).join("\n");
            alert(msg);
        },

        clearImageCache() {
            this.imageCacheBuster = {};
            this.$forceUpdate();
        },

        // ── Color / Qty Inside ───────────────────────────────────
        async updateColor(item) {
            const orig = item.color;
            this.savingColorFor = item.ASIN;
            try {
                const res = await axios.post(
                    `${API_BASE_URL}/api/asinlist/update-color`,
                    { asin: item.ASIN, color: item.color || null },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                        },
                    },
                );
                if (!res.data.success)
                    throw new Error(
                        res.data.message || "Failed to update color",
                    );
            } catch (e) {
                item.color = orig;
                this.$forceUpdate();
                alert(
                    "Failed to update Color: " +
                        (e.response?.data?.message || e.message),
                );
            } finally {
                this.savingColorFor = null;
            }
        },

        async updateQuantityInside(item) {
            const orig = item.QuantityInside;
            this.savingQuantityFor = item.ASIN;
            try {
                const res = await axios.post(
                    `${API_BASE_URL}/api/asinlist/update-quantity-inside`,
                    {
                        asin: item.ASIN,
                        quantity_inside: item.QuantityInside || null,
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                        },
                    },
                );
                if (!res.data.success)
                    throw new Error(
                        res.data.message || "Failed to update quantity",
                    );
            } catch (e) {
                item.QuantityInside = orig;
                this.$forceUpdate();
                alert(
                    "Failed to update Quantity Inside: " +
                        (e.response?.data?.message || e.message),
                );
            } finally {
                this.savingQuantityFor = null;
            }
        },

        // ── WebP helpers ─────────────────────────────────────────
        isWebPSupported() {
            const c = document.createElement("canvas");
            if (!c.getContext?.("2d")) return false;
            return (
                c.toBlob &&
                c.toDataURL("image/webp").startsWith("data:image/webp")
            );
        },

        convertToWebP(
            file,
            { quality = 0.85, maxWidth = null, maxHeight = null } = {},
        ) {
            return new Promise((resolve, reject) => {
                if (!file?.type.startsWith("image/")) {
                    reject(new Error("Invalid image file"));
                    return;
                }
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = new Image();
                    img.onload = () => {
                        try {
                            let { width, height } = img;
                            if (!width || !height) {
                                reject(new Error("Invalid image dimensions"));
                                return;
                            }
                            if (maxWidth && width > maxWidth) {
                                height = Math.round(
                                    (height * maxWidth) / width,
                                );
                                width = maxWidth;
                            }
                            if (maxHeight && height > maxHeight) {
                                width = Math.round(
                                    (width * maxHeight) / height,
                                );
                                height = maxHeight;
                            }
                            const canvas = document.createElement("canvas");
                            canvas.width = width;
                            canvas.height = height;
                            const ctx = canvas.getContext("2d");
                            if (!ctx) {
                                reject(
                                    new Error("Failed to get canvas context"),
                                );
                                return;
                            }
                            ctx.imageSmoothingEnabled = true;
                            ctx.imageSmoothingQuality = "high";
                            ctx.drawImage(img, 0, 0, width, height);
                            canvas.toBlob(
                                (blob) =>
                                    blob?.size
                                        ? resolve(blob)
                                        : reject(
                                              new Error(
                                                  "toBlob returned empty",
                                              ),
                                          ),
                                "image/webp",
                                quality,
                            );
                        } catch (err) {
                            reject(new Error(`Canvas error: ${err.message}`));
                        }
                    };
                    img.onerror = () =>
                        reject(new Error("Failed to load image"));
                    img.src = e.target.result;
                };
                reader.onerror = () => reject(new Error("Failed to read file"));
                try {
                    reader.readAsDataURL(file);
                } catch (err) {
                    reject(new Error(`FileReader error: ${err.message}`));
                }
            });
        },

        // ── Per-ASIN Config ──────────────────────────────────────

        /**
         * UPDATED — loads global config first so the inherited banner
         * and preview are ready when the dialog opens.
         */
        openASINConfig(data) {
            this.labelingCollapsed = true;
            this.testingCollapsed = true;
            this.repairCollapsed = true;
            this.cleaningCollapsed = true;
            this.packagingCollapsed = true;
            this.showInheritedPreview = false;

            this.selectedConfig = data;
            this.showASINConfig = true;

            // Load global defaults first (populates hasGlobalConfig / globalInheritedCount)
            this.loadGlobalConfig();

            // Then load ASIN-specific overrides
            this.loadAllFields(data.ASIN);
        },

        /**
         * UPDATED — loads only ASIN-specific fields.
         * Global fields are handled separately via loadGlobalConfig().
         */
        loadAllFields(asin) {
            const parse = (key, fallback) => {
                try {
                    const r = localStorage.getItem(key);
                    return r ? JSON.parse(r) : fallback;
                } catch {
                    return fallback;
                }
            };
            this.labelingFields = parse(`asin_config_labeling:${asin}`, []);
            this.testingFields = parse(`asin_config_testing:${asin}`, []);
            this.repairFields = parse(`asin_config_repair:${asin}`, []);
            this.cleaningFields = parse(`asin_config_cleaning:${asin}`, []);
            const pkg = parse(`asin_config_packaging:${asin}`, {});
            this.packagingImage = pkg.image || null;
            this.packagingComponents = pkg.components || [];
            this.boxSpecs = pkg.boxSpecs || {
                size: "",
                type: "",
                weight: "",
                materials: "",
            };
        },

        saveAllFields(publish = false) {
            publish ? (this.publishing = true) : (this.savingAll = true);
            try {
                const asin = this.selectedConfig.ASIN;
                localStorage.setItem(
                    `asin_config_labeling:${asin}`,
                    JSON.stringify(this.labelingFields),
                );
                localStorage.setItem(
                    `asin_config_testing:${asin}`,
                    JSON.stringify(this.testingFields),
                );
                localStorage.setItem(
                    `asin_config_repair:${asin}`,
                    JSON.stringify(this.repairFields),
                );
                localStorage.setItem(
                    `asin_config_cleaning:${asin}`,
                    JSON.stringify(this.cleaningFields),
                );
                localStorage.setItem(
                    `asin_config_packaging:${asin}`,
                    JSON.stringify({
                        image: this.packagingImage,
                        components: this.packagingComponents,
                        boxSpecs: this.boxSpecs,
                    }),
                );
                if (publish) {
                    Swal.fire({
                        icon: "success",
                        title: "Saved & Published!",
                        text: `Configuration for ${asin} has been saved and published.`,
                        confirmButtonText: "OK",
                    });
                    this.showASINConfig = false;
                } else {
                    Swal.fire({
                        icon: "success",
                        title: "Configuration Saved!",
                        text: `Configuration for ${asin} has been saved successfully.`,
                        confirmButtonText: "OK",
                    });
                }
            } catch {
                Swal.fire({
                    icon: "error",
                    title: "Save Failed",
                    text: "Something went wrong while saving. Please try again.",
                });
            } finally {
                this.savingAll = false;
                this.publishing = false;
            }
        },

        // Field builders
        addLabelingField() {
            this.labelingFields.push({
                label: "",
                type: "",
                defaultValue: "",
                required: false,
                hasOptions: false,
                preTypedNotes: false,
                options: [],
            });
        },
        addTestingField() {
            this.testingFields.push({
                label: "",
                type: "",
                defaultValue: "",
                required: false,
                hasOptions: false,
                preTypedNotes: false,
                options: [],
            });
        },
        addRepairField() {
            this.repairFields.push({ name: "", actions: [] });
        },
        addCleaningField() {
            this.cleaningFields.push({ name: "", actions: [] });
        },

        addOption(field) {
            field.options.push({
                value: "",
                hasNote: false,
                note: "",
                editingNote: false,
            });
        },
        addRepairAction(cat) {
            cat.actions.push({ title: "", description: "", editing: false });
        },
        addCleaningAction(cat) {
            cat.actions.push({ title: "", description: "", editing: false });
        },

        onPackagingImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                this.packagingImage = e.target.result;
            };
            reader.readAsDataURL(file);
        },
        addPackagingComponent() {
            this.packagingComponents.push({
                name: "",
                sku: "",
                qty: 1,
                note: "",
            });
        },

        removeOption(field, oIndex) {
            field.options.splice(oIndex, 1);
        },

        toggleHasNote(field, oIndex) {
            field.options[oIndex].hasNote = !field.options[oIndex].hasNote;
            if (!field.options[oIndex].hasNote) {
                field.options[oIndex].note = "";
                field.options[oIndex].editingNote = false;
            }
        },

        onFieldTypeChange(field) {
            if (field.type === "Dropdown/Select") field.hasOptions = true;
        },

        // ── Global Config ────────────────────────────────────────

        openGlobalConfig() {
            this.loadGlobalConfig();
            this.showGlobalConfig = true;
        },

        loadGlobalConfig() {
            const parse = (key, fallback) => {
                try {
                    const r = localStorage.getItem(key);
                    return r ? JSON.parse(r) : fallback;
                } catch {
                    return fallback;
                }
            };
            this.globalLabelingFields = parse(
                "asin_global_config_labeling",
                [],
            );
            this.globalTestingFields = parse("asin_global_config_testing", []);
            this.globalRepairFields = parse("asin_global_config_repair", []);
            this.globalCleaningFields = parse(
                "asin_global_config_cleaning",
                [],
            );
            const pkg = parse("asin_global_config_packaging", {});
            this.globalPackagingComponents = pkg.components || [];
            this.globalBoxSpecs = pkg.boxSpecs || {
                size: "",
                type: "",
                weight: "",
                materials: "",
            };
        },

        saveGlobalConfig() {
            this.savingGlobalConfig = true;
            try {
                localStorage.setItem(
                    "asin_global_config_labeling",
                    JSON.stringify(this.globalLabelingFields),
                );
                localStorage.setItem(
                    "asin_global_config_testing",
                    JSON.stringify(this.globalTestingFields),
                );
                localStorage.setItem(
                    "asin_global_config_repair",
                    JSON.stringify(this.globalRepairFields),
                );
                localStorage.setItem(
                    "asin_global_config_cleaning",
                    JSON.stringify(this.globalCleaningFields),
                );
                localStorage.setItem(
                    "asin_global_config_packaging",
                    JSON.stringify({
                        components: this.globalPackagingComponents,
                        boxSpecs: this.globalBoxSpecs,
                    }),
                );
                Swal.fire({
                    icon: "success",
                    title: "Global Config Saved!",
                    text: "All ASINs without overrides will now use these defaults.",
                    confirmButtonText: "OK",
                });
                this.showGlobalConfig = false;
            } catch {
                Swal.fire({
                    icon: "error",
                    title: "Save Failed",
                    text: "Something went wrong saving the global config.",
                });
            } finally {
                this.savingGlobalConfig = false;
            }
        },

        /**
         * Returns the merged effective fields for a given section.
         * Global fields come first (flagged _inherited: true),
         * then ASIN-specific fields (_inherited: false).
         * Use this wherever you need the final resolved list (e.g. printing/publishing).
         */
        getEffectiveFields(section) {
            const globalMap = {
                labeling: this.globalLabelingFields,
                testing: this.globalTestingFields,
                repair: this.globalRepairFields,
                cleaning: this.globalCleaningFields,
            };
            const asinMap = {
                labeling: this.labelingFields,
                testing: this.testingFields,
                repair: this.repairFields,
                cleaning: this.cleaningFields,
            };
            return [
                ...(globalMap[section] || []).map((f) => ({
                    ...f,
                    _inherited: true,
                })),
                ...(asinMap[section] || []).map((f) => ({
                    ...f,
                    _inherited: false,
                })),
            ];
        },

        /** Returns merged effective packaging (ASIN-level specs override global where non-empty). */
        getEffectivePackaging() {
            return {
                image: this.packagingImage,
                boxSpecs: {
                    size: this.boxSpecs.size || this.globalBoxSpecs.size,
                    type: this.boxSpecs.type || this.globalBoxSpecs.type,
                    weight: this.boxSpecs.weight || this.globalBoxSpecs.weight,
                    materials:
                        this.boxSpecs.materials ||
                        this.globalBoxSpecs.materials,
                },
                components: [
                    ...(this.globalPackagingComponents || []).map((c) => ({
                        ...c,
                        _inherited: true,
                    })),
                    ...(this.packagingComponents || []).map((c) => ({
                        ...c,
                        _inherited: false,
                    })),
                ],
            };
        },

        // ── Misc ─────────────────────────────────────────────────
        getUniqueStores(fnskus) {
            if (!fnskus?.length) return [];
            return [...new Set(fnskus.map((f) => f.storename).filter(Boolean))];
        },
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.first = 0;
            this.fetchAsinData();
        },
    },
    mounted() {
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token)
            axios.defaults.headers.common["X-CSRF-TOKEN"] =
                token.getAttribute("content");

        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fa = document.createElement("link");
            fa.rel = "stylesheet";
            fa.href =
                "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css";
            document.head.appendChild(fa);
        }

        this.defaultImagePath = this.createDefaultImageSVG();
        this.fetchStores();
        this.fetchAsinData();
        window.addEventListener("resize", this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.handleResize);
    },
};
