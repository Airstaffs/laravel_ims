<template>
    <div v-if="$parent.showHolidayModal" class="modal modal-holiday">
        <div class="modal-overlay" @click="$parent.closeHolidayModal()"></div>

        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Holidays</h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                    @click="$parent.closeHolidayModal()"
                ></button>
            </div>

            <div class="modal-body">
                <div class="controller-header">
                    <div class="controller-year">
                        <label>Year (view)</label>
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

                <form @submit.prevent="$parent.saveHoliday()">
                    <fieldset>
                        <label>Title</label>
                        <input
                            class="form-control"
                            v-model="$parent.holidayForm.title"
                            required
                        />
                    </fieldset>
                    <fieldset>
                        <label>Status</label>
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
                    </fieldset>
                    <fieldset>
                        <label>Date</label>
                        <input
                            type="date"
                            class="form-control"
                            v-model="$parent.holidayForm.holidate"
                            required
                        />
                    </fieldset>
                    <fieldset>
                        <label></label>
                        <div class="has-checkbox">
                            <input
                                class="form-check-input holiday-checkbox m-0"
                                type="checkbox"
                                id="isRecurring"
                                v-model="$parent.holidayForm.is_recurring"
                            />
                            <label>Recurring yearly</label>
                        </div>
                    </fieldset>
                    <fieldset>
                        <button class="btn btn-primary" type="submit">
                            {{
                                $parent.holidayForm.holidayID ? "Update" : "Add"
                            }}
                        </button>
                        <button
                            class="btn btn-secondary"
                            type="button"
                            @click="$parent.resetHolidayForm()"
                        >
                            Clear
                        </button>
                    </fieldset>
                </form>

                <div class="holiday-table-container">
                    <table>
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

                        <thead>
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
                            <template
                                v-for="(h, i) in $parent.holidays"
                                :key="h.holidayID"
                            >
                                <tr>
                                    <td>{{ i + 1 }}</td>
                                    <td>{{ h.title }}</td>
                                    <td>{{ h.status }}</td>
                                    <td>
                                        <span :title="'Stored: ' + h.holidate">
                                            {{ h.display_date }}
                                        </span>
                                    </td>
                                    <td>{{ h.is_recurring ? "Yes" : "No" }}</td>
                                    <td>
                                        <div class="btn-action-container">
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
                            </template>
                            <tr v-if="!$parent.holidays.length">
                                <td colspan="6" class="text-center text-muted">
                                    No holidays found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped src="../hr.css"></style>
