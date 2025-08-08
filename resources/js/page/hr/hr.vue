<template>
  <div>
    <!-- Navigation Tabs -->
    <div class="nav nav-tabs">
      <template v-for="tab in tabs" :key="typeof tab === 'string' ? tab : tab.label">
        <!-- Regular tabs -->
        <button v-if="typeof tab === 'string'" class="nav-link" :class="{ active: currentView === tab }"
          @click="currentView = tab">
          {{ tab }}
        </button>

        <!-- Dropdown tabs -->
        <div v-else class="nav-item dropdown">
          <button class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button">
            {{ tab.label }}
          </button>
          <ul class="dropdown-menu">
            <li v-for="item in tab.dropdown" :key="item">
              <a class="dropdown-item" href="#" @click.prevent="currentView = item">
                {{ item }}
              </a>
            </li>
          </ul>
        </div>
      </template>
    </div>

    <!-- Render current component -->
    <div class="p-3">
      <component :is="currentViewComponent" :time-records="timeRecords" :filters="filters"
        :employee-names="employeeNames" :page="page" :total-pages="totalPages" :fetch-records="fetchRecords"
        :sort="sort" :format-date="formatDate" />

    </div>
  </div>
</template>

<script>
import hr from "./hr.js";
export default hr;
</script>

<style scoped>
.nav-tabs .nav-link {
  cursor: pointer;
}
</style>
