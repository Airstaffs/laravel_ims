<template>
    <div class="history-container">
        <div class="history-header">
            <ul class="list-unstyled m-0">
                <li
                    :class="{ active: activeHistoryTab === 'time-record' }"
                    @click="setHistoryActive('time-record')"
                >
                    Time Record
                </li>
                <li
                    :class="{ active: activeHistoryTab === 'leave' }"
                    @click="setHistoryActive('leave')"
                >
                    Leave
                </li>
                <li
                    :class="{ active: activeHistoryTab === 'rate' }"
                    @click="setHistoryActive('rate')"
                >
                    Rate
                </li>
                <li
                    :class="{ active: activeHistoryTab === 'violation' }"
                    @click="setHistoryActive('violation')"
                >
                    Violation
                </li>
            </ul>
        </div>

        <div class="history-content">
            <TimeRecordHistory
                v-if="activeHistoryTab === 'time-record'"
                :hrContext="hrContext"
                class="time-record"
            />
            <LeaveHistory v-if="activeHistoryTab === 'leave'" class="leave" />
            <RateHistory v-if="activeHistoryTab === 'rate'" class="rate" />
            <ViolationsHistory
                v-if="activeHistoryTab === 'violation'"
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
    data() {
        return {
            activeHistoryTab: "time-record",

            rateHistory: [],
            rateHistoryFilterEmployeeId: "",
            rateHistoryFilterOnlyActive: false,
        };
    },
    methods: {
        setHistoryActive(tab) {
            this.activeHistoryTab = tab;
        },

        async fetchEmployeeRateHistoryOnce(employeeId = null) {
            if (this.loaded.rateHistory || this.loading.rateHistory) return;
            this.loading.rateHistory = true;
            try {
                const url = employeeId
                    ? `${API_BASE_URL}/hr/employee-rate-history?employee_id=${employeeId}`
                    : `${API_BASE_URL}/hr/employee-rate-history`;

                const res = await fetch(url);
                const data = await res.json();
                this.rateHistory = Array.isArray(data) ? data : data.data || [];
            } catch (e) {
                console.error("Failed to load rate history", e);
            } finally {
                this.loading.rateHistory = false;
                this.loaded.rateHistory = true;
            }
        },

        isActiveRate(row) {
            const today = new Date().toISOString().slice(0, 10);
            return (
                row.effective_start <= today &&
                (!row.effective_end || row.effective_end >= today)
            );
        },

        async refreshRateHistory(employeeId = null) {
            // force a refetch regardless of loaded flag
            this.loaded.rateHistory = false;
            await this.fetchEmployeeRateHistoryOnce(employeeId);
        },

        filteredRateHistory() {
            let list = Array.isArray(this.rateHistory) ? this.rateHistory : [];
            const empId = this.rateHistoryFilterEmployeeId;
            const onlyActive = this.rateHistoryFilterOnlyActive;
            const today = new Date().toISOString().slice(0, 10);

            if (empId) {
                list = list.filter(
                    (r) => String(r.employee_id) === String(empId)
                );
            }
            if (onlyActive) {
                list = list.filter(
                    (r) =>
                        r.effective_start <= today &&
                        (!r.effective_end || r.effective_end >= today)
                );
            }
            return list;
        },
    },
};
</script>

<!-- <style scoped src="../hr.css"></style> -->
<style>
.history-header ul li {
    display: inline-block;
    padding: 8px 15px;
    cursor: pointer;
}
.history-header ul li.active {
    border-bottom: 2px solid #007bff;
    font-weight: bold;
}
</style>
