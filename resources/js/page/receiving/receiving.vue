<template>
    <div class="vue-container receiving-module">
        <!-- <div class="top-header">
            <div class="header-buttons">
                <button class="btn btn-scan" @click="openScannerModal">
                    <i class="fas fa-barcode"></i>
                    <span>Scan Items</span>
                </button>
                <button class="btn btn-manual" @click="openDetectSerialModal">
                    <i class="fas fa-keyboard"></i> Detect Serial Numbers
                </button>
                <a
                    href="{{ url('/aiTraining') }}"
                    target="_blank"
                    class="btn btn-training"
                >
                    <i class="fas fa-robot"></i> Detection Training
                </a>
            </div>
        </div> -->

        <div class="d-flex align-items-center justify-content-between flex-wrap mb-4">
            <TitlePage title="Received Management"
                subtitle="View and log inbound inventory items as they are physically received and added to stock." />

            <Button class="mx-4" @click="openScannerModal" label="Scan Items" size="small" icon="pi pi-barcode" />
        </div>


        <!-- Detect Serial Numbers Modal -->
        <detect-serial-modal v-if="showDetectSerialModal" @close="closeDetectSerialModal"
            ref="detectSerialModal"></detect-serial-modal>

        <!-- Scanner Component -->
        <scanner-component scanner-title="Received Scanner" storage-prefix="received" :enable-camera="currentStep >= 1"
            :display-fields="[
                'Trackingnumber',
                'FirstSN',
                'SecondSN',
                'PCN',
                'Basket',
            ]" :api-endpoint="'/api/received/process-scan'" :hide-button="true" @process-scan="handleScanProcess"
            @hardware-scan="handleHardwareScan" @scanner-opened="handleScannerOpened"
            @scanner-closed="handleScannerClosed" @scanner-reset="handleScannerReset" @mode-changed="handleModeChange"
            ref="scanner">
            <!-- Define custom input fields for Received module -->
            <template #input-fields>
                <!-- Step 1: Tracking Number Input -->
                <div class="input-group" v-if="currentStep === 1">
                    <label>Tracking Number:</label>
                    <input type="text" v-model="trackingNumber" placeholder="Enter Tracking Number..."
                        @input="handleTrackingInput" @keyup.enter="verifyTrackingNumber" ref="trackingInput" />
                    <!-- Only show Verify Tracking button in Manual mode -->
                    <button v-if="showManualInput" @click="verifyTrackingNumber" class="verify-button">
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
                    <div class="border-dashed uploader-area" v-show="true" @dragover.prevent
                        @dragenter.prevent="isDragging = true" @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop" @click="triggerFileInput" :class="{ 'is-dragging': isDragging }">
                        <p>
                            Drag & drop an image here, or <span class="text-highlight">click to select</span>
                        </p>
                        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFileChange"
                            :disabled="loading" />
                    </div>

                    <!-- 👇 Optional small preview (after upload) -->
                    <div v-if="imageUrl" class="uploaded-preview">
                        <img :src="imageUrl" alt="Uploaded preview" />
                        <button class="clear-upload" @click="imageUrl = null">×</button>
                    </div>

                    <!-- OCR Detected Serials (show for step 3 & 4 only) -->
                    <div v-if="
                        apiResult.step3 &&
                        apiResult.step3.serials &&
                        apiResult.step3.serials.length
                    " class="serial-results-wrapper-main">
                        <p class="text-sm text-gray-500 mb-1">
                            Detected Serials:
                        </p>
                        <div v-for="(serial, index) in apiResult.step3.serials" :key="index"
                            class="mb-3 serial-results-wrapper">
                            <div class="flex items-center gap-2 serial-result-wrap">
                                <div class="font-mono serial-result">
                                    {{ serial.text }}
                                </div>
                                <button class="px-2 py-1 bg-green-500 text-white rounded serial-btn"
                                    @click="saveSerial(serial.text, index)">
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="instruction-text">
                        Please picture the first serial number.
                    </p>

                    <label>First Serial Number:</label>
                    <input type="text" v-model="firstSerialNumber" placeholder="Scan First Serial Number..."
                        @input="handleFirstSerialInput" @keyup.enter="processFirstSerial" ref="firstSerialInput" />
                    <button v-if="showManualInput" @click="processFirstSerial" class="scan-button">
                        Scan
                    </button>
                </div>

                <!-- Step 4: Second Serial Number Input (with Skip option) -->
                <div class="input-group" v-if="currentStep === 4">
                    <div class="border-dashed uploader-area" v-show="true" @dragover.prevent
                        @dragenter.prevent="isDragging = true" @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop" @click="triggerFileInput" :class="{ 'is-dragging': isDragging }">
                        <p>
                            Drag & drop an image here, or <span class="text-highlight">click to select</span>
                        </p>
                        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFileChange"
                            :disabled="loading" />
                    </div>

                    <!-- 👇 Optional small preview (after upload) -->
                    <div v-if="imageUrl" class="uploaded-preview">
                        <img :src="imageUrl" alt="Uploaded preview" />
                        <button class="clear-upload" @click="imageUrl = null">×</button>
                    </div>

                    <div v-if="
                        apiResult.step4 &&
                        apiResult.step4.serials &&
                        apiResult.step4.serials.length
                    " class="serial-results-wrapper-main">
                        <p class="text-sm text-gray-500 mb-1">
                            Detected Serials:
                        </p>
                        <div v-for="(serial, index) in apiResult.step4.serials" :key="index"
                            class="mb-3 serial-results-wrapper">
                            <div class="flex items-center gap-2 serial-result-wrap">
                                <div class="font-mono serial-result">
                                    {{ serial.text }}
                                </div>
                                <button class="px-2 py-1 bg-green-500 text-white rounded serial-btn"
                                    @click="saveSerial(serial.text, index)">
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>
                    <label>Second Serial Number:</label>
                    <input type="text" v-model="secondSerialNumber" placeholder="Scan Second Serial Number (or Skip)..."
                        @input="handleSecondSerialInput" @keyup.enter="processSecondSerial" ref="secondSerialInput" />
                    <div class="button-group">
                        <button v-if="showManualInput" @click="processSecondSerial" class="scan-button">
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
                    <input type="text" v-model="pcnNumber" placeholder="Scan PCN Number..." @input="handlePcnInput"
                        @keyup.enter="processPcnNumber" ref="pcnInput" />
                    <div class="container-type-hint">
                        Enter PCN format: PCN followed by numbers (e.g.,
                        PCN12345)
                    </div>
                    <button v-if="showManualInput" @click="processPcnNumber" class="scan-button">
                        Scan
                    </button>
                </div>

                <!-- Step 6: Basket Number Input (now step 6) -->
                <div class="input-group" v-if="currentStep === 6">
                    <label>Basket/Container Number:</label>
                    <input type="text" v-model="basketNumber" placeholder="Enter BKT/SH/ENV + numbers..."
                        @input="handleBasketInput" @keyup.enter="processBasketNumber" ref="basketInput" />
                    <div class="container-type-hint">
                        Enter numbers with prefix: BKT (Basket), SH (Shelf), or
                        ENV (Envelope)
                    </div>
                    <button v-if="showManualInput" @click="processBasketNumber" class="scan-button">
                        Submit
                    </button>
                </div>
            </template>
        </scanner-component>

        <!-- Desktop Table Container -->
        <div class="px-4">
            <XDataTable :value="sortedInventory" :loading="loading" :columns="visibleColumns" :paginator="false"
                tableClass="desktop-view">
                <template #gallery="{ data }">
                    <div class="d-flex justify-content-center align-items-center">
                        <TableGallery :data="data" :openImageModal="openImageModal" :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages" />
                    </div>
                </template>

                <template #ProductTitle="{ data }">
                    <div class="d-flex align-items-start gap-4">
                        <div style="word-break: break-word; white-space: normal; overflow-wrap: break-word; flex: 1;">
                            <p style="font-size: .8rem;">RT# {{ data.rtcounter }}</p>
                            <p class="fw-semibold">
                                {{ data.ProductTitle }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #actions="{ data }">
                    <Button size="small" severity="contrast" variant="text" label="View Details" class="text-primary"
                        icon="pi pi-exclamation-circle" @click="openEditModal(data)" />
                </template>
            </XDataTable>
        </div>
        <!-- <div class="table-container desktop-view">
            <table>
                <thead>
                    <tr>
                        <th class="sticky-header first-col">
                            <input type="checkbox" @click="toggleAll" v-model="selectAll" />
                        </th>
                        <th class="sticky-header second-sticky">
                            <div class="product-name">
                                <span class="sortable" @click="sortBy('ProductTitle')">
                                    Product Name
                                    <i v-if="sortColumn === 'ProductTitle'" :class="sortOrder === 'asc'
                                        ? 'fas fa-sort-up'
                                        : 'fas fa-sort-down'
                                        "></i>
                                </span>
                            </div>
                        </th>
                        <th>
                            <span class="sortable" @click="sortBy('warehouselocation')">
                                Location
                                <i v-if="sortColumn === 'warehouselocation'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th>
                            <span class="sortable" @click="sortBy('datedelivered')">
                                Added date
                                <i v-if="sortColumn === 'datedelivered'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th>
                            <span class="sortable" @click="sortBy('lastDateUpdate')">
                                Updated date
                                <i v-if="sortColumn === 'lastDateUpdate'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th>
                            <span class="sortable" @click="sortBy('FNSKUviewer')">
                                Fnsku
                                <i v-if="sortColumn === 'FNSKUviewer'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th>
                            <span class="sortable" @click="sortBy('MSKUviewer')">
                                Msku
                                <i v-if="sortColumn === 'MSKUviewer'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th>
                            <span class="sortable" @click="sortBy('ASINviewer')">
                                Asin
                                <i v-if="sortColumn === 'ASINviewer'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite" v-if="showDetails">
                            <span class="sortable" @click="sortBy('FBMAvailable')">
                                FBM
                                <i v-if="sortColumn === 'FBMAvailable'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite" v-if="showDetails">
                            <span class="sortable" @click="sortBy('FbaAvailable')">
                                FBA
                                <i v-if="sortColumn === 'FbaAvailable'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite" v-if="showDetails">
                            <span class="sortable" @click="sortBy('Outbound')">
                                Outbound
                                <i v-if="sortColumn === 'Outbound'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite" v-if="showDetails">
                            <span class="sortable" @click="sortBy('Inbound')">
                                Inbound
                                <i v-if="sortColumn === 'Inbound'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite" v-if="showDetails">
                            <span class="sortable" @click="sortBy('Reserved')">
                                Reserved
                                <i v-if="sortColumn === 'Reserved'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite" v-if="showDetails">
                            <span class="sortable" @click="sortBy('Unfulfillable')">
                                Unfulfillable
                                <i v-if="sortColumn === 'Unfulfillable'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th>
                            <span class="sortable" @click="sortBy('Fulfilledby')">
                                Fulfillment
                                <i v-if="sortColumn === 'Fulfilledby'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th>
                            <span class="sortable" @click="sortBy('Status')">
                                Status
                                <i v-if="sortColumn === 'Status'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th>
                            <span class="sortable" @click="sortBy('serialnumber')">
                                Serialnumber
                                <i v-if="sortColumn === 'serialnumber'" :class="sortOrder === 'asc'
                                    ? 'fas fa-sort-up'
                                    : 'fas fa-sort-down'
                                    "></i>
                            </span>
                        </th>
                        <th class="">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="12" class="text-center">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                                Loading...
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="sortedInventory.length === 0">
                        <td colspan="12" class="text-center">No data found</td>
                    </tr>
                    <template v-else v-for="(item, index) in sortedInventory" :key="item.id">
                        <tr>
                            <td class="sticky-col first-col">
                                <input type="checkbox" v-model="item.checked" />
                                <span class="placeholder-date">{{
                                    item.shipBy || ""
                                    }}</span>
                            </td>
                            <td class="sticky-col second-sticky">
                                <div class="product-container">
                                    <div class="product-image-container" @click="openImageModal(item)">
                                        <img :src="'/images/thumbnails/' +
                                            item.img1
                                            " :alt="item.ProductTitle || 'Product'
                                                " class="product-thumbnail clickable-image"
                                            @error="handleImageError($event)" />
                                        <div class="image-count-badge" v-if="
                                            countAdditionalImages(item) > 0
                                        ">
                                            +{{ countAdditionalImages(item) }}
                                        </div>
                                    </div>
                                    <div class="product-info">
                                        <p>RT# : {{ item.rtcounter }}</p>
                                        <p>{{ item.ProductTitle }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span><strong></strong>
                                    {{ item.warehouselocation }}</span>
                            </td>

                            <td>
                                <span><strong></strong>
                                    {{ item.datedelivered }}</span>
                            </td>

                            <td>
                                <span><strong></strong>
                                    {{ item.lastDateUpdate }}</span>
                            </td>

                            <td>
                                <span><strong></strong>
                                    {{ item.FNSKUviewer }}</span>
                            </td>

                            <td>
                                <span><strong></strong>
                                    {{ item.MSKUviewer }}</span>
                            </td>
                            <td>
                                <span><strong></strong>
                                    {{ item.ASINviewer }}</span>
                            </td>
                       
                            <td v-if="showDetails">
                                <span><strong></strong>
                                    {{ item.FBMAvailable }}</span>
                            </td>
                            <td v-if="showDetails">
                                <span><strong></strong>
                                    {{ item.FbaAvailable }}</span>
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
                                <span><strong></strong>
                                    {{ item.Unfulfillable }}</span>
                            </td>
            
                            <td>
                                <span><strong></strong>
                                    {{ item.Fulfilledby }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.Status }}</span>
                            </td>

                            <td>
                                <span><strong></strong>
                                    {{ item.serialnumber }}</span>
                            </td>

              
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-edit" @click="openEditModal(item)">
                                        <i class="fas fa-info-circle"></i>
                                        View Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div> -->

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <MobileCard1 :data="item" :showDetails="showDetails" :sortedInventory="sortedInventory"
                :expandedRows="expandedRows" :openImageModal="openImageModal" :handleImageError="handleImageError"
                :countAdditionalImages="countAdditionalImages" :openEditModal="openEditModal" :loading="loading" />
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

        <!-- Image Modal -->
        <div v-if="showImageModal" class="modal image-modal ">
            <div class="modal-overlay" @click="closeImageModal"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <div class="productTitle">
                        <h2>{{ ProductTitle }}</h2>
                    </div>
                    <button class="btn btn-modal-close" @click="closeImageModal">
                        &times;
                    </button>
                </div>

                <div class="modal-body">
                    <div class="main-image-container">
                        <button class="nav-button prev" @click="prevImage" v-if="imageList.length > 1">
                            <i class="bi bi-arrow-left-short"></i>
                        </button>
                        <img :src="activeImageUrl" alt="Main Product Image" class="modal-main-image" loading="lazy"
                            width="100%" @error="onImageErrorMain" />
                        <button class="nav-button next" @click="nextImage" v-if="imageList.length > 1">
                            <i class="bi bi-arrow-right-short"></i>
                        </button>
                    </div>

                    <div class="image-counter">
                        {{ activeIndex + 1 }} / {{ imageList.length }}
                    </div>

                    <div class="thumbnail-container" v-if="imageList.length > 1">
                        <div v-for="(img, index) in imageList" :key="index" class="modal-thumbnail" :class="[
                            'thumbnail',
                            {
                                active: index === activeIndex,
                            },
                        ]" @click="activeIndex = index" @mouseenter="activeIndex = index">
                            <img :src="basePath + img" alt="Thumbnail" loading="lazy"
                                @error="onThumbnailError($event)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="showEditModal" modal :style="{ width: '95%' }" :pt="{
            root: { class: 'mobile-fullscreen-dialog' }
        }">
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
                                <h5 class="text-primary fw-bolder">Description</h5>
                            </template>
                            <template #content>
                                <p style="word-break: break-all; max-height: 450px; overflow-y: auto; font-size: 14px;">
                                    {{
                                        item.description }}</p>
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
                                    <h3 class="text-primary fw-bolder">Warehouse & Tracking</h3>
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

                                <!-- Product Identifiers -->
                                <section class="info-section">
                                    <h3 class="text-primary fw-bolder">Product Identifiers</h3>
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
                                    <h3 class="text-primary fw-bolder">Order Information</h3>
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
                                                {{ item.orderdate }}
                                            </dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>Delivered Date:</dt>
                                            <dd>
                                                {{ item.datedelivered }}
                                            </dd>
                                        </div>
                                        <div class="info-item">
                                            <dt>Seller:</dt>
                                            <dd>{{ item.seller }}</dd>
                                        </div>
                                    </dl>
                                </section>


                                <!-- Additional Info -->
                                <section class="info-section" v-if="item.grading || item.notes">
                                    <h3 class="text-primary fw-bolder">Additional Info</h3>
                                    <dl class="info-list">
                                        <div class="info-item" v-if="item.grading">
                                            <dt>Grading:</dt>
                                            <dd>{{ item.grading }}</dd>
                                        </div>
                                        <div class="info-item" v-if="item.notes">
                                            <dt>Notes:</dt>
                                            <dd>{{ item.notes }}</dd>
                                        </div>
                                    </dl>
                                </section>
                            </div>

                            <!-- Right Column: Pricing -->
                            <div class="col-md-6">
                                <section class="pricing-section">
                                    <h3 class="text-primary fw-bolder">Pricing</h3>
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
                                                {{
                                                    item.price || "0.00"
                                                }}
                                            </dd>
                                        </div>

                                        <!-- Adjustments -->
                                        <div class="pricing-item" v-if="item.Discount">
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
                                        <div class="pricing-item refund-line" v-if="item.refund">
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
                                        <h5 class="text-primary fw-bolder">Description</h5>
                                    </template>
                                    <template #content>
                                        <p style="word-break: break-all; max-height: 450px; overflow-y: auto;">{{
                                            item.description }}</p>
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
                                            <h3 class="text-primary fw-bolder">Product Identifiers</h3>
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
                                            <h3 class="text-primary fw-bolder">Order Information</h3>
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
                                                        {{ item.orderdate }}
                                                    </dd>
                                                </div>
                                                <div class="info-item">
                                                    <dt>Delivered Date:</dt>
                                                    <dd>
                                                        {{ item.datedelivered }}
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
                                            <h3 class="text-primary fw-bolder">Warehouse & Tracking</h3>
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
                                        <section class="info-section" v-if="item.grading || item.notes">
                                            <h3 class="text-primary fw-bolder">Additional Info</h3>
                                            <dl class="info-list">
                                                <div class="info-item" v-if="item.grading">
                                                    <dt>Grading:</dt>
                                                    <dd>{{ item.grading }}</dd>
                                                </div>
                                                <div class="info-item" v-if="item.notes">
                                                    <dt>Notes:</dt>
                                                    <dd>{{ item.notes }}</dd>
                                                </div>
                                            </dl>
                                        </section>
                                    </div>

                                    <!-- Right Column: Pricing -->
                                    <div class="col-md-6">
                                        <section class="pricing-section">
                                            <h3 class="text-primary fw-bolder">Pricing</h3>
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
                                                        {{
                                                            item.price || "0.00"
                                                        }}
                                                    </dd>
                                                </div>

                                                <!-- Adjustments -->
                                                <div class="pricing-item" v-if="item.Discount">
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
                                                <div class="pricing-item refund-line" v-if="item.refund">
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

<script>
import { Button, Dialog, Card, ScrollTop } from "primevue";
import Received from "./receiving.js";
import gallery from "../../components/Gallery/gallery.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import MobileCard1 from "../../components/MobileCard1/MobileCard1.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";

const TABLE_COLUMNS = [
    {
        selectionMode: "multiple",
        header: "",
        style: { width: "3rem", minWidth: "3rem" },
        headerStyle: "width: 3rem; min-width: 3rem; max-width: 3rem; padding: 0.25rem;",
        bodyStyle: "width: 3rem; min-width: 3rem; max-width: 3rem; padding: 0.25rem;",
    },
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
    { field: "datedelivered", header: "Added Date", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "lastDateUpdate", header: "Updated Date", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "FNSKUviewer", header: "Fnsku", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "MSKUviewer", header: "Msku", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "ASINviewer", header: "Asin", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "FBMAvailable", header: "FBM", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "FbaAvailable", header: "FBA", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Outbound", header: "Outbound", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Inbound", header: "Inbound", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Reserved", header: "Reserved", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Unfulfillable", header: "Unfulfillable", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Fulfilledby", header: "Fulfillment", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "Status", header: "Status", sortable: true, bodyStyle: "font-size: 14px;" },
    { field: "serialNumber", header: "Serial Number", sortable: true, bodyStyle: "font-size: 14px;" },
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
        TitlePage
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
        };
    },
    computed: {
        visibleColumns() {
            if (!this.columns) return [];

            //columns can be showed or hidden
            const detailFields = ["FBMAvailable", "FbaAvailable", "Outbound", "Inbound", "Reserved", "Unfulfillable"];


            return this.columns.filter(col => {
                if (!this.showDetails && detailFields.includes(col.field)) {
                    return false;
                }
                return true;
            });
        },
    },
};
</script>
