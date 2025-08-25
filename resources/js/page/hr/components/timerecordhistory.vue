<script setup>
import { reactive, watch } from "vue";

const props = defineProps({
    history: { type: Array, default: () => [] },
    // Parent can override; default shows raw if parent doesn't provide one
    formatDate: { type: Function, default: (ts) => ts ?? "—" },
    initialFilters: {
        type: Object,
        default: () => ({ clock_id: "", edited_by: "", from: "", to: "" }),
    },
});

const emit = defineEmits(["apply", "clear"]);

const localFilters = reactive({
    clock_id: "",
    edited_by: "",
    from: "",
    to: "",
});

watch(
    () => props.initialFilters,
    (val) => {
        if (val) Object.assign(localFilters, val);
    },
    { immediate: true, deep: true }
);

function apply() {
    emit("apply", { ...localFilters });
}

function clear() {
    localFilters.clock_id = "";
    localFilters.edited_by = "";
    localFilters.from = "";
    localFilters.to = "";
    emit("clear");
    emit("apply", { ...localFilters });
}

// --- Helpers ---
// Normalize lots of possible "changes" shapes into { field: {from,to} }
function parseChanges(changes) {
    if (!changes) return {};
    // Already a map?
    if (typeof changes === "object" && !Array.isArray(changes)) {
        const out = {};
        for (const [k, v] of Object.entries(changes)) {
            if (Array.isArray(v)) out[k] = { from: v[0], to: v[1] };
            else if (v && typeof v === "object")
                out[k] = { from: v.from, to: v.to };
            else out[k] = { from: undefined, to: v };
        }
        return out;
    }
    // JSON string?
    if (typeof changes === "string") {
        try {
            return parseChanges(JSON.parse(changes));
        } catch {
            return {};
        }
    }
    // Array of records [{field,from,to}]?
    if (Array.isArray(changes)) {
        const out = {};
        for (const rec of changes) {
            if (!rec) continue;
            const key = rec.field || rec.key || rec.name;
            if (key) out[key] = { from: rec.from, to: rec.to };
        }
        return out;
    }
    return {};
}

// For template use; avoids re-parsing logic in-place
function normalizedChanges(changes) {
    return parseChanges(changes);
}

// Nicely format field names
function prettyLabel(s) {
    if (!s) return "";
    return String(s)
        .replace(/[_\-]+/g, " ")
        .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
        .replace(/\s+/g, " ")
        .replace(/^./, (ch) => ch.toUpperCase());
}

// Show dashes for empty values
function displayVal(v) {
    return v === undefined || v === null || v === "" ? "—" : v;
}
</script>

<template>
    <div class="time-record-section">
        <div class="time-record-header">
            <fieldset>
                <label>Clock ID</label>
                <input
                    type="number"
                    class="form-control"
                    v-model="localFilters.clock_id"
                />
            </fieldset>
            <fieldset>
                <label>Edited By (User ID)</label>
                <input
                    type="number"
                    class="form-control"
                    v-model="localFilters.edited_by"
                />
            </fieldset>
            <fieldset>
                <label>From</label>
                <input
                    type="date"
                    class="form-control"
                    v-model="localFilters.from"
                />
            </fieldset>
            <fieldset>
                <label>To</label>
                <input
                    type="date"
                    class="form-control"
                    v-model="localFilters.to"
                />
            </fieldset>
            <fieldset>
                <label></label>
                <div class="has-button">
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
            </fieldset>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Clock ID</th>
                    <th>Edited By</th>
                    <th>When</th>
                    <th>Changes</th>
                </tr>
            </thead>

            <tbody v-if="Array.isArray(history) && history.length">
                <tr v-for="(row, i) in history" :key="row?.id ?? `hist-${i}`">
                    <td>{{ i + 1 }}</td>
                    <td>{{ row?.clock_id ?? "—" }}</td>
                    <td>{{ row?.edited_by ?? "—" }}</td>
                    <td>{{ formatDate(row?.edit_timestamp) }}</td>

                    <td>
                        <ul class="m-0 ps-3">
                            <li
                                v-for="(chg, field) in normalizedChanges(
                                    row?.changes
                                )"
                                :key="field"
                                v-if="chg"
                            >
                                <strong>{{ prettyLabel(field) }}</strong
                                >:
                                <em>{{ displayVal(chg?.from) }}</em>
                                →
                                <em>{{ displayVal(chg?.to) }}</em>
                            </li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped src="../hr.css"></style>
