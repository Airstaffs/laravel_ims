<template>
    <div class="leave-history-section">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Date From</th>
                    <th>Date To</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(leave, index) in $parent.leaveHistory"
                    :key="leave.id"
                >
                    <td>{{ index + 1 }}</td>
                    <td>{{ leave.employee }}</td>
                    <td>{{ leave.type }}</td>
                    <td>{{ leave.date_from }}</td>
                    <td>{{ leave.date_to }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
