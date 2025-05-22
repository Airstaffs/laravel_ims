<template>
    <div class="vue-container validation-module">
        <div class="top-header">
            <span>Top Header</span>
        </div>

        <h2 class="module-title">Validation Module</h2>

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
                        <th class="">Location</th>
                        <th class="">Added date</th>
                        <th class="">Updated date</th>
                        <th class="">Fnsku</th>
                        <th class="">Msku</th>
                        <th class="">Asin</th>
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
                                        <div class="image-count-badge" v-if="countAdditionalImages(item) > 0">
                                            +{{ countAdditionalImages(item) }}
                                        </div>
                                    </div>
                                    <div class="product-info">
                                        <p>ID#:
                                            <span v-if="item.storename === 'Allrenewed'">AR {{ item.rtcounter }}</span>
                                            <span v-else-if="item.storename === 'Renovartech'">RT
                                                {{ item.rtcounter }}</span>
                                            <span v-else>{{ item.rtcounter }}</span>
                                        </p>
                                        <p>{{ item.astitle }}</p>
                                        <p
                                            :style="{ color: item.validation_status === 'validated' ? 'green' : 'orange' }">
                                            ({{ item.validation_status }})
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span><strong></strong> {{ item.warehouselocation }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.datedelivered }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.lastDateUpdate }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.FNSKUviewer }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.MSKUviewer }}</span>
                            </td>
                            <td>
                                <span><strong></strong> {{ item.ASINviewer }}</span>
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

                                    <button @click="confirmMoveToLabeling(item)" class="action-btn btn-labeling"
                                        :disabled="isProcessing">
                                        <i class="bi bi-check-circle"></i> Move to Labeling
                                    </button>

                                    <button @click="confirmMoveToStockroom(item)" class="action-btn btn-stockroom"
                                        :disabled="isProcessing">
                                        <i class="bi bi-box-seam"></i> Move to Stockroom
                                    </button>

                                    <button class="btn-validation" @click="openValidationModal(item)">
                                        Open Validation
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="expandedRows[index]">
                            <td :colspan="showDetails ? 18 : 12">
                                <div class="expanded-content p-3 border rounded">
                                    <p><strong>External Title provided by Supplier:</strong> {{ item.ProductTitle }}</p>
                                    <p><strong>Product Name:</strong> {{ item.astitle }}</p>
                                    <p><strong>Store Name:</strong> {{ item.storename }}</p>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <div class="mobile-showDetails-container">
                <button class="btn-showDetailsM"
                    @click="toggleDetailsVisibility">{{ showDetails ? 'Hide extra columns' : 'Show extra columns' }}
                </button>
            </div>
            <div class="mobile-cards">
                <div class="mobile-card" v-for="(item, index) in sortedInventory" :key="item.id">
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <div class="mobile-product-image clickable">
                            <!-- Use the actual file path for the main image -->
                            <img :src="'/images/thumbnails/' + item.img1" :alt="item.ProductTitle || 'Product'"
                                class="product-thumbnail clickable-image" @error="handleImageError($event)" />
                            <div class="image-count-badge" v-if="countAdditionalImages(item) > 0">
                                +{{ countAdditionalImages(item) }}
                            </div>
                        </div>
                        <div class="mobile-product-info">
                            <h3 class="mobile-product-name clickable">
                                <p>ID#:
                                    <span v-if="item.storename === 'Allrenewed'">AR {{ item.rtcounter }}</span>
                                    <span v-else-if="item.storename === 'Renovartech'">RT
                                        {{ item.rtcounter }}</span>
                                    <span v-else>{{ item.rtcounter }}</span>
                                </p>
                                <p>{{ item.astitle }}</p>
                                <p :style="{ color: item.validation_status === 'validated' ? 'green' : 'orange' }">
                                    ({{ item.validation_status }})
                                </p>
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
                        <button @click="confirmMoveToLabeling(item)" class="btn btn-labeling" :disabled="isProcessing">
                            <i class="bi bi-check-circle"></i> Move to Labeling
                        </button>

                        <button @click="confirmMoveToStockroom(item)" class="btn btn-stockroom"
                            :disabled="isProcessing">
                            <i class="bi bi-box-seam"></i> Move to Stockroom
                        </button>

                        <button class="btn btn-validation" @click="openValidationModal(item)">
                            Open Validation
                        </button>
                    </div>

                    <hr v-if="expandedRows[index]">

                    <div v-if="expandedRows[index]" class="mobile-expanded-content">
                        <p><strong>External Title provided by Supplier:</strong> {{ item.ProductTitle }}</p>
                        <p><strong>Product Name:</strong> {{ item.astitle }}</p>
                        <p><strong>Store Name:</strong> {{ item.storename }}</p>
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

                <!-- Tabs for switching between regular, captured, and ASIN images -->
                <div class="image-tabs">
                    <button class="tab-button" :class="{ active: activeTab === 'regular' }"
                        @click="switchTab('regular')" :disabled="regularImages.length === 0">
                        Product Images ({{ regularImages.length }})
                    </button>
                    <button class="tab-button" :class="{ active: activeTab === 'captured' }"
                        @click="switchTab('captured')" :disabled="capturedImages.length === 0">
                        Captured Images ({{ capturedImages.length }})
                    </button>
                    <button class="tab-button" :class="{ active: activeTab === 'asin' }" @click="switchTab('asin')"
                        :disabled="asinImages.length === 0">
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

        <!-- Validation Modal -->
        <div class="validation-modal" v-if="showValidationModal && currentValidationItem">
            <div class="modal-overlay" @click="closeValidationModal"></div>
            <div class="validation-modal-content">
                <div class="validation-modal-header">
                    <h3>Validate Item #

                        <span v-if="currentValidationItem.storename === 'Allrenewed'">AR
                            {{ currentValidationItem.rtcounter }}</span>
                        <span v-else-if="currentValidationItem.storename === 'Renovartech'">RT
                            {{ currentValidationItem.rtcounter }}</span>
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
                                <span v-if="currentValidationItem.storename === 'Allrenewed'">AR
                                    {{ currentValidationItem.rtcounter }}</span>
                                <span v-else-if="currentValidationItem.storename === 'Renovartech'">RT
                                    {{ currentValidationItem.rtcounter }}</span>
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
                            <span
                                :style="{ color: currentValidationItem.validation_status === 'validated' ? 'green' : 'orange' }">
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
                                        <img :src="'/images/thumbnails/' + currentValidationItem.img1"
                                            :alt="currentValidationItem.ProductTitle || 'Supplier Image'"
                                            @error="handleImageError($event)" class="compare-image" />
                                    </div>
                                </div>
                                <div class="compare-item">
                                    <div class="compare-title">From IMS fetch from Amazon</div>
                                    <div class="compare-subtitle">{{ currentValidationItem.astitle }}</div>
                                    <div class="compare-image-container">
                                        <img v-if="currentValidationItem.asin"
                                            :src="`/images/asinimg/${currentValidationItem.asin}.png`"
                                            :alt="currentValidationItem.astitle || 'Amazon Image'"
                                            @error="handleImageError($event)" class="compare-image" />
                                        <div v-else class="no-asin-image">
                                            No ASIN image available
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="validation-image-tabs">
                            <button class="validation-tab-button" :class="{ active: validationActiveTab === 'product' }"
                                @click="switchValidationTab('product')">
                                Product Images
                            </button>
                            <button class="validation-tab-button"
                                :class="{ active: validationActiveTab === 'captured' }"
                                @click="switchValidationTab('captured')">
                                Captured Images
                            </button>
                            <button class="validation-tab-button" :class="{ active: validationActiveTab === 'asin' }"
                                @click="switchValidationTab('asin')">
                                ASIN Images
                            </button>
                        </div>

                        <!-- Product Images Tab Content -->
                        <div v-if="validationActiveTab === 'product'" class="validation-images-grid">
                            <!-- Main image display -->
                            <div class="validation-main-image">
                                <img :src="'/images/thumbnails/' + currentValidationItem.img1"
                                    :alt="currentValidationItem.astitle" @error="handleImageError($event)" />
                            </div>

                            <!-- Thumbnails gallery -->
                            <div class="validation-thumbnails">
                                <!-- Regular images thumbnails -->
                                <template v-for="i in 15" :key="`img-${i}`">
                                    <div v-if="i > 1 && currentValidationItem['img' + i] && currentValidationItem['img' + i] !== 'NULL'"
                                        class="validation-thumbnail">
                                        <img :src="'/images/thumbnails/' + currentValidationItem['img' + i]"
                                            :alt="`Image ${i}`" @error="handleImageError($event)" />
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
                                        <div v-if="currentValidationItem.capturedImages['capturedimg' + i] && currentValidationItem.capturedImages['capturedimg' + i] !== 'NULL'"
                                            class="validation-thumbnail captured">
                                            <img :src="'/images/product_images/' + (currentValidationItem.company || 'Airstaffs') + '/' + currentValidationItem.capturedImages['capturedimg' + i]"
                                                :alt="`Captured ${i}`" @error="handleImageError($event)" />
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
                                <div v-for="(image, index) in currentValidationItemAsinImages" :key="`asin-${index}`"
                                    class="validation-thumbnail asin">
                                    <img :src="image" :alt="`ASIN Image ${index + 1}`"
                                        @error="handleImageError($event)" />
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
                    <button class="btn-cancel" @click="closeValidationModal" :disabled="isProcessingValidation">
                        Cancel
                    </button>
                    <button class="btn-invalid" @click="confirmMarkAsInvalid" :disabled="isProcessingValidation">
                        <i class="bi bi-x-circle"></i> Mark as Invalid
                    </button>
                    <button class="btn-valid" @click="confirmMarkAsValid" :disabled="isProcessingValidation">
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
                    <button class="btn-no" @click="cancelConfirmation">
                        <i class="bi bi-x"></i> No
                    </button>
                    <button class="btn-yes"
                        @click="confirmationActionType === 'valid' ? markAsValid() : markAsInvalid()" :class="{
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
    import Validation from "./validation.js";
    export default Validation;
</script>
