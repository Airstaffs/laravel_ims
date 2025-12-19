<template>
    <div class="history-tracking-container p-4">
        <!-- Header -->
        <Card class="mb-4">
            <template #title>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="display-6 fw-bold mb-2">History Tracking</h1>
                        <p class="text-muted mb-0">
                            Monitor and track all system activities
                        </p>
                    </div>
                    <Button
                        icon="pi pi-refresh"
                        label="Refresh"
                        @click="loadHistory"
                        :loading="loading"
                        severity="secondary"
                        size="small"
                    />
                </div>
            </template>
        </Card>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <Card class="stat-card stat-primary h-100">
                    <template #content>
                        <div class="d-flex align-items-center gap-3">
                            <i
                                class="pi pi-chart-line fs-1 text-primary opacity-75"
                            ></i>
                            <div>
                                <div class="text-muted small">
                                    Total Actions
                                </div>
                                <div class="fs-2 fw-bold">
                                    {{ stats.total_actions || 0 }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
            <div class="col-md-3">
                <Card class="stat-card stat-success h-100">
                    <template #content>
                        <div class="d-flex align-items-center gap-3">
                            <i
                                class="pi pi-calendar fs-1 text-success opacity-75"
                            ></i>
                            <div>
                                <div class="text-muted small">This Week</div>
                                <div class="fs-2 fw-bold">
                                    {{ stats.week_actions || 0 }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
            <div class="col-md-3">
                <Card class="stat-card stat-info h-100">
                    <template #content>
                        <div class="d-flex align-items-center gap-3">
                            <i
                                class="pi pi-clock fs-1 text-info opacity-75"
                            ></i>
                            <div>
                                <div class="text-muted small">Today</div>
                                <div class="fs-2 fw-bold">
                                    {{ stats.today_actions || 0 }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
            <div class="col-md-3">
                <Card class="stat-card stat-warning h-100">
                    <template #content>
                        <div class="d-flex align-items-center gap-3">
                            <i
                                class="pi pi-users fs-1 text-warning opacity-75"
                            ></i>
                            <div>
                                <div class="text-muted small">Active Users</div>
                                <div class="fs-2 fw-bold">
                                    {{ stats.active_users || 0 }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Filters -->
        <Card class="mb-4">
            <template #title>
                <div class="d-flex align-items-center gap-2">
                    <i class="pi pi-filter"></i>
                    <span>Filters</span>
                </div>
            </template>
            <template #content>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small"
                            >Module</label
                        >
                        <Dropdown
                            v-model="filters.module"
                            :options="moduleOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="All Modules"
                            class="w-100"
                            showClear
                        />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small"
                            >Action</label
                        >
                        <Dropdown
                            v-model="filters.action"
                            :options="actionOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="All Actions"
                            class="w-100"
                            showClear
                        />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small"
                            >Employee</label
                        >
                        <InputText
                            v-model="filters.employee_name"
                            placeholder="Employee name..."
                            class="w-100"
                        />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small"
                            >Search</label
                        >
                        <InputText
                            v-model="filters.search"
                            placeholder="Search..."
                            class="w-100"
                        />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small"
                            >Date From</label
                        >
                        <Calendar
                            v-model="filters.date_from"
                            dateFormat="yy-mm-dd"
                            placeholder="Select date"
                            class="w-100"
                            showIcon
                        />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small"
                            >Date To</label
                        >
                        <Calendar
                            v-model="filters.date_to"
                            dateFormat="yy-mm-dd"
                            placeholder="Select date"
                            class="w-100"
                            showIcon
                        />
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <Button
                            label="Apply Filters"
                            icon="pi pi-search"
                            @click="loadHistory"
                            class="w-100"
                            :loading="loading"
                        />
                    </div>
                </div>
            </template>
        </Card>

        <!-- Test Actions -->
        <Card class="mb-4">
            <template #title>
                <div class="d-flex align-items-center gap-2">
                    <i class="pi pi-flask"></i>
                    <span>Test Actions</span>
                </div>
            </template>
            <template #content>
                <div class="d-flex gap-2 flex-wrap">
                    <Button
                        label="Test Create Order"
                        icon="pi pi-plus-circle"
                        @click="testCreateOrder"
                        severity="success"
                        :loading="testLoading"
                        size="small"
                    />
                    <Button
                        label="Test Update Order"
                        icon="pi pi-pencil"
                        @click="testUpdateOrder"
                        severity="warning"
                        :loading="testLoading"
                        size="small"
                    />
                    <Button
                        label="Test Delete Order"
                        icon="pi pi-trash"
                        @click="testDeleteOrder"
                        severity="danger"
                        :loading="testLoading"
                        size="small"
                    />
                </div>
            </template>
        </Card>

        <!-- History Table -->
        <Card>
            <template #title>
                <div class="d-flex align-items-center gap-2">
                    <i class="pi pi-history"></i>
                    <span>History Records</span>
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="historyRecords"
                    :loading="loading"
                    :paginator="true"
                    :rows="20"
                    :totalRecords="totalRecords"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Showing {first} to {last} of {totalRecords} entries"
                    :rowsPerPageOptions="[10, 20, 50]"
                    stripedRows
                    responsiveLayout="scroll"
                    class="history-table"
                    @page="onPage"
                >
                    <Column
                        field="historyID"
                        header="ID"
                        :sortable="true"
                        style="min-width: 80px"
                    >
                        <template #body="slotProps">
                            <Tag
                                :value="
                                    slotProps.data.historyID ||
                                    slotProps.data.id
                                "
                                severity="secondary"
                            />
                        </template>
                    </Column>

                    <Column
                        field="editDate"
                        header="Date/Time"
                        :sortable="true"
                        style="min-width: 280px"
                    >
                        <template #body="slotProps">
                            <div class="d-flex flex-column">
                                <!-- Primary Time (User's Timezone) -->
                                <div
                                    class="d-flex align-items-center gap-2 mb-1"
                                >
                                    <!-- <i class="pi pi-clock text-primary"></i> -->
                                    <span class="fw-semibold text-primary">
                                        {{
                                            formatUserTime(
                                                slotProps.data.editDate
                                            )
                                        }}
                                    </span>
                                </div>

                                <!-- Secondary Time (Other Timezone) -->
                                <div
                                    class="d-flex align-items-center gap-2 ms-4"
                                >
                                    <!-- <i class="pi pi-globe text-muted small"></i> -->
                                    <span class="text-muted small">
                                        {{
                                            formatOtherTime(
                                                slotProps.data.editDate
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column
                        field="employeeName"
                        header="Employee"
                        :sortable="true"
                        style="min-width: 150px"
                    >
                        <template #body="slotProps">
                            <div class="d-flex align-items-center gap-2">
                                <Avatar
                                    :label="
                                        getInitials(slotProps.data.employeeName)
                                    "
                                    shape="circle"
                                    size="small"
                                    style="
                                        background-color: #2196f3;
                                        color: white;
                                    "
                                />
                                <span class="fw-semibold">{{
                                    slotProps.data.employeeName || "N/A"
                                }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column
                        field="Module"
                        header="Module"
                        :sortable="true"
                        style="min-width: 120px"
                    >
                        <template #body="slotProps">
                            <Tag
                                :value="cleanModuleName(slotProps.data.Module)"
                                severity="info"
                            />
                        </template>
                    </Column>

                    <Column
                        field="Action"
                        header="Action"
                        :sortable="true"
                        style="min-width: 150px"
                    >
                        <template #body="slotProps">
                            <Tag
                                :value="slotProps.data.Action || 'N/A'"
                                :severity="
                                    getActionSeverity(slotProps.data.Action)
                                "
                            />
                        </template>
                    </Column>

                    <Column
                        field="oldLocation"
                        header="Before"
                        style="min-width: 200px"
                    >
                        <template #body="slotProps">
                            <div class="d-flex align-items-start gap-2">
                                <i
                                    class="pi pi-arrow-left text-muted small mt-1"
                                ></i>
                                <span class="text-muted">{{
                                    slotProps.data.oldLocation ||
                                    slotProps.data.oldlocation ||
                                    "-"
                                }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column
                        field="newLocation"
                        header="After"
                        style="min-width: 200px"
                    >
                        <template #body="slotProps">
                            <div class="d-flex align-items-start gap-2">
                                <i
                                    class="pi pi-arrow-right text-primary small mt-1"
                                ></i>
                                <span class="text-muted">{{
                                    slotProps.data.newLocation ||
                                    slotProps.data.newlocation ||
                                    "-"
                                }}</span>
                            </div>
                        </template>
                    </Column>

                    <template #empty>
                        <div class="text-center py-5">
                            <i
                                class="pi pi-inbox display-1 text-muted mb-3"
                            ></i>
                            <p class="text-muted fs-5">No records found</p>
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <!-- Statistics Charts -->
        <div class="row g-4 mt-2">
            <div class="col-md-6">
                <Card>
                    <template #title>Actions by Module</template>
                    <template #content>
                        <div
                            v-if="stats.by_module && stats.by_module.length > 0"
                        >
                            <div
                                v-for="item in stats.by_module"
                                :key="item.Module"
                                class="d-flex justify-content-between align-items-center p-3 border-bottom"
                            >
                                <span class="fw-semibold">{{
                                    item.Module
                                }}</span>
                                <Tag :value="item.count" severity="info" />
                            </div>
                        </div>
                        <div v-else class="text-center text-muted py-5">
                            No data available
                        </div>
                    </template>
                </Card>
            </div>

            <div class="col-md-6">
                <Card>
                    <template #title>Actions by Type</template>
                    <template #content>
                        <div
                            v-if="stats.by_action && stats.by_action.length > 0"
                        >
                            <div
                                v-for="item in stats.by_action"
                                :key="item.Action"
                                class="d-flex justify-content-between align-items-center p-3 border-bottom"
                            >
                                <span class="fw-semibold">{{
                                    item.Action
                                }}</span>
                                <Tag
                                    :value="item.count"
                                    :severity="getActionSeverity(item.Action)"
                                />
                            </div>
                        </div>
                        <div v-else class="text-center text-muted py-5">
                            No data available
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import Card from "primevue/card";
import Button from "primevue/button";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Dropdown from "primevue/dropdown";
import InputText from "primevue/inputtext";
import Calendar from "primevue/calendar";
import Tag from "primevue/tag";
import Avatar from "primevue/avatar";

// State
const loading = ref(false);
const testLoading = ref(false);
const historyRecords = ref([]);
const totalRecords = ref(0);
const currentPage = ref(1);
const userTimezone = ref("America/Los_Angeles");
const isPhilippineUser = ref(false);

const stats = ref({
    total_actions: 0,
    week_actions: 0,
    today_actions: 0,
    active_users: 0,
    by_module: [],
    by_action: [],
});

const filters = ref({
    module: null,
    action: null,
    employee_name: "",
    search: "",
    date_from: null,
    date_to: null,
});

// Options
const moduleOptions = [
    { label: "All Modules", value: null },
    { label: "Orders", value: "Orders" },
    { label: "Products", value: "Products" },
    { label: "Inventory", value: "Inventory" },
    { label: "Users", value: "Users" },
    { label: "Shipping", value: "Shipping" },
    { label: "Labeling", value: "Labeling" },
    { label: "Validation", value: "Validation" },
    { label: "Stockroom", value: "Stockroom" },
    { label: "FBM Orders", value: "FBM Orders" },
];

const actionOptions = [
    { label: "All Actions", value: null },
    { label: "Create", value: "Create" },
    { label: "Update", value: "Update" },
    { label: "Delete", value: "Delete" },
    { label: "Status Change", value: "Status Change" },
    { label: "Location Change", value: "Location Change" },
];

// Get user's timezone from backend or browser
const getUserTimezone = async () => {
    // Use browser timezone directly
    userTimezone.value = Intl.DateTimeFormat().resolvedOptions().timeZone;

    // ✅ FIXED: Check for both Asia/Manila AND Asia/Shanghai (both are UTC+8)
    isPhilippineUser.value =
        userTimezone.value === "Asia/Manila" ||
        userTimezone.value === "Asia/Shanghai" ||
        userTimezone.value === "Asia/Hong_Kong" ||
        userTimezone.value === "Asia/Taipei" ||
        userTimezone.value === "Asia/Singapore" ||
        userTimezone.value === "Asia/Kuala_Lumpur";

    console.log("🌍 Detected Timezone:", userTimezone.value);
    console.log("🇵🇭 Is Philippine User:", isPhilippineUser.value);
};

// Helper functions - MUST be defined before they're used
const formatUserTime = (dateString) => {
    if (!dateString) return "N/A";
    try {
        const date = new Date(dateString.replace(" ", "T") + "Z");

        const options = {
            timeZone: userTimezone.value,
            year: "numeric",
            month: "short",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
            hour12: true,
        };
        const formatted = date.toLocaleString("en-US", options);

        // Get location name and flag
        let locationName =
            userTimezone.value.split("/")[1]?.replace("_", " ") || "Local";
        let flag = "🕐";

        if (userTimezone.value.includes("Manila")) {
            flag = "🇵🇭";
            locationName = "Manila";
        } else if (userTimezone.value.includes("Shanghai")) {
            flag = "🇨🇳";
            locationName = "Shanghai";
        } else if (userTimezone.value.includes("Singapore")) {
            flag = "🇸🇬";
            locationName = "Singapore";
        } else if (userTimezone.value.includes("Los_Angeles")) {
            flag = "🇺🇸";
            locationName = "Los Angeles";
        } else if (userTimezone.value.includes("Hong_Kong")) {
            flag = "🇭🇰";
            locationName = "Hong Kong";
        }

        // Calculate GMT offset
        const offset = -date.getTimezoneOffset();
        const offsetHours = Math.floor(Math.abs(offset) / 60);
        const offsetSign = offset >= 0 ? "+" : "-";
        const gmtOffset = `GMT${offsetSign}${offsetHours}`;

        // return `${flag} ${locationName}: ${formatted} (${gmtOffset})`;
        return `${formatted} (${gmtOffset})`;
    } catch (error) {
        console.error("Error formatting user time:", error);
        return dateString;
    }
};

const formatOtherTime = (dateString) => {
    if (!dateString) return "N/A";
    try {
        const date = new Date(dateString.replace(" ", "T") + "Z");

        // If user is in LA, show Manila time instead to avoid duplicate
        if (userTimezone.value.includes("Los_Angeles")) {
            const otherTimezone = "Asia/Manila";
            const label = "Manila";
            const flag = "🇵🇭";

            const options = {
                timeZone: otherTimezone,
                year: "numeric",
                month: "short",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
                hour12: true,
            };
            const formatted = date.toLocaleString("en-US", options);

            return `${formatted} (GMT+8)`;
        }

        // For all other timezones, show LA time
        const otherTimezone = "America/Los_Angeles";
        const label = "Los Angeles";
        const flag = "🇺🇸";

        const options = {
            timeZone: otherTimezone,
            year: "numeric",
            month: "short",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
            hour12: true,
        };
        const formatted = date.toLocaleString("en-US", options);

        // ✅ FIXED: Simple DST check for LA timezone
        // DST runs from second Sunday in March to first Sunday in November
        const month = date.getUTCMonth(); // Use UTC month
        const isDST = month >= 2 && month <= 10; // March (2) through November (10)
        const gmtOffset = isDST ? "GMT-7" : "GMT-8";

        return `${formatted} (${gmtOffset})`;
    } catch (error) {
        console.error("Error formatting other time:", error);
        return dateString;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return "N/A";
    const date = new Date(dateString);
    return date.toLocaleString();
};

const formatDateParam = (date) => {
    if (!date) return null;
    const d = new Date(date);
    return d.toISOString().split("T")[0];
};

const getActionSeverity = (action) => {
    const severityMap = {
        Create: "success",
        Update: "warning",
        Delete: "danger",
        "Status Change": "info",
        "Location Change": "secondary",
    };
    return severityMap[action] || "secondary";
};

const getInitials = (name) => {
    if (!name) return "?";
    return name
        .split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase()
        .substring(0, 2);
};

const cleanModuleName = (moduleName) => {
    if (!moduleName) return "N/A";
    // Remove "Module" word (case insensitive)
    return moduleName.replace(/\s*module\s*/gi, "").trim();
};

// Methods
const loadHistory = async () => {
    loading.value = true;
    try {
        const params = {
            page: currentPage.value,
            per_page: 20,
            module: filters.value.module,
            action: filters.value.action,
            employee_name: filters.value.employee_name,
            search: filters.value.search,
            date_from: filters.value.date_from
                ? formatDateParam(filters.value.date_from)
                : null,
            date_to: filters.value.date_to
                ? formatDateParam(filters.value.date_to)
                : null,
        };

        const response = await window.axios.get("/api/history", { params });
        historyRecords.value = response.data.data || [];
        totalRecords.value = response.data.total || 0;
    } catch (error) {
        console.error("Error loading history:", error);
        window.Swal.fire({
            icon: "error",
            title: "Error",
            text: "Failed to load history records",
            timer: 3000,
            showConfirmButton: false,
        });
    } finally {
        loading.value = false;
    }
};

const loadStats = async () => {
    try {
        const response = await window.axios.get("/api/history/stats");
        stats.value = response.data;
    } catch (error) {
        console.error("Error loading stats:", error);
    }
};

const testCreateOrder = async () => {
    testLoading.value = true;
    try {
        const maxIdResponse = await window.axios.get(
            "/api/orders/next-product-id"
        );
        const nextProductId = maxIdResponse.data.next_id;
        const timestamp = Date.now();

        const testData = {
            ProductID: nextProductId,
            itemnumber: "TEST-" + timestamp,
            ProductTitle: "Test Product " + timestamp,
            validation: "pending",
        };

        await window.axios.post("/api/orders/products", testData);

        window.Swal.fire({
            icon: "success",
            title: "Success!",
            text: `Test order created! ProductID: ${nextProductId}`,
            timer: 3000,
            showConfirmButton: false,
        });

        loadHistory();
        loadStats();
    } catch (error) {
        console.error("Error creating test order:", error);
        window.Swal.fire({
            icon: "error",
            title: "Error",
            text:
                error.response?.data?.message || "Failed to create test order",
            confirmButtonText: "OK",
        });
    } finally {
        testLoading.value = false;
    }
};

const testUpdateOrder = async () => {
    testLoading.value = true;
    try {
        const productResponse = await window.axios.get("/api/orders/products", {
            params: { search: "TEST-", per_page: 20 },
        });

        if (
            !productResponse.data.data ||
            productResponse.data.data.length === 0
        ) {
            const retryResponse = await window.axios.get(
                "/api/orders/products",
                {
                    params: { search: "TEST", per_page: 20 },
                }
            );

            if (
                !retryResponse.data.data ||
                retryResponse.data.data.length === 0
            ) {
                window.Swal.fire({
                    icon: "warning",
                    title: "No Test Orders",
                    text: "Please create a test order first before updating.",
                    confirmButtonText: "OK",
                });
                return;
            }

            const product = retryResponse.data.data[0];
            const updateData = {
                ProductID: product.ProductID,
                itemnumber: product.itemnumber,
                ProductTitle:
                    (product.ProductTitle || "Test Product") +
                    " [UPDATED " +
                    Date.now() +
                    "]",
                validation: "validated",
            };

            await window.axios.post("/api/orders/products", updateData);
            window.Swal.fire({
                icon: "success",
                title: "Success!",
                text: `Order ${product.itemnumber} updated successfully!`,
                timer: 3000,
                showConfirmButton: false,
            });

            loadHistory();
            loadStats();
            return;
        }

        const product = productResponse.data.data[0];
        const updateData = {
            ProductID: product.ProductID,
            itemnumber: product.itemnumber,
            ProductTitle:
                (product.ProductTitle || "Test Product") +
                " [UPDATED " +
                Date.now() +
                "]",
            validation: "validated",
        };

        await window.axios.post("/api/orders/products", updateData);
        window.Swal.fire({
            icon: "success",
            title: "Success!",
            text: `Order ${product.itemnumber} updated successfully!`,
            timer: 3000,
            showConfirmButton: false,
        });

        loadHistory();
        loadStats();
    } catch (error) {
        console.error("Error updating test order:", error);
        window.Swal.fire({
            icon: "error",
            title: "Error",
            text:
                error.response?.data?.message || "Failed to update test order",
            confirmButtonText: "OK",
        });
    } finally {
        testLoading.value = false;
    }
};

const testDeleteOrder = async () => {
    testLoading.value = true;
    try {
        const productResponse = await window.axios.get("/api/orders/products", {
            params: { search: "TEST-", per_page: 20 },
        });

        if (
            !productResponse.data.data ||
            productResponse.data.data.length === 0
        ) {
            const retryResponse = await window.axios.get(
                "/api/orders/products",
                {
                    params: { search: "TEST", per_page: 20 },
                }
            );

            if (
                !retryResponse.data.data ||
                retryResponse.data.data.length === 0
            ) {
                window.Swal.fire({
                    icon: "warning",
                    title: "No Test Orders",
                    text: "Please create a test order first.",
                    confirmButtonText: "OK",
                });
                return;
            }

            const product = retryResponse.data.data[0];
            const result = await window.Swal.fire({
                title: "Delete Order?",
                text: `Are you sure you want to delete ${product.itemnumber}?`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, delete it!",
                cancelButtonText: "Cancel",
            });

            if (!result.isConfirmed) {
                testLoading.value = false;
                return;
            }

            await window.axios.delete(`/api/orders/${product.ProductID}`);
            window.Swal.fire({
                icon: "success",
                title: "Deleted!",
                text: `Order ${product.itemnumber} deleted successfully!`,
                timer: 3000,
                showConfirmButton: false,
            });

            loadHistory();
            loadStats();
            return;
        }

        const product = productResponse.data.data[0];
        const result = await window.Swal.fire({
            title: "Delete Order?",
            text: `Are you sure you want to delete ${product.itemnumber}?`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, delete it!",
            cancelButtonText: "Cancel",
        });

        if (!result.isConfirmed) {
            testLoading.value = false;
            return;
        }

        await window.axios.delete(`/api/orders/${product.ProductID}`);
        window.Swal.fire({
            icon: "success",
            title: "Deleted!",
            text: `Order ${product.itemnumber} deleted successfully!`,
            timer: 3000,
            showConfirmButton: false,
        });

        loadHistory();
        loadStats();
    } catch (error) {
        console.error("Error deleting test order:", error);
        window.Swal.fire({
            icon: "error",
            title: "Error",
            text:
                error.response?.data?.message || "Failed to delete test order",
            confirmButtonText: "OK",
        });
    } finally {
        testLoading.value = false;
    }
};

const onPage = (event) => {
    currentPage.value = event.page + 1;
    loadHistory();
};

// Lifecycle
onMounted(async () => {
    await getUserTimezone();
    loadHistory();
    loadStats();
});
</script>

<style scoped src="./history.css"></style>
