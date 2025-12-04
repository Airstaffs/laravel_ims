<template>
    <div class="time-record-wrapper">
        <button class="btn btn-toggle d-md-none" @click="$parent.toggleFilters()">
            <i class="fas fa-sliders-h"></i>
        </button>

        <form class="filter-controls" v-show="$parent.showFilters" @submit.prevent="$parent.fetchRecords()">
            <fieldset>
                <label>Filter By Employee</label>
                <Select v-model="$parent.filters.employee" :options="['', ...$parent.employeeNames]" placeholder="All"
                    size="small" fluid optionLabel="" optionValue="" class="w-full">
                    <template #value="{ value }">
                        {{ value || 'All' }}
                    </template>
                    <template #option="{ option }">
                        {{ option || 'All' }}
                    </template>
                </Select>
            </fieldset>

            <fieldset>
                <label>Date From</label>
                <DatePicker v-model="$parent.filters.dateFrom" dateFormat="dd/mm/yy" showIcon placeholder="Select date"
                    class="w-full" size="small" fluid />
            </fieldset>

            <fieldset>
                <label>Date To</label>
                <DatePicker v-model="$parent.filters.dateTo" dateFormat="dd/mm/yy" showIcon placeholder="Select date"
                    class="w-full" size="small" fluid />
            </fieldset>

            <fieldset>
                <label></label>
                <Button class="w-100" @click="$parent.fetchRecords" size="small" severity="info">
                    Apply Filters
                </Button>
            </fieldset>
        </form>

        <!-- Mobile / Small screens: Card list -->
        <div class="d-md-none">
            <div class="tr-card shadow-sm rounded-3 mb-2 p-3" v-for="record in $parent.timeRecords"
                :key="record?.ID || record?.id">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-2">
                        <div class="small text-secondary">Clock ID</div>
                        <div class="fw-semibold">
                            {{ record?.ID || record?.id || "-" }}
                        </div>
                    </div>
                    <button class="btn btn-light btn-sm border" @click="$parent.toggleHistory(record?.ID || record?.id)"
                        :aria-expanded="$parent.expandedClockId ===
                            (record?.ID || record?.id)
                            ">
                        <span v-if="
                            $parent.expandedClockId ===
                            (record?.ID || record?.id)
                        ">Hide</span>
                        <span v-else>History</span>
                    </button>
                </div>

                <!-- Employee + Date -->
                <div class="mt-2">
                    <div class="fw-semibold text-truncate">
                        {{ record?.Employee || "-" }}
                    </div>
                    <div class="text-secondary small">
                        {{ record?.DateToday || "-" }}
                    </div>
                </div>

                <!-- Times grid -->
                <div class="row g-2 mt-3">
                    <div class="col-6">
                        <div class="label text-secondary small">Time In</div>
                        <div class="value fw-semibold">
                            {{ $parent.formatDate(record?.TimeIn) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="label text-secondary small">Time Out</div>
                        <div class="value fw-semibold">
                            {{ $parent.formatDate(record?.TimeOut) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="label text-secondary small">
                            Break Start
                        </div>
                        <div class="value fw-semibold">
                            {{ $parent.formatDate(record?.shortbreak_start) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="label text-secondary small">Break End</div>
                        <div class="value fw-semibold">
                            {{ $parent.formatDate(record?.shortbreak_end) }}
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="label text-secondary small">
                            Total Break
                        </div>
                        <div class="value fw-semibold">
                            {{ record?.shortbreak_totaltime || 0 }} mins
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mt-2">
                    <div class="label text-secondary small">Notes</div>
                    <div class="value">{{ record?.Notes || "-" }}</div>
                </div>

                <!-- Actions -->
                <div class="d-grid mt-3">
                    <button class="btn btn-primary btn-sm" @click="$parent.openEdit(record)">
                        Edit
                    </button>
                </div>

                <!-- Inline history (mobile) -->
                <div class="mt-3 border-top pt-3" v-if="
                    $parent.expandedClockId === (record?.ID || record?.id)
                ">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <strong class="me-2">Edit History</strong>
                        <span v-if="$parent.historyLoading" class="spinner-border spinner-border-sm"></span>
                    </div>

                    <!-- No history -->
                    <div v-if="
                        !$parent.historyLoading &&
                        (!$parent.clockEditHistory ||
                            !$parent.clockEditHistory.length)
                    " class="text-muted small">
                        No edits recorded for this clock.
                    </div>

                    <!-- History list -->
                    <div v-else class="history-list">
                        <div class="history-item py-2" v-for="(h, idx) in $parent.clockEditHistory" :key="idx">
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
                                        <li v-for="(
chg, i
                                            ) in $parent.prettyDiff(
    h.before,
    h.after
)" :key="i">
                                            <code>{{ chg.key }}</code>:
                                            <span class="text-decoration-line-through text-muted">{{ chg.from }}</span>
                                            →
                                            <strong>{{ chg.to }}</strong>
                                        </li>
                                    </ul>
                                </template>
                                <template v-else-if="h.changes || h.delta || h.after">
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
        </div>

        <!-- Desktop / Medium+ screens: Enhanced table view -->
        <div class="table-responsive d-none d-md-block">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th role="button" @click="$parent.sort('ID')">
                            Clock ID
                        </th>
                        <th role="button" @click="$parent.sort('Employee')">
                            Employee
                        </th>
                        <th role="button" @click="$parent.sort('DateToday')">
                            Date
                        </th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Break Start</th>
                        <th>Break End</th>
                        <th>Total</th>
                        <th>Notes</th>
                        <th style="width: 120px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="record in $parent.timeRecords" :key="record?.ID || record?.id">
                        <!-- Clickable data row -->
                        <tr class="tr-clickable" @click="
                            $parent.toggleHistory(record?.ID || record?.id)
                            ">
                            <td class="text-secondary">
                                {{ record?.ID || record?.id || "-" }}
                            </td>
                            <td class="fw-semibold">
                                {{ record?.Employee || "-" }}
                            </td>
                            <td class="text-secondary">
                                {{ record?.DateToday || "-" }}
                            </td>
                            <td>
                                {{ $parent.formatDate(record?.TimeIn) }}
                            </td>
                            <td>
                                {{ $parent.formatDate(record?.TimeOut) }}
                            </td>
                            <td>
                                {{
                                    $parent.formatDate(record?.shortbreak_start)
                                }}
                            </td>
                            <td>
                                {{ $parent.formatDate(record?.shortbreak_end) }}
                            </td>
                            <td>
                                {{ record?.shortbreak_totaltime || 0 }} mins
                            </td>
                            <td class="text-truncate" style="max-width: 240px">
                                {{ record?.Notes || "-" }}
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" @click.stop="$parent.openEdit(record)">
                                    Edit
                                </button>
                            </td>
                        </tr>

                        <!-- Inline history row -->
                        <tr v-if="
                            $parent.expandedClockId ===
                            (record?.ID || record?.id)
                        " class="bg-light">
                            <td :colspan="10">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <strong class="me-2">Edit History</strong>
                                    <span v-if="$parent.historyLoading" class="spinner-border spinner-border-sm"></span>
                                </div>

                                <!-- No history -->
                                <div v-if="
                                    !$parent.historyLoading &&
                                    (!$parent.clockEditHistory ||
                                        !$parent.clockEditHistory.length)
                                " class="text-muted small">
                                    No edits recorded for this clock.
                                </div>

                                <!-- History table -->
                                <div v-else class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th style="width: 180px">
                                                    Edited At
                                                </th>
                                                <th style="width: 160px">
                                                    Edited By
                                                </th>
                                                <th>Changes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(
h, idx
                                                ) in $parent.clockEditHistory" :key="idx">
                                                <td>
                                                    {{
                                                        h.edited_at ||
                                                        h.created_at ||
                                                        "-"
                                                    }}
                                                </td>
                                                <td>
                                                    {{
                                                        h.edited_by ||
                                                        h.user ||
                                                        h.username ||
                                                        "-"
                                                    }}
                                                </td>
                                                <td>
                                                    <template v-if="
                                                        h.before && h.after
                                                    ">
                                                        <ul class="mb-0 small">
                                                            <li v-for="(
chg, i
                                                                ) in $parent.prettyDiff(
    h.before,
    h.after
)" :key="i">
                                                                <code>{{
                                                                    chg.key
                                                                }}</code>:
                                                                <span class="text-decoration-line-through text-muted">{{
                                                                    chg.from
                                                                    }}</span>
                                                                →
                                                                <strong>{{
                                                                    chg.to
                                                                }}</strong>
                                                            </li>
                                                        </ul>
                                                    </template>
                                                    <template v-else-if="
                                                        h.changes ||
                                                        h.delta ||
                                                        h.after
                                                    ">
                                                        <pre class="small mb-0">{{
                                                            h.changes ||
                                                            h.delta ||
                                                            h.after
                                                        }}</pre>
                                                    </template>
                                                    <template v-else>
                                                        <span class="text-muted small">—</span>
                                                    </template>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <button class="btn btn-outline-secondary" :disabled="$parent.page === 1" @click="$parent.prevPage()">
                Previous
            </button>

            <span>Page {{ $parent.page }} / {{ $parent.totalPages }}</span>

            <button class="btn btn-outline-secondary" :disabled="$parent.page >= $parent.totalPages"
                @click="$parent.nextPage()">
                Next
            </button>
        </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="$parent.showEditModal" class="modal modal-editRecord">
        <div class="modal-overlay" @click="$parent.closeEdit"></div>

        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Edit Time Record (ID:
                    {{ $parent.editOriginal?.ID }})
                </h5>

                <button type="button" class="btn-close" @click="$parent.closeEdit"></button>
            </div>

            <div class="modal-body">
                <form>
                    <fieldset>
                        <label>Employee</label>
                        <input type="text" class="form-control" v-model="$parent.editForm.Employee" disabled />
                    </fieldset>

                    <fieldset>
                        <label>Date</label>
                        <input type="date" class="form-control" v-model="$parent.editForm.DateToday" />
                    </fieldset>

                    <fieldset>
                        <label>Time In</label>
                        <input type="datetime-local" class="form-control" v-model="$parent.editForm.TimeIn_local" />
                    </fieldset>

                    <fieldset>
                        <label>Time Out</label>
                        <input type="datetime-local" class="form-control" v-model="$parent.editForm.TimeOut_local" />
                    </fieldset>

                    <fieldset>
                        <label>Break Start</label>
                        <input type="datetime-local" class="form-control"
                            v-model="$parent.editForm.shortbreak_start_local" />
                    </fieldset>

                    <fieldset>
                        <label>Break End</label>
                        <input type="datetime-local" class="form-control"
                            v-model="$parent.editForm.shortbreak_end_local" />
                    </fieldset>

                    <fieldset>
                        <label>Break Total (mins)</label>
                        <input type="number" min="0" class="form-control" v-model.number="$parent.editForm.shortbreak_totaltime
                            " />
                    </fieldset>

                    <fieldset>
                        <label></label>
                    </fieldset>

                    <fieldset>
                        <label>Notes</label>
                        <textarea class="form-control" rows="3" v-model="$parent.editForm.Notes">
                    </textarea>
                    </fieldset>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary text-white m-0" @click="$parent.submitEdit"
                    :disabled="$parent.submittingEdit">
                    Save changes
                </button>

                <button class="btn btn-secondary text-white m-0" @click="$parent.closeEdit">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { Select, DatePicker, Button } from 'primevue'

export default {
    components: {
        Select,
        DatePicker, Button
    },
    mounted() {
        console.log(this.$parent.timeRecords)
    }
}
</script>

<style scoped src="../hr.css"></style>
