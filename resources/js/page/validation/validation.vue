<template>
    <div class="vue-container validation-module">
        <div class="top-header">
            <div class="header-buttons"></div>

            <div class="validation-filter">
                <label for="validationStatusFilter">Status:</label>
                <!-- <select id="validationStatusFilter" v-model="validationStatusFilter" class="valid-select">
                    <option value="">All Status</option>
                    <option v-for="status in uniqueValidationStatuses" :key="status" :value="status">
                        {{ status }}
                    </option>
                </select> -->
                <Select v-model="validationStatusFilter" :options="uniqueValidationStatusesList" optionLabel="label"
                    optionValue="value" placeholder="All Status" class="valid-select" size="small" />
            </div>
        </div>

        <h2 class="module-title">Validation Module</h2>

        <div class="px-4">
            <XDataTable :value="sortedInventory" :paginator="false" :columns="columns" tableClass="desktop-view">

                <template #productname="{ data }">
                    <div class="d-flex align-items-start gap-4">
                        <div style="word-break: break-word; white-space: normal; overflow-wrap: break-word; flex: 1;">
                            <div style="font-size: 14px;">
                                <span>ID# </span>
                                <span v-if="
                                    data.storename ===
                                    'Allrenewed'
                                ">AR {{ data.rtcounter }}</span>
                                <span v-else-if="
                                    data.storename ===
                                    'Renovartech'
                                ">RT {{ data.rtcounter }}</span>
                                <span v-else>{{
                                    data.rtcounter
                                }}</span>
                            </div>
                            <p class="fw-semibold">{{ data.astitle }}</p>
                        </div>
                    </div>
                </template>

                <template #validationStatus="{ data }">
                    <div>
                        <div class="badge" :class="data.validation_status +
                            '-badge'
                            ">
                            {{ data.validation_status }}
                        </div>
                    </div>
                </template>

                <template #ASIN="{ data }">
                    <div>
                        <a :href="`https://www.amazon.com/dp/${data.ASIN}`" target="_blank">
                            {{ data.ASIN }}
                        </a>
                    </div>
                </template>

                <template #serialnumber="{ data }">
                    <div>
                        <a :href="`/houseage?serial=${encodeURIComponent(
                            data.serialnumber
                        )}`" @click.prevent="
                            goToHouseage(data.serialnumber)
                            ">
                            {{ data.serialnumber }}
                        </a>
                    </div>
                </template>

                <template #actions="{ data }">
                    <div>
                        <Button size="small" severity="contrast" variant="text" label="View More" class="text-primary"
                            icon="pi pi-exclamation-circle" @click="openValidationModal(data)" />
                    </div>
                </template>
            </XDataTable>
        </div>

        <!-- Desktop Table Container -->
        <!-- <div class="table-container desktop-view">
            <table>
                <thead>
                    <tr>
                        <th class="sticky-header first-col">
                            <input type="checkbox" @click="toggleAll" v-model="selectAll" />
                        </th>
                        <th class="sticky-header second-sticky">
                            <div class="product-name">
                                <span class="sortable" @click="sortBy('AStitle')">
                                    Product Name
                                    <i v-if="sortColumn === 'AStitle'" :class="sortOrder === 'asc'
                                            ? 'fas fa-sort-up'
                                            : 'fas fa-sort-down'
                                        "></i>
                                </span>
                            </div>
                        </th>
                        <th class="">ASIN</th>
                        <th class="">Order Number</th>
                        <th class="">Serial Number</th>
                        <th class="">Tracking Number</th>
                        <th class="">Basket Number</th>
                        <th class="">Quantity</th>
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
                                    <div class="product-info">
                                        <p>{{ item.astitle }}</p>
                                        <p>
                                            ID#:
                                            <span v-if="
                                                item.storename ===
                                                'Allrenewed'
                                            ">AR {{ item.rtcounter }}</span>
                                            <span v-else-if="
                                                item.storename ===
                                                'Renovartech'
                                            ">RT {{ item.rtcounter }}</span>
                                            <span v-else>{{
                                                item.rtcounter
                                                }}</span>
                                        </p>
                                        <p>
                                            <span class="badge" :class="item.validation_status +
                                                '-badge'
                                                ">
                                                {{ item.validation_status }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <a :href="`https://www.amazon.com/dp/${item.ASIN}`" target="_blank">
                                    {{ item.ASIN }}
                                </a>
                            </td>
                            <td>
                                <span> {{ item.rtid }} </span>
                            </td>
                            <td>
                                <a :href="`/houseage?serial=${encodeURIComponent(
                                    item.serialnumber
                                )}`" @click.prevent="
                                        goToHouseage(item.serialnumber)
                                        ">
                                    {{ item.serialnumber }}
                                </a>
                            </td>
                            <td>
                                <span> {{ item.trackingnumber }} </span>
                            </td>
                            <td>
                                <span> {{ item.basketnumber }} </span>
                            </td>
                            <td>
                                <span> {{ item.quantity }} </span>
                            </td>

                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-viewMore" @click="openValidationModal(item)">
                                        View More
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="expandedRows[index]">
                            <td :colspan="showDetails ? 9 : 9">
                                <div class="expanded-content p-3 border rounded">
                                    <p>
                                        <strong>
                                            External Title provided by Supplier:
                                        </strong>
                                        {{ item.ProductTitle }}
                                    </p>
                                    <p>
                                        <strong>Product Name:</strong>
                                        {{ item.astitle }}
                                    </p>
                                    <p>
                                        <strong>Store Name:</strong>
                                        {{ item.storename }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </template>
</tbody>
</table>
</div> -->

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <div class="mobile-cards">
                <div v-if="loading" class="loading-spinner-mobile">
                    <i class="fas fa-spinner fa-spin"></i>
                    Loading...
                </div>
                <div v-else-if="sortedInventory.length === 0" class="no-data-mobile">
                    No data found
                </div>
                <div class="mobile-card" v-else v-for="(item, index) in sortedInventory" :key="item.id">
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <TableGallery :data="item" :openImageModal="openImageModal" :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages" />
                        <div class="mobile-product-info">
                            <div class="mobile-product-name clickable">
                                <p>
                                    ID#:
                                    <span v-if="item.storename === 'Allrenewed'">AR {{ item.rtcounter }}</span>
                                    <span v-else-if="
                                        item.storename === 'Renovartech'
                                    ">RT {{ item.rtcounter }}</span>
                                    <span v-else>{{ item.rtcounter }}</span>
                                </p>
                                <h6>{{ item.astitle }}</h6>
                                <div class="badge" :class="item.validation_status +
                                    '-badge'
                                    ">
                                    {{ item.validation_status }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <Divider />

                    <div class="mobile-card-details">
                        <div class="row gx-4 gy-2">
                            <div class="col-6 d-flex flex-column gap-2">
                                <div class="mobile-detail-row">
                                    <div class="d-flex flex-wrap">
                                        <span class="mobile-detail-label" style="margin-right: 8px;">Location:</span>
                                        <span class="mobile-detal-value">
                                            {{ item.warehouselocation }}</span>
                                    </div>
                                </div>
                                <div class="mobile-detail-row">
                                    <div class="d-flex flex-wrap">
                                        <span class="mobile-detail-label" style="margin-right: 8px;">Added date:</span>
                                        <span class="mobile-detal-value">
                                            {{ item.datedelivered }}</span>
                                    </div>
                                </div>
                                <div class="mobile-detail-row">
                                    <div class="d-flex flex-wrap">
                                        <span class="mobile-detail-label" style="margin-right: 8px;">Updated
                                            date:</span>
                                        <span class="mobile-detal-value">
                                            {{ item.lastDateUpdate }}</span>
                                    </div>

                                </div>
                                <div class="mobile-detail-row">
                                    <span class="mobile-detail-label">FNSKU:</span>
                                    <span class="mobile-detal-value">
                                        {{ item.FNSKUviewer }}</span>
                                </div>
                                <div class="mobile-detail-row">
                                    <span class="mobile-detail-label">MSKU:</span>
                                    <span class="mobile-detal-value">
                                        {{ item.MSKUviewer }}</span>
                                </div>
                                <div class="mobile-detail-row">
                                    <span class="mobile-detail-label">ASIN:</span>
                                    <span class="mobile-detal-value">
                                        {{ item.ASINviewer }}</span>
                                </div>
                                <div class="mobile-detail-row">
                                    <span class="mobile-detail-label">Status:</span>
                                    <span class="mobile-detal-value">
                                        {{ item.status }}</span>
                                </div>
                            </div>

                            <div class="col-6  d-flex flex-column gap-2">
                                <div class="mobile-detail-row" v-if="showDetails">
                                    <span class="mobile-detail-label">FBM:</span>
                                    <span class="mobile-detal-value">
                                        {{ item.FBMAvailable }}</span>
                                </div>
                                <div class="mobile-detail-row" v-if="showDetails">
                                    <span class="mobile-detail-label">FBA:</span>
                                    <span class="mobile-detal-value">
                                        {{ item.FbaAvailable }}</span>
                                </div>
                                <div class="mobile-detail-row" v-if="showDetails">
                                    <span class="mobile-detail-label">Outbound:</span>
                                    <span class="mobile-detal-value">
                                        {{ item.Outbound }}</span>
                                </div>
                                <div class="mobile-detail-row" v-if="showDetails">
                                    <span class="mobile-detail-label">Inbound:</span>
                                    <span class="mobile-detal-value">
                                        {{ item.Inbound }}</span>
                                </div>
                                <div class="mobile-detail-row" v-if="showDetails">
                                    <span class="mobile-detail-label">Unfulfillable:</span>
                                    <span class="mobile-detal-value">
                                        {{ item.Unfulfillable }}</span>
                                </div>
                                <div class="mobile-detail-row" v-if="showDetails">
                                    <span class="mobile-detail-label">Reserved:</span>
                                    <span class="mobile-detal-value">
                                        {{ item.Reserved }}</span>
                                </div>
                                <!--  -->
                                <div class="mobile-detail-row">
                                    <span class="mobile-detail-label">Fullfilment:</span>
                                    <span class="mobile-detal-value">
                                        {{ item.Fulfilledby }}</span>
                                </div>

                            </div>
                        </div>

                        <!-- Insert Hidden Here -->

                        <div class="mobile-detail-row mt-2">
                            <span class="mobile-detail-label">Serial Number:</span>
                            <span class="mobile-detal-value">
                                {{ item.serialnumber }}</span>
                        </div>
                    </div>

                    <Divider />

                    <div class="d-flex flex-nowrap overflow-auto gap-2 pb-3">
                        <div class="flex-shrink-0">
                            <Button label="Details" size="small" icon="pi pi-info-circle" severity="info"
                                @click="toggleDetails(index)" />
                        </div>

                        <div class="flex-shrink-0">
                            <Button label="Move to Labeling" size="small" icon="pi pi-check-circle"
                                @click="confirmMoveToLabeling(item)" :disabled="isProcessing" />
                        </div>

                        <div class="flex-shrink-0">
                            <Button label="Move to Stockroom" size="small" icon="pi pi-box"
                                @click="confirmMoveToStockroom(item)" :disabled="isProcessing" severity="warn" />
                        </div>

                        <div class="flex-shrink-0">
                            <Button label="Open Validation" size="small" icon="pi pi-verified"
                                @click="openValidationModal(item)" :disabled="isProcessing" severity="help" />
                        </div>
                    </div>



                    <!-- <div class="mobile-card-actions">
                        <button class="btn btn-details" @click="toggleDetails(index)">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                        <button @click="confirmMoveToLabeling(item)" class="btn btn-labeling" :disabled="isProcessing">
                            <i class="bi bi-check-circle"></i> Move to Labeling
                        </button>

                        <button @click="confirmMoveToStockroom(item)" class="btn btn-stockroom"
                            :disabled="isProcessing">
                            <i class="bi bi-box-seam"></i> Move to Stockroom
                        </button>

                        <button class="btn btn-validation" @click="openValidationModal(item)">
                            Open Validation
                        </button>
                    </div> -->

                    <hr v-if="expandedRows[index]" />

                    <div v-if="expandedRows[index]" class="mobile-expanded-content d-flex flex-column gap-3">
                        <div>
                            <p class="fw-semibold" style="font-size: 14px;">External Title provided by
                                Supplier</p>
                            <p style="font-size: 14px"> {{ item.ProductTitle }}</p>
                        </div>
                        <div>
                            <p class="fw-semibold" style="font-size: 14px;">Product Name</p>
                            <p style="font-size: 14px"> {{ item.astitle }}</p>
                        </div>
                        <div>
                            <p class="fw-semibold" style="font-size: 14px;">Store Name </p>
                            <p style="font-size: 14px"> {{ item.storename }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination with centered layout -->
        <div class=" pagination-container">
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

        <!-- Image Modal with Tabs -->
        <div v-if="showImageModal" class="image-modal">
            <div class="modal-overlay" @click="closeImageModal"></div>
            <div class="modal-content">
                <button class="close-button" @click="closeImageModal">
                    &times;
                </button>

                <!-- Tabs for switching between regular, captured, and ASIN images -->
                <div class="image-tabs">
                    <button class="tab-button" :class="{ active: activeTab === 'regular' }"
                        @click="switchTab('regular')" :disabled="regularImages.length === 0">
                        Product Images ({{ regularImages.length }})
                    </button>
                    <button class="tab-button" :class="{ active: activeTab === 'captured' }"
                        @click="switchTab('captured')" :disabled="capturedImages.length === 0">
                        Captured Images ({{ capturedImages.length }})
                    </button>
                    <button class="tab-button" :class="{ active: activeTab === 'asin' }" @click="switchTab('asin')"
                        :disabled="asinImages.length === 0">
                        ASIN Images ({{ asinImages.length }})
                    </button>
                </div>

                <!-- Display message if no images in current category -->
                <div v-if="currentImageSet.length === 0" class="no-images-message">
                    No images available in this category
                </div>

                <!-- Main image display (only shown if we have images) -->
                <div v-if="currentImageSet.length > 0" class="main-image-container">
                    <button class="nav-button prev" @click="prevImage" v-if="currentImageSet.length > 1">
                        &lt;
                    </button>
                    <img :src="currentImageSet[currentImageIndex]" alt="Product Image" class="modal-main-image"
                        @error="handleImageError" />
                    <button class="nav-button next" @click="nextImage" v-if="currentImageSet.length > 1">
                        &gt;
                    </button>
                </div>

                <div class="image-counter" v-if="currentImageSet.length > 0">
                    {{ currentImageIndex + 1 }} / {{ currentImageSet.length }}
                </div>

                <!-- Thumbnails for the current image set -->
                <div class="thumbnails-container" v-if="currentImageSet.length > 1">
                    <div v-for="(image, index) in currentImageSet" :key="index" class="modal-thumbnail"
                        :class="{ active: index === currentImageIndex }" @click="currentImageIndex = index">
                        <img :src="image" :alt="`Thumbnail ${index + 1}`" @error="handleImageError" />
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="showValidationModal" modal header="Product Details" :style="{ width: '90vw' }"
            :breakpoints="{ '960px': '95vw', '640px': '100vw' }">
            <div class="d-flex align-items-center justify-content-between gap-4 flex-wrap">
                <div class="d-flex align-items-center gap-2 " style="width: 900px;">
                    <h6>Serial Number Verification</h6>
                    <InputText class="form-control" size="small" type="text" id="textserial"
                        placeholder="Scan or enter serial number..." />
                    <Button size="small" severity="info" type="submit" label="Verify Serial"
                        style="height: 34px; width: 150px;" />

                </div>
                <div>
                    <Button style="margin-right: 10px;" size="small" icon="pi pi-thumbs-up" label="Mark as Valid"
                        @click="confirmMarkAsValid" />
                    <Button severity="danger" icon="pi pi-thumbs-down" size="small" label="Mark as Invalid"
                        @click="confirmMarkAsInvalid" />
                </div>

            </div>
            <div class="row mt-4">
                <div class="col-md-2">
                    <div class="form-col-left">
                        <!-- <Card>
                            <template #title>
                                <h4 style="font-size: 1.5rem;">Serial Number Verification</h4>
                            </template>
                            <template #content>
                                <form class="text-center">
                                    <InputText class="form-control" size="small" type="text" id="textserial"
                                        placeholder="Scan or enter serial number..." />

                                    <Button size="small" severity="info" type="submit" class="btn btn-submit mt-2">
                                        Verify Serial
                                    </Button>
                                </form>
                            </template>
                        </Card> -->

                        <div>
                            <div class="image-section" v-show="imageList.length && imageList">

                                <div class="main-image">
                                    <img :src="activeImageUrl" alt="Main Product Image" loading="lazy"
                                        @error="onImageErrorMain" />
                                </div>


                                <div class="thumbnail-carousel ">
                                    <div v-for="(img, index) in imageList" :key="index" :class="[
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

                            <p class="image-label text-center ">
                                <label>
                                    <p>Images from Product</p>
                                </label>
                            </p>
                        </div>


                        <Divider />

                        <div>
                            <div class="image-section" v-if="asinImageList.length">
                                <div class="main-image">
                                    <img :src="activeAsinImageUrl" alt="Main ASIN Image" loading="lazy"
                                        @error="onImageErrorMain" />
                                </div>

                                <div class="thumbnail-carousel">
                                    <div v-for="(img, index) in asinImageList" :key="'asin-' + index" :class="[
                                        'thumbnail',
                                        {
                                            active:
                                                index === activeAsinIndex,
                                        },
                                    ]" @click="activeAsinIndex = index" @mouseenter="activeAsinIndex = index">
                                        <img :src="asinBasePath + img" alt="ASIN Thumbnail" loading="lazy"
                                            @error="onThumbnailError($event)" />
                                    </div>
                                </div>
                            </div>
                            <p class="asin-label text-center" v-if="ASIN">
                                <label>
                                    <p>Image from <strong>ASIN</strong></p>
                                    <p>{{ ASIN }}</p>
                                </label>
                            </p>
                        </div>


                    </div>
                </div>
                <div class="col-md-10 " style="font-size: 14px;">
                    <div>
                        <h3>{{ item.ProductTitle }}</h3>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <!----Basic Details Section -->
                            <h5 class="text-primary">Basic Details</h5>
                            <Divider />

                            <div class="d-flex flex-column gap-3">
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">Serial Number</span>
                                    <span class="text-secondary">{{ item.serialnumber }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="fw-semibold">Date Delivered</span>
                                    <span>{{ item.shipdate }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="fw-semibold">Order Date</span>
                                    <span>{{ item.orderdate }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="fw-semibold">Seller</span>
                                    <span>{{ item.seller }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="fw-semibold">Store</span>
                                    <span>{{ item.storename }}</span>
                                </div>
                            </div>

                            <!------Shipping and Locaton Section -->
                            <h5 class="text-primary  mt-4">Shipping and Location</h5>
                            <Divider />
                            <div class="d-flex flex-column gap-3">
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">Order Number</span>
                                    <span class="text-secondary">{{ item.rtid }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">Tracking</span>
                                    <span class="text-secondary">{{ item.trackingnumber }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">Basket Number</span>
                                    <span class="text-secondary">{{ item.basketnumber }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">Item Number</span>
                                    <span class="text-secondary">{{ item.itemnumber }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">Location</span>
                                    <span class="text-secondary">{{ item.ProductModuleLoc }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!----Product Details Section -->
                            <h5 class="text-primary">Product Details</h5>
                            <Divider />
                            <div class="d-flex flex-column gap-3">
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">ASIN</span>
                                    <span class="text-secondary">{{ item.ASIN }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">FNSKU</span>
                                    <span class="text-secondary">{{ item.FNSKU }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">MSKU</span>
                                    <span class="text-secondary">{{ item.MSKU }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">RPN</span>
                                    <span class="text-secondary">{{ item.RPN }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">PRD</span>
                                    <span class="text-secondary">{{ item.PRD }}</span>
                                </div>
                            </div>
                            <!---Status Information Section--->
                            <h5 class="text-primary mt-4">Status Information</h5>
                            <Divider />
                            <div class="d-flex flex-column gap-3">
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">Grading</span>
                                    <span class="text-secondary">{{ item.grading }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">Priority</span>
                                    <span class="text-secondary">{{ item.priorityrank }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between ">
                                    <span class="fw-semibold">Validation</span>
                                    <div class="badge" :class="item.validation_status +
                                        '-badge'
                                        ">
                                        {{ item.validation_status }}
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!---Additional Information-->
                    <div class="mt-5">
                        <h5 class="text-primary">Additional Information</h5>
                        <Divider />
                        <p class="text-secondary">{{ item.notes }} Lorem ipsum dolor sit amet
                            consectetur
                            adipisicing
                            elit.
                            Debitis nisi
                            incidunt
                            similique hic sed illum magni error cupiditate sint natus!</p>
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- <div v-if="showValidationModal" class="modal validation-modal">
            <div class="modal-overlay" @click="closeValidationModal"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h3>Product Details</h3>

                    <button class="btn btn-modal-close" @click="closeValidationModal">
                        &times;
                    </button>
                </div>

                <div class="modal-body">
                    <div class="form-grid-wrapper">
                        <div class="form-col-left">
                            <div class="image-section" v-if="imageList.length">
                           
                                <div class="main-image">
                                    <img :src="activeImageUrl" alt="Main Product Image" loading="lazy"
                                        @error="onImageErrorMain" />
                                </div>

                        
                                <div class="thumbnail-carousel">
                                    <div v-for="(img, index) in imageList" :key="index" :class="[
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

                            <p class="image-label text-center">
                                <label>
                                    <p>Images from Product</p>
                                </label>
                            </p>

                            <hr />

                            <div class="image-section" v-if="asinImageList.length">
                                <div class="main-image">
                                    <img :src="activeAsinImageUrl" alt="Main ASIN Image" loading="lazy"
                                        @error="onImageErrorMain" />
                                </div>

                                <div class="thumbnail-carousel">
                                    <div v-for="(img, index) in asinImageList" :key="'asin-' + index" :class="[
                                        'thumbnail',
                                        {
                                            active:
                                                index === activeAsinIndex,
                                        },
                                    ]" @click="activeAsinIndex = index" @mouseenter="activeAsinIndex = index">
                                        <img :src="asinBasePath + img" alt="ASIN Thumbnail" loading="lazy"
                                            @error="onThumbnailError($event)" />
                                    </div>
                                </div>
                            </div>

                            <p class="asin-label text-center" v-if="ASIN">
                                <label>
                                    <p>Image from <strong>ASIN</strong></p>
                                    <p>{{ ASIN }}</p>
                                </label>
                            </p>
                        </div>

                        <div class="form-col-center">
                            <div class="form-section center-section other-section">
                                <div class="title-section">
                                    <h3>{{ item.ProductTitle }}</h3>
                                </div>

                                <div class="validation-details">
                                    <div class="basic-details-section shadow">
                                        <h3 class="form-section-heading">
                                            Basic Details
                                        </h3>
                                        <fieldset>
                                            <label>
                                                <span>Serial Number: </span>
                                                <span>
                                                    {{ item.serialnumber }}
                                                </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>Date Delivered: </span>
                                                <span>
                                                    {{ item.shipdate }}
                                                </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>Order Date: </span>
                                                <span>
                                                    {{ item.orderdate }}
                                                </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>Seller: </span>
                                                <span>{{ item.seller }}</span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>Store: </span>
                                                <span>
                                                    {{ item.storename }}
                                                </span>
                                            </label>
                                        </fieldset>
                                    </div>

                                    <div class="basic-details-section shadow">
                                        <h3 class="form-section-heading">
                                            Product Details
                                        </h3>
                                        <fieldset>
                                            <label>
                                                <span>ASIN: </span>
                                                <span>
                                                    {{ item.ASIN }}
                                                </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>FNSKU: </span>
                                                <span>
                                                    {{ item.FNSKU }}
                                                </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>MSKU: </span>
                                                <span>
                                                    {{ item.MSKU }}
                                                </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>RPN: </span>
                                                <span>{{ item.RPN }}</span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>PRD: </span>
                                                <span>
                                                    {{ item.PRD }}
                                                </span>
                                            </label>
                                        </fieldset>
                                    </div>

                                    <div class="basic-details-section shadow">
                                        <h3 class="form-section-heading">
                                            Shipping & Location
                                        </h3>
                                        <fieldset>
                                            <label>
                                                <span>Order Number: </span>
                                                <span>{{ item.rtid }}</span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>Tracking: </span>
                                                <span>
                                                    {{ item.trackingnumber }}
                                                </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>Basket Number: </span>
                                                <span>
                                                    {{ item.basketnumber }}
                                                </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>Item Number: </span>
                                                <span>
                                                    {{ item.itemnumber }}
                                                </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>Location: </span>
                                                <span>
                                                    {{ item.ProductModuleLoc }}
                                                </span>
                                            </label>
                                        </fieldset>
                                    </div>

                                    <div class="basic-details-section shadow">
                                        <h3 class="form-section-heading">
                                            Status Information
                                        </h3>
                                        <fieldset>
                                            <label>
                                                <span>Grading: </span>
                                                <span>{{ item.grading }}</span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>Priority: </span>
                                                <span>
                                                    {{ item.priorityrank }}
                                                </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span>Validation: </span>
                                                <span>
                                                    {{ item.validation_status }}
                                                </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span></span>
                                                <span> </span>
                                            </label>
                                        </fieldset>
                                        <fieldset>
                                            <label>
                                                <span></span>
                                                <span> </span>
                                            </label>
                                        </fieldset>
                                    </div>

                                    <div class="basic-details-section shadow">
                                        <h3 class="form-section-heading">
                                            Additional Information
                                        </h3>
                                        <fieldset>
                                            <label>
                                                <span>Notes: </span>
                                                <span>{{ item.notes }}</span>
                                            </label>
                                        </fieldset>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-col-right">
                            <div class="bg-white rounded shadow p-4">
                                <div class="border-bottom pb-2">
                                    <h3 class="text-dark mb-0">
                                        Serial Number Verification
                                    </h3>
                                </div>

                                <form class="serialVerificationForm">
                                    <input class="form-control" type="text" id="textserial"
                                        placeholder="Scan or enter serial number..." />

                                    <button type="submit" class="btn btn-submit">
                                        Verify Serial
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-success" @click="confirmMarkAsValid">
                        Mark as Valid
                    </button>

                    <button class="btn btn-danger" @click="confirmMarkAsInvalid">
                        Mark as Invalid
                    </button>
                </div>
            </div>
        </div> -->

        <!-- Add this confirmation modal HTML to your template section -->
        <!-- Confirmation Modal -->

        <Dialog v-model:visible="showConfirmationModal" modal :header="confirmationTitle" :style="{ width: '90vw' }">
            <p>{{ confirmationMessage }}</p>
            <template #footer>
                <div class="d-flex gap-2">
                    <Button size="small" severity="danger" @click="cancelConfirmation">Cancel</Button>
                    <Button size="small" :class="{
                        'btn-validation':
                            confirmationActionType === 'validation',
                        'btn-stockroom':
                            confirmationActionType === 'stockroom',
                    }" @click="confirmAction"> Yes, Proceed</Button>
                </div>
            </template>
        </Dialog>
        <!-- <div class=" confirmation-modal modal" v-if="showConfirmationModal">
            <div class="modal-overlay" @click="cancelConfirmation"></div>
            <div class="confirmation-modal-content">
                <div class="confirmation-modal-header">
                    <h3>{{ confirmationTitle }}</h3>
                    <button class="close-button" @click="cancelConfirmation">
                        &times;
                    </button>
                </div>
                <div class="confirmation-modal-body">
                    <p>{{ confirmationMessage }}</p>
                </div>
                <div class="confirmation-modal-footer">
                    <button class="btn-cancel" @click="cancelConfirmation">
                        Cancel
                    </button>
                    <button class="btn-confirm" @click="confirmAction" :class="{
                        'btn-validation':
                            confirmationActionType === 'validation',
                        'btn-stockroom':
                            confirmationActionType === 'stockroom',
                    }">
                        Yes, Proceed
                    </button>
                </div>
            </div>
        </div> -->

        <!-- Validation Modal -->
        <!-- <div class="validation-modal" v-if="showValidationModal && currentValidationItem">
            <div class="modal-overlay" @click="closeValidationModal"></div>
            <div class="validation-modal-content">
                <div class="validation-modal-header">
                    <h3>
                        Validate Item #

                        <span v-if="
                            currentValidationItem.storename === 'Allrenewed'
                        ">AR {{ currentValidationItem.rtcounter }}</span>
                        <span v-else-if="
                            currentValidationItem.storename ===
                            'Renovartech'
                        ">RT {{ currentValidationItem.rtcounter }}</span>
                        <span v-else>{{
                            currentValidationItem.rtcounter
                            }}</span>
                    </h3>
                    <button class="close-button" @click="closeValidationModal">
                        &times;
                    </button>
                </div>

                <div class="validation-modal-body">

                    <div class="validation-product-details">
                        <h4>Product Details</h4>
                        <div class="validation-detail-row">
                            <strong>ID Number:</strong>
                            <span>
                                <span v-if="
                                    currentValidationItem.storename ===
                                    'Allrenewed'
                                ">AR
                                    {{ currentValidationItem.rtcounter }}</span>
                                <span v-else-if="
                                    currentValidationItem.storename ===
                                    'Renovartech'
                                ">RT
                                    {{ currentValidationItem.rtcounter }}</span>
                                <span v-else>{{
                                    currentValidationItem.rtcounter
                                    }}</span>
                            </span>
                        </div>
                        <div class="validation-detail-row">
                            <strong>Product Name:</strong>
                            <span>{{ currentValidationItem.astitle }}</span>
                        </div>
                        <div class="validation-detail-row">
                            <strong>External Title:</strong>
                            <span>{{
                                currentValidationItem.ProductTitle
                                }}</span>
                        </div>
                        <div class="validation-detail-row">
                            <strong>FNSKU:</strong>
                            <span>
                                {{ currentValidationItem.FNSKUviewer }}
                                <template v-if="currentValidationItem.asin">
                                    <br />[ASIN:
                                    {{ currentValidationItem.asin }}]
                                </template>
                            </span>
                        </div>
                        <div class="validation-detail-row">
                            <strong>Serial Number:</strong>
                            <span>{{
                                currentValidationItem.serialnumber
                                }}</span>
                        </div>
                        <div class="validation-detail-row">
                            <strong>Location:</strong>
                            <span>{{
                                currentValidationItem.warehouselocation
                                }}</span>
                        </div>
                        <div class="validation-detail-row">
                            <strong>Current Status:</strong>
                            <span :style="{
                                color:
                                    currentValidationItem.validation_status ===
                                        'validated'
                                        ? 'green'
                                        : 'orange',
                            }">
                                {{ currentValidationItem.validation_status }}
                            </span>
                        </div>
                    </div>

       
                    <div class="validation-images-section">
                        <h4>Product Images</h4>


                        <div class="compare-gallery">
                            <h5>Image Comparison</h5>
                            <div class="compare-container">
                                <div class="compare-item">
                                    <div class="compare-title">
                                        Supplier Image
                                    </div>
                                    <div class="compare-subtitle">
                                        {{ currentValidationItem.ProductTitle }}
                                    </div>
                                    <div class="compare-image-container">
                                        <img :src="'/images/thumbnails/' +
                                            currentValidationItem.img1
                                            " :alt="currentValidationItem.ProductTitle ||
                                                'Supplier Image'
                                                " @error="handleImageError($event)" class="compare-image" />
                                    </div>
                                </div>
                                <div class="compare-item">
                                    <div class="compare-title">
                                        From IMS fetch from Amazon
                                    </div>
                                    <div class="compare-subtitle">
                                        {{ currentValidationItem.astitle }}
                                    </div>
                                    <div class="compare-image-container">
                                        <img v-if="currentValidationItem.asin"
                                            :src="`/images/asinimg/${currentValidationItem.asin}.png`" :alt="currentValidationItem.astitle ||
                                                'Amazon Image'
                                                " @error="handleImageError($event)" class="compare-image" />
                                        <div v-else class="no-asin-image">
                                            No ASIN image available
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="validation-image-tabs">
                            <button class="validation-tab-button" :class="{
                                active: validationActiveTab === 'product',
                            }" @click="switchValidationTab('product')">
                                Product Images
                            </button>
                            <button class="validation-tab-button" :class="{
                                active: validationActiveTab === 'captured',
                            }" @click="switchValidationTab('captured')">
                                Captured Images
                            </button>
                            <button class="validation-tab-button" :class="{
                                active: validationActiveTab === 'asin',
                            }" @click="switchValidationTab('asin')">
                                ASIN Images
                            </button>
                        </div>

              
                        <div v-if="validationActiveTab === 'product'" class="validation-images-grid">
              
                            <div class="validation-main-image">
                                <img :src="'/images/thumbnails/' +
                                    currentValidationItem.img1
                                    " :alt="currentValidationItem.astitle" @error="handleImageError($event)" />
                            </div>

              
                            <div class="validation-thumbnails">
                        
                                <template v-for="i in 15" :key="`img-${i}`">
                                    <div v-if="
                                        i > 1 &&
                                        currentValidationItem['img' + i] &&
                                        currentValidationItem['img' + i] !==
                                        'NULL'
                                    " class="validation-thumbnail">
                                        <img :src="'/images/thumbnails/' +
                                            currentValidationItem['img' + i]
                                            " :alt="`Image ${i}`" @error="handleImageError($event)" />
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div v-if="validationActiveTab === 'captured'" class="validation-images-grid">
                            <div class="validation-no-images" v-if="!hasValidationCapturedImages">
                                No captured images available
                            </div>
                            <div v-else class="validation-thumbnails-full">
                                <template v-if="currentValidationItem.capturedImages">
                                    <template v-for="i in 12" :key="`captured-${i}`">
                                        <div v-if="
                                            currentValidationItem
                                                .capturedImages[
                                            'capturedimg' + i
                                            ] &&
                                            currentValidationItem
                                                .capturedImages[
                                            'capturedimg' + i
                                            ] !== 'NULL'
                                        " class="validation-thumbnail captured">
                                            <img :src="'/images/product_images/' +
                                                (currentValidationItem.company ||
                                                    'Airstaffs') +
                                                '/' +
                                                currentValidationItem
                                                    .capturedImages[
                                                'capturedimg' + i
                                                ]
                                                " :alt="`Captured ${i}`" @error="
                                                    handleImageError($event)
                                                    " />
                                        </div>
                                    </template>
                                </template>
                            </div>
                        </div>

                        <div v-if="validationActiveTab === 'asin'" class="validation-images-grid">
                            <div class="validation-no-images" v-if="!currentValidationItem.asin">
                                No ASIN information available
                            </div>
                            <div v-else-if="!currentValidationItemAsinLoaded" class="validation-loading">
                                Loading ASIN images...
                            </div>
                            <div v-else-if="
                                currentValidationItemAsinImages.length === 0
                            " class="validation-no-images">
                                No ASIN images available
                            </div>
                            <div v-else class="validation-thumbnails-full">
                                <div v-for="(
image, index
                                    ) in currentValidationItemAsinImages" :key="`asin-${index}`"
                                    class="validation-thumbnail asin">
                                    <img :src="image" :alt="`ASIN Image ${index + 1}`"
                                        @error="handleImageError($event)" />
                                </div>
                            </div>
                        </div>
                    </div>

  
                    <div class="validation-notes-section">

                        <div class="validation-error" v-if="validationErrors">
                            {{ validationErrors }}
                        </div>
                    </div>
                </div>

                <div class="validation-modal-footer">
                    <button class="btn-cancel" @click="closeValidationModal" :disabled="isProcessingValidation">
                        Cancel
                    </button>
                    <button class="btn-invalid" @click="confirmMarkAsInvalid" :disabled="isProcessingValidation">
                        <i class="bi bi-x-circle"></i> Mark as Invalid
                    </button>
                    <button class="btn-valid" @click="confirmMarkAsValid" :disabled="isProcessingValidation">
                        <i class="bi bi-check-circle"></i> Mark as Valid
                    </button>
                </div>
            </div>
        </div> -->

        <!-- Validation Confirmation Modal -->
        <div class="validation-confirmation-modal" v-if="showConfirmationModal && confirmationActionType">
            <div class="validation-confirmation-overlay" @click="cancelConfirmation"></div>
            <div class="validation-confirmation-content">
                <div class="validation-confirmation-header" :class="{
                    'header-valid': confirmationActionType === 'valid',
                    'header-invalid': confirmationActionType === 'invalid',
                    'header-default': !['valid', 'invalid'].includes(
                        confirmationActionType
                    ),
                }">
                    <div class="header-icon-container">
                        <i class="header-icon" :class="{
                            'bi bi-check-circle-fill':
                                confirmationActionType === 'valid',
                            'bi bi-x-circle-fill':
                                confirmationActionType === 'invalid',
                            'bi bi-question-circle-fill': ![
                                'valid',
                                'invalid',
                            ].includes(confirmationActionType),
                        }"></i>
                    </div>
                    <h3>{{ confirmationTitle }}</h3>
                    <button class="close-button" @click="cancelConfirmation">
                        &times;
                    </button>
                </div>

                <div class="validation-confirmation-body">
                    <p>{{ confirmationMessage }}</p>
                </div>

                <div class="validation-confirmation-footer">
                    <button class="btn-no" @click="cancelConfirmation">
                        <i class="bi bi-x"></i> No
                    </button>
                    <button class="btn-yes" @click="
                        confirmationActionType === 'valid'
                            ? markAsValid()
                            : markAsInvalid()
                        " :class="{
                            'btn-valid-confirm':
                                confirmationActionType === 'valid',
                            'btn-invalid-confirm':
                                confirmationActionType === 'invalid',
                            'btn-default-confirm': ![
                                'valid',
                                'invalid',
                            ].includes(confirmationActionType),
                        }">
                        <i class="bi bi-check-lg"></i> Yes
                    </button>
                </div>
            </div>
        </div>
        <!-- End of Validation Confirmation Modal -->
    </div>
</template>

<script>
import Validation from "./validation.js";
import { Badge, Button, Divider, Dialog, Card, InputText, Select } from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
// export default Validation;

const TABLE_COLUMNS = [
    {
        selectionMode: "multiple",
        header: "",
        style: { width: "3rem", minWidth: "3rem" },
        headerStyle: "width: 3rem; min-width: 3rem; max-width: 3rem; padding: 0.25rem;",
        bodyStyle: "width: 3rem; min-width: 3rem; max-width: 3rem; padding: 0.25rem;",
    },
    {
        header: "Product Name",
        field: 'astitle',
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "productname",
        style: { maxWidth: "20rem" },
    },
    {
        header: "Status",
        field: 'validation_status',
        headerStyle: "font-size: 16px;",
        slot: "validationStatus",
        style: { width: "6rem", minWidth: "6rem" },
    },
    {
        header: "ASIN",
        field: 'ASIN',
        headerStyle: "font-size: 16px;",
        slot: "ASIN"
    },
    {
        header: "Order Number",
        field: 'rtid',
        headerStyle: "font-size: 16px;",
    },
    {
        header: "Serial Number",
        field: "serialnumber",
        slot: "serialnumber",
        headerStyle: "font-size: 16px;",
    },
    {
        header: "Tracking Number",
        field: "trackingnumber",
        headerStyle: "font-size: 16px;",
        bodyStyle: "font-size: 14px",
        style: { width: "16rem", minWidth: "16rem" },
    },
    {
        header: "Basket Number",
        field: "basketnumber",
        headerStyle: "font-size: 16px;",
    },
    {
        header: "Quantity",
        field: "quantity",
        headerStyle: "font-size: 16px;",
    }
]

export default {
    mixins: [Validation],
    components: {
        XDataTable,
        Button,
        Badge,
        TableGallery,
        Divider,
        Dialog,
        Card,
        InputText,
        Select
    },
    data() {
        return {
            columns: TABLE_COLUMNS
        }
    },
    computed: {
        uniqueValidationStatusesList() {
            let validationStatus = this.uniqueValidationStatuses.map(stat => ({
                value: stat,
                label: stat.charAt(0).toUpperCase() + stat.slice(1) //capitalize first letter
            }));

            return [
                { value: "", label: "All Status" },
                ...validationStatus

            ]
        }
    }
}
</script>