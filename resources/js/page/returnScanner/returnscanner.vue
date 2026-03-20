<template>
    <div class="vue-container return-module">
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-4">
            <TitlePage
                title="Return Scanner Module"
                subtitle="View and manage the status of all incoming customer product returns for processing."
            />

             <div class="d-flex align-items-center gap-2">
                <Button
                    @click="openScannerModal"
                    severity="secondary"
                    outlined
                    label="Scan Items"
                    size="small"
                    icon="pi pi-barcode"
                />

                <Button
                    label="Amazon Returns"
                    icon="pi pi-replay"
                    class="p-button-warning"
                    @click="openAmazonReturnsModal"
                />
            </div>
        </div>

        <scanner-component scanner-title="Return Scanner" storage-prefix="returnscanner" :enable-camera="true"
            :disableImagePreview="true" module="returnscanner" :display-fields="['ReturnID', 'Serial', 'Location']"
            :api-endpoint="'/api/returns/process-scan'" :hide-button="true" @process-scan="handleScanProcess"
            @hardware-scan="handleHardwareScan" @scanner-opened="handleScannerOpened"
            @scanner-closed="handleScannerClosed" @scanner-reset="handleScannerReset" @mode-changed="handleModeChange"
            ref="scanner">

            <template #input-fields>
                <!-- ReturnID toggle -->
                <div class="toggle-container">
                    <button type="button" class="toggle-return-id" @click="toggleReturnIdField"
                        :class="{ 'return-id-active': showReturnIdField }">
                        <i :class="['fas', showReturnIdField ? 'fa-eye-slash' : 'fa-eye']"></i>
                        {{ showReturnIdField ? "Hide Return ID" : "Show Return ID" }}
                    </button>
                </div>

                <!-- ReturnID field -->
              <div class="input-group" v-if="showReturnIdField">
                    <label>Return ID:</label>
                    <div class="input-with-action">
                        <input
                            type="text"
                            v-model="returnId"
                            placeholder="Enter Return ID (Amazon Order ID)..."
                            @input="handleReturnIdInput"
                            @keyup.enter="focusNextField('serialNumberInput')"
                            ref="returnIdInput"
                            :class="{
                                'input-complete': returnIdValidated,
                                'input-duplicate': returnIdNotFound
                            }"
                        />
                        <!-- Spinner -->
                        <span v-if="returnIdValidating" class="return-id-status">
                            <i class="fas fa-spinner fa-spin"></i>
                        </span>
                        <!-- Valid -->
                        <span v-else-if="returnIdValidated" class="return-id-status return-id-ok">
                            <i class="fas fa-check-circle"></i>
                        </span>
                        <!-- Not found -->
                        <span v-else-if="returnIdNotFound" class="return-id-status return-id-warn">
                            <i class="fas fa-exclamation-triangle"></i>
                        </span>
                    </div>

                    <!-- Info card shown when Return ID is found -->
                  <div v-if="returnIdValidated && returnIdInfo" class="return-id-info-card">

                        <!-- FBA badge -->
                        <div v-if="returnType === 'FBA'" class="return-id-type-badge fba-badge">
                            <i class="fas fa-warehouse"></i> FBA Return
                        </div>
                        <!-- FBM badge -->
                        <div v-else-if="returnType === 'FBM'" class="return-id-type-badge fbm-badge">
                            <i class="fas fa-box"></i> FBM Return
                        </div>

                        <!-- Buyer (FBM only — FBA has no buyer name) -->
                        <div v-if="returnIdInfo.buyerName" class="return-id-info-row">
                            <i class="fas fa-user"></i>
                            <span><strong>Buyer:</strong> {{ returnIdInfo.buyerName }}</span>
                        </div>

                        <!-- Item name -->
                        <div v-if="returnIdInfo.itemName" class="return-id-info-row">
                            <i class="fas fa-box"></i>
                            <span><strong>Item:</strong> {{ returnIdInfo.itemName }}</span>
                        </div>

                        <!-- Shipped serial (FBM only) -->
                        <div v-if="returnIdInfo.shippedSerial" class="return-id-info-row">
                            <i class="fas fa-barcode"></i>
                            <span>
                                <strong>Shipped Serial:</strong>
                                <code class="shipped-serial-badge">{{ returnIdInfo.shippedSerial }}</code>
                            </span>
                        </div>

                        <!-- FBA: no shipped serial — show note instead -->
                        <div v-else-if="returnType === 'FBA'" class="return-id-info-row return-id-fba-note">
                            <i class="fas fa-info-circle"></i>
                            <span>Amazon fulfilled — shipped serial unknown. Any unrecognised serial will be flagged as switcheru.</span>
                        </div>

                        <!-- ASIN -->
                        <div v-if="returnIdInfo.asin" class="return-id-info-row">
                            <i class="fas fa-tag"></i>
                            <span><strong>ASIN:</strong> {{ returnIdInfo.asin }}</span>
                        </div>

                        <!-- Return reason -->
                        <div v-if="returnIdInfo.returnReason" class="return-id-info-row">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><strong>Reason:</strong> {{ returnIdInfo.returnReason }}</span>
                        </div>

                        <!-- FBM: tracking used to look up, show canonical RMA -->
                        <div v-if="returnType === 'FBM' && returnIdInfo.trackingId && returnIdInfo.trackingId !== canonicalReturnId"
                            class="return-id-info-row">
                            <i class="fas fa-truck"></i>
                            <span>
                                <strong>Tracking:</strong> {{ returnIdInfo.trackingId }}
                                <em style="font-size:11px;color:#888;"> → RMA: {{ canonicalReturnId }}</em>
                            </span>
                        </div>
                    </div>

                    <!-- Warning when Return ID not found -->
                    <div v-if="returnIdNotFound" class="return-id-warn-card">
                        <i class="fas fa-exclamation-triangle"></i>
                        Return ID / Tracking not found — scan will proceed without order validation.
                    </div>

                </div>

                <!-- Multi-Serial Badge -->
                <div v-if="isMultiSerial && totalSerials > 1" class="multi-serial-badge">
                    <i class="fas fa-boxes"></i>
                    <span>{{ totalSerials }}-Pack Product Detected</span>
                </div>

                <!-- ========== INPUT MODE (currentCaptureStep === 0) ========== -->
                <template v-if="currentCaptureStep === 0">

                    <!-- SERIAL 1 INPUT -->
                    <div class="input-group">
                        <label>Serial Number: <span v-if="serial1CaptureComplete" class="capture-done-badge">✓ {{
                            capturedImagesForSerial1.length }} imgs</span></label>
                        <div class="input-with-action">
                            <input type="text" v-model="serialNumber" placeholder="Enter Serial Number..."
                                @input="handleSerialInput" @keyup.enter="serialNumber ? proceedToImageCapture(1) : null"
                                ref="serialNumberInput" :class="{ 'input-complete': serial1CaptureComplete }" />
                            <button v-if="serialNumber && !serial1CaptureComplete" type="button"
                                class="btn-capture-trigger" @click="proceedToImageCapture(1)" title="Capture images">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                    </div>

                    <!-- SERIAL 2 INPUT -->
                    <div class="input-group" v-if="showSecondSerialInput">
                        <label>
                            {{ secondSerialLabel }}:
                            <span v-if="serial2CaptureComplete" class="capture-done-badge">✓ {{
                                capturedImagesForSerial2.length }} imgs</span>
                            <span v-if="isDuplicateSerial(secondSerialNumber, 2)" class="serial-duplicate-badge">⚠
                                Duplicate Serial!</span>
                        </label>
                        <div class="input-with-action">
                            <input type="text" v-model="secondSerialNumber" placeholder="Scan or enter second serial..."
                                @input="handleSecondSerialInput"
                                @keyup.enter="secondSerialNumber ? proceedToImageCapture(2) : null"
                                ref="secondSerialInput" :class="{
                                    'input-complete': serial2CaptureComplete,
                                    'highlight-input': !serial2CaptureComplete && !isDuplicateSerial(secondSerialNumber, 2),
                                    'input-duplicate': isDuplicateSerial(secondSerialNumber, 2)
                                }" />
                            <button v-if="secondSerialNumber && !serial2CaptureComplete" type="button"
                                class="btn-capture-trigger" @click="proceedToImageCapture(2)" title="Capture images">
                                <i class="fas fa-camera"></i>
                            </button>
                            <button type="button" class="btn-remove-serial" @click="hideSecondSerial"
                                title="Don't return this serial">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div v-if="isDuplicateSerial(secondSerialNumber, 2)" class="duplicate-warning-row">
                            <i class="fas fa-exclamation-circle"></i> This serial was already scanned. Please re-scan
                            the correct one.
                        </div>
                    </div>

                    <!-- SERIAL 3 INPUT — replace your existing block -->
                    <div class="input-group" v-if="showThirdSerialInput">
                        <label>
                            {{ thirdSerialLabel }}:
                            <span v-if="serial3CaptureComplete" class="capture-done-badge">✓ {{
                                capturedImagesForSerial3.length }} imgs</span>
                            <span v-if="isDuplicateSerial(thirdSerialNumber, 3)" class="serial-duplicate-badge">⚠
                                Duplicate Serial!</span>
                        </label>
                        <div class="input-with-action">
                            <input type="text" v-model="thirdSerialNumber" placeholder="Scan or enter third serial..."
                                @input="handleThirdSerialInput"
                                @keyup.enter="thirdSerialNumber ? proceedToImageCapture(3) : null"
                                ref="thirdSerialInput" :class="{
                                    'input-complete': serial3CaptureComplete,
                                    'input-duplicate': isDuplicateSerial(thirdSerialNumber, 3)
                                }" />
                            <button v-if="thirdSerialNumber && !serial3CaptureComplete" type="button"
                                class="btn-capture-trigger" @click="proceedToImageCapture(3)" title="Capture images">
                                <i class="fas fa-camera"></i>
                            </button>
                            <button type="button" class="btn-remove-serial" @click="hideThirdSerial"
                                title="Don't return this serial">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div v-if="isDuplicateSerial(thirdSerialNumber, 3)" class="duplicate-warning-row">
                            <i class="fas fa-exclamation-circle"></i> This serial was already scanned. Please re-scan
                            the correct one.
                        </div>
                    </div>

                    <!-- SERIAL 4 INPUT — replace your existing block -->
                    <div class="input-group" v-if="showFourthSerialInput">
                        <label>
                            {{ fourthSerialLabel }}:
                            <span v-if="serial4CaptureComplete" class="capture-done-badge">✓ {{
                                capturedImagesForSerial4.length }} imgs</span>
                            <span v-if="isDuplicateSerial(fourthSerialNumber, 4)" class="serial-duplicate-badge">⚠
                                Duplicate Serial!</span>
                        </label>
                        <div class="input-with-action">
                            <input type="text" v-model="fourthSerialNumber" placeholder="Scan or enter fourth serial..."
                                @input="handleFourthSerialInput"
                                @keyup.enter="fourthSerialNumber ? proceedToImageCapture(4) : null"
                                ref="fourthSerialInput" :class="{
                                    'input-complete': serial4CaptureComplete,
                                    'input-duplicate': isDuplicateSerial(fourthSerialNumber, 4)
                                }" />
                            <button v-if="fourthSerialNumber && !serial4CaptureComplete" type="button"
                                class="btn-capture-trigger" @click="proceedToImageCapture(4)" title="Capture images">
                                <i class="fas fa-camera"></i>
                            </button>
                            <button type="button" class="btn-remove-serial" @click="hideFourthSerial"
                                title="Don't return this serial">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div v-if="isDuplicateSerial(fourthSerialNumber, 4)" class="duplicate-warning-row">
                            <i class="fas fa-exclamation-circle"></i> This serial was already scanned. Please re-scan
                            the correct one.
                        </div>
                    </div>

                    <!-- LOCATION INPUT -->
                    <div class="input-group" v-if="serialNumber">
                        <label>Location:</label>
                        <input type="text" v-model="locationInput" placeholder="Enter Location..."
                            @input="handleLocationInput" @keyup.enter="processScan()" ref="locationInput" />
                        <div class="container-type-hint">Format: L###X (e.g., L123A) or 'Floor' or 'L800G'</div>
                    </div>

                    <!-- Serials Summary -->
                    <div class="serials-summary" v-if="getActiveSerials().length > 0">
                        <strong>Serials to return ({{ getActiveSerials().length }}):</strong>
                        <div class="serial-chips">
                            <span class="serial-chip" v-for="s in getActiveSerials()" :key="s">{{ s }}</span>
                        </div>
                    </div>

                    <!-- Submit button -->
                    <button v-if="locationInput && serialNumber" @click="processScan()" class="submit-button">
                        <i class="fas fa-paper-plane"></i> Submit Return
                    </button>
                </template>

                <!-- ========== CAPTURE MODE ========== -->
                <div v-if="currentCaptureStep > 0" class="image-capture-section">
                    <div class="capture-header">
                        <h4>📸 Capture for Serial {{ currentCaptureStep }}:
                            <strong>{{ currentCaptureStep === 1 ? serialNumber : currentCaptureStep === 2 ?
                                secondSerialNumber :
                                currentCaptureStep === 3 ? thirdSerialNumber : fourthSerialNumber }}</strong>
                        </h4>
                        <p class="capture-instruction">Take up to 12 photos (optional)</p>
                    </div>
                    <div class="capture-count-badge">
                        {{ $refs.scanner?.capturedImages?.length || 0 }} / 12 images
                    </div>
                    <div class="capture-actions">
                        <button @click="skipImageCapture(currentCaptureStep)" class="btn-skip-images">
                            <i class="fas fa-forward"></i> Skip Images
                        </button>
                        <button @click="finishImageCapture(currentCaptureStep)" class="btn-done-images">
                            <i class="fas fa-check"></i> Done
                        </button>
                    </div>
                </div>
            </template>
        </scanner-component>

        <!-- Returns History Table -->
        <AnimateDiv :delay="200" class="px-4">
            <div class="search-container px-4">
                <fieldset class="d-flex align-items-center gap-1">
                    <label for="store-select">Store:</label>
                    <Select :options="storeOptions" optionLabel="label" optionValue="value" size="small"
                        class="select-form" v-model="selectedStore" @change="changeStore"
                        placeholder="Select a Store" />
                </fieldset>
            </div>
            <XDataTable :value="returnHistory" :loading="loading" :columns="columns" :paginator="false"
                tableClass="desktop-view" selectionMode="multiple" dataKey="ProductID">
                <template #gallery="{ data }">
                    <div class="d-flex justify-content-center align-items-center">
                        <div v-if="data.capturedImages && data.capturedImages.capturedimg1"
                            class="gallery-thumbnail position-relative" @click="openImageModal(data)"
                            style="cursor: pointer">
                            <img :src="`/images/product_images/${data.company || 'Airstaffs'}/${data.capturedImages.capturedimg1}`"
                                :alt="getDisplayTitle(data)"
                                style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                @error="handleImageError" />
                            <span v-if="countCapturedImages(data) > 1"
                                class="position-absolute bg-primary text-white rounded-circle"
                                style="top: -5px; right: -5px; min-width: 20px; height: 20px; font-size: 0.65rem; display: flex; align-items: center; justify-content: center; padding: 0 4px;">
                                +{{ countCapturedImages(data) - 1 }}
                            </span>
                        </div>
                        <TableGallery v-else :data="data" :openImageModal="openImageModal"
                            :handleImageError="handleImageError" :countAdditionalImages="countAllImages" />
                    </div>
                </template>
                <template #date="{ data }">
                    <p>{{ formatDate(data.LPNDATE) }}</p>
                </template>
                <template #returnId="{ data }">{{ data.LPN || "N/A" }}</template>
                <template #rtNumber="{ data }">{{ formatRTNumber(data.rtcounter, data.storename) }}</template>
                <template #serialnumberb="{ data }">
                    <p>{{ data.serialnumberb || "-" }}</p>
                </template>
                <template #status="{ data }">
                    <Tag :value="data.returnstatus"
                        :severity="data.returnstatus === 'Returned' ? 'success' : 'secondary'" />
                </template>
                <template #buyer="{ data }">
                    <p>{{ data.BuyerName || data.costumer_name || "Unknown" }}</p>
                </template>
                <template #reason="{ data }">
                    <p>{{ data.REASON || "N/A" }}</p>
                </template>
                <template #actions="{ data }">
                    <Button label="More Details" severity="contrast" icon="pi pi-info-circle" variant="text"
                        class="text-primary" size="small" @click="handleShowDetailsModal(data)" />
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <AnimateDiv :delay="200" class="mobile-view">
            <div class="mobile-cards">
                <div v-if="loading" class="loading-spinner-mobile"><i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
                <div v-else-if="sortedInventory.length === 0" class="no-data-mobile">No data found</div>
                <AnimateDiv v-else v-for="(item, index) in returnHistory" :key="index" class="mobile-card"
                    :delay="index * 100">
                    <div class="mobile-card-header">
                        <div class="mobile-product-image clickable">
                            <div v-if="item.capturedImages && item.capturedImages.capturedimg1"
                                class="gallery-thumbnail position-relative" @click="openImageModal(item)"
                                style="cursor: pointer">
                                <img :src="`/images/product_images/${item.company || 'Airstaffs'}/${item.capturedImages.capturedimg1}`"
                                    :alt="getDisplayTitle(item)" class="product-thumbnail clickable-image"
                                    @error="handleImageError" />
                                <div class="image-count-badge" v-if="countCapturedImages(item) > 1">+{{
                                    countCapturedImages(item) - 1 }}</div>
                            </div>
                            <div v-else @click="openImageModal(item)" style="cursor: pointer">
                                <img :src="'/images/thumbnails/' + item.img1" :alt="getDisplayTitle(item)"
                                    class="product-thumbnail clickable-image" @error="handleImageError($event)" />
                                <div class="image-count-badge" v-if="countAllImages(item) > 0">+{{ countAllImages(item)
                                }}</div>
                            </div>
                        </div>
                        <div class="mobile-return-info">
                            <h5 class="mobile-return-title">Return {{ formatRTNumber(item.rtcounter, item.storename) }}
                            </h5>
                            <div class="mobile-return-date">{{ formatDate(item.LPNDATE) }}</div>
                        </div>
                    </div>
                    <Divider />
                    <div class="mobile-card-details" :style="{ fontSize: '14px' }">
                        <div class="mobile-detail-row"><span class="fw-semibold">RT#:</span><span
                                class="mobile-detail-value">{{
                                    formatRTNumber(item.rtcounter, item.storename) }}</span></div>
                        <div class="mobile-detail-row"><span class="fw-semibold">Serial:</span><span
                                class="mobile-detail-value">{{ item.serialnumber }}</span></div>
                        <div v-if="item.serialnumberb" class="mobile-detail-row"><span class="fw-semibold">Second
                                Serial:</span><span class="mobile-detail-value">{{ item.serialnumberb }}</span></div>
                        <div class="mobile-detail-row"><span class="fw-semibold">Location:</span><span
                                class="mobile-detail-value">{{ item.warehouselocation || "Floor" }}</span></div>
                        <div class="mobile-detail-row"><span class="fw-semibold">Status:</span><span
                                :class="['mobile-detail-value', 'status-badge', 'status-' + item.returnstatus]">{{
                                    formatStatus(item.returnstatus) }}</span></div>
                        <div class="mobile-detail-row"><span class="fw-semibold">Buyer:</span><span
                                class="mobile-detail-value">{{ item.BuyerName || item.costumer_name || "Unknown"
                                }}</span></div>
                        <div class="mobile-detail-row"><span class="fw-semibold">Return Reason:</span><span
                                class="mobile-detail-value">{{ item.REASON || "N/A"
                                }}</span></div>

                    </div>
                    <Divider />
                    <div class="mobile-card-actions">
                        <Button @click="handleShowDetailsModal(item)" icon="pi pi-info-circle" label="More Details"
                            size="small" />
                    </div>
                </AnimateDiv>
                <div v-if="returnHistory.length === 0" class="mobile-card">
                    <div class="mobile-card-details">
                        <div class="mobile-detail-row text-center">No return history found</div>
                    </div>
                </div>
            </div>
        </AnimateDiv>

        <!-- Details Modal -->
        <Dialog v-model:visible="viewDetailsModal" modal header="Product Details" class="view-details-dialog"
            :pt="{ root: { class: 'mobile-fullscreen-dialog' } }" style="width: 50%">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <Gallery :item="item" />
                </div>
                <div class="col-md-6">
                    <div class="details-container">
                        <div class="item-container"><span>RT#: </span><span>{{ item.rtcounter ?
                            formatRTNumber(item.rtcounter,
                                item.storename || "") : "N/A" }}</span></div>
                        <div class="item-container"><span>Return ID: </span><span>{{ item.LPN || "N/A" }}</span></div>
                        <div class="item-container"><span>Return Date: </span><span>{{ formatDate(item.LPNDATE || null)
                        }}</span></div>
                        <div class="item-container"><span>Serial Number: </span><span>{{ item.serialnumber || "N/A"
                        }}</span>
                        </div>
                        <div class="item-container"><span>Second Serial: </span><span>{{ item.serialnumberb || "N/A"
                        }}</span>
                        </div>
                        <div class="item-container"><span>Location: </span><span>{{ item.warehouselocation || "Floor"
                        }}</span>
                        </div>
                        <div class="item-container"><span>Status: </span>
                            <Tag :value="item.returnstatus"
                                :severity="item.returnstatus === 'Returned' ? 'success' : 'secondary'" />
                        </div>
                        <div class="item-container"><span>FNSKU: </span><span>{{ item.FNSKUviewer || "N/A" }}</span>
                        </div>
                        <div class="item-container"><span>ASIN: </span><span>{{ item.ASINviewer || "N/A" }}</span></div>
                        <div class="item-container"><span>Buyer: </span><span>{{ item.BuyerName || item.costumer_name ||
                            "Unknown" }}</span></div>
                        <div class="item-container"><span>Return Reason: </span><span>{{ item.REASON || "N/A" }}</span></div>

                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Pagination -->
        <Paginator :first="first" :rows="perPage" :total-records="totalRecords" :rows-per-page-options="[10, 20, 50]"
            template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords}" class="small-paginator"
            @page="onPageChange" />

        <ViewImageGalleryModal :showImageModal="showImageModal" :closeImageModal="closeImageModal"
            :ProductTitle="ProductTitle" :regularImages="regularImages" :capturedImages="capturedImages"
            :handleImageError="handleImageError" />


    </div>
            <AmazonReturnsModal
    v-model:visible="showAmazonReturnsModal"
/>

    <ReturnReasonPrintModal
    :show="showReturnReasonPrintModal"
    :returnInfo="pendingReturnPrintInfo"
    :availablePrinters="availablePrinters"
    :loadingPrinters="loadingPrinters"
    :rememberedPrinterId="rememberedPrinterId"
    :singlePrinters="singlePrinters"
    :marriedPrinterGroups="marriedPrinterGroups"
    @skip="closeReturnReasonPrintModal(); submitPendingScan()"
    @print="onReturnReasonPrint($event).then(() => submitPendingScan())"
    @remember-printer="saveReturnPrinterPreference"
/>
   
</template>

<script>
import returnsScanner from "./returnscanner.js";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import Gallery from "../../components/Gallery/gallery.vue";
import { Button, Dialog, Divider, Select, Tag, Paginator } from "primevue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
import ReturnReasonPrintModal from "./modals/ReturnReasonPrintModal.vue";


const TABLE_COLUMNS = [
    { header: "Gallery", slot: "gallery", style: { width: "4rem", minWidth: "4rem" } },
    { header: "Date", slot: "date", bodyStyle: "font-size: 14px", sortable: true },
    { header: "Return ID", slot: "returnId", bodyStyle: "font-size: 14px", sortable: true },
    { header: "RT#", slot: "rtNumber", bodyStyle: "font-size: 14px", sortable: true },
    { field: "serialnumber", header: "Serial Number", bodyStyle: "font-size: 14px", sortable: true },
    { field: "serialnumberb", header: "Second Serial", slot: "serialnumberb", bodyStyle: "font-size: 14px", sortable: true },
    { field: "returnstatus", header: "Status", slot: "status", bodyStyle: "font-size: 14px", sortable: true },
    { header: "Buyer", slot: "buyer", bodyStyle: "font-size: 14px", sortable: true },
    {field: 'REASON',header: 'Return Reason',slot: 'reason', sortable: true },

    //  { header: "Actions", slot: "actions", bodyStyle: "font-size: 14px" },
];

export default {
    mixins: [returnsScanner],
    components: { XDataTable, TableGallery, Tag, Button, Dialog, Gallery, Divider, Select, TitlePage, AnimateDiv, ViewImageGalleryModal, Paginator,ReturnReasonPrintModal },
    data() {
        return { columns: TABLE_COLUMNS, rowsPerPage: ROWS_PER_PAGE };
    },
    computed: {
        storeOptions() {
            return [{ value: "", label: "All Stores" }, ...this.stores.map(s => ({ value: s, label: s }))];
        },
    },
    methods: {
        transformDataForGallery(data) {
            if (!data) return {};
            if (data.capturedImages?.capturedimg1) {
                const t = { ...data };
                for (let i = 1; i <= 12; i++) t[`img${i}`] = data.capturedImages[`capturedimg${i}`] ? `/images/product_images/Airstaffs/${data.capturedImages[`capturedimg${i}`]}` : null;
                for (let i = 13; i <= 15; i++) t[`img${i}`] = null;
                return t;
            }
            return data;
        },
    },
};
</script>

<style scoped>
/* ========== TOGGLE & INPUT STYLES ========== */
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
    background: #f0f0f0;
    border: 1px solid #d0d0d0;
    border-radius: 4px;
    font-weight: 500;
    color: #505050;
    cursor: pointer;
    transition: all 0.2s;
}

.toggle-return-id:hover {
    background: #e8e8e8;
    border-color: #c0c0c0;
}

.toggle-return-id:active {
    transform: scale(0.98);
}

.return-id-active {
    background: #e6f7ff;
    border-color: #91d5ff;
    color: #1890ff;
}

.return-id-active:hover {
    background: #d6f0ff;
    border-color: #69c0ff;
}

/* ========== MULTI-SERIAL BADGE ========== */
.multi-serial-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

/* ========== INPUT WITH ACTION BUTTONS ========== */
.input-with-action {
    display: flex;
    gap: 8px;
    align-items: center;
}

.input-with-action input {
    flex: 1;
}

.btn-capture-trigger {
    background: #2196f3;
    color: white;
    border: none;
    padding: 10px 14px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    white-space: nowrap;
}

.btn-capture-trigger:hover {
    background: #1976d2;
    transform: scale(1.05);
}

.btn-remove-serial {
    background: #ff5252;
    color: white;
    border: none;
    padding: 10px 12px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.btn-remove-serial:hover {
    background: #ff1744;
    transform: scale(1.05);
}

/* ========== INPUT STATES ========== */
.input-complete {
    background: #e8f5e9 !important;
    border-color: #4caf50 !important;
}

.capture-done-badge {
    background: #4caf50;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    margin-left: 8px;
    white-space: nowrap;
}

.highlight-input {
    background: #fff3cd !important;
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

/* ========== IMAGE CAPTURE SECTION ========== */
.image-capture-section {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    margin: 15px 0;
}

.capture-header {
    margin-bottom: 15px;
}

.capture-header h4 {
    margin: 0 0 5px 0;
    color: #333;
    font-size: 18px;
}

.capture-instruction {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.capture-count-badge {
    background: #2196f3;
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    display: inline-block;
    margin-bottom: 15px;
    font-size: 14px;
    font-weight: 500;
}

.capture-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-skip-images {
    padding: 12px 24px;
    background: #f5f5f5;
    border: 2px solid #ddd;
    color: #666;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-skip-images:hover {
    background: #eee;
    border-color: #ccc;
    transform: translateY(-1px);
}

.btn-done-images {
    padding: 12px 24px;
    background: #4caf50;
    border: none;
    color: white;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-done-images:hover {
    background: #45a049;
    transform: translateY(-1px);
}

/* ========== SERIALS SUMMARY ========== */
.serials-summary {
    margin: 15px 0;
    padding: 12px;
    background: #e3f2fd;
    border-radius: 8px;
    border: 1px solid #bbdefb;
}

.serials-summary strong {
    display: block;
    margin-bottom: 8px;
    color: #1565c0;
}

.serial-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.serial-chip {
    background: #1976d2;
    color: white;
    padding: 4px 12px;
    border-radius: 14px;
    font-size: 12px;
    font-family: monospace;
}

/* ========== SUBMIT BUTTON ========== */
.submit-button {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
    border: none;
    color: white;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 15px;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
}

.submit-button:hover {
    background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
}

/* ========== DETAILS CONTAINER ========== */
.details-container {
    display: flex;
    flex-direction: column;
    gap: 14px;
    font-size: 14px;
}

.item-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #d5d5d5;
    padding: 6px 0;
}

/* ========== SEARCH & FILTERS ========== */
.search-container {
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 20px;
}

.select-form {
    width: 200px;
}

/* ========== INPUT WITH CLEAR BUTTON ========== */
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

/* ========== CONTAINER TYPE HINT ========== */
.container-type-hint {
    font-size: 12px;
    color: #666;
    margin-top: 4px;
    font-style: italic;
}

/* ========== GALLERY & THUMBNAILS ========== */
.gallery-thumbnail {
    position: relative;
    cursor: pointer;
    display: inline-block;
}

.gallery-thumbnail img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 4px;
    transition: transform 0.2s;
}

.gallery-thumbnail:hover img {
    transform: scale(1.1);
}

.image-count-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #2196f3;
    color: white;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    font-weight: 600;
}

/* ========== MOBILE CARDS ========== */
.mobile-view {
    display: none;
}

.mobile-cards {
    padding: 0 15px;
}

.mobile-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s;
}

.mobile-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.mobile-card-header {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
}

.mobile-product-image {
    flex-shrink: 0;
}

.mobile-product-image img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 6px;
}

.mobile-return-info {
    flex: 1;
}

.mobile-return-title {
    margin: 0 0 5px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.mobile-return-date {
    font-size: 12px;
    color: #666;
}

.mobile-card-details {
    font-size: 14px;
}

.mobile-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid #f0f0f0;
}

.mobile-detail-row:last-child {
    border-bottom: none;
}

.mobile-detail-value {
    font-weight: 500;
    text-align: right;
}

.mobile-card-actions {
    margin-top: 10px;
    display: flex;
    gap: 10px;
}

.loading-spinner-mobile {
    text-align: center;
    padding: 40px;
    color: #666;
}

.loading-spinner-mobile i {
    font-size: 32px;
    margin-bottom: 10px;
    display: block;
}

.no-data-mobile {
    text-align: center;
    padding: 40px;
    color: #999;
    font-size: 14px;
}

/* ========== STATUS BADGES ========== */
.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
}

.status-Returned,
.status-returned {
    background: #e8f5e9;
    color: #2e7d32;
}

.status-Pending,
.status-pending {
    background: #fff3e0;
    color: #e65100;
}

.status-Processed,
.status-processed {
    background: #e3f2fd;
    color: #1565c0;
}

.status-Rejected,
.status-rejected {
    background: #ffebee;
    color: #c62828;
}

/* ========== PAGINATION ========== */
.pagination-container {
    margin-top: 20px;
    padding: 15px 20px;
    background: white;
    border-top: 1px solid #e0e0e0;
}

.pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.per-page-selector {
    display: flex;
    align-items: center;
    gap: 10px;
}

.pagination {
    display: flex;
    align-items: center;
    gap: 15px;
}

.pagination-info {
    font-size: 14px;
    color: #666;
}

/* ========== DESKTOP VIEW ========== */
.desktop-view {
    display: block;
}

/* ========== DIALOGS ========== */
.view-details-dialog {
    max-width: 900px;
}

/* ========== MOBILE RESPONSIVE ========== */
@media (max-width: 768px) {
    .desktop-view {
        display: none;
    }

    .mobile-view {
        display: block;
    }

    .capture-actions {
        flex-direction: column;
    }

    .btn-skip-images,
    .btn-done-images {
        width: 100%;
        justify-content: center;
    }

    .input-with-action {
        flex-wrap: wrap;
    }

    .input-with-action input {
        min-width: 100%;
        margin-bottom: 8px;
    }

    .btn-capture-trigger,
    .btn-remove-serial {
        flex: 1;
    }

    .view-details-dialog {
        width: 95% !important;
        max-width: 95% !important;
    }

    .search-container {
        flex-direction: column;
        align-items: stretch;
    }

    .select-form {
        width: 100%;
    }

    .pagination-wrapper {
        flex-direction: column;
        align-items: stretch;
    }

    .per-page-selector {
        justify-content: space-between;
    }

    .pagination {
        justify-content: center;
    }

    .serials-summary {
        font-size: 13px;
    }

    .serial-chip {
        font-size: 11px;
        padding: 3px 10px;
    }

    .multi-serial-badge {
        font-size: 13px;
        padding: 8px 12px;
    }
}

@media (min-width: 768px) {
    .view-details-dialog {
        width: 50% !important;
    }
}

/* ========== UTILITY CLASSES ========== */
.clickable {
    cursor: pointer;
    transition: all 0.2s;
}

.clickable:hover {
    opacity: 0.8;
}

.clickable-image {
    cursor: pointer;
    transition: transform 0.2s;
}

.clickable-image:hover {
    transform: scale(1.05);
}

.text-success {
    color: #4caf50 !important;
}

.text-primary {
    color: #2196f3 !important;
}

.text-muted {
    color: #999 !important;
}

.fw-semibold {
    font-weight: 600;
}

/* ========== ANIMATIONS ========== */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.mobile-card {
    animation: fadeIn 0.3s ease;
}
</style>