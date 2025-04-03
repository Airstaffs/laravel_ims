<template>
  <div class="scanner-wrapper">
    <!-- Scanner Button -->
    <div class="scanner-container">
      <button @click="openScannerModal" class="scanner-button">
        <i class="fas fa-barcode"></i>
      </button>
      <span v-if="totalScanned > 0" class="scan-count">
        {{ totalScanned }}
      </span>
    </div>
    
    <!-- Top Notification Area -->
    <div class="top-notification-container">
      <div v-if="showSuccessNotification && !showScannerModal" class="top-notification success">
        <i class="fas fa-check-circle"></i> Successfully scanned: {{ lastScannedItem }}
      </div>
      <div v-if="showErrorNotification && !showScannerModal" class="top-notification error">
        <i class="fas fa-exclamation-circle"></i> {{ scanErrorMessage }}
      </div>
    </div>
    
    <!-- Scanner Modal -->
    <div v-if="showScannerModal" class="scanner-modal">
      <div class="scanner-modal-content">
        <!-- Scanner Header -->
        <div class="scanner-header">
          <h2>{{ scannerTitle }}</h2>
          <div class="header-controls">
            <div class="header-toggle">
              <label class="toggle-switch">
                <input 
                  type="checkbox" 
                  :checked="showManualInput" 
                  @change="toggleManualInput"
                >
                <span class="toggle-slider"></span>
              </label>
              <span>{{ showManualInput ? 'Manual' : 'Auto' }}</span>
            </div>
            <!-- Camera button - only if camera is enabled -->
            <div v-if="enableCamera" class="header-actions">
              <button @click="toggleCamera" class="camera-toggle-btn">
                <i class="fas fa-camera"></i>
              </button>
            </div>
          </div>
        </div>
        
        <div class="scanner-body">
          <!-- Top Scanner Notification Area -->
          <div class="scanner-top-notification-area">
            <div v-if="showSuccessNotification" class="notification success">
              <i class="fas fa-check-circle"></i> Successfully scanned: {{ lastScannedItem }}
            </div>
            <div v-if="showErrorNotification" class="notification error">
              <i class="fas fa-exclamation-circle"></i> {{ scanErrorMessage }}
            </div>
          </div>
          
          <!-- Captured Images Preview - only if camera is enabled -->
          <div v-if="enableCamera && capturedImages.length > 0" class="captured-images-container">
            <div class="images-header" @click="toggleImagePreview">
              <h3>Images ({{ capturedImages.length }}/{{ maxImages }})</h3>
              <span class="toggle-preview">{{ previewImages ? 'Hide' : 'Show' }}</span>
            </div>
            <div v-if="previewImages" class="image-thumbnails">
              <div v-for="(image, index) in capturedImages" :key="index" class="image-thumbnail">
                <img :src="image.data" alt="Captured image" @click="openImagePreview(index)" />
                <button @click="deleteImage(index)" class="delete-image-btn">
                  <i class="fas fa-trash"></i>
                </button>
                <span class="image-timestamp">{{ image.timestamp }}</span>
                <span class="view-image-hint"><i class="fas fa-search-plus"></i></span>
              </div>
            </div>
          </div>
          
          <!-- Camera/Scanner View - only if camera is enabled -->
          <div v-if="enableCamera" class="scanner-view" :class="{ 'compact-view': isCompactMode, 'active-camera': scannerCameraActive }">
            <!-- When camera is inactive, show the grid overlay -->
            <div v-if="!scannerCameraActive" class="scanner-overlay">
              <div class="scanner-corner top-left"></div>
              <div class="scanner-corner top-right"></div>
              <div class="scanner-corner bottom-left"></div>
              <div class="scanner-corner bottom-right"></div>
            </div>
            
            <!-- When camera is active, show the live camera feed here -->
            <video v-if="scannerCameraActive" id="scanner-camera-preview" autoplay playsinline></video>
            
            <!-- Camera restart overlay -->
            <div v-if="!scannerCameraActive && showScannerModal" class="camera-restart-overlay">
              <button 
                class="restart-camera-btn" 
                @click="restartCamera" 
                :disabled="isCameraBeingReleased"
              >
                <i class="fas" :class="isCameraBeingReleased ? 'fa-spinner fa-spin' : 'fa-sync'"></i> 
                {{ isCameraBeingReleased ? 'Releasing camera...' : 'Restart Camera' }}
              </button>
            </div>
            
            <div class="scanner-controls">
              <!-- Left side: Counter -->
              <div class="counter-area">
                <div class="capture-count">{{ capturedImages.length }}/{{ maxImages }}</div>
              </div>
              
              <!-- Center: Single camera capture button -->
              <div class="camera-area">
                <button class="camera-button" @click="captureFromScanner">
                  <i class="fas fa-camera"></i>
                </button>
              </div>
              
              <!-- Right side: Compact toggle -->
              <div class="toggle-area">
                <button class="compact-toggle" @click="toggleCompactMode">
                  {{ isCompactMode ? 'Expand' : 'Compact' }}
                </button>
              </div>
            </div>
          </div>
          
          <!-- Input Fields - Customizable via slots -->
          <div class="input-form">
            <!-- Use the slot to let each module provide its own input fields -->
            <slot name="input-fields"></slot>
            
            <!-- The default Submit button has been removed to avoid duplicates -->
            <!-- Each module should provide its own submit buttons in manual mode -->
          </div>
          
          <!-- Scan Statistics -->
          <div class="scan-stats">
            <div class="stat-item">
              <span class="stat-label">Total:</span>
              <span class="stat-value">{{ totalScanned }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Success:</span>
              <span class="stat-value success">{{ successfulScans }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Failed:</span>
              <span class="stat-value error">{{ failedScans }}</span>
            </div>
          </div>
          
          <!-- Scanned Items List -->
          <div class="scanned-items">
            <div class="scans-header" @click="toggleScansVisibility">
              <h3>Recent Scans</h3>
              <span class="toggle-scans">{{ showScans ? 'Hide' : 'Show' }}</span>
            </div>
            <transition name="slide">
              <ul v-if="showScans" class="scan-list">
                <!-- Default scan items display -->
                <li v-for="(scan, index) in recentScans" :key="index" :class="{ 'success': scan.success, 'error': !scan.success }">
                  <div class="scan-details">
                    <div v-for="(value, key) in getScanDisplayFields(scan)" :key="key" class="scan-field">
                      {{ key }}: {{ value }}
                    </div>
                    <div class="scan-time-small">{{ scan.time }}</div>
                  </div>
                  <span class="scan-time">{{ scan.time }}</span>
                  <span class="scan-status">{{ scan.success ? 'Success' : 'Failed' }}</span>
                </li>
              </ul>
            </transition>
          </div>
          
          <!-- Action Buttons -->
          <div class="scanner-actions">
            <button @click="resetScanner" class="reset-button">Reset</button>
            <button @click="closeScannerModal" class="done-button">Done</button>
          </div>
        </div>
        
        <!-- Camera Modal - only if camera is enabled -->
        <div v-if="enableCamera && showCameraModal" class="camera-modal">
          <div class="camera-modal-content">
            <div class="camera-header">
              <h2>Item Camera</h2>
              <span class="image-counter">{{ capturedImages.length }} / {{ maxImages }}</span>
            </div>
            
            <div class="camera-preview-container">
              <video id="camera-preview" autoplay playsinline></video>
              <div class="camera-overlay">
                <div class="camera-corner top-left"></div>
                <div class="camera-corner top-right"></div>
                <div class="camera-corner bottom-left"></div>
                <div class="camera-corner bottom-right"></div>
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
                <img :src="image.data" alt="Thumbnail" @click="openImagePreview(index)" />
              </div>
            </div>
          </div>
        </div>
        
        <!-- Image Preview Modal -->
        <div v-if="showImagePreviewModal" class="image-preview-modal" @click="closeImagePreview">
          <div class="image-preview-content" @click.stop>
            <div class="image-preview-header">
              <h3>Image Preview</h3>
              <button @click="closeImagePreview" class="close-preview-btn">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div class="image-preview-body">
              <div class="image-preview-container">
                <img :src="currentPreviewImage.data" alt="Image preview" class="preview-image" />
              </div>
              <div class="image-preview-controls">
                <button @click="prevImage" :disabled="currentImageIndex === 0" class="nav-btn prev-btn">
                  <i class="fas fa-chevron-left"></i>
                </button>
                <div class="image-info">
                  <span class="image-number">{{ currentImageIndex + 1 }} / {{ capturedImages.length }}</span>
                  <span class="image-time">{{ currentPreviewImage.timestamp }}</span>
                </div>
                <button @click="nextImage" :disabled="currentImageIndex >= capturedImages.length - 1" class="nav-btn next-btn">
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
              <div class="image-preview-actions">
                <button @click="deleteCurrentImage" class="delete-btn">
                  <i class="fas fa-trash"></i> Delete
                </button>
                <button @click="closeImagePreview" class="close-btn">
                  <i class="fas fa-times"></i> Close
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ScannerMixin from './ScannerMixin.js';

export default {
  name: 'ScannerComponent',
  mixins: [ScannerMixin],
  data() {
    return {
      // Add image preview data
      showImagePreviewModal: false,
      currentImageIndex: 0
    };
  },
  computed: {
    // Check if the slot contains a submit button
    hasCustomSubmitButton() {
      // This is a simplified approach - in a real app, you'd need to use refs or other methods
      // to detect if the slot content includes a submit button
      const slotContent = this.$slots['input-fields'];
      return slotContent && slotContent.some(node => 
        node.tag && 
        (node.tag.includes('button') || 
         (node.children && node.children.some(child => 
           child.tag && child.tag.includes('button')
         ))
        )
      );
    },
    
    // Get the current image being previewed
    currentPreviewImage() {
      if (this.capturedImages.length > 0 && this.currentImageIndex >= 0 && this.currentImageIndex < this.capturedImages.length) {
        return this.capturedImages[this.currentImageIndex];
      }
      return { data: '', timestamp: '' };
    }
  },
  methods: {
    // Open the image preview modal
    openImagePreview(index) {
      this.currentImageIndex = index;
      this.showImagePreviewModal = true;
      
      // Prevent scrolling when modal is open
      document.body.style.overflow = 'hidden';
    },
    
    // Close the image preview modal
    closeImagePreview() {
      this.showImagePreviewModal = false;
      
      // Re-enable scrolling
      document.body.style.overflow = '';
    },
    
    // Navigate to previous image
    prevImage() {
      if (this.currentImageIndex > 0) {
        this.currentImageIndex--;
      }
    },
    
    // Navigate to next image
    nextImage() {
      if (this.currentImageIndex < this.capturedImages.length - 1) {
        this.currentImageIndex++;
      }
    },
    
    // Delete the current image from preview
    deleteCurrentImage() {
      if (this.capturedImages.length > 0) {
        this.deleteImage(this.currentImageIndex);
        
        // Adjust index if needed
        if (this.currentImageIndex >= this.capturedImages.length) {
          this.currentImageIndex = Math.max(0, this.capturedImages.length - 1);
        }
        
        // Close modal if no images left
        if (this.capturedImages.length === 0) {
          this.closeImagePreview();
        }
      }
    }
  }
};
</script>

<style>
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

/* Responsive adjustments */
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
</style>