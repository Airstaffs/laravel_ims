<template>
    <div class="vue-container labeling-module">
        <!-- <div class="top-header">
            <span>Top Header</span>
        </div> -->

        <h2 class="module-title">Labeling Module</h2>

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
                                    @click="sortBy('AStitle')"
                                >
                                    Product Name
                                    <i
                                        v-if="sortColumn === 'AStitle'"
                                        :class="
                                            sortOrder === 'asc'
                                                ? 'fas fa-sort-up'
                                                : 'fas fa-sort-down'
                                        "
                                    ></i>
                                </span>
                            </div>
                        </th>
                        <th class="">Serial Number</th>
                        <th class="">ASIN</th>
                        <th class="">FNSKU</th>
                        <th class="">Tracking Number</th>
                        <th class="">Quantity</th>
                        <th class="">Date Delivered</th>
                        <th class="">Actions</th>
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
                    <tr v-else-if="sortedInventory.length === 0">
                        <td colspan="9" class="text-center">No data found</td>
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
                                        <!-- Use the actual file path for the main image -->
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
                                            v-if="countAllImages(item) > 0"
                                        >
                                            +{{ countAllImages(item) }}
                                        </div>
                                    </div>
                                    <div class="product-info clickable">
                                        <p>RT# : {{ item.rtcounter }}</p>
                                        <p>{{ item.ProductTitle }}</p>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span>
                                    {{ item.serialnumber }}
                                </span>
                            </td>
                            <td>
                                <span>
                                    {{ item.ASIN }}
                                </span>
                            </td>
                            <td>
                                <span>
                                    {{ item.FNSKUviewer }}
                                </span>
                            </td>
                            <td>
                                <span>
                                    {{ item.trackingnumber }}
                                </span>
                            </td>
                            <td>
                                <span> {{ item.quantity }} unit </span>
                            </td>
                            <td>
                                <span>
                                    {{ item.datedelivered }}
                                </span>
                            </td>

                            <!-- Button for more details -->
                            <td>
                                <div class="action-buttons">
                                    <span>
                                        <strong></strong>
                                        {{ item.actions }}
                                    </span>
                                    <button
                                        @click="showFnskuModal(item)"
                                        class="btn btn-fnsku"
                                    >
                                        <i class="bi bi-clipboard-check"></i>
                                        SET FNSKU
                                    </button>
                                    <!--split -->
                                    <button
                                        @click="confirmSplitItem(item)"
                                        class="btn btn-split"
                                        :disabled="
                                            isProcessing || !canSplit(item)
                                        "
                                        :title="
                                            !canSplit(item)
                                                ? 'Cannot split - quantity must be greater than 1'
                                                : 'Split into individual items'
                                        "
                                    >
                                        <i class="bi bi-scissors"></i> Split
                                    </button>

                                    <button
                                        @click="confirmMoveToValidation(item)"
                                        class="btn btn-validation"
                                        :disabled="isProcessing"
                                    >
                                        <i class="bi bi-check-circle"></i> Move
                                        to Validation
                                    </button>

                                    <button
                                        @click="confirmMoveToStockroom(item)"
                                        class="btn btn-stockroom"
                                        :disabled="isProcessing"
                                    >
                                        <i class="bi bi-box-seam"></i> Move to
                                        Stockroom
                                    </button>

                                    <!-- ADD THIS COPY DETAILS BUTTON -->
                                    <button
                                        @click="openCopyDetailsModal(item)"
                                        class="btn btn-copy-details"
                                        title="Copy product details"
                                    >
                                        <i class="bi bi-clipboard"></i> Copy
                                        Details
                                    </button>

                                    <button
                                        @click="openEditModal(item)"
                                        class="btn btn-edit"
                                    >
                                        <i class="bi bi-pencil"></i>Edit
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
            <button class="btn-showDetailsM" @click="toggleDetailsVisibility">
                {{ showDetails ? "Hide extra columns" : "Show extra columns" }}
            </button>

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
                                v-if="countAllImages(item) > 0"
                            >
                                +{{ countAllImages(item) }}
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
                        <!-- Insert Hidden Here -->
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
                        <!--  -->
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
                        <!-- <span><strong></strong> {{ item.actions }}</span> -->
                        <button
                            @click="showFnskuModal(item)"
                            class="btn btn-fnsku"
                        >
                            <i class="bi bi-clipboard-check"></i> SET FNSKU
                        </button>

                        <button
                            @click="confirmMoveToValidation(item)"
                            class="btn btn-validation"
                            :disabled="isProcessing"
                        >
                            <i class="bi bi-check-circle"></i> Move to
                            Validation
                        </button>

                        <button
                            @click="confirmMoveToStockroom(item)"
                            class="btn btn-stockroom"
                            :disabled="isProcessing"
                        >
                            <i class="bi bi-box-seam"></i> Move to Stockroom
                        </button>

                        <!-- ADD THIS COPY DETAILS BUTTON -->
                        <button
                            @click="openCopyDetailsModal(item)"
                            class="btn btn-copy-details"
                            title="Copy product details"
                        >
                            <i class="bi bi-clipboard"></i> Copy Details
                        </button>

                        <button
                            @click="openEditModal(item)"
                            class="btn btn-edit"
                        >
                            <i class="bi bi-pencil"></i>Edit
                        </button>
                    </div>
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

        <!-- Image Modal with Tabs -->
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
                    <!-- Tabs for switching between regular and captured images -->
                    <div class="image-tabs">
                        <button
                            class="tab-button"
                            :class="{ active: activeTab === 'regular' }"
                            @click="switchTab('regular')"
                            :disabled="regularImages.length === 0"
                        >
                            <span>Product Images</span>
                            <span class="badge img-badge">{{
                                regularImages.length
                            }}</span>
                        </button>
                        <button
                            class="tab-button"
                            :class="{ active: activeTab === 'captured' }"
                            @click="switchTab('captured')"
                            :disabled="capturedImages.length === 0"
                        >
                            <span>Captured Images</span>
                            <span class="badge img-badge">{{
                                capturedImages.length
                            }}</span>
                        </button>
                    </div>

                    <!-- Display message if no images in current category -->
                    <div
                        v-if="currentImageSet.length === 0"
                        class="no-images-message"
                    >
                        No images available in this category
                    </div>

                    <!-- Main image display (only shown if we have images) -->
                    <div
                        v-if="currentImageSet.length > 0"
                        class="main-image-container"
                    >
                        <button
                            class="nav-button prev"
                            @click="prevImage"
                            v-if="currentImageSet.length > 1"
                        >
                            <i class="bi bi-arrow-left-short"></i>
                        </button>
                        <img
                            :src="currentImageSet[currentImageIndex]"
                            alt="Product Image"
                            class="modal-main-image"
                            @error="handleImageError"
                        />
                        <button
                            class="nav-button next"
                            @click="nextImage"
                            v-if="currentImageSet.length > 1"
                        >
                            <i class="bi bi-arrow-right-short"></i>
                        </button>
                    </div>

                    <div
                        class="image-counter"
                        v-if="currentImageSet.length > 0"
                    >
                        {{ currentImageIndex + 1 }} /
                        {{ currentImageSet.length }}
                    </div>

                    <!-- Thumbnails for the current image set -->
                    <div
                        class="thumbnails-container"
                        v-if="currentImageSet.length > 1"
                    >
                        <div
                            v-for="(image, index) in currentImageSet"
                            :key="index"
                            class="modal-thumbnail"
                            :class="{ active: index === currentImageIndex }"
                            @click="currentImageIndex = index"
                        >
                            <img
                                :src="image"
                                :alt="`Thumbnail ${index + 1}`"
                                @error="handleImageError"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showEditModal" class="modal edit-modal">
            <div class="modal-overlay" @click="closeEditModal"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <div class="productTitle">
                        <h2>Header Here</h2>
                    </div>
                    <button class="btn btn-modal-close" @click="closeEditModal">
                        &times;
                    </button>
                </div>

                <div class="modal-body">
                    <div class="edit-order-container">
                        <form method="POST" class="editOrderForm">
                            <div class="form-grid-wrapper">
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
                                                v-for="(
                                                    img, index
                                                ) in imageList"
                                                :key="index"
                                                :class="[
                                                    'thumbnail',
                                                    {
                                                        active:
                                                            index ===
                                                            activeIndex,
                                                    },
                                                ]"
                                                @click="activeIndex = index"
                                                @mouseenter="
                                                    activeIndex = index
                                                "
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

                                    <div
                                        class="form-section general-info-section"
                                    >
                                        <!-- SECTION: General Info -->
                                        <div class="general-info-section">
                                            <h3 class="form-section-heading">
                                                General Info
                                            </h3>

                                            <fieldset>
                                                <label><span>RT:</span></label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    :value="item.rtcounter"
                                                    placeholder="RT Counter"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label>
                                                    <span>ASIN:</span>
                                                    <span>*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    :value="item.ASIN"
                                                    readonly
                                                    disabled
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >External Title:</span
                                                    ></label
                                                >
                                                <textarea
                                                    ref="productTextarea"
                                                    class="form-control no-resize"
                                                    v-model="item.ProductTitle"
                                                    placeholder="Product Title"
                                                    rows="1"
                                                    @input="autoResize"
                                                ></textarea>
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Internal Title:</span
                                                    ></label
                                                >
                                                <textarea
                                                    ref="productTextarea"
                                                    class="form-control no-resize"
                                                    v-model="item.ProductTitle"
                                                    placeholder="Product Title"
                                                    rows="1"
                                                    @input="autoResize"
                                                    readonly
                                                    disabled
                                                ></textarea>
                                            </fieldset>
                                        </div>
                                    </div>
                                </div>

                                <!-- CENTER: ALL OTHER INFO EXCEPT PRICING -->
                                <div class="form-col-center">
                                    <div class="form-section other-section">
                                        <!-- SECTION: Dates -->
                                        <div class="dates-section">
                                            <h3 class="form-section-heading">
                                                Dates
                                            </h3>

                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Order Date:</span
                                                    ></label
                                                >
                                                <input
                                                    type="date"
                                                    class="form-control"
                                                    v-model="item.orderdate"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Payment Date:</span
                                                    ></label
                                                >
                                                <input
                                                    type="date"
                                                    class="form-control"
                                                    v-model="item.paymentdate"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Shipped Date:</span
                                                    ></label
                                                >
                                                <input
                                                    type="date"
                                                    class="form-control"
                                                    v-model="item.shipdate"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Delivered Date:</span
                                                    ></label
                                                >
                                                <input
                                                    type="date"
                                                    class="form-control"
                                                    v-model="item.datedelivered"
                                                />
                                            </fieldset>
                                        </div>

                                        <!-- SECTION: Serial & Tracking -->
                                        <div class="serial-tracking-section">
                                            <h3 class="form-section-heading">
                                                Serial & Tracking
                                            </h3>

                                            <template v-if="serialKeys.length">
                                                <fieldset
                                                    v-for="(
                                                        key, index
                                                    ) in serialKeys"
                                                    :key="key"
                                                >
                                                    <label>
                                                        <span
                                                            >Serial Number
                                                            {{
                                                                getLabel(index)
                                                            }}:</span
                                                        >
                                                        <span v-if="index === 0"
                                                            >*</span
                                                        >
                                                    </label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        v-model="item[key]"
                                                    />
                                                </fieldset>
                                            </template>

                                            <template
                                                v-if="trackingKeys.length"
                                            >
                                                <fieldset
                                                    v-for="(
                                                        key, index
                                                    ) in trackingKeys"
                                                    :key="key"
                                                >
                                                    <label
                                                        ><span
                                                            >Tracking Number
                                                            {{
                                                                index + 1
                                                            }}:</span
                                                        ></label
                                                    >
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        v-model="item[key]"
                                                    />
                                                </fieldset>
                                            </template>
                                        </div>

                                        <!-- SECTION: Product Info -->
                                        <div class="product-info-section">
                                            <h3 class="form-section-heading">
                                                Product Info
                                            </h3>

                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Order Number</span
                                                    ></label
                                                >
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    :value="item.rtid"
                                                    placeholder="Order Number"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Item Number</span
                                                    ></label
                                                >
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.itemnumber"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label>
                                                    <span>Basket Number</span>
                                                    <span>*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.basketnumber"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label>
                                                    <span>RPN</span>
                                                    <span>*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.RPN"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label>
                                                    <span>PRD</span>
                                                    <span>*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.PRD"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label>
                                                    <span>PCN</span>
                                                    <span>*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.PCN"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Priority Rank</span
                                                    ></label
                                                >
                                                <select
                                                    class="form-control"
                                                    v-model="item.priorityrank"
                                                >
                                                    <option disabled value="">
                                                        Select Priority Rank
                                                    </option>
                                                    <option
                                                        v-for="type in priorityRanks"
                                                        :key="type"
                                                        :value="type"
                                                    >
                                                        {{ type }}
                                                    </option>
                                                </select>
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Return Status</span
                                                    ></label
                                                >
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.returnstatus"
                                                    readonly
                                                    disabled
                                                />
                                            </fieldset>
                                        </div>
                                    </div>
                                </div>

                                <!-- RIGHT: PRICING -->
                                <div class="form-col-right">
                                    <div
                                        class="pos-pricing-ui bg-white rounded shadow p-4"
                                        style="max-width: 480px"
                                    >
                                        <!-- Header -->
                                        <div class="border-bottom pb-2">
                                            <h3 class="text-dark mb-0">
                                                Pricing
                                            </h3>
                                        </div>

                                        <fieldset>
                                            <label><span>Quantity</span></label>
                                            <input
                                                type="number"
                                                class="form-control form-control-lg text-end"
                                                v-model="item.quantity"
                                            />
                                        </fieldset>

                                        <fieldset>
                                            <label
                                                ><span>Total Price</span></label
                                            >
                                            <input
                                                type="number"
                                                class="form-control form-control-lg text-end"
                                                v-model="item.price"
                                            />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Discount</span></label>
                                            <input
                                                type="number"
                                                class="form-control form-control-lg text-end"
                                                v-model="item.Discount"
                                            />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Tax</span></label>
                                            <input
                                                type="number"
                                                class="form-control form-control-lg text-end"
                                                v-model="item.tax"
                                            />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Shipping</span></label>
                                            <input
                                                type="number"
                                                class="form-control form-control-lg text-end"
                                                v-model="item.priceshipping"
                                            />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Refund</span></label>
                                            <input
                                                type="number"
                                                class="form-control form-control-lg text-end"
                                                v-model="item.refund"
                                            />
                                        </fieldset>

                                        <!-- Divider -->
                                        <hr class="my-4" />

                                        <fieldset>
                                            <label
                                                ><span>Unit Price</span></label
                                            >
                                            <input
                                                type="text"
                                                class="form-control form-control-lg text-end bg-light"
                                                :value="formattedUnitprice"
                                                readonly
                                            />
                                        </fieldset>
                                        <!-- Total Summary -->
                                        <fieldset>
                                            <label
                                                ><span>Grand Total</span></label
                                            >
                                            <input
                                                type="text"
                                                class="form-control form-control-lg text-end bg-light fw-bold text-success"
                                                :value="grandTotal"
                                                readonly
                                            />
                                        </fieldset>
                                    </div>
                                </div>
                            </div>

                            <div class="form-notes">
                                <div class="form-section notes-section">
                                    <!-- Description, Supplier Notes, Employee Notes -->
                                    <!-- SECTION: Notes -->
                                    <fieldset>
                                        <label><span>Description</span></label>
                                        <textarea
                                            ref="descriptionarea"
                                            class="form-control no-resize"
                                            v-model="item.description"
                                            placeholder="Description"
                                            rows="2"
                                            @input="autoResize"
                                        ></textarea>
                                    </fieldset>

                                    <fieldset>
                                        <label
                                            ><span>Supplier Notes</span></label
                                        >
                                        <textarea
                                            ref="supplierNotesarea"
                                            class="form-control no-resize"
                                            v-model="item.supplierNotes"
                                            placeholder="Supplier Notes"
                                            rows="2"
                                            @input="autoResize"
                                        ></textarea>
                                    </fieldset>

                                    <fieldset>
                                        <label
                                            ><span>Employee Notes</span></label
                                        >
                                        <textarea
                                            ref="employeeNotesarea"
                                            class="form-control no-resize"
                                            v-model="item.employeeNotes"
                                            placeholder="Employee Notes"
                                            rows="2"
                                            @input="autoResize"
                                        ></textarea>
                                    </fieldset>

                                    <fieldset>
                                        <label
                                            ><span>Sticker Notes</span></label
                                        >
                                        <textarea
                                            ref="stickerNotesarea"
                                            class="form-control no-resize"
                                            v-model="item.stickerNotes"
                                            placeholder="Sticker Notes"
                                            rows="2"
                                            @input="autoResize"
                                        ></textarea>
                                    </fieldset>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-primary btn-lg text-white"
                        @click="saveEditModal"
                    >
                        <i class="fas fa-save me-2"></i> Save
                    </button>
                </div>
            </div>
        </div>

        <div v-if="isFnskuModalVisible" class="modal fnsku-modal">
            <div class="modal-overlay" @click="hideFnskuModal"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h2>Select FNSKU</h2>
                    <button class="fnsku-close" @click="hideFnskuModal">
                        &times;
                    </button>
                </div>

                <!-- Body -->
                <div class="modal-body fnsku-product">
                    <!-- Product Info - Updated to hide ID -->
                    <div class="fnsku-product-card">
                        <!-- LEFT COLUMN: Product Images -->
                        <div class="fnsku-image-column">
                            <div
                                v-if="productImages.length"
                                class="image-display"
                            >
                                <div class="hover-image-container">
                                    <img
                                        :src="selectedImage || mainImage"
                                        alt="Main Image"
                                        class="preview-image"
                                    />
                                    <div class="hover-preview">
                                        <img
                                            :src="selectedImage || mainImage"
                                            alt="Zoomed Preview"
                                        />
                                    </div>
                                </div>

                                <div class="thumbnail-list">
                                    <img
                                        v-for="(img, index) in productImages"
                                        :key="index"
                                        :src="img"
                                        alt="Thumbnail"
                                        class="thumbnail"
                                        :class="{
                                            active: selectedImage === img,
                                        }"
                                        @click="selectedImage = img"
                                    />
                                </div>
                            </div>

                            <div v-else class="no-image">
                                <p>No image available</p>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: Product Details -->
                        <div class="fnsku-details-column">
                            <h4 class="product-title">
                                {{ currentItem?.ProductTitle }}
                            </h4>
                            <div class="detail-item">
                                <span class="label">Current FNSKU</span>
                                <span class="value">{{
                                    currentItem?.FNSKUviewer || "None"
                                }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">RT#:</span>
                                <span class="value">{{
                                    currentItem?.rtcounter
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="d-flex justify-content-between align-items-center"
                    >
                        <h5 class="mb-0">Search & Filters</h5>
                        <button
                            class="btn btn-sm btn-outline-dark d-flex align-items-center gap-2"
                            @click="showFilters = !showFilters"
                        >
                            <i class="fas fa-sliders-h"></i>
                            <span>{{ showFilters ? "Hide" : "Show" }}</span>
                        </button>
                    </div>

                    <!-- Improved Search & Filter UI -->
                    <div
                        class="fnsku-search-container card p-3 shadow-sm rounded-0 bg-light-subtle"
                        v-show="showFilters"
                    >
                        <!-- Spinner + Search -->
                        <div class="position-relative mb-3">
                            <label class="form-label fw-semibold"
                                >Search Title or ASIN</label
                            >
                            <input
                                type="text"
                                v-model="fnskuSearch"
                                placeholder="Search Title or ASIN"
                                class="form-control pe-5"
                                @input="filterFnskuList"
                                :disabled="isSearching"
                            />
                            <!-- Spinner overlay inside input -->
                            <div
                                v-if="isSearching"
                                class="position-absolute top-50 end-0 translate-middle-y me-3"
                            >
                                <div
                                    class="spinner-border spinner-border-sm text-secondary"
                                ></div>
                            </div>
                        </div>

                        <!-- Filters Grid -->
                        <div class="row g-3">
                            <!-- Store Filter -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold"
                                    >Filter by Store</label
                                >
                                <select
                                    v-model="selectedStore"
                                    @change="filterFnskuList"
                                    class="form-select"
                                    :disabled="isSearching"
                                >
                                    <option value="">All Stores</option>
                                    <option
                                        v-for="store in uniqueStores"
                                        :key="store"
                                        :value="store"
                                    >
                                        {{ store }}
                                    </option>
                                </select>
                            </div>

                            <!-- FNSKU Filter -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold"
                                    >Filter by FNSKU</label
                                >
                                <input
                                    type="text"
                                    v-model="fnskuExact"
                                    @input="filterFnskuList"
                                    class="form-control"
                                    placeholder="Exact or partial FNSKU"
                                    :disabled="isSearching"
                                />
                            </div>

                            <!-- Grading Filter -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold"
                                    >Filter by Condition</label
                                >
                                <select
                                    v-model="selectedGrading"
                                    @change="filterFnskuList"
                                    class="form-select"
                                    :disabled="isSearching"
                                >
                                    <option value="">All Conditions</option>
                                    <option
                                        v-for="option in gradingOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Optional loading status below -->
                        <div
                            v-if="isSearching"
                            class="text-muted mt-3 text-center small"
                        >
                            Searching FNSKUs...
                        </div>
                    </div>

                    <!-- FNSKU List - Desktop Table -->
                    <div class="d-none d-md-block">
                        <div v-if="isSearching" class="fnsku-loading-overlay">
                            <div class="loading-content">
                                <div class="loading-spinner-large"></div>
                                <p>Loading FNSKUs...</p>
                            </div>
                        </div>

                        <div class="fnsku-table-scroll">
                            <table
                                class="table"
                                :class="{ 'loading-blur': isSearching }"
                            >
                                <thead class="table-dark sticky-header">
                                    <tr>
                                        <th>Image</th>
                                        <th>ASIN</th>
                                        <th>Title & Inventory</th>
                                        <th>FNSKU</th>
                                        <th>MSKU</th>
                                        <th>Grade</th>
                                        <th>Store</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template
                                        v-for="(fnsku, index) in validFnskuList"
                                        :key="fnsku.FNSKU"
                                    >
                                        <tr>
                                            <td>
                                                <img
                                                    :src="
                                                        getImageSrc(
                                                            fnsku.ASIN,
                                                            0
                                                        )
                                                    "
                                                    :alt="`Main image for ${fnsku.ASIN}`"
                                                    class="asin-thumbnail"
                                                    @error="setDefaultImage"
                                                />
                                            </td>
                                            <td>{{ fnsku.ASIN }}</td>
                                            <td>
                                                <ul
                                                    class="list-unstyled m-0 fnsku-title"
                                                >
                                                    <li>{{ fnsku.astitle }}</li>
                                                    <li
                                                        class="text-muted small"
                                                    >
                                                        {{ fnsku.Units }} in
                                                        inventory
                                                        <span
                                                            v-if="
                                                                fnsku.Units < 11
                                                            "
                                                            class="badge bg-warning ms-1"
                                                        >
                                                            Used
                                                            {{
                                                                11 - fnsku.Units
                                                            }}
                                                            times
                                                        </span>
                                                        <span
                                                            v-else
                                                            class="badge bg-success ms-1"
                                                        >
                                                            First use
                                                        </span>
                                                    </li>
                                                </ul>
                                            </td>
                                            <td>
                                                <div>
                                                    {{ fnsku.FNSKU }}
                                                    <div
                                                        class="small text-muted"
                                                    >
                                                        <i
                                                            class="fas fa-arrow-right"
                                                        ></i>
                                                        Will assign:
                                                        <strong>{{
                                                            getNextFnskuToUse(
                                                                fnsku
                                                            )
                                                        }}</strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ fnsku.MSKU }}</td>
                                            <td>
                                                {{
                                                    getGradingLabel(
                                                        fnsku.grading
                                                    )
                                                }}
                                            </td>
                                            <td>{{ fnsku.storename }}</td>
                                            <td>
                                                <div class="fnsku-action">
                                                    <button
                                                        @click="
                                                            selectFnsku(fnsku)
                                                        "
                                                        class="btn btn-fnsku-select"
                                                        :class="{
                                                            'fnsku-recommended':
                                                                fnsku.ASIN ===
                                                                currentItem?.ASINviewer,
                                                        }"
                                                        :disabled="isSearching"
                                                    >
                                                        {{
                                                            fnsku.ASIN ===
                                                            currentItem?.ASINviewer
                                                                ? "Recommended"
                                                                : "Select"
                                                        }}
                                                    </button>
                                                    <button
                                                        @click="
                                                            showFnskuAvailabilityInfo(
                                                                fnsku
                                                            )
                                                        "
                                                        class="btn btn-sm btn-outline-info ms-1"
                                                        title="Show FNSKU details"
                                                    >
                                                        <i
                                                            class="fas fa-info-circle"
                                                        ></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <tr
                                        v-if="
                                            filteredFnskuList.length === 0 &&
                                            !isSearching
                                        "
                                    >
                                        <td colspan="8" class="text-center">
                                            <span class="fnsku-no-results"
                                                >No matching FNSKUs found</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div
                        class="d-flex justify-content-between align-items-center mt-3 p-3 bg-light d-none d-md-block"
                    >
                        <div>
                            <span v-if="isInitialLoad || isSearching"
                                >Loading...</span
                            >
                            <span
                                v-else-if="
                                    paginationInfo.from && paginationInfo.to
                                "
                            >
                                Showing {{ paginationInfo.from }} to
                                {{ paginationInfo.to }} entries of
                                {{ totalRecords }}
                            </span>
                            <span v-else>No entries found</span>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <select
                                v-model="pageSize"
                                @change="changePageSize"
                                class="form-select form-select-sm"
                                style="width: 80px"
                            >
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>

                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <li
                                        class="page-item"
                                        :class="{ disabled: currentPage === 1 }"
                                    >
                                        <button
                                            class="page-link"
                                            @click="prevPage"
                                            :disabled="currentPage === 1"
                                        >
                                            Previous
                                        </button>
                                    </li>

                                    <li class="page-item active">
                                        <span class="page-link"
                                            >Page {{ currentPage }}</span
                                        >
                                    </li>

                                    <li
                                        class="page-item"
                                        :class="{ disabled: !hasMorePages }"
                                    >
                                        <button
                                            class="page-link"
                                            @click="nextPage"
                                            :disabled="!hasMorePages"
                                        >
                                            Next
                                        </button>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="fnsku-card-container d-block d-md-none">
                        <div
                            v-if="isSearching"
                            class="fnsku-loading-overlay mobile"
                        >
                            <div class="loading-content">
                                <div class="loading-spinner-large"></div>
                                <p>Loading FNSKUs...</p>
                            </div>
                        </div>

                        <div :class="{ 'loading-blur': isSearching }">
                            <div
                                v-for="(fnsku, index) in filteredFnskuList"
                                :key="fnsku.FNSKU"
                                class="card mb-3 shadow-sm"
                                :class="
                                    index % 2 === 0 ? 'bg-light' : 'bg-white'
                                "
                            >
                                <div class="card-body d-flex flex-column gap-3">
                                    <div class="d-flex justify-content-between">
                                        <div class="fnsku-details">
                                            <h6>{{ fnsku.FNSKU }}</h6>
                                            <p>
                                                <strong>ASIN:</strong>
                                                {{ fnsku.ASIN }}
                                            </p>
                                            <p>
                                                <strong>Store:</strong>
                                                {{ fnsku.storename }}
                                            </p>
                                        </div>
                                        <span
                                            class="badge fnsku-badge"
                                            :class="{
                                                'bg-success':
                                                    fnsku.grading.includes(
                                                        'New'
                                                    ),
                                                'bg-secondary':
                                                    !fnsku.grading.includes(
                                                        'New'
                                                    ),
                                            }"
                                        >
                                            {{ fnsku.grading }}
                                        </span>
                                    </div>

                                    <div
                                        class="d-flex flex-column align-items-start gap-1"
                                    >
                                        <span
                                            ><strong>{{
                                                fnsku.astitle
                                            }}</strong></span
                                        >
                                        <span class="text-muted mb-0"
                                            >{{ fnsku.Units }} in
                                            inventory</span
                                        >
                                    </div>

                                    <div>
                                        <button
                                            @click="selectFnsku(fnsku)"
                                            class="btn btn-sm"
                                            :class="{
                                                'btn-success':
                                                    fnsku.ASIN ===
                                                    currentItem?.ASINviewer,
                                                'btn-outline-primary':
                                                    fnsku.ASIN !==
                                                    currentItem?.ASINviewer,
                                            }"
                                            :disabled="isSearching"
                                        >
                                            {{
                                                fnsku.ASIN ===
                                                currentItem?.ASINviewer
                                                    ? "Recommended"
                                                    : "Select"
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="
                                    filteredFnskuList.length === 0 &&
                                    !isSearching
                                "
                                class="alert alert-info text-center"
                            >
                                No matching FNSKUs found
                            </div>
                        </div>
                    </div>

                    <div class="mobile-pagination d-block d-md-none">
                        <div class="info-row">
                            <span v-if="isInitialLoad || isSearching"
                                >Loading...</span
                            >
                            <span
                                v-else-if="
                                    paginationInfo.from && paginationInfo.to
                                "
                            >
                                {{ paginationInfo.from }}-{{
                                    paginationInfo.to
                                }}
                                of {{ totalRecords }}
                            </span>
                            <span v-else>No entries</span>
                        </div>

                        <div class="controls-row">
                            <select
                                v-model="pageSize"
                                @change="changePageSize"
                                class="page-size-select"
                            >
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>

                            <div class="nav-buttons">
                                <button
                                    @click="prevPage"
                                    :disabled="currentPage === 1"
                                    class="nav-btn"
                                >
                                    ‹
                                </button>
                                <span class="page-info">{{ currentPage }}</span>
                                <button
                                    @click="nextPage"
                                    :disabled="!hasMorePages"
                                    class="nav-btn"
                                >
                                    ›
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div v-if="showConfirmationModal" class="modal confirmation-modal">
            <div class="modal-overlay" @click="cancelConfirmation"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h3>{{ confirmationTitle }}</h3>
                    <button
                        class="btn btn-modal-close"
                        @click="cancelConfirmation"
                    >
                        &times;
                    </button>
                </div>

                <div class="modal-body">
                    <p>{{ confirmationMessage }}</p>
                </div>

                <div class="modal-footer">
                    <button class="btn-cancel" @click="cancelConfirmation">
                        Cancel
                    </button>
                    <button
                        class="btn-confirm"
                        @click="confirmAction"
                        :class="{
                            'btn-validation':
                                confirmationActionType === 'validation',
                            'btn-stockroom':
                                confirmationActionType === 'stockroom',
                        }"
                    >
                        Yes, Proceed
                    </button>
                </div>
            </div>
        </div>

        <!-- split modal  -->
        <splittingModal
            :show-modal="showSplitModal"
            :split-item="currentSplitItem"
            @close="closeSplitModal"
            @split-success="onSplitSuccess"
        />

        <!--OPY DETAILS MODAL-->
        <copyDetailsModal
            :show-modal="showCopyDetailsModal"
            :item-data="currentCopyItem"
            @close="closeCopyDetailsModal"
        />
    </div>
</template>

<script>
import Labeling from "./labeling.js";
export default Labeling;
</script>

<style scoped>
/* Loading Animation CSS - Add this to your labeling.css file */

/* Search input wrapper for positioning */
.search-input-wrapper {
    position: relative;
    width: 100%;
}

/* Small spinner inside search input */
.search-loading-spinner {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
}

.search-loading-spinner .spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Loading text below search */
.search-loading-text {
    text-align: center;
    color: #6c757d;
    font-size: 0.9em;
    margin-top: 8px;
    font-style: italic;
}

/* Loading overlay for FNSKU list */
.fnsku-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    border-radius: 8px;
}

.fnsku-loading-overlay.mobile {
    position: relative;
    min-height: 200px;
    background: rgba(248, 249, 250, 0.9);
}

/* Loading content */
.loading-content {
    text-align: center;
    padding: 20px;
}

.loading-content p {
    margin-top: 15px;
    color: #6c757d;
    font-weight: 500;
}

/* Large spinner for overlay */
.loading-spinner-large {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

/* Blur effect when loading */
.loading-blur {
    filter: blur(1px);
    opacity: 0.6;
    pointer-events: none;
    transition: all 0.3s ease;
}

/* Spinner animation */
@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

/* Disabled state for buttons during loading */
button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Search input disabled state */
.fnsku-search-input:disabled {
    background-color: #f8f9fa;
    cursor: not-allowed;
}

/* Pulse animation for search input when loading */
.fnsku-search-input:disabled {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        background-color: #f8f9fa;
    }
    50% {
        background-color: #e9ecef;
    }
    100% {
        background-color: #f8f9fa;
    }
}

/* Container positioning for overlay */
.fnsku-list-container,
.fnsku-card-container {
    position: relative;
}

/* Ensure table maintains structure during blur */
.table.loading-blur {
    table-layout: fixed;
}

/* Loading state for mobile cards */
.fnsku-card-container.loading-blur .card {
    pointer-events: none;
}

/* Smooth transitions */
.fnsku-list-container,
.fnsku-card-container,
.search-input-wrapper {
    transition: all 0.3s ease;
}

/* Loading indicator variants */
.spinner-small {
    width: 12px;
    height: 12px;
    border-width: 2px;
}

.spinner-medium {
    width: 24px;
    height: 24px;
    border-width: 3px;
}

.spinner-large {
    width: 48px;
    height: 48px;
    border-width: 4px;
}

/* Responsive loading overlay */
@media (max-width: 768px) {
    .fnsku-loading-overlay {
        border-radius: 0;
    }

    .loading-spinner-large {
        width: 32px;
        height: 32px;
        border-width: 3px;
    }

    .loading-content {
        padding: 15px;
    }

    .loading-content p {
        font-size: 0.9em;
        margin-top: 10px;
    }
}

.btn-copy-details {
    background-color: #17a2b8;
    color: white;
    border: 1px solid #17a2b8;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.875rem;
    margin: 2px;
    transition: all 0.2s;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-copy-details:hover {
    background-color: #138496;
    border-color: #117a8b;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(23, 162, 184, 0.3);
}

.btn-copy-details i {
    font-size: 0.9rem;
}

/* Mobile responsive for copy details button */
@media (max-width: 768px) {
    .btn-copy-details {
        padding: 8px 12px;
        font-size: 0.8rem;
        margin: 1px;
    }
}
</style>
