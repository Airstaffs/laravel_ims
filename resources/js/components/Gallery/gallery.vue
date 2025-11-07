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

const props = defineProps({
    item: { type: Object, required: true },
});

const basePath = "/images/thumbnails/";
const defaultImage =
    "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4=";

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
