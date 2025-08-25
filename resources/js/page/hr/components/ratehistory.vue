<template>
    <div class="rate-history-section">
        <div class="rate-history-header">
            <fieldset>
                <label>Employee</label>
                <select
                    class="form-select d-inline-block"
                    v-model="$parent.rateHistoryFilterEmployeeId"
                >
                    <option value="">All employees</option>
                    <option
                        v-for="e in $parent.employees"
                        :key="e.id"
                        :value="e.id"
                    >
                        {{ e.name }} ({{ e.username }})
                    </option>
                </select>
            </fieldset>

            <fieldset class="has-checkbox">
                <input
                    id="onlyActive"
                    type="checkbox"
                    class="form-check-input"
                    v-model="$parent.rateHistoryFilterOnlyActive"
                />
                <label for="onlyActive" class="form-check-label"
                    >Active only</label
                >
            </fieldset>

            <button
                class="btn btn-sm btn-outline-secondary ms-auto"
                @click="
                    $parent.refreshRateHistory(
                        $parent.rateHistoryFilterEmployeeId || null
                    )
                "
            >
                Refresh
            </button>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Effective Start</th>
                    <th>Effective End</th>
                    <th>Monthly</th>
                    <th>Hourly</th>
                    <th>Currency</th>
                    <th>Created By</th>
                    <th>Created At</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="(row, i) in $parent.filteredRateHistory"
                    :key="row.id"
                >
                    <td>{{ i + 1 }}</td>
                    <td>
                        {{
                            row.employee_username ||
                            row.username ||
                            "#" + row.employee_id
                        }}
                    </td>
                    <td>{{ row.effective_start }}</td>
                    <td>{{ row.effective_end || "Present" }}</td>
                    <td>
                        <span v-if="row.monthly_rate != null">
                            ₱{{ Number(row.monthly_rate).toFixed(2) }}
                        </span>
                        <span v-else>-</span>
                    </td>
                    <td>
                        <span v-if="row.hourly_rate != null">
                            ₱{{ Number(row.hourly_rate).toFixed(2) }}
                        </span>
                        <span v-else>-</span>
                    </td>
                    <td>{{ row.currency || "PHP" }}</td>
                    <td>{{ row.created_by || "-" }}</td>
                    <td>
                        {{ $parent.formatDate(row.created_at) }}
                    </td>
                    <td>
                        <span
                            v-if="$parent.isActiveRate(row)"
                            class="badge bg-success"
                        >
                            Active
                        </span>
                        <span v-else class="badge bg-secondary">Inactive</span>
                    </td>
                </tr>

                <tr v-if="!$parent.filteredRateHistory.length">
                    <td colspan="10" class="text-center text-muted py-3">
                        No rate history found.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped src="../hr.css"></style>
