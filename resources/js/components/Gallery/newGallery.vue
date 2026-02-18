<template>
    <div class="image-section" v-if="imageList.length">
        <!-- Main Image -->
        <div class="main-image" @click="openZoomModal">
            <img :src="activeImageUrl" alt="Main Product Image" loading="lazy" @error="onImageErrorMain" />
            
            <!-- Zoom Indicator -->
            <div class="zoom-indicator">
                <i class="pi pi-search-plus"></i>
                <span>Click to zoom</span>
            </div>
        </div>

        <!-- Thumbnails -->
        <div class="thumbnail-list">
            <div v-for="(img, index) in imageList" :key="index"
                :class="['thumbnail', { active: index === activeIndex }]" @click="activeIndex = index">
                <img :src="basePath + img" alt="Thumbnail" loading="lazy" @error="onThumbnailError" />
            </div>
        </div>
    </div>

    <!-- Image Zoom Modal -->
    <ImageZoomModal
        v-model:visible="showZoomModal"
        :images="imageList"
        :initialIndex="activeIndex"
        :title="item.ProductTitle || 'Product Image'"
    />
</template>

<script setup>
import { ref, computed, defineProps } from "vue";
import ImageZoomModal from "./ImageZoomModal.vue";

const props = defineProps({
    item: { type: Object, required: true },
});

const basePath = "/images/thumbnails/";
const fullSizePath = "/images/product_images/Airstaffs/";
const defaultImage =
    "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4=";

const activeIndex = ref(0);
const showZoomModal = ref(false);

const imageList = computed(() =>
    Object.keys(props.item || {})
        .filter((key) => key.startsWith("img") && props.item[key])
        .map((key) => props.item[key])
);

const activeImageUrl = computed(() =>
    imageList.value[activeIndex.value]
        ? basePath + imageList.value[activeIndex.value]
        : defaultImage
);

const zoomImagePath = computed(() => {
    if (!imageList.value[activeIndex.value]) return defaultImage;
    // Use full-size image for zoom, not thumbnail
    return fullSizePath + imageList.value[activeIndex.value];
});

const openZoomModal = () => {
    showZoomModal.value = true;
    console.log(activeIndex, "activeIndex")
};

const onImageErrorMain = (event) => (event.target.src = defaultImage);
const onThumbnailError = (event) => (event.target.src = defaultImage);
</script>

<style scoped>
.image-section {
    background-color: #fffefc;
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 16px;
}

.main-image {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
    background-color: #fff;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
}

.main-image img {
    width: 100%;
    max-width: 400px;
    height: auto;
    object-fit: contain;
    display: block;
    transition: transform 0.3s ease;
}

.main-image:hover img {
    transform: scale(1.05);
}

/* Zoom Indicator */
.zoom-indicator {
    position: absolute;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.75);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
    backdrop-filter: blur(4px);
    z-index: 5;
}

.main-image:hover .zoom-indicator {
    opacity: 1;
}

.zoom-indicator i {
    font-size: 1rem;
}

.thumbnail-list {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 4px;
    scrollbar-width: thin;
    scrollbar-color: #ccc transparent;
}

.thumbnail-list::-webkit-scrollbar {
    height: 6px;
    width: 6px;
}

.thumbnail-list::-webkit-scrollbar-track {
    background: transparent;
}

.thumbnail-list::-webkit-scrollbar-thumb {
    background-color: #ccc;
    border-radius: 3px;
}

.thumbnail-list::-webkit-scrollbar-thumb:hover {
    background-color: #999;
}

.thumbnail {
    flex-shrink: 0;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    overflow: hidden;
    background-color: #fff;
}

.thumbnail:hover {
    border-color: #999;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.thumbnail.active {
    border-color: rgb(58, 106, 250);
    box-shadow: 0 0 0 1px rgb(58, 106, 250);
}

.thumbnail img {
    width: 56px;
    height: 55px;
    object-fit: cover;
    display: block;
    transition: transform 0.2s ease;
}

.thumbnail:hover img {
    transform: scale(1.05);
}

/* Large screens - thumbnails on the right */
@media (min-width: 769px) {
    .image-section {
        flex-direction: row;
        align-items: flex-start;
    }

    .main-image {
        flex: 1;
        max-width: 400px;
        min-height: 300px;
    }

    .thumbnail-list {
        flex-direction: column;
        overflow-x: hidden;
        overflow-y: auto;
        max-height: 400px;
        padding: 4px;
    }
}

/* Small screens - thumbnails below */
@media (max-width: 768px) {
    .image-section {
        padding: 12px;
        gap: 12px;
    }

    .main-image {
        min-height: 250px;
    }

    .main-image img {
        max-width: 100%;
    }

    .thumbnail-list {
        justify-content: center;
    }

    .thumbnail img {
        width: 56px;
        height: 55px;
    }

    .zoom-indicator {
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
    }
    
    .zoom-indicator i {
        font-size: 0.875rem;
    }
}
</style>