<template>
    <div class="time-record-wrapper">
        <h4>Time Records</h4>
        <button
            class="btn btn-toggle d-md-none"
            @click="$parent.toggleFilters()"
        >
            <i class="fas fa-sliders-h"></i>
        </button>

        <form
            class="filter-controls"
            v-show="$parent.showFilters"
            @submit.prevent="$parent.fetchRecords()"
        >
            <fieldset>
                <label>Filter By Employee</label>
                <Select
                    v-model="$parent.filters.employee"
                    :options="['', ...$parent.employeeNames]"
                    placeholder="All"
                    size="small"
                    fluid
                    optionLabel=""
                    optionValue=""
                    class="w-full"
                >
                    <template #value="{ value }">
                        {{ value || "All" }}
                    </template>
                    <template #option="{ option }">
                        {{ option || "All" }}
                    </template>
                </Select>
            </fieldset>

            <!-- NEW: Employee Status Filter -->
            <fieldset>
                <label>Employee Status</label>
                <Select
                    v-model="employeeStatusFilter"
                    :options="statusOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Status"
                    size="small"
                    fluid
                    class="w-full"
                />
            </fieldset>

            <!-- NEW: Employee Location Filter -->
            <fieldset>
                <label>Employee Location</label>
                <Select
                    v-model="employeeLocationFilter"
                    :options="locationOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Location"
                    size="small"
                    fluid
                    class="w-full"
                />
            </fieldset>

            <fieldset>
                <label>Date From</label>
                <DatePicker
                    v-model="$parent.filters.dateFrom"
                    dateFormat="dd/mm/yy"
                    showIcon
                    placeholder="Select date"
                    class="w-full"
                    size="small"
                    fluid
                />
            </fieldset>

            <fieldset>
                <label>Date To</label>
                <DatePicker
                    v-model="$parent.filters.dateTo"
                    dateFormat="dd/mm/yy"
                    showIcon
                    placeholder="Select date"
                    class="w-full"
                    size="small"
                    fluid
                />
            </fieldset>

            <fieldset>
                <label></label>
                <Button
                    class="w-100"
                    @click="applyFilters"
                    size="small"
                    severity="info"
                >
                    Apply Filters
                </Button>
            </fieldset>
        </form>

        <!-- Mobile / Small screens: Card list -->
        <div class="d-md-none">
            <div
                class="tr-card shadow-sm rounded-3 mb-2 p-3"
                v-for="record in filteredTimeRecords"
                :key="record?.ID || record?.id"
            >
                <!-- Header -->
                <div class="d-flex justify-content-between">
                    <!-- Employee + Date -->
                    <div>
                        <div class="fw-semibold text-truncate">
                            {{ record?.Employee || "-" }}
                        </div>
                        <div class="text-secondary small">
                            {{ record?.DateToday || "-" }}
                        </div>
                    </div>

                    <div
                        class="d-flex justify-content-between align-items-start"
                    >
                        <div class="me-2">
                            <div class="small text-secondary">Clock ID</div>
                            <div class="fw-semibold">
                                {{ record?.ID || record?.id || "-" }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Times grid -->
                <div class="row g-2 mt-3">
                    <div class="col-6">
                        <div class="label text-secondary small">Time In</div>
                        <div class="value-history-text fw-semibold">
                            <p>
                                PH:
                                {{
                                    formatTimeWithTimezone(
                                        record?.TimeIn,
                                        record?.Employee,
                                    ).local
                                }}
                            </p>
                            <p>
                                US:
                                {{
                                    formatTimeWithTimezone(
                                        record?.TimeIn,
                                        record?.Employee,
                                    ).us
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="label text-secondary small">Time Out</div>
                        <div class="value-history-text fw-semibold">
                            <p>
                                PH:
                                {{
                                    formatTimeWithTimezone(
                                        record?.TimeOut,
                                        record?.Employee,
                                    ).local
                                }}
                            </p>
                            <p>
                                US:
                                {{
                                    formatTimeWithTimezone(
                                        record?.TimeOut,
                                        record?.Employee,
                                    ).us
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="label text-secondary small">
                            Break Start
                        </div>
                        <div class="value-history-text fw-semibold">
                            <p>
                                PH:
                                {{
                                    formatTimeWithTimezone(
                                        record?.shortbreak_start,
                                        record?.Employee,
                                    ).local
                                }}
                            </p>
                            <p>
                                US:
                                {{
                                    formatTimeWithTimezone(
                                        record?.shortbreak_start,
                                        record?.Employee,
                                    ).us
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="label text-secondary small">Break End</div>
                        <div class="value-history-text fw-semibold">
                            <p>
                                PH:
                                {{
                                    formatTimeWithTimezone(
                                        record?.shortbreak_end,
                                        record?.Employee,
                                    ).local
                                }}
                            </p>
                            <p>
                                US:
                                {{
                                    formatTimeWithTimezone(
                                        record?.shortbreak_end,
                                        record?.Employee,
                                    ).us
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="label text-secondary small">
                            Total Break
                        </div>
                        <div class="value-history-text fw-semibold">
                            {{ record?.shortbreak_totaltime || 0 }} mins
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mt-2">
                    <div class="label text-secondary small">Notes</div>
                    <Button
                        v-if="record?.Notes"
                        size="small"
                        text
                        severity="info"
                        label="View Note"
                        icon="pi pi-eye"
                        @click="openNotesModal(record)"
                        class="p-0"
                    />
                    <div v-else class="text-muted">-</div>
                </div>

                <!-- Actions -->
                <div class="d-grid mt-3 gap-2">
                    <Button
                        size="small"
                        severity="info"
                        @click="$parent.openEdit(record)"
                        label="Edit"
                    />
                    <Button
                        size="small"
                        severity="success"
                        @click="$parent.toggleHistory(record?.ID || record?.id)"
                        :aria-expanded="
                            $parent.expandedClockId ===
                            (record?.ID || record?.id)
                        "
                    >
                        <span
                            v-if="
                                $parent.expandedClockId ===
                                (record?.ID || record?.id)
                            "
                            >Hide</span
                        >
                        <span v-else>History</span>
                    </Button>
                </div>

                <!-- Inline history (mobile) -->
                <div
                    class="mt-3 border-top pt-3"
                    v-if="
                        $parent.expandedClockId === (record?.ID || record?.id)
                    "
                >
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <strong class="me-2">Edit History</strong>
                        <span
                            v-if="$parent.historyLoading"
                            class="spinner-border spinner-border-sm"
                        ></span>
                    </div>

                    <!-- No history -->
                    <div
                        v-if="
                            !$parent.historyLoading &&
                            (!$parent.clockEditHistory ||
                                !$parent.clockEditHistory.length)
                        "
                        class="text-muted small"
                    >
                        No edits recorded for this clock.
                    </div>

                    <!-- History list -->
                    <div v-else class="history-list">
                        <div
                            class="history-item py-2"
                            v-for="(h, idx) in $parent.clockEditHistory"
                            :key="idx"
                        >
                            <div class="d-flex justify-content-between">
                                <div class="small">
                                    <div class="text-secondary">Edited At</div>
                                    <div class="fw-semibold">
                                        {{ h.edited_at || h.created_at || "-" }}
                                    </div>
                                </div>
                                <div class="small text-end">
                                    <div class="text-secondary">Edited By</div>
                                    <div class="fw-semibold">
                                        {{
                                            h.edited_by ||
                                            h.user ||
                                            h.username ||
                                            "-"
                                        }}
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <template v-if="h.before && h.after">
                                    <ul class="mb-0 small ps-3">
                                        <li
                                            v-for="(
                                                chg, i
                                            ) in $parent.prettyDiff(
                                                h.before,
                                                h.after,
                                            )"
                                            :key="i"
                                        >
                                            <code>{{ chg.key }}</code
                                            >:
                                            <span
                                                class="text-decoration-line-through text-muted"
                                                >{{ chg.from }}</span
                                            >
                                            →
                                            <strong>{{ chg.to }}</strong>
                                        </li>
                                    </ul>
                                </template>
                                <template
                                    v-else-if="h.changes || h.delta || h.after"
                                >
                                    <pre class="small mb-0">{{
                                        h.changes || h.delta || h.after
                                    }}</pre>
                                </template>
                                <template v-else>
                                    <span class="text-muted small">—</span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /tr-card -->

            <!-- No records message for mobile -->
            <div
                v-if="filteredTimeRecords.length === 0"
                class="text-center text-secondary py-4"
            >
                <p>No time records found matching the selected filters.</p>
            </div>
        </div>

        <!-- Desktop / Medium+ screens: Enhanced table view -->
        <XDataTable
            :value="filteredTimeRecords"
            :columns="columns"
            tableClass="d-none d-md-block"
            :loading="$parent.loadingTimeRecords"
            :actionsFrozen="true"
        >
            <template #TimeIn="{ data }">
                <p>
                    PH:
                    {{
                        formatTimeWithTimezone(data.TimeIn, data.Employee).local
                    }}
                </p>
                <p>
                    US:
                    {{ formatTimeWithTimezone(data.TimeIn, data.Employee).us }}
                </p>
            </template>
            <template #TimeOut="{ data }">
                <p>
                    PH:
                    {{
                        formatTimeWithTimezone(data.TimeOut, data.Employee)
                            .local
                    }}
                </p>
                <p>
                    US:
                    {{ formatTimeWithTimezone(data.TimeOut, data.Employee).us }}
                </p>
            </template>
            <template #shortbreakStart="{ data }">
                <p>
                    PH:
                    {{
                        formatTimeWithTimezone(
                            data.shortbreak_start,
                            data.Employee,
                        ).local
                    }}
                </p>
                <p>
                    US:
                    {{
                        formatTimeWithTimezone(
                            data.shortbreak_start,
                            data.Employee,
                        ).us
                    }}
                </p>
            </template>
            <template #shortbreakEnd="{ data }">
                <p>
                    PH:
                    {{
                        formatTimeWithTimezone(
                            data.shortbreak_end,
                            data.Employee,
                        ).local
                    }}
                </p>
                <p>
                    US:
                    {{
                        formatTimeWithTimezone(
                            data.shortbreak_end,
                            data.Employee,
                        ).us
                    }}
                </p>
            </template>
            <template #shortbreakTotaltime="{ data }">
                <p>{{ data.shortbreak_totaltime || 0 }} mins</p>
            </template>
            <template #notes="{ data }">
                <div>
                    <Button
                        v-if="data.Notes"
                        style="width: 50px; height: 50px"
                        size="small"
                        text
                        severity="info"
                        icon="pi pi-eye"
                        @click="openNotesModal(data)"
                    />
                    <span v-else class="text-muted">-</span>
                </div>
            </template>
            <template #actions="{ data }">
                <div class="d-flex flex-column gap-2 align-items-start">
                    <Button
                        label="Edit"
                        size="small"
                        variant="text"
                        severity="contrast"
                        class="text-primary"
                        icon="pi pi-pencil"
                        @click.stop="$parent.openEdit(data)"
                    />
                    <Button
                        label="Edit History"
                        size="small"
                        variant="text"
                        severity="contrast"
                        class="text-success"
                        icon="pi pi-history"
                        @click="handleOpenEditHistoryModal(data.ID)"
                    />
                </div>
            </template>

            <template #empty>
                <div class="text-center text-secondary py-3">
                    No time records found matching the selected filters.
                </div>
            </template>
        </XDataTable>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <button
                class="btn btn-outline-secondary"
                :disabled="$parent.page === 1"
                @click="$parent.prevPage()"
            >
                Previous
            </button>

            <span>Page {{ $parent.page }} / {{ $parent.totalPages }}</span>

            <button
                class="btn btn-outline-secondary"
                :disabled="$parent.page >= $parent.totalPages"
                @click="$parent.nextPage()"
            >
                Next
            </button>
        </div>
    </div>

    <Dialog
        v-model:visible="showEditHistoryModal"
        modal
        header="Edit History"
        :style="{ width: '50%' }"
        :pt="{
            root: { class: 'mobile-fullscreen-dialog' },
        }"
    >
        <div>
            <XDataTable
                :value="$parent.clockEditHistory"
                :columns="editHistoryColumns"
            >
                <template #editedAt="{ data }">
                    <div>
                        <p>{{ data.edited_at || data.created_at || "-" }}</p>
                    </div>
                </template>
                <template #editedBy="{ data }">
                    <p>
                        {{
                            data.edited_by || data.user || data.username || "-"
                        }}
                    </p>
                </template>
                <template #changes="{ data }">
                    <template v-if="data.before && data.after">
                        <ul class="mb-0 small">
                            <li
                                v-for="(chg, i) in $parent.prettyDiff(
                                    data.before,
                                    data.after,
                                )"
                                :key="i"
                            >
                                <code>{{ chg.key }}</code
                                >:
                                <span
                                    class="text-decoration-line-through text-muted"
                                    >{{ chg.from }}</span
                                >
                                →
                                <strong>{{ chg.to }}</strong>
                            </li>
                        </ul>
                    </template>
                    <template
                        v-else-if="data.changes || data.delta || data.after"
                    >
                        <pre class="small mb-0">{{
                            data.changes || data.delta || data.after
                        }}</pre>
                    </template>
                    <template v-else>
                        <span class="text-muted small">—</span>
                    </template>
                </template>
            </XDataTable>
        </div>
    </Dialog>

    <!-- Notes Modal -->
    <Dialog
        v-model:visible="showNotesModal"
        modal
        :header="`Notes - Clock ID: ${selectedRecord?.ID || selectedRecord?.id}`"
        :style="{ width: '600px', maxWidth: '90vw' }"
        :pt="{
            root: { class: 'mobile-fullscreen-dialog' },
        }"
    >
        <div class="notes-modal-content">
            <div class="mb-3">
                <div class="fw-semibold mb-1">Employee:</div>
                <div>{{ selectedRecord?.Employee || "-" }}</div>
            </div>
            <div class="mb-3">
                <div class="fw-semibold mb-1">Date:</div>
                <div>{{ selectedRecord?.DateToday || "-" }}</div>
            </div>
            <div>
                <div class="fw-semibold mb-2">Notes:</div>
                <div
                    class="p-3 bg-light rounded"
                    style="
                        white-space: pre-wrap;
                        word-break: break-word;
                        max-height: 400px;
                        overflow-y: auto;
                    "
                >
                    {{ selectedRecord?.Notes || "No notes available" }}
                </div>
            </div>
        </div>
        <template #footer>
            <Button
                label="Close"
                severity="secondary"
                @click="closeNotesModal"
            />
        </template>
    </Dialog>

    <!-- Edit Modal -->
    <div v-if="$parent.showEditModal" class="modal modal-editRecord">
        <div class="modal-overlay" @click="$parent.closeEdit"></div>

        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Edit Time Record (ID:
                    {{ $parent.editOriginal?.ID }})
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    @click="$parent.closeEdit"
                ></button>
            </div>

            <div class="modal-body">
                <form>
                    <fieldset>
                        <label>Employee</label>
                        <input
                            type="text"
                            class="form-control"
                            v-model="$parent.editForm.Employee"
                            disabled
                        />
                    </fieldset>

                    <fieldset>
                        <label>Date</label>
                        <input
                            type="date"
                            class="form-control"
                            v-model="$parent.editForm.DateToday"
                        />
                    </fieldset>

                    <fieldset>
                        <label>Time In</label>
                        <input
                            type="datetime-local"
                            class="form-control"
                            v-model="$parent.editForm.TimeIn_local"
                        />
                    </fieldset>

                    <fieldset>
                        <label>Time Out</label>
                        <input
                            type="datetime-local"
                            class="form-control"
                            v-model="$parent.editForm.TimeOut_local"
                        />
                    </fieldset>

                    <fieldset>
                        <label>Break Start</label>
                        <input
                            type="datetime-local"
                            class="form-control"
                            v-model="$parent.editForm.shortbreak_start_local"
                        />
                    </fieldset>

                    <fieldset>
                        <label>Break End</label>
                        <input
                            type="datetime-local"
                            class="form-control"
                            v-model="$parent.editForm.shortbreak_end_local"
                        />
                    </fieldset>

                    <fieldset>
                        <label>Break Total (mins)</label>
                        <input
                            type="number"
                            min="0"
                            class="form-control"
                            v-model.number="
                                $parent.editForm.shortbreak_totaltime
                            "
                        />
                    </fieldset>

                    <fieldset>
                        <label></label>
                    </fieldset>

                    <fieldset>
                        <label>Notes</label>
                        <textarea
                            class="form-control"
                            rows="3"
                            v-model="$parent.editForm.Notes"
                        >
                        </textarea>
                    </fieldset>
                </form>
            </div>

            <div class="modal-footer">
                <button
                    class="btn btn-primary text-white m-0"
                    @click="$parent.submitEdit"
                    :disabled="$parent.submittingEdit"
                >
                    Save changes
                </button>

                <button
                    class="btn btn-secondary text-white m-0"
                    @click="$parent.closeEdit"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { Select, DatePicker, Button, Dialog } from "primevue";
import XDataTable from "../../../components/DataTable/XDataTable.vue";

const TABLE_COLUMNS = [
    {
        header: "Clock Id",
        field: "ID",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Employee",
        field: "Employee",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Date",
        field: "DateToday",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Time In",
        slot: "TimeIn",
        bodyStyle:
            "font-size: 14px;word-break: break-word; white-space: normal;",
        style: { minWidth: "7rem" },
    },
    {
        header: "Time Out",
        slot: "TimeOut",
        bodyStyle:
            "font-size: 14px;word-break: break-word; white-space: normal;",
        style: { minWidth: "7rem" },
    },
    {
        header: "Break Start",
        slot: "shortbreakStart",
        bodyStyle:
            "font-size: 14px;word-break: break-word; white-space: normal;",
        style: { minWidth: "7rem" },
    },
    {
        header: "Break End",
        slot: "shortbreakEnd",
        bodyStyle:
            "font-size: 14px;word-break: break-word; white-space: normal;",
        style: { minWidth: "7rem" },
    },
    {
        header: "Total",
        slot: "shortbreakTotaltime",
        bodyStyle:
            "font-size: 14px;word-break: break-word; white-space: normal;",
        style: { minWidth: "10rem" },
    },
    {
        header: "Notes",
        slot: "notes",
        bodyStyle:
            "font-size: 14px; word-break: break-word; white-space: normal;",
    },
];

const TABLE_EDIT_HISTORY_COLUMNS = [
    {
        header: "Edited At",
        slot: "editedAt",
        style: { verticalAlign: "top" },
    },
    {
        header: "Edited By",
        slot: "editedBy",
        style: { verticalAlign: "top" },
    },
    {
        header: "Changes",
        slot: "changes",
        style: { fontSize: "14px" },
    },
];

export default {
    components: {
        Select,
        DatePicker,
        Button,
        XDataTable,
        Dialog,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            editHistoryColumns: TABLE_EDIT_HISTORY_COLUMNS,
            showEditHistoryModal: false,
            showNotesModal: false,
            selectedRecord: null,
            employeeStatusFilter: "all",
            employeeLocationFilter: "all",
            statusOptions: [
                { label: "All Status", value: "all" },
                { label: "Active", value: "active" },
                { label: "Inactive", value: "inactive" },
            ],
            locationOptions: [
                { label: "All Locations", value: "all" },
                { label: "Philippines", value: "PH" },
                { label: "United States", value: "US" },
            ],
            employeesData: [], // Cache employee data with their status/location
        };
    },
    computed: {
        filteredTimeRecords() {
            let records = this.$parent?.timeRecords || [];

            // Filter by employee status and location
            if (
                this.employeeStatusFilter !== "all" ||
                this.employeeLocationFilter !== "all"
            ) {
                records = records.filter((record) => {
                    const employee = this.employeesData.find(
                        (emp) =>
                            emp.username === record.Employee ||
                            emp.name === record.Employee,
                    );

                    if (!employee) return true; // Show if employee not found

                    // Filter by status
                    if (this.employeeStatusFilter !== "all") {
                        const isActive =
                            employee.active === true ||
                            employee.active === 1 ||
                            employee.is_active === true ||
                            employee.status === "active";
                        const matchesStatus =
                            this.employeeStatusFilter === "active"
                                ? isActive
                                : !isActive;
                        if (!matchesStatus) return false;
                    }

                    // Filter by location
                    if (this.employeeLocationFilter !== "all") {
                        const empLocation = (
                            employee.accounttype ||
                            employee.location ||
                            ""
                        ).toUpperCase();
                        if (empLocation !== this.employeeLocationFilter)
                            return false;
                    }

                    return true;
                });
            }

            return records;
        },
    },
    methods: {
        async handleOpenEditHistoryModal(id) {
            try {
                await this.$parent.toggleHistory(id);
                this.showEditHistoryModal = true;
            } catch (error) {
                console.log(error);
            }
        },
        openNotesModal(record) {
            this.selectedRecord = record;
            this.showNotesModal = true;
        },
        closeNotesModal() {
            this.showNotesModal = false;
            this.selectedRecord = null;
        },

        // ADD THIS NEW METHOD HERE:
        formatTimeWithTimezone(isoDateTime, employeeName) {
            if (
                !isoDateTime ||
                isoDateTime === "--:--" ||
                isoDateTime === "-"
            ) {
                return { local: "--:--:--", us: "--:--:--" };
            }

            try {
                let timeString = String(isoDateTime);
                let dateString = null;

                if (timeString.includes(" ") && timeString.length > 10) {
                    const parts = timeString.split(" ");
                    dateString = parts[0]; // "2025-09-09"
                    timeString = parts[1]; // "14:19:19"
                }

                // Parse HH:MM:SS
                const [hours, minutes, seconds] = timeString
                    .split(":")
                    .map((s) => parseInt(s) || 0);

                // PH/Manila 12-hour format (stored as-is)
                const period = hours >= 12 ? "PM" : "AM";
                const hour12 = hours % 12 || 12;
                const localTime = `${hour12}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")} ${period}`;

                // Build a real Date in Manila time so we can ask Intl what LA's offset is on that date
                const isoForParsing = dateString
                    ? `${dateString}T${timeString}+08:00` // Manila = UTC+8, always fixed
                    : `1970-01-01T${timeString}+08:00`;

                const recordDate = new Date(isoForParsing);

                // Ask the browser: what time is it in LA at this exact moment?
                const laParts = new Intl.DateTimeFormat("en-US", {
                    timeZone: "America/Los_Angeles",
                    hour: "numeric",
                    minute: "2-digit",
                    second: "2-digit",
                    hour12: true,
                    timeZoneName: "short", // gives "PST" or "PDT"
                }).formatToParts(recordDate);

                const get = (type) =>
                    laParts.find((p) => p.type === type)?.value ?? "";

                const usTime = `${get("hour")}:${get("minute")}:${get("second")} ${get("dayPeriod")} ${get("timeZoneName")}`;

                return { local: localTime, us: usTime };
            } catch (error) {
                console.error("Error formatting time:", error, isoDateTime);
                return { local: "--:--:--", us: "--:--:--" };
            }
        },

        async loadEmployeesData() {
            // You'll need to fetch or access employee data
            // This assumes you have access to employees via parent or need to fetch
            try {
                // Option 1: If parent has employees
                if (this.$parent.employees) {
                    this.employeesData = this.$parent.employees;
                }
                // Option 2: Fetch from API
                // const response = await fetch('/api/employees');
                // this.employeesData = await response.json();
            } catch (error) {
                console.error("Failed to load employees data:", error);
            }
        },
        applyFilters() {
            // Call parent fetchRecords and your local filtering will apply automatically
            this.$parent.fetchRecords();
        },
    },
    mounted() {
        console.log(this.$parent.timeRecords);
        this.loadEmployeesData();
    },
};
</script>

<style scoped src="../hr.css"></style>
