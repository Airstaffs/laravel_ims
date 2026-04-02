<template>
    <Dialog
        v-model:visible="localVisible"
        modal
        header="FBM Print Log"
        :style="{ width: '95%' }"
        :pt="{ root: { class: 'mobile-fullscreen-dialog' } }"
    >
        <div>
            <div class="mb-3 d-flex flex-wrap gap-2 align-items-end">
                <fieldset class="filter-field">
                    <label>Search</label>
                    <InputText
                        v-model="filters.q"
                        placeholder="Order ID, user, notes, error"
                        size="small"
                        @keyup.enter="fetchLogs"
                    />
                </fieldset>

                <fieldset class="filter-field">
                    <label>User</label>
                    <InputText
                        v-model="filters.user"
                        placeholder="User"
                        size="small"
                        @keyup.enter="fetchLogs"
                    />
                </fieldset>

                <fieldset class="filter-field">
                    <label>Type</label>
                    <Select
                        :options="typeOptions"
                        optionLabel="label"
                        optionValue="value"
                        v-model="filters.type"
                        placeholder="All"
                        size="small"
                    />
                </fieldset>

                <fieldset class="filter-field">
                    <label>Action</label>
                    <Select
                        :options="actionOptions"
                        optionLabel="label"
                        optionValue="value"
                        v-model="filters.action"
                        placeholder="All"
                        size="small"
                    />
                </fieldset>

                <fieldset class="filter-field">
                    <label>Status</label>
                    <Select
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        v-model="filters.status"
                        placeholder="All"
                        size="small"
                    />
                </fieldset>

                <fieldset class="filter-field">
                    <label>Date From</label>
                    <InputText type="date" v-model="filters.date_from" size="small" />
                </fieldset>

                <fieldset class="filter-field">
                    <label>Date To</label>
                    <InputText type="date" v-model="filters.date_to" size="small" />
                </fieldset>

                <div class="d-flex gap-2">
                    <Button label="Search" icon="pi pi-search" size="small" @click="searchLogs" />
                    <Button label="Reset" icon="pi pi-refresh" size="small" severity="secondary" outlined @click="resetFilters" />
                </div>
            </div>

            <XDataTable
                :value="logs"
                :columns="columns"
                :loading="loading"
                :paginator="false"
                scrollable
                scrollHeight="600px"
                tableClass="mt-3 desktop-view"
            >
                <template #createdAt="{ data }">
                    <span>{{ formatDateTime(data.created_at) }}</span>
                </template>

                <template #status="{ data }">
                    <Tag
                        :value="data.status"
                        :severity="data.status === 'success' ? 'success' : 'danger'"
                    />
                </template>

                <template #pdfPath="{ data }">
                    <a v-if="data.pdf_path" :href="normalizePdfPath(data.pdf_path)" target="_blank">
                        View PDF
                    </a>
                    <span v-else>N/A</span>
                </template>

                <template #errorMessage="{ data }">
                    <span class="text-danger">{{ data.error_message || 'N/A' }}</span>
                </template>
            </XDataTable>

            <div class="d-block d-md-none mt-3">
                <div class="card mb-3" v-for="log in logs" :key="log.id" :style="{ fontSize: '14px' }">
                    <div class="card-body">
                        <p><strong>Date:</strong> {{ formatDateTime(log.created_at) }}</p>
                        <p><strong>User:</strong> {{ log.user || 'N/A' }}</p>
                        <p><strong>Order ID:</strong> {{ log.platform_order_id || 'N/A' }}</p>
                        <p><strong>Type:</strong> {{ log.type || 'N/A' }}</p>
                        <p><strong>Action:</strong> {{ log.action || 'N/A' }}</p>
                        <p><strong>Status:</strong> {{ log.status || 'N/A' }}</p>
                        <p><strong>Printer IP:</strong> {{ log.printer_ip || 'N/A' }}</p>
                        <p><strong>Copies:</strong> {{ log.copies || 1 }}</p>
                        <p><strong>Notes:</strong> {{ log.notes || 'N/A' }}</p>
                        <p><strong>Error:</strong> {{ log.error_message || 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>Total: {{ pagination.total }}</div>

                <div class="d-flex gap-2 align-items-center">
                    <Button
                        icon="pi pi-angle-left"
                        size="small"
                        outlined
                        severity="secondary"
                        :disabled="pagination.page <= 1"
                        @click="changePage(pagination.page - 1)"
                    />
                    <span>Page {{ pagination.page }} / {{ totalPages }}</span>
                    <Button
                        icon="pi pi-angle-right"
                        size="small"
                        outlined
                        severity="secondary"
                        :disabled="pagination.page >= totalPages"
                        @click="changePage(pagination.page + 1)"
                    />
                </div>
            </div>
        </div>
    </Dialog>
</template>

<script>
import axios from "axios";
import { Button, Dialog, InputText, Select, Tag } from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";

const TABLE_COLUMNS = [
    { header: "Date", field: "created_at", slot: "createdAt", style: { minWidth: "12rem" } },
    { header: "User", field: "user", style: { minWidth: "8rem" } },
    { header: "Order ID", field: "platform_order_id", style: { minWidth: "12rem" } },
    { header: "Type", field: "type", style: { minWidth: "6rem" } },
    { header: "Action", field: "action", style: { minWidth: "10rem" } },
    { header: "Status", field: "status", slot: "status", style: { minWidth: "7rem" } },
    { header: "Printer IP", field: "printer_ip", style: { minWidth: "9rem" } },
    { header: "Copies", field: "copies", style: { minWidth: "5rem" } },
    { header: "Notes", field: "notes", style: { minWidth: "12rem" } },
    { header: "Error", field: "error_message", slot: "errorMessage", style: { minWidth: "12rem" } },
    { header: "PDF", field: "pdf_path", slot: "pdfPath", style: { minWidth: "7rem" } },
];

export default {
    name: "FbmPrintLogModal",
    components: {
        Dialog,
        Button,
        InputText,
        Select,
        Tag,
        XDataTable,
    },
    props: {
        visible: {
            type: Boolean,
            default: false,
        },
    },
    emits: ["close"],
    data() {
        return {
            localVisible: false,
            loading: false,
            logs: [],
            columns: TABLE_COLUMNS,
            filters: {
                q: "",
                user: "",
                type: "",
                action: "",
                status: "",
                date_from: "",
                date_to: "",
            },
            pagination: {
                page: 1,
                rows: 20,
                total: 0,
            },
            typeOptions: [
                { label: "All", value: "" },
                { label: "Invoice", value: "invoice" },
                { label: "Label", value: "label" },
            ],
            actionOptions: [
                { label: "All", value: "" },
                { label: "PrintInvoice", value: "PrintInvoice" },
                { label: "ViewInvoice", value: "ViewInvoice" },
                { label: "PrintShipmentLabel", value: "PrintShipmentLabel" },
                { label: "ViewShipmentLabel", value: "ViewShipmentLabel" },
            ],
            statusOptions: [
                { label: "All", value: "" },
                { label: "Success", value: "success" },
                { label: "Failed", value: "failed" },
            ],
        };
    },
    computed: {
        totalPages() {
            return Math.max(1, Math.ceil((this.pagination.total || 0) / this.pagination.rows));
        },
    },
    watch: {
        visible: {
            immediate: true,
            handler(val) {
                this.localVisible = val;
                if (val) {
                    this.fetchLogs();
                }
            },
        },
        localVisible(val) {
            if (!val) {
                this.$emit("close");
            }
        },
    },
    methods: {
        async fetchLogs() {
            this.loading = true;

            try {
                const response = await axios.get("/fbm/print-logs", {
                    params: {
                        ...this.filters,
                        page: this.pagination.page,
                        rows: this.pagination.rows,
                    },
                });

                if (response.data?.success) {
                    this.logs = response.data.data || [];
                    this.pagination.page = response.data.pagination?.page || 1;
                    this.pagination.rows = response.data.pagination?.rows || 20;
                    this.pagination.total = response.data.pagination?.total || 0;
                }
            } catch (error) {
                console.error("Failed to fetch FBM print logs", error);
            } finally {
                this.loading = false;
            }
        },

        searchLogs() {
            this.pagination.page = 1;
            this.fetchLogs();
        },

        resetFilters() {
            this.filters = {
                q: "",
                user: "",
                type: "",
                action: "",
                status: "",
                date_from: "",
                date_to: "",
            };
            this.pagination.page = 1;
            this.fetchLogs();
        },

        changePage(page) {
            this.pagination.page = page;
            this.fetchLogs();
        },

        formatDateTime(value) {
            if (!value) return "N/A";
            return new Date(value).toLocaleString();
        },

        normalizePdfPath(path) {
            if (!path) return "#";

            if (path.startsWith("http")) return path;
            if (path.startsWith("/")) return path;

            const publicIndex = path.indexOf("public/");
            if (publicIndex !== -1) {
                return "/" + path.substring(publicIndex + 7).replace(/\\/g, "/");
            }

            return "/" + path.replace(/\\/g, "/");
        },
    },
};
</script>