<template>
    <div class="violation-section">
        <h4>Employee Violations</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Description</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(violation, index) in $parent.violations"
                    :key="violation.id"
                >
                    <td>{{ index + 1 }}</td>
                    <td>{{ violation.employee }}</td>
                    <td>{{ violation.description }}</td>
                    <td>{{ violation.date }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
