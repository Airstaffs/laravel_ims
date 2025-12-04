<template>
    <div class="image-section" v-if="imageList.length">
        <!-- Main Image -->
        <div class="main-image">
            <img :src="activeImageUrl" alt="Main Product Image" loading="lazy" @error="onImageErrorMain" />
        </div>

        <!-- Thumbnails -->
        <div class="thumbnail-list">
            <div v-for="(img, index) in imageList" :key="index"
                :class="['thumbnail', { active: index === activeIndex }]" @click="activeIndex = index">
                <img :src="basePath + img" alt="Thumbnail" loading="lazy" @error="onThumbnailError" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, defineProps } from "vue";
import { DEFAULT_IMAGE } from "../../constant";

const props = defineProps({
    item: { type: Object, required: true },
});

const basePath = "/images/thumbnails/";
const defaultImage = DEFAULT_IMAGE

const activeIndex = ref(0);

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
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
    background-color: #fff;
    border-radius: 8px;
    overflow: hidden;
}

.main-image img {
    width: 100%;
    max-width: 400px;
    height: 400px;
    object-fit: contain;
    display: block;
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
    height: 56px;
    object-fit: cover;
    display: block;
    transition: transform 0.2s ease;
}

.thumbnail:hover img {
    transform: scale(1.05);
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
}
</style>