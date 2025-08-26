<template>
    <div class="history-container">
        <div class="history-header">
            <ul class="list-unstyled m-0">
                <li
                    :class="{
                        active: $parent.hrContext.history.type === 'time',
                    }"
                    @click="$parent.hrContext.switchHistoryType('time')"
                >
                    Time Record
                </li>

                <li
                    :class="{
                        active: $parent.hrContext.history.type === 'leave',
                    }"
                    @click="$parent.hrContext.switchHistoryType('leave')"
                >
                    Leave
                </li>

                <li
                    :class="{
                        active: $parent.hrContext.history.type === 'rate',
                    }"
                    @click="$parent.hrContext.switchHistoryType('rate')"
                >
                    Rate
                </li>

                <li
                    :class="{
                        active: $parent.hrContext.history.type === 'violation',
                    }"
                    @click="$parent.hrContext.switchHistoryType('violation')"
                >
                    Violation
                </li>
            </ul>
        </div>

        <div class="history-content">
            <TimeRecordHistory
                v-if="$parent.hrContext.history.type === 'time'"
                :key="$parent.hrContext.history.type"
                :hr-context="$parent.hrContext"
            />

            <LeaveHistory
                v-if="$parent.hrContext.history.type === 'leave'"
                :hr-context="$parent.hrContext"
                class="leave"
            />

            <RateHistory
                v-if="$parent.hrContext.history.type === 'rate'"
                :key="$parent.hrContext.history.type"
                :hr-context="$parent.hrContext"
                class="rate"
            />

            <ViolationsHistory
                v-if="$parent.hrContext.history.type === 'violation'"
                :hr-context="$parent.hrContext"
                class="violation"
            />
        </div>
    </div>
</template>

<script>
import TimeRecordHistory from "../components/timerecordhistory.vue";
import LeaveHistory from "../components/leavehistory.vue";
import RateHistory from "../components/ratehistory.vue";
import ViolationsHistory from "../components/violationshistory.vue";

export default {
    components: {
        TimeRecordHistory,
        LeaveHistory,
        RateHistory,
        ViolationsHistory,
    },
};
</script>

<style>
.history-header ul li {
    display: inline-block;
    padding: 8px 15px;
    cursor: pointer;
}
.history-header ul li.active {
    border-bottom: 2px solid #007bff;
    font-weight: 700;
}
</style>
