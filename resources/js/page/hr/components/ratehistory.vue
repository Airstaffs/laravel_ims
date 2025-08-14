<template>
    <div class="p-2">
        <div class="d-flex align-items-center gap-2 mb-3">
            <h5 class="mb-0">Employee Rate History</h5>

            <div class="ms-3">
                <label class="form-label mb-0 me-2">Employee</label>
                <select
                    class="form-select d-inline-block"
                    style="width: 260px"
                    v-model="$parent.hrContext.rateHistoryFilterEmployeeId"
                >
                    <option value="">All employees</option>
                    <option
                        v-for="e in $parent.hrContext.employees"
                        :key="e.id"
                        :value="e.id"
                    >
                        {{ e.name }} ({{ e.username }})
                    </option>
                </select>
            </div>

            <div class="form-check ms-3">
                <input
                    id="onlyActive"
                    type="checkbox"
                    class="form-check-input"
                    v-model="$parent.hrContext.rateHistoryFilterOnlyActive"
                />
                <label for="onlyActive" class="form-check-label"
                    >Active only</label
                >
            </div>

            <button
                class="btn btn-sm btn-outline-secondary ms-auto"
                :disabled="$parent.hrContext.loading.rateHistory"
                @click="
                    $parent.hrContext.refreshRateHistory(
                        $parent.hrContext.rateHistoryFilterEmployeeId || null
                    )
                "
            >
                <span
                    v-if="$parent.hrContext.loading.rateHistory"
                    class="spinner-border spinner-border-sm me-1"
                ></span>
                Refresh
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px">#</th>
                        <th>Employee</th>
                        <th>Effective Start</th>
                        <th>Effective End</th>
                        <th>Monthly</th>
                        <th>Hourly</th>
                        <th>Currency</th>
                        <th>Created By</th>
                        <th>Created At</th>
                        <th style="width: 90px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, i) in $parent.hrContext
                            .filteredRateHistory"
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
                            <span v-if="row.monthly_rate != null"
                                >₱{{
                                    Number(row.monthly_rate).toFixed(2)
                                }}</span
                            >
                            <span v-else>-</span>
                        </td>
                        <td>
                            <span v-if="row.hourly_rate != null"
                                >₱{{ Number(row.hourly_rate).toFixed(2) }}</span
                            >
                            <span v-else>-</span>
                        </td>
                        <td>{{ row.currency || "PHP" }}</td>
                        <td>{{ row.created_by || "-" }}</td>
                        <td>
                            {{ $parent.hrContext.formatDate(row.created_at) }}
                        </td>
                        <td>
                            <span
                                v-if="$parent.hrContext.isActiveRate(row)"
                                class="badge bg-success"
                                >Active</span
                            >
                            <span v-else class="badge bg-secondary"
                                >Inactive</span
                            >
                        </td>
                    </tr>

                    <tr v-if="!$parent.hrContext.filteredRateHistory.length">
                        <td colspan="10" class="text-center text-muted py-3">
                            No rate history found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
