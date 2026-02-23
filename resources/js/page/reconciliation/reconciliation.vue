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
                    {{ convertToLocalDate(data.datedelivered) }}
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
import { Button, ScrollTop, Paginator } from "primevue";
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
        Paginator,
    },

    data() {
        return {
            inventory: [],
            loading: true,

            currentPage: 1,
            totalRecords: 1,
            perPage: 10,
            first: 0,

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
                },
            ],

            currentTimezone: "UTC",
            timezoneLabel: "Loading...",
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
                        this.selectedItem[key] !== "NULL",
                )
                .map((key) => this.selectedItem[key]);
        },

        // ✅ ADD THESE COMPUTED PROPERTIES FOR DATE CONVERSION
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
        async fetchInventory() {
            this.loading = true;

            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/reconciliation/products`,
                    {
                        params: {
                            page: this.currentPage,
                            per_page: this.perPage,
                        },
                    },
                );

                this.inventory = response.data.data;
                this.totalRecords = response.data.total;
            } catch (error) {
                console.error("Error loading reconciliation data:", error);
            } finally {
                this.loading = false;
            }
        },

        onPageChange(event) {
            this.first = event.first;
            this.currentPage = event.page + 1; // convert to 1-based
            this.perPage = event.rows;
            this.fetchInventory();
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
