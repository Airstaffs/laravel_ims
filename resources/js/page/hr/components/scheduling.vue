<template>
    <div class="sched-container">
        <header class="sched-header">
            <!-- <h1>Scheduling</h1> -->
            <ul class="list-unstyled m-0">
                <li :class="{ active: tab === 'templates' }" @click="tab = 'templates'">
                    Schedules
                </li>
                <li :class="{ active: tab === 'userlinks' }" @click="tab = 'userlinks'">
                    User Schedules
                </li>
            </ul>

            <datalist id="empnames">
                <option v-for="e in ctx.employees || []" :key="e.id" :value="e.name || e.username">
                    {{ e.username }} (#{{ e.id }})
                </option>
            </datalist>
        </header>

        <section v-show="tab === 'templates'" class="pane sched-ui">
            <!-- <form class="section-form" @submit.prevent="ctx.schedSaveTemplate()">
                <h2 class="form-title">
                    {{ ctx.sched_editTId ? "Edit Template" : "New Template" }}
                </h2>
                <fieldset>
                    <label>Days</label>
                    <select class="form-control" v-model="ctx.sched_preset"
                        @change="ctx.schedSetPreset(ctx.sched_preset)">
                        <option value="Everyday">Everyday (Mon–Sun)</option>
                        <option value="Mon">Monday</option>
                        <option value="Tue">Tuesday</option>
                        <option value="Wed">Wednesday</option>
                        <option value="Thu">Thursday</option>
                        <option value="Fri">Friday</option>
                        <option value="Sat">Saturday</option>
                        <option value="Sun">Sunday</option>
                        <option value="Custom">Custom…</option>
                    </select>
                    <div v-if="ctx.sched_preset === 'Custom'" class="day-chips" style="margin-top: 8px">
                        <label v-for="d in DAYS" :key="d.v" class="chip">
                            <input type="checkbox" :value="d.v" v-model="ctx.sched_tForm._days_bits" />
                            {{ d.label }}
                        </label>
                        <div class="presets">
                            <button type="button" class="btn btn-ghost" @click="
                                ctx.sched_tForm._days_bits = [
                                    1, 2, 4, 8, 16, 32, 64,
                                ]
                                ">
                                All
                            </button>
                            <button type="button" class="btn btn-ghost" @click="ctx.sched_tForm._days_bits = []">
                                Clear
                            </button>
                        </div>
                    </div>
                    <p class="hint">
                        Pick one of the defaults, or choose “Custom” to select
                        multiple days.
                    </p>
                </fieldset>
                <fieldset>
                    <label>Effective</label>
                    <div class="effective-container">
                        <fieldset>
                            <label>Start</label>
                            <input class="form-control" type="time" v-model="ctx.sched_tForm.start_time" required />
                        </fieldset>
                        <fieldset>
                            <label>End</label>
                            <input class="form-control" type="time" v-model="ctx.sched_tForm.end_time" required />
                        </fieldset>
                    </div>
                    <p class="hint">Time in <b>America/Los_Angeles</b>.</p>
                </fieldset>
                <fieldset>
                    <label class="chk">
                        <input type="checkbox" v-model="ctx.sched_tForm.end_next_day" />
                        Crosses midnight (ends next day)
                    </label>
                </fieldset>
                <fieldset>
                    <label>Unpaid break (mins)</label>
                    <input class="form-control" type="number" min="0" max="600"
                        v-model.number="ctx.sched_tForm.unpaid_break_minutes" />
                </fieldset>
                <fieldset>
                    <label>Allowed early login (mins)</label>
                    <input class="form-control" type="number" min="0" max="180"
                        v-model.number="ctx.sched_tForm.early_login_mins" />
                    <p class="hint">
                        How many minutes before shift start a user may log into
                        the system.
                    </p>
                </fieldset>

                <fieldset>
                    <label>Allowed early clock-in (mins)</label>
                    <input class="form-control" type="number" min="0" max="180"
                        v-model.number="ctx.sched_tForm.early_clockin_mins" />
                    <p class="hint">
                        How many minutes before shift start a user can clock in.
                    </p>
                </fieldset>

                <fieldset>
                    <label>Clock-out grace before auto clock-out (mins)</label>
                    <input class="form-control" type="number" min="0" max="180"
                        v-model.number="ctx.sched_tForm.grace_clockout_mins" />
                    <p class="hint">
                        Minutes after shift end before auto clock-out triggers.
                    </p>
                </fieldset>
                <fieldset>
                    <label>Title (optional)</label>
                    <input class="form-control" type="text" v-model="ctx.sched_tForm.title"
                        placeholder="Leave blank to auto-generate" />
                </fieldset>
                <fieldset>
                    <label class="chk">
                        <input type="checkbox" v-model="ctx.sched_tForm.is_active" />
                        Active
                    </label>
                </fieldset>
                <div class="actions-container">
                    <button type="submit">
                        {{ ctx.sched_editTId ? "Update" : "Create" }}
                    </button>
                    <button type="button" v-if="ctx.sched_editTId" class="btn btn-ghost" @click="ctx.schedResetTForm()">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-ghost" @click="ctx.schedLoadTemplates()">
                        ⟳ Refresh
                    </button>
                </div>
            </form> -->

            <div class="list-table-container">
                <!-- Filters -->
                <form class="filters-container" @submit.prevent="ctx.schedLoadTemplates()">
                    <fieldset>
                        <label>Day</label>
                        <Select placeholder="Select Day" :options="filterDayOptions" v-model="ctx.sched_filter.day"
                            size="small" fluid optionLabel="label" optionValue="value" />
                        <!-- <select v-model="ctx.sched_filter.day" class="form-control">
                            <option value="">All</option>
                            <option v-for="opt in dayOptions" :key="opt.value" :value="String(opt.value)">
                                {{ opt.label }}
                            </option>
                        </select> -->
                    </fieldset>

                    <fieldset>
                        <label>Status</label>
                        <Select placeholder="Select Status" :options="filterStatusOptions"
                            v-model="ctx.sched_filter.active" size="small" fluid optionLabel="label"
                            optionValue="value" />
                        <!-- <select v-model="ctx.sched_filter.active" class="form-control">
                            <option value="">All</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select> -->
                    </fieldset>

                    <Button type="submit" label="Apply" size="small" severity="info" />
                    <Button size="small" icon="pi pi-plus" label="New Template" @click="openScheduleModal = true" />
                </form>

                <div class="d-md-none mt-4">
                    <div v-if="!ctx.sched_times || ctx.sched_times.length === 0" class="text-center text-muted py-4">
                        No templates yet.
                    </div>

                    <div class="sched-card shadow-sm rounded-3 mb-2 p-3" v-for="row in ctx.sched_times"
                        :key="row.timeschedId">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="flex-grow-1">
                                <div class="small text-secondary">Day</div>
                                <div class="fw-semibold">
                                    {{
                                        ctx.schedDaysLabel(
                                            row.days_mask,
                                            row.day_of_week,
                                            true
                                        )
                                    }}
                                </div>
                            </div>
                            <span :class="[
                                'pill',
                                Number(row.is_active) === 1
                                    ? 'ok'
                                    : 'muted',
                            ]">
                                {{
                                    Number(row.is_active) === 1
                                        ? "Active"
                                        : "Inactive"
                                }}
                            </span>
                        </div>

                        <div class="row g-2 mt-2">
                            <div class="col-8">
                                <div class="small text-secondary">Window</div>
                                <div class="fw-semibold">
                                    {{ ctx.schedHhmm(row.start_time) }} –
                                    {{ ctx.schedHhmm(row.end_time) }}
                                    <span v-if="Number(row.end_next_day) === 1" class="tag">+1</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="small text-secondary">Break</div>
                                <div class="fw-semibold">
                                    {{ row.unpaid_break_minutes }} min
                                </div>
                            </div>
                        </div>

                        <div class="mt-2">
                            <div class="small text-secondary">Title</div>
                            <div class="text-truncate">
                                {{ row.title || "—" }}
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <Button @click="openModalEditMode(row)" label="Edit" size="small" severity="info" />
                            <Button @click="ctx.schedDeleteTemplate(row)" label="Delete" size="small"
                                severity="danger" />
                        </div>
                    </div>
                </div>
                <XDataTable :value="ctx.sched_times" :columns="scheduleColumns" :showIndex="true" :paginator="false"
                    tableClass=" d-none d-md-block mt-4">
                    <template #day="{ data }">
                        <p> {{
                            ctx.schedDaysLabel(
                                data.days_mask,
                                data.day_of_week,
                                true
                            )
                        }}</p>
                    </template>
                    <template #window="{ data }">
                        <div>
                            {{ ctx.schedHhmm(data.start_time) }} –
                            {{ ctx.schedHhmm(data.end_time) }}
                            <span v-if="Number(data.end_next_day) === 1" class="tag">+1</span>
                        </div>
                    </template>
                    <template #break="{ data }">
                        <p>{{ data.unpaid_break_minutes }} min</p>
                    </template>
                    <template #earlyLogin="{ data }">
                        <p> {{ minsOrDash(data.early_login_mins) }}</p>
                    </template>
                    <template #earlyIn="{ data }">
                        <p>{{ minsOrDash(data.early_clockin_mins) }}</p>
                    </template>
                    <template #graceOut="{ data }">
                        {{ minsOrDash(data.grace_clockout_mins) }}
                    </template>
                    <template #status="{ data }">
                        <span :class="[
                            'pill',
                            Number(data.is_active) === 1
                                ? 'ok'
                                : 'muted',
                        ]">
                            {{
                                Number(data.is_active) === 1
                                    ? "Active"
                                    : "Inactive"
                            }}
                        </span>
                    </template>
                    <template #actions="{ data }">
                        <div class="d-flex gap-2">
                            <Button @click="openModalEditMode(data)" label="Edit" size="small" severity="info"
                                variant="text" />
                            <Button @click="ctx.schedDeleteTemplate(data)" label="Delete" size="small" severity="danger"
                                variant="text" />
                        </div>
                    </template>
                </XDataTable>
                <!-- <div class="table-responsive d-none d-md-block">
                    <table class="table table-bordered m-0 align-middle table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>#</th>
                                <th>Day</th>
                                <th>Window</th>
                                <th>Break</th>
                                <th>Early Login</th>
                                <th>Early In</th>
                                <th>Grace Out</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in ctx.sched_times" :key="row.timeschedId">
                                <td>{{ row.timeschedId }}</td>
                                <td>
                                    {{
                                        ctx.schedDaysLabel(
                                            row.days_mask,
                                            row.day_of_week,
                                            true
                                        )
                                    }}
                                </td>
                                <td>
                                    {{ ctx.schedHhmm(row.start_time) }} –
                                    {{ ctx.schedHhmm(row.end_time) }}
                                    <span v-if="Number(row.end_next_day) === 1" class="tag">+1</span>
                                </td>
                                <td>{{ row.unpaid_break_minutes }} min</td>
                                <td>
                                    {{ minsOrDash(row.early_login_mins) }}
                                </td>
                                <td>
                                    {{ minsOrDash(row.early_clockin_mins) }}
                                </td>
                                <td>
                                    {{ minsOrDash(row.grace_clockout_mins) }}
                                </td>
                                <td class="text-truncate" style="max-width: 280px">
                                    {{ row.title }}
                                </td>
                                <td>
                                    <span :class="[
                                        'pill',
                                        Number(row.is_active) === 1
                                            ? 'ok'
                                            : 'muted',
                                    ]">
                                        {{
                                            Number(row.is_active) === 1
                                                ? "Active"
                                                : "Inactive"
                                        }}
                                    </span>
                                </td>
                                <td class="actions">
                                    <Button @click="openModalEditMode(row)" label="Edit" size="small" />
                                    <button class="btn-small danger" @click="ctx.schedDeleteTemplate(row)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="
                                !ctx.sched_times ||
                                ctx.sched_times.length === 0
                            ">
                                <td colspan="7" class="empty">
                                    No templates yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div> -->
            </div>
        </section>

        <section v-show="tab === 'userlinks'" class="pane sched-ui">
            <!-- <form class="section-form" @submit.prevent="ctx.schedSaveUserLink()">
                <h2 class="form-title">
                    {{ ctx.sched_editUId ? "Edit User Link" : "New User Link" }}
                </h2>
                <fieldset>
                    <label>User</label>
                    <select class="form-control" v-model.number="ctx.sched_selectedUserId" @change="
                        ctx.schedOnSelectUser(ctx.sched_selectedUserId)
                        " required>
                        <option :value="null" disabled>Select user…</option>
                        <option v-for="e in ctx.employees || []" :key="e.id" :value="e.id">
                            {{ e.name || e.username }}
                        </option>
                    </select>
                    <input type="hidden" v-model.number="ctx.sched_uForm.userId" />
                    <small v-if="!ctx.sched_uForm.userId" style="color: #a00">Select a user.</small>
                </fieldset>
                <fieldset>
                    <label>Template</label>
                    <select class="form-control" v-model.number="ctx.sched_uForm.schedId" required>
                        <option disabled value="">Select…</option>
                        <option v-for="t in ctx.sched_times" :key="t.timeschedId" :value="t.timeschedId">
                            #{{ t.timeschedId }} •
                            {{ ctx.schedDayName(t.day_of_week) }}
                            {{ ctx.schedHhmm(t.start_time) }} –
                            {{ ctx.schedHhmm(t.end_time) }}
                            {{ Number(t.end_next_day) === 1 ? " (+1)" : "" }}
                        </option>
                    </select>
                </fieldset>
                <fieldset>
                    <div class="effective-container">
                        <fieldset>
                            <label>Effective From (LA)</label>
                            <input class="form-control" type="date" v-model="ctx.sched_uForm.effective_from" />
                        </fieldset>
                        <fieldset>
                            <label>Effective To (LA)</label>
                            <input class="form-control" type="date" v-model="ctx.sched_uForm.effective_to" />
                        </fieldset>
                    </div>
                </fieldset>
                <fieldset>
                    <label>Note</label>
                    <input class="form-control" type="text" v-model="ctx.sched_uForm.schednote" maxlength="255" />
                </fieldset>
                <fieldset>
                    <label class="chk">
                        <input type="checkbox" v-model="ctx.sched_uForm.is_active" />
                        Active
                    </label>
                </fieldset>
                <div class="actions-container">
                    <button type="submit">
                        {{ ctx.sched_editUId ? "Update" : "Link" }}
                    </button>
                    <button type="button" v-if="ctx.sched_editUId" class="btn btn-ghost" @click="ctx.schedResetUForm()">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-ghost" @click="ctx.schedLoadUserLinks()">
                        ⟳ Refresh Links
                    </button>
                </div>
            </form> -->

            <div class="list-table-container">
                <!-- Filters -->
                <form class="filters-container mb-4" @submit.prevent="ctx.schedLoadUserLinksSelected()">
                    <fieldset>
                        <label>User</label>
                        <!-- <select class="form-control" v-model="ctx.sched_selectedUserId" @change="
                            ctx.schedOnSelectUser(ctx.sched_selectedUserId)
                            ">
                            <option :value="null">Select user…</option>
                            <option v-for="e in ctx.employees || []" :key="e.id" :value="e.id">
                                {{ e.name || e.username }}
                            </option>
                        </select> -->
                        <Select :options="employeeOptions" v-model.number="ctx.sched_selectedUserId" @change="
                            ctx.schedOnSelectUser(ctx.sched_selectedUserId)
                            " size="small" optionLabel="label" optionValue="value" fluid />
                    </fieldset>

                    <Button type="submit" size="small" severity="info" label="Apply" />
                    <Button type="button" size="small" severity="success" icon="pi pi-plus" label="Link User"
                        @click="openModalUserSchedAddMode()" />
                    <Button type="button" size="small" severity="warn" @click="ctx.schedLoadTemplates()"
                        icon="pi pi-refresh" label="Refresh" />


                </form>

                <div class="d-md-none">
                    <div v-if="
                        !ctx.sched_userlinks ||
                        ctx.sched_userlinks.length === 0
                    " class="text-center text-muted py-4">
                        No links loaded.
                    </div>

                    <div class="userlink-card shadow-sm rounded-3 mb-2 p-3" v-for="row in ctx.sched_userlinks"
                        :key="row.userschedId">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="flex-grow-1">
                                <div class="small text-secondary">Template</div>
                                <div class="fw-semibold text-truncate">
                                    #{{ row.schedId }} —
                                    {{
                                        ctx.schedDaysLabel(
                                            row.days_mask,
                                            row.day_of_week,
                                            true
                                        )
                                    }}
                                </div>
                            </div>
                            <span :class="[
                                'pill',
                                Number(row.is_active) === 1
                                    ? 'ok'
                                    : 'muted',
                            ]">
                                {{
                                    Number(row.is_active) === 1
                                        ? "Active"
                                        : "Inactive"
                                }}
                            </span>
                        </div>

                        <div class="row g-2 mt-2">
                            <div class="col-12">
                                <div class="small text-secondary">Window</div>
                                <div class="fw-semibold">
                                    {{ ctx.schedHhmm(row.start_time) }} –
                                    {{ ctx.schedHhmm(row.end_time) }}
                                    <span v-if="Number(row.end_next_day) === 1" class="tag">+1</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="small text-secondary">
                                    Eff. From
                                </div>
                                <div class="fw-semibold">
                                    {{ row.effective_from || "—" }}
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="small text-secondary">Eff. To</div>
                                <div class="fw-semibold">
                                    {{ row.effective_to || "—" }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-2">
                            <div class="small text-secondary">Note</div>
                            <div class="text-truncate">
                                {{ row.schednote || "—" }}
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <Button severity="info" size="small" @click="ctx.schedStartEditUserLink(row)"
                                label="Edit" />
                            <Button severity="danger" size="small" @click="ctx.schedDeleteUserLink(row)"
                                label="Delete" />
                        </div>
                    </div>
                </div>
                <XDataTable :value="ctx.sched_userlinks" :columns="userScheduleColumns" :paginator="false"
                    :showIndex="true">
                    <template #template="{ data }">
                        <p>
                            #{{ data.schedId }} —
                            {{
                                ctx.schedDaysLabel(
                                    data.days_mask,
                                    data.day_of_week,
                                    true
                                )
                            }}
                        </p>
                    </template>
                    <template #window="{ data }">
                        <p> {{ ctx.schedHhmm(data.start_time) }} –
                            {{ ctx.schedHhmm(data.end_time) }}
                            <span v-if="Number(data.end_next_day) === 1" class="tag">+1</span>
                        </p>
                    </template>
                    <template #effFrom="{ data }">
                        <p> {{ data.effective_from || "—" }}
                        </p>
                    </template>
                    <template #effTo="{ data }">
                        <p> {{ data.effective_to || "—" }}
                        </p>
                    </template>
                    <template #note="{ data }">
                        <p>{{ data.schednote || "—" }}</p>
                    </template>
                    <template #status="{ data }">
                        <span :class="[
                            'pill',
                            Number(data.is_active) === 1
                                ? 'ok'
                                : 'muted',
                        ]">
                            {{
                                Number(data.is_active) === 1
                                    ? "Active"
                                    : "Inactive"
                            }}
                        </span>
                    </template>
                    <template #actions="{ data }">
                        <Button severity="contrast" variant="text" @click="openModalUserSchedEditMode(data)"
                            label="Edit" class="text-primary" size="small" />
                        <Button severity="contrast" variant="text" class="text-danger"
                            @click="ctx.schedDeleteUserLink(data)" label="Delete" size="small" />
                    </template>
                </XDataTable>
                <!-- <div class="table-responsive d-none d-md-block">
                    <table class="table table-bordered m-0 align-middle table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>#</th>
                                <th>Template</th>
                                <th>Window</th>
                                <th>Eff. From</th>
                                <th>Eff. To</th>
                                <th>Note</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in ctx.sched_userlinks" :key="row.userschedId">
                                <td>{{ row.userschedId }}</td>
                                <td>
                                    #{{ row.schedId }} —
                                    {{
                                        ctx.schedDaysLabel(
                                            row.days_mask,
                                            row.day_of_week,
                                            true
                                        )
                                    }}
                                </td>
                                <td>
                                    {{ ctx.schedHhmm(row.start_time) }} –
                                    {{ ctx.schedHhmm(row.end_time) }}
                                    <span v-if="Number(row.end_next_day) === 1" class="tag">+1</span>
                                </td>
                                <td>{{ row.effective_from || "—" }}</td>
                                <td>{{ row.effective_to || "—" }}</td>
                                <td class="text-truncate" style="max-width: 260px">
                                    {{ row.schednote || "—" }}
                                </td>
                                <td>
                                    <span :class="[
                                        'pill',
                                        Number(row.is_active) === 1
                                            ? 'ok'
                                            : 'muted',
                                    ]">
                                        {{
                                            Number(row.is_active) === 1
                                                ? "Active"
                                                : "Inactive"
                                        }}
                                    </span>
                                </td>
                                <td class="actions">
                                    <button class="btn-small" @click="ctx.schedStartEditUserLink(row)">
                                        Edit
                                    </button>
                                    <button class="btn-small danger" @click="ctx.schedDeleteUserLink(row)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="
                                !ctx.sched_userlinks ||
                                ctx.sched_userlinks.length === 0
                            ">
                                <td colspan="8" class="empty">
                                    No links loaded.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div> -->
            </div>
        </section>

        <!---Schedules Modal--->
        <Dialog v-model:visible="openScheduleModal" modal @update:visible="(value) => {
            if (!value) {
                ctx.schedResetTForm()
            }
        }" :header="ctx.sched_editTId ? 'Edit Template' : 'New Template'">
            <form class="section-form" @submit.prevent="submitSchedule()">
                <fieldset>
                    <label>Days</label>
                    <select class="form-control" v-model="ctx.sched_preset"
                        @change="ctx.schedSetPreset(ctx.sched_preset)">
                        <option value="Everyday">Everyday (Mon–Sun)</option>
                        <option value="Mon">Monday</option>
                        <option value="Tue">Tuesday</option>
                        <option value="Wed">Wednesday</option>
                        <option value="Thu">Thursday</option>
                        <option value="Fri">Friday</option>
                        <option value="Sat">Saturday</option>
                        <option value="Sun">Sunday</option>
                        <option value="Custom">Custom…</option>
                    </select>
                    <div v-if="ctx.sched_preset === 'Custom'" class="day-chips" style="margin-top: 8px">
                        <label v-for="d in DAYS" :key="d.v" class="chip">
                            <input type="checkbox" :value="d.v" v-model="ctx.sched_tForm._days_bits" />
                            {{ d.label }}
                        </label>
                        <div class="presets d-flex gap-2">
                            <Button type="button" size="small" severity="info" @click="
                                ctx.sched_tForm._days_bits = [
                                    1, 2, 4, 8, 16, 32, 64,
                                ]
                                " label="All" />
                            <Button type="button" size="small" severity="info" outlined
                                @click="ctx.sched_tForm._days_bits = []" label="Clear" />
                        </div>
                    </div>
                    <p class="hint">
                        Pick one of the defaults, or choose “Custom” to select
                        multiple days.
                    </p>
                </fieldset>
                <fieldset>
                    <label>Effective</label>
                    <div class="effective-container">
                        <fieldset>
                            <label>Start</label>
                            <input class="form-control" type="time" v-model="ctx.sched_tForm.start_time" required />
                        </fieldset>
                        <fieldset>
                            <label>End</label>
                            <input class="form-control" type="time" v-model="ctx.sched_tForm.end_time" required />
                        </fieldset>
                    </div>
                    <p class="hint">Time in <b>America/Los_Angeles</b>.</p>
                </fieldset>
                <fieldset>
                    <label class="chk">
                        <input type="checkbox" v-model="ctx.sched_tForm.end_next_day" />
                        Crosses midnight (ends next day)
                    </label>
                </fieldset>
                <fieldset>
                    <label>Unpaid break (mins)</label>
                    <input class="form-control" type="number" min="0" max="600"
                        v-model.number="ctx.sched_tForm.unpaid_break_minutes" />
                </fieldset>
                <fieldset>
                    <label>Allowed early login (mins)</label>
                    <input class="form-control" type="number" min="0" max="180"
                        v-model.number="ctx.sched_tForm.early_login_mins" />
                    <p class="hint">
                        How many minutes before shift start a user may log into
                        the system.
                    </p>
                </fieldset>

                <fieldset>
                    <label>Allowed early clock-in (mins)</label>
                    <input class="form-control" type="number" min="0" max="180"
                        v-model.number="ctx.sched_tForm.early_clockin_mins" />
                    <p class="hint">
                        How many minutes before shift start a user can clock in.
                    </p>
                </fieldset>

                <fieldset>
                    <label>Clock-out grace before auto clock-out (mins)</label>
                    <input class="form-control" type="number" min="0" max="180"
                        v-model.number="ctx.sched_tForm.grace_clockout_mins" />
                    <p class="hint">
                        Minutes after shift end before auto clock-out triggers.
                    </p>
                </fieldset>
                <fieldset>
                    <label>Title (optional)</label>
                    <input class="form-control" type="text" v-model="ctx.sched_tForm.title"
                        placeholder="Leave blank to auto-generate" />
                </fieldset>
                <fieldset>
                    <label class="chk">
                        <input type="checkbox" v-model="ctx.sched_tForm.is_active" />
                        Active
                    </label>
                </fieldset>
                <div class="actions-container">
                    <Button type="submit" :label="ctx.sched_editTId ? 'Update' : 'Create'" severity="info" size="small"
                        icon="pi pi-save" />
                    <Button type="button" v-if="ctx.sched_editTId" severity="danger" size="small"
                        @click="cancelModalEditMode()" label="Cancel" icon="pi pi-trash" />
                    <Button type="button" severity="success" size="small" @click="ctx.schedLoadTemplates()"
                        label="Refresh" icon="pi pi-refresh" />
                </div>
            </form>
        </Dialog>

        <!----UserSchedule Modal-->
        <Dialog v-model:visible="openUserScheduleModal" modal
            :header="ctx.sched_editUId ? 'Edit User Link' : 'New User Link'" @update:visible="(value) => {
                if (!value) {
                    console.log(value)
                    ctx.schedResetUForm()

                }
            }">
            <form class="section-form" @submit.prevent="submitUSchedule()">
                <h2 class="form-title">
                    {{ ctx.sched_editUId ? "Edit User Link" : "New User Link" }}
                </h2>
                <fieldset>
                    <label>User</label>
                    <select class="form-control" v-model.number="ctx.sched_selectedUserId" @change="
                        ctx.schedOnSelectUser(ctx.sched_selectedUserId)
                        " required>
                        <option :value="null" disabled>Select user…</option>
                        <option v-for="e in ctx.employees || []" :key="e.id" :value="e.id">
                            {{ e.name || e.username }}
                        </option>
                    </select>
                    <input type="hidden" v-model.number="ctx.sched_uForm.userId" />
                    <small v-if="!ctx.sched_uForm.userId" style="color: #a00">Select a user.</small>
                </fieldset>
                <fieldset>
                    <label>Template</label>
                    <select class="form-control" v-model.number="ctx.sched_uForm.schedId" required>
                        <option disabled value="">Select…</option>
                        <option v-for="t in ctx.sched_times" :key="t.timeschedId" :value="t.timeschedId">
                            #{{ t.timeschedId }} •
                            {{ ctx.schedDayName(t.day_of_week) }}
                            {{ ctx.schedHhmm(t.start_time) }} –
                            {{ ctx.schedHhmm(t.end_time) }}
                            {{ Number(t.end_next_day) === 1 ? " (+1)" : "" }}
                        </option>
                    </select>
                </fieldset>
                <fieldset>
                    <div class="effective-container">
                        <fieldset>
                            <label>Effective From (LA)</label>
                            <input class="form-control" type="date" v-model="ctx.sched_uForm.effective_from" />
                        </fieldset>
                        <fieldset>
                            <label>Effective To (LA)</label>
                            <input class="form-control" type="date" v-model="ctx.sched_uForm.effective_to" />
                        </fieldset>
                    </div>
                </fieldset>
                <fieldset>
                    <label>Note</label>
                    <input class="form-control" type="text" v-model="ctx.sched_uForm.schednote" maxlength="255" />
                </fieldset>
                <fieldset>
                    <label class="chk">
                        <input type="checkbox" v-model="ctx.sched_uForm.is_active" />
                        Active
                    </label>
                </fieldset>
                <div class="actions-container mt-4">
                    <Button severity="info" size="small" type="submit" :label="ctx.sched_editUId ? 'Update' : 'Link'"
                        :icon="ctx.sched_editUId ? 'pi pi-save' : 'pi pi-link'" />
                    <Button type="button" severity="danger" size="small" v-if="ctx.sched_editUId"
                        @click="ctx.schedResetUForm()" label="Cancel" />
                    <Button type="button" severity="warn" size="small" @click="ctx.schedLoadUserLinks()"
                        label="Refresh Links" icon="pi pi-refresh" />
                </div>
            </form>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { Button, Dialog, Select } from "primevue";
import XDataTable from "../../../components/DataTable/XDataTable.vue";
const props = defineProps({
    ctx: {
        type: Object, default: () =>
            typeof window !== "undefined" ? window.HR_CONTEXT || {} : {},
    },
});

const tab = ref("templates");
const ctx = computed(() => props.ctx || {});

// used by the “Day” filter dropdown in the templates list
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

// for Custom mode checkboxes
const DAYS = [
    { v: 1, label: "Mon" },
    { v: 2, label: "Tue" },
    { v: 4, label: "Wed" },
    { v: 8, label: "Thu" },
    { v: 16, label: "Fri" },
    { v: 32, label: "Sat" },
    { v: 64, label: "Sun" },
];

const employeeOptions = [{ value: null, label: "All" }, ...ctx.value.employees.map(e => ({ value: e.id, label: e.username }))]

const filterDayOptions = [{ value: '', label: 'All' }, ...dayOptions.map((day) => ({ value: String(day.value), label: day.label }))]

const filterStatusOptions = [{ value: '', label: "All" }, { value: "1", label: "Active" }, { value: "0", label: "Inactive" }]
const scheduleColumns = [
    {
        header: "Day",
        slot: "day",
        style: { fontSize: '14px' }
    },
    {
        header: "Window",
        slot: "window",
        style: { fontSize: '14px' }
    },
    {
        header: "Break",
        slot: "break",
        style: { fontSize: '14px' }
    },
    {
        header: "Early Login",
        slot: "earlyLogin",
        style: { fontSize: '14px' }
    },
    {
        header: "Early In",
        slot: "earlyIn",
        style: { fontSize: '14px' }
    },
    {
        header: "Grace Out",
        slot: "graceOut",
        style: { fontSize: '14px' }
    },
    {
        header: "Title",
        field: "title",
        style: { fontSize: '14px' }
    },
    {
        header: "Status",
        slot: "status",
        style: { fontSize: '14px' }
    },
]

const userScheduleColumns = [
    {
        header: "Template",
        slot: "template",
        style: { fontSize: '14px' }
    },
    {
        header: "Window",
        slot: "window",
        style: { fontSize: '14px' }
    },
    {
        header: "Eff. From",
        slot: "effFrom",
        style: { fontSize: '14px' }
    },
    {
        header: "Eff. To",
        slot: "effTo",
        style: { fontSize: '14px' }
    },
    {
        header: "Note",
        slot: "note",
        style: { fontSize: '14px' }
    },
    {
        header: "Status",
        slot: "status",
        style: { fontSize: '14px' }
    }
]

const openScheduleModal = ref(false);
const openUserScheduleModal = ref(false)

function openModalEditMode(row) {
    this.ctx.schedStartEditTemplate(row)
    openScheduleModal.value = true

}

function cancelModalEditMode() {
    this.ctx.schedResetTForm()
    openScheduleModal.value = false
}

function submitSchedule() {
    this.ctx.schedSaveTemplate()
    openScheduleModal.value = false
}

function openModalUserSchedEditMode(row) {
    this.ctx.schedStartEditUserLink(row)
    openUserScheduleModal.value = true
}

function openModalUserSchedAddMode() {
    openUserScheduleModal.value = true
}

function submitUSchedule() {
    this.ctx.schedSaveUserLink()
    openUserScheduleModal.value = false

}
// optional helpers (unused here; fine to delete if you like)
function maskFromArray(arr) {
    return (arr || []).reduce((m, bit) => m | bit, 0);
}
function arrayFromMask(mask) {
    const bits = Number(mask || 0);
    return DAYS.filter((d) => (bits & d.v) > 0).map((d) => d.v);
}

function minsOrDash(v) {
    const n = Number(v ?? 0);
    return n > 0 ? `${n} min` : "—";
}
</script>

<style scoped>
.sched-container {
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: stretch;
    gap: 20px;
}

.sched-header ul li {
    display: inline-block;
    padding: 8px 15px;
    cursor: pointer;
}

.sched-header ul li.active {
    border-bottom: 2px solid #007bff;
    font-weight: bold;
}

.head h1 {
    font-size: 20px;
    margin: 0;
}

/* .pane .grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 16px;
} */

.pane .section-form,
.pane .list-table-container {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
}

/* .pane {
    display: flex;
    justify-content: flex-start;
    align-items: flex-start;
    gap: 20px;
} */

.pane .section-form {
    width: 360px;
}

/* .pane .list-table-container {
    width: calc(100% - 380px);
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: flex-start;
    gap: 20px;
} */

.pane .list-table-container .table-responsive {
    width: 100%;
}

.form-title {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 600;
}

label {
    font-size: 12px;
    color: #555;
}

.effective-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.effective-container fieldset {
    flex: 1;
}

.chk {
    display: flex;
    align-items: center;
    gap: 8px;
}

.actions-container {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
}

/* button {
    border: none;
    background: #0d6efd;
    color: #fff;
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
} */

button.btn-ghost {
    height: 40px;
    background: #f3f4f6;
    color: #111;
}

button.btn-small {
    padding: 6px 10px;
    font-size: 12px;
}

button.btn-small.danger {
    background: #ef4444;
}

.grid .list-table-container {
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: stretch;
    gap: 20px;
}

.filters-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.filters-container fieldset {
    flex: 1;
}

.list-table-container table {
    font-size: 12px;
    color: #555;
    vertical-align: middle;
}

.actions {
    display: flex;
    gap: 5px;
}

.actions button {
    flex: 1;
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

.empty {
    text-align: center;
    padding: 16px;
    color: #777;
}

@media (max-width: 900px) {
    .pane .grid {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 768px) {
    .filters-container {
        grid-template-columns: repeat(4, 1fr);
        align-items: end;
    }
}
</style>
