<template>
  <div class="vue-container">
    <h1 class="vue-title">Labeling Module</h1>
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
                    <div class="image-count-badge" v-if="countAllImages(item) > 0">
                      +{{ countAllImages(item) }}
                    </div>
                  </div>
               
                  <div class="product-info">
                    <p class="product-name">RT# : {{ item.rtcounter }}</p>
                    <p class="product-name">{{ item.AStitle }}</p>

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

                <span><strong></strong> {{ item.actions }}</span>   
                <button @click="showFnskuModal(item)" class="test-btn mt-2">
                  <i class="bi bi-clipboard-check"></i> SET FNSKU
                </button>

                <button 
                  @click="confirmMoveToValidation(item)" 
                  class="action-btn btn-validation"
                  :disabled="isProcessing">
                  <i class="bi bi-check-circle"></i> Move to Validation
                </button><br>

                <button 
                  @click="confirmMoveToStockroom(item)" 
                  class="action-btn btn-stockroom" 
                  :disabled="isProcessing">
                  <i class="bi bi-box-seam"></i> Move to Stockroom
                </button><br>

                <button class="btn-moredetails">example</button><br>
              </td>
            </tr>
             <!-- More details results -->
            <tr v-if="expandedRows[index]">
              <td colspan="11">
                <div class="expanded-content p-3 border rounded">
                  <div class="Mobile">
                <br>

                  <span><strong></strong> {{ item.actions }}</span>   
                  <button @click="showFnskuModal(item)" class="test-btn mt-2">
                    <i class="bi bi-clipboard-check"></i> SET FNSKU
                  </button>
                  
                  <button 
                    @click="confirmMoveToValidation(item)" 
                    class="action-btn btn-validation" 
                    :disabled="isProcessing">
                    <i class="bi bi-check-circle"></i> Move to Validation
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
        
        <!-- Tabs for switching between regular and captured images -->
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

    <!-- FNSKU Selection Modal - Moved outside image modal and now has proper styling -->
  <div class="fnsku-modal-container" v-if="isFnskuModalVisible">
    <!-- Overlay -->
    <div class="fnsku-modal-overlay" @click="hideFnskuModal"></div>
    
    <!-- Modal Content -->
    <div class="fnsku-modal-content">
      <!-- Header -->
      <div class="fnsku-modal-header">
        <h2>Select FNSKU</h2>
        <button class="fnsku-close" @click="hideFnskuModal">&times;</button>
      </div>
      
      <!-- Body -->
      <div class="fnsku-modal-body">
        <!-- Product Info -->
        <div class="fnsku-product-info">
          <h4>{{ currentItem?.ProductTitle }}</h4>
          <div class="fnsku-product-details">
            <p><strong>ID:</strong> {{ currentItem?.ProductID }}</p>
            <p><strong>Serial#:</strong> {{ currentItem?.serialnumber }}</p>
            <p><strong>FNSKU:</strong> {{ currentItem?.FNSKUviewer || 'None' }}</p>
          </div>
        </div>
        
        <!-- Search -->
        <div class="fnsku-search-container">
          <input
            type="text"
            v-model="fnskuSearch"
            placeholder="Search FNSKU, ASIN, title, or grading..."
            class="fnsku-search-input"
            @input="filterFnskuList"
          />
        </div>
        
        <!-- FNSKU List -->
        <div class="fnsku-list-container">
          <!-- List Header -->
          <div class="fnsku-list-header">
            <div class="fnsku-details-column">FNSKU Details</div>
            <div class="fnsku-title-column">Title & Inventory</div>
            <div class="fnsku-action-column">Action</div>
          </div>
          
          <!-- No Results -->
          <div v-if="filteredFnskuList.length === 0" class="fnsku-no-results">
            No matching FNSKUs found
          </div>
          
          <!-- List Items -->
          <div class="fnsku-list">
            <div
              v-for="(fnsku, index) in filteredFnskuList"
              :key="fnsku.FNSKU"
              class="fnsku-item"
              :class="{
                'fnsku-highlighted': fnsku.ASIN === currentItem?.ASIN,
                'fnsku-even': index % 2 === 0
              }"
            >
              <div class="fnsku-details-column">
                <div class="fnsku-code">{{ fnsku.FNSKU }}</div>
                <div class="fnsku-asin">ASIN: {{ fnsku.ASIN }}</div>
                <div class="fnsku-badge" :class="{'fnsku-new': fnsku.grading.includes('New')}">
                  {{ fnsku.grading }}
                </div>
              </div>
              
              <div class="fnsku-title-column">
                <div class="fnsku-title">{{ fnsku.astitle }}</div>
                <div class="fnsku-units">{{ fnsku.Units }} in inventory</div>
              </div>
              
              <div class="fnsku-action-column">
                <button
                  @click="selectFnsku(fnsku)"
                  class="fnsku-select-btn"
                  :class="{'fnsku-recommended': fnsku.ASIN === currentItem?.ASIN}"
                >
                  {{ fnsku.ASIN === currentItem?.ASIN ? 'Recommended' : 'Select' }}
                </button>
              </div>
            </div>
          </div>
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
      regularImages: [],    // For regular product images
      capturedImages: [],   // For captured images
      activeTab: 'regular', // Track which tab is active
      currentImageIndex: 0,
      currentImageSet: [],   // The currently displayed image set based on active tab
      
      // FNSKU Modal properties
      isFnskuModalVisible: false,
      currentItem: null,
      fnskuList: [],
      filteredFnskuList: [],
      fnskuSearch: '',

      showConfirmationModal: false,
      confirmationTitle: '',
      confirmationMessage: '',
      confirmationActionType: '', // 'validation' or 'stockroom'
      currentItemForAction: null // Store the item to be processed
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
    
    // Count all images (regular + captured)
    countAllImages(item) {
      return this.countRegularImages(item) + this.countCapturedImages(item);
    },
    
    // Open image modal with all available images in separate categories
    openImageModal(item) {
      if (!item) return;
      
      console.log("Opening modal for item:", item);
      
      // Reset modal state
      this.regularImages = [];
      this.capturedImages = [];
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
      
      // If no images were found in either category, add a default image to regularImages
      if (this.regularImages.length === 0 && this.capturedImages.length === 0) {
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
      this.currentImageSet = tab === 'regular' ? this.regularImages : this.capturedImages;
    },
    
    closeImageModal() {
      this.showImageModal = false;
      this.currentImageSet = [];
      this.regularImages = [];
      this.capturedImages = [];
      
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
          location: 'Labeling',
          include_images: true
        });
        
        const response = await axios.get(`${API_BASE_URL}/api/labeling/products`, {
          params: { 
            search: this.searchQuery, 
            page: this.currentPage, 
            per_page: this.perPage, 
            location: 'Labeling',
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
    
    // FNSKU Modal methods - Fixed and improved
    async showFnskuModal(item) {
      console.log("Opening FNSKU modal for item:", item);
      this.currentItem = item;
      this.isFnskuModalVisible = true;
      this.fnskuSearch = item.ASINviewer || ''; // Pre-fill with current ASIN for easier search
      
      try {
        console.log("Fetching FNSKU list...");
        const response = await axios.get(`${API_BASE_URL}/fnsku-list`);
        console.log("FNSKU list response:", response.data);
        this.fnskuList = response.data;
        this.filterFnskuList(); // Apply initial filter
      } catch (error) {
        console.error('Error fetching FNSKU list:', error);
        alert('Error fetching FNSKU list. Please try again.');
      }
    },
    
    hideFnskuModal() {
      console.log("Hiding FNSKU modal");
      this.isFnskuModalVisible = false;
      this.currentItem = null;
      this.fnskuList = [];
      this.filteredFnskuList = [];
      this.fnskuSearch = '';
    },
    
    filterFnskuList() {
      console.log("Filtering FNSKU list with search:", this.fnskuSearch);
      if (!this.fnskuSearch) {
        // If empty search, show matching ASIN first, then everything else
        this.filteredFnskuList = [...this.fnskuList].sort((a, b) => {
          if (a.ASIN === this.currentItem?.ASINviewer && b.ASIN !== this.currentItem?.ASINviewer) {
            return -1;
          } else if (a.ASIN !== this.currentItem?.ASINviewer && b.ASIN === this.currentItem?.ASINviewer) {
            return 1;
          }
          return 0;
        });
        return;
      }
      
      const search = this.fnskuSearch.toLowerCase();
      this.filteredFnskuList = this.fnskuList.filter(fnsku => 
        fnsku.FNSKU?.toLowerCase().includes(search) || 
        fnsku.ASIN?.toLowerCase().includes(search) || 
        fnsku.astitle?.toLowerCase().includes(search)
      );
    },
    
    async selectFnsku(fnsku) {
      console.log("Selecting FNSKU:", fnsku);
      if (!this.currentItem || !fnsku) return;
      
      try {
    // Get the CSRF token from the meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Make the request with proper data format and headers
    const response = await axios.post(`${API_BASE_URL}/update-fnsku`, {
      product_id: this.currentItem.ProductID,
      fnsku: fnsku.FNSKU,
      msku: fnsku.MSKU,
      asin: fnsku.ASIN,
      grading: fnsku.grading,
      astitle: fnsku.astitle
    }, {
      headers: {
        'X-CSRF-TOKEN': csrfToken
      }
    });
        
        console.log("Update FNSKU response:", response.data);
        
        if (response.data.success) {
          alert(`FNSKU updated to ${fnsku.FNSKU}`);
          this.hideFnskuModal();
          this.fetchInventory(); // Refresh the data
        } else {
          alert(response.data.message || 'Failed to update FNSKU');
        }
      } catch (error) {
        console.error('Error updating FNSKU:', error);
        alert('Failed to update FNSKU. Please try again.');
      }
    },

    // Add these methods to the methods object in your component
    async moveToValidation(item) {
      if (!item || !item.ProductID) {
        console.error('Invalid item data for moving to Validation');
        return;
      }
      
      try {
        // Get the CSRF token from the meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Make the request with proper data format and headers
        const response = await axios.post(`${API_BASE_URL}/api/labeling/move-to-validation`, {
          product_id: item.ProductID,
          rt_counter: item.rtcounter,
          current_location: 'Labeling',
          new_location: 'Validation'
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
      }
    },

    async moveToStockroom(item) {
      if (!item || !item.ProductID) {
        console.error('Invalid item data for moving to Stockroom');
        return;
      }
      
      try {
        // Get the CSRF token from the meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Make the request with proper data format and headers
        const response = await axios.post(`${API_BASE_URL}/api/labeling/move-to-stockroom`, {
          product_id: item.ProductID,
          rt_counter: item.rtcounter,
          current_location: 'Labeling',
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
      }
    },

    
    // Method to show the validation confirmation
    confirmMoveToValidation(item) {
      this.showConfirmationModal = true;
      this.confirmationTitle = 'Move to Validation';
      this.confirmationMessage = `Are you sure you want to move item #${item.rtcounter} from Labeling to Validation?`;
      this.confirmationActionType = 'validation';
      this.currentItemForAction = item;
      
      // Prevent scrolling when modal is open
      document.body.style.overflow = 'hidden';
    },
    
    // Method to show the stockroom confirmation
    confirmMoveToStockroom(item) {
      this.showConfirmationModal = true;
      this.confirmationTitle = 'Move to Stockroom';
      this.confirmationMessage = `Are you sure you want to move item #${item.rtcounter} from Labeling to Stockroom?`;
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
      
      if (this.confirmationActionType === 'validation') {
        this.moveToValidation(this.currentItemForAction);
      } else if (this.confirmationActionType === 'stockroom') {
        this.moveToStockroom(this.currentItemForAction);
      }
      
      // Close the modal
      this.showConfirmationModal = false;
      this.currentItemForAction = null;
      
      // Re-enable scrolling
      document.body.style.overflow = 'auto';
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

/* FNSKU Modal Styles */

.fnsku-modal-container {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1050;
  display: flex;
  align-items: center;
  justify-content: center;
}

.fnsku-modal-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(2px);
}

.fnsku-modal-content {
  position: relative;
  background-color: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  width: 95%;
  max-width: 900px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.fnsku-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
  background-color: #f9fafb;
  border-top-left-radius: 8px;
  border-top-right-radius: 8px;
}

.fnsku-modal-header h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.fnsku-close {
  font-size: 1.5rem;
  font-weight: bold;
  color: #6b7280;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0 4px;
  transition: color 0.2s;
}

.fnsku-close:hover {
  color: #1f2937;
}

.fnsku-modal-body {
  padding: 20px;
  overflow-y: auto;
  flex: 1;
}

.fnsku-product-info {
  margin-bottom: 20px;
  padding: 16px;
  background-color: #eff6ff;
  border-left: 4px solid #3b82f6;
  border-radius: 6px;
}

.fnsku-product-info h4 {
  font-size: 1.1rem;
  font-weight: 600;
  margin: 0 0 12px 0;
  color: #1f2937;
}

.fnsku-product-details {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  font-size: 0.9rem;
}

.fnsku-product-details p {
  margin: 0;
}

.fnsku-search-container {
  margin-bottom: 20px;
}

.fnsku-search-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.95rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: all 0.2s;
}

.fnsku-search-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.fnsku-list-container {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.fnsku-list-header {
  display: grid;
  grid-template-columns: 3fr 6fr 3fr;
  padding: 12px 16px;
  background-color: #f3f4f6;
  font-weight: 500;
  color: #4b5563;
  border-bottom: 1px solid #e5e7eb;
}

.fnsku-no-results {
  padding: 24px;
  text-align: center;
  color: #6b7280;
}

.fnsku-list {
  max-height: 380px;
  overflow-y: auto;
}

.fnsku-item {
  display: grid;
  grid-template-columns: 3fr 6fr 3fr;
  padding: 16px;
  border-bottom: 1px solid #e5e7eb;
  transition: background-color 0.15s;
}

.fnsku-item:last-child {
  border-bottom: none;
}

.fnsku-even {
  background-color: #f9fafb;
}

.fnsku-highlighted {
  background-color: #fffbeb;
}

.fnsku-item:hover {
  background-color: #f3f4f6;
}

.fnsku-code {
  font-family: monospace;
  background-color: #f3f4f6;
  padding: 4px 8px;
  border-radius: 4px;
  display: inline-block;
  margin-bottom: 6px;
  font-size: 0.9rem;
}

.fnsku-asin {
  font-size: 0.85rem;
  color: #4b5563;
  margin-bottom: 6px;
}

.fnsku-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  background-color: #fef3c7;
  color: #92400e;
}

.fnsku-badge.fnsku-new {
  background-color: #d1fae5;
  color: #065f46;
}

.fnsku-title {
  font-weight: 500;
  color: #1f2937;
  margin-bottom: 6px;
}

.fnsku-units {
  font-size: 0.85rem;
  color: #6b7280;
}

.fnsku-action-column {
  display: flex;
  align-items: center;
  justify-content: center;
}

.fnsku-select-btn {
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  background-color: #e5e7eb;
  color: #4b5563;
}

.fnsku-select-btn:hover {
  background-color: #d1d5db;
}

.fnsku-select-btn.fnsku-recommended {
  background-color: #2563eb;
  color: #fff;
}

.fnsku-select-btn.fnsku-recommended:hover {
  background-color: #1d4ed8;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .fnsku-product-details {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  
  .fnsku-list-header, .fnsku-item {
    grid-template-columns: 2fr 4fr 2fr;
  }
  
  .fnsku-select-btn {
    padding: 6px 12px;
    font-size: 0.8rem;
  }
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
</style>