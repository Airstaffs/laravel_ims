<template>
    <div class="vue-container reconciliation-module">
        <!-- Header -->
        <div
            class="d-flex align-items-center justify-content-between flex-wrap mb-4"
        >
            <TitlePage
                title="Reconciliation Module"
                subtitle="Review and verify box quantities before returning items to stock."
            />
        </div>

        <!-- Desktop Table -->
        <AnimateDiv :delay="200" class="px-4">
            <XDataTable
                :value="inventory"
                :loading="loading"
                :columns="columns"
                :paginator="false"
                dataKey="id"
                tableClass="desktop-view"
            >
                <!-- Gallery Column -->
                <template #gallery="{ data }">
                    <div class="d-flex justify-content-center align-items-center">
                        <TableGallery
                            :data="data"
                            :openImageModal="openImageModal"
                            :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages"
                        />
                    </div>
                </template>

                <!-- Product Title Column -->
                <template #ProductTitle="{ data }">
                    <div>
                        <p style="font-size: 0.8rem">
                            RT# {{ data.rtcounter }}
                        </p>
                        <p class="fw-semibold">
                            {{ data.ProductTitle }}
                        </p>
                    </div>
                </template>

                <!-- Date Column -->
                <template #datedelivered="{ data }">
                    {{ formatDate(data.datedelivered) }}
                </template>

                <!-- Actions -->
                <template #actions="{ data }">
                    <Button
                        size="small"
                        severity="contrast"
                        variant="text"
                        label="View Details"
                        icon="pi pi-eye"
                        @click="openEditModal(data)"
                    />
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Pagination -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="pagination">
                    <Button
                        @click="prevPage"
                        :disabled="currentPage === 1"
                        label="Back"
                        icon="pi pi-angle-left"
                        size="small"
                        severity="info"
                    />

                    <span class="pagination-info">
                        Page {{ currentPage }} of {{ totalPages }}
                    </span>

                    <Button
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        label="Next"
                        icon="pi pi-angle-right"
                        size="small"
                        severity="info"
                        iconPos="right"
                    />
                </div>
            </div>
        </div>

        <!-- View Modal -->
        <ViewImageModal
            v-model:visible="showImageModal"
            :title="selectedItem?.ProductTitle"
            :imageList="imageList"
            :basePath="basePath"
            :onImageErrorMain="handleImageError"
            @close="closeImageModal"
        />

        <ScrollTop />
    </div>
</template>

<script>
import { Button, ScrollTop } from "primevue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import ViewImageModal from "../../components/ViewImageModal/ViewImageModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import Reconciliation from "./reconciliation.js";

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ReconciliationModule",

    components: {
        Button,
        ScrollTop,
        TitlePage,
        XDataTable,
        TableGallery,
        ViewImageModal,
        AnimateDiv,
    },

    data() {
        return {
            inventory: [],
            loading: true,
            currentPage: 1,
            totalPages: 1,

            showImageModal: false,
            selectedItem: null,
            basePath: "/images/thumbnails/",

            columns: [
                {
                    field: "gallery",
                    header: "Gallery",
                    slot: "gallery",
                },
                {
                    field: "ProductTitle",
                    header: "Product Name",
                    slot: "ProductTitle",
                },
                {
                    field: "quantity",
                    header: "Quantity",
                },
                {
                    field: "trackingnumber",
                    header: "Tracking Number",
                },
                {
                    field: "datedelivered",
                    header: "Date Delivered",
                    slot: "datedelivered",
                }
            ],
        };
    },

    computed: {
        imageList() {
            if (!this.selectedItem) return [];

            return Object.keys(this.selectedItem)
                .filter(
                    (key) =>
                        key.startsWith("img") &&
                        this.selectedItem[key] &&
                        this.selectedItem[key] !== "NULL"
                )
                .map((key) => this.selectedItem[key]);
        },
    },

    methods: {
        async fetchInventory() {
            this.loading = true;

            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/reconciliation/products`,
                    {
                        params: {
                            page: this.currentPage,
                        },
                    }
                );

                this.inventory = response.data.data;
                this.totalPages = response.data.last_page;
            } catch (error) {
                console.error("Error loading reconciliation data:", error);
            } finally {
                this.loading = false;
            }
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

        openEditModal(item) {
            this.selectedItem = item;
            this.showImageModal = true;
        },

        closeImageModal() {
            this.showImageModal = false;
            this.selectedItem = null;
        },

        handleImageError(event) {
            event.target.src = "/images/default.png";
        },

        countAdditionalImages(item) {
            if (!item) return 0;

            let count = 0;
            for (let i = 2; i <= 15; i++) {
                const key = `img${i}`;
                if (item[key] && item[key] !== "NULL") {
                    count++;
                }
            }
            return count;
        },

        formatDate(date) {
            if (!date) return "";
            return new Date(date).toLocaleDateString();
        },
    },

    mounted() {
        this.fetchInventory();
    },
};
</script>

<style scoped>
.reconciliation-module {
    min-height: 100vh;
}
.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}
.pagination-info {
    margin: 0 10px;
    font-weight: 500;
}
</style>
