<template>
    <div class="vue-container receiving-module">
        <div
            class="d-flex align-items-center justify-content-between flex-wrap mb-4"
        >
            <TitlePage
                title="Received Module"
                subtitle="View and log inbound inventory items as they are physically received and added to stock."
            />

            <div class="d-flex gap-4 me-4 desktop-view">
                <Button
                    @click="openScannerModal"
                    label="Scan Items"
                    size="small"
                    icon="pi pi-barcode"
                    severity="secondary"
                    outlined
                />
                <Button
                    @click="openDetectSerialModal"
                    label="Detect Serial Numbers"
                    size="small"
                    icon="pi pi-hashtag"
                    severity="secondary"
                    outlined
                />
                <Button
                    label="Detection Training"
                    icon="pi pi-search"
                    outlined
                    severity="secondary"
                    size="small"
                    as="a"
                    href="/aitraining"
                    target="_blank"
                    rel="noopener"
                />
            </div>
        </div>

        <div class="mobile-view w-100 mb-4">
            <Button
                label="More Actions"
                fluid
                size="small"
                severity="secondary"
                outlined
                icon="pi pi-list"
                @click="toggle($event)"
                aria-haspopup="true"
                aria-controls="overlay_menu"
            />
            <Menu
                ref="menu"
                id="overlay_menu"
                :model="menuActions"
                :popup="true"
            />
        </div>

        <!-- Detect Serial Numbers Modal -->
        <detect-serial-modal
            v-if="showDetectSerialModal"
            @close="closeDetectSerialModal"
            ref="detectSerialModal"
        ></detect-serial-modal>

        <!-- Scanner Component -->
        <scanner-component
            module="received"
            scanner-title="Received Scanner"
            storage-prefix="received"
            :enable-camera="currentStep >= 1"
            :display-fields="[
                'Trackingnumber',
                'FirstSN',
                'SecondSN',
                'PCN',
                'Basket',
            ]"
            :api-endpoint="'/api/received/process-scan'"
            :hide-button="true"
            @process-scan="handleScanProcess"
            @hardware-scan="handleHardwareScan"
            @scanner-opened="handleScannerOpened"
            @scanner-closed="handleScannerClosed"
            @scanner-reset="handleScannerReset"
            @mode-changed="handleModeChange"
            ref="scanner"
        >
            <!-- Define custom input fields for Received module -->
            <template #input-fields>
                <!-- Step 1: Tracking Number Input -->
                <div class="input-group" v-if="currentStep === 1">
                    <label>Tracking Number:</label>
                    <input
                        type="text"
                        v-model="trackingNumber"
                        placeholder="Enter Tracking Number..."
                        @input="handleTrackingInput"
                        @keyup.enter="verifyTrackingNumber"
                        ref="trackingInput"
                    />
                    <!-- Only show Verify Tracking button in Manual mode -->
                    <button
                        v-if="showManualInput"
                        @click="verifyTrackingNumber"
                        class="verify-button"
                    >
                        Verify Tracking
                    </button>
                </div>

                <!-- Step 2: Pass/Fail Buttons (shown after tracking verification) -->
                <div class="input-group" v-if="currentStep === 2">
                    <div class="tracking-verified mt-4">
                        <div class="success-banner">
                            Tracking found for {{ trackingNumber }}
                        </div>
                    </div>

                    <div class="pass-fail-buttons mt-4">
                        <button @click="passItem" class="pass-button step-btn">
                            <i class="fas fa-check"></i> Pass
                        </button>
                        <button @click="failItem" class="fail-button step-btn">
                            <i class="fas fa-times"></i> Fail
                        </button>
                    </div>

                    <!-- ✅ Modal viewer for thumbnails -->
                </div>

                <!-- Step 3: First Serial Number Input -->
                <div class="input-group" v-if="currentStep === 3">
                    <div
                        class="border-dashed uploader-area"
                        v-show="true"
                        @dragover.prevent
                        @dragenter.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop"
                        @click="triggerFileInput"
                        :class="{ 'is-dragging': isDragging }"
                    >
                        <p>
                            Drag & drop an image here, or
                            <span class="text-highlight">click to select</span>
                        </p>
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="onFileChange"
                            :disabled="loading"
                        />
                    </div>

                    <!-- 👇 Optional small preview (after upload) -->
                    <div v-if="imageUrl" class="uploaded-preview">
                        <img :src="imageUrl" alt="Uploaded preview" />
                        <button class="clear-upload" @click="imageUrl = null">
                            ×
                        </button>
                    </div>

                    <!-- OCR Detected Serials (show for step 3 & 4 only) -->
                    <div
                        v-if="
                            apiResult.step3 &&
                            apiResult.step3.serials &&
                            apiResult.step3.serials.length
                        "
                        class="serial-results-wrapper-main"
                    >
                        <p class="text-sm text-gray-500 mb-1">
                            Detected Serials:
                        </p>
                        <div
                            v-for="(serial, index) in apiResult.step3.serials"
                            :key="index"
                            class="mb-3 serial-results-wrapper"
                        >
                            <div
                                class="flex items-center gap-2 serial-result-wrap"
                            >
                                <div class="font-mono serial-result">
                                    {{ serial.text }}
                                </div>
                                <button
                                    class="px-2 py-1 bg-green-500 text-white rounded serial-btn"
                                    @click="saveSerial(serial.text, index)"
                                >
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="instruction-text">
                        Please picture the first serial number.
                    </p>

                    <label>First Serial Number:</label>
                    <input
                        type="text"
                        v-model="firstSerialNumber"
                        placeholder="Scan First Serial Number..."
                        @input="handleFirstSerialInput"
                        @keyup.enter="processFirstSerial"
                        ref="firstSerialInput"
                    />
                    <button
                        v-if="showManualInput"
                        @click="processFirstSerial"
                        class="scan-button"
                    >
                        Scan
                    </button>
                </div>

                <!-- Step 4: Second Serial Number Input (with Skip option) -->
                <div class="input-group" v-if="currentStep === 4">
                    <div
                        class="border-dashed uploader-area"
                        v-show="true"
                        @dragover.prevent
                        @dragenter.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop"
                        @click="triggerFileInput"
                        :class="{ 'is-dragging': isDragging }"
                    >
                        <p>
                            Drag & drop an image here, or
                            <span class="text-highlight">click to select</span>
                        </p>
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="onFileChange"
                            :disabled="loading"
                        />
                    </div>

                    <!-- 👇 Optional small preview (after upload) -->
                    <div v-if="imageUrl" class="uploaded-preview">
                        <img :src="imageUrl" alt="Uploaded preview" />
                        <button class="clear-upload" @click="imageUrl = null">
                            ×
                        </button>
                    </div>

                    <div
                        v-if="
                            apiResult.step4 &&
                            apiResult.step4.serials &&
                            apiResult.step4.serials.length
                        "
                        class="serial-results-wrapper-main"
                    >
                        <p class="text-sm text-gray-500 mb-1">
                            Detected Serials:
                        </p>
                        <div
                            v-for="(serial, index) in apiResult.step4.serials"
                            :key="index"
                            class="mb-3 serial-results-wrapper"
                        >
                            <div
                                class="flex items-center gap-2 serial-result-wrap"
                            >
                                <div class="font-mono serial-result">
                                    {{ serial.text }}
                                </div>
                                <button
                                    class="px-2 py-1 bg-green-500 text-white rounded serial-btn"
                                    @click="saveSerial(serial.text, index)"
                                >
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>
                    <label>Second Serial Number:</label>
                    <input
                        type="text"
                        v-model="secondSerialNumber"
                        placeholder="Scan Second Serial Number (or Skip)..."
                        @input="handleSecondSerialInput"
                        @keyup.enter="processSecondSerial"
                        ref="secondSerialInput"
                    />
                    <div class="button-group">
                        <button
                            v-if="showManualInput"
                            @click="processSecondSerial"
                            class="scan-button"
                        >
                            Scan
                        </button>
                        <button @click="skipSecondSerial" class="skip-button">
                            Skip
                        </button>
                    </div>
                </div>

                <!-- Step 5: PCN Input  -->
                <div class="input-group" v-if="currentStep === 5">
                    <label>PCN (Product Control Number):</label>
                    <input
                        type="text"
                        v-model="pcnNumber"
                        placeholder="Scan PCN Number..."
                        @input="handlePcnInput"
                        @keyup.enter="processPcnNumber"
                        ref="pcnInput"
                    />
                    <div class="container-type-hint">
                        Enter PCN format: PCN followed by numbers (e.g.,
                        PCN12345)
                    </div>
                    <button
                        v-if="showManualInput"
                        @click="processPcnNumber"
                        class="scan-button"
                    >
                        Scan
                    </button>
                </div>

                <!-- Step 6: Basket Number Input (now step 6) -->
                <div class="input-group" v-if="currentStep === 6">
                    <label>Basket/Container Number:</label>
                    <input
                        type="text"
                        v-model="basketNumber"
                        placeholder="Enter BKT/SI/ENV + numbers..."
                        @input="handleBasketInput"
                        @keyup.enter="processBasketNumber"
                        ref="basketInput"
                    />
                    <div class="container-type-hint">
                        Enter numbers with prefix: BKT (Basket), SI (Shelf), or
                        ENV (Envelope)
                    </div>
                    <button
                        v-if="showManualInput"
                        @click="processBasketNumber"
                        class="scan-button"
                    >
                        Submit
                    </button>
                </div>
            </template>
        </scanner-component>

        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="px-4">
            <XDataTable
                :value="sortedInventory"
                :loading="loading"
                :columns="visibleColumns"
                :paginator="false"
                dataKey="ProductID"
                selectionMode="multiple"
                tableClass="desktop-view"
            >
                <template #gallery="{ data }">
                    <div
                        class="d-flex justify-content-center align-items-center"
                    >
                        <TableGallery
                            :data="data"
                            :openImageModal="openImageModal"
                            :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages"
                        />
                    </div>
                </template>

                <template #ProductTitle="{ data }">
                    <div class="d-flex align-items-start gap-4">
                        <div
                            style="
                                word-break: break-word;
                                white-space: normal;
                                overflow-wrap: break-word;
                                flex: 1;
                            "
                        >
                            <p style="font-size: 0.8rem">
                                RT# {{ data.rtcounter }}
                            </p>
                            <p class="fw-semibold">
                                {{ data.ProductTitle }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #datedelivered="{ data }">
                    {{ convertToLocalDate(data.datedelivered) }}
                </template>

                <template #actions="{ data }">
                    <Button
                        size="small"
                        severity="contrast"
                        variant="text"
                        label="View Details"
                        class="text-primary"
                        icon="pi pi-exclamation-circle"
                        @click="openEditModal(data)"
                    />
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <MobileCard1
                :sortedInventory="sortedInventory"
                :expandedRows="expandedRows"
                :openImageModal="openImageModal"
                :handleImageError="handleImageError"
                :countAdditionalImages="countAdditionalImages"
                :openEditModal="openEditModal"
                :loading="loading"
                :showDetails="showDetails"
            />
        </div>

        <!-- Pagination with centered layout -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <Select
                        v-model="perPage"
                        @change="changePerPage"
                        :options="rowsPerPage"
                        optionLabel="label"
                        optionValue="value"
                        size="small"
                    />
                </div>

                <div class="pagination">
                    <Button
                        @click="prevPage"
                        :disabled="currentPage === 1"
                        class="pagination-button"
                        label="Back"
                        icon="pi pi-angle-left"
                        size="small"
                        severity="info"
                    />
                    <span class="pagination-info"
                        >Page {{ currentPage }} of {{ totalPages }}</span
                    >
                    <Button
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        class="pagination-button"
                        label="Next"
                        icon="pi pi-angle-right"
                        size="small"
                        severity="info"
                        iconPos="right"
                    />
                </div>
            </div>
        </div>

        <!-- Image Modal -->
        <ViewImageModal
            v-model:visible="showImageModal"
            :title="ProductTitle"
            :imageList="imageList"
            :basePath="basePath"
            :onImageErrorMain="onImageErrorMain"
            :onThumbnailError="onThumbnailError"
            @close="closeImageModal"
        />

        <Dialog
            v-model:visible="showEditModal"
            modal
            :style="{ width: '95%' }"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <template #header>
                <div class="productTitle">
                    <h5>
                        <span>RT #{{ item.ProductID }} {{ " " }} </span>
                        <span>
                            {{ item.ProductTitle }}
                        </span>
                    </h5>
                </div>
            </template>

            <div class="view-info-container">
                <div class="view-grid-wrapper">
                    <!-- LEFT: IMAGE -->
                    <div class="form-col-left">
                        <gallery :item="item" />
                        <Card>
                            <template #title>
                                <h5 class="text-primary fw-bolder">
                                    Description
                                </h5>
                            </template>
                            <template #content>
                                <p
                                    style="
                                        word-break: break-all;
                                        max-height: 450px;
                                        overflow-y: auto;
                                        font-size: 14px;
                                    "
                                >
                                    {{ item.description }}
                                </p>
                            </template>
                        </Card>
                    </div>

                    <!-- RIGHT: DETAILS -->
                    <div class="form-col-right">
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <!-- Warehouse & Tracking -->
                                <section class="info-section">
                                    <h3 class="text-primary fw-bolder">
                                        Warehouse & Tracking
                                    </h3>
                                    <dl class="info-list">
                                        <div class="info-item">
                                            <dt>Module:</dt>
                                            <dd>
                                                {{ item.ProductModuleLoc }}
                                            </dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>Warehouse Location:</dt>
                                            <dd>
                                                {{ item.warehouselocation }}
                                            </dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>Serial Number:</dt>
                                            <dd>
                                                {{ item.serialnumber }}
                                            </dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>Tracking Number:</dt>
                                            <dd>
                                                {{ item.trackingnumber }}
                                            </dd>
                                        </div>
                                    </dl>
                                </section>

                                <!-- Product Identifiers -->
                                <section class="info-section">
                                    <h3 class="text-primary fw-bolder">
                                        Product Identifiers
                                    </h3>
                                    <dl class="info-list">
                                        <div class="info-item">
                                            <dt>RT:</dt>
                                            <dd>
                                                {{ item.ProductID }}
                                            </dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>ASIN:</dt>
                                            <dd>{{ item.ASIN }}</dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>RPN:</dt>
                                            <dd>{{ item.RPN }}</dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>PRD:</dt>
                                            <dd>{{ item.PRD }}</dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>UPC:</dt>
                                            <dd>{{ item.UPC }}</dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>EAN:</dt>
                                            <dd>{{ item.EAN }}</dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>FNSKU:</dt>
                                            <dd>{{ item.FNSKU }}</dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>SKU:</dt>
                                            <dd>{{ item.SKU }}</dd>
                                        </div>
                                    </dl>
                                </section>

                                <!-- Order Information -->
                                <section class="info-section">
                                    <h3 class="text-primary fw-bolder">
                                        Order Information
                                    </h3>
                                    <dl class="info-list">
                                        <div class="info-item">
                                            <dt>Order Number:</dt>
                                            <dd>{{ item.rtid }}</dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>Item Number:</dt>
                                            <dd>
                                                {{ item.itemnumber }}
                                            </dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>Basket Number:</dt>
                                            <dd>
                                                {{ item.basketnumber }}
                                            </dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>Order Date:</dt>
                                            <dd>
                                                {{ localOrderDate }}
                                            </dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>Delivered Date:</dt>
                                            <dd>
                                                {{ localDeliveredDate }}
                                            </dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>Seller:</dt>
                                            <dd>{{ item.seller }}</dd>
                                        </div>
                                    </dl>
                                </section>

                                <!-- Additional Info -->
                                <section
                                    class="info-section"
                                    v-if="item.grading || item.notes"
                                >
                                    <h3 class="text-primary fw-bolder">
                                        Additional Info
                                    </h3>
                                    <dl class="info-list">
                                        <div
                                            class="info-item"
                                            v-if="item.grading"
                                        >
                                            <dt>Grading:</dt>
                                            <dd>{{ item.grading }}</dd>
                                        </div>
                                        <div
                                            class="info-item"
                                            v-if="item.notes"
                                        >
                                            <dt>Notes:</dt>
                                            <dd>{{ item.notes }}</dd>
                                        </div>
                                    </dl>
                                </section>
                            </div>

                            <!-- Right Column: Pricing -->
                            <div class="col-md-6">
                                <section
                                    class="pricing-section"
                                    v-show="showPricingSection"
                                >
                                    <h3 class="text-primary fw-bolder">
                                        Pricing
                                    </h3>
                                    <dl class="pricing-list">
                                        <!-- Base Pricing -->
                                        <div class="pricing-item">
                                            <dt>Unit Price:</dt>
                                            <dd>
                                                {{
                                                    item.formattedUnitprice ||
                                                    "0.00"
                                                }}
                                            </dd>
                                        </div>
                                        <div class="pricing-item">
                                            <dt>Quantity:</dt>
                                            <dd>
                                                {{ item.quantity || 0 }}
                                            </dd>
                                        </div>
                                        <div class="pricing-item subtotal-line">
                                            <dt>Subtotal:</dt>
                                            <dd>
                                                {{ item.price || "0.00" }}
                                            </dd>
                                        </div>

                                        <!-- Adjustments -->
                                        <div
                                            class="pricing-item"
                                            v-if="item.Discount"
                                        >
                                            <dt>Discount:</dt>
                                            <dd class="discount">
                                                -{{ item.Discount }}
                                            </dd>
                                        </div>
                                        <div class="pricing-item">
                                            <dt>Tax:</dt>
                                            <dd>{{ item.tax }}</dd>
                                        </div>
                                        <div class="pricing-item">
                                            <dt>Shipping:</dt>
                                            <dd>
                                                {{ item.priceshipping }}
                                            </dd>
                                        </div>

                                        <!-- Total -->
                                        <div class="pricing-item total-line">
                                            <dt>Total Price:</dt>
                                            <dd class="total-amount">
                                                {{ grandTotal }}
                                            </dd>
                                        </div>

                                        <!-- Refund -->
                                        <div
                                            class="pricing-item refund-line"
                                            v-if="item.refund"
                                        >
                                            <dt>Refund:</dt>
                                            <dd class="refund">
                                                {{ item.refund }}
                                            </dd>
                                        </div>
                                    </dl>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>

        <div v-if="false" class="modal view-modal">
            <div class="modal-overlay" @click="closeEditModal"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <div class="productTitle">
                        <h2 class="fw-bold">
                            <small>RT #{{ item.ProductID }}</small>
                            <span>
                                {{ item.ProductTitle }}
                            </span>
                        </h2>
                    </div>
                    <button class="btn btn-modal-close" @click="closeEditModal">
                        &times;
                    </button>
                </div>

                <div class="modal-body">
                    <div class="view-info-container">
                        <div class="view-grid-wrapper">
                            <!-- LEFT: IMAGE -->
                            <div class="form-col-left">
                                <gallery :item="item" />
                                <Card>
                                    <template #title>
                                        <h5 class="text-primary fw-bolder">
                                            Description
                                        </h5>
                                    </template>
                                    <template #content>
                                        <p
                                            style="
                                                word-break: break-all;
                                                max-height: 450px;
                                                overflow-y: auto;
                                            "
                                        >
                                            {{ item.description }}
                                        </p>
                                    </template>
                                </Card>
                            </div>

                            <!-- RIGHT: DETAILS -->
                            <div class="form-col-right">
                                <div class="row">
                                    <!-- Left Column -->
                                    <div class="col-md-6">
                                        <!-- Product Identifiers -->
                                        <section class="info-section">
                                            <h3 class="text-primary fw-bolder">
                                                Product Identifiers
                                            </h3>
                                            <dl class="info-list">
                                                <div class="info-item">
                                                    <dt>RT:</dt>
                                                    <dd>
                                                        {{ item.ProductID }}
                                                    </dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>ASIN:</dt>
                                                    <dd>{{ item.ASIN }}</dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>RPN:</dt>
                                                    <dd>{{ item.RPN }}</dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>PRD:</dt>
                                                    <dd>{{ item.PRD }}</dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>UPC:</dt>
                                                    <dd>{{ item.UPC }}</dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>EAN:</dt>
                                                    <dd>{{ item.EAN }}</dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>FNSKU:</dt>
                                                    <dd>{{ item.FNSKU }}</dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>SKU:</dt>
                                                    <dd>{{ item.SKU }}</dd>
                                                </div>
                                            </dl>
                                        </section>

                                        <!-- Order Information -->
                                        <section class="info-section">
                                            <h3 class="text-primary fw-bolder">
                                                Order Information
                                            </h3>
                                            <dl class="info-list">
                                                <div class="info-item">
                                                    <dt>Order Number:</dt>
                                                    <dd>{{ item.rtid }}</dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>Item Number:</dt>
                                                    <dd>
                                                        {{ item.itemnumber }}
                                                    </dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>Basket Number:</dt>
                                                    <dd>
                                                        {{ item.basketnumber }}
                                                    </dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>Order Date:</dt>
                                                    <dd>
                                                        {{ localOrderDate }}
                                                    </dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>Delivered Date:</dt>
                                                    <dd>
                                                        {{ localDeliveredDate }}
                                                    </dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>Seller:</dt>
                                                    <dd>{{ item.seller }}</dd>
                                                </div>
                                            </dl>
                                        </section>

                                        <!-- Warehouse & Tracking -->
                                        <section class="info-section">
                                            <h3 class="text-primary fw-bolder">
                                                Warehouse & Tracking
                                            </h3>
                                            <dl class="info-list">
                                                <div class="info-item">
                                                    <dt>Module:</dt>
                                                    <dd>
                                                        {{
                                                            item.ProductModuleLoc
                                                        }}
                                                    </dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>Warehouse Location:</dt>
                                                    <dd>
                                                        {{
                                                            item.warehouselocation
                                                        }}
                                                    </dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>Serial Number:</dt>
                                                    <dd>
                                                        {{ item.serialnumber }}
                                                    </dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>Tracking Number:</dt>
                                                    <dd>
                                                        {{
                                                            item.trackingnumber
                                                        }}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </section>

                                        <!-- Additional Info -->
                                        <section
                                            class="info-section"
                                            v-if="item.grading || item.notes"
                                        >
                                            <h3 class="text-primary fw-bolder">
                                                Additional Info
                                            </h3>
                                            <dl class="info-list">
                                                <div
                                                    class="info-item"
                                                    v-if="item.grading"
                                                >
                                                    <dt>Grading:</dt>
                                                    <dd>{{ item.grading }}</dd>
                                                </div>
                                                <div
                                                    class="info-item"
                                                    v-if="item.notes"
                                                >
                                                    <dt>Notes:</dt>
                                                    <dd>{{ item.notes }}</dd>
                                                </div>
                                            </dl>
                                        </section>
                                    </div>

                                    <!-- Right Column: Pricing -->
                                    <div class="col-md-6">
                                        <section class="pricing-section">
                                            <h3 class="text-primary fw-bolder">
                                                Pricing
                                            </h3>
                                            <dl class="pricing-list">
                                                <!-- Base Pricing -->
                                                <div class="pricing-item">
                                                    <dt>Unit Price:</dt>
                                                    <dd>
                                                        {{
                                                            item.formattedUnitprice ||
                                                            "0.00"
                                                        }}
                                                    </dd>
                                                </div>
                                                <div class="pricing-item">
                                                    <dt>Quantity:</dt>
                                                    <dd>
                                                        {{ item.quantity || 0 }}
                                                    </dd>
                                                </div>
                                                <div
                                                    class="pricing-item subtotal-line"
                                                >
                                                    <dt>Subtotal:</dt>
                                                    <dd>
                                                        {{
                                                            item.price || "0.00"
                                                        }}
                                                    </dd>
                                                </div>

                                                <!-- Adjustments -->
                                                <div
                                                    class="pricing-item"
                                                    v-if="item.Discount"
                                                >
                                                    <dt>Discount:</dt>
                                                    <dd class="discount">
                                                        -{{ item.Discount }}
                                                    </dd>
                                                </div>
                                                <div class="pricing-item">
                                                    <dt>Tax:</dt>
                                                    <dd>{{ item.tax }}</dd>
                                                </div>
                                                <div class="pricing-item">
                                                    <dt>Shipping:</dt>
                                                    <dd>
                                                        {{ item.priceshipping }}
                                                    </dd>
                                                </div>

                                                <!-- Total -->
                                                <div
                                                    class="pricing-item total-line"
                                                >
                                                    <dt>Total Price:</dt>
                                                    <dd class="total-amount">
                                                        {{ grandTotal }}
                                                    </dd>
                                                </div>

                                                <!-- Refund -->
                                                <div
                                                    class="pricing-item refund-line"
                                                    v-if="item.refund"
                                                >
                                                    <dt>Refund:</dt>
                                                    <dd class="refund">
                                                        {{ item.refund }}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </section>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <ScrollTop />
    </div>
</template>

<script>
import { Button, Dialog, Card, ScrollTop, Menu, Select } from "primevue";
import Received from "./receiving.js";
import gallery from "../../components/Gallery/gallery.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import MobileCard1 from "../../components/MobileCard1/MobileCard1.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageModal from "../../components/ViewImageModal/ViewImageModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
import { showPricingForPH } from "../../utils/helpers.js";

const TABLE_COLUMNS = [
    {
        field: "gallery",
        header: "Gallery",
        slot: "gallery",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        field: "ProductTitle",
        header: "Product Name",
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "ProductTitle",
        style: { maxWidth: "20rem" },
    },

    {
        field: "price",
        header: "Price",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "serialNumber",
        header: "Serial Number",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "trackingnumber",
        header: "Tracking Number",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "datedelivered",
        header: "Date Delivered",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "materialtype",
        header: "Material",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
];

export default {
    mixins: [Received],
    components: {
        Button,
        Dialog,
        Card,
        gallery,
        TableGallery,
        XDataTable,
        MobileCard1,
        ScrollTop,
        TitlePage,
        ViewImageModal,
        AnimateDiv,
        Menu,
        Select,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            menuActions: [],
            rowsPerPage: ROWS_PER_PAGE,
            currentTimezone: "UTC",
            timezoneLabel: "Loading...",
            showPricingSection: showPricingForPH(),
        };
    },
    async mounted() {
        await this.loadUserTimezone();
        window.addEventListener("resize", this.updatePricingView);
    },
    methods: {
        toggle(event) {
            this.menuActions = this.getMoreActionItems(this.currentActionItem);
            if (this.$refs.menu) {
                this.$refs.menu.toggle(event);
            }
        },
        getMoreActionItems() {
            return [
                {
                    label: "Scan Items",
                    icon: "pi pi-barcode",
                    command: () => this.openScannerModal(),
                },
                {
                    label: "Detect Serial Numbers",
                    icon: "pi pi-hashtag",
                    command: () => this.openDetectSerialModal(),
                },
                {
                    label: "Detection Training",
                    icon: "pi pi-search",
                    command: () => url("/aiTraining"),
                },
            ];
        },

        convertToLocalDate(dateString) {
            if (!dateString) return "";

            try {
                // Parse the date from database (assumed to be in UTC or server timezone)
                const date = new Date(dateString);

                // Format to YYYY-MM-DD for date input in user's timezone
                const options = {
                    timeZone: this.currentTimezone,
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                };

                const formatter = new Intl.DateTimeFormat("en-CA", options); // en-CA gives YYYY-MM-DD format
                return formatter.format(date);
            } catch (error) {
                console.error("Error converting to local date:", error);
                return dateString;
            }
        },

        convertFromLocalDate(localDateString) {
            if (!localDateString) return null;

            try {
                // The input gives us YYYY-MM-DD in user's timezone
                // We need to convert it to a proper datetime for storage

                // Create a date object at noon in the user's timezone to avoid day boundary issues
                const [year, month, day] = localDateString.split("-");
                const dateInUserTz = new Date(
                    `${year}-${month}-${day}T12:00:00`,
                );

                // Format for database storage (ISO format)
                return dateInUserTz.toISOString().split("T")[0]; // Returns YYYY-MM-DD
            } catch (error) {
                console.error("Error converting from local date:", error);
                return localDateString;
            }
        },

        async loadUserTimezone() {
            try {
                const response = await axios.get("/api/timezone/current");

                if (response.data.success && response.data.usertimezone) {
                    this.currentTimezone = response.data.usertimezone;

                    // Format timezone for display
                    const timezoneParts = this.currentTimezone.split("/");
                    const location = timezoneParts[
                        timezoneParts.length - 1
                    ].replace("_", " ");

                    // ✅ FIXED: Calculate GMT offset for the SELECTED timezone, not browser's
                    const date = new Date();

                    // Get the date in UTC
                    const utcDate = new Date(
                        date.toLocaleString("en-US", { timeZone: "UTC" }),
                    );

                    // Get the date in user's selected timezone
                    const userTzDate = new Date(
                        date.toLocaleString("en-US", {
                            timeZone: this.currentTimezone,
                        }),
                    );

                    // Calculate offset in hours
                    const offsetMs = userTzDate - utcDate;
                    const offsetHours = Math.round(offsetMs / (1000 * 60 * 60));
                    const offsetSign = offsetHours >= 0 ? "+" : "-";
                    const gmtOffset = `GMT${offsetSign}${Math.abs(
                        offsetHours,
                    )}`;

                    this.timezoneLabel = `(${gmtOffset})`;
                } else {
                    // Fallback to browser timezone
                    const browserTz =
                        Intl.DateTimeFormat().resolvedOptions().timeZone;
                    this.currentTimezone = browserTz;
                    const location = browserTz
                        .split("/")
                        .pop()
                        .replace("_", " ");
                    this.timezoneLabel = location;
                }

                console.log("📍 Timezone loaded:", this.timezoneLabel);
            } catch (error) {
                console.error("Error loading timezone:", error);
                this.currentTimezone = "UTC";
                this.timezoneLabel = "UTC";
            }
        },
        updatePricingView() {
            this.showPricingSection = showPricingForPH();
        },
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.updatePricingView);
    },
    computed: {
        visibleColumns() {
            if (!this.columns) return [];

            //columns can be showed or hidden
            const detailFields = [
                "FBMAvailable",
                "FbaAvailable",
                "Outbound",
                "Inbound",
                "Reserved",
                "Unfulfillable",
            ];

            return this.columns.filter((col) => {
                if (!this.showDetails && detailFields.includes(col.field)) {
                    return false;
                }
                return true;
            });
        },

        // ✅ ADD THESE COMPUTED PROPERTIES FOR DATE CONVERSION
        localOrderDate: {
            get() {
                return this.convertToLocalDate(this.item.orderdate);
            },
            set(value) {
                this.item.orderdate = this.convertFromLocalDate(value);
            },
        },
        localDeliveredDate: {
            get() {
                return this.convertToLocalDate(this.item.datedelivered);
            },
            set(value) {
                this.item.datedelivered = this.convertFromLocalDate(value);
            },
        },
    },
};
</script>

<style scoped>
.uploader-area {
    border: 2px dashed #ccc;
    border-radius: 12px;
    padding: 20px 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #fafafa;
    color: #666;
}

.uploader-area:hover {
    border-color: #4caf50;
    background-color: #f0fff0;
}

.uploader-area.is-dragging {
    border-color: #2196f3;
    background-color: #e3f2fd;
    color: #000;
}

.uploader-area p {
    margin: 0;
    font-size: 1rem;
}

.text-highlight {
    color: #007bff;
    text-decoration: underline;
    cursor: pointer;
}

.hidden {
    display: none;
}

.uploaded-preview {
    margin-top: 10px;
    position: relative;
    display: inline-block;
}

.uploaded-preview img {
    max-width: 160px;
    border-radius: 10px;
    border: 1px solid #ddd;
}

.clear-upload {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #f44336;
    border: none;
    color: white;
    font-size: 14px;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    line-height: 20px;
    text-align: center;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.uploaded-preview {
    display: flex !important;
    justify-content: center;
}
</style>
