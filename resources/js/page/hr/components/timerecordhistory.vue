<script setup>
import { reactive, watch, computed } from "vue";

const props = defineProps({
    hrContext: { type: Object, required: true },
});

const ctx = computed(() => props.hrContext || null); // guard

const localFilters = reactive({
    clock_id: "",
    edited_by: "",
    from: "",
    to: "",
});

// Mirror parent filters when ctx becomes ready or changes
watch(
    () => ctx.value?.history?.filters,
    (v) => {
        if (v) Object.assign(localFilters, v);
    },
    { immediate: true, deep: true }
);

function apply() {
    if (!ctx.value) return;
    Object.assign(ctx.value.history.filters, localFilters);
    ctx.value.historyApply();
}

function clear() {
    if (!ctx.value) return;
    Object.assign(localFilters, {
        clock_id: "",
        edited_by: "",
        from: "",
        to: "",
    });
    Object.assign(ctx.value.history.filters, localFilters);
    ctx.value.historyApply();
}

// --- helpers (unchanged, but safe to keep here) ---
function parseChanges(changes) {
    if (!changes) return {};
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
    if (typeof changes === "string") {
        try {
            return parseChanges(JSON.parse(changes));
        } catch {
            return {};
        }
    }
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
const normalizedChanges = (c) => parseChanges(c);
const prettyLabel = (s) =>
    String(s || "")
        .replace(/[_\-]+/g, " ")
        .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
        .replace(/\s+/g, " ")
        .replace(/^./, (ch) => ch.toUpperCase());
const displayVal = (v) => (v === undefined || v === null || v === "" ? "—" : v);
</script>

<template>
    <!-- Guard rendering until ctx is ready -->
    <div v-if="ctx?.history" class="time-record-section">
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

            <tbody
                v-if="
                    Array.isArray(ctx.history.rows) && ctx.history.rows.length
                "
            >
                <tr
                    v-for="(row, i) in ctx.history.rows"
                    :key="row?.id ?? `hist-${i}`"
                >
                    <td>{{ i + 1 }}</td>
                    <td>{{ row?.clock_id ?? "—" }}</td>
                    <td>{{ row?.edited_by?.name ?? row?.edited_by ?? "—" }}</td>
                    <td>
                        {{
                            (ctx.formatDate ? ctx.formatDate : (x) => x ?? "—")(
                                row?.when || row?.edit_timestamp
                            )
                        }}
                    </td>
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
                                >: <em>{{ displayVal(chg?.from) }}</em> →
                                <em>{{ displayVal(chg?.to) }}</em>
                            </li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div v-else class="p-3">Loading…</div>
</template>

<style scoped src="../hr.css"></style>
