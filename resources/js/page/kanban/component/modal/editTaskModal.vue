<template>
    <div class="modal fade show" id="editTaskModal" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <!-- Header -->
                <div class="modal-header">
                    <h5 class="modal-title">Edit Task</h5>
                    <button type="button" class="btn-close" @click="emit('close')" aria-label="Close"></button>
                </div>

                <!-- Body -->
                <div class="modal-body">
                    <form @submit.prevent="handleEdit" novalidate class="row g-4">
                        <!-- Left Column -->
                        <div class="col-12 col-md-6">
                            <!-- Title -->
                            <div class="mb-3">
                                <label for="edit-title" class="form-label">Title</label>
                                <input id="edit-title" type="text" v-model.trim="task.title" class="form-control"
                                    required />
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="edit-desc" class="form-label">Description</label>
                                <textarea id="edit-desc" v-model="task.description" class="form-control"
                                    rows="10"></textarea>
                            </div>

                            <!-- Notes -->
                            <div class="mb-3">
                                <label for="edit-notes" class="form-label">Notes</label>
                                <textarea id="edit-notes" v-model="task.notes" class="form-control" rows="3"></textarea>
                            </div>

                            <!-- Status -->
                            <div class="mb-3">
                                <label for="edit-status" class="form-label">Status</label>
                                <select id="edit-status" v-model="task.status" class="form-select" required>
                                    <option value="todo">To Do</option>
                                    <option value="inprogress">In Progress</option>
                                    <option value="review">Under Review</option>
                                    <option value="done">Done</option>
                                </select>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-12 col-md-6">
                            <!-- Priority -->
                            <div class="mb-3">
                                <label for="edit-priority" class="form-label">Priority</label>
                                <select id="edit-priority" v-model="task.priority" class="form-select" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>

                            <!-- Mentions -->
                            <div class="mb-3">
                                <label for="edit-mentions" class="form-label">Mentions</label>
                                <MentionsDropdown id="edit-mentions" v-model="task.mentions" :users="allUsers" />
                            </div>

                            <!-- Existing Media -->
                            <div v-if="task.existingMedias.length" class="mb-3">
                                <label class="form-label">Current Media</label>
                                <ul class="list-group">
                                    <li v-for="media in task.existingMedias" :key="media"
                                        class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                        <span class="d-flex align-items-center gap-2 text-truncate">
                                            <img v-if="mediaIsImage(media)" :src="getImageUrl(media)" alt="task media"
                                                style="max-height: 40px; border-radius: 4px; object-fit: cover;"
                                                loading="lazy" @error="e => e.target.style.display = 'none'" />
                                            <span class="text-truncate">{{ getFileName(media) }}</span>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-danger flex-shrink-0 mt-2 mt-sm-0"
                                            @click="removeExistingMedia(media)">
                                            Remove
                                        </button>
                                    </li>
                                </ul>
                            </div>

                            <!-- Upload New Media -->
                            <div class="mb-3">
                                <label for="edit-media" class="form-label">Upload New Media</label>
                                <input id="edit-media" type="file" class="form-control" multiple
                                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" @change="handleFiles" />

                                <ul v-if="task.images.length" class="list-group mt-2">
                                    <li v-for="(file, index) in task.images" :key="file.name + file.size + index"
                                        class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                        <span class="text-truncate">{{ file.name }}</span>
                                        <button type="button" class="btn btn-sm btn-warning flex-shrink-0 mt-2 mt-sm-0"
                                            @click="removeNewFile(index)">
                                            Remove
                                        </button>
                                    </li>
                                </ul>
                            </div>

                            <!-- Submit -->
                            <div class="d-flex flex-wrap gap-2 justify-content-end">
                                <button type="button" class="btn btn-secondary" @click="emit('close')">Cancel</button>
                                <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                                    <i v-if="isSubmitting" class="bi bi-arrow-repeat spin me-1"></i>
                                    {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Backdrop -->
        <div class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import MentionsDropdown from '../mentionsDropdown.vue'

const props = defineProps({
    taskData: Object,
    allUsers: Array
})
const emit = defineEmits(['task-updated', 'close'])

const task = ref({
    id: null,
    title: '',
    description: '',
    notes: '',
    status: 'todo',
    priority: 'medium',
    mentions: [],
    images: [],
    existingMedias: [],
    user_id: window.user.id
})
const isSubmitting = ref(false)
const originalMedias = ref([])

watch(() => props.taskData, (newVal) => {
    if (!newVal) return
    let medias = [], mentions = []

    try {
        medias = typeof newVal.medias === 'string' ? JSON.parse(newVal.medias) :
            Array.isArray(newVal.medias) ? newVal.medias : []
        mentions = typeof newVal.mentions === 'string' ? JSON.parse(newVal.mentions) :
            Array.isArray(newVal.mentions) ? newVal.mentions : []
    } catch (err) {
        medias = Array.isArray(newVal.medias) ? newVal.medias : []
        mentions = Array.isArray(newVal.mentions) ? newVal.mentions : []
    }

    task.value = {
        id: newVal.id,
        title: newVal.title || '',
        description: newVal.description || '',
        notes: newVal.note || '',
        status: newVal.status || 'todo',
        priority: newVal.priority || 'medium',
        mentions,
        images: [],
        existingMedias: [...medias],
        user_id: newVal.userId || window.user.id
    }
    originalMedias.value = [...medias]
}, { immediate: true })

function handleFiles(e) {
    const files = Array.from(e.target.files)
    if (files.length) task.value.images.push(...files)
}
function removeNewFile(index) { task.value.images.splice(index, 1) }
function removeExistingMedia(mediaPath) { task.value.existingMedias = task.value.existingMedias.filter(m => m !== mediaPath) }
function getImageUrl(path) { return path.startsWith('http') ? path : `/images/kanban_media/${path}` }
function getFileName(fileOrPath) { return typeof fileOrPath === 'string' ? fileOrPath.split('/').pop() : fileOrPath.name }
function mediaIsImage(path) { return /\.(jpg|jpeg|png|gif|webp)$/i.test(path) }

function buildFormData() {
    const formData = new FormData()
    formData.append('taskId', task.value.id)
    formData.append('title', task.value.title)
    formData.append('description', task.value.description)
    formData.append('note', task.value.notes)
    formData.append('status', task.value.status)
    formData.append('priority', task.value.priority)

    task.value.mentions.forEach((m, i) => {
        const mentionId = typeof m === 'object' ? m.id : m
        formData.append(`mentions[${i}]`, mentionId)
    })

    task.value.images.forEach((file, i) => formData.append(`images[${i}]`, file))

    const removed = originalMedias.value.filter(m => !task.value.existingMedias.includes(m))
    removed.forEach((m, i) => formData.append(`removed_images[${i}]`, m))

    return formData
}

async function handleEdit() {
    if (!task.value.title.trim()) { Swal.fire('Validation Error', 'Title is required', 'warning'); return }
    isSubmitting.value = true
    try {
        const formData = buildFormData()
        const res = await axios.post('/user/kanban/editTask', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
        if (!res.data?.success) throw new Error(res.data?.message || 'Unknown error')
        await Swal.fire({ icon: 'success', title: 'Task Updated Successfully', confirmButtonText: 'Ok' })
        emit('task-updated', res.data.task)
        emit('close')
    } catch (err) {
        const msg = err.response?.data?.message || err.response?.data?.error || err.message
        await Swal.fire({ icon: 'error', title: 'Failed to update task', text: msg })
    } finally { isSubmitting.value = false }
}
</script>

<style scoped>
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.spin {
    animation: spin 1s linear infinite;
}

.modal-dialog {
    max-width: 900px;
    width: 90%;
    margin: 1rem auto;
}

@media (max-width: 768px) {
    .modal-dialog {
        max-width: 95%;
    }

    .modal-body .row {
        gap: 1rem;
    }

    .list-group-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .list-group-item button {
        margin-top: 0.5rem;
    }
}
</style>
