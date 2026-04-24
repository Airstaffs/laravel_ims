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
            stats: {
                total: 0,
                components: 0,
                supplies: 0,
                office_equipment: 0,
            },

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

            showSidListModal: false,
            sidListLoading: false,
            sidListItems: [],

            // Add SID modal
            showAddSidModal: false,
            addSidLoading: false,
            sidForm: {
                sid_number: "",
                alias: "",
                price: null,
                quantity: 0,
                threshold: 0,
            },

            // View SID modal
            showViewSidModal: false,
            viewSidItem: null,

            // Image upload
            sidImageFile: null,
            uploadSidImageLoading: false,
            deleteSidImageLoading: false,

            // Edit SID modal
            showEditSidModal: false,
            editSidLoading: false,
            editSidForm: {
                id: null,
                sid_number: "",
                alias: "",
                price: null,
                quantity: 0,
                threshold: 0,
            },
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
                const response = await axios.get(
                    `${API_BASE_URL}/api/supplies-components`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            category: this.selectedCategory,
                        },
                        withCredentials: true,
                    },
                );

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
                const response = await axios.get(
                    `${API_BASE_URL}/api/supplies-components/stats`,
                    {
                        withCredentials: true,
                    },
                );
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
            return path && path !== "NULL" && String(path).trim() !== "";
        },

        hasCapturedImages(item) {
            if (!item?.capturedImages) return false;
            return Object.values(item.capturedImages).some((v) =>
                this.isValidImage(v),
            );
        },

        getFirstImage(item) {
            const company = item?.company || "Airstaffs";
            if (item?.capturedImages) {
                for (let i = 1; i <= 12; i++) {
                    const v = item.capturedImages[`capturedimg${i}`];
                    if (this.isValidImage(v))
                        return `/images/product_images/${company}/${v}`;
                }
                for (const k of [
                    "serialimg1",
                    "serialimg2",
                    "trackingimg1",
                    "trackingimg2",
                ]) {
                    if (this.isValidImage(item.capturedImages[k]))
                        return `/images/product_images/${company}/${item.capturedImages[k]}`;
                }
            }
            for (let i = 1; i <= 5; i++) {
                if (this.isValidImage(item[`img${i}`]))
                    return `/images/thumbnails/${item[`img${i}`]}`;
            }
            return this.defaultImage;
        },

        countAllImages(item) {
            if (!item) return 0;
            if (item?.capturedImages) {
                let c = 0;
                for (let i = 1; i <= 12; i++)
                    if (
                        this.isValidImage(
                            item.capturedImages[`capturedimg${i}`],
                        )
                    )
                        c++;
                for (const k of [
                    "serialimg1",
                    "serialimg2",
                    "trackingimg1",
                    "trackingimg2",
                ])
                    if (this.isValidImage(item.capturedImages[k])) c++;
                if (c > 0) return c;
            }
            let c = 0;
            for (let i = 1; i <= 5; i++)
                if (this.isValidImage(item[`img${i}`])) c++;
            return c;
        },

        // for TableGallery fallback (counts img2+)
        countAdditionalImages(item) {
            if (!item) return 0;
            let c = 0;
            for (let i = 2; i <= 5; i++)
                if (this.isValidImage(item[`img${i}`])) c++;
            return c;
        },

        handleImageError(event) {
            event.target.src = this.defaultImage;
            event.target.onerror = null;
        },

        // ─── Image modal (same as labeling) ──────────────────────────────────

        openImageModal(item) {
            if (!item) return;
            this.regularImages = [];
            this.capturedImages = [];
            this.modalProductTitle = item.product_title || "";
            const company = item?.company || "Airstaffs";

            // Regular images (img1–img5)
            for (let i = 1; i <= 5; i++) {
                if (this.isValidImage(item[`img${i}`]))
                    this.regularImages.push(
                        `/images/thumbnails/${item[`img${i}`]}`,
                    );
            }

            // Captured images (same as labeling)
            if (
                item.capturedImages &&
                typeof item.capturedImages === "object"
            ) {
                const ci = item.capturedImages;
                for (let i = 1; i <= 12; i++) {
                    if (this.isValidImage(ci[`capturedimg${i}`]))
                        this.capturedImages.push(
                            `/images/product_images/${company}/${ci[`capturedimg${i}`]}`,
                        );
                }
                for (const k of [
                    "serialimg1",
                    "serialimg2",
                    "trackingimg1",
                    "trackingimg2",
                ]) {
                    if (this.isValidImage(ci[k]))
                        this.capturedImages.push(
                            `/images/product_images/${company}/${ci[k]}`,
                        );
                }
            }

            if (
                this.regularImages.length === 0 &&
                this.capturedImages.length === 0
            )
                this.regularImages.push(this.defaultImage);

            this.showImageModal = true;
            document.body.style.overflow = "hidden";
        },

        closeImageModal() {
            this.showImageModal = false;
            this.regularImages = [];
            this.capturedImages = [];
            this.modalProductTitle = "";
            document.body.style.overflow = "auto";
        },

        // ─── Move to Labeling ─────────────────────────────────────────────────

        async moveToLabeling(item) {
            if (this.moveLabelingLoading) return;

            const productId = item?.product_id ?? null;
            if (!productId) {
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Missing product_id.",
                });
                return;
            }

            const confirm = await Swal.fire({
                icon: "question",
                title: "Move to Labeling?",
                html: `<b>RT# ${item?.rt_counter || "N/A"}</b><br>${item?.product_title || "N/A"}`,
                showCancelButton: true,
                confirmButtonText: "Yes, Move it",
                cancelButtonText: "Cancel",
                confirmButtonColor: "#f59e0b",
            });

            if (!confirm.isConfirmed) return;

            this.moveLabelingLoading = true;
            try {
                const resp = await axios.post(
                    `${API_BASE_URL}/api/supplies-components/move-to-labeling`,
                    { product_id: productId },
                    { withCredentials: true },
                );

                if (resp?.data?.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Moved!",
                        text: "Item successfully moved to Labeling.",
                        timer: 2000,
                        showConfirmButton: false,
                    });
                    await Promise.all([this.fetchItems(), this.fetchStats()]);
                } else {
                    await Swal.fire({
                        icon: "error",
                        title: "Failed",
                        text: resp?.data?.message || "Move to Labeling failed.",
                    });
                }
            } catch (e) {
                console.error("moveToLabeling error:", e);
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        e?.response?.data?.message ||
                        e?.message ||
                        "Move to Labeling error.",
                });
            } finally {
                this.moveLabelingLoading = false;
            }
        },

        // ─── Utilities ────────────────────────────────────────────────────────

        formatDate(dateStr) {
            if (!dateStr || dateStr === "N/A") return "N/A";
            try {
                const d = new Date(dateStr);
                return (
                    d.toLocaleDateString() +
                    " " +
                    d.toLocaleTimeString([], {
                        hour: "2-digit",
                        minute: "2-digit",
                    })
                );
            } catch (e) {
                return dateStr;
            }
        },

        // ─── SID List ─────────────────────────────────────────────────────────────

        async openSidListModal() {
            this.showSidListModal = true;
            await this.fetchSidList();
        },

        async fetchSidList() {
            this.sidListLoading = true;
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/supplies-components/sid-list`,
                    {
                        withCredentials: true,
                    },
                );
                if (response.data && response.data.success) {
                    this.sidListItems = response.data.data || [];
                } else {
                    this.sidListItems = [];
                }
            } catch (error) {
                console.error("Error fetching SID list:", error);
                this.sidListItems = [];
            } finally {
                this.sidListLoading = false;
            }
        },

        async deleteSidEntry(sidItem) {
            const confirm = await Swal.fire({
                icon: "warning",
                title: "Delete SID Entry?",
                html: `SID: <b>${sidItem.sid_number || "N/A"}</b><br>Alias: ${sidItem.alias || "N/A"}`,
                showCancelButton: true,
                confirmButtonText: "Yes, Delete",
                cancelButtonText: "Cancel",
                confirmButtonColor: "#ef4444",
            });

            if (!confirm.isConfirmed) return;

            try {
                const resp = await axios.delete(
                    `${API_BASE_URL}/api/supplies-components/sid-list/${sidItem.id}`,
                    { withCredentials: true },
                );
                if (resp?.data?.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Deleted!",
                        timer: 1800,
                        showConfirmButton: false,
                    });
                    await this.fetchSidList(); // refresh the modal table
                } else {
                    await Swal.fire({
                        icon: "error",
                        title: "Failed",
                        text: resp?.data?.message || "Delete failed.",
                    });
                }
            } catch (e) {
                console.error("deleteSidEntry error:", e);
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        e?.response?.data?.message ||
                        e?.message ||
                        "Delete error.",
                });
            }
        },

        openAddSidModal() {
            this.sidForm = {
                sid_number: "",
                alias: "",
                price: null,
                quantity: 0,
                threshold: 0,
            };
            this.showAddSidModal = true;
        },

        closeAddSidModal() {
            this.showAddSidModal = false;
        },

        async submitAddSid() {
            if (!this.sidForm.sid_number?.trim()) {
                await Swal.fire({
                    icon: "warning",
                    title: "Required",
                    text: "SID Number is required.",
                });
                return;
            }

            this.addSidLoading = true;
            try {
                const resp = await axios.post(
                    `${API_BASE_URL}/api/supplies-components/sid-list`,
                    this.sidForm,
                    { withCredentials: true },
                );

                if (resp?.data?.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Added!",
                        timer: 1800,
                        showConfirmButton: false,
                    });
                    this.closeAddSidModal();
                    await this.fetchSidList();
                } else {
                    await Swal.fire({
                        icon: "error",
                        title: "Failed",
                        text: resp?.data?.message || "Failed to add SID entry.",
                    });
                }
            } catch (e) {
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        e?.response?.data?.message ||
                        e?.message ||
                        "Error adding SID entry.",
                });
            } finally {
                this.addSidLoading = false;
            }
        },

        // ─── View SID ─────────────────────────────────────────────────────────────

        openViewSidModal(item) {
            this.viewSidItem = { ...item };
            this.sidImageFile = null;
            this.showViewSidModal = true;
        },

        closeViewSidModal() {
            this.showViewSidModal = false;
            this.viewSidItem = null;
            this.sidImageFile = null;
        },

        handleSidImageError(event) {
            event.target.src = this.defaultImage;
            event.target.onerror = null;
        },

        onSidImageSelected(event) {
            this.sidImageFile = event.target.files[0] || null;
        },

        async uploadSidImage(item) {
            if (!this.sidImageFile || !item?.id) return;

            const formData = new FormData();
            formData.append("image", this.sidImageFile);
            formData.append("sid_id", item.id);

            this.uploadSidImageLoading = true;
            try {
                const resp = await axios.post(
                    `${API_BASE_URL}/api/supplies-components/sid-list/${item.id}/upload-image`,
                    formData,
                    {
                        withCredentials: true,
                        headers: { "Content-Type": "multipart/form-data" },
                    },
                );

                if (resp?.data?.success) {
                    this.viewSidItem.image_path = resp.data.image_path;
                    this.sidImageFile = null;
                    if (this.$refs.sidImageInput)
                        this.$refs.sidImageInput.value = "";
                    await Swal.fire({
                        icon: "success",
                        title: "Uploaded!",
                        timer: 1800,
                        showConfirmButton: false,
                    });
                    await this.fetchSidList();
                } else {
                    await Swal.fire({
                        icon: "error",
                        title: "Failed",
                        text: resp?.data?.message || "Upload failed.",
                    });
                }
            } catch (e) {
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        e?.response?.data?.message ||
                        e?.message ||
                        "Upload error.",
                });
            } finally {
                this.uploadSidImageLoading = false;
            }
        },

        async deleteSidImage(item) {
            if (!item?.id) return;

            const confirm = await Swal.fire({
                icon: "warning",
                title: "Delete Image?",
                text: "This will permanently remove the image.",
                showCancelButton: true,
                confirmButtonText: "Yes, Delete",
                confirmButtonColor: "#ef4444",
            });

            if (!confirm.isConfirmed) return;

            this.deleteSidImageLoading = true;
            try {
                const resp = await axios.delete(
                    `${API_BASE_URL}/api/supplies-components/sid-list/${item.id}/delete-image`,
                    { withCredentials: true },
                );

                if (resp?.data?.success) {
                    this.viewSidItem.image_path = null;
                    await Swal.fire({
                        icon: "success",
                        title: "Deleted!",
                        timer: 1800,
                        showConfirmButton: false,
                    });
                    await this.fetchSidList();
                } else {
                    await Swal.fire({
                        icon: "error",
                        title: "Failed",
                        text: resp?.data?.message || "Delete failed.",
                    });
                }
            } catch (e) {
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        e?.response?.data?.message ||
                        e?.message ||
                        "Delete image error.",
                });
            } finally {
                this.deleteSidImageLoading = false;
            }
        },

        // ─── Edit SID ─────────────────────────────────────────────────────────────

        openEditSidModal(item) {
            this.editSidForm = {
                id: item.id,
                sid_number: item.sid_number,
                alias: item.alias ?? "",
                price: item.price !== null ? Number(item.price) : null,
                quantity: item.quantity ?? 0,
                threshold: item.threshold ?? 0,
            };
            this.showEditSidModal = true;
        },

        closeEditSidModal() {
            this.showEditSidModal = false;
            this.editSidForm = {
                id: null,
                sid_number: "",
                alias: "",
                price: null,
                quantity: 0,
                threshold: 0,
            };
        },

        async submitEditSid() {
            if (!this.editSidForm.sid_number?.trim()) {
                await Swal.fire({
                    icon: "warning",
                    title: "Required",
                    text: "SID Number is required.",
                });
                return;
            }

            this.editSidLoading = true;
            try {
                const resp = await axios.put(
                    `${API_BASE_URL}/api/supplies-components/sid-list/${this.editSidForm.id}`,
                    this.editSidForm,
                    { withCredentials: true },
                );

                if (resp?.data?.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Updated!",
                        timer: 1800,
                        showConfirmButton: false,
                    });
                    this.closeEditSidModal();
                    await this.fetchSidList();
                } else {
                    await Swal.fire({
                        icon: "error",
                        title: "Failed",
                        text: resp?.data?.message || "Update failed.",
                    });
                }
            } catch (e) {
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        e?.response?.data?.message ||
                        e?.message ||
                        "Error updating SID entry.",
                });
            } finally {
                this.editSidLoading = false;
            }
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
        if (token)
            axios.defaults.headers.common["X-CSRF-TOKEN"] =
                token.getAttribute("content");
        this.fetchStats();
        this.fetchItems();
    },
};
