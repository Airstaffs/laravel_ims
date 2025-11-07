<template>
    <div :class="[
        size === 'small' ? 'product-image-container-small' : 'product-image-container-large'
    ]" @click="openImageModal(data)" style="flex-shrink: 0;">
        <img :src="'/images/thumbnails/' + data.img1" :alt="data.ProductTitle || 'Product'"
            class="product-thumbnail clickable-image" @error="handleImageError($event)" />
        <div v-if="countAdditionalImages(data) > 0" class="image-count-badge">
            +{{ countAdditionalImages(data) }}
        </div>
    </div>
</template>

<script setup>
defineProps({
    data: Object,
    openImageModal: Function,
    handleImageError: Function,
    countAdditionalImages: Function,
    size: {
        type: String,
        default: 'large'
    }
});
</script>

<style scoped>
.product-image-container-small {
    position: relative;
    width: 40px;
    min-width: 40px;
    max-width: 40px;
}

.product-image-container-large {
    position: relative;
    width: 60px;
    min-width: 60px;
    max-width: 60px;
}

.image-count-badge {
    position: absolute;
    bottom: -5px;
    right: -5px;
    background-color: #007bff;
    color: #fff;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
}
</style>
