<template>
  <div class="vue-container">
    <h1 class="vue-title">Validation Module</h1>
    <!-- Pagination -->
    <div class="pagination">
      <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">Back</button>
      <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
      <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">Next</button>
      
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
              <a style="color:black" @click="sortBy('astitle')" class="sortable">
                Product Name
                <span v-if="sortColumn === 'astitle'">
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
                    <div class="image-count-badge" v-if="countAllImages(item) > 0">
                      +{{ countAllImages(item) }}
                    </div>
                  </div>
               
                  <div class="product-info">
                    <p class="product-name">
                      ID#: 
                      <span v-if="item.storename === 'Allrenewed'">AR {{ item.rtcounter }}</span>
                      <span v-else-if="item.storename === 'Renovartech'">RT {{ item.rtcounter }}</span>
                      <span v-else>{{ item.rtcounter }}</span>
                    </p>
                    <p class="product-name">{{ item.astitle }}</p>
                    <p class="product-name" :style="{ color: item.validation_status === 'validated' ? 'green' : 'orange' }">
                      ({{ item.validation_status }})
                    </p>

                    <p class="Mobile">Location : {{ item.warehouselocation }}</p>
                    <p class="Mobile">Added date : {{ item.datedelivered }}</p>
                    <p class="Mobile">Updated date : {{ item.lastDateUpdate }}</p>
                    <p class="Mobile">Fnsku : {{ item.FNSKUviewer }}</p>
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
                <span><strong></strong> {{ item.FNSKUviewer }}<br>{{ item.asin }}</span>
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

                <span><strong></strong> {{ item.actions }}</span>   

                <button 
                  @click="confirmMoveToLabeling(item)" 
                  class="action-btn btn-labeling"
                  :disabled="isProcessing">
                  <i class="bi bi-check-circle"></i> Move to Labeling
                </button><br>

                <button 
                  @click="confirmMoveToStockroom(item)" 
                  class="action-btn btn-stockroom" 
                  :disabled="isProcessing">
                  <i class="bi bi-box-seam"></i> Move to Stockroom
                </button><br>

                <button 
                    class="btn-validation"
                    @click="openValidationModal(item)">
                    Open Validation
                  </button><br>
              </td>
            </tr>
             <!-- More details results -->
            <tr v-if="expandedRows[index]">
              <td colspan="11">
                <div class="expanded-content p-3 border rounded">
                  <div class="Mobile">
                <br>

                  <span><strong></strong> {{ item.actions }}</span> 
                  
                  <button 
                    @click="confirmMoveToLabeling(item)" 
                    class="action-btn btn-labeling" 
                    :disabled="isProcessing">
                    <i class="bi bi-check-circle"></i> Move to Labeling
                  </button>

                  <button 
                    @click="confirmMoveToStockroom(item)" 
                    class="action-btn btn-stockroom" 
                    :disabled="isProcessing">
                    <i class="bi bi-box-seam"></i> Move to Stockroom
                  </button>

                  </div>
                  <strong>External Title provided by Supplier:</strong> {{ item.ProductTitle }}
                  <br>
                  <strong>Product Name:</strong> {{ item.astitle }}
                  <br>
                  <strong>Store Name:</strong> {{ item.storename }}
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
        <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">Back</button>
        <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
        <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">Next</button>
      </div>
    </div>
    
    <!-- Image Modal with Tabs -->
    <div v-if="showImageModal" class="image-modal">
      <div class="modal-overlay" @click="closeImageModal"></div>
      <div class="modal-content">
        <button class="close-button" @click="closeImageModal">&times;</button>
        
        <!-- Tabs for switching between regular, captured, and ASIN images -->
        <div class="image-tabs">
          <button 
            class="tab-button" 
            :class="{ active: activeTab === 'regular' }"
            @click="switchTab('regular')"
            :disabled="regularImages.length === 0"
          >
            Product Images ({{ regularImages.length }})
          </button>
          <button 
            class="tab-button" 
            :class="{ active: activeTab === 'captured' }"
            @click="switchTab('captured')"
            :disabled="capturedImages.length === 0"
          >
            Captured Images ({{ capturedImages.length }})
          </button>
          <button 
            class="tab-button" 
            :class="{ active: activeTab === 'asin' }"
            @click="switchTab('asin')"
            :disabled="asinImages.length === 0"
          >
            ASIN Images ({{ asinImages.length }})
          </button>
        </div>
        
        <!-- Display message if no images in current category -->
        <div v-if="currentImageSet.length === 0" class="no-images-message">
          No images available in this category
        </div>
        
        <!-- Main image display (only shown if we have images) -->
        <div v-if="currentImageSet.length > 0" class="main-image-container">
          <button class="nav-button prev" @click="prevImage" v-if="currentImageSet.length > 1">&lt;</button>
          <img :src="currentImageSet[currentImageIndex]" alt="Product Image" class="modal-main-image" @error="handleImageError" />
          <button class="nav-button next" @click="nextImage" v-if="currentImageSet.length > 1">&gt;</button>
        </div>
        
        <div class="image-counter" v-if="currentImageSet.length > 0">
          {{ currentImageIndex + 1 }} / {{ currentImageSet.length }}
        </div>
        
        <!-- Thumbnails for the current image set -->
        <div class="thumbnails-container" v-if="currentImageSet.length > 1">
          <div v-for="(image, index) in currentImageSet" 
               :key="index" 
               class="modal-thumbnail" 
               :class="{ active: index === currentImageIndex }"
               @click="currentImageIndex = index">
            <img :src="image" :alt="`Thumbnail ${index + 1}`" @error="handleImageError" />
          </div>
        </div>
      </div>
    </div>

    <!-- Add this confirmation modal HTML to your template section -->
    <!-- Confirmation Modal -->
    <div class="confirmation-modal" v-if="showConfirmationModal">
      <div class="modal-overlay" @click="cancelConfirmation"></div>
      <div class="confirmation-modal-content">
        <div class="confirmation-modal-header">
          <h3>{{ confirmationTitle }}</h3>
          <button class="close-button" @click="cancelConfirmation">&times;</button>
        </div>
        <div class="confirmation-modal-body">
          <p>{{ confirmationMessage }}</p>
        </div>
        <div class="confirmation-modal-footer">
          <button 
            class="btn-cancel" 
            @click="cancelConfirmation">
            Cancel
          </button>
          <button 
            class="btn-confirm" 
            @click="confirmAction"
            :class="{'btn-validation': confirmationActionType === 'validation', 'btn-stockroom': confirmationActionType === 'stockroom'}">
            Yes, Proceed
          </button>
        </div>
      </div>
    </div>

   <!-- Validation Modal -->
    <div class="validation-modal" v-if="showValidationModal && currentValidationItem">
      <div class="modal-overlay" @click="closeValidationModal"></div>
      <div class="validation-modal-content">
        <div class="validation-modal-header">
          <h3>Validate Item #
            
            <span v-if="currentValidationItem.storename === 'Allrenewed'">AR {{ currentValidationItem.rtcounter }}</span>
            <span v-else-if="currentValidationItem.storename === 'Renovartech'">RT {{ currentValidationItem.rtcounter }}</span>
            <span v-else>{{ currentValidationItem.rtcounter }}</span>

          </h3>
          <button class="close-button" @click="closeValidationModal">&times;</button>
        </div>
        
        <div class="validation-modal-body">
          <!-- Product details section -->
          <div class="validation-product-details">
            <h4>Product Details</h4>
            <div class="validation-detail-row">
              <strong>ID Number:</strong> 
              <span>
                <span v-if="currentValidationItem.storename === 'Allrenewed'">AR {{ currentValidationItem.rtcounter }}</span>
                <span v-else-if="currentValidationItem.storename === 'Renovartech'">RT {{ currentValidationItem.rtcounter }}</span>
                <span v-else>{{ currentValidationItem.rtcounter }}</span>
              </span>
            </div>
            <div class="validation-detail-row">
              <strong>Product Name:</strong> 
              <span>{{ currentValidationItem.astitle }}</span>
            </div>
            <div class="validation-detail-row">
              <strong>External Title:</strong> 
              <span>{{ currentValidationItem.ProductTitle }}</span>
            </div>
            <div class="validation-detail-row">
              <strong>FNSKU:</strong> 
              <span>
                {{ currentValidationItem.FNSKUviewer }}
                <template v-if="currentValidationItem.asin">
                  <br>[ASIN: {{ currentValidationItem.asin }}]
                </template>
              </span>
            </div>
            <div class="validation-detail-row">
              <strong>Serial Number:</strong> 
              <span>{{ currentValidationItem.serialnumber }}</span>
            </div>
            <div class="validation-detail-row">
              <strong>Location:</strong> 
              <span>{{ currentValidationItem.warehouselocation }}</span>
            </div>
            <div class="validation-detail-row">
              <strong>Current Status:</strong> 
              <span :style="{ color: currentValidationItem.validation_status === 'validated' ? 'green' : 'orange' }">
                {{ currentValidationItem.validation_status }}
              </span>
            </div>
          </div>
          
          <!-- Product images section with ASIN images tab -->
          <div class="validation-images-section">
            <h4>Product Images</h4>
            
            <!-- New Compare Gallery Section -->
            <div class="compare-gallery">
              <h5>Image Comparison</h5>
              <div class="compare-container">
                <div class="compare-item">
                  <div class="compare-title">Supplier Image</div>
                  <div class="compare-subtitle">{{ currentValidationItem.ProductTitle }}</div>
                  <div class="compare-image-container">
                    <img 
                      :src="'/images/thumbnails/' + currentValidationItem.img1" 
                      :alt="currentValidationItem.ProductTitle || 'Supplier Image'" 
                      @error="handleImageError($event)"
                      class="compare-image"
                    />
                  </div>
                </div>
                <div class="compare-item">
                  <div class="compare-title">From IMS fetch from Amazon</div>
                  <div class="compare-subtitle">{{ currentValidationItem.astitle }}</div>
                  <div class="compare-image-container">
                    <img 
                      v-if="currentValidationItem.asin"
                      :src="`/images/asinimg/${currentValidationItem.asin}.png`" 
                      :alt="currentValidationItem.astitle || 'Amazon Image'" 
                      @error="handleImageError($event)"
                      class="compare-image"
                    />
                    <div v-else class="no-asin-image">
                      No ASIN image available
                    </div>
                  </div>
                </div>
              </div>
            </div>


            <div class="validation-image-tabs">
              <button 
                class="validation-tab-button" 
                :class="{ active: validationActiveTab === 'product' }"
                @click="switchValidationTab('product')"
              >
                Product Images
              </button>
              <button 
                class="validation-tab-button" 
                :class="{ active: validationActiveTab === 'captured' }"
                @click="switchValidationTab('captured')"
              >
                Captured Images
              </button>
              <button 
                class="validation-tab-button" 
                :class="{ active: validationActiveTab === 'asin' }"
                @click="switchValidationTab('asin')"
              >
                ASIN Images
              </button>
            </div>
            
            <!-- Product Images Tab Content -->
            <div v-if="validationActiveTab === 'product'" class="validation-images-grid">
              <!-- Main image display -->
              <div class="validation-main-image">
                <img 
                  :src="'/images/thumbnails/' + currentValidationItem.img1" 
                  :alt="currentValidationItem.astitle" 
                  @error="handleImageError($event)"
                />
              </div>
              
              <!-- Thumbnails gallery -->
              <div class="validation-thumbnails">
                <!-- Regular images thumbnails -->
                <template v-for="i in 15" :key="`img-${i}`">
                  <div 
                    v-if="i > 1 && currentValidationItem['img'+i] && currentValidationItem['img'+i] !== 'NULL'" 
                    class="validation-thumbnail"
                  >
                    <img 
                      :src="'/images/thumbnails/' + currentValidationItem['img'+i]" 
                      :alt="`Image ${i}`" 
                      @error="handleImageError($event)"
                    />
                  </div>
                </template>
              </div>
            </div>
            
            <!-- Captured Images Tab Content -->
            <div v-if="validationActiveTab === 'captured'" class="validation-images-grid">
              <div class="validation-no-images" v-if="!hasValidationCapturedImages">
                No captured images available
              </div>
              <div v-else class="validation-thumbnails-full">
                <template v-if="currentValidationItem.capturedImages">
                  <template v-for="i in 12" :key="`captured-${i}`">
                    <div 
                      v-if="currentValidationItem.capturedImages['capturedimg'+i] && currentValidationItem.capturedImages['capturedimg'+i] !== 'NULL'" 
                      class="validation-thumbnail captured"
                    >
                      <img 
                        :src="'/images/product_images/' + (currentValidationItem.company || 'Airstaffs') + '/' + currentValidationItem.capturedImages['capturedimg'+i]" 
                        :alt="`Captured ${i}`" 
                        @error="handleImageError($event)"
                      />
                    </div>
                  </template>
                </template>
              </div>
            </div>
            
            <!-- ASIN Images Tab Content -->
            <div v-if="validationActiveTab === 'asin'" class="validation-images-grid">
              <div class="validation-no-images" v-if="!currentValidationItem.asin">
                No ASIN information available
              </div>
              <div v-else-if="!currentValidationItemAsinLoaded" class="validation-loading">
                Loading ASIN images...
              </div>
              <div v-else-if="currentValidationItemAsinImages.length === 0" class="validation-no-images">
                No ASIN images available
              </div>
              <div v-else class="validation-thumbnails-full">
                <div v-for="(image, index) in currentValidationItemAsinImages" 
                    :key="`asin-${index}`" 
                    class="validation-thumbnail asin">
                  <img 
                    :src="image"
                    :alt="`ASIN Image ${index + 1}`" 
                    @error="handleImageError($event)"
                  />
                </div>
              </div>
            </div>
          </div>
          
          <!-- Validation notes -->
          <div class="validation-notes-section">
            <!--<h4>Validation Notes</h4>
            <textarea 
              v-model="validationNotes" 
              placeholder="Enter notes about this item (required for invalid items)"
              rows="4"
              class="validation-notes-textarea"
            ></textarea>-->
            
            <!-- Error message display -->
            <div class="validation-error" v-if="validationErrors">
              {{ validationErrors }}
            </div>
          </div>
        </div>
        
        <div class="validation-modal-footer">
          <button 
            class="btn-cancel" 
            @click="closeValidationModal"
            :disabled="isProcessingValidation"
          >
            Cancel
          </button>
          <button 
            class="btn-invalid"
            @click="confirmMarkAsInvalid"
            :disabled="isProcessingValidation"
          >
            <i class="bi bi-x-circle"></i> Mark as Invalid
          </button>
          <button 
            class="btn-valid"
            @click="confirmMarkAsValid"
            :disabled="isProcessingValidation"
          >
            <i class="bi bi-check-circle"></i> Mark as Valid
          </button>
        </div>
      </div>
    </div>

      <!-- Validation Confirmation Modal -->
        
    <div class="validation-confirmation-modal" v-if="showConfirmationModal && confirmationActionType">
      <div class="validation-confirmation-overlay" @click="cancelConfirmation"></div>
      <div class="validation-confirmation-content">
        <div class="validation-confirmation-header" :class="{
          'header-valid': confirmationActionType === 'valid',
          'header-invalid': confirmationActionType === 'invalid',
          'header-default': !['valid', 'invalid'].includes(confirmationActionType)
        }">
          <div class="header-icon-container">
            <i class="header-icon" :class="{
              'bi bi-check-circle-fill': confirmationActionType === 'valid',
              'bi bi-x-circle-fill': confirmationActionType === 'invalid',
              'bi bi-question-circle-fill': !['valid', 'invalid'].includes(confirmationActionType)
            }"></i>
          </div>
          <h3>{{ confirmationTitle }}</h3>
          <button class="close-button" @click="cancelConfirmation">&times;</button>
        </div>
        
        <div class="validation-confirmation-body">
          <p>{{ confirmationMessage }}</p>
        </div>
        
        <div class="validation-confirmation-footer">
          <button 
            class="btn-no" 
            @click="cancelConfirmation">
            <i class="bi bi-x"></i> No
          </button>
          <button 
            class="btn-yes"
            @click="confirmationActionType === 'valid' ? markAsValid() : markAsInvalid()"
            :class="{
              'btn-valid-confirm': confirmationActionType === 'valid', 
              'btn-invalid-confirm': confirmationActionType === 'invalid',
              'btn-default-confirm': !['valid', 'invalid'].includes(confirmationActionType)
            }">
            <i class="bi bi-check-lg"></i> Yes
          </button>
        </div>
      </div>
    </div>
    <!-- End of Validation Confirmation Modal -->

  </div>
</template>

<script>
import axios from 'axios';
import { eventBus } from './eventBus'; // Using your event bus
import '../../css/modules.css';
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
  name: 'ProductList',
  data() {
    return {
      inventory: [],
      currentPage: 1,
      totalPages: 1,
      perPage: 10, // Default rows per page
      selectAll: false,
      expandedRows: {},
      sortColumn: '',
      sortOrder: 'asc',   
      showDetails: false,
      defaultImage: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4=',
      
      // Modal state
      showImageModal: false,
      regularImages: [],      // For regular product images
      capturedImages: [],     // For captured images
      asinImages: [],         // For ASIN images
      activeTab: 'regular',   // Track which tab is active
      currentImageIndex: 0,
      currentImageSet: [],    // The currently displayed image set based on active tab

      showConfirmationModal: false,
      confirmationTitle: '',
      confirmationMessage: '',
      confirmationActionType: '', // 'validation' or 'stockroom'
      currentItemForAction: null, // Store the item to be processed
    
      // New validation modal properties
      showValidationModal: false,
      currentValidationItem: null,
      validationNotes: '',
      isProcessingValidation: false,
      validationErrors: null,
      
      // ASIN related properties
      currentValidationItemAsinImages: [],
      currentValidationItemAsinLoaded: false,
      
      // Validation tabs
      validationActiveTab: 'product',
    
      // Validation confirmation properties
      validationConfirmationTitle: '',
      validationConfirmationMessage: '',
      validationConfirmationType: '', // 'valid' or 'invalid'
      showValidationConfirmationModal: false,
      
      isProcessing: false

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
    },
    hasValidationCapturedImages() {
      if (!this.currentValidationItem || !this.currentValidationItem.capturedImages) return false;
      
      for (let i = 1; i <= 12; i++) {
        const fieldName = `capturedimg${i}`;
        if (this.currentValidationItem.capturedImages[fieldName] && 
            this.currentValidationItem.capturedImages[fieldName] !== 'NULL' && 
            this.currentValidationItem.capturedImages[fieldName].trim() !== '') {
          return true;
        }
      }
      
      return false;
    }
  },
  methods: {
    handleImageError(event) {
      // If image fails to load, use an inline SVG placeholder
      event.target.src = this.defaultImage;
      event.target.onerror = null; // Prevent infinite error loop
    },
    
    // Count additional images based on the image fields (img2-img15)
    countRegularImages(item) {
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
    
    countCapturedImages(item) {
      if (!item || !item.capturedImages) return 0;
      
      // For debugging
      console.log("Checking capturedImages:", item.capturedImages);
      
      let count = 0;
      // Check capturedimg1 through capturedimg12
      for (let i = 1; i <= 12; i++) {
        const fieldName = `capturedimg${i}`;
        if (item.capturedImages && 
            item.capturedImages[fieldName] && 
            item.capturedImages[fieldName] !== 'NULL' && 
            item.capturedImages[fieldName].trim() !== '') {
          count++;
        }
      }
      
      return count;
    },
    
    // Count all images (regular + captured + ASIN)
    countAllImages(item) {
      return this.countRegularImages(item) + this.countCapturedImages(item) + (item.asin ? 1 : 0);
    },
    
    // Open image modal with all available images in separate categories
    async openImageModal(item) {
      if (!item) return;
      
      console.log("Opening modal for item:", item);
      
      // Reset modal state
      this.regularImages = [];
      this.capturedImages = [];
      this.asinImages = [];
      this.currentImageIndex = 0;
      
      // First collect regular images (img1-img15)
      if (item.img1 && item.img1 !== 'NULL' && item.img1.trim() !== '') {
        const mainImagePath = `/images/thumbnails/${item.img1}`;
        this.regularImages.push(mainImagePath);
        console.log("Added main image:", mainImagePath);
      }
      
      // Add regular additional images
      for (let i = 2; i <= 15; i++) {
        const fieldName = `img${i}`;
        if (item[fieldName] && item[fieldName] !== 'NULL' && item[fieldName].trim() !== '') {
          const imagePath = `/images/thumbnails/${item[fieldName]}`;
          this.regularImages.push(imagePath);
          console.log("Added additional image:", imagePath);
        }
      }
      
      // Get company folder for captured image paths
      const companyFolder = item.company || 'Airstaffs';
      
      // Then collect captured images if available
      if (item.capturedImages) {
        console.log("Processing captured images data:", item.capturedImages);
        
        // Check if capturedImages is empty or not a proper object
        const hasCapturedImages = typeof item.capturedImages === 'object' && 
                                Object.keys(item.capturedImages).length > 0;
        
        if (hasCapturedImages) {
          for (let i = 1; i <= 12; i++) {
            const fieldName = `capturedimg${i}`;
            if (item.capturedImages[fieldName] && 
                item.capturedImages[fieldName] !== 'NULL' && 
                item.capturedImages[fieldName].trim() !== '') {
              // Use the exact path based on your server structure
              const imagePath = `/images/product_images/${companyFolder}/${item.capturedImages[fieldName]}`;
              console.log(`Adding captured image path: ${imagePath}`);
              this.capturedImages.push(imagePath);
            }
          }
        } else {
          console.log("Captured images object exists but is empty or invalid");
        }
      } else {
        console.log("No captured images data found for item:", item);
      }
      
      // If no images were found in any category, add a default image to regularImages
      if (this.regularImages.length === 0 && this.capturedImages.length === 0 && this.asinImages.length === 0) {
        const defaultPath = this.defaultImage;
        this.regularImages.push(defaultPath);
        console.log("No images found, using default:", defaultPath);
      }
      
      // Set initial tab based on which images are available
      if (this.regularImages.length > 0) {
        this.activeTab = 'regular';
        this.currentImageSet = this.regularImages;
      } else if (this.capturedImages.length > 0) {
        this.activeTab = 'captured';
        this.currentImageSet = this.capturedImages;
      } else if (this.asinImages.length > 0) {
        this.activeTab = 'asin';
        this.currentImageSet = this.asinImages;
      }
      
      // Show the modal
      this.showImageModal = true;
      
      // Prevent scrolling when modal is open
      document.body.style.overflow = 'hidden';
    },
    
    // Method to switch tabs
    switchTab(tab) {
      this.activeTab = tab;
      this.currentImageIndex = 0;
      
      if (tab === 'regular') {
        this.currentImageSet = this.regularImages;
      } else if (tab === 'captured') {
        this.currentImageSet = this.capturedImages;
      } else if (tab === 'asin') {
        this.currentImageSet = this.asinImages;
      }
    },
    
    // Method to switch tabs in validation modal
    switchValidationTab(tab) {
      this.validationActiveTab = tab;
      
      // If switching to ASIN tab, load ASIN images if not loaded already
      if (tab === 'asin' && !this.currentValidationItemAsinLoaded && this.currentValidationItem) {
        this.loadAsinImagesForValidation();
      }
    },
    
    // Load ASIN images for validation modal
    async loadAsinImagesForValidation() {
      if (!this.currentValidationItem || !this.currentValidationItem.FNSKUviewer) return;
      
      this.currentValidationItemAsinLoaded = false;
      this.currentValidationItemAsinImages = [];
      
      try {
        // Add the ASIN image
        const asinImagePath = `/images/asinimg/${this.currentValidationItem.asin}.png`;
        this.currentValidationItemAsinImages.push(asinImagePath);
        
      } catch (error) {
        console.error('Error loading ASIN images for validation:', error);
      } finally {
        this.currentValidationItemAsinLoaded = true;
      }
    },
    
    closeImageModal() {
      this.showImageModal = false;
      this.currentImageSet = [];
      this.regularImages = [];
      this.capturedImages = [];
      this.asinImages = [];
      
      // Re-enable scrolling
      document.body.style.overflow = 'auto';
    },
    
    nextImage() {
      if (this.currentImageIndex < this.currentImageSet.length - 1) {
        this.currentImageIndex++;
      } else {
        this.currentImageIndex = 0; // Loop back to the first image
      }
    },
    
    prevImage() {
      if (this.currentImageIndex > 0) {
        this.currentImageIndex--;
      } else {
        this.currentImageIndex = this.currentImageSet.length - 1; // Loop to the last image
      }
    },
    
    // Fetch inventory data from the API
    async fetchInventory() {
      try {
        console.log("Fetching inventory with params:", { 
          search: this.searchQuery, 
          page: this.currentPage, 
          per_page: this.perPage, 
          location: 'Validation',
          include_images: true
        });
        
        const response = await axios.get(`${API_BASE_URL}/api/validation/products`, {
          params: { 
            search: this.searchQuery, 
            page: this.currentPage, 
            per_page: this.perPage, 
            location: 'Validation',
            include_images: true
          },
        });

        console.log("API Response:", response.data);
        
        // Process the returned data
        this.inventory = response.data.data;
        this.totalPages = response.data.last_page;
        
        // Debug first item to see structure
        if (this.inventory.length > 0) {
          console.log("First item structure:", this.inventory[0]);
          if (this.inventory[0].capturedImages) {
            console.log("First item capturedImages:", this.inventory[0].capturedImages);
          }
        }
        
      } catch (error) {
        console.error('Error fetching inventory data:', error);
      }
    },
    
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
    
    toggleAll() {
      this.inventory.forEach((item) => (item.checked = this.selectAll));
    },
    
    toggleDetails(index) {
      this.expandedRows = { ...this.expandedRows, [index]: !this.expandedRows[index] };
    },
    
    toggleDetailsVisibility() {
      this.showDetails = !this.showDetails;
    },
    
    sortBy(column) {
      if (this.sortColumn === column) {
        this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortColumn = column;
        this.sortOrder = 'asc';
      }
    },

    // Add these methods to the methods object in your component
    async moveToLabeling(item) {
      if (!item || !item.ProductID) {
        console.error('Invalid item data for moving to Validation');
        return;
      }
      
      try {
        this.isProcessing = true;
        // Get the CSRF token from the meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Make the request with proper data format and headers
        const response = await axios.post(`${API_BASE_URL}/api/validation/move-to-validation`, {
          product_id: item.ProductID,
          rt_counter: item.rtcounter,
          current_location: 'Validation',
          new_location: 'Labeling'
        }, {
          headers: {
            'X-CSRF-TOKEN': csrfToken
          }
        });
        
        console.log("Move to Validation response:", response.data);
        
        if (response.data.success) {
          // Show success message
          alert(`Item ${item.rtcounter} successfully moved to Validation`);
          // Refresh the inventory list
          this.fetchInventory();
        } else {
          alert(response.data.message || 'Failed to move item to Validation');
        }
      } catch (error) {
        console.error('Error moving item to Validation:', error);
        alert('Failed to move item to Validation. Please try again.');
      } finally {
        this.isProcessing = false;
      }
    },

    async moveToStockroom(item) {
      if (!item || !item.ProductID) {
        console.error('Invalid item data for moving to Stockroom');
        return;
      }
      
      try {
        this.isProcessing = true;
        // Get the CSRF token from the meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Make the request with proper data format and headers
        const response = await axios.post(`${API_BASE_URL}/api/validation/move-to-stockroom`, {
          product_id: item.ProductID,
          rt_counter: item.rtcounter,
          current_location: 'Validation',
          new_location: 'Stockroom'
        }, {
          headers: {
            'X-CSRF-TOKEN': csrfToken
          }
        });
        
        console.log("Move to Stockroom response:", response.data);
        
        if (response.data.success) {
          // Show success message
          alert(`Item ${item.rtcounter} successfully moved to Stockroom`);
          // Refresh the inventory list
          this.fetchInventory();
        } else {
          alert(response.data.message || 'Failed to move item to Stockroom');
        }
      } catch (error) {
        console.error('Error moving item to Stockroom:', error);
        alert('Failed to move item to Stockroom. Please try again.');
      } finally {
        this.isProcessing = false;
      }
    },

    
    // Method to show the validation confirmation
    confirmMoveToLabeling(item) {
      this.showConfirmationModal = true;
      this.confirmationTitle = 'Move to Labeling';
      this.confirmationMessage = `Are you sure you want to move item #${item.rtcounter} from Validation to Labeling ?`;
      this.confirmationActionType = 'labeling';
      this.currentItemForAction = item;
      
      // Prevent scrolling when modal is open
      document.body.style.overflow = 'hidden';
    },
    
    // Method to show the stockroom confirmation
    confirmMoveToStockroom(item) {
      this.showConfirmationModal = true;
      this.confirmationTitle = 'Move to Stockroom';
      this.confirmationMessage = `Are you sure you want to move item #${item.rtcounter} from Validation to Stockroom?`;
      this.confirmationActionType = 'stockroom';
      this.currentItemForAction = item;
      
      // Prevent scrolling when modal is open
      document.body.style.overflow = 'hidden';
    },
    
    // Method to handle the cancellation
    cancelConfirmation() {
      this.showConfirmationModal = false;
      this.currentItemForAction = null;
      
      // Re-enable scrolling
      document.body.style.overflow = 'auto';
    },
    
    // Method to confirm and execute the action
    confirmAction() {
      if (!this.currentItemForAction) return;
      
      if (this.confirmationActionType === 'labeling') {
        this.moveToLabeling(this.currentItemForAction);
      } else if (this.confirmationActionType === 'stockroom') {
        this.moveToStockroom(this.currentItemForAction);
      }
      
      // Close the modal
      this.showConfirmationModal = false;
      this.currentItemForAction = null;
      
      // Re-enable scrolling
      document.body.style.overflow = 'auto';
    },
    
   
      
    // Open the validation modal
    async openValidationModal(item) {
      this.currentValidationItem = item;
      this.validationNotes = '';
      this.validationErrors = null;
      this.validationActiveTab = 'product';
      this.currentValidationItemAsinLoaded = false;
      this.currentValidationItemAsinImages = [];
      this.showValidationModal = true;
      
      // Prevent scrolling when modal is open
      document.body.style.overflow = 'hidden';
      
    },
    
    // Close the validation modal
    closeValidationModal() {
      this.showValidationModal = false;
      this.currentValidationItem = null;
      this.validationNotes = '';
      this.validationErrors = null;
      this.currentValidationItemAsinImages = [];
      this.currentValidationItemAsinLoaded = false;
      
      // Re-enable scrolling
      document.body.style.overflow = 'auto';
    },
    
    // Open confirm dialog for valid
    confirmMarkAsValid() {
      if (!this.currentValidationItem) return;
      
      this.showConfirmationModal = true;
      this.confirmationTitle = 'Confirm Validation';
      this.confirmationMessage = `Are you sure you want to mark item #${this.currentValidationItem.rtcounter} as VALID?`;
      this.confirmationActionType = 'valid';
      
      // Prevent scrolling when confirmation modal is open
      document.body.style.overflow = 'hidden';
    },
    
    // Open confirm dialog for invalid
    confirmMarkAsInvalid() {
      if (!this.currentValidationItem) return;
      
      // Check if notes are provided for invalid items
      /*if (!this.validationNotes.trim()) {
        this.validationErrors = 'Please provide notes explaining why this item is invalid';
        return;
      }*/
      
      this.showConfirmationModal = true;
      this.confirmationTitle = 'Confirm Invalidation';
      this.confirmationMessage = `Are you sure you want to mark item #${this.currentValidationItem.rtcounter} as INVALID?`;
      this.confirmationActionType = 'invalid';
      
      // Prevent scrolling when confirmation modal is open
      document.body.style.overflow = 'hidden';
    },
    
    // Cancel the confirmation
    cancelConfirmation() {
      console.log('Canceling confirmation');
      this.showConfirmationModal = false;
      this.confirmationActionType = '';
      
      // Don't reset body overflow since we still have the validation modal open
      // The validation modal will handle this when it's closed
    },
    
    // Mark item as valid after confirmation
    async markAsValid() {
      if (!this.currentValidationItem) return;
      
      try {
        this.isProcessingValidation = true;
        this.showConfirmationModal = false; // Close confirmation dialog
        
        // Get the CSRF token from the meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Make the API request to validate the item
        const response = await axios.post(`${API_BASE_URL}/api/validation/validate`, {
          product_id: this.currentValidationItem.ProductID,
          rt_counter: this.currentValidationItem.rtcounter,
          status: 'validated',
          notes: this.validationNotes
        }, {
          headers: {
            'X-CSRF-TOKEN': csrfToken
          }
        });
        
        console.log("Validation response:", response.data);
        
        if (response.data.success) {
          // Show success message
          alert(`Item ${this.currentValidationItem.rtcounter} has been validated successfully`);
          // Close the modal
          this.closeValidationModal();
          // Refresh the inventory list
          this.fetchInventory();
        } else {
          this.validationErrors = response.data.message || 'Failed to validate item';
        }
      } catch (error) {
        console.error('Error validating item:', error);
        this.validationErrors = 'Failed to validate item. Please try again.';
      } finally {
        this.isProcessingValidation = false;
      }
    },
    
    // Mark item as invalid after confirmation
    async markAsInvalid() {
      if (!this.currentValidationItem) return;
      
      try {
        this.isProcessingValidation = true;
        this.showConfirmationModal = false; // Close confirmation dialog
        
        // Get the CSRF token from the meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Make the API request to mark the item as invalid
        const response = await axios.post(`${API_BASE_URL}/api/validation/validate`, {
          product_id: this.currentValidationItem.ProductID,
          rt_counter: this.currentValidationItem.rtcounter,
          status: 'invalid',
          notes: this.validationNotes
        }, {
          headers: {
            'X-CSRF-TOKEN': csrfToken
          }
        });
        
        console.log("Invalidation response:", response.data);
        
        if (response.data.success) {
          // Show success message
          alert(`Item ${this.currentValidationItem.rtcounter} has been marked as invalid`);
          // Close the modal
          this.closeValidationModal();
          // Refresh the inventory list
          this.fetchInventory();
        } else {
          this.validationErrors = response.data.message || 'Failed to mark item as invalid';
        }
      } catch (error) {
        console.error('Error marking item as invalid:', error);
        this.validationErrors = 'Failed to mark item as invalid. Please try again.';
      } finally {
        this.isProcessingValidation = false;
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
    this.fetchInventory();
    
    // Handle keyboard navigation for the modal
    const handleKeyDown = (e) => {
      if (!this.showImageModal) return;
      
      switch (e.key) {
        case 'Escape':
          this.closeImageModal();
          break;
        case 'ArrowRight':
          this.nextImage();
          break;
        case 'ArrowLeft':
          this.prevImage();
          break;
      }
    };
    
    window.addEventListener('keydown', handleKeyDown);
    this.handleKeyDown = handleKeyDown; // Store for cleanup
  },
  
  beforeDestroy() {
    // Clean up keyboard event listener
    if (this.handleKeyDown) {
      window.removeEventListener('keydown', this.handleKeyDown);
    }
  }
};
</script>

<style>
.expanded-content{
  background-color: azure;
}

.product-thumbnail {
  max-width: 60px;
  max-height: 60px;
  margin-right: 10px;
  object-fit: contain;
  border: 1px solid #ddd;
  background-color: #f8f8f8;
}

.product-container {
  display: flex;
  align-items: center;
}

.product-info {
  flex: 1;
}

/* Product image with count badge */
.product-image-container {
  position: relative;
  cursor: pointer;
}

.clickable-image {
  transition: transform 0.2s;
}

.product-image-container:hover .clickable-image {
  transform: scale(1.1);
}

.image-count-badge {
  position: absolute;
  bottom: -5px;
  right: 5px;
  background-color: #007bff;
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: bold;
}

/* Image Modal Styles */
.image-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.8);
}

.modal-content {
  position: relative;
  background-color: white;
  padding: 20px;
  padding-top: 50px;
  border-radius: 8px;
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow: auto;
  z-index: 1001;
  display: flex;
  flex-direction: column;
}

.close-button {
  position: absolute;
  top: 10px;
  right: 15px;
  font-size: 30px;
  background: none;
  border: none;
  cursor: pointer;
  color: #333;
  z-index: 1003;
}

.main-image-container {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 15px;
}

.modal-main-image {
  max-width: 100%;
  max-height: 60vh;
  object-fit: contain;
}

.nav-button {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background-color: rgba(255, 255, 255, 0.7);
  border: none;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  font-size: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 1002;
}

.nav-button.prev {
  left: 10px;
}

.nav-button.next {
  right: 10px;
}

.thumbnails-container {
  display: flex;
  overflow-x: auto;
  gap: 10px;
  padding: 10px 0;
  margin-top: 15px;
}

.modal-thumbnail {
  width: 60px;
  height: 60px;
  border: 2px solid transparent;
  border-radius: 4px;
  overflow: hidden;
  cursor: pointer;
  flex-shrink: 0;
}

.modal-thumbnail.active {
  border-color: #007bff;
}

.modal-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.image-counter {
  text-align: center;
  margin-top: 10px;
  color: #666;
}

/* Tab styling */
.image-tabs {
  display: flex;
  margin-bottom: 15px;
  border-bottom: 1px solid #ddd;
}

.tab-button {
  padding: 10px 15px;
  background-color: #f8f8f8;
  border: 1px solid #ddd;
  border-bottom: none;
  border-radius: 4px 4px 0 0;
  margin-right: 5px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.tab-button:hover:not(:disabled) {
  background-color: #e9e9e9;
}

.tab-button.active {
  background-color: #fff;
  border-bottom: 1px solid #fff;
  margin-bottom: -1px;
  font-weight: bold;
}

.tab-button:disabled {
  color: #999;
  background-color: #f0f0f0;
  cursor: not-allowed;
}

.no-images-message {
  text-align: center;
  padding: 30px;
  color: #666;
  font-style: italic;
}

/* For mobile */
@media (max-width: 768px) {
  .modal-content{
    padding: 10px;
    width: 95%;
  }
  
  .nav-button {
    width: 30px;
    height: 30px;
    font-size: 16px;
  }
  
  .modal-thumbnail {
    width: 50px;
    height: 50px;
  }
}

/* Add these styles for the confirmation modal */
.confirmation-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: center;
}

.confirmation-modal-content {
  background-color: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
  width: 90%;
  max-width: 480px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  z-index: 1101;
  animation: modal-fade-in 0.2s ease-out;
}

@keyframes modal-fade-in {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.confirmation-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
  background-color: #f9fafb;
}

.confirmation-modal-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.confirmation-modal-body {
  padding: 20px;
  flex-grow: 1;
  overflow-y: auto;
}

.confirmation-modal-body p {
  margin: 0;
  color: #4b5563;
  font-size: 1rem;
  line-height: 1.5;
}

.confirmation-modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 20px;
  border-top: 1px solid #e5e7eb;
  background-color: #f9fafb;
}

.btn-cancel {
  padding: 8px 16px;
  background-color: #f3f4f6;
  color: #4b5563;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-cancel:hover {
  background-color: #e5e7eb;
}

.btn-confirm {
  padding: 8px 16px;
  background-color: #dc2626;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-confirm:hover {
  background-color: #b91c1c;
}

.btn-confirm.btn-validation {
  background-color: #1976d2;
}

.btn-confirm.btn-validation:hover {
  background-color: #0d47a1;
}

.btn-confirm.btn-stockroom {
  background-color: #9c27b0;
}

.btn-confirm.btn-stockroom:hover {
  background-color: #6a1b9a;
}

/* Validation Modal Styles */
.validation-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
}

.validation-modal-content {
  background-color: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
  width: 95%;
  max-width: 800px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  z-index: 1201;
  animation: modal-slide-in 0.3s ease-out;
}

@keyframes modal-slide-in {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.validation-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
  background-color: #f0f7ff;
}

.validation-modal-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e40af;
  margin: 0;
}

.validation-modal-body {
  padding: 20px;
  overflow-y: auto;
  max-height: calc(90vh - 130px);
}

.validation-product-details {
  margin-bottom: 24px;
  background-color: #f9fafb;
  padding: 15px;
  border-radius: 6px;
  border-left: 4px solid #3b82f6;
}

.validation-product-details h4 {
  margin-top: 0;
  margin-bottom: 12px;
  color: #1e40af;
  font-size: 1.1rem;
}

.validation-detail-row {
  display: flex;
  margin-bottom: 8px;
  font-size: 0.95rem;
}

.validation-detail-row strong {
  min-width: 140px;
  color: #4b5563;
}

.validation-images-section {
  margin-bottom: 24px;
}

.validation-images-section h4 {
  margin-top: 0;
  margin-bottom: 12px;
  color: #1e40af;
  font-size: 1.1rem;
}

/* Validation Image Tabs */
.validation-image-tabs {
  display: flex;
  margin-bottom: 15px;
  border-bottom: 1px solid #ddd;
}

.validation-tab-button {
  padding: 8px 15px;
  background-color: #f8f8f8;
  border: 1px solid #ddd;
  border-bottom: none;
  border-radius: 4px 4px 0 0;
  margin-right: 5px;
  cursor: pointer;
  transition: background-color 0.2s;
  font-size: 0.9rem;
}

.validation-tab-button:hover {
  background-color: #e9e9e9;
}

.validation-tab-button.active {
  background-color: #fff;
  border-bottom: 1px solid #fff;
  margin-bottom: -1px;
  font-weight: bold;
  color: #1e40af;
}

.validation-images-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.validation-main-image {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f8f8f8;
  height: 200px;
}

.validation-main-image img {
  max-width: 100%;
  max-height: 200px;
  object-fit: contain;
}

.validation-thumbnails {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  max-height: 300px;
  overflow-y: auto;
}

.validation-thumbnails-full {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  width: 100%;
}

.validation-no-images {
  grid-column: 1 / -1;
  text-align: center;
  padding: 20px;
  background-color: #f9fafb;
  border-radius: 6px;
  color: #6b7280;
  font-style: italic;
}

.validation-loading {
  grid-column: 1 / -1;
  text-align: center;
  padding: 20px;
  background-color: #f0f7ff;
  border-radius: 6px;
  color: #1e40af;
}

.validation-thumbnail {
  width: 100%;
  aspect-ratio: 1;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
  background-color: #f8f8f8;
}

.validation-thumbnail.captured {
  border-color: #9c27b0;
}

.validation-thumbnail.asin {
  border-color: #f59e0b;
}

.validation-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.validation-notes-section {
  margin-bottom: 16px;
}

.validation-notes-section h4 {
  margin-top: 0;
  margin-bottom: 8px;
  color: #1e40af;
  font-size: 1.1rem;
}

.validation-notes-textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  font-size: 0.95rem;
  resize: vertical;
}

.validation-error {
  margin-top: 8px;
  color: #ef4444;
  font-size: 0.9rem;
}

.validation-modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 20px;
  border-top: 1px solid #e5e7eb;
  background-color: #f9fafb;
}

.btn-valid {
  padding: 10px 20px;
  background-color: #10b981;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-valid:hover {
  background-color: #059669;
}

.btn-invalid {
  padding: 10px 20px;
  background-color: #ef4444;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-invalid:hover {
  background-color: #dc2626;
}

.btn-valid:disabled,
.btn-invalid:disabled,
.btn-cancel:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Validation Confirmation Modal Styles */

/* Enhanced Validation Confirmation Modal Styles */
@keyframes confirmation-pop {
  from {
    opacity: 0;
    transform: scale(0.85);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.validation-confirmation-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1300; /* Higher than validation modal */
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
}

.validation-confirmation-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.4);
}

.validation-confirmation-content {
  background-color: #fff;
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15), 0 2px 8px rgba(0, 0, 0, 0.08);
  width: 90%;
  max-width: 480px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  z-index: 1301;
  position: relative;
  animation: confirmation-pop 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.validation-confirmation-header {
  display: flex;
  align-items: center;
  padding: 20px 24px;
  position: relative;
  color: white;
}

.header-valid {
  background-color: #10b981;
  background-image: linear-gradient(135deg, #10b981, #059669);
}

.header-invalid {
  background-color: #ef4444;
  background-image: linear-gradient(135deg, #ef4444, #dc2626);
}

.header-default {
  background-color: #3b82f6;
  background-image: linear-gradient(135deg, #3b82f6, #2563eb);
}

.header-icon-container {
  margin-right: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.header-icon {
  font-size: 24px;
  color: white;
}

.validation-confirmation-header h3 {
  font-size: 1.3rem;
  font-weight: 600;
  margin: 0;
  color: white;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
  flex-grow: 1;
}

.validation-confirmation-header .close-button {
  position: absolute;
  top: 16px;
  right: 16px;
  font-size: 26px;
  background: none;
  border: none;
  cursor: pointer;
  color: rgba(255, 255, 255, 0.8);
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s;
}

.validation-confirmation-header .close-button:hover {
  color: white;
  background-color: rgba(255, 255, 255, 0.15);
}

.validation-confirmation-body {
  padding: 24px;
  flex-grow: 1;
  border-bottom: 1px solid #e5e7eb;
}

.validation-confirmation-body p {
  margin: 0;
  color: #374151;
  font-size: 1.05rem;
  line-height: 1.6;
}

.validation-confirmation-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  background-color: #f9fafb;
}

.btn-no, .btn-yes {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-no {
  background-color: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn-no:hover {
  background-color: #e5e7eb;
}

.btn-yes {
  border: none;
  color: white;
}

.btn-valid-confirm {
  background-color: #10b981;
}

.btn-valid-confirm:hover {
  background-color: #059669;
}

.btn-invalid-confirm {
  background-color: #ef4444;
}

.btn-invalid-confirm:hover {
  background-color: #dc2626;
}

.btn-default-confirm {
  background-color: #3b82f6;
}

.btn-default-confirm:hover {
  background-color: #2563eb;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .validation-confirmation-content {
    width: 95%;
    max-width: none;
  }
  
  .validation-confirmation-header {
    padding: 16px 20px;
  }
  
  .validation-confirmation-body {
    padding: 20px;
  }
  
  .validation-confirmation-footer {
    padding: 14px 20px;
  }
  
  .btn-no, .btn-yes {
    padding: 10px 16px;
    font-size: 0.95rem;
  }
}

/* Compare Gallery Styles */
.compare-gallery {
  margin-bottom: 24px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  overflow: hidden;
  background-color: #f9fafb;
}

.compare-gallery h5 {
  margin: 0;
  padding: 12px 15px;
  background-color: #edf2f7;
  color: #2d3748;
  font-size: 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.compare-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
}

.compare-item {
  padding: 15px;
  display: flex;
  flex-direction: column;
}

.compare-item:first-child {
  border-right: 1px solid #e5e7eb;
}

.compare-title {
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 4px;
  font-size: 0.95rem;
}

.compare-subtitle {
  color: #4a5568;
  margin-bottom: 12px;
  font-size: 0.85rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.compare-image-container {
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
}

.compare-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.no-asin-image {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  width: 100%;
  background-color: #f1f5f9;
  color: #64748b;
  font-style: italic;
  font-size: 0.9rem;
}

/* Mobile responsive adjustments for compare gallery */
@media (max-width: 768px) {
  .compare-container {
    grid-template-columns: 1fr;
  }
  
  .compare-item:first-child {
    border-right: none;
    border-bottom: 1px solid #e5e7eb;
  }
  
  .compare-image-container {
    height: 180px;
  }
}
</style>