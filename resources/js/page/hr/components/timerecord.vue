<template>
    <div class="time-record-wrapper">
        <form @submit.prevent="$parent.fetchRecords()">
            <fieldset>
                <label>Filber By Employee</label>
                <select v-model="$parent.filters.employee" class="form-control">
                    <option value="">All</option>
                    <option
                        v-for="name in $parent.employeeNames"
                        :key="name"
                        :value="name"
                    >
                        {{ name }}
                    </option>
                </select>
            </fieldset>

            <fieldset>
                <label>Date From</label>
                <input
                    type="date"
                    v-model="$parent.filters.dateFrom"
                    class="form-control"
                />
            </fieldset>

            <fieldset>
                <label>Date To</label>
                <input
                    type="date"
                    v-model="$parent.filters.dateTo"
                    class="form-control"
                />
            </fieldset>

            <fieldset>
                <label></label>
                <button
                    class="btn btn-primary w-100"
                    @click="$parent.fetchRecords"
                >
                    Apply Filters
                </button>
            </fieldset>
        </form>

        <!-- Table -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th @click="$parent.sort('ID')">Clock ID</th>
                    <th @click="$parent.sort('Employee')">Employee</th>
                    <th @click="$parent.sort('DateToday')">Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Break Start</th>
                    <th>Break End</th>
                    <th>Total</th>
                    <th>Notes</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <template
                    v-for="record in $parent.timeRecords"
                    :key="record?.ID || record?.id"
                >
                    <!-- Clickable data row -->
                    <tr
                        class="tr-clickable"
                        @click="$parent.toggleHistory(record?.ID || record?.id)"
                    >
                        <td>{{ record?.ID || record?.id || "-" }}</td>
                        <td>{{ record?.Employee || "-" }}</td>
                        <td>{{ record?.DateToday || "-" }}</td>
                        <td>{{ $parent.formatDate(record?.TimeIn) }}</td>
                        <td>{{ $parent.formatDate(record?.TimeOut) }}</td>
                        <td>
                            {{ $parent.formatDate(record?.shortbreak_start) }}
                        </td>
                        <td>
                            {{ $parent.formatDate(record?.shortbreak_end) }}
                        </td>
                        <td>{{ record?.shortbreak_totaltime || 0 }} mins</td>
                        <td>{{ record?.Notes || "-" }}</td>
                        <td>
                            <button
                                class="btn btn-sm btn-outline-primary"
                                @click.stop="$parent.openEdit(record)"
                            >
                                Edit
                            </button>
                        </td>
                    </tr>

                    <!-- Inline history row -->
                    <tr
                        v-if="
                            $parent.expandedClockId ===
                            (record?.ID || record?.id)
                        "
                        class="bg-light"
                    >
                        <td :colspan="10">
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
                                        <tr
                                            v-for="(
                                                h, idx
                                            ) in $parent.clockEditHistory"
                                            :key="idx"
                                        >
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
                                                <template
                                                    v-if="h.before && h.after"
                                                >
                                                    <ul class="mb-0 small">
                                                        <li
                                                            v-for="(
                                                                chg, i
                                                            ) in $parent.prettyDiff(
                                                                h.before,
                                                                h.after
                                                            )"
                                                            :key="i"
                                                        >
                                                            <code>{{
                                                                chg.key
                                                            }}</code
                                                            >:
                                                            <span
                                                                class="text-decoration-line-through text-muted"
                                                                >{{
                                                                    chg.from
                                                                }}</span
                                                            >
                                                            →
                                                            <strong>{{
                                                                chg.to
                                                            }}</strong>
                                                        </li>
                                                    </ul>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        h.changes ||
                                                        h.delta ||
                                                        h.after
                                                    "
                                                >
                                                    <pre class="small mb-0">{{
                                                        h.changes ||
                                                        h.delta ||
                                                        h.after
                                                    }}</pre>
                                                </template>
                                                <template v-else>
                                                    <span
                                                        class="text-muted small"
                                                        >—</span
                                                    >
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

<style scoped src="../hr.css"></style>
