<template>
    <div class="announcement-container">
        <div class="announcement-header">
            <h4>Manage Announcements</h4>
            <!---Show in Desktop Hide in Mobile---->
            <Button class="d-none d-md-block" size="small" severity="info" @click="$parent.openAddAnnouncementModal()"
                label="Add Announcement" />
        </div>
        <!---Show in Mobile Hide in Desktop---->
        <Button class="d-block d-md-none" size="small" severity="info" @click="$parent.openAddAnnouncementModal()"
            label="Add Announcement" />
        <div class="row g-2 mb-2">
            <div class="col-sm-3">
                <label class="form-label">Show</label>
                <Select :options="statusOptions" v-model="$parent.manageFilter.status"
                    @change="$parent.refreshManageAnnouncements()" size="small" fluid optionLabel="label"
                    optionValue="value" />
            </div>
            <div class="col-sm-9">
                <label class="form-label">Search</label>
                <InputText v-model.trim="$parent.manageFilter.q" @input="$parent.debouncedRefreshManage()"
                    placeholder="Title or message" size="small" fluid />
            </div>
        </div>

        <div class="d-md-none">
            <!-- Empty state -->
            <div v-if="!($parent.manageRows && $parent.manageRows.length)" class="text-center text-muted py-4">
                No announcements found.
            </div>

            <!-- Cards -->
            <div class="ann-card shadow-sm rounded-3 mb-2 p-3" v-for="row in $parent.manageRows" :key="row.id">
                <!-- Header: Title + Status -->
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-truncate">
                            {{ row.title || "(untitled)" }}
                        </div>
                        <div class="text-secondary small">
                            {{
                                (row.start_at || "—") +
                                " → " +
                                (row.end_at || "—")
                            }}
                        </div>
                    </div>
                    <span class="badge" :class="row.is_active ? 'bg-success' : 'bg-secondary'">
                        {{ row.is_active ? "Active" : "Draft" }}
                    </span>
                </div>

                <!-- Recipients -->
                <div class="mt-2">
                    <div class="text-secondary small mb-1">Recipients</div>
                    <div class="d-flex flex-wrap gap-1">
                        <span v-if="row.recipients === 'ALL'" class="badge text-bg-info text-dark">
                            All Users
                        </span>
                        <template v-else-if="
                            Array.isArray(row.recipients) &&
                            row.recipients.length
                        ">
                            <span v-for="(r, i) in row.recipients" :key="i" class="badge text-bg-light">
                                {{ r }}
                            </span>
                        </template>
                        <span v-else class="text-muted">—</span>
                    </div>
                </div>

                <!-- Read info -->
                <div class="d-flex flex-column align-items-start gap-2 mt-2">
                    <div>
                        <span class="small text-secondary">Read by me? </span>
                        <span class="badge" :class="row.read_by_me ? 'bg-primary' : 'bg-light text-dark'
                            ">
                            {{ row.read_by_me ? "Yes" : "No" }}
                        </span>
                    </div>
                    <div>
                        <span class="small text-secondary">Read Count: </span>
                        <span class="fw-semibold">{{ row.readby_count ?? 0 }}</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="d-grid gap-2 mt-3">
                    <Button severity="info" size="small" @click="$parent.prefillAnnouncementForm(row)" label="Edit" />
                    <Button size="small" :severity="row.is_active ? 'warn' : 'success'" @click="
                        $parent.toggleAnnouncementActive(
                            row
                        )
                        " :label="row.is_active
                                ? 'Deactivate'
                                : 'Activate'" />
                </div>
            </div>
        </div>

        <!-- Desktop / Medium+ screens: Enhanced table -->
        <XDataTable :value="$parent.manageRows" :columns="columns" :paginator="false" tableClass="d-none d-md-block">
            <template #window="{ data }">
                <p>
                    {{
                        (data.start_at || "—") +
                        " → " +
                        (data.end_at || "—")
                    }}
                </p>
            </template>
            <template #status="{ data }">
                <Tag v-if="data.is_active" severity="success" value="Active" />
                <Tag v-else severity="secondary" value="Draft" />
            </template>
            <template #recipients="{ data }">

                <Tag v-if="data.recipients === 'ALL'" value="All Users" severity="info" />
                <template v-else-if="
                    Array.isArray(data.recipients) &&
                    data.recipients.length
                ">
                    <span v-for="(r, i) in data.recipients" :key="i" class="badge text-bg-light me-1 mb-1">
                        {{ r }}
                    </span>
                </template>
            </template>
            <template #readByMe="{ data }">
                <Tag :severity="data.read_by_me
                    ? 'info'
                    : 'secondary'
                    " :value="data.read_by_me ? 'Yes' : 'No'" />
            </template>
            <template #readCount="{ data }">
                <p>{{ data.readby_count ?? 0 }}</p>
            </template>
            <template #actions="{ data }">
                <div class="d-flex flex-wrap gap-2">
                    <Button size="small" severity="contrast" variant="text" class="text-primary" @click="
                        $parent.prefillAnnouncementForm(data)
                        " label="Edit" />
                    <Button size="small" severity="contrast" variant="text"
                        :class="data.is_active ? 'text-warning' : 'text-success'" @click="
                            $parent.toggleAnnouncementActive(
                                data
                            )
                            " :label="data.is_active
                                ? 'Deactivate'
                                : 'Activate'" />
                </div>
            </template>
        </XDataTable>
        <!-- <div class="table-responsive d-none d-md-block">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th>Title</th>
                        <th>Window (Local)</th>
                        <th>Status</th>
                        <th>Recipients</th>
                        <th>Read by me?</th>
                        <th>Read Count</th>
                        <th style="width: 220px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="row in $parent.manageRows" :key="row.id">
                        <tr>
                            <td class="fw-semibold text-truncate" style="max-width: 360px">
                                {{ row.title || "(untitled)" }}
                            </td>
                            <td class="text-secondary">
                                {{
                                    (row.start_at || "—") +
                                    " → " +
                                    (row.end_at || "—")
                                }}
                            </td>
                            <td>
                                <span v-if="row.is_active" class="badge bg-success">Active</span>
                                <span v-else class="badge bg-secondary">Draft</span>
                            </td>
                            <td>
                                <span v-if="row.recipients === 'ALL'" class="badge text-bg-info text-dark">
                                    All Users
                                </span>
                                <template v-else-if="
                                    Array.isArray(row.recipients) &&
                                    row.recipients.length
                                ">
                                    <span v-for="(r, i) in row.recipients" :key="i"
                                        class="badge text-bg-light me-1 mb-1">
                                        {{ r }}
                                    </span>
                                </template>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>
                                <span :class="row.read_by_me
                                    ? 'badge bg-primary'
                                    : 'badge bg-light text-dark'
                                    ">
                                    {{ row.read_by_me ? "Yes" : "No" }}
                                </span>
                            </td>
                            <td class="value">{{ row.readby_count ?? 0 }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-sm btn-outline-primary" @click="
                                        $parent.prefillAnnouncementForm(row)
                                        ">
                                        Edit
                                    </button>
                                    <button class="btn btn-sm" :class="row.is_active
                                        ? 'btn-outline-warning'
                                        : 'btn-outline-success'
                                        " @click="
                                            $parent.toggleAnnouncementActive(
                                                row
                                            )
                                            ">
                                        {{
                                            row.is_active
                                                ? "Deactivate"
                                                : "Activate"
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr v-if="!$parent.manageRows.length">
                        <td colspan="7" class="text-center text-muted py-4">
                            No announcements found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div> -->

        <div v-if="$parent.showAddAnnouncementModal" class="modal modal-addAnnouncement">
            <div class="modal-overlay" @click="$parent.closeAddAnnouncementModal()"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Announcement</h5>
                </div>

                <div class="modal-body">
                    <form>
                        <fieldset>
                            <label>Title</label>
                            <InputText type="text" size="small" fluid v-model.trim="$parent.announcementForm.title"
                                placeholder="e.g., Office schedule update" />
                        </fieldset>

                        <fieldset>
                            <label>Content</label>
                            <Textarea rows="5" size="small" fluid v-model.trim="$parent.announcementForm.content"
                                placeholder="Write the announcement details here…"></Textarea>
                        </fieldset>

                        <fieldset>
                            <label>Start At</label>
                            <InputText type="datetime-local" size="small" fluid
                                v-model="$parent.announcementForm.start_at" />
                        </fieldset>

                        <fieldset>
                            <label>End At</label>
                            <InputText type="datetime-local" size="small" fluid
                                v-model="$parent.announcementForm.end_at" />
                        </fieldset>

                        <fieldset>
                            <label>Status</label>
                            <Select :options="statusEditOptions" v-model="$parent.announcementForm.status"
                                optionLabel="label" optionValue="value" size="small" fluid />
                            <!-- <select class="form-select" v-model="$parent.announcementForm.status">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                            </select> -->
                        </fieldset>

                        <fieldset class="isCheckbox-container">
                            <div class="form-check">
                                <input id="grpPH" class="form-check-input" type="checkbox"
                                    :checked="$parent.announcementForm.groupPH" @change="$parent.toggleGroup('PH')" />
                                <label for="grpPH" class="form-check-label">PH Accounts</label>
                            </div>

                            <div class="form-check">
                                <input id="grpUS" class="form-check-input" type="checkbox"
                                    :checked="$parent.announcementForm.groupUS" @change="$parent.toggleGroup('US')" />
                                <label for="grpUS" class="form-check-label">US Accounts</label>
                            </div>
                        </fieldset>

                        <fieldset>
                            <label>Recipients (add/remove individually)</label>
                            <div class="border rounded p-2" style="max-height: 260px; overflow: auto">
                                <div class="d-flex align-items-center gap-2" v-for="emp in $parent.employees"
                                    :key="emp.id">
                                    <input class="form-check-input" type="checkbox" :id="'emp-' + emp.id"
                                        :value="emp.id" v-model="$parent.announcementForm.user_ids
                                            " />
                                    <label class="form-check-label" :for="'emp-' + emp.id">
                                        {{ emp.name }} ({{ emp.username }}) —
                                        <small>{{
                                            emp.accounttype || "N/A"
                                        }}</small>
                                    </label>
                                </div>
                            </div>

                            <div class="mt-2 d-flex gap-2">
                                <Button type="button" severity="secondary" size="small" @click="
                                    $parent.announcementForm.user_ids = []
                                    " label="Clear selection" />
                                <div class="ms-auto small text-muted">
                                    Selected:
                                    {{
                                        $parent.announcementForm.user_ids.length
                                    }}
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>

                <div class="modal-footer">
                    <Button type="button" size="small" @click="$parent.openManageAnnouncements()"
                        label="Manage Announcements" />

                    <div class="d-flex gap-2 m-0">
                        <Button severity="warn" size="small" type="button" :disabled="$parent.annSubmitting"
                            @click="$parent.submitAnnouncement('draft')">
                            <span v-if="
                                $parent.annSubmitting &&
                                $parent.announcementForm._mode === 'draft'
                            " class="spinner-border spinner-border-sm me-1"></span>
                            Save as Draft
                        </Button>

                        <Button severity="info" size="small" type="button" :disabled="$parent.annSubmitting"
                            @click="$parent.submitAnnouncement('active')">
                            <span v-if="
                                $parent.annSubmitting &&
                                $parent.announcementForm._mode === 'active'
                            " class="spinner-border spinner-border-sm me-1"></span>
                            Save & Activate
                        </Button>

                        <Button severity="danger" size="small" type="button"
                            @click="$parent.closeAddAnnouncementModal()" label="Cancel" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="$parent.showManageAnnouncements" class="modal-mask" style="z-index: 1060">
        <div class="modal-container" style="max-width: 1080px">
            <div class="d-flex align-items-center mb-2">
                <h5 class="m-0">Manage Announcements</h5>
                <button class="btn btn-sm btn-outline-secondary ms-auto" @click="$parent.refreshManageAnnouncements()">
                    Refresh
                </button>
                <button class="btn btn-sm btn-dark ms-2" @click="$parent.closeManageAnnouncements()">
                    Close
                </button>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-sm-3">
                    <label class="form-label">Show</label>
                    <select class="form-select" v-model="$parent.manageFilter.status"
                        @change="$parent.refreshManageAnnouncements()">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
                <div class="col-sm-9">
                    <label class="form-label">Search</label>
                    <input class="form-control" v-model.trim="$parent.manageFilter.q"
                        @input="$parent.debouncedRefreshManage()" placeholder="Title or message" />
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Window (Local)</th>
                            <th>Status</th>
                            <th>Recipients</th>
                            <th>Read by me?</th>
                            <th>Read Count</th>
                            <th style="width: 200px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in $parent.manageRows" :key="row.id">
                            <td>{{ row.title || "(untitled)" }}</td>
                            <td>
                                {{
                                    (row.start_at || "—") +
                                    " → " +
                                    (row.end_at || "—")
                                }}
                            </td>
                            <td>
                                <span v-if="row.is_active" class="badge bg-success">Active</span>
                                <span v-else class="badge bg-secondary">Draft</span>
                            </td>
                            <td>
                                <span v-if="row.recipients === 'ALL'" class="badge bg-info text-dark">All Users</span>
                                <span v-else-if="
                                    Array.isArray(row.recipients) &&
                                    row.recipients.length
                                ">{{ row.recipients.join(", ") }}</span>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>
                                <span v-if="row.read_by_me" class="badge bg-primary">Yes</span>
                                <span v-else class="badge bg-light text-dark">No</span>
                            </td>
                            <td>{{ row.readby_count ?? 0 }}</td>
                            <td class="d-flex flex-wrap gap-2">
                                <button class="btn btn-sm btn-outline-primary" @click="
                                    $parent.prefillAnnouncementForm(row)
                                    ">
                                    Edit
                                </button>
                                <button class="btn btn-sm" :class="row.is_active
                                    ? 'btn-outline-warning'
                                    : 'btn-outline-success'
                                    " @click="
                                        $parent.toggleAnnouncementActive(row)
                                        ">
                                    {{
                                        row.is_active
                                            ? "Deactivate"
                                            : "Activate"
                                    }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!$parent.manageRows.length">
                            <td colspan="7" class="text-center text-muted">
                                No announcements found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import { Button, Checkbox, InputText, Select, Tag, Textarea } from "primevue";
import XDataTable from "../../../components/DataTable/XDataTable.vue"


const TABLE_COLUMNS = [
    {
        field: "title",
        header: "Title",
        bodyStyle: "font-size: 14px"
    },
    {
        header: "Window (Local)",
        slot: "window",
        bodyStyle: "font-size: 14px"
    },
    {
        header: "Status",
        slot: "status",
        bodyStyle: "font-size: 14px"
    },
    {
        header: "Recipients",
        slot: "recipients",
        bodyStyle: "font-size: 14px",
        style: "maxWidth: 10rem"
    },
    {
        header: "Read by me?",
        slot: "readByMe",
        bodyStyle: "font-size: 14px"
    },
    {
        header: "Read Count",
        slot: "readCount",
        bodyStyle: "font-size: 14px"
    }
]
export default {
    components: {
        XDataTable,
        Select,
        InputText,
        Tag,
        Button,
        Textarea,
        Checkbox
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            statusOptions: [
                { value: "all", label: "All" },
                { value: "active", label: "Active" },
                { value: "draft", label: "Draft" }
            ],
            statusEditOptions: [
                { value: "draft", label: "Draft" },
                { value: "active", label: "Active" }
            ]
        }
    }
}
</script>

<style scoped>
/* re-use your existing modal styles; these are fallbacks if needed */
.modal-mask {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
}

.modal-container {
    background: #fff;
    border-radius: 12px;
    padding: 16px;
    width: 100%;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}
</style>

<style scoped src="../hr.css"></style>
