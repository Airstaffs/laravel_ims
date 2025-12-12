<template>
    <h6>Images</h6>
    <div class="gallery-container">
        <!-- Thumbnails with built-in preview -->
        <div class="gallery-item" v-for="(image, index) in imagesWithPath" :key="index">
            <Image :src="image" alt="image" preview />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import Image from 'primevue/image'

// Props
const props = defineProps({
    images: {
        type: Array,
        default: () => []
    }
})

// Computed: prepend public path
const imagesWithPath = computed(() =>
    props.images.map(img => `/images/kanban_media/${img}`)
)
</script>

<style scoped>
.gallery-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.gallery-item {
    flex: 1 1 150px;
    max-width: 100px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
}

.gallery-item :deep(img) {
    width: 100%;
    height: 100px;
    object-fit: cover;
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.gallery-item:hover :deep(img) {
    transform: scale(1.05);
    opacity: 0.9;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .gallery-item {
        flex: 1 1 45%;
    }

    .gallery-item :deep(img) {
        height: 120px;
    }
}

@media (max-width: 480px) {
    .gallery-item {
        flex: 1 1 100%;
    }

    .gallery-item :deep(img) {
        height: 150px;
    }
}
</style>