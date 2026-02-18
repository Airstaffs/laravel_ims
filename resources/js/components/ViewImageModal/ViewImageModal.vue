<template>
    <Dialog v-model:visible="isVisible" modal :header="title" :style="{ width: '900px', maxWidth: '95vw' }"
        :breakpoints="{ '960px': '90vw', '640px': '100vw' }" :closable="true" @update:visible="handleClose" :pt="{
            root: { class: 'mobile-fullscreen-dialog' },
            header: { class: 'modal-header-custom' },
            content: { class: 'modal-content-custom' },
            mask: { class: 'modal-mask-custom' }
        }">
        <div class="image-modal-container">
            <!-- Main Image Display -->
            <div class="main-image-section">
                <Button v-if="imageList.length > 1" icon="pi pi-chevron-left" text rounded class="nav-button nav-prev"
                    @click="prevImage" aria-label="Previous image" />

                <div class="image-display-area" @click="openZoomModal">
                    <img :src="activeImageUrl" :alt="title" class="main-product-image" @error="handleMainImageError" />
                    
                    <!-- Zoom Indicator -->
                    <div class="zoom-indicator">
                        <i class="pi pi-search-plus"></i>
                        <span>Click to zoom</span>
                    </div>
                </div>

                <Button v-if="imageList.length > 1" icon="pi pi-chevron-right" text rounded class="nav-button nav-next"
                    @click="nextImage" aria-label="Next image" />
            </div>

            <!-- Image Counter -->
            <div class="image-counter-display" v-if="imageList.length > 1">
                {{ activeIndex + 1 }} / {{ imageList.length }}
            </div>

            <!-- Thumbnail Strip -->
            <div class="thumbnail-strip" v-if="imageList.length > 1">
                <div v-for="(img, index) in imageList" :key="index"
                    :class="['thumbnail-item', { active: index === activeIndex }]" @click="activeIndex = index"
                    @mouseenter="activeIndex = index">
                    <img :src="basePath + img" :alt="`Thumbnail ${index + 1}`" class="thumbnail-image"
                        @error="handleThumbnailImageError" />
                </div>
            </div>
        </div>
    </Dialog>

    <!-- Image Zoom Modal -->
    <ZoomImageModal
        v-model:visible="showZoomModal"
        :images="imagesWithPath"
        :initialIndex="activeIndex"
        :title="title"
    />
</template>

<script>
import { Dialog, Button } from 'primevue';
import ZoomImageModal from '../ZoomImageModal/ZoomImageModal.vue';

export default {
    name: 'ImageModal',
    components: {
        Dialog,
        Button,
        ZoomImageModal
    },
    props: {
        visible: {
            type: Boolean,
            required: true
        },
        title: {
            type: String,
            required: true
        },
        imageList: {
            type: Array,
            required: true
        },
        basePath: {
            type: String,
            required: true
        },
        onImageErrorMain: {
            type: Function,
            required: true
        },
        onThumbnailError: {
            type: Function,
            required: true
        }
    },
    emits: ['update:visible', 'close'],
    data() {
        return {
            activeIndex: 0,
            isVisible: this.visible,
            showZoomModal: false,
            zoomImagePath: ''
        };
    },
    computed: {
        activeImageUrl() {
            if (!this.imageList.length) return '';
            return this.getImageUrl(this.imageList[this.activeIndex]);
        },
        imagesWithPath() {
            return this.imageList.map((image) => {
                return image.startsWith('/') ? image : this.basePath+image
            })
        }
    },
    watch: {
        visible(newVal) {
            this.isVisible = newVal;
            if (newVal) {
                this.activeIndex = 0;
            }
        },
        isVisible(newVal) {
            this.$emit('update:visible', newVal);
            if (!newVal) {
                this.$emit('close');
            }
        }
    },
    mounted() {
        document.addEventListener('keydown', this.handleKeydown);
    },
    beforeUnmount() {
        document.removeEventListener('keydown', this.handleKeydown);
    },
    methods: {
        getImageUrl(img) {
            if (typeof img === 'string') {
                return img.startsWith('http') ? img : this.basePath + img;
            }
            return '';
        },
        openZoomModal() {
            // Get the full-size image path for zoom
            const currentImage = this.imageList[this.activeIndex];
            if (currentImage) {
                // Remove any query parameters for clean path
                const cleanPath = this.getImageUrl(currentImage).split('?')[0];
                this.zoomImagePath = cleanPath;
                this.showZoomModal = true;
            }
        },
        selectThumbnail(index) {
            this.activeIndex = index;
            // Also open zoom when clicking thumbnail
            this.openZoomModal();
        },
        prevImage() {
            this.activeIndex = this.activeIndex > 0 ? this.activeIndex - 1 : this.imageList.length - 1;
        },
        nextImage() {
            this.activeIndex = this.activeIndex < this.imageList.length - 1 ? this.activeIndex + 1 : 0;
        },
        handleKeydown(event) {
            if (!this.isVisible) return;

            if (event.key === 'ArrowLeft' && this.imageList.length > 1) {
                this.prevImage();
            } else if (event.key === 'ArrowRight' && this.imageList.length > 1) {
                this.nextImage();
            }
        },
        handleClose() {
            this.isVisible = false;
        },
        handleMainImageError(event) {
            this.onImageErrorMain(event);
        },
        handleThumbnailImageError(event) {
            this.onThumbnailError(event);
        }
    }
};
</script>

<style scoped>
/* Modal Customization */
:deep(.modal-header-custom) {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    background: #ffffff;
}

:deep(.modal-header-custom .p-dialog-title) {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
}

:deep(.modal-content-custom) {
    padding: 0;
    background: #f9fafb;
    overflow: hidden;
}

.image-modal-container {
    display: flex;
    flex-direction: column;
    background: #ffffff;
}

/* Main Image Section */
.main-image-section {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    padding: 1rem;
    min-height: 500px;
}

.image-display-area {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    max-height: 500px;
    cursor: pointer;
    position: relative;
}

.main-product-image {
    max-width: 100%;
    max-height: 500px;
    object-fit: contain;
    transition: transform 0.2s ease;
}

.image-display-area:hover .main-product-image {
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

.image-display-area:hover .zoom-indicator {
    opacity: 1;
}

.zoom-indicator i {
    font-size: 1rem;
}

/* Navigation Buttons */
.nav-button {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
    width: 2.5rem;
    height: 2.5rem;
    background: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid #e5e7eb !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}

.nav-button:hover {
    background: #ffffff !important;
    border-color: #d1d5db !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.nav-prev {
    left: 1.5rem;
}

.nav-next {
    right: 1.5rem;
}

/* Image Counter */
.image-counter-display {
    text-align: center;
    padding: 1rem;
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
    background: #ffffff;
    border-top: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
}

/* Thumbnail Strip */
.thumbnail-strip {
    display: flex;
    gap: 0.75rem;
    padding: 1.25rem;
    background: #ffffff;
    overflow-x: auto;
    justify-content: center;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db #f3f4f6;
}

.thumbnail-strip::-webkit-scrollbar {
    height: 8px;
}

.thumbnail-strip::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 4px;
}

.thumbnail-strip::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.thumbnail-strip::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

.thumbnail-item {
    position: relative;
    flex-shrink: 0;
    width: 80px;
    height: 80px;
    border: 2px solid #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #ffffff;
}

.thumbnail-item:hover {
    border-color: #9ca3af;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.thumbnail-item.active {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.thumbnail-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
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

.thumbnail-item:hover .thumbnail-zoom-icon {
    opacity: 1;
}

.thumbnail-zoom-icon i {
    color: white;
    font-size: 1.25rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    :deep(.mobile-fullscreen-dialog) {
        width: 100vw !important;
        height: 100vh !important;
        max-height: 100vh !important;
        margin: 0 !important;
        border-radius: 0 !important;
    }

    :deep(.modal-header-custom) {
        border-radius: 0 !important;
    }

    :deep(.modal-content-custom) {
        height: calc(100vh - 60px) !important;
        border-radius: 0 !important;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .image-modal-container {
        height: 100%;
        width: 100%;
        display: flex;
        flex-direction: column;
    }

    .main-image-section {
        min-height: 350px;
        flex: 1;
        padding: 1rem;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .image-display-area {
        max-height: none;
        height: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .main-product-image {
        max-height: 100%;
        max-width: 100%;
    }

    .nav-button {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50% !important;
    }

    .nav-prev {
        left: 0.75rem;
    }

    .nav-next {
        right: 0.75rem;
    }

    .thumbnail-strip {
        padding: 1rem;
        gap: 0.5rem;
        justify-content: center;
        flex-shrink: 0;
    }

    .thumbnail-item {
        width: 60px;
        height: 60px;
    }

    .image-counter-display {
        flex-shrink: 0;
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

@media (max-width: 480px) {
    :deep(.mobile-fullscreen-dialog) {
        width: 100vw !important;
        height: 100vh !important;
        max-height: 100vh !important;
        margin: 0 !important;
        border-radius: 0 !important;
    }

    :deep(.modal-header-custom) {
        padding: 1rem 1.25rem;
        border-radius: 0 !important;
    }

    :deep(.modal-header-custom .p-dialog-title) {
        font-size: 1rem;
    }

    :deep(.modal-content-custom) {
        height: calc(100vh - 56px) !important;
        border-radius: 0 !important;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .main-image-section {
        min-height: 280px;
        padding: 1rem;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .image-display-area {
        max-height: none;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .main-product-image {
        max-height: 100%;
        max-width: 100%;
    }

    .nav-button {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50% !important;
    }

    .nav-prev {
        left: 0.5rem;
    }

    .nav-next {
        right: 0.5rem;
    }

    .thumbnail-strip {
        justify-content: center;
    }

    .thumbnail-item {
        width: 50px;
        height: 50px;
    }

    .image-counter-display {
        padding: 0.75rem;
        font-size: 0.8125rem;
    }
}
</style>