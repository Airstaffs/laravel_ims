<template>
  <div class="scanner-modal">
    <div class="scanner-modal-content">
      <!-- Scanner Header -->
      <div class="scanner-header">
        <h2>{{ scannerTitle }}</h2>
        <div class="header-controls">
          <div v-if="enableCamera" class="header-actions">
            <button @click="toggleCamera" class="camera-toggle-btn">
              <i class="fas fa-camera"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- CAMERA MODAL (INLINE) -->
    <div v-if="enableCamera && showCameraModal" class="camera-modal">
    <div class="camera-modal-content">
        <div class="camera-header">
        <h2>Item Camera</h2>
        <span class="image-counter">{{ capturedImages.length }} / {{ maxImages }}</span>
        </div>

        <div class="camera-preview-container">
            <video id="camera-preview" autoplay playsinline></video>
            
            <div class="camera-overlay">
                <div class="dimmed-background"></div>

                <div class="target-box" ref="targetBox">
                    <div class="resize-handle top-left"></div>
                    <div class="resize-handle top-right"></div>
                    <div class="resize-handle bottom-left"></div>
                    <div class="resize-handle bottom-right"></div>
                </div>
            </div>
        </div>


        <div class="camera-actions">
        <button @click="closeCameraModal" class="cancel-btn">
            <i class="fas fa-times"></i> Close
        </button>
        <button @click="captureImage" class="capture-btn">
            <i class="fas fa-camera"></i> Capture
        </button>
        </div>

        <div class="camera-thumbnails">
        <div v-for="(image, index) in capturedImages" :key="index" class="camera-thumbnail">
            <img :src="image.data" alt="Thumbnail" />
        </div>
        </div>
    </div>
    </div>

    <!-- Hidden canvas for capture -->
    <canvas ref="cameraCanvas" class="hidden"></canvas>


      <!-- Scanner Body -->
      <div class="scanner-body">
        <!-- Uploader Area (only when no image or cropped result) -->
        <div
          class="border-dashed uploader-area"
          v-if="!imageUrl && !croppedImage"
          @dragover.prevent
          @dragenter.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @drop.prevent="handleDrop"
          @click="triggerFileInput"
          :class="{ 'is-dragging': isDragging }"
        >
          <p>
            Drag & drop an image here, or <span class="text-highlight">click to select</span>
          </p>
          <input
            ref="fileInput"
            type="file"
            accept="image/*"
            class="hidden"
            @change="onFileChange"
          />
        </div>

        <!-- Image Preview (with cropper) -->
        <div v-if="imageUrl && !croppedImage" class="image-preview">
          <img
            ref="imageElement"
            :src="imageUrl"
            class="preview-img"
            :style="rotationStyle"
          />
        </div>

        <!-- Cropper Controls -->
        <div v-if="imageUrl && !croppedImage" class="uploader-controls">
          <button @click="rotateLeft" class="btn btn-blue">Rotate Left</button>
          <button @click="rotateRight" class="btn btn-blue">Rotate Right</button>
          <button @click="cropImage" class="btn btn-green">Submit</button>
          <button @click="resetImage" class="btn btn-red">Reset</button>
        </div>

        <!-- Cropped Image Output Preview -->
        <div v-if="croppedImage" class="cropped-output">
          <h4 class="mb-2">🖼️ Output Preview</h4>
          <img :src="croppedImage" alt="Cropped" class="border preview-img" style="max-width: 300px;" />

          <div v-if="loading" class="mt-3">
            <p>⏳ Processing image...</p>
            </div>

            <div v-if="apiResult" class="mt-3">
            <h4>Detected Serials:</h4>
            <ul>
                <li v-for="(serial, index) in apiResult.serials" :key="index">{{ serial }}</li>
            </ul>

            <h4>Raw OCR:</h4>
            <pre>{{ apiResult.raw_ocr }}</pre>
            </div>

          <div class="mt-3 btn-cropped-output">
            <button @click="resetImage" class="btn reset btn-red">Reset</button>
            <button @click="apiSend" class="btn submit btn-green">Submit</button>
          </div>
        </div>

        <!-- Close Button -->
        <div class="scanner-actions">
          <button @click="$emit('close')" class="done-button">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ScannerMixin from '../../../components/ScannerMixin.js'
import { ref, watch, nextTick, defineComponent, computed, shallowRef } from 'vue'
import Cropper from 'cropperjs'
import 'cropperjs/dist/cropper.css'

export default defineComponent({
  name: 'ScannerModal',
  mixins: [ScannerMixin],
  props: {
    scannerTitle: {
      type: String,
      default: 'Detect Serial Numbers'
    },
    enableCamera: {
      type: Boolean,
      default: true
    }
  },
  setup(props, { emit }) {
    const imageUrl = ref(null)
    const cropper = shallowRef(null)
    const imageElement = ref(null)
    const fileInput = ref(null)
    const rotation = ref(0)
    const isDragging = ref(false)
    const croppedImage = ref(null)
    const isCropperReady = ref(false)
    const showCameraModal = ref(false)
    const cameraStream = ref(null)
    const cameraCanvas = ref(null)
    const capturedImages = ref([])
    const maxImages = 5
    const apiResult = ref(null)
    const loading = ref(false)
    const targetBox = ref(null)

    const rotationStyle = computed(() => ({
      transform: `rotate(${rotation.value}deg)`,
      transition: 'transform 0.3s ease',
    }))

    function rotateLeft() {
      if (cropper.value && typeof cropper.value.rotate === 'function') {
        cropper.value.rotate(-90)
      } else {
        console.warn('⚠️ rotateLeft failed: cropper not ready or method missing')
      }
    }

    function rotateRight() {
      if (cropper.value && typeof cropper.value.rotate === 'function') {
        cropper.value.rotate(90)
      } else {
        console.warn('⚠️ rotateRight failed: cropper not ready or method missing')
      }
    }

    watch(imageUrl, () => {
      nextTick(() => {
        const img = imageElement.value
        if (!img) return

        const tryInit = (attempts = 5) => {
          if (!img.complete || img.naturalWidth === 0) {
            if (attempts > 0) {
              setTimeout(() => tryInit(attempts - 1), 100)
            }
            return
          }

          if (cropper.value && typeof cropper.value.destroy === 'function') {
            cropper.value.destroy()
            cropper.value = null
          }

          cropper.value = new Cropper(img, {
            viewMode: 1,
            autoCropArea: 1,
            responsive: true,
            background: false,
            ready() {
              isCropperReady.value = true
            }
          })
        }

        tryInit()
      })
    })

    function triggerFileInput() {
      fileInput.value?.click()
    }

    function onFileChange(e) {
      const file = e.target.files[0]
      if (file) loadImage(file)
    }

    function handleDrop(e) {
      isDragging.value = false
      const file = e.dataTransfer.files[0]
      if (file) loadImage(file)
    }

    function loadImage(file) {
      const reader = new FileReader()
      reader.onload = () => {
        imageUrl.value = reader.result
        rotation.value = 0
        croppedImage.value = null
        isCropperReady.value = false
      }
      reader.readAsDataURL(file)
    }

    async function cropImage() {
        if (!cropper.value || typeof cropper.value.getCroppedCanvas !== 'function') {
            console.warn('⚠️ Cropper not initialized or getCroppedCanvas not available');
            return;
        }

        const canvas = cropper.value.getCroppedCanvas();
        if (!canvas) {
            console.warn('❌ Failed to get canvas from cropper');
            return;
        }

        // Step 1: Get cropped image as base64
        croppedImage.value = canvas.toDataURL('image/png');

        // Step 2: Send to backend immediately
        try {
            loading.value = true;
            apiResult.value = null;

            // Convert Base64 to Blob
            const blob = await fetch(croppedImage.value).then(res => res.blob());

            // Create FormData
            const formData = new FormData();
            formData.append("file", blob, "cropped.png");

            // Send to FastAPI
            const res = await fetch("http://127.0.0.1:8001/detect", {
            method: "POST",
            body: formData
            });

            apiResult.value = await res.json();

        } catch (error) {
            console.error("Error sending image to API:", error);
        } finally {
            loading.value = false;
        }
    }

    function resetImage() {
      if (cropper.value && typeof cropper.value.destroy === 'function') {
        cropper.value.destroy()
        cropper.value = null
      }

      imageUrl.value = null
      croppedImage.value = null
      rotation.value = 0
      isCropperReady.value = false
    }

    function toggleCamera() {
      emit('toggle-camera')
    }

    function toggleCamera() {
    showCameraModal.value = true
    startCamera()
    }

    function startCamera() {
        navigator.mediaDevices.getUserMedia({ video: true })
        .then((stream) => {
            cameraStream.value = stream
            const video = document.getElementById('camera-preview')
            video.srcObject = stream

            nextTick(() => {
                initDraggableTarget()
            })
        })
            .catch((err) => {
            console.error("🚫 Camera access denied:", err)
        })
    }

    function captureImage() {
        const video = document.getElementById('camera-preview')
        const canvas = cameraCanvas.value
        const ctx = canvas.getContext('2d')

        const overlayRect = targetBox.value.getBoundingClientRect()
        const videoRect = video.getBoundingClientRect()

        const scaleX = video.videoWidth / videoRect.width
        const scaleY = video.videoHeight / videoRect.height

        const sx = (overlayRect.left - videoRect.left) * scaleX
        const sy = (overlayRect.top - videoRect.top) * scaleY
        const sw = overlayRect.width * scaleX
        const sh = overlayRect.height * scaleY

        canvas.width = sw
        canvas.height = sh

        ctx.drawImage(video, sx, sy, sw, sh, 0, 0, sw, sh)

        const dataUrl = canvas.toDataURL('image/png')
        capturedImages.value.push({ data: dataUrl })

        loadImageFromCamera(dataUrl)
        closeCameraModal()
    }

    function loadImageFromCamera(dataUrl) {
        imageUrl.value = dataUrl
        rotation.value = 0
        croppedImage.value = null
        isCropperReady.value = false
    }

    function closeCameraModal() {
        showCameraModal.value = false
        if (cameraStream.value) {
            cameraStream.value.getTracks().forEach((track) => track.stop())
        }
    }

    function initDraggableTarget() {
        const target = targetBox.value
        if (!target) return

        const parentRect = target.parentElement.getBoundingClientRect()
        target.style.left = `${(parentRect.width - target.offsetWidth) / 2}px`
        target.style.top = `${(parentRect.height - target.offsetHeight) / 2}px`

        let offsetX = 0, offsetY = 0
        let dragStartX = 0, dragStartY = 0
        let isDragging = false
        let isResizing = false
        let resizeDir = ''
        const sensitivity = window.innerWidth < 768 ? 0.7 : 1

        function preventScroll(e) { e.preventDefault() }

        function endDragOrResize() {
            isDragging = false
            isResizing = false
            window.removeEventListener('pointermove', onPointerMove)
            window.removeEventListener('pointerup', endDragOrResize)
            window.removeEventListener('pointercancel', endDragOrResize)
            window.removeEventListener('mouseleave', endDragOrResize)
            document.body.style.overflow = ''
            document.removeEventListener('touchmove', preventScroll)
        }

        function onPointerMove(e) {
            const parentRect = target.parentElement.getBoundingClientRect()

            if (!isDragging && !isResizing) {
                if (Math.abs(e.clientX - dragStartX) > 5 || Math.abs(e.clientY - dragStartY) > 5) {
                    isDragging = true
                } else {
                    return
                }
            }

            if (isDragging) {
                let left = e.clientX - parentRect.left - offsetX
                let top = e.clientY - parentRect.top - offsetY
                left *= sensitivity
                top *= sensitivity
                left = Math.max(0, Math.min(left, parentRect.width - target.offsetWidth))
                top = Math.max(0, Math.min(top, parentRect.height - target.offsetHeight))
                target.style.left = `${left}px`
                target.style.top = `${top}px`
            }

            if (isResizing) {
                const rect = target.getBoundingClientRect()
                if (resizeDir.includes('right')) {
                    target.style.width = `${Math.max(50, (e.clientX - rect.left) * sensitivity)}px`
                }
                if (resizeDir.includes('left')) {
                    let deltaX = (e.clientX - rect.left) * sensitivity
                    let newLeft = parseFloat(target.style.left) + deltaX
                    let newWidth = rect.width - deltaX
                    if (newWidth >= 50) {
                        target.style.left = `${Math.max(0, newLeft)}px`
                        target.style.width = `${newWidth}px`
                    }
                }
                if (resizeDir.includes('bottom')) {
                    target.style.height = `${Math.max(50, (e.clientY - rect.top) * sensitivity)}px`
                }
                if (resizeDir.includes('top')) {
                    let deltaY = (e.clientY - rect.top) * sensitivity
                    let newTop = parseFloat(target.style.top) + deltaY
                    let newHeight = rect.height - deltaY
                    if (newHeight >= 50) {
                        target.style.top = `${Math.max(0, newTop)}px`
                        target.style.height = `${newHeight}px`
                    }
                }
            }
        }

        // Drag start
        target.addEventListener('pointerdown', (e) => {
            if (e.target.classList.contains('resize-handle')) return
            dragStartX = e.clientX
            dragStartY = e.clientY
            offsetX = e.clientX - target.getBoundingClientRect().left
            offsetY = e.clientY - target.getBoundingClientRect().top

            document.body.style.overflow = 'hidden'
            document.addEventListener('touchmove', preventScroll, { passive: false })

            window.addEventListener('pointermove', onPointerMove)
            window.addEventListener('pointerup', endDragOrResize)
            window.addEventListener('pointercancel', endDragOrResize)
            window.addEventListener('mouseleave', endDragOrResize)
        })

        // Resize start
        target.querySelectorAll('.resize-handle').forEach(handle => {
            handle.addEventListener('pointerdown', (e) => {
                e.stopPropagation()
                isResizing = true
                resizeDir = handle.classList[1]

                document.body.style.overflow = 'hidden'
                document.addEventListener('touchmove', preventScroll, { passive: false })

                window.addEventListener('pointermove', onPointerMove)
                window.addEventListener('pointerup', endDragOrResize)
                window.addEventListener('pointercancel', endDragOrResize)
                window.addEventListener('mouseleave', endDragOrResize)
            })
        })
    }

    return {
        imageUrl,
        cropper,
        imageElement,
        fileInput,
        rotation,
        rotationStyle,
        isDragging,
        isCropperReady,
        croppedImage,
        triggerFileInput,
        onFileChange,
        handleDrop,
        cropImage,
        resetImage,
        rotateLeft,
        rotateRight,
        toggleCamera,
        showCameraModal,
        cameraStream,
        cameraCanvas,
        capturedImages,
        maxImages,
        toggleCamera,
        startCamera,
        captureImage,
        closeCameraModal,
        apiResult,
        loading,
        targetBox
    }

  }
})
</script>

<style scoped>
/* hidden */
.hidden {
  display: none;
}   

/* Top Notification Styles */
.top-notification-container {
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 1100;
  width: 90%;
  max-width: 500px;
}

.top-notification {
  padding: 12px 18px;
  border-radius: 6px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  animation: slideDown 0.3s ease, fadeIn 0.3s ease;
}

.top-notification.success {
  background-color: #e8f5e9;
  color: #2e7d32;
  border-left: 4px solid #4CAF50;
}

.top-notification.error {
  background-color: #ffebee;
  color: #c62828;
  border-left: 4px solid #f44336;
}

@keyframes slideDown {
  from { transform: translateY(-20px); }
  to { transform: translateY(0); }
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Scanner Button Styles */
.scanner-container {
  display: flex;
  align-items: center;
  margin-bottom: 15px;
}

.scanner-button {
  background-color: #4CAF50;
  color: white;
  border: none;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 22px;
  display: flex;
  justify-content: center;
  align-items: center;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
  transition: all 0.2s ease;
}

.scanner-button:hover {
  background-color: #45a049;
  transform: scale(1.05);
}

.scan-count {
  margin-left: 10px;
  background-color: #f8f9fa;
  padding: 5px 10px;
  border-radius: 15px;
  font-size: 14px;
  color: #333;
}

/* Scanner Modal Styles */
.scanner-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.scanner-modal-content {
  background-color: white;
  border-radius: 8px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.scanner-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 15px;
  border-bottom: 1px solid #eee;
}

.scanner-header h2 {
  margin: 0;
  font-size: 18px;
}

.header-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-toggle {
  display: flex;
  align-items: center;
  gap: 5px;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 40px;
  height: 20px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: .4s;
  border-radius: 20px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 14px;
  width: 14px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .4s;
  border-radius: 50%;
}

input:checked + .toggle-slider {
  background-color: #4CAF50;
}

input:checked + .toggle-slider:before {
  transform: translateX(20px);
}

.scanner-body {
  padding: 10px;
}

.scanner-top-notification-area {
  min-height: 40px;
  margin-bottom: 12px;
}

/* Scanner camera styles */
#scanner-camera-preview {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

.scanner-view.active-camera {
  background-color: #000;
  position: relative;
  overflow: hidden;
  aspect-ratio: 4/3;
}

.scanner-view {
  background-color: #000;
  width: 100%;
  height: 200px;
  position: relative;
  margin-bottom: 12px;
  border-radius: 4px;
  overflow: hidden;
  transition: height 0.3s ease;
}

.scanner-view.compact-view {
  height: 100px;
}

.scanner-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
}

.scanner-corner {
  position: absolute;
  width: 20px;
  height: 20px;
  border-color: #4CAF50;
  border-style: solid;
  border-width: 0;
}

.top-left {
  top: 20px;
  left: 20px;
  border-top-width: 3px;
  border-left-width: 3px;
}

.top-right {
  top: 20px;
  right: 20px;
  border-top-width: 3px;
  border-right-width: 3px;
}

.bottom-left {
  bottom: 20px;
  left: 20px;
  border-bottom-width: 3px;
  border-left-width: 3px;
}

.bottom-right {
  bottom: 20px;
  right: 20px;
  border-bottom-width: 3px;
  border-right-width: 3px;
}

.scanner-controls {
  position: absolute;
  bottom: 10px;
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-between; /* This ensures good spacing */
  align-items: center;
  padding: 0 10px; /* Add horizontal padding */
  z-index: 5;
}

/* Left side controls - counter and camera button */
.scanner-controls .capture-count {
  background-color: rgba(0, 0, 0, 0.6);
  color: white;
  padding: 5px 10px;
  border-radius: 12px;
  font-size: 12px;
  margin-right: 10px; /* Add space after counter */
}

/* Center area - camera buttons */
.scanner-controls .camera-button,
.scanner-controls .capture-button {
  background-color: rgba(0, 0, 0, 0.6);
  color: white;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 16px;
  cursor: pointer;
  margin: 0 5px; /* Add space around buttons */
}

/* Right side - compact toggle button */
.scanner-controls .compact-toggle {
  background-color: rgba(0, 0, 0, 0.6);
  color: white;
  border: none;
  border-radius: 4px;
  padding: 5px 10px;
  font-size: 12px;
  cursor: pointer;
  margin-left: auto; /* Push to right side */
}


.capture-count {
  background-color: rgba(0, 0, 0, 0.6);
  color: white;
  padding: 5px 10px;
  border-radius: 12px;
  font-size: 12px;
}


.capture-button {
  background-color: #4CAF50;
}

.camera-restart-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 10;
}

.restart-camera-btn {
  background-color: #4CAF50;
  color: white;
  border: none;
  padding: 10px 15px;
  border-radius: 5px;
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}

.restart-camera-btn:disabled {
  background-color: #999;
  cursor: not-allowed;
}

/* Input form styles */
.input-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 12px;
}

.submit-button {
  margin-top: 5px;
  padding: 8px;
  background-color: #4CAF50;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: bold;
  cursor: pointer;
}

/* Input field styles that will be used by slots */
.input-group {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.input-group label {
  font-weight: 600;
  font-size: 12px;
  color: #333;
}

.input-group input {
  padding: 8px 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.input-group input:focus {
  border-color: #4CAF50;
  outline: none;
  box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
}

/* Scan Statistics Styles */
.scan-stats {
  display: flex;
  justify-content: space-between;
  background-color: #f8f9fa;
  border-radius: 4px;
  padding: 8px 10px;
  margin-bottom: 12px;
}

.stat-item {
  text-align: center;
  flex: 1;
}

.stat-label {
  font-size: 12px;
  color: #555;
  font-weight: 500;
  display: block;
}

.stat-value {
  font-size: 14px;
  font-weight: 700;
}

.stat-value.success {
  color: #4CAF50;
}

.stat-value.error {
  color: #f44336;
}

/* Notification Styles */
.notification {
  padding: 8px 12px;
  border-radius: 4px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.notification.success {
  background-color: #e8f5e9;
  color: #2e7d32;
  border-left: 4px solid #4CAF50;
}

.notification.error {
  background-color: #ffebee;
  color: #c62828;
  border-left: 4px solid #f44336;
}

.top-notification.warning {
  background-color: #fff3cd;
  color: #856404;
  border-left: 4px solid #ffc107;
}


/* Scanned items list styles */
.scanned-items {
  margin-bottom: 15px;
}

.scans-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  cursor: pointer;
  user-select: none;
}

.toggle-scans {
  color: #4CAF50;
  font-size: 14px;
  font-weight: 500;
}

.scans-header h3 {
  margin: 0;
  font-size: 16px;
  color: #333;
}

/* Animation for slide transition */
.slide-enter-active, .slide-leave-active {
  transition: max-height 0.3s ease, opacity 0.2s ease;
  max-height: 180px;
  overflow: hidden;
}

.slide-enter-from, .slide-leave-to {
  max-height: 0;
  opacity: 0;
}

.scan-list {
  list-style: none;
  padding: 0;
  margin: 0;
  max-height: 180px;
  overflow-y: auto;
  border: 1px solid #eee;
  border-radius: 4px;
}

.scan-list li {
  padding: 8px;
  border-bottom: 1px solid #eee;
  display: grid;
  grid-template-columns: 1fr auto 70px;
  gap: 8px;
  align-items: center;
}

.scan-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.scan-field {
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.scan-list li:last-child {
  border-bottom: none;
}

.scan-list li.success {
  border-left: 3px solid #4CAF50;
}

.scan-list li.error {
  border-left: 3px solid #f44336;
}

.scan-time {
  color: #666;
  font-size: 12px;
}

.scan-time-small {
  display: none;
  color: #666;
  font-size: 10px;
  font-style: italic;
  margin-top: 2px;
}

.scan-status {
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
  text-align: center;
}

.scan-list li.success .scan-status {
  background-color: #e8f5e9;
  color: #2e7d32;
}

.scan-list li.error .scan-status {
  background-color: #ffebee;
  color: #c62828;
}

/* Action Buttons Styles */
.scanner-actions {
  display: flex;
  gap: 10px;
  margin-top: 5px;
}

.reset-button, .done-button {
  flex: 1;
  padding: 10px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
}

.reset-button {
  background-color: #f5f5f5;
  color: #333;
}

.done-button {
  background-color: #4CAF50;
  color: white;
}

/* Captured Images Preview */
.captured-images-container {
  margin-bottom: 12px;
  border: 1px solid #eee;
  border-radius: 4px;
  overflow: hidden;
}

.images-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  background-color: #f8f9fa;
  cursor: pointer;
  user-select: none;
}

.images-header h3 {
  margin: 0;
  font-size: 14px;
  color: #333;
}

.toggle-preview {
  color: #4CAF50;
  font-size: 12px;
}

.image-thumbnails {
  display: flex;
  gap: 8px;
  padding: 10px;
  overflow-x: auto;
  background-color: #fff;
  max-height: 120px;
}

.image-thumbnail {
  position: relative;
  min-width: 80px;
  height: 80px;
  border-radius: 4px;
  overflow: hidden;
  border: 1px solid #ddd;
}

.image-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.delete-image-btn {
  position: absolute;
  top: 4px;
  right: 4px;
  background-color: rgba(255, 0, 0, 0.7);
  color: white;
  border: none;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  z-index: 5;
}

.delete-image-btn i {
  font-size: 12px;
}

.image-timestamp {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: rgba(0, 0, 0, 0.5);
  color: white;
  font-size: 8px;
  padding: 2px 4px;
  text-align: center;
}

/* Camera Modal */
.camera-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.9);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1100;
}

.camera-modal-content {
  width: 90%;
  max-width: 500px;
  background-color: #000;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.camera-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 15px;
  background-color: #222;
  color: white;
}

.camera-header h2 {
  margin: 0;
  font-size: 18px;
}

.image-counter {
  background-color: rgba(255, 255, 255, 0.2);
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
}

.camera-preview-container {
  position: relative;
  width: 100%;
  height: 0;
  padding-bottom: 75%;
  overflow: hidden;
}

#camera-preview {
  position: absolute;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.camera-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  pointer-events: none;
}

.camera-corner {
  position: absolute;
  width: 20px;
  height: 20px;
  border-color: #fff;
  border-style: solid;
  border-width: 0;
}

.camera-overlay .top-left {
  top: 20px;
  left: 20px;
  border-top-width: 3px;
  border-left-width: 3px;
}

.camera-overlay .top-right {
  top: 20px;
  right: 20px;
  border-top-width: 3px;
  border-right-width: 3px;
}

.camera-overlay .bottom-left {
  bottom: 20px;
  left: 20px;
  border-bottom-width: 3px;
  border-left-width: 3px;
}

.camera-overlay .bottom-right {
  bottom: 20px;
  right: 20px;
  border-bottom-width: 3px;
  border-right-width: 3px;
}

.camera-actions {
  display: flex;
  padding: 10px;
  gap: 10px;
  background-color: #222;
}

.cancel-btn, .capture-btn {
  flex: 1;
  padding: 12px;
  border: none;
  border-radius: 4px;
  font-weight: bold;
  cursor: pointer;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 6px;
}

.cancel-btn {
  background-color: #444;
  color: white;
}

.capture-btn {
  background-color: #4CAF50;
  color: white;
}

.camera-thumbnails {
  display: flex;
  gap: 4px;
  padding: 10px;
  background-color: #222;
  overflow-x: auto;
  height: 60px;
}

.camera-thumbnail {
  width: 50px;
  height: 50px;
  border-radius: 4px;
  overflow: hidden;
  border: 2px solid white;
}

.camera-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Header actions and camera toggle button */
.header-actions {
  display: flex;
  gap: 10px;
  margin-left: 10px;
}

.camera-toggle-btn {
  display: flex;
  justify-content: center;
  align-items: center;
  background-color: #4CAF50;
  color: white;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 16px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.camera-toggle-btn:hover {
  background-color: #45a049;
  transform: scale(1.05);
}

/* Uploader styles css */
.image-preview cropper-canvas {
    height: 300px;
}

.uploader-area {
  border: 2px dashed #ccc;
  padding: 20px;
  text-align: center;
  cursor: pointer;
  margin-bottom: 16px;
}

.uploader-area.is-dragging {
  background-color: #f0f8ff;
  border-color: #3b82f6;
}

.text-highlight {
  color: #3b82f6;
  text-decoration: underline;
}

.image-preview {
  border: 1px solid #ccc;
  padding: 10px;
  /* max-height: 400px; */
  overflow: auto;
}

.preview-img {
  max-width: 100%;
  /* max-height: 300px; */
  display: block;
  margin: auto;
}

.uploader-controls {
  margin-top: 16px;
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
  justify-content: space-between
}

.uploader-controls .btn {
    width: 23.8%;
    justify-content: center;
}

.btn {
  padding: 8px 16px;
  /* border: none; */
  color: #fff;
  border-radius: 4px;
  cursor: pointer;
}

.btn:hover {
    border: 1px solid #000;
}

.btn-blue {
  background-color: #3b82f6;
}

.btn-green {
  background-color: #10b981;
}

.btn-red {
  background-color: #ef4444;
}

.btn-cropped-output {
    display: flex;
    gap: 5px;
}

.btn.reset, .btn.submit {
    display: flex;
    width: 100%;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
    padding: 10px;
}
/* camera target */
.camera-preview-container {
    position: relative;
    width: 100%;
    height: 100%;
}

.camera-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none; /* default to none, only box will allow events */
}

.dimmed-background {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    /* background-color: rgba(0,0,0,0.6); */
    z-index: 1;
}

.target-box {
    position: absolute;
    width: 60%;
    height: 40%;
    border: 2px dashed rgba(255, 0, 0, 0.9);
    box-sizing: border-box;
    border-radius: 4px;
    z-index: 2;
    background: transparent;
    cursor: grab;
    pointer-events: auto; /* allow mouse/touch events here */
}

.resize-handle {
    position: absolute;
    width: 14px;
    height: 14px;
    background: rgb(255, 0, 0);
    border: 2px solid black;
    border-radius: 50%;
    z-index: 3;
}

.resize-handle.top-left { top: -7px; left: -7px; cursor: nwse-resize; }
.resize-handle.top-right { top: -7px; right: -7px; cursor: nesw-resize; }
.resize-handle.bottom-left { bottom: -7px; left: -7px; cursor: nesw-resize; }
.resize-handle.bottom-right { bottom: -7px; right: -7px; cursor: nwse-resize; }


/* Responsive adjustments */
@media (max-width: 768px) {
    .resize-handle {
        width: 24px;
        height: 24px;
        margin: -12px; /* keeps handle center in same place */
    }
}

@media (max-width: 600px) {
  .scanner-modal-content {
    width: 100%;
    max-width: none;
    height: 100%;
    max-height: none;
    display: flex;
    flex-direction: column;
    border-radius: 0;
  }
  
  .scanner-body {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
  }
  
  .scanner-view {
    height: 180px;
  }
  
  .scanner-view.compact-view {
    height: 80px;
  }
  
  .scan-time {
    display: none;
  }
  
  .scan-time-small {
    display: block;
  }
  
  .scan-list li {
    grid-template-columns: 1fr 60px;
  }
  
  .scanner-actions {
    position: sticky;
    bottom: 0;
    background-color: white;
    padding-top: 8px;
    z-index: 10;
  }
  
  .slide-enter-active, .slide-leave-active {
    max-height: 120px;
  }
  
  .scans-header {
    padding: 6px 0;
  }
  
  .toggle-scans {
    font-size: 12px;
  }

  .camera-modal-content {
    width: 100%;
    height: 100%;
    max-width: none;
    border-radius: 0;
  }
  
  .camera-preview-container {
    padding-bottom: 100%;
  }
  
  .camera-actions {
    position: sticky;
    bottom: 0;
  }
  
  .image-thumbnails {
    max-height: 100px;
  }
  
  .image-thumbnail {
    min-width: 70px;
    height: 70px;
  }
}

@media (max-width: 360px) {
  .scanner-view {
    height: 150px;
  }
  
  .scanner-view.compact-view {
    height: 70px;
  }
  
  .scan-stats {
    padding: 6px;
  }
  
  .stat-label {
    font-size: 10px;
  }
  
  .stat-value {
    font-size: 12px;
  }
  
  .notification {
    padding: 6px 10px;
    font-size: 12px;
  }
  
  .scanned-items h3 {
    font-size: 13px;
  }
  
  .scan-list {
    max-height: 100px;
  }
  
  .slide-enter-active, .slide-leave-active {
    max-height: 100px;
  }

  .camera-header h2 {
    font-size: 16px;
  }
  
  .image-counter {
    font-size: 10px;
  }
  
  .image-thumbnails {
    max-height: 80px;
  }
  
  .image-thumbnail {
    min-width: 60px;
    height: 60px;
  }
  
  .camera-thumbnails {
    height: 50px;
  }
  
  .camera-thumbnail {
    width: 40px;
    height: 40px;
  }
}

.image-preview-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.9);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 2000; /* Higher than other modals */
}

.image-preview-content {
  width: 95%;
  max-width: 800px;
  background-color: #222;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}

.image-preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 15px;
  background-color: #333;
  border-bottom: 1px solid #444;
}

.image-preview-header h3 {
  margin: 0;
  color: white;
  font-size: 16px;
}

.close-preview-btn {
  background: none;
  border: none;
  color: white;
  font-size: 18px;
  cursor: pointer;
}

.image-preview-body {
  display: flex;
  flex-direction: column;
  padding: 15px;
  overflow-y: auto;
}

.image-preview-container {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 15px;
  min-height: 200px;
}

.preview-image {
  max-width: 100%;
  max-height: 60vh;
  object-fit: contain;
  border-radius: 4px;
}

.image-preview-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.nav-btn {
  background-color: rgba(255, 255, 255, 0.1);
  color: white;
  border: none;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 16px;
  cursor: pointer;
}

.nav-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.image-info {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.image-number {
  color: white;
  font-size: 14px;
  font-weight: bold;
}

.image-time {
  color: #aaa;
  font-size: 12px;
  margin-top: 5px;
}

.image-preview-actions {
  display: flex;
  justify-content: space-between;
  margin-top: 10px;
}

.delete-btn, .close-btn {
  padding: 10px 15px;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}

.delete-btn {
  background-color: #f44336;
  color: white;
}

.close-btn {
  background-color: #444;
  color: white;
}

/* Make thumbnails clickable */
.image-thumbnail, .camera-thumbnail {
  cursor: pointer;
  position: relative;
}

.view-image-hint {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background-color: rgba(0, 0, 0, 0.5);
  color: white;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.image-thumbnail:hover .view-image-hint,
.camera-thumbnail:hover .view-image-hint {
  opacity: 1;
}

/* Responsive styles for mobile */
@media (max-width: 600px) {
  .image-preview-content {
    width: 100%;
    height: 100%;
    max-width: none;
    max-height: none;
    border-radius: 0;
  }
  
  .image-preview-container {
    height: 60vh; /* Take up most of the screen on mobile */
  }
  
  .preview-image {
    max-height: 100%;
  }
  
  .image-preview-actions {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #222;
    padding: 10px;
    z-index: 10;
  }
  
  .nav-btn {
    width: 50px;
    height: 50px;
    font-size: 20px;
  }
}

@media (max-width: 360px) {
  .image-preview-container {
    height: 50vh;
  }
  
  .image-preview-header h3 {
    font-size: 14px;
  }
  
  .nav-btn {
    width: 36px;
    height: 36px;
  }
  
  .delete-btn, .close-btn {
    padding: 8px 12px;
    font-size: 13px;
  }
}


.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 2000;
  border-radius: 8px;
}

.loading-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 15px;
}

.loading-text {
  color: white;
  font-size: 16px;
  font-weight: 500;
  text-align: center;
}

/* Spinner Animation */
.spinner {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 6px;
  margin: 0 auto;
}

.spinner > div {
  width: 18px;
  height: 18px;
  background-color: #4CAF50;
  border-radius: 100%;
  display: inline-block;
  animation: sk-bouncedelay 1.4s infinite ease-in-out both;
}

.spinner .bounce1 {
  animation-delay: -0.32s;
}

.spinner .bounce2 {
  animation-delay: -0.16s;
}

@keyframes sk-bouncedelay {
  0%, 80%, 100% { 
    transform: scale(0);
  } 40% { 
    transform: scale(1.0);
  }
}

/* On mobile devices, make sure the loader is centered */
@media (max-width: 600px) {
  .loading-overlay {
    border-radius: 0;
  }
  
  .loading-text {
    font-size: 14px;
    padding: 0 20px;
  }
}

/* Product Thumbnails Styles with unique class names */
.scanner-product-thumbnails-container {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 10;
  display: flex;
}

.scanner-default-thumbnail {
  width: 80px;
  height: 80px;
  border-radius: 4px;
  overflow: hidden;
  border: 2px solid rgba(255, 255, 255, 0.7);
  position: relative;
  cursor: pointer;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
  background-color: #000;
}

.scanner-default-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.2s;
}

.scanner-default-thumbnail:hover img {
  transform: scale(1.05);
}

.scanner-thumbnail-label {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: rgba(0, 0, 0, 0.7);
  color: white;
  font-size: 10px;
  padding: 4px;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

/* Product Image Preview Modal with unique class names */
.scanner-product-image-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.9);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 2500; /* Higher than other modals */
}

.scanner-product-image-content {
  width: 95%;
  max-width: 800px;
  background-color: #222;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}

.scanner-product-image-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 15px;
  background-color: #333;
  border-bottom: 1px solid #444;
}

.scanner-product-image-header h3 {
  margin: 0;
  color: white;
  font-size: 16px;
}

.scanner-close-preview-btn {
  background: none;
  border: none;
  color: white;
  font-size: 18px;
  cursor: pointer;
}

.scanner-product-image-body {
  display: flex;
  flex-direction: column;
  padding: 15px;
  overflow-y: auto;
}

.scanner-product-image-container {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 15px;
  min-height: 300px;
}

.scanner-preview-image {
  max-width: 100%;
  max-height: 60vh;
  object-fit: contain;
}

.scanner-product-image-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 10px;
}

.scanner-nav-btn {
  background-color: rgba(255, 255, 255, 0.1);
  color: white;
  border: none;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 16px;
  cursor: pointer;
}

.scanner-nav-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.scanner-image-info {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.scanner-image-label {
  color: #aaa;
  font-size: 12px;
  margin-top: 5px;
  display: block;
  text-align: center;
}

/* Mobile responsiveness */
@media (max-width: 600px) {
  .scanner-default-thumbnail {
    width: 70px;
    height: 70px;
  }
  
  .scanner-product-image-content {
    width: 100%;
    height: 100%;
    max-width: none;
    max-height: none;
    border-radius: 0;
  }
}

@media (max-width: 360px) {
  .scanner-default-thumbnail {
    width: 60px;
    height: 60px;
  }
  
  .scanner-thumbnail-label {
    font-size: 9px;
  }
}
</style>
