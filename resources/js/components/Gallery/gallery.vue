<template>
    <div class="image-section" v-if="imageList.length">
        <!-- Main Image -->
        <div class="main-image" @click="openEditModalZoom">
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
                :class="['thumbnail', { active: index === activeIndex }]" 
                @click="selectThumbnail(index)">
                <img :src="basePath + img" alt="Thumbnail" loading="lazy" @error="onThumbnailError" />
            </div>
        </div>
    </div>

    <!-- Zoom Modal -->
    <ZoomImageModal 
        v-model:visible="showEditZoomModal"
        :images="imageWithPathList"
        :initialIndex="activeIndex"
        :title="item?.ProductTitle || 'Image'"
    />
</template>

<script setup>
import { ref, computed, defineProps } from "vue";
import { DEFAULT_IMAGE } from "../../constant";
import ZoomImageModal from "../ZoomImageModal/ZoomImageModal.vue";

const props = defineProps({
    item: { type: Object, required: true },
});

const basePath = "/images/thumbnails/";
const fullSizePath = "/images/product_images/Airstaffs/";
const defaultImage = DEFAULT_IMAGE;

const activeIndex = ref(0);
const showEditZoomModal = ref(false);
const editZoomImagePath = ref("");

const imageList = computed(() =>
    Object.keys(props.item || {})
        .filter((key) => key.startsWith("img") && props.item[key])
        .map((key) => props.item[key])
);

const imageWithPathList = computed(() => {
    return imageList.value.map((image) => {
        return image.startsWith('/') ? image : basePath+image
    })
})

const activeImageUrl = computed(() =>
    imageList.value[activeIndex.value]
        ? basePath + imageList.value[activeIndex.value]
        : defaultImage
);

const onImageErrorMain = (event) => (event.target.src = defaultImage);
const onThumbnailError = (event) => (event.target.src = defaultImage);

const openEditModalZoom = () => {
    if (activeImageUrl.value && imageList.value[activeIndex.value]) {
        // Use full-size image path instead of thumbnail
        const imageName = imageList.value[activeIndex.value];
        const fullPath = fullSizePath + imageName;
        // Remove cache buster for clean path
        const cleanPath = fullPath.split('?')[0];
        editZoomImagePath.value = activeImageUrl.value;
        showEditZoomModal.value = true;
        console.log(imageList.value, "indexindexsss")
    }
};

const selectThumbnail = (index) => {
    activeIndex.value = index;
};
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
    height: 400px;
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
    position: relative;
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
    height: 56px;
    object-fit: cover;
    display: block;
    transition: transform 0.2s ease;
}

.thumbnail:hover img {
    transform: scale(1.05);
}

/* Thumbnail Zoom Icon */
.thumbnail-zoom-icon {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.thumbnail:hover .thumbnail-zoom-icon {
    opacity: 1;
}

.thumbnail-zoom-icon i {
    color: white;
    font-size: 1.25rem;
}

/* Large screens - thumbnails on the right */
@media (min-width: 1024px) {
    .image-section {
        flex-direction: row;
        align-items: flex-start;
    }

    .main-image {
        flex: 1;
        max-width: 400px;
    }

    .thumbnail-list {
        flex-direction: column;
        overflow-x: hidden;
        overflow-y: auto;
        max-height: 400px;
        padding: 4px;
    }
}

/* Mobile and Tablet - thumbnails below */
@media (max-width: 1023px) {
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
        height: 56px;
    }

    .zoom-indicator {
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
    }
    
    .zoom-indicator i {
        font-size: 0.875rem;
    }
    
    .thumbnail-zoom-icon {
        display: none;
    }
}
</style>