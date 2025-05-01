<template>
    <div class="vue-container">
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

        <!-- Scanner Component (reusing your existing one) -->
        <scanner-component scanner-title="Return Scanner" storage-prefix="returnscanner" :enable-camera="true"
            :display-fields="['Serial', 'FNSKU', 'Reason']" :api-endpoint="'/api/returns/process-scan'"
            :hide-button="true" @process-scan="handleScanProcess" @hardware-scan="handleHardwareScan"
            @scanner-opened="handleScannerOpened" @scanner-closed="handleScannerClosed"
            @scanner-reset="handleScannerReset" @mode-changed="handleModeChange" ref="scanner">
            <!-- Define custom input fields for Return Scanner module -->
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
                        @keyup.enter="showManualInput ? focusNextField('reasonSelect') : processScan()"
                        ref="fnskuInput" />
                </div>

                <div class="input-group">
                    <label>Return Reason:</label>
                    <select v-model="returnReason" @change="handleReasonChange" ref="reasonSelect">
                        <option value="">Select Reason...</option>
                        <option value="damaged">Damaged</option>
                        <option value="defective">Defective</option>
                        <option value="wrong_item">Wrong Item</option>
                        <option value="customer_dissatisfied">Customer Dissatisfied</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="input-group" v-if="returnReason === 'other'">
                    <label>Specify Reason:</label>
                    <input type="text" v-model="otherReason" placeholder="Enter specific reason..."
                        @keyup.enter="processScan()" ref="otherReasonInput" />
                </div>

                <!-- Submit button (only in manual mode) -->
                <button v-if="showManualInput" @click="processScan()" class="submit-button">Submit</button>
            </template>
        </scanner-component>

        <!-- Returns History Table -->
        <div class="table-container">
            <h2>Recent Returns</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Serial Number</th>
                        <th>FNSKU</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in returnHistory" :key="index">
                        <td>{{ formatDate(item.created_at) }}</td>
                        <td>{{ item.serial_number }}</td>
                        <td>{{ item.fnsku }}</td>
                        <td>{{ item.reason }}</td>
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
                        <td colspan="6" class="text-center">No return history found</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
    import returnsScanner from "./returnscanner.js";
    export default returnsScanner;
</script>
