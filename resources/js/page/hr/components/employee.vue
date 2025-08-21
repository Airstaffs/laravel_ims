<template>
    <div class="employee-container">
        <div class="employee-header">
            <h4>Employees</h4>
            <button
                class="btn btn-primary text-white m-0"
                @click="$parent.openAddEmployeeModal()"
            >
                Add Employee
            </button>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Current Rate<br /><small>Monthly | Hourly</small></th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="(emp, index) in $parent.employees" :key="emp.id">
                    <td>{{ index + 1 }}</td>
                    <td>{{ emp.name }}</td>
                    <td>{{ emp.position }}</td>
                    <td>
                        <span>
                            {{
                                emp.current_monthly_rate != null
                                    ? "₱" +
                                      Number(emp.current_monthly_rate).toFixed(
                                          2
                                      )
                                    : "-"
                            }}
                            |
                            {{
                                emp.current_hourly_rate != null
                                    ? "₱" +
                                      Number(emp.current_hourly_rate).toFixed(2)
                                    : "-"
                            }}
                        </span>
                    </td>
                    <td>
                        <button
                            class="btn btn-sm btn-outline-primary"
                            @click="$parent.hrContext.openRateModal(emp)"
                        >
                            Edit Employee Rate
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <div
            v-if="$parent.showAddEmployeeModal"
            class="modal modal-addEmployee"
        >
            <div
                class="modal-overlay"
                @click="$parent.closeAddEmployeeModal()"
            ></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Employee</h5>
                </div>

                <div class="modal-body">
                    <form>
                        <fieldset>
                            <input
                                v-model="$parent.newEmployee.name"
                                class="form-control"
                                placeholder="Name"
                            />
                            <input
                                v-model="$parent.newEmployee.position"
                                class="form-control"
                                placeholder="Position"
                            />
                        </fieldset>
                    </form>
                </div>

                <div class="modal-footer">
                    <button
                        class="btn btn-success text-white"
                        @click="$parent.addEmployee()"
                    >
                        Add
                    </button>

                    <button
                        class="btn btn-secondary text-white"
                        @click="$parent.closeAddEmployeeModal()"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Rate Modal (driven by parent hrContext) -->
        <div
            v-if="$parent.hrContext.showRateModal"
            class="modal modal-rateEmployee"
        >
            <div
                class="modal-overlay"
                @click="$parent.hrContext.closeRateModal()"
            ></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Edit Employee Rate —
                        {{ $parent.hrContext.selectedEmployee?.name }}
                    </h5>
                </div>

                <div class="modal-body">
                    <form>
                        <fieldset>
                            <label>Effective Start</label>
                            <input
                                type="date"
                                class="form-control"
                                v-model="
                                    $parent.hrContext.rateForm.effective_start
                                "
                            />
                        </fieldset>

                        <fieldset>
                            <label>Effective End (optional)</label>
                            <input
                                type="date"
                                class="form-control"
                                v-model="
                                    $parent.hrContext.rateForm.effective_end
                                "
                            />
                        </fieldset>

                        <fieldset>
                            <label>Monthly Rate (PHP)</label>
                            <input
                                type="number"
                                step="0.01"
                                class="form-control"
                                v-model.number="
                                    $parent.hrContext.rateForm.monthly_rate
                                "
                            />
                        </fieldset>

                        <fieldset>
                            <label>Hourly Rate (PHP)</label>
                            <input
                                type="number"
                                step="0.01"
                                class="form-control"
                                v-model.number="
                                    $parent.hrContext.rateForm.hourly_rate
                                "
                            />
                        </fieldset>

                        <fieldset>
                            <label>Currency</label>
                            <input
                                type="text"
                                maxlength="3"
                                class="form-control"
                                @input="
                                    ($event) => {
                                        $event.target.value =
                                            $event.target.value.toUpperCase();
                                    }
                                "
                                v-model="$parent.hrContext.rateForm.currency"
                            />
                        </fieldset>
                    </form>
                </div>

                <div class="modal-footer">
                    <button
                        class="btn btn-success text-white"
                        :disabled="$parent.hrContext.savingRate"
                        @click="$parent.hrContext.submitRate()"
                    >
                        <span
                            v-if="$parent.hrContext.savingRate"
                            class="spinner-border spinner-border-sm me-1"
                        ></span>
                        Save
                    </button>

                    <button
                        class="btn btn-secondary text-white"
                        @click="$parent.hrContext.closeRateModal()"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped src="../hr.css"></style>
