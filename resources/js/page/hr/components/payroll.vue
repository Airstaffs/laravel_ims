<template>
    <div class="payroll-wrapper">
        <div class="payroll-header">
            <h4>Payroll Management</h4>
            <Button
                v-if="isHR"
                @click="openCreatePayslip"
                label="Create Payslip"
            />
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
                        'bg-success': data.status === 'released',
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
                        v-if="isHR && data.status === 'draft'"
                        label="Release"
                        size="small"
                        variant="text"
                        severity="contrast"
                        class="text-success"
                        icon="pi pi-send"
                        @click="releasePayslip(data)"
                    />
                    <Button
                        v-if="isHR"
                        label="Edit"
                        size="small"
                        variant="text"
                        severity="contrast"
                        class="text-primary"
                        icon="pi pi-pencil"
                        @click="editPayslip(data)"
                    />
                    <Button
                        v-if="isHR"
                        label="Delete"
                        size="small"
                        variant="text"
                        severity="contrast"
                        class="text-danger"
                        icon="pi pi-trash"
                        @click="deletePayslip(data)"
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

            <fieldset
                v-if="
                    attendanceRecords &&
                    attendanceRecords.length > 0 &&
                    selectedEmployee
                "
            >
                <div
                    class="d-flex justify-content-between align-items-center mb-2"
                >
                    <label
                        class="mb-0 fw-semibold d-flex align-items-center gap-2"
                    >
                        Night Differential
                        <span class="badge bg-dark" style="font-size: 10px"
                            >10PM – 6AM</span
                        >
                    </label>
                    <!-- Auto-calculated badge -->
                    <span
                        v-if="calculateNightDiffHours() > 0"
                        class="badge bg-warning text-dark"
                    >
                        {{ calculateNightDiffHours() }} night hrs detected
                    </span>
                    <span v-else class="badge bg-secondary"
                        >No night hours</span
                    >
                </div>

                <div
                    v-if="calculateNightDiffHours() > 0"
                    class="p-3 border rounded bg-light"
                >
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <div class="text-muted small">Night Hours</div>
                                <div class="fw-bold">
                                    {{ calculateNightDiffHours() }} hrs
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <div class="text-muted small">Diff Rate/hr</div>
                                <div class="fw-bold text-warning">
                                    {{ selectedEmployeeData?.current_currency }}
                                    {{
                                        formatCurrency(
                                            getHourlyRate() -
                                                getHourlyRate() / 1.1,
                                        )
                                    }}
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <div class="text-muted small">
                                    Night Diff Pay
                                </div>
                                <div class="fw-bold text-success">
                                    {{ selectedEmployeeData?.current_currency }}
                                    {{
                                        formatCurrency(calculateNightDiffPay())
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 small text-muted text-center">
                        Auto-detected from attendance records (PH Labor Code
                        Art. 86)
                    </div>
                </div>

                <div
                    v-else
                    class="text-muted small p-2 bg-light rounded text-center"
                >
                    No hours between 10:00 PM – 6:00 AM found in attendance
                    records.
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
                    class="d-flex justify-content-between align-items-center mb-3"
                >
                    <label class="mb-0 fw-semibold">Deductions:</label>
                </div>

                <!-- FIXED DEDUCTIONS -->
                <div class="mb-3">
                    <div
                        class="d-flex justify-content-between align-items-center mb-2"
                    >
                        <span
                            class="fw-semibold text-secondary small text-uppercase"
                        >
                            <i class="pi pi-lock me-1"></i>Fixed Deductions
                        </span>
                        <span class="badge bg-secondary"
                            >Auto-applied to all employees</span
                        >
                    </div>

                    <div v-if="loadingFixedDeductions" class="text-center py-2">
                        <span class="spinner-border spinner-border-sm"></span>
                        <span class="text-muted ms-2 small"
                            >Loading fixed deductions...</span
                        >
                    </div>

                    <div
                        v-else-if="fixedDeductions.length === 0"
                        class="text-muted small p-3 bg-light rounded text-center"
                    >
                        No fixed deductions configured.
                    </div>

                    <div
                        v-for="(deduction, index) in fixedDeductions"
                        :key="'fixed-' + index"
                        class="deduction-item mb-2 p-3 border rounded bg-light"
                    >
                        <div
                            class="d-flex justify-content-between align-items-center mb-2"
                        >
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary">Fixed</span>
                                <span class="fw-semibold">{{
                                    deduction.name
                                }}</span>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    :id="'fixed-active-' + index"
                                    v-model="deduction.active"
                                    role="switch"
                                />
                                <label
                                    class="form-check-label small"
                                    :for="'fixed-active-' + index"
                                >
                                    <span
                                        v-if="deduction.active"
                                        class="text-success"
                                        >Included</span
                                    >
                                    <span v-else class="text-muted"
                                        >Excluded</span
                                    >
                                </label>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="small fw-semibold">Amount:</label>
                                <InputText
                                    v-model.number="deduction.amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    placeholder="0.00"
                                />
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-semibold"
                                    >Description (Optional):</label
                                >
                                <InputText
                                    v-model="deduction.description"
                                    class="form-control"
                                    placeholder="Notes..."
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CUSTOM DEDUCTIONS -->
                <div>
                    <div
                        class="d-flex justify-content-between align-items-center mb-2"
                    >
                        <span
                            class="fw-semibold text-secondary small text-uppercase"
                        >
                            <i class="pi pi-user me-1"></i>Custom Deductions
                        </span>
                        <Button
                            label="Add Custom"
                            icon="pi pi-plus"
                            size="small"
                            severity="success"
                            text
                            @click="addDeduction"
                            type="button"
                        />
                    </div>

                    <div
                        v-if="customDeductions.length === 0"
                        class="text-muted small p-3 bg-light rounded text-center"
                    >
                        No custom deductions. Click "Add Custom" to add one
                        specific to this employee.
                    </div>

                    <div
                        v-for="(deduction, index) in customDeductions"
                        :key="'custom-' + index"
                        class="deduction-item mb-2 p-3 border rounded bg-white"
                    >
                        <div
                            class="d-flex justify-content-between align-items-start mb-2"
                        >
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark"
                                    >Custom</span
                                >
                                <div class="form-check mb-0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        :id="'custom-active-' + index"
                                        v-model="deduction.active"
                                    />
                                    <label
                                        class="form-check-label small fw-semibold"
                                        :for="'custom-active-' + index"
                                    >
                                        <span
                                            v-if="deduction.active"
                                            class="text-success"
                                            >✓ Include</span
                                        >
                                        <span v-else class="text-muted"
                                            >○ Exclude</span
                                        >
                                    </label>
                                </div>
                            </div>
                            <Button
                                icon="pi pi-trash"
                                size="small"
                                severity="danger"
                                text
                                @click="removeDeduction(index)"
                                type="button"
                            />
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="small fw-semibold">
                                    Deduction Name:
                                    <span class="text-danger">*</span>
                                </label>
                                <InputText
                                    v-model="deduction.name"
                                    class="form-control"
                                    placeholder="e.g., Loan, Cash Advance"
                                />
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="small fw-semibold">
                                    Amount: <span class="text-danger">*</span>
                                </label>
                                <InputText
                                    v-model.number="deduction.amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    placeholder="0.00"
                                />
                            </div>
                            <div class="col-md-12">
                                <label class="small fw-semibold"
                                    >Description (Optional):</label
                                >
                                <textarea
                                    v-model="deduction.description"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Add notes about this deduction"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deduction Summary -->
                <div
                    v-if="
                        fixedDeductions.length > 0 ||
                        customDeductions.length > 0
                    "
                    class="mt-3 p-2 bg-light rounded d-flex justify-content-between align-items-center"
                >
                    <span class="small text-muted"
                        >Total Active Deductions:</span
                    >
                    <span class="fw-bold text-danger">
                        {{ selectedEmployeeData?.current_currency }}
                        {{ formatCurrency(calculateTotalActiveDeductions()) }}
                    </span>
                </div>
            </fieldset>

            <fieldset>
                <label>Notes: </label>
                <textarea
                    v-model="payslipNotes"
                    class="form-control"
                    rows="4"
                    placeholder="Add any additional notes or comments about this payslip..."
                ></textarea>
                <small class="text-muted">
                    Optional: Add any special instructions, adjustments, or
                    comments
                </small>
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
                                            viewingPayslip.night_diff_pay,
                                        ) > 0
                                    "
                                >
                                    <td>
                                        Night Differential
                                        <small class="text-muted d-block">
                                            {{
                                                viewingPayslip.night_diff_hours
                                            }}
                                            hrs × 10% premium
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        {{ viewingPayslip.currency }}
                                        {{
                                            formatCurrency(
                                                viewingPayslip.night_diff_pay,
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
                                        parsedDeductions &&
                                        parsedDeductions.length > 0
                                    "
                                >
                                    <!-- Fixed deductions first -->
                                    <template
                                        v-for="(
                                            deduction, index
                                        ) in parsedDeductions.filter(
                                            (d) =>
                                                d.type === 'fixed' &&
                                                (d.active === true ||
                                                    d.active === 1),
                                        )"
                                        :key="'view-fixed-' + index"
                                    >
                                        <tr>
                                            <td>
                                                <span
                                                    class="badge bg-primary me-1"
                                                    style="font-size: 10px"
                                                    >Fixed</span
                                                >
                                                {{ deduction.name }}
                                            </td>
                                            <td class="text-end">
                                                {{ viewingPayslip.currency }}
                                                {{
                                                    formatCurrency(
                                                        deduction.amount,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                    </template>
                                    <!-- Custom deductions -->
                                    <template
                                        v-for="(
                                            deduction, index
                                        ) in parsedDeductions.filter(
                                            (d) =>
                                                d.type === 'custom' &&
                                                (d.active === true ||
                                                    d.active === 1),
                                        )"
                                        :key="'view-custom-' + index"
                                    >
                                        <tr>
                                            <td>
                                                <span
                                                    class="badge bg-warning text-dark me-1"
                                                    style="font-size: 10px"
                                                    >Custom</span
                                                >
                                                {{ deduction.name }}
                                            </td>
                                            <td class="text-end">
                                                {{ viewingPayslip.currency }}
                                                {{
                                                    formatCurrency(
                                                        deduction.amount,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                    </template>
                                    <!-- Fallback if no active deductions -->
                                    <tr v-if="activeDeductions.length === 0">
                                        <td
                                            colspan="2"
                                            class="text-center text-muted small"
                                        >
                                            No active deductions
                                        </td>
                                    </tr>
                                </template>
                                <template v-else>
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

            <!-- Notes Section - NEW -->
            <div
                class="section mt-4 no-print"
                v-if="
                    viewingPayslip.notes && viewingPayslip.notes.trim() !== ''
                "
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
                            {{ viewingPayslip.notes }}
                        </p>
                    </div>
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

    <!-- Edit Dialog -->
    <Dialog
        v-model:visible="showEditPayslip"
        modal
        header="Edit Payslip"
        :style="{ width: '70%' }"
        :pt="{
            root: { class: 'mobile-fullscreen-dialog' },
        }"
    >
        <fieldset>
            <label>Employee: </label>
            <InputText
                :value="editForm.employee_name"
                class="form-control"
                disabled
            />
        </fieldset>

        <fieldset>
            <label>Pay Out: </label>
            <InputText
                v-model="editForm.payout_date"
                type="date"
                class="form-control"
                required
            />
        </fieldset>

        <fieldset>
            <label>Cut off from - to: </label>
            <div class="d-flex gap-2">
                <InputText
                    v-model="editForm.cutoff_from"
                    type="date"
                    class="form-control"
                    required
                />
                <InputText
                    v-model="editForm.cutoff_to"
                    type="date"
                    class="form-control"
                    required
                />
            </div>
        </fieldset>

        <!-- Deductions Section -->
        <fieldset>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <label class="mb-0 fw-semibold">Deductions:</label>
            </div>

            <!-- FIXED DEDUCTIONS -->
            <div class="mb-3">
                <div
                    class="d-flex justify-content-between align-items-center mb-2"
                >
                    <span
                        class="fw-semibold text-secondary small text-uppercase"
                    >
                        <i class="pi pi-lock me-1"></i>Fixed Deductions
                    </span>
                    <span class="badge bg-secondary"
                        >Auto-applied to all employees</span
                    >
                </div>

                <div
                    v-if="editForm.fixedDeductions.length === 0"
                    class="text-muted small p-3 bg-light rounded text-center"
                >
                    No fixed deductions configured.
                </div>

                <div
                    v-for="(deduction, index) in editForm.fixedDeductions"
                    :key="'edit-fixed-' + index"
                    class="deduction-item mb-2 p-3 border rounded bg-light"
                >
                    <div
                        class="d-flex justify-content-between align-items-center mb-2"
                    >
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary">Fixed</span>
                            <span class="fw-semibold">{{
                                deduction.name
                            }}</span>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                :id="'edit-fixed-active-' + index"
                                v-model="deduction.active"
                                role="switch"
                            />
                            <label
                                class="form-check-label small"
                                :for="'edit-fixed-active-' + index"
                            >
                                <span
                                    v-if="deduction.active"
                                    class="text-success"
                                    >Included</span
                                >
                                <span v-else class="text-muted">Excluded</span>
                            </label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="small fw-semibold">Amount:</label>
                            <InputText
                                v-model.number="deduction.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control"
                                placeholder="0.00"
                            />
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-semibold"
                                >Description (Optional):</label
                            >
                            <InputText
                                v-model="deduction.description"
                                class="form-control"
                                placeholder="Notes..."
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- CUSTOM DEDUCTIONS -->
            <div>
                <div
                    class="d-flex justify-content-between align-items-center mb-2"
                >
                    <span
                        class="fw-semibold text-secondary small text-uppercase"
                    >
                        <i class="pi pi-user me-1"></i>Custom Deductions
                    </span>
                    <Button
                        label="Add Custom"
                        icon="pi pi-plus"
                        size="small"
                        severity="success"
                        text
                        @click="addEditDeduction"
                        type="button"
                    />
                </div>

                <div
                    v-if="editForm.customDeductions.length === 0"
                    class="text-muted small p-3 bg-light rounded text-center"
                >
                    No custom deductions. Click "Add Custom" to add one.
                </div>

                <div
                    v-for="(deduction, index) in editForm.customDeductions"
                    :key="'edit-custom-' + index"
                    class="deduction-item mb-2 p-3 border rounded bg-white"
                >
                    <div
                        class="d-flex justify-content-between align-items-start mb-2"
                    >
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning text-dark"
                                >Custom</span
                            >
                            <div class="form-check mb-0">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    :id="'edit-custom-active-' + index"
                                    v-model="deduction.active"
                                />
                                <label
                                    class="form-check-label small fw-semibold"
                                    :for="'edit-custom-active-' + index"
                                >
                                    <span
                                        v-if="deduction.active"
                                        class="text-success"
                                        >✓ Include</span
                                    >
                                    <span v-else class="text-muted"
                                        >○ Exclude</span
                                    >
                                </label>
                            </div>
                        </div>
                        <Button
                            icon="pi pi-trash"
                            size="small"
                            severity="danger"
                            text
                            @click="removeEditDeduction(index)"
                            type="button"
                        />
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="small fw-semibold">
                                Deduction Name:
                                <span class="text-danger">*</span>
                            </label>
                            <InputText
                                v-model="deduction.name"
                                class="form-control"
                                placeholder="e.g., Loan, Cash Advance"
                            />
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="small fw-semibold">
                                Amount: <span class="text-danger">*</span>
                            </label>
                            <InputText
                                v-model.number="deduction.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control"
                                placeholder="0.00"
                            />
                        </div>
                        <div class="col-md-12">
                            <label class="small fw-semibold"
                                >Description (Optional):</label
                            >
                            <textarea
                                v-model="deduction.description"
                                class="form-control"
                                rows="2"
                                placeholder="Add notes about this deduction"
                            ></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Deduction Summary -->
            <div
                v-if="
                    editForm.fixedDeductions.length > 0 ||
                    editForm.customDeductions.length > 0
                "
                class="mt-3 p-2 bg-light rounded d-flex justify-content-between align-items-center"
            >
                <span class="small text-muted">Total Active Deductions:</span>
                <span class="fw-bold text-danger">
                    {{ formatCurrency(calculateEditTotalActiveDeductions()) }}
                </span>
            </div>
        </fieldset>

        <fieldset>
            <label>Notes: </label>
            <textarea
                v-model="editForm.notes"
                class="form-control"
                rows="4"
                placeholder="Add any additional notes or comments about this payslip..."
            ></textarea>
            <small class="text-muted">
                Optional: Add any special instructions, adjustments, or comments
            </small>
        </fieldset>

        <template #footer>
            <div class="d-flex justify-content-between w-100">
                <Button
                    label="Cancel"
                    severity="secondary"
                    @click="closeEditPayslip"
                    :disabled="updating"
                />
                <Button
                    label="Update Payslip"
                    severity="success"
                    icon="pi pi-save"
                    @click="updatePayslip"
                    :loading="updating"
                    :disabled="!canUpdate"
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
            payslipNotes: "",

            fixedDeductions: [],
            loadingFixedDeductions: false,
            customDeductions: [],

            currentEmployeeRate: null,
            loadingRate: false,

            // Auth user
            authUser: null,

            showEditPayslip: false,
            updating: false,
            editForm: {
                id: null,
                employee_id: null,
                employee_name: "",
                payout_date: null,
                cutoff_from: null,
                cutoff_to: null,
                deductions: [],
                fixedDeductions: [],
                customDeductions: [],
                notes: "",
            },

            nightDiffHours: 0,
            nightDiffRate: 0,
            nightDiffPay: 0,
        };
    },
    computed: {
        isHR() {
            return ["SuperAdmin", "SubAdmin", "hr"].includes(
                this.authUser?.role,
            );
        },

        selectedEmployeeData() {
            if (
                !this.selectedEmployee ||
                !this.employees ||
                this.employees.length === 0
            ) {
                return null;
            }
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

        canUpdate() {
            return (
                this.editForm.employee_id &&
                this.editForm.payout_date &&
                this.editForm.cutoff_from &&
                this.editForm.cutoff_to &&
                !this.updating
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
                this.fetchCurrentEmployeeRate();
            }
        },
        selectedEmployee(newVal) {
            if (newVal && this.cutoffDateFrom && this.cutoffDateTo) {
                this.fetchAttendanceData();
            }
            if (newVal) {
                this.fetchCurrentEmployeeRate();
            }
        },
        attendanceRecords(newVal) {
            if (newVal && newVal.length > 0 && this.holidays.length > 0) {
                this.matchHolidaysWithTimeRecords();
            }
        },
    },
    mounted() {
        this.fetchAuthUser();
        this.fetchPayslips();
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

        async fetchCurrentEmployeeRate() {
            if (!this.selectedEmployee) return;

            this.loadingRate = true;
            this.currentEmployeeRate = null;

            try {
                const { data } = await axios.get(
                    `/hr/employees/${this.selectedEmployee}/rates/current`,
                    // Pass cutoff_to so the backend returns the rate
                    // that was active at the END of the pay period
                    { params: { as_of: this.cutoffDateTo || null } },
                );
                this.currentEmployeeRate = data?.data || null;
            } catch (e) {
                console.warn("Could not fetch employee rate:", e);
                this.currentEmployeeRate = null;
            } finally {
                this.loadingRate = false;
            }
        },

        getHourlyRate() {
            return parseFloat(
                this.currentEmployeeRate?.hourly_rate ??
                    this.selectedEmployeeData?.current_hourly_rate ??
                    0,
            );
        },
        getCurrency() {
            return (
                this.currentEmployeeRate?.currency ??
                this.selectedEmployeeData?.current_currency ??
                "PHP"
            );
        },

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

        async fetchFixedDeductions() {
            this.loadingFixedDeductions = true;
            try {
                const response = await axios.get("/hr/fixed-deductions");
                // Map each fixed deduction and mark type
                this.fixedDeductions = (response.data || []).map((d) => ({
                    id: d.id,
                    name: d.name,
                    amount: parseFloat(d.default_amount) || 0,
                    description: d.description || "",
                    active: true,
                    type: "fixed",
                }));
            } catch (error) {
                console.error("Error fetching fixed deductions:", error);
                this.fixedDeductions = [];
            } finally {
                this.loadingFixedDeductions = false;
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
            if (this.fixedDeductions.length === 0) {
                this.fetchFixedDeductions();
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
            this.fixedDeductions = [];
            this.customDeductions = [];
            this.payslipNotes = "";
            this.currentEmployeeRate = null;
            this.nightDiffHours = 0;
            this.nightDiffRate = 0;
            this.nightDiffPay = 0;
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
            const hourlyRate = this.getHourlyRate();
            const hoursWorked = parseFloat(holiday.actualHoursWorked) || 0;

            if (holiday.status === "regular") {
                return hoursWorked > 0
                    ? hoursWorked * hourlyRate * 2
                    : 8 * hourlyRate;
            } else {
                return hoursWorked > 0 ? hoursWorked * hourlyRate * 1.3 : 0;
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
            return this.totalHoursWorked * this.getHourlyRate();
        },

        calculateGrossPay() {
            return (
                this.calculateBasicPay() +
                this.calculateTotalHolidayPay() +
                this.calculateNightDiffPay()
            );
        },
        addDeduction() {
            this.customDeductions.push({
                name: "",
                amount: 0,
                description: "",
                active: true,
                type: "custom",
            });
        },
        removeDeduction(index) {
            this.customDeductions.splice(index, 1);
        },
        calculateTotalActiveDeductions() {
            const fixedTotal = this.fixedDeductions
                .filter((d) => d.active)
                .reduce((sum, d) => sum + (parseFloat(d.amount) || 0), 0);

            const customTotal = this.customDeductions
                .filter((d) => d.active)
                .reduce((sum, d) => sum + (parseFloat(d.amount) || 0), 0);

            return fixedTotal + customTotal;
        },
        calculateNetPay() {
            return (
                this.calculateGrossPay() - this.calculateTotalActiveDeductions()
            );
        },

        async savePayslip() {
            if (!this.canSave) return;

            if (!this.selectedEmployeeData) {
                await Swal.fire({
                    icon: "warning",
                    title: "No Employee Selected",
                    text: "Please select an employee first.",
                });
                return;
            }

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
                    employee_name: this.selectedEmployeeData?.name || "",
                    payout_date: this.payoutDate,
                    cutoff_from: this.cutoffDateFrom,
                    cutoff_to: this.cutoffDateTo,
                    total_days: this.attendanceRecords.length,
                    total_hours: this.totalHoursWorked,
                    hourly_rate: this.getHourlyRate(), // ← fetched rate
                    currency: this.getCurrency(), // ← fetched currency
                    basic_pay: basicPay,
                    regular_holiday_hours: regularHolidayHours,
                    regular_holiday_pay: regularHolidayPay,
                    special_holiday_hours: specialHolidayHours,
                    special_holiday_pay: specialHolidayPay,
                    gross_pay: grossPay,
                    deductions: totalDeductions,
                    net_pay: netPay,
                    deduction_details: [
                        // ← merged fixed + custom
                        ...this.fixedDeductions,
                        ...this.customDeductions,
                    ],
                    holiday_details: this.holidays,
                    attendance_records: this.attendanceRecords,
                    notes: this.payslipNotes || null,

                    night_diff_hours: this.calculateNightDiffHours(),
                    night_diff_rate:
                        this.getHourlyRate() - this.getHourlyRate() / 1.1,
                    night_diff_pay: this.calculateNightDiffPay(),
                };

                await axios.post("/hr/payslips", payslipData);

                await Swal.fire({
                    icon: "success",
                    title: "Payslip Created",
                    text: "Payslip created successfully.",
                    confirmButtonText: "OK",
                });

                this.closeCreatePayslip();
                this.fetchPayslips();
            } catch (error) {
                const errorMessage =
                    error.response?.data?.message ||
                    error.response?.data?.error ||
                    error.message ||
                    "Failed to create payslip";
                await Swal.fire({
                    icon: "error",
                    title: "Failed to Create",
                    text: errorMessage,
                });
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
            let allDeductions = [];
            if (payslip.deduction_details) {
                try {
                    allDeductions =
                        typeof payslip.deduction_details === "string"
                            ? JSON.parse(payslip.deduction_details)
                            : payslip.deduction_details;
                } catch (e) {
                    allDeductions = [];
                }
            }

            this.editForm = {
                id: payslip.id,
                employee_id: payslip.employee_id,
                employee_name: payslip.employee_name,
                payout_date:
                    payslip.payout_date?.split("T")[0] ?? payslip.payout_date,
                cutoff_from:
                    payslip.cutoff_from?.split("T")[0] ?? payslip.cutoff_from,
                cutoff_to:
                    payslip.cutoff_to?.split("T")[0] ?? payslip.cutoff_to,
                fixedDeductions: allDeductions.filter(
                    (d) => d.type === "fixed",
                ),
                customDeductions: allDeductions.filter(
                    (d) => d.type !== "fixed",
                ),
                notes: payslip.notes || "",
            };

            this.showEditPayslip = true;

            if (this.employees.length === 0) {
                this.fetchEmployees();
            }
        },

        async deletePayslip(payslip) {
            const confirm = await Swal.fire({
                icon: "warning",
                title: "Delete Payslip?",
                html: `Are you sure you want to delete the payslip for <strong>${payslip.employee_name}</strong>?`,
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Yes, delete it",
                cancelButtonText: "Cancel",
            });

            if (!confirm.isConfirmed) return;

            try {
                await axios.delete(`/hr/payslips/${payslip.id}`);
                await Swal.fire({
                    icon: "success",
                    title: "Deleted",
                    text: "Payslip deleted successfully.",
                    confirmButtonText: "OK",
                });
                this.fetchPayslips();
            } catch (error) {
                console.error("Error deleting payslip:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Delete Failed",
                    text:
                        error.response?.data?.message ||
                        "Failed to delete payslip.",
                });
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

        async releasePayslip(payslip) {
            const confirm = await Swal.fire({
                icon: "question",
                title: "Release Payslip?",
                html: `This will make the payslip for <strong>${payslip.employee_name}</strong> visible to the employee.`,
                showCancelButton: true,
                confirmButtonColor: "#28a745",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Yes, release it",
                cancelButtonText: "Cancel",
            });

            if (!confirm.isConfirmed) return;

            try {
                await axios.patch(`/hr/payslips/${payslip.id}/release`);
                await Swal.fire({
                    icon: "success",
                    title: "Released",
                    text: `Payslip for ${payslip.employee_name} is now visible to the employee.`,
                    confirmButtonText: "OK",
                });
                this.fetchPayslips();
            } catch (error) {
                console.error("Error releasing payslip:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Release Failed",
                    text:
                        error.response?.data?.message ||
                        "Failed to release payslip.",
                });
            }
        },

        editPayslip(payslip) {
            // Parse deduction_details if it's a string
            let deductions = [];
            if (payslip.deduction_details) {
                try {
                    deductions =
                        typeof payslip.deduction_details === "string"
                            ? JSON.parse(payslip.deduction_details)
                            : payslip.deduction_details;
                } catch (e) {
                    deductions = [];
                }
            }

            this.editForm = {
                id: payslip.id,
                employee_id: payslip.employee_id,
                employee_name: payslip.employee_name,
                payout_date:
                    payslip.payout_date?.split("T")[0] ?? payslip.payout_date,
                cutoff_from:
                    payslip.cutoff_from?.split("T")[0] ?? payslip.cutoff_from,
                cutoff_to:
                    payslip.cutoff_to?.split("T")[0] ?? payslip.cutoff_to,
                deductions: deductions,
                notes: payslip.notes || "",
            };

            this.showEditPayslip = true;

            if (this.employees.length === 0) {
                this.fetchEmployees();
            }
        },

        closeEditPayslip() {
            this.showEditPayslip = false;
            this.editForm = {
                id: null,
                employee_id: null,
                employee_name: "",
                payout_date: null,
                cutoff_from: null,
                cutoff_to: null,
                fixedDeductions: [],
                customDeductions: [],
                notes: "",
            };
        },

        addEditDeduction() {
            this.editForm.customDeductions.push({
                name: "",
                amount: 0,
                description: "",
                active: true,
                type: "custom",
            });
        },

        removeEditDeduction(index) {
            this.editForm.customDeductions.splice(index, 1);
        },

        calculateEditTotalActiveDeductions() {
            const fixedTotal = this.editForm.fixedDeductions
                .filter((d) => d.active)
                .reduce((sum, d) => sum + (parseFloat(d.amount) || 0), 0);

            const customTotal = this.editForm.customDeductions
                .filter((d) => d.active)
                .reduce((sum, d) => sum + (parseFloat(d.amount) || 0), 0);

            return fixedTotal + customTotal;
        },

        async updatePayslip() {
            if (!this.canUpdate) return;
            this.updating = true;

            try {
                await axios.patch(`/hr/payslips/${this.editForm.id}`, {
                    employee_id: this.editForm.employee_id,
                    payout_date: this.editForm.payout_date,
                    cutoff_from: this.editForm.cutoff_from,
                    cutoff_to: this.editForm.cutoff_to,
                    deduction_details: [
                        ...this.editForm.fixedDeductions,
                        ...this.editForm.customDeductions,
                    ],
                    notes: this.editForm.notes,
                });

                await Swal.fire({
                    icon: "success",
                    title: "Updated",
                    text: "Payslip updated successfully.",
                    timer: 1500,
                    showConfirmButton: false,
                });

                this.closeEditPayslip();
                this.fetchPayslips();
            } catch (error) {
                console.error("Error updating payslip:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Update Failed",
                    text:
                        error.response?.data?.message ||
                        "Failed to update payslip.",
                });
            } finally {
                this.updating = false;
            }
        },

        calculateNightDiffHours() {
            if (
                !this.attendanceRecords ||
                this.attendanceRecords.length === 0
            ) {
                return 0;
            }

            let totalNightHours = 0;

            this.attendanceRecords.forEach((record) => {
                const timeIn = this.parseDateTime(record.TimeIn);
                const timeOut = this.parseDateTime(record.TimeOut);

                if (!timeIn || !timeOut) return;

                // Night window: 10PM (22:00) to 6AM (06:00) next day
                // We scan in 1-minute increments within the shift
                // to count how many minutes fall in the night window
                let nightMinutes = 0;
                const step = 60 * 1000; // 1 minute in ms
                let cursor = new Date(timeIn.getTime());

                // Collect break period if available
                const breakStart = this.parseDateTime(record.shortbreak_start);
                const breakEnd = this.parseDateTime(record.shortbreak_end);

                while (cursor < timeOut) {
                    // Skip break time
                    if (
                        breakStart &&
                        breakEnd &&
                        cursor >= breakStart &&
                        cursor < breakEnd
                    ) {
                        cursor = new Date(cursor.getTime() + step);
                        continue;
                    }

                    const hour = cursor.getHours();
                    // Night window: hour >= 22 OR hour < 6
                    if (hour >= 22 || hour < 6) {
                        nightMinutes++;
                    }

                    cursor = new Date(cursor.getTime() + step);
                }

                totalNightHours += nightMinutes / 60;
            });

            return Math.round(totalNightHours * 100) / 100;
        },

        calculateNightDiffPay() {
            const hourlyRate = this.getHourlyRate();
            const nightHours = this.calculateNightDiffHours();
            const diffRate = hourlyRate - hourlyRate / 1.1; // +10% premium only
            return diffRate * nightHours;
        },
    },
};
</script>

<style scoped src="../hr.css"></style>
