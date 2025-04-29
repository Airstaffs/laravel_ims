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

                            <button class="Desktop" style="border: solid 1px black; background-color: aliceblue;"
                                @click="toggleDetailsVisibility">{{ showDetails ? 'Hide extra columns' : 'Show extra columns' }}</button>
                        </th>
                        <th class="Desktop">Location</th>
                        <th class="Desktop">Added date</th>
                        <th class="Desktop">Updated date</th>
                        <th class="Desktop">Fnsku</th>
                        <th class="Desktop">Msku</th>
                        <th class="Desktop">Asin</th>
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
                                <button @click="showFnskuModal(item)" class="test-btn mt-2">
                                    <i class="bi bi-clipboard-check"></i> SET FNSKU
                                </button>

                                <button @click="confirmMoveToValidation(item)" class="action-btn btn-validation"
                                    :disabled="isProcessing">
                                    <i class="bi bi-check-circle"></i> Move to Validation
                                </button><br>

                                <button @click="confirmMoveToStockroom(item)" class="action-btn btn-stockroom"
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

                                        <button @click="confirmMoveToValidation(item)" class="action-btn btn-validation"
                                            :disabled="isProcessing">
                                            <i class="bi bi-check-circle"></i> Move to Validation
                                        </button>

                                        <button @click="confirmMoveToStockroom(item)" class="action-btn btn-stockroom"
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
                        <input type="text" v-model="fnskuSearch" placeholder="Search FNSKU, ASIN, title, or grading..."
                            class="fnsku-search-input" @input="filterFnskuList" />
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
