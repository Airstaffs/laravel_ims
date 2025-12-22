<template>
    <Dialog :visible="true" modal header="Edit Task" :style="{ width: '90vw', maxWidth: '900px' }"
        :breakpoints="{ '768px': '95vw' }" :draggable="false" @update:visible="emit('close')">
        <form @submit.prevent="handleEdit" novalidate class="row g-4">
            <!-- Left Column -->
            <div class="col-12 col-md-6">
                <!-- Title -->
                <div class="mb-3">
                    <label for="edit-title" class="form-label fw-semibold">Title</label>
                    <InputText id="edit-title" size="small" v-model.trim="task.title" class="w-100"
                        :invalid="!task.title && showValidation" />
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <label for="edit-desc" class="form-label fw-semibold">Description</label>
                    <Textarea id="edit-desc" size="small" v-model="task.description" rows="10" class="w-100" />
                </div>

                <!-- Notes -->
                <div class="mb-3">
                    <label for="edit-notes" class="form-label fw-semibold">Notes</label>
                    <Textarea id="edit-notes" size="small" v-model="task.notes" rows="3" class="w-100" />
                </div>

                <!-- Status -->
                <div class="mb-3">
                    <label for="edit-status" class="form-label fw-semibold">Status</label>
                    <Select id="edit-status" size="small" v-model="task.status" :options="statusOptions"
                        optionLabel="label" optionValue="value" class="w-100" />
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-12 col-md-6">
                <!-- Priority -->
                <div class="mb-3">
                    <label for="edit-priority" class="form-label fw-semibold">Priority</label>
                    <Select id="edit-priority" size="small" v-model="task.priority" :options="priorityOptions"
                        optionLabel="label" optionValue="value" class="w-100" />
                </div>

                <!-- Mentions -->
                <div class="mb-3">
                    <label for="edit-mentions" class="form-label fw-semibold">Mentions</label>
                    <MentionsDropdown id="edit-mentions" size="small" v-model="task.mentions" :users="allUsers" />
                </div>

                <!-- Existing Media -->
                <div v-if="task.existingMedias.length" class="mb-3">
                    <label class="form-label fw-semibold">Current Media</label>
                    <div class="media-list">
                        <div v-for="media in task.existingMedias" :key="media"
                            class="media-item p-3 mb-2 border rounded d-flex justify-content-between align-items-center">
                            <span class="d-flex align-items-center gap-2 text-truncate flex-grow-1">
                                <Image v-if="mediaIsImage(media)" :src="getImageUrl(media)" alt="task media" width="40"
                                    height="40" preview imageClass="media-thumbnail" />
                                <i v-else :class="getFileIcon(media)" class="text-secondary"></i>
                                <span class="text-truncate">{{ getFileName(media) }}</span>
                            </span>
                            <Button icon="pi pi-times" severity="danger" size="small" text rounded
                                @click="removeExistingMedia(media)" aria-label="Remove media" />
                        </div>
                    </div>
                </div>

                <!-- Upload New Media -->
                <div class="mb-3">
                    <label for="edit-media" class="form-label fw-semibold">Upload New Media</label>
                    <FileUpload mode="basic" :multiple="true"
                        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" :maxFileSize="10000000"
                        @select="handleFiles" :auto="false" chooseLabel="Choose Files" class="w-100" />

                    <div v-if="task.images.length" class="mt-3">
                        <div v-for="(file, index) in task.images" :key="file.name + file.size + index"
                            class="media-item p-3 mb-2 border rounded d-flex justify-content-between align-items-center">
                            <span class="text-truncate flex-grow-1">{{ file.name }}</span>
                            <Button icon="pi pi-times" severity="warning" size="small" text rounded
                                @click="removeNewFile(index)" aria-label="Remove file" />
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <Button label="Cancel" size="small" severity="secondary" @click="emit('close')" text />
                    <Button type="submit" size="small" :label="isSubmitting ? 'Saving...' : 'Save Changes'"
                        :loading="isSubmitting" :disabled="isSubmitting" />
                </div>
            </div>
        </form>
    </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import FileUpload from 'primevue/fileupload'
import Image from 'primevue/image'

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
const showValidation = ref(false)
const originalMedias = ref([])

const statusOptions = [
    { label: 'To Do', value: 'todo' },
    { label: 'In Progress', value: 'inprogress' },
    { label: 'Under Review', value: 'review' },
    { label: 'Done', value: 'done' }
]

const priorityOptions = [
    { label: 'Low', value: 'low' },
    { label: 'Medium', value: 'medium' },
    { label: 'High', value: 'high' }
]

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
    showValidation.value = false
}, { immediate: true })

function handleFiles(event) {
    const files = Array.from(event.files)
    if (files.length) task.value.images.push(...files)
}

function removeNewFile(index) {
    task.value.images.splice(index, 1)
}

function removeExistingMedia(mediaPath) {
    task.value.existingMedias = task.value.existingMedias.filter(m => m !== mediaPath)
}

function getImageUrl(path) {
    return path.startsWith('http') ? path : `/images/kanban_media/${path}`
}

function getFileName(fileOrPath) {
    return typeof fileOrPath === 'string' ? fileOrPath.split('/').pop() : fileOrPath.name
}

function mediaIsImage(path) {
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(path)
}

function getFileIcon(path) {
    const ext = path.split('.').pop().toLowerCase()
    if (ext === 'pdf') return 'pi pi-file-pdf'
    if (['doc', 'docx'].includes(ext)) return 'pi pi-file-word'
    if (['xls', 'xlsx'].includes(ext)) return 'pi pi-file-excel'
    if (['ppt', 'pptx'].includes(ext)) return 'pi pi-file'
    return 'pi pi-file'
}

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
    showValidation.value = true

    if (!task.value.title.trim()) {
        Swal.fire('Validation Error', 'Title is required', 'warning')
        return
    }

    isSubmitting.value = true

    try {
        const formData = buildFormData()
        const res = await axios.post('/user/kanban/editTask', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })

        if (!res.data?.success) throw new Error(res.data?.message || 'Unknown error')

        await Swal.fire({
            icon: 'success',
            title: 'Task Updated Successfully',
            confirmButtonText: 'Ok'
        })

        emit('task-updated', res.data.task)
        emit('close')
    } catch (err) {
        const msg = err.response?.data?.message || err.response?.data?.error || err.message
        await Swal.fire({
            icon: 'error',
            title: 'Failed to update task',
            text: msg
        })
    } finally {
        isSubmitting.value = false
    }
}
</script>

<style scoped>
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

.media-list {
    max-height: 300px;
    overflow-y: auto;
}

.media-item {
    background-color: #f8f9fa;
    transition: background-color 0.2s;
}

.media-item:hover {
    background-color: #e9ecef;
}

.media-thumbnail {
    border-radius: 4px;
    object-fit: cover;
}

:deep(.media-thumbnail) {
    max-height: 40px;
    border-radius: 4px;
    object-fit: cover;
}

@media (max-width: 768px) {
    .media-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>