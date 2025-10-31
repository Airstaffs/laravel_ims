<template>
    <div>
        <PriorityBadge :priority="task.priority" />
        <h4 class="my-2">{{ task.title }}</h4>
        <p class="text-secondary my-2">{{ task.description || 'No description' }}</p>
        <p class="my-2"><strong>Notes:</strong> {{ task.note || 'No notes' }}</p>

        <MentionedProfile :mentions="task.mentions" />

        <!-- Image gallery -->
        <div class="mt-4">
            <PictureGallery v-if="images.length" :images="images" />
        </div>

        <!-- Document list -->
        <div v-if="documents.length" class="mt-3">
            <h6>Attached Files</h6>
            <ul class="list-group">
                <li v-for="(file, index) in documents" :key="index"
                    class="list-group-item d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-text"></i>
                    <a :href="`/images/kanban_media/files/${file}`" target="_blank" :download="getFileName(file)"
                        class="text-decoration-none text-truncate text-secondary">
                        {{ getFileName(file) }}
                    </a>

                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import MentionedProfile from './mentionedProfile.vue'
import PictureGallery from './pictureGallery.vue'
import PriorityBadge from './priorityBadge.vue'

const props = defineProps({
    task: { type: Object, required: true }
})

const images = ref([])
const documents = ref([])

// Filter files into images & documents
function filterFileTypes() {
    if (!props.task.medias) return

    const files = Array.isArray(props.task.medias)
        ? props.task.medias
        : JSON.parse(props.task.medias || '[]')

    files.forEach(file => {
        if (file.startsWith('images/')) {
            images.value.push(file)
        } else if (file.startsWith('files/')) {
            documents.value.push(file)
        }
    })
}

// Get only the filename, removing the folder prefix
function getFileName(filePath) {
    return filePath.split('/').pop()
}

onMounted(() => {
    filterFileTypes()
})
</script>
