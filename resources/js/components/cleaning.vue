<template>
  <div class="vue-container">
    <h1 class="vue-title">Cleaning Module</h1>
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
        <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">Back</button>
        <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
        <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">Next</button>
      </div>
    </div>
    
    <!-- Image Modal -->
    <div v-if="showImageModal" class="image-modal">
      <div class="modal-overlay" @click="closeImageModal"></div>
      <div class="modal-content">
        <button class="close-button" @click="closeImageModal">&times;</button>
        
        <div class="main-image-container">
          <button class="nav-button prev" @click="prevImage" v-if="modalImages.length > 1">&lt;</button>
          <img :src="modalImages[currentImageIndex]" alt="Product Image" class="modal-main-image" />
          <button class="nav-button next" @click="nextImage" v-if="modalImages.length > 1">&gt;</button>
        </div>
        
        <div class="image-counter">{{ currentImageIndex + 1 }} / {{ modalImages.length }}</div>
        
        <div class="thumbnails-container" v-if="modalImages.length > 1">
          <div v-for="(image, index) in modalImages" 
               :key="index" 
               class="modal-thumbnail" 
               :class="{ active: index === currentImageIndex }"
               @click="currentImageIndex = index">
            <img :src="image" :alt="`Thumbnail ${index + 1}`" />
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
        const response = await axios.get(`${API_BASE_URL}/products`, {
          params: { search: this.searchQuery, page: this.currentPage, per_page: this.perPage, location: 'Cleaning' },
        });

        this.inventory = response.data.data;
        this.totalPages = response.data.last_page;
        
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

/* Modal Styles */
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
  z-index: 1002;
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

/* For mobile */
@media (max-width: 768px) {
  .modal-content {
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
</style>