import { eventBus } from "../../components/eventbus";
import { DEFAULT_IMAGE } from "../../constant";

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "SwitcheruModule",
    data() {
        return {
            switcherus: [],
            loading: true,
            sortColumn: "",
            sortOrder: "asc",

            // Image modal
            showImageModal: false,
            regularImages: [],
            capturedImages: [],
            activeTab: "regular",
            currentImageSet: [],
            currentImageIndex: 0,
            ProductTitle: "",
            defaultImagePath: DEFAULT_IMAGE,

            // Details modal
            viewDetailsModal: false,
            selectedItem: {},

            // Comparison modal
            showCompareModal: false,
            compareItem: {},

            // Pagination
            currentPage: 1,
            totalRecords: 0,
            perPage: 10,
            first: 0,
        };
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery;
        },
        sortedSwitcherus() {
            if (!this.sortColumn) return this.switcherus;
            return [...this.switcherus].sort((a, b) => {
                const vA = a[this.sortColumn],
                    vB = b[this.sortColumn];
                if (typeof vA === "number" && typeof vB === "number")
                    return this.sortOrder === "asc" ? vA - vB : vB - vA;
                return this.sortOrder === "asc"
                    ? String(vA || "").localeCompare(String(vB || ""))
                    : String(vB || "").localeCompare(String(vA || ""));
            });
        },
        isMobile() {
            return window.innerWidth <= 768;
        },
    },
    methods: {
        // ── Data fetching ──
        async fetchSwitcherus() {
            this.loading = true;
            try {
                const r = await axios.get(`${API_BASE_URL}/api/switcherus/`, {
                    params: {
                        search: this.searchQuery,
                        page: this.currentPage,
                        per_page: this.perPage,
                    },
                    withCredentials: true,
                });
                this.switcherus = r.data.data || [];
                this.totalRecords = r.data.total || 0;
            } catch (e) {
                console.error("Error fetching switcherus:", e);
                this.switcherus = [];
                this.totalRecords = 0;
            } finally {
                this.loading = false;
            }
        },

        onPageChange(event) {
            this.first = event.first;
            this.currentPage = event.page + 1;
            this.perPage = event.rows;
            this.fetchSwitcherus();
        },

        // ── Formatting helpers ──
        formatDate(ds) {
            if (!ds) return "N/A";
            try {
                const d = new Date(ds);
                return d.toLocaleDateString() + " " + d.toLocaleTimeString();
            } catch {
                return "Invalid Date";
            }
        },
        formatRTNumber(rt) {
            if (!rt) return "N/A";
            return `RT ${String(rt).padStart(5, "0")}`;
        },
        truncateSerial(s, max = 16) {
            if (!s) return "—";
            return s.length > max ? s.substring(0, max) + "…" : s;
        },

        // ── Image helpers ──
        handleImageError(e) {
            e.target.src = this.defaultImagePath;
            e.target.onerror = null;
        },
        isValidImage(path) {
            return path && path !== "NULL" && path.trim() !== "";
        },
        getSerialImageUrl(item, serialType) {
            // serialType: 'sent' or 'received'
            const images = serialType === "sent" ? item.sentImages : item.receivedImages;
            const company = item.company || "Airstaffs";

            if (images && images.capturedimg1) {
                return `/images/product_images/${company}/${images.capturedimg1}`;
            }
            // Fallback to product img1
            const prod = serialType === "sent" ? item.sentProduct : item.receivedProduct;
            if (prod && prod.img1 && this.isValidImage(prod.img1)) {
                return `/images/thumbnails/${prod.img1}`;
            }
            return this.defaultImagePath;
        },
        countSerialImages(images) {
            if (!images) return 0;
            let c = 0;
            for (let i = 1; i <= 12; i++) {
                if (this.isValidImage(images[`capturedimg${i}`])) c++;
            }
            return c;
        },

        // ── Open image gallery for a specific serial ──
        openSerialImageModal(item, serialType) {
            this.regularImages = [];
            this.capturedImages = [];
            this.currentImageIndex = 0;

            const images = serialType === "sent" ? item.sentImages : item.receivedImages;
            const company = item.company || "Airstaffs";
            const serial = serialType === "sent" ? item.sendserial : item.receiveserial;
            this.ProductTitle = `${serialType === "sent" ? "Sent" : "Received"} Serial: ${serial}`;

            if (images && typeof images === "object") {
                for (let i = 1; i <= 12; i++) {
                    if (this.isValidImage(images[`capturedimg${i}`])) {
                        this.capturedImages.push(
                            `/images/product_images/${company}/${images[`capturedimg${i}`]}`
                        );
                    }
                }
                if (this.isValidImage(images.serialimg1)) {
                    this.capturedImages.push(`/images/product_images/${company}/${images.serialimg1}`);
                }
                if (this.isValidImage(images.serialimg2)) {
                    this.capturedImages.push(`/images/product_images/${company}/${images.serialimg2}`);
                }
            }

            // Fallback to product thumbnail
            const prod = serialType === "sent" ? item.sentProduct : item.receivedProduct;
            if (prod && prod.img1 && this.isValidImage(prod.img1)) {
                this.regularImages.push(`/images/thumbnails/${prod.img1}`);
            }

            if (!this.regularImages.length && !this.capturedImages.length) {
                this.regularImages.push(this.defaultImagePath);
            }

            this.activeTab = this.capturedImages.length ? "captured" : "regular";
            this.currentImageSet = this.activeTab === "regular" ? this.regularImages : this.capturedImages;
            this.showImageModal = true;
            document.body.style.overflow = "hidden";
        },
        closeImageModal() {
            this.showImageModal = false;
            this.currentImageSet = [];
            this.regularImages = [];
            this.capturedImages = [];
            document.body.style.overflow = "auto";
        },
        switchTab(tab) {
            this.activeTab = tab;
            this.currentImageIndex = 0;
            this.currentImageSet = tab === "regular" ? this.regularImages : this.capturedImages;
        },
        nextImage() {
            this.currentImageIndex =
                this.currentImageIndex < this.currentImageSet.length - 1 ? this.currentImageIndex + 1 : 0;
        },
        prevImage() {
            this.currentImageIndex =
                this.currentImageIndex > 0 ? this.currentImageIndex - 1 : this.currentImageSet.length - 1;
        },

        // ── Details modal ──
        handleShowDetails(item) {
            this.selectedItem = item;
            this.viewDetailsModal = true;
        },

        // ── Comparison modal (side-by-side sent vs received) ──
        openCompareModal(item) {
            this.compareItem = item;
            this.showCompareModal = true;
        },

        // ── Collect all images for a serial side in compare view ──
        getCompareImages(item, serialType) {
            const images = serialType === "sent" ? item.sentImages : item.receivedImages;
            const company = item.company || "Airstaffs";
            const result = [];

            if (images && typeof images === "object") {
                for (let i = 1; i <= 12; i++) {
                    if (this.isValidImage(images[`capturedimg${i}`])) {
                        result.push(`/images/product_images/${company}/${images[`capturedimg${i}`]}`);
                    }
                }
            }
            if (!result.length) {
                const prod = serialType === "sent" ? item.sentProduct : item.receivedProduct;
                if (prod && prod.img1 && this.isValidImage(prod.img1)) {
                    result.push(`/images/thumbnails/${prod.img1}`);
                }
            }
            if (!result.length) result.push(this.defaultImagePath);
            return result;
        },
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.first = 0;
            this.fetchSwitcherus();
        },
    },
    mounted() {
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) axios.defaults.headers.common["X-CSRF-TOKEN"] = token.getAttribute("content");

        this.fetchSwitcherus();

        const handleKeyDown = (e) => {
            if (!this.showImageModal && !this.showCompareModal) return;
            if (e.key === "Escape") {
                this.closeImageModal();
                this.showCompareModal = false;
            }
            if (e.key === "ArrowRight") this.nextImage();
            if (e.key === "ArrowLeft") this.prevImage();
        };
        window.addEventListener("keydown", handleKeyDown);
        this._handleKeyDown = handleKeyDown;
    },
    beforeUnmount() {
        if (this._handleKeyDown) window.removeEventListener("keydown", this._handleKeyDown);
    },
};