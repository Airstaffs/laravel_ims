<template>
  <div>
    <!-- Button: Open Modal -->
    <Button icon="pi pi-plus" @click="openModal" aria-label="Add new task" size="small" severity="secondary" />

    <!-- Dialog -->
    <Dialog v-model:visible="visible" modal header="Add New Task" :style="{ width: '90vw', maxWidth: '900px' }"
      :draggable="false" @hide="resetForm">
      <form @submit.prevent="submitTask" novalidate class="row g-4">
        <!-- Left Column -->
        <div class="col-12 col-md-6">
          <div class="mb-3">
            <label for="taskTitle" class="form-label fw-semibold">Title</label>
            <InputText id="taskTitle" v-model.trim="task.title" size="small" placeholder="Enter task title"
              class="w-100" :invalid="!task.title && showValidation" />
          </div>

          <div class="mb-3">
            <label for="taskDescription" class="form-label fw-semibold">Description</label>
            <Textarea id="taskDescription" v-model="task.description" rows="3" size="small"
              placeholder="Enter task description" class="w-100" />
          </div>

          <div class="mb-3">
            <label for="taskNotes" class="form-label fw-semibold">Notes</label>
            <Textarea id="taskNotes" v-model="task.notes" rows="3" size="small" placeholder="Enter notes"
              class="w-100" />
          </div>
        </div>

        <!-- Right Column -->
        <div class="col-12 col-md-6">
          <div class="mb-3">
            <label for="taskStatus" class="form-label fw-semibold">Status</label>
            <p class="fw-bold mb-0">To Do</p>
          </div>

          <div class="mb-3">
            <label for="taskPriority" class="form-label fw-semibold">Priority</label>
            <Select id="taskPriority" v-model="task.priority" size="small" :options="priorityOptions"
              optionLabel="label" optionValue="value" placeholder="Select Priority" class="w-100" />
          </div>

          <div class="mb-3">
            <label for="taskMentions" class="form-label fw-semibold">Mentions</label>
            <MentionsDropdown id="taskMentions" v-model="task.mentions" size="small" :users="allUsers" />
          </div>

          <!-- Upload Files -->
          <div class="mb-3">
            <label for="taskFiles" class="form-label fw-semibold">Upload Images / Documents</label>
            <FileUpload mode="basic" :multiple="true" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt"
              :maxFileSize="10000000" @select="handleFiles" :auto="false" chooseLabel="Choose Files" class="w-100" />

            <div v-if="task.files.length" class="mt-3">
              <div v-for="(file, index) in task.files" :key="fileKey(file, index)"
                class="file-item p-3 mb-2 border rounded d-flex justify-content-between align-items-center">
                <span class="text-truncate d-flex align-items-center gap-2 flex-grow-1">
                  <i :class="fileIcon(file)"></i>
                  <span>{{ file.name }}</span>
                </span>
                <Button icon="pi pi-times" severity="danger" size="small" text rounded @click="removeFile(index)"
                  aria-label="Remove file" />
              </div>
            </div>
          </div>

          <div class="text-end">
            <Button type="submit" label="Save Task" icon="pi pi-check" size="small" :loading="isSubmitting"
              :disabled="isSubmitting" />
          </div>
        </div>
      </form>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import FileUpload from 'primevue/fileupload'

import MentionsDropdown from '../mentionsDropdown.vue'

const props = defineProps({ allUsers: { type: Array, default: () => [] } })
const emit = defineEmits(['task-added'])

const visible = ref(false)
const isSubmitting = ref(false)
const showValidation = ref(false)

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

const priorityOptions = [
  { label: 'Low', value: 'low' },
  { label: 'Medium', value: 'medium' },
  { label: 'High', value: 'high' }
]

function openModal() {
  visible.value = true
  showValidation.value = false
}

function closeModal() {
  visible.value = false
}

function resetForm() {
  task.value = { ...INITIAL_TASK }
  showValidation.value = false
}

function handleFiles(event) {
  const files = Array.from(event.files)
  if (files.length) {
    task.value.files.push(...files)
  }
}

function removeFile(index) {
  task.value.files.splice(index, 1)
}

function fileKey(file, index) {
  return `${file.name}-${file.size}-${index}`
}

function fileIcon(file) {
  const type = file.type
  if (type.startsWith('image/')) return 'pi pi-image'
  if (type.includes('pdf')) return 'pi pi-file-pdf'
  if (type.includes('word')) return 'pi pi-file-word'
  if (type.includes('excel') || type.includes('spreadsheet')) return 'pi pi-file-excel'
  if (type.includes('presentation')) return 'pi pi-file'
  return 'pi pi-file'
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
  showValidation.value = true

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
    Swal.fire({
      icon: 'error',
      title: 'Failed to add task',
      text: err.response?.data?.message || 'An unexpected error occurred.'
    })
  } finally {
    isSubmitting.value = false
  }
}

// Expose openModal for parent component access
defineExpose({ openModal })
</script>

<style scoped>
.file-item {
  background-color: #f8f9fa;
  transition: background-color 0.2s;
}

.file-item:hover {
  background-color: #e9ecef;
}

.file-item i {
  font-size: 1.2rem;
  color: #6c757d;
}

/* Ensure form inputs take full width */
:deep(.p-inputtext),
:deep(.p-textarea),
:deep(.p-select) {
  width: 100%;
}

/* Custom styling for file upload button */
:deep(.p-fileupload-choose) {
  width: 100%;
}

@media (max-width: 768px) {
  .file-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}
</style>