import { eventBus } from "../../components/eventbus";
import { DEFAULT_IMAGE } from "../../constant";
import Swal from "sweetalert2";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "SuppliesComponentsModule",
    data() {
        return {
            items: [],
            loading: true,

            // Filters
            selectedCategory: "",

            // Statistics
            stats: { total: 0, components: 0, supplies: 0, office_equipment: 0 },

            // Move to Labeling
            moveLabelingLoading: false,

            // Image modal (same pattern as labeling)
            showImageModal: false,
            modalProductTitle: "",
            regularImages: [],
            capturedImages: [],
            defaultImage: DEFAULT_IMAGE,

            // Pagination
            currentPage: 1,
            totalRecords: 0,
            perPage: 10,
            first: 0,
        };
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery || "";
        },
    },
    methods: {
        async fetchItems() {
            this.loading = true;
            try {
                const response = await axios.get(`${API_BASE_URL}/api/supplies-components`, {
                    params: {
                        search: this.searchQuery,
                        page: this.currentPage,
                        per_page: this.perPage,
                        category: this.selectedCategory,
                    },
                    withCredentials: true,
                });

                if (response.data && response.data.success) {
                    this.items = response.data.data || [];
                    this.totalRecords = response.data.total || 0;
                } else {
                    this.items = [];
                    this.totalRecords = 0;
                }
            } catch (error) {
                console.error("Error fetching supplies/components:", error);
                this.items = [];
                this.totalRecords = 0;
            } finally {
                this.loading = false;
            }
        },

        async fetchStats() {
            try {
                const response = await axios.get(`${API_BASE_URL}/api/supplies-components/stats`, {
                    withCredentials: true,
                });
                if (response.data && response.data.success) {
                    this.stats = response.data.stats;
                }
            } catch (error) {
                console.error("Error fetching stats:", error);
            }
        },

        async refreshData() {
            await Promise.all([this.fetchItems(), this.fetchStats()]);
        },

        changeCategory() {
            this.currentPage = 1;
            this.first = 0;
            this.fetchItems();
        },

        onPageChange(event) {
            this.first = event.first;
            this.currentPage = event.page + 1;
            this.perPage = event.rows;
            this.fetchItems();
        },

        // ─── Image helpers (mirrors labeling.js) ─────────────────────────────

        isValidImage(path) {
            return path && path !== 'NULL' && String(path).trim() !== '';
        },

        hasCapturedImages(item) {
            if (!item?.capturedImages) return false;
            return Object.values(item.capturedImages).some(v => this.isValidImage(v));
        },

        getFirstImage(item) {
            const company = item?.company || 'Airstaffs';
            if (item?.capturedImages) {
                for (let i = 1; i <= 12; i++) {
                    const v = item.capturedImages[`capturedimg${i}`];
                    if (this.isValidImage(v)) return `/images/product_images/${company}/${v}`;
                }
                for (const k of ['serialimg1','serialimg2','trackingimg1','trackingimg2']) {
                    if (this.isValidImage(item.capturedImages[k]))
                        return `/images/product_images/${company}/${item.capturedImages[k]}`;
                }
            }
            for (let i = 1; i <= 5; i++) {
                if (this.isValidImage(item[`img${i}`])) return `/images/thumbnails/${item[`img${i}`]}`;
            }
            return this.defaultImage;
        },

        countAllImages(item) {
            if (!item) return 0;
            if (item?.capturedImages) {
                let c = 0;
                for (let i = 1; i <= 12; i++) if (this.isValidImage(item.capturedImages[`capturedimg${i}`])) c++;
                for (const k of ['serialimg1','serialimg2','trackingimg1','trackingimg2']) if (this.isValidImage(item.capturedImages[k])) c++;
                if (c > 0) return c;
            }
            let c = 0;
            for (let i = 1; i <= 5; i++) if (this.isValidImage(item[`img${i}`])) c++;
            return c;
        },

        // for TableGallery fallback (counts img2+)
        countAdditionalImages(item) {
            if (!item) return 0;
            let c = 0;
            for (let i = 2; i <= 5; i++) if (this.isValidImage(item[`img${i}`])) c++;
            return c;
        },

        handleImageError(event) {
            event.target.src = this.defaultImage;
            event.target.onerror = null;
        },

        // ─── Image modal (same as labeling) ──────────────────────────────────

        openImageModal(item) {
            if (!item) return;
            this.regularImages  = [];
            this.capturedImages = [];
            this.modalProductTitle = item.product_title || '';
            const company = item?.company || 'Airstaffs';

            // Regular images (img1–img5)
            for (let i = 1; i <= 5; i++) {
                if (this.isValidImage(item[`img${i}`]))
                    this.regularImages.push(`/images/thumbnails/${item[`img${i}`]}`);
            }

            // Captured images (same as labeling)
            if (item.capturedImages && typeof item.capturedImages === 'object') {
                const ci = item.capturedImages;
                for (let i = 1; i <= 12; i++) {
                    if (this.isValidImage(ci[`capturedimg${i}`]))
                        this.capturedImages.push(`/images/product_images/${company}/${ci[`capturedimg${i}`]}`);
                }
                for (const k of ['serialimg1','serialimg2','trackingimg1','trackingimg2']) {
                    if (this.isValidImage(ci[k]))
                        this.capturedImages.push(`/images/product_images/${company}/${ci[k]}`);
                }
            }

            if (this.regularImages.length === 0 && this.capturedImages.length === 0)
                this.regularImages.push(this.defaultImage);

            this.showImageModal = true;
            document.body.style.overflow = 'hidden';
        },

        closeImageModal() {
            this.showImageModal  = false;
            this.regularImages   = [];
            this.capturedImages  = [];
            this.modalProductTitle = '';
            document.body.style.overflow = 'auto';
        },

        // ─── Move to Labeling ─────────────────────────────────────────────────

        async moveToLabeling(item) {
            if (this.moveLabelingLoading) return;

            const productId = item?.product_id ?? null;
            if (!productId) {
                await Swal.fire({ icon: 'error', title: 'Error', text: 'Missing product_id.' });
                return;
            }

            const confirm = await Swal.fire({
                icon: 'question',
                title: 'Move to Labeling?',
                html: `<b>RT# ${item?.rt_counter || 'N/A'}</b><br>${item?.product_title || 'N/A'}`,
                showCancelButton: true,
                confirmButtonText: 'Yes, Move it',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#f59e0b',
            });

            if (!confirm.isConfirmed) return;

            this.moveLabelingLoading = true;
            try {
                const resp = await axios.post(
                    `${API_BASE_URL}/api/supplies-components/move-to-labeling`,
                    { product_id: productId },
                    { withCredentials: true }
                );

                if (resp?.data?.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Moved!',
                        text: 'Item successfully moved to Labeling.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                    await Promise.all([this.fetchItems(), this.fetchStats()]);
                } else {
                    await Swal.fire({ icon: 'error', title: 'Failed', text: resp?.data?.message || 'Move to Labeling failed.' });
                }
            } catch (e) {
                console.error("moveToLabeling error:", e);
                await Swal.fire({ icon: 'error', title: 'Error', text: e?.response?.data?.message || e?.message || 'Move to Labeling error.' });
            } finally {
                this.moveLabelingLoading = false;
            }
        },

        // ─── Utilities ────────────────────────────────────────────────────────

        formatDate(dateStr) {
            if (!dateStr || dateStr === "N/A") return "N/A";
            try {
                const d = new Date(dateStr);
                return d.toLocaleDateString() + " " + d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
            } catch (e) { return dateStr; }
        },
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.first = 0;
            this.fetchItems();
        },
    },
    mounted() {
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) axios.defaults.headers.common["X-CSRF-TOKEN"] = token.getAttribute("content");
        this.fetchStats();
        this.fetchItems();
    },
};