<template>
    <div class="employee-section">
        <h4>Employees</h4>
        <button class="btn btn-primary mb-3" @click="$parent.showAddEmployeeModal = true">
            Add Employee
        </button>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Current Rate<br><small>Monthly | Hourly</small></th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="(emp, index) in $parent.hrContext.employees" :key="emp.id">
                    <td>{{ index + 1 }}</td>
                    <td>{{ emp.name }}</td>
                    <td>{{ emp.position }}</td>
                    <td>
                        <span>
                            {{ emp.current_monthly_rate != null ? ('₱' + Number(emp.current_monthly_rate).toFixed(2)) :
                            '-' }}
                            |
                            {{ emp.current_hourly_rate != null ? ('₱' + Number(emp.current_hourly_rate).toFixed(2)) :
                            '-' }}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" @click="$parent.hrContext.openRateModal(emp)">
                            Edit Employee Rate
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Add Employee Modal (unchanged) -->
        <div v-if="$parent.showAddEmployeeModal" class="modal-mask">
            <div class="modal-container">
                <h5>Add Employee</h5>
                <input v-model="$parent.newEmployee.name" class="form-control mb-2" placeholder="Name" />
                <input v-model="$parent.newEmployee.position" class="form-control mb-2" placeholder="Position" />
                <div class="d-flex justify-content-end">
                    <button class="btn btn-secondary me-2" @click="$parent.showAddEmployeeModal = false">
                        Cancel
                    </button>
                    <button class="btn btn-success" @click="$parent.addEmployee()">
                        Add
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Rate Modal (driven by parent hrContext) -->
        <div v-if="$parent.hrContext.showRateModal" class="modal-mask">
            <div class="modal-container" style="max-width: 520px">
                <h5 class="mb-3">
                    Edit Employee Rate —
                    {{ $parent.hrContext.selectedEmployee?.name }}
                </h5>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Effective Start</label>
                        <input type="date" class="form-control" v-model="$parent.hrContext.rateForm.effective_start" />
                    </div>
                    <div class="col-6">
                        <label class="form-label">Effective End (optional)</label>
                        <input type="date" class="form-control" v-model="$parent.hrContext.rateForm.effective_end" />
                    </div>

                    <div class="col-6">
                        <label class="form-label">Monthly Rate (PHP)</label>
                        <input type="number" step="0.01" class="form-control" v-model.number="$parent.hrContext.rateForm.monthly_rate
                            " />
                    </div>
                    <div class="col-6">
                        <label class="form-label">Hourly Rate (PHP)</label>
                        <input type="number" step="0.01" class="form-control" v-model.number="$parent.hrContext.rateForm.hourly_rate
                            " />
                    </div>

                    <div class="col-6">
                        <label class="form-label">Currency</label>
                        <input type="text" maxlength="3" class="form-control" @input="
                            ($event) => {
                                $event.target.value =
                                    $event.target.value.toUpperCase();
                            }
                        " v-model="$parent.hrContext.rateForm.currency" />
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-secondary me-2" @click="$parent.hrContext.closeRateModal()">
                        Cancel
                    </button>
                    <button class="btn btn-success" :disabled="$parent.hrContext.savingRate"
                        @click="$parent.hrContext.submitRate()">
                        <span v-if="$parent.hrContext.savingRate" class="spinner-border spinner-border-sm me-1"></span>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
