<template>
  <div class="p-3">
    
    <div class="row mb-3">
      <div class="col-md-3">
        <label>Filter by Employee</label>
        <select v-model="filters.employee" class="form-select">
          <option value="">All</option>
          <option v-for="name in employeeNames" :key="name" :value="name">{{ name }}</option>
        </select>
      </div>
      <div class="col-md-3">
        <label>Date From</label>
        <input type="date" v-model="filters.dateFrom" class="form-control" />
      </div>
      <div class="col-md-3">
        <label>Date To</label>
        <input type="date" v-model="filters.dateTo" class="form-control" />
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button class="btn btn-primary w-100" @click="fetchRecords">Apply Filters</button>
      </div>
    </div>

  

    <table class="table table-bordered table-sm">
      <thead>
        <tr>
          <th @click="sort('Employee')">Employee</th>
          <th @click="sort('DateToday')">Date</th>
          <th>Time In
            <hr class="my-1" /> Time Out
          </th>
          <th>Break Start - End
            <hr class="my-1" /> Total
          </th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="record in timeRecords" :key="record?.ID || record?.id">

          <td>{{ record?.Employee || '-' }}</td>
          <td>{{ record?.DateToday || '-' }}</td>
          <td>
            {{ formatDate(record?.TimeIn) }}
            <hr class="my-1" />
            {{ formatDate(record?.TimeOut) }}
          </td>
          <td>
            {{ formatDate(record?.shortbreak_start) }} - {{ formatDate(record?.shortbreak_end) }}
            <hr class="my-1" />
            {{ record?.shortbreak_totaltime || 0 }} mins
          </td>
          <td>{{ record?.Notes || '-' }}</td>
        </tr>
      </tbody>
    </table>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <button class="btn btn-outline-secondary" :disabled="page === 1"
        @click="() => { props.page > 1 && (props.page--, props.fetchRecords()); }">
        Previous
      </button>

      <span>Page {{ page }} / {{ totalPages }}</span>

      <button class="btn btn-outline-secondary" :disabled="page >= totalPages"
        @click="() => { props.page < totalPages && (props.page++, props.fetchRecords()); }">
        Next
      </button>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  timeRecords: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  employeeNames: { type: Array, default: () => [] },
  page: { type: Number, default: 1 },
  totalPages: { type: Number, default: 1 },
  fetchRecords: { type: Function, required: true },
  sort: { type: Function, required: true },
  formatDate: { type: Function, required: true },
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
