<template>
    <div class="vue-container testing-module">
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
                    <div class="d-flex gap-2">
                        <Button size="small" severity="success" variant="text" label="Condition" 
                            icon="pi pi-check-square" @click="openConditionModal(data)" 
                            class="text-success" />
                        <Button size="small" severity="contrast" variant="text" label="View Details" class="text-primary"
                            icon="pi pi-exclamation-circle" @click="openEditModal(data)" />
                    </div>
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <MobileCard1 
                :showDetails="showDetails" 
                :sortedInventory="sortedInventory"
                :expandedRows="expandedRows" 
                :openImageModal="openImageModal" 
                :handleImageError="handleImageError"
                :countAdditionalImages="countAdditionalImages" 
                :openEditModal="openEditModal"
                :openConditionModal="openConditionModal"
                :loading="loading" 
            />
        </div>

        <!-- Pagination -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <Select v-model="perPage" @change="changePerPage" :options="rowsPerPage" optionLabel="label"
                        optionValue="value" size="small" />
                </div>

                <div class="pagination">
                    <Button @click="prevPage" :disabled="currentPage === 1" class="pagination-button" label="Back"
                        icon="pi pi-angle-left" size="small" severity="info" />
                    <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                    <Button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button"
                        label="Next" icon="pi pi-angle-right" size="small" severity="info" iconPos="right" />
                </div>
            </div>
        </div>

        <!-- Image Modal -->
        <ViewImageModal v-model:visible="showImageModal" :title="ProductTitle" :imageList="imageList"
            :basePath="basePath" :onImageErrorMain="onImageErrorMain" :onThumbnailError="onThumbnailError"
            @close="closeImageModal" />

        <!-- Condition Checklist Modal -->
        <ReceivedConditionModal 
            v-model:visible="showConditionModal"
            :item="selectedItem"
            @saved="handleConditionSaved"
        />

        <!-- Move to Cleaning Confirmation Dialog -->
        <Dialog v-model:visible="showMoveConfirmation" modal header="Move to Cleaning & Prepping?" 
            style="width: 35rem;" :pt="{ root: { class: 'mobile-fullscreen-dialog' } }">
            <div class="confirmation-content">
                <i class="pi pi-arrow-right-arrow-left" style="font-size: 3rem; color: var(--primary-color); display: block; text-align: center; margin-bottom: 1rem;"></i>
                <p class="text-center mb-3">
                    <strong>{{ moveItemDetails?.ProductTitle }}</strong>
                </p>
                <p class="text-center">
                    Testing complete! Would you like to move this item to <strong>Cleaning & Prepping</strong> module?
                </p>
                <div class="mt-3 p-3 bg-light rounded">
                    <small class="text-muted">
                        <i class="pi pi-info-circle"></i> 
                        This will update the item location from <strong>Testing</strong> to <strong>Cleaning</strong>
                    </small>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="cancelMove" severity="secondary" />
                <Button label="Move to Cleaning" icon="pi pi-arrow-right" @click="confirmMoveToCleaning" 
                    :loading="movingItem" severity="success" />
            </template>
        </Dialog>

        <!-- View Details Modal -->
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
                                    <p style="word-break: break-all; max-height: 450px; overflow-y: auto; font-size: 14px;">
                                        {{ item.description }}
                                    </p>
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
                                                <dd>{{ item.ProductModuleLoc }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Warehouse Location:</dt>
                                                <dd>{{ item.warehouselocation }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Serial Number:</dt>
                                                <dd>{{ item.serialnumber }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Tracking Number:</dt>
                                                <dd>{{ item.trackingnumber }}</dd>
                                            </div>
                                        </dl>
                                    </section>
                                    <!-- Product Identifiers -->
                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">Product Identifiers</h3>
                                        <dl class="info-list">
                                            <div class="info-item">
                                                <dt>RT:</dt>
                                                <dd>{{ item.ProductID }}</dd>
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
                                                <dd>{{ item.itemnumber }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Basket Number:</dt>
                                                <dd>{{ item.basketnumber }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Order Date:</dt>
                                                <dd>{{ item.orderdate }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Delivered Date:</dt>
                                                <dd>{{ item.datedelivered }}</dd>
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
                                            <div class="pricing-item">
                                                <dt>Unit Price:</dt>
                                                <dd>{{ item.formattedUnitprice || "0.00" }}</dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Quantity:</dt>
                                                <dd>{{ item.quantity || 0 }}</dd>
                                            </div>
                                            <div class="pricing-item subtotal-line">
                                                <dt>Subtotal:</dt>
                                                <dd>{{ item.price || "0.00" }}</dd>
                                            </div>
                                            <div class="pricing-item" v-if="item.Discount">
                                                <dt>Discount:</dt>
                                                <dd class="discount">-{{ item.Discount }}</dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Tax:</dt>
                                                <dd>{{ item.tax }}</dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Shipping:</dt>
                                                <dd>{{ item.priceshipping }}</dd>
                                            </div>
                                            <div class="pricing-item total-line">
                                                <dt>Total Price:</dt>
                                                <dd class="total-amount">{{ grandTotal }}</dd>
                                            </div>
                                            <div class="pricing-item refund-line" v-if="item.refund">
                                                <dt>Refund:</dt>
                                                <dd class="refund">{{ item.refund }}</dd>
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
import { Button, Dialog, Card, ScrollTop, Select } from "primevue";
import Testing from "./testing.js";
import gallery from "../../components/Gallery/gallery.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import MobileCard1 from "../../components/MobileCard1/MobileCard1.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageModal from "../../components/ViewImageModal/ViewImageModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import ReceivedConditionModal from "./modals/receivedCondtion_modal.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL;

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
        AnimateDiv,
        Select,
        ReceivedConditionModal
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            rowsPerPage: ROWS_PER_PAGE,
            showConditionModal: false,
            selectedItem: null,
            showMoveConfirmation: false,
            moveItemDetails: null,
            movingItem: false
        };
    },
    computed: {
        visibleColumns() {
            if (!this.columns) return [];

            const detailFields = ["FBMAvailable", "FbaAvailable", "Outbound", "Inbound", "Reserved", "Unfulfillable"];
            const mandatoryFields = ["gallery", "ProductTitle"];

            return this.columns.filter(col => {
                if (mandatoryFields.includes(col.field)) {
                    return true;
                }
                
                if (!this.showDetails && detailFields.includes(col.field)) {
                    return false;
                }
                
                return true;
            });
        },
    },
    methods: {
        openConditionModal(item) {
            this.selectedItem = item;
            this.showConditionModal = true;
        },

        async handleConditionSaved(conditionData) {
            console.log('Condition saved:', conditionData);
            
            // Show success notification
            if (typeof this.$swal !== 'undefined') {
                await this.$swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Received condition saved successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Received condition saved successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
            
            // Store item details for move confirmation
            this.moveItemDetails = this.selectedItem;
            
            // Show move to cleaning confirmation
            this.showMoveConfirmation = true;
        },

        async confirmMoveToCleaning() {
            if (!this.moveItemDetails) return;

            this.movingItem = true;
            try {
                const dataToSend = {
                    item_number: this.moveItemDetails.itemnumber,
                    product_id: String(this.moveItemDetails.ProductID)
                };
                
                console.log('Moving to cleaning:', dataToSend);
                
                const response = await axios.post(
                    `${API_BASE_URL}/api/testing/move-to-cleaning`,
                    dataToSend
                );

                if (response.data.success) {
                    // Success notification
                    if (typeof this.$swal !== 'undefined') {
                        this.$swal.fire({
                            icon: 'success',
                            title: 'Moved!',
                            text: 'Item moved to Cleaning & Prepping module successfully',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Moved!',
                            text: 'Item moved to Cleaning & Prepping module successfully',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert('Success! Item moved to Cleaning & Prepping module');
                    }

                    // Close modal and refresh
                    this.showMoveConfirmation = false;
                    this.moveItemDetails = null;
                    
                    // Refresh inventory to remove the moved item
                    await this.fetchInventory();
                }
            } catch (error) {
                console.error('Failed to move item:', error);
                console.error('Error response data:', error.response?.data);
                console.error('Validation errors:', error.response?.data?.errors);
                
                let errorMessage = 'Failed to move item to Cleaning module';
                
                // Handle validation errors
                if (error.response?.data?.errors) {
                    const errors = error.response.data.errors;
                    errorMessage = Object.values(errors).flat().join('\n');
                } else if (error.response?.data?.message) {
                    errorMessage = error.response.data.message;
                }
                
                if (typeof this.$swal !== 'undefined') {
                    this.$swal.fire({
                        icon: 'error',
                        title: 'Error Moving Item',
                        text: errorMessage,
                        confirmButtonText: 'OK'
                    });
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error Moving Item',
                        text: errorMessage,
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Error: ' + errorMessage);
                }
            } finally {
                this.movingItem = false;
            }
        },

        cancelMove() {
            this.showMoveConfirmation = false;
            this.moveItemDetails = null;
            
            // Still refresh inventory to show updated condition
            this.fetchInventory();
        }
    }
};
</script>

<style scoped>
.confirmation-content {
    padding: 1rem 0;
}

.confirmation-content p {
    font-size: 1rem;
    line-height: 1.6;
}

.confirmation-content .bg-light {
    background-color: #f8f9fa !important;
    border-left: 3px solid var(--primary-color);
}
</style>