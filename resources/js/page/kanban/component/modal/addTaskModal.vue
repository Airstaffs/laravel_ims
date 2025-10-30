<template>
  <div>
    <!-- Button: Open Modal -->
    <button type="button" class="btn btn-secondary btn-xs" @click="openModal" aria-label="Add new task">
      <i class="bi bi-plus"></i>
    </button>

    <!-- Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-width">
        <div class="modal-content">
          <!-- Header -->
          <div class="modal-header">
            <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
            <button type="button" class="btn-close" @click="closeModal" aria-label="Close"></button>
          </div>

          <!-- Body -->
          <div class="modal-body">
            <form @submit.prevent="submitTask" novalidate>
              <!-- Title -->
              <div class="mb-3">
                <label for="taskTitle" class="form-label">Title</label>
                <input type="text" id="taskTitle" v-model.trim="task.title" class="form-control"
                  placeholder="Enter task title" required />
              </div>

              <!-- Description -->
              <div class="mb-3">
                <label for="taskDescription" class="form-label">Description</label>
                <textarea id="taskDescription" v-model="task.description" class="form-control"
                  placeholder="Enter task description"></textarea>
              </div>

              <!-- Notes -->
              <div class="mb-3">
                <label for="taskNotes" class="form-label">Notes</label>
                <textarea id="taskNotes" v-model="task.notes" class="form-control" placeholder="Enter notes"></textarea>
              </div>

              <!-- Status -->
              <div class="mb-3">
                <label for="taskStatus" class="form-label">Status</label>
                <p class="fw-bold">To Do</p>
                <!-- <select id="taskStatus" v-model="task.status" class="form-select" required>
                  <option value="todo">To Do</option>
                  <option value="inprogress" disabled>In Progress</option>
                  <option value="review">Under Review</option>
                  <option value="done">Done</option>
                </select> -->
              </div>

              <!-- Priority -->
              <div class="mb-3">
                <label for="taskPriority" class="form-label">Priority</label>
                <select id="taskPriority" v-model="task.priority" class="form-select" required>
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>

              <!-- Mentions -->
              <div class="mb-3">
                <label for="taskMentions" class="form-label">Mentions</label>
                <MentionsDropdown id="taskMentions" v-model="task.mentions" :users="allUsers" />
              </div>

              <!-- Upload Files -->
              <div class="mb-3">
                <label for="taskFiles" class="form-label">Upload Images / Documents</label>
                <input type="file" id="taskFiles" @change="handleFiles" class="form-control" multiple
                  accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" />

                <!-- File List -->
                <ul v-if="task.files.length" class="list-group mt-2">
                  <li v-for="(file, index) in task.files" :key="fileKey(file, index)"
                    class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-truncate d-flex align-items-center">
                      <i :class="fileIcon(file)" class="me-2"></i>
                      {{ file.name }}
                    </span>
                    <button type="button" class="btn btn-sm btn-danger" @click="removeFile(index)"
                      :aria-label="`Remove ${file.name}`">
                      Remove
                    </button>
                  </li>
                </ul>
              </div>

              <!-- Submit -->
              <div class="text-end">
                <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                  <i v-if="isSubmitting" class="bi bi-arrow-repeat spin me-1"></i>
                  {{ isSubmitting ? 'Saving...' : 'Save Task' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import * as bootstrap from 'bootstrap'
import MentionsDropdown from '../mentionsDropdown.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  allUsers: { type: Array, default: () => [] }
})

const emit = defineEmits(['task-added'])

let modalInstance = null
const isSubmitting = ref(false)

const INITIAL_TASK = {
  title: '',
  description: '',
  notes: '',
  status: 'todo',
  priority: 'medium',
  mentions: [],
  files: [],
  user_id: window.user.id
}

const task = ref({ ...INITIAL_TASK })

function openModal() {
  modalInstance?.show()
}

function closeModal() {
  modalInstance?.hide()
}

function resetForm() {
  task.value = { ...INITIAL_TASK }
}

function handleFiles(event) {
  const files = Array.from(event.target.files)
  if (files.length) task.value.files.push(...files)
  event.target.value = '' // reset input
}

function removeFile(index) {
  task.value.files.splice(index, 1)
}

function fileKey(file, index) {
  return `${file.name}-${file.size}-${index}`
}

function fileIcon(file) {
  const type = file.type
  if (type.startsWith('image/')) return 'bi bi-image'
  if (type.includes('pdf')) return 'bi bi-file-earmark-pdf'
  if (type.includes('word')) return 'bi bi-file-earmark-word'
  if (type.includes('excel') || type.includes('spreadsheet')) return 'bi bi-file-earmark-excel'
  if (type.includes('presentation')) return 'bi bi-file-earmark-ppt'
  return 'bi bi-file-earmark-text'
}

function buildFormData() {
  const formData = new FormData()
  const { title, description, notes, status, priority, mentions, files, user_id } = task.value

  formData.append('title', title)
  formData.append('description', description)
  formData.append('note', notes)
  formData.append('status', status)
  formData.append('priority', priority)
  formData.append('user_id', user_id)

  mentions.forEach((m, i) => formData.append(`mentions[${i}]`, m.id))
  files.forEach((file, i) => formData.append(`files[${i}]`, file))

  return formData
}

async function submitTask() {
  if (!task.value.title.trim()) {
    Swal.fire({ icon: 'warning', title: 'Title is required!' })
    return
  }

  isSubmitting.value = true
  try {
    await axios.post('/user/kanban/addTask', buildFormData(), {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    await Swal.fire({
      icon: 'success',
      title: 'Task Added Successfully',
      confirmButtonText: 'Ok'
    })

    emit('task-added')
    resetForm()
    closeModal()
  } catch (err) {
    console.error('❌ Submit failed:', err)
    await Swal.fire({
      icon: 'error',
      title: 'Failed to add task',
      text: err.response?.data?.message || 'An unexpected error occurred.'
    })
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  const modalEl = document.getElementById('addTaskModal')
  modalInstance = new bootstrap.Modal(modalEl)

  // Reset form (including mentions and files) whenever the modal is fully hidden
  modalEl.addEventListener('hidden.bs.modal', () => {
    resetForm()
    task.value.files = []
  })
})

onBeforeUnmount(() => {
  modalInstance?.dispose()
})
</script>


<style scoped>
.btn-xs {
  padding: 0.15rem 0.3rem;
  font-size: 0.7rem;
}

.modal-dialog.modal-width {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  margin: 0.5rem;
}

.modal-content {
  width: 100%;
  max-width: 700px;
  display: flex;
  flex-direction: column;
  max-height: 90vh;
  overflow: hidden;
}

.modal-body {
  overflow-y: auto;
  flex: 1 1 auto;
  padding: 1rem;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
