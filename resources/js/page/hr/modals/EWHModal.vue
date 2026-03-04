<template>
    <Dialog
        v-model:visible="internalVisible"
        modal
        header="My Work Hours (EWH)"
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
                <button class="btn btn-sm btn-primary" @click="fetchMyEwh">
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
            your EWH records...
        </div>

        <!-- Empty -->
        <div
            v-else-if="records.length === 0"
            class="text-center py-5 text-muted"
        >
            <i class="pi pi-inbox" style="font-size: 2rem"></i>
            <p class="mt-2">No released EWH records found.</p>
        </div>

        <!-- Table -->
        <div v-else>
            <XDataTable
                :value="records"
                :columns="ewhColumns"
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
                <template #totalDays="{ data }">
                    {{ data.total_days }} day/s
                </template>
                <template #totalHours="{ data }">
                    {{ parseFloat(data.total_hours).toFixed(2) }} hrs
                </template>
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
                        No EWH records found.
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

    <!-- View EWH Dialog -->
    <Dialog
        v-model:visible="showViewDialog"
        modal
        header="Work Hours Detail"
        :style="{ width: '90%', maxWidth: '900px' }"
        :pt="{ root: { class: 'mobile-fullscreen-dialog' } }"
    >
        <div v-if="viewingRecord">
            <!-- Header Info -->
            <div
                class="bg-dark text-white px-3 py-2 rounded mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2"
            >
                <div>
                    <strong>{{ viewingRecord.employee_name }}</strong>
                    <span class="ms-3 text-secondary small">
                        Cutoff: {{ formatDate(viewingRecord.cutoff_from) }} -
                        {{ formatDate(viewingRecord.cutoff_to) }}
                    </span>
                </div>
                <span class="text-secondary small">
                    Payout Date: {{ formatDate(viewingRecord.payout_date) }}
                </span>
            </div>

            <!-- Attendance Table -->
            <div
                class="table-responsive"
                v-if="parseAttendance(viewingRecord).length > 0"
            >
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Worked Hours</th>
                            <th>Conversion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(att, idx) in parseAttendance(viewingRecord)"
                            :key="idx"
                        >
                            <td class="fw-semibold">
                                {{ formatDate(att.DateToday) }}
                            </td>
                            <td>{{ att.status || "Regular Day" }}</td>
                            <td>{{ formatWorkedHoursHM(att) }}</td>
                            <td>{{ calculateTotalHoursDecimal(att) }} hrs</td>
                        </tr>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-center fw-semibold">
                                Worked hr:
                                {{
                                    parseFloat(
                                        viewingRecord.regular_hours,
                                    ).toFixed(2)
                                }}
                                hrs, OT total:
                                {{
                                    parseFloat(viewingRecord.ot_hours).toFixed(
                                        2,
                                    )
                                }}
                                hrs
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-center fw-semibold">
                                Total Worked Hours:
                                {{
                                    formatTotalHoursHM(
                                        parseFloat(viewingRecord.total_hours),
                                    )
                                }}
                                or
                                {{
                                    parseFloat(
                                        viewingRecord.total_hours,
                                    ).toFixed(2)
                                }}
                                hrs
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Summary -->
                <table class="table table-bordered table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="fw-semibold" style="width: 200px">
                                Regular Day:
                            </td>
                            <td>{{ viewingRecord.total_days }} day/s</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Regular Holiday:</td>
                            <td>
                                {{ viewingRecord.regular_holiday_days || 0 }}
                                day/s
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Special Holiday:</td>
                            <td>
                                {{ viewingRecord.special_holiday_days || 0 }}
                                day/s
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Optional Day:</td>
                            <td>0 day/s</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Vacation Leave:</td>
                            <td>0 day/s</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Sick Leave:</td>
                            <td>0 day/s</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="text-center text-muted py-3">
                No attendance details available.
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

const EWH_COLUMNS = [
    {
        header: "Employee",
        field: "employee_name",
        bodyStyle: "font-size:14px; text-align:center;",
    },
    {
        header: "Payout Date",
        slot: "payoutDate",
        bodyStyle: "font-size:14px; text-align:center;",
    },
    {
        header: "Cutoff Period",
        slot: "cutoffDates",
        bodyStyle: "font-size:14px; text-align:center;",
    },
    {
        header: "Total Days",
        slot: "totalDays",
        bodyStyle: "font-size:14px; text-align:center;",
        style: { width: "110px" },
    },
    {
        header: "Total Hours",
        slot: "totalHours",
        bodyStyle: "font-size:14px; text-align:center;",
        style: { width: "120px" },
    },
    {
        header: "Status",
        slot: "employeeStatus",
        bodyStyle: "font-size:14px; text-align:center;",
        style: { width: "130px" },
    },
];

export default {
    name: "EwhModal",
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
            ewhColumns: EWH_COLUMNS,

            // View dialog
            showViewDialog: false,
            viewingRecord: null,
        };
    },

    watch: {
        // When parent sets visible=true, sync and fetch
        visible(val) {
            this.internalVisible = val;
            if (val) {
                this.currentPage = 1;
                this.records = [];
                this.fetchMyEwh();
            }
        },
        // When dialog closes internally (e.g. X button), notify parent
        internalVisible(val) {
            if (!val) {
                this.$emit("update:visible", false);
            }
        },
    },

    methods: {
        async fetchMyEwh() {
            this.loading = true;
            try {
                const response = await axios.get("/hr/ewh", {
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
                console.error("Error fetching EWH records:", error);
            } finally {
                this.loading = false;
            }
        },

        clearFilters() {
            this.filterFrom = null;
            this.filterTo = null;
            this.currentPage = 1;
            this.fetchMyEwh();
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchMyEwh();
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchMyEwh();
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
                    `/hr/ewh/${record.id}/employee-status`,
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
                    this.records = [...this.records]; // trigger reactivity
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

        parseAttendance(record) {
            if (!record?.attendance_records) return [];
            if (typeof record.attendance_records === "string") {
                try {
                    return JSON.parse(record.attendance_records);
                } catch {
                    return [];
                }
            }
            return record.attendance_records;
        },

        printRecord(record) {
            if (!record) return;
            const win = window.open("", "_blank");
            const attendance = this.parseAttendance(record);

            const rows = attendance
                .map(
                    (att) => `
                <tr>
                    <td>${this.formatDate(att.DateToday)}</td>
                    <td>${att.status || "Regular Day"}</td>
                    <td>${this.formatWorkedHoursHM(att)}</td>
                    <td>${this.calculateTotalHoursDecimal(att)} hrs</td>
                </tr>
            `,
                )
                .join("");

            win.document.write(`
                <html><head><title>EWH - ${record.employee_name}</title>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 13px; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    th, td { border: 1px solid #ccc; padding: 6px 10px; }
                    thead { background: #f0f0f0; }
                    tfoot { background: #f0f0f0; font-weight: bold; }
                    h3 { margin-bottom: 4px; }
                    .meta { color: #555; font-size: 12px; margin-bottom: 16px; }
                    .summary td:first-child { font-weight: bold; width: 200px; }
                </style>
                </head><body>
                <h3>Employee Work Hours (EWH)</h3>
                <div class="meta">
                    <strong>Name:</strong> ${record.employee_name} &nbsp;|&nbsp;
                    <strong>Cutoff:</strong> ${this.formatDate(record.cutoff_from)} - ${this.formatDate(record.cutoff_to)} &nbsp;|&nbsp;
                    <strong>Payout Date:</strong> ${this.formatDate(record.payout_date)}
                </div>
                <table>
                    <thead><tr><th>Date</th><th>Status</th><th>Worked Hours</th><th>Conversion</th></tr></thead>
                    <tbody>${rows}</tbody>
                    <tfoot>
                        <tr><td colspan="4" style="text-align:center">
                            Worked hr: ${parseFloat(record.regular_hours).toFixed(2)} hrs,
                            OT total: ${parseFloat(record.ot_hours).toFixed(2)} hrs
                        </td></tr>
                        <tr><td colspan="4" style="text-align:center">
                            Total Worked Hours: ${this.formatTotalHoursHM(parseFloat(record.total_hours))}
                            or ${parseFloat(record.total_hours).toFixed(2)} hrs
                        </td></tr>
                    </tfoot>
                </table>
                <table class="summary" style="margin-top:10px">
                    <tr><td>Regular Day:</td><td>${record.total_days} day/s</td></tr>
                    <tr><td>Regular Holiday:</td><td>${record.regular_holiday_days || 0} day/s</td></tr>
                    <tr><td>Special Holiday:</td><td>${record.special_holiday_days || 0} day/s</td></tr>
                    <tr><td>Optional Day:</td><td>0 day/s</td></tr>
                    <tr><td>Vacation Leave:</td><td>0 day/s</td></tr>
                    <tr><td>Sick Leave:</td><td>0 day/s</td></tr>
                </table>
                </body></html>
            `);
            win.document.close();
            win.print();
        },

        // ── Helpers ───────────────────────────────────────────────
        parseDateTime(str) {
            if (!str || str === "--:--" || str === "-") return null;
            try {
                if (str.includes(" ")) {
                    const [d, t] = str.split(" ");
                    const [yr, mo, dy] = d.split("-").map(Number);
                    const [hr, mi, se] = t.split(":").map(Number);
                    return new Date(yr, mo - 1, dy, hr, mi, se || 0);
                }
                return new Date(str);
            } catch {
                return null;
            }
        },
        calculateHoursFromRecord(record) {
            const i = this.parseDateTime(record.TimeIn);
            const o = this.parseDateTime(record.TimeOut);
            if (!i || !o) return 0;
            let ms = o - i;
            const bs = this.parseDateTime(record.shortbreak_start);
            const be = this.parseDateTime(record.shortbreak_end);
            if (bs && be) ms -= be - bs;
            else if (record.shortbreak_totaltime)
                ms -= record.shortbreak_totaltime * 60000;
            return ms / 3600000 > 0 ? ms / 3600000 : 0;
        },
        formatWorkedHoursHM(record) {
            const i = this.parseDateTime(record.TimeIn);
            const o = this.parseDateTime(record.TimeOut);
            if (!i || !o) return "-- hrs : -- mins";
            let ms = o - i;
            if (record.shortbreak_totaltime)
                ms -= record.shortbreak_totaltime * 60000;
            const m = Math.floor(ms / 60000);
            return `${String(Math.floor(m / 60)).padStart(2, "0")} hrs : ${String(m % 60).padStart(2, "0")} mins`;
        },
        calculateTotalHoursDecimal(record) {
            return this.calculateHoursFromRecord(record).toFixed(2);
        },
        formatTotalHoursHM(dec) {
            const m = Math.round(dec * 60);
            return `${Math.floor(m / 60)} hrs ${m % 60} mins`;
        },
        formatDate(dateString) {
            if (!dateString) return "-";
            return new Date(dateString).toLocaleDateString("en-US", {
                year: "numeric",
                month: "short",
                day: "numeric",
            });
        },
    },
};
</script>
