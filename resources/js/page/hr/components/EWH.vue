<template>
    <div class="ewh-wrapper">
        <div
            class="ewh-header d-flex justify-content-between align-items-center mb-3"
        >
            <h4 class="mb-0">Employee Work Hours (EWH)</h4>
            <Button
                v-if="isHR"
                @click="openCreateEWH"
                size="small"
                severity="warn"
                icon="pi pi-plus"
                label="Create EWH"
            />
        </div>

        <!-- Saved EWH Records Table -->
        <XDataTable
            :value="ewhRecords"
            :columns="ewhColumns"
            :loading="loadingEwhRecords"
            :actionsFrozen="true"
            tableClass="mt-3"
        >
            <template #cutoffDates="{ data }">
                {{ formatDate(data.cutoff_from) }} -
                {{ formatDate(data.cutoff_to) }}
            </template>
            <template #payoutDate="{ data }">
                {{ formatDate(data.payout_date) }}
            </template>
            <template #totalHours="{ data }">
                {{ parseFloat(data.total_hours).toFixed(2) }} hrs
            </template>
            <template #status="{ data }">
                <span
                    class="badge"
                    :class="
                        data.status === 'released'
                            ? 'bg-success'
                            : 'bg-secondary'
                    "
                >
                    {{ data.status === "released" ? "RELEASED" : "DRAFT" }}
                </span>
            </template>
            <template #totalDays="{ data }">
                {{ data.total_days }} day/s
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
                        @click="viewEwh(data)"
                    />
                    <Button
                        v-if="isHR"
                        label="Delete"
                        size="small"
                        variant="text"
                        severity="contrast"
                        class="text-danger"
                        icon="pi pi-trash"
                        @click="deleteEwh(data)"
                    />
                </div>
            </template>
            <template #empty>
                <div class="text-center text-secondary py-3">
                    No EWH records found. Create your first EWH to get started.
                </div>
            </template>
        </XDataTable>

        <!-- Pagination -->
        <div
            class="d-flex justify-content-between align-items-center mt-3"
            v-if="ewhRecords.length > 0"
        >
            <button
                class="btn btn-outline-secondary"
                :disabled="currentPage === 1"
                @click="prevPage()"
            >
                Previous
            </button>
            <span>Page {{ currentPage }} / {{ totalPages }}</span>
            <button
                class="btn btn-outline-secondary"
                :disabled="currentPage >= totalPages"
                @click="nextPage()"
            >
                Next
            </button>
        </div>
    </div>

    <!-- ===================== CREATE EWH DIALOG ===================== -->
    <Dialog
        v-model:visible="showCreateEWH"
        modal
        header="Create Employee Work Hours (EWH)"
        :style="{ width: '90%', maxWidth: '1100px' }"
        :pt="{ root: { class: 'mobile-fullscreen-dialog' } }"
    >
        <!-- Row 1: Dates + Generate -->
        <div class="row g-3 mb-2">
            <div class="col-md-4">
                <label class="form-label fw-semibold"
                    >Pay Out Date: <span class="text-danger">*</span></label
                >
                <InputText
                    v-model="ewhPayoutDate"
                    type="date"
                    class="form-control"
                />
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold"
                    >Cutoff From: <span class="text-danger">*</span></label
                >
                <InputText
                    v-model="ewhCutoffFrom"
                    type="date"
                    class="form-control"
                />
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold"
                    >Cutoff To: <span class="text-danger">*</span></label
                >
                <InputText
                    v-model="ewhCutoffTo"
                    type="date"
                    class="form-control"
                />
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <Button
                    label="Generate"
                    icon="pi pi-refresh"
                    severity="info"
                    size="small"
                    :loading="loadingEWH"
                    :disabled="!canGenerateEWH"
                    @click="generateEWH"
                    type="button"
                    class="w-100"
                />
            </div>
        </div>

        <!-- Row 2: Employee Checkbox Grid -->
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div
                    class="d-flex justify-content-between align-items-center mb-2"
                >
                    <label class="form-label fw-semibold mb-0">
                        Employee(s): <span class="text-danger">*</span>
                        <span class="text-muted fw-normal ms-2 small">
                            ({{ ewhSelectedEmployees.length }} of
                            {{ employees.length }} selected)
                        </span>
                    </label>
                    <div class="d-flex gap-2">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-success"
                            @click="
                                ewhSelectedEmployees = employees.map(
                                    (e) => e.id,
                                )
                            "
                        >
                            Select All
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            @click="ewhSelectedEmployees = []"
                        >
                            Deselect All
                        </button>
                    </div>
                </div>

                <div
                    v-if="loadingEmployees"
                    class="text-muted small py-3 text-center"
                >
                    <span class="spinner-border spinner-border-sm me-1"></span>
                    Loading employees...
                </div>

                <div
                    v-else
                    class="border rounded p-3"
                    style="background: #f8f9fa"
                >
                    <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-2">
                        <div v-for="emp in employees" :key="emp.id" class="col">
                            <div
                                class="d-flex align-items-center gap-2 px-2 py-1 rounded"
                                style="cursor: pointer"
                                @click="toggleEwhEmployee(emp.id)"
                            >
                                <div
                                    class="rounded d-flex align-items-center justify-content-center flex-shrink-0"
                                    style="
                                        width: 20px;
                                        height: 20px;
                                        border: 2px solid;
                                        transition: all 0.15s;
                                    "
                                    :style="
                                        ewhSelectedEmployees.includes(emp.id)
                                            ? 'background:#28a745; border-color:#28a745;'
                                            : 'background:#fff; border-color:#ced4da;'
                                    "
                                >
                                    <i
                                        v-if="
                                            ewhSelectedEmployees.includes(
                                                emp.id,
                                            )
                                        "
                                        class="pi pi-check"
                                        style="color: #fff; font-size: 11px"
                                    ></i>
                                </div>
                                <span
                                    class="small text-truncate"
                                    :title="emp.name"
                                    >{{ emp.name }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EWH Results per Employee -->
        <div v-if="ewhResults.length > 0">
            <div
                v-for="(result, idx) in ewhResults"
                :key="idx"
                class="mb-4 border rounded overflow-hidden"
            >
                <!-- Employee Header -->
                <div
                    class="bg-dark text-white px-3 py-2 d-flex justify-content-between align-items-center"
                >
                    <div>
                        <strong>Name: {{ result.employeeName }}</strong>
                        <span class="ms-3 text-secondary small">
                            Cut-off Date: {{ formatDate(ewhCutoffFrom) }} -
                            {{ formatDate(ewhCutoffTo) }}
                        </span>
                    </div>
                    <span
                        class="badge"
                        :class="
                            result.loading
                                ? 'bg-secondary'
                                : result.error
                                  ? 'bg-danger'
                                  : 'bg-success'
                        "
                    >
                        {{
                            result.loading
                                ? "Loading..."
                                : result.error
                                  ? "Error"
                                  : "Loaded"
                        }}
                    </span>
                </div>

                <!-- Loading State -->
                <div v-if="result.loading" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2"></span>
                    Loading attendance records...
                </div>

                <!-- Error State -->
                <div v-else-if="result.error" class="alert alert-danger m-3">
                    <i class="pi pi-exclamation-triangle me-2"></i
                    >{{ result.error }}
                </div>

                <!-- Attendance Table -->
                <div
                    v-else-if="result.records && result.records.length > 0"
                    class="table-responsive"
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
                                v-for="(record, rIdx) in result.records"
                                :key="rIdx"
                            >
                                <td class="fw-semibold">
                                    {{ formatDate(record.DateToday) }}
                                </td>
                                <td>{{ record.status || "Regular Day" }}</td>
                                <td>{{ formatWorkedHoursHM(record) }}</td>
                                <td>
                                    {{ calculateTotalHoursDecimal(record) }} hrs
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-center fw-semibold">
                                    Worked hr:
                                    {{ result.regularHours.toFixed(2) }} hrs, OT
                                    total: {{ result.otHours.toFixed(2) }} hrs
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-center fw-semibold">
                                    Total Worked Hours:
                                    {{ formatTotalHoursHM(result.totalHours) }}
                                    or {{ result.totalHours.toFixed(2) }} hrs
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <!-- Summary Rows -->
                    <table class="table table-bordered table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="fw-semibold" style="width: 200px">
                                    Regular Day:
                                </td>
                                <td>{{ result.records.length }} day/s</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Regular Holiday:</td>
                                <td>{{ result.regularHolidayDays }} day/s</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Special Holiday:</td>
                                <td>{{ result.specialHolidayDays }} day/s</td>
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

                <!-- No Records -->
                <div v-else class="text-center text-muted py-4">
                    No attendance records found for this cutoff period.
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!loadingEWH" class="text-center text-muted py-5">
            <i class="pi pi-users" style="font-size: 2rem"></i>
            <p class="mt-2">
                Select employee(s) and cutoff dates, then click Generate to view
                EWH.
            </p>
        </div>

        <!-- Save Warning Summary -->
        <div v-if="ewhResults.length > 0 && !loadingEWH" class="mt-3">
            <div
                v-if="ewhNoRecordEmployees.length > 0"
                class="alert alert-warning mb-2"
            >
                <i class="pi pi-exclamation-triangle me-2"></i>
                <strong
                    >The following employee(s) have no attendance records and
                    will be skipped:</strong
                >
                <ul class="mb-0 mt-1">
                    <li v-for="name in ewhNoRecordEmployees" :key="name">
                        {{ name }}
                    </li>
                </ul>
            </div>
            <div
                v-if="ewhValidEmployees.length > 0"
                class="alert alert-success mb-0"
            >
                <i class="pi pi-check-circle me-2"></i>
                <strong
                    >{{ ewhValidEmployees.length }} employee(s) will be
                    saved:</strong
                >
                {{ ewhValidEmployees.join(", ") }}
            </div>
        </div>

        <template #footer>
            <div class="d-flex justify-content-between w-100">
                <Button
                    label="Cancel"
                    severity="secondary"
                    @click="closeCreateEWH"
                    :disabled="saving"
                />
                <Button
                    label="Save EWH"
                    severity="success"
                    icon="pi pi-save"
                    :loading="saving"
                    :disabled="!canSaveEWH"
                    @click="saveEWH"
                />
            </div>
        </template>
    </Dialog>

    <!-- ===================== VIEW EWH DIALOG ===================== -->
    <Dialog
        v-model:visible="showViewEWH"
        modal
        header="View Employee Work Hours (EWH)"
        :style="{ width: '90%', maxWidth: '900px' }"
        :pt="{ root: { class: 'mobile-fullscreen-dialog' } }"
    >
        <div v-if="viewingEwh">
            <div class="bg-dark text-white px-3 py-2 rounded mb-3">
                <strong>Name: {{ viewingEwh.employee_name }}</strong>
                <span class="ms-3 text-secondary small">
                    Cut-off Date: {{ formatDate(viewingEwh.cutoff_from) }} -
                    {{ formatDate(viewingEwh.cutoff_to) }}
                </span>
            </div>

            <div class="table-responsive" v-if="viewingEwhRecords.length > 0">
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
                            v-for="(record, idx) in viewingEwhRecords"
                            :key="idx"
                        >
                            <td class="fw-semibold">
                                {{ formatDate(record.DateToday) }}
                            </td>
                            <td>{{ record.status || "Regular Day" }}</td>
                            <td>{{ formatWorkedHoursHM(record) }}</td>
                            <td>
                                {{ calculateTotalHoursDecimal(record) }} hrs
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-center fw-semibold">
                                Total Worked Hours:
                                {{
                                    formatTotalHoursHM(
                                        parseFloat(viewingEwh.total_hours),
                                    )
                                }}
                                or
                                {{
                                    parseFloat(viewingEwh.total_hours).toFixed(
                                        2,
                                    )
                                }}
                                hrs
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <table class="table table-bordered table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="fw-semibold" style="width: 200px">
                                Regular Day:
                            </td>
                            <td>{{ viewingEwh.total_days }} day/s</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Regular Holiday:</td>
                            <td>
                                {{ viewingEwh.regular_holiday_days || 0 }} day/s
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Special Holiday:</td>
                            <td>
                                {{ viewingEwh.special_holiday_days || 0 }} day/s
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
            <div v-else class="text-center text-muted py-4">
                No records available.
            </div>
        </div>

        <template #footer>
            <div class="d-flex justify-content-end gap-2 w-100">
                <Button
                    label="Close"
                    severity="secondary"
                    @click="showViewEWH = false"
                />
                <Button
                    label="Print"
                    severity="info"
                    icon="pi pi-print"
                    @click="printEWH"
                />
            </div>
        </template>
    </Dialog>
</template>

<script>
import { Button, InputText, Dialog } from "primevue";
import XDataTable from "../../../components/DataTable/XDataTable.vue";
import axios from "axios";
import Swal from "sweetalert2";

const EWH_COLUMNS = [
    { header: "Employee", field: "employee_name", bodyStyle: "font-size:14px" },
    { header: "Payout Date", slot: "payoutDate", bodyStyle: "font-size:14px" },
    {
        header: "Cutoff Period",
        slot: "cutoffDates",
        bodyStyle: "font-size:14px",
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
        slot: "status",
        bodyStyle: "font-size:14px; text-align:center;",
        style: { width: "110px" },
    },
];

export default {
    components: { Button, InputText, Dialog, XDataTable },

    data() {
        return {
            // Table
            ewhRecords: [],
            loadingEwhRecords: false,
            ewhColumns: EWH_COLUMNS,
            currentPage: 1,
            perPage: 10,
            totalPages: 1,

            // Employees
            employees: [],
            loadingEmployees: false,

            // Create dialog
            showCreateEWH: false,
            ewhSelectedEmployees: [],
            ewhPayoutDate: null,
            ewhCutoffFrom: null,
            ewhCutoffTo: null,
            ewhResults: [],
            loadingEWH: false,
            saving: false,

            // View dialog
            showViewEWH: false,
            viewingEwh: null,
            viewingEwhRecords: [],

            // Auth user
            authUser: null,
        };
    },

    computed: {
        isHR() {
            return (
                this.authUser?.role === "SuperAdmin" ||
                this.authUser?.role === "SubAdmin" ||
                this.authUser?.role === "hr"
            );
        },
        canGenerateEWH() {
            return (
                this.ewhSelectedEmployees.length > 0 &&
                this.ewhCutoffFrom &&
                this.ewhCutoffTo
            );
        },
        canSaveEWH() {
            return (
                this.ewhResults.length > 0 &&
                this.ewhValidEmployees.length > 0 &&
                this.ewhPayoutDate &&
                !this.saving &&
                !this.loadingEWH
            );
        },
        // Employees with valid records — will be saved
        ewhValidEmployees() {
            return this.ewhResults
                .filter(
                    (r) =>
                        !r.loading &&
                        !r.error &&
                        r.records &&
                        r.records.length > 0,
                )
                .map((r) => r.employeeName);
        },
        // Employees with no records — will be skipped
        ewhNoRecordEmployees() {
            return this.ewhResults
                .filter(
                    (r) =>
                        !r.loading &&
                        !r.error &&
                        (!r.records || r.records.length === 0),
                )
                .map((r) => r.employeeName);
        },
    },

    mounted() {
        this.fetchAuthUser();
        this.fetchEwhRecords();
    },

    methods: {
        async fetchAuthUser() {
            try {
                const response = await axios.get("/auth/user");
                this.authUser = response.data;
            } catch (error) {
                console.error("Error fetching auth user:", error);
            }
        },

        // ── Table ────────────────────────────────────────────────
        async fetchEwhRecords() {
            this.loadingEwhRecords = true;
            try {
                const response = await axios.get("/hr/ewh", {
                    params: { page: this.currentPage, per_page: this.perPage },
                });
                this.ewhRecords = response.data.data || response.data;
                this.totalPages = response.data.last_page || 1;
            } catch (error) {
                console.error("Error fetching EWH records:", error);
            } finally {
                this.loadingEwhRecords = false;
            }
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchEwhRecords();
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchEwhRecords();
            }
        },

        // ── Employees ────────────────────────────────────────────
        async fetchEmployees() {
            this.loadingEmployees = true;
            try {
                const response = await axios.get("/hr/employees");
                this.employees = response.data;
            } catch (error) {
                console.error("Error fetching employees:", error);
            } finally {
                this.loadingEmployees = false;
            }
        },
        toggleEwhEmployee(id) {
            const idx = this.ewhSelectedEmployees.indexOf(id);
            idx === -1
                ? this.ewhSelectedEmployees.push(id)
                : this.ewhSelectedEmployees.splice(idx, 1);
        },

        // ── Dialog open/close ─────────────────────────────────────
        openCreateEWH() {
            this.showCreateEWH = true;
            this.fetchEmployees();
        },
        closeCreateEWH() {
            this.showCreateEWH = false;
            this.ewhSelectedEmployees = [];
            this.ewhPayoutDate = null;
            this.ewhCutoffFrom = null;
            this.ewhCutoffTo = null;
            this.ewhResults = [];
        },

        // ── Generate ──────────────────────────────────────────────
        async generateEWH() {
            if (!this.canGenerateEWH) return;
            this.loadingEWH = true;

            this.ewhResults = this.ewhSelectedEmployees.map((empId) => {
                const emp = this.employees.find((e) => e.id === empId);
                return {
                    employeeId: empId,
                    employeeName: emp ? emp.name : `Employee #${empId}`,
                    loading: true,
                    error: null,
                    records: [],
                    totalHours: 0,
                    regularHours: 0,
                    otHours: 0,
                    regularHolidayDays: 0,
                    specialHolidayDays: 0,
                };
            });

            const fetchPromises = this.ewhResults.map(async (result, idx) => {
                try {
                    const response = await axios.get("/hr/time-records", {
                        params: {
                            employee: result.employeeName,
                            dateFrom: this.ewhCutoffFrom,
                            dateTo: this.ewhCutoffTo,
                        },
                    });
                    const records = response.data.data || response.data;

                    let totalHours = 0;
                    records.forEach((r) => {
                        totalHours += this.calculateHoursFromRecord(r);
                    });

                    const regularHours = Math.min(
                        totalHours,
                        records.length * 8,
                    );
                    const otHours = Math.max(0, totalHours - regularHours);

                    let regularHolidayDays = 0,
                        specialHolidayDays = 0;
                    try {
                        const hResp = await axios.get("/hr/holidays", {
                            params: {
                                date_from: this.ewhCutoffFrom,
                                date_to: this.ewhCutoffTo,
                            },
                        });
                        (hResp.data || []).forEach((h) => {
                            if (
                                records.find((r) => r.DateToday === h.holidate)
                            ) {
                                h.status === "regular"
                                    ? regularHolidayDays++
                                    : specialHolidayDays++;
                            }
                        });
                    } catch (_) {}

                    this.ewhResults[idx] = {
                        ...result,
                        loading: false,
                        records,
                        totalHours,
                        regularHours,
                        otHours,
                        regularHolidayDays,
                        specialHolidayDays,
                    };
                } catch (error) {
                    console.error("EWH fetch error:", error);
                    this.ewhResults[idx] = {
                        ...result,
                        loading: false,
                        error: "Failed to load attendance records.",
                    };
                }
            });

            await Promise.all(fetchPromises);
            this.loadingEWH = false;
        },

        // ── Save ──────────────────────────────────────────────────
        async saveEWH() {
            if (!this.canSaveEWH) return;
            this.saving = true;
            try {
                // Only save employees who have attendance records
                const validResults = this.ewhResults.filter(
                    (r) =>
                        !r.loading &&
                        !r.error &&
                        r.records &&
                        r.records.length > 0,
                );

                const payload = validResults.map((result) => ({
                    employee_id: result.employeeId,
                    employee_name: result.employeeName,
                    payout_date: this.ewhPayoutDate,
                    cutoff_from: this.ewhCutoffFrom,
                    cutoff_to: this.ewhCutoffTo,
                    total_days: result.records.length,
                    total_hours: result.totalHours,
                    regular_hours: result.regularHours,
                    ot_hours: result.otHours,
                    regular_holiday_days: result.regularHolidayDays,
                    special_holiday_days: result.specialHolidayDays,
                    attendance_records: result.records,
                }));

                await axios.post("/hr/ewh", { records: payload });

                const skipped = this.ewhNoRecordEmployees;
                let html = `<strong>${validResults.length} EWH record(s) saved successfully.</strong>`;
                if (skipped.length > 0) {
                    html += `<hr><div class="text-start"><strong class="text-warning">Skipped (no attendance records):</strong><ul class="mt-1 mb-0">`;
                    skipped.forEach((name) => {
                        html += `<li>${name}</li>`;
                    });
                    html += `</ul></div>`;
                }

                await Swal.fire({
                    icon: "success",
                    title: "EWH Saved",
                    html,
                    confirmButtonText: "OK",
                });

                this.closeCreateEWH();
                this.fetchEwhRecords();
            } catch (error) {
                console.error("Error saving EWH:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Save Failed",
                    text:
                        error.response?.data?.message ||
                        "Failed to save EWH records.",
                });
            } finally {
                this.saving = false;
            }
        },

        // ── View ──────────────────────────────────────────────────
        viewEwh(record) {
            this.viewingEwh = record;
            this.viewingEwhRecords = record.attendance_records
                ? typeof record.attendance_records === "string"
                    ? JSON.parse(record.attendance_records)
                    : record.attendance_records
                : [];
            this.showViewEWH = true;
        },

        // ── Delete ────────────────────────────────────────────────
        async releaseEwh(record) {
            const confirm = await Swal.fire({
                icon: "question",
                title: "Release EWH?",
                html: `This will make the EWH record for <strong>${record.employee_name}</strong> visible to the employee.`,
                showCancelButton: true,
                confirmButtonColor: "#28a745",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Yes, release it",
                cancelButtonText: "Cancel",
            });

            if (!confirm.isConfirmed) return;

            try {
                await axios.patch(`/hr/ewh/${record.id}/release`);
                await Swal.fire({
                    icon: "success",
                    title: "Released",
                    text: `EWH record for ${record.employee_name} is now visible to the employee.`,
                    timer: 2000,
                    showConfirmButton: false,
                });
                this.fetchEwhRecords();
            } catch (error) {
                console.error("Error releasing EWH:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Release Failed",
                    text:
                        error.response?.data?.message ||
                        "Failed to release EWH record.",
                });
            }
        },

        async deleteEwh(record) {
            const confirm = await Swal.fire({
                icon: "warning",
                title: "Delete EWH Record?",
                html: `Are you sure you want to delete the EWH record for <strong>${record.employee_name}</strong>?`,
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Yes, delete it",
                cancelButtonText: "Cancel",
            });

            if (!confirm.isConfirmed) return;

            try {
                await axios.delete(`/hr/ewh/${record.id}`);
                await Swal.fire({
                    icon: "success",
                    title: "Deleted",
                    text: "EWH record deleted successfully.",
                    timer: 1500,
                    showConfirmButton: false,
                });
                this.fetchEwhRecords();
            } catch (error) {
                console.error("Error deleting EWH:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Delete Failed",
                    text: "Failed to delete EWH record. Please try again.",
                });
            }
        },

        // ── Print ─────────────────────────────────────────────────
        printEWH() {
            setTimeout(() => window.print(), 100);
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
            const timeIn = this.parseDateTime(record.TimeIn);
            const timeOut = this.parseDateTime(record.TimeOut);
            if (!timeIn || !timeOut) return 0;

            let ms = timeOut - timeIn;
            const bs = this.parseDateTime(record.shortbreak_start);
            const be = this.parseDateTime(record.shortbreak_end);
            if (bs && be) ms -= be - bs;
            else if (record.shortbreak_totaltime)
                ms -= record.shortbreak_totaltime * 60000;

            const hrs = ms / 3600000;
            return hrs > 0 ? hrs : 0;
        },
        formatWorkedHoursHM(record) {
            const timeIn = this.parseDateTime(record.TimeIn);
            const timeOut = this.parseDateTime(record.TimeOut);
            if (!timeIn || !timeOut) return "-- hrs : -- mins";

            let ms = timeOut - timeIn;
            if (record.shortbreak_totaltime)
                ms -= record.shortbreak_totaltime * 60000;
            const totalMins = Math.floor(ms / 60000);
            const h = Math.floor(totalMins / 60);
            const m = totalMins % 60;
            return `${String(h).padStart(2, "0")} hrs : ${String(m).padStart(2, "0")} mins`;
        },
        calculateTotalHoursDecimal(record) {
            return this.calculateHoursFromRecord(record).toFixed(2);
        },
        formatTotalHoursHM(decimalHours) {
            const totalMins = Math.round(decimalHours * 60);
            return `${Math.floor(totalMins / 60)} hrs ${totalMins % 60} mins`;
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
