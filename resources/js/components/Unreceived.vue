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
      <table>
        <thead>
          <tr>
            <th>
              <input type="checkbox" @click="toggleAll" v-model="selectAll" />
              <span class="header-date"></span>
            </th>
            <th>Details</th>
            <th>Order Details</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, index) in inventory" :key="item.id">
            <td>
              <div class="checkbox-container">
                <input type="checkbox" v-model="item.checked" />
                <span class="placeholder-date">{{ item.shipBy || 'N/A' }}</span>
              </div>
              <img :src="item.imageUrl" alt="Product Image" class="product-thumbnail" />
            </td>
            <td class="vue-details">
              <span class="product-name">{{ item.AStitle }}</span>
            </td>
            <td class="vue-details">
              <span><strong>ID:</strong> {{ item.ProductID }}</span><br />
              <span><strong>ASIN:</strong> {{ item.ProductModuleLoc }}</span><br />
              <span><strong>FNSKU:</strong> {{ item.serialnumber }}</span><br />
              <span><strong>Condition:</strong> {{ item.gradingview }}</span>
            </td>
            <td>
              {{ item.totalquantity }}
              <button @click="toggleDetails(index)" class="more-details-btn">
                {{ expandedRows[index] ? 'Less Details' : 'More Details' }}
              </button>
            </td>
          </tr>
          <tr v-if="expandedRows[index]" class="expanded-row">
            <td colspan="4">
              <div class="expanded-content">
                <strong>Product Name:</strong> {{ item.ProductTitle }}
              </div>
            </td>
          </tr>
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
import axios from 'axios';
import { eventBus } from './eventBus'; 
import ScannerComponent from './Scanner.vue';
import '../../css/modules.css';

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
      
      // For validation
      trackingNumberValid: true,
      
      // For auto verification
      autoVerifyTimeout: null,
      showManualInput: false // Track manual mode state
    };
  },
  computed: {
    searchQuery() {
      return eventBus.searchQuery;
    },
    todayDate() {
      // Format today's date as YYYY-MM-DD for date input min attribute
      const today = new Date();
      return today.toISOString().split('T')[0];
    }
  },
  methods: {
    async fetchInventory() {
      try {
        const response = await axios.get(`/products`, {
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
    },
    
    // Handle PRD date change - auto submit in auto mode
    handlePrdDateChange(event) {
      // In auto mode, when date is selected, automatically submit
      if (!this.showManualInput && this.prdDate) {
        this.submitScan();
      }
    },
    
    // Handle Today button click - set today's date and auto submit in auto mode
    handleTodayButtonClick() {
      this.prdDate = this.todayDate;
      
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
      this.validateTrackingNumber();
      
      if (!this.trackingNumberValid) {
        this.$refs.scanner.showScanError('Please enter a valid tracking number');
        return;
      }
      
      try {
        // Check if tracking exists in database
        const response = await axios.get('/api/unreceived/verify-tracking', {
          params: { tracking: this.trackingNumber }
        });
        
        if (response.data.found) {
          // Tracking found in the database
          this.trackingFound = true;
          
          // Store the product ID received from the backend
          this.productId = response.data.productId;
          
          // Get next RPN number from backend
          const rpnResponse = await axios.get('/api/unreceived/get-next-rpn');
          this.rpnNumber = rpnResponse.data.rpn || `RPN${Math.floor(Math.random() * 100000)}`; // Fallback for testing
          
          // Move to RPN step
          this.currentStep = 2;
        } else {
          // Tracking not found
          this.$refs.scanner.showScanError('Tracking number not found in orders');
          this.trackingFound = false;
        }
      } catch (error) {
        console.error('Error verifying tracking:', error);
        this.$refs.scanner.showScanError('Error checking tracking number');
      }
    },
    
    // Move from RPN to PRD step
    goToNextStep() {
      if (this.currentStep === 2) {
        this.currentStep = 3;
        
        // In auto mode, don't set a default date - wait for user to select
        if (this.showManualInput) {
          this.prdDate = this.todayDate; // Only set default in manual mode
        } else {
          this.prdDate = ''; // Keep it blank in auto mode
        }
      }
    },
    
    // Set today's date for PRD
    setTodayDate() {
      this.prdDate = this.todayDate;
    },
    
    // Submit the scan data
    async submitScan() {
      if (!this.prdDate) {
        this.$refs.scanner.showScanError('Please select a PRD date');
        return;
      }
      
      if (!this.productId) {
        this.$refs.scanner.showScanError('Missing product ID, please verify tracking number again');
        return;
      }
      
      try {
        // Prepare the scan data
        const scanData = {
          trackingNumber: this.trackingNumber,
          rpnNumber: this.rpnNumber,
          prdDate: this.prdDate,
          productId: this.productId
        };
        
        // Get images from scanner component
        const images = this.$refs.scanner.capturedImages.map(img => img.data);
        
        // Send data to API
        const response = await axios.post('/api/unreceived/process-scan', {
          ...scanData,
          Images: images
        }, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        });
        
        const data = response.data;
        
        if (data.success) {
          // Show success notification
          this.$refs.scanner.showScanSuccess(data.item || 'Item received successfully');
          
          // Add to scan history
          this.$refs.scanner.addSuccessScan({
            trackingnumber: this.trackingNumber,
            rpn: this.rpnNumber,
            prd: this.prdDate
          });
          
          // Reset workflow
          this.resetScannerState();
          
          // Refresh inventory
          this.fetchInventory();
        } else {
          // Show error notification
          this.$refs.scanner.showScanError(data.message || 'Error processing scan');
          
          // Add to error scan history
          this.$refs.scanner.addErrorScan({
            trackingnumber: this.trackingNumber,
            rpn: this.rpnNumber,
            prd: this.prdDate
          }, data.reason || 'error');
        }
      } catch (error) {
        console.error('Error submitting scan:', error);
        this.$refs.scanner.showScanError('Network or server error');
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