<template>
  <div class="vue-container">
    <h1 class="vue-title">Stockroom Module</h1>
    
    <!-- Scanner Component -->
    <scanner-component
      scanner-title="Stockroom Scanner"
      storage-prefix="stockroom"
      :enable-camera="true"
      :display-fields="['Serial', 'FNSKU', 'Location']"
      :api-endpoint="'/api/stockroom/process-scan'"
      @process-scan="handleScanProcess"
      @hardware-scan="handleHardwareScan"
      @scanner-opened="handleScannerOpened"
      @scanner-closed="handleScannerClosed"
      @scanner-reset="handleScannerReset"
      @mode-changed="handleModeChange"
      ref="scanner"
    >
      <!-- Define custom input fields for Stockroom module -->
      <template #input-fields>
        <div class="input-group">
          <label>Serial Number:</label>
          <input 
            type="text" 
            v-model="serialNumber" 
            placeholder="Enter Serial Number..." 
            @input="handleSerialInput"
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
            @input="handleFnskuInput"
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
            @input="handleLocationInput"
            @keyup.enter="processScan()"
            ref="locationInput"
          />
          <div class="container-type-hint">Format: L###X (e.g., L123A) or 'Floor'</div>
        </div>
        
        <!-- Submit button (only in manual mode) -->
        <button v-if="showManualInput" @click="processScan()" class="submit-button">Submit</button>
      </template>
    </scanner-component>
    
    <!-- Button for FBA Inbound Shipment -->
    <button class="pagination-button" @click="loadFBAInboundShipment">
      FBA Inbound Shipment
    </button>
    
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

    <!-- Table Container -->
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
      <!-- Pagination (bottom) -->
      <div class="pagination">
        <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
          Back
        </button>
        <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
        <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">Next</button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { eventBus } from './eventBus';
import ScannerComponent from './Scanner.vue';
import { SoundService } from './Sound_service';
import '../../css/modules.css';
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
  name: 'StockroomModule',
  components: {
    ScannerComponent
  },
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
      
      // Scanner data
      serialNumber: '',
      fnsku: '',
      locationInput: '',
      showManualInput: false, // Will be set from scanner component
      
      // For auto verification
      autoVerifyTimeout: null,
      
      // For FNSKU validation
      fnskuValid: false,
      fnskuChecking: false,
      fnskuStatus: ''
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
    }
  },
  methods: {
    // Fetch inventory method
    async fetchInventory() {
      try {
        const response = await axios.get(`${API_BASE_URL}/products`, {
          params: { 
            search: this.searchQuery, 
            page: this.currentPage, 
            per_page: this.perPage, 
            location: 'stockroom' 
          },
          withCredentials: true
        });

        this.inventory = response.data.data;
        this.totalPages = response.data.last_page;
      } catch (error) {
        console.error("Error fetching inventory data:", error);
        SoundService.error();
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
      this.$set(this.expandedRows, index, !this.expandedRows[index]);
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
    
    // Validate serial number
    validateSerialNumber() {
      const serial = this.serialNumber.trim();
      
      // Skip validation if empty (it's optional)
      if (!serial) return true;
      
      // Check for valid serial format using regex
      const validFormat = /^[a-zA-Z0-9]+$/.test(serial);
      
      // Check if it contains X00
      const containsX00 = serial.includes('X00');
      
      return validFormat && !containsX00;
    },
    
    // Validate FNSKU and check if it's a location code
    validateFnsku() {
      const fnsku = this.fnsku.trim();
      
      // Skip validation if empty (when serial is provided)
      if (!fnsku) return true;
      
      // Check if it matches a location pattern
      const isLocation = /^L\d{3}[A-G]$/i.test(fnsku);
      
      // If it looks like a location code, mark it as invalid for FNSKU field
      return !isLocation;
    },
    
    // Check FNSKU availability
    async checkFnskuAvailability() {
      const fnsku = this.fnsku.trim();
      
      // Skip check if empty or appears to be a location
      if (!fnsku || /^L\d{3}[A-G]$/i.test(fnsku)) {
        this.fnskuValid = false;
        return false;
      }
      
      try {
        this.fnskuChecking = true;
        
        // Call API to check FNSKU status
        const response = await axios.get(`${API_BASE_URL}/api/stockroom/check-fnsku`, {
          params: { fnsku: fnsku }
        });
        
        this.fnskuChecking = false;
        
        // Update validity based on response
        if (response.data.exists && response.data.status === 'available') {
          this.fnskuValid = true;
          this.fnskuStatus = 'available';
          return true;
        } else {
          this.fnskuValid = false;
          this.fnskuStatus = response.data.exists ? response.data.status : 'not_found';
          return false;
        }
      } catch (error) {
        console.error('Error checking FNSKU:', error);
        this.fnskuChecking = false;
        this.fnskuValid = false;
        this.fnskuStatus = 'error';
        return false;
      }
    },
    
    // Input field handlers with sound
    async handleSerialInput() {
      // First validate serial number
      const isValid = this.validateSerialNumber();
      
      if (!isValid) {
        // Show error for invalid serial
        this.$refs.scanner.showScanError("Invalid Serial Number - must be alphanumeric and not contain X00");
        this.$refs.serialNumberInput.select();
        SoundService.error();
        return;
      }
      
      // In auto mode with valid input, play sound and proceed
      if (!this.showManualInput && this.serialNumber.trim().length > 5) {
        if (this.autoVerifyTimeout) {
          clearTimeout(this.autoVerifyTimeout);
        }
        
        this.autoVerifyTimeout = setTimeout(() => {
          // Play success sound
          SoundService.success();
          
          // Try to auto-capture if camera is active
         // this.captureImageIfPossible();
          
          // Focus on next field
          this.focusNextField('fnskuInput');
        }, 500);
      }
    },
    
    async handleFnskuInput() {
      // First validate FNSKU
      const isValid = this.validateFnsku();
      
      if (!isValid) {
        // If it looks like a location, show a specific message
        this.$refs.scanner.showScanError("This appears to be a location code. Please enter it in the Location field.");
        this.$refs.fnskuInput.select();
        SoundService.error();
        return;
      }
      
      // In auto mode with valid input, check availability and proceed
      if (!this.showManualInput && this.fnsku.trim().length > 5) {
        if (this.autoVerifyTimeout) {
          clearTimeout(this.autoVerifyTimeout);
        }
        
        this.autoVerifyTimeout = setTimeout(async () => {
          // Check FNSKU availability
          const isAvailable = await this.checkFnskuAvailability();
          
          if (isAvailable) {
            // Play success sound if FNSKU is valid and available
            SoundService.success();
            
            // Focus on location field
            this.focusNextField('locationInput');
          } else {
            // Show appropriate error message based on status
            let errorMessage = "Unknown FNSKU status";
            
            switch (this.fnskuStatus) {
              case 'not_found':
                errorMessage = "FNSKU not found in database";
                break;
              case 'unavailable':
                errorMessage = "FNSKU exists but is not available";
                break;
              case 'error':
                errorMessage = "Error checking FNSKU status";
                break;
            }
            
            this.$refs.scanner.showScanError(errorMessage);
            SoundService.error();
          }
        }, 500);
      }
    },
    
    handleLocationInput() {
      // Validate location format
      const locationRegex = /^L\d{3}[A-G]$/i;
      const isValid = locationRegex.test(this.locationInput.trim()) || 
                     this.locationInput.trim() === 'Floor' || 
                     this.locationInput.trim() === 'L800G';
      
      if (!isValid && this.locationInput.trim() !== '') {
        this.$refs.scanner.showScanError("Invalid Location Format (use L###X, Floor, or L800G)");
        this.$refs.locationInput.select();
        SoundService.error();
        return;
      }
      
      // In auto mode, process scan after valid location input
      if (!this.showManualInput && isValid && this.locationInput.trim().length > 0) {
        if (this.autoVerifyTimeout) {
          clearTimeout(this.autoVerifyTimeout);
        }
        
        this.autoVerifyTimeout = setTimeout(() => {
          // Play success sound for valid location
          SoundService.success();
          
          // Process the scan
          this.processScan();
        }, 500);
      }
    },
    
    // Focus the next input field
    focusNextField(fieldRef) {
      this.$nextTick(() => {
        const nextField = this.$refs[fieldRef];
        if (nextField) {
          nextField.focus();
        }
      });
    },
    
    // Try to capture an image if possible
    captureImageIfPossible() {
      if (this.$refs.scanner && this.$refs.scanner.captureFromScanner) {
        try {
          this.$refs.scanner.captureFromScanner();
        } catch (error) {
          console.error('Error capturing image:', error);
        }
      }
    },
    
    // Process scan with validation
    async processScan(scannedCode = null) {
      try {
        // Try to capture an image first
    //    this.captureImageIfPossible();
        
        // Use either the scanned code or input fields
        let scanSerial, scanFnsku, scanLocation;
        
        if (scannedCode) {
          // External code passed (from hardware scanner)
          scanSerial = '';
          scanFnsku = scannedCode;
          scanLocation = this.locationInput || '';
        } else {
          // Use the input fields
          scanSerial = this.serialNumber;
          scanFnsku = this.fnsku;
          scanLocation = this.locationInput;
          
          // Basic validation - need at least one of serial or FNSKU
          if (!scanFnsku && !scanSerial) {
            this.$refs.scanner.showScanError("Serial Number or FNSKU is required");
            SoundService.error();
            this.focusNextField('serialNumberInput');
            return;
          }
        }
        
        // Validate serial number if provided
        if (scanSerial && (!(/^[a-zA-Z0-9]+$/.test(scanSerial)) || scanSerial.includes('X00'))) {
          this.$refs.scanner.showScanError("Invalid Serial Number - must be alphanumeric and not contain X00");
          SoundService.error();
          return;
        }
        
        // Check if FNSKU is actually a location
        if (scanFnsku && /^L\d{3}[A-G]$/i.test(scanFnsku)) {
          this.$refs.scanner.showScanError("FNSKU appears to be a location. Please enter it in the Location field.");
          SoundService.error();
          return;
        }
        
        // Validate location format
        const locationRegex = /^L\d{3}[A-G]$/i;
        if (scanLocation && !locationRegex.test(scanLocation) && scanLocation !== 'Floor' && scanLocation !== 'L800G') {
          this.$refs.scanner.showScanError("Invalid Location Format (use L###X, Floor, or L800G)");
          SoundService.error();
          return;
        }
        
        // Get images from scanner
        const imageData = this.$refs.scanner.capturedImages.map(img => img.data);
        
        // Send data to server
        const scanData = {
          SerialNumber: scanSerial, 
          FNSKU: scanFnsku,
          Location: scanLocation,
          Images: imageData
        };
        
        // Show loading state
        this.$refs.scanner.startLoading('Processing Scan');
        
        // Send to API
        const response = await axios.post('/api/stockroom/process-scan', scanData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        // Hide loading
        this.$refs.scanner.stopLoading();
        
        const data = response.data;
        
        if (data.success) {
          // Success case
          this.$refs.scanner.showScanSuccess(data.item || 'Item scanned successfully');
          SoundService.successScan(true);
          
          // Add to scan history
          this.$refs.scanner.addSuccessScan({
            Serial: scanSerial,
            FNSKU: scanFnsku,
            Location: scanLocation
          });
          
          // Clear images
          this.$refs.scanner.capturedImages = [];
          
          // Check if we need to handle reprint
          if (data.needReprint && data.productId) {
            // Confirm reprint label
            if (confirm("Different FNSKU found in the database. Do you want to reprint the label?")) {
              this.printLabel(data.productId);
            }
          }
        } else {
          // Error case
          this.$refs.scanner.showScanError(data.message || 'Error processing scan');
          SoundService.scanRejected(true);
          
          // Add to error scan history
          this.$refs.scanner.addErrorScan({
            Serial: scanSerial,
            FNSKU: scanFnsku,
            Location: scanLocation
          }, data.reason || 'error');
          
          // Clear images
          this.$refs.scanner.capturedImages = [];
        }
        
        // Clear input fields and focus first field
        this.serialNumber = '';
        this.fnsku = '';
        this.locationInput = '';
        this.focusNextField('serialNumberInput');
        
      } catch (error) {
        // Hide loading
        this.$refs.scanner.stopLoading();
        
        console.error('Error processing scan:', error);
        this.$refs.scanner.showScanError('Network or server error');
        SoundService.scanRejected(true);
        
        // Add failed scan to history
        this.$refs.scanner.addErrorScan({
          Serial: this.serialNumber || '',
          FNSKU: this.fnsku || '',
          Location: this.locationInput || ''
        }, 'network_error');
      }
    },
    
    // Print label method
    async printLabel(productId) {
      try {
        const response = await axios.post('/api/stockroom/print-label', {
          productId: productId
        }, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
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
    
    // Scanner event handlers
    handleScanProcess() {
      this.processScan();
    },
    
    handleHardwareScan(scannedCode) {
      // For hardware scanner input, process the scan
      this.processScan(scannedCode);
    },
    
    handleModeChange(event) {
      this.showManualInput = event.manual;
    },
    
    handleScannerOpened() {
      // Get current mode from scanner component
      this.showManualInput = this.$refs.scanner.showManualInput;
      
      // Reset fields
      this.serialNumber = '';
      this.fnsku = '';
      this.locationInput = '';
      this.fnskuValid = false;
      this.fnskuStatus = '';
      
      // Focus on first field
      this.$nextTick(() => {
        if (this.$refs.serialNumberInput) {
          this.$refs.serialNumberInput.focus();
        }
      });
    },
    
    handleScannerClosed() {
      // Refresh inventory when scanner is closed
      this.fetchInventory();
    },
    
    handleScannerReset() {
      // Reset fields when scanner is reset
      this.serialNumber = '';
      this.fnsku = '';
      this.locationInput = '';
      this.fnskuValid = false;
      this.fnskuStatus = '';
    }
  },
  watch: {
    searchQuery() {
      this.currentPage = 1;
      this.fetchInventory();
    }
  },
  mounted() {
    // Configure axios
    axios.defaults.baseURL = window.location.origin;
    axios.defaults.withCredentials = true;
    
    // Set CSRF token
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
      axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
    }
    
    // Fetch initial data
    this.fetchInventory();
  },
  beforeDestroy() {
    // Clean up any timeouts
    if (this.autoVerifyTimeout) {
      clearTimeout(this.autoVerifyTimeout);
    }
  }
};
</script>

<style scoped>
/* Styles specific to the Stockroom Module */
.pagination {
  display: flex;
  align-items: center;
  margin: 15px 0;
}

.pagination-button {
  background-color: #4CAF50;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
  margin: 0 5px;
}

.pagination-button:disabled {
  background-color: #cccccc;
  cursor: not-allowed;
}

.pagination-info {
  margin: 0 10px;
}

.pagination select {
  margin-left: 10px;
  padding: 5px;
  border-radius: 4px;
  border: 1px solid #ddd;
}

.btn-moredetails {
  background-color: #f1f1f1;
  border: 1px solid #ddd;
  padding: 5px 10px;
  border-radius: 4px;
  margin-top: 5px;
  cursor: pointer;
  width: 100%;
  text-align: center;
}

.sortable {
  cursor: pointer;
  text-decoration: none;
}

/* Container type hint style */
.container-type-hint {
  font-size: 12px;
  color: #666;
  margin-top: 4px;
  font-style: italic;
}

/* Responsive classes */
@media (max-width: 768px) {
  .Desktop {
    display: none;
  }
}

@media (min-width: 769px) {
  .Mobile {
    display: none;
  }
}

/* Submit button style */
.submit-button {
  margin-top: 10px;
  padding: 8px 16px;
  background-color: #4CAF50;
  color: white;
  border: none;
  border-radius: 4px;
  font-weight: bold;
  cursor: pointer;
  width: 100%;
}
</style>