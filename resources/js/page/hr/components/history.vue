<template>
    <div class="history-container">
        <TabMenu :model="items" v-model:activeIndex="activeIndex" class="mb-3" size="small" />

        <div class="history-content mt-3">
            <transition name="fade-slide" mode="out-in">
                <component :is="activeComponent" :key="active" :hr-context="$parent.hrContext" />
            </transition>
        </div>
    </div>
</template>


<script>
import TabMenu from "primevue/tabmenu";

import TimeRecordHistory from "../components/timerecordhistory.vue";
import LeaveHistory from "../components/leavehistory.vue";
import RateHistory from "../components/ratehistory.vue";
import ViolationsHistory from "../components/violationshistory.vue";

export default {
    components: {
        TabMenu,
        TimeRecordHistory,
        LeaveHistory,
        RateHistory,
        ViolationsHistory,
    },

    data() {
        return {
            activeIndex: 0,
            items: [
                { label: "Time Record", value: "time" },
                { label: "Leave", value: "leave" },
                { label: "Rate", value: "rate" },
                { label: "Violation", value: "violation" }
            ]
        };
    },

    computed: {
        active() {
            return this.items[this.activeIndex].value;
        },

        activeComponent() {
            return {
                time: "TimeRecordHistory",
                leave: "LeaveHistory",
                rate: "RateHistory",
                violation: "ViolationsHistory",
            }[this.active];
        }
    }
};
</script>


<style>
/* Optional: Better mobile UI */
.p-tabmenu-nav {
    flex-wrap: wrap;
}

.p-tabmenu-nav li {
    flex: 1 1 auto;
    text-align: center;
}

/* Animation */
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.35s ease;
}

.fade-slide-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
