<template>
    <div class="vue-container labeling-module">
        <!-- <div class="top-header">
            <span>Top Header</span>
        </div> -->

        <!-- <h2 class="module-title">Labeling Module</h2> -->
        <TitlePage title="Label Generation"
            subtitle="Prepare and manage the details required to generate inventory, shipment, or fulfillment labels for products." />

        <!-- Desktop Table Container -->
        <div class="px-4">
            <XDataTable :value="sortedInventory" :loading=loading :columns="columns" :paginator="false"
                tableClass="desktop-view">
                <template #gallery="{ data }">
                    <div class="d-flex justify-content-center align-items-center">
                        <TableGallery :data="data" :openImageModal="openImageModal" :handleImageError="handleImageError"
                            :countAdditionalImages="countAllImages" size="small" />
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
                <template #quantity="{ data }">
                    <div>
                        <p>{{ data.quantity }} unit</p>
                    </div>
                </template>
                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start">
                        <Button label="Copy Details" icon="pi pi-clone" size=small severity="contrast" variant="text"
                            class="text-success" @click="openCopyDetailsModal(data)" />
                        <Button label="Edit" icon="pi pi-pencil" size=small severity="contrast" variant="text"
                            class="text-primary" @click="openEditModal(data)" />
                        <Button type="button" severity="contrast" variant="text" icon="pi pi-list"
                            @click="toggle($event, data)" aria-haspopup="true" aria-controls="overlay_menu" size="small"
                            label="More Actions" />
                        <Menu ref="menu" id="overlay_menu" :model="menuActions" :popup="true" />
                    </div>
                </template>
            </XDataTable>
        </div>

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
                        <!-- <div class="mobile-product-image clickable">
                            <img :src="'/images/thumbnails/' + item.img1" :alt="item.ProductTitle || 'Product'"
                                class="product-thumbnail clickable-image" @error="handleImageError($event)"
                                @click="openImageModal(item)" />
                            <div class="image-count-badge" v-if="countAllImages(item) > 0">
                                +{{ countAllImages(item) }}
                            </div>
                        </div> -->
                        <TableGallery :data="item" :openImageModal="openImageModal" :handleImageError="handleImageError"
                            :countAdditionalImages="countAllImages" />
                        <div class="mobile-product-info">
                            <h6 class="mobile-product-name clickable">
                                <span style="font-size: 1rem;">RT# : {{ item.rtcounter }}</span>
                                <span>{{ item.ProductTitle }}</span>
                            </h6>
                        </div>
                    </div>

                    <Divider />

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Date Delivered:</span>
                            <span class="mobile-detal-value">
                                {{ item.datedelivered }}</span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detal-value">
                                {{ item.ASIN }}</span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">FNSKU:</span>
                            <span class="mobile-detal-value">
                                {{ item.FNSKUviewer }}</span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Quantity:</span>
                            <span class="mobile-detal-value">
                                {{ item.quantity }} unit/s</span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Serial Number:</span>
                            <span class="mobile-detal-value">
                                {{ item.serialnumber }}</span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Tracking Number:</span>
                            <span class="mobile-detal-value">
                                {{ item.trackingnumber }}</span>
                        </div>
                    </div>

                    <Divider />

                    <div class="d-flex flex-nowrap overflow-auto gap-2 pb-3">
                        <div class="flex-shrink-0"> <Button @click="openCopyDetailsModal(item)" icon="pi pi-clone"
                                label="Copy Details" size="small" severity="info" /></div>
                        <div class="flex-shrink-0">
                            <Button @click="openEditModal(item)" icon="pi pi-pencil" label="Edit" size="small" />
                        </div>
                        <div class="flex-shrink-0">
                            <Button @click="confirmSplitItem(item)" :disabled="isProcessing || !canSplit(item)"
                                icon="pi pi-arrow-up-right-and-arrow-down-left-from-center" label="Split" size="small"
                                severity="contrast" />
                        </div>
                        <div class="flex-shrink-0">
                            <Button @click="showFnskuModal(item)" icon="pi pi-file-check" label="Set FNSKU" size="small"
                                severity="help" />
                        </div>


                        <div class="flex-shrink-0"><Button @click="confirmMoveToValidation(item)"
                                icon="pi pi-check-circle" label="Move to Validation" :disabled="isProcessing"
                                size="small" /></div>
                        <div class="flex-shrink-0"> <Button @click="confirmMoveToStockroom(item)"
                                :disabled="isProcessing" icon="pi pi-box" label="Move to Stockroom" severity="warn"
                                size="small" /></div>



                    </div>
                </div>
            </div>
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

        <!-- Image Modal with Tabs -->
        <div v-if="showImageModal" class="modal image-modal">
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
                    <!-- Tabs for switching between regular and captured images -->
                    <div class="image-tabs">
                        <button class="tab-button" :class="{ active: activeTab === 'regular' }"
                            @click="switchTab('regular')" :disabled="regularImages.length === 0">
                            <span>Product Images</span>
                            <span class="badge img-badge">{{
                                regularImages.length
                            }}</span>
                        </button>
                        <button class="tab-button" :class="{ active: activeTab === 'captured' }"
                            @click="switchTab('captured')" :disabled="capturedImages.length === 0">
                            <span>Captured Images</span>
                            <span class="badge img-badge">{{
                                capturedImages.length
                            }}</span>
                        </button>
                    </div>

                    <!-- Display message if no images in current category -->
                    <div v-if="currentImageSet.length === 0" class="no-images-message">
                        No images available in this category
                    </div>

                    <!-- Main image display (only shown if we have images) -->
                    <div v-if="currentImageSet.length > 0" class="main-image-container">
                        <button class="nav-button prev" @click="prevImage" v-if="currentImageSet.length > 1">
                            <i class="bi bi-arrow-left-short"></i>
                        </button>
                        <img :src="currentImageSet[currentImageIndex]" alt="Product Image" class="modal-main-image"
                            @error="handleImageError" />
                        <button class="nav-button next" @click="nextImage" v-if="currentImageSet.length > 1">
                            <i class="bi bi-arrow-right-short"></i>
                        </button>
                    </div>

                    <div class="image-counter" v-if="currentImageSet.length > 0">
                        {{ currentImageIndex + 1 }} /
                        {{ currentImageSet.length }}
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
        </div>

        <Dialog v-model:visible="showEditModal" header="Edit Product" modal :style="{ width: '90%' }">
            <div class="edit-order-container">
                <form method="POST" class="editOrderForm">
                    <div class="form-grid-wrapper">
                        <div class="form-col-left">
                            <div class="image-section" v-if="imageList.length">
                                <!-- Main Image -->
                                <div class="main-image">
                                    <img :src="activeImageUrl" alt="Main Product Image" loading="lazy"
                                        @error="onImageErrorMain" />
                                </div>

                                <!-- Thumbnails -->
                                <div class="thumbnail-carousel">
                                    <div v-for="(
img, index
                                                ) in imageList" :key="index" :class="[
                                                    'thumbnail',
                                                    {
                                                        active:
                                                            index ===
                                                            activeIndex,
                                                    },
                                                ]" @click="activeIndex = index" @mouseenter="
                                                    activeIndex = index
                                                    ">
                                        <img :src="basePath + img" alt="Thumbnail" loading="lazy" @error="
                                            onThumbnailError($event)
                                            " />
                                    </div>
                                </div>
                            </div>

                            <!-- <div class="form-section general-info-section">
                                <div class="general-info-section">
                                    <h3 class="form-section-heading">
                                        General Info
                                    </h3>

                                    <fieldset>
                                        <label><span>RT:</span></label>
                                        <input type="text" class="form-control" :value="item.rtcounter"
                                            placeholder="RT Counter" />
                                    </fieldset>
                                    <fieldset>
                                        <label>
                                            <span>ASIN:</span>
                                            <span>*</span>
                                        </label>
                                        <input type="text" class="form-control" :value="item.ASIN" readonly disabled />
                                    </fieldset>
                                    <fieldset>
                                        <label><span>External Title:</span></label>
                                        <textarea ref="productTextarea" class="form-control no-resize"
                                            v-model="item.ProductTitle" placeholder="Product Title" rows="1"
                                            @input="autoResize"></textarea>
                                    </fieldset>
                                    <fieldset>
                                        <label><span>Internal Title:</span></label>
                                        <textarea ref="productTextarea" class="form-control no-resize"
                                            v-model="item.ProductTitle" placeholder="Product Title" rows="1"
                                            @input="autoResize" readonly disabled></textarea>
                                    </fieldset>
                                </div>
                            </div> -->
                        </div>

                        <!-- CENTER: ALL OTHER INFO EXCEPT PRICING -->
                        <div class="form-col-center">
                            <Card>
                                <template #title>
                                    <div>
                                        <h6 class="text-primary">General Information</h6>
                                        <Divider />
                                    </div>
                                </template>
                                <template #content>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>External Title</label>
                                                <Textarea ref="productTextarea" class="form-control no-resize"
                                                    v-model="item.ProductTitle" placeholder="Product Title" rows="1"
                                                    @input="autoResize" fluid size="small" />
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>Internal Title</label>
                                                <Textarea ref="productTextarea" class="form-control no-resize"
                                                    v-model="item.ProductTitle" placeholder="Product Title" rows="1"
                                                    @input="autoResize" fluid size="small" disabled />
                                            </fieldset>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>RT:</label>
                                                <InputText type="text" size="small" fluid :value="item.rtcounter"
                                                    placeholder="RT Counter" />
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>
                                                    <span>ASIN:</span>
                                                    <span>*</span>
                                                </label>
                                                <InputText type="text" :value="item.ASIN" size="small" fluid readonly
                                                    disabled />
                                            </fieldset>
                                        </div>
                                    </div>
                                </template>
                            </Card>
                            <div class="form-section other-section bg-white border-0">
                                <!-- SECTION: Dates -->
                                <div class="dates-section">
                                    <Card>
                                        <template #title>
                                            <div>
                                                <h6 class="text-primary">Dates</h6>
                                                <Divider />
                                            </div>
                                        </template>
                                        <template #content>
                                            <fieldset>
                                                <label>Order Date:</label>
                                                <InputText type="date" v-model="item.orderdate" size="small" fluid />
                                            </fieldset>
                                            <fieldset>
                                                <label>Payment Date:</label>
                                                <InputText type="date" v-model="item.paymentdate" size="small" fluid />
                                            </fieldset>
                                            <fieldset>
                                                <label>Shipped Date:</label>
                                                <InputText type="date" v-model="item.shipdate" size="small" fluid />
                                            </fieldset>
                                            <fieldset>
                                                <label>Delivered Date:</label>
                                                <InputText type="date" v-model="item.datedelivered" size="small"
                                                    fluid />
                                            </fieldset>
                                        </template>
                                    </Card>
                                </div>

                                <!-- SECTION: Serial & Tracking -->
                                <div class="serial-tracking-section">
                                    <Card>
                                        <template #title>
                                            <div>
                                                <h6 class="text-primary">Serial & Tracking</h6>
                                                <Divider />
                                            </div>
                                        </template>
                                        <template #content>
                                            <template v-if="serialKeys.length">
                                                <fieldset v-for="(
key, index
                                                    ) in serialKeys" :key="key">
                                                    <label>
                                                        <span>Serial Number
                                                            {{
                                                                getLabel(index)
                                                            }}:</span>
                                                        <span v-if="index === 0">*</span>
                                                    </label>
                                                    <InputText type="text" size="small" fluid v-model="item[key]" />
                                                </fieldset>
                                            </template>
                                            <template v-if="trackingKeys.length">
                                                <fieldset v-for="(
key, index
                                                    ) in trackingKeys" :key="key">
                                                    <label><span>Tracking Number
                                                            {{
                                                                index + 1
                                                            }}:</span></label>
                                                    <InputText type="text" size="small" fluid v-model="item[key]" />
                                                </fieldset>
                                            </template>
                                        </template>
                                    </Card>
                                </div>

                                <!-- SECTION: Product Info -->
                                <div class="product-info-section">
                                    <Card>
                                        <template #title>
                                            <div>
                                                <h6 class="text-primary">
                                                    Product Info
                                                </h6>
                                                <Divider />
                                            </div>
                                        </template>
                                        <template #content>
                                            <div>
                                                <fieldset>
                                                    <label>Order Number</label>
                                                    <InputText type="text" :value="item.rtid" placeholder="Order Number"
                                                        size="small" fluid />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Item Number</label>
                                                    <InputText type="text" size="small" fluid
                                                        v-model="item.itemnumber" />
                                                </fieldset>
                                                <fieldset>
                                                    <label>
                                                        <span>Basket Number</span>
                                                        <span>*</span>
                                                    </label>
                                                    <InputText type="text" size="small" fluid
                                                        v-model="item.basketnumber" />
                                                </fieldset>
                                                <fieldset>
                                                    <label>
                                                        <span>RPN</span>
                                                        <span>*</span>
                                                    </label>
                                                    <InputText size="small" fluid type="text" v-model="item.RPN" />
                                                </fieldset>
                                                <fieldset>
                                                    <label>
                                                        <span>PRD</span>
                                                        <span>*</span>
                                                    </label>
                                                    <InputText size="small" fluid type="text" v-model="item.PRD" />
                                                </fieldset>
                                                <fieldset>
                                                    <label>
                                                        <span>PCN</span>
                                                        <span>*</span>
                                                    </label>
                                                    <InputText size="small" fluid type="text" v-model="item.PCN" />
                                                </fieldset>
                                                <fieldset>
                                                    <label><span>Priority Rank</span></label>
                                                    <Select v-model="item.priorityrank" :options="priorityRanksOptions"
                                                        optionLabel="label" optionValue="value" size="small" fluid
                                                        placeholder="Select Priority Bank" />
                                                </fieldset>
                                                <fieldset>
                                                    <label><span>Return Status</span></label>
                                                    <InputText size="small" fluid type="text"
                                                        v-model="item.returnstatus" readonly disabled />
                                                </fieldset>
                                            </div>
                                        </template>
                                    </Card>

                                    <!-- <fieldset>
                                        <label><span>Order Number</span></label>
                                        <input type="text" class="form-control" :value="item.rtid"
                                            placeholder="Order Number" />
                                    </fieldset>
                                    <fieldset>
                                        <label><span>Item Number</span></label>
                                        <input type="text" class="form-control" v-model="item.itemnumber" />
                                    </fieldset>
                                    <fieldset>
                                        <label>
                                            <span>Basket Number</span>
                                            <span>*</span>
                                        </label>
                                        <input type="text" class="form-control" v-model="item.basketnumber" />
                                    </fieldset>
                                    <fieldset>
                                        <label>
                                            <span>RPN</span>
                                            <span>*</span>
                                        </label>
                                        <input type="text" class="form-control" v-model="item.RPN" />
                                    </fieldset>
                                    <fieldset>
                                        <label>
                                            <span>PRD</span>
                                            <span>*</span>
                                        </label>
                                        <input type="text" class="form-control" v-model="item.PRD" />
                                    </fieldset>
                                    <fieldset>
                                        <label>
                                            <span>PCN</span>
                                            <span>*</span>
                                        </label>
                                        <input type="text" class="form-control" v-model="item.PCN" />
                                    </fieldset>
                                    <fieldset>
                                        <label><span>Priority Rank</span></label>
                                        <select class="form-control" v-model="item.priorityrank">
                                            <option disabled value="">
                                                Select Priority Rank
                                            </option>
                                            <option v-for="type in priorityRanks" :key="type" :value="type">
                                                {{ type }}
                                            </option>
                                        </select>
                                    </fieldset>
                                    <fieldset>
                                        <label><span>Return Status</span></label>
                                        <input type="text" class="form-control" v-model="item.returnstatus" readonly
                                            disabled />
                                    </fieldset> -->
                                </div>
                            </div>
                            <Card>
                                <template #content>
                                    <div>
                                        <fieldset>
                                            <label><span>Description</span></label>
                                            <Textarea ref="descriptionarea" class="no-resize" fluid
                                                v-model="item.description" placeholder="Description" rows="2"
                                                @input="autoResize" size="small" />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Supplier Notes</span></label>
                                            <Textarea ref="supplierNotesarea" class="no-resize" fluid
                                                v-model="item.supplierNotes" placeholder="Supplier Notes" rows="2"
                                                @input="autoResize" size="small" />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Employee Notes</span></label>
                                            <Textarea ref="employeeNotesarea" class="no-resize" fluid
                                                v-model="item.employeeNotes" placeholder="Employee Notes" rows="2"
                                                @input="autoResize" size="small" />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Sticker Notes</span></label>
                                            <Textarea ref="stickerNotesarea" class="no-resize" fluid
                                                v-model="item.stickerNotes" placeholder="Sticker Notes" rows="2"
                                                @input="autoResize" size="small" />
                                        </fieldset>
                                    </div>
                                </template>
                            </Card>
                        </div>

                        <!-- RIGHT: PRICING -->
                        <div class="form-col-right">
                            <Card>
                                <template #title>
                                    <div>
                                        <h3 class="text-primary">Pricing</h3>
                                        <Divider />
                                    </div>
                                </template>
                                <template #content>
                                    <div>
                                        <fieldset>
                                            <label>Quantity</label>
                                            <InputText type="number" class=" text-end" size="small" fluid
                                                v-model="item.quantity" />
                                        </fieldset>
                                        <fieldset>
                                            <label>Total Price</label>
                                            <InputText type="number" class="text-end" size="small" fluid
                                                v-model="item.price" />
                                        </fieldset>
                                        <fieldset>
                                            <label>Discount</label>
                                            <InputText type="number" class=" text-end" size="small" fluid
                                                v-model="item.Discount" />
                                        </fieldset>

                                        <fieldset>
                                            <label>Tax</label>
                                            <InputText type="number" class=" text-end" size="small" fluid
                                                v-model="item.tax" />
                                        </fieldset>
                                        <fieldset>
                                            <label>Shipping</label>
                                            <InputText type="number" class="text-end" size="small" fluid
                                                v-model="item.priceshipping" />
                                        </fieldset>

                                        <fieldset>
                                            <label>Refund</label>
                                            <InputText type="number" class="text-end" size="small" fluid
                                                v-model="item.refund" />
                                        </fieldset>
                                        <Divider />
                                        <fieldset>
                                            <label>Unit price</label>
                                            <InputText type="text" class="text-end bg-light" size="small" fluid
                                                :value="formattedUnitprice" readonly />
                                        </fieldset>

                                        <fieldset>
                                            <label>Grand Totalprice</label>
                                            <InputText type="text" class="text-end bg-light fw-bold text-success"
                                                size="small" fluid :value="grandTotal" readonly />
                                        </fieldset>
                                    </div>
                                </template>
                            </Card>

                        </div>
                    </div>
                </form>
            </div>
            <template #footer>
                <div class="pt-2 pr-2">
                    <Button label="Save" severity="info" size="small" @click="saveEditModal" icon="pi pi-save" />
                </div>
            </template>
        </Dialog>

        <Dialog v-model:visible="isFnskuModalVisible" header="Select FNSKU" modal :style="{ width: '95%' }">

            <div class="row">
                <div class="col-md-3">
                    <div class="fnsku-image-column">
                        <div v-if="productImages.length" class="image-display">
                            <div class="hover-image-container">
                                <img :src="selectedImage || mainImage" alt="Main Image" class="preview-image" />
                                <div class="hover-preview">
                                    <img :src="selectedImage || mainImage" alt="Zoomed Preview" />
                                </div>
                            </div>

                            <div class="thumbnail-list overflow-auto">
                                <img v-for="(img, index) in productImages" :key="index" :src="img" alt="Thumbnail"
                                    class="thumbnail" :class="{
                                        active: selectedImage === img,
                                    }" @click="selectedImage = img" />
                            </div>
                        </div>

                        <div v-else class="no-image">
                            <p>No image available</p>
                        </div>
                    </div>

                    <div class="my-4">

                        <h5>{{ currentItem.ProductTitle }}</h5>

                        <div class="mt-4">
                            <span class="fw-semibold">RT#: </span>
                            <span>{{ currentItem?.rtcounter }}</span>
                        </div>

                        <div class="mt-2">
                            <span class="fw-semibold">Current FNSKU: </span>
                            <span>{{ currentItem?.FNSKUviewer || "None" }}</span>
                        </div>


                    </div>
                </div>
                <div class="col-md-9">
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="fs-5 fw-semibold">Search and Filters</p>
                        <Button :icon="showFilters ? 'pi pi-filter-slash' : 'pi pi-filter'"
                            :label="showFilters ? 'Hide Filters' : 'Show Filters'" @click="showFilters = !showFilters"
                            size=small severity="contrast" variant="text" class="text-primary" />
                    </div>
                    <div v-if="showFilters" class="mb-4">
                        <fieldset>
                            <label>Search Title or Asin</label>
                            <InputText fluid size="small" placeholder="Enter Title or Asin" @input="filterFnskuList"
                                v-model="fnskuSearch" />
                        </fieldset>
                        <div class="row">
                            <div class="col-md-4">
                                <fieldset>
                                    <label>Filter by Store</label>
                                    <Select v-model="selectedStore" :options="storeListOptions" optionLabel="label"
                                        optionValue="value" fluid size="small" placeholder="Select Store"
                                        :disabled="isSearching" @change="filterFnskuList" />

                                </fieldset>
                            </div>
                            <div class="col-md-4">
                                <fieldset>
                                    <label>Filter by FNSKU</label>
                                    <InputText placeholder="Exact of partial FNSKU" v-model="fnskuExact"
                                        @input="filterFnskuList" size="small" :disabled="isSearching" fluid />
                                </fieldset>
                            </div>
                            <div class="col-md-4">
                                <fieldset>
                                    <label>Filter by Condition</label>
                                    <Select v-model="selectedGrading" @change="filterFnskuList"
                                        :options="conditionListOptions" optionLabel="label" optionValue="value" fluid
                                        size="small" placeholder="Select Condition" :disabled="isSearching" />
                                </fieldset>
                            </div>
                        </div>
                    </div>

                    <XDataTable :value="validFnskuList" :columns="fnskuColumn" :pagination="false"
                        :loading="isSearching" scrollable scrollHeight="400px" tableClass="mt-4 desktop-view"
                        :actionsHeaderClass="my - actions - header">
                        <template #image="{ data }">
                            <img :src="getImageSrc(
                                data.ASIN,
                                0
                            )
                                " :alt="`Main image for ${data.ASIN}`" class="asin-thumbnail"
                                @error="setDefaultImage" />

                        </template>

                        <template #ProductTitle="{ data }">
                            <div class="d-flex align-items-start gap-4">
                                <div
                                    style="word-break: break-word; white-space: normal; overflow-wrap: break-word; flex: 1;">
                                    <p class="fw-semibold">
                                        {{ data.astitle }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <template #units="{ data }">
                            <div>
                                <p>{{ data.Units }} items in stock</p>
                                <span v-if="
                                    data.Units < 11
                                " class="badge bg-warning ms-1">
                                    Used
                                    {{
                                        11 - data.Units
                                    }}
                                    times
                                </span>
                                <span v-else class="badge bg-success ms-1">
                                    First use
                                </span>

                            </div>
                        </template>

                        <template #FNSKU="{ data }">
                            <div>
                                {{ data.FNSKU }}
                                <div class="small text-muted">
                                    <i class="fas fa-arrow-right"></i>
                                    Will assign:
                                    <strong>{{
                                        getNextFnskuToUse(
                                            data
                                        )
                                    }}</strong>
                                </div>
                            </div>

                        </template>
                        <template #grading="{ data }">
                            <div>
                                {{
                                    getGradingLabel(
                                        data.grading
                                    )
                                }}
                            </div>
                        </template>

                        <template #actions="{ data }" headerStyle="background-color: black">
                            <div class="d-flex gap-2 align-items-center">
                                <Button size="small" severity="contrast" variant="text" class="text-primary" outlined
                                    :label="data.ASIN ===
                                        currentItem?.ASINviewer
                                        ? 'Recommended' : 'Select'" @click=" selectFnsku(data)" />
                                <Button size="small" variant="text" rounded icon="pi pi-info-circle" @click="
                                    showOtherFNSKUInfos(data)" />
                            </div>
                        </template>
                    </XDataTable>

                    <div class="mobile-view p-0 mt-4">
                        <div class="mobile-cards">
                            <div v-if="isSearching" class="loading-spinner-mobile">
                                <i class="fas fa-spinner fa-spin"></i>
                                Loading...
                            </div>
                            <div v-else-if="sortedInventory.length === 0" class="no-data-mobile">
                                No data found
                            </div>

                            <div class="mobile-card" v-else v-for="(item, index) in validFnskuList" :key="item.id">
                                <div class="mobile-card-header">
                                    <div class="mobile-checkbox">
                                        <input type="checkbox" v-model="item.checked" />
                                    </div>
                                    <img :src="getImageSrc(
                                        item.ASIN,
                                        0
                                    )
                                        " :alt="`Main image for ${item.ASIN}`" class="asin-thumbnail"
                                        :style="{ maxWidth: '5rem' }" @error="setDefaultImage" />
                                    <div class="mobile-product-info">
                                        <div class="mobile-product-name">
                                            <h6>{{ item.astitle || "----" }}</h6>
                                        </div>
                                    </div>
                                </div>
                                <hr />
                                <div class="mobile-card-details">
                                    <div class="mobile-detail-row mb-2">
                                        <span class="mobile-detail-label">ASIN:</span>
                                        <span class="mobile-detal-value">
                                            {{ item.ASIN }}</span>
                                    </div>

                                    <div class="mobile-detail-row mb-2">
                                        <span class="mobile-detail-label">Inventory:</span>
                                        <span class="mobile-detal-value">
                                            <span>{{ item.Units }} items in stock</span>
                                            <span v-if="
                                                item.Units < 11
                                            " class="badge bg-warning ms-1">
                                                Used
                                                {{
                                                    11 - item.Units
                                                }}
                                                times
                                            </span>
                                            <span v-else class="badge bg-success ms-1">
                                                First use
                                            </span></span>
                                    </div>
                                    <div class="mobile-detail-row mb-2">
                                        <span class="mobile-detail-label">FNSKU:</span>
                                        <div class="mobile-detal-value">
                                            <span>{{ item.FNSKU }}</span>
                                            <div class="small text-muted">
                                                <i class="fas fa-arrow-right"></i>
                                                Will assign:
                                                <strong>{{
                                                    getNextFnskuToUse(
                                                        item
                                                    )
                                                }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mobile-detail-row mb-2">
                                        <span class="mobile-detail-label">MSKU:</span>
                                        <span class="mobile-detal-value">
                                            {{ item.MSKU }}</span>
                                    </div>
                                    <div class="mobile-detail-row mb-2">
                                        <span class="mobile-detail-label">Grading:</span>
                                        <span class="mobile-detal-value">
                                            {{
                                                getGradingLabel(
                                                    item.grading
                                                )
                                            }}</span>
                                    </div>
                                    <div class="mobile-detail-row mb-2">
                                        <span class="mobile-detail-label">Store:</span>
                                        <span class="mobile-detal-value">
                                            {{ item.storename }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 p-3 bg-light flex-wrap">
                        <div>
                            <span v-if="isInitialLoad || isSearching">Loading...</span>
                            <span v-else-if="
                                paginationInfo.from && paginationInfo.to
                            ">
                                Showing {{ paginationInfo.from }} to
                                {{ paginationInfo.to }} entries of
                                {{ totalRecords }}
                            </span>
                            <span v-else>No entries found</span>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <select v-model="pageSize" @change="changePageSize" class="form-select form-select-sm"
                                style="width: 80px">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>

                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                        <Button @click="prevPage" :disabled="currentPage === 1" size="small"
                                            severity="info">
                                            Previous
                                        </Button>
                                    </li>

                                    <li class="page-item active">
                                        <span>Page {{ currentPage }}</span>
                                    </li>

                                    <li class="page-item" :class="{ disabled: !hasMorePages }">
                                        <Button @click="nextPage" :disabled="!hasMorePages" size="small"
                                            severity="info">
                                            Next
                                        </Button>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>
        </Dialog>



        <!-- Confirmation Modal -->
        <div v-if="showConfirmationModal" class="modal confirmation-modal">
            <div class="modal-overlay" @click="cancelConfirmation"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h3>{{ confirmationTitle }}</h3>
                    <button class="btn btn-modal-close" @click="cancelConfirmation">
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
        </div>

        <!-- split modal  -->
        <splittingModal :show-modal="showSplitModal" :split-item="currentSplitItem" @close="closeSplitModal"
            @split-success="onSplitSuccess" />

        <!--OPY DETAILS MODAL-->
        <copyDetailsModal :show-modal="showCopyDetailsModal" :item-data="currentCopyItem"
            @close="closeCopyDetailsModal" />

        <!----SHOW OTHER FNSKU INFOS--->
        <Dialog v-model:visible="showOtherFNSKUInfoModal" modal header="Other FNSKU Information"
            :style="{ width: '40rem' }">


            <div v-if="!otherFNSKUInfoState.loading">
                <div v-if="otherFNSKUInfoState.errorMessage" class="text-danger">
                    {{ otherFNSKUInfoState.errorMessage }}
                </div>
                <div v-else class="info-container ">
                    <div class="info-items">
                        <span>FNSKU:</span>
                        <span>{{ otherFNSKUInfoState.info.base_fnsku }}</span>
                    </div>
                    <div class="info-items">
                        <span>Next FNSKU to use:</span>
                        <span>{{ otherFNSKUInfoState.info.next_fnsku_to_use }}</span>
                    </div>
                    <div class="info-items">
                        <span>ASIN:</span>
                        <span>{{ otherFNSKUInfoState.info.asin }}</span>
                    </div>
                    <div class="info-items">
                        <span>Condition:</span>
                        <span>{{ otherFNSKUInfoState.info.grading }}</span>
                    </div>
                    <div class="info-items">
                        <span>Store:</span>
                        <span>{{ otherFNSKUInfoState.info.storename }}</span>
                    </div>
                    <div class="info-items">
                        <span>Times used:</span>
                        <span>{{ otherFNSKUInfoState.info.times_used }}</span>
                    </div>
                    <div class="info-items">
                        <span>Units after used:</span>
                        <span>{{ otherFNSKUInfoState.info.units_after_use }}</span>
                    </div>
                    <div class="info-items">
                        <span>Current units:</span>
                        <span>{{ otherFNSKUInfoState.info.remaining_units }}</span>
                    </div>
                </div>
            </div>
            <div v-else class="d-flex justify-content-center align-items-center" style="height:100px;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <template #footer>
                <Button size="small" severity="danger" @click="showOtherFNSKUInfoModal = false">Close</Button>
            </template>
        </Dialog>
        <ScrollTop />
    </div>
</template>

<script>
import Labeling from "./labeling.js";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import { Button, Menu, SplitButton, Divider, Dialog, InputText, Select, Card, Textarea, ScrollTop } from "primevue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
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
        style: { minWidth: "15rem", maxWidth: "20rem" },
    },
    {
        field: "serialnumber",
        header: "Serial Number",
        sortable: true,
        headerStyle: "font-size: 16px;",
    },
    {
        field: "ASIN",
        header: "ASIN",
        sortable: true,
        headerStyle: "font-size: 16px;",
    },
    {
        field: "FNSKUviewer",
        header: "FNSKU",
        sortable: true,
        headerStyle: "font-size: 16px;",
    },
    {
        field: "trackingnumber",
        header: "Tracking Number",
        sortable: true,
        headerStyle: "font-size: 16px;",
    },
    {
        field: "quantity",
        header: "Quantity",
        sortable: true,
        slot: "quantity",
        headerStyle: "font-size: 16px;",
    },
    {
        field: "datedelivered",
        header: "Date Delivered",
        sortable: true,
        headerStyle: "font-size: 16px;",
    },
]

const FNSKU_COLUMN = [
    {
        field: "image",
        header: "Image",
        slot: "image",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        field: "ASIN",
        header: "ASIN",
        bodyStyle: "font-size: 14px",
        style: { width: "10rem", minWidth: "10rem" },
    },
    {
        field: "astitle",
        header: "Product Name",
        slot: "ProductTitle",
        bodyStyle: "font-size: 14px",
        style: { minWidth: "20rem", maxWidth: "20rem" },
    },
    {
        field: "units",
        header: "Inventory",
        bodyStyle: "font-size: 14px",
        slot: "units",
    },
    {
        field: "FNSKU",
        header: "FNSKU",
        bodyStyle: "font-size: 14px",
        slot: "FNSKU",
    },
    {
        field: "MSKU",
        header: "MSKU",
        bodyStyle: "font-size: 14px",
    },
    {
        field: "grading",
        header: "Grading",
        bodyStyle: "font-size: 14px",
        slot: "grading"
    },
    {
        field: "storename",
        header: "Store",
        bodyStyle: "font-size: 14px",
    }
]


export default {
    mixins: [Labeling],
    components: { XDataTable, Button, SplitButton, TableGallery, Menu, Divider, Dialog, Divider, InputText, Select, Card, Textarea, ScrollTop, TitlePage },
    data() {
        return {
            columns: TABLE_COLUMNS,
            menuActions: [],
            currentActionItem: null,
            fnskuColumn: FNSKU_COLUMN,
            showOtherFNSKUInfoModal: false,
            otherFNSKUInfoState: {
                loading: false,
                info: {},
                errorMessage: ""
            }
        }
    },
    methods: {
        toggle(event, item) {
            this.currentActionItem = item;
            this.menuActions = this.getMoreActionItems(this.currentActionItem);
            if (this.$refs.menu) {
                this.$refs.menu.toggle(event);
            }
        },
        getMoreActionItems(item) {
            return [
                {
                    label: 'Split',
                    icon: 'pi pi-arrow-up-right-and-arrow-down-left-from-center',
                    command: () => this.confirmSplitItem(item),
                    disabled: !this.canSplit(item) || this.isProcessing
                },
                {
                    label: 'Set FNSKU',
                    icon: 'pi pi-file-check',
                    command: () => this.showFnskuModal(item)
                },
                {
                    label: 'Move to Validation',
                    icon: 'pi pi-check-circle',
                    command: () => this.confirmMoveToValidation(item)
                },
                {
                    label: 'Move to Stockroom',
                    icon: 'pi pi-box',
                    command: () => this.confirmMoveToStockroom(item)
                }
            ];
        },
        async showOtherFNSKUInfos(data) {
            this.showOtherFNSKUInfoModal = true;

            // start loading
            this.otherFNSKUInfoState.loading = true;
            this.otherFNSKUInfoState.info = {};
            this.otherFNSKUInfoState.errorMessage = "";

            try {
                const { info, errorMessage } = await this.showFnskuAvailabilityInfo(data);

                this.otherFNSKUInfoState.info = info;
                this.otherFNSKUInfoState.errorMessage = errorMessage;
            } catch (error) {
                console.error("Error fetching FNSKU availability:", error);
                this.otherFNSKUInfoState.errorMessage =
                    "Error fetching FNSKU availability information";
            } finally {
                // ✅ Vue will now properly detect this change
                this.otherFNSKUInfoState.loading = false;
            }
        }


    },
    computed: {
        storeListOptions() {
            const options = this.uniqueStores.map((store) => ({ label: store, value: store }))
            return [{ label: "All Store", value: "" }, ...options]
        },
        conditionListOptions() {
            const options = this.gradingOptions.map((grade) => ({ label: grade.label, value: grade.value }))
            return [{ label: "All Conditions", value: "" }, ...options]
        },
        priorityRanksOptions() {
            return this.priorityRanks.map((type) => ({ label: type, value: type }))
        }
    }
}
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

.my-actions-header {
    background-color: #f0f0f0;
    color: red;
    text-align: center;
}

.info-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.info-items {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgb(181, 181, 181);
    font-size: 14px;
}

.info-items span:nth-child(1) {
    font-weight: bold;
}
</style>
