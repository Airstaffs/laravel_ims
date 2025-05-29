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

        <h2 class="module-title">Stockroom Module</h2>

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
                            </div>
                        </th>
                        <th>
                            <div class="sortable" @click="sortBy('ASIN')">
                                ASIN
                                <i v-if="sortColumn === 'ASIN'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th>
                            <div class="sortable" @click="sortBy('storename')">
                                Store
                                <i v-if="sortColumn === 'storename'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th>
                            <div class="">
                                FNSKUs
                            </div>
                        </th>
                        <th>
                            <div class="sortable" @click="sortBy('FBMAvailable')">
                                FBM
                                <i v-if="sortColumn === 'FBMAvailable'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th>
                            <div class="sortable" @click="sortBy('FbaAvailable')">
                                FBA
                                <i v-if="sortColumn === 'FbaAvailable'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th>
                            <div class="th-content sortable" @click="sortBy('item_count')">
                                Quantity Inside
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
                            <td class="sticky-col first-col">
                                <input type="checkbox" v-model="item.checked" />
                            </td>
                            <td class="sticky-col second-sticky">
                                <div class="product-container">
                                    <div class="product-image-container clickable" @click="viewProductImage(item)">
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
                            </td>
                            <td>{{ item.ASIN }}</td>
                            <td>{{ item.storename }}</td>
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
                                        {{ expandedRows[index] ? 'Hide Details' : 'Show Details' }}
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
                            <td colspan="9">
                                <div class="expanded-content">
                                    <div class="expanded-serials">
                                        <strong>Serial Numbers & Locations:</strong>
                                        <div class="serial-table-container">
                                            <table class="serial-detail-table">
                                                <thead>
                                                    <tr>
                                                        <th>RT#</th>
                                                        <th>Serial Number</th>
                                                        <th>Location</th>
                                                        <th>FNSKU</th>
                                                        <th>MSKU</th>
                                                        <th>Grading</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="serial in item.serials" :key="serial.ProductID">
                                                        <td>{{ formatRTNumber(serial.rtcounter, item.storename) }}</td>
                                                        <td>{{ serial.serialnumber }}</td>
                                                        <td>{{ serial.warehouselocation }}</td>
                                                        <td>{{ serial.FNSKUviewer }}</td>
                                                        <td>{{ serial.MSKU }}</td>
                                                        <td>{{ serial.grading }}</td>
                                                    </tr>
                                                    <tr v-if="!item.serials || item.serials.length === 0">
                                                        <td colspan="6" class="text-center">No serial numbers found</td>
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

                    <hr>

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detail-value">{{ item.ASIN }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Store:</span>
                            <span class="mobile-detail-value">{{ item.storename }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Quantity Inside:</span>
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
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">FNSKUs:</span>
                            <span class="mobile-detail-value">{{ item.fnskus ? item.fnskus.length : 0 }}</span>
                        </div>
                    </div>

                    <hr>

                    <div class="mobile-card-actions">
                        <button class="btn" @click="printLabel(item.ProductID)">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button class="btn btn-expand" @click="toggleDetails(index)">
                            <i class="fas fa-list"></i> {{ expandedRows[index] ? 'Hide' : 'Details' }}
                        </button>
                        <button class="btn btn-details" @click="viewProductDetails(item)">
                            <i class="fas fa-info-circle"></i> More
                        </button>
                        <button class="btn btn-process" @click="openProcessModal(item)">
                            <i class="fas fa-cogs"></i> Process
                        </button>
                    </div>

                    <hr v-if="expandedRows[index]">

                    <div v-if="expandedRows[index]" class="mobile-expanded-content">
                        <div class="mobile-section">
                            <h4>Serial Numbers:</h4>
                            <div class="mobile-serial-list">
                                <div v-for="serial in item.serials" :key="serial.ProductID" class="mobile-serial-item">
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">RT#:</span>
                                        <span class="mobile-serial-value">{{ formatRTNumber(serial.rtcounter, item.storename) }}</span>
                                    </div>
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">Serial:</span>
                                        <span class="mobile-serial-value">{{ serial.serialnumber }}</span>
                                    </div>
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">Location:</span>
                                        <span class="mobile-serial-value">{{ serial.warehouselocation }}</span>
                                    </div>
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">FNSKU:</span>
                                        <span class="mobile-serial-value">{{ serial.FNSKUviewer }}</span>
                                    </div>
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">MSKU:</span>
                                        <span class="mobile-serial-value">{{ serial.MSKU }}</span>
                                    </div>
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">Grading:</span>
                                        <span class="mobile-serial-value">{{ serial.grading }}</span>
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
                                            {{ serial.serialnumber }} - {{ serial.FNSKUviewer }}</span>
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
                                    <span class="product-details-label">Store:</span>
                                    <span class="product-details-value">{{ selectedProduct.storename }}</span>
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
                                    <span class="product-details-label">Quantity Inside:</span>
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
                                        <div v-for="fnsku in selectedProduct.fnskus" :key="fnsku.FNSKU" class="product-details-fnsku-item">
                                            <div class="fnsku-main">{{ fnsku.FNSKU || fnsku }}</div>
                                            <div class="fnsku-details">
                                                <span class="fnsku-detail">MSKU: {{ fnsku.MSKU || '-' }}</span>
                                                <span class="fnsku-detail">Grade: {{ fnsku.grading || '-' }}</span>
                                            </div>
                                        </div>
                                        <div v-if="!selectedProduct.fnskus || selectedProduct.fnskus.length === 0" class="product-details-empty">
                                            No FNSKUs found
                                        </div>
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
                                                <th>FNSKU</th>
                                                <th>MSKU</th>
                                                <th>Grading</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="serial in selectedProduct.serials" :key="serial.ProductID">
                                                <td>{{ formatRTNumber(serial.rtcounter, selectedProduct.storename) }}</td>
                                                <td>{{ serial.serialnumber }}</td>
                                                <td>{{ serial.warehouselocation }}</td>
                                                <td>{{ serial.FNSKUviewer || '-' }}</td>
                                                <td>{{ serial.MSKU || '-' }}</td>
                                                <td>{{ serial.grading || '-' }}</td>
                                            </tr>
                                            <tr v-if="!selectedProduct.serials || selectedProduct.serials.length === 0">
                                                <td colspan="6" class="text-center">No serial numbers found</td>
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

<style>
/* FNSKU Tag Styles - Simplified Layout */
/* FNSKU Tag Styles - Simplified Layout */
.fnsku-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.fnsku-tag {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px 12px;
    min-width: 200px;
}

.fnsku-main {
    font-weight: 600;
    color: #007bff;
    font-size: 14px;
    margin-bottom: 4px;
}

.fnsku-sub {
    font-size: 12px;
    color: #6c757d;
}

/* Product Details FNSKU Items */
.product-details-fnsku-item {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 8px;
}

serial-detail-table thead {
    background-color: #1a252f !important; /* Much darker header - almost black */
    color: white !important;
}

.serial-detail-table thead th {
    background-color: #1a252f !important;
    color: white !important;
    padding: 16px 12px !important;
    text-align: left !important;
    font-weight: 800 !important;
    font-size: 14px !important;
    border-right: 1px solid rgba(255,255,255,0.4) !important;
    white-space: nowrap !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3) !important;
}

/* Product Details Modal Table Styles with much darker header - ONLY for More Details */
.product-details-table thead {
    background-color: #212529 !important; /* Much darker header - almost black */
    color: white !important;
}

.product-details-table thead th {
    background-color: #212529 !important;
    color: white !important;
    padding: 14px 12px !important;
    text-align: left !important;
    font-weight: 800 !important;
    font-size: 13px !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3) !important;
}

.product-details-fnsku-item .fnsku-main {
    font-weight: 600;
    color: #007bff;
    font-size: 14px;
    margin-bottom: 4px;
}

.fnsku-details {
    font-size: 12px;
    color: #6c757d;
}

.fnsku-detail {
    margin-right: 15px;
}

/* Mobile FNSKU Styles - Simplified */
.mobile-fnsku-item {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 8px;
}

.mobile-fnsku-item .fnsku-main {
    font-weight: 600;
    color: #007bff;
    font-size: 14px;
    margin-bottom: 4px;
}

.mobile-fnsku-item .fnsku-details {
    font-size: 12px;
    color: #6c757d;
}

/* Enhanced Mobile Serial Detail Styles */
.mobile-serial-item {
    background-color: #ffffff;
    border: 1px solid #007bff;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    box-shadow: 0 2px 4px rgba(0,123,255,0.1);
}

.mobile-serial-detail {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid #e3f2fd;
}

.mobile-serial-detail:last-child {
    border-bottom: none;
}

.mobile-serial-label {
    font-weight: 600;
    color: #007bff;
    font-size: 13px;
    min-width: 70px;
}

.mobile-serial-value {
    color: #495057;
    font-size: 13px;
    text-align: right;
    flex: 1;
    margin-left: 10px;
    font-weight: 500;
}

/* Product Details Modal - Make it even wider */
.product-details-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.product-details-content {
    background-color: white;
    border-radius: 12px;
    width: 98%;
    max-width: 1600px; /* Increased even more */
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.product-details-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.product-details-body {
    padding: 20px;
}

/* Product Details Layout Fixes */
.product-details-layout {
    display: flex;
    gap: 40px; /* Increased gap even more */
    max-width: 100%;
}

.product-details-left {
    flex: 0 0 400px; /* Increased width even more */
    max-width: 400px;
}

.product-details-right {
    flex: 1;
    min-width: 0; /* Allow shrinking */
}

.product-details-fnskus-section {
    margin-top: 15px;
}

.product-details-fnskus-section h4 {
    margin-bottom: 8px;
    color: #495057;
    font-size: 14px;
}

/* Expanded row table - Make it 90% width */
.expanded-row {
    background-color: #f8f9fa;
}

.expanded-content {
    padding: 20px;
    width: 90%; /* Make expanded content 90% width */
    margin: 0 auto; /* Center it */
}

.serial-table-container {
    width: 100%;
    overflow-x: auto;
    margin-top: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Enhanced Serial Detail Table with much darker header */
.serial-detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    background-color: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    min-width: 900px; /* Increased minimum width */
}

.serial-detail-table thead {
    background-color: #1a252f; /* Much darker header - almost black */
    color: white;
}

.serial-detail-table thead th {
    padding: 16px 12px; /* Even more padding */
    text-align: left;
    font-weight: 800; /* Extra bold font */
    font-size: 14px; /* Larger font size */
    border-right: 1px solid rgba(255,255,255,0.4);
    white-space: nowrap;
    text-transform: uppercase; /* Make headers uppercase */
    letter-spacing: 1px; /* More letter spacing */
    text-shadow: 0 1px 2px rgba(0,0,0,0.3); /* Add text shadow for better contrast */
}

.serial-detail-table thead th:last-child {
    border-right: none;
}

.serial-detail-table tbody td {
    padding: 12px 10px; /* Increased padding even more */
    border-bottom: 1px solid #dee2e6;
    border-right: 1px solid #dee2e6;
    color: #495057;
    vertical-align: middle;
    font-size: 13px;
}

.serial-detail-table tbody td:last-child {
    border-right: none;
}

.serial-detail-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.serial-detail-table tbody tr:hover {
    background-color: #e3f2fd;
}

/* Product Details Modal Table Styles with much darker header */
.product-details-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px; /* Increased font size */
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.product-details-table thead {
    background-color: #212529; /* Much darker header - almost black */
    color: white;
}

.product-details-table thead th {
    padding: 14px 12px; /* Increased padding */
    text-align: left;
    font-weight: 800; /* Extra bold font */
    font-size: 13px;
    text-transform: uppercase; /* Make headers uppercase */
    letter-spacing: 1px; /* More letter spacing */
    text-shadow: 0 1px 2px rgba(0,0,0,0.3); /* Add text shadow for better contrast */
}

.product-details-table tbody td {
    padding: 10px; /* Increased padding */
    border-bottom: 1px solid #dee2e6;
    color: #495057;
    font-size: 12px;
}

.product-details-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.product-details-table tbody tr:hover {
    background-color: #e9ecef;
}

/* Expanded content headers */
.expanded-content strong {
    color: #1a252f !important;
    font-weight: 700 !important;
    font-size: 16px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

/* Mobile section headers */
.mobile-section h4 {
    background-color: #1a252f !important;
    color: white !important;
    padding: 10px 15px !important;
    margin: 0 0 10px 0 !important;
    border-radius: 6px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

/* Responsive adjustments */
@media (max-width: 1400px) {
    .product-details-content {
        width: 99%;
        max-width: 1400px;
    }
    
    .product-details-layout {
        gap: 30px;
    }
    
    .product-details-left {
        flex: 0 0 350px;
        max-width: 350px;
    }
}

@media (max-width: 1200px) {
    .product-details-content {
        width: 98%;
        max-width: 1200px;
    }
    
    .product-details-layout {
        gap: 20px;
    }
    
    .product-details-left {
        flex: 0 0 300px;
        max-width: 300px;
    }
}

@media (max-width: 992px) {
    .product-details-layout {
        flex-direction: column;
    }
    
    .product-details-left {
        flex: none;
        max-width: 100%;
    }
    
    .product-details-right {
        flex: none;
    }
    
    .expanded-content {
        width: 95%;
    }
}

@media (max-width: 768px) {
    .product-details-content {
        width: 100%;
        height: 100vh;
        border-radius: 0;
        max-height: 100vh;
    }
    
    .serial-detail-table {
        min-width: 700px;
    }
    
    .serial-detail-table thead th,
    .serial-detail-table tbody td {
        padding: 10px 8px;
        font-size: 12px;
    }
    
    .expanded-content {
        width: 100%;
        padding: 15px;
    }
}

/* Expanded Content Section Spacing */
.expanded-fnskus, .expanded-serials {
    margin-bottom: 20px;
}

.expanded-fnskus strong, .expanded-serials strong {
    color: #495057;
    font-size: 15px;
    display: block;
    margin-bottom: 10px;
}

/* Process Modal Item Display Enhancement */
.process-item-row span {
    font-size: 14px;
    color: #495057;
}

/* Responsive adjustments for tables */
@media (max-width: 768px) {
    .fnsku-detail-table-container,
    .serial-table-container {
        font-size: 12px;
    }
    
    .fnsku-detail-table th,
    .fnsku-detail-table td,
    .serial-detail-table th,
    .serial-detail-table td {
        padding: 8px 6px;
    }
    
    .mobile-fnsku-item,
    .mobile-serial-item {
        margin-bottom: 8px;
        padding: 10px;
    }
}
</style>
