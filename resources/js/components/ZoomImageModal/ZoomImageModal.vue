<template>
  <Teleport to="body">
    <Transition name="zoom-overlay">
      <div 
        v-if="isVisible" 
        class="zoom-overlay"
        @click.self="closeModal"
      >
        <!-- Close Button -->
        <Button
          icon="pi pi-times"
          class="close-btn"
          @click="closeModal"
          rounded
          text
          severity="secondary"
        />

        <!-- Previous Button -->
        <Button
          v-if="images.length > 1"
          icon="pi pi-chevron-left"
          class="nav-btn nav-prev"
          @click="previousImage"
          rounded
          text
          severity="secondary"
        />

        <!-- Next Button -->
        <Button
          v-if="images.length > 1"
          icon="pi pi-chevron-right"
          class="nav-btn nav-next"
          @click="nextImage"
          rounded
          text
          severity="secondary"
        />

        <!-- Image Container -->
        <div 
          class="zoom-content"
          ref="imageWrapper"
          @wheel.prevent="handleWheel"
          @touchstart="handleTouchStart"
          @touchmove="handleTouchMove"
          @touchend="handleTouchEnd"
        >
          <img
            :src="currentImage"
            :style="imageStyle"
            alt="Preview"
            class="zoom-image"
            @mousedown="startDrag"
            @click="handleImageClick"
            :key="currentIndex"
          />
        </div>

        <!-- Zoom Controls -->
        <div class="zoom-controls">
          <Button
            icon="pi pi-search-minus"
            @click="zoomOut"
            :disabled="scale <= minScale"
            severity="secondary"
            rounded
            size="small"
          />
          <span class="zoom-level">{{ Math.round(scale * 100) }}%</span>
          <Button
            icon="pi pi-search-plus"
            @click="zoomIn"
            :disabled="scale >= maxScale"
            severity="secondary"
            rounded
            size="small"
          />

          <!-- Divider -->
          <span class="controls-divider" />

          <!-- Rotate Left -->
          <Button
            icon="pi pi-undo"
            @click="rotateLeft"
            severity="secondary"
            rounded
            size="small"
            v-tooltip.top="'Rotate Left (←)'"
          />
          <span class="zoom-level">{{ rotation }}°</span>
          <!-- Rotate Right -->
          <Button
            icon="pi pi-refresh"
            @click="rotateRight"
            severity="secondary"
            rounded
            size="small"
            v-tooltip.top="'Rotate Right (→)'"
          />

          <!-- Divider -->
          <span class="controls-divider" />

          <Button
            icon="pi pi-arrows-alt"
            @click="resetTransform"
            severity="secondary"
            rounded
            size="small"
            v-tooltip.top="'Reset All'"
          />
        </div>

        <!-- Image Counter -->
        <div v-if="images.length > 1" class="image-counter">
          {{ currentIndex + 1 }} / {{ images.length }}
        </div>

        <!-- Title (Optional) -->
        <div v-if="title" class="zoom-title">
          {{ title }}
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import Button from 'primevue/button';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  images: {
    type: [Array, String],
    required: true
  },
  initialIndex: {
    type: Number,
    default: 0
  },
  title: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['update:visible', 'image-changed']);

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
});

const imageArray = computed(() => {
  if (Array.isArray(props.images)) return props.images;
  return props.images ? [props.images] : [];
});

const currentIndex = ref(0);
const currentImage = computed(() => imageArray.value[currentIndex.value] || '');

// Transform state
const scale = ref(1);
const translateX = ref(0);
const translateY = ref(0);
const rotation = ref(0); // degrees, multiples of 90

const isDragging = ref(false);
const dragStartX = ref(0);
const dragStartY = ref(0);
const imageWrapper = ref(null);
const wasDragging = ref(false);

// Touch support
const lastTouchDistance = ref(0);
const lastTap = ref(0);
const wasPinching = ref(false);


const minScale = 0.5;
const maxScale = 5;
const zoomStep = 0.25;

const imageStyle = computed(() => ({
  transform: `translate(${translateX.value}px, ${translateY.value}px) scale(${scale.value}) rotate(${rotation.value}deg)`,
  cursor: scale.value > 1 ? (isDragging.value ? 'grabbing' : 'grab') : 'zoom-in',
  transition: isDragging.value ? 'none' : 'transform 0.2s ease'
}));

// Navigation
const nextImage = () => {
  currentIndex.value = currentIndex.value < imageArray.value.length - 1
    ? currentIndex.value + 1
    : 0;
  resetTransform();
  emit('image-changed', currentIndex.value);
};

const previousImage = () => {
  currentIndex.value = currentIndex.value > 0
    ? currentIndex.value - 1
    : imageArray.value.length - 1;
  resetTransform();
  emit('image-changed', currentIndex.value);
};

// Zoom
const zoomIn = () => { scale.value = Math.min(scale.value + zoomStep, maxScale); };
const zoomOut = () => {
  scale.value = Math.max(scale.value - zoomStep, minScale);
  if (scale.value === 1) { translateX.value = 0; translateY.value = 0; }
};

// Rotation — snaps to 90° increments
const rotateLeft  = () => { rotation.value = (rotation.value - 90 + 360) % 360; };
const rotateRight = () => { rotation.value = (rotation.value + 90) % 360; };

// Reset everything
const resetTransform = () => {
  scale.value = 1;
  translateX.value = 0;
  translateY.value = 0;
  rotation.value = 0;
};

const closeModal = () => {
  resetTransform();
  isVisible.value = false;
};

const handleImageClick = () => {
  if (wasDragging.value) return;
  if (scale.value === 1) zoomIn();
};

const handleWheel = (event) => {
  const delta = event.deltaY > 0 ? -zoomStep : zoomStep;
  scale.value = Math.max(minScale, Math.min(maxScale, scale.value + delta));
  if (scale.value === 1) { translateX.value = 0; translateY.value = 0; }
};

// Mouse drag
const startDrag = (event) => {
  if (scale.value > 1) {
    isDragging.value = true;
    wasDragging.value = false;
    dragStartX.value = event.clientX - translateX.value;
    dragStartY.value = event.clientY - translateY.value;
    event.preventDefault();
  }
};

const onDrag = (event) => {
  if (isDragging.value) {
    wasDragging.value = true;
    translateX.value = event.clientX - dragStartX.value;
    translateY.value = event.clientY - dragStartY.value;
  }
};

const stopDrag = () => {
  setTimeout(() => { wasDragging.value = false; }, 100);
  isDragging.value = false;
};

// In handleTouchStart, set it when 2 fingers detected:
const handleTouchStart = (event) => {
  if (event.touches.length === 1) {
    wasPinching.value = false; // reset on single touch
    const touch = event.touches[0];
    if (scale.value > 1) {
      isDragging.value = true;
      wasDragging.value = false;
      dragStartX.value = touch.clientX - translateX.value;
      dragStartY.value = touch.clientY - translateY.value;
    }
  } else if (event.touches.length === 2) {
    event.preventDefault();
    isDragging.value = false;
    wasPinching.value = true; // mark as pinch gesture
    const [t1, t2] = event.touches;
    lastTouchDistance.value = Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY);
  }
};

const handleTouchMove = (event) => {
  if (event.touches.length === 1 && isDragging.value && scale.value > 1) {
    event.preventDefault();
    wasDragging.value = true;
    const touch = event.touches[0];
    translateX.value = touch.clientX - dragStartX.value;
    translateY.value = touch.clientY - dragStartY.value;
  } else if (event.touches.length === 2) {
    event.preventDefault();
    const [t1, t2] = event.touches;
    const distance = Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY);
    if (lastTouchDistance.value > 0) {
      const scaleDelta = (distance - lastTouchDistance.value) * 0.01;
      scale.value = Math.max(minScale, Math.min(maxScale, scale.value + scaleDelta));
      if (scale.value === 1) { translateX.value = 0; translateY.value = 0; }
    }
    lastTouchDistance.value = distance;
  }
};

// In handleTouchEnd, skip double-tap logic if it was a pinch:
const handleTouchEnd = (event) => {
  if (wasPinching.value) {
    // Pinch ended — just clean up, don't run double-tap logic
    isDragging.value = false;
    lastTouchDistance.value = 0;
    // Only clear wasPinching when ALL fingers are lifted
    if (event.touches.length === 0) {
      wasPinching.value = false;
      lastTap.value = 0; // prevent a stale tap time from triggering double-tap
    }
    return;
  }

  const now = Date.now();
  const timeSinceLastTap = now - lastTap.value;
  if (timeSinceLastTap < 300 && timeSinceLastTap > 0 && !wasDragging.value) {
    event.preventDefault();
    scale.value === 1 ? (scale.value = 2) : resetTransform();
  }
  lastTap.value = now;
  setTimeout(() => { wasDragging.value = false; }, 100);
  isDragging.value = false;
  lastTouchDistance.value = 0;
};

const handleKeyboard = (event) => {
  switch (event.key) {
    case 'Escape':      closeModal();     break;
    case '+': case '=': zoomIn();         break;
    case '-':           zoomOut();        break;
    case '0':           resetTransform(); break;
    case 'ArrowRight':  nextImage();      break;
    case 'ArrowLeft':   previousImage();  break;
    case 'ArrowUp':     rotateLeft();     break; // ← rotate left
    case 'ArrowDown':   rotateRight();    break; // → rotate right
    case 'r': case 'R': rotateRight();    break; // R key shortcut
  }
};

watch(() => props.visible, (newVal) => {
  if (newVal) {
    currentIndex.value = props.initialIndex;
    resetTransform();
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

watch(currentIndex, () => resetTransform());

onMounted(() => {
  document.addEventListener('mousemove', onDrag);
  document.addEventListener('mouseup', stopDrag);
  document.addEventListener('keydown', handleKeyboard);
});

onUnmounted(() => {
  document.removeEventListener('mousemove', onDrag);
  document.removeEventListener('mouseup', stopDrag);
  document.removeEventListener('keydown', handleKeyboard);
  document.body.style.overflow = '';
});
</script>

<style scoped>
.zoom-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  width: 100vw; height: 100vh;
  background-color: rgba(0, 0, 0, 0.98);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-btn {
  position: absolute;
  top: 1.5rem; right: 1.5rem;
  z-index: 100001;
  background: rgba(255, 255, 255, 0.1) !important;
  color: white !important;
  width: 3rem; height: 3rem;
  font-size: 1.5rem;
}
.close-btn:hover { background: rgba(255, 255, 255, 0.2) !important; }

.nav-btn {
  position: absolute;
  top: 50%; transform: translateY(-50%);
  z-index: 100001;
  background: rgba(255, 255, 255, 0.1) !important;
  color: white !important;
  width: 3.5rem; height: 3.5rem;
  font-size: 1.75rem;
  transition: all 0.2s;
}
.nav-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.2) !important;
  transform: translateY(-50%) scale(1.1);
}
.nav-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.nav-prev { left: 1.5rem; }
.nav-next { right: 1.5rem; }

.zoom-content {
  position: absolute;
  top: 0; left: 0;
  width: 100vw; height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  touch-action: none;
}

.zoom-image {
  max-width: 100%; max-height: 100%;
  object-fit: contain;
  user-select: none;
  -webkit-user-drag: none;
  -webkit-user-select: none;
  -webkit-touch-callout: none;
}

/* Controls Bar */
.zoom-controls {
  position: absolute;
  bottom: 2rem; left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 12px;
  background-color: rgba(255, 255, 255, 0.95);
  padding: 12px 20px;
  border-radius: 50px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  z-index: 100001;
}

.zoom-level {
  font-weight: 600;
  font-size: 14px;
  color: #495057;
  min-width: 50px;
  text-align: center;
}

/* Visual separator between zoom and rotate groups */
.controls-divider {
  width: 1px;
  height: 24px;
  background: #dee2e6;
  display: inline-block;
}

.image-counter {
  position: absolute;
  top: 1.5rem; left: 50%;
  transform: translateX(-50%);
  color: white;
  font-size: 1rem; font-weight: 600;
  background: rgba(0, 0, 0, 0.5);
  padding: 8px 16px;
  border-radius: 20px;
  z-index: 100001;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
}

.zoom-title {
  position: absolute;
  top: 1.5rem; left: 1.5rem;
  color: white;
  font-size: 1.25rem; font-weight: 600;
  z-index: 100001;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
  max-width: calc(100% - 8rem);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Transitions */
.zoom-overlay-enter-active,
.zoom-overlay-leave-active { transition: opacity 0.3s ease; }
.zoom-overlay-enter-from,
.zoom-overlay-leave-to { opacity: 0; }

/* Mobile */
@media (max-width: 768px) {
  .close-btn { top: 1rem; right: 1rem; width: 2.5rem; height: 2.5rem; font-size: 1.25rem; }
  .nav-btn { width: 3rem; height: 3rem; font-size: 1.5rem; }
  .nav-prev { left: 0.5rem; }
  .nav-next { right: 0.5rem; }
  .zoom-controls { bottom: 1.5rem; padding: 10px 16px; gap: 8px; }
  .zoom-controls :deep(.p-button) { width: 2.5rem; height: 2.5rem; }
  .zoom-level { font-size: 13px; min-width: 42px; }
  .image-counter { top: 1rem; font-size: 0.875rem; padding: 6px 12px; }
  .zoom-title { top: 3.5rem; left: 1rem; font-size: 1rem; max-width: calc(100% - 2rem); }
}

@media (max-width: 480px) {
  .close-btn { top: 0.75rem; right: 0.75rem; width: 2.25rem; height: 2.25rem; }
  .nav-btn { width: 2.5rem; height: 2.5rem; font-size: 1.25rem; }
  .zoom-controls { bottom: 1rem; padding: 8px 10px; gap: 6px; }
  .zoom-controls :deep(.p-button) { width: 2rem; height: 2rem; }
  .zoom-level { font-size: 12px; min-width: 36px; }
  .controls-divider { height: 20px; }
  .image-counter { font-size: 0.75rem; padding: 5px 10px; }
  .zoom-title { font-size: 0.875rem; }
}
</style>