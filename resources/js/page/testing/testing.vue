<template>
    <div class="vue-container testing-module">
        <!-- <div class="top-header">
            <span>Top Header</span>
        </div> -->

        <!-- <h2 class="module-title">Testing Module</h2> -->
        <TitlePage title="Testing Module"
            subtitle="Manage and log quality assurance and functional testing results for products prior to inventory staging." />

        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="px-4">
            <XDataTable :value="sortedInventory" :loading="loading" :columns="visibleColumns" :paginator="false"
                tableClass="desktop-view" selectionMode="multiple" dataKey="ProductID">

                <template #gallery="{ data }">
                    <div class="d-flex justify-content-center align-items-center">
                        <TableGallery :data="data" :openImageModal="openImageModal" :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages" />
                    </div>
                </template>

                <template #ProductTitle="{ data }">
                    <div class="d-flex align-items-start gap-4">
                        <div style="word-break: break-word; white-space: normal; overflow-wrap: break-word; flex: 1;">
                            <p style="font-size: .8rem;">RT# {{ data.rtcounter }}</p>
                            <p class="fw-semibold">
                                {{ data.ProductTitle }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #actions="{ data }">
                    <Button size="small" severity="contrast" variant="text" label="View Details" class="text-primary"
                        icon="pi pi-exclamation-circle" @click="openEditModal(data)" />
                </template>
            </XDataTable>
        </AnimateDiv>


        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <MobileCard1 :data="item" :showDetails="showDetails" :sortedInventory="sortedInventory"
                :expandedRows="expandedRows" :openImageModal="openImageModal" :handleImageError="handleImageError"
                :countAdditionalImages="countAdditionalImages" :openEditModal="openEditModal" :loading="loading" />
        </div>

        <!-- Pagination with centered layout -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <select v-model="perPage" @change="changePerPage" class="per-page-select">
                        <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                </div>

                <div class="pagination">
                    <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
                        <i class="fas fa-chevron-left"></i> Back
                    </button>
                    <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                    <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Image Modal -->
        <ViewImageModal v-model:visible="showImageModal" :title="ProductTitle" :imageList="imageList"
            :basePath="basePath" :onImageErrorMain="onImageErrorMain" :onThumbnailError="onThumbnailError"
            @close="closeImageModal" />

        <Dialog v-model:visible="showEditModal" class="view-modal" modal
            :header="`RT # ${item.ProductID} ${item.ProductTitle}`" style="width: 110rem;" :pt="{
                root: { class: 'mobile-fullscreen-dialog' }
            }">
            <div>
                <div class="view-info-container">
                    <div class="view-grid-wrapper">
                        <!-- LEFT: IMAGE -->
                        <div class="form-col-left">
                            <gallery :item="item" />
                            <Card>
                                <template #title>
                                    <h5 class="text-primary fw-bolder">Description</h5>
                                </template>
                                <template #content>
                                    <p
                                        style="word-break: break-all; max-height: 450px; overflow-y: auto; font-size: 14px;">
                                        {{
                                            item.description }}</p>
                                </template>
                            </Card>
                        </div>
                        <!-- RIGHT: DETAILS -->
                        <div class="form-col-right">
                            <div class="row">
                                <!-- Left Column -->
                                <div class="col-md-6">
                                    <!-- Warehouse & Tracking -->
                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">Warehouse & Tracking</h3>
                                        <dl class="info-list">
                                            <div class="info-item">
                                                <dt>Module:</dt>
                                                <dd>
                                                    {{
                                                        item.ProductModuleLoc
                                                    }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Warehouse Location:</dt>
                                                <dd>
                                                    {{
                                                        item.warehouselocation
                                                    }}
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
                                                    {{
                                                        item.trackingnumber
                                                    }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </section>
                                    <!-- Product Identifiers -->
                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">Product Identifiers</h3>
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

                                    <!-- Order Information -->
                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">Order Information</h3>
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



                                    <!-- Additional Info -->
                                    <section class="info-section" v-if="item.grading || item.notes">
                                        <h3 class="text-primary fw-bolder">Additional Info</h3>
                                        <dl class="info-list">
                                            <div class="info-item" v-if="item.grading">
                                                <dt>Grading:</dt>
                                                <dd>{{ item.grading }}</dd>
                                            </div>
                                            <div class="info-item" v-if="item.notes">
                                                <dt>Notes:</dt>
                                                <dd>{{ item.notes }}</dd>
                                            </div>
                                        </dl>
                                    </section>
                                </div>

                                <!-- Right Column: Pricing -->
                                <div class="col-md-6">
                                    <section class="pricing-section">
                                        <h3 class="text-primary fw-bolder">Pricing</h3>
                                        <dl class="pricing-list">
                                            <!-- Base Pricing -->
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
                                            <div class="pricing-item subtotal-line">
                                                <dt>Subtotal:</dt>
                                                <dd>
                                                    {{
                                                        item.price || "0.00"
                                                    }}
                                                </dd>
                                            </div>

                                            <!-- Adjustments -->
                                            <div class="pricing-item" v-if="item.Discount">
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

                                            <!-- Total -->
                                            <div class="pricing-item total-line">
                                                <dt>Total Price:</dt>
                                                <dd class="total-amount">
                                                    {{ grandTotal }}
                                                </dd>
                                            </div>

                                            <!-- Refund -->
                                            <div class="pricing-item refund-line" v-if="item.refund">
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
import { Button, Dialog, Card, ScrollTop } from "primevue";
import Testing from "./testing.js";
import gallery from "../../components/Gallery/gallery.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import MobileCard1 from "../../components/MobileCard1/MobileCard1.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageModal from "../../components/ViewImageModal/ViewImageModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";

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
        style: { maxWidth: "20rem" },
    },
    { field: "datedelivered", header: "Added Date", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "lastDateUpdate", header: "Updated Date", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "FNSKUviewer", header: "Fnsku", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "MSKUviewer", header: "Msku", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "ASINviewer", header: "Asin", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "FBMAvailable", header: "FBM", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "FbaAvailable", header: "FBA", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Outbound", header: "Outbound", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Inbound", header: "Inbound", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Reserved", header: "Reserved", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Unfulfillable", header: "Unfulfillable", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Fulfilledby", header: "Fulfillment", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Status", header: "Status", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "serialNumber", header: "Serial Number", sortable: true, bodyStyle: "font-size: 14px;" },
];

export default {
    mixins: [Testing],
    components: {
        Button,
        Dialog,
        Card,
        gallery,
        TableGallery,
        XDataTable,
        MobileCard1,
        ScrollTop,
        TitlePage,
        ViewImageModal,
        AnimateDiv
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
        };
    },
    computed: {
        visibleColumns() {
            if (!this.columns) return [];

            //columns can be showed or hidden
            const detailFields = ["FBMAvailable", "FbaAvailable", "Outbound", "Inbound", "Reserved", "Unfulfillable"];


            return this.columns.filter(col => {
                if (!this.showDetails && detailFields.includes(col.field)) {
                    return false;
                }
                return true;
            });
        },
    },
};
</script>