<template>
    <div class="vue-container supplies-components-module">
        <!-- Header Section -->
        <div
            class="d-flex align-items-center justify-content-between flex-wrap mb-4"
        >
            <TitlePage
                title="Supplies & Components Module"
                subtitle="Track all supplies, components, and office equipment in inventory."
            />
        </div>

        <!-- Statistics Cards -->
        <AnimateDiv :delay="100" class="stats-container px-4">
            <div class="stat-card bg-primary-light">
                <div class="stat-icon bg-primary">
                    <i class="pi pi-box text-white"></i>
                </div>
                <div>
                    <p class="mb-0">Total Items</p>
                    <h5 class="mb-0">{{ stats.total || 0 }}</h5>
                </div>
            </div>
            <div class="stat-card bg-info-light">
                <div class="stat-icon bg-info">
                    <i class="pi pi-wrench text-white"></i>
                </div>
                <div>
                    <p class="mb-0">Components</p>
                    <h5 class="mb-0">{{ stats.components || 0 }}</h5>
                </div>
            </div>
            <div class="stat-card bg-success-light">
                <div class="stat-icon bg-success">
                    <i class="pi pi-shopping-bag text-white"></i>
                </div>
                <div>
                    <p class="mb-0">Supplies</p>
                    <h5 class="mb-0">{{ stats.supplies || 0 }}</h5>
                </div>
            </div>
            <div class="stat-card bg-warning-light">
                <div class="stat-icon bg-warning">
                    <i class="pi pi-desktop text-white"></i>
                </div>
                <div>
                    <p class="mb-0">Office Equipment</p>
                    <h5 class="mb-0">{{ stats.office_equipment || 0 }}</h5>
                </div>
            </div>
        </AnimateDiv>

        <!-- Filter -->
        <AnimateDiv :delay="200" class="px-4">
            <div class="search-container">
                <fieldset class="d-flex align-items-center gap-3">
                    <label>Category</label>
                    <Select
                        :options="categoryOptions"
                        v-model="selectedCategory"
                        optionLabel="label"
                        optionValue="value"
                        size="small"
                        class="select-form"
                        @change="changeCategory"
                        placeholder="Select category"
                    />
                </fieldset>
            </div>

            <!-- Desktop Table -->
            <XDataTable
                :value="items"
                :loading="loading"
                :columns="columns"
                :pagination="false"
                tableClass="desktop-view"
                dataKey="product_id"
                scrollable
                scrollHeight="600px"
                :key="'sc-table'"
            >
                <template #gallery="{ data }">
                    <div
                        class="d-flex justify-content-center align-items-center"
                    >
                        <!-- Captured images (priority) -->
                        <div
                            v-if="hasCapturedImages(data)"
                            class="gallery-thumbnail position-relative"
                            @click="openImageModal(data)"
                            style="cursor: pointer"
                        >
                            <img
                                :src="getFirstImage(data)"
                                :alt="data.product_title"
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
                        <!-- Fallback to img1..img5 -->
                        <TableGallery
                            v-else
                            :data="data"
                            :openImageModal="openImageModal"
                            :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages"
                        />
                    </div>
                </template>

                <template #productName="{ data }">
                    <div class="d-flex flex-column gap-1">
                        <small class="text-muted"
                            >RT# {{ data.rt_counter || "N/A" }}</small
                        >
                        <span class="fw-bold">{{
                            data.product_title || "N/A"
                        }}</span>
                    </div>
                </template>

                <template #category="{ data }">
                    <span>{{ data.category || "N/A" }}</span>
                </template>

                <template #quantity="{ data }">
                    <span>{{ data.quantity || 1 }}</span>
                </template>

                <template #orderDate="{ data }">
                    <span>{{ convertToLocalDate(data.order_date) }}</span>
                </template>

                <template #deliveredDate="{ data }">
                    <span>{{ convertToLocalDate(data.delivered_date) }}</span>
                </template>

                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start">
                        <Button
                            label="Move to Labeling"
                            icon="pi pi-tags"
                            size="small"
                            severity="contrast"
                            variant="text"
                            class="text-warning"
                            :loading="moveLabelingLoading"
                            :disabled="moveLabelingLoading"
                            @click="moveToLabeling(data)"
                        />
                    </div>
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <div class="mobile-view px-3">
            <div v-if="loading" class="loading-spinner-mobile">
                <i class="pi pi-spin pi-spinner"></i> Loading items...
            </div>
            <div v-else-if="items.length === 0" class="no-data-mobile">
                No items found
            </div>
            <div v-else class="mobile-cards">
                <div
                    v-for="item in items"
                    :key="item.product_id"
                    class="mobile-card"
                >
                    <div class="mobile-card-header">
                        <!-- Mobile image -->
                        <div class="mobile-product-image clickable">
                            <div
                                v-if="hasCapturedImages(item)"
                                class="gallery-thumbnail position-relative"
                                @click="openImageModal(item)"
                                style="cursor: pointer"
                            >
                                <img
                                    :src="getFirstImage(item)"
                                    :alt="item.product_title"
                                    class="product-thumbnail clickable-image"
                                    @error="handleImageError"
                                />
                                <div
                                    class="image-count-badge"
                                    v-if="countAllImages(item) > 1"
                                >
                                    +{{ countAllImages(item) - 1 }}
                                </div>
                            </div>
                            <div
                                v-else
                                @click="openImageModal(item)"
                                style="cursor: pointer"
                            >
                                <img
                                    :src="
                                        item.img1
                                            ? '/images/thumbnails/' + item.img1
                                            : defaultImage
                                    "
                                    :alt="item.product_title"
                                    class="product-thumbnail clickable-image"
                                    @error="handleImageError"
                                />
                                <div
                                    class="image-count-badge"
                                    v-if="countAdditionalImages(item) > 0"
                                >
                                    +{{ countAdditionalImages(item) }}
                                </div>
                            </div>
                        </div>

                        <div class="mobile-product-info">
                            <h6 class="mobile-product-name">
                                <span style="font-size: 1rem"
                                    >RT# : {{ item.rt_counter || "N/A" }}</span
                                >
                                <span>{{ item.product_title || "N/A" }}</span>
                            </h6>
                        </div>
                    </div>

                    <Divider />

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Category:</span>
                            <span class="mobile-detal-value">{{
                                item.category || "N/A"
                            }}</span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Quantity:</span>
                            <span class="mobile-detal-value">{{
                                item.quantity || 1
                            }}</span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Order Date:</span>
                            <span class="mobile-detal-value">{{
                                formatDate(item.order_date)
                            }}</span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Delivered Date:</span
                            >
                            <span class="mobile-detal-value">{{
                                formatDate(item.delivered_date)
                            }}</span>
                        </div>
                    </div>

                    <Divider />

                    <div class="d-flex flex-nowrap overflow-auto gap-2 pb-3">
                        <div class="flex-shrink-0">
                            <Button
                                label="Move to Labeling"
                                icon="pi pi-tags"
                                size="small"
                                severity="warn"
                                :loading="moveLabelingLoading"
                                :disabled="moveLabelingLoading"
                                @click="moveToLabeling(item)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
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
        <ViewImageGalleryModal
            :showImageModal="showImageModal"
            :closeImageModal="closeImageModal"
            :ProductTitle="modalProductTitle"
            :regularImages="regularImages"
            :capturedImages="capturedImages"
            :handleImageError="handleImageError"
        />

        <ScrollTop />
    </div>
</template>

<script>
import { Badge, Button, Divider, ScrollTop, Select, Paginator } from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import suppliesComponentsModule from "./suppliesComponents.js";
import { ROWS_PER_PAGE } from "../../constant.js";

const TABLE_COLUMNS = [
    {
        header: "Gallery",
        slot: "gallery",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        header: "Product Name",
        slot: "productName",
        bodyStyle: "font-size:14px",
        style: { minWidth: "22rem" },
    },
    {
        header: "Category",
        slot: "category",
        bodyStyle: "font-size:14px",
        style: { minWidth: "10rem" },
    },
    {
        header: "Quantity",
        slot: "quantity",
        bodyStyle: "font-size:14px",
        style: { minWidth: "8rem" },
    },
    {
        header: "Order Date",
        slot: "orderDate",
        bodyStyle: "font-size:14px",
        style: { minWidth: "12rem" },
    },
    {
        header: "Delivered Date",
        slot: "deliveredDate",
        bodyStyle: "font-size:14px",
        style: { minWidth: "12rem" },
    },
];

export default {
    mixins: [suppliesComponentsModule],
    components: {
        XDataTable,
        TableGallery,
        ViewImageGalleryModal,
        Button,
        Select,
        Badge,
        Divider,
        ScrollTop,
        TitlePage,
        AnimateDiv,
        Paginator,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            rowsPerPageOptions: ROWS_PER_PAGE,
            categoryOptions: [
                { label: "All Categories", value: "" },
                { label: "Components", value: "Components" },
                { label: "Supplies", value: "Supplies" },
                { label: "Office Equipment", value: "Office Equipment" },
            ],

            currentTimezone: "UTC",
            timezoneLabel: "Loading...",
        };
    },
    computed: {
        // ✅ ADD THESE COMPUTED PROPERTIES FOR DATE CONVERSION
        localOrderDate() {
            return this.convertToLocalDate(this.item.order_date);
        },
        localDeliveredDate: {
            get() {
                return this.convertToLocalDate(this.item.delivered_date);
            },
            set(value) {
                this.item.delivered_date = this.convertFromLocalDate(value);
            },
        },
    },
    methods: {
        getCategorySeverity(category) {
            switch (category) {
                case "Components":
                    return "info";
                case "Supplies":
                    return "success";
                case "Office Equipment":
                    return "warning";
                default:
                    return "secondary";
            }
        },

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
    },
};
</script>

<style scoped>
@import "./suppliesComponents.css";
</style>
