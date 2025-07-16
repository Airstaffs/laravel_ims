<template>
    <div class="vue-container orders-module">
        <div class="top-header">
            <span>Top Header</span>
        </div>

        <h2 class="module-title">Order Module</h2>

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
                        <th class="">Seller Location</th>
                        <th class="">Tracking Number</th>
                        <th class="">Ordered Condition</th>
                        <th class="">Condition Status</th>
                        <th class="">Ordered Date</th>
                        <th class="">Delivered Date</th>
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
                                            v-if="
                                                countAdditionalImages(item) > 0
                                            "
                                        >
                                            +{{ countAdditionalImages(item) }}
                                        </div>
                                    </div>
                                    <div class="product-info clickable">
                                        <p>RT# : {{ item.rtcounter }}</p>
                                        <p>{{ item.ProductTitle }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.Ebay_seller_location }}</span
                                >
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.trackingnumber }}</span
                                >
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.listedcondition }}</span
                                >
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.itemstatus }}</span
                                >
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.orderdate }}</span
                                >
                            </td>
                            <td>
                                <span
                                    ><strong></strong>
                                    {{ item.datedelivered }}</span
                                >
                            </td>
                            <!-- Button for more details -->
                            <td>
                                <div class="action-buttons">
                                    <button
                                        class="btn-details"
                                        @click="toggleDetails(index)"
                                    >
                                        <i class="fas fa-info-circle"></i> More
                                        Details
                                    </button>
                                    <button
                                        class="btn btn-edit"
                                        @click="openEditModal(item)"
                                    >
                                        Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="expandedRows[index]">
                            <td :colspan="showDetails ? 18 : 12">
                                <div
                                    class="expanded-content p-3 border rounded"
                                >
                                    <p><strong>Expanded Rows Here</strong></p>
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
            <button
                class="btn btn-showDetailsM"
                @click="toggleDetailsVisibility"
            >
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
                        <div
                            class="mobile-product-image clickable"
                            @click="openImageModal(item)"
                        >
                            <img
                                :src="'/images/thumbnails/' + item.img1"
                                :alt="item.ProductTitle || 'Product'"
                                class="product-thumbnail clickable-image"
                                @error="handleImageError($event)"
                            />
                            <div
                                class="image-count-badge"
                                v-if="countAdditionalImages(item) > 0"
                            >
                                +{{ countAdditionalImages(item) }}
                            </div>
                        </div>
                        <div class="mobile-product-info">
                            <p class="mobile-product-name clickable">
                                <span>RT# : {{ item.rtcounter }}</span>
                                <span>{{ item.ProductTitle }}</span>
                            </p>
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
                        <button
                            class="btn btn-details"
                            @click="toggleDetails(index)"
                        >
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                        <button
                            class="btn btn-edit"
                            @click="openEditModal(item)"
                        >
                            Edit
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
                            v-if="modalImages.length > 1"
                        >
                            <i class="bi bi-arrow-left-short"></i>
                        </button>
                        <img
                            :src="modalImages[currentImageIndex]"
                            alt="Product Image"
                            class="modal-main-image"
                            width="100%"
                        />
                        <button
                            class="nav-button next"
                            @click="nextImage"
                            v-if="modalImages.length > 1"
                        >
                            <i class="bi bi-arrow-right-short"></i>
                        </button>
                    </div>

                    <div class="image-counter">
                        {{ currentImageIndex + 1 }} / {{ modalImages.length }}
                    </div>

                    <div
                        class="thumbnails-container"
                        v-if="modalImages.length > 1"
                    >
                        <div
                            v-for="(image, index) in modalImages"
                            :key="index"
                            class="modal-thumbnail"
                            :class="{ active: index === currentImageIndex }"
                            @click="currentImageIndex = index"
                        >
                            <img :src="image" :alt="`Thumbnail ${index + 1}`" />
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
                        <h2>{{ item?.rtcounter || "RT Counter" }}</h2>
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

                            <!-- NOTES FULL WIDTH -->
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
    </div>
</template>

<script>
import Orders from "./orders.js";
export default Orders;
</script>
