<template>
    <div class="vue-container stockroom-module">
        <!-- Top header bar with blue background -->
        <div class="top-header">
            <div class="header-buttons">
                <button class="btn" @click="openScannerModal">
                    <i class="fas fa-barcode"></i> Scan Items
                </button>
                <button class="btn" @click="loadFBAInboundShipment">
                    <i class="fas fa-truck"></i> FBA Inbound Shipment
                </button>
            </div>

            <div class="store-filter">
                <label for="store-select">Store:</label>
                <select id="store-select" v-model="selectedStore" @change="changeStore" class="store-select">
                    <option value="">All Stores</option>
                    <option v-for="store in stores" :key="store" :value="store">
                        {{ store }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Scanner Component (with hideButton prop to hide the scanner button) -->
        <scanner-component scanner-title="Stockroom Scanner" storage-prefix="stockroom" :enable-camera="true"
            :display-fields="['Serial', 'FNSKU', 'Location']" :api-endpoint="'/api/stockroom/process-scan'"
            :hide-button="true" @process-scan="handleScanProcess" @hardware-scan="handleHardwareScan"
            @scanner-opened="handleScannerOpened" @scanner-closed="handleScannerClosed"
            @scanner-reset="handleScannerReset" @mode-changed="handleModeChange" ref="scanner">
            <!-- Define custom input fields for Stockroom module -->
            <template #input-fields>
                <div class="input-group">
                    <label>Serial Number:</label>
                    <input type="text" v-model="serialNumber" placeholder="Enter Serial Number..."
                        @input="handleSerialInput"
                        @keyup.enter="showManualInput ? focusNextField('fnskuInput') : processScan()"
                        ref="serialNumberInput" />
                </div>

                <div class="input-group">
                    <label>FNSKU:</label>
                    <input type="text" v-model="fnsku" placeholder="Enter FNSKU..." @input="handleFnskuInput"
                        @keyup.enter="showManualInput ? focusNextField('locationInput') : processScan()"
                        ref="fnskuInput" />
                </div>

                <div class="input-group">
                    <label>Location:</label>
                    <input type="text" v-model="locationInput" placeholder="Enter Location..."
                        @input="handleLocationInput" @keyup.enter="processScan()" ref="locationInput" />
                    <div class="container-type-hint">Format: L###X (e.g., L123A) or 'Floor'</div>
                </div>

                <!-- Submit button (only in manual mode) -->
                <button v-if="showManualInput" @click="processScan()" class="submit-button">Submit</button>
            </template>
        </scanner-component>

        <!-- Desktop Table Container -->
        <div class="table-container desktop-view">
            <h2 class="module-title">Stockroom Module</h2>

            <table class="table">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" @click="toggleAll" v-model="selectAll" />
                        </th>
                        <th>
                            <div class="th-content">
                                <span class="sortable" @click="sortBy('AStitle')">
                                    Product Name
                                    <i v-if="sortColumn === 'AStitle'"
                                        :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                                </span>
                            </div>
                        </th>
                        <th>
                            <div class="th-content sortable" @click="sortBy('ASIN')">
                                ASIN
                                <i v-if="sortColumn === 'ASIN'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th>
                            <div class="th-content sortable" @click="sortBy('MSKUviewer')">
                                MSKU/SKU
                                <i v-if="sortColumn === 'MSKUviewer'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th>
                            <div class="th-content sortable" @click="sortBy('storename')">
                                Store
                                <i v-if="sortColumn === 'storename'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th>
                            <div class="th-content sortable" @click="sortBy('grading')">
                                Grading
                                <i v-if="sortColumn === 'grading'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th>
                            <div class="th-content">
                                FNSKUs
                            </div>
                        </th>
                        <th>
                            <div class="th-content sortable" @click="sortBy('FBMAvailable')">
                                FBM
                                <i v-if="sortColumn === 'FBMAvailable'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th>
                            <div class="th-content sortable" @click="sortBy('FbaAvailable')">
                                FBA
                                <i v-if="sortColumn === 'FbaAvailable'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th>
                            <div class="th-content sortable" @click="sortBy('item_count')">
                                Item Count
                                <i v-if="sortColumn === 'item_count'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th>
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
                                <div class="checkbox-container">
                                    <input type="checkbox" v-model="item.checked" />
                                </div>
                            </td>
                            <td>
                                <div class="product-cell">
                                    <div class="product-container">
                                        <div class="product-image clickable" @click="viewProductImage(item)">
                                            <img :src="item.useDefaultImage ? defaultImagePath : getImagePath(item.ASIN)"
                                                :alt="item.AStitle" class="product-thumbnail"
                                                @error="handleImageError($event, item)" />
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
                                        <option v-for="fnsku in item.fnskus" :key="fnsku.FNSKU || fnsku"
                                            :value="fnsku.FNSKU || fnsku">
                                            {{ fnsku.FNSKU || fnsku }}
                                        </option>
                                    </select>
                                    <span class="fnsku-count">({{ item.fnskus.length }})</span>
                                </div>
                                <div v-else>-</div>
                            </td>
                            <td>{{ item.FBMAvailable }}</td>
                            <td>{{ item.FbaAvailable }}</td>
                            <td :class="{ 'item-count-cell': true, 'item-count-warning': !item.countValid }">
                                {{ item.item_count }}
                                <i v-if="!item.countValid" class="fas fa-exclamation-circle"
                                    title="Item count doesn't match serial numbers"></i>
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
                                            <span v-for="fnsku in item.fnskus" :key="fnsku.FNSKU || fnsku"
                                                class="fnsku-tag">
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
            <h2 class="module-title">Stockroom Module</h2>

            <div class="mobile-cards">
                <div v-for="(item, index) in sortedInventory" :key="item.ASIN" class="mobile-card">
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <div class="mobile-product-image clickable" @click="viewProductImage(item)">
                            <img :src="item.useDefaultImage ? defaultImagePath : getImagePath(item.ASIN)"
                                :alt="item.AStitle" class="product-thumbnail-mobile"
                                @error="handleImageError($event, item)" />
                        </div>
                        <div class="mobile-product-info">
                            <h3 class="mobile-product-name clickable" @click="viewProductDetails(item)">
                                {{ item.AStitle }}
                            </h3>
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
                            <span :class="{ 'mobile-detail-value': true, 'item-count-warning': !item.countValid }">
                                {{ item.item_count }}
                                <i v-if="!item.countValid" class="fas fa-exclamation-circle"
                                    title="Item count doesn't match serial numbers"></i>
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
                                        <span
                                            class="mobile-serial-value">{{ formatRTNumber(serial.rtcounter, item.storename) }}</span>
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
                            <input type="text" v-model="processTrackingNumber" class="form-control"
                                placeholder="Enter tracking number...">
                        </div>
                        <div class="form-group">
                            <label>Notes (optional):</label>
                            <textarea v-model="processNotes" class="form-control"
                                placeholder="Add notes about this process..."></textarea>
                        </div>
                        <div class="form-group" v-if="singleItemSelected">
                            <label>New Location (optional):</label>
                            <input type="text" v-model="processLocation" class="form-control"
                                placeholder="e.g., L123A or Floor">
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
                                <div v-for="serial in currentProcessItem.serials" :key="serial.ProductID"
                                    class="process-item-row">
                                    <label class="process-item-checkbox">
                                        <input type="checkbox" v-model="selectedItems" :value="serial.ProductID">
                                        <span>{{ formatRTNumber(serial.rtcounter, currentProcessItem.storename) }} -
                                            {{ serial.serialnumber }}</span>
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
                    <button class="btn-update-location" @click="updateSelectedLocation"
                        :disabled="!hasSelectedItems || !processLocation">
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
                                <img :src="selectedProduct.useDefaultImage ? defaultImagePath : getImagePath(selectedProduct.ASIN)"
                                    :alt="selectedProduct.AStitle"
                                    :class="['product-details-thumbnail', enlargeImage ? 'enlarged' : '']"
                                    @error="handleImageError($event, selectedProduct)" />
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
                                    <span
                                        :class="{ 'product-details-value': true, 'item-count-warning': !selectedProduct.countValid }">
                                        {{ selectedProduct.item_count }}
                                        <i v-if="!selectedProduct.countValid" class="fas fa-exclamation-circle"
                                            title="Item count doesn't match serial numbers"></i>
                                    </span>
                                </div>

                                <div class="product-details-fnskus-section">
                                    <h4>FNSKUs</h4>
                                    <div class="product-details-fnskus">
                                        <span v-for="fnsku in selectedProduct.fnskus" :key="fnsku.FNSKU || fnsku"
                                            class="product-details-fnsku">
                                            {{ fnsku.FNSKU || fnsku }}
                                        </span>
                                        <span v-if="!selectedProduct.fnskus || selectedProduct.fnskus.length === 0"
                                            class="product-details-empty">
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
                                                <td>{{ formatRTNumber(serial.rtcounter, selectedProduct.storename) }}
                                                </td>
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
    import Stockroom from "./stockroom.js";
    export default Stockroom;
</script>
