<template>
    <div class="vue-container labeling-module">
        <div class="top-header">
            <span>Top Header</span>
        </div>

        <h2 class="module-title">Labeling Module</h2>

        <!-- Desktop Table Container -->
        <div class="table-container desktop-view">
            <table>
                <thead>
                    <tr>
                        <th class="sticky-header first-col">
                            <input type="checkbox" @click="toggleAll" v-model="selectAll" />
                        </th>
                        <th class="sticky-header second-sticky">
                            <div class="product-name">
                                <span class="sortable" @click="sortBy('AStitle')">
                                    Product Name
                                    <i v-if="sortColumn === 'AStitle'"
                                        :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                                </span>

                                <button class="btn-showDetails"
                                    @click="toggleDetailsVisibility">{{ showDetails ? 'Hide extra columns' : 'Show extra columns' }}
                                </button>
                            </div>
                        </th>
                        <th class="">ASIN</th>
                        <th class="">FNSKU</th>
                        <th class="">MSKU</th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">FBM
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">FBA
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">
                            Outbound</th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">Inbound
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">
                            Unfulfillable</th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">
                            Reserved</th>
                        <th class="">Fulfillment</th>
                        <th class="">Status</th>
                        <th class="">Serialnumber</th>
                        <th class="">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(item, index) in sortedInventory" :key="item.id">
                        <tr>
                            <td class="sticky-col first-col">
                                <input type="checkbox" v-model="item.checked" />
                                <span class="placeholder-date">{{ item.shipBy || '' }}</span>
                            </td>
                            <td class="sticky-col second-sticky">
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
                                    <div class="product-info clickable">
                                        <p>RT# : {{ item.rtcounter }}</p>
                                        <p>{{ item.ProductTitle }}</p>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.ASIN }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.FNSKUviewer  }}</span>
                            </td>
                            <td>
                                <span><strong></strong> {{ item.MSKU  }}</span>
                            </td>
                            <!-- Hidden -->
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
                            <!-- End Hidden -->
                            <td>
                                <span><strong></strong> {{ item.Fulfilledby }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.Status }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.serialnumber }}</span>
                            </td>

                            <!-- Button for more details -->
                            <td>
                                <div class="action-buttons">
                                    {{ item.totalquantity }}
                                    <button class="btn-details" @click="toggleDetails(index)">
                                        <i class="fas fa-info-circle"></i> More Details
                                    </button>

                                    <span><strong></strong> {{ item.actions }}</span>
                                    <button @click="showFnskuModal(item)" class="btn btn-fnsku">
                                        <i class="bi bi-clipboard-check"></i> SET FNSKU
                                    </button>

                                    <button @click="confirmMoveToValidation(item)" class="btn btn-validation"
                                        :disabled="isProcessing">
                                        <i class="bi bi-check-circle"></i> Move to Validation
                                    </button>

                                    <button @click="confirmMoveToStockroom(item)" class="btn btn-stockroom"
                                        :disabled="isProcessing">
                                        <i class="bi bi-box-seam"></i> Move to Stockroom
                                    </button>

                                    <button class="btn-example">example</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="expandedRows[index]">
                            <td :colspan="showDetails ? 18 : 12">
                                <div class="expanded-content p-3 border rounded">
                                    <p><strong>External Title provided by Supplier:</strong> {{ item.ProductTitle }}</p>
                                    <p><strong>Product Name:</strong> {{ item.AStitle }}</p>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <button class="btn-showDetailsM"
                @click="toggleDetailsVisibility">{{ showDetails ? 'Hide extra columns' : 'Show extra columns' }}
            </button>

            <div class="mobile-cards">
                <div class="mobile-card" v-for="(item, index) in sortedInventory" :key="item.id">
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <div class="mobile-product-image clickable">
                            <img :src="'/images/thumbnails/' + item.img1" :alt="item.ProductTitle || 'Product'"
                                class="product-thumbnail clickable-image" @error="handleImageError($event)" />
                            <div class="image-count-badge" v-if="countAllImages(item) > 0">
                                +{{ countAllImages(item) }}
                            </div>
                        </div>
                        <div class="mobile-product-info">
                            <h3 class="mobile-product-name clickable">
                                <p>RT# : {{ item.rtcounter }}</p>
                                <p>{{ item.ProductTitle }}</p>
                            </h3>
                        </div>
                    </div>

                    <hr>

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Location:</span>
                            <span class="mobile-detal-value"> {{ item.warehouselocation }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Added date:</span>
                            <span class="mobile-detal-value"> {{ item.datedelivered }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Updated date:</span>
                            <span class="mobile-detal-value"> {{ item.lastDateUpdate }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">FNSKU:</span>
                            <span class="mobile-detal-value"> {{ item.FNSKUviewer }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">MSKU:</span>
                            <span class="mobile-detal-value"> {{ item.MSKUviewer }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detal-value"> {{ item.ASINviewer }}</span>
                        </div>
                        <!-- Insert Hidden Here -->
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">FBM:</span>
                            <span class="mobile-detal-value"> {{ item.FBMAvailable }}</span>
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">FBA:</span>
                            <span class="mobile-detal-value"> {{ item.FbaAvailable }}</span>
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">Outbound:</span>
                            <span class="mobile-detal-value"> {{ item.Outbound }}</span>
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">Inbound:</span>
                            <span class="mobile-detal-value"> {{ item.Inbound }}</span>
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">Unfulfillable:</span>
                            <span class="mobile-detal-value"> {{ item.Unfulfillable }}</span>
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">Reserved:</span>
                            <span class="mobile-detal-value"> {{ item.Reserved }}</span>
                        </div>
                        <!--  -->
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Fullfilment:</span>
                            <span class="mobile-detal-value"> {{ item.Fulfilledby }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Status:</span>
                            <span class="mobile-detal-value"> {{ item.status }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Serial Number:</span>
                            <span class="mobile-detal-value"> {{ item.serialnumber }}</span>
                        </div>
                    </div>

                    <hr>

                    <div class="mobile-card-actions">
                        <button class="btn btn-details" @click="toggleDetails(index)">
                            <i class="fas fa-info-circle"></i> Details
                        </button>

                        <!-- <span><strong></strong> {{ item.actions }}</span> -->
                        <button @click="showFnskuModal(item)" class="btn btn-fnsku">
                            <i class="bi bi-clipboard-check"></i> SET FNSKU
                        </button>

                        <button @click="confirmMoveToValidation(item)" class="btn btn-validation"
                            :disabled="isProcessing">
                            <i class="bi bi-check-circle"></i> Move to Validation
                        </button>

                        <button @click="confirmMoveToStockroom(item)" class="btn btn-stockroom"
                            :disabled="isProcessing">
                            <i class="bi bi-box-seam"></i> Move to Stockroom
                        </button>
                    </div>

                    <hr v-if="expandedRows[index]">

                    <div v-if="expandedRows[index]" class="mobile-expanded-content">
                        <p><strong>External Title provided by Supplier:</strong> {{ item.ProductTitle }}</p>
                        <p><strong>Product Name:</strong> {{ item.AStitle }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination with centered layout -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <select v-model="perPage" @change="changePerPage" class="per-page-select">
                        <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                </div>

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

        <!-- Image Modal with Tabs -->
        <div v-if="showImageModal" class="image-modal">
            <div class="modal-overlay" @click="closeImageModal"></div>
            <div class="modal-content">
                <button class="close-button" @click="closeImageModal">&times;</button>

                <!-- Tabs for switching between regular and captured images -->
                <div class="image-tabs">
                    <button class="tab-button" :class="{ active: activeTab === 'regular' }"
                        @click="switchTab('regular')" :disabled="regularImages.length === 0">
                        Product Images ({{ regularImages.length }})
                    </button>
                    <button class="tab-button" :class="{ active: activeTab === 'captured' }"
                        @click="switchTab('captured')" :disabled="capturedImages.length === 0">
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
                    <img :src="currentImageSet[currentImageIndex]" alt="Product Image" class="modal-main-image"
                        @error="handleImageError" />
                    <button class="nav-button next" @click="nextImage" v-if="currentImageSet.length > 1">&gt;</button>
                </div>

                <div class="image-counter" v-if="currentImageSet.length > 0">
                    {{ currentImageIndex + 1 }} / {{ currentImageSet.length }}
                </div>

                <!-- Thumbnails for the current image set -->
                <div class="thumbnails-container" v-if="currentImageSet.length > 1">
                    <div v-for="(image, index) in currentImageSet" :key="index" class="modal-thumbnail"
                        :class="{ active: index === currentImageIndex }" @click="currentImageIndex = index">
                        <img :src="image" :alt="`Thumbnail ${index + 1}`" @error="handleImageError" />
                    </div>
                </div>
            </div>
        </div>

        <!-- FNSKU Selection Modal - Moved outside image modal and now has proper styling -->
     <!-- FNSKU Selection Modal - Updated with hidden fields and loading -->
<div v-if="isFnskuModalVisible" class="modal fnsku-modal">
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
            <!-- Product Info - Updated to hide ID -->
            <div class="fnsku-product-info">
                <h4>{{ currentItem?.ProductTitle }}</h4>
                <div class="fnsku-product-details">
                    <!-- ID and Serial are now hidden -->
                    <!-- <p><strong>ID:</strong> {{ currentItem?.ProductID }}</p> -->
                    <!-- <p><strong>Serial#:</strong> {{ currentItem?.serialnumber }}</p> -->
                    <p><strong>Current FNSKU:</strong> {{ currentItem?.FNSKUviewer || 'None' }}</p>
                    <p><strong>RT#:</strong> {{ currentItem?.rtcounter }}</p>
                </div>
            </div>

            <!-- Search with Loading -->
            <div class="fnsku-search-container">
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        v-model="fnskuSearch" 
                        placeholder="Search FNSKU, ASIN, title, or grading..."
                        class="fnsku-search-input form-control" 
                        @input="filterFnskuList"
                        :disabled="isSearching" />
                    
                    <!-- Loading spinner inside search input -->
                    <div v-if="isSearching" class="search-loading-spinner">
                        <div class="spinner"></div>
                    </div>
                </div>
                
                <!-- Loading text below search -->
                <div v-if="isSearching" class="search-loading-text">
                    Searching FNSKUs...
                </div>
            </div>

            <!-- FNSKU List - Desktop Table -->
            <div class="fnsku-list-container d-none d-md-block">
                <!-- Show loading overlay when searching -->
                <div v-if="isSearching" class="fnsku-loading-overlay">
                    <div class="loading-content">
                        <div class="loading-spinner-large"></div>
                        <p>Loading FNSKUs...</p>
                    </div>
                </div>

                <table class="table" :class="{ 'loading-blur': isSearching }">
                    <thead class="table-dark">
                        <tr>
                            <th>FNSKU Details</th>
                            <th>Title & Inventory</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(fnsku, index) in filteredFnskuList" :key="fnsku.FNSKU">
                            <tr>
                                <td>
                                    <ul class="list-unstyled m-0 fnsku-details">
                                        <li>{{ fnsku.FNSKU }}</li>
                                        <li><strong>ASIN:</strong> {{ fnsku.ASIN }}</li>
                                        <li>
                                            <div class="badge badge-pill badge-secondary fnsku-badge"
                                                :class="{ 'badge-success': fnsku.grading.includes('New') }">
                                                {{ fnsku.grading }}
                                            </div>
                                        </li>
                                    </ul>
                                </td>
                                <td>
                                    <ul class="list-unstyled m-0 fnsku-title">
                                        <li>{{ fnsku.astitle }}</li>
                                        <li>{{ fnsku.Units }} in inventory</li>
                                    </ul>
                                </td>
                                <td>
                                    <div class="fnsku-action">
                                        <button @click="selectFnsku(fnsku)" class="btn btn-fnsku-select"
                                            :class="{ 'fnsku-recommended': fnsku.ASIN === currentItem?.ASINviewer }"
                                            :disabled="isSearching">
                                            {{ fnsku.ASIN === currentItem?.ASINviewer ? 'Recommended' : 'Select' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        
                        <!-- No results row -->
                        <tr v-if="filteredFnskuList.length === 0 && !isSearching">
                            <td colspan="3" class="text-center">
                                <span class="fnsku-no-results">No matching FNSKUs found</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile FNSKU Card View -->
            <div class="fnsku-card-container d-block d-md-none">
                <!-- Mobile loading overlay -->
                <div v-if="isSearching" class="fnsku-loading-overlay mobile">
                    <div class="loading-content">
                        <div class="loading-spinner-large"></div>
                        <p>Loading FNSKUs...</p>
                    </div>
                </div>

                <div :class="{ 'loading-blur': isSearching }">
                    <div v-for="(fnsku, index) in filteredFnskuList" :key="fnsku.FNSKU" class="card mb-3 shadow-sm"
                        :class="index % 2 === 0 ? 'bg-light' : 'bg-white'">
                        <div class="card-body d-flex flex-column gap-3">
                            <!-- Section 1: FNSKU Details -->
                            <div class="d-flex justify-content-between">
                                <div class="fnsku-details">
                                    <h6>{{ fnsku.FNSKU }}</h6>
                                    <p><strong>ASIN:</strong> {{ fnsku.ASIN }}</p>
                                </div>
                                <span class="badge fnsku-badge" :class="{
                                    'bg-success': fnsku.grading.includes('New'),
                                    'bg-secondary': !fnsku.grading.includes('New')
                                }">
                                    {{ fnsku.grading }}
                                </span>
                            </div>

                            <!-- Section 2: Title & Inventory -->
                            <div class="d-flex flex-column align-items-start gap-1">
                                <span><strong>{{ fnsku.astitle }}</strong></span>
                                <span class="text-muted mb-0">{{ fnsku.Units }} in inventory</span>
                            </div>

                            <!-- Section 3: Action Button -->
                            <div>
                                <button @click="selectFnsku(fnsku)" class="btn btn-sm" :class="{
                                    'btn-success': fnsku.ASIN === currentItem?.ASINviewer,
                                    'btn-outline-primary': fnsku.ASIN !== currentItem?.ASINviewer
                                }" :disabled="isSearching">
                                    {{ fnsku.ASIN === currentItem?.ASINviewer ? 'Recommended' : 'Select' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- No results message -->
                    <div v-if="filteredFnskuList.length === 0 && !isSearching" class="alert alert-info text-center">
                        No matching FNSKUs found
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Add this confirmation modal HTML to your template section -->
        <!-- Confirmation Modal -->
        <div v-if="showConfirmationModal" class="modal confirmation-modal">
            <div class="modal-overlay" @click="cancelConfirmation"></div>
            <div class="confirmation-modal-content">
                <div class="confirmation-modal-header">
                    <h3>{{ confirmationTitle }}</h3>
                    <button class="confirmation-close" @click="cancelConfirmation">&times;</button>
                </div>
                <div class="confirmation-modal-body">
                    <p>{{ confirmationMessage }}</p>
                </div>
                <div class="confirmation-modal-footer">
                    <button class="btn-cancel" @click="cancelConfirmation">
                        Cancel
                    </button>
                    <button class="btn-confirm" @click="confirmAction"
                        :class="{ 'btn-validation': confirmationActionType === 'validation', 'btn-stockroom': confirmationActionType === 'stockroom' }">
                        Yes, Proceed
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
    import Labeling from "./labeling.js";
    export default Labeling;
</script>

<style>

/* Loading Animation CSS - Add this to your labeling.css file */

/* Search input wrapper for positioning */
.search-input-wrapper {
    position: relative;
    width: 100%;
}

/* Small spinner inside search input */
.search-loading-spinner {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
}

.search-loading-spinner .spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Loading text below search */
.search-loading-text {
    text-align: center;
    color: #6c757d;
    font-size: 0.9em;
    margin-top: 8px;
    font-style: italic;
}

/* Loading overlay for FNSKU list */
.fnsku-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    border-radius: 8px;
}

.fnsku-loading-overlay.mobile {
    position: relative;
    min-height: 200px;
    background: rgba(248, 249, 250, 0.9);
}

/* Loading content */
.loading-content {
    text-align: center;
    padding: 20px;
}

.loading-content p {
    margin-top: 15px;
    color: #6c757d;
    font-weight: 500;
}

/* Large spinner for overlay */
.loading-spinner-large {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

/* Blur effect when loading */
.loading-blur {
    filter: blur(1px);
    opacity: 0.6;
    pointer-events: none;
    transition: all 0.3s ease;
}

/* Spinner animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Disabled state for buttons during loading */
button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Search input disabled state */
.fnsku-search-input:disabled {
    background-color: #f8f9fa;
    cursor: not-allowed;
}

/* Pulse animation for search input when loading */
.fnsku-search-input:disabled {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        background-color: #f8f9fa;
    }
    50% {
        background-color: #e9ecef;
    }
    100% {
        background-color: #f8f9fa;
    }
}

/* Container positioning for overlay */
.fnsku-list-container,
.fnsku-card-container {
    position: relative;
}

/* Ensure table maintains structure during blur */
.table.loading-blur {
    table-layout: fixed;
}

/* Loading state for mobile cards */
.fnsku-card-container.loading-blur .card {
    pointer-events: none;
}

/* Smooth transitions */
.fnsku-list-container,
.fnsku-card-container,
.search-input-wrapper {
    transition: all 0.3s ease;
}

/* Loading indicator variants */
.spinner-small {
    width: 12px;
    height: 12px;
    border-width: 2px;
}

.spinner-medium {
    width: 24px;
    height: 24px;
    border-width: 3px;
}

.spinner-large {
    width: 48px;
    height: 48px;
    border-width: 4px;
}

/* Responsive loading overlay */
@media (max-width: 768px) {
    .fnsku-loading-overlay {
        border-radius: 0;
    }
    
    .loading-spinner-large {
        width: 32px;
        height: 32px;
        border-width: 3px;
    }
    
    .loading-content {
        padding: 15px;
    }
    
    .loading-content p {
        font-size: 0.9em;
        margin-top: 10px;
    }
}
</style>
