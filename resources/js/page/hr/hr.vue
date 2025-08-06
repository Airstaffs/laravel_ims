<template>
  <div class="hr-module">
    <!-- Employee Section -->
    <div class="employee-section">
      <h4>Employees</h4>
      <button class="btn btn-primary mb-3" @click="showAddEmployeeModal = true">Add Employee</button>

      <table class="table table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Position</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(emp, index) in employees" :key="emp.id">
            <td>{{ index + 1 }}</td>
            <td>{{ emp.name }}</td>
            <td>{{ emp.position }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal for Adding Employee -->
    <div v-if="showAddEmployeeModal" class="modal-mask">
      <div class="modal-container">
        <h5>Add Employee</h5>
        <div class="form-group">
          <label>Name:</label>
          <input v-model="newEmployee.name" class="form-control" />
        </div>
        <div class="form-group">
          <label>Position:</label>
          <input v-model="newEmployee.position" class="form-control" />
        </div>

        <div class="mt-3 d-flex justify-content-end">
          <button class="btn btn-secondary me-2" @click="showAddEmployeeModal = false">Cancel</button>
          <button class="btn btn-success" @click="addEmployee">Add</button>
        </div>
      </div>
    </div>

    <!-- Future sections -->
    <div class="holiday-section">
      <!-- Add holiday UI here -->
    </div>

    <div class="announcement-section">
      <!-- Add announcement UI here -->
    </div>
  </div>
</template>

<script>
import hr from './hr.js'
export default hr
</script>

<style scoped>
.modal-mask {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 999;
}

.modal-container {
  background: white;
  padding: 20px;
  border-radius: 8px;
  width: 400px;
}
</style>
