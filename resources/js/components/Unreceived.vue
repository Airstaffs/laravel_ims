<template>
  <div class="vue-container">
    <h1 class="vue-title">Unreceived Module</h1>
    
    <!-- Scanner Component Here -->
    <scanner-component
      scanner-title="Unreceived Scanner"
      storage-prefix="unreceived"
      :enable-camera="true"
      :display-fields="['Trackingnumber', 'RPN', 'PRD']"
      :api-endpoint="'/api/unreceived/process-scan'"
      @process-scan="handleScanProcess"
      @hardware-scan="handleHardwareScan"
      @scanner-opened="handleScannerOpened"
      @scanner-closed="handleScannerClosed"
      @scanner-reset="handleScannerReset"
      @mode-changed="handleModeChange"
      ref="scanner"
    >
      <!-- Define custom input fields for Unreceived module -->
      <template #input-fields>
        <!-- Step 1: Tracking Number Input -->
        <div class="input-group" v-if="currentStep === 1">
          <label>Tracking Number:</label>
          <input 
            type="text" 
            v-model="trackingNumber" 
            placeholder="Enter Tracking Number..." 
            @input="handleTrackingInput"
            @keyup.enter="verifyTrackingNumber"
            ref="trackingInput"
          />
          <!-- Only show Verify Tracking button in Manual mode -->
          <button v-if="showManualInput" @click="verifyTrackingNumber" class="verify-button">Verify Tracking</button>
        </div>
        
        <!-- Step 2: RPN Field (shown after tracking verification) -->
        <div class="input-group" v-if="currentStep === 2">
          <div class="tracking-verified">
            <div class="success-banner">Tracking found for {{ trackingNumber }}</div>
          </div>
          <label>RPN:</label>
          <input 
            type="text" 
            v-model="rpnNumber" 
            placeholder="RPN Number" 
            readonly
            class="readonly-input"
          />
          <button @click="goToNextStep" class="next-button">Next</button>
        </div>
        
        <!-- Step 3: PRD Field (shown after RPN) -->
        <div class="input-group" v-if="currentStep === 3">
          <label>PRD:</label>
          <div class="date-input-container">
            <input 
              type="date" 
              v-model="prdDate" 
              @change="handlePrdDateChange"
              :min="todayDate"
              class="date-input"
            />
            <button class="calendar-icon">
              <i class="fas fa-calendar"></i>
            </button>
          </div>
          <button @click="handleTodayButtonClick" class="today-button">Today</button>
          <!-- Only show Submit button in Manual mode -->
          <button v-if="showManualInput" @click="submitScan" class="submit-scan-button">Submit</button>
        </div>
      </template>
    </scanner-component>

    <!-- table display -->
    <div class="table-container">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>
              <input type="checkbox" @click="toggleAll" v-model="selectAll" />
              <a style="color:black" @click="sortBy('AStitle')" class="sortable">
                Product Name
                <span v-if="sortColumn === 'AStitle'">
                  {{ sortOrder === 'asc' ? '▲' : '▼' }}
                </span>
              </a>
              <span style="margin-right: 20px;"></span>
              <a style="color:black" @click="sortBy('rtcounter')" class="sortable">
                RT counter
                <span v-if="sortColumn === 'rtcounter'">
                  {{ sortOrder === 'asc' ? '▲' : '▼' }}
                </span> </a>

                <span style="margin-right: 20px;"></span>

                <button class="Desktop" style="border: solid 1px black; background-color: aliceblue;" @click="toggleDetailsVisibility">{{ showDetails ? 'Hide extra columns' : 'Show extra columns' }}</button>
            </th>
            <th class="Desktop">Location</th>
            <th class="Desktop">Added date</th>
            <th class="Desktop">Updated date</th>
            <th class="Desktop">Fnsku</th>
            <th class="Desktop">Msku</th>
            <th class="Desktop">Asin</th>
            <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">FBM</th>
            <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">FBA</th>
            <th class="Desktop"style="background-color: antiquewhite;" v-if="showDetails">Outbound</th>
            <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">Inbound</th>
            <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">Unfulfillable</th>
            <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">Reserved</th>
            <th class="Desktop" >Fulfillment</th>
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
                  <span class="placeholder-date">{{ item.shipBy || '' }}</span>
                </div>
                <div class="product-container">
                  <div class="product-image-container" @click="openImageModal(item)">
                    <!-- Use the actual file path for the main image -->
                    <img :src="'/images/thumbnails/' + item.img1" 
                         :alt="item.ProductTitle || 'Product'" 
                         class="product-thumbnail clickable-image" 
                         @error="handleImageError($event)" />
                    <div class="image-count-badge" v-if="countAdditionalImages(item) > 0">
                      +{{ countAdditionalImages(item) }}
                    </div>
                  </div>
               
                  <div class="product-info">
                    <p class="product-name">RT# : {{ item.rtcounter }}</p>
                    <p class="product-name">{{ item.ProductTitle }}</p>

                    <p class="Mobile">Location : {{ item.warehouselocation }}</p>
                    <p class="Mobile">Added date : {{ item.datedelivered }}</p>
                    <p class="Mobile">Updated date : {{ item.lastDateUpdate }}</p>
                    <p class="Mobile">Fnsku : {{ item.FNSKUviewer }}</p>
                    <p class="Mobile">Msku : {{ item.MSKUviewer }}</p>
                    <p class="Mobile">Asin : {{ item.ASINviewer }}</p>
                  </div>
                </div>
              </td>
              <td class="Desktop">
                <span><strong></strong> {{ item.warehouselocation }}</span>
              </td>
        
              <td class="Desktop">
                <span><strong></strong> {{ item.datedelivered }}</span>
              </td>
              
              <td class="Desktop">
                <span><strong></strong> {{ item.lastDateUpdate }}</span>
              </td>
              
              <td class="Desktop">           
                <span><strong></strong> {{ item.FNSKUviewer }}</span>
              </td>
              
              <td class="Desktop">        
                <span><strong></strong> {{ item.MSKUviewer }}</span>
              </td>
              
              <td class="Desktop">              
               <span><strong></strong> {{ item.ASINviewer }}</span>
              </td>
              
             <!-- Hidden -->  <!-- Hidden -->  <!-- Hidden -->
              <td v-if="showDetails">
                <span><strong></strong> {{ item.FBMAvailable }}</span>
              </td>
              <td v-if="showDetails">
                <span><strong></strong> {{ item.FbaAvailable }}</span>
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
                <span><strong></strong> {{ item.Unfulfillable }}</span>
              </td>
            <!-- Hidden -->  <!-- Hidden -->  <!-- Hidden -->

              <td class="Desktop">
                <span><strong></strong> {{ item.Fulfilledby }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong> {{ item.Status }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong> {{ item.serialnumber }}</span>
              </td>
          
             <!-- Button for more details -->
              <td class="Desktop">
                {{ item.totalquantity }}
                <button class="btn-moredetails" @click="toggleDetails(index)">
                  {{ expandedRows[index] ? 'Less Details' : 'More Details' }}
                </button>
                <br>
                <button class="btn-moredetails">example</button><br>
                <button class="btn-moredetails">example</button><br>
                <button class="btn-moredetails">example</button><br>
              </td>
            </tr>
             <!-- More details results -->
            <tr v-if="expandedRows[index]">
              <td colspan="11">
                <div class="expanded-content p-3 border rounded">
                  <div class="Mobile">
                  <button class="btn-moredetails">sample button</button>
                  <button class="btn-moredetails">sample button</button>
                  <button class="btn-moredetails">sample button</button>
                  </div>
                  <strong>Product Name:</strong> {{ item.AStitle }}
                </div>
              </td>
            </tr>

             <!-- Button for more details (Mobile) -->
               <td class="Mobile">
                {{ item.totalquantity }}
                <button style="width: 100%; border-bottom: 2px solid black; padding:0px" @click="toggleDetails(index)">
                  {{ expandedRows[index] ? 'Less Details ▲ ' : 'More Details ▼ ' }}
                </button>
              </td>
          </template>
        </tbody>
      </table>
      <!-- Pagination -->
      <div class="pagination">
        <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">Previous</button>
        <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
        <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">Next</button>
      </div>
    </div>
  </div>
</template>

<script>
// Fixed Vue Component
import axios from 'axios';
import { eventBus } from './eventBus'; 
import ScannerComponent from './Scanner.vue';
import { SoundService } from './Sound_service';
import '../../css/modules.css';
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
  name: 'UnreceivedModule',
  components: {
    ScannerComponent
  },
  data() {
    return {
      inventory: [],
      currentPage: 1,
      totalPages: 1,
      selectAll: false,
      expandedRows: {},
      
      // Scanner workflow data
      currentStep: 1, // 1: Tracking, 2: RPN, 3: PRD
      trackingNumber: '',
      rpnNumber: '',
      prdDate: '',
      trackingValid: false,
      trackingFound: false,
      productId: '',
      rtcounter: '', // Added rtcounter field
      
      // For validation
      trackingNumberValid: true,
      
      // For auto verification
      autoVerifyTimeout: null,
      showManualInput: false, // Track manual mode state

      perPage: 10, // Default rows per page
      sortColumn: '',
      sortOrder: 'asc',   
      showDetails: false,
      defaultImage: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4=',
      // Modal state
      showImageModal: false,
      modalImages: [],
      currentImageIndex: 0
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

        if (typeof valueA === 'number' && typeof valueB === 'number') {
          return this.sortOrder === 'asc' ? valueA - valueB : valueB - valueA;
        }

        return this.sortOrder === 'asc'
          ? String(valueA).localeCompare(String(valueB))
          : String(valueB).localeCompare(String(valueA));
      });
    }
  },
  methods: {
    handleImageError(event) {
      // If image fails to load, use an inline SVG placeholder
      event.target.src = this.defaultImage;
      event.target.onerror = null; // Prevent infinite error loop
    },
    
    // Count additional images based on the image fields (img2-img15)
    countAdditionalImages(item) {
      if (!item) return 0;
      
      let count = 0;
      // Check fields img2 through img15
      for (let i = 2; i <= 15; i++) {
        const fieldName = `img${i}`;
        if (item[fieldName] && item[fieldName] !== 'NULL' && item[fieldName].trim() !== '') {
          count++;
        }
      }
      
      return count;
    },
    
    // Open image modal with all available images from img1-img15 fields
    openImageModal(item) {
      if (!item) return;
      
      // Reset modal state
      this.modalImages = [];
      this.currentImageIndex = 0;
      
      // Image field names in your data (img1 through img15)
      const imageFields = [
        'img2', 'img3', 'img4', 'img5', 
        'img6', 'img7', 'img8', 'img9', 'img10', 
        'img11', 'img12', 'img13', 'img14', 'img15'
      ];
      
      // Loop through all possible image fields and add non-empty ones
      imageFields.forEach(field => {
        if (item[field] && item[field] !== 'NULL' && item[field].trim() !== '') {
          // Use the direct image field value as the path
          const imagePath = `/images/thumbnails/${item[field]}`;
          this.modalImages.push(imagePath);
        }
      });
      
      // If no images were found, add a default image
      if (this.modalImages.length === 0) {
        const defaultPath = `/images/thumbnails/${item.ProductID}.jpg`;
        this.modalImages.push(defaultPath);
      }
      
      // Show the modal
      this.showImageModal = true;
      
      // Prevent scrolling when modal is open
      document.body.style.overflow = 'hidden';
    },
    
    closeImageModal() {
      this.showImageModal = false;
      this.modalImages = [];
      
      // Re-enable scrolling
      document.body.style.overflow = 'auto';
    },
    
    nextImage() {
      if (this.currentImageIndex < this.modalImages.length - 1) {
        this.currentImageIndex++;
      } else {
        this.currentImageIndex = 0; // Loop back to the first image
      }
    },
    
    prevImage() {
      if (this.currentImageIndex > 0) {
        this.currentImageIndex--;
      } else {
        this.currentImageIndex = this.modalImages.length - 1; // Loop to the last image
      }
    },
    
    // Format today's date as YYYY-MM-DD for date input min attribute
    todayDate() {
      const today = new Date();
      return today.toISOString().split('T')[0];
    },
    
    async fetchInventory() {
      try {
        const response = await axios.get(`${API_BASE_URL}/api/unreceived/products`, {
          params: { 
            search: this.searchQuery, 
            page: this.currentPage, 
            location: 'Orders'
          },
        });
        
        this.inventory = response.data.data;
        this.totalPages = response.data.last_page;
      } catch (error) {
        console.error('Error fetching inventory data:', error);
        SoundService.error(); // Error vibration for fetch failure
      }
    },
    
    // Handle tracking input with auto verification in auto mode
    handleTrackingInput(event) {
      this.validateTrackingNumber();
      
      // In auto mode, automatically verify after short delay when typing
      if (!this.showManualInput && this.trackingNumberValid && this.trackingNumber.length >= 5) {
        // Clear any existing timeout to avoid multiple calls
        if (this.autoVerifyTimeout) {
          clearTimeout(this.autoVerifyTimeout);
        }
        
        // Set new timeout for auto verification
        this.autoVerifyTimeout = setTimeout(() => {
          this.verifyTrackingNumber();
        }, 500); // 500ms delay to let user finish typing
      }
    },
    
    // Validation method for tracking number
    validateTrackingNumber() {
      // Basic validation - can be enhanced as needed
      this.trackingNumberValid = this.trackingNumber.trim() !== '';
      if (!this.trackingNumberValid) {
        SoundService.error(); // Error vibration for invalid input
      }
      return this.trackingNumberValid;
    },
    
    // Handle PRD date change - auto submit in auto mode
    handlePrdDateChange(event) {
      // In auto mode, when date is selected, automatically submit
      if (!this.showManualInput && this.prdDate) {
        SoundService.success(); // Success sound for date selection
        this.submitScan();
      }
    },
    
    // Handle Today button click - set today's date and auto submit in auto mode
    handleTodayButtonClick() {
      this.prdDate = this.todayDate();
      SoundService.success(); // Success sound for today button
      
      // In auto mode, automatically submit
      if (!this.showManualInput) {
        setTimeout(() => {
          this.submitScan();
        }, 100); // Small delay to ensure prdDate is set
      }
    },
    
    // Mode change handler
    handleModeChange(event) {
      this.showManualInput = event.manual;
      
      // When switching modes, clear the PRD field if we're in step 3
      if (this.currentStep === 3) {
        this.prdDate = '';
      }
    },
    
    // Step navigation
    async verifyTrackingNumber() {
      // Tracking found in the database
      this.validateTrackingNumber();
      
      if (!this.trackingNumberValid) {
        this.$refs.scanner.showScanError('Please enter a valid tracking number');
        SoundService.error(); // Error vibration for invalid tracking
        return;
      }
      
      try {
        // Check if tracking exists in database
        const response = await axios.get(`${API_BASE_URL}/api/unreceived/verify-tracking`, {
          params: { tracking: this.trackingNumber }
        });
        
        if (response.data.found) {
          this.trackingFound = true;
     
          if (response.data.alreadyScanned) {
            // Item has already been scanned
            SoundService.alreadyScanned(); // Play already scanned sound
          
            // Show warning notification for already scanned item (using our new method)
            this.$refs.scanner.showScanWarning(`Item already scanned`);
            
            // Focus back on tracking input for next scan
            this.$refs.trackingInput.select();
            return;
          }

          
          // Store the product ID and rtcounter received from the backend
          this.productId = response.data.productId;
          this.rtcounter = response.data.rtcounter; // Store rtcounter
          
          // Get next RPN number from backend
          const rpnResponse = await axios.get(`${API_BASE_URL}/api/unreceived/get-next-rpn`);
          this.rpnNumber = rpnResponse.data.rpn || `RPN${Math.floor(Math.random() * 100000)}`; // Fallback for testing
          
          // Move to RPN step
          this.currentStep = 2;
          SoundService.success(); // Success sound for found tracking
        } else {
          // Tracking not found
          this.$refs.scanner.showScanError('Tracking number not found in orders');
          this.trackingFound = false;
          SoundService.notFound(); // Not found sound for missing tracking
          this.$refs.trackingInput.select();
        }
      } catch (error) {
        console.error('Error verifying tracking:', error);
        this.$refs.scanner.showScanError('Error checking tracking number');
        SoundService.error(); // Error vibration for network/server error
        this.$refs.trackingInput.select();
      }
    },
    
    // Move from RPN to PRD step
    goToNextStep() {
      if (this.currentStep === 2) {
        this.currentStep = 3;
        SoundService.success(); // Success sound for next step
        
        // In auto mode, don't set a default date - wait for user to select
        if (this.showManualInput) {
          this.prdDate = this.todayDate(); // Only set default in manual mode
        } else {
          this.prdDate = ''; // Keep it blank in auto mode
        }
      }
    },
    
    // Set today's date for PRD
    setTodayDate() {
      this.prdDate = this.todayDate();
      SoundService.success(); // Success sound for today date
    },
    
    // Submit the scan data
    async submitScan() {
      if (!this.prdDate) {
        this.$refs.scanner.showScanError('Please select a PRD date');
        SoundService.error(); // Error vibration for missing date
        return;
      }
      
      if (!this.productId) {
        this.$refs.scanner.showScanError('Missing product ID, please verify tracking number again');
        SoundService.error(); // Error vibration for missing product ID
        return;
      }

      //loading animation
      this.$refs.scanner.startLoading('Processing Data');
      
      try {
        // Prepare the scan data
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        const scanData = {
          _token: csrfToken, // Add CSRF token here
          trackingNumber: this.trackingNumber,
          rpnNumber: this.rpnNumber,
          prdDate: this.prdDate,
          productId: this.productId,
          rtcounter: this.rtcounter
        };
        
        // Get images from scanner component
        const images = this.$refs.scanner.capturedImages.map(img => img.data);
        
        // Send data to API
        const response = await axios.post(`${API_BASE_URL}/api/unreceived/process-scan`, {
          ...scanData,
          Images: images
        }, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken // Also add it to headers
          }
        });
        
        const data = response.data;
        
        if (data.success) {
          
          //stop loading animation
          this.$refs.scanner.stopLoading();
          // Show success notification
          this.$refs.scanner.showScanSuccess(data.item || 'Item received successfully');
          SoundService.successScan(true); // Play special success sound with 
          
          const dateParts = this.prdDate.split('-');
          const prdFormatted = dateParts.length === 3 ? 
            `PRD${dateParts[1]}${dateParts[2]}${dateParts[0].substring(2)}` : 'PRD';
            
          // Add to scan history with detailed information
          this.$refs.scanner.addSuccessScan({
            Trackingnumber: this.trackingNumber,
            RPN: this.rpnNumber,
            PRD: prdFormatted
          });

          
          // Reset workflow
          this.resetScannerState();
          
          // Refresh inventory
          this.fetchInventory();
        } else {
          // Show error notification
          this.$refs.scanner.showScanError(data.message || 'Error processing scan');
          SoundService.scanRejected(true); // Play special error sound with vibration

          const dateParts = this.prdDate.split('-');
          const prdFormatted = dateParts.length === 3 ? 
            `PRD${dateParts[1]}${dateParts[2]}${dateParts[0].substring(2)}` : 'PRD';
            
          // Add to error scan history with detailed information
          this.$refs.scanner.addErrorScan({
            Trackingnumber: this.trackingNumber,
            RPN: this.rpnNumber,
            PRD: prdFormatted
          }, data.reason || 'error');
          
          // Auto-select the tracking input text for quick rescanning
          this.$nextTick(() => {
            if (this.currentStep === 1 && this.$refs.trackingInput) {
              this.$refs.trackingInput.select(); // Select all text in tracking input
            } else if (this.currentStep === 3) {
              // For date inputs, we might need a different approach
              const dateInput = document.querySelector('.date-input');
              if (dateInput) dateInput.focus();
            }
          });
        }
      } catch (error) {
        console.error('Error submitting scan:', error);
        this.$refs.scanner.showScanError('Network or server error');
        SoundService.scanRejected(true); // Play special error sound with vibration
        
        // Auto-select the tracking input text for quick rescanning
        this.$nextTick(() => {
          if (this.currentStep === 1 && this.$refs.trackingInput) {
            this.$refs.trackingInput.select(); // Select all text in tracking input
          }
        });
      }
    },

    // Reset scanner state
    resetScannerState() {
      // Reset the scanner workflow to initial state
      this.currentStep = 1;
      this.trackingNumber = '';
      this.rpnNumber = '';
      this.prdDate = '';
      this.trackingFound = false;
      this.productId = '';
      this.rtcounter = ''; // Reset rtcounter
      
      // Clear any pending auto-verify timeouts
      if (this.autoVerifyTimeout) {
        clearTimeout(this.autoVerifyTimeout);
        this.autoVerifyTimeout = null;
      }
      
      // Focus back on tracking input
      this.$nextTick(() => {
        if (this.$refs.trackingInput) {
          this.$refs.trackingInput.focus();
        }
      });
    },
    
    // Scanner event handlers
    handleScanProcess() {
      // Process based on current step
      if (this.currentStep === 1) {
        this.verifyTrackingNumber();
      } else if (this.currentStep === 3) {
        this.submitScan();
      }
    },
    
    handleHardwareScan(scannedCode) {
      // For hardware scanner, assume it's always a tracking number
      if (this.currentStep === 1) {
        this.trackingNumber = scannedCode;
        this.verifyTrackingNumber();
      }
    },
    
    handleScannerOpened() {
      console.log('Scanner opened');
      // Get current mode from scanner component
      this.showManualInput = this.$refs.scanner.showManualInput;
      this.resetScannerState();
    },
    
    handleScannerClosed() {
      console.log('Scanner closed');
      this.fetchInventory();
    },
    
    handleScannerReset() {
      console.log('Scanner reset');
      this.resetScannerState();
    },
    
    // Pagination methods
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
    
    toggleAll() {
      this.inventory.forEach((item) => (item.checked = this.selectAll));
    },
    
    toggleDetails(index) {
      this.$set(this.expandedRows, index, !this.expandedRows[index]);
    },
    
    // Add the missing method for toggleDetailsVisibility
    toggleDetailsVisibility() {
      this.showDetails = !this.showDetails;
    }
  },
  watch: {
    searchQuery() {
      this.currentPage = 1;
      this.fetchInventory();
    },
  },
  mounted() {
    axios.defaults.baseURL = window.location.origin;
    this.fetchInventory();
  },
};
</script>

<style scoped>
/* Styles specific to the Unreceived Module */
.success-banner {
  background-color: #4CAF50;
  color: white;
  padding: 10px;
  text-align: center;
  border-radius: 4px;
  margin-bottom: 15px;
  font-weight: bold;
}

.readonly-input {
  background-color: #f5f5f5;
}

.next-button, .today-button, .verify-button, .submit-scan-button {
  background-color: #4CAF50;
  color: white;
  border: none;
  padding: 8px 16px;
  margin-top: 10px;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
}

.date-input-container {
  position: relative;
  width: 100%;
}

.date-input {
  width: 100%;
  padding: 8px 30px 8px 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.calendar-icon {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
}

/* Your existing styles */
.table-container {
  margin-top: 20px;
}
</style>