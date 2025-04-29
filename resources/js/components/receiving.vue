<template>
  <div class="vue-container">
    <h1 class="vue-title">Received Module</h1>

    <!-- Scanner Component -->
    <scanner-component
      scanner-title="Received Scanner"
      storage-prefix="received"
      :enable-camera="true"
      :display-fields="['Trackingnumber', 'FirstSN', 'SecondSN', 'PCN', 'Basket']"
      :api-endpoint="'/api/received/process-scan'"
      @process-scan="handleScanProcess"
      @hardware-scan="handleHardwareScan"
      @scanner-opened="handleScannerOpened"
      @scanner-closed="handleScannerClosed"
      @scanner-reset="handleScannerReset"
      @mode-changed="handleModeChange"
      ref="scanner"
    >
      <!-- Define custom input fields for Received module -->
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

        <!-- Step 2: Pass/Fail Buttons (shown after tracking verification) -->
        <div class="input-group" v-if="currentStep === 2">
          <div class="tracking-verified">
            <div class="success-banner">Tracking found for {{ trackingNumber }}</div>
          </div>
          <div class="pass-fail-buttons">
            <button @click="passItem" class="pass-button">
              <i class="fas fa-check"></i> Pass
            </button>
            <button @click="failItem" class="fail-button">
              <i class="fas fa-times"></i> Fail
            </button>
          </div>
        </div>

        <!-- Step 3: First Serial Number Input -->
        <div class="input-group" v-if="currentStep === 3">
          <label>First Serial Number:</label>
          <input
            type="text"
            v-model="firstSerialNumber"
            placeholder="Scan First Serial Number..."
            @input="handleFirstSerialInput"
            @keyup.enter="processFirstSerial"
            ref="firstSerialInput"
          />
          <button v-if="showManualInput" @click="processFirstSerial" class="scan-button">Scan</button>
        </div>

        <!-- Step 4: Second Serial Number Input (with Skip option) -->
        <div class="input-group" v-if="currentStep === 4">
          <label>Second Serial Number:</label>
          <input
            type="text"
            v-model="secondSerialNumber"
            placeholder="Scan Second Serial Number (or Skip)..."
            @input="handleSecondSerialInput"
            @keyup.enter="processSecondSerial"
            ref="secondSerialInput"
          />
          <div class="button-group">
            <button v-if="showManualInput" @click="processSecondSerial" class="scan-button">Scan</button>
            <button @click="skipSecondSerial" class="skip-button">Skip</button>
          </div>
        </div>

        <!-- Step 5: PCN Input  -->
      <div class="input-group" v-if="currentStep === 5">
        <label>PCN (Product Control Number):</label>
        <input
          type="text"
          v-model="pcnNumber"
          placeholder="Scan PCN Number..."
          @input="handlePcnInput"
          @keyup.enter="processPcnNumber"
          ref="pcnInput"
        />
        <div class="container-type-hint">Enter PCN format: PCN followed by numbers (e.g., PCN12345)</div>
        <button v-if="showManualInput" @click="processPcnNumber" class="scan-button">Scan</button>
      </div>

        <!-- Step 6: Basket Number Input (now step 6) -->
        <div class="input-group" v-if="currentStep === 6">
          <label>Basket/Container Number:</label>
          <input
            type="text"
            v-model="basketNumber"
            placeholder="Enter BKT/SH/ENV + numbers..."
            @input="handleBasketInput"
            @keyup.enter="processBasketNumber"
            ref="basketInput"
          />
          <div class="container-type-hint">Enter numbers with prefix: BKT (Basket), SH (Shelf), or ENV (Envelope)</div>
          <button v-if="showManualInput" @click="processBasketNumber" class="scan-button">Submit</button>
        </div>
      </template>
    </scanner-component>

    <!-- Table Container -->
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

// ReceivedModule.vue
<script>
import axios from 'axios';
import { eventBus } from './eventBus';
import ScannerComponent from './Scanner.vue';
import { SoundService } from './Sound_service';
import '../../css/modules.css';
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
  name: 'ReceivedModule',
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
      currentStep: 1, // 1: Tracking, 2: Pass/Fail, 3: First SN, 4: Second SN, 5: PCN, 6: Basket
      trackingNumber: '',
      firstSerialNumber: '',
      secondSerialNumber: '',
      pcnNumber: '',  // New PCN field
      basketNumber: '',
      trackingValid: false,
      trackingFound: false,
      productId: '',
      rtcounter: '', // Added rtcounter field
      status: '', // 'pass' or 'fail'

      // For validation
      trackingNumberValid: true,
      basketNumberValid: true,
      pcnNumberValid: true,  // New validation field

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

    // Fetch inventory data from the API
    async fetchInventory() {
      try {
        const response = await axios.get(`${API_BASE_URL}/api/received/products`, {
          params: {
            search: this.searchQuery,
            page: this.currentPage,
            location: 'Received'
          },
        });

        this.inventory = response.data.data;
        this.totalPages = response.data.last_page;
      } catch (error) {
        console.error('Error fetching inventory data:', error);
        SoundService.error(); // Error sound for fetch failure
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
        SoundService.error(); // Play error sound for invalid input
      }
      return this.trackingNumberValid;
    },

    // Validation method for basket number
    validateBasketNumber() {
      // Updated regex to support BKT, SH, or ENV prefixes
      const basketRegex = /^(BKT|SH|ENV)\d+$/i;
      this.basketNumberValid = basketRegex.test(this.basketNumber.trim());
      if (!this.basketNumberValid) {
        SoundService.error(); // Play error sound for invalid input
      }
      return this.basketNumberValid;
    },

    // Validation method for PCN
    validatePcnNumber() {
      if (this.pcnNumber.trim() === 'N/A') {
        this.pcnNumberValid = true;
        return true;
      }

      const pcnRegex = /^PCN\d+$/i;
      this.pcnNumberValid = pcnRegex.test(this.pcnNumber.trim());
      if (!this.pcnNumberValid) {
        SoundService.error(); // Play error sound for invalid input
      }
      return this.pcnNumberValid;
    },

    // Step 1: Verify tracking number
    async verifyTrackingNumber() {
      this.validateTrackingNumber();

      if (!this.trackingNumberValid) {
        this.$refs.scanner.showScanError('Please enter a valid tracking number');
        SoundService.error(); // Play error sound for invalid input
        return;
      }

      try {
        // Check if tracking exists in database
        const response = await axios.get(`${API_BASE_URL}/api/received/verify-tracking`, {
          params: { tracking: this.trackingNumber }
        });

        if (response.data.found) {
          // Tracking found in the database
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
          this.$refs.scanner.loadProductThumbnails(response.data.productDetails);
          // Move to Pass/Fail step
          this.currentStep = 2;
          SoundService.success(); // Play success sound for finding tracking
        } else {
          // Tracking not found
          this.$refs.scanner.showScanError('Tracking number not found');
          this.trackingFound = false;
          SoundService.notFound(); // Play not found sound for missing
          this.$refs.trackingInput.select();

        }
      } catch (error) {
        console.error('Error verifying tracking:', error);
        this.$refs.scanner.showScanError('Error checking tracking number');
        SoundService.error(); // Play error sound for network/server errors
        this.$refs.trackingInput.select();
      }
    },

    // Step 2: Pass or fail the item
    passItem() {
      this.status = 'pass';
      this.currentStep = 3; // Move to First Serial Number step
      SoundService.success(); // Success sound for pass action

      // Focus on the first serial number input
      this.$nextTick(() => {
        if (this.$refs.firstSerialInput) {
          this.$refs.firstSerialInput.focus();
        }
      });
    },

    // For failed items - move to basket number entry but allow image capture
    failItem() {
      this.status = 'fail';

      // Add a camera button for failed items
      this.$refs.scanner.showScanSuccess('Item marked for failure - capture images if needed');
      SoundService.error(true); // Error sound with vibration for fail action

      // Go to PCN step first instead of directly to basket
      this.currentStep = 5;

      // Focus on the PCN input
      this.$nextTick(() => {
        if (this.$refs.pcnInput) {
          this.$refs.pcnInput.focus();
        }
      });
    },

    // Handle first serial number input
    handleFirstSerialInput() {
      // Auto-process in auto mode when input is valid
      if (!this.showManualInput && this.firstSerialNumber.trim().length > 5) {
        if (this.autoVerifyTimeout) {
          clearTimeout(this.autoVerifyTimeout);
        }

        this.autoVerifyTimeout = setTimeout(() => {
          this.processFirstSerial();
        }, 500);
      }
    },

    // Process first serial number
    async processFirstSerial() {
      if (!this.firstSerialNumber.trim()) {
        this.$refs.scanner.showScanError('Please enter a valid serial number');
        SoundService.error(); // Error sound for invalid input
        this.$refs.firstSerialInput.select();
        return;
      }

      // Capture image for first serial
      //await this.captureSerialImage();
      SoundService.success(); // Success sound after capturing image

      // Move to second serial number step
      this.currentStep = 4;

      // Focus on the second serial number input
      this.$nextTick(() => {
        if (this.$refs.secondSerialInput) {
          this.$refs.secondSerialInput.focus();
        }
      });
    },

    // Handle second serial number input
    handleSecondSerialInput() {
      // Auto-process in auto mode when input is valid
      if (!this.showManualInput && this.secondSerialNumber.trim().length > 5) {
        if (this.autoVerifyTimeout) {
          clearTimeout(this.autoVerifyTimeout);
        }

        this.autoVerifyTimeout = setTimeout(() => {
          this.processSecondSerial();
        }, 500);
      }
    },

    // Process second serial number
    async processSecondSerial() {
      if (!this.secondSerialNumber.trim()) {
        this.$refs.scanner.showScanError('Please enter a valid serial number');
        SoundService.error(); // Error sound for invalid input
        this.$refs.secondSerialInput.select();
        return;
      }

      // Capture image for second serial
      //await this.captureSerialImage();
      SoundService.success(); // Success sound after capturing image

      // Move to PCN step
      this.currentStep = 5;

      // Focus on the PCN input
      this.$nextTick(() => {
        if (this.$refs.pcnInput) {
          this.$refs.pcnInput.focus();
        }
      });
    },

    // Skip second serial number
    skipSecondSerial() {
      this.secondSerialNumber = 'N/A'; // Mark as not applicable
      SoundService.success(); // Success sound for skip action

      // Move to PCN step
      this.currentStep = 5;

      // Focus on the PCN input
      this.$nextTick(() => {
        if (this.$refs.pcnInput) {
          this.$refs.pcnInput.focus();
        }
      });
    },

    // Handle PCN input
    handlePcnInput() {
      // Auto-process in auto mode when input is valid
      if (!this.showManualInput && this.pcnNumber.trim().length > 4) {
        if (this.autoVerifyTimeout) {
          clearTimeout(this.autoVerifyTimeout);
        }

        this.autoVerifyTimeout = setTimeout(() => {
          this.processPcnNumber();
        }, 500);
      }
    },

    // Process PCN
    async processPcnNumber() {
      if (!this.validatePcnNumber()) {
        this.$refs.scanner.showScanError('PCN must start with PCN followed by numbers (e.g. PCN12345)');
        SoundService.error(); // Error sound for invalid PCN
        this.$refs.pcnInput.select();
        return;
      }

      try {
        // First validate PCN format locally
        // Then check if PCN is already used in the database
        const pcnResponse = await axios.post(`${API_BASE_URL}/api/received/validate-pcn`, {
          pcn: this.pcnNumber
        });

        if (pcnResponse.data.alreadyUsed) {
          // PCN already exists in the database
          this.$refs.scanner.showScanWarning(`${this.pcnNumber} is already in use`);
          SoundService.PCNalreadyUsed(); // Use the same sound as for already scanned items
          this.$refs.pcnInput.select();
          return;
        }

        // Capture image for PCN
        // await this.captureSerialImage();
        SoundService.success(); // Success sound after capturing PCN image

        // Move to basket number step
        this.currentStep = 6;

        // Focus on the basket number input
        this.$nextTick(() => {
          if (this.$refs.basketInput) {
            this.$refs.basketInput.focus();
          }
        });
      } catch (error) {
        console.error('Error validating PCN:', error);
        this.$refs.scanner.showScanError('Error validating PCN');
        SoundService.error();
      }
    },

    // Handle basket number input
    handleBasketInput() {
      // Auto-process in auto mode when input is valid
      if (!this.showManualInput && this.basketNumber.trim().length > 3) {
        if (this.autoVerifyTimeout) {
          clearTimeout(this.autoVerifyTimeout);
        }

        this.autoVerifyTimeout = setTimeout(() => {
          this.processBasketNumber();
        }, 500);
      }
    },

    // Process basket number
    processBasketNumber() {
      if (!this.validateBasketNumber()) {
        this.$refs.scanner.showScanError('Basket number must start with BKT, SH, or ENV followed by numbers');
        SoundService.error(); // Error sound for invalid basket
        this.$refs.basketInput.select();
        return;
      }

      // Submit data based on status (pass or fail)
      if (this.status === 'fail') {
        this.submitFailedItem();
      } else {
        this.submitScanData();
      }
    },

    // Capture image of serial number
    async captureSerialImage() {
      // Use scanner component to capture image
      if (this.$refs.scanner && this.$refs.scanner.captureFromScanner) {
        try {
          await this.$refs.scanner.captureFromScanner();
          return true;
        } catch (error) {
          console.error('Error capturing image:', error);
          SoundService.error(); // Error sound for capture failure
          return false;
        }
      }
      return false;
    },

    // Submit failed item data
    async submitFailedItem() {
      try {
        // Make sure we have a basket number
        if (!this.validateBasketNumber()) {
          this.$refs.scanner.showScanError('Basket number must start with BKT, SH, or ENV followed by numbers');
          SoundService.error(); // Error vibration for invalid basket
          return;
        }

        if (!this.validatePcnNumber()) {
          this.$refs.scanner.showScanError('PCN must start with PCN followed by numbers (e.g. PCN12345)');
          SoundService.error(); // Error vibration for invalid PCN
          return;
        }
        //loading animation
        this.$refs.scanner.startLoading('Processing Data');
        // Get images from scanner component
        const images = this.$refs.scanner.capturedImages.map(img => img.data);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;


        const failData = {
          _token: csrfToken,
          trackingNumber: this.trackingNumber,
          status: 'fail',
          pcnNumber: this.pcnNumber, // Include PCN field
          basketNumber: this.basketNumber,
          productId: this.productId,
          rtcounter: this.rtcounter, // Include rtcounter
          Images: images
        };

        const response = await axios.post(`${API_BASE_URL}/api/received/process-scan`, failData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
          }
        });

        if (response.data.success) {
           //clear fetch delivered item image
           this.$refs.scanner.clearProductThumbnails();
          //stop loading animation
          this.$refs.scanner.stopLoading();
          this.$refs.scanner.showScanSuccess('Item marked as failed');
          SoundService.successScan(true); // Use successscan sound for final submission

          // Add to scan history
          this.$refs.scanner.addSuccessScan({
            trackingnumber: this.trackingNumber,
            status: 'fail',
            pcn: this.pcnNumber,
            basket: this.basketNumber
          });

          // Clear all captured images if requested by the server
          if (response.data.clearImages) {
            this.$refs.scanner.capturedImages = [];
          }

          // Reset scanner state
          this.resetScannerState();

          // Refresh inventory
          this.fetchInventory();
        } else {
          this.$refs.scanner.stopLoading();
          this.$refs.scanner.showScanError(response.data.message || 'Error processing scan');
          SoundService.scanRejected(true); // Use scanrejected sound for submission error
        }
      } catch (error) {
        console.error('Error submitting failed item:', error);
        SoundService.scanRejected(true); // Use scanrejected sound for submission error

        // Enhanced error handling
        if (error.response && error.response.status === 422) {
          console.log('Validation errors:', error.response.data);
          if (error.response.data.errors) {
            const errorMessages = [];
            Object.keys(error.response.data.errors).forEach(field => {
              errorMessages.push(`${field}: ${error.response.data.errors[field].join(', ')}`);
            });
            const errorMsg = errorMessages.join('\n');
            this.$refs.scanner.showScanError(`Validation error: ${errorMsg}`);
          } else {
            this.$refs.scanner.showScanError('Validation failed. Please check your inputs.');
          }
        } else {
          this.$refs.scanner.showScanError('Network or server error');
        }
      }
    },

    // Submit complete scan data
    async submitScanData() {
      try {
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        //loading animation
        this.$refs.scanner.startLoading('Processing Data...');
        // Create data without images first
        const scanData = {
          _token: csrfToken,
          trackingNumber: this.trackingNumber,
          status: 'pass',
          firstSerialNumber: this.firstSerialNumber,
          secondSerialNumber: this.secondSerialNumber,
          pcnNumber: this.pcnNumber,
          basketNumber: this.basketNumber,
          productId: this.productId,
          rtcounter: this.rtcounter
          // No images in this initial request
        };

        // Debug: Log the data being sent
        console.log('Submitting scan data (without images):', scanData);

        // Send data to API
        const response = await axios.post(`${API_BASE_URL}/api/received/process-scan`, scanData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
          }
        });

        if (response.data.success) {
          // If basic data submission was successful, now upload images one by one
          const images = this.$refs.scanner.capturedImages.map(img => img.data);
          if (images.length > 0) {
            const hasSerialTwo = this.secondSerialNumber !== 'N/A';
            const hasPcn = this.pcnNumber !== 'N/A';

            // Upload each image separately
            for (let i = 0; i < images.length; i++) {
              try {
               // Change this line in your submitScanData method:
                const imageResponse = await axios.post(`${API_BASE_URL}/api/images/upload`, {
                    _token: csrfToken,
                    productId: this.productId,
                    imageIndex: i,
                    imageData: images[i],
                    hasSerialTwo: hasSerialTwo,
                    hasPcn: hasPcn
                }, {
                    withCredentials: true,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                console.log(`Image ${i} uploaded:`, imageResponse.data);
              } catch (imageError) {
                console.error(`Error uploading image ${i}:`, imageError);
              }
            }
          }
          //clear fetch delivered item image
          this.$refs.scanner.clearProductThumbnails();
          //stop loading animation
          this.$refs.scanner.stopLoading();
          // Show success notification
          this.$refs.scanner.showScanSuccess('Item received successfully');
          SoundService.successScan(true);

          // Add to scan history
          this.$refs.scanner.addSuccessScan({
            Trackingnumber: this.trackingNumber,
            FirstSN: this.firstSerialNumber,
            SecondSN: this.secondSerialNumber,
            PCN: this.pcnNumber,
            Basket: this.basketNumber // Fixed property name
          });
          // Clear captured images
          this.$refs.scanner.capturedImages = [];

          // Reset workflow
          this.resetScannerState();

          // Refresh inventory
          this.fetchInventory();
        } else {
          this.$refs.scanner.stopLoading();
          // Show error notification
          this.$refs.scanner.showScanError(response.data.message || 'Error processing scan');
          SoundService.scanRejected(true);

          // Add to error scan history
          this.$refs.scanner.addErrorScan({
            Trackingnumber: this.trackingNumber,
            FirstSN: this.firstSerialNumber,
            SecondSN: this.secondSerialNumber,
            PCN: this.pcnNumber,
            Basket: this.basketNumber // Fixed property name
          }, response.data.reason || 'error');
        }
      } catch (error) {
        console.error('Error submitting scan:', error);
        SoundService.scanRejected(true);

        // Enhanced error handling for validation errors
        if (error.response && error.response.status === 422) {
          console.log('Validation errors:', error.response.data);
          if (error.response.data.errors) {
            const errorMessages = [];
            Object.keys(error.response.data.errors).forEach(field => {
              errorMessages.push(`${field}: ${error.response.data.errors[field].join(', ')}`);
            });
            const errorMsg = errorMessages.join('\n');
            this.$refs.scanner.showScanError(`Validation error: ${errorMsg}`);
          } else {
            this.$refs.scanner.showScanError('Validation failed. Please check your inputs.');
          }
        } else if (error.response && error.response.status === 403) {
          this.$refs.scanner.showScanError('Permission denied. Please try again or contact support.');
        } else {
          this.$refs.scanner.showScanError('Network or server error');
        }
      }
    },

    // Reset scanner state
    resetScannerState() {
      // Reset all data
      this.currentStep = 1;
      this.trackingNumber = '';
      this.firstSerialNumber = '';
      this.secondSerialNumber = '';
      this.pcnNumber = '';  // Reset PCN
      this.basketNumber = '';
      this.trackingValid = false;
      this.trackingFound = false;
      this.productId = '';
      this.rtcounter = ''; // Reset rtcounter
      this.status = '';

       // Clear the product thumbnails
      if (this.$refs.scanner && this.$refs.scanner.clearProductThumbnails) {
        this.$refs.scanner.clearProductThumbnails();
      }

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
      switch (this.currentStep) {
        case 1:
          this.verifyTrackingNumber();
          break;
        case 3:
          this.processFirstSerial();
          break;
        case 4:
          this.processSecondSerial();
          break;
        case 5:
          this.processPcnNumber();  // Handle PCN scan
          break;
        case 6:
          this.processBasketNumber();
          break;
      }
    },

    // Handle hardware scanner input
    handleHardwareScan(scannedCode) {
      // Determine which step we're on and handle the scan accordingly
      switch (this.currentStep) {
        case 1:
          this.trackingNumber = scannedCode;
          this.verifyTrackingNumber();
          break;
        case 3:
          this.firstSerialNumber = scannedCode;
          this.processFirstSerial();
          break;
        case 4:
          this.secondSerialNumber = scannedCode;
          this.processSecondSerial();
          break;
        case 5:
          this.pcnNumber = scannedCode;  // Scan PCN
          this.processPcnNumber();
          break;
        case 6:
          this.basketNumber = scannedCode;
          this.processBasketNumber();
          break;
      }
    },

    // Handle mode changes
    handleModeChange(event) {
      this.showManualInput = event.manual;
    },

    // Scanner opened event
    handleScannerOpened() {
      console.log('Scanner opened');
      // Get current mode from scanner component
      this.showManualInput = this.$refs.scanner.showManualInput;
      this.resetScannerState();
    },

    // Scanner closed event
    handleScannerClosed() {
      console.log('Scanner closed');
      this.fetchInventory();
    },

    // Scanner reset event
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

    // Sort column
    sortBy(column) {
      if (this.sortColumn === column) {
        this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortColumn = column;
        this.sortOrder = 'asc';
      }
    },

    // Toggle details visibility
    toggleDetailsVisibility() {
      this.showDetails = !this.showDetails;
    },

    // Change rows per page
    changePerPage() {
      this.currentPage = 1;
      this.fetchInventory();
    }
  },
  watch: {
    searchQuery() {
      this.currentPage = 1;
      this.fetchInventory();
    }
  },
  mounted() {
    axios.defaults.baseURL = window.location.origin;
    this.fetchInventory();
  }
};
</script>

<style scoped>
/* Styles for the Received Module */
.success-banner {
  background-color: #4CAF50;
  color: white;
  padding: 10px;
  text-align: center;
  border-radius: 4px;
  margin-bottom: 15px;
  font-weight: bold;
}

.pass-fail-buttons {
  display: flex;
  gap: 10px;
  margin-top: 15px;
}

.pass-button, .fail-button {
  flex: 1;
  padding: 12px;
  border: none;
  border-radius: 4px;
  font-weight: bold;
  cursor: pointer;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
}

.pass-button {
  background-color: #4CAF50;
  color: white;
}

.fail-button {
  background-color: #f44336;
  color: white;
}

.button-group {
  display: flex;
  gap: 10px;
  margin-top: 10px;
}

.scan-button, .skip-button, .verify-button {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
}

.scan-button, .verify-button {
  background-color: #4CAF50;
  color: white;
}

.skip-button {
  background-color: #FF9800;
  color: white;
}

.container-type-hint {
  font-size: 12px;
  color: #666;
  margin-top: 4px;
  font-style: italic;
}

.date-input-container {
  position: relative;
  width: 100%;
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

/* Your existing table styles */
.table-container {
  margin-top: 20px;
}
</style>
