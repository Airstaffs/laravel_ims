<template>
    <div class="hr-module">
        <div class="sidebar-nav">
            <ul class="list-unstyled m-0">
                <li
                    :class="{ active: tab === currentView }"
                    v-for="tab in [currentView]"
                    :key="tab"
                    @click="toggleDropdown"
                >
                    <div
                        class="d-flex justify-content-between align-items-center"
                        style="height: 40px"
                    >
                        <span @click="setView(tab)">
                            {{ tab }}
                        </span>
                        <span style="cursor: pointer">
                            <i
                                :class="
                                    dropdownOpen
                                        ? 'fa fa-chevron-up'
                                        : 'fa fa-chevron-down'
                                "
                            ></i>
                        </span>
                    </div>

                    <!-- Dropdown for other tabs -->
                    <ul v-if="dropdownOpen" class="list-unstyled">
                        <li
                            v-for="tab in tabs.filter((t) => t !== currentView)"
                            :key="tab"
                            @click="
                                setView(tab);
                                toggleDropdown;
                            "
                            style="cursor: pointer"
                        >
                            {{ tab }}
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="main-content">
            <Employee v-if="currentView === 'Employee'" />

            <TimeRecord v-if="currentView === 'Time Record'" />

            <Violations v-if="currentView === 'Violations'" />

            <AnnouncementModal v-if="currentView === 'Announcement'" />

            <HolidayModal v-if="currentView === 'Holiday'" />

            <History v-if="currentView === 'History'" />

            <scheduling v-if="currentView === 'Scheduling'" :ctx="hrContext" />
        </div>
    </div>
</template>

<script>
import hr from "./hr.js";
export default hr;
</script>

<style scoped src="./hr.css"></style>
