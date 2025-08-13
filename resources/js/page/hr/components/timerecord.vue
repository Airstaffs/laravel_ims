<template>
    <div class="time-record-wrapper">
        <div class="p-3">
            <!-- Filters -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label>Filter by Employee</label>
                    <select
                        v-model="hrContext.filters.employee"
                        class="form-select"
                    >
                        <option value="">All</option>
                        <option
                            v-for="name in hrContext.employeeNames"
                            :key="name"
                            :value="name"
                        >
                            {{ name }}
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Date From</label>
                    <input
                        type="date"
                        v-model="hrContext.filters.dateFrom"
                        class="form-control"
                    />
                </div>
                <div class="col-md-3">
                    <label>Date To</label>
                    <input
                        type="date"
                        v-model="hrContext.filters.dateTo"
                        class="form-control"
                    />
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button
                        class="btn btn-primary w-100"
                        @click="hrContext.fetchRecords"
                    >
                        Apply Filters
                    </button>
                </div>
            </div>

            <!-- Table -->
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th @click="hrContext.sort('Employee')">Employee</th>
                        <th @click="hrContext.sort('DateToday')">Date</th>
                        <th>
                            Time In
                            <hr class="my-1" />
                            Time Out
                        </th>
                        <th>
                            Break Start - End
                            <hr class="my-1" />
                            Total
                        </th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="record in hrContext.timeRecords"
                        :key="record?.ID || record?.id"
                    >
                        <td>{{ record?.Employee || "-" }}</td>
                        <td>{{ record?.DateToday || "-" }}</td>
                        <td>
                            {{ hrContext.formatDate(record?.TimeIn) }}
                            <hr class="my-1" />
                            {{ hrContext.formatDate(record?.TimeOut) }}
                        </td>
                        <td>
                            {{
                                hrContext.formatDate(record?.shortbreak_start)
                            }}
                            - {{ hrContext.formatDate(record?.shortbreak_end) }}
                            <hr class="my-1" />
                            {{ record?.shortbreak_totaltime || 0 }} mins
                        </td>
                        <td>{{ record?.Notes || "-" }}</td>
                        <td>
                            <button
                                class="btn btn-sm btn-outline-primary"
                                @click="hrContext.openEdit(record)"
                            >
                                Edit
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <button
                    class="btn btn-outline-secondary"
                    :disabled="hrContext.page === 1"
                    @click="hrContext.prevPage()"
                >
                    Previous
                </button>

                <span
                    >Page {{ hrContext.page }} /
                    {{ hrContext.totalPages }}</span
                >

                <button
                    class="btn btn-outline-secondary"
                    :disabled="hrContext.page >= hrContext.totalPages"
                    @click="hrContext.nextPage()"
                >
                    Next
                </button>
            </div>
        </div>

        <!-- Edit Modal -->
        <div
            v-if="hrContext.showEditModal"
            class="modal fade show d-block"
            tabindex="-1"
            style="background: rgba(0, 0, 0, 0.5)"
        >
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title">
                            Edit Time Record (ID:
                            {{ hrContext.editOriginal?.ID }})
                        </h6>
                        <button
                            type="button"
                            class="btn-close"
                            @click="hrContext.closeEdit"
                        ></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Employee</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    v-model="hrContext.editForm.Employee"
                                    disabled
                                />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Date</label>
                                <input
                                    type="date"
                                    class="form-control"
                                    v-model="hrContext.editForm.DateToday"
                                />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Time In</label>
                                <input
                                    type="datetime-local"
                                    class="form-control"
                                    v-model="hrContext.editForm.TimeIn_local"
                                />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Time Out</label>
                                <input
                                    type="datetime-local"
                                    class="form-control"
                                    v-model="hrContext.editForm.TimeOut_local"
                                />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Break Start</label>
                                <input
                                    type="datetime-local"
                                    class="form-control"
                                    v-model="
                                        hrContext.editForm
                                            .shortbreak_start_local
                                    "
                                />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Break End</label>
                                <input
                                    type="datetime-local"
                                    class="form-control"
                                    v-model="
                                        hrContext.editForm.shortbreak_end_local
                                    "
                                />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label"
                                    >Break Total (mins)</label
                                >
                                <input
                                    type="number"
                                    min="0"
                                    class="form-control"
                                    v-model.number="
                                        hrContext.editForm.shortbreak_totaltime
                                    "
                                />
                            </div>

                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea
                                    class="form-control"
                                    rows="3"
                                    v-model="hrContext.editForm.Notes"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button
                            class="btn btn-light"
                            @click="hrContext.closeEdit"
                        >
                            Cancel
                        </button>
                        <button
                            class="btn btn-primary"
                            @click="hrContext.submitEdit"
                            :disabled="hrContext.submittingEdit"
                        >
                            Save changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
const { hrContext } = defineProps({
    hrContext: { type: Object, required: true },
});
</script>

<style scoped>
th {
    cursor: pointer;
}

th:hover {
    text-decoration: underline;
}
</style>
