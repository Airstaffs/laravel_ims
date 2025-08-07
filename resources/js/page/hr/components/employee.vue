<template>
  <div class="employee-section">
    <h4>Employees</h4>
    <button class="btn btn-primary mb-3" @click="$parent.showAddEmployeeModal = true">Add Employee</button>

    <table class="table table-bordered">
      <thead>
        <tr>
          <th>#</th>
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

    <div v-if="$parent.showAddEmployeeModal" class="modal-mask">
      <div class="modal-container">
        <h5>Add Employee</h5>
        <input v-model="$parent.newEmployee.name" class="form-control mb-2" placeholder="Name" />
        <input v-model="$parent.newEmployee.position" class="form-control mb-2" placeholder="Position" />
        <div class="d-flex justify-content-end">
          <button class="btn btn-secondary me-2" @click="$parent.showAddEmployeeModal = false">Cancel</button>
          <button class="btn btn-success" @click="$parent.addEmployee()">Add</button>
        </div>
      </div>
    </div>
  </div>
</template>
