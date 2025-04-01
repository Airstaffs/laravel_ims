<template>
  <div class="vue-container">
    <h1 class="vue-title">Stockroom Module</h1>
    
    <!-- Top Notification Area - NEW -->
    <div class="top-notification-container">
      <div v-if="showSuccessNotification && !showScannerModal" class="top-notification success">
        <i class="fas fa-check-circle"></i> Successfully scanned: {{ lastScannedItem }}
      </div>
      <div v-if="showErrorNotification && !showScannerModal" class="top-notification error">
        <i class="fas fa-exclamation-circle"></i> {{ scanErrorMessage }}
      </div>
    </div>
    
    <!-- Scanner Button -->
    <div class="scanner-container">
      <button @click="openScannerModal" class="scanner-button">
        <i class="fas fa-barcode"></i>
      </button>
      <span v-if="totalScanned > 0" class="scan-count">
        {{ totalScanned }}
      </span>
    </div>
    
    <!-- Pagination -->
    <div class="pagination">
      <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
        Back
      </button>
      <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
      <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">
        Next
      </button>

      <select v-model="perPage" @change="changePerPage">
        <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
          {{ option }}
        </option>
      </select>
    </div>

    <div class="table-container">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>
              <input type="checkbox" @click="toggleAll" v-model="selectAll" />
              <a style="color: black" @click="sortBy('AStitle')" class="sortable">
                Product Name
                <span v-if="sortColumn === 'AStitle'">
                  {{ sortOrder === "asc" ? "▲" : "▼" }}
                </span>
              </a>
              <span style="margin-right: 20px"></span>
              <a style="color: black" @click="sortBy('rtcounter')" class="sortable">
                RT counter
                <span v-if="sortColumn === 'rtcounter'">
                  {{ sortOrder === "asc" ? "▲" : "▼" }}
                </span>
              </a>

              <span style="margin-right: 20px"></span>

              <button class="Desktop" style="
                                    border: solid 1px black;
                                    background-color: aliceblue;
                                " @click="toggleDetailsVisibility">
                {{
                  showDetails
                    ? "Hide extra columns"
                    : "Show extra columns"
                }}
              </button>
            </th>
            <th class="Desktop">Location</th>
            <th class="Desktop">Added date</th>
            <th class="Desktop">Updated date</th>
            <th class="Desktop">Fnsku</th>
            <th class="Desktop">Msku</th>
            <th class="Desktop">Asin</th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              FBM
            </th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              FBA
            </th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              Outbound
            </th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              Inbound
            </th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              Unfulfillable
            </th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              Reserved
            </th>
            <th class="Desktop">Fulfillment</th>
            <th class="Desktop">Status</th>
            <th class="Desktop">Serialnumber</th>
            <th class="Desktop">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(item, index) in sortedInventory" :key="item.id">
            <tr>
              <td class="vue-details">
                <div class="checkbox-container">
                  <input type="checkbox" v-model="item.checked" />
                  <span class="placeholder-date">{{
                    item.shipBy || ""
                    }}</span>
                </div>
                <div class="product-container">
                  <img src="" alt="Product Image" class="product-thumbnail" />
                  <div class="product-info">
                    <p class="product-name">
                      RT# : {{ item.rtcounter }}
                    </p>
                    <p class="product-name">
                      {{ item.AStitle }}
                    </p>

                    <p class="Mobile">
                      Location :
                      {{ item.warehouselocation }}
                    </p>
                    <p class="Mobile">
                      Added date :
                      {{ item.datedelivered }}
                    </p>
                    <p class="Mobile">
                      Updated date :
                      {{ item.lastDateUpdate }}
                    </p>
                    <p class="Mobile">
                      Fnsku : {{ item.FNSKUviewer }}
                    </p>
                    <p class="Mobile">
                      Msku : {{ item.MSKUviewer }}
                    </p>
                    <p class="Mobile">
                      Asin : {{ item.ASINviewer }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="Desktop">
                <span><strong></strong>
                  {{ item.warehouselocation }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.datedelivered }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.lastDateUpdate }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.FNSKUviewer }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.MSKUviewer }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.ASINviewer }}</span>
              </td>
              
              <!-- Hidden -->  <!-- Hidden -->  <!-- Hidden -->
              <td v-if="showDetails">
                <span><strong></strong>
                  {{ item.FBMAvailable }}</span>
              </td>
              <td v-if="showDetails">
                <span><strong></strong>
                  {{ item.FbaAvailable }}</span>
              </td>
              <td v-if="showDetails">
                <span><strong></strong> {{ item.Outbound }}</span>
              </td>
              <td v-if="showDetails">
                <span><strong></strong> {{ item.Inbound }}</span>
              </td>
              <td v-if="showDetails">
                <span><strong></strong> {{ item.Reserved }}</span>
              </td>
              <td v-if="showDetails">
                <span><strong></strong>
                  {{ item.Unfulfillable }}</span>
              </td>
              <!-- Hidden -->
              <!-- Hidden -->
              <!-- Hidden -->

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.Fulfilledby }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong> {{ item.Status }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.serialnumber }}</span>
              </td>

              <!-- Button for more details -->
              <td class="Desktop">
                {{ item.totalquantity }}
                <button class="btn-moredetails" @click="toggleDetails(index)">
                  {{
                    expandedRows[index]
                      ? "Less Details"
                      : "More Details"
                  }}
                </button>
                <br />
                <button class="btn-moredetails">example</button><br />
                <button class="btn-moredetails">example</button><br />
                <button class="btn-moredetails">example</button><br />
              </td>
            </tr>
            <!-- More details results -->
            <tr v-if="expandedRows[index]">
              <td colspan="11">
                <div class="expanded-content p-3 border rounded">
                  <div class="Mobile">
                    <button class="btn-moredetails">
                      sample button
                    </button>
                    <button class="btn-moredetails">
                      sample button
                    </button>
                    <button class="btn-moredetails">
                      sample button
                    </button>
                  </div>
                  <strong>Product Name:</strong>
                  {{ item.AStitle }}
                </div>
              </td>
            </tr>

            <!-- Button for more details (Mobile) -->
            <td class="Mobile">
              {{ item.totalquantity }}
              <button style="
                                    width: 100%;
                                    border-bottom: 2px solid black;
                                    padding: 0px;
                                " @click="toggleDetails(index)">
                {{
                  expandedRows[index]
                    ? "Less Details ▲ "
                    : "More Details ▼ "
                }}
              </button>
            </td>
          </template>
        </tbody>
      </table>
      <!-- Pagination -->
      <div class="pagination">
        <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
          Back
        </button>
        <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
        <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">Next</button>
      </div>
    </div>
    
    <!-- Scanner Modal -->
    <div v-if="showScannerModal" class="scanner-modal">
  <div class="scanner-modal-content">
    <!-- Scanner Header -->
            <div class="scanner-header">
          <h2>Product Scanner</h2>
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
            <!-- Camera button -->
            <div class="header-actions">
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
      
      <!-- Captured Images Preview -->
      <div class="captured-images-container" v-if="capturedImages.length > 0">
        <div class="images-header" @click="toggleImagePreview">
          <h3>Images ({{ capturedImages.length }}/{{ maxImages }})</h3>
          <span class="toggle-preview">{{ previewImages ? 'Hide' : 'Show' }}</span>
        </div>
        <div v-if="previewImages" class="image-thumbnails">
          <div v-for="(image, index) in capturedImages" :key="index" class="image-thumbnail">
          <img :src="image.data" alt="Captured image" />
          <button @click="deleteImage(index)" class="delete-image-btn">
            <i class="fas fa-trash"></i>
          </button>
          <span class="image-timestamp">{{ image.timestamp }}</span>
        </div>
        </div>
      </div>
      
<!-- Camera/Scanner View -->
<div class="scanner-view" :class="{ 'compact-view': isCompactMode, 'active-camera': scannerCameraActive }">
  <!-- When camera is inactive, show the grid overlay -->
  <div v-if="!scannerCameraActive" class="scanner-overlay">
    <div class="scanner-corner top-left"></div>
    <div class="scanner-corner top-right"></div>
    <div class="scanner-corner bottom-left"></div>
    <div class="scanner-corner bottom-right"></div>
  </div>
  
  <!-- When camera is active, show the live camera feed here -->
  <video v-if="scannerCameraActive" id="scanner-camera-preview" autoplay playsinline></video>
  
  <!-- Replace your existing camera restart overlay with this improved version -->
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
    <div class="capture-count" v-if="scannerCameraActive">
      {{ capturedImages.length }}/{{ maxImages }}
    </div>
    <button class="camera-button" @click="toggleScannerCamera">
      <i class="fas" :class="scannerCameraActive ? 'fa-camera-retro' : 'fa-camera'"></i>
    </button>
    <button v-if="scannerCameraActive" class="capture-button" @click="captureFromScanner">
      <i class="fas fa-camera-retro"></i>
    </button>
    <button class="compact-toggle" @click="toggleCompactMode">
      {{ isCompactMode ? 'Expand' : 'Compact' }}
    </button>
  </div>
</div>
      
      <!-- Input Fields -->
      <div class="input-form">
        <div class="input-group">
          <label>Serial Number:</label>
          <input 
            type="text" 
            v-model="serialNumber" 
            placeholder="Enter Serial Number..." 
            @input="handleInputChange('serial')"
            @keyup.enter="showManualInput ? focusNextField('fnskuInput') : processScan()"
            ref="serialNumberInput"
          />
        </div>
        
        <div class="input-group">
          <label>FNSKU:</label>
          <input 
            type="text" 
            v-model="fnsku" 
            placeholder="Enter FNSKU..." 
            @input="handleInputChange('fnsku')"
            @keyup.enter="showManualInput ? focusNextField('locationInput') : processScan()"
            ref="fnskuInput"
          />
        </div>
        
        <div class="input-group">
          <label>Location:</label>
          <input 
            type="text" 
            v-model="locationInput" 
            placeholder="Enter Location..." 
            @input="handleInputChange('location')"
            @keyup.enter="processScan()"
            ref="locationInput"
          />
        </div>
        
        <!-- Submit button (only in manual mode) -->
        <button v-if="showManualInput" @click="processScan()" class="submit-button">Submit</button>
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
            <li v-for="(scan, index) in recentScans" :key="index" :class="{ 'success': scan.success, 'error': !scan.success }">
              <div class="scan-details">
                <div v-if="scan.serial" class="scan-serial">SN: {{ scan.serial }}</div>
                <div v-if="scan.code" class="scan-code">FNSKU: {{ scan.code }}</div>
                <div v-if="scan.location" class="scan-location">Loc: {{ scan.location }}</div>
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
    
    <!-- Camera Modal - outside scanner-body but inside scanner-modal-content -->
    <div v-if="showCameraModal" class="camera-modal">
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
            <img :src="image.data" alt="Thumbnail" />
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

   </div> 
</template>

<script>
import axios from 'axios';
import { eventBus } from './eventBus'; // Using your event bus
import '../../css/modules.css';
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
  name: "ProductList",
  data() {
    return {
      inventory: [],
      currentPage: 1,
      totalPages: 1,
      perPage: 10, // Default rows per page
      selectAll: false,
      expandedRows: {},
      sortColumn: "",
      sortOrder: "asc",
      showDetails: false,
      
      // Scanner related data
      showScannerModal: false,
      showManualInput: this.loadManualInputPreference(),
      serialNumber: '',
      fnsku: '',
      locationInput: '',
      lastScannedItem: '',
      showSuccessNotification: false,
      showErrorNotification: false,
      scanErrorMessage: '',
      totalScanned: 0,
      successfulScans: 0,
      failedScans: 0,
      recentScans: [],
      scanTimeout: null,
      scanBuffer: '',
      isCompactMode: false,
      showScans: true, // Toggle for Recent Scans visibility

    
    // Camera related data
    modalCameraStream: null,
    scannerCameraActive: false,
    modalCameraActive: false,
    isCameraBeingReleased: false,
    capturedImages: [],
    cameraStream: null,
    showCameraModal: false,
    currentImageIndex: 0,
    maxImages: 10, // Maximum number of images to capture
    
    // Image preview states
    previewImages: false,
    };
  },
  computed: {
    searchQuery() {
      return eventBus.searchQuery;
    },
    sortedInventory() {
      if (!this.sortColumn) return this.inventory;
      return [...this.inventory].sort((a, b) => {
        const valueA = a[this.sortColumn];
        const valueB = b[this.sortColumn];

        if (typeof valueA === "number" && typeof valueB === "number") {
          return this.sortOrder === "asc"
            ? valueA - valueB
            : valueB - valueA;
        }

        return this.sortOrder === "asc"
          ? String(valueA).localeCompare(String(valueB))
          : String(valueB).localeCompare(String(valueA));
      });
    },
  },
  methods: {
    // Save scan data to localStorage
    saveScanData() {
      const scanData = {
        totalScanned: this.totalScanned,
        successfulScans: this.successfulScans,
        failedScans: this.failedScans,
        recentScans: this.recentScans
      };
      localStorage.setItem('stockroomScanData', JSON.stringify(scanData));
    },


    

    // Load scan data from localStorage
    loadScanData() {
      const savedData = localStorage.getItem('stockroomScanData');
      if (savedData) {
        const parsedData = JSON.parse(savedData);
        this.totalScanned = parsedData.totalScanned || 0;
        this.successfulScans = parsedData.successfulScans || 0;
        this.failedScans = parsedData.failedScans || 0;
        this.recentScans = parsedData.recentScans || [];
      }
    },

    // Save manual input preference to localStorage
    saveManualInputPreference() {
      try {
        localStorage.setItem('scannerManualMode', JSON.stringify(this.showManualInput));
      } catch (error) {
        console.error('Error saving scanner mode preference:', error);
      }
    },

    // Load manual input preference from localStorage
    loadManualInputPreference() {
      try {
        const savedMode = localStorage.getItem('scannerManualMode');
        return savedMode !== null ? JSON.parse(savedMode) : false; // Default to auto mode
      } catch (error) {
        console.error('Error loading scanner mode preference:', error);
        return false;
      }
    },

    // Fetch inventory method
    async fetchInventory() {
      try {
        const response = await axios.get(`${API_BASE_URL}/products`, {
          params: { search: this.searchQuery, page: this.currentPage, per_page: this.perPage, location: 'stockroom' }, withCredentials: true
        });

        this.inventory = response.data.data;
        this.totalPages = response.data.last_page;
      } catch (error) {
        console.error("Error fetching inventory data:", error);
      }
    },

    // Pagination methods
    changePerPage() {
      this.currentPage = 1;
      this.fetchInventory();
    },
    prevPage() {
      if (this.currentPage > 1) {
        this.currentPage--;
        this.fetchInventory();
      }
    },
    nextPage() {
      if (this.currentPage < this.totalPages) {
        this.currentPage++;
        this.fetchInventory();
      }
    },

    // Inventory selection methods
    toggleAll() {
      this.inventory.forEach((item) => (item.checked = this.selectAll));
    },
    toggleDetails(index) {
      this.expandedRows = {
        ...this.expandedRows,
        [index]: !this.expandedRows[index],
      };
    },
    toggleDetailsVisibility() {
      this.showDetails = !this.showDetails;
    },

    // Sorting method
    sortBy(column) {
      if (this.sortColumn === column) {
        this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
      } else {
        this.sortColumn = column;
        this.sortOrder = "asc";
      }
    },
    loadFBAInboundShipment() {
      if (window.loadContent) {
        window.loadContent("fbashipmentinbound");
      } else {
        console.error("loadContent not found on window");
      }
    },

    // Toggle manual input mode
    toggleManualInput() {
      this.showManualInput = !this.showManualInput;
      this.saveManualInputPreference();
    },

    // Scanner methods
  
    openScannerModal() {
  // Make sure any existing camera is stopped first
  this.stopCamera();
  this.stopScannerCamera();
  
  this.showScannerModal = true;
  this.initializeScanner();
  
  // Give the DOM time to update before starting camera
  setTimeout(() => {
    this.startScannerCamera();
  }, 300);
  
  // Focus the serial number input
  this.$nextTick(() => {
    if (this.$refs.serialNumberInput) {
      this.$refs.serialNumberInput.focus();
    }
  });
},

    // Update the closeScannerModal method to stop the scanner camera
    closeScannerModal() {
      this.showScannerModal = false;
      this.stopScannerCamera();
      this.stopScanner();
    },
    
    initializeScanner() {
      console.log('Scanner initialized');
      
      // Set up keyboard listener for hardware scanners
      document.addEventListener('keydown', this.handleKeyDown);
    },
    
    stopScanner() {
      console.log('Scanner stopped');
      
      // Remove keyboard listener
      document.removeEventListener('keydown', this.handleKeyDown);
    },
    
    handleKeyDown(event) {
      // Special handling for barcode scanners that send data as keyboard input
      if (this.showScannerModal && !this.showManualInput) {
        if (event.key === 'Enter' && this.scanBuffer) {
          // Process the accumulated buffer as a scan
          this.processScan(this.scanBuffer);
          this.scanBuffer = '';
          event.preventDefault();
        } else if (event.key.length === 1) {
          // Accumulate character into buffer
          this.scanBuffer += event.key;
        }
      }
    },

    // Focus the next input field
    focusNextField(fieldRef) {
      this.$nextTick(() => {
        const nextField = this.$refs[fieldRef];
        if (nextField) {
          nextField.focus();
          nextField.classList.add('input-active');
          setTimeout(() => {
            nextField.classList.remove('input-active');
          }, 1000);
        }
      });
    },
    
    // Handle input change - in auto mode, we process immediately
    handleInputChange(field) {
      // First, try to capture an image if camera is active
      if (this.scannerCameraActive && this.capturedImages.length < this.maxImages && field === 'serial' && this.serialNumber) {
    // Capture image when serial number is entered
          this.captureFromScanner();
        }

      // In automatic mode, only process when we have all required data
      if (!this.showManualInput) {
        if (field === 'location' && this.locationInput) {
          // Only process if we have at least one of serial number or FNSKU
          if (this.serialNumber || this.fnsku) {
            this.processScan();
          } else {
            // Just focus next field if data is incomplete
            this.focusNextField('serialNumberInput');
          }
        } else if (field === 'fnsku' && this.fnsku && !this.locationInput) {
          this.focusNextField('locationInput');
        } else if (field === 'serial' && this.serialNumber && !this.fnsku) {
          this.focusNextField('fnskuInput');
        }
      }
    },
    
    toggleScansVisibility() {
      this.showScans = !this.showScans;
    },

    // Toggle compact mode for scanner view
    toggleCompactMode() {
      this.isCompactMode = !this.isCompactMode;
    },




// Start the scanner camera
startScannerCamera() {
  console.log('Starting scanner camera...');
  
  if (this.isCameraBeingReleased) {
    console.log("Camera is being released, please wait...");
    return;
  }
  
  // Make sure any previous camera is completely stopped
  if (this.cameraStream) {
    console.log('Stopping existing camera stream');
    const tracks = this.cameraStream.getTracks();
    tracks.forEach(track => {
      if (track.readyState === 'live') {
        track.stop();
      }
    });
    this.cameraStream = null;
  }

  // Reset camera states
  this.modalCameraActive = false;
  
  // Wait for browser to release camera resources
  setTimeout(() => {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      console.log('Requesting camera access');
      // Use the CAMERA_CONSTRAINTS here
      navigator.mediaDevices.getUserMedia(CAMERA_CONSTRAINTS)
      .then(stream => {
        console.log('Camera access granted for scanner');
        this.cameraStream = stream;
        this.scannerCameraActive = true;
        
        this.$nextTick(() => {
          const video = document.getElementById('scanner-camera-preview');
          if (video) {
            console.log('Attaching stream to scanner video');
            video.srcObject = stream;
            video.play()
              .then(() => console.log('Scanner video playing'))
              .catch(e => console.error('Error playing scanner video:', e));
          } else {
            console.error('Scanner video element not found');
          }
        });
      })
      .catch(error => {
        console.error('Error accessing scanner camera:', error);
        
        if (error.name === 'NotAllowedError') {
          alert('Camera access denied. Please enable camera permissions and try again.');
        } else if (error.name === 'NotReadableError') {
          alert('Camera may be in use by another application. Please close other camera apps and try again.');
        } else {
          alert('Could not access scanner camera: ' + error.message);
        }
        
        this.scannerCameraActive = false;
      });
    } else {
      alert('Camera access is not supported in this browser.');
    }
  }, 700); // Longer delay to ensure camera is fully released
},


// Update your stopScannerCamera method
stopScannerCamera() {
  if (this.cameraStream) {
    const tracks = this.cameraStream.getTracks();
    tracks.forEach(track => track.stop());
    this.cameraStream = null;
  }
  this.scannerCameraActive = false;
  this.cameraActive = false;
},


toggleCamera() {
  if (this.modalCameraActive) {
    this.stopCamera();
    this.showCameraModal = false;
  } else {
    // Make sure scanner camera is stopped before starting modal camera
    if (this.scannerCameraActive) {
      this.stopScannerCamera();
    }
    this.startCamera();
  }
},

// Update your toggleScannerCamera method
toggleScannerCamera() {
  if (this.scannerCameraActive) {
    this.stopScannerCamera();
  } else {
    this.startScannerCamera();
  }
},

restartCamera() {
  console.log("Attempting to restart camera with page reload fallback...");
  
  // Show a loading state
  this.isCameraBeingReleased = true;
  
  // First try to release camera resources
  if (this.cameraStream) {
    const tracks = this.cameraStream.getTracks();
    tracks.forEach(track => {
      try {
        track.stop();
      } catch (e) {
        console.error("Error stopping track:", e);
      }
    });
    this.cameraStream = null;
  }
  
  // Save current state to sessionStorage to preserve it during reload
  const currentState = {
    capturedImages: this.capturedImages,
    totalScanned: this.totalScanned,
    successfulScans: this.successfulScans,
    failedScans: this.failedScans,
    recentScans: this.recentScans
  };
  sessionStorage.setItem('scannerState', JSON.stringify(currentState));
  
  // First try to restart camera normally
  setTimeout(() => {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      navigator.mediaDevices.getUserMedia({ 
        video: { facingMode: "environment" },
        audio: false 
      })
      .then(stream => {
        console.log('Camera restarted successfully');
        this.cameraStream = stream;
        this.scannerCameraActive = true;
        this.isCameraBeingReleased = false;
        
        this.$nextTick(() => {
          const video = document.getElementById('scanner-camera-preview');
          if (video) {
            video.srcObject = stream;
            video.play().catch(e => console.error('Error playing video:', e));
          }
        });
      })
      .catch(error => {
        console.error('Error restarting camera, will try page reload:', error);
        
        // If camera restart fails, reload the page
        window.location.reload();
      });
    } else {
      // If media devices not supported, reload the page
      window.location.reload();
    }
  }, 1500);
},

closeCameraModal() {
  this.isCameraBeingReleased = true;
  console.log("Aggressively releasing camera resources...");
  
  // Stop the camera with extra checks
  if (this.cameraStream) {
    try {
      const tracks = this.cameraStream.getTracks();
      tracks.forEach(track => {
        try {
          track.stop();
          console.log("Track stopped:", track.kind);
        } catch (e) {
          console.error("Error stopping track:", e);
        }
      });
      this.cameraStream = null;
    } catch (e) {
      console.error("Error stopping camera stream:", e);
    }
  }
  
  this.showCameraModal = false;
  this.modalCameraActive = false;
  this.cameraActive = false;
  
  // Add an extremely long delay to ensure browser fully releases camera
  setTimeout(() => {
    this.isCameraBeingReleased = false;
    console.log("Camera resources should be released now");
  }, 2000);
},


startCamera() {
  console.log('Starting modal camera...');
  
  // Don't stop scanner camera, just hide the video element
  // We'll keep the scannerCameraActive state as is
  
  // Make sure any previous modal camera stream is stopped
  if (this.cameraStream && this.modalCameraActive) {
    const tracks = this.cameraStream.getTracks();
    tracks.forEach(track => track.stop());
    this.cameraStream = null;
  }
  
  // Allow time for camera to be prepared
  setTimeout(() => {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      // Use the CAMERA_CONSTRAINTS here too
      navigator.mediaDevices.getUserMedia(CAMERA_CONSTRAINTS)
      .then(stream => {
        console.log('Camera access granted for modal');
        // Store this as a separate stream
        this.modalCameraStream = stream;
        this.modalCameraActive = true;
        this.showCameraModal = true;
        
        this.$nextTick(() => {
          const video = document.getElementById('camera-preview');
          if (video) {
            console.log('Attaching stream to modal video');
            video.srcObject = stream;
            video.play()
              .then(() => console.log('Modal video playing'))
              .catch(e => console.error('Error playing modal video:', e));
          } else {
            console.error('Modal video element not found');
          }
        });
      })
      .catch(error => {
        console.error('Error accessing camera for modal:', error);
        alert('Could not access the camera. Please make sure camera permissions are granted.');
      });
    } else {
      alert('Camera access is not supported in this browser.');
    }
  }, 300);
},  
stopCamera() {
  if (this.cameraStream) {
    const tracks = this.cameraStream.getTracks();
    tracks.forEach(track => track.stop());
    this.cameraStream = null;
  }
  this.modalCameraActive = false;
  this.cameraActive = false;
},
  
  captureImage() {
    if (this.capturedImages.length >= this.maxImages) {
      alert(`Maximum of ${this.maxImages} images allowed. Please delete an image to capture more.`);
      return;
    }
    
    const video = document.getElementById('camera-preview');
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Convert to data URL (base64)
    const imageData = canvas.toDataURL('image/jpeg', 0.8);
    
    // Add to captured images array
    this.capturedImages.push({
      data: imageData,
      timestamp: new Date().toLocaleTimeString()
    });
    
    // Play capture sound
    this.playCaptureSound();
    
    // Show notification
    this.showSuccessNotification = true;
    this.lastScannedItem = 'Image Captured';
    this.showErrorNotification = false;
    
    // Auto-hide notification after delay
    clearTimeout(this.scanTimeout);
    this.scanTimeout = setTimeout(() => {
      this.showSuccessNotification = false;
    }, 2000);
    
    // If reached max images, close camera
    if (this.capturedImages.length >= this.maxImages) {
      this.closeCameraModal();
    }
  },
  
  deleteImage(index) {
    this.capturedImages.splice(index, 1);
  },
  
  closeCameraModal() {
  this.stopCamera();
  this.showCameraModal = false;
  
  // Reset cameraActive state to false
  this.cameraActive = false;
  
  // Focus back on serial number input
  this.$nextTick(() => {
    if (this.$refs.serialNumberInput) {
      this.$refs.serialNumberInput.focus();
    }
  });
},
  
  toggleImagePreview() {
    this.previewImages = !this.previewImages;
  },
  
  playCaptureSound() {
    // Check if browser supports Audio API
    if ('Audio' in window) {
      // Change this path to your actual capture sound file
      const audio = new Audio('/sounds/camera-shutter.mp3');
      audio.play().catch(e => console.log('Error playing sound:', e));
    }
  },

  captureFromScanner() {
    if (this.capturedImages.length >= this.maxImages) {
    alert(`Maximum of ${this.maxImages} images allowed. Please delete an image to capture more.`);
    return;
  }
  
  const video = document.getElementById('scanner-camera-preview');
  // Change this line - use scannerCameraActive instead of cameraActive
  if (!video || !this.scannerCameraActive) return;

  
  const canvas = document.createElement('canvas');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  
  const context = canvas.getContext('2d');
  context.drawImage(video, 0, 0, canvas.width, canvas.height);
  
  // Convert to data URL (base64)
  const imageData = canvas.toDataURL('image/jpeg', 0.8);
  
  // Add to captured images array
  this.capturedImages.push({
    data: imageData,
    timestamp: new Date().toLocaleTimeString()
  });
  
  // Play capture sound
  this.playCaptureSound();
  
  // Show notification
  this.showSuccessNotification = true;
  this.lastScannedItem = 'Image Captured';
  this.showErrorNotification = false;
  
  // Auto-hide notification after delay
  clearTimeout(this.scanTimeout);
  this.scanTimeout = setTimeout(() => {
    this.showSuccessNotification = false;
  }, 2000);
},
    
    async processScan(scannedCode = null) {
      try {
        // Use either the scanned code (from barcode scanner) or input fields

   if (this.scannerCameraActive && this.capturedImages.length < this.maxImages) {
      this.captureFromScanner();
    }


        let scanSerial, scanCode, scanLocation;
        
        if (scannedCode) {
          // External code passed (from hardware scanner)
          scanSerial = '';
          scanCode = scannedCode;
          scanLocation = this.locationInput || ''; // Use any location input if available
        } else {
          // Use the input fields regardless of mode
          scanSerial = this.serialNumber;
          scanCode = this.fnsku;
          scanLocation = this.locationInput;
          
          // Validate inputs
          if (!scanCode && !scanSerial) {
            this.showErrorNotification = true;
            this.showSuccessNotification = false;
            this.scanErrorMessage = "Serial Number or FNSKU is required";
            this.focusNextField('serialNumberInput');
            return;
          }
        }
        
        // Check for basic validation before sending to server
        if (scanSerial && (!(/^[a-zA-Z0-9]+$/.test(scanSerial)) || scanSerial.includes('X00'))) {
          this.showErrorNotification = true;
          this.showSuccessNotification = false;
          this.scanErrorMessage = "Invalid Serial Number";
          this.failedScans++;
          
          // Add to recent scans
          const now = new Date();
          const timeString = now.toLocaleTimeString();
          
          this.recentScans.unshift({
            serial: scanSerial,
            code: scanCode,
            location: scanLocation,
            time: timeString,
            success: false,
            reason: 'invalid_serial'
          });
          
          // Update total count
          this.totalScanned = this.successfulScans + this.failedScans;
          
          // Save scan data
          this.saveScanData();
          
          return;
        }
        
        // Check location format
        const locationRegex = /^L\d{3}[A-G]$/i;
        if (scanLocation && !locationRegex.test(scanLocation) && scanLocation !== 'Floor' && scanLocation !== 'L800G') {
          this.showErrorNotification = true;
          this.showSuccessNotification = false;
          this.scanErrorMessage = "Invalid Location Format";
          this.failedScans++;
          
          // Add to recent scans
          const now = new Date();
          const timeString = now.toLocaleTimeString();
          
          this.recentScans.unshift({
            serial: scanSerial,
            code: scanCode,
            location: scanLocation,
            time: timeString,
            success: false,
            reason: 'invalid_location'
          });
          
          // Update total count
          this.totalScanned = this.successfulScans + this.failedScans;
          
          // Save scan data
          this.saveScanData();
          
          return;
        }

        const imageData = this.capturedImages.map(img => img.data);
        
        // Send data to server
        const scanData = {
          SerialNumber: scanSerial, 
          FNSKU: scanCode,
          Location: scanLocation,
          Images: imageData
        };
        
        console.log('Sending scan data:', {
        ...scanData, 
        Images: `${imageData.length} images` // Don't log the entire image data
      });
        
        // Send to Laravel API endpoint
        const response = await axios.post('/api/stockroom/process-scan', scanData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        
        const data = response.data;
        console.log('Scan response:', data);
        
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        
        if (data.success) {
          // Success case
          this.lastScannedItem = data.item || 'Item';
          this.showSuccessNotification = true;
          this.showErrorNotification = false;
          this.scanErrorMessage = '';
          this.successfulScans++;
          
          // Play success sound
          this.playSuccessSound();
          this.capturedImages = [];

          // Add to recent scans
          this.recentScans.unshift({
            serial: scanSerial,
            code: scanCode,
            location: scanLocation,
            item: data.item || 'Item',
            time: timeString,
            success: true
          });
          
          // Check if we need to handle reprint
          if (data.needReprint && data.productId) {
            // Open the reprint dialog
            this.handleReprintLabel(data.productId);
          }
        } else {
          // Error case
          this.showErrorNotification = true;
          this.showSuccessNotification = false;
          this.scanErrorMessage = data.message || 'Error processing scan';
          this.failedScans++;
          
          // Play error sound
          this.playErrorSound();
          this.capturedImages = []; // This line clears the images
          
          // Add to recent scans
          this.recentScans.unshift({
            serial: scanSerial,
            code: scanCode,
            location: scanLocation,
            time: timeString,
            success: false,
            reason: data.reason || 'error'
          });
        }
        
        // Keep only last 10 scans
        if (this.recentScans.length > 10) {
          this.recentScans.pop();
        }
        
        this.totalScanned = this.successfulScans + this.failedScans;
        
        // Save scan data to localStorage
        this.saveScanData();
        
        // Clear input and focus first field for next scan
        this.serialNumber = '';
        this.fnsku = '';
        this.locationInput = '';
        this.focusNextField('serialNumberInput');
        
        // Auto-hide notifications after a delay
        clearTimeout(this.scanTimeout);
        this.scanTimeout = setTimeout(() => {
          this.showSuccessNotification = false;
          this.showErrorNotification = false;
        }, 3000);
        
      } catch (error) {
        console.error('Error processing scan:', error);
        
        // Enhanced error logging
        if (error.response) {
          console.error('Response data:', error.response.data);
          console.error('Response status:', error.response.status);
          console.error('Response headers:', error.response.headers);
          
          this.scanErrorMessage = error.response.data.message || 'Server error';
        } else if (error.request) {
          console.error('No response received:', error.request);
          this.scanErrorMessage = 'No response from server';
        } else {
          console.error('Error setting up request:', error.message);
          this.scanErrorMessage = 'Request setup error';
        }
        
        this.showErrorNotification = true;
        this.showSuccessNotification = false;
        this.failedScans++;
        
        // Add failed scan to list
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        this.recentScans.unshift({
          serial: this.serialNumber || '',
          code: this.fnsku || '',
          location: this.locationInput || '',
          time: timeString,
          success: false,
          reason: 'network_error'
        });

        this.totalScanned = this.successfulScans + this.failedScans;
        
        // Save scan data to localStorage
        this.saveScanData();
        
        // Play error sound
        this.playErrorSound();
        
        // Auto-hide notifications after a delay
        clearTimeout(this.scanTimeout);
        this.scanTimeout = setTimeout(() => {
          this.showSuccessNotification = false;
          this.showErrorNotification = false;
        }, 3000);
      }
    },
    
    // Add sound methods
    playSuccessSound() {
      // Check if browser supports Audio API
      if ('Audio' in window) {
        // Change this path to your actual success sound file
        const audio = new Audio('/sounds/success.mp3');
        audio.play().catch(e => console.log('Error playing sound:', e));
      } else {
        console.log('Audio playback not supported');
      }
    },

    playErrorSound() {
      // Check if browser supports Audio API
      if ('Audio' in window) {
        // Change this path to your actual error sound file
        const audio = new Audio('/sounds/error.mp3');
        audio.play().catch(e => console.log('Error playing sound:', e));
      } else {
        console.log('Audio playback not supported');
      }
    },

    // Method to handle label reprinting
    handleReprintLabel(productId) {
      if (confirm("Different FNSKU found in the database. Do you want to reprint the label?")) {
        // Call your label printing function
        this.printLabel(productId);
      }
    },

    // Add a method to print labels
    async printLabel(productId) {
      try {
        const response = await axios.post('/api/stockroom/print-label', {
          productId: productId
        }, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        
        if (response.data.status === 'success') {
          alert('Label printing started.');
        } else {
          alert('Error: ' + response.data.message);
        }
      } catch (error) {
        console.error('Error printing label:', error);
        alert('Failed to print label. Please try again.');
      }
    },
    
    // Reset scanner method
    resetScanner() {
      // Reset scanner data
      this.lastScannedItem = '';
      this.showSuccessNotification = false;
      this.showErrorNotification = false;
      this.scanErrorMessage = '';
      this.totalScanned = 0;
      this.successfulScans = 0;
      this.failedScans = 0;
      this.recentScans = [];
      this.serialNumber = '';
      this.fnsku = '';
      this.locationInput = '';
       // Also clear images
      this.capturedImages = [];
      
      // Clear localStorage data
      localStorage.removeItem('stockroomScanData');
      
      // Clear any pending timeouts
      clearTimeout(this.scanTimeout);
      
      // Focus the first input field
      this.focusNextField('serialNumberInput');
    }
  },
  watch: {
    searchQuery() {
      this.currentPage = 1;
      this.fetchInventory();
    },
    showManualInput(newValue) {
      this.saveManualInputPreference();
    }
  },
  mounted() {
  // Configure Axios defaults
  axios.defaults.baseURL = window.location.origin;
  axios.defaults.withCredentials = true;
  
  // Add CSRF token to requests if using Laravel's CSRF protection
  const token = document.querySelector('meta[name="csrf-token"]');
  if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
  }
  
  // Set Accept header for API requests
  axios.defaults.headers.common['Accept'] = 'application/json';
  
  // Check if we're restoring after a reload
  const savedState = sessionStorage.getItem('scannerState');
  if (savedState) {
    try {
      const state = JSON.parse(savedState);
      // Restore saved state
      this.capturedImages = state.capturedImages || [];
      this.totalScanned = state.totalScanned || 0;
      this.successfulScans = state.successfulScans || 0;
      this.failedScans = state.failedScans || 0;
      this.recentScans = state.recentScans || [];
      
      // Clear the saved state so it's only used once
      sessionStorage.removeItem('scannerState');
      
      console.log('Restored scanner state after reload');
    } catch (e) {
      console.error('Error restoring scanner state:', e);
    }
  }
  
  // Load saved scan data from localStorage
  this.loadScanData();
  
  this.fetchInventory();
},
  beforeDestroy() {
    // Clean up any scanner resources
    this.stopScanner();
    clearTimeout(this.scanTimeout);
  }
};
</script>

<style>
/* Existing styles */.expanded-content {
    background-color: azure;
}
/* NEW - Top Notification Styles */
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

/* Scanner top notification area within the modal */
.scanner-top-notification-area {
  min-height: 40px;
  margin-bottom: 12px;
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

#scanner-camera-preview {
  width: 100%;
  height: 100%;
  object-fit: cover; /* This maintains aspect ratio */
  object-position: center; /* Centers the image */
}

.scanner-view.active-camera {
  background-color: #000;
  position: relative;
  overflow: hidden;
  aspect-ratio: 4/3; /* Maintains consistent aspect ratio */
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

.compact-toggle {
  position: absolute;
  bottom: 10px;
  right: 10px;
  background-color: rgba(0, 0, 0, 0.6);
  color: white;
  border: none;
  border-radius: 4px;
  padding: 5px 10px;
  font-size: 12px;
  cursor: pointer;
}

/* Input Form Styles */
.input-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 12px;
}

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

/* Animation for focus effect */
@keyframes focusAnimation {
  0% { box-shadow: 0 0 0 0px rgba(76, 175, 80, 0.3); }
  100% { box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.0); }
}

.input-active {
  animation: focusAnimation 1s;
}

/* Notification Styles */
.notification-area {
  min-height: 40px;
  margin-bottom: 12px;
}

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

/* Collapsible Scanned Items List Styles */
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

.scans-header:hover .toggle-scans {
  text-decoration: underline;
}

.scans-header h3 {
  margin-top: 0;
  margin-bottom: 0;
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

.scan-serial, .scan-code, .scan-location {
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.scan-serial {
  font-weight: bold;
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


.header-actions {
  display: flex;
  gap: 10px;
  margin-left: 10px;
}

.scanner-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 15px;
  border-bottom: 1px solid #eee;
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

.restart-camera-btn i {
  font-size: 16px;
}

.camera-restart-btn:disabled {
  background-color: #999;
  cursor: not-allowed;
}

.camera-release-message {
  color: white;
  font-size: 12px;
  margin-top: 8px;
  text-align: center;
}

.header-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Fix for camera button in the header */
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

.camera-toggle-btn i {
  font-size: 16px;
}

/* Fix for delete button */
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

/* Fix for scanner camera button */
.camera-button {
  display: flex;
  justify-content: center;
  align-items: center;
  background-color: rgba(0, 0, 0, 0.6);
  color: white;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 16px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.camera-button i {
  font-size: 16px;
}

.camera-toggle-btn:hover {
  background-color: #45a049;
  transform: scale(1.05);
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
  padding-bottom: 75%; /* 4:3 aspect ratio */
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
    padding-bottom: 100%; /* Square aspect on mobile */
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
  
  .input-group {
    gap: 2px;
  }
  
  .input-group label {
    font-size: 11px;
  }
  
  .input-group input {
    padding: 6px;
    font-size: 13px;
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
</style>