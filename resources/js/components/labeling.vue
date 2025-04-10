<template>
  <div class="vue-container">
    <h1 class="vue-title">Labeling Module</h1>
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
              <div class="product-image-container" @click="openImageModal(item)">
                <!-- Use the actual file path for the main image -->
                <img :src="getImageUrl(item)" 
                     :alt="item.ProductTitle || 'Product'" 
                     class="product-thumbnail clickable-image" 
                     @error="handleImageError($event)" />
                <div class="image-count-badge" v-if="countAdditionalImages(item) > 0">
                  +{{ countAdditionalImages(item) }}
                </div>
              </div>
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
    
    <!-- Image Modal with Sectioned Display -->
    <div v-if="showImageModal" class="image-modal">
      <div class="modal-overlay" @click="closeImageModal"></div>
      <div class="modal-content">
        <button class="close-button" @click="closeImageModal">&times;</button>
        
        <div class="main-image-container">
          <button class="nav-button prev" @click="prevImage" v-if="allImages.length > 1">&lt;</button>
          <img :src="allImages[currentImageIndex]" alt="Product Image" class="modal-main-image" @error="handleModalImageError($event)" />
          <button class="nav-button next" @click="nextImage" v-if="allImages.length > 1">&gt;</button>
        </div>
        
        <div class="image-counter">{{ currentImageIndex + 1 }} / {{ allImages.length }}</div>
        
        <!-- Section 1: Standard Images -->
        <div class="image-section" v-if="standardImages.length > 0">
          <h3 class="section-title">Standard Images</h3>
          <div class="thumbnails-container">
            <div v-for="(image, index) in standardImages" 
                 :key="`standard-${index}`" 
                 class="modal-thumbnail" 
                 :class="{ active: getGlobalIndex('standard', index) === currentImageIndex }"
                 @click="currentImageIndex = getGlobalIndex('standard', index)">
              <img :src="image" :alt="`Standard Image ${index + 1}`" @error="handleThumbnailError($event)" />
            </div>
          </div>
        </div>
        
        <!-- Section 2: Captured Images -->
        <div class="image-section" v-if="capturedImages.length > 0">
          <h3 class="section-title">Captured Images</h3>
          <div class="thumbnails-container">
            <div v-for="(image, index) in capturedImages" 
                 :key="`captured-${index}`" 
                 class="modal-thumbnail" 
                 :class="{ active: getGlobalIndex('captured', index) === currentImageIndex }"
                 @click="currentImageIndex = getGlobalIndex('captured', index)">
              <img :src="image" :alt="`Captured Image ${index + 1}`" @error="handleThumbnailError($event)" />
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
  name: 'ProductList',
  data() {
    return {
      inventory: [],
      currentPage: 1,
      totalPages: 1,
      selectAll: false,
      expandedRows: {},
      // Image modal properties
      showImageModal: false,
      standardImages: [],  // Section 1: Standard product images
      capturedImages: [],  // Section 2: Captured images
      currentImageIndex: 0,
      defaultImage: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4='
    };
  },
  computed: {
    searchQuery() {
      return eventBus.searchQuery; // Making search reactive
    },
    
    // Computed property to combine both image arrays for navigation
    allImages() {
      return [...this.standardImages, ...this.capturedImages];
    }
  },

  methods: {
    async fetchInventory() {
      try {
        const response = await axios.get(`${API_BASE_URL}/products`, {
          params: { 
            search: this.searchQuery, 
            page: this.currentPage, 
            location: 'Labeling'
          },
        });
        
        this.inventory = response.data.data;
        this.totalPages = response.data.last_page;
      } catch (error) {
        console.error('Error fetching inventory data:', error);
      }
    },
    
    // Helper method to get the global index from section type and local index
    getGlobalIndex(section, localIndex) {
      if (section === 'standard') {
        return localIndex;
      } else {
        return this.standardImages.length + localIndex;
      }
    },
    
    getImageUrl(item) {
      if (item.img1 && item.img1 !== 'NULL' && item.img1.trim() !== '') {
        return `/images/thumbnails/${item.img1}`;
      }
      return this.defaultImage;
    },
    
    handleImageError(event) {
      // If image fails to load, use the default image
      event.target.src = this.defaultImage;
      event.target.onerror = null; // Prevent infinite error loop
    },
    
    handleModalImageError(event) {
      // If modal image fails to load, use the default image
      event.target.src = this.defaultImage;
      event.target.onerror = null;
    },
    
    handleThumbnailError(event) {
      // If thumbnail fails to load, use the default image
      event.target.src = this.defaultImage;
      event.target.onerror = null;
    },
    
    // Count additional images based on the image fields (all possible image fields)
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
      
      // Check the new captured image fields
      const capturedFields = [
        'Serial1capturedimg', 'Serial2capturedimg',
        'capturedimg1', 'capturedimg2', 'capturedimg3', 
        'capturedimg4', 'capturedimg5', 'capturedimg6', 
        'capturedimg7', 'capturedimg8'
      ];
      
      capturedFields.forEach(field => {
        if (item[field] && item[field] !== 'NULL' && item[field].trim() !== '') {
          count++;
        }
      });
      
      return count;
    },
    
    // Open image modal with all available images organized into sections
    openImageModal(item) {
      if (!item) return;
      
      // Reset modal state
      this.standardImages = [];
      this.capturedImages = [];
      this.currentImageIndex = 0;
      
      // SECTION 1: Standard product images
      
      // First check for the main image (img1)
      if (item.img1 && item.img1 !== 'NULL' && item.img1.trim() !== '') {
        const mainImagePath = `/images/thumbnails/${item.img1}`;
        this.standardImages.push(mainImagePath);
      }
      
      // Add additional standard images (img2-img15)
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
          this.standardImages.push(imagePath);
        }
      });
      
      // SECTION 2: Captured images
      
      // Add captured images from the new fields
      const capturedImageFields = [
        'capturedimg1', 'capturedimg2','capturedimg3',
        'capturedimg4', 'capturedimg5', 'capturedimg6', 
        'capturedimg7', 'capturedimg8', 'capturedimg9', 
        'capturedimg10', 'capturedimg11', 'capturedimg12'
      ];
      
      capturedImageFields.forEach(field => {
        if (item[field] && item[field] !== 'NULL' && item[field].trim() !== '') {
          // Use the product_images path for captured images
          const imagePath = `/images/product_images/${item[field]}`;
          this.capturedImages.push(imagePath);
        }
      });
      
      // If no images were found in either section, add a default image to standard section
      if (this.standardImages.length === 0 && this.capturedImages.length === 0) {
        const defaultPath = `/images/product_images/${item.ProductID}.jpg`;
        this.standardImages.push(defaultPath);
      }
      
      // Show the modal
      this.showImageModal = true;
      
      // Prevent scrolling when modal is open
      document.body.style.overflow = 'hidden';
    },
    
    closeImageModal() {
      this.showImageModal = false;
      this.standardImages = [];
      this.capturedImages = [];
      
      // Re-enable scrolling
      document.body.style.overflow = 'auto';
    },
    
    nextImage() {
      if (this.currentImageIndex < this.allImages.length - 1) {
        this.currentImageIndex++;
      } else {
        this.currentImageIndex = 0; // Loop back to the first image
      }
    },
    
    prevImage() {
      if (this.currentImageIndex > 0) {
        this.currentImageIndex--;
      } else {
        this.currentImageIndex = this.allImages.length - 1; // Loop to the last image
      }
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
      this.$set(this.expandedRows, index, !this.expandedRows[index]);
    },
  },
  
  watch: {
    searchQuery() {
      this.currentPage = 1; // Reset to first page on search
      this.fetchInventory();
    },
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
.product-thumbnail {
  max-width: 60px;
  max-height: 60px;
  margin-right: 10px;
  object-fit: contain;
  border: 1px solid #ddd;
  background-color: #f8f8f8;
}

.product-image-container {
  position: relative;
  cursor: pointer;
  display: inline-block;
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
  min-height: 300px;
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

/* Image Section Styles */
.image-section {
  margin-top: 20px;
  border-top: 1px solid #eee;
  padding-top: 15px;
}

.section-title {
  font-size: 16px;
  font-weight: bold;
  margin-bottom: 10px;
  color: #333;
  position: relative;
  display: inline-block;
}

.section-title:after {
  content: '';
  display: block;
  height: 2px;
  width: 40px;
  background-color: #007bff;
  position: absolute;
  bottom: -5px;
  left: 0;
}

.thumbnails-container {
  display: flex;
  overflow-x: auto;
  gap: 10px;
  padding: 10px 0;
}

.modal-thumbnail {
  width: 60px;
  height: 60px;
  border: 2px solid transparent;
  border-radius: 4px;
  overflow: hidden;
  cursor: pointer;
  flex-shrink: 0;
  transition: transform 0.2s, border-color 0.2s;
}

.modal-thumbnail:hover {
  transform: scale(1.05);
}

.modal-thumbnail.active {
  border-color: #007bff;
  box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
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
  background-color: #f8f9fa;
  padding: 5px;
  border-radius: 15px;
  font-size: 14px;
  width: fit-content;
  margin: 10px auto;
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
  
  .main-image-container {
    min-height: 200px;
  }
}

.expanded-content {
  background-color: azure;
  padding: 10px;
  border-radius: 4px;
}
</style>