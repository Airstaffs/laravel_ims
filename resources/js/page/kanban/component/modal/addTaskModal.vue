<template>
  <div>
    <!-- Button to open modal -->
    <button type="button" class="btn btn-primary" @click="openModal">
      Add Task
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
            <form @submit.prevent="submitTask">
              <!-- Task Title -->
              <div class="mb-3">
                <label for="taskTitle" class="form-label">Title</label>
                <input type="text" id="taskTitle" v-model="task.title" class="form-control"
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
                <label class="form-label">Mentions</label>
                <MentionsDropdown v-model="task.mentions" :users="props.allUsers" />
              </div>

              <!-- Multiple Images -->
              <div class="mb-3">
                <label for="taskImages" class="form-label">Upload Images</label>
                <input type="file" id="taskImages" @change="handleFiles" class="form-control" multiple />
                <div v-if="task.images.length" class="mt-2">
                  <p class="mb-1">Selected files:</p>
                  <ul class="list-group">
                    <li v-for="(file, index) in task.images" :key="index"
                      class="list-group-item d-flex justify-content-between align-items-center">
                      {{ file.name }}
                      <button type="button" class="btn btn-sm btn-danger" @click="removeFile(index)">
                        Remove
                      </button>
                    </li>
                  </ul>
                </div>
              </div>

              <!-- Submit -->
              <div class="text-end">
                <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                  <span v-if="isSubmitting">
                    <i class="bi bi-arrow-repeat spin me-1"></i> Saving...
                  </span>
                  <span v-else>Save Task</span>
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
import MentionsDropdown from '../MentionsDropdown.vue'
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

const task = ref({
  title: '',
  description: '',
  notes: '',
  status: 'todo',
  priority: 'medium',
  mentions: [],
  images: [],
  user_id: window.user.id
})




// =======================
// Modal Controls
// =======================
function openModal() {
  modalInstance?.show()
}

function closeModal() {
  modalInstance?.hide()
}

// =======================
// File Handlers
// =======================
function handleFiles(event) {
  const files = Array.from(event.target.files)
  task.value.images.push(...files)
}

function removeFile(index) {
  task.value.images.splice(index, 1)
}

// =======================
// Submit Task
// =======================
async function submitTask() {
  isSubmitting.value = true
  try {
    const formData = new FormData()
    formData.append('title', task.value.title)
    formData.append('description', task.value.description)
    formData.append('status', task.value.status)
    formData.append('priority', task.value.priority)
    formData.append('note', task.value.notes)
    formData.append('user_id', task.value.user_id)

    if (task.value.mentions.length) {
      task.value.mentions.forEach((m, i) => formData.append(`mentions[${i}]`, m.id))
    }
    if (task.value.images.length) {
      task.value.images.forEach((file, index) => formData.append(`images[${index}]`, file))
    }

    const res = await axios.post('/user/kanban/addTask', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    emit('task-added')

    Swal.fire({
      icon: 'success',
      title: 'Task Added Successfully',
      confirmButtonText: 'Ok'
    })

    closeModal()

    // Reset form
    task.value = {
      title: '',
      description: '',
      notes: '',
      status: 'todo',
      priority: 'medium',
      mentions: [],
      images: [],
      user_id: window.user.id
    }
  } catch (err) {
    console.error('❌ Submit failed:', err)
  } finally {
    isSubmitting.value = false
  }
}

// =======================
// Lifecycle
// =======================
onMounted(() => {
  const modalEl = document.getElementById('addTaskModal')
  modalInstance = new bootstrap.Modal(modalEl)
})

onBeforeUnmount(() => {
  modalInstance?.dispose()
})
</script>

<style scoped>
.modal-dialog.modal-width {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  /* Full viewport height */
  margin: 0.5rem;
}

.modal-content {
  width: 100%;
  max-width: 700px;
  /* Desktop width */
  display: flex;
  flex-direction: column;
  max-height: 90vh;
  /* Make it scrollable if content exceeds */
  overflow: hidden;
}

.modal-body {
  overflow-y: auto;
  flex: 1 1 auto;
  /* Let modal-body grow/shrink */
  padding: 1rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .modal-content {
    max-width: 90%;
    margin: 0.5rem;
  }

  .modal-body {
    max-height: calc(100vh - 120px);
    /* leave space for header/footer */
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
</style>
