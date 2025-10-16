<template>
    <div class="vue-container testing-module">
        <!-- <div class="top-header">
            <span>Top Header</span>
        </div> -->

        <h2 class="module-title">Testing Module</h2>

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

                                <button
                                    class="btn-showDetails"
                                    @click="toggleDetailsVisibility"
                                >
                                    {{
                                        showDetails
                                            ? "Hide extra columns"
                                            : "Show extra columns"
                                    }}
                                </button>
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
                                <i
                                    v-if="sortColumn === 'datedelivered'"
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
                                @click="sortBy('lastDateUpdate')"
                            >
                                Updated date
                                <i
                                    v-if="sortColumn === 'lastDateUpdate'"
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
                                @click="sortBy('FNSKUviewer')"
                            >
                                Fnsku
                                <i
                                    v-if="sortColumn === 'FNSKUviewer'"
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
                                @click="sortBy('MSKUviewer')"
                            >
                                Msku
                                <i
                                    v-if="sortColumn === 'MSKUviewer'"
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
                                @click="sortBy('ASINviewer')"
                            >
                                Asin
                                <i
                                    v-if="sortColumn === 'ASINviewer'"
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
                            <span class="sortable" @click="sortBy('Outbound')">
                                Outbound
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
                            <span class="sortable" @click="sortBy('Inbound')">
                                Inbound
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
                            <span class="sortable" @click="sortBy('Reserved')">
                                Reserved
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
                        <th
                            class="bg-warning-subtle"
                            style="background-color: antiquewhite"
                            v-if="showDetails"
                        >
                            <span
                                class="sortable"
                                @click="sortBy('Unfulfillable')"
                            >
                                Unfulfillable
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
                        <th>Actions</th>
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
                            <!-- Hidden -->
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
                                    ><strong></strong> {{ item.Reserved }}</span
                                >
                            </td>
                            <td v-if="showDetails">
                                <span
                                    ><strong></strong>
                                    {{ item.Unfulfillable }}</span
                                >
                            </td>
                            <!-- End Hidden -->
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

                            <!-- Button for more details -->
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
                            <i class="fas fa-info-circle"></i>
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
        <div v-if="showImageModal" class="image-modal">
            <div class="modal-overlay" @click="closeImageModal"></div>
            <div class="modal-content">
                <button class="close-button" @click="closeImageModal">
                    &times;
                </button>

                <div class="main-image-container">
                    <button
                        class="nav-button prev"
                        @click="prevImage"
                        v-if="modalImages.length > 1"
                    >
                        &lt;
                    </button>
                    <img
                        :src="modalImages[currentImageIndex]"
                        alt="Product Image"
                        class="modal-main-image"
                    />
                    <button
                        class="nav-button next"
                        @click="nextImage"
                        v-if="modalImages.length > 1"
                    >
                        &gt;
                    </button>
                </div>

                <div class="image-counter">
                    {{ currentImageIndex + 1 }} / {{ modalImages.length }}
                </div>

                <div class="thumbnails-container" v-if="modalImages.length > 1">
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
</template>

<script>
import Testing from "./testing.js";
export default Testing;
</script>
