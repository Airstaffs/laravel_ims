<template>
    <div class="hr-module">
        <div class="sidebar-nav">
            <ul class="list-unstyled m-0">
                <li
                    v-for="tab in tabs"
                    :key="tab"
                    :class="{ active: tab === currentView }"
                    @click="setView(tab)"
                >
                    {{ tab }}
                </li>
            </ul>
        </div>

        <div class="main-content">
            <Employee
                v-if="currentView === 'Employee'"
                :hr-context="hrContext"
            />

            <TimeRecord
                v-if="currentView === 'Time Record'"
                :hr-context="hrContext"
            />

            <Violations v-if="currentView === 'Violations'" />

            <AnnouncementModal
                v-if="currentView === 'Announcement'"
                :hr-context="hrContext"
            />
        </div>
    </div>

    <div class="hr-module">
        <!-- Main views -->

        <TimeRecordHistory
            v-if="currentView === 'Time Record Edit History'"
            :key="currentView"
            :hr-context="hrContext"
        />
        <LeaveHistory v-show="currentView === 'Employee Leave History'" />
        <RateHistory
            v-show="currentView === 'Employee Rate History'"
            :hr-context="hrContext"
        />

        <ViolationsHistory v-show="currentView === 'Violations History'" />

        <!-- Modals -->
        <HolidayModal />
    </div>
</template>

<script>
import hr from "./hr.js";
export default hr;
</script>

<style scoped src="./hr.css"></style>
