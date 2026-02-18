<template>
    <div class="payroll-wrapper">
        <div class="payroll-header">
            <h4>Payroll Management</h4>
            <Button @click="openCreatePayslip" size="small" severity="info">
                Create Payslip
            </Button>
        </div>

        <!-- Payslips Table -->
        <XDataTable
            :value="payslips"
            :columns="payslipColumns"
            :loading="loadingPayslips"
            :actionsFrozen="true"
            tableClass="mt-3"
        >
            <template #cutoffDates="{ data }">
                <div>
                    {{ formatDate(data.cutoff_from) }} -
                    {{ formatDate(data.cutoff_to) }}
                </div>
            </template>
            <template #payoutDate="{ data }">
                <div>{{ formatDate(data.payout_date) }}</div>
            </template>
            <template #status="{ data }">
                <span
                    :class="{
                        badge: true,
                        'bg-secondary': data.status === 'draft',
                        'bg-success': data.status === 'approved',
                        'bg-primary': data.status === 'paid',
                    }"
                >
                    {{ data.status.toUpperCase() }}
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
                        @click="viewPayslip(data)"
                    />
                    <Button
                        label="Edit"
                        size="small"
                        variant="text"
                        severity="contrast"
                        class="text-primary"
                        icon="pi pi-pencil"
                        @click="editPayslip(data)"
                        v-if="data.status === 'draft'"
                    />
                    <Button
                        label="Delete"
                        size="small"
                        variant="text"
                        severity="contrast"
                        class="text-danger"
                        icon="pi pi-trash"
                        @click="deletePayslip(data)"
                        v-if="data.status === 'draft'"
                    />
                </div>
            </template>

            <template #empty>
                <div class="text-center text-secondary py-3">
                    No payslips found. Create your first payslip to get started.
                </div>
            </template>
        </XDataTable>

        <!-- Pagination -->
        <div
            class="d-flex justify-content-between align-items-center mt-3"
            v-if="payslips.length > 0"
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

    <!-- Create Payslip Dialog -->
    <Dialog
        v-model:visible="showCreatePayslip"
        modal
        header="Create Payslip"
        :style="{ width: '70%' }"
        :pt="{
            root: { class: 'mobile-fullscreen-dialog' },
        }"
    >
        <form @submit.prevent="savePayslip">
            <fieldset>
                <label>Employee: </label>
                <Select
                    v-model="selectedEmployee"
                    :options="employees"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Select an employee"
                    :loading="loadingEmployees"
                    class="w-full"
                    required
                />
            </fieldset>

            <fieldset>
                <label>Pay Out: </label>
                <InputText
                    v-model="payoutDate"
                    type="date"
                    class="form-control"
                    required
                />
            </fieldset>

            <fieldset>
                <label>Cut off from - to: </label>
                <div class="d-flex gap-2">
                    <InputText
                        v-model="cutoffDateFrom"
                        type="date"
                        class="form-control"
                        required
                    />
                    <InputText
                        v-model="cutoffDateTo"
                        type="date"
                        class="form-control"
                        required
                    />
                </div>

                <!-- Display attendance data table -->
                <div
                    v-if="cutoffDateFrom && cutoffDateTo && selectedEmployee"
                    class="mt-3"
                >
                    <div
                        class="d-flex justify-content-between align-items-center mb-2"
                    >
                        <strong>Attendance Records</strong>
                        <div class="d-flex gap-2 align-items-center">
                            <span
                                v-if="loadingAttendance"
                                class="spinner-border spinner-border-sm"
                            ></span>
                            <Button
                                v-if="
                                    attendanceRecords &&
                                    attendanceRecords.length > 0
                                "
                                :icon="
                                    showAttendanceTable
                                        ? 'pi pi-eye-slash'
                                        : 'pi pi-eye'
                                "
                                :label="
                                    showAttendanceTable
                                        ? 'Hide Table'
                                        : 'Show Table'
                                "
                                size="small"
                                text
                                severity="secondary"
                                @click="
                                    showAttendanceTable = !showAttendanceTable
                                "
                            />
                        </div>
                    </div>

                    <!-- Loading state -->
                    <div v-if="loadingAttendance" class="text-center py-3">
                        <span class="text-muted"
                            >Loading attendance data...</span
                        >
                    </div>

                    <!-- No data -->
                    <div
                        v-else-if="
                            !attendanceRecords || attendanceRecords.length === 0
                        "
                        class="text-muted small text-center py-3"
                    >
                        No attendance records found for this period.
                    </div>

                    <!-- Attendance table (with toggle) -->
                    <div v-else>
                        <!-- Summary (always visible) -->
                        <div class="p-2 bg-light rounded mb-3">
                            <div>
                                <strong>Total Days:</strong>
                                {{ attendanceRecords.length }}
                            </div>
                            <div>
                                <strong>Total Hours:</strong>
                                {{ calculateGrandTotal() }}
                            </div>
                        </div>

                        <!-- Table (toggleable) -->
                        <div
                            v-show="showAttendanceTable"
                            class="table-responsive"
                            style="max-height: 400px; overflow-y: auto"
                        >
                            <table class="table table-sm table-bordered">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Date</th>
                                        <th>Total Hours Worked</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(
                                            record, idx
                                        ) in attendanceRecords"
                                        :key="idx"
                                    >
                                        <td>{{ record.DateToday || "-" }}</td>
                                        <td>
                                            {{ calculateTotalHours(record) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td><strong>Total</strong></td>
                                        <td>
                                            <strong>{{
                                                calculateGrandTotal()
                                            }}</strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </fieldset>

            <!-- Holiday Detection Section with AUTO-CALCULATED hours -->
            <fieldset v-if="holidays.length > 0">
                <label class="fw-semibold"
                    >Detected Holidays in Cutoff Period:
                </label>
                <div class="p-3 border rounded bg-light">
                    <!-- Show only holidays that were actually worked -->
                    <template v-for="(holiday, index) in holidays" :key="index">
                        <div
                            v-if="holiday.actualHoursWorked > 0"
                            class="mb-3 pb-3 border-bottom"
                        >
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="fw-semibold">
                                        {{ holiday.title }}
                                    </div>
                                    <div class="text-muted small">
                                        {{ formatDate(holiday.holidate) }}
                                    </div>
                                    <span
                                        class="badge mt-1"
                                        :class="
                                            holiday.status === 'regular'
                                                ? 'bg-primary'
                                                : 'bg-warning'
                                        "
                                    >
                                        {{
                                            holiday.status === "regular"
                                                ? "Regular Holiday"
                                                : "Special Non-Working"
                                        }}
                                    </span>
                                </div>
                                <div class="col-md-3 text-center">
                                    <label class="small text-muted d-block"
                                        >Hours Worked</label
                                    >
                                    <div class="fs-5 fw-bold text-success">
                                        {{
                                            holiday.actualHoursWorked.toFixed(2)
                                        }}
                                        hrs
                                    </div>
                                    <small class="text-success">Worked</small>
                                </div>
                                <div class="col-md-3 text-end">
                                    <label class="small text-muted d-block"
                                        >Holiday Pay</label
                                    >
                                    <div class="fs-5 fw-bold text-primary">
                                        {{
                                            selectedEmployeeData?.current_currency
                                        }}
                                        {{
                                            formatCurrency(
                                                calculateHolidayPay(holiday),
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>

                            <!-- Show time record details -->
                            <div
                                v-if="holiday.timeRecord"
                                class="mt-2 p-2 bg-white rounded small"
                            >
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Time In:</strong>
                                        {{
                                            formatTime(
                                                holiday.timeRecord.TimeIn,
                                            )
                                        }}
                                    </div>
                                    <div class="col-6">
                                        <strong>Time Out:</strong>
                                        {{
                                            formatTime(
                                                holiday.timeRecord.TimeOut,
                                            )
                                        }}
                                    </div>
                                    <div
                                        class="col-12 mt-1"
                                        v-if="
                                            holiday.timeRecord
                                                .shortbreak_totaltime
                                        "
                                    >
                                        <strong>Break:</strong>
                                        {{
                                            holiday.timeRecord
                                                .shortbreak_totaltime
                                        }}
                                        mins
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="mt-3 pt-3 border-top">
                        <div class="row">
                            <div class="col-6">
                                <div class="d-flex justify-content-between">
                                    <span>Regular Holiday Pay:</span>
                                    <span class="fw-semibold">
                                        {{
                                            selectedEmployeeData?.current_currency
                                        }}
                                        {{
                                            formatCurrency(
                                                calculateRegularHolidayPayTotal(),
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between">
                                    <span>Special Holiday Pay:</span>
                                    <span class="fw-semibold">
                                        {{
                                            selectedEmployeeData?.current_currency
                                        }}
                                        {{
                                            formatCurrency(
                                                calculateSpecialHolidayPayTotal(),
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 mt-2 pt-2 border-top">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold"
                                        >Total Holiday Pay:</span
                                    >
                                    <span class="fw-bold text-success">
                                        {{
                                            selectedEmployeeData?.current_currency
                                        }}
                                        {{
                                            formatCurrency(
                                                calculateTotalHolidayPay(),
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>

            <!-- Show message if no holidays were worked -->
            <fieldset
                v-else-if="
                    cutoffDateFrom &&
                    cutoffDateTo &&
                    selectedEmployee &&
                    !loadingHolidays &&
                    attendanceRecords.length > 0
                "
            >
                <div class="alert alert-info">
                    <i class="pi pi-info-circle"></i> No holidays were worked
                    during this cutoff period.
                </div>
            </fieldset>

            <!-- Deductions Section -->
            <fieldset>
                <div
                    class="d-flex justify-content-between align-items-center mb-2"
                >
                    <label class="mb-0 fw-semibold">Deductions: </label>
                    <Button
                        label="Add Deduction"
                        icon="pi pi-plus"
                        size="small"
                        severity="success"
                        text
                        @click="addDeduction"
                        type="button"
                    />
                </div>

                <div
                    v-if="deductions.length === 0"
                    class="text-muted small p-3 bg-light rounded text-center"
                >
                    No deductions added. Click "Add Deduction" to add one.
                </div>

                <div
                    v-for="(deduction, index) in deductions"
                    :key="index"
                    class="deduction-item mb-3 p-3 border rounded bg-white"
                >
                    <!-- ... existing deduction fields ... -->
                </div>
            </fieldset>

            <!-- Summary Preview -->
            <!-- <fieldset v-if="selectedEmployee && attendanceRecords.length > 0">
                <label class="fw-semibold">Payment Summary: </label>
                <div class="p-3 border rounded bg-light">
                    <div class="row">
                        <div class="col-12 mb-2">
                            <div class="d-flex justify-content-between">
                                <span>Basic Pay:</span>
                                <span class="fw-semibold"
                                    >{{
                                        selectedEmployeeData?.current_currency
                                    }}
                                    {{
                                        formatCurrency(calculateBasicPay())
                                    }}</span
                                >
                            </div>
                        </div>
                        <div
                            class="col-12 mb-2"
                            v-if="calculateTotalHolidayPay() > 0"
                        >
                            <div class="d-flex justify-content-between">
                                <span>Holiday Pay:</span>
                                <span class="fw-semibold"
                                    >{{
                                        selectedEmployeeData?.current_currency
                                    }}
                                    {{
                                        formatCurrency(
                                            calculateTotalHolidayPay(),
                                        )
                                    }}</span
                                >
                            </div>
                        </div>
                        <div class="col-12 mb-2 border-top pt-2">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">Gross Pay:</span>
                                <span class="fw-semibold text-success"
                                    >{{
                                        selectedEmployeeData?.current_currency
                                    }}
                                    {{
                                        formatCurrency(calculateGrossPay())
                                    }}</span
                                >
                            </div>
                        </div>
                        <div
                            class="col-12 mb-2"
                            v-if="calculateTotalActiveDeductions() > 0"
                        >
                            <div class="d-flex justify-content-between">
                                <span class="text-danger"
                                    >Total Deductions:</span
                                >
                                <span class="fw-semibold text-danger"
                                    >-
                                    {{ selectedEmployeeData?.current_currency }}
                                    {{
                                        formatCurrency(
                                            calculateTotalActiveDeductions(),
                                        )
                                    }}</span
                                >
                            </div>
                        </div>
                        <div class="col-12 border-top pt-2">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Net Pay:</span>
                                <span class="fw-bold text-primary fs-5"
                                    >{{
                                        selectedEmployeeData?.current_currency
                                    }}
                                    {{
                                        formatCurrency(calculateNetPay())
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset> -->
        </form>

        <template #footer>
            <div class="d-flex justify-content-between w-100">
                <Button
                    label="Cancel"
                    severity="secondary"
                    @click="closeCreatePayslip"
                    :disabled="saving"
                />
                <Button
                    label="Save Payslip"
                    severity="success"
                    @click="savePayslip"
                    :loading="saving"
                    :disabled="!canSave"
                />
            </div>
        </template>
    </Dialog>

    <!-- View Payslip Dialog -->
    <Dialog
        v-model:visible="showViewPayslip"
        modal
        header="Pay Slip"
        :style="{ width: '800px', maxWidth: '95vw' }"
        :pt="{
            root: { class: 'mobile-fullscreen-dialog payslip-print-dialog' },
        }"
    >
        <div v-if="viewingPayslip" class="payslip-view">
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
                                viewingPayslip.employee_name
                            }}</span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="d-flex">
                            <span class="info-label" style="min-width: 150px"
                                >Employee Number:</span
                            >
                            <span class="fw-semibold">{{
                                viewingPayslip.employee_id
                            }}</span>
                        </div>
                    </div>
                    <div class="col-md-12 mb-2">
                        <div class="d-flex">
                            <span class="info-label" style="min-width: 150px"
                                >Address:</span
                            >
                            <span class="fw-semibold">-</span>
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
                                {{ formatDate(viewingPayslip.cutoff_from) }} -
                                {{ formatDate(viewingPayslip.cutoff_to) }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="d-flex">
                            <span class="info-label" style="min-width: 150px"
                                >Payout Date:</span
                            >
                            <span class="fw-semibold">{{
                                formatDate(viewingPayslip.payout_date)
                            }}</span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="d-flex">
                            <span class="info-label" style="min-width: 150px"
                                >Release Date:</span
                            >
                            <span class="fw-semibold">{{
                                formatDate(viewingPayslip.payout_date)
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
                                        {{ viewingPayslip.total_hours }} hrs
                                    </td>
                                </tr>
                                <tr>
                                    <td>Days Worked:</td>
                                    <td class="fw-semibold">
                                        {{ viewingPayslip.total_days }} days
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
                                        {{ viewingPayslip.total_hours }} hrs
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
                                        {{ viewingPayslip.currency }}
                                        {{
                                            formatCurrency(
                                                viewingPayslip.basic_pay,
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        parseFloat(
                                            viewingPayslip.regular_holiday_pay,
                                        ) > 0
                                    "
                                >
                                    <td>Holiday</td>
                                    <td class="text-end">
                                        {{ viewingPayslip.currency }}
                                        {{
                                            formatCurrency(
                                                viewingPayslip.regular_holiday_pay,
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        parseFloat(
                                            viewingPayslip.special_holiday_pay,
                                        ) > 0
                                    "
                                >
                                    <td>Holiday Overtime</td>
                                    <td class="text-end">
                                        {{ viewingPayslip.currency }}
                                        {{
                                            formatCurrency(
                                                viewingPayslip.special_holiday_pay,
                                            )
                                        }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        parseFloat(
                                            viewingPayslip.regular_holiday_pay,
                                        ) === 0
                                    "
                                >
                                    <td>Overtime</td>
                                    <td class="text-end">
                                        {{ viewingPayslip.currency }} 0.00
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        parseFloat(
                                            viewingPayslip.regular_holiday_pay,
                                        ) === 0
                                    "
                                >
                                    <td>Holiday</td>
                                    <td class="text-end">
                                        {{ viewingPayslip.currency }} 0.00
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        parseFloat(
                                            viewingPayslip.special_holiday_pay,
                                        ) === 0
                                    "
                                >
                                    <td>Holiday Overtime</td>
                                    <td class="text-end">
                                        {{ viewingPayslip.currency }} 0.00
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>TOTAL EARNINGS</td>
                                    <td class="text-end">
                                        {{ viewingPayslip.currency }}
                                        {{
                                            formatCurrency(
                                                viewingPayslip.gross_pay,
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
                                <template
                                    v-if="
                                        activeDeductions &&
                                        activeDeductions.length > 0
                                    "
                                >
                                    <!-- Show only active deductions -->
                                    <tr
                                        v-for="(
                                            deduction, index
                                        ) in activeDeductions"
                                        :key="'active-' + index"
                                    >
                                        <td>{{ deduction.name }}</td>
                                        <td class="text-end">
                                            {{ viewingPayslip.currency }}
                                            {{
                                                formatCurrency(deduction.amount)
                                            }}
                                        </td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <!-- Default deductions when no custom deductions -->
                                    <tr>
                                        <td>SSS</td>
                                        <td class="text-end">
                                            {{ viewingPayslip.currency }} 0.00
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>PAGIBIG</td>
                                        <td class="text-end">
                                            {{ viewingPayslip.currency }} 0.00
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>PHILHEALTH</td>
                                        <td class="text-end">
                                            {{ viewingPayslip.currency }} 0.00
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>TOTAL DEDUCTIONS</td>
                                    <td class="text-end">
                                        {{ viewingPayslip.currency }}
                                        {{
                                            formatCurrency(
                                                viewingPayslip.deductions || 0,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Additional Deductions Info (Inactive deductions) -->
            <div
                class="section mb-4"
                v-if="inactiveDeductions && inactiveDeductions.length > 0"
            >
                <h6 class="section-title fw-bold mb-3 text-muted">
                    EXCLUDED DEDUCTIONS (Not included in computation)
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-secondary">
                            <tr>
                                <th>Deduction Name</th>
                                <th class="text-end">Amount</th>
                                <th>Description</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(deduction, index) in inactiveDeductions"
                                :key="index"
                            >
                                <td>{{ deduction.name }}</td>
                                <td class="text-end">
                                    {{ viewingPayslip.currency }}
                                    {{ formatCurrency(deduction.amount) }}
                                </td>
                                <td class="text-muted small">
                                    {{ deduction.description || "-" }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"
                                        >Excluded</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- NET SALARY -->
            <div class="section">
                <div
                    class="bg-dark text-white p-3 d-flex justify-content-between align-items-center"
                >
                    <h5 class="mb-0 fw-bold">NET SALARY</h5>
                    <h5 class="mb-0 fw-bold">
                        {{ viewingPayslip.currency }}
                        {{
                            formatCurrency(
                                viewingPayslip.net_pay ||
                                    viewingPayslip.gross_pay,
                            )
                        }}
                    </h5>
                </div>
            </div>

            <!-- Additional Notes Section (if deductions have descriptions) -->
            <div
                class="section mt-4"
                v-if="deductionsWithNotes && deductionsWithNotes.length > 0"
            >
                <h6 class="section-title fw-bold mb-3">DEDUCTION NOTES</h6>
                <div class="card">
                    <div class="card-body">
                        <div
                            v-for="(deduction, index) in deductionsWithNotes"
                            :key="index"
                            class="mb-2"
                        >
                            <strong>{{ deduction.name }}:</strong>
                            <p class="mb-1 text-muted small">
                                {{ deduction.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="d-flex justify-content-end gap-2 w-100">
                <Button
                    label="Close"
                    severity="secondary"
                    @click="closeViewPayslip"
                />
                <Button
                    label="Print"
                    severity="info"
                    icon="pi pi-print"
                    @click="printPayslip"
                />
            </div>
        </template>
    </Dialog>
</template>

<script>
import { Button, Select, InputText, Dialog } from "primevue";
import XDataTable from "../../../components/DataTable/XDataTable.vue";
import axios from "axios";

const PAYSLIP_COLUMNS = [
    {
        header: "Employee",
        field: "employee_name",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Pay Out Date",
        slot: "payoutDate",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Cutoff Period",
        slot: "cutoffDates",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Status",
        slot: "status",
        bodyStyle: "font-size: 14px; text-align: center;",
        style: { width: "120px" },
    },
];

export default {
    components: {
        Button,
        Select,
        InputText,
        Dialog,
        XDataTable,
    },
    data() {
        return {
            showCreatePayslip: false,
            showViewPayslip: false,
            viewingPayslip: null,
            employees: [],
            selectedEmployee: null,
            loadingEmployees: false,
            payoutDate: null,
            cutoffDateFrom: null,
            cutoffDateTo: null,
            attendanceRecords: [],
            loadingAttendance: false,
            showAttendanceTable: false,
            holidays: [],
            loadingHolidays: false,
            saving: false,
            payslips: [],
            loadingPayslips: false,
            payslipColumns: PAYSLIP_COLUMNS,
            currentPage: 1,
            perPage: 10,
            totalPages: 1,
            deductions: [],
        };
    },
    computed: {
        sselectedEmployeeData() {
            if (!this.selectedEmployee) return null;
            return this.employees.find(
                (emp) => emp.id === this.selectedEmployee,
            );
        },
        canSave() {
            return (
                this.selectedEmployee &&
                this.payoutDate &&
                this.cutoffDateFrom &&
                this.cutoffDateTo &&
                !this.saving
            );
        },
        totalHoursWorked() {
            if (
                !this.attendanceRecords ||
                this.attendanceRecords.length === 0
            ) {
                return 0;
            }

            let totalHours = 0;
            this.attendanceRecords.forEach((record) => {
                const timeIn = this.parseDateTime(record.TimeIn);
                const timeOut = this.parseDateTime(record.TimeOut);

                if (timeIn && timeOut) {
                    let totalMs = timeOut - timeIn;

                    const breakStart = this.parseDateTime(
                        record.shortbreak_start,
                    );
                    const breakEnd = this.parseDateTime(record.shortbreak_end);

                    if (breakStart && breakEnd) {
                        const breakMs = breakEnd - breakStart;
                        totalMs -= breakMs;
                    } else if (record.shortbreak_totaltime) {
                        const breakMs = record.shortbreak_totaltime * 60 * 1000;
                        totalMs -= breakMs;
                    }

                    totalHours += totalMs / (1000 * 60 * 60);
                }
            });

            return totalHours;
        },
        parsedDeductions() {
            if (!this.viewingPayslip) {
                return [];
            }

            if (!this.viewingPayslip.deduction_details) {
                return [];
            }

            try {
                if (typeof this.viewingPayslip.deduction_details === "object") {
                    return this.viewingPayslip.deduction_details;
                }

                if (typeof this.viewingPayslip.deduction_details === "string") {
                    return JSON.parse(this.viewingPayslip.deduction_details);
                }

                return [];
            } catch (error) {
                console.error("Error parsing deductions:", error);
                return [];
            }
        },
        activeDeductions() {
            return this.parsedDeductions.filter(
                (d) => d.active === true || d.active === 1,
            );
        },
        inactiveDeductions() {
            return this.parsedDeductions.filter(
                (d) => d.active === false || d.active === 0,
            );
        },
        deductionsWithNotes() {
            return this.parsedDeductions.filter(
                (d) => d.description && d.description.trim() !== "",
            );
        },
    },
    watch: {
        cutoffDateFrom(newVal) {
            if (newVal && this.cutoffDateTo && this.selectedEmployee) {
                this.fetchAttendanceData();
            }
        },
        cutoffDateTo(newVal) {
            if (newVal && this.cutoffDateFrom && this.selectedEmployee) {
                this.fetchAttendanceData();
            }
        },
        selectedEmployee(newVal) {
            if (newVal && this.cutoffDateFrom && this.cutoffDateTo) {
                this.fetchAttendanceData();
            }
        },
        attendanceRecords(newVal) {
            if (newVal && newVal.length > 0 && this.holidays.length > 0) {
                this.matchHolidaysWithTimeRecords();
            }
        },
    },
    mounted() {
        this.fetchPayslips();
    },
    methods: {
        async fetchPayslips() {
            this.loadingPayslips = true;
            try {
                const response = await axios.get("/hr/payslips", {
                    params: {
                        page: this.currentPage,
                        per_page: this.perPage,
                    },
                });
                this.payslips = response.data.data || response.data;
                this.totalPages = response.data.last_page || 1;
            } catch (error) {
                console.error("Error fetching payslips:", error);
            } finally {
                this.loadingPayslips = false;
            }
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchPayslips();
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchPayslips();
            }
        },
        openCreatePayslip() {
            this.showCreatePayslip = true;
            if (this.employees.length === 0) {
                this.fetchEmployees();
            }
        },
        closeCreatePayslip() {
            this.showCreatePayslip = false;
            this.resetForm();
        },
        resetForm() {
            this.selectedEmployee = null;
            this.payoutDate = null;
            this.cutoffDateFrom = null;
            this.cutoffDateTo = null;
            this.attendanceRecords = [];
            this.holidays = [];
            this.showAttendanceTable = false;
            this.deductions = [];
        },
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
        async fetchAttendanceData() {
            if (
                !this.selectedEmployee ||
                !this.cutoffDateFrom ||
                !this.cutoffDateTo
            ) {
                return;
            }

            this.loadingAttendance = true;
            try {
                const employee = this.employees.find(
                    (emp) => emp.id === this.selectedEmployee,
                );
                const employeeName = employee ? employee.name : "";

                const response = await axios.get("/hr/time-records", {
                    params: {
                        employee: employeeName,
                        dateFrom: this.cutoffDateFrom,
                        dateTo: this.cutoffDateTo,
                    },
                });
                this.attendanceRecords = response.data.data || response.data;

                // Fetch holidays after getting attendance
                await this.fetchHolidays();
            } catch (error) {
                console.error("Error fetching attendance data:", error);
                this.attendanceRecords = [];
            } finally {
                this.loadingAttendance = false;
            }
        },
        async fetchHolidays() {
            if (!this.cutoffDateFrom || !this.cutoffDateTo) {
                return;
            }

            this.loadingHolidays = true;
            try {
                const response = await axios.get("/hr/holidays", {
                    params: {
                        date_from: this.cutoffDateFrom,
                        date_to: this.cutoffDateTo,
                    },
                });

                // Initialize holidays
                let holidays = response.data.map((holiday) => ({
                    ...holiday,
                    actualHoursWorked: 0,
                    timeRecord: null,
                }));

                // Match holidays with time records
                if (
                    this.attendanceRecords &&
                    this.attendanceRecords.length > 0
                ) {
                    holidays = holidays.map((holiday) => {
                        const holidayDate = holiday.holidate;
                        const timeRecord = this.attendanceRecords.find(
                            (record) => {
                                return record.DateToday === holidayDate;
                            },
                        );

                        if (timeRecord) {
                            const hoursWorked =
                                this.calculateHoursFromRecord(timeRecord);

                            return {
                                ...holiday,
                                actualHoursWorked: hoursWorked,
                                timeRecord: timeRecord,
                            };
                        } else {
                            return holiday;
                        }
                    });
                }

                // FILTER: Only keep holidays with actual hours worked
                this.holidays = holidays.filter(
                    (holiday) => holiday.actualHoursWorked > 0,
                );

                console.log(
                    "Holidays with actual hours worked:",
                    this.holidays,
                );
            } catch (error) {
                console.error("Error fetching holidays:", error);
                this.holidays = [];
            } finally {
                this.loadingHolidays = false;
            }
        },
        // NEW: Match holidays with actual time records
        matchHolidaysWithTimeRecords() {
            if (
                !this.attendanceRecords ||
                this.attendanceRecords.length === 0
            ) {
                return;
            }

            this.holidays = this.holidays.map((holiday) => {
                // Find time record that matches the holiday date
                const holidayDate = holiday.holidate; // Format: YYYY-MM-DD
                const timeRecord = this.attendanceRecords.find((record) => {
                    return record.DateToday === holidayDate;
                });

                if (timeRecord) {
                    // Calculate hours worked for this holiday
                    const hoursWorked =
                        this.calculateHoursFromRecord(timeRecord);

                    return {
                        ...holiday,
                        actualHoursWorked: hoursWorked,
                        timeRecord: timeRecord,
                    };
                } else {
                    return {
                        ...holiday,
                        actualHoursWorked: 0,
                        timeRecord: null,
                    };
                }
            });
        },
        // NEW: Calculate hours from a time record
        calculateHoursFromRecord(record) {
            const timeIn = this.parseDateTime(record.TimeIn);
            const timeOut = this.parseDateTime(record.TimeOut);

            if (!timeIn || !timeOut) {
                return 0;
            }

            let totalMs = timeOut - timeIn;

            const breakStart = this.parseDateTime(record.shortbreak_start);
            const breakEnd = this.parseDateTime(record.shortbreak_end);

            if (breakStart && breakEnd) {
                const breakMs = breakEnd - breakStart;
                totalMs -= breakMs;
            } else if (record.shortbreak_totaltime) {
                const breakMs = record.shortbreak_totaltime * 60 * 1000;
                totalMs -= breakMs;
            }

            const hours = totalMs / (1000 * 60 * 60);
            return hours > 0 ? hours : 0;
        },
        calculateHolidayPay(holiday) {
            if (!this.selectedEmployeeData) return 0;

            const hourlyRate =
                parseFloat(this.selectedEmployeeData.current_hourly_rate) || 0;
            const hoursWorked = parseFloat(holiday.actualHoursWorked) || 0;

            if (holiday.status === "regular") {
                // Regular Holiday
                if (hoursWorked > 0) {
                    // Worked: Hours × Rate × 2
                    return hoursWorked * hourlyRate * 2;
                } else {
                    // Not worked: 8 hours × Rate
                    return 8 * hourlyRate;
                }
            } else {
                // Special Non-Working Holiday
                if (hoursWorked > 0) {
                    // Worked: Hours × Rate × 1.3
                    return hoursWorked * hourlyRate * 1.3;
                } else {
                    // Not worked: NO WORK NO PAY
                    return 0;
                }
            }
        },
        calculateRegularHolidayPayTotal() {
            return this.holidays
                .filter((h) => h.status === "regular")
                .reduce((total, h) => total + this.calculateHolidayPay(h), 0);
        },
        calculateSpecialHolidayPayTotal() {
            return this.holidays
                .filter((h) => h.status !== "regular")
                .reduce((total, h) => total + this.calculateHolidayPay(h), 0);
        },
        calculateTotalHolidayPay() {
            return this.holidays.reduce((total, holiday) => {
                return total + this.calculateHolidayPay(holiday);
            }, 0);
        },
        calculateBasicPay() {
            if (!this.selectedEmployeeData) return 0;

            const hourlyRate =
                parseFloat(this.selectedEmployeeData.current_hourly_rate) || 0;
            return this.totalHoursWorked * hourlyRate;
        },
        calculateGrossPay() {
            const basicPay = this.calculateBasicPay();
            const holidayPay = this.calculateTotalHolidayPay();
            return basicPay + holidayPay;
        },
        addDeduction() {
            this.deductions.push({
                name: "",
                amount: 0,
                description: "",
                active: true,
            });
        },
        removeDeduction(index) {
            this.deductions.splice(index, 1);
        },
        calculateTotalActiveDeductions() {
            return this.deductions
                .filter((d) => d.active)
                .reduce((total, d) => total + (parseFloat(d.amount) || 0), 0);
        },
        calculateNetPay() {
            const grossPay = this.calculateGrossPay();
            const totalDeductions = this.calculateTotalActiveDeductions();
            return grossPay - totalDeductions;
        },
        async savePayslip() {
            if (!this.canSave) return;

            this.saving = true;
            try {
                const basicPay = this.calculateBasicPay();
                const regularHolidayPay =
                    this.calculateRegularHolidayPayTotal();
                const specialHolidayPay =
                    this.calculateSpecialHolidayPayTotal();
                const grossPay = this.calculateGrossPay();
                const totalDeductions = this.calculateTotalActiveDeductions();
                const netPay = this.calculateNetPay();

                const regularHolidayHours = this.holidays
                    .filter((h) => h.status === "regular")
                    .reduce(
                        (total, h) =>
                            total + (parseFloat(h.actualHoursWorked) || 0),
                        0,
                    );

                const specialHolidayHours = this.holidays
                    .filter((h) => h.status !== "regular")
                    .reduce(
                        (total, h) =>
                            total + (parseFloat(h.actualHoursWorked) || 0),
                        0,
                    );

                const payslipData = {
                    employee_id: this.selectedEmployee,
                    employee_name: this.selectedEmployeeData.name,
                    payout_date: this.payoutDate,
                    cutoff_from: this.cutoffDateFrom,
                    cutoff_to: this.cutoffDateTo,
                    total_days: this.attendanceRecords.length,
                    total_hours: this.totalHoursWorked,
                    hourly_rate: this.selectedEmployeeData.current_hourly_rate,
                    currency: this.selectedEmployeeData.current_currency,
                    basic_pay: basicPay,
                    regular_holiday_hours: regularHolidayHours,
                    regular_holiday_pay: regularHolidayPay,
                    special_holiday_hours: specialHolidayHours,
                    special_holiday_pay: specialHolidayPay,
                    gross_pay: grossPay,
                    deductions: totalDeductions,
                    net_pay: netPay,
                    deduction_details: this.deductions,
                    holiday_details: this.holidays,
                    attendance_records: this.attendanceRecords,
                };

                const response = await axios.post("/hr/payslips", payslipData);

                console.log("Payslip saved successfully:", response.data);
                alert("Payslip created successfully!");

                this.closeCreatePayslip();
                this.fetchPayslips();
            } catch (error) {
                console.error("Error saving payslip:", error);
                alert("Failed to create payslip. Please try again.");
            } finally {
                this.saving = false;
            }
        },
        parseDateTime(dateTimeString) {
            if (
                !dateTimeString ||
                dateTimeString === "--:--" ||
                dateTimeString === "-"
            ) {
                return null;
            }

            try {
                if (dateTimeString.includes(" ")) {
                    const [datePart, timePart] = dateTimeString.split(" ");
                    const [year, month, day] = datePart.split("-").map(Number);
                    const [hours, minutes, seconds] = timePart
                        .split(":")
                        .map(Number);
                    return new Date(
                        year,
                        month - 1,
                        day,
                        hours,
                        minutes,
                        seconds || 0,
                    );
                }

                return new Date(dateTimeString);
            } catch (error) {
                console.error("Error parsing datetime:", error, dateTimeString);
                return null;
            }
        },
        calculateTotalHours(record) {
            const timeIn = this.parseDateTime(record.TimeIn);
            const timeOut = this.parseDateTime(record.TimeOut);

            if (!timeIn || !timeOut) {
                return "0.00 hrs";
            }

            let totalMs = timeOut - timeIn;

            const breakStart = this.parseDateTime(record.shortbreak_start);
            const breakEnd = this.parseDateTime(record.shortbreak_end);

            if (breakStart && breakEnd) {
                const breakMs = breakEnd - breakStart;
                totalMs -= breakMs;
            } else if (record.shortbreak_totaltime) {
                const breakMs = record.shortbreak_totaltime * 60 * 1000;
                totalMs -= breakMs;
            }

            const hours = totalMs / (1000 * 60 * 60);

            return hours > 0 ? `${hours.toFixed(2)} hrs` : "0.00 hrs";
        },
        calculateGrandTotal() {
            if (
                !this.attendanceRecords ||
                this.attendanceRecords.length === 0
            ) {
                return "0.00 hrs";
            }

            return `${this.totalHoursWorked.toFixed(2)} hrs`;
        },
        formatTime(isoDateTime) {
            if (
                !isoDateTime ||
                isoDateTime === "--:--" ||
                isoDateTime === "-"
            ) {
                return "--:--:--";
            }

            try {
                let timeString = String(isoDateTime);

                if (timeString.includes(" ") && timeString.length > 10) {
                    const parts = timeString.split(" ");
                    if (parts.length >= 2) {
                        timeString = parts[1];
                    }
                }

                const [hours, minutes, seconds] = timeString
                    .split(":")
                    .map((s) => parseInt(s) || 0);

                const period = hours >= 12 ? "PM" : "AM";
                const hour12 = hours % 12 || 12;

                return `${hour12}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")} ${period}`;
            } catch (error) {
                console.error("Error formatting time:", error, isoDateTime);
                return "--:--:--";
            }
        },
        viewPayslip(payslip) {
            this.viewingPayslip = payslip;
            this.showViewPayslip = true;
        },
        closeViewPayslip() {
            this.showViewPayslip = false;
            this.viewingPayslip = null;
        },
        printPayslip() {
            setTimeout(() => {
                window.print();
            }, 100);
        },
        editPayslip(payslip) {
            console.log("Edit payslip:", payslip);
        },
        async deletePayslip(payslip) {
            if (
                !confirm(
                    `Are you sure you want to delete payslip for ${payslip.employee_name}?`,
                )
            ) {
                return;
            }

            try {
                await axios.delete(`/hr/payslips/${payslip.id}`);
                alert("Payslip deleted successfully!");
                this.fetchPayslips();
            } catch (error) {
                console.error("Error deleting payslip:", error);
                alert("Failed to delete payslip. Please try again.");
            }
        },
        formatDate(dateString) {
            if (!dateString) return "-";
            const date = new Date(dateString);
            return date.toLocaleDateString("en-US", {
                year: "numeric",
                month: "short",
                day: "numeric",
            });
        },
        formatDateTime(dateTimeString) {
            if (!dateTimeString) return "-";
            const date = new Date(dateTimeString);
            return date.toLocaleString("en-US", {
                year: "numeric",
                month: "short",
                day: "numeric",
                hour: "2-digit",
                minute: "2-digit",
            });
        },
        formatCurrency(value) {
            const num = parseFloat(value) || 0;
            return num.toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
    },
};
</script>

<style scoped src="../hr.css"></style>
