<script setup>
import { reactive, watch } from "vue";

const props = defineProps({
    // rows to render (parent-owned)
    history: { type: Array, default: () => [] },
    // optional formatter from parent
    formatDate: { type: Function, default: (ts) => ts ?? "—" },
    // optional initial filter values from parent
    initialFilters: {
        type: Object,
        default: () => ({ clock_id: "", edited_by: "", from: "", to: "" }),
    },
});

// emits for parent to handle data fetching / side effects
const emit = defineEmits(["apply", "clear"]);

const localFilters = reactive({
    clock_id: "",
    edited_by: "",
    from: "",
    to: "",
});

// hydrate local from parent-provided initial filters
watch(
    () => props.initialFilters,
    (val) => {
        if (val) Object.assign(localFilters, val);
    },
    { immediate: true, deep: true }
);

function apply() {
    // tell parent to refresh using current filters
    emit("apply", { ...localFilters });
}

function clear() {
    localFilters.clock_id = "";
    localFilters.edited_by = "";
    localFilters.from = "";
    localFilters.to = "";
    emit("clear");
    // optional: immediately re-apply with cleared filters
    emit("apply", { ...localFilters });
}

function parseChanges(changes) {
    if (!changes) return {};
    if (typeof changes === "object") return changes;
    try {
        return JSON.parse(changes);
    } catch {
        return {};
    }
}
</script>

<template>
    <div class="p-2">
        <!-- Filters -->
        <div class="row g-2 align-items-end mb-3">
            <div class="col-12 col-md-3">
                <label class="form-label">Clock ID</label>
                <input
                    type="number"
                    class="form-control"
                    v-model="localFilters.clock_id"
                />
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Edited By (User ID)</label>
                <input
                    type="number"
                    class="form-control"
                    v-model="localFilters.edited_by"
                />
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">From</label>
                <input
                    type="date"
                    class="form-control"
                    v-model="localFilters.from"
                />
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">To</label>
                <input
                    type="date"
                    class="form-control"
                    v-model="localFilters.to"
                />
            </div>

            <div class="col-12 d-flex gap-2">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="apply"
                >
                    Apply
                </button>
                <button
                    type="button"
                    class="btn btn-outline-dark"
                    @click="clear"
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

                <tbody v-if="Array.isArray(history)">
                    <tr
                        v-for="(row, i) in history"
                        :key="row?.id ?? `hist-${i}`"
                    >
                        <td>{{ i + 1 }}</td>
                        <td>{{ row?.clock_id ?? "—" }}</td>
                        <td>{{ row?.edited_by ?? "—" }}</td>
                        <td>{{ formatDate(row?.edit_timestamp) }}</td>
                        <td>
                            <ul class="m-0 ps-3">
                                <li
                                    v-for="(chg, field) in parseChanges(
                                        row?.changes
                                    )"
                                    :key="field"
                                >
                                    <strong>{{ field }}</strong
                                    >: <em>{{ chg?.from ?? "—" }}</em> →
                                    <em>{{ chg?.to ?? "—" }}</em>
                                </li>
                            </ul>
                        </td>
                    </tr>
                    <tr v-if="!history.length">
                        <td colspan="5" class="text-center text-muted py-3">
                            No edit history found.
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">
                            Loading…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
