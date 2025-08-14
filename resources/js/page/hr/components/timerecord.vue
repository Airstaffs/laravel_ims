<template>
    <div class="time-record-wrapper">
        <!-- Filters -->
        <form>
            <fieldset>
                <label>Filber By Employee</label>
                <select
                    v-model="hrContext.filters.employee"
                    class="form-control"
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
            </fieldset>

            <fieldset>
                <label>Date From</label>
                <input
                    type="date"
                    v-model="hrContext.filters.dateFrom"
                    class="form-control"
                />
            </fieldset>

            <fieldset>
                <label>Date To</label>
                <input
                    type="date"
                    v-model="hrContext.filters.dateTo"
                    class="form-control"
                />
            </fieldset>

            <fieldset>
                <label></label>
                <button
                    class="btn btn-primary w-100"
                    @click="hrContext.fetchRecords"
                >
                    Apply Filters
                </button>
            </fieldset>
        </form>

        <!-- Table -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th @click="hrContext.sort('Employee')">Employee</th>
                    <th @click="hrContext.sort('DateToday')">Date</th>
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
                <tr
                    v-for="record in hrContext.timeRecords"
                    :key="record?.ID || record?.id"
                >
                    <td>{{ record?.Employee || "-" }}</td>
                    <td>{{ record?.DateToday || "-" }}</td>
                    <td>{{ hrContext.formatDate(record?.TimeIn) }}</td>
                    <td>{{ hrContext.formatDate(record?.TimeOut) }}</td>
                    <td>
                        {{ hrContext.formatDate(record?.shortbreak_start) }}
                    </td>
                    <td>{{ hrContext.formatDate(record?.shortbreak_end) }}</td>
                    <td>{{ record?.shortbreak_totaltime || 0 }} mins</td>
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

            <span>Page {{ hrContext.page }} / {{ hrContext.totalPages }}</span>

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
    <div v-if="hrContext.showEditModal" class="modal modal-editRecord">
        <div class="modal-overlay" @click="hrContext.closeEdit"></div>

        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Edit Time Record (ID:
                    {{ hrContext.editOriginal?.ID }})
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    @click="hrContext.closeEdit"
                ></button>
            </div>

            <div class="modal-body">
                <form>
                    <fieldset>
                        <label>Employee</label>
                        <input
                            type="text"
                            class="form-control"
                            v-model="hrContext.editForm.Employee"
                            disabled
                        />
                    </fieldset>

                    <fieldset>
                        <label>Date</label>
                        <input
                            type="date"
                            class="form-control"
                            v-model="hrContext.editForm.DateToday"
                        />
                    </fieldset>

                    <fieldset>
                        <label>Time In</label>
                        <input
                            type="datetime-local"
                            class="form-control"
                            v-model="hrContext.editForm.TimeIn_local"
                        />
                    </fieldset>

                    <fieldset>
                        <label>Time Out</label>
                        <input
                            type="datetime-local"
                            class="form-control"
                            v-model="hrContext.editForm.TimeOut_local"
                        />
                    </fieldset>

                    <fieldset>
                        <label>Break Start</label>
                        <input
                            type="datetime-local"
                            class="form-control"
                            v-model="hrContext.editForm.shortbreak_start_local"
                        />
                    </fieldset>

                    <fieldset>
                        <label>Break End</label>
                        <input
                            type="datetime-local"
                            class="form-control"
                            v-model="hrContext.editForm.shortbreak_end_local"
                        />
                    </fieldset>

                    <fieldset>
                        <label>Break Total (mins)</label>
                        <input
                            type="number"
                            min="0"
                            class="form-control"
                            v-model.number="
                                hrContext.editForm.shortbreak_totaltime
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
                            v-model="hrContext.editForm.Notes"
                        >
                        </textarea>
                    </fieldset>
                </form>
            </div>

            <div class="modal-footer">
                <button
                    class="btn btn-primary text-white m-0"
                    @click="hrContext.submitEdit"
                    :disabled="hrContext.submittingEdit"
                >
                    Save changes
                </button>

                <button
                    class="btn btn-secondary text-white m-0"
                    @click="hrContext.closeEdit"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
const { hrContext } = defineProps({
    hrContext: { type: Object, required: true },
});
</script>

<style scoped src="../hr.css"></style>
