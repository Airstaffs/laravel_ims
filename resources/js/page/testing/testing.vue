<template>
    <div class="vue-container testing-module">
        <TitlePage
            title="Testing Module"
            subtitle="Manage and log quality assurance and functional testing results for products prior to inventory staging."
        />

        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="px-4">
            <XDataTable
                :value="sortedInventory"
                :loading="loading"
                :columns="visibleColumns"
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

                        <!-- Use regular product images as fallback -->
                        <div
                            v-else-if="data.img1 && data.img1 !== 'NULL'"
                            class="gallery-thumbnail position-relative"
                            @click="openImageModal(data)"
                            style="cursor: pointer"
                        >
                            <img
                                :src="`/images/thumbnails/${data.img1}`"
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
                                v-if="countAllImages(data) > 1"
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
                                +{{ countAllImages(data) - 1 }}
                            </span>
                        </div>

                        <!-- Fallback icon if no images -->
                        <div
                            v-else
                            class="d-flex justify-content-center align-items-center"
                            style="
                                width: 50px;
                                height: 50px;
                                background-color: #f0f0f0;
                                border-radius: 4px;
                            "
                        >
                            <i
                                class="pi pi-image"
                                style="font-size: 1.5rem; color: #999"
                            ></i>
                        </div>
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
                                RT# {{ data.rtcounter }}
                            </p>
                            <p class="fw-semibold">
                                {{ getDisplayTitle(data) }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #datedelivered="{ data }">
                    {{ convertToLocalDate(data.datedelivered) }}
                </template>

                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start">
                        <Button
                            size="small"
                            severity="success"
                            variant="text"
                            label="Condition"
                            icon="pi pi-check-square"
                            @click="openConditionModal(data)"
                            class="text-success"
                        />
                        <Button
                            size="small"
                            severity="contrast"
                            variant="text"
                            label="View Details"
                            class="text-primary"
                            icon="pi pi-exclamation-circle"
                            @click="openEditModal(data)"
                        />
                    </div>
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <MobileCard1
                :sortedInventory="sortedInventory"
                :expandedRows="expandedRows"
                :openImageModal="openImageModal"
                :handleImageError="handleImageError"
                :countAdditionalImages="countAdditionalImages"
                :openEditModal="openEditModal"
                :loading="loading"
                :showDetails="showDetails"
                :visibleFields="[
                    'price',
                    'serialnumber',
                    'trackingnumber',
                    'datedelivered',
                ]"
            />
        </div>

        <!-- Pagination -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <Select
                        v-model="perPage"
                        @change="changePerPage"
                        :options="rowsPerPage"
                        optionLabel="label"
                        optionValue="value"
                        size="small"
                    />
                </div>

                <div class="pagination">
                    <Button
                        @click="prevPage"
                        :disabled="currentPage === 1"
                        class="pagination-button"
                        label="Back"
                        icon="pi pi-angle-left"
                        size="small"
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
                        icon="pi pi-angle-right"
                        size="small"
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

        <!-- Condition Checklist Modal -->
        <ReceivedConditionModal
            v-model:visible="showConditionModal"
            :item="selectedItem"
            @saved="handleConditionSaved"
        />

        <!-- Move to Cleaning Confirmation Dialog -->
        <Dialog
            v-model:visible="showMoveConfirmation"
            modal
            header="Move to Cleaning & Prepping?"
            style="width: 35rem"
            :pt="{ root: { class: 'mobile-fullscreen-dialog' } }"
        >
            <div class="confirmation-content">
                <i
                    class="pi pi-arrow-right-arrow-left"
                    style="
                        font-size: 3rem;
                        color: var(--primary-color);
                        display: block;
                        text-align: center;
                        margin-bottom: 1rem;
                    "
                ></i>
                <p class="text-center mb-3">
                    <strong>{{ moveItemDetails?.ProductTitle }}</strong>
                </p>
                <p class="text-center">
                    Testing complete! Would you like to move this item to
                    <strong>Cleaning & Prepping</strong> module?
                </p>
                <div class="mt-3 p-3 bg-light rounded">
                    <small class="text-muted">
                        <i class="pi pi-info-circle"></i>
                        This will update the item location from
                        <strong>Testing</strong> to <strong>Cleaning</strong>
                    </small>
                </div>
            </div>
            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="cancelMove"
                    severity="secondary"
                />
                <Button
                    label="Move to Cleaning"
                    icon="pi pi-arrow-right"
                    @click="confirmMoveToCleaning"
                    :loading="movingItem"
                    severity="success"
                />
            </template>
        </Dialog>

        <!-- View Details Modal -->
        <Dialog
            v-model:visible="showEditModal"
            class="view-modal"
            modal
            :header="`RT # ${item.rtcounter} - ${getDisplayTitle(item)}`"
            style="width: 110rem"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <div>
                <div class="view-info-container">
                    <div class="view-grid-wrapper">
                        <!-- LEFT: IMAGE -->
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
                                            font-size: 14px;
                                        "
                                    >
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
                                                <dd>{{ item.serialnumber }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Tracking Number:</dt>
                                                <dd>
                                                    {{ item.trackingnumber }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </section>
                                    <!-- Product Identifiers -->
                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">
                                            Product Identifiers
                                        </h3>
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
                                                <dd>{{ item.itemnumber }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Basket Number:</dt>
                                                <dd>{{ item.basketnumber }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Order Date:</dt>
                                                <dd>{{ localOrderDate }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Delivered Date:</dt>
                                                <dd>
                                                    {{ localDeliveredDate }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Seller:</dt>
                                                <dd>{{ item.seller }}</dd>
                                            </div>
                                        </dl>
                                    </section>

                                    <!-- Additional Info -->
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

                                <!-- Right Column: Pricing -->
                                <div
                                    class="col-md-6"
                                    v-show="showPricingSection"
                                >
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
import { Button, Dialog, Card, ScrollTop, Select } from "primevue";
import Testing from "./testing.js";
import gallery from "../../components/Gallery/gallery.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import MobileCard1 from "../../components/MobileCard1/MobileCard1.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import ReceivedConditionModal from "./modals/receivedCondtion_modal.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
import axios from "axios";
import { showPricingForPH } from "../../utils/helpers.js";

const API_BASE_URL = import.meta.env.VITE_API_URL;

const TABLE_COLUMNS = [
    {
        field: "gallery",
        header: "Gallery",
        slot: "gallery",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        field: "AStitle",
        header: "Product Name",
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "ProductTitle",
        style: { maxWidth: "20rem" },
    },
    {
        field: "price",
        header: "Price",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "serialNumber",
        header: "Serial Number",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "trackingnumber",
        header: "Tracking Number",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "datedelivered",
        header: "Date Delivered",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
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
        ViewImageGalleryModal,
        AnimateDiv,
        Select,
        ReceivedConditionModal,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            rowsPerPage: ROWS_PER_PAGE,
            showConditionModal: false,
            selectedItem: null,
            showMoveConfirmation: false,
            moveItemDetails: null,
            movingItem: false,
            currentTimezone: "UTC",
            timezoneLabel: "Loading...",
            showPricingSection: showPricingForPH(),
        };
    },
    async mounted() {
        await this.loadUserTimezone();
        window.addEventListener("resize", this.updatePricingView);
    },
    computed: {
        visibleColumns() {
            if (!this.columns) return [];

            const detailFields = [
                "FBMAvailable",
                "FbaAvailable",
                "Outbound",
                "Inbound",
                "Reserved",
                "Unfulfillable",
            ];
            const mandatoryFields = ["gallery", "ProductTitle"];

            return this.columns.filter((col) => {
                if (mandatoryFields.includes(col.field)) {
                    return true;
                }

                if (!this.showDetails && detailFields.includes(col.field)) {
                    return false;
                }

                return true;
            });
        },

        // ✅ ADD THESE COMPUTED PROPERTIES FOR DATE CONVERSION
        localOrderDate: {
            get() {
                return this.convertToLocalDate(this.item.orderdate);
            },
            set(value) {
                this.item.orderdate = this.convertFromLocalDate(value);
            },
        },
        localDeliveredDate: {
            get() {
                return this.convertToLocalDate(this.item.datedelivered);
            },
            set(value) {
                this.item.datedelivered = this.convertFromLocalDate(value);
            },
        },
    },
    methods: {
        openConditionModal(item) {
            this.selectedItem = item;
            this.showConditionModal = true;
        },

        async handleConditionSaved(conditionData) {
            console.log("Condition saved:", conditionData);

            // Show success notification
            if (typeof this.$swal !== "undefined") {
                await this.$swal.fire({
                    icon: "success",
                    title: "Success!",
                    text: "Received condition saved successfully",
                    timer: 2000,
                    showConfirmButton: false,
                });
            } else if (typeof Swal !== "undefined") {
                await Swal.fire({
                    icon: "success",
                    title: "Success!",
                    text: "Received condition saved successfully",
                    timer: 2000,
                    showConfirmButton: false,
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
                    product_id: String(this.moveItemDetails.ProductID),
                };

                console.log("Moving to cleaning:", dataToSend);

                const response = await axios.post(
                    `${API_BASE_URL}/api/testing/move-to-cleaning`,
                    dataToSend,
                );

                if (response.data.success) {
                    // Success notification
                    if (typeof this.$swal !== "undefined") {
                        this.$swal.fire({
                            icon: "success",
                            title: "Moved!",
                            text: "Item moved to Cleaning & Prepping module successfully",
                            timer: 2000,
                            showConfirmButton: false,
                        });
                    } else if (typeof Swal !== "undefined") {
                        Swal.fire({
                            icon: "success",
                            title: "Moved!",
                            text: "Item moved to Cleaning & Prepping module successfully",
                            timer: 2000,
                            showConfirmButton: false,
                        });
                    } else {
                        alert(
                            "Success! Item moved to Cleaning & Prepping module",
                        );
                    }

                    // Close modal and refresh
                    this.showMoveConfirmation = false;
                    this.moveItemDetails = null;

                    // Refresh inventory to remove the moved item
                    await this.fetchInventory();
                }
            } catch (error) {
                console.error("Failed to move item:", error);
                console.error("Error response data:", error.response?.data);
                console.error(
                    "Validation errors:",
                    error.response?.data?.errors,
                );

                let errorMessage = "Failed to move item to Cleaning module";

                // Handle validation errors
                if (error.response?.data?.errors) {
                    const errors = error.response.data.errors;
                    errorMessage = Object.values(errors).flat().join("\n");
                } else if (error.response?.data?.message) {
                    errorMessage = error.response.data.message;
                }

                if (typeof this.$swal !== "undefined") {
                    this.$swal.fire({
                        icon: "error",
                        title: "Error Moving Item",
                        text: errorMessage,
                        confirmButtonText: "OK",
                    });
                } else if (typeof Swal !== "undefined") {
                    Swal.fire({
                        icon: "error",
                        title: "Error Moving Item",
                        text: errorMessage,
                        confirmButtonText: "OK",
                    });
                } else {
                    alert("Error: " + errorMessage);
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
        },

        transformDataForGallery(data) {
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

        convertToLocalDate(dateString) {
            if (!dateString) return "";

            try {
                // Parse the date from database (assumed to be in UTC or server timezone)
                const date = new Date(dateString);

                // Format to YYYY-MM-DD for date input in user's timezone
                const options = {
                    timeZone: this.currentTimezone,
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                };

                const formatter = new Intl.DateTimeFormat("en-CA", options); // en-CA gives YYYY-MM-DD format
                return formatter.format(date);
            } catch (error) {
                console.error("Error converting to local date:", error);
                return dateString;
            }
        },

        convertFromLocalDate(localDateString) {
            if (!localDateString) return null;

            try {
                // The input gives us YYYY-MM-DD in user's timezone
                // We need to convert it to a proper datetime for storage

                // Create a date object at noon in the user's timezone to avoid day boundary issues
                const [year, month, day] = localDateString.split("-");
                const dateInUserTz = new Date(
                    `${year}-${month}-${day}T12:00:00`,
                );

                // Format for database storage (ISO format)
                return dateInUserTz.toISOString().split("T")[0]; // Returns YYYY-MM-DD
            } catch (error) {
                console.error("Error converting from local date:", error);
                return localDateString;
            }
        },

        async loadUserTimezone() {
            try {
                const response = await axios.get("/api/timezone/current");

                if (response.data.success && response.data.usertimezone) {
                    this.currentTimezone = response.data.usertimezone;

                    // Format timezone for display
                    const timezoneParts = this.currentTimezone.split("/");
                    const location = timezoneParts[
                        timezoneParts.length - 1
                    ].replace("_", " ");

                    // ✅ FIXED: Calculate GMT offset for the SELECTED timezone, not browser's
                    const date = new Date();

                    // Get the date in UTC
                    const utcDate = new Date(
                        date.toLocaleString("en-US", { timeZone: "UTC" }),
                    );

                    // Get the date in user's selected timezone
                    const userTzDate = new Date(
                        date.toLocaleString("en-US", {
                            timeZone: this.currentTimezone,
                        }),
                    );

                    // Calculate offset in hours
                    const offsetMs = userTzDate - utcDate;
                    const offsetHours = Math.round(offsetMs / (1000 * 60 * 60));
                    const offsetSign = offsetHours >= 0 ? "+" : "-";
                    const gmtOffset = `GMT${offsetSign}${Math.abs(
                        offsetHours,
                    )}`;

                    this.timezoneLabel = `(${gmtOffset})`;
                } else {
                    // Fallback to browser timezone
                    const browserTz =
                        Intl.DateTimeFormat().resolvedOptions().timeZone;
                    this.currentTimezone = browserTz;
                    const location = browserTz
                        .split("/")
                        .pop()
                        .replace("_", " ");
                    this.timezoneLabel = location;
                }

                console.log("📍 Timezone loaded:", this.timezoneLabel);
            } catch (error) {
                console.error("Error loading timezone:", error);
                this.currentTimezone = "UTC";
                this.timezoneLabel = "UTC";
            }
        },
        updatePricingView() {
            this.showPricingSection = showPricingForPH();
        },
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.updatePricingView);
    },
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
