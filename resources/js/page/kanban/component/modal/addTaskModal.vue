<template>
  <div>
    <!-- Button to open modal -->
    <button type="button" class="btn btn-secondary btn-xs" @click="openModal" aria-label="Add new task">
      <i class="bi bi-plus"></i>
    </button>

    <!-- Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-width">
        <div class="modal-content">
          <!-- Modal Header -->
          <div class="modal-header">
            <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
            <button type="button" class="btn-close" @click="closeModal" aria-label="Close"></button>
          </div>

          <!-- Modal Body (Form) -->
          <div class="modal-body">
            <form @submit.prevent="submitTask" novalidate>
              <!-- Task Title -->
              <div class="mb-3">
                <label for="taskTitle" class="form-label">Title</label>
                <input type="text" id="taskTitle" v-model.trim="task.title" class="form-control"
                  placeholder="Enter task title" required />
              </div>

              <!-- Task Description -->
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
                <select id="taskStatus" v-model="task.status" class="form-select" required>
                  <option value="todo">To Do</option>
                  <option value="inprogress">In Progress</option>
                  <option value="review">Under Review</option>
                  <option value="done">Done</option>
                </select>
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

              <!-- Mentions Dropdown -->
              <div class="mb-3">
                <label for="taskMentions" class="form-label">Mentions</label>
                <MentionsDropdown id="taskMentions" v-model="task.mentions" :users="allUsers" />
              </div>

              <!-- Multiple Images -->
              <div class="mb-3">
                <label for="taskImages" class="form-label">Upload Images</label>
                <input type="file" id="taskImages" @change="handleFiles" class="form-control" multiple
                  accept="image/*" />
                <ul v-if="task.images.length" class="list-group mt-2">
                  <li v-for="(file, index) in task.images" :key="getFileKey(file, index)"
                    class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-truncate">{{ file.name }}</span>
                    <button type="button" class="btn btn-sm btn-danger flex-shrink-0" @click="removeFile(index)"
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
  allUsers: {
    type: Array,
    default: () => []
  }
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
  images: [],
  user_id: window.user.id
}

const task = ref({ ...INITIAL_TASK })

function openModal() {
  modalInstance?.show()
}

function closeModal() {
  modalInstance?.hide()
}

function handleFiles(event) {
  const files = Array.from(event.target.files)
  if (files.length) {
    task.value.images.push(...files)
  }
}

function removeFile(index) {
  task.value.images.splice(index, 1)
}

function getFileKey(file, index) {
  return file.name + file.size + index
}

function resetForm() {
  task.value = { ...INITIAL_TASK }
}

function buildFormData() {
  const formData = new FormData()
  formData.append('title', task.value.title)
  formData.append('description', task.value.description)
  formData.append('status', task.value.status)
  formData.append('priority', task.value.priority)
  formData.append('note', task.value.notes)
  formData.append('user_id', task.value.user_id)

  if (task.value.mentions.length) {
    task.value.mentions.forEach((m, i) => {
      formData.append(`mentions[${i}]`, m.id)
    })
  }

  if (task.value.images.length) {
    task.value.images.forEach((file, index) => {
      formData.append(`images[${index}]`, file)
    })
  }

  return formData
}

async function submitTask() {
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
      text: err.response?.data?.message || 'An error occurred'
    })
  } finally {
    isSubmitting.value = false
  }
}

// Lifecycle
onMounted(() => {
  const modalEl = document.getElementById('addTaskModal')
  modalInstance = new bootstrap.Modal(modalEl)
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

@media (max-width: 768px) {
  .modal-content {
    max-width: 90%;
    margin: 0.5rem;
  }

  .modal-body {
    max-height: calc(100vh - 120px);
  }
}

@media (max-width: 480px) {
  .modal-content {
    max-width: 100%;
    margin: 0;
    border-radius: 0;
  }

  .modal-body {
    max-height: calc(100vh - 100px);
  }
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.spin {
  animation: spin 1s linear infinite;
}
</style>