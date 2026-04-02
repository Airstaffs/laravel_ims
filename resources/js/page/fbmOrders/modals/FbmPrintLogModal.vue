<template>
    <Dialog
        header="FBM Print Log"
        :visible="visible"
        :modal="true"
        :style="dialogStyle"
        :contentStyle="dialogContentStyle"
        @update:visible="onClose"
    >
        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
            <span class="p-input-icon-left flex-grow-1" style="min-width: 220px">
                <i class="pi pi-search" />
                <InputText
                    v-model="filters.q"
                    placeholder="Search Order ID / User / Notes / Error"
                    @keyup.enter="fetchLogs(1)"
                    class="w-100"
                />
            </span>

            <span style="min-width: 160px">
                <InputText
                    v-model="filters.user"
                    placeholder="User"
                    @keyup.enter="fetchLogs(1)"
                    class="w-100"
                />
            </span>

            <span style="min-width: 140px">
                <Select
                    :options="typeOptions"
                    optionLabel="label"
                    optionValue="value"
                    v-model="filters.type"
                    placeholder="All Types"
                    class="w-100"
                />
            </span>

            <span style="min-width: 180px">
                <Select
                    :options="actionOptions"
                    optionLabel="label"
                    optionValue="value"
                    v-model="filters.action"
                    placeholder="All Actions"
                    class="w-100"
                />
            </span>

            <span style="min-width: 140px">
                <Select
                    :options="statusOptions"
                    optionLabel="label"
                    optionValue="value"
                    v-model="filters.status"
                    placeholder="All Status"
                    class="w-100"
                />
            </span>

            <span style="min-width: 150px">
                <InputText
                    type="date"
                    v-model="filters.date_from"
                    class="w-100"
                />
            </span>

            <span style="min-width: 150px">
                <InputText
                    type="date"
                    v-model="filters.date_to"
                    class="w-100"
                />
            </span>

            <Button
                severity="secondary"
                outlined
                icon="pi pi-search"
                label="Search"
                @click="fetchLogs(1)"
                :disabled="loading"
            />

            <Button
                severity="secondary"
                outlined
                icon="pi pi-refresh"
                label="Reset"
                @click="resetFilters"
                :disabled="loading"
            />

            <div class="ms-auto d-flex align-items-center gap-2">
                <small class="text-muted" v-if="pagination.total">
                    Total: {{ pagination.total }} • Page {{ pagination.page }} / {{ totalPages }}
                </small>
            </div>
        </div>

        <DataTable
            :value="logs"
            :loading="loading"
            dataKey="id"
            class="p-datatable-sm desktop-view"
        >
            <Column field="created_at" header="Date">
                <template #body="{ data }">
                    {{ formatDateTime(data.created_at) }}
                </template>
            </Column>

            <Column field="user" header="User">
                <template #body="{ data }">
                    {{ data.user || "-" }}
                </template>
            </Column>

            <Column field="platform_order_id" header="Order ID">
                <template #body="{ data }">
                    <span class="wrap-anywhere">{{ data.platform_order_id || "-" }}</span>
                </template>
            </Column>

            <Column field="type" header="Type">
                <template #body="{ data }">
                    {{ data.type || "-" }}
                </template>
            </Column>

            <Column field="action" header="Action">
                <template #body="{ data }">
                    {{ data.action || "-" }}
                </template>
            </Column>

            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag
                        :value="data.status || 'N/A'"
                        :severity="data.status === 'success' ? 'success' : 'danger'"
                    />
                </template>
            </Column>

            <Column v-if="!isMobile" field="printer_ip" header="Printer IP">
                <template #body="{ data }">
                    {{ data.printer_ip || "-" }}
                </template>
            </Column>

            <Column v-if="!isMobile" field="copies" header="Copies" style="width: 90px">
                <template #body="{ data }">
                    {{ data.copies || 1 }}
                </template>
            </Column>

            <Column v-if="!isMobile" field="notes" header="Notes">
                <template #body="{ data }">
                    <span class="wrap-anywhere">{{ data.notes || "-" }}</span>
                </template>
            </Column>

            <Column v-if="!isMobile" field="error_message" header="Error">
                <template #body="{ data }">
                    <span class="text-danger wrap-anywhere">{{ data.error_message || "-" }}</span>
                </template>
            </Column>

            <Column v-if="!isMobile" field="pdf_path" header="PDF" style="width: 100px">
                <template #body="{ data }">
                    <a
                        v-if="data.pdf_path"
                        :href="normalizePdfPath(data.pdf_path)"
                        target="_blank"
                    >
                        View PDF
                    </a>
                    <span v-else>-</span>
                </template>
            </Column>

            <Column v-if="isMobile" header="Details">
                <template #body="{ data }">
                    <div class="mobile-details">
                        <div><strong>Printer IP:</strong> {{ data.printer_ip || "-" }}</div>
                        <div><strong>Copies:</strong> {{ data.copies || 1 }}</div>
                        <div><strong>Notes:</strong> {{ data.notes || "-" }}</div>
                        <div><strong>Error:</strong> {{ data.error_message || "-" }}</div>
                        <div>
                            <strong>PDF:</strong>
                            <a
                                v-if="data.pdf_path"
                                :href="normalizePdfPath(data.pdf_path)"
                                target="_blank"
                            >
                                View PDF
                            </a>
                            <span v-else>-</span>
                        </div>
                    </div>
                </template>
            </Column>
        </DataTable>

        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
            <Button
                severity="secondary"
                outlined
                label="Close"
                icon="pi pi-times"
                @click="onClose"
            />

            <div class="d-flex gap-2">
                <Button
                    severity="secondary"
                    outlined
                    icon="pi pi-angle-left"
                    label="Prev"
                    @click="fetchLogs(pagination.page - 1)"
                    :disabled="loading || pagination.page <= 1"
                />
                <Button
                    severity="secondary"
                    outlined
                    label="Next"
                    icon="pi pi-angle-right"
                    iconPos="right"
                    @click="fetchLogs(pagination.page + 1)"
                    :disabled="loading || pagination.page >= totalPages"
                />
            </div>
        </div>
    </Dialog>
</template>

<script>
import axios from "axios";

import Dialog from "primevue/dialog";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";

export default {
    name: "FbmPrintLogModal",
    components: {
        Dialog,
        DataTable,
        Column,
        Button,
        InputText,
        Select,
        Tag,
    },
    props: {
        visible: { type: Boolean, default: false },
    },
    data() {
        return {
            loading: false,
            logs: [],
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
            isMobile: window.innerWidth < 768,

            typeOptions: [
                { label: "All Types", value: "" },
                { label: "Invoice", value: "invoice" },
                { label: "Label", value: "label" },
            ],
            actionOptions: [
                { label: "All Actions", value: "" },
                { label: "PrintInvoice", value: "PrintInvoice" },
                { label: "ViewInvoice", value: "ViewInvoice" },
                { label: "PrintShipmentLabel", value: "PrintShipmentLabel" },
                { label: "ViewShipmentLabel", value: "ViewShipmentLabel" },
            ],
            statusOptions: [
                { label: "All Status", value: "" },
                { label: "Success", value: "success" },
                { label: "Failed", value: "failed" },
            ],
        };
    },
    computed: {
        totalPages() {
            return Math.max(
                1,
                Math.ceil((this.pagination.total || 0) / (this.pagination.rows || 20))
            );
        },
        dialogStyle() {
            return this.isMobile
                ? { width: "100vw", maxWidth: "100vw", height: "100vh" }
                : { width: "95vw", maxWidth: "95%" };
        },
        dialogContentStyle() {
            return this.isMobile
                ? { height: "calc(100vh - 120px)", overflow: "auto" }
                : {};
        },
    },
    watch: {
        visible(isOpen) {
            if (isOpen) {
                this.fetchLogs(1);
            }
        },
    },
    mounted() {
        this._onResize = () => {
            this.isMobile = window.innerWidth < 768;
        };
        window.addEventListener("resize", this._onResize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this._onResize);
    },
    methods: {
        onClose() {
            this.$emit("close");
        },

        async fetchLogs(page = 1) {
            if (page < 1) return;

            this.loading = true;

            try {
                const response = await axios.get("/fbm/print-logs", {
                    params: {
                        ...this.filters,
                        page,
                        rows: this.pagination.rows,
                    },
                });

                if (response.data?.success) {
                    this.logs = response.data.data || [];
                    this.pagination.page = response.data.pagination?.page || 1;
                    this.pagination.rows = response.data.pagination?.rows || 20;
                    this.pagination.total = response.data.pagination?.total || 0;
                } else {
                    this.logs = [];
                }
            } catch (error) {
                console.error("Failed to fetch FBM print logs", error);
                this.logs = [];
            } finally {
                this.loading = false;
            }
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
            this.fetchLogs(1);
        },

        formatDateTime(value) {
            if (!value) return "-";
            return String(value).replace("T", " ").replace("Z", "");
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

<style scoped>
.mobile-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 0.9rem;
    line-height: 1.25rem;
}

.wrap-anywhere {
    overflow-wrap: anywhere;
    word-break: break-word;
}

:deep(.p-datatable .p-datatable-thead > tr > th),
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 0.5rem;
}

@media (max-width: 768px) {
    :deep(.p-datatable-thead) {
        display: none !important;
    }

    :deep(.p-datatable-tbody > tr) {
        display: block;
        border-bottom: 1px solid #e5e7eb;
        padding: 0.75rem 0;
    }

    :deep(.p-datatable-tbody > tr > td) {
        display: block;
        width: 100% !important;
        border: none !important;
        padding: 0.25rem 0 !important;
    }
}
</style>