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
                    class="form-select"
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

        <!-- Mobile / Small screens: Card list -->
        <div class="d-md-none">
            <!-- Empty state -->
            <div
                v-if="!(Array.isArray(rows) && rows.length)"
                class="text-center text-muted py-4"
            >
                {{
                    ctx.loading.rateHistory
                        ? "Loading…"
                        : "No rate history found."
                }}
            </div>

            <!-- Cards -->
            <div
                class="rate-card shadow-sm rounded-3 mb-2 p-3"
                v-for="(row, i) in rows"
                :key="row.id || i"
            >
                <!-- Header: Employee + Status -->
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-3 flex-grow-1">
                        <div class="small text-secondary">Employee</div>
                        <div class="fw-semibold text-truncate">
                            {{
                                row.employee_username ||
                                row.username ||
                                "#" + row.employee_id
                            }}
                        </div>
                    </div>
                    <span
                        class="badge"
                        :class="
                            ctx.isActiveRate(row)
                                ? 'bg-success'
                                : 'bg-secondary'
                        "
                    >
                        {{ ctx.isActiveRate(row) ? "Active" : "Inactive" }}
                    </span>
                </div>

                <!-- Effective period -->
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <div class="label text-secondary small">
                            Effective Start
                        </div>
                        <div class="value fw-semibold">
                            {{ row.effective_start }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="label text-secondary small">
                            Effective End
                        </div>
                        <div class="value fw-semibold">
                            {{ row.effective_end || "Present" }}
                        </div>
                    </div>
                </div>

                <!-- Rates -->
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <div class="label text-secondary small">Monthly</div>
                        <div class="value fw-semibold">
                            ₱{{ fmtMoney(row.monthly_rate) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="label text-secondary small">Hourly</div>
                        <div class="value fw-semibold">
                            ₱{{ fmtMoney(row.hourly_rate) }}
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="label text-secondary small">Currency</div>
                        <div class="value">
                            <span class="badge text-bg-light">{{
                                row.currency || "PHP"
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Created info -->
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <div class="label text-secondary small">Created By</div>
                        <div class="value">{{ row.created_by || "—" }}</div>
                    </div>
                    <div class="col-6">
                        <div class="label text-secondary small">Created At</div>
                        <div class="value">
                            {{ ctx.formatDate(row.created_at) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desktop / Medium+ screens: Enhanced table -->
        <div class="table-responsive d-none d-md-block">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>Employee</th>
                        <th style="width: 160px">Effective Start</th>
                        <th style="width: 160px">Effective End</th>
                        <th style="width: 160px">Monthly</th>
                        <th style="width: 140px">Hourly</th>
                        <th style="width: 120px">Currency</th>
                        <th style="width: 160px">Created By</th>
                        <th style="width: 180px">Created At</th>
                        <th style="width: 120px">Status</th>
                    </tr>
                </thead>

                <tbody v-if="Array.isArray(rows) && rows.length">
                    <tr v-for="(row, i) in rows" :key="row.id || i">
                        <td class="text-secondary">{{ i + 1 }}</td>
                        <td
                            class="fw-semibold text-truncate"
                            style="max-width: 280px"
                        >
                            {{
                                row.employee_username ||
                                row.username ||
                                "#" + row.employee_id
                            }}
                        </td>
                        <td>{{ row.effective_start }}</td>
                        <td>{{ row.effective_end || "Present" }}</td>
                        <td class="value">₱{{ fmtMoney(row.monthly_rate) }}</td>
                        <td class="value">₱{{ fmtMoney(row.hourly_rate) }}</td>
                        <td>
                            <span class="badge text-bg-light">{{
                                row.currency || "PHP"
                            }}</span>
                        </td>
                        <td class="text-truncate" style="max-width: 200px">
                            {{ row.created_by || "-" }}
                        </td>
                        <td>{{ ctx.formatDate(row.created_at) }}</td>
                        <td>
                            <span
                                v-if="ctx.isActiveRate(row)"
                                class="badge bg-success"
                                >Active</span
                            >
                            <span v-else class="badge bg-secondary"
                                >Inactive</span
                            >
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
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
    </div>
</template>

<style scoped src="../hr.css"></style>
