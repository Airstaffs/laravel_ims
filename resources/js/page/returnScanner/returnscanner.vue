<template>
    <div class="vue-container return-module">
        <!-- Top header bar with blue background -->
        <div class="top-header">
            <div class="store-filter">
                <label for="store-select">Store:</label>
                <select id="store-select" v-model="selectedStore" @change="changeStore" class="store-select">
                    <option value="">All Stores</option>
                    <option v-for="store in stores" :key="store" :value="store">
                        {{ store }}
                    </option>
                </select>
            </div>

            <h1 class="module-title">Return Scanner</h1>

            <div class="header-buttons">
                <button class="btn scan-button" @click="openScannerModal">
                    <i class="fas fa-barcode"></i> Scan Items
                </button>
            </div>
        </div>

        <!-- Scanner Component (with hideButton prop to hide the scanner button) -->
        <scanner-component scanner-title="Return Scanner" storage-prefix="returnscanner" :enable-camera="true"
            :display-fields="['ReturnID', 'Serial', 'Location']" :api-endpoint="'/api/returns/process-scan'"
            :hide-button="true" @process-scan="handleScanProcess" @hardware-scan="handleHardwareScan"
            @scanner-opened="handleScannerOpened" @scanner-closed="handleScannerClosed"
            @scanner-reset="handleScannerReset" @mode-changed="handleModeChange" ref="scanner">
            <!-- Define custom input fields for Return Scanner module -->
            <template #input-fields>
                <!-- ReturnID toggle button -->
                <div class="toggle-container">
                    <button type="button" class="toggle-return-id" @click="toggleReturnIdField">
                        {{ showReturnIdField ? 'Hide Return ID' : 'Show Return ID' }}
                    </button>
                </div>

                <!-- ReturnID field (optional) -->
                <div class="input-group" v-if="showReturnIdField">
                    <label>Return ID:</label>
                    <input type="text" v-model="returnId" placeholder="Enter Return ID..."
                        @input="handleReturnIdInput"
                        @keyup.enter="showManualInput ? focusNextField('serialNumberInput') : processScan()"
                        ref="returnIdInput" />
                </div>

                <div class="input-group">
                    <label>Serial Number:</label>
                    <input type="text" v-model="serialNumber" placeholder="Enter Serial Number..."
                        @input="handleSerialInput"
                        @keyup.enter="dualSerialProduct ? focusNextField('secondSerialInput') : (showManualInput ? focusNextField('locationInput') : processScan())"
                        ref="serialNumberInput" />
                </div>

                <!-- Second Serial Number field (appears when a dual serial product is detected) -->
                <div class="input-group" v-if="dualSerialProduct">
                    <label>{{ secondSerialLabel || 'Second Serial' }}:</label>
                    <input type="text" v-model="secondSerialNumber" placeholder="Enter Second Serial Number..."
                        @input="handleSecondSerialInput"
                        @keyup.enter="showManualInput ? focusNextField('locationInput') : processScan()"
                        ref="secondSerialInput" />
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

        <!-- Pagination with centered layout -->
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

                <div class="per-page-selector">
                    <select v-model="perPage" @change="changePerPage" class="per-page-select">
                        <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
                            {{ option }} per page
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Returns History Table -->
        <div class="table-container desktop-view">
            <h2>Recent Returns</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Return ID</th>
                        <th>Serial</th>
                        <th v-if="true">Second Serial</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in returnHistory" :key="index">
                        <td>{{ formatDate(item.created_at) }}</td>
                        <td>{{ item.return_id || 'N/A' }}</td>
                        <td>{{ item.serial_number }}</td>
                        <td v-if="true">{{ item.second_serial || '-' }}</td>
                        <td>{{ item.location || 'Floor' }}</td>
                        <td>
                            <span :class="'status-badge status-' + item.status">
                                {{ formatStatus(item.status) }}
                            </span>
                        </td>
                        <td>
                            <button class="btn-details" @click="viewReturnDetails(item)">
                                <i class="fas fa-info-circle"></i> Details
                            </button>
                        </td>
                    </tr>
                    <tr v-if="returnHistory.length === 0">
                        <td colspan="7" class="text-center">No return history found</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <div class="mobile-cards">
                <div v-for="(item, index) in returnHistory" :key="index" class="mobile-card">
                    <div class="mobile-card-header">
                        <div class="mobile-return-info">
                            <h3 class="mobile-return-title">Return #{{ item.return_id || 'N/A' }}</h3>
                            <div class="mobile-return-date">{{ formatDate(item.created_at) }}</div>
                        </div>
                    </div>

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Serial:</span>
                            <span class="mobile-detail-value">{{ item.serial_number }}</span>
                        </div>
                        <div v-if="item.second_serial" class="mobile-detail-row">
                            <span class="mobile-detail-label">Second Serial:</span>
                            <span class="mobile-detail-value">{{ item.second_serial }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Location:</span>
                            <span class="mobile-detail-value">{{ item.location || 'Floor' }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Status:</span>
                            <span :class="['mobile-detail-value', 'status-badge', 'status-' + item.status]">
                                {{ formatStatus(item.status) }}
                            </span>
                        </div>
                    </div>

                    <div class="mobile-card-actions">
                        <button class="mobile-btn mobile-btn-details" @click="viewReturnDetails(item)">
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
    </div>
</template>

<script>
    import returnsScanner from "./returnscanner.js";
    export default returnsScanner;
</script>
