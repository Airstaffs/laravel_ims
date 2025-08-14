<template>
    <div class="hr-module">
        <nav class="nav-tabs">
            <ul class="list-unstyled m-0">
                <li
                    v-for="tab in tabs"
                    :key="typeof tab === 'string' ? tab : tab.label"
                >
                    <button
                        v-if="typeof tab === 'string'"
                        class="btn btn-nav"
                        :class="{ active: currentView === tab }"
                        @click="setView(tab)"
                    >
                        {{ tab }}
                    </button>

                    <template v-else>
                        <button
                            class="btn btn-nav btn-dropdown dropdown-toggle"
                            data-bs-toggle="dropdown"
                        >
                            <span>{{ tab.label }}</span>
                        </button>

                        <ul class="list-unstyled dropdown-menu m-0 p-0">
                            <li v-for="item in tab.dropdown" :key="item">
                                <a
                                    href="#"
                                    class="dropdown-item"
                                    @click.prevent="setView(item)"
                                >
                                    {{ item }}
                                </a>
                            </li>
                        </ul>
                    </template>
                </li>
            </ul>
        </nav>

        <Employee
            v-if="currentView === 'Employee'"
            :key="currentView"
            :show-add-employee-modal="showAddEmployeeModal"
            :new-employee="newEmployee"
            @open-add-employee="showAddEmployeeModal = true"
            @close-add-employee="showAddEmployeeModal = false"
            @add-employee="addEmployee"
        />

        <TimeRecord
            v-if="currentView === 'Time Record'"
            :hr-context="hrContext"
        />
        <TimeRecordHistory
            v-show="currentView === 'Time Record Edit History'"
        />
        <LeaveHistory v-show="currentView === 'Employee Leave History'" />
        <RateHistory v-show="currentView === 'Employee Rate History'" />
        <ViolationsHistory v-show="currentView === 'Violations History'" />
        <Violations v-show="currentView === 'Violations'" />
    </div>
</template>

<script>
import hr from "./hr.js";
export default hr;
</script>

<style scoped src="./hr.css"></style>
