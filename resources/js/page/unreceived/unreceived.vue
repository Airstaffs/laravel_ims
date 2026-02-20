<template>
    <div class="vue-container unreceived-module">
        <div
            class="d-flex align-items-center justify-content-between flex-wrap mb-4"
        >
            <TitlePage
                title="Unreceived Module"
                subtitle="Track and manage all inbound inventory shipments that have not yet been received or confirmed."
            />
            <Button
                class="mx-4"
                @click="openScannerModal"
                label="Scan Items"
                size="small"
                icon="pi pi-barcode"
                severity="secondary"
                outlined
            />
        </div>

        <!-- Simplified Scanner Component -->
        <scanner-component
            scanner-title="Unreceived Scanner"
            storage-prefix="unreceived"
            :enable-camera="true"
            :display-fields="['Trackingnumber', 'RPN', 'PRD', 'Status']"
            :api-endpoint="'/api/unreceived/process-scan'"
            :hide-button="true"
            @process-scan="handleScanProcess"
            @hardware-scan="handleHardwareScan"
            @scanner-opened="handleScannerOpened"
            @scanner-closed="handleScannerClosed"
            @scanner-reset="handleScannerReset"
            @mode-changed="handleModeChange"
            ref="scanner"
        >
            <!-- Simplified input - only tracking number needed -->
            <template #input-fields>
                <div class="input-group">
                    <label>Tracking Number:</label>
                    <input
                        type="text"
                        v-model="trackingNumber"
                        placeholder="Enter Tracking Number (RPN & PRD will be auto-generated)..."
                        @input="handleTrackingInput"
                        @keyup.enter="verifyAndProcessTracking"
                        ref="trackingInput"
                    />
                    <!-- Only show manual process button in Manual mode -->
                    <button
                        v-if="showManualInput"
                        @click="verifyAndProcessTracking"
                        class="verify-button"
                    >
                        Process Tracking
                    </button>
                    <div class="scanner-info">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            RPN and PRD (today's date) will be automatically
                            generated
                        </small>
                    </div>
                </div>
            </template>
        </scanner-component>

        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="px-4">
            <XDataTable
                :value="sortedInventory"
                :loading="loading"
                :columns="visibleColumns"
                :paginator="false"
                selectionMode="multiple"
                dataKey="ProductID"
                tableClass="desktop-view"
            >
                <template #gallery="{ data }">
                    <div
                        class="d-flex justify-content-center align-items-center"
                    >
                        <TableGallery
                            :data="data"
                            :openImageModal="openImageModal"
                            :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages"
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
                                RT# {{ data.rtcounter }}
                            </p>
                            <p class="fw-semibold">
                                {{ data.ProductTitle }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #datedelivered="{ data }">
                    {{ convertToLocalDate(data.datedelivered) }}
                </template>

                <template #actions="{ data }">
                    <Button
                        size="small"
                        severity="contrast"
                        variant="text"
                        label="View Details"
                        class="text-primary"
                        icon="pi pi-exclamation-circle"
                        @click="openEditModal(data)"
                    />
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

        <!-- Image Modal -->
        <div
            v-if="showImageModal"
            class="image-modal-overlay"
            @click="closeImageModal"
        >
            <div class="image-modal-content" @click.stop>
                <button class="modal-close-btn" @click="closeImageModal">
                    <i class="fas fa-times"></i>
                </button>

                <div class="modal-image-container">
                    <button
                        v-if="modalImages.length > 1"
                        class="modal-nav-btn prev-btn"
                        @click="prevImage"
                    >
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <img
                        :src="modalImages[currentImageIndex]"
                        alt="Product Image"
                        class="modal-image"
                        @error="handleImageError($event)"
                    />

                    <button
                        v-if="modalImages.length > 1"
                        class="modal-nav-btn next-btn"
                        @click="nextImage"
                    >
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <div v-if="modalImages.length > 1" class="modal-image-counter">
                    {{ currentImageIndex + 1 }} / {{ modalImages.length }}
                </div>
            </div>
        </div>

        <!-- Pagination with centered layout -->
        <Paginator
            :first="first"
            :rows="perPage"
            :total-records="totalRecords"
            :rows-per-page-options="[10, 20, 50]"
            template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
            class="small-paginator"
            @page="onPageChange"
        />

        <!-- Image Modal -->
        <ViewImageModal
            v-model:visible="showImageModal"
            :title="ProductTitle"
            :imageList="imageList"
            :basePath="basePath"
            :onImageErrorMain="onImageErrorMain"
            :onThumbnailError="onThumbnailError"
            @close="closeImageModal"
        />

        <Dialog
            class="view-modal"
            v-model:visible="showEditModal"
            modal
            :header="`RT # ${item.ProductID} ${item.ProductTitle}`"
            :style="{ width: '95%' }"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
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
                                        font-size: 14px;
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
                                                {{ localOrderDate }}
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
                                <section
                                    class="pricing-section"
                                    v-show="showPricingSection"
                                >
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
                                        <div class="pricing-item subtotal-line">
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

                                        <div class="pricing-item total-line">
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
        </Dialog>
        <ScrollTop />
    </div>
</template>

<script>
import { Button, Dialog, Card, ScrollTop, Select, Paginator } from "primevue";
import Unreceived from "./unreceived.js";
import gallery from "../../components/Gallery/gallery.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import MobileCard1 from "../../components/MobileCard1/MobileCard1.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageModal from "../../components/ViewImageModal/ViewImageModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
import { showPricingForPH } from "../../utils/helpers.js";

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
    mixins: [Unreceived],
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
        Paginator,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            rowsPerPage: ROWS_PER_PAGE,
            currentTimezone: "UTC",
            timezoneLabel: "Loading...",
            showPricingSection: showPricingForPH(),
        };
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.updatePricingView);
    },
    async mounted() {
        await this.loadUserTimezone();
        window.addEventListener("resize", this.updatePricingView);
    },
    computed: {
        visibleColumns() {
            if (!this.columns) return [];

            //columns can be showed or hidden
            const detailFields = [
                "FBMAvailable",
                "FbaAvailable",
                "Outbound",
                "Inbound",
                "Reserved",
                "Unfulfillable",
            ];

            return this.columns.filter((col) => {
                if (!this.showDetails && detailFields.includes(col.field)) {
                    return false;
                }
                return true;
            });
        },

        localOrderDate() {
            return this.convertToLocalDate(this.item.orderdate);
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
        convertToLocalDate(dateString) {
            if (!dateString) return "";

            try {
                const userTimezone = this.currentTimezone;
                const isLATimezone =
                    userTimezone === "America/Los_Angeles" ||
                    userTimezone === "America/Pacific" ||
                    !userTimezone;

                // DB stores time in LA timezone — if user is already in LA, just extract date directly
                if (isLATimezone) {
                    return dateString.split(" ")[0].split("T")[0];
                }

                // User is in a different timezone — convert LA time to user's local timezone
                const isRawFormat =
                    !dateString.includes("T") &&
                    !dateString.includes("Z") &&
                    !dateString.includes("+");

                let date;
                if (isRawFormat) {
                    const isoLike = dateString.replace(" ", "T");
                    const tempDate = new Date(isoLike);
                    const laWallClock = new Date(
                        new Date(isoLike).toLocaleString("en-US", {
                            timeZone: "America/Los_Angeles",
                        }),
                    );
                    const diff = tempDate - laWallClock;
                    date = new Date(tempDate.getTime() + diff);
                } else {
                    date = new Date(dateString);
                }

                const formatter = new Intl.DateTimeFormat("en-CA", {
                    timeZone: userTimezone,
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                });

                return formatter.format(date);
            } catch (error) {
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
};
</script>
