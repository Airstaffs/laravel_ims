<template>
    <Dialog
        v-model:visible="internalVisible"
        modal
        header="My Payslips"
        :style="{ width: '90%', maxWidth: '1000px' }"
        :pt="{ root: { class: 'mobile-fullscreen-dialog' } }"
    >
        <!-- Filters -->
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Cutoff From:</label>
                <InputText
                    v-model="filterFrom"
                    type="date"
                    class="form-control form-control-sm"
                />
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Cutoff To:</label>
                <InputText
                    v-model="filterTo"
                    type="date"
                    class="form-control form-control-sm"
                />
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button class="btn btn-sm btn-primary" @click="fetchMyPayslips">
                    Search
                </button>
                <button
                    class="btn btn-sm btn-outline-secondary"
                    @click="clearFilters"
                >
                    Clear
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-5 text-muted">
            <span class="spinner-border spinner-border-sm me-2"></span> Loading
            your payslips...
        </div>

        <!-- Empty -->
        <div
            v-else-if="records.length === 0"
            class="text-center py-5 text-muted"
        >
            <i class="pi pi-inbox" style="font-size: 2rem"></i>
            <p class="mt-2">No released payslips found.</p>
        </div>

        <!-- Table -->
        <div v-else>
            <XDataTable
                :value="records"
                :columns="columns"
                :loading="loading"
                :actionsFrozen="true"
            >
                <template #cutoffDates="{ data }">
                    {{ formatDate(data.cutoff_from) }} -
                    {{ formatDate(data.cutoff_to) }}
                </template>
                <template #payoutDate="{ data }">
                    {{ formatDate(data.payout_date) }}
                </template>
                <!-- <template #netPay="{ data }">
                    {{ data.currency }} {{ formatCurrency(data.net_pay) }}
                </template> -->
                <!-- <template #status="{ data }">
                    <span class="badge bg-success">
                        {{ data.status.toUpperCase() }}
                    </span>
                </template> -->
                <template #employeeStatus="{ data }">
                    <span
                        class="badge"
                        :class="{
                            'bg-danger': data.employee_status === 'new',
                            'bg-warning text-dark':
                                data.employee_status === 'viewed',
                            'bg-success':
                                data.employee_status === 'acknowledged',
                        }"
                    >
                        {{ data.employee_status?.toUpperCase() || "NEW" }}
                    </span>
                </template>
                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start">
                        <Button
                            label="View"
                            size="small"
                            variant="text"
                            severity="contrast"
                            class="text-info"
                            icon="pi pi-eye"
                            @click="viewRecord(data)"
                        />
                        <Button
                            label="Print"
                            size="small"
                            variant="text"
                            severity="contrast"
                            class="text-secondary"
                            icon="pi pi-print"
                            @click="printRecord(data)"
                        />
                    </div>
                </template>
                <template #empty>
                    <div class="text-center text-secondary py-3">
                        No payslips found.
                    </div>
                </template>
            </XDataTable>

            <!-- Pagination -->
            <div
                class="d-flex justify-content-between align-items-center mt-3"
                v-if="totalPages > 1"
            >
                <button
                    class="btn btn-sm btn-outline-secondary"
                    :disabled="currentPage === 1"
                    @click="prevPage"
                >
                    Previous
                </button>
                <span class="small"
                    >Page {{ currentPage }} / {{ totalPages }}</span
                >
                <button
                    class="btn btn-sm btn-outline-secondary"
                    :disabled="currentPage >= totalPages"
                    @click="nextPage"
                >
                    Next
                </button>
            </div>
        </div>

        <template #footer>
            <Button
                label="Close"
                severity="secondary"
                @click="internalVisible = false"
            />
        </template>
    </Dialog>

    <!-- ===================== VIEW PAYSLIP DIALOG ===================== -->
    <Dialog
        v-model:visible="showViewDialog"
        modal
        header="Pay Slip"
        :style="{ width: '800px', maxWidth: '95vw' }"
        :pt="{
            root: { class: 'mobile-fullscreen-dialog payslip-print-dialog' },
        }"
    >
        <div v-if="viewingRecord" class="payslip-view">
            <!-- EMPLOYEE INFORMATION -->
            <div class="section mb-4">
                <h6 class="section-title fw-bold mb-3">EMPLOYEE INFORMATION</h6>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="d-flex">
                            <span class="info-label" style="min-width: 150px"
                                >Employee Name:</span
                            >
                            <span class="fw-semibold">{{
                                viewingRecord.employee_name
                            }}</span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="d-flex">
                            <span class="info-label" style="min-width: 150px"
                                >Employee Number:</span
                            >
                            <span class="fw-semibold">{{
                                viewingRecord.employee_id
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PAY PERIOD INFORMATION -->
            <div class="section mb-4">
                <h6 class="section-title fw-bold mb-3">
                    PAY PERIOD INFORMATION
                </h6>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="d-flex">
                            <span class="info-label" style="min-width: 150px"
                                >Cut-off Date:</span
                            >
                            <span class="fw-semibold">
                                {{ formatDate(viewingRecord.cutoff_from) }} -
                                {{ formatDate(viewingRecord.cutoff_to) }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="d-flex">
                            <span class="info-label" style="min-width: 150px"
                                >Payout Date:</span
                            >
                            <span class="fw-semibold">{{
                                formatDate(viewingRecord.payout_date)
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WORK HOURS SUMMARY -->
            <div class="section mb-4">
                <h6 class="section-title fw-bold mb-3">WORK HOURS SUMMARY</h6>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <tr>
                                    <td>Total Work Hours:</td>
                                    <td class="fw-semibold">
                                        {{ viewingRecord.total_hours }} hrs
                                    </td>
                                </tr>
                                <tr>
                                    <td>Days Worked:</td>
                                    <td class="fw-semibold">
                                        {{ viewingRecord.total_days }} days
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <tr>
                                    <td>Total OT Hours:</td>
                                    <td class="fw-semibold">0.00 hrs</td>
                                </tr>
                                <tr>
                                    <td>Total Time:</td>
                                    <td class="fw-semibold">
                                        {{ viewingRecord.total_hours }} hrs
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- EARNINGS & DEDUCTIONS -->
            <div class="section mb-4">
                <h6 class="section-title fw-bold mb-3">
                    EARNINGS & DEDUCTIONS
                </h6>
                <div class="row">
                    <!-- EARNINGS -->
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>EARNINGS</th>
                                    <th class="text-end">AMOUNT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Regular day</td>
                                    <td class="text-end">
                                        {{ viewingRecord.currency }}
                                        {{
                                            formatCurrency(
                                                viewingRecord.basic_pay,
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        parseFloat(
                                            viewingRecord.regular_holiday_pay,
                                        ) > 0
                                    "
                                >
                                    <td>Holiday</td>
                                    <td class="text-end">
                                        {{ viewingRecord.currency }}
                                        {{
                                            formatCurrency(
                                                viewingRecord.regular_holiday_pay,
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        parseFloat(
                                            viewingRecord.special_holiday_pay,
                                        ) > 0
                                    "
                                >
                                    <td>Holiday Overtime</td>
                                    <td class="text-end">
                                        {{ viewingRecord.currency }}
                                        {{
                                            formatCurrency(
                                                viewingRecord.special_holiday_pay,
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        parseFloat(
                                            viewingRecord.regular_holiday_pay,
                                        ) === 0
                                    "
                                >
                                    <td>Overtime</td>
                                    <td class="text-end">
                                        {{ viewingRecord.currency }} 0.00
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        parseFloat(
                                            viewingRecord.regular_holiday_pay,
                                        ) === 0
                                    "
                                >
                                    <td>Holiday</td>
                                    <td class="text-end">
                                        {{ viewingRecord.currency }} 0.00
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        parseFloat(
                                            viewingRecord.special_holiday_pay,
                                        ) === 0
                                    "
                                >
                                    <td>Holiday Overtime</td>
                                    <td class="text-end">
                                        {{ viewingRecord.currency }} 0.00
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>TOTAL EARNINGS</td>
                                    <td class="text-end">
                                        {{ viewingRecord.currency }}
                                        {{
                                            formatCurrency(
                                                viewingRecord.gross_pay,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- DEDUCTIONS -->
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>DEDUCTIONS</th>
                                    <th class="text-end">AMOUNT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-if="activeDeductions.length > 0">
                                    <tr
                                        v-for="(d, i) in activeDeductions"
                                        :key="i"
                                    >
                                        <td>{{ d.name }}</td>
                                        <td class="text-end">
                                            {{ viewingRecord.currency }}
                                            {{ formatCurrency(d.amount) }}
                                        </td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr>
                                        <td>SSS</td>
                                        <td class="text-end">
                                            {{ viewingRecord.currency }} 0.00
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>PAGIBIG</td>
                                        <td class="text-end">
                                            {{ viewingRecord.currency }} 0.00
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>PHILHEALTH</td>
                                        <td class="text-end">
                                            {{ viewingRecord.currency }} 0.00
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>TOTAL DEDUCTIONS</td>
                                    <td class="text-end">
                                        {{ viewingRecord.currency }}
                                        {{
                                            formatCurrency(
                                                viewingRecord.deductions || 0,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- NET SALARY -->
            <div class="section">
                <div
                    class="bg-dark text-white p-3 d-flex justify-content-between align-items-center"
                >
                    <h5 class="mb-0 fw-bold">NET SALARY</h5>
                    <h5 class="mb-0 fw-bold">
                        {{ viewingRecord.currency }}
                        {{
                            formatCurrency(
                                viewingRecord.net_pay ||
                                    viewingRecord.gross_pay,
                            )
                        }}
                    </h5>
                </div>
            </div>

            <!-- NOTES -->
            <div
                class="section mt-4"
                v-if="viewingRecord.notes && viewingRecord.notes.trim() !== ''"
            >
                <h6 class="section-title fw-bold mb-3">NOTES</h6>
                <div class="card">
                    <div class="card-body">
                        <p
                            class="mb-0"
                            style="
                                white-space: pre-wrap;
                                word-break: break-word;
                            "
                        >
                            {{ viewingRecord.notes }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div
                class="d-flex justify-content-between w-100 align-items-center"
            >
                <Button
                    v-if="viewingRecord?.employee_status !== 'acknowledged'"
                    label="Acknowledge"
                    severity="success"
                    icon="pi pi-check"
                    @click="updateEmployeeStatus(viewingRecord, 'acknowledged')"
                />
                <span v-else class="badge bg-success px-3 py-2">
                    <i class="pi pi-check me-1"></i> Acknowledged
                </span>
                <div class="d-flex gap-2">
                    <Button
                        label="Close"
                        severity="secondary"
                        @click="showViewDialog = false"
                    />
                    <Button
                        label="Print"
                        severity="info"
                        icon="pi pi-print"
                        @click="printRecord(viewingRecord)"
                    />
                </div>
            </div>
        </template>
    </Dialog>
</template>

<script>
import { Dialog, Button, InputText } from "primevue";
import XDataTable from "../../../components/DataTable/XDataTable.vue";
import axios from "axios";

const COLUMNS = [
    { header: "Employee", field: "employee_name", bodyStyle: "font-size:14px" },
    { header: "Payout Date", slot: "payoutDate", bodyStyle: "font-size:14px" },
    {
        header: "Cutoff Period",
        slot: "cutoffDates",
        bodyStyle: "font-size:14px",
    },
    {
        header: "Status",
        slot: "employeeStatus",
        bodyStyle: "font-size:14px; text-align:center;",
        style: { width: "130px" },
    },
];

export default {
    name: "PayrollModal",
    components: { Dialog, Button, InputText, XDataTable },

    props: {
        visible: { type: Boolean, default: false },
    },

    emits: ["update:visible"],

    data() {
        return {
            internalVisible: false,
            records: [],
            loading: false,
            currentPage: 1,
            perPage: 10,
            totalPages: 1,
            filterFrom: null,
            filterTo: null,
            columns: COLUMNS,

            // View dialog
            showViewDialog: false,
            viewingRecord: null,
        };
    },

    computed: {
        activeDeductions() {
            if (!this.viewingRecord?.deduction_details) return [];
            try {
                const d =
                    typeof this.viewingRecord.deduction_details === "string"
                        ? JSON.parse(this.viewingRecord.deduction_details)
                        : this.viewingRecord.deduction_details;
                return d.filter((x) => x.active === true || x.active === 1);
            } catch {
                return [];
            }
        },
    },

    watch: {
        visible(val) {
            this.internalVisible = val;
            if (val) {
                this.currentPage = 1;
                this.records = [];
                this.fetchMyPayslips();
            }
        },
        internalVisible(val) {
            if (!val) this.$emit("update:visible", false);
        },
    },

    methods: {
        async fetchMyPayslips() {
            this.loading = true;
            try {
                const response = await axios.get("/hr/payslips", {
                    params: {
                        page: this.currentPage,
                        per_page: this.perPage,
                        from: this.filterFrom || undefined,
                        to: this.filterTo || undefined,
                    },
                });
                this.records = response.data.data || response.data;
                this.totalPages = response.data.last_page || 1;
            } catch (error) {
                console.error("Error fetching payslips:", error);
            } finally {
                this.loading = false;
            }
        },

        clearFilters() {
            this.filterFrom = null;
            this.filterTo = null;
            this.currentPage = 1;
            this.fetchMyPayslips();
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchMyPayslips();
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchMyPayslips();
            }
        },

        viewRecord(record) {
            this.viewingRecord = record;
            this.showViewDialog = true;
            // Auto-mark as viewed if still new
            if (record.employee_status === "new") {
                this.updateEmployeeStatus(record, "viewed");
            }
        },

        async updateEmployeeStatus(record, status) {
            try {
                const response = await axios.patch(
                    `/hr/payslips/${record.id}/employee-status`,
                    {
                        employee_status: status,
                    },
                );
                // Update locally so badge reflects immediately
                const idx = this.records.findIndex((r) => r.id === record.id);
                if (idx !== -1) {
                    this.records[idx] = {
                        ...this.records[idx],
                        employee_status: response.data.data.employee_status,
                    };
                    this.records = [...this.records];
                }
                if (this.viewingRecord?.id === record.id) {
                    this.viewingRecord = {
                        ...this.viewingRecord,
                        employee_status: response.data.data.employee_status,
                    };
                }
            } catch (error) {
                console.error("Error updating employee status:", error);
            }
        },

        printRecord(record) {
            if (!record) return;
            const win = window.open("", "_blank");
            const active = (() => {
                try {
                    const d =
                        typeof record.deduction_details === "string"
                            ? JSON.parse(record.deduction_details)
                            : record.deduction_details || [];
                    return d.filter((x) => x.active === true || x.active === 1);
                } catch {
                    return [];
                }
            })();

            const deductionRows =
                active.length > 0
                    ? active
                          .map(
                              (d) =>
                                  `<tr><td>${d.name}</td><td style="text-align:right">${record.currency} ${this.formatCurrency(d.amount)}</td></tr>`,
                          )
                          .join("")
                    : `<tr><td>SSS</td><td style="text-align:right">${record.currency} 0.00</td></tr>
                   <tr><td>PAGIBIG</td><td style="text-align:right">${record.currency} 0.00</td></tr>
                   <tr><td>PHILHEALTH</td><td style="text-align:right">${record.currency} 0.00</td></tr>`;

            win.document.write(`
                <html><head><title>Payslip - ${record.employee_name}</title>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 13px; padding: 20px; }
                    h3 { margin-bottom: 4px; }
                    .meta { margin-bottom: 16px; }
                    .meta div { margin-bottom: 4px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
                    th, td { border: 1px solid #ccc; padding: 6px 10px; }
                    thead { background: #f0f0f0; }
                    tfoot { background: #f0f0f0; font-weight: bold; }
                    .net { background:#222; color:#fff; padding:10px 14px; display:flex; justify-content:space-between; margin-top:10px; }
                    .section-title { font-weight:bold; margin: 14px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
                    .two-col { display: flex; gap: 16px; }
                    .two-col table { flex: 1; }
                </style>
                </head><body>
                <h3>PAY SLIP</h3>
                <div class="meta">
                    <div><strong>Employee Name:</strong> ${record.employee_name} &nbsp;&nbsp; <strong>Employee No.:</strong> ${record.employee_id}</div>
                    <div><strong>Cut-off Date:</strong> ${this.formatDate(record.cutoff_from)} - ${this.formatDate(record.cutoff_to)}</div>
                    <div><strong>Payout Date:</strong> ${this.formatDate(record.payout_date)}</div>
                </div>

                <div class="section-title">WORK HOURS SUMMARY</div>
                <table>
                    <tr><td>Total Work Hours</td><td>${record.total_hours} hrs</td><td>Total OT Hours</td><td>0.00 hrs</td></tr>
                    <tr><td>Days Worked</td><td>${record.total_days} days</td><td>Total Time</td><td>${record.total_hours} hrs</td></tr>
                </table>

                <div class="section-title">EARNINGS & DEDUCTIONS</div>
                <div class="two-col">
                    <table>
                        <thead><tr><th>EARNINGS</th><th style="text-align:right">AMOUNT</th></tr></thead>
                        <tbody>
                            <tr><td>Regular day</td><td style="text-align:right">${record.currency} ${this.formatCurrency(record.basic_pay)}</td></tr>
                            <tr><td>Overtime</td><td style="text-align:right">${record.currency} 0.00</td></tr>
                            <tr><td>Holiday</td><td style="text-align:right">${record.currency} ${this.formatCurrency(record.regular_holiday_pay || 0)}</td></tr>
                            <tr><td>Holiday Overtime</td><td style="text-align:right">${record.currency} ${this.formatCurrency(record.special_holiday_pay || 0)}</td></tr>
                        </tbody>
                        <tfoot><tr><td>TOTAL EARNINGS</td><td style="text-align:right">${record.currency} ${this.formatCurrency(record.gross_pay)}</td></tr></tfoot>
                    </table>
                    <table>
                        <thead><tr><th>DEDUCTIONS</th><th style="text-align:right">AMOUNT</th></tr></thead>
                        <tbody>${deductionRows}</tbody>
                        <tfoot><tr><td>TOTAL DEDUCTIONS</td><td style="text-align:right">${record.currency} ${this.formatCurrency(record.deductions || 0)}</td></tr></tfoot>
                    </table>
                </div>

                <div class="net">
                    <strong>NET SALARY</strong>
                    <strong>${record.currency} ${this.formatCurrency(record.net_pay || record.gross_pay)}</strong>
                </div>

                ${record.notes ? `<div class="section-title">NOTES</div><p>${record.notes}</p>` : ""}
                </body></html>
            `);
            win.document.close();
            win.print();
        },

        formatDate(d) {
            if (!d) return "-";
            return new Date(d).toLocaleDateString("en-US", {
                year: "numeric",
                month: "short",
                day: "numeric",
            });
        },
        formatCurrency(value) {
            return parseFloat(value || 0).toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
    },
};
</script>
