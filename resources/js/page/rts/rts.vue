<template>
    <div class="vue-container houseage-module">
        <!-- <div class="top-header">
            <span>Top Header</span>
        </div> -->

        <h2 class="module-title">RTS Module</h2>

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
                        <th>ASIN</th>
                        <th>FNSKU</th>
                        <th>Grading</th>
                        <th>Serial Number</th>
                        <th>Quantity</th>
                        <th>Fullfilment Status</th>
                        <th>Module</th>
                        <th>Return Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="11" class="text-center">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                                Loading...
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="sortedInventory.length === 0">
                        <td colspan="11" class="text-center">
                            No orders found
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
                                <span><strong></strong> {{ item.ASIN }}</span>
                            </td>
                            <td>
                                <span><strong></strong> {{ item.FNSKU }}</span>
                            </td>
                            <td>
                                <span
                                    ><strong></strong> {{ item.grading }}</span
                                >
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.serialnumber }}</span
                                >
                            </td>
                            <td>
                                <span
                                    ><strong></strong> {{ item.quantity }}</span
                                >
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.fulfillment_status }}</span
                                >
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.ProductModuleLoc }}</span
                                >
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.returnstatus }}</span
                                >
                            </td>
                            <td>
                                <div class="action-buttons">
                                    {{ item.totalquantity }}
                                    <button
                                        class="btn-details"
                                        @click="toggleDetails(index)"
                                    >
                                        <i class="fas fa-info-circle"></i> More
                                        Details
                                    </button>

                                    <button
                                        @click="openEditModal(item)"
                                        class="btn btn-edit"
                                    >
                                        <i class="bi bi-pencil"></i>Edit
                                    </button>

                                    <button
                                        @click="openRTSModal(item)"
                                        class="btn btn-rts-option"
                                    >
                                        <i class="fas fa-tools"></i>RTS Option
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="expandedRows[index]">
                            <td :colspan="showDetails ? 18 : 12">
                                <div
                                    class="expanded-content p-3 border rounded"
                                >
                                    <p>
                                        <strong
                                            >External Title provided by
                                            Supplier:</strong
                                        >
                                        {{ item.ProductTitle }}
                                    </p>
                                    <p>
                                        <strong>Product Name:</strong>
                                        {{ item.AStitle }}
                                    </p>
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
                        <div class="mobile-detail-row">
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
                            @click="toggleDetails(index)"
                        >
                            <i class="fas fa-info-circle"></i> Details
                        </button>

                        <button @click="EditItem(item)" class="btn btn-fnsku">
                            <i class="bi bi-clipboard-check"></i> Edit
                        </button>

                        <button
                            @click="openRTSModal(item)"
                            class="btn btn-rts-option"
                        >
                            <i class="fas fa-tools"></i> RTS Option
                        </button>
                    </div>

                    <hr v-if="expandedRows[index]" />

                    <div
                        v-if="expandedRows[index]"
                        class="mobile-expanded-content"
                    >
                        <p>
                            <strong
                                >External Title provided by Supplier:</strong
                            >
                            {{ item.ProductTitle }}
                        </p>
                        <p><strong>Product Name:</strong> {{ item.AStitle }}</p>
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

                        <div
                            v-if="currentImageSet.length === 0"
                            class="no-images-message"
                        >
                            No images available in this category
                        </div>

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
        </div>

        <!-- Edit Modal -->
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
                                <!-- LEFT: IMAGE + GENERAL INFO -->
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
                                                <label
                                                    ><span>ASIN:</span></label
                                                >
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.ASIN"
                                                    readonly
                                                    disabled
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span>FNSKU:</span></label
                                                >
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.FNSKU"
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
                                                    <label
                                                        ><span
                                                            >Serial Number
                                                            {{
                                                                getLabel(index)
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
                                                        >Sub-variant:</span
                                                    ></label
                                                >
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.itemnumber"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Order Number:</span
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
                                                        >Item Number:</span
                                                    ></label
                                                >
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.itemnumber"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Supplier ID/Name:</span
                                                    ></label
                                                >
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.seller"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Material:</span
                                                    ></label
                                                >
                                                <select
                                                    class="form-control"
                                                    v-model="item.materialtype"
                                                >
                                                    <option disabled value="">
                                                        Select material type
                                                    </option>
                                                    <option
                                                        v-for="type in materialTypes"
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
                                                        >Source Type:</span
                                                    ></label
                                                >
                                                <select
                                                    class="form-control"
                                                    v-model="item.sourceType"
                                                >
                                                    <option disabled value="">
                                                        Select source type
                                                    </option>
                                                    <option value="ES">
                                                        ES
                                                    </option>
                                                    <option value="AS">
                                                        AS
                                                    </option>
                                                    <option value="XS">
                                                        XS
                                                    </option>
                                                    <option value="PS">
                                                        PS
                                                    </option>
                                                    <option value="RS">
                                                        RS
                                                    </option>
                                                    <option value="B&H">
                                                        B&H
                                                    </option>
                                                </select>
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Carrier /
                                                        Courier:</span
                                                    ></label
                                                >
                                                <select
                                                    class="form-control"
                                                    v-model="item.carrier"
                                                >
                                                    <option disabled value="">
                                                        Select courier
                                                    </option>
                                                    <option
                                                        v-for="carrier in carrierOptions"
                                                        :key="carrier"
                                                        :value="carrier"
                                                    >
                                                        {{ carrier }}
                                                    </option>
                                                </select>
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Listed Condition:</span
                                                    ></label
                                                >
                                                <select
                                                    class="form-control"
                                                    v-model="
                                                        item.listedcondition
                                                    "
                                                >
                                                    <option disabled value="">
                                                        Select condition
                                                    </option>
                                                    <option value="New">
                                                        New
                                                    </option>
                                                    <option value="Open Box">
                                                        Open Box
                                                    </option>
                                                    <option value="Used">
                                                        Used
                                                    </option>
                                                    <option
                                                        value="For parts or not working"
                                                    >
                                                        For parts or not working
                                                    </option>
                                                </select>
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Payment Method:</span
                                                    ></label
                                                >
                                                <select
                                                    class="form-control"
                                                    v-model="item.paymentmethod"
                                                >
                                                    <option disabled value="">
                                                        Select Payment Method
                                                    </option>
                                                    <option value="PayPal">
                                                        PayPal
                                                    </option>
                                                    <option
                                                        value="Credit/Debit Card"
                                                    >
                                                        Credit/Debit Card
                                                    </option>
                                                    <option value="Cash">
                                                        Cash
                                                    </option>
                                                    <option
                                                        value="Bank Transfer"
                                                    >
                                                        Bank Transfer
                                                    </option>
                                                    <option value="Check">
                                                        Check
                                                    </option>
                                                </select>
                                            </fieldset>
                                        </div>

                                        <!-- SECTION: Other Info -->
                                        <div class="other-info-section">
                                            <h3 class="form-section-heading">
                                                Other Info
                                            </h3>

                                            <fieldset>
                                                <label
                                                    ><span>Module:</span></label
                                                >
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="
                                                        item.ProductModuleLoc
                                                    "
                                                    readonly
                                                    disabled
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Store Name:</span
                                                    ></label
                                                >
                                                <select
                                                    class="form-control"
                                                    v-model="item.storename"
                                                >
                                                    <option disabled value="">
                                                        Select Store Name
                                                    </option>
                                                    <option
                                                        v-for="type in storeNames"
                                                        :key="type"
                                                        :value="type"
                                                    >
                                                        {{ type }}
                                                    </option>
                                                </select>
                                            </fieldset>
                                            <fieldset>
                                                <label><span>RPN:</span></label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.RPN"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label><span>PRD:</span></label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.PRD"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label><span>PCN:</span></label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.PCN"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Basket Number:</span
                                                    ></label
                                                >
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="item.basketnumber"
                                                />
                                            </fieldset>
                                            <fieldset>
                                                <label
                                                    ><span
                                                        >Priority Rank:</span
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
                                                        >Validation
                                                        Status:</span
                                                    ></label
                                                >
                                                <select
                                                    class="form-control"
                                                    v-model="
                                                        item.validation_status
                                                    "
                                                >
                                                    <option disabled value="">
                                                        Select Validation Status
                                                    </option>
                                                    <option
                                                        v-for="type in validationStatuses"
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
                                                        >Return Status:</span
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

                                        <!-- Full-width Fields -->
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
                                                ><span>Sub-total</span></label
                                            >
                                            <input
                                                type="text"
                                                class="form-control form-control-lg text-end bg-light"
                                                :value="formattedSubtotal"
                                                readonly
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

                                        <fieldset>
                                            <label
                                                ><span>Unit Price</span></label
                                            >
                                            <input
                                                type="text"
                                                class="form-control form-control-lg text-end bg-light"
                                                :value="unitPrice"
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
                                        <label><span>Description:</span></label>
                                        <textarea
                                            ref="descriptionarea"
                                            class="form-control no-resize"
                                            v-model="item.description"
                                            placeholder="Description"
                                            rows="1"
                                            @input="autoResize"
                                        ></textarea>
                                    </fieldset>

                                    <fieldset>
                                        <label
                                            ><span>Supplier Notes:</span></label
                                        >
                                        <textarea
                                            ref="supplierNotesarea"
                                            class="form-control no-resize"
                                            v-model="item.supplierNotes"
                                            placeholder="Supplier Notes"
                                            rows="1"
                                            @input="autoResize"
                                        ></textarea>
                                    </fieldset>

                                    <fieldset>
                                        <label
                                            ><span>Employee Notes:</span></label
                                        >
                                        <textarea
                                            ref="employeeNotesarea"
                                            class="form-control no-resize"
                                            v-model="item.employeeNotes"
                                            placeholder="Employee Notes"
                                            rows="1"
                                            @input="autoResize"
                                        ></textarea>
                                    </fieldset>

                                    <fieldset>
                                        <label
                                            ><span>Sticker Notes:</span></label
                                        >
                                        <textarea
                                            ref="stickerNotesarea"
                                            class="form-control no-resize"
                                            v-model="item.stickerNotes"
                                            placeholder="Employee Notes"
                                            rows="1"
                                            @input="autoResize"
                                        ></textarea>
                                    </fieldset>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="modal-footer">
                    <!-- <button
                        type="button"
                        class="btn btn-primary btn-lg text-white"
                        @click="saveEditModal"
                    >
                        <i class="fas fa-save me-2"></i> Save
                    </button> -->
                </div>
            </div>
        </div>

        <!-- RTS Options Modal -->
        <div v-if="showRTSModal" class="modal rts-modal">
            <div class="modal-overlay" @click="closeRTSModal"></div>

            <div class="modal-content rts-modal-content">
                <div class="modal-header">
                    <div class="productTitle">
                        <h2>
                            RTS Options - RT# {{ rtsCurrentItem?.rtcounter }}
                        </h2>
                    </div>
                    <button class="btn btn-modal-close" @click="closeRTSModal">
                        &times;
                    </button>
                </div>

                <div class="modal-body">
                    <div class="rts-form-container">
                        <form @submit.prevent="saveRTSModal" class="rts-form">
                            <!-- Product Info Header -->
                            <div class="rts-product-info">
                                <div class="product-image-mini">
                                    <img
                                        :src="
                                            '/images/thumbnails/' +
                                            (rtsCurrentItem?.img1 || '')
                                        "
                                        :alt="
                                            rtsCurrentItem?.ProductTitle ||
                                            'Product'
                                        "
                                        @error="handleImageError($event)"
                                    />
                                </div>
                                <div class="product-details">
                                    <h4>{{ rtsCurrentItem?.ProductTitle }}</h4>
                                    <p>
                                        <strong>FNSKU:</strong>
                                        {{ rtsCurrentItem?.FNSKU }}
                                    </p>
                                    <p>
                                        <strong>Serial:</strong>
                                        {{ rtsCurrentItem?.serialnumber }}
                                    </p>
                                </div>
                            </div>

                            <hr class="divider" />

                            <!-- RTS Form Fields -->
                            <div class="rts-form-grid">
                                <div class="rts-form-section">
                                    <!-- Date Field -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text"
                                                >Date Filed</span
                                            >
                                        </label>
                                        <input
                                            type="date"
                                            class="form-control rts-input"
                                            v-model="rtsForm.dateField"
                                            required
                                        />
                                    </fieldset>

                                    <!-- Filed IN Checkboxes -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text"
                                                >Filed IN:</span
                                            >
                                        </label>
                                        <div class="checkbox-group">
                                            <label class="checkbox-label">
                                                <input
                                                    type="checkbox"
                                                    v-model="rtsForm.filedInES"
                                                    class="checkbox-input"
                                                />
                                                <span class="checkbox-text"
                                                    >ES</span
                                                >
                                            </label>
                                            <label class="checkbox-label">
                                                <input
                                                    type="checkbox"
                                                    v-model="rtsForm.filedInPPL"
                                                    class="checkbox-input"
                                                />
                                                <span class="checkbox-text"
                                                    >PPL</span
                                                >
                                            </label>
                                        </div>
                                    </fieldset>

                                    <!-- Test Result -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text"
                                                >Test Result</span
                                            >
                                        </label>
                                        <select
                                            class="form-control rts-select"
                                            v-model="rtsForm.testResult"
                                            required
                                        >
                                            <option value="">
                                                Select Test Result
                                            </option>
                                            <option value="Passed">
                                                Passed
                                            </option>
                                            <option value="Failed">
                                                Failed
                                            </option>
                                        </select>
                                    </fieldset>

                                    <!-- Status -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text"
                                                >Status</span
                                            >
                                        </label>
                                        <select
                                            class="form-control rts-select"
                                            v-model="rtsForm.status"
                                            required
                                        >
                                            <option value="">
                                                Select Status
                                            </option>
                                            <option value="RTS">RTS</option>
                                            <option value="Dismantle">
                                                Dismantle
                                            </option>
                                        </select>
                                    </fieldset>

                                    <!-- RTS Result -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text"
                                                >RTS Result</span
                                            >
                                        </label>
                                        <select
                                            class="form-control rts-select"
                                            v-model="rtsForm.rtsResult"
                                            required
                                        >
                                            <option value="">
                                                Select RTS Result
                                            </option>
                                            <option value="PRNR">PRNR</option>
                                            <option value="FRNR">FRNR</option>
                                            <option value="LST">LST</option>
                                            <option value="Replacement">
                                                Replacement
                                            </option>
                                            <option value="Ship-Back">
                                                Ship-Back
                                            </option>
                                        </select>
                                    </fieldset>
                                </div>

                                <div class="rts-form-section">
                                    <!-- REFUND STATUS Section -->
                                    <div class="refund-status-section">
                                        <h3 class="section-title">
                                            REFUND STATUS
                                        </h3>

                                        <!-- Amount -->
                                        <fieldset class="rts-fieldset">
                                            <label class="rts-label">
                                                <span class="label-text"
                                                    >Amount:</span
                                                >
                                            </label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                class="form-control rts-input"
                                                v-model="rtsForm.refundAmount"
                                                placeholder="0.00"
                                            />
                                        </fieldset>

                                        <!-- Date of Refund -->
                                        <fieldset class="rts-fieldset">
                                            <label class="rts-label">
                                                <span class="label-text"
                                                    >Date of Refund</span
                                                >
                                            </label>
                                            <input
                                                type="date"
                                                class="form-control rts-input"
                                                v-model="rtsForm.refundDate"
                                            />
                                        </fieldset>

                                        <!-- Reason of Return -->
                                        <fieldset class="rts-fieldset">
                                            <label class="rts-label">
                                                <span class="label-text"
                                                    >Reason of Return</span
                                                >
                                            </label>
                                            <textarea
                                                class="form-control rts-textarea"
                                                v-model="rtsForm.reasonOfReturn"
                                                rows="3"
                                                placeholder="Enter reason for return..."
                                            ></textarea>
                                        </fieldset>

                                        <!-- Return TN -->
                                        <fieldset class="rts-fieldset">
                                            <label class="rts-label">
                                                <span class="label-text"
                                                    >Return TN:</span
                                                >
                                            </label>
                                            <input
                                                type="text"
                                                class="form-control rts-input"
                                                v-model="rtsForm.returnTN"
                                                placeholder="Enter tracking number"
                                            />
                                        </fieldset>

                                        <!-- Notes -->
                                        <fieldset class="rts-fieldset">
                                            <label class="rts-label">
                                                <span class="label-text"
                                                    >Notes</span
                                                >
                                            </label>
                                            <textarea
                                                class="form-control rts-textarea"
                                                v-model="rtsForm.notes"
                                                rows="4"
                                                placeholder="Additional notes..."
                                            ></textarea>
                                        </fieldset>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        @click="closeRTSModal"
                    >
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="saveRTSModal"
                        :disabled="loading"
                    >
                        <i class="fas fa-save me-2"></i>
                        {{ loading ? "Saving..." : "Save" }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import RTS from "./rts.js";
export default RTS;
</script>

<style scoped>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 7000 !important;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1;
}

.modal-content {
    position: relative;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    z-index: 7001 !important;
    max-width: 90vw;
    max-height: 90vh;
    overflow-y: auto;
}

/* Image Modal Specific */
.image-modal {
    z-index: 7500 !important;
}

.image-modal .modal-content {
    z-index: 7501 !important;
}

/* Edit Modal Specific */
.edit-modal {
    z-index: 7200 !important;
}

.edit-modal .modal-content {
    z-index: 7201 !important;
}

/* RTS Modal Specific Styles - FIXED CENTERING & COMPACT */
.rts-modal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(0, 0, 0, 0.5) !important;
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    z-index: 8000 !important;
    margin: 0 !important;
    padding: 15px !important;
    box-sizing: border-box !important;
}

.rts-modal .modal-overlay {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0, 0, 0, 0.5) !important;
    z-index: 1 !important;
}

.rts-modal .modal-content {
    position: relative !important;
    max-width: 850px !important;
    width: 100% !important;
    max-height: 85vh !important;
    overflow-y: auto !important;
    z-index: 8001 !important;
    margin: 0 auto !important;
    background: white !important;
    border-radius: 8px !important;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2) !important;
    border: 1px solid #e9ecef !important;
}

.rts-modal-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    border: 1px solid #e9ecef;
}

/* SweetAlert2 Z-Index Fix - HIGHEST PRIORITY */
.swal2-container {
    z-index: 99999 !important;
}

.swal2-popup {
    z-index: 100000 !important;
}

/* Custom class for top-level SweetAlert */
.swal2-top-level {
    z-index: 100001 !important;
    position: fixed !important;
}

.swal2-top-level .swal2-popup {
    z-index: 100002 !important;
}

/* Ensure SweetAlert2 appears above everything */
div[aria-labelledby="swal2-title"] {
    z-index: 100000 !important;
}

/* Fix for any backdrop issues */
.swal2-container.swal2-backdrop-show {
    z-index: 99999 !important;
}

.swal2-container .swal2-popup {
    z-index: 100001 !important;
}

/* Override any inline z-index styles */
.swal2-container[style*="z-index"] {
    z-index: 99999 !important;
}

.swal2-popup[style*="z-index"] {
    z-index: 100000 !important;
}

/* Force hide RTS modal when needed */
.rts-modal.force-hidden {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    z-index: -1 !important;
}

.rts-form-container {
    padding: 0;
}

.rts-product-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
}

.product-image-mini {
    width: 50px;
    height: 50px;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
}

.product-image-mini img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-details h4 {
    margin: 0 0 6px 0;
    font-size: 14px;
    font-weight: 600;
    color: #333;
    line-height: 1.2;
}

.product-details p {
    margin: 1px 0;
    font-size: 12px;
    color: #666;
    line-height: 1.2;
}

.divider {
    margin: 15px 0;
    border: none;
    height: 1px;
    background: #e9ecef;
}

.rts-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.rts-form-section {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 12px;
    padding-bottom: 6px;
    border-bottom: 2px solid #007bff;
}

.rts-fieldset {
    margin: 0;
    padding: 0;
    border: none;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.rts-label {
    margin: 0;
    font-weight: 500;
    color: #333;
}

.label-text {
    font-size: 13px;
}

.rts-input,
.rts-select,
.rts-textarea {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.rts-input:focus,
.rts-select:focus,
.rts-textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.1);
}

.rts-textarea {
    resize: vertical;
    min-height: 60px;
}

.checkbox-group {
    display: flex;
    gap: 15px;
    margin-top: 4px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-weight: normal;
}

.checkbox-input {
    width: 14px;
    height: 14px;
    cursor: pointer;
}

.checkbox-text {
    font-size: 13px;
    color: #333;
}

.refund-status-section {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

/* FIXED BUTTON STYLES - ALL SAME WIDTH */
.btn-details,
.btn-edit,
.btn-rts-option {
    background: #007bff;
    color: white;
    border: 1px solid #007bff;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    font-weight: 500;
    white-space: nowrap;
    margin: 2px 0;
    width: 100% !important; /* Force same width */
    min-width: 120px !important; /* Match "More Details" button width */
    max-width: 120px !important;
    justify-content: center !important;
    text-align: center !important;
    box-sizing: border-box !important;
}

/* Specific color overrides */
.btn-edit {
    background: #ffc107;
    border-color: #ffc107;
    color: #212529;
}

.btn-edit:hover {
    background: #e0a800;
    border-color: #d39e00;
    color: #212529;
}

.btn-rts-option {
    background: #28a745;
    border-color: #28a745;
    color: white;
}

.btn-rts-option:hover {
    background: #218838;
    border-color: #1e7e34;
    color: white;
}

.btn-details:hover {
    background: #0056b3;
    border-color: #004085;
    color: white;
}

/* Focus states */
.btn-details:focus,
.btn-edit:focus,
.btn-rts-option:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.btn-edit:focus {
    box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.25);
}

.btn-rts-option:focus {
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.25);
}

/* Active states */
.btn-details:active {
    background: #004085;
    border-color: #003d82;
}

.btn-edit:active {
    background: #d39e00;
    border-color: #c69500;
}

.btn-rts-option:active {
    background: #1e7e34;
    border-color: #1c7430;
}

/* Icon sizes */
.btn-details i,
.btn-edit i,
.btn-rts-option i {
    font-size: 11px;
}

/* Modal Header Styling - COMPACT */
.rts-modal .modal-header {
    padding: 15px 20px 12px;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.rts-modal .modal-header h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #495057;
}

.rts-modal .modal-body {
    padding: 18px 20px;
    background: white;
}

.rts-modal .modal-footer {
    padding: 12px 20px 15px;
    border-top: 1px solid #dee2e6;
    background: #f8f9fa;
    border-radius: 0 0 8px 8px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Modal Footer Buttons */
.rts-modal .modal-footer .btn {
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    border: 1px solid;
    cursor: pointer;
    transition: all 0.3s ease;
}

.rts-modal .modal-footer .btn-secondary {
    background: #6c757d;
    border-color: #6c757d;
    color: white;
}

.rts-modal .modal-footer .btn-secondary:hover {
    background: #5a6268;
    border-color: #545b62;
}

.rts-modal .modal-footer .btn-primary {
    background: #007bff;
    border-color: #007bff;
    color: white;
}

.rts-modal .modal-footer .btn-primary:hover {
    background: #0056b3;
    border-color: #004085;
}

.rts-modal .modal-footer .btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    pointer-events: none;
}

/* Close button styling */
.btn-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.btn-modal-close:hover {
    background: #f8f9fa;
    color: #495057;
}

/* FIXED Action buttons container - ALL BUTTONS SAME WIDTH */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: stretch; /* Changed from flex-start to stretch */
    width: 100%;
}

.action-buttons .btn {
    width: 100% !important;
    min-width: 120px !important; /* Set consistent minimum width */
    max-width: 120px !important; /* Set consistent maximum width */
    text-align: center !important; /* Center text */
    justify-content: center !important; /* Center content */
    padding: 6px 8px !important; /* Consistent padding */
    font-size: 12px !important;
    white-space: nowrap !important;
    box-sizing: border-box !important;
    margin: 0 !important; /* Remove any margin */
}

/* Mobile card actions - FIXED BUTTON WIDTHS */
.mobile-card-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: stretch;
}

.mobile-card-actions .btn {
    flex: 1 1 auto !important;
    min-width: 120px !important;
    max-width: 120px !important;
    justify-content: center !important;
    text-align: center !important;
    padding: 6px 8px !important;
    font-size: 12px !important;
    white-space: nowrap !important;
}

/* Ensure proper stacking order for all modals */
.vue-container .modal {
    z-index: 7000 !important;
}

.vue-container .image-modal {
    z-index: 7500 !important;
}

.vue-container .edit-modal {
    z-index: 7200 !important;
}

.vue-container .rts-modal {
    z-index: 8000 !important;
}

/* Global SweetAlert2 overrides - HIGHEST PRIORITY */
:global(.swal2-container) {
    z-index: 99999 !important;
}

:global(.swal2-popup) {
    z-index: 100000 !important;
}

/* Custom class for top-level SweetAlert */
:global(.swal2-top-level) {
    z-index: 100001 !important;
    position: fixed !important;
}

:global(.swal2-top-level .swal2-popup) {
    z-index: 100002 !important;
}

:global(.swal2-container.swal2-backdrop-show) {
    z-index: 99999 !important;
}

:global(div[aria-labelledby="swal2-title"]) {
    z-index: 100000 !important;
}

:global(.swal2-container[style*="z-index"]) {
    z-index: 99999 !important;
}

:global(.swal2-popup[style*="z-index"]) {
    z-index: 100000 !important;
}

/* Force remove modal backdrop classes when needed */
:global(body.swal2-shown) {
    overflow: hidden !important;
}

:global(body.modal-open) {
    overflow: hidden !important;
}

/* Prevent scroll when modals are open */
.vue-container.modal-open {
    overflow: hidden;
}

/* Additional safety measures for SweetAlert2 */
:global(.swal2-container) {
    pointer-events: auto !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
}

:global(.swal2-backdrop-show) {
    background-color: rgba(0, 0, 0, 0.4) !important;
}

/* Ensure buttons work properly in SweetAlert */
:global(.swal2-actions) {
    z-index: 100003 !important;
}

:global(.swal2-confirm) {
    z-index: 100004 !important;
}

:global(.swal2-cancel) {
    z-index: 100004 !important;
}

/* Fix potential overlay conflicts */
:global(.swal2-container.swal2-backdrop-show .swal2-popup) {
    z-index: 100002 !important;
    position: relative !important;
}

/* Additional protection against modal conflicts */
.modal.rts-modal.swal2-active {
    z-index: 7999 !important;
}

/* Ensure loading states don't interfere */
.loading-spinner,
.loading-spinner-mobile {
    z-index: 1;
    position: relative;
}

/* Prevent any child elements from creating stacking contexts */
.rts-modal * {
    position: relative;
    z-index: auto;
}

.rts-modal .modal-content * {
    position: relative;
    z-index: auto;
}

/* Exception for close button */
.rts-modal .btn-modal-close {
    position: relative;
    z-index: 2;
}

/* Exception for form controls */
.rts-modal .rts-input,
.rts-modal .rts-select,
.rts-modal .rts-textarea {
    position: relative;
    z-index: 1;
}

/* Final safety net - if everything else fails */
:global(.swal2-container) {
    position: fixed !important;
    z-index: 999999 !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

:global(.swal2-popup) {
    position: relative !important;
    z-index: 1000000 !important;
    margin: auto !important;
}

/* Mobile Responsive - MORE COMPACT */
@media (max-width: 768px) {
    .rts-modal {
        padding: 8px !important;
    }

    .rts-modal .modal-content {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        max-height: 92vh !important;
    }

    .rts-form-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .rts-product-info {
        flex-direction: row;
        text-align: left;
        gap: 8px;
        padding: 10px;
        margin-bottom: 12px;
    }

    .product-image-mini {
        width: 45px;
        height: 45px;
        margin: 0;
    }

    .checkbox-group {
        justify-content: flex-start;
        gap: 12px;
    }

    .rts-modal .modal-header {
        padding: 12px 15px 10px;
    }

    .rts-modal .modal-body {
        padding: 12px 15px;
    }

    .rts-modal .modal-footer {
        padding: 10px 15px 12px;
    }

    .rts-modal .modal-header h2 {
        font-size: 15px;
    }

    .product-details h4 {
        font-size: 13px;
        margin-bottom: 4px;
    }

    .product-details p {
        font-size: 11px;
        margin: 0;
    }

    .section-title {
        font-size: 14px;
        margin-bottom: 8px;
        padding-bottom: 4px;
    }

    .rts-fieldset {
        gap: 4px;
    }

    .label-text {
        font-size: 12px;
    }

    .rts-input,
    .rts-select,
    .rts-textarea {
        padding: 6px 8px;
        font-size: 12px;
    }

    .rts-textarea {
        min-height: 50px;
    }

    .refund-status-section {
        padding: 12px;
    }

    .divider {
        margin: 12px 0;
    }

    /* Ensure SweetAlert2 is responsive on mobile */
    :global(.swal2-popup) {
        width: 90% !important;
        max-width: 400px !important;
        margin: 0 auto !important;
    }

    /* Mobile action buttons - consistent width */
    .action-buttons .btn,
    .mobile-card-actions .btn {
        min-width: 100px !important;
        max-width: 100px !important;
        font-size: 11px !important;
        padding: 5px 6px !important;
    }
}

/* Additional responsive fixes for very small screens */
@media (max-width: 480px) {
    .rts-modal {
        padding: 5px !important;
    }

    .rts-modal .modal-content {
        width: 100% !important;
        height: 98vh !important;
        max-height: 98vh !important;
        margin: 1vh auto !important;
        border-radius: 4px !important;
    }

    .rts-form-grid {
        gap: 15px;
    }

    .rts-fieldset {
        gap: 6px;
    }

    .section-title {
        font-size: 16px;
        margin-bottom: 10px;
    }

    /* Very small screen button adjustments */
    .action-buttons .btn,
    .mobile-card-actions .btn {
        min-width: 90px !important;
        max-width: 90px !important;
        font-size: 10px !important;
        padding: 4px 6px !important;
    }
}

/* Force consistent button behavior */
.action-buttons {
    width: 120px; /* Fixed container width */
}

.mobile-card-actions {
    justify-content: space-between;
    align-items: stretch;
}

/* Ensure no button grows beyond intended size */
.btn {
    flex-shrink: 0 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
</style>
