<template>
    <div class="announcement-container">
        <div class="announcement-header">
            <h4>Manage Announcements</h4>
            <button
                class="btn btn-primary text-white m-0"
                @click="$parent.showAddAnnouncementModal = true"
            >
                Add Announcement
            </button>
        </div>

        <div class="row g-2 mb-2">
            <div class="col-sm-3">
                <label class="form-label">Show</label>
                <select
                    class="form-select"
                    v-model="$parent.manageFilter.status"
                    @change="$parent.refreshManageAnnouncements()"
                >
                    <option value="all">All</option>
                    <option value="active">Active</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
            <div class="col-sm-9">
                <label class="form-label">Search</label>
                <input
                    class="form-control"
                    v-model.trim="$parent.manageFilter.q"
                    @input="$parent.debouncedRefreshManage()"
                    placeholder="Title or message"
                />
            </div>
        </div>

        <table class="table table-bordered">
            <thead>
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
                <template v-for="row in $parent.manageRows" :key="row.id">
                    <tr>
                        <td>{{ row.title || "(untitled)" }}</td>
                        <td>
                            {{
                                (row.start_at || "—") +
                                " → " +
                                (row.end_at || "—")
                            }}
                        </td>
                        <td>
                            <span v-if="row.is_active" class="badge bg-success"
                                >Active</span
                            >
                            <span v-else class="badge bg-secondary">Draft</span>
                        </td>
                        <td>
                            <span
                                v-if="row.recipients === 'ALL'"
                                class="badge bg-info text-dark"
                            >
                                All Users
                            </span>
                            <span
                                v-else-if="
                                    Array.isArray(row.recipients) &&
                                    row.recipients.length
                                "
                                >{{ row.recipients.join(", ") }}</span
                            >
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td>
                            <span
                                v-if="row.read_by_me"
                                class="badge bg-primary"
                            >
                                Yes
                            </span>
                            <span v-else class="badge bg-light text-dark">
                                No
                            </span>
                        </td>
                        <td>{{ row.readby_count ?? 0 }}</td>
                        <td class="d-flex flex-wrap gap-2">
                            <button
                                class="btn btn-sm btn-outline-primary"
                                @click="$parent.prefillAnnouncementForm(row)"
                            >
                                Edit
                            </button>
                            <button
                                class="btn btn-sm"
                                :class="
                                    row.is_active
                                        ? 'btn-outline-warning'
                                        : 'btn-outline-success'
                                "
                                @click="$parent.toggleAnnouncementActive(row)"
                            >
                                {{ row.is_active ? "Deactivate" : "Activate" }}
                            </button>
                        </td>
                    </tr>
                </template>

                <tr v-if="!$parent.manageRows.length">
                    <td colspan="7" class="text-center text-muted">
                        No announcements found.
                    </td>
                </tr>
            </tbody>
        </table>

        <div
            v-if="$parent.showAddAnnouncementModal"
            class="modal modal-addAnnouncement"
        >
            <div
                class="modal-overlay"
                @click="$parent.showAddAnnouncementModal = false"
            ></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Announcement</h5>
                </div>

                <div class="modal-body">
                    <form>
                        <fieldset>
                            <label>Title</label>
                            <input
                                type="text"
                                class="form-control"
                                v-model.trim="$parent.announcementForm.title"
                                placeholder="e.g., Office schedule update"
                            />
                        </fieldset>

                        <fieldset>
                            <label>Content</label>
                            <textarea
                                rows="5"
                                class="form-control"
                                v-model.trim="$parent.announcementForm.content"
                                placeholder="Write the announcement details here…"
                            ></textarea>
                        </fieldset>

                        <fieldset>
                            <label>Start At</label>
                            <input
                                type="datetime-local"
                                class="form-control"
                                v-model="$parent.announcementForm.start_at"
                            />
                        </fieldset>

                        <fieldset>
                            <label>End At</label>
                            <input
                                type="datetime-local"
                                class="form-control"
                                v-model="$parent.announcementForm.end_at"
                            />
                        </fieldset>

                        <fieldset>
                            <label>Status</label>
                            <select
                                class="form-select"
                                v-model="$parent.announcementForm.status"
                            >
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                            </select>
                        </fieldset>

                        <fieldset class="isCheckbox-container">
                            <div class="form-check">
                                <input
                                    id="grpPH"
                                    class="form-check-input"
                                    type="checkbox"
                                    :checked="$parent.announcementForm.groupPH"
                                    @change="$parent.toggleGroup('PH')"
                                />
                                <label for="grpPH" class="form-check-label"
                                    >PH Accounts</label
                                >
                            </div>

                            <div class="form-check">
                                <input
                                    id="grpUS"
                                    class="form-check-input"
                                    type="checkbox"
                                    :checked="$parent.announcementForm.groupUS"
                                    @change="$parent.toggleGroup('US')"
                                />
                                <label for="grpUS" class="form-check-label"
                                    >US Accounts</label
                                >
                            </div>
                        </fieldset>

                        <fieldset>
                            <label>Recipients (add/remove individually)</label>
                            <div
                                class="border rounded p-2"
                                style="max-height: 260px; overflow: auto"
                            >
                                <div
                                    class="form-check"
                                    v-for="emp in $parent.employees"
                                    :key="emp.id"
                                >
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        :id="'emp-' + emp.id"
                                        :value="emp.id"
                                        v-model="
                                            $parent.announcementForm.user_ids
                                        "
                                    />
                                    <label
                                        class="form-check-label"
                                        :for="'emp-' + emp.id"
                                    >
                                        {{ emp.name }} ({{ emp.username }}) —
                                        <small>{{
                                            emp.accounttype || "N/A"
                                        }}</small>
                                    </label>
                                </div>
                            </div>

                            <div class="mt-2 d-flex gap-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    @click="
                                        $parent.announcementForm.user_ids = []
                                    "
                                >
                                    Clear selection
                                </button>
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
                    <button
                        class="btn btn-outline-secondary m-0"
                        type="button"
                        @click="$parent.openManageAnnouncements()"
                    >
                        Manage Announcements
                    </button>

                    <div class="d-flex gap-2 m-0">
                        <button
                            class="btn btn-secondary"
                            type="button"
                            :disabled="$parent.annSubmitting"
                            @click="$parent.submitAnnouncement('draft')"
                        >
                            <span
                                v-if="
                                    $parent.annSubmitting &&
                                    $parent.announcementForm._mode === 'draft'
                                "
                                class="spinner-border spinner-border-sm me-1"
                            ></span>
                            Save as Draft
                        </button>

                        <button
                            class="btn btn-primary"
                            type="button"
                            :disabled="$parent.annSubmitting"
                            @click="$parent.submitAnnouncement('active')"
                        >
                            <span
                                v-if="
                                    $parent.annSubmitting &&
                                    $parent.announcementForm._mode === 'active'
                                "
                                class="spinner-border spinner-border-sm me-1"
                            ></span>
                            Save & Activate
                        </button>

                        <button
                            class="btn btn-outline-secondary"
                            type="button"
                            @click="$parent.showAddAnnouncementModal = false"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        v-if="$parent.showManageAnnouncements"
        class="modal-mask"
        style="z-index: 1060"
    >
        <div class="modal-container" style="max-width: 1080px">
            <div class="d-flex align-items-center mb-2">
                <h5 class="m-0">Manage Announcements</h5>
                <button
                    class="btn btn-sm btn-outline-secondary ms-auto"
                    @click="$parent.refreshManageAnnouncements()"
                >
                    Refresh
                </button>
                <button
                    class="btn btn-sm btn-dark ms-2"
                    @click="$parent.closeManageAnnouncements()"
                >
                    Close
                </button>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-sm-3">
                    <label class="form-label">Show</label>
                    <select
                        class="form-select"
                        v-model="$parent.manageFilter.status"
                        @change="$parent.refreshManageAnnouncements()"
                    >
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
                <div class="col-sm-9">
                    <label class="form-label">Search</label>
                    <input
                        class="form-control"
                        v-model.trim="$parent.manageFilter.q"
                        @input="$parent.debouncedRefreshManage()"
                        placeholder="Title or message"
                    />
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
                                <span
                                    v-if="row.is_active"
                                    class="badge bg-success"
                                    >Active</span
                                >
                                <span v-else class="badge bg-secondary"
                                    >Draft</span
                                >
                            </td>
                            <td>
                                <span
                                    v-if="row.recipients === 'ALL'"
                                    class="badge bg-info text-dark"
                                    >All Users</span
                                >
                                <span
                                    v-else-if="
                                        Array.isArray(row.recipients) &&
                                        row.recipients.length
                                    "
                                    >{{ row.recipients.join(", ") }}</span
                                >
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>
                                <span
                                    v-if="row.read_by_me"
                                    class="badge bg-primary"
                                    >Yes</span
                                >
                                <span v-else class="badge bg-light text-dark"
                                    >No</span
                                >
                            </td>
                            <td>{{ row.readby_count ?? 0 }}</td>
                            <td class="d-flex flex-wrap gap-2">
                                <button
                                    class="btn btn-sm btn-outline-primary"
                                    @click="
                                        $parent.prefillAnnouncementForm(row)
                                    "
                                >
                                    Edit
                                </button>
                                <button
                                    class="btn btn-sm"
                                    :class="
                                        row.is_active
                                            ? 'btn-outline-warning'
                                            : 'btn-outline-success'
                                    "
                                    @click="
                                        $parent.toggleAnnouncementActive(row)
                                    "
                                >
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
