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
            :display-fields="['Trackingnumber', 'Serials', 'PCN', 'Basket']"
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
            <template #input-fields>
                <div
                    class="fw-bold text-dark quantity-info"
                    :class="{
                        'text-warning': remainingQuantity > 1,
                        'text-success': remainingQuantity === 1,
                    }"
                    v-if="trackingFound"
                >
                    Quantity: {{ remainingQuantity }}
                </div>

                <!-- ─── Step 1: Tracking Number ─── -->
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
                    <Button
                        v-if="showManualInput"
                        @click="verifyTrackingNumber"
                        class="verify-button"
                    >
                        Verify Tracking
                    </Button>

                    <div v-if="currentStep === 1 && trackingFound" class="mt-4">
                        <p class="text-sm text-gray-500">
                            📸 Capture 1–2 images of the tracking number to
                            continue.
                        </p>
                        <button
                            class="continue-button step-btn text-center"
                            :disabled="!hasTrackingImages"
                            @click="proceedToPassFail"
                        >
                            Continue
                        </button>
                    </div>
                </div>

                <!-- ─── Step 2: Pass / Fail ─── -->
                <div class="input-group" v-if="currentStep === 2">
                    <div class="tracking-verified mt-4">
                        <div class="success-banner">
                            Tracking found for {{ trackingNumber }}
                        </div>
                    </div>

                    <div class="pass-fail-buttons mt-4">
                        <button
                            @click="passItem"
                            class="pass-button step-btn"
                            :disabled="!scannerHasCapturedImage"
                        >
                            <i class="fas fa-check"></i> Pass
                        </button>
                        <button
                            @click="failItem"
                            class="fail-button step-btn"
                            :disabled="!scannerHasCapturedImage"
                        >
                            <i class="fas fa-times"></i> Fail
                        </button>
                    </div>

                    <p v-if="!scannerHasCapturedImage" class="text-sm mt-2">
                        📸 Please capture at least 3 images before passing or
                        failing.
                    </p>
                </div>

                <!-- ─── Step 3: Inspection Checklist (NEW — after Pass/Fail) ─── -->
                <div
                    class="input-group checklist-step"
                    v-if="currentStep === 3"
                >
                    <!-- Show what was selected in Step 2 -->
                    <div class="tracking-verified mt-4">
                        <div
                            :class="
                                passFailResult === 'pass'
                                    ? 'success-banner'
                                    : 'fail-banner'
                            "
                        >
                            Item marked as:
                            {{
                                passFailResult === "pass" ? "✓ Pass" : "✗ Fail"
                            }}
                        </div>
                    </div>

                    <!-- Header -->
                    <div class="checklist-header mt-4">
                        <span class="checklist-badge">CHECKLIST</span>
                        <span class="checklist-title">Inspection Items</span>
                    </div>

                    <!-- ── Card 1: Item received correct on order ── -->
                    <div class="checklist-card">
                        <div class="checklist-card-top">
                            <span class="checklist-card-label"
                                >Item received correct on order</span
                            >
                            <span class="checklist-default-badge"
                                >Default: yes</span
                            >
                        </div>

                        <div class="checklist-options">
                            <button
                                class="checklist-btn"
                                :class="{
                                    'checklist-btn--active-pass':
                                        checklist.correctOnOrder === 'yes',
                                }"
                                @click="checklist.correctOnOrder = 'yes'"
                            >
                                Yes ✓
                            </button>
                            <button
                                class="checklist-btn"
                                :class="{
                                    'checklist-btn--active-fail':
                                        checklist.correctOnOrder === 'no',
                                }"
                                @click="checklist.correctOnOrder = 'no'"
                            >
                                No ✗
                            </button>
                        </div>

                        <p class="checklist-hint">
                            <span class="checklist-hint-icon">!</span>
                            If fail: Auto-select 'No'
                        </p>
                    </div>

                    <!-- ── Card 2: Condition on Arrival ── -->
                    <div class="checklist-card">
                        <div class="checklist-card-top">
                            <span class="checklist-card-label"
                                >Condition on Arrival</span
                            >
                            <span class="checklist-default-badge"
                                >Default: good</span
                            >
                        </div>

                        <div class="checklist-options">
                            <button
                                class="checklist-btn"
                                :class="{
                                    'checklist-btn--active-pass':
                                        checklist.condition === 'good',
                                }"
                                @click="
                                    checklist.condition = 'good';
                                    checklist.conditionNotes = '';
                                "
                            >
                                Good ✓
                            </button>
                            <button
                                class="checklist-btn"
                                :class="{
                                    'checklist-btn--active-fail':
                                        checklist.condition === 'damaged',
                                }"
                                @click="checklist.condition = 'damaged'"
                            >
                                Damaged
                            </button>
                            <button
                                class="checklist-btn"
                                :class="{
                                    'checklist-btn--active-fail':
                                        checklist.condition === 'defective',
                                }"
                                @click="checklist.condition = 'defective'"
                            >
                                Defective
                            </button>
                            <button
                                class="checklist-btn"
                                :class="{
                                    'checklist-btn--active-fail':
                                        checklist.condition === 'incomplete',
                                }"
                                @click="checklist.condition = 'incomplete'"
                            >
                                Incomplete
                            </button>
                        </div>

                        <p class="checklist-hint">
                            <span class="checklist-hint-icon">!</span>
                            If fail: Select condition category + Add notes
                        </p>

                        <textarea
                            v-if="
                                checklist.condition &&
                                checklist.condition !== 'good'
                            "
                            v-model="checklist.conditionNotes"
                            class="checklist-notes"
                            placeholder="Add condition notes here..."
                            rows="3"
                        ></textarea>

                        <textarea
                            v-else
                            class="checklist-notes checklist-notes--placeholder"
                            disabled
                            placeholder="Conditional notes appear here when fail option is selected..."
                            rows="3"
                        ></textarea>
                    </div>

                    <!-- Continue to serials — gated until checklist is complete -->
                    <button
                        class="continue-button step-btn text-center mt-4"
                        :disabled="!checklistComplete"
                        @click="proceedFromChecklist"
                    >
                        Continue
                    </button>
                </div>

                <!-- ─── Steps 4–8: Serial Inputs (was 3–7) ─── -->
                <div
                    class="input-group"
                    v-if="currentStep >= 4 && currentStep <= 8"
                >
                    <div class="label-wrap">
                        <label
                            >Serial Number {{ currentSerialIndex + 1 }}:</label
                        >
                        <div class="ai-switch-container">
                            <span class="ai-label">AI Detection</span>
                            <label class="ai-switch">
                                <input
                                    type="checkbox"
                                    v-model="useAiDetection"
                                />
                                <span class="ai-slider"></span>
                            </label>
                            <span class="ai-status">{{
                                useAiDetection ? "ON" : "OFF"
                            }}</span>
                        </div>
                    </div>

                    <div
                        v-if="useAiDetection"
                        class="border-dashed uploader-area"
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

                    <div v-if="imageUrl" class="uploaded-preview">
                        <img :src="imageUrl" alt="Uploaded preview" />
                        <button class="clear-upload" @click="imageUrl = null">
                            ×
                        </button>
                    </div>

                    <div
                        v-if="
                            apiResult['step' + currentStep] &&
                            apiResult['step' + currentStep].serials &&
                            apiResult['step' + currentStep].serials.length
                        "
                        class="serial-results-wrapper-main"
                    >
                        <p class="text-sm text-gray-500 mb-1">
                            Detected Serials:
                        </p>
                        <div
                            v-for="(serial, idx) in apiResult[
                                'step' + currentStep
                            ].serials"
                            :key="idx"
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
                                    @click="saveSerial(serial.text)"
                                >
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>

                    <input
                        type="text"
                        v-model="serialNumbers[currentSerialIndex]"
                        :placeholder="`Scan Serial #${currentSerialIndex + 1}...`"
                        @input="handleSerialTyping"
                        :ref="`serialInput${currentSerialIndex + 1}`"
                    />

                    <div class="process-buttons">
                        <button
                            v-if="showManualInput"
                            @click="triggerManualSerial"
                            class="scan-button"
                        >
                            Save Serial
                        </button>
                        <button
                            v-if="currentStep >= 5 && currentStep <= 8"
                            class="skip-button"
                            type="button"
                            @click="skipSerialStep"
                        >
                            Skip
                        </button>
                    </div>

                    <p class="instruction-text">
                        📸 Capture the serial image for Serial #{{
                            currentSerialIndex + 1
                        }}
                        before continuing.
                    </p>
                </div>

                <!-- ─── Step 9: PCN (was Step 8) ─── -->
                <div class="input-group" v-if="currentStep === 9">
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

                <!-- ─── Step 10: Basket (was Step 9) ─── -->
                <div class="input-group" v-if="currentStep === 10">
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
        <Paginator
            :first="first"
            :rows="perPage"
            :total-records="totalRecords"
            :rows-per-page-options="[10, 20, 50]"
            template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
            class="small-paginator"
            @page="onPageChange"
        />

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
import {
    Button,
    Dialog,
    Card,
    ScrollTop,
    Menu,
    Select,
    Paginator,
} from "primevue";
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
        Paginator,
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
                const userTimezone = this.currentTimezone;
                const isLATimezone =
                    userTimezone === "America/Los_Angeles" ||
                    userTimezone === "America/Pacific" ||
                    !userTimezone;

                // DB stores time in LA timezone — if user is already in LA, just extract date directly
                if (isLATimezone) {
                    return dateString.split(" ")[0].split("T")[0];
                }

                // User is in a different timezone — convert LA time to user's local timezone
                const isRawFormat =
                    !dateString.includes("T") &&
                    !dateString.includes("Z") &&
                    !dateString.includes("+");

                let date;
                if (isRawFormat) {
                    const isoLike = dateString.replace(" ", "T");
                    const tempDate = new Date(isoLike);
                    const laWallClock = new Date(
                        new Date(isoLike).toLocaleString("en-US", {
                            timeZone: "America/Los_Angeles",
                        }),
                    );
                    const diff = tempDate - laWallClock;
                    date = new Date(tempDate.getTime() + diff);
                } else {
                    date = new Date(dateString);
                }

                const formatter = new Intl.DateTimeFormat("en-CA", {
                    timeZone: userTimezone,
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                });

                return formatter.format(date);
            } catch (error) {
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
        triggerManualSerial() {
            this._manualTrigger = true;
            this.processSerial();
            this._manualTrigger = false;
        },
        handleAutoSerial() {
            // Let v-model update first
            this.$nextTick(() => {
                this.processSerial();
            });
        },
        handleSerialAutoInput(event) {
            const value = event.target.value?.trim();

            // Do nothing if empty
            if (!value) return;

            // If scanner typed fast (length threshold)
            // Adjust minimum length to your serial format
            if (value.length >= 5) {
                // Delay slightly to allow full scan string
                clearTimeout(this._serialTimer);

                this._serialTimer = setTimeout(() => {
                    this.processSerial();
                }, 150); // 150ms debounce for scanner
            }
        },
        handleSerialTyping(event) {
            const value = event.target.value?.trim();
            const idx = this.currentStep - 3;

            // Clear previous timer
            clearTimeout(this._serialTypingTimer);

            // If empty, do nothing
            if (!value) return;

            // Wait until user stops typing
            this._serialTypingTimer = setTimeout(() => {
                // Only auto proceed if still on same step
                if (this.currentStep === idx + 3) {
                    this.processSerial();
                }
            }, 300); // 🔥 300ms pause = considered "finished typing"
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

        scannerHasCapturedImage() {
            const images = this.$refs.scanner?.capturedImages || [];

            // ✅ Only count PRODUCT images (Step 2)
            const productImages = images.filter((img) => img.step === 2);

            return productImages.length >= 3;
        },

        // ✅ ADD THESE COMPUTED PROPERTIES FOR DATE CONVERSION
        localOrderDate() {
            return this.convertToLocalDate(this.item.orderdate);
        },
        localDeliveredDate: {
            get() {
                return this.convertToLocalDate(this.item.datedelivered);
            },
            set(value) {
                this.item.datedelivered = this.convertFromLocalDate(value);
            },
        },
        canProceedFromTracking() {
            const images = this.$refs.scanner?.capturedImages || [];
            return (
                this.trackingFound &&
                images.filter((img) => img.step === 1).length >= 1
            );
        },
        hasTrackingImages() {
            const images = this.$refs.scanner?.capturedImages || [];

            return (
                this.trackingFound === true &&
                images.filter((img) => img.step === 1).length >= 1
            );
        },
        currentSerialIndex() {
            return Math.max(0, Math.min(4, (this.currentStep || 4) - 4));
        },
    },
};
</script>

<style scoped>
.process-buttons {
    display: flex;
    gap: 5px;
    flex-direction: row;
}
.label-wrap {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}
.ai-switch-container {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 10px 0;
    font-size: 14px;
}

.ai-label {
    font-weight: 600;
    color: #374151;
}

.ai-status {
    font-weight: 600;
    min-width: 35px;
}

/* Switch Wrapper */
.ai-switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 24px;
}

.ai-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

/* Slider */
.ai-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #9ca3af;
    transition: 0.3s;
    border-radius: 34px;
}

.ai-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

/* ON State */
.ai-switch input:checked + .ai-slider {
    background-color: #16a34a;
}

.ai-switch input:checked + .ai-slider:before {
    transform: translateX(22px);
}

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
.continue-button {
    width: 100%;
    color: #fff;
    padding: 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    background-color: #0d6efd;
}
button.skip-button {
    flex: 1;
    color: #fff;
    border: none;
    border-radius: 4px !important;
    cursor: pointer;
    font-weight: bold;
    background-color: #f44336;
    padding: 10px;
    height: 45px;
}
button.scan-button {
    flex: 1;
    color: #fff;
    border: none;
    border-radius: 4px !important;
    cursor: pointer;
    font-weight: bold;
    background-color: #0d6efd;
    padding: 10px;
    height: 45px;
}
.input-container {
    display: flex;
    flex-direction: column;
}
.button-group.mt-4 {
    display: flex;
    gap: 10px;
}
.second-serial.scan-button,
.first-serial.scan-button {
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    width: 100%;
    padding: 10px;
}
button.back-button {
    width: 100%;
    padding: 10px;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    background-color: #f44336;
}

/* ── SweetAlert2 — Scanner duplicate-warning popups ── */
.swal-scanner-popup {
    font-family: inherit;
    border-radius: 12px;
    padding: 1.5rem;
    max-width: 420px;
}

.swal-scanner-popup .swal2-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1f2937;
}

.swal-scanner-popup .swal2-html-container {
    font-size: 0.9rem;
    color: #374151;
    text-align: left;
    margin-top: 0.5rem;
}

.swal-scanner-popup .swal2-html-container p {
    margin-bottom: 0.35rem;
}

.swal-scanner-popup .swal2-html-container .text-muted {
    color: #6b7280;
    font-size: 0.82rem;
}

/* Existing-record detail block */
.swal-detail-block {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-left: 4px solid #f59e0b;
    border-radius: 6px;
    padding: 0.6rem 0.8rem;
    margin: 0.6rem 0;
    font-size: 0.83rem;
}

.swal-detail-block p {
    margin: 0.15rem 0;
    color: #374151;
}

.swal-detail-block strong {
    color: #111827;
}

/* Action buttons row */
.swal-scanner-popup .swal2-actions {
    gap: 0.5rem;
    margin-top: 1rem;
}

.swal-scanner-popup .swal2-confirm,
.swal-scanner-popup .swal2-cancel {
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 6px;
    padding: 0.45rem 1.1rem;
}

/* ── Result banner ── */
.result-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 1.25rem;
    border: 1px solid transparent;
}
.result-banner.pass {
    background: #f0fdf4;
    color: #166534;
    border-color: #86efac;
}
.result-banner.fail {
    background: #fef2f2;
    color: #991b1b;
    border-color: #fca5a5;
}

/* ── Section header ── */
.checklist-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}
.checklist-badge {
    display: inline-block;
    background: #b9f8cf;
    color: #008236;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    padding: 3px 8px;
    border-radius: 4px;
    border: 1px solid #dcfce7;
    text-transform: uppercase;
}
.checklist-title {
    font-size: 16px;
    font-weight: 700;
    color: #1f2937;
}

/* ── Cards ── */
.checklist-card {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 14px 16px;
    margin-bottom: 10px;
}
.checklist-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 12px;
}
.checklist-card-label {
    font-size: 14px;
    font-weight: 700;
    color: #111827;
}
.checklist-default-badge {
    font-size: 11px;
    color: #6b7280;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 3px 8px;
    white-space: nowrap;
    flex-shrink: 0;
}

/* ── Option buttons ── */
.checklist-options {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.checklist-btn {
    height: 38px;
    padding: 0 16px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: #ffffff;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.checklist-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}
.checklist-btn--active-pass {
    background: #f0fdf4 !important;
    border-color: #86efac !important;
    color: #166534 !important;
}
.checklist-btn--active-fail {
    background: #fef2f2 !important;
    border-color: #fca5a5 !important;
    color: #991b1b !important;
}

/* ── Hint row ── */
.checklist-hint {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    color: #6b7280;
    margin-top: 2px;
}
.checklist-hint-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    min-width: 16px;
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 50%;
    font-size: 10px;
    font-weight: 700;
    color: #92400e;
    line-height: 1;
}

/* ── Notes textarea ── */
.checklist-notes {
    display: block;
    width: 100%;
    margin-top: 10px;
    padding: 10px 12px;
    font-size: 13px;
    font-family: inherit;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: #ffffff;
    color: #111827;
    resize: vertical;
    line-height: 1.6;
    transition: border-color 0.15s;
}
.checklist-notes:focus {
    outline: none;
    border-color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
}
.checklist-notes--placeholder,
.checklist-notes[disabled] {
    background: #f9fafb;
    color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.7;
}
</style>
