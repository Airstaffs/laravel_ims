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

                            <button class="Desktop" style="border: solid 1px black; background-color: aliceblue;"
                                @click="toggleDetailsVisibility">{{ showDetails ? 'Hide extra columns' : 'Show extra columns' }}</button>
                        </th>
                        <th class="Desktop">Location</th>
                        <th class="Desktop">Added date</th>
                        <th class="Desktop">Updated date</th>
                        <th class="Desktop">Fnsku</th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">FBM</th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">FBA</th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">Outbound</th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">Inbound</th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">Unfulfillable
                        </th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">Reserved</th>
                        <th class="Desktop">Fulfillment</th>
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
                                            <span v-else-if="item.storename === 'Renovartech'">RT
                                                {{ item.rtcounter }}</span>
                                            <span v-else>{{ item.rtcounter }}</span>
                                        </p>
                                        <p class="product-name">{{ item.astitle }}</p>
                                        <p class="product-name"
                                            :style="{ color: item.validation_status === 'validated' ? 'green' : 'orange' }">
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


                            <!-- Hidden --> <!-- Hidden --> <!-- Hidden -->
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
                            <!-- Hidden --> <!-- Hidden --> <!-- Hidden -->

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

                                <button @click="confirmMoveToLabeling(item)" class="action-btn btn-labeling"
                                    :disabled="isProcessing">
                                    <i class="bi bi-check-circle"></i> Move to Labeling
                                </button><br>

                                <button @click="confirmMoveToStockroom(item)" class="action-btn btn-stockroom"
                                    :disabled="isProcessing">
                                    <i class="bi bi-box-seam"></i> Move to Stockroom
                                </button><br>

                                <button class="btn-validation" @click="openValidationModal(item)">
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

                                        <button @click="confirmMoveToLabeling(item)" class="action-btn btn-labeling"
                                            :disabled="isProcessing">
                                            <i class="bi bi-check-circle"></i> Move to Labeling
                                        </button>

                                        <button @click="confirmMoveToStockroom(item)" class="action-btn btn-stockroom"
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
                            <button style="width: 100%; border-bottom: 2px solid black; padding:0px"
                                @click="toggleDetails(index)">
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
