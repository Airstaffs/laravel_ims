<template>
    <div class="vue-container validation-module">
        <TitlePage
            title="Validation Module"
            subtitle="Review and validate critical order and inventory data to ensure all records are complete and accurate before processing."
        />

        <AnimateDiv :delay="200" class="px-4">
            <div class="search-container">
                <fieldset class="d-flex align-items-center gap-3">
                    <label for="moduleFilter">Status:</label>
                    <Select
                        v-model="validationStatusFilter"
                        :options="uniqueValidationStatusesList"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="All Status"
                        class="select-form"
                        size="small"
                    />
                </fieldset>
            </div>
            <XDataTable
                :value="sortedInventory"
                :paginator="false"
                :columns="columns"
                tableClass="desktop-view"
                selectionMode="multiple"
                dataKey="ProductID"
            >
                <template #gallery="{ data }">
                    <div
                        class="d-flex justify-content-center align-items-center"
                    >
                        <!-- Use custom image display for captured images -->
                        <div
                            v-if="
                                data.capturedImages &&
                                data.capturedImages.capturedimg1
                            "
                            class="gallery-thumbnail position-relative"
                            @click="openImageModal(data)"
                            style="cursor: pointer"
                        >
                            <img
                                :src="`/images/product_images/${
                                    data.company || 'Airstaffs'
                                }/${data.capturedImages.capturedimg1}`"
                                :alt="getDisplayTitle(data)"
                                style="
                                    width: 50px;
                                    height: 50px;
                                    object-fit: cover;
                                    border-radius: 4px;
                                "
                                @error="handleImageError"
                            />
                            <span
                                v-if="countCapturedImages(data) > 1"
                                class="position-absolute bg-primary text-white rounded-circle"
                                style="
                                    top: -5px;
                                    right: -5px;
                                    min-width: 20px;
                                    height: 20px;
                                    font-size: 0.65rem;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    padding: 0 4px;
                                "
                            >
                                +{{ countCapturedImages(data) - 1 }}
                            </span>
                        </div>

                        <!-- Use regular product images as fallback -->
                        <div
                            v-else-if="data.img1 && data.img1 !== 'NULL'"
                            class="gallery-thumbnail position-relative"
                            @click="openImageModal(data)"
                            style="cursor: pointer"
                        >
                            <img
                                :src="`/images/thumbnails/${data.img1}`"
                                :alt="getDisplayTitle(data)"
                                style="
                                    width: 50px;
                                    height: 50px;
                                    object-fit: cover;
                                    border-radius: 4px;
                                "
                                @error="handleImageError"
                            />
                            <span
                                v-if="countAllImages(data) > 1"
                                class="position-absolute bg-primary text-white rounded-circle"
                                style="
                                    top: -5px;
                                    right: -5px;
                                    min-width: 20px;
                                    height: 20px;
                                    font-size: 0.65rem;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    padding: 0 4px;
                                "
                            >
                                +{{ countAllImages(data) - 1 }}
                            </span>
                        </div>

                        <!-- Fallback icon if no images -->
                        <div
                            v-else
                            class="d-flex justify-content-center align-items-center"
                            style="
                                width: 50px;
                                height: 50px;
                                background-color: #f0f0f0;
                                border-radius: 4px;
                            "
                        >
                            <i
                                class="pi pi-image"
                                style="font-size: 1.5rem; color: #999"
                            ></i>
                        </div>
                    </div>
                </template>
                <template #productname="{ data }">
                    <div class="d-flex align-items-start gap-4">
                        <div
                            style="
                                word-break: break-word;
                                white-space: normal;
                                overflow-wrap: break-word;
                                flex: 1;
                            "
                        >
                            <div style="font-size: 14px">
                                <span>ID# </span>
                                <span v-if="data.storename === 'Allrenewed'"
                                    >AR {{ data.rtcounter }}</span
                                >
                                <span
                                    v-else-if="data.storename === 'Renovartech'"
                                    >RT {{ data.rtcounter }}</span
                                >
                                <span v-else>{{ data.rtcounter }}</span>
                            </div>
                            <p class="fw-semibold">
                                {{ getDisplayTitle(data) }}
                            </p>
                        </div>
                    </div>
                </template>
                <template #validationStatus="{ data }">
                    <div>
                        <div
                            class="badge"
                            :class="data.validation_status + '-badge'"
                        >
                            {{ data.validation_status }}
                        </div>
                    </div>
                </template>
                <template #ASIN="{ data }">
                    <div>
                        <a
                            :href="`https://www.amazon.com/dp/${data.ASIN}`"
                            target="_blank"
                        >
                            {{ data.ASIN }}
                        </a>
                    </div>
                </template>
                <template #serialnumber="{ data }">
                    <div>
                        <a
                            :href="`/houseage?serial=${encodeURIComponent(
                                data.serialnumber
                            )}`"
                            @click.prevent="goToHouseage(data.serialnumber)"
                        >
                            {{ data.serialnumber }}
                        </a>
                    </div>
                </template>

                <template #actions="{ data }">
                    <div>
                        <Button
                            size="small"
                            severity="contrast"
                            variant="text"
                            label="View More"
                            class="text-primary"
                            icon="pi pi-exclamation-circle"
                            @click="openValidationModal(data)"
                        />
                    </div>
                </template>
            </XDataTable>
        </AnimateDiv>

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
                        <TableGallery
                            :data="item"
                            :openImageModal="openImageModal"
                            :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages"
                        />
                        <div class="mobile-product-info">
                            <div class="mobile-product-name clickable">
                                <p>
                                    ID#:
                                    <span v-if="item.storename === 'Allrenewed'"
                                        >AR {{ item.rtcounter }}</span
                                    >
                                    <span
                                        v-else-if="
                                            item.storename === 'Renovartech'
                                        "
                                        >RT {{ item.rtcounter }}</span
                                    >
                                    <span v-else>{{ item.rtcounter }}</span>
                                </p>
                                <h6>{{ item.astitle }}</h6>
                                <div
                                    class="badge"
                                    :class="item.validation_status + '-badge'"
                                >
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
                                        <span
                                            class="mobile-detail-label"
                                            style="margin-right: 8px"
                                            >Location:</span
                                        >
                                        <span class="mobile-detal-value">
                                            {{ item.warehouselocation }}</span
                                        >
                                    </div>
                                </div>
                                <div class="mobile-detail-row">
                                    <div class="d-flex flex-wrap">
                                        <span
                                            class="mobile-detail-label"
                                            style="margin-right: 8px"
                                            >Added date:</span
                                        >
                                        <span class="mobile-detal-value">
                                            {{ item.datedelivered }}</span
                                        >
                                    </div>
                                </div>
                                <div class="mobile-detail-row">
                                    <div class="d-flex flex-wrap">
                                        <span
                                            class="mobile-detail-label"
                                            style="margin-right: 8px"
                                            >Updated date:</span
                                        >
                                        <span class="mobile-detal-value">
                                            {{ item.lastDateUpdate }}</span
                                        >
                                    </div>
                                </div>
                                <div class="mobile-detail-row">
                                    <span class="mobile-detail-label"
                                        >FNSKU:</span
                                    >
                                    <span class="mobile-detal-value">
                                        {{ item.FNSKUviewer }}</span
                                    >
                                </div>
                                <div class="mobile-detail-row">
                                    <span class="mobile-detail-label"
                                        >MSKU:</span
                                    >
                                    <span class="mobile-detal-value">
                                        {{ item.MSKUviewer }}</span
                                    >
                                </div>
                                <div class="mobile-detail-row">
                                    <span class="mobile-detail-label"
                                        >ASIN:</span
                                    >
                                    <span class="mobile-detal-value">
                                        {{ item.ASINviewer }}</span
                                    >
                                </div>
                                <div class="mobile-detail-row">
                                    <span class="mobile-detail-label"
                                        >Status:</span
                                    >
                                    <span class="mobile-detal-value">
                                        {{ item.status }}</span
                                    >
                                </div>
                            </div>

                            <div class="col-6 d-flex flex-column gap-2">
                                <div
                                    class="mobile-detail-row"
                                    v-if="showDetails"
                                >
                                    <span class="mobile-detail-label"
                                        >FBM:</span
                                    >
                                    <span class="mobile-detal-value">
                                        {{ item.FBMAvailable }}</span
                                    >
                                </div>
                                <div
                                    class="mobile-detail-row"
                                    v-if="showDetails"
                                >
                                    <span class="mobile-detail-label"
                                        >FBA:</span
                                    >
                                    <span class="mobile-detal-value">
                                        {{ item.FbaAvailable }}</span
                                    >
                                </div>
                                <div
                                    class="mobile-detail-row"
                                    v-if="showDetails"
                                >
                                    <span class="mobile-detail-label"
                                        >Outbound:</span
                                    >
                                    <span class="mobile-detal-value">
                                        {{ item.Outbound }}</span
                                    >
                                </div>
                                <div
                                    class="mobile-detail-row"
                                    v-if="showDetails"
                                >
                                    <span class="mobile-detail-label"
                                        >Inbound:</span
                                    >
                                    <span class="mobile-detal-value">
                                        {{ item.Inbound }}</span
                                    >
                                </div>
                                <div
                                    class="mobile-detail-row"
                                    v-if="showDetails"
                                >
                                    <span class="mobile-detail-label"
                                        >Unfulfillable:</span
                                    >
                                    <span class="mobile-detal-value">
                                        {{ item.Unfulfillable }}</span
                                    >
                                </div>
                                <div
                                    class="mobile-detail-row"
                                    v-if="showDetails"
                                >
                                    <span class="mobile-detail-label"
                                        >Reserved:</span
                                    >
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
                            </div>
                        </div>

                        <!-- Insert Hidden Here -->

                        <div class="mobile-detail-row mt-2">
                            <span class="mobile-detail-label"
                                >Serial Number:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.serialnumber }}</span
                            >
                        </div>
                    </div>

                    <Divider />

                    <div class="d-flex flex-nowrap overflow-auto gap-2 pb-3">
                        <div class="flex-shrink-0">
                            <Button
                                label="Details"
                                size="small"
                                icon="pi pi-info-circle"
                                severity="info"
                                @click="toggleDetails(index)"
                            />
                        </div>

                        <div class="flex-shrink-0">
                            <Button
                                label="Move to Labeling"
                                size="small"
                                icon="pi pi-check-circle"
                                @click="confirmMoveToLabeling(item)"
                                :disabled="isProcessing"
                            />
                        </div>

                        <div class="flex-shrink-0">
                            <Button
                                label="Move to Stockroom"
                                size="small"
                                icon="pi pi-box"
                                @click="confirmMoveToStockroom(item)"
                                :disabled="isProcessing"
                                severity="warn"
                            />
                        </div>

                        <div class="flex-shrink-0">
                            <Button
                                label="Open Validation"
                                size="small"
                                icon="pi pi-verified"
                                @click="openValidationModal(item)"
                                :disabled="isProcessing"
                                severity="help"
                            />
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

                    <div
                        v-if="expandedRows[index]"
                        class="mobile-expanded-content d-flex flex-column gap-3"
                    >
                        <div>
                            <p class="fw-semibold" style="font-size: 14px">
                                External Title provided by Supplier
                            </p>
                            <p style="font-size: 14px">
                                {{ item.ProductTitle }}
                            </p>
                        </div>
                        <div>
                            <p class="fw-semibold" style="font-size: 14px">
                                Product Name
                            </p>
                            <p style="font-size: 14px">{{ item.astitle }}</p>
                        </div>
                        <div>
                            <p class="fw-semibold" style="font-size: 14px">
                                Store Name
                            </p>
                            <p style="font-size: 14px">{{ item.storename }}</p>
                        </div>
                    </div>
                </div>
            </div>
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

        <!-- Image Modal with Tabs -->
        <ViewImageGalleryModal
            :showImageModal="showImageModal"
            :closeImageModal="closeImageModal"
            :ProductTitle="ProductTitle"
            :regularImages="regularImages"
            :capturedImages="capturedImages"
            :handleImageError="handleImageError"
        />

        <Dialog
            v-model:visible="showValidationModal"
            modal
            header="Product Details"
            :style="{ width: '90vw' }"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <div
                class="d-flex align-items-center justify-content-between gap-4 flex-wrap"
            >
                <div
                    class="d-flex align-items-center gap-2"
                    style="width: 900px"
                >
                    <h6>Serial Number Verification</h6>
                    <InputText
                        class="form-control"
                        size="small"
                        type="text"
                        id="textserial"
                        placeholder="Scan or enter serial number..."
                    />
                    <Button
                        size="small"
                        severity="info"
                        type="submit"
                        label="Verify Serial"
                        style="height: 34px; width: 150px"
                    />
                </div>
                <div>
                    <Button
                        style="margin-right: 10px"
                        size="small"
                        icon="pi pi-thumbs-up"
                        label="Mark as Valid"
                        @click="confirmMarkAsValid"
                    />
                    <Button
                        severity="danger"
                        icon="pi pi-thumbs-down"
                        size="small"
                        label="Mark as Invalid"
                        @click="confirmMarkAsInvalid"
                    />
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-2">
                    <div class="form-col-left">
                        <div>
                            <div
                                class="image-section"
                                v-show="capturedImageList.length"
                            >
                                <div class="main-image">
                                    <img
                                        :src="activeCapturedImageUrl"
                                        alt="Main Product Image"
                                        loading="lazy"
                                        @error="onImageErrorMain"
                                    />
                                </div>

                                <div class="thumbnail-carousel">
                                    <div
                                        v-for="(
                                            img, index
                                        ) in capturedImageList"
                                        :key="index"
                                        :class="[
                                            'thumbnail',
                                            {
                                                active:
                                                    index ===
                                                    activeCapturedIndex,
                                            },
                                        ]"
                                        @click="activeCapturedIndex = index"
                                        @mouseenter="
                                            activeCapturedIndex = index
                                        "
                                    >
                                        <img
                                            :src="img"
                                            alt="Thumbnail"
                                            loading="lazy"
                                            @error="onThumbnailError($event)"
                                        />
                                    </div>
                                </div>
                            </div>

                            <p
                                class="image-label text-center"
                                v-show="capturedImageList.length"
                            >
                                <label>
                                    <p>Images from Product</p>
                                </label>
                            </p>
                        </div>

                        <Divider />

                        <div>
                            <div
                                class="image-section"
                                v-if="ASIN && asinImageList.length"
                            >
                                <div class="main-image">
                                    <img
                                        :src="activeAsinImageUrl"
                                        alt="Main ASIN Image"
                                        loading="lazy"
                                        @error="handleImageError"
                                    />
                                </div>

                                <div
                                    class="thumbnail-carousel"
                                    v-if="asinImageList.length > 1"
                                >
                                    <div
                                        v-for="(img, index) in asinImageList"
                                        :key="'asin-' + index"
                                        :class="[
                                            'thumbnail',
                                            {
                                                active:
                                                    index === activeAsinIndex,
                                            },
                                        ]"
                                        @click="activeAsinIndex = index"
                                        @mouseenter="activeAsinIndex = index"
                                    >
                                        <img
                                            :src="img"
                                            alt="ASIN Thumbnail"
                                            loading="lazy"
                                            @error="handleImageError"
                                        />
                                    </div>
                                </div>

                                <p class="asin-label text-center">
                                    <label>
                                        <p>Image from <strong>ASIN</strong></p>
                                        <p>{{ ASIN }}</p>
                                        <p class="fw-semibold">
                                            {{ getDisplayTitle(item) }}
                                        </p>
                                    </label>
                                </p>
                            </div>

                            <div class="image-section" v-else>
                                <div class="main-image">
                                    <img
                                        :src="defaultImage"
                                        alt="No ASIN Image Available"
                                    />
                                </div>
                                <p class="asin-label text-center" v-if="ASIN">
                                    <label>
                                        <p>
                                            No images available for
                                            <strong>ASIN</strong>
                                        </p>
                                        <p>{{ ASIN }}</p>
                                    </label>
                                </p>
                                <p class="asin-label text-center" v-else>
                                    <label>
                                        <p>No ASIN available</p>
                                    </label>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-10" style="font-size: 14px">
                    <div>
                        <h3>{{ item.ProductTitle }}</h3>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <!----Basic Details Section -->
                            <h5 class="text-primary">Basic Details</h5>
                            <Divider />

                            <div class="d-flex flex-column gap-3">
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold"
                                        >Serial Number</span
                                    >
                                    <span class="text-secondary">{{
                                        item.serialnumber
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold"
                                        >Date Delivered</span
                                    >
                                    <span>{{ item.shipdate }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">Order Date</span>
                                    <span>{{ item.orderdate }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">Seller</span>
                                    <span>{{ item.seller }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">Store</span>
                                    <span>{{ item.storename }}</span>
                                </div>
                            </div>

                            <!------Shipping and Locaton Section -->
                            <h5 class="text-primary mt-4">
                                Shipping and Location
                            </h5>
                            <Divider />
                            <div class="d-flex flex-column gap-3">
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold"
                                        >Order Number</span
                                    >
                                    <span class="text-secondary">{{
                                        item.rtid
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">Tracking</span>
                                    <span class="text-secondary">{{
                                        item.trackingnumber
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold"
                                        >Basket Number</span
                                    >
                                    <span class="text-secondary">{{
                                        item.basketnumber
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">Item Number</span>
                                    <span class="text-secondary">{{
                                        item.itemnumber
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">Location</span>
                                    <span class="text-secondary">{{
                                        item.ProductModuleLoc
                                    }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!----Product Details Section -->
                            <h5 class="text-primary">Product Details</h5>
                            <Divider />
                            <div class="d-flex flex-column gap-3">
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">ASIN</span>
                                    <span class="text-secondary">{{
                                        item.ASIN
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">FNSKU</span>
                                    <span class="text-secondary">{{
                                        item.FNSKU
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">MSKU</span>
                                    <span class="text-secondary">{{
                                        item.MSKU
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">RPN</span>
                                    <span class="text-secondary">{{
                                        item.RPN
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">PRD</span>
                                    <span class="text-secondary">{{
                                        item.PRD
                                    }}</span>
                                </div>
                            </div>
                            <!---Status Information Section--->
                            <h5 class="text-primary mt-4">
                                Status Information
                            </h5>
                            <Divider />
                            <div class="d-flex flex-column gap-3">
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">Grading</span>
                                    <span class="text-secondary">{{
                                        item.grading
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">Priority</span>
                                    <span class="text-secondary">{{
                                        item.priorityrank
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <span class="fw-semibold">Validation</span>
                                    <div
                                        class="badge"
                                        :class="
                                            item.validation_status + '-badge'
                                        "
                                    >
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
                        <p class="text-secondary">
                            {{ item.notes }} Lorem ipsum dolor sit amet
                            consectetur adipisicing elit. Debitis nisi incidunt
                            similique hic sed illum magni error cupiditate sint
                            natus!
                        </p>
                    </div>
                </div>
            </div>
        </Dialog>

        <Dialog
            v-model:visible="showConfirmationModal"
            modal
            :header="confirmationTitle"
            :style="{ width: '90vw' }"
        >
            <p>{{ confirmationMessage }}</p>
            <template #footer>
                <div class="d-flex gap-2">
                    <Button
                        size="small"
                        severity="danger"
                        @click="cancelConfirmation"
                        >Cancel</Button
                    >
                    <Button
                        size="small"
                        :class="{
                            'btn-validation':
                                confirmationActionType === 'validation',
                            'btn-stockroom':
                                confirmationActionType === 'stockroom',
                        }"
                        @click="confirmAction"
                    >
                        Yes, Proceed</Button
                    >
                </div>
            </template>
        </Dialog>

        <!-- Validation Confirmation Modal -->
        <div
            class="validation-confirmation-modal"
            v-if="showConfirmationModal && confirmationActionType"
        >
            <div
                class="validation-confirmation-overlay"
                @click="cancelConfirmation"
            ></div>
            <div class="validation-confirmation-content">
                <div
                    class="validation-confirmation-header"
                    :class="{
                        'header-valid': confirmationActionType === 'valid',
                        'header-invalid': confirmationActionType === 'invalid',
                        'header-default': !['valid', 'invalid'].includes(
                            confirmationActionType
                        ),
                    }"
                >
                    <div class="header-icon-container">
                        <i
                            class="header-icon"
                            :class="{
                                'bi bi-check-circle-fill':
                                    confirmationActionType === 'valid',
                                'bi bi-x-circle-fill':
                                    confirmationActionType === 'invalid',
                                'bi bi-question-circle-fill': ![
                                    'valid',
                                    'invalid',
                                ].includes(confirmationActionType),
                            }"
                        ></i>
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
                    <button
                        class="btn-yes"
                        @click="
                            confirmationActionType === 'valid'
                                ? markAsValid()
                                : markAsInvalid()
                        "
                        :class="{
                            'btn-valid-confirm':
                                confirmationActionType === 'valid',
                            'btn-invalid-confirm':
                                confirmationActionType === 'invalid',
                            'btn-default-confirm': ![
                                'valid',
                                'invalid',
                            ].includes(confirmationActionType),
                        }"
                    >
                        <i class="bi bi-check-lg"></i> Yes
                    </button>
                </div>
            </div>
        </div>
        <!-- End of Validation Confirmation Modal -->
        <ScrollTop />
    </div>
</template>

<script>
import Validation from "./validation.js";
import {
    Badge,
    Button,
    Divider,
    Dialog,
    Card,
    InputText,
    Select,
    ScrollTop,
    Tab,
    TabList,
    Tabs,
    TabPanels,
    TabPanel,
    Galleria,
} from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
// export default Validation;

const TABLE_COLUMNS = [
    {
        field: "gallery",
        header: "Gallery",
        slot: "gallery",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        field: "AStitle",
        header: "Product Name",
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "productname",
        style: { maxWidth: "20rem" },
    },
    {
        header: "Status",
        field: "validation_status",
        headerStyle: "font-size: 16px;",
        slot: "validationStatus",
        style: { width: "6rem", minWidth: "6rem" },
    },
    {
        header: "ASIN",
        field: "ASIN",
        headerStyle: "font-size: 16px;",
        slot: "ASIN",
    },
    {
        header: "Order Number",
        field: "rtid",
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
    },
];

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
        Select,
        ScrollTop,
        TitlePage,
        ViewImageGalleryModal,
        AnimateDiv,
        Tab,
        TabList,
        Tabs,
        TabPanel,
        TabPanels,
        Galleria,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            rowsPerPage: ROWS_PER_PAGE,
        };
    },
    computed: {
        uniqueValidationStatusesList() {
            let validationStatus = this.uniqueValidationStatuses.map(
                (stat) => ({
                    value: stat,
                    label: stat.charAt(0).toUpperCase() + stat.slice(1), //capitalize first letter
                })
            );

            return [{ value: "", label: "All Status" }, ...validationStatus];
        },
    },
};
</script>

<style>
.search-container {
    margin: 20px 0;
}

.select-form {
    width: 200px;
}

@media (max-width: 768px) {
    .select-form {
        width: 100%;
    }
}

.modal-wrapper-image {
    padding: 0;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.tabs-container {
    padding: 0 1rem;
}

/* Main viewing area */
.main-image-area {
    flex: 1;
    min-height: 60vh;
    max-height: 70vh;
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    /* REQUIRED FOR ABSOLUTE BUTTONS */
    padding: 1px;
}

/* Centered main image */
.main-image {
    max-width: 100%;
    max-height: 75vh;
    object-fit: cover;
}

/* Nav buttons */
.nav-btn {
    position: absolute !important;
    /* FORCE POSITIONING */
    top: 50%;
    transform: translateY(-50%);
    z-index: 9999 !important;
    background: rgba(0, 0, 0, 0.45) !important;
    color: white !important;
    width: 40px !important;
    height: 40px !important;
    border-radius: 50% !important;
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    backdrop-filter: blur(2px);
}

/* left and right positioning */
.prev-btn {
    left: 10px !important;
}

.next-btn {
    right: 10px !important;
}

/* Counter */
.image-counter {
    text-align: center;
    font-weight: 600;
    margin: 8px 0;
}

/* Thumbnails */
.thumbs-wrapper {
    display: flex;
    gap: 10px;
    padding: 10px 1rem;
    overflow-x: auto;
}

.thumb-item {
    width: 60px;
    height: 60px;
    border-radius: 6px;
    overflow: hidden;
    cursor: pointer;
    border: 2px solid transparent;
}

.thumb-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.thumb-item.active {
    border-color: #3b82f6;
}
</style>
