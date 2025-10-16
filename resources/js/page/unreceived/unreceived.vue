<template>
    <div class="vue-container unreceived-module">
        <div class="top-header">
            <div class="header-buttons">
                <button class="btn btn-scan" @click="openScannerModal">
                    <i class="fas fa-barcode"></i>
                    <span>Scan Items</span>
                </button>
            </div>
        </div>

        <h2 class="module-title">Unreceived Module</h2>

        <!-- Simplified Scanner Component -->
        <scanner-component
            scanner-title="Unreceived Scanner"
            storage-prefix="unreceived"
            :enable-camera="true"
            :display-fields="['Trackingnumber', 'RPN', 'PRD', 'Status']"
            :api-endpoint="'/api/unreceived/process-scan'"
            :hide-button="true"
            @process-scan="handleScanProcess"
            @hardware-scan="handleHardwareScan"
            @scanner-opened="handleScannerOpened"
            @scanner-closed="handleScannerClosed"
            @scanner-reset="handleScannerReset"
            @mode-changed="handleModeChange"
            ref="scanner"
        >
            <!-- Simplified input - only tracking number needed -->
            <template #input-fields>
                <div class="input-group">
                    <label>Tracking Number:</label>
                    <input
                        type="text"
                        v-model="trackingNumber"
                        placeholder="Enter Tracking Number (RPN & PRD will be auto-generated)..."
                        @input="handleTrackingInput"
                        @keyup.enter="verifyAndProcessTracking"
                        ref="trackingInput"
                    />
                    <!-- Only show manual process button in Manual mode -->
                    <button
                        v-if="showManualInput"
                        @click="verifyAndProcessTracking"
                        class="verify-button"
                    >
                        Process Tracking
                    </button>
                    <div class="scanner-info">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            RPN and PRD (today's date) will be automatically
                            generated
                        </small>
                    </div>
                </div>
            </template>
        </scanner-component>

        <!-- Desktop Table Container -->
        <div class="table-container desktop-view">
            <table>
                <thead>
                    <tr>
                        <th class="sticky-header first-col">
                            <input
                                type="checkbox"
                                @click="toggleAll"
                                v-model="selectAll"
                            />
                        </th>
                        <th class="sticky-header second-sticky">
                            <div class="product-name">
                                <span
                                    class="sortable"
                                    @click="sortBy('ProductTitle')"
                                >
                                    Product Name
                                    <i
                                        v-if="sortColumn === 'ProductTitle'"
                                        :class="
                                            sortOrder === 'asc'
                                                ? 'fas fa-sort-up'
                                                : 'fas fa-sort-down'
                                        "
                                    ></i>
                                </span>
                            </div>
                        </th>
                        <th>
                            <span
                                class="sortable"
                                @click="sortBy('warehouselocation')"
                            >
                                Location
                                <i
                                    v-if="sortColumn === 'warehouselocation'"
                                    :class="
                                        sortOrder === 'asc'
                                            ? 'fas fa-sort-up'
                                            : 'fas fa-sort-down'
                                    "
                                ></i>
                            </span>
                        </th>
                        <th>
                            <span
                                class="sortable"
                                @click="sortBy('datedelivered')"
                            >
                                Added date
                            </span>
                            <i
                                v-if="sortColumn === 'datedelivered'"
                                :class="
                                    sortOrder === 'asc'
                                        ? 'fas fa-sort-up'
                                        : 'fas fa-sort-down'
                                "
                            ></i>
                        </th>
                        <th>
                            <span
                                class="sortable"
                                @click="sortBy('lastDateUpdate')"
                            >
                                Updated date
                            </span>
                            <i
                                v-if="sortColumn === 'lastDateUpdate'"
                                :class="
                                    sortOrder === 'asc'
                                        ? 'fas fa-sort-up'
                                        : 'fas fa-sort-down'
                                "
                            ></i>
                        </th>
                        <th>
                            <span
                                class="sortable"
                                @click="sortBy('FNSKUviewer')"
                            >
                                Fnsku
                            </span>
                            <i
                                v-if="sortColumn === 'FNSKUviewer'"
                                :class="
                                    sortOrder === 'asc'
                                        ? 'fas fa-sort-up'
                                        : 'fas fa-sort-down'
                                "
                            ></i>
                        </th>
                        <th>
                            <span
                                class="sortable"
                                @click="sortBy('MSKUviewer')"
                            >
                                Msku
                            </span>
                            <i
                                v-if="sortColumn === 'MSKUviewer'"
                                :class="
                                    sortOrder === 'asc'
                                        ? 'fas fa-sort-up'
                                        : 'fas fa-sort-down'
                                "
                            ></i>
                        </th>
                        <th>
                            <span
                                class="sortable"
                                @click="sortBy('ASINviewer')"
                            >
                                Asin
                            </span>
                            <i
                                v-if="sortColumn === 'ASINviewer'"
                                :class="
                                    sortOrder === 'asc'
                                        ? 'fas fa-sort-up'
                                        : 'fas fa-sort-down'
                                "
                            ></i>
                        </th>
                        <th
                            class="bg-warning-subtle"
                            style="background-color: antiquewhite"
                            v-if="showDetails"
                        >
                            <span
                                class="sortable"
                                @click="sortBy('FBMAvailable')"
                            >
                                FBM
                                <i
                                    v-if="sortColumn === 'FBMAvailable'"
                                    :class="
                                        sortOrder === 'asc'
                                            ? 'fas fa-sort-up'
                                            : 'fas fa-sort-down'
                                    "
                                ></i>
                            </span>
                        </th>
                        <th
                            class="bg-warning-subtle"
                            style="background-color: antiquewhite"
                            v-if="showDetails"
                        >
                            <span
                                class="sortable"
                                @click="sortBy('FbaAvailable')"
                            >
                                FBA
                                <i
                                    v-if="sortColumn === 'FbaAvailable'"
                                    :class="
                                        sortOrder === 'asc'
                                            ? 'fas fa-sort-up'
                                            : 'fas fa-sort-down'
                                    "
                                ></i>
                            </span>
                        </th>
                        <th
                            class="bg-warning-subtle"
                            style="background-color: antiquewhite"
                            v-if="showDetails"
                        >
                            <span class="sortable" @click="sortBy('Outbound')"
                                >Outbound
                                <i
                                    v-if="sortColumn === 'Outbound'"
                                    :class="
                                        sortOrder === 'asc'
                                            ? 'fas fa-sort-up'
                                            : 'fas fa-sort-down'
                                    "
                                ></i>
                            </span>
                        </th>
                        <th
                            class="bg-warning-subtle"
                            style="background-color: antiquewhite"
                            v-if="showDetails"
                        >
                            <span class="sortable" @click="sortBy('Inbound')"
                                >Inbound
                                <i
                                    v-if="sortColumn === 'Inbound'"
                                    :class="
                                        sortOrder === 'asc'
                                            ? 'fas fa-sort-up'
                                            : 'fas fa-sort-down'
                                    "
                                ></i>
                            </span>
                        </th>
                        <th
                            class="bg-warning-subtle"
                            style="background-color: antiquewhite"
                            v-if="showDetails"
                        >
                            <span
                                class="sortable"
                                @click="sortBy('Unfulfillable')"
                                >Unfulfillable
                                <i
                                    v-if="sortColumn === 'Unfulfillable'"
                                    :class="
                                        sortOrder === 'asc'
                                            ? 'fas fa-sort-up'
                                            : 'fas fa-sort-down'
                                    "
                                ></i>
                            </span>
                        </th>
                        <th
                            class="bg-warning-subtle"
                            style="background-color: antiquewhite"
                            v-if="showDetails"
                        >
                            <span class="sortable" @click="sortBy('Reserved')"
                                >Reserved
                                <i
                                    v-if="sortColumn === 'Reserved'"
                                    :class="
                                        sortOrder === 'asc'
                                            ? 'fas fa-sort-up'
                                            : 'fas fa-sort-down'
                                    "
                                ></i>
                            </span>
                        </th>
                        <th>
                            <span
                                class="sortable"
                                @click="sortBy('Fulfilledby')"
                            >
                                Fulfillment
                                <i
                                    v-if="sortColumn === 'Fulfilledby'"
                                    :class="
                                        sortOrder === 'asc'
                                            ? 'fas fa-sort-up'
                                            : 'fas fa-sort-down'
                                    "
                                ></i>
                            </span>
                        </th>
                        <th>
                            <span class="sortable" @click="sortBy('Status')">
                                Status
                                <i
                                    v-if="sortColumn === 'Status'"
                                    :class="
                                        sortOrder === 'asc'
                                            ? 'fas fa-sort-up'
                                            : 'fas fa-sort-down'
                                    "
                                ></i>
                            </span>
                        </th>
                        <th>
                            <span
                                class="sortable"
                                @click="sortBy('serialnumber')"
                            >
                                Serialnumber
                                <i
                                    v-if="sortColumn === 'serialnumber'"
                                    :class="
                                        sortOrder === 'asc'
                                            ? 'fas fa-sort-up'
                                            : 'fas fa-sort-down'
                                    "
                                ></i>
                            </span>
                        </th>
                        <th class="">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td
                            :colspan="showDetails ? 18 : 12"
                            class="text-center"
                        >
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                                Loading...
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="sortedInventory.length === 0">
                        <td
                            :colspan="showDetails ? 18 : 12"
                            class="text-center"
                        >
                            No data found
                        </td>
                    </tr>
                    <template
                        v-else
                        v-for="(item, index) in sortedInventory"
                        :key="item.id"
                    >
                        <tr>
                            <td class="sticky-col first-col">
                                <input type="checkbox" v-model="item.checked" />
                                <span class="placeholder-date">{{
                                    item.shipBy || ""
                                }}</span>
                            </td>
                            <td class="sticky-col second-sticky">
                                <div class="product-container">
                                    <div
                                        class="product-image-container"
                                        @click="openImageModal(item)"
                                    >
                                        <img
                                            :src="
                                                '/images/thumbnails/' +
                                                item.img1
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
                                    <div class="product-info">
                                        <p>RT# : {{ item.rtcounter }}</p>
                                        <p>{{ item.ProductTitle }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.warehouselocation }}</span
                                >
                            </td>

                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.datedelivered }}</span
                                >
                            </td>

                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.lastDateUpdate }}</span
                                >
                            </td>

                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.FNSKUviewer }}</span
                                >
                            </td>

                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.MSKUviewer }}</span
                                >
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.ASINviewer }}</span
                                >
                            </td>
                            <!-- Hidden columns -->
                            <td v-if="showDetails">
                                <span
                                    ><strong></strong>
                                    {{ item.FBMAvailable }}</span
                                >
                            </td>
                            <td v-if="showDetails">
                                <span
                                    ><strong></strong>
                                    {{ item.FbaAvailable }}</span
                                >
                            </td>
                            <td v-if="showDetails">
                                <span
                                    ><strong></strong> {{ item.Outbound }}</span
                                >
                            </td>
                            <td v-if="showDetails">
                                <span
                                    ><strong></strong> {{ item.Inbound }}</span
                                >
                            </td>
                            <td v-if="showDetails">
                                <span
                                    ><strong></strong>
                                    {{ item.Unfulfillable }}</span
                                >
                            </td>
                            <td v-if="showDetails">
                                <span
                                    ><strong></strong> {{ item.Reserved }}</span
                                >
                            </td>
                            <!-- End Hidden columns -->
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.Fulfilledby }}</span
                                >
                            </td>

                            <td>
                                <span><strong></strong> {{ item.Status }}</span>
                            </td>

                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.serialnumber }}</span
                                >
                            </td>

                            <!-- Actions -->
                            <td>
                                <div class="action-buttons">
                                    <button
                                        class="btn btn-edit"
                                        @click="openEditModal(item)"
                                    >
                                        <i class="fas fa-info-circle"></i>
                                        <span>View Details</span>
                                    </button>
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
                    class="mobile-card"
                    v-else
                    v-for="(item, index) in sortedInventory"
                    :key="item.id"
                >
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <div class="mobile-product-image clickable">
                            <img
                                :src="'/images/thumbnails/' + item.img1"
                                :alt="item.ProductTitle || 'Product'"
                                class="product-thumbnail clickable-image"
                                @error="handleImageError($event)"
                                @click="openImageModal(item)"
                            />
                            <div
                                class="image-count-badge"
                                v-if="countAdditionalImages(item) > 0"
                            >
                                +{{ countAdditionalImages(item) }}
                            </div>
                        </div>
                        <div class="mobile-product-info">
                            <h3 class="mobile-product-name clickable">
                                <p>RT# : {{ item.rtcounter }}</p>
                                <p>{{ item.ProductTitle }}</p>
                            </h3>
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Location:</span>
                            <span class="mobile-detal-value">
                                {{ item.warehouselocation }}</span
                            >
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Added date:</span>
                            <span class="mobile-detal-value">
                                {{ item.datedelivered }}</span
                            >
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label"
                                >Updated date:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.lastDateUpdate }}</span
                            >
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">FNSKU:</span>
                            <span class="mobile-detal-value">
                                {{ item.FNSKUviewer }}</span
                            >
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">MSKU:</span>
                            <span class="mobile-detal-value">
                                {{ item.MSKUviewer }}</span
                            >
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detal-value">
                                {{ item.ASINviewer }}</span
                            >
                        </div>
                        <!-- Hidden details -->
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">FBM:</span>
                            <span class="mobile-detal-value">
                                {{ item.FBMAvailable }}</span
                            >
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">FBA:</span>
                            <span class="mobile-detal-value">
                                {{ item.FbaAvailable }}</span
                            >
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">Outbound:</span>
                            <span class="mobile-detal-value">
                                {{ item.Outbound }}</span
                            >
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">Inbound:</span>
                            <span class="mobile-detal-value">
                                {{ item.Inbound }}</span
                            >
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label"
                                >Unfulfillable:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.Unfulfillable }}</span
                            >
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">Reserved:</span>
                            <span class="mobile-detal-value">
                                {{ item.Reserved }}</span
                            >
                        </div>
                        <!-- End hidden details -->
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label"
                                >Fullfilment:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.Fulfilledby }}</span
                            >
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Status:</span>
                            <span class="mobile-detal-value">
                                {{ item.status }}</span
                            >
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label"
                                >Serial Number:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.serialnumber }}</span
                            >
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-actions">
                        <button
                            class="btn btn-details"
                            @click="openEditModal(item)"
                        >
                            <i class="fas fa-info-circle"></i>
                            <span>View Details</span>
                        </button>
                    </div>

                    <hr v-if="expandedRows[index]" />

                    <div
                        v-if="expandedRows[index]"
                        class="mobile-expanded-content"
                    >
                        <p><strong>Expanded Rows Here</strong></p>
                        <p><strong>Product Name:</strong> {{ item.AStitle }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Image Modal -->
        <div
            v-if="showImageModal"
            class="image-modal-overlay"
            @click="closeImageModal"
        >
            <div class="image-modal-content" @click.stop>
                <button class="modal-close-btn" @click="closeImageModal">
                    <i class="fas fa-times"></i>
                </button>

                <div class="modal-image-container">
                    <button
                        v-if="modalImages.length > 1"
                        class="modal-nav-btn prev-btn"
                        @click="prevImage"
                    >
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <img
                        :src="modalImages[currentImageIndex]"
                        alt="Product Image"
                        class="modal-image"
                        @error="handleImageError($event)"
                    />

                    <button
                        v-if="modalImages.length > 1"
                        class="modal-nav-btn next-btn"
                        @click="nextImage"
                    >
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <div v-if="modalImages.length > 1" class="modal-image-counter">
                    {{ currentImageIndex + 1 }} / {{ modalImages.length }}
                </div>
            </div>
        </div>

        <!-- Pagination with centered layout -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <select
                        v-model="perPage"
                        @change="changePerPage"
                        class="per-page-select"
                    >
                        <option
                            v-for="option in [10, 15, 20, 50, 100]"
                            :key="option"
                            :value="option"
                        >
                            {{ option }}
                        </option>
                    </select>
                </div>

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
        <div v-if="showImageModal" class="modal image-modal">
            <div class="modal-overlay" @click="closeImageModal"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <div class="productTitle">
                        <h2>{{ ProductTitle }}</h2>
                    </div>
                    <button
                        class="btn btn-modal-close"
                        @click="closeImageModal"
                    >
                        &times;
                    </button>
                </div>

                <div class="modal-body">
                    <div class="main-image-container">
                        <button
                            class="nav-button prev"
                            @click="prevImage"
                            v-if="imageList.length > 1"
                        >
                            <i class="bi bi-arrow-left-short"></i>
                        </button>
                        <img
                            :src="activeImageUrl"
                            alt="Main Product Image"
                            class="modal-main-image"
                            loading="lazy"
                            width="100%"
                            @error="onImageErrorMain"
                        />
                        <button
                            class="nav-button next"
                            @click="nextImage"
                            v-if="imageList.length > 1"
                        >
                            <i class="bi bi-arrow-right-short"></i>
                        </button>
                    </div>

                    <div class="image-counter">
                        {{ activeIndex + 1 }} / {{ imageList.length }}
                    </div>

                    <div
                        class="thumbnail-container"
                        v-if="imageList.length > 1"
                    >
                        <div
                            v-for="(img, index) in imageList"
                            :key="index"
                            class="modal-thumbnail"
                            :class="[
                                'thumbnail',
                                {
                                    active: index === activeIndex,
                                },
                            ]"
                            @click="activeIndex = index"
                            @mouseenter="activeIndex = index"
                        >
                            <img
                                :src="basePath + img"
                                alt="Thumbnail"
                                loading="lazy"
                                @error="onThumbnailError($event)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showEditModal" class="modal view-modal">
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
                                <div
                                    class="image-section"
                                    v-if="imageList.length"
                                >
                                    <!-- Main Image -->
                                    <div class="main-image">
                                        <img
                                            :src="activeImageUrl"
                                            alt="Main Product Image"
                                            loading="lazy"
                                            @error="onImageErrorMain"
                                        />
                                    </div>

                                    <!-- Thumbnails -->
                                    <div class="thumbnail-carousel">
                                        <div
                                            v-for="(img, index) in imageList"
                                            :key="index"
                                            :class="[
                                                'thumbnail',
                                                {
                                                    active:
                                                        index === activeIndex,
                                                },
                                            ]"
                                            @click="activeIndex = index"
                                            @mouseenter="activeIndex = index"
                                        >
                                            <img
                                                :src="basePath + img"
                                                alt="Thumbnail"
                                                loading="lazy"
                                                @error="
                                                    onThumbnailError($event)
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="description-section">
                                    <h3>Description</h3>
                                    <p>{{ item.description }}</p>
                                </div>
                            </div>

                            <!-- RIGHT: DETAILS -->
                            <div class="form-col-right">
                                <div class="row">
                                    <!-- Left Column -->
                                    <div class="col-md-6">
                                        <!-- Product Identifiers -->
                                        <section class="info-section">
                                            <h3>Product Identifiers</h3>
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
                                            <h3>Order Information</h3>
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
                                            <h3>Warehouse & Tracking</h3>
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
                                            <h3>Additional Info</h3>
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
                                            <h3>Pricing</h3>
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
    </div>
</template>

<script>
import Unreceived from "./unreceived.js";
export default Unreceived;
</script>
