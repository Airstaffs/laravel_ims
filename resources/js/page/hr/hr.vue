<template>
  <div>
    <!-- Navigation Tabs -->
    <div class="nav nav-tabs">
      <template v-for="tab in tabs" :key="typeof tab === 'string' ? tab : tab.label">
        <!-- Regular tabs -->
        <button v-if="typeof tab === 'string'" class="nav-link"
          :class="{ active: currentView === tab }"
          @click="setView(tab)">
          {{ tab }}
        </button>

        <!-- Dropdown tabs -->
        <div v-else class="nav-item dropdown">
          <button class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button">
            {{ tab.label }}
          </button>
          <ul class="dropdown-menu">
            <li v-for="item in tab.dropdown" :key="item">
              <a class="dropdown-item" href="#" @click.prevent="setView(item)">
                {{ item }}
              </a>
            </li>
          </ul>
        </div>
      </template>
    </div>

    <!-- Render current view directly -->
    <div class="p-3">
      <Employee v-show="currentView === 'Employee'" />
      <TimeRecord v-show="currentView === 'Time Record'" :hr-context="hrContext" />
      <TimeRecordHistory v-show="currentView === 'Time Record Edit History'" />
      <LeaveHistory v-show="currentView === 'Employee Leave History'" />
      <RateHistory v-show="currentView === 'Employee Rate History'" />
      <ViolationsHistory v-show="currentView === 'Violations History'" />
      <Violations v-show="currentView === 'Violations'" />
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
