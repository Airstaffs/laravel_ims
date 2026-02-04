<template>
    <Dialog
        :visible="showImageModal"
        :header="ProductTitle"
        modal
        closable
        :draggable="false"
        :style="{ width: '90vw', maxWidth: '1200px' }"
        :contentStyle="{ padding: '0' }"
        @update:visible="handleVisibilityChange"
        :pt="{
            root: { class: 'mobile-fullscreen-dialog' },
        }"
    >
        <div class="image-gallery-content">
            <!-- TABS -->
            <div class="tabs-container">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    :class="[
                        'tab-btn',
                        { 'active-tab': activeTab === tab.key },
                    ]"
                    :disabled="tab.images.length === 0"
                    @click="switchTab(tab.key)"
                >
                    {{ tab.label }} ({{ tab.images.length }})
                </button>
            </div>

            <!-- RENDER BOTH TABS (hidden via CSS) -->
            <div
                v-for="tab in tabs"
                :key="tab.key"
                :class="[
                    'tab-content',
                    { 'tab-active': activeTab === tab.key },
                ]"
            >
                <!-- EMPTY STATE -->
                <div v-if="tab.images.length === 0" class="no-images">
                    <i class="pi pi-image" style="font-size: 3rem"></i>
                    <p>No images available in this category</p>
                </div>

                <!-- GALLERY -->
                <div v-else class="gallery-container">
                    <!-- MAIN IMAGE + NAV -->
                    <div class="main-image-wrapper">
                        <Button
                            v-if="tab.images.length > 1"
                            icon="pi pi-chevron-left"
                            class="nav-btn nav-btn-prev"
                            rounded
                            text
                            @click="changeImage(tab.key, -1)"
                        />

                        <img
                            :src="tab.images[tabIndices[tab.key]]"
                            alt="Image"
                            class="main-image"
                            @error="handleImageError"
                        />

                        <Button
                            v-if="tab.images.length > 1"
                            icon="pi pi-chevron-right"
                            class="nav-btn nav-btn-next"
                            rounded
                            text
                            @click="changeImage(tab.key, 1)"
                        />
                    </div>

                    <!-- COUNTER -->
                    <div class="image-counter">
                        <Tag
                            :value="`${tabIndices[tab.key] + 1} / ${
                                tab.images.length
                            }`"
                        />
                    </div>

                    <!-- THUMBNAILS -->
                    <div v-if="tab.images.length > 1" class="thumbnails">
                        <div
                            v-for="(img, i) in tab.images"
                            :key="i"
                            v-show="img"
                            :class="[
                                'thumbnail',
                                {
                                    'thumbnail-active':
                                        i === tabIndices[tab.key],
                                },
                            ]"
                            @click="tabIndices[tab.key] = i"
                        >
                            <img
                                :src="img"
                                @error="handleImageError"
                                loading="lazy"
                                :alt="img"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch, reactive } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import Tag from "primevue/tag";

const props = defineProps({
    showImageModal: Boolean,
    ProductTitle: String,
    regularImages: { type: Array, default: () => [] },
    capturedImages: { type: Array, default: () => [] },
    handleImageError: Function,
    closeImageModal: Function,
});

const activeTab = ref("regular");
const tabIndices = reactive({
    regular: 0,
    captured: 0,
});

console.log(props.capturedImages, "capturedImages", props.regularImages);

const tabs = computed(() => [
    {
        key: "regular",
        label: "Product Images",
        images: (props.regularImages || []).filter(
            (img) => img != null && img !== "",
        ),
    },
    {
        key: "captured",
        label: "Captured Images",
        images: (props.capturedImages || []).filter(
            (img) => img != null && img !== "",
        ),
    },
]);

// Preload adjacent images for smoother navigation
const preloadImages = (tabKey) => {
    const tab = tabs.value.find((t) => t.key === tabKey);
    if (!tab || tab.images.length <= 1) return;

    const currentIndex = tabIndices[tabKey];
    const images = tab.images;

    // Preload next and previous images
    const prevIndex = (currentIndex - 1 + images.length) % images.length;
    const nextIndex = (currentIndex + 1) % images.length;

    [images[prevIndex], images[nextIndex]].forEach((src) => {
        if (src) {
            const img = new Image();
            img.src = src;
        }
    });
};

const switchTab = (tab) => {
    activeTab.value = tab;
    preloadImages(tab);
};

const changeImage = (tabKey, dir) => {
    const images = tabs.value.find((t) => t.key === tabKey)?.images || [];
    const total = images.length;
    tabIndices[tabKey] = (tabIndices[tabKey] + dir + total) % total;
    preloadImages(tabKey);
};

const handleVisibilityChange = (v) => {
    if (!v) props.closeImageModal();
};

watch(
    () => props.showImageModal,
    (v) => {
        if (v) {
            activeTab.value = "regular";
            tabIndices.regular = 0;
            tabIndices.captured = 0;

            // Preload first images when modal opens
            setTimeout(() => {
                preloadImages("regular");
                if (
                    tabs.value.find((t) => t.key === "captured")?.images
                        .length > 0
                ) {
                    preloadImages("captured");
                }
            }, 100);
        }
    },
);
</script>

<style scoped>
/* ===== GENERAL LAYOUT ===== */
.image-gallery-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
    padding: 0.5rem;
    width: 100%;
    box-sizing: border-box;
}

/* ===== TAB CONTENT ===== */
.tab-content {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    visibility: hidden;
    height: 0;
    overflow: hidden;
    position: absolute;
}

.tab-content.tab-active {
    visibility: visible;
    height: auto;
    position: relative;
}

/* ===== TABS ===== */
.tabs-container {
    display: flex;
    justify-content: center;
    gap: 1rem;
    width: 100%;
    padding-bottom: 0;
    border-bottom: 2px solid var(--surface-border);
}

.tab-btn {
    font-weight: 600;
    font-size: 1rem;
    padding: 0.75rem 1rem;
    color: var(--text-color-secondary);
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    margin-bottom: -2px;
}

.tab-btn:hover:not(:disabled) {
    color: #22c55e;
}

.tab-btn.active-tab {
    color: #22c55e;
    border-bottom-color: #22c55e;
}

.tab-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* ===== GALLERY CONTENT ===== */
.gallery-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    max-width: 900px;
    gap: 0.5rem;
}

.main-image-wrapper {
    position: relative;
    width: 100%;
    max-width: 900px;
    background: var(--surface-ground);
    border-radius: var(--border-radius);
    min-height: 500px;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1rem;
}

.main-image {
    width: 100%;
    height: auto;
    max-height: 500px;
    object-fit: contain;
}

/* ===== NAV ARROWS ===== */
.nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.92) !important;
    padding: 0.5rem;
    border-radius: 50%;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
}

.nav-btn:hover {
    background: rgba(255, 255, 255, 1) !important;
}

.nav-btn-prev {
    left: -2rem;
}

.nav-btn-next {
    right: -2rem;
}

/* ===== COUNTER ===== */
.image-counter {
    display: flex;
    justify-content: center;
    width: 100%;
}

/* ===== THUMBNAILS ===== */
.thumbnails {
    display: flex;
    justify-content: center;
    gap: 1rem;
    width: 100%;
    max-width: 900px;
    overflow-x: auto;
    padding: 0.5rem 0.25rem;
    scrollbar-width: thin;
}

.thumbnails::-webkit-scrollbar {
    height: 8px;
}

.thumbnails::-webkit-scrollbar-track {
    background: var(--surface-ground);
    border-radius: 4px;
}

.thumbnails::-webkit-scrollbar-thumb {
    background: var(--surface-border);
    border-radius: 4px;
}

.thumbnails::-webkit-scrollbar-thumb:hover {
    background: #22c55e;
}

.thumbnail {
    width: 60px;
    height: 60px;
    border: 2px solid var(--surface-border);
    border-radius: var(--border-radius);
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
}

.thumbnail:hover {
    border-color: #22c55e;
    transform: scale(1.05);
}

.thumbnail-active {
    border-color: #22c55e;
    box-shadow: 0 0 0 2px #22c55e;
    transform: scale(1.05);
}

.thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* ===== NO IMAGES STATE ===== */
.no-images {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    gap: 1rem;
    color: var(--text-color-secondary);
    min-height: 400px;
}

/* ===== MOBILE ===== */
@media (max-width: 768px) {
    .tabs-container {
        gap: 1rem;
    }

    .tab-btn {
        font-size: 0.9rem;
        padding: 0.5rem 0.75rem;
    }

    .main-image-wrapper {
        min-height: 300px;
    }

    .nav-btn-prev {
        left: 0.5rem;
    }

    .nav-btn-next {
        right: 0.5rem;
    }

    .thumbnail {
        width: 60px;
        height: 60px;
    }
}
</style>
