<template>
  <div class="scanner-wrapper">
    <!-- Scanner Button - only show if hideButton is false -->
    <div v-if="!hideButton" class="scanner-container">
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
          <div class="scanner-top-notification-area" v-show="showSuccessNotification || showErrorNotification">
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
              <!-- <h3>Images ({{ capturedImages.length }}/{{ maxImages }})</h3> -->
              <h3>
                Images ({{ imagesForCurrentStep.length }}/{{ maxImagesForCurrentStep }})
              </h3>
              <span class="toggle-preview">{{ previewImages ? 'Hide' : 'Show' }}</span>
            </div>
            <div v-if="previewImages" class="image-thumbnails">
              <!-- <div v-for="(image, index) in capturedImages" :key="index" class="image-thumbnail"> -->
              <div v-for="(image, index) in imagesForCurrentStep" :key="index" class="image-thumbnail">
                <img :src="image.data" alt="Captured image" @click="openImagePreview(index)" />
                <!-- <button @click="deleteImage(index)" class="delete-image-btn"> -->
                  <button @click="deleteImageByRef(image)" class="delete-image-btn">
                  <i class="fas fa-trash"></i>
                </button>
                <span class="image-timestamp">{{ image.timestamp }}</span>
                <span class="view-image-hint"><i class="fas fa-search-plus"></i></span>
              </div>
            </div>
          </div>
          
          <!-- Camera/Scanner View - only if camera is enabled -->
          <div v-if="enableCamera" class="scanner-view" :class="{ 'compact-view': isCompactMode, 'active-camera': scannerCameraActive }">

            <!-- When camera is disabled -->
            <div v-if="currentStep" class="scanner-disabled-overlay">
              <!-- <p>Camera disabled until serial number tracking step</p> -->
            </div>

            <!-- Product Thumbnails Panel -->
            <div v-if="productThumbnails.length >= 2" class="scanner-product-thumbnails-container">
              <!-- First image preview (always visible) -->
              <div 
                v-if="productThumbnails.length > 0"
                class="scanner-default-thumbnail"
                @click="openProductImagePreview(0)"
              >
                <img :src="productThumbnails[0].src" alt="Product image" />
                <div class="scanner-thumbnail-label">
                  <i class="fas fa-search-plus"></i> View All ({{ productThumbnails.length }})
                </div>
              </div>
            </div>

            <!-- Product Image Preview Modal with unique class names -->
            <div v-if="showProductImageModal" class="scanner-product-image-modal" @click="closeProductImagePreview">
              <div class="scanner-product-image-content" @click.stop>
                <div class="scanner-product-image-header">
                  <h3>Product Image {{ currentProductImageIndex + 1 }}/{{ productThumbnails.length }}</h3>
                  <button @click="closeProductImagePreview" class="scanner-close-preview-btn">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="scanner-product-image-body">
                  <div class="scanner-product-image-container">
                    <img :src="currentProductImage.src" alt="Product image" class="scanner-preview-image" />
                  </div>
                  <div class="scanner-product-image-controls">
                    <button @click="prevProductImage" :disabled="currentProductImageIndex === 0" class="scanner-nav-btn scanner-prev-btn">
                      <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="scanner-image-info">
                      <span class="scanner-image-label" v-if="currentProductImage.label">{{ currentProductImage.label }}</span>
                    </div>
                    <button @click="nextProductImage" :disabled="currentProductImageIndex >= productThumbnails.length - 1" class="scanner-nav-btn scanner-next-btn">
                      <i class="fas fa-chevron-right"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- When camera is inactive, show the grid overlay -->
            <div v-if="!scannerCameraActive" class="scanner-overlay">
              <div class="scanner-corner top-left"></div>
              <div class="scanner-corner top-right"></div>
              <div class="scanner-corner bottom-left"></div>
              <div class="scanner-corner bottom-right"></div>
            </div>
            
            <!-- When camera is active, show the live camera feed here -->
            <video
              v-if="scannerCameraActive"
              id="scanner-camera-preview"
              autoplay
              playsinline
              @click="tapToFocus"
            />

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
                    {{ key }}: 
                    <span v-if="key === 'Status' && scan.StatusClass" :class="scan.StatusClass">{{ value }}</span>
                    <span v-else>{{ value }}</span>
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
            <button @click="closeScannerModal" class="done-button">Exit</button>
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

      <div v-if="isProcessing" class="loading-overlay">
        <div class="loading-content">
          <div class="spinner">
            <div class="bounce1"></div>
            <div class="bounce2"></div>
            <div class="bounce3"></div>
          </div>
          <div class="loading-text">{{ loadingMessage }}</div>
        </div>
      </div>


    </div>
  </div>
</template>

<script>
import ScannerMixin from './ScannerMixin.js';

const FREE_CAPTURE_LIMIT = 12;

export default {
  name: 'ScannerComponent',
  mixins: [ScannerMixin],
  props: {
    hideButton: {
      type: Boolean,
      default: false
    },

    // ✅ Controls capture behavior:
    // - 'received'  => apply step rules + OCR
    // - 'default'   => free capture
    module: {
      type: String,
      default: 'default'
    }
  },
  data() {
    return {
      isProcessing: false,
      loadingMessage: 'Processing scan...',
      showImagePreviewModal: false,
      currentImageIndex: 0,

      productThumbnails: [],
      showThumbnailsPanel: false,
      showProductImageModal: false,
      currentProductImageIndex: 0,
    };
  },
  computed: {
    hasCustomSubmitButton() {
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

    currentPreviewImage() {
      if (
        this.capturedImages.length > 0 &&
        this.currentImageIndex >= 0 &&
        this.currentImageIndex < this.capturedImages.length
      ) {
        return this.capturedImages[this.currentImageIndex];
      }
      return { data: '', timestamp: '' };
    },

    currentProductImage() {
      if (
        this.productThumbnails.length > 0 &&
        this.currentProductImageIndex >= 0 &&
        this.currentProductImageIndex < this.productThumbnails.length
      ) {
        return this.productThumbnails[this.currentProductImageIndex];
      }
      return { src: '', label: '' };
    },

    hasCapturedImage() {
      return this.capturedImages && this.capturedImages.length > 0
    },
    currentStep() {
      return this.$parent?.currentStep ?? 0;
    },

    imagesForCurrentStep() {
      return this.capturedImages.filter(
        img => img.step === this.currentStep
      );
    },

    maxImagesForCurrentStep() {
      // Step 1: Tracking images
      if (this.currentStep === 1) return 2;

      // Step 2: Product images
      if (this.currentStep === 2) return this.maxImages;

      // Default (serials etc.)
      return 1;
    }
    
  },
  methods: {
    openScannerModal() {
      this.showScannerModal = true;
      this.$emit('scanner-opened');
    },

    openImagePreview(index) {
      this.currentImageIndex = index;
      this.showImagePreviewModal = true;
      document.body.style.overflow = 'hidden';
    },

    closeImagePreview() {
      this.showImagePreviewModal = false;
      document.body.style.overflow = '';
    },

    prevImage() {
      if (this.currentImageIndex > 0) this.currentImageIndex--;
    },

    nextImage() {
      if (this.currentImageIndex < this.capturedImages.length - 1) {
        this.currentImageIndex++;
      }
    },

    showLoadingState(message = 'Processing scan...') {
      this.isProcessing = true;
      this.loadingMessage = message;
    },

    hideLoadingState() {
      this.isProcessing = false;
    },

    startLoading(message) {
      this.showLoadingState(message);
    },

    stopLoading() {
      this.hideLoadingState();
    },

    deleteCurrentImage() {
      if (this.capturedImages.length > 0) {
        this.deleteImage(this.currentImageIndex);

        if (this.currentImageIndex >= this.capturedImages.length) {
          this.currentImageIndex = Math.max(0, this.capturedImages.length - 1);
        }

        if (this.capturedImages.length === 0) {
          this.closeImagePreview();
        }
      }
    },

    setExistingTrackingImages(images = []) {
        images.forEach(img => {
            this.capturedImages.push({
                data: img.src,
                step: 1,
                reused: true,
                timestamp: "reused"
            });
        });
    },

    // =========================
    // ✅ FREE CAPTURE (default)
    // =========================
    async captureFree() {
      const video = document.getElementById('scanner-camera-preview');
      if (!video || !this.scannerCameraActive) return;

      // 🚫 Limit free capture to 12 images
      if (this.capturedImages.length >= FREE_CAPTURE_LIMIT) {
        this.showScanWarning(`Maximum of ${FREE_CAPTURE_LIMIT} images allowed.`);
        return;
      }

      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;

      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

      const timestamp = new Date().toLocaleTimeString();
      const dataUrl = canvas.toDataURL('image/jpeg');

      this.capturedImages.push({
        data: dataUrl,
        timestamp
      });

      this.showScanSuccess('Image captured.');
    },

    // ===================================
    // ✅ OCR helper (used by Received only)
    // ===================================
    async detectSerialFromCanvas(canvas, currentStep) {
      try {
        this.showScanSuccess('Detecting serial number...');

        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg'));
        if (!blob) throw new Error('Failed to create image blob');

        const formData = new FormData();
        formData.append('file', blob, 'capture.jpg');

        const baseURL =
          location.hostname === 'localhost' || location.hostname === '127.0.0.1'
            ? 'http://127.0.0.1:8001'
            : '/fastapi';

        const response = await fetch(`${baseURL}/detect`, {
          method: 'POST',
          body: formData
        });

        if (!response.ok) throw new Error(`OCR request failed (${response.status})`);

        const result = await response.json();

        // Ensure parent container exists
        if (this.$parent) {
          if (!this.$parent.apiResult) {
            this.$parent.apiResult = { step3: null, step4: null };
          }

          if (currentStep === 3) {
            this.$parent.apiResult.step3 = result;
          } else if (currentStep === 4) {
            this.$parent.apiResult.step4 = result;
          }
        }

        const detectedSerial = result?.serials?.[0];

        if (detectedSerial) {
          if (this.$parent) {
            if (currentStep === 3) this.$parent.firstSerialNumber = detectedSerial;
            if (currentStep === 4) this.$parent.secondSerialNumber = detectedSerial;
          }

          this.showScanSuccess(
            currentStep === 3
              ? `✅ Serial #1 detected: ${detectedSerial}`
              : `✅ Serial #2 detected: ${detectedSerial}`
          );
        } else {
          this.showScanWarning('⚠️ No serial detected.');
        }
      } catch (err) {
        console.error('OCR API error:', err);
        this.showScanError('❌ Serial detection failed.');
      }
    },

    // ==========================================
    // ✅ Dispatcher: decides which capture to run
    // ==========================================
    async captureFromScanner() {
      // Extra safety guard
      if (!this.scannerCameraActive) return;

      if (this.module === 'received') {
        return this.captureReceived();
      }

      if (this.module === 'returnscanner') {
         return this.captureReturnScanner();
    }
      return this.captureFree();
    },

    // ============================
    // Existing thumbnails functions
    // ============================
    loadProductThumbnails(productData) {
      this.productThumbnails = [];
      const basePath = '/images/thumbnails/';

      const imageFields = [
        { field: 'img1', label: 'Image 1' },
        { field: 'img2', label: 'Image 2' },
        { field: 'img3', label: 'Image 3' },
        { field: 'img4', label: 'Image 4' },
        { field: 'img5', label: 'Image 5' },
        { field: 'img6', label: 'Image 6' },
        { field: 'img7', label: 'Image 7' },
        { field: 'img8', label: 'Image 8' },
        { field: 'img9', label: 'Image 9' },
        { field: 'img10', label: 'Image 10' },
        { field: 'img11', label: 'Image 11' },
        { field: 'img12', label: 'Image 12' },
        { field: 'img13', label: 'Image 13' },
        { field: 'img14', label: 'Image 14' },
        { field: 'img15', label: 'Image 15' }
      ];

      imageFields.forEach(item => {
        if (productData && productData[item.field]) {
          this.productThumbnails.push({
            src: basePath + productData[item.field],
            label: item.label
          });
        }
      });
    },

    openProductImagePreview(index) {
      this.currentProductImageIndex = index;
      this.showProductImageModal = true;
      document.body.style.overflow = 'hidden';
    },

    closeProductImagePreview() {
      this.showProductImageModal = false;
      document.body.style.overflow = '';
    },

    prevProductImage() {
      if (this.currentProductImageIndex > 0) this.currentProductImageIndex--;
    },

    nextProductImage() {
      if (this.currentProductImageIndex < this.productThumbnails.length - 1) {
        this.currentProductImageIndex++;
      }
    },

    clearProductThumbnails() {
      this.productThumbnails = [];
      this.showProductImageModal = false;
    },

    // ==================================
    // ✅ RECEIVED CAPTURE (restricted flow)
    // ==================================
    async captureReceived() {
      const video = document.getElementById('scanner-camera-preview');
      if (!video || !this.scannerCameraActive) return;

      const currentStep = this.$parent?.currentStep ?? 0;

      // 🚫 Step 5+: Not allowed anymore
      if (currentStep >= 5) {
        this.showScanWarning('Capture is not allowed beyond Serial number detection.');
        return;
      }

      // 🚫 Step 1: Not allowed
      // 🚫 Step 0 or invalid
      // if (currentStep < 1) {
      //   this.showScanWarning('Please start with tracking verification.');
      //   return;
      // }
      // 🚫 Standard capture permission gate
      // if (!this.canCaptureImage()) {
      //   this.showScanWarning(
      //     'Please verify tracking and follow the capture limits.'
      //   );
      //   return;
      // }

      // 🚫 STEP 1 — Tracking capture rules
      if (currentStep === 1) {

        // Must verify tracking first
        if (!this.$parent?.trackingFound) {
          this.showScanWarning('Please verify tracking number first.');
          return;
        }

        // If tracking image already exists (reused from DB)
        const hasReusedTracking = this.capturedImages.some(
          img => img.step === 1 && img.reused === true
        );

        if (hasReusedTracking) {
          this.showScanWarning('Tracking image already exists. Reuse is enabled.');
          return;
        }

        // Allow only ONE tracking image
        const trackingImages = this.capturedImages.filter(img => img.step === 1);
        if (trackingImages.length >= 2) {
          this.showScanWarning('Only one tracking image is allowed.');
          return;
        }
      }

      // ✅ Step 2 limit
      if (currentStep === 2 && this.capturedImages.length >= this.maxImages) {
        this.showScanError(`Maximum of ${this.maxImages} product images allowed.`);
        return;
      }

      // ✅ Step 3: only one
      if (currentStep === 3 && this.capturedImages.some(img => img.step === 3)) {
        this.showScanWarning('Only one image allowed for the first serial number.');
        return;
      }

      // ✅ Step 4: only one
      if (currentStep === 4 && this.capturedImages.some(img => img.step === 4)) {
        this.showScanWarning('Only one image allowed for the second serial number.');
        return;
      }

      // ✅ Capture
      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;

      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

      const timestamp = new Date().toLocaleTimeString();
      const dataUrl = canvas.toDataURL('image/jpeg');

      this.capturedImages.push({ data: dataUrl, timestamp, step: currentStep });
      this.showScanSuccess('Image captured.');

      // ✅ Step 3–4 OCR
      if (currentStep === 3 || currentStep === 4) {
        await this.detectSerialFromCanvas(canvas, currentStep);
      }

      setTimeout(() => {
        this.showSuccessNotification = false;
      }, 2000);
    },

    deleteImageByRef(image) {
      const index = this.capturedImages.indexOf(image);
      if (index !== -1) {
        this.capturedImages.splice(index, 1);
      }
    },

    canCaptureImage() {
      const parent = this.$parent;
      if (!parent) return false;

      const currentStep = parent.currentStep;
      const trackingFound = parent.trackingFound === true;

      // 🚫 Must have verified tracking
      if (!trackingFound) {
        return false;
      }

      // 🚫 Do not allow capture beyond serial steps
      if (currentStep >= 5) {
        return false;
      }

      // ✅ Step 1: Tracking images (max 2)
      // if (currentStep === 1) {
      //   const trackingImages = this.capturedImages.filter(
      //     img => img.step === 1
      //   );
      //   return trackingImages.length < 2;
      // }

      // Step 1 handled directly in captureReceived()
      if (currentStep === 1) {
        return true;
      }


      // ✅ Step 2: Product images (respect maxImages)
      if (currentStep === 2) {
        return this.capturedImages.length < this.maxImages;
      }

      // ✅ Step 3 & 4 handled elsewhere (serial rules)
      return true;
    },
    
   //return scanner condition 
  async captureReturnScanner() {
    const video = document.getElementById('scanner-camera-preview');
    if (!video || !this.scannerCameraActive) return;

    // Get the capture step from parent
    const currentCaptureStep = this.$parent?.currentCaptureStep ?? 0;
    
    console.log('📸 Return Scanner Capture:', {
        currentCaptureStep,
        capturedImagesLength: this.capturedImages.length
    });

    // Limit to 12 images per serial
    if (this.capturedImages.length >= 12) {
        this.showScanWarning('Maximum of 12 images per serial allowed.');
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const timestamp = new Date().toLocaleTimeString();
    const dataUrl = canvas.toDataURL('image/jpeg');

    // ✅ FIXED: Get serial data directly from parent at capture time
    let serialData = {
        serial: null,
        serialIndex: null
    };

    // Get the correct serial based on current capture step
    if (this.$parent) {
        switch (currentCaptureStep) {
            case 1:
                serialData.serial = this.$parent.serialNumber || null;
                serialData.serialIndex = 1;
                break;
            case 2:
                serialData.serial = this.$parent.secondSerialNumber || null;
                serialData.serialIndex = 2;
                break;
            case 3:
                serialData.serial = this.$parent.thirdSerialNumber || null;
                serialData.serialIndex = 3;
                break;
            case 4:
                serialData.serial = this.$parent.fourthSerialNumber || null;
                serialData.serialIndex = 4;
                break;
            default:
                console.warn('⚠️ Capture step is 0, image will not be associated with a serial');
                break;
        }
    }

    // Store captured image with all metadata
    const captureData = {
        data: dataUrl,
        timestamp,
        captureStep: currentCaptureStep,
        serial: serialData.serial,
        serialIndex: serialData.serialIndex
    };

    this.capturedImages.push(captureData);

    console.log(`✅ Image captured for Return Scanner`, {
        step: currentCaptureStep,
        serial: serialData.serial,
        serialIndex: serialData.serialIndex,
        totalImages: this.capturedImages.length
    });

    setTimeout(() => {
        this.showSuccessNotification = false;
    }, 2000);
}

    
  }
};
</script>

<style>
/* newly added */
.pass-fail-buttons {
    display: flex;
    width: 100%;
    justify-content: space-between;
}

.step-btn {
    width: 49%;
    color: #fff; 
    padding: 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}

.step-btn i{
    color: #fff; 
}

button.pass-button.step-btn {
    background-color: #0d6efd;
}

button.fail-button.step-btn {
    background-color: #dc3545;
}
.serial-result-wrap {
    display: flex;
    align-content: center;
    align-items: center;
}
.serial-btn {
    background-color: #0d6efd;
    border: 0 #fff !important;
}
/* Thumbnails grid */
.product-thumbnails-grid {
  @apply grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mt-3;
}
.product-thumbnail {
  @apply border rounded-lg shadow-sm overflow-hidden cursor-pointer transition-transform;
}
.product-thumbnail:hover {
  @apply scale-105;
}
.product-thumbnail img {
  @apply w-full h-32 object-cover;
}
.thumbnail-label {
  @apply text-center text-sm bg-gray-100 py-1;
}
.no-images {
  @apply text-center text-gray-500 mt-4;
}

/* Modal styling */
.image-preview-modal {
  @apply fixed inset-0 bg-black/80 flex items-center justify-center z-50;
}
.image-preview-content {
  @apply relative bg-white rounded-xl p-2 max-w-3xl w-full flex flex-col items-center;
}
.modal-image {
  @apply max-h-[80vh] object-contain;
}
.close-btn {
  @apply absolute top-2 right-2 text-gray-700 hover:text-black;
}
.modal-controls {
  @apply flex justify-between w-full mt-2;
}
.nav-btn {
  @apply bg-gray-200 hover:bg-gray-300 rounded-full p-2;
}
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
  z-index: 2500;
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
  color: white;
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
  max-height: 70vh;
  object-fit: contain;
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

.scanner-close-preview-btn {
  background: none;
  border: none;
  color: white;
  font-size: 18px;
  cursor: pointer;
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
    /* height: 180px; */
    height: 380px;
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