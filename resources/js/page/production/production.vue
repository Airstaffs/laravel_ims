<template>
    <div class="vue-container production-module">
        <TitlePage
            title="Production Module"
            subtitle="Track and manage products through the internal assembly, manufacturing, or customization process before final staging."
        />
        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="p-4">
            <XDataTable
                :value="sortedInventory"
                :loading="loading"
                :columns="columns"
                :paginator="false"
                tableClass="desktop-view"
                selectionMode="multiple"
                dataKey="ProductID"
            >
                <template #gallery="{ data }">
                    <div
                        class="d-flex justify-content-center align-items-center"
                    >
                        <!-- Use custom image display for captured images -->
                        <div
                            v-if="
                                data.capturedImages &&
                                data.capturedImages.capturedimg1
                            "
                            class="gallery-thumbnail position-relative"
                            @click="openImageModal(data)"
                            style="cursor: pointer"
                        >
                            <img
                                :src="`/images/product_images/${
                                    data.company || 'Airstaffs'
                                }/${data.capturedImages.capturedimg1}`"
                                :alt="getDisplayTitle(data)"
                                style="
                                    width: 50px;
                                    height: 50px;
                                    object-fit: cover;
                                    border-radius: 4px;
                                "
                                @error="handleImageError"
                            />
                            <span
                                v-if="countCapturedImages(data) > 1"
                                class="position-absolute bg-primary text-white rounded-circle"
                                style="
                                    top: -5px;
                                    right: -5px;
                                    min-width: 20px;
                                    height: 20px;
                                    font-size: 0.65rem;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    padding: 0 4px;
                                "
                            >
                                +{{ countCapturedImages(data) - 1 }}
                            </span>
                        </div>

                        <!-- Use TableGallery for regular product images -->
                        <TableGallery
                            v-else
                            :data="data"
                            :openImageModal="openImageModal"
                            :handleImageError="handleImageError"
                            :countAdditionalImages="countAllImages"
                        />
                    </div>
                </template>

                <template #ProductTitle="{ data }">
                    <div class="d-flex align-items-start gap-4">
                        <div
                            style="
                                word-break: break-word;
                                white-space: normal;
                                overflow-wrap: break-word;
                                flex: 1;
                            "
                        >
                            <p style="font-size: 0.8rem">
                                ID# {{ data.rtcounter }}
                            </p>
                            <p class="fw-semibold">
                                {{ getDisplayTitle(data) }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #actions="{ data }">
                    <Button
                        size="small"
                        severity="contrast"
                        variant="text"
                        icon="pi pi-info-circle"
                        label="Details"
                        class="text-primary"
                        @click="openEditModal(data)"
                    />
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <div class="mobile-cards">
                <div v-if="loading" class="loading-spinner-mobile">
                    <i class="fas fa-spinner fa-spin"></i>
                    Loading...
                </div>
                <div
                    v-else-if="sortedInventory.length === 0"
                    class="no-data-mobile"
                >
                    No data found
                </div>
                <div
                    class="mobile-card"
                    v-else
                    v-for="(item, index) in sortedInventory"
                    :key="item.id"
                >
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <!-- Updated mobile gallery with captured images support -->
                        <div class="mobile-product-image clickable">
                            <!-- Show captured image if available -->
                            <div
                                v-if="
                                    item.capturedImages &&
                                    item.capturedImages.capturedimg1
                                "
                                class="gallery-thumbnail position-relative"
                                @click="openImageModal(item)"
                                style="cursor: pointer"
                            >
                                <img
                                    :src="`/images/product_images/${
                                        item.company || 'Airstaffs'
                                    }/${item.capturedImages.capturedimg1}`"
                                    :alt="getDisplayTitle(item)"
                                    class="product-thumbnail clickable-image"
                                    @error="handleImageError"
                                />
                                <div
                                    class="image-count-badge"
                                    v-if="countCapturedImages(item) > 1"
                                >
                                    +{{ countCapturedImages(item) - 1 }}
                                </div>
                            </div>

                            <!-- Fallback to regular product image -->
                            <div
                                v-else
                                @click="openImageModal(item)"
                                style="cursor: pointer"
                            >
                                <img
                                    :src="'/images/thumbnails/' + item.img1"
                                    :alt="getDisplayTitle(item)"
                                    class="product-thumbnail clickable-image"
                                    @error="handleImageError($event)"
                                />
                                <div
                                    class="image-count-badge"
                                    v-if="countAllImages(item) > 0"
                                >
                                    +{{ countAllImages(item) }}
                                </div>
                            </div>
                        </div>
                        <div class="mobile-product-info">
                            <h6 class="mobile-product-name clickable">
                                <p>RT# : {{ item.rtcounter }}</p>
                                <p>{{ getDisplayTitle(data) }}</p>
                            </h6>
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Added date:</span>
                            <span class="mobile-detal-value">
                                {{ item.datedelivered }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Updated date:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.lastDateUpdate }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">FNSKU:</span>
                            <span class="mobile-detal-value">
                                {{ item.FNSKUviewer }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">MSKU:</span>
                            <span class="mobile-detal-value">
                                {{ item.MSKUviewer }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detal-value">
                                {{ item.ASIN }}</span
                            >
                        </div>
                        <!-- Insert Hidden Here -->
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label">FBM:</span>
                            <span class="mobile-detal-value">
                                {{ item.FBMAvailable }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label">FBA:</span>
                            <span class="mobile-detal-value">
                                {{ item.FbaAvailable }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label">Outbound:</span>
                            <span class="mobile-detal-value">
                                {{ item.Outbound }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label">Inbound:</span>
                            <span class="mobile-detal-value">
                                {{ item.Inbound }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label"
                                >Unfulfillable:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.Unfulfillable }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label">Reserved:</span>
                            <span class="mobile-detal-value">
                                {{ item.Reserved }}</span
                            >
                        </div>
                        <!--  -->
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Fullfilment:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.Fulfilledby }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Status:</span>
                            <span class="mobile-detal-value">
                                {{ item.status }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Serial Number:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.serialnumber }}</span
                            >
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-actions">
                        <Button
                            @click="openEditModal(item)"
                            icon="pi pi-info-circle"
                            size="small"
                            severity="info"
                            label="More Details"
                            :style="{ width: '100%' }"
                        />
                    </div>

                    <hr v-if="expandedRows[index]" />

                    <div
                        v-if="expandedRows[index]"
                        class="mobile-expanded-content"
                    >
                        <p><strong>Expanded Rows Here</strong></p>
                        <p><strong>Product Name:</strong> {{ item.AStitle }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination with centered layout -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <Select
                        v-model="perPage"
                        @change="changePerPage"
                        :options="rowsPerPage"
                        size="small"
                        optionLabel="label"
                        optionValue="value"
                    />
                    <!-- <select v-model="perPage" @change="changePerPage" class="per-page-select">
                        <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select> -->
                </div>

                <div class="pagination">
                    <Button
                        @click="prevPage"
                        :disabled="currentPage === 1"
                        class="pagination-button"
                        label="Back"
                        size="small"
                        icon="pi pi-angle-left"
                        severity="info"
                    />
                    <span class="pagination-info"
                        >Page {{ currentPage }} of {{ totalPages }}</span
                    >
                    <Button
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        class="pagination-button"
                        label="Next"
                        size="small"
                        icon="pi pi-angle-right"
                        severity="info"
                        iconPos="right"
                    />
                </div>
            </div>
        </div>

        <!-- Image Modal with Tabs -->
        <ViewImageGalleryModal
            :showImageModal="showImageModal"
            :closeImageModal="closeImageModal"
            :ProductTitle="ProductTitle"
            :regularImages="regularImages"
            :capturedImages="capturedImages"
            :handleImageError="handleImageError"
        />

        <Dialog
            class="view-modal"
            v-model:visible="showEditModal"
            modal
            :header="`RT # ${item.rtcounter} - ${getDisplayTitle(item)}`"
            style="width: 110rem"
        >
            <div class="modal-body">
                <div class="view-info-container">
                    <div class="view-grid-wrapper">
                        <div class="form-col-left">
                            <gallery :item="item" />
                            <Card>
                                <template #title>
                                    <h5 class="text-primary fw-bolder">
                                        Description
                                    </h5>
                                </template>
                                <template #content>
                                    <p
                                        style="
                                            word-break: break-all;
                                            max-height: 450px;
                                            overflow-y: auto;
                                        "
                                    >
                                        {{ item.description }}
                                    </p>
                                </template>
                            </Card>
                        </div>

                        <div class="form-col-right">
                            <div class="row">
                                <div class="col-lg-6">
                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">
                                            Warehouse & Tracking
                                        </h3>
                                        <dl class="info-list">
                                            <div class="info-item">
                                                <dt>Module:</dt>
                                                <dd>
                                                    {{ item.ProductModuleLoc }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Warehouse Location:</dt>
                                                <dd>
                                                    {{ item.warehouselocation }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Serial Number:</dt>
                                                <dd>
                                                    {{ item.serialnumber }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Tracking Number:</dt>
                                                <dd>
                                                    {{ item.trackingnumber }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </section>

                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">
                                            Product Identifiers
                                        </h3>
                                        <dl class="info-list">
                                            <div class="info-item">
                                                <dt>RT:</dt>
                                                <dd>
                                                    {{ item.ProductID }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>ASIN:</dt>
                                                <dd>{{ item.ASIN }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>RPN:</dt>
                                                <dd>{{ item.RPN }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>PRD:</dt>
                                                <dd>{{ item.PRD }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>UPC:</dt>
                                                <dd>{{ item.UPC }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>EAN:</dt>
                                                <dd>{{ item.EAN }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>FNSKU:</dt>
                                                <dd>{{ item.FNSKU }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>SKU:</dt>
                                                <dd>{{ item.SKU }}</dd>
                                            </div>
                                        </dl>
                                    </section>

                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">
                                            Order Information
                                        </h3>
                                        <dl class="info-list">
                                            <div class="info-item">
                                                <dt>Order Number:</dt>
                                                <dd>{{ item.rtid }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Item Number:</dt>
                                                <dd>
                                                    {{ item.itemnumber }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Basket Number:</dt>
                                                <dd>
                                                    {{ item.basketnumber }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Order Date:</dt>
                                                <dd>
                                                    {{ item.orderdate }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Delivered Date:</dt>
                                                <dd>
                                                    {{ item.datedelivered }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Seller:</dt>
                                                <dd>{{ item.seller }}</dd>
                                            </div>
                                        </dl>
                                    </section>

                                    <section
                                        class="info-section"
                                        v-if="item.grading || item.notes"
                                    >
                                        <h3 class="text-primary fw-bolder">
                                            Additional Info
                                        </h3>
                                        <dl class="info-list">
                                            <div
                                                class="info-item"
                                                v-if="item.grading"
                                            >
                                                <dt>Grading:</dt>
                                                <dd>{{ item.grading }}</dd>
                                            </div>
                                            <div
                                                class="info-item"
                                                v-if="item.notes"
                                            >
                                                <dt>Notes:</dt>
                                                <dd>{{ item.notes }}</dd>
                                            </div>
                                        </dl>
                                    </section>
                                </div>

                                <div class="col-lg-6">
                                    <section class="pricing-section">
                                        <h3 class="text-primary fw-bolder">
                                            Pricing
                                        </h3>
                                        <dl class="pricing-list">
                                            <div class="pricing-item">
                                                <dt>Unit Price:</dt>
                                                <dd>
                                                    {{
                                                        item.formattedUnitprice ||
                                                        "0.00"
                                                    }}
                                                </dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Quantity:</dt>
                                                <dd>
                                                    {{ item.quantity || 0 }}
                                                </dd>
                                            </div>
                                            <div
                                                class="pricing-item subtotal-line"
                                            >
                                                <dt>Subtotal:</dt>
                                                <dd>
                                                    {{ item.price || "0.00" }}
                                                </dd>
                                            </div>

                                            <div
                                                class="pricing-item"
                                                v-if="item.Discount"
                                            >
                                                <dt>Discount:</dt>
                                                <dd class="discount">
                                                    -{{ item.Discount }}
                                                </dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Tax:</dt>
                                                <dd>{{ item.tax }}</dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Shipping:</dt>
                                                <dd>
                                                    {{ item.priceshipping }}
                                                </dd>
                                            </div>

                                            <div
                                                class="pricing-item total-line"
                                            >
                                                <dt>Total Price:</dt>
                                                <dd class="total-amount">
                                                    {{ grandTotal }}
                                                </dd>
                                            </div>

                                            <div
                                                class="pricing-item refund-line"
                                                v-if="item.refund"
                                            >
                                                <dt>Refund:</dt>
                                                <dd class="refund">
                                                    {{ item.refund }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>
        <ScrollTop />
    </div>
</template>

<script>
import { Button, Dialog, ScrollTop, Select } from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import Production from "./production.js";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import Gallery from "../../components/Gallery/gallery.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import { ROWS_PER_PAGE } from "../../constant.js";

const TABLE_COLUMNS = [
    {
        field: "gallery",
        header: "Gallery",
        slot: "gallery",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        field: "ProductTitle",
        header: "Product Name",
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "ProductTitle",
        style: { minWidth: "15rem", maxWidth: "20rem" },
    },

    {
        field: "ASIN",
        header: "ASIN",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "FNSKUviewer",
        header: "FNSKU",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "MSKUviewer",
        header: "MNSKU",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "grading",
        header: "Grade",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "ProductModuleLoc",
        header: "Module Location",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "Fulfilledby",
        header: "Fulfilledby",
        bodyStyle: { fontSize: "14px" },
    },
];
export default {
    mixins: [Production],
    components: {
        XDataTable,
        Dialog,
        TableGallery,
        Gallery,
        Button,
        ScrollTop,
        TitlePage,
        ViewImageGalleryModal,
        AnimateDiv,
        Select,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            rowsPerPage: ROWS_PER_PAGE,
        };
    },
    methods: {
        transformDataForGallery(data) {
            // Safety check
            if (!data) {
                return {};
            }

            // If captured images exist, use them with full path
            if (data.capturedImages && data.capturedImages.capturedimg1) {
                const transformedData = { ...data };

                // Map capturedimg1-12 to img1-12 with full path
                for (let i = 1; i <= 12; i++) {
                    const capturedImg = data.capturedImages[`capturedimg${i}`];
                    if (capturedImg) {
                        // Add full path: /images/product_images/Airstaffs/
                        transformedData[`img${i}`] =
                            `/images/product_images/Airstaffs/${capturedImg}`;
                    } else {
                        transformedData[`img${i}`] = null;
                    }
                }

                // Clear img13-15 since captured images only go up to 12
                for (let i = 13; i <= 15; i++) {
                    transformedData[`img${i}`] = null;
                }

                return transformedData;
            }

            // Return original data if no captured images exist (fallback to product images)
            return data;
        },

        countAllImages(data) {
            // Safety check
            if (!data) {
                return 0;
            }

            // If captured images exist, count them
            if (data.capturedImages) {
                let count = 0;
                for (let i = 1; i <= 12; i++) {
                    if (data.capturedImages[`capturedimg${i}`]) {
                        count++;
                    }
                }
                // Return count if captured images exist
                if (count > 0) {
                    return count;
                }
            }

            // Otherwise count product images (fallback)
            let count = 0;
            for (let i = 1; i <= 15; i++) {
                if (data[`img${i}`]) {
                    count++;
                }
            }
            return count;
        },
    },
};
</script>
