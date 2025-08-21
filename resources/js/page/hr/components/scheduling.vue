<template>
    <div class="sched-wrap">
        <header class="head">
            <h1>Scheduling</h1>
            <nav class="tabs">
                <button
                    :class="{ active: tab === 'templates' }"
                    @click="tab = 'templates'"
                >
                    Schedule
                </button>
                <button
                    :class="{ active: tab === 'userlinks' }"
                    @click="tab = 'userlinks'"
                >
                    User Schedules
                </button>
            </nav>
        </header>

        <!-- Alerts -->
        <div v-if="flash.msg" :class="['flash', flash.type]">
            <span>{{ flash.msg }}</span>
            <button class="x" @click="flash.msg = ''">×</button>
        </div>

        <!-- =============== TEMPLATES TAB =============== -->
        <section v-show="tab === 'templates'" class="pane">
            <div class="grid">
                <!-- Create / Edit form -->
                <form class="card" @submit.prevent="saveTimesched">
                    <h2>{{ editTId ? "Edit Template" : "New Template" }}</h2>
                    <div class="row">
                        <label>Day</label>
                        <select v-model.number="tForm.day_of_week" required>
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
                        <div>
                            <label>Start</label>
                            <input
                                type="time"
                                v-model="tForm.start_time"
                                required
                            />
                        </div>
                        <div>
                            <label>End</label>
                            <input
                                type="time"
                                v-model="tForm.end_time"
                                required
                            />
                        </div>
                    </div>
                    <div class="row">
                        <label class="chk">
                            <input
                                type="checkbox"
                                v-model="tForm.end_next_day"
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
                            v-model.number="tForm.unpaid_break_minutes"
                        />
                    </div>
                    <div class="row">
                        <label>Title (optional)</label>
                        <input
                            type="text"
                            v-model="tForm.title"
                            placeholder="Leave blank to auto-generate"
                        />
                    </div>
                    <div class="row">
                        <label class="chk">
                            <input type="checkbox" v-model="tForm.is_active" />
                            Active
                        </label>
                    </div>
                    <div class="actions">
                        <button type="submit">
                            {{ editTId ? "Update" : "Create" }}
                        </button>
                        <button
                            type="button"
                            v-if="editTId"
                            class="ghost"
                            @click="resetTForm"
                        >
                            Cancel
                        </button>
                    </div>
                    <p class="hint">
                        All times stored/checked in <b>America/Los_Angeles</b>.
                        Day “0” = Everyday.
                    </p>
                </form>

                <!-- List -->
                <div class="card">
                    <div class="list-head">
                        <div class="filters">
                            <label>Day</label>
                            <select v-model="filter.day">
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
                            <select v-model="filter.active">
                                <option value="">All</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                            <button class="ghost" @click="loadTimesched">
                                Apply
                            </button>
                        </div>
                        <button class="ghost" @click="loadTimesched">
                            ⟳ Refresh
                        </button>
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
                            <tr v-for="row in timesched" :key="row.timeschedId">
                                <td>{{ row.timeschedId }}</td>
                                <td>{{ dayName(row.day_of_week) }}</td>
                                <td>
                                    {{ hhmm(row.start_time) }}–{{
                                        hhmm(row.end_time)
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
                                        @click="startEditTimesched(row)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        class="small danger"
                                        @click="delTimesched(row)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="timesched.length === 0">
                                <td colspan="7" class="empty">
                                    No templates yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- =============== USER LINKS TAB =============== -->
        <section v-show="tab === 'userlinks'" class="pane">
            <div class="grid">
                <!-- Link form -->
                <form class="card" @submit.prevent="saveUserLink">
                    <h2>{{ editUId ? "Edit User Link" : "New User Link" }}</h2>
                    <div class="row">
                        <label>User ID</label>
                        <input
                            type="number"
                            v-model.number="uForm.userId"
                            required
                        />
                    </div>
                    <div class="row">
                        <label>Template</label>
                        <select v-model.number="uForm.schedId" required>
                            <option disabled value="">Select…</option>
                            <option
                                v-for="t in timesched"
                                :key="t.timeschedId"
                                :value="t.timeschedId"
                            >
                                #{{ t.timeschedId }} •
                                {{ dayName(t.day_of_week) }}
                                {{ hhmm(t.start_time) }}–{{ hhmm(t.end_time)
                                }}{{
                                    Number(t.end_next_day) === 1 ? " (+1)" : ""
                                }}
                            </option>
                        </select>
                    </div>
                    <div class="row two">
                        <div>
                            <label>Effective From (LA)</label>
                            <input type="date" v-model="uForm.effective_from" />
                        </div>
                        <div>
                            <label>Effective To (LA)</label>
                            <input type="date" v-model="uForm.effective_to" />
                        </div>
                    </div>
                    <div class="row">
                        <label>Note</label>
                        <input
                            type="text"
                            v-model="uForm.schednote"
                            maxlength="255"
                        />
                    </div>
                    <div class="actions">
                        <button type="submit">
                            {{ editUId ? "Update" : "Link" }}
                        </button>
                        <button
                            type="button"
                            v-if="editUId"
                            class="ghost"
                            @click="resetUForm"
                        >
                            Cancel
                        </button>
                    </div>
                    <p class="hint">Leave dates empty to apply indefinitely.</p>
                </form>

                <!-- User links list -->
                <div class="card">
                    <div class="list-head">
                        <div class="filters">
                            <label>User ID</label>
                            <input
                                type="number"
                                v-model.number="currentUserId"
                                placeholder="e.g. 12"
                            />
                            <button class="ghost" @click="loadUserLinks">
                                Load
                            </button>
                        </div>
                        <button class="ghost" @click="loadUserLinks">
                            ⟳ Refresh
                        </button>
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
                                <th class="right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in userlinks" :key="row.userschedId">
                                <td>{{ row.userschedId }}</td>
                                <td>
                                    #{{ row.schedId }} —
                                    {{ dayName(row.day_of_week) }}
                                </td>
                                <td>
                                    {{ hhmm(row.start_time) }}–{{
                                        hhmm(row.end_time)
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
                                <td class="right">
                                    <button
                                        class="small"
                                        @click="startEditUserLink(row)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        class="small danger"
                                        @click="delUserLink(row)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="userlinks.length === 0">
                                <td colspan="7" class="empty">
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
import { reactive, ref, onMounted, computed } from "vue";

const tab = ref("templates");
const flash = reactive({ msg: "", type: "ok" });

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

const dayName = (d) => {
    const map = {
        0: "Everyday",
        1: "Mon",
        2: "Tue",
        3: "Wed",
        4: "Thu",
        5: "Fri",
        6: "Sat",
        7: "Sun",
    };
    return map[Number(d)] ?? "???";
};
const hhmm = (t) => (t || "").slice(0, 5);

const csrf = () =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";
const j = (obj) => JSON.stringify(obj);
const api = async (url, method = "GET", body = null) => {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrf(),
        },
        body: body ? j(body) : null,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
        throw new Error(data.error || data.message || `HTTP ${res.status}`);
    }
    return data;
};

/* --------- TEMPLATES --------- */
const timesched = ref([]);
const filter = reactive({ day: "", active: "" });

const tForm = reactive({
    day_of_week: 1,
    start_time: "",
    end_time: "",
    end_next_day: false,
    unpaid_break_minutes: 60,
    title: "",
    is_active: true,
});
const editTId = ref(null);

const resetTForm = () => {
    editTId.value = null;
    Object.assign(tForm, {
        day_of_week: 1,
        start_time: "",
        end_time: "",
        end_next_day: false,
        unpaid_break_minutes: 60,
        title: "",
        is_active: true,
    });
};

const loadTimesched = async () => {
    try {
        const q = [];
        if (filter.day !== "")
            q.push(`day_of_week=${encodeURIComponent(filter.day)}`);
        if (filter.active !== "")
            q.push(`is_active=${encodeURIComponent(filter.active)}`);
        const url = `/hr/timesched${q.length ? "?" + q.join("&") : ""}`;
        const { data } = await api(url, "GET");
        timesched.value = data;
    } catch (e) {
        notify(e.message, "err");
    }
};

const saveTimesched = async () => {
    try {
        const payload = {
            day_of_week: tForm.day_of_week,
            start_time: tForm.start_time,
            end_time: tForm.end_time,
            end_next_day: !!tForm.end_next_day,
            unpaid_break_minutes: tForm.unpaid_break_minutes,
            title: tForm.title || undefined,
            is_active: !!tForm.is_active,
        };
        if (editTId.value) {
            await api(`/hr/timesched/${editTId.value}`, "PUT", payload);
            notify("Template updated.");
        } else {
            await api("/hr/timesched", "POST", payload);
            notify("Template created.");
        }
        resetTForm();
        await loadTimesched();
    } catch (e) {
        notify(e.message, "err");
    }
};

const startEditTimesched = (row) => {
    editTId.value = row.timeschedId;
    Object.assign(tForm, {
        day_of_week: Number(row.day_of_week),
        start_time: hhmm(row.start_time),
        end_time: hhmm(row.end_time),
        end_next_day: Number(row.end_next_day) === 1,
        unpaid_break_minutes: Number(row.unpaid_break_minutes),
        title: row.title || "",
        is_active: Number(row.is_active) === 1,
    });
};

const delTimesched = async (row) => {
    if (!confirm(`Delete template #${row.timeschedId}?`)) return;
    try {
        await api(`/hr/timesched/${row.timeschedId}`, "DELETE");
        notify("Template deleted.");
        if (editTId.value === row.timeschedId) resetTForm();
        await loadTimesched();
    } catch (e) {
        notify(e.message, "err");
    }
};

/* --------- USER LINKS --------- */
const userlinks = ref([]);
const currentUserId = ref(null);
const uForm = reactive({
    userId: null,
    schedId: "",
    schednote: "",
    effective_from: "",
    effective_to: "",
});
const editUId = ref(null);

const resetUForm = () => {
    editUId.value = null;
    Object.assign(uForm, {
        userId: currentUserId.value || null,
        schedId: "",
        schednote: "",
        effective_from: "",
        effective_to: "",
    });
};

const loadUserLinks = async () => {
    if (!currentUserId.value) {
        notify("Enter a User ID", "err");
        return;
    }
    try {
        const { data } = await api(
            `/hr/usersched?userId=${encodeURIComponent(currentUserId.value)}`,
            "GET"
        );
        userlinks.value = data;
        if (!uForm.userId) uForm.userId = currentUserId.value;
    } catch (e) {
        notify(e.message, "err");
    }
};

const saveUserLink = async () => {
    try {
        if (!uForm.userId || !uForm.schedId) {
            notify("User and Template are required.", "err");
            return;
        }
        const payload = {
            userId: uForm.userId,
            schedId: uForm.schedId,
            schednote: uForm.schednote || undefined,
            effective_from: uForm.effective_from || undefined,
            effective_to: uForm.effective_to || undefined,
        };
        if (editUId.value) {
            await api(`/hr/usersched/${editUId.value}`, "PUT", {
                schedId: payload.schedId,
                schednote: payload.schednote,
                effective_from: payload.effective_from,
                effective_to: payload.effective_to,
            });
            notify("Link updated.");
        } else {
            await api("/hr/usersched", "POST", payload);
            notify("Link created.");
        }
        resetUForm();
        await loadUserLinks();
    } catch (e) {
        notify(e.message, "err");
    }
};

const startEditUserLink = (row) => {
    editUId.value = row.userschedId;
    Object.assign(uForm, {
        userId: row.userId,
        schedId: row.schedId,
        schednote: row.schednote || "",
        effective_from: row.effective_from || "",
        effective_to: row.effective_to || "",
    });
};

const delUserLink = async (row) => {
    if (!confirm(`Delete link #${row.userschedId}?`)) return;
    try {
        await api(`/hr/usersched/${row.userschedId}`, "DELETE");
        notify("Link deleted.");
        if (editUId.value === row.userschedId) resetUForm();
        await loadUserLinks();
    } catch (e) {
        notify(e.message, "err");
    }
};

/* --------- UX helpers --------- */
function notify(msg, type = "ok") {
    flash.msg = msg;
    flash.type = type;
    setTimeout(() => {
        if (flash.msg === msg) flash.msg = "";
    }, 4000);
}

onMounted(() => {
    loadTimesched();
});
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
    border: 1px solid #ddd;
    background: #64a2e9;
    border-radius: 8px;
    cursor: pointer;
}
.tabs button.active {
    background: #0d6efd;
    color: #fff;
    border-color: #0d6efd;
}
.flash {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    margin: 12px 0;
    font-size: 14px;
}
.flash.ok {
    background: #e8f4ff;
    color: #0b5ed7;
    border: 1px solid #b6dbff;
}
.flash.err {
    background: #fff1f0;
    color: #a8071a;
    border: 1px solid #ffccc7;
}
.flash .x {
    all: unset;
    cursor: pointer;
    font-weight: 700;
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
