<template>
    <div class="hr-module">
        <nav class="nav-tabs d-flex align-items-center justify-content-between">
            <!-- LEFT: view tabs / dropdown -->
            <ul class="list-unstyled m-0 d-flex gap-2 flex-wrap">
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

            <!-- RIGHT: modal triggers (Add dropdown) -->
            <div class="btn-group">
                <button
                    class="btn btn-outline-primary dropdown-toggle"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    Add
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a
                            href="#"
                            class="dropdown-item"
                            @click.prevent="openHolidayModal"
                        >
                            Add Holiday
                        </a>
                        <a
                            href="#"
                            class="dropdown-item"
                            @click.prevent="openAnnouncementModal"
                        >
                            Add Announcement
                        </a>
                    </li>
                    <!-- (future) add more modal actions here -->
                </ul>
            </div>
        </nav>

        <!-- Main views -->
        <Employee v-if="currentView === 'Employee'" :hr-context="hrContext" />

        <TimeRecord
            v-if="currentView === 'Time Record'"
            :hr-context="hrContext"
        />
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
        <Violations v-show="currentView === 'Violations'" />

        <!-- Modals -->
        <HolidayModal />
        <AnnouncementModal :hr-context="hrContext" />
    </div>
</template>

<script>
import hr from "./hr.js";
export default hr;
</script>

<style scoped src="./hr.css"></style>
