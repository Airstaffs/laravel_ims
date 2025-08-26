<script setup>
import { onMounted, computed, toRef } from "vue";

const props = defineProps({ hrContext: { type: Object, required: true } });

// reactive reference to the prop
const ctx = toRef(props, "hrContext");

// rows follows parent computed reactively
const rows = computed(() => ctx.value.filteredRateHistory);

// nice currency formatting
function fmtMoney(v) {
    if (v == null) return "–";
    try {
        return new Intl.NumberFormat(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Number(v));
    } catch {
        return Number(v).toFixed(2);
    }
}

onMounted(async () => {
    if (!ctx.value.loaded.employees && !ctx.value.loading.employees) {
        await ctx.value.fetchEmployeesOnce();
    }
    if (!ctx.value.loaded.rateHistory && !ctx.value.loading.rateHistory) {
        await ctx.value.fetchEmployeeRateHistoryOnce(
            ctx.value.rateHistoryFilterEmployeeId || null
        );
    }
});

function onChangeEmployee() {
    ctx.value.refreshRateHistory(ctx.value.rateHistoryFilterEmployeeId || null);
}
</script>

<template>
    <div class="rate-history-section">
        <div class="rate-history-header">
            <fieldset>
                <label>Employee</label>
                <select
                    class="form-select d-inline-block"
                    v-model="ctx.rateHistoryFilterEmployeeId"
                    @change="onChangeEmployee"
                >
                    <option value="">All employees</option>
                    <option
                        v-for="e in ctx.employees"
                        :key="e.id"
                        :value="e.id"
                    >
                        {{ e.name }} ({{ e.username || "#" + e.id }})
                    </option>
                </select>
            </fieldset>

            <fieldset class="has-checkbox">
                <input
                    id="onlyActive"
                    type="checkbox"
                    class="form-check-input"
                    v-model="ctx.rateHistoryFilterOnlyActive"
                />
                <label for="onlyActive" class="form-check-label"
                    >Active only</label
                >
            </fieldset>

            <button
                class="btn btn-sm btn-outline-secondary ms-auto"
                @click="
                    ctx.refreshRateHistory(
                        ctx.rateHistoryFilterEmployeeId || null
                    )
                "
                :disabled="ctx.loading.rateHistory"
            >
                {{ ctx.loading.rateHistory ? "Refreshing…" : "Refresh" }}
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

            <tbody v-if="Array.isArray(rows) && rows.length">
                <tr v-for="(row, i) in rows" :key="row.id || i">
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
                    <td>₱{{ fmtMoney(row.monthly_rate) }}</td>
                    <td>₱{{ fmtMoney(row.hourly_rate) }}</td>
                    <td>{{ row.currency || "PHP" }}</td>
                    <td>{{ row.created_by || "-" }}</td>
                    <td>{{ ctx.formatDate(row.created_at) }}</td>
                    <td>
                        <span
                            v-if="ctx.isActiveRate(row)"
                            class="badge bg-success"
                            >Active</span
                        >
                        <span v-else class="badge bg-secondary">Inactive</span>
                    </td>
                </tr>
            </tbody>

            <tbody v-else>
                <tr>
                    <td colspan="10" class="text-center text-muted py-3">
                        {{
                            ctx.loading.rateHistory
                                ? "Loading…"
                                : "No rate history found."
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
