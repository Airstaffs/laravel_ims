<template>
    <div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true">
        <div
            class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered mx-3"
        >
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Holidays</h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                        @click="$parent.resetHolidayForm()"
                    ></button>
                </div>

                <div class="modal-body">
                    <!-- Top controls -->
                    <div class="d-flex gap-2 align-items-end flex-wrap">
                        <div>
                            <label class="form-label">Year (view)</label>
                            <input
                                type="number"
                                class="form-control"
                                v-model.number="$parent.holidayYear"
                                min="2000"
                                max="2100"
                                @change="$parent.fetchHolidays()"
                            />
                        </div>
                        <button
                            class="btn btn-outline-secondary ms-auto"
                            @click="$parent.fetchHolidays()"
                        >
                            Refresh
                        </button>
                    </div>

                    <hr />

                    <!-- Add/Edit form -->
                    <form @submit.prevent="$parent.saveHoliday()">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Title</label>
                                <input
                                    class="form-control"
                                    v-model="$parent.holidayForm.title"
                                    required
                                />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select
                                    class="form-select"
                                    v-model="$parent.holidayForm.status"
                                    required
                                >
                                    <option value="Regular Holiday">
                                        Regular Holiday
                                    </option>
                                    <option value="Special Holiday">
                                        Special Holiday
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date</label>
                                <input
                                    type="date"
                                    class="form-control"
                                    v-model="$parent.holidayForm.holidate"
                                    required
                                />
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check">
                                    <input
                                        class="form-check-input holiday-checkbox"
                                        type="checkbox"
                                        id="isRecurring"
                                        v-model="
                                            $parent.holidayForm.is_recurring
                                        "
                                    />
                                    <label
                                        class="form-check-label"
                                        for="isRecurring"
                                        >Recurring yearly</label
                                    >
                                </div>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button class="btn btn-primary" type="submit">
                                    {{
                                        $parent.holidayForm.holidayID
                                            ? "Update"
                                            : "Add"
                                    }}
                                </button>
                                <button
                                    class="btn btn-secondary"
                                    type="button"
                                    @click="$parent.resetHolidayForm()"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                    </form>

                    <hr />

                    <!-- Table -->
                    <div class="table-responsive">
                        <table
                            class="table table-sm table-bordered align-middle table-fixed"
                            id="holidayTable"
                        >
                            <!-- lock column widths -->
                            <colgroup>
                                <col style="width: 10px" />
                                <!-- # -->
                                <col style="width: 420px" />
                                <!-- Title -->
                                <col style="width: 130px" />
                                <!-- Status -->
                                <col style="width: 10px" />
                                <!-- Date -->
                                <col style="width: 0px" />
                                <!-- Recurring -->
                                <col style="width: 0px" />
                                <!-- Actions -->
                            </colgroup>

                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Recurring</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="(h, i) in $parent.holidays"
                                    :key="h.holidayID"
                                >
                                    <td>{{ i + 1 }}</td>
                                    <!-- clamp the title -->
                                    <td class="title-cell">
                                        <span class="truncate-2">{{
                                            h.title
                                        }}</span>
                                    </td>
                                    <td>{{ h.status }}</td>
                                    <td>
                                        <span
                                            :title="'Stored: ' + h.holidate"
                                            >{{ h.display_date }}</span
                                        >
                                    </td>
                                    <td>{{ h.is_recurring ? "Yes" : "No" }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button
                                                class="btn btn-outline-primary"
                                                @click="$parent.editHoliday(h)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                class="btn btn-outline-danger"
                                                @click="
                                                    $parent.deleteHoliday(
                                                        h.holidayID
                                                    )
                                                "
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!$parent.holidays.length">
                                    <td
                                        colspan="6"
                                        class="text-center text-muted"
                                    >
                                        No holidays found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                        @click="$parent.resetHolidayForm()"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
