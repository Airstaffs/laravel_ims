<template>
  <!-- ===== Add / Edit Announcement (existing modal) ===== -->
  <div v-if="hrContext.showAnnouncementModal" class="modal-mask">
    <div class="modal-container" style="max-width: 720px;">
      <h5 class="mb-3">{{ hrContext.announcementForm.id ? 'Edit Announcement' : 'Add Announcement' }}</h5>

      <!-- Title -->
      <div class="mb-2">
        <label class="form-label">Title</label>
        <input
          type="text"
          class="form-control"
          v-model.trim="hrContext.announcementForm.title"
          placeholder="e.g., Office schedule update"
        />
      </div>

      <!-- Content -->
      <div class="mb-2">
        <label class="form-label">Content</label>
        <textarea
          rows="5"
          class="form-control"
          v-model.trim="hrContext.announcementForm.content"
          placeholder="Write the announcement details here…"
        ></textarea>
      </div>

      <!-- Start / End -->
      <div class="row g-2 mb-2">
        <div class="col-md-6">
          <label class="form-label">Start At</label>
          <input
            type="datetime-local"
            class="form-control"
            v-model="hrContext.announcementForm.start_at"
          />
        </div>
        <div class="col-md-6">
          <label class="form-label">End At</label>
          <input
            type="datetime-local"
            class="form-control"
            v-model="hrContext.announcementForm.end_at"
          />
        </div>
      </div>

      <!-- Status -->
      <div class="mb-2">
        <label class="form-label">Status</label>
        <select class="form-select" v-model="hrContext.announcementForm.status">
          <option value="draft">Draft</option>
          <option value="active">Active</option>
        </select>
      </div>

      <!-- Group quick-select (PH/US) -->
      <div class="d-flex align-items-center gap-4 mb-2">
        <div class="form-check">
          <input
            id="grpPH"
            class="form-check-input"
            type="checkbox"
            :checked="hrContext.announcementForm.groupPH"
            @change="hrContext.toggleGroup('PH')"
          />
          <label for="grpPH" class="form-check-label">PH Accounts</label>
        </div>

        <div class="form-check">
          <input
            id="grpUS"
            class="form-check-input"
            type="checkbox"
            :checked="hrContext.announcementForm.groupUS"
            @change="hrContext.toggleGroup('US')"
          />
          <label for="grpUS" class="form-check-label">US Accounts</label>
        </div>
      </div>

      <!-- Recipients -->
      <div class="mb-2">
        <label class="form-label">Recipients (add/remove individually)</label>

        <div class="border rounded p-2" style="max-height: 260px; overflow: auto;">
          <div
            class="form-check"
            v-for="emp in hrContext.employees"
            :key="emp.id"
          >
            <input
              class="form-check-input"
              type="checkbox"
              :id="'emp-'+emp.id"
              :value="emp.id"
              v-model="hrContext.announcementForm.user_ids"
            />
            <label class="form-check-label" :for="'emp-'+emp.id">
              {{ emp.name }} ({{ emp.username }}) — <small>{{ emp.accounttype || 'N/A' }}</small>
            </label>
          </div>
        </div>

        <div class="mt-2 d-flex gap-2">
          <button
            type="button"
            class="btn btn-sm btn-outline-secondary"
            @click="hrContext.announcementForm.user_ids = []"
          >
            Clear selection
          </button>
          <div class="ms-auto small text-muted">
            Selected: {{ hrContext.announcementForm.user_ids.length }}
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="d-flex justify-content-between mt-3">
        <button class="btn btn-outline-secondary" type="button" @click="hrContext.openManageAnnouncements()">
          Manage Announcements
        </button>

        <div class="d-flex gap-2">
          <button
            class="btn btn-secondary"
            type="button"
            :disabled="hrContext.annSubmitting"
            @click="hrContext.submitAnnouncement('draft')"
          >
            <span v-if="hrContext.annSubmitting && hrContext.announcementForm._mode==='draft'" class="spinner-border spinner-border-sm me-1"></span>
            Save as Draft
          </button>

          <button
            class="btn btn-primary"
            type="button"
            :disabled="hrContext.annSubmitting"
            @click="hrContext.submitAnnouncement('active')"
          >
            <span v-if="hrContext.annSubmitting && hrContext.announcementForm._mode==='active'" class="spinner-border spinner-border-sm me-1"></span>
            Save & Activate
          </button>

          <button class="btn btn-outline-secondary" type="button" @click="hrContext.closeAnnouncementModal()">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== Manage Announcements (stacked modal) ===== -->
  <div v-if="hrContext.showManageAnnouncements" class="modal-mask" style="z-index: 1060;">
    <div class="modal-container" style="max-width: 1080px;">
      <div class="d-flex align-items-center mb-2">
        <h5 class="m-0">Manage Announcements</h5>
        <button class="btn btn-sm btn-outline-secondary ms-auto" @click="hrContext.refreshManageAnnouncements()">Refresh</button>
        <button class="btn btn-sm btn-dark ms-2" @click="hrContext.closeManageAnnouncements()">Close</button>
      </div>

      <div class="row g-2 mb-2">
        <div class="col-sm-3">
          <label class="form-label">Show</label>
          <select class="form-select" v-model="hrContext.manageFilter.status" @change="hrContext.refreshManageAnnouncements()">
            <option value="all">All</option>
            <option value="active">Active</option>
            <option value="draft">Draft</option>
          </select>
        </div>
        <div class="col-sm-9">
          <label class="form-label">Search</label>
          <input class="form-control" v-model.trim="hrContext.manageFilter.q" @input="hrContext.debouncedRefreshManage()" placeholder="Title or message" />
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
              <th style="width: 200px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in hrContext.manageRows" :key="row.id">
              <td>{{ row.title || '(untitled)' }}</td>
              <td>{{ (row.start_at || '—') + ' → ' + (row.end_at || '—') }}</td>
              <td>
                <span v-if="row.is_active" class="badge bg-success">Active</span>
                <span v-else class="badge bg-secondary">Draft</span>
              </td>
              <td>
                <span v-if="row.recipients === 'ALL'" class="badge bg-info text-dark">All Users</span>
                <span v-else-if="Array.isArray(row.recipients) && row.recipients.length">{{ row.recipients.join(', ') }}</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span v-if="row.read_by_me" class="badge bg-primary">Yes</span>
                <span v-else class="badge bg-light text-dark">No</span>
              </td>
              <td>{{ row.readby_count ?? 0 }}</td>
              <td class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-outline-primary" @click="hrContext.prefillAnnouncementForm(row)">Edit</button>
                <button
                  class="btn btn-sm"
                  :class="row.is_active ? 'btn-outline-warning' : 'btn-outline-success'"
                  @click="hrContext.toggleAnnouncementActive(row)"
                >
                  {{ row.is_active ? 'Deactivate' : 'Activate' }}
                </button>
              </td>
            </tr>
            <tr v-if="!hrContext.manageRows.length">
              <td colspan="7" class="text-center text-muted">No announcements found.</td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</template>


<script setup>
const { hrContext } = defineProps({
  hrContext: { type: Object, required: true },
});
</script>

<style scoped>
/* re-use your existing modal styles; these are fallbacks if needed */
.modal-mask {
  position: fixed; inset: 0; background: rgba(0,0,0,.35);
  display: flex; align-items: center; justify-content: center; z-index: 1050;
}
.modal-container {
  background: #fff; border-radius: 12px; padding: 16px; width: 100%;
  box-shadow: 0 10px 30px rgba(0,0,0,.2);
}
</style>
