<template>
    <div class="p-2">
        <!-- Filters -->
        <div class="row g-2 align-items-end mb-3">
            <div class="col-12 col-md-3">
                <label class="form-label">Clock ID</label>
                <input
                    type="number"
                    class="form-control"
                    v-model="hrContext.histFilters.clock_id"
                />
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Edited By (User ID)</label>
                <input
                    type="number"
                    class="form-control"
                    v-model="hrContext.histFilters.edited_by"
                />
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">From</label>
                <input
                    type="date"
                    class="form-control"
                    v-model="hrContext.histFilters.from"
                />
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">To</label>
                <input
                    type="date"
                    class="form-control"
                    v-model="hrContext.histFilters.to"
                />
            </div>

            <div class="col-12 d-flex gap-2">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="$parent.hrContext.loading.clockHistory"
                    @click="
                        $parent.hrContext.refreshClockEditHistory(
                            $parent.hrContext.histFilters
                        )
                    "
                >
                    <span
                        v-if="$parent.hrContext.loading.clockHistory"
                        class="spinner-border spinner-border-sm me-1"
                    ></span>
                    Apply
                </button>

                <button
                    type="button"
                    class="btn btn-outline-dark"
                    @click="clearFilters()"
                >
                    Clear
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px">#</th>
                        <th>Clock ID</th>
                        <th>Edited By</th>
                        <th>When</th>
                        <th>Changes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, i) in hrContext.clockEditHistory"
                        :key="row.id"
                    >
                        <td>{{ i + 1 }}</td>
                        <td>{{ row.clock_id }}</td>
                        <td>{{ row.edited_by }}</td>
                        <td>{{ hrContext.formatDate(row.edit_timestamp) }}</td>
                        <td>
                            <ul class="m-0 ps-3">
                                <li
                                    v-for="(chg, field) in parseChanges(
                                        row.changes
                                    )"
                                    :key="field"
                                >
                                    <strong>{{ field }}</strong
                                    >: <em>{{ chg.from ?? "—" }}</em> →
                                    <em>{{ chg.to ?? "—" }}</em>
                                </li>
                            </ul>
                        </td>
                    </tr>

                    <!-- empty state -->
                    <tr
                        v-if="
                            !$parent.hrContext.clockEditHistory.length &&
                            !$parent.hrContext.loading.clockHistory
                        "
                    >
                        <td colspan="5" class="text-center text-muted py-3">
                            No edit history found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
const { hrContext } = defineProps({
    hrContext: { type: Object, required: true },
});

const parseChanges = (json) => {
    try {
        return typeof json === "string" ? JSON.parse(json || "{}") : json || {};
    } catch {
        return {};
    }
};

function clearFilters() {
    hrContext.histFilters.clock_id = "";
    hrContext.histFilters.edited_by = "";
    hrContext.histFilters.from = "";
    hrContext.histFilters.to = "";
    hrContext.refreshClockEditHistory({});
}
</script>
