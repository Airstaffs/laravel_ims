<template>
    <div class="log-wrapper">
        <!-- Header -->
        <div class="log-header">
            <i class="fas fa-clipboard-list"></i>
            <span>Item Log Module — Search & Print System</span>
        </div>

        <!-- Search & Filter -->
        <div class="search-card">
            <div class="section-label-row">
                <span class="section-badge badge-blue">SEARCH & FILTER</span>
                <span class="section-title">Find Items in Stockroom</span>
            </div>

            <div class="search-grid">
                <div class="field-wrap">
                    <label>Serial Number</label>
                    <InputText
                        v-model="filters.serial"
                        placeholder="Search by serial..."
                    />
                </div>
                <div class="field-wrap">
                    <label>ASIN</label>
                    <InputText
                        v-model="filters.asin"
                        placeholder="Search by ASIN..."
                    />
                </div>
                <div class="field-wrap">
                    <label>Start Date</label>
                    <Calendar
                        v-model="filters.from"
                        dateFormat="mm/dd/yy"
                        placeholder="mm/dd/yyyy"
                        :showIcon="true"
                    />
                </div>
                <div class="field-wrap">
                    <label>End Date</label>
                    <Calendar
                        v-model="filters.to"
                        dateFormat="mm/dd/yy"
                        placeholder="mm/dd/yyyy"
                        :showIcon="true"
                    />
                </div>
            </div>

            <div class="search-actions">
                <Button
                    label="Search"
                    icon="pi pi-search"
                    class="p-button-primary"
                    @click="fetchLogs"
                />
                <Button
                    label="Clear Filters"
                    icon="pi pi-times"
                    class="p-button-secondary"
                    @click="clearFilters"
                />
            </div>
        </div>

        <!-- Results -->
        <div class="results-card">
            <div class="results-header">
                <div class="results-label-row">
                    <span class="section-badge badge-green"
                        >SEARCH RESULTS</span
                    >
                    <span class="section-title">
                        Items in Stockroom
                        <span class="subtitle"
                            >(Clickable to View Full Logs)</span
                        >
                    </span>
                </div>
                <div class="print-actions">
                    <Button
                        label="Print Selected"
                        icon="pi pi-print"
                        class="p-button-success p-button-sm"
                        :disabled="!selectedRows.length"
                        @click="printSelected"
                    />
                    <Button
                        label="Print All"
                        icon="pi pi-print"
                        class="p-button-success p-button-sm"
                        @click="printAll"
                    />
                </div>
            </div>

            <DataTable
                v-model:selection="selectedRows"
                :value="logs"
                :loading="loading"
                :paginator="true"
                :rows="10"
                :rowsPerPageOptions="[10, 25, 50]"
                dataKey="checklist_id"
                selectionMode="multiple"
                :selectAll="selectAll"
                @select-all-change="onSelectAllChange"
                stripedRows
                responsiveLayout="scroll"
                class="log-table"
            >
                <template #empty>
                    <div class="empty-state">No items found.</div>
                </template>
                <template #loading>
                    <div class="empty-state">Loading records...</div>
                </template>

                <Column selectionMode="multiple" style="width: 3rem" />

                <Column field="serialnumber" header="Serial Number" sortable>
                    <template #body="{ data }">
                        <span class="serial-link" @click="viewFullLog(data)">
                            {{ data.serialnumber || "—" }}
                        </span>
                    </template>
                </Column>

                <Column field="asin" header="ASIN" sortable>
                    <template #body="{ data }">{{ data.asin || "—" }}</template>
                </Column>

                <Column field="product_name" header="Product Name" sortable>
                    <template #body="{ data }">{{
                        data.product_name || "—"
                    }}</template>
                </Column>

                <Column field="date_received" header="Date Stored" sortable>
                    <template #body="{ data }">{{
                        data.date_received
                    }}</template>
                </Column>

                <Column field="pass_fail_result" header="Status" sortable>
                    <template #body="{ data }">
                        <span
                            :class="[
                                'status-badge',
                                data.pass_fail_result === 'pass'
                                    ? 'status-pass'
                                    : 'status-fail',
                            ]"
                        >
                            {{
                                data.pass_fail_result === "pass"
                                    ? "✓ Pass"
                                    : "✗ Fail"
                            }}
                        </span>
                    </template>
                </Column>

                <Column header="Action">
                    <template #body="{ data }">
                        <Button
                            label="View Full Log"
                            icon="pi pi-eye"
                            class="p-button-sm view-btn"
                            @click="viewFullLog(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- View Full Log Dialog -->
        <Dialog
            v-model:visible="showLogDialog"
            :showHeader="false"
            :modal="true"
            :style="{ width: '720px', padding: '0' }"
            :breakpoints="{ '768px': '95vw' }"
            :pt="{ content: { style: 'padding: 0;' } }"
        >
            <div v-if="selectedLog" class="wl-page">
                <!-- Page number -->
                <div class="wl-page-number">Page 1 of 1</div>

                <!-- Top Header -->
                <div class="wl-header">
                    <div class="wl-header-left">
                        <h2 class="wl-title">WORKFLOW LOG REPORT</h2>
                        <p class="wl-subtitle">
                            Complete Item Processing History
                        </p>
                    </div>
                    <div class="wl-serial-badge">
                        <div class="wl-serial-label">Serial Number</div>
                        <div class="wl-serial-value">
                            {{ selectedLog.serialnumber || "—" }}
                        </div>
                    </div>
                </div>

                <!-- Meta Row 1: ASIN, FNSKU, Product -->
                <div class="wl-meta-grid">
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">ASIN</div>
                        <div class="wl-meta-value">
                            {{ selectedLog.asin || "—" }}
                        </div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">FNSKU</div>
                        <div class="wl-meta-value">
                            {{ selectedLog.fnsku || "—" }}
                        </div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">Product</div>
                        <div class="wl-meta-value">
                            {{ selectedLog.product_name || "—" }}
                        </div>
                    </div>
                </div>

                <!-- Meta Row 2: Date Received, Date Completed, Total Processing Time -->
                <div class="wl-meta-grid wl-meta-grid--second">
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">Date Received</div>
                        <div class="wl-meta-value">
                            {{ selectedLog.date_received || "—" }}
                        </div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">Date Completed</div>
                        <div class="wl-meta-value">
                            {{ selectedLog.date_completed || "—" }}
                        </div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">Total Processing Time</div>
                        <div class="wl-meta-value">
                            {{ selectedLog.processing_time || "—" }}
                        </div>
                    </div>
                </div>

                <hr class="wl-divider" />

                <!-- 1. Received Module Section -->
                <div class="wl-section-header">
                    <span>📦</span>
                    <span>1. RECEIVED MODULE</span>
                </div>

                <div class="wl-section-body">
                    <div class="wl-field">
                        <span class="wl-field-label">Date Received:</span>
                        <span class="wl-field-value">{{
                            selectedLog.date_received || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Tracking Number:</span>
                        <span class="wl-field-value">{{
                            selectedLog.trackingnumber || "—"
                        }}</span>
                    </div>
                    <template v-if="parsedSerials(selectedLog).length">
                        <div
                            class="wl-field"
                            v-for="(sn, i) in parsedSerials(selectedLog)"
                            :key="i"
                        >
                            <span class="wl-field-label"
                                >Serial Number{{
                                    i > 0 ? " " + (i + 1) : ""
                                }}:</span
                            >
                            <span class="wl-field-value">{{ sn }}</span>
                        </div>
                    </template>
                    <div class="wl-field" v-else>
                        <span class="wl-field-label">Serial Number:</span>
                        <span class="wl-field-value">—</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label"
                            >Working / Not Working:</span
                        >
                        <span class="wl-field-value">{{
                            selectedLog.pass_fail_result === "pass"
                                ? "Working"
                                : "Not Working"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Received By:</span>
                        <span class="wl-field-value">{{
                            selectedLog.received_by || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label"
                            >Item received correct on order:</span
                        >
                        <span class="wl-field-value">{{
                            selectedLog.correct_on_order === "yes"
                                ? "Yes ✓"
                                : "No ✗"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label"
                            >Condition on Arrival:</span
                        >
                        <span
                            class="wl-field-value"
                            style="text-transform: capitalize"
                        >
                            {{ selectedLog.condition_on_arrival || "—"
                            }}{{
                                selectedLog.condition_on_arrival === "good"
                                    ? " ✓"
                                    : ""
                            }}
                        </span>
                    </div>
                    <div class="wl-field" v-if="selectedLog.condition_notes">
                        <span class="wl-field-label">Condition Notes:</span>
                        <span class="wl-field-value">{{
                            selectedLog.condition_notes
                        }}</span>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="wl-footer-actions">
                    <Button
                        label="Print"
                        icon="pi pi-print"
                        class="p-button-success"
                        @click="printLog(selectedLog)"
                    />
                    <Button
                        label="Close"
                        icon="pi pi-times"
                        class="p-button-secondary"
                        @click="showLogDialog = false"
                    />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<script>
import {
    Button,
    InputText,
    Calendar,
    DataTable,
    Column,
    Dialog,
} from "primevue";

const API_BASE_URL = window.location.origin;

export default {
    components: { Button, InputText, Calendar, DataTable, Column, Dialog },

    data() {
        return {
            logs: [],
            selectedRows: [],
            selectAll: false,
            loading: false,
            showLogDialog: false,
            selectedLog: null,
            filters: {
                serial: "",
                asin: "",
                from: null,
                to: null,
            },
        };
    },

    mounted() {
        this.fetchLogs();
    },

    methods: {
        async fetchLogs() {
            this.loading = true;
            try {
                const params = {};
                if (this.filters.serial) params.serial = this.filters.serial;
                if (this.filters.asin) params.asin = this.filters.asin;
                if (this.filters.from)
                    params.from = this.formatDate(this.filters.from);
                if (this.filters.to)
                    params.to = this.formatDate(this.filters.to);

                const response = await axios.get(
                    `${API_BASE_URL}/api/received/checklist-logs`,
                    { params },
                );
                this.logs = response.data.data ?? response.data;
            } catch (error) {
                console.error("Error fetching checklist logs:", error);
            } finally {
                this.loading = false;
            }
        },

        clearFilters() {
            this.filters = { serial: "", asin: "", from: null, to: null };
            this.fetchLogs();
        },

        formatDate(date) {
            if (!date) return null;
            return new Date(date).toISOString().split("T")[0];
        },

        onSelectAllChange(event) {
            this.selectAll = event.checked;
            this.selectedRows = event.checked ? [...this.logs] : [];
        },

        viewFullLog(row) {
            this.selectedLog = row;
            this.showLogDialog = true;
        },

        parsedSerials(log) {
            return [
                log.serialnumber,
                log.serialnumberb,
                log.serialnumberc,
                log.serialnumberd,
                log.serialnumbere,
            ].filter(Boolean);
        },

        printLog(log) {
            const serials = this.parsedSerials(log);
            const serialRows = serials
                .map(
                    (sn, i) =>
                        `<div class="wl-field">
                    <span class="wl-field-label">Serial Number${i > 0 ? " " + (i + 1) : ""}:</span>
                    <span class="wl-field-value">${sn}</span>
                </div>`,
                )
                .join("");

            const win = window.open("", "_blank");
            win.document.write(`
                <html>
                <head>
                    <title>Workflow Log Report</title>
                    <style>
                        @page { size: 8.5in 11in portrait; margin: 0.75in; }
                        body { font-family: Arial, sans-serif; font-size: 13px; color: #111; }
                        .wl-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
                        .wl-title { font-size: 22px; font-weight: bold; margin: 0 0 4px; }
                        .wl-subtitle { color: #666; margin: 0; font-size: 13px; }
                        .wl-serial-badge { background: #4f46e5; color: #fff; padding: 10px 18px; border-radius: 8px; text-align: center; min-width: 130px; }
                        .wl-serial-label { font-size: 11px; margin-bottom: 4px; }
                        .wl-serial-value { font-size: 18px; font-weight: bold; }
                        .wl-meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; margin-bottom: 10px; }
                        .wl-meta-label { font-size: 11px; color: #888; text-transform: uppercase; margin-bottom: 3px; }
                        .wl-meta-value { font-weight: bold; font-size: 13px; }
                        hr { border: none; border-top: 1px solid #ddd; margin: 16px 0; }
                        .wl-section-header { background: #e8eaf6; padding: 8px 12px; border-left: 4px solid #4f46e5; font-weight: bold; font-size: 13px; margin-bottom: 10px; }
                        .wl-field { display: flex; padding: 5px 0; border-bottom: 1px solid #f1f5f9; }
                        .wl-field-label { width: 260px; color: #555; }
                        .wl-field-value { font-weight: 500; }
                    </style>
                </head>
                <body>
                    <div class="wl-header">
                        <div>
                            <div class="wl-title">WORKFLOW LOG REPORT</div>
                            <div class="wl-subtitle">Complete Item Processing History</div>
                        </div>
                        <div class="wl-serial-badge">
                            <div class="wl-serial-label">Serial Number</div>
                            <div class="wl-serial-value">${log.serialnumber || "—"}</div>
                        </div>
                    </div>

                    <div class="wl-meta-grid">
                        <div>
                            <div class="wl-meta-label">ASIN</div>
                            <div class="wl-meta-value">${log.asin || "—"}</div>
                        </div>
                        <div>
                            <div class="wl-meta-label">FNSKU</div>
                            <div class="wl-meta-value">${log.fnsku || "—"}</div>
                        </div>
                        <div>
                            <div class="wl-meta-label">Product</div>
                            <div class="wl-meta-value">${log.product_name || "—"}</div>
                        </div>
                    </div>
                    <div class="wl-meta-grid" style="margin-top:8px;">
                        <div>
                            <div class="wl-meta-label">Date Received</div>
                            <div class="wl-meta-value">${log.date_received || "—"}</div>
                        </div>
                        <div>
                            <div class="wl-meta-label">Date Completed</div>
                            <div class="wl-meta-value">${log.date_completed || "—"}</div>
                        </div>
                        <div>
                            <div class="wl-meta-label">Total Processing Time</div>
                            <div class="wl-meta-value">${log.processing_time || "—"}</div>
                        </div>
                    </div>

                    <hr/>

                    <div class="wl-section-header">📦 1. RECEIVED MODULE</div>
                    <div class="wl-field"><span class="wl-field-label">Date Received:</span><span class="wl-field-value">${log.date_received || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Tracking Number:</span><span class="wl-field-value">${log.trackingnumber || "—"}</span></div>
                    ${serialRows || `<div class="wl-field"><span class="wl-field-label">Serial Number:</span><span class="wl-field-value">—</span></div>`}
                    <div class="wl-field"><span class="wl-field-label">Working / Not Working:</span><span class="wl-field-value">${log.pass_fail_result === "pass" ? "Working" : "Not Working"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Received By:</span><span class="wl-field-value">${log.received_by || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Item received correct on order:</span><span class="wl-field-value">${log.correct_on_order === "yes" ? "Yes ✓" : "No ✗"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Condition on Arrival:</span><span class="wl-field-value" style="text-transform:capitalize;">${log.condition_on_arrival || "—"}${log.condition_on_arrival === "good" ? " ✓" : ""}</span></div>
                    ${log.condition_notes ? `<div class="wl-field"><span class="wl-field-label">Condition Notes:</span><span class="wl-field-value">${log.condition_notes}</span></div>` : ""}
                    <div class="wl-field"><span class="wl-field-label">PCN:</span><span class="wl-field-value">${log.pcn_number || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Basket:</span><span class="wl-field-value">${log.basket_number || "—"}</span></div>
                </body>
                </html>`);
            win.document.close();
            win.print();
        },

        printSelected() {
            this.triggerPrint(this.selectedRows);
        },

        printAll() {
            this.triggerPrint(this.logs);
        },

        triggerPrint(rows) {
            const win = window.open("", "_blank");
            const rows_html = rows
                .map(
                    (r) => `
                <tr>
                    <td>${r.trackingnumber || "—"}</td>
                    <td>${r.serialnumber || "—"}</td>
                    <td>${r.asin || "—"}</td>
                    <td>${r.product_name || "—"}</td>
                    <td>${r.pcn_number || "—"}</td>
                    <td>${r.basket_number || "—"}</td>
                    <td>${r.pass_fail_result === "pass" ? "✓ Pass" : "✗ Fail"}</td>
                    <td>${r.date_received || "—"}</td>
                    <td>${r.received_by || "—"}</td>
                </tr>`,
                )
                .join("");

            win.document.write(`
                <html><head><title>Item Log Print</title>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 12px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
                    th { background: #f0f0f0; }
                </style>
                </head><body>
                <h2>Item Log Report</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Tracking #</th><th>Serial</th><th>ASIN</th>
                            <th>Product</th><th>PCN</th><th>Basket</th>
                            <th>Status</th><th>Date</th><th>Received By</th>
                        </tr>
                    </thead>
                    <tbody>${rows_html}</tbody>
                </table>
                </body></html>`);
            win.document.close();
            win.print();
        },
    },
};
</script>

<style scoped>
.log-wrapper {
    padding: 1.5rem;
    background: #f0f4ff;
    min-height: 100vh;
    font-family: inherit;
}

.log-header {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 17px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1.5rem;
}

.search-card,
.results-card {
    background: #fff;
    border-radius: 10px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}

.search-card {
    border: 1.5px solid #bfdbfe;
}
.results-card {
    border: 1px solid #e2e8f0;
    padding: 0;
    overflow: hidden;
}

.section-label-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 1rem;
}

.section-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 5px;
    letter-spacing: 0.04em;
}

.badge-blue {
    background: #dbeafe;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.badge-green {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.section-title {
    font-weight: 600;
    font-size: 15px;
    color: #1e293b;
}
.subtitle {
    font-weight: 400;
    font-size: 13px;
    color: #64748b;
}

.search-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 1rem;
}

.field-wrap label {
    display: block;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 5px;
}
.field-wrap .p-inputtext,
.field-wrap .p-calendar {
    width: 100%;
}

.search-actions {
    display: flex;
    gap: 8px;
}

.results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.print-actions {
    display: flex;
    gap: 8px;
}

.log-table {
    width: 100%;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #9ca3af;
    font-size: 14px;
}

.serial-link {
    color: #2563eb;
    font-weight: 600;
    cursor: pointer;
    font-size: 13px;
}
.serial-link:hover {
    text-decoration: underline;
}

.status-badge {
    font-size: 12px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 5px;
    border: 1px solid;
}
.status-pass {
    background: #dcfce7;
    color: #166534;
    border-color: #86efac;
}
.status-fail {
    background: #fef2f2;
    color: #991b1b;
    border-color: #fca5a5;
}

.view-btn {
    background: #7c3aed !important;
    border-color: #7c3aed !important;
    font-size: 12px !important;
}

/* Workflow Log Dialog — print-ready layout */
.wl-page {
    background: #fff;
    padding: 32px 36px;
    font-family: Arial, sans-serif;
    font-size: 13px;
    color: #111;
    position: relative;
}

.wl-page-number {
    text-align: right;
    font-size: 11px;
    color: #888;
    margin-bottom: 12px;
}

.wl-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.wl-title {
    font-size: 22px;
    font-weight: bold;
    margin: 0 0 4px;
    letter-spacing: 0.01em;
}
.wl-subtitle {
    color: #555;
    margin: 0;
    font-size: 13px;
}

.wl-serial-badge {
    background: #4f46e5;
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    text-align: center;
    min-width: 140px;
}
.wl-serial-label {
    font-size: 11px;
    margin-bottom: 4px;
    opacity: 0.9;
}
.wl-serial-value {
    font-size: 20px;
    font-weight: bold;
    letter-spacing: 0.03em;
}

.wl-meta-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0;
    margin-bottom: 12px;
}

.wl-meta-grid--second {
    margin-bottom: 0;
}

.wl-meta-item {
    padding: 6px 0;
}
.wl-meta-label {
    font-size: 11px;
    color: #888;
    margin-bottom: 3px;
}
.wl-meta-value {
    font-weight: 700;
    font-size: 13px;
    color: #111;
}

.wl-divider {
    border: none;
    border-top: 1.5px solid #ccc;
    margin: 16px 0;
}

.wl-section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #e8eaf6;
    padding: 9px 14px;
    border-left: 4px solid #4f46e5;
    font-weight: bold;
    font-size: 13px;
    letter-spacing: 0.04em;
    border-radius: 0 4px 4px 0;
    margin-bottom: 0;
}

.wl-section-body {
    padding: 4px 0 8px 4px;
}

.wl-field {
    display: flex;
    align-items: flex-start;
    padding: 2px 10px;
    font-size: 13px;
}

.wl-field-label {
    width: 260px;
    min-width: 260px;
    color: #444;
}
.wl-field-value {
    font-weight: 500;
    color: #111;
}

.wl-footer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}
</style>
