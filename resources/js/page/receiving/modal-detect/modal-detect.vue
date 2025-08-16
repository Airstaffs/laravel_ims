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
                <!-- <span class="image-counter">{{ capturedImages.length }} / {{ maxImages }}</span> -->
                 <!-- Add this inside your modal, ideally below the camera preview and above the actions -->
                <div v-if="errorMessage" class="error-message text-red-500" style="margin-top: 1rem;">
                {{ errorMessage }}
                </div>
                </div>

                <div class="camera-preview-container">
                    <video ref="cameraPreview" id="camera-preview" autoplay playsinline></video>
                    
                    <div class="camera-overlay">
                        <div class="dimmed-background"></div>

                        <div class="target-box" ref="targetBox" :style="targetBoxStyle">
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
                <!-- <button @click="captureImage" class="capture-btn">
                    <i class="fas fa-camera"></i> Capture
                </button> -->
                </div>
                
            </div>
        </div>

        <!-- Hidden canvas for capture -->
        <canvas ref="cameraCanvas" class="hidden"></canvas>

      <!-- Scanner Body -->
      <div class="scanner-body">
        <!-- Uploader Area -->
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
            :disabled="loading"
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

        <!-- Cropped Image Output Preview -->
        <div v-if="croppedImage" class="cropped-output">
          <h4 class="mb-2">🖼️ Output Preview</h4>
          <img
            :src="croppedImage"
            alt="Cropped"
            class="border preview-img"
            style="max-width: 300px; object-fit: contain; width: 100%; height: auto;"
          />

          <div v-if="loading" class="mt-3">
            <p>⏳ Uploading and scanning image...</p>
          </div>

          <!-- Error Message (always visible if set) -->
          <div v-if="apiResult" class="mt-3">
            <h4>Detected Serials:</h4>
            <ul v-if="apiResult.serials && apiResult.serials.length">
              <li v-for="(serial, index) in apiResult.serials" :key="index">
                {{ serial.text }}
              </li>
            </ul>
            <p v-else class="text-red-500">⚠️ No serials detected.</p>
            <!-- <h4 class="mt-3">Raw OCR:</h4>
            <pre>
              {{ Array.isArray(apiResult.raw_ocr) 
                  ? apiResult.raw_ocr.map(o => `${o.text} (conf: ${o.confidence.toFixed(2)})`).join('\n') 
                  : apiResult.raw_ocr 
              }}
            </pre> -->
          </div>

          <div class="mt-3 btn-cropped-output">
            <button @click="resetImage" class="btn reset btn-red">Reset</button>
            <button
              @click="apiSend"
              class="btn submit btn-green"
              :disabled="!apiResult?.serials?.length"
              :class="{ 'btn-disabled': !apiResult?.serials?.length }"
            >
              Submit
            </button>
          </div>
        </div>

        <!-- Live Camera Detection Preview -->
    <div v-if="liveDetectionActive" class="live-detection">
      <h4 class="mb-2">📷 Live Camera Detection</h4>

      <video
        ref="liveVideo"
        autoplay
        muted
        playsinline
        class="border rounded"
        style="max-width: 300px; width: 100%;"
      ></video>

      <div v-if="liveResult" class="mt-3">
        <h4>Detected Serial:</h4>
        <p v-if="liveResult.found" class="text-green-600 font-bold">
          ✅ {{ liveResult.serial }}
        </p>
        <p v-else class="text-red-500">⚠️ No serial found...</p>
      </div>

      <div v-if="liveError" class="error-message text-red-500 mt-2">
        {{ liveError }}
      </div>

      <div class="mt-3 btn-live-output">
        <button @click="stopLiveDetection" class="btn btn-red">Stop</button>
        <button
          v-if="liveResult?.found"
          @click="submitLiveSerial"
          class="btn btn-green"
        >
          Submit Serial
        </button>
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
    import ComponentLogic from './modal-detect.logic.js'
    export default ComponentLogic
</script>

<style scoped src="./modal-detect.style.css"></style>
