<template>
    <div class="sched-wrap">
        <header class="head">
            <h1>Scheduling</h1>
            <nav class="tabs">
                <button
                    :class="{ active: tab === 'templates' }"
                    @click="tab = 'templates'"
                >
                    Schedules
                </button>
                <button
                    :class="{ active: tab === 'userlinks' }"
                    @click="tab = 'userlinks'"
                >
                    User Schedules
                </button>
            </nav>

            <!-- Shared datalist (once) -->
            <datalist id="empnames">
                <option
                    v-for="e in ctx.employees || []"
                    :key="e.id"
                    :value="e.name || e.username"
                >
                    {{ e.username }} (#{{ e.id }})
                </option>
            </datalist>
        </header>

        <!-- TEMPLATES -->
        <section v-show="tab === 'templates'" class="pane">
            <div class="grid">
                <form class="card" @submit.prevent="ctx.schedSaveTemplate()">
                    <h2>
                        {{
                            ctx.sched_editTId ? "Edit Template" : "New Template"
                        }}
                    </h2>
                    <div class="row">
                        <label>Day</label>
                        <select
                            v-model.number="ctx.sched_tForm.day_of_week"
                            required
                        >
                            <option
                                v-for="opt in dayOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="row two">
                        <p class="hint">Effective</p>
                        <div>
                            <label>Start</label>
                            <input
                                type="time"
                                v-model="ctx.sched_tForm.start_time"
                                required
                            />
                        </div>
                        <div>
                            <label>End</label>
                            <input
                                type="time"
                                v-model="ctx.sched_tForm.end_time"
                                required
                            />
                        </div>
                        <p class="hint">
                            Time in <b>America/Los_Angeles</b>.
                        </p>
                    </div>
                    <div class="row">
                        <label class="chk">
                            <input
                                type="checkbox"
                                v-model="ctx.sched_tForm.end_next_day"
                            />
                            Crosses midnight (ends next day)
                        </label>
                    </div>
                    <div class="row">
                        <label>Unpaid break (mins)</label>
                        <input
                            type="number"
                            min="0"
                            max="600"
                            v-model.number="
                                ctx.sched_tForm.unpaid_break_minutes
                            "
                        />
                    </div>
                    <div class="row">
                        <label>Title (optional)</label>
                        <input
                            type="text"
                            v-model="ctx.sched_tForm.title"
                            placeholder="Leave blank to auto-generate"
                        />
                    </div>
                    <div class="row">
                        <label class="chk">
                            <input
                                type="checkbox"
                                v-model="ctx.sched_tForm.is_active"
                            />
                            Active
                        </label>
                    </div>
                    <div class="actions">
                        <button type="submit">
                            {{ ctx.sched_editTId ? "Update" : "Create" }}
                        </button>
                        <button
                            type="button"
                            v-if="ctx.sched_editTId"
                            class="ghost"
                            @click="ctx.schedResetTForm()"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="ghost"
                            @click="ctx.schedLoadTemplates()"
                        >
                            ⟳ Refresh
                        </button>
                    </div>
                </form>

                <div class="card">
                    <div class="list-head">
                        <div class="filters">
                            <label>Day</label>
                            <select v-model="ctx.sched_filter.day">
                                <option value="">All</option>
                                <option
                                    v-for="opt in dayOptions"
                                    :key="opt.value"
                                    :value="String(opt.value)"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                            <label>Status</label>
                            <select v-model="ctx.sched_filter.active">
                                <option value="">All</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                            <button
                                class="ghost"
                                @click="ctx.schedLoadTemplates()"
                            >
                                Apply
                            </button>
                        </div>
                    </div>

                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Day</th>
                                <th>Window</th>
                                <th>Break</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th class="right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in ctx.sched_times"
                                :key="row.timeschedId"
                            >
                                <td>{{ row.timeschedId }}</td>
                                <td>{{ ctx.schedDayName(row.day_of_week) }}</td>
                                <td>
                                    {{ ctx.schedHhmm(row.start_time) }}–{{
                                        ctx.schedHhmm(row.end_time)
                                    }}
                                    <span
                                        v-if="Number(row.end_next_day) === 1"
                                        class="tag"
                                        >+1</span
                                    >
                                </td>
                                <td>{{ row.unpaid_break_minutes }} min</td>
                                <td>{{ row.title }}</td>
                                <td>
                                    <span
                                        :class="[
                                            'pill',
                                            Number(row.is_active) === 1
                                                ? 'ok'
                                                : 'muted',
                                        ]"
                                    >
                                        {{
                                            Number(row.is_active) === 1
                                                ? "Active"
                                                : "Inactive"
                                        }}
                                    </span>
                                </td>
                                <td class="right">
                                    <button
                                        class="small"
                                        @click="ctx.schedStartEditTemplate(row)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        class="small danger"
                                        @click="ctx.schedDeleteTemplate(row)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr
                                v-if="
                                    !ctx.sched_times ||
                                    ctx.sched_times.length === 0
                                "
                            >
                                <td colspan="7" class="empty">
                                    No templates yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- USER LINKS -->
        <section v-show="tab === 'userlinks'" class="pane">
            <div class="grid">
                <form class="card" @submit.prevent="ctx.schedSaveUserLink()">
                    <h2>
                        {{
                            ctx.sched_editUId
                                ? "Edit User Link"
                                : "New User Link"
                        }}
                    </h2>
                    <div class="row">
                        <label>User</label>
                        <select
                            v-model.number="ctx.sched_selectedUserId"
                            @change="
                                ctx.schedOnSelectUser(ctx.sched_selectedUserId)
                            "
                            required
                        >
                            <option :value="null" disabled>Select user…</option>
                            <option
                                v-for="e in ctx.employees || []"
                                :key="e.id"
                                :value="e.id"
                            >
                                {{ e.name || e.username }}
                            </option>
                        </select>

                        <!-- backend still receives the numeric ID via form -->
                        <input
                            type="hidden"
                            v-model.number="ctx.sched_uForm.userId"
                        />
                        <small
                            v-if="!ctx.sched_uForm.userId"
                            style="color: #a00"
                            >Select a user.</small
                        >
                    </div>

                    <div class="row">
                        <label>Template</label>
                        <select
                            v-model.number="ctx.sched_uForm.schedId"
                            required
                        >
                            <option disabled value="">Select…</option>
                            <option
                                v-for="t in ctx.sched_times"
                                :key="t.timeschedId"
                                :value="t.timeschedId"
                            >
                                #{{ t.timeschedId }} •
                                {{ ctx.schedDayName(t.day_of_week) }}
                                {{ ctx.schedHhmm(t.start_time) }}–{{
                                    ctx.schedHhmm(t.end_time)
                                }}{{
                                    Number(t.end_next_day) === 1 ? " (+1)" : ""
                                }}
                            </option>
                        </select>
                    </div>
                    <div class="row two">
                        <div>
                            <label>Effective From (LA)</label>
                            <input
                                type="date"
                                v-model="ctx.sched_uForm.effective_from"
                            />
                        </div>
                        <div>
                            <label>Effective To (LA)</label>
                            <input
                                type="date"
                                v-model="ctx.sched_uForm.effective_to"
                            />
                        </div>
                    </div>
                    <div class="row">
                        <label>Note</label>
                        <input
                            type="text"
                            v-model="ctx.sched_uForm.schednote"
                            maxlength="255"
                        />
                    </div>

                    <div class="row">
                        <label class="chk">
                            <input
                                type="checkbox"
                                v-model="ctx.sched_uForm.is_active"
                            />
                            Active
                        </label>
                    </div>

                    <div class="actions">
                        <button type="submit">
                            {{ ctx.sched_editUId ? "Update" : "Link" }}
                        </button>
                        <button
                            type="button"
                            v-if="ctx.sched_editUId"
                            class="ghost"
                            @click="ctx.schedResetUForm()"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="ghost"
                            @click="ctx.schedLoadUserLinks()"
                        >
                            ⟳ Refresh Links
                        </button>
                    </div>
                </form>

                <div class="card">
                    <div class="list-head">
                        <div class="filters">
                            <label>User</label>
                            <select
                                v-model="ctx.sched_selectedUserId"
                                @change="
                                    ctx.schedOnSelectUser(
                                        ctx.sched_selectedUserId
                                    )
                                "
                            >
                                <option :value="null">Select user…</option>
                                <option
                                    v-for="e in ctx.employees || []"
                                    :key="e.id"
                                    :value="e.id"
                                >
                                    {{ e.name || e.username }}
                                </option>
                            </select>

                            <button
                                class="ghost"
                                @click="ctx.schedLoadUserLinksSelected()"
                            >
                                Load
                            </button>

                            <button
                                class="ghost"
                                @click="ctx.schedLoadTemplates()"
                            >
                                Refresh Templates
                            </button>
                        </div>
                    </div>

                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Template</th>
                                <th>Window</th>
                                <th>Eff. From</th>
                                <th>Eff. To</th>
                                <th>Note</th>
                                <th>Status</th>
                                <th class="right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in ctx.sched_userlinks"
                                :key="row.userschedId"
                            >
                                <td>{{ row.userschedId }}</td>
                                <td>
                                    #{{ row.schedId }} —
                                    {{ ctx.schedDayName(row.day_of_week) }}
                                </td>
                                <td>
                                    {{ ctx.schedHhmm(row.start_time) }}–{{
                                        ctx.schedHhmm(row.end_time)
                                    }}
                                    <span
                                        v-if="Number(row.end_next_day) === 1"
                                        class="tag"
                                        >+1</span
                                    >
                                </td>
                                <td>{{ row.effective_from || "—" }}</td>
                                <td>{{ row.effective_to || "—" }}</td>
                                <td>{{ row.schednote || "—" }}</td>
                                <td>
                                    <span
                                        :class="[
                                            'pill',
                                            Number(row.is_active) === 1
                                                ? 'ok'
                                                : 'muted',
                                        ]"
                                    >
                                        {{
                                            Number(row.is_active) === 1
                                                ? "Active"
                                                : "Inactive"
                                        }}
                                    </span>
                                </td>
                                <td class="right">
                                    <button
                                        class="small"
                                        @click="ctx.schedStartEditUserLink(row)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        class="small danger"
                                        @click="ctx.schedDeleteUserLink(row)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr
                                v-if="
                                    !ctx.sched_userlinks ||
                                    ctx.sched_userlinks.length === 0
                                "
                            >
                                <td colspan="8" class="empty">
                                    No links loaded.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";

const props = defineProps({
    ctx: {
        type: Object,
        default: () =>
            typeof window !== "undefined" ? window.HR_CONTEXT || {} : {},
    },
});

const dayOptions = [
    { value: 0, label: "Everyday" },
    { value: 1, label: "Mon" },
    { value: 2, label: "Tue" },
    { value: 3, label: "Wed" },
    { value: 4, label: "Thu" },
    { value: 5, label: "Fri" },
    { value: 6, label: "Sat" },
    { value: 7, label: "Sun" },
];

const tab = ref("templates");
const ctx = computed(() => props.ctx || {});
</script>

<style scoped>
.sched-wrap {
    max-width: 1100px;
    margin: 24px auto;
    padding: 0 16px;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}
.head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.head h1 {
    font-size: 20px;
    margin: 0;
}
.tabs {
    display: flex;
    gap: 8px;
}
.tabs button {
    padding: 8px 12px;
    border: 1px solid #0d6efd;
    background: #ffffff;
    border-radius: 8px;
    cursor: pointer;
    color: #000000;
}
.tabs button.active {
    background: #0d6efd;
    color: #fff;
    border-color: #0d6efd;
}
.pane .grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 16px;
}
.card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 14px;
}
.card h2 {
    margin: 0 0 12px;
    font-size: 16px;
}
.row {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 10px;
}
.row.two {
    flex-direction: row;
    gap: 12px;
}
.row.two > div {
    flex: 1;
}
label {
    font-size: 12px;
    color: #555;
}
input[type="time"],
input[type="text"],
input[type="date"],
input[type="number"],
select {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 14px;
}
.chk {
    display: flex;
    align-items: center;
    gap: 8px;
}
.actions {
    display: flex;
    gap: 8px;
    margin-top: 6px;
}
button {
    border: none;
    background: #0d6efd;
    color: #fff;
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
}
button.ghost {
    background: #f3f4f6;
    color: #111;
}
button.small {
    padding: 6px 10px;
    font-size: 12px;
}
button.danger {
    background: #ef4444;
}
.list-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.filters {
    display: flex;
    align-items: end;
    gap: 8px;
}
.tbl {
    width: 100%;
    border-collapse: collapse;
}
.tbl th,
.tbl td {
    padding: 8px 10px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
    text-align: left;
}
.tbl .right {
    text-align: right;
}
.empty {
    text-align: center;
    padding: 16px;
    color: #777;
}
.pill {
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
}
.pill.ok {
    background: #e8f5e9;
    color: #1b5e20;
}
.pill.muted {
    background: #f3f4f6;
    color: #6b7280;
}
.tag {
    font-size: 11px;
    color: #6b7280;
    border: 1px solid #e5e7eb;
    padding: 0 6px;
    border-radius: 999px;
    margin-left: 6px;
}
.hint {
    color: #6b7280;
    font-size: 12px;
    margin-top: 8px;
}
@media (max-width: 900px) {
    .pane .grid {
        grid-template-columns: 1fr;
    }
}
</style>
