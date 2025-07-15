<template>
    <div class="vue-container return-module">
        <div class="top-header">
            <div class="header-buttons">
                <button class="btn btn-scan" @click="openScannerModal">
                    <i class="fas fa-barcode"></i> Scan Items
                </button>
            </div>

            <div class="store-filter">
                <label for="store-select">Store:</label>
                <select
                    id="store-select"
                    v-model="selectedStore"
                    @change="changeStore"
                    class="store-select"
                >
                    <option value="">All Stores</option>
                    <option v-for="store in stores" :key="store" :value="store">
                        {{ store }}
                    </option>
                </select>
            </div>
        </div>

        <h2 class="module-title">Return List</h2>

        <!-- Scanner Component (with hideButton prop to hide the scanner button) -->
        <scanner-component
            scanner-title="Return Scanner"
            storage-prefix="returnscanner"
            :enable-camera="true"
            :display-fields="['ReturnID', 'Serial', 'Location']"
            :api-endpoint="'/api/returns/process-scan'"
            :hide-button="true"
            @process-scan="handleScanProcess"
            @hardware-scan="handleHardwareScan"
            @scanner-opened="handleScannerOpened"
            @scanner-closed="handleScannerClosed"
            @scanner-reset="handleScannerReset"
            @mode-changed="handleModeChange"
            ref="scanner"
        >
            <!-- Define custom input fields for Return Scanner module -->
            <template #input-fields>
                <!-- ReturnID toggle button -->
                <div class="toggle-container">
                    <button
                        type="button"
                        class="toggle-return-id"
                        @click="toggleReturnIdField"
                        :class="{ 'return-id-active': showReturnIdField }"
                    >
                        <i
                            :class="[
                                'fas',
                                showReturnIdField ? 'fa-eye-slash' : 'fa-eye',
                            ]"
                        ></i>
                        {{
                            showReturnIdField
                                ? "Hide Return ID"
                                : "Show Return ID"
                        }}
                    </button>
                </div>

                <!-- ReturnID field (optional) -->
                <div class="input-group" v-if="showReturnIdField">
                    <label>Return ID:</label>
                    <input
                        type="text"
                        v-model="returnId"
                        placeholder="Enter Return ID..."
                        @input="handleReturnIdInput"
                        @keyup.enter="
                            showManualInput
                                ? focusNextField('serialNumberInput')
                                : processScan()
                        "
                        ref="returnIdInput"
                    />
                </div>

                <div class="input-group">
                    <label>Serial Number:</label>
                    <input
                        type="text"
                        v-model="serialNumber"
                        placeholder="Enter Serial Number..."
                        @input="handleSerialInput"
                        @keyup.enter="
                            dualSerialProduct && showSecondSerialInput
                                ? focusNextField('secondSerialInput')
                                : showManualInput
                                ? focusNextField('locationInput')
                                : processScan()
                        "
                        ref="serialNumberInput"
                    />
                </div>

                <!-- Second Serial Number field (appears when a dual serial product is detected) -->
                <div
                    class="input-group"
                    v-if="dualSerialProduct && showSecondSerialInput"
                >
                    <label>{{ secondSerialLabel || "Second Serial" }}:</label>
                    <div class="input-with-clear">
                        <input
                            type="text"
                            v-model="secondSerialNumber"
                            placeholder="Enter Second Serial Number..."
                            @input="handleSecondSerialInput"
                            @keyup.enter="
                                showManualInput
                                    ? focusNextField('locationInput')
                                    : processScan()
                            "
                            ref="secondSerialInput"
                        />
                        <button
                            type="button"
                            class="clear-input-btn"
                            @click="hideSecondSerial"
                            title="Remove second serial"
                        >
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="input-group">
                    <label>Location:</label>
                    <input
                        type="text"
                        v-model="locationInput"
                        placeholder="Enter Location..."
                        @input="handleLocationInput"
                        @keyup.enter="processScan()"
                        ref="locationInput"
                    />
                    <div class="container-type-hint">
                        Format: L###X (e.g., L123A) or 'Floor'
                    </div>
                </div>

                <!-- Submit button (only in manual mode) -->
                <button
                    v-if="showManualInput"
                    @click="processScan()"
                    class="submit-button"
                >
                    Submit
                </button>
            </template>
        </scanner-component>

        <!-- Returns History Table -->
        <div class="table-container desktop-view">
            <table>
                <thead>
                    <tr>
                        <th class="sticky-col first-sticky">Image</th>
                        <th
                            class="sticky-col second-sticky"
                            @click="sortBy('LPNDATE')"
                        >
                            Date
                        </th>
                        <th @click="sortBy('LPN')">Return ID</th>
                        <th @click="sortBy('rtcounter')">RT#</th>
                        <th @click="sortBy('serialnumber')">Serial</th>
                        <th @click="sortBy('serialnumberb')">Second Serial</th>
                        <th @click="sortBy('returnstatus')">Status</th>
                        <th @click="sortBy('BuyerName')">Buyer</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="9" class="text-center">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                                Loading...
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="returnHistory.length === 0">
                        <td colspan="9" class="text-center">No data found</td>
                    </tr>
                    <template
                        v-else
                        v-for="(item, index) in returnHistory"
                        :key="index"
                    >
                        <tr>
                            <td class="sticky-col first-col">
                                <div class="product-container">
                                    <div
                                        class="product-image-container"
                                        @click="openImageModal(item)"
                                    >
                                        <!-- Use direct image path like in Production module -->
                                        <img
                                            :src="
                                                '/images/thumbnails/' +
                                                (item.img1 || 'default.jpg')
                                            "
                                            :alt="
                                                item.ProductTitle || 'Product'
                                            "
                                            class="product-thumbnail clickable-image"
                                            @error="handleImageError($event)"
                                        />
                                        <div
                                            class="image-count-badge"
                                            v-if="
                                                countAdditionalImages(item) > 0
                                            "
                                        >
                                            +{{ countAdditionalImages(item) }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="sticky-col second-sticky">
                                {{ formatDate(item.LPNDATE) }}
                            </td>
                            <td>{{ item.LPN || "N/A" }}</td>
                            <td>
                                {{
                                    formatRTNumber(
                                        item.rtcounter,
                                        item.storename
                                    )
                                }}
                            </td>
                            <td>{{ item.serialnumber }}</td>
                            <td>{{ item.serialnumberb || "-" }}</td>
                            <td>
                                <span
                                    :class="
                                        'status-badge status-' +
                                        item.returnstatus
                                    "
                                >
                                    {{ formatStatus(item.returnstatus) }}
                                </span>
                            </td>
                            <td>
                                {{
                                    item.BuyerName ||
                                    item.costumer_name ||
                                    "Unknown"
                                }}
                            </td>
                            <td>
                                <button
                                    class="btn-details"
                                    @click="viewReturnDetails(item)"
                                >
                                    <i class="fas fa-info-circle"></i> Details
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <div class="mobile-cards">
                <div v-if="loading" class="loading-spinner-mobile">
                    <i class="fas fa-spinner fa-spin"></i>
                    Loading...
                </div>
                <div
                    v-else-if="sortedInventory.length === 0"
                    class="no-data-mobile"
                >
                    No data found
                </div>
                <div
                    v-else
                    v-for="(item, index) in returnHistory"
                    :key="index"
                    class="mobile-card"
                >
                    <div class="mobile-card-header">
                        <div
                            class="mobile-product-image clickable"
                            @click="openImageModal(item)"
                        >
                            <!-- Use direct image path like in Production module -->
                            <img
                                :src="
                                    '/images/thumbnails/' +
                                    (item.img1 || 'default.jpg')
                                "
                                :alt="item.ProductTitle || 'Product'"
                                class="product-thumbnail clickable-image"
                                @error="handleImageError($event)"
                            />
                            <div
                                class="image-count-badge"
                                v-if="countAdditionalImages(item) > 0"
                            >
                                +{{ countAdditionalImages(item) }}
                            </div>
                        </div>
                        <div class="mobile-return-info">
                            <h3 class="mobile-return-title">
                                Return #{{ item.LPN || "N/A" }}
                            </h3>
                            <div class="mobile-return-date">
                                {{ formatDate(item.LPNDATE) }}
                            </div>
                        </div>
                    </div>

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">RT#:</span>
                            <span class="mobile-detail-value">{{
                                formatRTNumber(item.rtcounter, item.storename)
                            }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Serial:</span>
                            <span class="mobile-detail-value">{{
                                item.serialnumber
                            }}</span>
                        </div>
                        <div
                            v-if="item.serialnumberb"
                            class="mobile-detail-row"
                        >
                            <span class="mobile-detail-label"
                                >Second Serial:</span
                            >
                            <span class="mobile-detail-value">{{
                                item.serialnumberb
                            }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Location:</span>
                            <span class="mobile-detail-value">{{
                                item.warehouselocation || "Floor"
                            }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Status:</span>
                            <span
                                :class="[
                                    'mobile-detail-value',
                                    'status-badge',
                                    'status-' + item.returnstatus,
                                ]"
                            >
                                {{ formatStatus(item.returnstatus) }}
                            </span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Buyer:</span>
                            <span class="mobile-detail-value">{{
                                item.BuyerName ||
                                item.costumer_name ||
                                "Unknown"
                            }}</span>
                        </div>
                    </div>

                    <div class="mobile-card-actions">
                        <button
                            class="mobile-btn mobile-btn-details"
                            @click="viewReturnDetails(item)"
                        >
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    </div>
                </div>

                <div v-if="returnHistory.length === 0" class="mobile-card">
                    <div class="mobile-card-details">
                        <div class="mobile-detail-row text-center">
                            No return history found
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom pagination (also centered) -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="pagination">
                    <button
                        @click="prevPage"
                        :disabled="currentPage === 1"
                        class="pagination-button"
                    >
                        <i class="fas fa-chevron-left"></i> Back
                    </button>
                    <span class="pagination-info"
                        >Page {{ currentPage }} of {{ totalPages }}</span
                    >
                    <button
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        class="pagination-button"
                    >
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Image Modal -->
        <div v-if="showImageModal" class="image-modal">
            <div class="modal-overlay" @click="closeImageModal"></div>
            <div class="modal-content">
                <button class="close-button" @click="closeImageModal">
                    &times;
                </button>

                <div class="main-image-container">
                    <button
                        class="nav-button prev"
                        @click="prevImage"
                        v-if="modalImages.length > 1"
                    >
                        &lt;
                    </button>
                    <img
                        :src="modalImages[currentImageIndex]"
                        alt="Product Image"
                        class="modal-main-image"
                    />
                    <button
                        class="nav-button next"
                        @click="nextImage"
                        v-if="modalImages.length > 1"
                    >
                        &gt;
                    </button>
                </div>

                <div class="image-counter">
                    {{ currentImageIndex + 1 }} / {{ modalImages.length }}
                </div>

                <div class="thumbnails-container" v-if="modalImages.length > 1">
                    <div
                        v-for="(image, index) in modalImages"
                        :key="index"
                        class="modal-thumbnail"
                        :class="{ active: index === currentImageIndex }"
                        @click="currentImageIndex = index"
                    >
                        <img :src="image" :alt="`Thumbnail ${index + 1}`" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import returnsScanner from "./returnscanner.js";
export default returnsScanner;
</script>

<style scoped>
/* CSS for input with clear button */
.input-with-clear {
    position: relative;
    display: flex;
    flex: 1;
}

.input-with-clear input {
    flex: 1;
    padding-right: 30px;
    /* Make room for the clear button */
}

.clear-input-btn {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.clear-input-btn:hover {
    background-color: rgba(0, 0, 0, 0.1);
    color: #333;
}

/* Image styles */
.product-image-cell {
    width: 80px;
    height: 80px;
    padding: 5px;
}

.product-image-container {
    position: relative;
    width: 70px;
    height: 70px;
    overflow: hidden;
    border-radius: 4px;
    cursor: pointer;
    background-color: #f5f5f5;
    /* Light background for image container */
}

.product-thumbnail {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.2s;
}

.product-thumbnail:hover {
    transform: scale(1.05);
}

.image-count-badge {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background-color: rgba(0, 0, 0, 0.6);
    color: white;
    font-size: 11px;
    padding: 1px 5px;
    border-radius: 8px;
}

/* Mobile image styles */
.mobile-product-image {
    width: 60px;
    height: 60px;
    overflow: hidden;
    border-radius: 4px;
    position: relative;
    margin-right: 10px;
    background-color: #f5f5f5;
    /* Light background for image container */
}

/* Image modal styles */
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
    max-width: 90%;
    max-height: 90vh;
    z-index: 1001;
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    align-items: center;
}

.close-button {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 24px;
    background: none;
    border: none;
    cursor: pointer;
    z-index: 1002;
}

.main-image-container {
    position: relative;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
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
    background-color: rgba(255, 255, 255, 0.5);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nav-button.prev {
    left: 10px;
}

.nav-button.next {
    right: 10px;
}

.image-counter {
    margin: 10px 0;
    font-size: 14px;
}

.thumbnails-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
    max-width: 100%;
    justify-content: center;
}

.modal-thumbnail {
    width: 60px;
    height: 60px;
    border-radius: 4px;
    overflow: hidden;
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.modal-thumbnail.active {
    opacity: 1;
    border: 2px solid #0066cc;
}

.modal-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Highlight effect for the second serial input when populated automatically */
.highlight-input {
    background-color: #fff3cd !important;
    border-color: #ffecb5 !important;
    animation: pulse-highlight 1s ease-in-out;
}

@keyframes pulse-highlight {
    0% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
    }

    70% {
        box-shadow: 0 0 0 10px rgba(255, 193, 7, 0);
    }

    100% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0);
    }
}

.toggle-container {
    margin-bottom: 15px;
    display: flex;
    justify-content: flex-start;
}

.toggle-return-id {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background-color: #f0f0f0;
    border: 1px solid #d0d0d0;
    border-radius: 4px;
    font-weight: 500;
    color: #505050;
    transition: all 0.2s ease;
    cursor: pointer;
}

.toggle-return-id:hover {
    background-color: #e8e8e8;
    border-color: #c0c0c0;
}

.toggle-return-id:active {
    transform: scale(0.98);
}

.return-id-active {
    background-color: #e6f7ff;
    border-color: #91d5ff;
    color: #1890ff;
}

.return-id-active:hover {
    background-color: #d6f0ff;
    border-color: #69c0ff;
}
</style>
