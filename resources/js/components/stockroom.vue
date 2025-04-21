<template>
  <div class="vue-container">
    <!-- Top header bar with blue background -->
    <div class="top-header">
      <div class="store-filter">
        <label for="store-select">Store:</label>
        <select id="store-select" v-model="selectedStore" @change="changeStore" class="store-select">
          <option value="">All Stores</option>
          <option v-for="store in stores" :key="store" :value="store">
            {{ store }}
          </option>
        </select>
      </div>

      <h1 class="module-title">Stockroom Module</h1>

      <div class="header-buttons">
        <button class="scan-button" @click="openScannerModal">
          <i class="fas fa-barcode"></i> Scan Items
        </button>
        <button class="shipment-button" @click="loadFBAInboundShipment">
          <i class="fas fa-truck"></i> FBA Inbound Shipment
        </button>
      </div>
    </div>
    
    <!-- Scanner Component (with hideButton prop to hide the scanner button) -->
    <scanner-component
      scanner-title="Stockroom Scanner"
      storage-prefix="stockroom"
      :enable-camera="true"
      :display-fields="['Serial', 'FNSKU', 'Location']"
      :api-endpoint="'/api/stockroom/process-scan'"
      :hide-button="true"
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
    
    <!-- Pagination with centered layout -->
    <div class="pagination-container">
      <div class="pagination-wrapper">
        <div class="pagination">
          <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
            <i class="fas fa-chevron-left"></i> Back
          </button>
          <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">
            Next <i class="fas fa-chevron-right"></i>
          </button>
        </div>
        
        <div class="per-page-selector">
          <select v-model="perPage" @change="changePerPage" class="per-page-select">
            <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
              {{ option }} per page
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Desktop Table Container -->
    <div class="table-container desktop-view">
      <table class="table">
        <thead>
          <tr>
            <th width="30%">
              <div class="th-content">
                <input type="checkbox" @click="toggleAll" v-model="selectAll" />
                <span class="sortable" @click="sortBy('AStitle')">
                  Product Name
                  <i v-if="sortColumn === 'AStitle'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                </span>
              </div>
            </th>
            <th width="8%">
              <div class="th-content sortable" @click="sortBy('ASIN')">
                ASIN
                <i v-if="sortColumn === 'ASIN'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </div>
            </th>
            <th width="8%">
              <div class="th-content sortable" @click="sortBy('MSKUviewer')">
                MSKU/SKU
                <i v-if="sortColumn === 'MSKUviewer'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </div>
            </th>
            <th width="8%">
              <div class="th-content sortable" @click="sortBy('storename')">
                Store
                <i v-if="sortColumn === 'storename'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </div>
            </th>
            <th width="8%">
              <div class="th-content sortable" @click="sortBy('grading')">
                Grading
                <i v-if="sortColumn === 'grading'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </div>
            </th>
            <th width="8%">
              <div class="th-content">
                FNSKUs
              </div>
            </th>
            <th width="5%">
              <div class="th-content sortable" @click="sortBy('FBMAvailable')">
                FBM
                <i v-if="sortColumn === 'FBMAvailable'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </div>
            </th>
            <th width="5%">
              <div class="th-content sortable" @click="sortBy('FbaAvailable')">
                FBA
                <i v-if="sortColumn === 'FbaAvailable'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </div>
            </th>
            <th width="5%">
              <div class="th-content sortable" @click="sortBy('item_count')">
                Item Count
                <i v-if="sortColumn === 'item_count'" :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </div>
            </th>
            <th width="15%">
              <div class="th-content">
                Actions
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(item, index) in sortedInventory" :key="item.ASIN">
            <tr>
              <td>
                <div class="product-cell">
                  <div class="checkbox-container">
                    <input type="checkbox" v-model="item.checked" />
                  </div>
                  <div class="product-container">
                    <div class="product-image">
                      <img src="" alt="" class="product-thumbnail" />
                    </div>
                    <div class="product-info">
                      <p class="product-name">
                        {{ item.AStitle }}
                      </p>
                    </div>
                  </div>
                </div>
              </td>
              <td>{{ item.ASIN }}</td>
              <td>{{ item.MSKUviewer }}</td>
              <td>{{ item.storename }}</td>
              <td>{{ item.grading }}</td>
              <td>
                <div class="fnsku-selector" v-if="item.fnskus && item.fnskus.length > 0">
                  <select class="fnsku-select">
                    <option v-for="fnsku in item.fnskus" :key="fnsku.FNSKU || fnsku" :value="fnsku.FNSKU || fnsku">
                      {{ fnsku.FNSKU || fnsku }}
                    </option>
                  </select>
                  <span class="fnsku-count">({{ item.fnskus.length }})</span>
                </div>
                <div v-else>-</div>
              </td>
              <td>{{ item.FBMAvailable }}</td>
              <td>{{ item.FbaAvailable }}</td>
              <td>{{ item.item_count }}</td>
              <td>
                <div class="action-buttons">
                  <button class="btn-print" @click="printLabel(item.ProductID)">
                    <i class="fas fa-print"></i> Print
                  </button>
                  <button class="btn-details" @click="toggleDetails(index)">
                    {{ expandedRows[index] ? 'Hide' : 'Show' }}
                  </button>
                  <button class="btn-move" @click="openMoveModal(item)">
                    <i class="fas fa-exchange-alt"></i> Move
                  </button>
                </div>
              </td>
            </tr>
            
            <!-- Expanded Details Row -->
            <tr v-if="expandedRows[index]" class="expanded-row">
              <td colspan="10">
                <div class="expanded-content">
                  <div class="expanded-header">
                    <div><strong>Product Name:</strong> {{ item.AStitle }}</div>
                    <div><strong>ASIN:</strong> {{ item.ASIN }}</div>
                    <div><strong>MSKU/SKU:</strong> {{ item.MSKUviewer }}</div>
                  </div>
                  
                  <div class="expanded-fnskus">
                    <strong>All FNSKUs:</strong>
                    <div class="fnsku-tags">
                      <span v-for="fnsku in item.fnskus" :key="fnsku.FNSKU || fnsku" class="fnsku-tag">
                        {{ fnsku.FNSKU || fnsku }}
                      </span>
                    </div>
                  </div>
                  
                  <div class="expanded-serials">
                    <strong>Serial Numbers & Locations:</strong>
                    <div class="serial-table-container">
                      <table class="serial-detail-table">
                        <thead>
                          <tr>
                            <th>RT#</th>
                            <th>Serial Number</th>
                            <th>Location</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="serial in item.serials" :key="serial.ProductID">
                            <td>{{ formatRTNumber(serial.rtcounter, item.storename) }}</td>
                            <td>{{ serial.serialnumber }}</td>
                            <td>{{ serial.warehouselocation }}</td>
                          </tr>
                          <tr v-if="!item.serials || item.serials.length === 0">
                            <td colspan="3" class="text-center">No serial numbers found</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    
    <!-- Mobile Cards View -->
    <div class="mobile-view">
      <div class="mobile-cards">
        <div v-for="(item, index) in sortedInventory" :key="item.ASIN" class="mobile-card">
          <div class="mobile-card-header">
            <div class="mobile-checkbox">
              <input type="checkbox" v-model="item.checked" />
            </div>
            <div class="mobile-product-info">
              <h3 class="mobile-product-name">{{ item.AStitle }}</h3>
            </div>
          </div>
          
          <div class="mobile-card-details">
            <div class="mobile-detail-row">
              <span class="mobile-detail-label">ASIN:</span>
              <span class="mobile-detail-value">{{ item.ASIN }}</span>
            </div>
            <div class="mobile-detail-row">
              <span class="mobile-detail-label">MSKU:</span>
              <span class="mobile-detail-value">{{ item.MSKUviewer }}</span>
            </div>
            <div class="mobile-detail-row">
              <span class="mobile-detail-label">Store:</span>
              <span class="mobile-detail-value">{{ item.storename }}</span>
            </div>
            <div class="mobile-detail-row">
              <span class="mobile-detail-label">Grading:</span>
              <span class="mobile-detail-value">{{ item.grading }}</span>
            </div>
            <div class="mobile-detail-row">
              <span class="mobile-detail-label">Item Count:</span>
              <span class="mobile-detail-value">{{ item.item_count }}</span>
            </div>
            <div class="mobile-detail-row">
              <span class="mobile-detail-label">FBM/FBA:</span>
              <span class="mobile-detail-value">{{ item.FBMAvailable }} / {{ item.FbaAvailable }}</span>
            </div>
          </div>
          
          <div class="mobile-card-actions">
            <button class="mobile-btn" @click="printLabel(item.ProductID)">
              <i class="fas fa-print"></i> Print
            </button>
            <button class="mobile-btn mobile-btn-details" @click="toggleDetails(index)">
              <i class="fas fa-info-circle"></i> {{ expandedRows[index] ? 'Hide' : 'Details' }}
            </button>
            <button class="mobile-btn mobile-btn-move" @click="openMoveModal(item)">
              <i class="fas fa-exchange-alt"></i> Move
            </button>
          </div>
          
          <div v-if="expandedRows[index]" class="mobile-expanded-content">
            <div class="mobile-section">
              <h4>FNSKUs:</h4>
              <div class="mobile-fnsku-list">
                <div v-for="fnsku in item.fnskus" :key="fnsku.FNSKU || fnsku" class="mobile-fnsku-item">
                  {{ fnsku.FNSKU || fnsku }}
                </div>
                <div v-if="!item.fnskus || item.fnskus.length === 0" class="mobile-empty">
                  No FNSKUs found
                </div>
              </div>
            </div>
            
            <div class="mobile-section">
              <h4>Serial Numbers:</h4>
              <div class="mobile-serial-list">
                <div v-for="serial in item.serials" :key="serial.ProductID" class="mobile-serial-item">
                  <div class="mobile-serial-detail">
                    <span class="mobile-serial-label">RT#:</span>
                    <span class="mobile-serial-value">{{ formatRTNumber(serial.rtcounter, item.storename) }}</span>
                  </div>
                  <div class="mobile-serial-detail">
                    <span class="mobile-serial-label">Serial:</span>
                    <span class="mobile-serial-value">{{ serial.serialnumber }}</span>
                  </div>
                  <div class="mobile-serial-detail">
                    <span class="mobile-serial-label">Location:</span>
                    <span class="mobile-serial-value">{{ serial.warehouselocation }}</span>
                  </div>
                </div>
                <div v-if="!item.serials || item.serials.length === 0" class="mobile-empty">
                  No serial numbers found
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Bottom pagination (also centered) -->
    <div class="pagination-container">
      <div class="pagination-wrapper">
        <div class="pagination">
          <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
            <i class="fas fa-chevron-left"></i> Back
          </button>
          <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">
            Next <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Move Items Modal -->
    <div v-if="showMoveModal" class="move-modal">
      <div class="move-modal-content">
        <div class="move-modal-header">
          <h2>Move Items</h2>
          <button class="move-modal-close" @click="closeMoveModal">&times;</button>
        </div>
        <div class="move-modal-body">
          <div class="move-form">
            <div class="form-group">
              <label>Destination:</label>
              <select v-model="moveDestination" class="form-control">
                <option value="Production">Production Area</option>
                <option value="Shipping">Shipping</option>
                <option value="QA">Quality Assurance</option>
              </select>
            </div>
            <div class="form-group">
              <label>New Location (optional):</label>
              <input type="text" v-model="moveLocation" class="form-control" placeholder="e.g., L123A or Floor">
            </div>
            <div class="form-group">
              <label>Notes (optional):</label>
              <textarea v-model="moveNotes" class="form-control" placeholder="Add notes about this move..."></textarea>
            </div>
          </div>
          <div class="move-item-list">
            <h3>Items to Move</h3>
            <ul>
              <li v-for="serial in selectedItemSerials" :key="serial.ProductID">
                {{ formatRTNumber(serial.rtcounter, currentMoveItem.storename) }} - {{ serial.serialnumber }}
              </li>
            </ul>
          </div>
        </div>
        <div class="move-modal-footer">
          <button class="btn-cancel" @click="closeMoveModal">Cancel</button>
          <button class="btn-confirm" @click="confirmMove">Confirm Move</button>
        </div>
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
      serialDropdowns: {}, // Added for serial number dropdowns
      sortColumn: "",
      sortOrder: "asc",
      showDetails: false,
      
      // Store filter
      stores: [],
      selectedStore: '',
      
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
      fnskuStatus: '',
      
      // For move modal
      showMoveModal: false,
      moveDestination: 'Production',
      moveLocation: '',
      moveNotes: '',
      currentMoveItem: null,
      selectedItemSerials: []
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
    // Add a computed property to detect mobile
    isMobile() {
      return window.innerWidth <= 768;
    }
  },
  methods: {
    // Open scanner modal method - this will call the scanner component's method
    openScannerModal() {
      this.$refs.scanner.openScannerModal();
    },
    
    loadFBAInboundShipment() {
      if (window.loadContent) {
        window.loadContent("fbashipmentinbound");
      } else {
        console.error("loadContent not found on window");
      }
    },
    
    // Format RT number based on store name
    formatRTNumber(rtCounter, storeName) {
      const paddedCounter = String(rtCounter).padStart(5, '0');
      
      if (storeName === 'RenovarTech') {
        return `RT ${paddedCounter}`;
      } else if (storeName === 'Allrenewed') {
        return `AR ${paddedCounter}`;
      } else {
        // Default format if store doesn't match known patterns
        return `#${paddedCounter}`;
      }
    },
    
    // Store dropdown functions
    async fetchStores() {
      try {
        const response = await axios.get(`${API_BASE_URL}/api/stockroom/stores`, {
          withCredentials: true
        });
        this.stores = response.data;
      } catch (error) {
        console.error("Error fetching stores:", error);
      }
    },
    
    changeStore() {
      this.currentPage = 1;
      this.fetchInventory();
    },
    
    // Modified fetchInventory 
    async fetchInventory() {
      try {
        const response = await axios.get(`${API_BASE_URL}/api/stockroom/products`, {
          params: { 
            search: this.searchQuery, 
            page: this.currentPage, 
            per_page: this.perPage,
            store: this.selectedStore
          },
          withCredentials: true
        });

        // Initialize items with checked property
        this.inventory = (response.data.data || []).map(item => {
          return {
            ...item,
            checked: false,
            serials: item.serials || [],
            fnskus: item.fnskus || []
          };
        });
        
        this.totalPages = response.data.last_page || 1;
      } catch (error) {
        console.error("Error fetching inventory data:", error);
        if (SoundService && SoundService.error) {
          SoundService.error();
        }
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
      // Create a new object for reactivity
      const updatedExpandedRows = { ...this.expandedRows };
      updatedExpandedRows[index] = !updatedExpandedRows[index];
      this.expandedRows = updatedExpandedRows;
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
    
    // Move modal functions
    openMoveModal(item) {
      this.currentMoveItem = item;
      this.selectedItemSerials = item.serials || [];
      this.showMoveModal = true;
      this.moveDestination = 'Production';
      this.moveLocation = '';
      this.moveNotes = '';
    },
    
    closeMoveModal() {
      this.showMoveModal = false;
      this.currentMoveItem = null;
      this.selectedItemSerials = [];
    },
    
    confirmMove() {
      // Here you would implement the actual move functionality
      alert(`Moving ${this.selectedItemSerials.length} items to ${this.moveDestination}`);
      this.closeMoveModal();
      // Refresh inventory after move
      this.fetchInventory();
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
    
    // Process scan with validation
    async processScan(scannedCode = null) {
      try {
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
    },
    
    // New methods for handling responsiveness
    handleResize() {
      // If we're on mobile and dropdowns are open, we might want to close them
      if (this.isMobile) {
        const hasOpenDropdowns = Object.values(this.serialDropdowns).some(isOpen => isOpen);
        if (hasOpenDropdowns) {
          this.serialDropdowns = {};
        }
      }
    },
    
    closeDropdownsOnClickOutside(event) {
      // Check if click is outside any dropdown
      const isOutside = !event.target.closest('.serial-dropdown');
      if (isOutside) {
        this.serialDropdowns = {};
      }
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
    
    // Add Font Awesome if not already included
    if (!document.querySelector('link[href*="font-awesome"]')) {
      const fontAwesome = document.createElement('link');
      fontAwesome.rel = 'stylesheet';
      fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
      document.head.appendChild(fontAwesome);
    }
    
    // Fetch stores for dropdown
    this.fetchStores();
    
    // Fetch initial data
    this.fetchInventory();
    
    // Listen for window resize to update isMobile
    window.addEventListener('resize', this.handleResize);
    
    // Initialize serialDropdowns
    this.inventory.forEach((_, index) => {
      this.$set(this.serialDropdowns, index, false);
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', this.closeDropdownsOnClickOutside);
  },
  beforeDestroy() {
    // Clean up any timeouts
    if (this.autoVerifyTimeout) {
      clearTimeout(this.autoVerifyTimeout);
    }
    
    window.removeEventListener('resize', this.handleResize);
    document.removeEventListener('click', this.closeDropdownsOnClickOutside);
  }
};
</script>

<style scoped>
/* Top header bar */
.top-header {
  display: flex;
  align-items: center;
  background-color: #4a90e2; /* Amazon blue color */
  padding: 8px 15px;
  color: white;
}

.store-filter {
  display: flex;
  align-items: center;
  width: 200px;
}

.store-filter label {
  margin-right: 8px;
  font-weight: bold;
}

.store-select {
  padding: 4px 8px;
  border-radius: 3px;
  border: 1px solid #ccc;
  width: 120px;
}

.module-title {
  flex-grow: 1;
  text-align: center;
  margin: 0;
  font-size: 20px;
  font-weight: bold;
}

.header-buttons {
  display: flex;
  gap: 10px;
}

.scan-button {
  background-color: #f0f0f0;
  border: 1px solid #ddd;
  border-radius: 3px;
  padding: 5px 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 14px;
  transition: background-color 0.2s;
}

.scan-button:hover {
  background-color: #e0e0e0;
}

.shipment-button {
  background-color: #4CAF50; /* Green */
  color: white;
  border: 1px solid #43a047;
  border-radius: 3px;
  padding: 5px 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 14px;
  transition: background-color 0.2s;
}

.shipment-button:hover {
  background-color: #43a047;
}

/* Pagination layout */
.pagination-container {
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 15px 0;
  padding: 0 15px;
}

.pagination-wrapper {
  display: flex;
  align-items: center;
  gap: 15px;
}

.pagination {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pagination-button {
  background-color: #f0f0f0;
  border: 1px solid #ddd;
  padding: 5px 10px;
  cursor: pointer;
  border-radius: 3px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.pagination-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-info {
  color: #333;
  font-size: 14px;
}

.per-page-selector {
  display: flex;
  align-items: center;
}

.per-page-select {
  padding: 5px 8px;
  border: 1px solid #ddd;
  border-radius: 3px;
  background-color: white;
}

/* Table styles */
.table-container {
  overflow-x: auto;
  margin-bottom: 20px;
}

.table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  border: 1px solid #ddd;
}

.table th {
  background-color: #f5f5f5;
  padding: 8px 10px;
  text-align: left;
  border-bottom: 2px solid #ddd;
  font-weight: bold;
  white-space: nowrap;
  position: sticky;
  top: 0;
  z-index: 1;
}

.th-content {
  display: flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
}

.table td {
  padding: 8px 10px;
  border-top: 1px solid #ddd;
  vertical-align: middle;
  overflow: hidden;
  text-overflow: ellipsis;
}

.table tr:nth-child(even) {
  background-color: #f9f9f9;
}

.table tr:hover {
  background-color: #f1f1f1;
}

.expanded-row td {
  padding: 0;
}

/* Sortable columns */
.sortable {
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.sortable:hover {
  color: #4a90e2;
}

.sortable i {
  font-size: 12px;
}

/* Product cell styling */
.product-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.checkbox-container {
  flex-shrink: 0;
}

.product-container {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-width: 0;
}

.product-image {
  width: 50px;
  height: 50px;
  flex-shrink: 0;
  background-color: #f0f0f0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.product-thumbnail {
  max-width: 100%;
  max-height: 100%;
}

.product-info {
  flex: 1;
  min-width: 0;
}

.product-info .product-name {
  font-weight: bold;
  margin: 0 0 5px 0;
  white-space: normal;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

/* FNSKU selector */
.fnsku-selector {
  display: flex;
  align-items: center;
  gap: 5px;
}

.fnsku-select {
  padding: 4px;
  border: 1px solid #ddd;
  border-radius: 3px;
  max-width: 100%;
}

.fnsku-count {
  color: #666;
  font-size: 12px;
  white-space: nowrap;
}

/* Action buttons */
.action-buttons {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.btn-print, .btn-details, .btn-move {
  padding: 5px;
  border: 1px solid #ddd;
  border-radius: 3px;
  cursor: pointer;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  background-color: #f0f0f0;
  white-space: nowrap;
}

.btn-print:hover, .btn-details:hover {
  background-color: #e0e0e0;
}

.btn-move {
  background-color: #ffc107;
  color: #212529;
  border: 1px solid #e0a800;
}

.btn-move:hover {
  background-color: #e0a800;
}

/* Expanded content */
.expanded-content {
  padding: 15px;
  background-color: #f9f9f9;
}

.expanded-header {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  margin-bottom: 15px;
}

.expanded-fnskus, .expanded-serials {
  margin-top: 10px;
}

.fnsku-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 5px;
}

.fnsku-tag {
  background-color: #e0e0e0;
  padding: 3px 8px;
  border-radius: 3px;
  font-size: 12px;
}

.serial-table-container {
  margin-top: 8px;
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid #ddd;
  border-radius: 3px;
}

.serial-detail-table {
  width: 100%;
  border-collapse: collapse;
}

.serial-detail-table th, .serial-detail-table td {
  padding: 6px 8px;
  text-align: left;
  border-bottom: 1px solid #eee;
}

.serial-detail-table th {
  background-color: #f5f5f5;
  font-weight: bold;
  position: sticky;
  top: 0;
}

.text-center {
  text-align: center;
}

/* Move Modal */
.move-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.move-modal-content {
  background-color: white;
  border-radius: 8px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

.move-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid #ddd;
}

.move-modal-header h2 {
  margin: 0;
  font-size: 18px;
}

.move-modal-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #666;
}

.move-modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.move-form {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.form-group label {
  font-weight: 500;
}

.form-control {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.move-item-list {
  background-color: #f8f9fa;
  padding: 10px;
  border-radius: 4px;
}

.move-item-list h3 {
  margin-top: 0;
  font-size: 16px;
  border-bottom: 1px solid #ddd;
  padding-bottom: 8px;
}

.move-item-list ul {
  margin: 0;
  padding-left: 20px;
  max-height: 150px;
  overflow-y: auto;
}

.move-modal-footer {
  padding: 15px 20px;
  border-top: 1px solid #ddd;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn-cancel {
  padding: 8px 16px;
  background-color: #6c757d;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-confirm {
  padding: 8px 16px;
  background-color: #28a745;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

/* Scanner component styling */
.submit-button {
  background-color: #4CAF50;
  color: white;
  border: none;
  padding: 8px;
  border-radius: 3px;
  cursor: pointer;
  margin-top: 10px;
}

.input-group {
  margin-bottom: 10px;
}

.input-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
}

.input-group input {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 3px;
}

.container-type-hint {
  font-size: 12px;
  color: #666;
  margin-top: 4px;
}

/* Mobile view */
.mobile-view {
  display: none;
}

/* Mobile Card Styling */
.mobile-cards {
  padding: 0 10px;
}

.mobile-card {
  margin-bottom: 15px;
  background-color: #fff;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  border: 1px solid #e0e0e0;
}

.mobile-card-header {
  padding: 12px;
  border-bottom: 1px solid #eee;
  display: flex;
  align-items: flex-start;
}

.mobile-checkbox {
  margin-right: 10px;
  padding-top: 2px;
}

.mobile-product-info {
  flex: 1;
}

.mobile-product-name {
  margin: 0;
  font-size: 16px;
  line-height: 1.3;
  font-weight: 600;
  color: #333;
}

.mobile-card-details {
  padding: 12px;
  border-bottom: 1px solid #eee;
}

.mobile-detail-row {
  display: flex;
  margin-bottom: 6px;
  font-size: 14px;
}

.mobile-detail-label {
  min-width: 70px;
  font-weight: 500;
  color: #666;
}

.mobile-detail-value {
  flex: 1;
  color: #333;
}

.mobile-card-actions {
  display: flex;
  padding: 8px;
  background-color: #f9f9f9;
}

.mobile-btn {
  flex: 1;
  padding: 10px 5px;
  border: none;
  background-color: #f1f1f1;
  color: #333;
  font-size: 14px;
  font-weight: 500;
  border-radius: 4px;
  margin: 0 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
}

.mobile-btn-details {
  background-color: #e0e0e0;
}

.mobile-btn-move {
  background-color: #ffc107;
  color: #212529;
}

.mobile-expanded-content {
  padding: 12px;
  background-color: #f5f5f5;
  border-top: 1px solid #e0e0e0;
}

.mobile-section {
  margin-bottom: 15px;
}

.mobile-section:last-child {
  margin-bottom: 0;
}

.mobile-section h4 {
  margin: 0 0 8px 0;
  font-size: 15px;
  color: #444;
  padding-bottom: 5px;
  border-bottom: 1px solid #ddd;
}

.mobile-fnsku-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.mobile-fnsku-item {
  background-color: #e0e0e0;
  padding: 6px 10px;
  border-radius: 15px;
  font-size: 13px;
}

.mobile-serial-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.mobile-serial-item {
  background-color: #fff;
  padding: 10px;
  border-radius: 4px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
  border: 1px solid #e0e0e0;
}

.mobile-serial-detail {
  display: flex;
  margin-bottom: 4px;
}

.mobile-serial-label {
  min-width: 60px;
  font-weight: 500;
  color: #666;
}

.mobile-serial-value {
  flex: 1;
}

.mobile-empty {
  padding: 10px;
  color: #666;
  font-style: italic;
  text-align: center;
}

/* Responsive styles */
@media (max-width: 768px) {
  .desktop-view {
    display: none;
  }
  
  .mobile-view {
    display: block;
  }
  
  .top-header {
    flex-direction: column;
    gap: 10px;
    padding: 12px;
  }
  
  .store-filter {
    width: 100%;
    display: flex;
    justify-content: space-between;
  }
  
  .store-filter label {
    min-width: 50px;
  }
  
  .store-select {
    flex: 1;
    max-width: calc(100% - 60px);
  }
  
  .module-title {
    font-size: 18px;
    margin: 5px 0;
  }
  
  .header-buttons {
    width: 100%;
    justify-content: space-between;
  }
  
  .scan-button, .shipment-button {
    flex: 1;
    justify-content: center;
    font-size: 13px;
    padding: 8px 5px;
  }
  
  .pagination-container {
    padding: 0 10px;
  }
  
  .pagination-wrapper {
    flex-direction: column;
    gap: 10px;
    width: 100%;
  }
  
  .pagination {
    width: 100%;
    justify-content: space-between;
  }
  
  .pagination-button {
    flex: 1;
    justify-content: center;
    padding: 8px 5px;
  }
  
  .per-page-selector {
    width: 100%;
    justify-content: center;
  }
  
  .per-page-select {
    width: 100%;
    max-width: 200px;
  }
  
  .move-modal-content {
    width: 95%;
    max-height: 90vh;
  }
}

@media (min-width: 769px) and (max-width: 1024px) {
  .action-buttons {
    flex-direction: row;
    flex-wrap: wrap;
  }
  
  .btn-print, .btn-details, .btn-move {
    flex-basis: calc(33.33% - 5px);
    font-size: 12px;
    padding: 6px 3px;
  }
}
</style>
