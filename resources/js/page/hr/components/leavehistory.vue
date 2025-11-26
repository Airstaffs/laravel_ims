<template>
    <div class="leave-history-section">
        <!-- Mobile / Small screens: Card list -->
        <div class="d-md-none">
            <!-- Empty state -->
            <div v-if="
                !(
                    Array.isArray($parent.leaveHistory) &&
                    $parent.leaveHistory.length
                )
            " class="text-center text-muted py-4">
                No leave history found.
            </div>

            <!-- Cards -->
            <div class="leave-card shadow-sm rounded-3 mb-2 p-3" v-for="(leave, index) in $parent.leaveHistory"
                :key="leave.id">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-2 flex-grow-1">
                        <div class="fw-semibold text-truncate">
                            {{ leave.employee || "—" }}
                        </div>
                        <div class="text-secondary small">#{{ index + 1 }}</div>
                    </div>
                    <span class="badge text-bg-light">{{
                        leave.type || "—"
                        }}</span>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <div class="label text-secondary small">Date From</div>
                        <div class="value fw-semibold">
                            {{ leave.date_from || "—" }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="label text-secondary small">Date To</div>
                        <div class="value fw-semibold">
                            {{ leave.date_to || "—" }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desktop / Medium+ screens: Enhanced table -->
        <div class=" d-none d-md-block">
            <XDataTable :value="$parent.leaveHistory" :columns="columns" :showIndex="true">
                <template #employee="{ data }">
                    {{ data.employee || "—" }}
                </template>

                <template #type="{ data }">
                    {{ data.type || "—" }}
                </template>

                <template #dateFrom="{ data }">
                    {{ data.date_from || "—" }}
                </template>

                <template #dateTo="{ data }">
                    {{ data.date_to || "—" }}
                </template>
            </XDataTable>
        </div>

        <!-- <div class="table-responsive d-none d-md-block">

            <table class="table align-middle table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 72px">#</th>
                        <th>Employee</th>
                        <th style="width: 200px">Leave Type</th>
                        <th style="width: 180px">Date From</th>
                        <th style="width: 180px">Date To</th>
                    </tr>
                </thead>
                <tbody v-if="
                    Array.isArray($parent.leaveHistory) &&
                    $parent.leaveHistory.length
                ">
                    <tr v-for="(leave, index) in $parent.leaveHistory" :key="leave.id">
                        <td class="text-secondary">{{ index + 1 }}</td>
                        <td class="fw-semibold text-truncate" style="max-width: 320px">
                            {{ leave.employee || "—" }}
                        </td>
                        <td>
                            <span class="badge text-bg-light">{{
                                leave.type || "—"
                                }}</span>
                        </td>
                        <td>{{ leave.date_from || "—" }}</td>
                        <td>{{ leave.date_to || "—" }}</td>
                    </tr>
                </tbody>
                <tbody v-else>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No leave history found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div> -->
    </div>
</template>

<script setup>

import XDataTable from '../../../components/DataTable/XDataTable.vue';

const columns = [
    {
        field: "employee",
        header: "Employee",
        slot: "employee"
    },
    {
        field: "type",
        header: "Leave Type",
        slot: "type"
    },
    {
        field: "date_from",
        header: "Date From",
        slot: "dateFrom"
    },
    {
        field: "date_to",
        header: "Date To",
        slot: "dateTo"
    }
]
</script>
