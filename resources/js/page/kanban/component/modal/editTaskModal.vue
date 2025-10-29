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
                    <form @submit.prevent="handleEdit" novalidate>
                        <!-- Title -->
                        <div class="mb-3">
                            <label for="edit-title" class="form-label">Title</label>
                            <input id="edit-title" type="text" v-model.trim="task.title" class="form-control"
                                required />
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label for="edit-desc" class="form-label">Description</label>
                            <textarea id="edit-desc" v-model="task.description" class="form-control"></textarea>
                        </div>

                        <!-- Notes -->
                        <div class="mb-3">
                            <label for="edit-notes" class="form-label">Notes</label>
                            <textarea id="edit-notes" v-model="task.notes" class="form-control"></textarea>
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

                        <!-- Existing Images/Media -->
                        <div v-if="task.existingMedias.length" class="mb-3">
                            <label class="form-label">Current Media</label>
                            <ul class="list-group">
                                <li v-for="media in task.existingMedias" :key="media"
                                    class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="d-flex align-items-center gap-2">
                                        <img :src="getImageUrl(media)" alt="task media"
                                            style="max-height: 40px; border-radius: 4px; object-fit: cover;"
                                            loading="lazy" @error="e => e.target.style.display = 'none'" />
                                        <span class="text-truncate">{{ media.split('/').pop() }}</span>
                                    </span>
                                    <button type="button" class="btn btn-sm btn-danger flex-shrink-0"
                                        @click="removeExistingMedia(media)"
                                        :aria-label="`Remove ${media.split('/').pop()}`">
                                        Remove
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- New Images to Upload -->
                        <div class="mb-3">
                            <label for="edit-images" class="form-label">Upload New Images</label>
                            <input id="edit-images" type="file" @change="handleFiles" class="form-control" multiple
                                accept="image/*" />
                            <ul v-if="task.images.length" class="list-group mt-2">
                                <li v-for="(file, index) in task.images" :key="getFileKey(file, index)"
                                    class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="text-truncate">{{ file.name }}</span>
                                    <button type="button" class="btn btn-sm btn-warning flex-shrink-0"
                                        @click="removeNewFile(index)" :aria-label="`Remove ${file.name}`">
                                        Remove
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Submit -->
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-secondary" @click="emit('close')">Cancel</button>
                            <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                                <i v-if="isSubmitting" class="bi bi-arrow-repeat spin me-1"></i>
                                {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                            </button>
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
import MentionsDropdown from '../MentionsDropdown.vue'

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
    if (newVal) {
        let medias = []
        let mentions = []

        try {
            // Handle medias - could be string, array, or null
            if (newVal.medias) {
                medias = typeof newVal.medias === 'string' ? JSON.parse(newVal.medias) : Array.isArray(newVal.medias) ? newVal.medias : []
            }

            // Handle mentions - could be string, array of objects, or array of IDs
            if (newVal.mentions) {
                mentions = typeof newVal.mentions === 'string' ? JSON.parse(newVal.mentions) : Array.isArray(newVal.mentions) ? newVal.mentions : []
            }
        } catch (err) {
            console.error('Error parsing JSON:', err)
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
            mentions: mentions,
            images: [],
            existingMedias: [...medias],
            user_id: newVal.userId || window.user.id
        }

        originalMedias.value = [...medias]
    }
}, { immediate: true })

function handleFiles(event) {
    const files = Array.from(event.target.files)
    if (files.length) {
        task.value.images.push(...files)
    }
}

function removeNewFile(index) {
    task.value.images.splice(index, 1)
}

function removeExistingMedia(mediaPath) {
    task.value.existingMedias = task.value.existingMedias.filter(m => m !== mediaPath)
}

function getImageUrl(imagePath) {
    return imagePath.startsWith('http')
        ? imagePath
        : `/images/kanban_media/${imagePath}`
}

function getFileKey(file, index) {
    return file instanceof File
        ? file.name + file.size + index
        : file
}

function buildFormData() {
    const formData = new FormData()

    formData.append('taskId', task.value.id)
    formData.append('title', task.value.title)
    formData.append('description', task.value.description)
    formData.append('note', task.value.notes)
    formData.append('status', task.value.status)
    formData.append('priority', task.value.priority)

    // Mentions array
    if (task.value.mentions.length) {
        task.value.mentions.forEach((m, i) => {
            const mentionId = typeof m === 'object' ? m.id : m
            formData.append(`mentions[${i}]`, mentionId)
        })
    }

    // New images to upload
    if (task.value.images.length) {
        task.value.images.forEach((file, index) => {
            formData.append(`images[${index}]`, file)
        })
    }

    // Media paths to remove
    const mediasToRemove = originalMedias.value.filter(
        path => !task.value.existingMedias.includes(path)
    )

    if (mediasToRemove.length) {
        mediasToRemove.forEach((path, index) => {
            formData.append(`removed_images[${index}]`, path)
        })
    }

    return formData
}

async function handleEdit() {
    if (!task.value.title.trim()) {
        Swal.fire('Validation Error', 'Title is required', 'warning')
        return
    }

    isSubmitting.value = true
    try {
        const formData = buildFormData()

        const response = await axios.post('/user/kanban/editTask', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })

        if (!response.data || !response.data.success) {
            throw new Error(response.data?.message || 'Unknown error occurred')
        }

        await Swal.fire({
            icon: 'success',
            title: 'Task Updated Successfully',
            confirmButtonText: 'Ok'
        })

        emit('task-updated', response.data.task)
        emit('close')
    } catch (err) {
        console.error('❌ Update failed:', err)
        const errorMsg = err.response?.data?.message || err.response?.data?.error || err.message || 'An error occurred'
        await Swal.fire({
            icon: 'error',
            title: 'Failed to update task',
            text: errorMsg
        })
    } finally {
        isSubmitting.value = false
    }
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

/* Modal width */
.modal-dialog {
    width: 50%;
    max-width: 600px;
    /* optional */
}

/* Mobile */
@media (max-width: 780px) {
    .modal-dialog {
        width: 95%;
        margin: 0.5rem auto;
    }
}
</style>
