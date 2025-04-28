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
                    <div class="product-image clickable" @click="viewProductImage(item)">
                      <img 
                        :src="item.useDefaultImage ? defaultImagePath : getImagePath(item.ASIN)" 
                        :alt="item.AStitle" 
                        class="product-thumbnail" 
                        @error="handleImageError($event, item)" 
                      />
                    </div>
                    <div class="product-info">
                      <p class="product-name clickable" @click="viewProductDetails(item)">
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
              <td :class="{'item-count-cell': true, 'item-count-warning': !item.countValid}">
                {{ item.item_count }}
                <i v-if="!item.countValid" class="fas fa-exclamation-circle" title="Item count doesn't match serial numbers"></i>
              </td>
              <td>
                <div class="action-buttons">
                  <button class="btn-print" @click="printLabel(item.ProductID)">
                    <i class="fas fa-print"></i> Print
                  </button>
                  <button class="btn-expand" @click="toggleDetails(index)">
                    {{ expandedRows[index] ? 'Hide Serials' : 'Show Serials' }}
                  </button>
                  <button class="btn-details" @click="viewProductDetails(item)">
                    <i class="fas fa-info-circle"></i> More Details
                  </button>
                  <button class="btn-process" @click="openProcessModal(item)">
                    <i class="fas fa-cogs"></i> Process
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
            <div class="mobile-product-image clickable" @click="viewProductImage(item)">
              <img 
                :src="item.useDefaultImage ? defaultImagePath : getImagePath(item.ASIN)" 
                :alt="item.AStitle" 
                class="product-thumbnail-mobile" 
                @error="handleImageError($event, item)" 
              />
            </div>
            <div class="mobile-product-info">
              <h3 class="mobile-product-name clickable" @click="viewProductDetails(item)">{{ item.AStitle }}</h3>
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
              <span :class="{'mobile-detail-value': true, 'item-count-warning': !item.countValid}">
                {{ item.item_count }}
                <i v-if="!item.countValid" class="fas fa-exclamation-circle" title="Item count doesn't match serial numbers"></i>
              </span>
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
            <button class="mobile-btn mobile-btn-expand" @click="toggleDetails(index)">
              <i class="fas fa-list"></i> {{ expandedRows[index] ? 'Hide' : 'Serials' }}
            </button>
            <button class="mobile-btn mobile-btn-details" @click="viewProductDetails(item)">
              <i class="fas fa-info-circle"></i> Details
            </button>
            <button class="mobile-btn mobile-btn-process" @click="openProcessModal(item)">
              <i class="fas fa-cogs"></i> Process
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
    
    <!-- Process Items Modal (Replaces Move Items Modal) -->
    <div v-if="showProcessModal" class="process-modal">
      <div class="process-modal-content">
        <div class="process-modal-header">
          <h2>Process Items</h2>
          <button class="process-modal-close" @click="closeProcessModal">&times;</button>
        </div>
        <div class="process-modal-body">
          <div class="process-form">
            <div class="form-group">
              <label>Shipment Type:</label>
              <select v-model="processShipmentType" class="form-control">
                <option value="For Dispense">For Dispense</option>
                <option value="For Replacement">For Replacement</option>
              </select>
            </div>
            <div class="form-group">
              <label>Tracking Number:</label>
              <input type="text" v-model="processTrackingNumber" class="form-control" placeholder="Enter tracking number...">
            </div>
            <div class="form-group">
              <label>Notes (optional):</label>
              <textarea v-model="processNotes" class="form-control" placeholder="Add notes about this process..."></textarea>
            </div>
            <div class="form-group" v-if="singleItemSelected">
              <label>New Location (optional):</label>
              <input type="text" v-model="processLocation" class="form-control" placeholder="e.g., L123A or Floor">
            </div>
          </div>
          <div class="process-item-list">
            <h3>Items to Process</h3>
            <div class="process-item-selector">
              <label class="select-all-checkbox">
                <input type="checkbox" v-model="selectAllItems" @change="toggleAllItems">
                <span>Select All</span>
              </label>
              <div class="process-items-container">
                <div v-for="serial in currentProcessItem.serials" :key="serial.ProductID" class="process-item-row">
                  <label class="process-item-checkbox">
                    <input type="checkbox" v-model="selectedItems" :value="serial.ProductID">
                    <span>{{ formatRTNumber(serial.rtcounter, currentProcessItem.storename) }} - {{ serial.serialnumber }}</span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="process-modal-footer">
          <button class="btn-cancel" @click="closeProcessModal">Cancel</button>
          <button class="btn-print-selected" @click="printSelectedItems" :disabled="!hasSelectedItems">
            <i class="fas fa-print"></i> Print Selected
          </button>
          <button class="btn-update-location" @click="updateSelectedLocation" :disabled="!hasSelectedItems || !processLocation">
          <i class="fas fa-map-marker-alt"></i> Update Location
        </button>
          <button class="btn-merge" @click="mergeSelectedItems" :disabled="selectedItems.length < 2">
            <i class="fas fa-object-group"></i> Merge Items
          </button>
          <button class="btn-process-submit" @click="submitProcess" :disabled="!isProcessFormValid">
            <i class="fas fa-check"></i> Submit Process
          </button>
        </div>
      </div>
    </div>
    <!-- Product Details Modal -->
    <div v-if="showProductDetailsModal" class="product-details-modal">
      <div class="product-details-content">
        <div class="product-details-header">
          <h2>Product Details</h2>
          <button class="product-details-close" @click="closeProductDetailsModal">&times;</button>
        </div>
        
        <!-- Product details body with improved layout -->
        <div class="product-details-body" v-if="selectedProduct">
          <div class="product-details-layout">
            <!-- Left Column: Image and Basic Info -->
            <div class="product-details-left">
              <div class="product-details-image clickable" @click="enlargeImage = !enlargeImage">
                <img 
                  :src="selectedProduct.useDefaultImage ? defaultImagePath : getImagePath(selectedProduct.ASIN)" 
                  :alt="selectedProduct.AStitle" 
                  :class="['product-details-thumbnail', enlargeImage ? 'enlarged' : '']" 
                  @error="handleImageError($event, selectedProduct)" 
                />
              </div>
              <div class="product-details-info">
                <h3 class="product-details-title">{{ selectedProduct.AStitle }}</h3>
                <div class="product-details-row">
                  <span class="product-details-label">ASIN:</span>
                  <span class="product-details-value">{{ selectedProduct.ASIN }}</span>
                </div>
                <div class="product-details-row">
                  <span class="product-details-label">MSKU/SKU:</span>
                  <span class="product-details-value">{{ selectedProduct.MSKUviewer }}</span>
                </div>
                <div class="product-details-row">
                  <span class="product-details-label">Store:</span>
                  <span class="product-details-value">{{ selectedProduct.storename }}</span>
                </div>
                <div class="product-details-row">
                  <span class="product-details-label">Grading:</span>
                  <span class="product-details-value">{{ selectedProduct.grading }}</span>
                </div>
                <div class="product-details-row">
                  <span class="product-details-label">FBM Available:</span>
                  <span class="product-details-value">{{ selectedProduct.FBMAvailable }}</span>
                </div>
                <div class="product-details-row">
                  <span class="product-details-label">FBA Available:</span>
                  <span class="product-details-value">{{ selectedProduct.FbaAvailable }}</span>
                </div>
                <div class="product-details-row">
                  <span class="product-details-label">Item Count:</span>
                  <span :class="{'product-details-value': true, 'item-count-warning': !selectedProduct.countValid}">
                    {{ selectedProduct.item_count }}
                    <i v-if="!selectedProduct.countValid" class="fas fa-exclamation-circle" title="Item count doesn't match serial numbers"></i>
                  </span>
                </div>
                
                <div class="product-details-fnskus-section">
                  <h4>FNSKUs</h4>
                  <div class="product-details-fnskus">
                    <span v-for="fnsku in selectedProduct.fnskus" :key="fnsku.FNSKU || fnsku" class="product-details-fnsku">
                      {{ fnsku.FNSKU || fnsku }}
                    </span>
                    <span v-if="!selectedProduct.fnskus || selectedProduct.fnskus.length === 0" class="product-details-empty">
                      No FNSKUs found
                    </span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Right Column: Serial Numbers & Locations -->
            <div class="product-details-right">
              <div class="product-details-section serial-section">
                <h4>Serial Numbers & Locations</h4>
                <div class="product-details-serials">
                  <table class="product-details-table">
                    <thead>
                      <tr>
                        <th>RT#</th>
                        <th>Serial Number</th>
                        <th>Location</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="serial in selectedProduct.serials" :key="serial.ProductID">
                        <td>{{ formatRTNumber(serial.rtcounter, selectedProduct.storename) }}</td>
                        <td>{{ serial.serialnumber }}</td>
                        <td>{{ serial.warehouselocation }}</td>
                      </tr>
                      <tr v-if="!selectedProduct.serials || selectedProduct.serials.length === 0">
                        <td colspan="3" class="text-center">No serial numbers found</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="product-details-footer">
          <button class="btn-print-details" @click="printLabel(selectedProduct.ProductID)">
            <i class="fas fa-print"></i> Print Label
          </button>
          <button class="btn-process-details" @click="openProcessModalFromDetails(selectedProduct)">
            <i class="fas fa-cogs"></i> Process Items
          </button>
          <button class="btn-close-details" @click="closeProductDetailsModal">Close</button>
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
      
      // For process modal (replaces move modal)
      showProcessModal: false,
      processShipmentType: 'For Dispense',
      processTrackingNumber: '',
      processNotes: '',
      processLocation: '',    
      currentProcessItem: null,
      currentProductId: null, // Added to store the product ID
      currentProductAsin: null, // Added to store the ASIN
      currentProductTitle: '', // Added to store the product title
      selectedItems: [],
      selectAllItems: false,
      isProcessing: false,
      
      // For product details modal
      showProductDetailsModal: false,
      selectedProduct: null,
      enlargeImage: false, // For toggling enlarged image view
      
      // For image handling
      defaultImagePath: '/images/default-product.png'
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
    },
    // Check if only one item is selected
    singleItemSelected() {
      return this.selectedItems.length === 1;
    },
    // Check if any items are selected
    hasSelectedItems() {
      return this.selectedItems.length > 0;
    },
    // Check if process form is valid for submission
    isProcessFormValid() {
      // Basic validation for processing - require shipment type and tracking number
      return this.processShipmentType && 
             this.processTrackingNumber && 
             this.selectedItems.length > 0;
    }
  },
  methods: {
    // Function to get the image path based on ASIN
    getImagePath(asin) {
      // Direct path return without checks to prevent blinking
      return asin ? `/images/asinimg/${asin}_0.png` : this.defaultImagePath;
    },
    
    // Simplified image error handling that just swaps to default image
    handleImageError(event, item) {
      // Immediately set the source to default image
      event.target.src = this.defaultImagePath;
      
      // Mark this item to use default image from now on
      if (item) item.useDefaultImage = true;
    },
    
    // Add this method to create an SVG placeholder
    createDefaultImageSVG() {
      return `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' width='100' height='100'%3E%3Crect width='100' height='100' fill='%23f0f0f0'/%3E%3Cpath d='M35,30L65,30L65,70L35,70Z' fill='%23e0e0e0' stroke='%23bbbbbb' stroke-width='2'/%3E%3Cpath d='M45,40L55,40L55,60L45,60Z' fill='%23d0d0d0' stroke='%23bbbbbb'/%3E%3Cpath d='M35,80L65,80L65,85L35,85Z' fill='%23e0e0e0'/%3E%3C/svg%3E`;
    },
    
    // Add a separate method for viewing product image
    viewProductImage(item) {
      // Set the selected product and open the modal directly
      this.selectedProduct = item;
      this.showProductDetailsModal = true;
    },
    
    // Regular product details modal
    viewProductDetails(item) {
      this.selectedProduct = item;
      this.showProductDetailsModal = true;
    },
    
    // Close product details modal
    closeProductDetailsModal() {
      this.showProductDetailsModal = false;
      this.selectedProduct = null;
      this.enlargeImage = false; // Reset enlarged state
    },
    
    // Open process modal from product details
    openProcessModalFromDetails(item) {
      this.closeProductDetailsModal();
      this.openProcessModal(item);
    },
    
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
    
    // Add this method to validate the item count against serials
    validateItemCount(item) {
      if (!item) return true;
      
      // If no serials, just use the item_count value
      if (!item.serials || item.serials.length === 0) {
        return true;
      }
      
      // Compare the actual serials count with the reported item_count
      const serialCount = item.serials.length;
      return serialCount === item.item_count;
    },
    
    // Modified fetchInventory with count validation
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

        // Initialize items with checked property and useDefaultImage flag
        this.inventory = (response.data.data || []).map(item => {
          const itemWithFlags = {
            ...item,
            checked: false,
            serials: item.serials || [],
            fnskus: item.fnskus || [],
            useDefaultImage: false, // Add this flag
            countValid: true // Add a flag for item count validation
          };
          
          // Validate the item count
          itemWithFlags.countValid = this.validateItemCount(itemWithFlags);
          
          return itemWithFlags;
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
    
    // Process modal functions
    openProcessModal(item) {
    this.currentProcessItem = item;
    this.showProcessModal = true;
    this.processShipmentType = 'For Dispense';
    this.processTrackingNumber = '';
    this.processNotes = '';
    this.processLocation = '';
    this.selectedItems = [];
    this.selectAllItems = false;
    
    // Store the parent product ID (ASIN level) - hidden from UI
    this.currentProductId = item.ProductID || null;
    this.currentProductAsin = item.ASIN || null;
    this.currentProductTitle = item.AStitle || '';
    
    // If the item has just one serial number, pre-select it and show its location
    if (item.serials && item.serials.length === 1) {
      const singleSerial = item.serials[0];
      this.selectedItems = [singleSerial.ProductID];
      this.processLocation = singleSerial.warehouselocation || '';
      
      // Use nextTick to ensure the input is rendered before focusing
      this.$nextTick(() => {
        // Focus and select all text in the location field for easy editing
        const locationInput = document.querySelector('.process-modal .form-control[placeholder="e.g., L123A or Floor"]');
        if (locationInput) {
          locationInput.focus();
          locationInput.select();
        }
      });
    }
  },
    
    closeProcessModal() {
      this.showProcessModal = false;
      this.currentProcessItem = null;
      this.selectedItems = [];
    },
    
    // Toggle selection of all items
    toggleAllItems() {
      if (this.selectAllItems) {
        // Select all items
        this.selectedItems = this.currentProcessItem.serials.map(serial => serial.ProductID);
        
        // Clear location field when multiple items are selected
        if (this.selectedItems.length > 1) {
          this.processLocation = '';
        }
      } else {
        // Deselect all items
        this.selectedItems = [];
        this.processLocation = '';
      }
    },
    
    // Submit the process
    async submitProcess() {
      if (!this.isProcessFormValid) return;
      
      try {
        // Start loading state
        this.isProcessing = true;
        
        // Prepare data for API
        const processData = {
          shipmentType: this.processShipmentType,
          trackingNumber: this.processTrackingNumber,
          notes: this.processNotes,
          items: this.selectedItems
        };
        
        // Send to API
        const response = await axios.post('/api/stockroom/process-items', processData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        if (response.data.success) {
          // Show success message
          alert(`Successfully processed ${this.selectedItems.length} items`);
          this.closeProcessModal();
          // Refresh inventory
          this.fetchInventory();
        } else {
          // Show error message
          alert(`Error: ${response.data.message || 'Failed to process items'}`);
        }
      } catch (error) {
        console.error('Error processing items:', error);
        alert('Failed to process items. Please try again.');
      } finally {
        this.isProcessing = false;
      }
    },
    
    
    // Update location for a single selected item
    async updateSelectedLocation() {
      if (!this.hasSelectedItems) {
        alert('Please select at least one item to update location.');
        return;
      }
      
      if (!this.processLocation) {
        alert('Please enter a new location.');
        return;
      }
      
      // Validate location format
      const locationRegex = /^L\d{3}[A-G]$/i;
      const isValid = locationRegex.test(this.processLocation.trim()) || 
                    this.processLocation.trim() === 'Floor' || 
                    this.processLocation.trim() === 'L800G';
                    
      if (!isValid) {
        alert('Invalid Location Format (use L###X, Floor, or L800G)');
        return;
      }
      
      try {
        // Show loading state
        this.isProcessing = true;
        
        // Prepare update data
        const updateData = {
          itemId: this.singleItemSelected ? this.selectedItems[0] : null, // For backward compatibility
          itemIds: this.selectedItems,
          newLocation: this.processLocation
        };
        
        console.log('Sending update data:', updateData); // Add this for debugging
        
        // Send to API
        const response = await axios.post(`${API_BASE_URL}/api/stockroom/update-location`, updateData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        if (response.data.success) {
          // Show success message with item count
          const itemCount = this.selectedItems.length;
          const itemText = itemCount === 1 ? 'item' : 'items';
          alert(`Location updated successfully for ${itemCount} ${itemText}`);
          
          this.closeProcessModal();
          // Refresh inventory
          this.fetchInventory();
        } else {
          alert(`Error: ${response.data.message || 'Failed to update location'}`);
        }
      } catch (error) {
        console.error('Error updating location:', error);
        if (error.response && error.response.data) {
          console.error('Server response:', error.response.data);
          alert(`Failed to update location: ${error.response.data.message || 'Unknown error'}`);
        } else {
          alert('Failed to update location. Please try again.');
        }
      } finally {
        this.isProcessing = false;
      }
    },
    
    // Print selected items
    printSelectedItems() {
      if (!this.hasSelectedItems) {
        alert('Please select at least one item to print.');
        return;
      }
      
      this.selectedItems.forEach(itemId => {
        this.printLabel(itemId);
      });
      
      alert(`Printing ${this.selectedItems.length} labels...`);
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
    
    // Fixed handleLocationInput method
    handleLocationInput() {
      // Only perform validation in auto mode
      if (!this.showManualInput) {
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
        
        // Only in auto mode, process scan after valid location input
        if (isValid && this.locationInput.trim().length > 0) {
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
        
        // Validate location format - this should happen for both auto and manual mode at submission time
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


 // Updated mergeSelectedItems function with correct API URL format
 async mergeSelectedItems() {
  if (this.selectedItems.length < 2) {
    alert('Please select at least two items to merge.');
    return;
  }
  
  // When merging from process modal, we know all items belong to the same product
  // Make sure to get the title directly from the modal item
  let productTitle = '';
  let productAsin = '';
  let productStore = '';
  let selectedSerials = [];
  let selectedFnsku = ''; // Add variable for FNSKU
  
  if (this.currentProcessItem) {
    // We're in the process modal, so use the title from the current process item
    productTitle = this.currentProcessItem.AStitle || '';
    productAsin = this.currentProcessItem.ASIN || '';
    productStore = this.currentProcessItem.storename || '';
    
    console.log("Using process modal title:", productTitle);
    
    // Get just the serial numbers of selected items
    selectedSerials = this.currentProcessItem.serials
      .filter(serial => this.selectedItems.includes(serial.ProductID))
      .map(serial => serial.serialnumber);
      
    // Get the first available FNSKU for this product if any
    if (this.currentProcessItem.fnskus && this.currentProcessItem.fnskus.length > 0) {
      selectedFnsku = this.currentProcessItem.fnskus[0].FNSKU || this.currentProcessItem.fnskus[0];
      console.log("Using FNSKU from current process item:", selectedFnsku);
    }
  } else {
    // If not in process modal, find the product information
    const firstSelectedId = this.selectedItems[0];
    for (const item of this.inventory) {
      if (item.serials && item.serials.some(serial => serial.ProductID === firstSelectedId)) {
        productTitle = item.AStitle || '';
        productAsin = item.ASIN || '';
        productStore = item.storename || '';
        
        // Get the first available FNSKU for this product if any
        if (item.fnskus && item.fnskus.length > 0) {
          selectedFnsku = item.fnskus[0].FNSKU || item.fnskus[0];
          console.log("Found FNSKU from inventory:", selectedFnsku);
        }
        
        console.log("Found title from inventory:", productTitle);
        break;
      }
    }
    
    // Get serial numbers from the inventory
    for (const id of this.selectedItems) {
      for (const item of this.inventory) {
        if (item.serials) {
          const serial = item.serials.find(s => s.ProductID === id);
          if (serial) {
            selectedSerials.push(serial.serialnumber);
          }
        }
      }
    }
  }
  
  if (!productTitle) {
    alert('Could not determine product title for merging.');
    return;
  }

  console.log("Final title being sent:", productTitle);
  console.log("Number of items being merged:", this.selectedItems.length);
  console.log("FNSKU being sent:", selectedFnsku);
  
  if (confirm(`Are you sure you want to merge ${this.selectedItems.length} items of "${productTitle}"?`)) {
    try {
      // Start loading state
      this.isProcessing = true;
      
      // Prepare merge data with all required information
      const mergeData = {
        items: this.selectedItems,
        title: productTitle,
        asin: productAsin,
        store: productStore,
        serialNumbers: selectedSerials,
        fnsku: selectedFnsku // Add FNSKU to merge data
      };
      
      console.log("Sending merge data:", mergeData);
      
      // Send to API using the correct API_BASE_URL format
      const response = await axios.post(`${API_BASE_URL}/api/stockroom/merge-items`, mergeData, {
        withCredentials: true,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        }
      });
      
      if (response.data.success) {
        // Show success message with new RT number
        const newRtNumber = response.data.newrt;
        const productId = response.data.productid;
        const mergedTitle = response.data.title || productTitle;
        const mergedFnsku = response.data.fnsku || selectedFnsku;
        
        // Format the RT number based on the store
        let storeNameForRt = response.data.store || productStore;
        const formattedRt = this.formatRTNumber(newRtNumber, storeNameForRt);
        
        // Show success alert with details
        alert(`Items successfully merged into new item ${formattedRt}: ${mergedTitle}${mergedFnsku ? ` (FNSKU: ${mergedFnsku})` : ''}`);
        
        // Ask if user wants to print the new label
        if (confirm('Do you want to print a label for the newly created item?')) {
          await this.printLabel(productId);
        }
        
        this.closeProcessModal();
        // Refresh inventory
        this.fetchInventory();
      } else {
        alert(`Error: ${response.data.message || 'Failed to merge items'}`);
      }
    } catch (error) {
      console.error('Error merging items:', error);
      alert('Failed to merge items. Please try again.');
    } finally {
      this.isProcessing = false;
    }
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
    
    // Methods for handling responsiveness
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
  },
  // Watch for changes to selectedItems to update location field
  selectedItems(newValue) {
    // If exactly one item is selected, try to get its current location
    if (newValue.length === 1 && this.currentProcessItem && this.currentProcessItem.serials) {
      const selectedSerial = this.currentProcessItem.serials.find(serial => serial.ProductID === newValue[0]);
      if (selectedSerial) {
        this.processLocation = selectedSerial.warehouselocation || '';
      }
    } else if (newValue.length > 1) {
      // Clear location when multiple items are selected
      this.processLocation = '';
    }
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
    
    // Set the default image to our SVG
    this.defaultImagePath = this.createDefaultImageSVG();
    
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
  beforeUnmount() {
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
/* Process button styling */
.btn-process {
  background-color: #6c5ce7; /* Purple color for process button */
  color: white;
  border: 1px solid #5741d9;
  border-radius: 3px;
  cursor: pointer;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 5px;
  white-space: nowrap;
  transition: background-color 0.2s;
}

.btn-process:hover {
  background-color: #5741d9;
}

.mobile-btn-process {
  background-color: #6c5ce7;
  color: white;
  border: none;
}

/* Process Modal Styling */
.process-modal {
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

.process-modal-content {
  background-color: white;
  border-radius: 8px;
  width: 90%;
  max-width: 700px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

.process-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid #ddd;
}

.process-modal-header h2 {
  margin: 0;
  font-size: 18px;
  color: #333;
}

.process-modal-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #666;
}
.process-modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.process-form {
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
  color: #444;
}

.form-control {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

textarea.form-control {
  min-height: 80px;
}

.process-item-list {
  background-color: #f8f9fa;
  padding: 15px;
  border-radius: 4px;
  border: 1px solid #eee;
}

.process-item-list h3 {
  margin-top: 0;
  font-size: 16px;
  margin-bottom: 15px;
  border-bottom: 1px solid #ddd;
  padding-bottom: 8px;
  color: #444;
}

.process-item-selector {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.select-all-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
  padding: 5px 0;
  border-bottom: 1px solid #eee;
  margin-bottom: 5px;
}

.process-items-container {
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid #eee;
  border-radius: 4px;
  padding: 5px;
}

.process-item-row {
  padding: 6px 8px;
  border-bottom: 1px solid #f0f0f0;
}

.process-item-row:last-child {
  border-bottom: none;
}

.process-item-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.process-modal-footer {
  padding: 15px 20px;
  border-top: 1px solid #ddd;
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
}

/* Footer Buttons */
.btn-cancel,
.btn-print-selected,
.btn-update-location,
.btn-merge,
.btn-process-submit {
  padding: 8px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 5px;
}

.btn-cancel {
  background-color: #6c757d;
  color: white;
  border: none;
}

.btn-print-selected {
  background-color: #17a2b8;
  color: white;
  border: none;
}

.btn-update-location {
  background-color: #fd7e14;
  color: white;
  border: none;
}

.btn-merge {
  background-color: #20c997;
  color: white;
  border: none;
}

.btn-process-submit {
  background-color: #28a745;
  color: white;
  border: none;
}

.btn-process-details {
  background-color: #6c5ce7;
  color: white;
  border: none;
  padding: 10px 15px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
}

/* Disabled button styles */
.btn-print-selected:disabled,
.btn-update-location:disabled,
.btn-merge:disabled,
.btn-process-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Responsive styles */
@media (max-width: 768px) {
  .process-modal-footer {
    flex-direction: column;
    align-items: stretch;
  }
  
  .btn-cancel,
  .btn-print-selected,
  .btn-update-location,
  .btn-merge,
  .btn-process-submit {
    width: 100%;
    justify-content: center;
  }
  
  .process-modal-content {
    width: 95%;
    max-height: 95vh;
  }
}

.vue-container {
  font-family: Arial, sans-serif;
  background-color: #ffffffec;
}

.vue-title {
  text-align: left;
  font-size: 1.5rem;
  margin-bottom: 10px;
  background-color: #578FCA;
  padding: 5px;
  color: #111111;
  font-weight: bold;
}

.table-container {
  overflow-x: auto;
  border: 1px solid #ddd;
  border-radius: 6px;
  background: #fff;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

thead {
  background-color: #f7f7f7;
  border-bottom: 2px solid #ddd;
}

th {
  text-align: left;
  padding: 10px 12px;
  font-weight: bold;
  color: #333;
  white-space: nowrap;
}

tbody tr:nth-child(even) {
  background-color: #f9f9f9;
}

tbody tr:hover {
  background-color: #f1f1f1;
}

td {
  padding: 12px;
  vertical-align: middle;
  color: #333;
  text-align: left;
  white-space: normal;
}

.checkbox-container {
  width: 10px;
  
}

.placeholder-date {
  font-size: 0.85rem;
  color: #666;
}

.product-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.product-thumbnail {
  width: 200px;
  height: 200px;
  object-fit: cover;
  border-radius: 4px;
  border: 1px solid #ddd;
}

.product-name {
  color: #0073bb;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
}

.product-name:hover {
  text-decoration: underline;
}

.more-details-btn {
  background-color: #0073bb;
  color: white;
  border: none;
  padding: 5px 10px;
  cursor: pointer;
  font-size: 0.85rem;
  border-radius: 4px;
  margin-left: 10px;
}

.more-details-btn:hover {
  background-color: #0056a3;
}

.expanded-row {
  background-color: #eef7ff;
}

.expanded-content {
  padding: 10px;
  font-size: 0.9rem;
  color: #333;
  border-top: 1px solid #ddd;
}

.pagination {
  margin-top: 20px;
  margin-bottom: 20px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
}

.pagination-info {
  font-size: 1rem;
  color: #555;
}

.pagination-button {
  padding: 5px 10px;
  font-size: 0.9rem;
  color: #fff;
  background-color: #578FCA;
  border: none;
  border-radius: 2px;
  cursor: pointer;
}

.pagination-button:disabled {
  background-color: #ddd;
  cursor: not-allowed;
}

.pagination-button:not(:disabled):hover {
  background-color: #0056a3;
}
.Mobile{
  display: none;
}
.btn-moredetails{
  background-color: rgb(255, 255, 255);
  border: 1px solid rgb(122, 122, 122);
  font-weight: bold;
  font-size: 12px;
  border-radius: 2px;
  margin-bottom: 5px;

}
@media (max-width: 768px) {
  .Desktop{
    display: none;
  }
  .Mobile{
    display: block;
  }
  .vue-title {
    font-size: 1.2rem;
  }

  .table-container {
    overflow-x: auto;
  }

  th, td {
    padding: 8px;
    font-size: 0.85rem;
  }
  .product-thumbnail {
 
    margin-top: 150px;
    right: 10px;
    position: absolute;
  }
}
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
  border-radius: 4px;
  overflow: hidden;
  position: relative;
}

.product-thumbnail, .product-thumbnail-mobile {
  width: 100%;
  height: 100%;
  object-fit: contain;
  transition: transform 0.2s ease;
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

/* Clickable elements */
.clickable {
  cursor: pointer;
  transition: all 0.2s ease;
}

.product-image.clickable:hover img, 
.mobile-product-image.clickable:hover img {
  transform: scale(1.1); /* Slight zoom on hover */
}

.product-image.clickable:hover,
.mobile-product-image.clickable:hover {
  box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.product-name.clickable:hover {
  color: #4a90e2;
  text-decoration: underline;
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

.btn-print, .btn-details, .btn-expand {
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

.btn-print:hover, .btn-details:hover, .btn-expand:hover {
  background-color: #e0e0e0;
}

.btn-details {
  background-color: #007bff; /* Blue color for details button */
  color: white;
  border: 1px solid #0069d9;
}

.btn-details:hover {
  background-color: #0069d9;
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

/* Product Details Modal */
.product-details-modal {
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

.product-details-content {
  background-color: white;
  border-radius: 8px;
  width: 95%;
  max-width: 1000px; /* Increased from 800px */
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
}

.product-details-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid #ddd;
}

.product-details-header h2 {
  margin: 0;
  font-size: 20px;
  color: #333;
}

.product-details-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #666;
}

.product-details-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  overflow-y: auto;
}

/* Product details layout */
.product-details-layout {
  display: flex;
  flex-direction: row;
  gap: 20px;
  width: 100%;
}

.product-details-left {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.product-details-right {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.product-details-image {
  width: 100%;
  height: auto;
  min-height: 300px;
  max-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f8f9fa;
  padding: 10px;
  border-radius: 4px;
  overflow: hidden;
  cursor: pointer;
  margin-bottom: 15px;
}

.product-details-thumbnail {
  max-width: 100%;
  max-height: 380px;
  object-fit: contain;
  transition: transform 0.3s ease;
}

.product-details-thumbnail.enlarged {
  transform: scale(1.8);
  max-height: none;
  z-index: 10;
}

.product-details-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.product-details-title {
  font-size: 18px;
  margin: 0 0 10px 0;
  color: #333;
  line-height: 1.4;
  font-weight: bold;
}

.product-details-row {
  display: flex;
  padding-bottom: 6px;
  border-bottom: 1px solid #eee;
}

.product-details-label {
  width: 120px;
  font-weight: 500;
  color: #666;
}

.product-details-value {
  flex: 1;
  color: #333;
}

.product-details-fnskus-section {
  margin-top: 15px;
  background-color: #f8f9fa;
  border-radius: 4px;
  padding: 12px;
  border: 1px solid #eee;
}

.product-details-fnskus-section h4 {
  margin-top: 0;
  margin-bottom: 10px;
  font-size: 16px;
  color: #444;
  border-bottom: 1px solid #ddd;
  padding-bottom: 6px;
}

.product-details-fnskus {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.product-details-fnsku {
  background-color: #e9ecef;
  padding: 5px 10px;
  border-radius: 20px;
  font-size: 14px;
  display: inline-block;
}

.serial-section {
  height: 100%;
}

.product-details-serials {
  height: calc(100% - 40px);
  max-height: 500px;
  overflow-y: auto;
}

.product-details-table {
  width: 100%;
  border-collapse: collapse;
}

.product-details-table th, .product-details-table td {
  padding: 8px 10px;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.product-details-table th {
  background-color: #f1f3f5;
  position: sticky;
  top: 0;
  font-weight: 500;
}

.product-details-empty {
  color: #6c757d;
  font-style: italic;
  padding: 10px 0;
  display: block;
  text-align: center;
}

.product-details-footer {
  padding: 15px 20px;
  border-top: 1px solid #ddd;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn-print-details, .btn-close-details {
  padding: 10px 15px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-print-details {
  background-color: #007bff;
  color: white;
  border: none;
}

.btn-close-details {
  background-color: #6c757d;
  color: white;
  border: none;
}

/* Mobile Cards View */
.mobile-view {
  display: none;
}

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
  display: flex;
  padding: 12px;
  border-bottom: 1px solid #eee;
  position: relative;
}

.mobile-checkbox {
  position: absolute;
  top: 12px;
  left: 12px;
}

.mobile-product-image {
  width: 60px;
  height: 60px;
  margin-left: 30px; /* Space for checkbox */
  flex-shrink: 0;
  background-color: #f5f5f5;
  border-radius: 4px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}

.mobile-product-info {
  flex: 1;
  padding-left: 12px;
  min-width: 0;
}

.mobile-product-name {
  margin: 0 0 5px 0;
  font-size: 16px;
  line-height: 1.3;
  font-weight: 600;
  color: #333;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
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
  min-width: 80px;
  font-weight: 500;
  color: #666;
}

.mobile-detail-value {
  flex: 1;
  color: #333;
  word-break: break-word;
}

.mobile-card-actions {
  display: flex;
  padding: 8px;
  background-color: #f9f9f9;
}

.mobile-btn {
  flex: 1;
  padding: 12px 5px;
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
  background-color: #007bff;
  color: white;
}

.mobile-expanded-content {
  padding: 12px;
  background-color: #f5f5f5;
  border-top: 1px solid #e0e0e0;
  overflow: hidden; /* Fix overflow issues */
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
  overflow-x: hidden; /* Prevent horizontal scroll */
}

.mobile-fnsku-item {
  background-color: #e0e0e0;
  padding: 6px 10px;
  border-radius: 15px;
  font-size: 13px;
  display: inline-block;
  margin-bottom: 5px;
  box-sizing: border-box;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mobile-serial-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  box-sizing: border-box;
}

.mobile-serial-item {
  background-color: #fff;
  padding: 10px;
  border-radius: 4px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
  border: 1px solid #e0e0e0;
  width: 100%;
  box-sizing: border-box;
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
  overflow: hidden;
  text-overflow: ellipsis;
}

.mobile-empty {
  padding: 10px;
  color: #666;
  font-style: italic;
  text-align: center;
}

/* Item count warning */
.item-count-warning {
  color: #dc3545 !important;
}

/* Responsive layouts */
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
  
  .product-details-layout {
    flex-direction: column;
  }
  
  .product-details-image {
    min-height: 200px;
    max-height: 250px;
  }
  
  .product-details-thumbnail {
    max-height: 230px;
  }
  
  .product-details-thumbnail.enlarged {
    transform: scale(1.5);
  }
  
  .product-details-serials {
    max-height: 300px;
  }
}

@media (max-width: 480px) {
  .mobile-card-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .mobile-checkbox {
    position: relative;
    top: 0;
    left: 0;
    margin-bottom: 10px;
  }
  
  .mobile-product-image {
    margin-left: 0;
    margin-bottom: 10px;
  }
  
  .mobile-product-info {
    padding-left: 0;
    width: 100%;
  }
  
  .mobile-detail-row {
    flex-direction: column;
    margin-bottom: 10px;
  }
  
  .mobile-detail-label {
    margin-bottom: 2px;
  }
  
  .mobile-detail-value {
    padding-left: 10px;
  }
  
  .mobile-btn {
    padding: 8px 5px;
  }
  
  .product-details-footer {
    flex-direction: column;
    gap: 8px;
  }
  
  .btn-print-details, .btn-process-details, .btn-close-details {
    width: 100%;
    justify-content: center;
  }
}
</style>