<template>
    <div class="vue-container return-module">
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-4">
            <TitlePage title="Return Scanner Module"
                subtitle="View and manage the status of all incoming customer product returns for processing." />
            <Button class="mx-4" @click="openScannerModal" severity="secondary" outlined label="Scan Items" size="small"
                icon="pi pi-barcode" />
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
                    <button type="button" class="toggle-return-id" @click="toggleReturnIdField"
                        :class="{ 'return-id-active': showReturnIdField }">
                        <i :class="[
                            'fas',
                            showReturnIdField ? 'fa-eye-slash' : 'fa-eye',
                        ]"></i>
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
                    <input type="text" v-model="returnId" placeholder="Enter Return ID..." @input="handleReturnIdInput"
                        @keyup.enter="
                            showManualInput
                                ? focusNextField('serialNumberInput')
                                : processScan()
                            " ref="returnIdInput" />
                </div>

                <div class="input-group">
                    <label>Serial Number:</label>
                    <input type="text" v-model="serialNumber" placeholder="Enter Serial Number..."
                        @input="handleSerialInput" @keyup.enter="
                            dualSerialProduct && showSecondSerialInput
                                ? focusNextField('secondSerialInput')
                                : showManualInput
                                    ? focusNextField('locationInput')
                                    : processScan()
                            " ref="serialNumberInput" />
                </div>

                <!-- Second Serial Number field (appears when a dual serial product is detected) -->
                <div class="input-group" v-if="dualSerialProduct && showSecondSerialInput">
                    <label>{{ secondSerialLabel || "Second Serial" }}:</label>
                    <div class="input-with-clear">
                        <input type="text" v-model="secondSerialNumber" placeholder="Enter Second Serial Number..."
                            @input="handleSecondSerialInput" @keyup.enter="
                                showManualInput
                                    ? focusNextField('locationInput')
                                    : processScan()
                                " ref="secondSerialInput" />
                        <button type="button" class="clear-input-btn" @click="hideSecondSerial"
                            title="Remove second serial">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- ✅ NEW: Multi-Serial Image Capture UI -->
                <div v-if="needsImageCapture" class="multi-serial-capture-container">
                    <!-- Progress Header -->
                    <div class="capture-progress-header">
                        <h4>📸 Capture Images for Each Serial</h4>
                        <div class="progress-info">
                            <span class="current-serial-badge">Serial {{ currentSerialIndex + 1 }} of {{ totalSerials }}</span>
                        </div>
                    </div>

                    <!-- Serial Cards List -->
                    <div class="serial-cards-wrapper">
                        <div 
                            v-for="(serial, index) in serialsToCapture" 
                            :key="index"
                            class="serial-card"
                            :class="{
                                'active': index === currentSerialIndex,
                                'completed': capturedImagesPerSerial[serial] && capturedImagesPerSerial[serial].length > 0,
                                'pending': index > currentSerialIndex
                            }"
                        >
                            <!-- Serial Number Display -->
                            <div class="serial-card-header">
                                <span class="serial-number">
                                    <i class="fas fa-tag"></i>
                                    Serial {{ index + 1 }}: <strong>{{ serial }}</strong>
                                </span>
                                <span class="serial-status">
                                    <i 
                                        v-if="capturedImagesPerSerial[serial] && capturedImagesPerSerial[serial].length > 0" 
                                        class="fas fa-check-circle text-success"
                                    ></i>
                                    <i 
                                        v-else-if="index === currentSerialIndex" 
                                        class="fas fa-camera text-primary"
                                    ></i>
                                    <i 
                                        v-else 
                                        class="fas fa-clock text-muted"
                                    ></i>
                                </span>
                            </div>

                            <!-- Image Count Display -->
                            <div class="serial-card-body" v-if="index === currentSerialIndex || (capturedImagesPerSerial[serial] && capturedImagesPerSerial[serial].length > 0)">
                                <div class="image-count">
                                    <i class="fas fa-images"></i>
                                    <span v-if="capturedImagesPerSerial[serial]">
                                        {{ capturedImagesPerSerial[serial].length }} image(s) captured
                                    </span>
                                    <span v-else class="text-muted">
                                        0 images - Click "Done" when finished
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Serial Capture Instructions -->
                    <div class="capture-instructions">
                        <div class="instruction-banner">
                            <i class="fas fa-info-circle"></i>
                            <span>Currently capturing for: <strong>{{ serialsToCapture[currentSerialIndex] }}</strong></span>
                        </div>
                        <p class="instruction-text">
                            Use the camera above to capture product images. Click "Done with this serial" when finished.
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="capture-actions">
                        <button 
                            @click="skipCurrentSerial" 
                            class="btn-skip"
                            v-if="currentSerialIndex < totalSerials - 1"
                        >
                            <i class="fas fa-forward"></i> Skip (No Images)
                        </button>
                        <button 
                            @click="finishCurrentSerialCapture" 
                            class="btn-done-serial"
                        >
                            <i class="fas fa-check"></i> 
                            {{ currentSerialIndex < totalSerials - 1 ? 'Done with this Serial' : 'Done - Continue to Location' }}
                        </button>
                    </div>
                </div>

                <div class="input-group">
                    <label>Location:</label>
                    <input type="text" v-model="locationInput" placeholder="Enter Location..."
                        @input="handleLocationInput" @keyup.enter="processScan()" ref="locationInput" />
                    <div class="container-type-hint">
                        Format: L###X (e.g., L123A) or 'Floor'
                    </div>
                </div>

                <!-- Submit button (only in manual mode) -->
                <button v-if="showManualInput" @click="processScan()" class="submit-button">
                    Submit
                </button>
            </template>
        </scanner-component>

        <!-- Returns History Table -->
        <AnimateDiv :delay="200" class=" px-4">
            <div class="search-container px-4">
                <fieldset class="d-flex align-items-center gap-1">
                    <label for="store-select">Store:</label>
                    <Select :options="storeOptions" optionLabel="label" optionValue="value" size="small"
                        class="select-form" v-model="selectedStore" @change="changeStore"
                        placeholder="Select a Store" />
                </fieldset>
            </div>
            <div class="desktop-view">
                <XDataTable :value="returnHistory" :columns="columns" :paginator="false" selectionMode="multiple"
                    dataKey="ProductID" :loading="loading">
                    <template #gallery="{ data }">
                        <div class="d-flex justify-content-center align-items-center">
                            <TableGallery :data="data" :openImageModal="openImageModal"
                                :handleImageError="handleImageError" :countAdditionalImages="countAdditionalImages" />
                        </div>
                    </template>
                    <template #date="{ data }">
                        <p>{{ formatDate(data.LPNDATE) }}</p>
                    </template>
                    <template #returnId="{ data }">
                        {{ data.LPN || "N/A" }}
                    </template>
                    <template #rtNumber="{ data }">
                        {{
                            formatRTNumber(
                                data.rtcounter,
                                data.storename
                            )
                        }}
                    </template>
                    <template #serialnumberb="{ data }">
                        <p>{{ data.serialnumberb || "-" }}</p>
                    </template>
                    <template #status="{ data }">
                        <Tag :value="data.returnstatus"
                            :severity="data.returnstatus === 'Returned' ? 'success' : 'secondary'" />
                    </template>
                    <template #buyer="{ data }">
                        <p>{{
                            data.BuyerName ||
                            data.costumer_name ||
                            "Unknown"
                            }}</p>
                    </template>
                    <template #actions="{ data }">
                        <div>
                            <Button label="More Details" severity="contrast" icon="pi pi-info-circle" variant="text"
                                class="text-primary" size="small" @click="handleShowDetailsModal(data)" />
                        </div>
                    </template>
                </XDataTable>
            </div>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <AnimateDiv :delay="200" class="mobile-view">
            <div class="mobile-cards">
                <div v-if="loading" class="loading-spinner-mobile">
                    <i class="fas fa-spinner fa-spin"></i>
                    Loading...
                </div>
                <div v-else-if="sortedInventory.length === 0" class="no-data-mobile">
                    No data found
                </div>
                <AnimateDiv v-else v-for="(item, index) in returnHistory" :key="index" class="mobile-card"
                    :delay="index * 100">
                    <div class="mobile-card-header">
                        <TableGallery :data="item" :openImageModal="openImageModal" :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages" />
                        <div class="mobile-return-info">
                            <h5 class="mobile-return-title">
                                Return {{
                                    formatRTNumber(
                                        item.rtcounter,
                                        item.storename
                                    )
                                }}
                            </h5>
                            <div class="mobile-return-date">
                                {{ formatDate(item.LPNDATE) }}
                            </div>
                        </div>
                    </div>
                    <Divider />
                    <div class="mobile-card-details" :style="{ fontSize: '14px' }">
                        <div class="mobile-detail-row">
                            <span class="fw-semibold">RT#:</span>
                            <span class="mobile-detail-value">{{
                                formatRTNumber(item.rtcounter, item.storename)
                                }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="fw-semibold">Serial:</span>
                            <span class="mobile-detail-value">{{
                                item.serialnumber
                                }}</span>
                        </div>
                        <div v-if="item.serialnumberb" class="mobile-detail-row">
                            <span class="fw-semibold">Second Serial:</span>
                            <span class="mobile-detail-value">{{
                                item.serialnumberb
                                }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="fw-semibold">Location:</span>
                            <span class="mobile-detail-value">{{
                                item.warehouselocation || "Floor"
                                }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="fw-semibold">Status:</span>
                            <span :class="[
                                'mobile-detail-value',
                                'status-badge',
                                'status-' + item.returnstatus,
                            ]">
                                {{ formatStatus(item.returnstatus) }}
                            </span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="fw-semibold">Buyer:</span>
                            <span class="mobile-detail-value">{{
                                item.BuyerName ||
                                item.costumer_name ||
                                "Unknown"
                                }}</span>
                        </div>
                    </div>
                    <Divider />
                    <div class="mobile-card-actions">
                        <Button @click="handleShowDetailsModal(item)" icon="pi pi-info-circle" label="More Details"
                            size="small" />
                    </div>
                </AnimateDiv>

                <div v-if="returnHistory.length === 0" class="mobile-card">
                    <div class="mobile-card-details">
                        <div class="mobile-detail-row text-center">
                            No return history found
                        </div>
                    </div>
                </div>
            </div>
        </AnimateDiv>

        <!---DETAILS MODAL--->
        <Dialog v-model:visible="viewDetailsModal" modal header="Product Details" class="view-details-dialog" :pt="{
            root: { class: 'mobile-fullscreen-dialog' }
        }" style="width: 50%;">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <Gallery :item="item" />
                </div>
                <div class="col-md-6">
                    <div class="details-container">
                        <div class="item-container">
                            <span>RT#: </span>
                            <span>{{ item.rtcounter
                                ? this.formatRTNumber(item.rtcounter, item.storename || "")
                                : "N/A" }}</span>
                        </div>
                        <div class="item-container">
                            <span>Return ID: </span>
                            <span>{{ item.LPN || "N/A" }}</span>
                        </div>
                        <div class="item-container">
                            <span>Return Date: </span>
                            <span>{{ formatDate(item.LPNDATE || null) }}</span>
                        </div>
                        <div class="item-container">
                            <span>Serial Number: </span>
                            <span>{{ item.serialnumber || "N/A" }}</span>
                        </div>
                        <div class="item-container">
                            <span>Second Serial Number: </span>
                            <span>{{ item.serialnumberb || "N/A" }}</span>
                        </div>
                        <div class="item-container">
                            <span>Location: </span>
                            <span>{{ item.warehouselocation || "Floor" }}</span>
                        </div>
                        <div class="item-container">
                            <span>Status: </span>
                            <Tag :value="item.returnstatus"
                                :severity="item.returnstatus === 'Returned' ? 'success' : 'secondary'" />
                        </div>
                        <div class="item-container">
                            <span>FNSKU: </span>
                            <span>{{ item.FNSKUviewer || "N/A" }}</span>
                        </div>
                        <div class="item-container">
                            <span>ASIN: </span>
                            <span>{{ item.ASINviewer || "N/A" }}</span>
                        </div>
                        <div class="item-container">
                            <span>Buyer: </span>
                            <span>{{ item.BuyerName || item.costumer_name || "Unknown" }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Bottom pagination -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <Select v-model="perPage" @change="changePerPage" :options="rowsPerPage" size="small"
                        optionLabel="label" optionValue="value" />
                </div>
                <div class="pagination">
                    <Button @click="prevPage" :disabled="currentPage === 1" class="pagination-button" label="Back"
                        icon="pi pi-angle-left" size="small" severity="info" />
                    <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                    <Button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button"
                        label="Next" icon="pi pi-angle-right" size="small" severity="info" iconPos="right" />
                </div>
            </div>
        </div>

        <!-- Image Modal -->
        <ViewImageModal v-model:visible="showImageModal" :title="'Images'" :imageList="modalImages" :basePath="basePath"
            :onImageErrorMain="handleImageError" :onThumbnailError="onThumbnailError" @close="closeImageModal" />

    </div>
</template>

<script>
import returnsScanner from "./returnscanner.js";
import TableGallery from '../../components/Gallery/tableGallery.vue'
import XDataTable from "../../components/DataTable/XDataTable.vue";
import Gallery from "../../components/Gallery/gallery.vue";
import { Button, Dialog, Divider, Select, Tag } from "primevue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import ViewImageModal from "../../components/ViewImageModal/ViewImageModal.vue";
import { ROWS_PER_PAGE } from "../../constant.js";

const TABLE_COLUMNS = [
    {
        header: "Gallery",
        slot: "gallery",
        style: { width: "4rem", minWidth: "4rem" }
    },
    {
        header: "Date",
        slot: "date",
        bodyStyle: "font-size: 14px",
        sortable: true
    },
    {
        header: "Return ID",
        slot: "returnId",
        bodyStyle: "font-size: 14px",
        sortable: true
    },
    {
        header: "RT#",
        slot: "rtNumber",
        bodyStyle: "font-size: 14px",
        sortable: true
    },
    {
        field: "serialnumber",
        header: "Serial Number",
        bodyStyle: "font-size: 14px",
        sortable: true
    },
    {
        field: "serialnumberb",
        header: "Second Serial Number",
        slot: "serialnumberb",
        bodyStyle: "font-size: 14px",
        sortable: true
    },
    {
        field: "returnstatus",
        header: "Status",
        slot: "status",
        bodyStyle: "font-size: 14px",
        sortable: true
    },
    {
        header: "Buyer",
        slot: "buyer",
        bodyStyle: "font-size: 14px",
        sortable: true
    }
]
export default {
    mixins: [returnsScanner],
    components: {
        XDataTable,
        TableGallery,
        Tag,
        Button,
        Dialog,
        Gallery,
        Divider,
        Select,
        TitlePage,
        AnimateDiv,
        ViewImageModal,
        Select
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            rowsPerPage: ROWS_PER_PAGE
        }
    },
    computed: {
        storeOptions() {
            const options = this.stores.map((store) => ({ value: store, label: store }))
            return [{ value: "", label: "All Stores" }, ...options]
        }
    }
};
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

.details-container {
    display: flex;
    flex-direction: column;
    gap: 14px;
    font-size: 14px !important;
}

.item-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgb(213, 213, 213);
    padding: 6px 0;
}

.search-container {
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 20px;
}

.select-form {
    width: 200px;
}

.view-details-dialog {
    width: 100% !important;
}

/* ✅ NEW: Multi-Serial Capture Styles */
.multi-serial-capture-container {
    margin: 20px 0;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.capture-progress-header {
    text-align: center;
    margin-bottom: 20px;
    color: white;
}

.capture-progress-header h4 {
    margin: 0 0 10px 0;
    font-size: 20px;
    font-weight: 600;
}

.progress-info {
    display: flex;
    justify-content: center;
    gap: 10px;
}

.current-serial-badge {
    background: rgba(255, 255, 255, 0.3);
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    backdrop-filter: blur(10px);
}

.serial-cards-wrapper {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.serial-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.serial-card.active {
    border-color: #4CAF50;
    box-shadow: 0 0 20px rgba(76, 175, 80, 0.3);
    transform: scale(1.02);
}

.serial-card.completed {
    background: #f1f8f4;
    border-color: #4CAF50;
}

.serial-card.pending {
    opacity: 0.6;
    background: #f5f5f5;
}

.serial-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.serial-number {
    font-size: 16px;
    color: #333;
}

.serial-number i {
    margin-right: 8px;
    color: #666;
}

.serial-number strong {
    color: #1976d2;
    font-family: 'Courier New', monospace;
}

.serial-status i {
    font-size: 24px;
}

.serial-card-body {
    padding-top: 10px;
    border-top: 1px solid #e0e0e0;
}

.image-count {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #666;
}

.image-count i {
    color: #2196F3;
}

.capture-instructions {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.instruction-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    font-size: 16px;
    color: #1976d2;
    font-weight: 500;
}

.instruction-banner i {
    font-size: 20px;
}

.instruction-text {
    margin: 0;
    font-size: 14px;
    color: #666;
    line-height: 1.5;
}

.capture-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.btn-skip {
    flex: 1;
    max-width: 200px;
    padding: 12px 24px;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.5);
    color: white;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-skip:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn-done-serial {
    flex: 2;
    max-width: 400px;
    padding: 14px 28px;
    background: #4CAF50;
    border: none;
    color: white;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
}

.btn-done-serial:hover {
    background: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(76, 175, 80, 0.5);
}

.btn-done-serial i {
    font-size: 18px;
}

.text-success {
    color: #4CAF50 !important;
}

.text-primary {
    color: #2196F3 !important;
}

.text-muted {
    color: #999 !important;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .multi-serial-capture-container {
        padding: 15px;
    }

    .serial-cards-wrapper {
        gap: 10px;
    }

    .serial-card {
        padding: 12px;
    }

    .capture-actions {
        flex-direction: column;
    }

    .btn-skip,
    .btn-done-serial {
        max-width: 100%;
    }
    
    .view-details-dialog {
        width: 95% !important;
    }
}

@media (min-width: 768px) {
    .view-details-dialog {
        width: 50% !important;
    }
}
</style>