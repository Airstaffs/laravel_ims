// ScannerMixin.js
export default {
  props: {
    scannerTitle: {
      type: String,
      default: 'Scanner'
    },
    storagePrefix: {
      type: String,
      default: 'scanner'
    },
    enableCamera: {
      type: Boolean,
      default: false
    },
    displayFields: {
      type: Array,
      default: () => ['code']
    },
    apiEndpoint: {
      type: String,
      default: ''
    },
    initialMode: {
      type: String,
      default: 'auto' // 'auto' or 'manual'
    }
  },
  data() {
    return {
      // Scanner state
      showScannerModal: false,
      showManualInput: this.initialMode === 'manual', // Default is auto mode
      isCompactMode: false,
      showSuccessNotification: false,
      showErrorNotification: false,
      scanErrorMessage: '',
      lastScannedItem: '',
      
      // Camera state
      scannerCameraActive: false,
      showCameraModal: false,
      capturedImages: [],
      previewImages: true,
      maxImages: 12,
      isCameraBeingReleased: false,
      
      // Scan statistics
      totalScanned: 0,
      successfulScans: 0,
      failedScans: 0,
      recentScans: [],
      showScans: true,

      isMobileDevice: false

    };
  },
  methods: {

     // Detect if the device is mobile
  detectMobileDevice() {
    // Simple mobile detection logic
    const userAgent = navigator.userAgent || navigator.vendor || window.opera;
    this.isMobileDevice = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(userAgent.toLowerCase());
  },


  setupKeyboardControl() {
    // Skip if not a mobile device
    if (!this.isMobileDevice) return;
    
    // Find all input elements in the scanner modal
    const inputs = this.$el.querySelectorAll('input[type="text"], input:not([type])');
    
    inputs.forEach(input => {
      if (!this.showManualInput) {
        // Auto mode: Prevent keyboard by using the inputmode attribute
        input.setAttribute('inputmode', 'none');
      } else {
        // Manual mode: Allow keyboard
        input.setAttribute('inputmode', 'text');
      }
    });
  },

    // Modal controls
    openScannerModal() {
      this.showScannerModal = true;
      
      // Start in auto mode by default, unless initialMode prop specifies manual
      this.showManualInput = this.initialMode === 'manual';
      
      // Emit initial mode
      this.$emit('mode-changed', { manual: this.showManualInput });

      this.detectMobileDevice();
      
      // Apply keyboard control for mobile devices
      if (this.isMobileDevice) {
        this.$nextTick(() => {
          this.setupKeyboardControl();
        });
      }

      
      // Activate camera if enabled and in auto mode
      if (this.enableCamera && !this.showManualInput) {
        this.$nextTick(() => {
          this.startScanner();
        });
      }
      
      // Load previous scans from storage
      this.loadScans();
      
      // Emit event
      this.$emit('scanner-opened');
    },
    
    closeScannerModal() {
      // Check if any camera is active and stop it
      if (this.scannerCameraActive) {
        this.stopScanner();
      }
      
      this.showScannerModal = false;
      this.showCameraModal = false;
      
      // Save scans to storage
      this.saveScans();
      
      // Emit event
      this.$emit('scanner-closed');
    },
    
    // Toggle between auto and manual modes
    toggleManualInput() {
      this.showManualInput = !this.showManualInput;

      // Apply keyboard control for mobile devices
      if (this.isMobileDevice) {
        this.$nextTick(() => {
          this.setupKeyboardControl();
        });
      }

      
      // Emit mode change event
      this.$emit('mode-changed', { manual: this.showManualInput });
      
      // If switching to auto mode, start the scanner
      if (!this.showManualInput && this.enableCamera) {
        this.startScanner();
      } else if (this.scannerCameraActive) {
        // If switching to manual mode, stop the scanner
        this.stopScanner();
      }
    },
    
    // Process a scan (called from hardware scanner or auto mode)
    processScan() {
      // Emit event to let parent component handle it
      this.$emit('process-scan');
    },
    
    // Scanner camera controls
    startScanner() {
      if (!this.enableCamera) return;
      
      // Start scanner camera here
      this.scannerCameraActive = true;
      
      // Example: Access the camera via navigator.mediaDevices
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
          const video = document.getElementById('scanner-camera-preview');
          if (video) {
            video.srcObject = stream;
          }
        })
        .catch(error => {
          console.error('Camera error:', error);
          this.showScanError('Could not access camera');
          this.scannerCameraActive = false;
        });
    },
    
    stopScanner() {
      if (!this.scannerCameraActive) return;
      
      this.isCameraBeingReleased = true;
      
      // Stop the scanner camera
      const video = document.getElementById('scanner-camera-preview');
      if (video && video.srcObject) {
        const tracks = video.srcObject.getTracks();
        tracks.forEach(track => track.stop());
        video.srcObject = null;
      }
      
      this.scannerCameraActive = false;
      
      // Add a small delay to avoid rapid camera restart attempts
      setTimeout(() => {
        this.isCameraBeingReleased = false;
      }, 1000);
    },
    
    restartCamera() {
      if (this.isCameraBeingReleased) return;
      
      this.stopScanner();
      setTimeout(() => {
        this.startScanner();
      }, 500);
    },
    
    // Toggle camera modal
    toggleCamera() {
      if (this.showCameraModal) {
        this.closeCameraModal();
      } else {
        this.openCameraModal();
      }
    },
    
    openCameraModal() {
      this.showCameraModal = true;
      
      // Start camera
      setTimeout(() => {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
          .then(stream => {
            const video = document.getElementById('camera-preview');
            if (video) {
              video.srcObject = stream;
            }
          })
          .catch(error => {
            console.error('Camera modal error:', error);
            this.showScanError('Could not access camera for photos');
            this.closeCameraModal();
          });
      }, 100);
    },
    
    closeCameraModal() {
      // Stop the camera
      const video = document.getElementById('camera-preview');
      if (video && video.srcObject) {
        const tracks = video.srcObject.getTracks();
        tracks.forEach(track => track.stop());
        video.srcObject = null;
      }
      
      this.showCameraModal = false;
    },
    
    // Capture image from camera modal
    captureImage() {
      const video = document.getElementById('camera-preview');
      if (!video) return;
      
      // Check if we've reached the max images
      if (this.capturedImages.length >= this.maxImages) {
        this.showScanError(`Maximum of ${this.maxImages} images allowed`);
        return;
      }
      
      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      
      // Get current timestamp
      const now = new Date();
      const timestamp = now.toLocaleTimeString();
      
      // Add image to captured images
      this.capturedImages.push({
        data: canvas.toDataURL('image/jpeg'),
        timestamp: timestamp
      });
      
      // Show success notification
      this.showSuccessNotification = true;
      this.lastScannedItem = 'Image captured';
      
      setTimeout(() => {
        this.showSuccessNotification = false;
      }, 2000);
      
      // Close modal if we've reached the max
      if (this.capturedImages.length >= this.maxImages) {
        this.closeCameraModal();
      }
    },
    
    // Capture from the scanner camera
    captureFromScanner() {
      const video = document.getElementById('scanner-camera-preview');
      if (!video || !this.scannerCameraActive) return;
      
      // Check if we've reached the max images
      if (this.capturedImages.length >= this.maxImages) {
        this.showScanError(`Maximum of ${this.maxImages} images allowed`);
        return;
      }
      
      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      
      // Get current timestamp
      const now = new Date();
      const timestamp = now.toLocaleTimeString();
      
      // Add image to captured images
      this.capturedImages.push({
        data: canvas.toDataURL('image/jpeg'),
        timestamp: timestamp
      });
      
      // Show success notification
      this.showSuccessNotification = true;
      this.lastScannedItem = 'Image captured';
      
      setTimeout(() => {
        this.showSuccessNotification = false;
      }, 2000);
    },
    
    // Delete captured image
    deleteImage(index) {
      this.capturedImages.splice(index, 1);
    },
    
    // Toggle compact mode
    toggleCompactMode() {
      this.isCompactMode = !this.isCompactMode;
    },
    
    // Toggle image preview
    toggleImagePreview() {
      this.previewImages = !this.previewImages;
    },
    
    // Show notifications
    showScanSuccess(item) {
      this.lastScannedItem = item;
      this.showSuccessNotification = true;
      this.showErrorNotification = false;
      this.successfulScans++;
      this.totalScanned++;
      
      // Clear after delay
      setTimeout(() => {
        this.showSuccessNotification = false;
      }, 3000);
    },
    
    showScanError(message) {
      this.scanErrorMessage = message;
      this.showErrorNotification = true;
      this.showSuccessNotification = false;
      this.failedScans++;
      
      // Clear after delay
      setTimeout(() => {
        this.showErrorNotification = false;
      }, 3000);
    },
    
    // Reset scanner
    resetScanner() {
      // Reset scan counts
      this.totalScanned = 0;
      this.successfulScans = 0;
      this.failedScans = 0;
      
      // Clear scans
      this.recentScans = [];
      
      // Clear captured images
      this.capturedImages = [];
      
      // Reset auto mode if camera is enabled
      if (this.enableCamera) {
        this.showManualInput = false;
        // Emit mode changed event
        this.$emit('mode-changed', { manual: false });
        this.restartCamera();

             
        // Apply keyboard control for mobile devices
        if (this.isMobileDevice) {
          this.$nextTick(() => {
            this.setupKeyboardControl();
          });
        }
      }

      
      
      // Clear notifications
      this.showSuccessNotification = false;
      this.showErrorNotification = false;
      
      // Emit event
      this.$emit('scanner-reset');
      
      // Clear local storage
      localStorage.removeItem(`${this.storagePrefix}_scans`);
      localStorage.removeItem(`${this.storagePrefix}_stats`);
    },
    
    // Scanned items list
    toggleScansVisibility() {
      this.showScans = !this.showScans;
    },
    
    // Format scan object to get display fields
    getScanDisplayFields(scan) {
      const displayObj = {};
      this.displayFields.forEach(field => {
        if (scan[field] !== undefined) {
          displayObj[field] = scan[field];
        }
      });
      return displayObj;
    },
    
    // Add success scan to history
    addSuccessScan(scanData) {
      const now = new Date();
      const time = now.toLocaleTimeString();
      
      // Create scan object
      const scan = {
        ...scanData,
        success: true,
        time: time
      };
      
      // Add to recent scans, limit to 20
      this.recentScans.unshift(scan);
      if (this.recentScans.length > 20) {
        this.recentScans.pop();
      }
      
      // Save to storage
      this.saveScans();
    },
    
    // Add error scan to history
    addErrorScan(scanData, reason) {
      const now = new Date();
      const time = now.toLocaleTimeString();
      
      // Create scan object
      const scan = {
        ...scanData,
        success: false,
        reason: reason,
        time: time
      };
      
      // Add to recent scans, limit to 20
      this.recentScans.unshift(scan);
      if (this.recentScans.length > 20) {
        this.recentScans.pop();
      }
      
      // Save to storage
      this.saveScans();
    },

    // Show warning notification (for already scanned items)
showScanWarning(message) {
  this.scanErrorMessage = message;
  this.showErrorNotification = true;
  this.showSuccessNotification = false;
  
  // Get the notification element and change its class
  this.$nextTick(() => {
    const notificationEl = document.querySelector('.top-notification.error');
    if (notificationEl) {
      notificationEl.classList.remove('error');
      notificationEl.classList.add('warning');
      
      // Find the icon and change it to a warning icon
      const iconEl = notificationEl.querySelector('i');
      if (iconEl) {
        iconEl.classList.remove('fa-exclamation-circle');
        iconEl.classList.add('fa-exclamation-triangle');
      }
    }
  });
  
  // Clear after delay (longer for warnings - 5 seconds)
  setTimeout(() => {
    this.showErrorNotification = false;
    
    // Reset the class when hiding
    this.$nextTick(() => {
      const warningEl = document.querySelector('.top-notification.warning');
      if (warningEl) {
        warningEl.classList.remove('warning');
        warningEl.classList.add('error');
        
        // Reset the icon
        const iconEl = warningEl.querySelector('i');
        if (iconEl) {
          iconEl.classList.remove('fa-exclamation-triangle');
          iconEl.classList.add('fa-exclamation-circle');
        }
      }
    });
  }, 5000); // 5 seconds for warnings (longer than regular errors)
  
  // Add to failed scans count since it's not a successful scan
  this.failedScans++;
},
    
    // Storage methods
    saveScans() {
      // Save scan history
      localStorage.setItem(`${this.storagePrefix}_scans`, JSON.stringify(this.recentScans));
      
      // Save statistics
      const stats = {
        total: this.totalScanned,
        success: this.successfulScans,
        failed: this.failedScans
      };
      localStorage.setItem(`${this.storagePrefix}_stats`, JSON.stringify(stats));
    },
    
    loadScans() {
      // Load scan history
      const savedScans = localStorage.getItem(`${this.storagePrefix}_scans`);
      if (savedScans) {
        try {
          this.recentScans = JSON.parse(savedScans);
        } catch (e) {
          console.error('Error parsing saved scans:', e);
        }
      }
      
      // Load statistics
      const savedStats = localStorage.getItem(`${this.storagePrefix}_stats`);
      if (savedStats) {
        try {
          const stats = JSON.parse(savedStats);
          this.totalScanned = stats.total || 0;
          this.successfulScans = stats.success || 0;
          this.failedScans = stats.failed || 0;
        } catch (e) {
          console.error('Error parsing saved stats:', e);
        }
      }
    }
  },
  
  // Lifecycle hooks
  mounted() {
    // Load scans from storage when component mounts
    this.loadScans();
       // Detect if on mobile device
    this.detectMobileDevice();
  },
  
  updated() {
    // Re-apply keyboard control if inputs were added/changed on mobile
    if (this.showScannerModal && this.isMobileDevice) {
      this.setupKeyboardControl();
    }
  },
  

  beforeDestroy() {
    // Clean up resources if component is destroyed
    if (this.scannerCameraActive) {
      this.stopScanner();
    }
    
    if (this.showCameraModal) {
      this.closeCameraModal();
    }
    
    // Save scans when component is destroyed
    this.saveScans();
  }
};