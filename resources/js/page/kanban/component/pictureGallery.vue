<template>
    <h6>Images</h6>
    <div class="gallery-container">
        <!-- Thumbnails -->

        <div class="gallery-item" v-for="(image, index) in imagesWithPath" :key="index" @click="openModal(index)">
            <img :src="image" alt="image" />
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="modal fade show d-block" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content position-relative">
                    <!-- Close button -->
                    <button type="button" class="btn-close" @click="closeModal" aria-label="Close"></button>

                    <!-- Zoomed Image -->
                    <img :src="imagesWithPath[currentIndex]" class="gallery-modal-img" />

                    <!-- Navigation -->
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
    flex: 1 1 150px;
    max-width: 100px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
}

.gallery-item img {
    width: 100%;
    height: 100px;
    object-fit: cover;
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.gallery-item img:hover {
    transform: scale(1.05);
    opacity: 0.9;
}

/* Modal image */
.gallery-modal-img {
    width: 100%;
    max-width: 90vw;
    /* responsive width */
    height: auto;
    max-height: 85vh;
    /* responsive height */
    object-fit: contain;
    display: block;
    margin: 0 auto;
}

/* Modal content */
.modal-dialog {
    max-width: 95vw;
    margin: 1rem auto;
}

.modal-content {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
    padding: 0;
    border-radius: 8px;
}

/* Close button */
.btn-close {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    z-index: 10;
    /* on top of nav buttons */
}

/* Navigation buttons */
.btn-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: rgba(0, 0, 0, 0.4);
    border: none;
    font-size: 1.5rem;
    padding: 0.6rem 1rem;
    color: white;
    z-index: 5;
    /* below close button */
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .gallery-item {
        flex: 1 1 45%;
    }

    .gallery-item img {
        height: 120px;
    }
}

@media (max-width: 480px) {
    .gallery-item {
        flex: 1 1 100%;
    }

    .gallery-item img {
        height: 150px;
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
