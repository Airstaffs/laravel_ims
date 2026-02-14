<template>
    <div class="hr-module">
        <div class="sidebar-nav">
            <div class="card desktop-only">
                <Menu :model="newTabs" />
            </div>

            <ul class="list-unstyled m-0 mobile-only">
                <li
                    :class="{ active: newTabsItem.label === currentView }"
                    v-for="newTabsItem in [
                        newTabs.find((t) => t.label === currentView),
                    ]"
                    :key="newTabsItem.label"
                    @click="toggleDropdown"
                >
                    <div
                        class="d-flex justify-content-between align-items-center"
                        style="height: 40px"
                    >
                        <span @click.stop="setView(newTabsItem.label)">
                            <i
                                :class="newTabsItem.icon"
                                style="margin-right: 5px"
                            ></i>
                            {{ newTabsItem.label }}
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
                            v-for="tab in newTabs.filter(
                                (t) => t.label !== currentView,
                            )"
                            :key="tab.label"
                            @click.stop="
                                setView(tab.label);
                                toggleDropdown();
                            "
                            style="cursor: pointer"
                        >
                            <i :class="tab.icon" style="margin-right: 5px"></i>
                            {{ tab.label }}
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

            <Payroll v-if="currentView === 'Payroll'" />
        </div>
    </div>
</template>

<script>
import hr from "./hr.js";
export default hr;
</script>

<style scoped src="./hr.css"></style>
