<template>
  <div v-if="hrContext.showAnnouncementModal" class="modal-mask">
    <div class="modal-container" style="max-width: 720px;">
      <h5 class="mb-3">Add Announcement</h5>

      <!-- Title -->
      <div class="mb-2">
        <label class="form-label">Title</label>
        <input
          type="text"
          class="form-control"
          v-model="hrContext.announcementForm.title"
          placeholder="e.g., Office schedule update"
        />
      </div>

      <!-- Content -->
      <div class="mb-2">
        <label class="form-label">Content</label>
        <textarea
          rows="5"
          class="form-control"
          v-model="hrContext.announcementForm.content"
          placeholder="Write the announcement details here…"
        ></textarea>
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
        <label class="form-label">Recipients (you can still add/remove individually)</label>

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
      <div class="d-flex justify-content-end mt-3">
        <button class="btn btn-secondary me-2" type="button" @click="hrContext.closeAnnouncementModal()">
          Cancel
        </button>
        <button
          class="btn btn-success"
          type="button"
          :disabled="hrContext.annSubmitting"
          @click="hrContext.submitAnnouncement()"
        >
          <span
            v-if="hrContext.annSubmitting"
            class="spinner-border spinner-border-sm me-1"
          ></span>
          Send
        </button>
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
