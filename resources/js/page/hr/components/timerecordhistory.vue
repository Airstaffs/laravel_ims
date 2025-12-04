<script setup>
import { reactive, watch, computed, ref } from "vue";
import XDataTable from '../../../components/DataTable/XDataTable.vue'
import { InputText, Button } from "primevue";

const columns = [
    {
        field: "clock_id",
        header: "Clock Id",
        style: { fontSize: '14px' }
    },
    {
        header: "Edited By",
        slot: "editedBy",
        style: { fontSize: '14px' }
    },
    {
        header: "When",
        slot: "when",
        style: { fontSize: '14px' }
    },
    {
        header: "Changes",
        slot: "changes",
        style: { fontSize: '14px' }
    }
]

const props = defineProps({
    hrContext: { type: Object, required: true },
});

const ctx = computed(() => props.hrContext || null); // guard

const showFilters = ref(true);
function toggleFilters() {
    showFilters.value = !showFilters.value;
}

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

// --- helpers ---
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

const prettyLabel = (s) =>
    String(s || "")
        .replace(/[_\-]+/g, " ")
        .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
        .replace(/\s+/g, " ")
        .replace(/^./, (ch) => ch.toUpperCase());
const displayVal = (v) => (v === undefined || v === null || v === "" ? "—" : v);

// normalize for template
function getNormalized(row) {
    return parseChanges(row?.changes);
}

// 🔎 map employees by id (from ctx.employees)
const employeesById = computed(() => {
    const map = new Map();
    const list = ctx.value?.employees;
    if (Array.isArray(list)) {
        for (const e of list) {
            const id = e?.id;
            if (id != null) {
                map.set(String(id), e.name || e.username || `User #${id}`);
            }
        }
    }
    return map; // may be empty initially—computed will update when employees arrive
});
// Show name for edited_by
function editorName(row) {
    const eb = row?.edited_by;

    // already an object with name/username?
    if (eb && typeof eb === "object") {
        return eb.name || eb.username || eb.id || "—";
    }

    // numeric/string id
    const id = eb?.id ?? eb;
    if (id == null || id === "") return "—";

    const fromCtx = employeesById.value.get(String(id));
    return fromCtx || String(id); // show id until employees load
}
</script>

<template>
    <div v-if="ctx?.history" class="time-record-section">
        <button class="btn btn-toggle d-md-none" @click="toggleFilters">
            <i class="fas fa-sliders-h"></i>
        </button>

        <div class="time-record-header" v-if="showFilters">
            <fieldset>
                <label>Clock ID</label>
                <InputText size="small" fluid type="number" v-model="localFilters.clock_id" />
            </fieldset>
            <fieldset>
                <label>Edited By (User ID)</label>
                <InputText size="small" fluid type="number" v-model="localFilters.edited_by" />
            </fieldset>
            <fieldset>
                <label>From</label>
                <InputText size="small" fluid type="date" v-model="localFilters.from" />
            </fieldset>
            <fieldset>
                <label>To</label>
                <InputText size="small" fluid type="date" v-model="localFilters.to" />
            </fieldset>
            <fieldset>
                <label></label>
                <div class="has-button">
                    <Button type="button" class="btn btn-outline-secondary" @click="apply" size="small" severity="info"
                        label="Search" />
                    <Button type="button" class="btn btn-outline-dark" @click="clear" size="small" severity="info"
                        outlined="" label="Reset" />
                </div>
            </fieldset>
        </div>

        <!-- Mobile / Small screens: Card list -->
        <div class="d-md-none">
            <div v-if="
                !(
                    Array.isArray(ctx.history.rows) &&
                    ctx.history.rows.length
                )
            " class="text-center text-muted py-4">
                No edit history found.
            </div>

            <div class="hist-card shadow-sm rounded-3 mb-2 p-3" v-for="(row, i) in ctx.history.rows"
                :key="row?.id ?? `hist-${i}`">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-3 flex-grow-1">
                        <div class="small text-secondary">Clock ID</div>
                        <div class="fw-semibold text-truncate">
                            {{ row?.clock_id ?? "—" }}
                        </div>
                    </div>
                    <span class="badge text-bg-light">#{{ i + 1 }}</span>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <div class="label text-secondary small">Edited By</div>
                        <div class="value text-truncate">
                            {{ editorName(row) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="label text-secondary small">When</div>
                        <div class="value">
                            {{
                                (ctx.formatDate
                                    ? ctx.formatDate
                                    : (x) => x ?? "—")(
                                        row?.when || row?.edit_timestamp
                                    )
                            }}
                        </div>
                    </div>
                </div>

                <div class="mt-2">
                    <div class="label text-secondary small mb-1">Changes</div>
                    <ul class="changes-list mb-0 ps-3">
                        <li v-for="[field, chg] in Object.entries(
                            getNormalized(row)
                        ).filter(([, v]) => v)" :key="field" class="small">
                            <code class="me-1">{{ prettyLabel(field) }}</code>
                            <span class="text-muted text-decoration-line-through">
                                {{ displayVal(chg?.from) }}
                            </span>
                            &nbsp;→&nbsp;
                            <strong>{{ displayVal(chg?.to) }}</strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Desktop / Medium+ screens: Enhanced table -->
        <div class="d-none d-md-block">
            <XDataTable :value="ctx.history.rows" :showIndex="true" :columns="columns">
                <template #editedBy="{ data }">
                    <p>{{ editorName(data) }}</p>
                </template>
                <template #when="{ data }">
                    <p> {{
                        (ctx.formatDate
                            ? ctx.formatDate
                            : (x) => x ?? "—")(
                                data?.when || data?.edit_timestamp
                            )
                    }}</p>
                </template>
                <template #changes="{ data }">
                    <div>
                        <div v-for="[field, chg] in Object.entries(
                            getNormalized(data)
                        ).filter(([, v]) => v)" :key="field" class="small">
                            <code class="me-1">{{ prettyLabel(field) }}</code>
                            <span class="text-muted text-decoration-line-through">
                                {{ displayVal(chg?.from) }}
                            </span>
                            &nbsp;→&nbsp;
                            <strong>{{ displayVal(chg?.to) }}</strong>
                        </div>
                    </div>
                </template>
            </XDataTable>
        </div>
        <!-- <div class="table-responsive d-none d-md-block">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 64px">#</th>
                        <th style="width: 140px">Clock ID</th>
                        <th style="width: 220px">Edited By</th>
                        <th style="width: 220px">When</th>
                        <th>Changes</th>
                    </tr>
                </thead>

                <tbody v-if="
                    Array.isArray(ctx.history.rows) &&
                    ctx.history.rows.length
                ">
                    <tr v-for="(row, i) in ctx.history.rows" :key="row?.id ?? `hist-${i}`">
                        <td class="text-secondary">{{ i + 1 }}</td>
                        <td class="fw-semibold">{{ row?.clock_id ?? "—" }}</td>
                        <td class="text-truncate">{{ editorName(row) }}</td>
                        <td>
                            {{
                                (ctx.formatDate
                                    ? ctx.formatDate
                                    : (x) => x ?? "—")(
                                        row?.when || row?.edit_timestamp
                                    )
                            }}
                        </td>
                        <td>
                            <ul class="mb-0 ps-3">
                                <template v-for="(chg, field) in getNormalized(row)" :key="field">
                                    <li v-if="chg" class="small">
                                        <code class="me-1">{{
                                            prettyLabel(field)
                                        }}</code>
                                        <span class="text-muted text-decoration-line-through">
                                            {{ displayVal(chg?.from) }}
                                        </span>
                                        &nbsp;→&nbsp;
                                        <strong>{{
                                            displayVal(chg?.to)
                                            }}</strong>
                                    </li>
                                </template>
                            </ul>
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No edit history found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div> -->
    </div>

    <div v-else class="p-3">Loading…</div>
</template>

<style scoped src="../hr.css"></style>
