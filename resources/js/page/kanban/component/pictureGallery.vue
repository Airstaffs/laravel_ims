<template>
    <div class="gallery-container">
        <!-- Thumbnails -->
        <div class="gallery-item" v-for="(image, index) in imagesWithPath" :key="index" @click="openModal(index)">
            <img :src="image" alt="image" />
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="modal fade show d-block" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content position-relative">
                    <button type="button" class="btn-close position-absolute end-0 m-3" @click="closeModal"></button>

                    <img :src="imagesWithPath[currentIndex]" class="gallery-modal-img" />

                    <button class="btn-nav left" @click="prevImage">‹</button>
                    <button class="btn-nav right" @click="nextImage">›</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'

// Props
const props = defineProps({
    images: {
        type: Array,
        default: () => []
    }
})

// State
const showModal = ref(false)
const currentIndex = ref(0)

console.log(props.images)

// Computed: prepend public path
const imagesWithPath = computed(() =>
    props.images.map(img => `/images/kanban_media/${img}`)
)

// Methods
const openModal = index => {
    currentIndex.value = index
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
}

const prevImage = () => {
    currentIndex.value =
        (currentIndex.value - 1 + imagesWithPath.value.length) %
        imagesWithPath.value.length
}

const nextImage = () => {
    currentIndex.value =
        (currentIndex.value + 1) % imagesWithPath.value.length
}
</script>

<style scoped>
.gallery-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.gallery-item {
    flex: 1 1 200px;
    max-width: 200px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
}

.gallery-item img {
    width: 100%;
    height: 180px;
    object-fit: cover;
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.gallery-item img:hover {
    transform: scale(1.05);
    opacity: 0.9;
}

.gallery-modal-img {
    width: 100%;
    height: auto;
    max-height: 85vh;
    object-fit: contain;
}

.btn-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: rgba(0, 0, 0, 0.4);
    border: none;
    font-size: 1.5rem;
    padding: 0.6rem 1rem;
    color: white;
}

.btn-nav:hover {
    background-color: rgba(0, 0, 0, 0.7);
}

.btn-nav.left {
    left: 0.5rem;
}

.btn-nav.right {
    right: 0.5rem;
}

@media (max-width: 576px) {
    .gallery-item {
        flex: 1 1 45%;
    }

    .gallery-item img {
        height: 130px;
    }

    .gallery-modal-img {
        max-height: 75vh;
    }

    .btn-nav {
        font-size: 1.2rem;
        padding: 0.4rem 0.8rem;
    }
}
</style>
