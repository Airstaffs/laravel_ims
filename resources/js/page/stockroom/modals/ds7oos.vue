<template>
    <teleport to="body">
        <div v-if="show" class="ds7oos-overlay" @click.self="$emit('close')">
            <div class="ds7oos-dialog">
                <header class="ds7oos-head">
                    <h3>DS7 & OOS</h3>
                    <button class="ds7oos-close" @click="$emit('close')">
                        ✕
                    </button>
                </header>

                <section class="ds7oos-body">
                    <!-- SETTINGS -->
                    <div class="grid2">
                        <div>
                            <label>Days threshold (DS ≤)</label>
                            <input
                                type="number"
                                v-model.number="form.datalimit"
                                min="1"
                            />
                        </div>
                        <div>
                            <label>Window</label>
                            <select v-model.number="form.window">
                                <option :value="7">7 days</option>
                                <option :value="30">30 days</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid2">
                        <div>
                            <label>Store</label>
                            <select v-model="form.store">
                                <option value="">All</option>
                                <option
                                    v-for="s in storeOptions"
                                    :key="s"
                                    :value="s"
                                >
                                    {{ s }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label>Min Sold (in window)</label>
                            <input
                                type="number"
                                v-model.number="form.min_sold"
                                min="0"
                            />
                        </div>
                    </div>

                    <div class="grid2">
                        <div>
                            <label>Sort</label>
                            <select v-model="form.sort">
                                <option value="ds_asc">Days Supply ↑</option>
                                <option value="ds_desc">Days Supply ↓</option>
                                <option value="sold_desc">
                                    Sold (window) ↓
                                </option>
                            </select>
                        </div>
                        <div>
                            <label>Per page</label>
                            <input
                                type="number"
                                v-model.number="form.per_page"
                                min="5"
                            />
                        </div>
                    </div>

                    <div class="row">
                        <label class="checkbox">
                            <input type="checkbox" v-model="form.include_oos" />
                            <span
                                >Include true OOS (QOH=0 but recent sales)</span
                            >
                        </label>
                        <label class="checkbox">
                            <input type="checkbox" v-model="form.use_orders" />
                            <span>Compute sales from orders tables</span>
                        </label>
                    </div>

                    <div class="actions">
                        <button @click="$emit('close')">Close</button>
                        <button class="primary" @click="applyAndFetch">
                            Apply & Fetch
                        </button>
                    </div>

                    <!-- RESULTS -->
                    <div class="results">
                        <div class="results-bar">
                            <div class="small text-muted">
                                Showing DS ≤ {{ form.datalimit }} • Window:
                                {{ form.window }}d
                                <span v-if="form.store">
                                    • Store: {{ form.store }}</span
                                >
                                <span v-if="form.min_sold">
                                    • Min sold: {{ form.min_sold }}</span
                                >
                                <span v-if="form.include_oos"> • +OOS</span>
                                <span v-if="form.use_orders">
                                    • live orders</span
                                >
                            </div>
                            <div class="small text-muted">
                                Page {{ pagination.page }} /
                                {{ pagination.total_pages }} • Total:
                                {{ pagination.total }}
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th
                                            @click="changeSort('ASIN')"
                                            class="sortable"
                                        >
                                            ASIN
                                            <i :class="sortIcon('ASIN')"></i>
                                        </th>
                                        <th>Title</th>
                                        <th>Stores</th>
                                        <th class="text-end">FBA QOH</th>
                                        <th class="text-end">FBM QOH</th>
                                        <th class="text-end">Total QOH</th>
                                        <th class="text-end">Sold (W)</th>
                                        <th
                                            @click="changeSort('DS')"
                                            class="text-end sortable"
                                        >
                                            DS <i :class="sortIcon('DS')"></i>
                                        </th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody v-if="loading">
                                    <tr>
                                        <td
                                            colspan="9"
                                            class="text-center py-3"
                                        >
                                            Loading…
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody v-else-if="rows.length === 0">
                                    <tr>
                                        <td
                                            colspan="9"
                                            class="text-center py-3 text-muted"
                                        >
                                            No results
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody v-else>
                                    <tr v-for="r in rows" :key="r.ASIN">
                                        <td class="font-monospace">
                                            {{ r.ASIN }}
                                        </td>
                                        <td
                                            class="text-truncate"
                                            style="max-width: 420px"
                                        >
                                            {{ r.astitle }}
                                        </td>
                                        <td>{{ r.stores }}</td>
                                        <td class="text-end">
                                            {{ fmtInt(r.FbaQoh) }}
                                        </td>
                                        <td class="text-end">
                                            {{ fmtInt(r.FbmAvailableCount) }}
                                        </td>
                                        <td class="text-end fw-semibold">
                                            {{ fmtInt(r.TotalQOH) }}
                                        </td>
                                        <td class="text-end">
                                            {{ fmtInt(r.TotalUnitSold) }}
                                        </td>
                                        <td class="text-end">
                                            <span
                                                :class="[
                                                    'badge',
                                                    dsBadgeClass(r),
                                                ]"
                                                >{{ fmtDS(r.DS) }}</span
                                            >
                                        </td>
                                        <td>
                                            <span
                                                v-if="r.is_oos == 1"
                                                class="badge bg-danger"
                                                >OOS + recent sales</span
                                            >
                                            <span v-else class="text-muted"
                                                >—</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="pager">
                            <button
                                class="btn btn-outline-secondary btn-sm"
                                :disabled="pagination.page <= 1"
                                @click="goPage(pagination.page - 1)"
                            >
                                Prev
                            </button>
                            <button
                                class="btn btn-outline-secondary btn-sm"
                                :disabled="
                                    pagination.page >= pagination.total_pages
                                "
                                @click="goPage(pagination.page + 1)"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </teleport>
</template>

<script>
import axios from "axios";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "Ds7OosModal",
    props: {
        show: { type: Boolean, default: false },
        storeOptions: { type: Array, default: () => [] },
        initial: {
            type: Object,
            default: () => ({
                datalimit: 14,
                window: 7,
                store: "",
                min_sold: 0,
                sort: "ds_asc",
                per_page: 25,
                include_oos: true,
                use_orders: false,
                page: 1,
            }),
        },
    },
    data() {
        return {
            form: {
                // local copy of filters
                datalimit: 14,
                window: 7,
                store: "",
                min_sold: 0,
                sort: "ds_asc",
                per_page: 25,
                include_oos: true,
                use_orders: false,
                page: 1,
            },
            rows: [],
            pagination: { page: 1, per_page: 25, total: 0, total_pages: 1 },
            loading: false,
            // local sort state for column header clicks (maps to form.sort)
            sortCol: null,
            sortDir: null,
        };
    },
    watch: {
        show(v) {
            if (v) {
                this.resetFromInitial();
                this.fetchRows();
            }
        },
        initial: {
            deep: true,
            handler() {
                if (!this.show) this.resetFromInitial();
            },
        },
    },
    created() {
        this.resetFromInitial();
    },
    methods: {
        resetFromInitial() {
            this.form = {
                datalimit: this.initial.datalimit ?? 14,
                window: this.initial.window ?? 7,
                store: this.initial.store ?? "",
                min_sold: this.initial.min_sold ?? 0,
                sort: this.initial.sort ?? "ds_asc",
                per_page: this.initial.per_page ?? 25,
                include_oos: this.initial.include_oos ?? true,
                use_orders: this.initial.use_orders ?? false,
                page: this.initial.page ?? 1,
            };
            // sync header sort hint
            if (this.form.sort === "ds_asc") {
                this.sortCol = "DS";
                this.sortDir = "asc";
            }
            if (this.form.sort === "ds_desc") {
                this.sortCol = "DS";
                this.sortDir = "desc";
            }
            if (this.form.sort === "sold_desc") {
                this.sortCol = "TotalUnitSold";
                this.sortDir = "desc";
            }
        },
        buildQuery() {
            const q = new URLSearchParams();
            const map = {
                datalimit: this.form.datalimit,
                window: this.form.window,
                store: this.form.store || "",
                min_sold: this.form.min_sold,
                sort: this.form.sort,
                per_page: this.form.per_page,
                include_oos: this.form.include_oos ? 1 : 0,
                use_orders: this.form.use_orders ? 1 : 0,
                page: this.form.page || 1,
            };
            Object.entries(map).forEach(([k, v]) => q.set(k, v));
            return q.toString();
        },
        async fetchRows() {
            this.loading = true;
            try {
                const qs = this.buildQuery();
                const { data } = await axios.get(`${API_BASE_URL}/ds7oos?${qs}`, {
                    withCredentials: true,
                });
                this.rows = data?.data || [];
                this.pagination = data?.pagination || {
                    page: 1,
                    per_page: this.form.per_page,
                    total: 0,
                    total_pages: 1,
                };
                // let parent know we applied filters (optional persist)
                this.$emit("save", {
                    ...this.form,
                    include_oos: this.form.include_oos ? 1 : 0,
                    use_orders: this.form.use_orders ? 1 : 0,
                });
            } catch (e) {
                console.error("DS fetch failed", e);
                this.rows = [];
                this.pagination = {
                    page: 1,
                    per_page: this.form.per_page,
                    total: 0,
                    total_pages: 1,
                };
            } finally {
                this.loading = false;
            }
        },
        applyAndFetch() {
            this.form.page = 1;
            this.fetchRows();
        },
        goPage(p) {
            const target = Math.max(
                1,
                Math.min(p, this.pagination.total_pages || 1)
            );
            if (target !== this.form.page) {
                this.form.page = target;
                this.fetchRows();
            }
        },
        changeSort(col) {
            // Only DS is server-sorted via form.sort; ASIN click just toggles client hint
            if (col === "DS") {
                if (this.form.sort === "ds_asc") this.form.sort = "ds_desc";
                else this.form.sort = "ds_asc";
                this.sortCol = "DS";
                this.sortDir = this.form.sort === "ds_asc" ? "asc" : "desc";
                this.applyAndFetch();
            } else if (col === "ASIN") {
                // client header hint only
                this.sortCol = "ASIN";
                this.sortDir = this.sortDir === "asc" ? "desc" : "asc";
            }
        },
        sortIcon(col) {
            if (this.sortCol !== col) return "fas fa-sort";
            return this.sortDir === "asc"
                ? "fas fa-sort-up"
                : "fas fa-sort-down";
        },
        // helpers
        fmtInt(v) {
            return Number(v || 0).toLocaleString();
        },
        fmtDS(v) {
            const n = Number(v || 0);
            if (!isFinite(n)) return "—";
            return n.toFixed(1);
        },
        dsBadgeClass(r) {
            const ds = Number(r.DS || 0);
            if (r.is_oos == 1) return "bg-danger";
            if (ds <= 3) return "bg-danger";
            if (ds <= 7) return "bg-warning text-dark";
            if (ds <= 14) return "bg-info";
            return "bg-secondary";
        },
    },
};
</script>

<style scoped>
/* Overlay & shell */
.ds7oos-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: grid;
    place-items: center;
    z-index: 4000;
}
.ds7oos-dialog {
    width: min(1100px, calc(100vw - 24px));
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    z-index: 4010;
    animation: fadeIn 0.2s ease-out;
}
.ds7oos-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
}
.ds7oos-head h3 {
    font-size: 18px;
    margin: 0;
    font-weight: 600;
}
.ds7oos-close {
    background: transparent;
    border: 0;
    font-size: 20px;
    cursor: pointer;
    color: #444;
    line-height: 1;
}

/* Body */
.ds7oos-body {
    padding: 16px;
    display: grid;
    gap: 14px;
}
.grid2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.row {
    display: grid;
    gap: 6px;
}
label {
    font-weight: 600;
    color: #374151;
}
input,
select {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

input[type="checkbox"] {
    width: 16px;
    height: 16px;
    padding: 0;
    margin: 0 6px 0 0;
    border: none;
    vertical-align: middle;
    accent-color: #2563eb; /* optional: blue tick color */
    cursor: pointer;
}

.checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}

/* Ensure labels align properly next to checkboxes */
.checkbox-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 4px;
}
button {
    background: #e5e7eb;
    color: #111827;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    cursor: pointer;
}
button:hover {
    background: #d1d5db;
}
button.primary {
    background: #2b6cb0;
    color: #fff;
    border: 1px solid #2c5282;
}
button.primary:hover {
    background: #2c5282;
}

/* Results */
.results {
    border-top: 1px solid #eee;
    padding-top: 8px;
    display: grid;
    gap: 10px;
}
.results-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.table-responsive {
    max-height: 60vh;
    overflow: auto;
    border: 1px solid #eee;
    border-radius: 8px;
}
.table {
    width: 100%;
    border-collapse: collapse;
}
.table thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 1;
}
.text-end {
    text-align: right;
}
.small {
    font-size: 12px;
}
.pager {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

/* Sortable headers */
.sortable {
    cursor: pointer;
    user-select: none;
}

/* Anim */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.97);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
</style>
