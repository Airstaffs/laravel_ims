<template>
    <div class="employee-container">
        <div class="employee-header">
            <h3 class="section-title m-0">Employee</h3>
            <button
                class="btn btn-addEmployee"
                @click="$parent.showAddEmployeeModal = true"
            >
                Add Employee
            </button>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Name</th>
                    <th>Position</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(emp, index) in $parent.employees" :key="emp.id">
                    <td>{{ index + 1 }}</td>
                    <td>{{ emp.name }}</td>
                    <td>{{ emp.position }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div v-if="$parent.showAddEmployeeModal" class="modal modal-addEmployee">
        <div
            class="modal-overlay"
            @click="$parent.showAddEmployeeModal = false"
        ></div>

        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add Employee</h2>
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
                    class="btn btn-success m-0 text-white"
                    @click="$parent.addEmployee()"
                >
                    Add
                </button>
                <button
                    class="btn btn-secondary m-0 text-white"
                    @click="$parent.showAddEmployeeModal = false"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped src="../hr.css"></style>
