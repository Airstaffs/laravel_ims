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

                        <button @click="confirmMoveToValidation(item)" class="btn btn-validation" :disabled="isProcessing">
                            <i class="bi bi-check-circle"></i> Move to Validation
                        </button>

                        <button @click="confirmMoveToStockroom(item)" class="btn btn-stockroom" :disabled="isProcessing">
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
                        <input type="text" v-model="fnskuSearch" placeholder="Search FNSKU, ASIN, title, or grading..."
                            class="fnsku-search-input form-control" @input="filterFnskuList" />
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
                            <div v-for="(fnsku, index) in filteredFnskuList" :key="fnsku.FNSKU" class="fnsku-item"
                                :class="{
                                    'fnsku-highlighted': fnsku.ASIN === currentItem?.ASIN,
                                    'fnsku-even': index % 2 === 0
                                }">
                                <div class="fnsku-details-column">
                                    <div class="fnsku-code">{{ fnsku.FNSKU }}</div>
                                    <div class="fnsku-asin">ASIN: {{ fnsku.ASIN }}</div>
                                    <div class="fnsku-badge" :class="{ 'fnsku-new': fnsku.grading.includes('New') }">
                                        {{ fnsku.grading }}
                                    </div>
                                </div>

                                <div class="fnsku-title-column">
                                    <div class="fnsku-title">{{ fnsku.astitle }}</div>
                                    <div class="fnsku-units">{{ fnsku.Units }} in inventory</div>
                                </div>

                                <div class="fnsku-action-column">
                                    <button @click="selectFnsku(fnsku)" class="fnsku-select-btn"
                                        :class="{ 'fnsku-recommended': fnsku.ASIN === currentItem?.ASIN }">
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
