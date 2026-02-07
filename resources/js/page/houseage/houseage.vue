<template>
    <div class="vue-container houseage-module">
        <TitlePage
            title="Houseage Module"
            subtitle="Manage all products in the internal processing flow, including grading, return status, and next module assignment."
        />

        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="px-4">
            <div class="search-container">
                <fieldset class="d-flex align-items-center gap-3">
                    <label for="moduleFilter">Module:</label>
                    <Select
                        v-model="moduleFilter"
                        :options="uniqueModuleOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select module"
                        size="small"
                        class="select-form"
                    />
                </fieldset>
            </div>

            <XDataTable
                :value="sortedInventory"
                :loading="loading"
                :columns="columns"
                :paginator="false"
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

                        <!-- Use TableGallery for regular product images -->
                        <TableGallery
                            v-else
                            :data="data"
                            :openImageModal="openImageModal"
                            :handleImageError="handleImageError"
                            :countAdditionalImages="countAllImages"
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
                                ID# {{ data.rtcounter }}
                            </p>
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

                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start">
                        <Button
                            severity="contrast"
                            variant="text"
                            size="small"
                            class="text-success"
                            label="Copy Details"
                            icon="pi pi-clone"
                            @click="openCopyDetailsModal(data)"
                        />
                        <Button
                            severity="contrast"
                            variant="text"
                            size="small"
                            class="text-primary"
                            label="Edit"
                            icon="pi pi-pencil"
                            @click="openEditModal(data)"
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

                        <!-- Updated mobile gallery with captured images support -->
                        <div class="mobile-product-image clickable">
                            <!-- Show captured image if available -->
                            <div
                                v-if="
                                    item.capturedImages &&
                                    item.capturedImages.capturedimg1
                                "
                                class="gallery-thumbnail position-relative"
                                @click="openImageModal(item)"
                                style="cursor: pointer"
                            >
                                <img
                                    :src="`/images/product_images/${
                                        item.company || 'Airstaffs'
                                    }/${item.capturedImages.capturedimg1}`"
                                    :alt="getDisplayTitle(item)"
                                    class="product-thumbnail clickable-image"
                                    @error="handleImageError"
                                />
                                <div
                                    class="image-count-badge"
                                    v-if="countCapturedImages(item) > 1"
                                >
                                    +{{ countCapturedImages(item) - 1 }}
                                </div>
                            </div>

                            <!-- Fallback to regular product image -->
                            <div
                                v-else
                                @click="openImageModal(item)"
                                style="cursor: pointer"
                            >
                                <img
                                    :src="'/images/thumbnails/' + item.img1"
                                    :alt="getDisplayTitle(item)"
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
                        </div>

                        <div class="mobile-product-info">
                            <h6 class="mobile-product-name clickable">
                                <span style="font-size: 1rem"
                                    >RT# : {{ item.rtcounter }}</span
                                >
                                <span>{{ getDisplayTitle(item) }}</span>
                            </h6>
                            <div
                                class="badge"
                                :class="item.validation_status + '-badge'"
                            >
                                {{ item.validation_status }}
                            </div>
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detal-value">
                                {{ item.ASIN }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">FNSKU:</span>
                            <span class="mobile-detal-value">
                                {{ item.FNSKU }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Grading:</span>
                            <span class="mobile-detal-value">
                                {{ item.grading }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Quantity:</span>
                            <span class="mobile-detal-value">
                                {{ item.quantity }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Fullfilment Status:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.fulfillment_status }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Module:</span>
                            <span class="mobile-detal-value">
                                {{ item.ProductModuleLoc }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Return Status:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.returnstatus }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Serial Number:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.serialnumber }}</span
                            >
                        </div>
                    </div>

                    <hr />

                    <div
                        class="d-flex align-items-center justify-content-evenly"
                    >
                        <Button
                            severity="success"
                            size="small"
                            label="Copy Details"
                            icon="pi pi-clone"
                            @click="openCopyDetailsModal(item)"
                        />
                        <Button
                            severity="info"
                            size="small"
                            label="Edit"
                            icon="pi pi-pencil"
                            @click="openEditModal(item)"
                        />
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
                    <Select
                        v-model="perPage"
                        @change="changePerPage"
                        :options="rowsPerPage"
                        size="small"
                        optionLabel="label"
                        optionValue="value"
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
            v-model:visible="showEditModal"
            modal
            :style="{ width: '95%' }"
            header="Edit Product"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <div class="edit-order-container">
                <form method="POST" class="editOrderForm">
                    <div class="form-grid-wrapper">
                        <!-- LEFT: IMAGE + GENERAL INFO -->
                        <div class="form-col-left">
                            <fieldset>
                                <label for="">Product Images</label>
                                <div
                                    class="image-section"
                                    v-if="imageList.length"
                                    :key="`section-${imageRenderKey}`"
                                >
                                    <!-- Main Image -->
                                    <div
                                        class="main-image"
                                        @click="
                                            handleOpenProductImageDialog(
                                                'product',
                                                12,
                                            )
                                        "
                                    >
                                        <img
                                            :src="activeImageUrl"
                                            :key="`main-${activeImageUrl}-${imageRenderKey}`"
                                            alt="Main Product Image"
                                            loading="lazy"
                                            @error="onImageErrorMain"
                                        />
                                    </div>

                                    <!-- Thumbnails -->
                                    <div class="thumbnail-carousel">
                                        <div
                                            v-for="(img, index) in imageList"
                                            :key="`thumb-${index}-${img}-${imageRenderKey}`"
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
                                                :src="
                                                    img.startsWith('/images/')
                                                        ? img
                                                        : basePath + img
                                                "
                                                alt="Thumbnail"
                                                loading="lazy"
                                                @error="
                                                    onThumbnailError($event)
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <div class="image-section">
                                <fieldset>
                                    <label>Serial Number</label>
                                    <div
                                        class="main-image"
                                        @click="
                                            handleOpenProductImageDialog(
                                                'serial',
                                                2,
                                            )
                                        "
                                    >
                                        <img
                                            :src="activeSerialImageUrl"
                                            :key="`main-${activeSerialImageUrl}-${imageRenderKey}`"
                                            alt="Serial Image"
                                            loading="lazy"
                                            @error="onImageErrorMain"
                                        />
                                    </div>
                                    <div class="thumbnail-carousel">
                                        <div
                                            v-for="(
                                                img, index
                                            ) in serialImgList"
                                            :key="`thumb-${index}-${img}-${imageRenderKey}`"
                                            :class="[
                                                'thumbnail',
                                                {
                                                    active:
                                                        index ===
                                                        serialActiveIndex,
                                                },
                                            ]"
                                            @click="serialActiveIndex = index"
                                            @mouseenter="
                                                serialActiveIndex = index
                                            "
                                        >
                                            <img
                                                :src="
                                                    img.startsWith('/images/')
                                                        ? img
                                                        : basePath + img
                                                "
                                                alt="Thumbnail"
                                                loading="lazy"
                                                @error="
                                                    onThumbnailError($event)
                                                "
                                            />
                                        </div>
                                    </div>
                                </fieldset>
                            </div>

                            <!----Tracking Image List--->
                            <div class="image-section">
                                <fieldset>
                                    <label>Tracking Number</label>
                                    <div
                                        class="main-image"
                                        @click="
                                            handleOpenProductImageDialog(
                                                'tracking',
                                                2,
                                            )
                                        "
                                    >
                                        <img
                                            :src="activeTrackingImageUrl"
                                            :key="`main-${activeTrackingImageUrl}-${imageRenderKey}`"
                                            alt="Tracking Image"
                                            loading="lazy"
                                            @error="onImageErrorMain"
                                        />
                                    </div>
                                    <div class="thumbnail-carousel">
                                        <div
                                            v-for="(
                                                img, index
                                            ) in trackingImgList"
                                            :key="`thumb-${index}-${img}-${imageRenderKey}`"
                                            :class="[
                                                'thumbnail',
                                                {
                                                    active:
                                                        index ===
                                                        trackingActiveIndex,
                                                },
                                            ]"
                                            @click="trackingActiveIndex = index"
                                            @mouseenter="
                                                trackingActiveIndex = index
                                            "
                                        >
                                            <img
                                                :src="
                                                    img.startsWith('/images/')
                                                        ? img
                                                        : basePath + img
                                                "
                                                alt="Thumbnail"
                                                loading="lazy"
                                                @error="
                                                    onThumbnailError($event)
                                                "
                                            />
                                        </div>
                                    </div>
                                </fieldset>
                            </div>
                        </div>

                        <!-- CENTER: ALL OTHER INFO EXCEPT PRICING -->
                        <div class="form-col-center">
                            <Card>
                                <template #title>
                                    <div>
                                        <h6 class="text-primary">
                                            General Information
                                        </h6>
                                        <Divider />
                                    </div>
                                </template>
                                <template #content>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>External Title:</label>
                                                <Textarea
                                                    ref="productTextarea"
                                                    class="no-resize"
                                                    v-model="item.ProductTitle"
                                                    placeholder="Product Title"
                                                    rows="2"
                                                    @input="autoResize"
                                                    size="small"
                                                    fluid
                                                ></Textarea>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>Internal Title:</label>
                                                <Textarea
                                                    ref="productTextarea"
                                                    class="no-resize"
                                                    :value="
                                                        getDisplayTitle(item)
                                                    "
                                                    placeholder="Product Title"
                                                    rows="2"
                                                    @input="autoResize"
                                                    size="small"
                                                    fluid
                                                    readonly
                                                    disabled
                                                ></Textarea>
                                            </fieldset>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <fieldset>
                                                <label>RT:</label>
                                                <InputText
                                                    type="text"
                                                    size="small"
                                                    fluid
                                                    :value="item.ProductID"
                                                    placeholder="RT Counter"
                                                />
                                            </fieldset>
                                        </div>
                                        <div class="col-md-4">
                                            <fieldset>
                                                <label>ASIN:</label>
                                                <InputText
                                                    type="text"
                                                    size="small"
                                                    fluid
                                                    v-model="item.ASIN"
                                                    readonly
                                                    disabled
                                                />
                                            </fieldset>
                                        </div>
                                        <div class="col-md-4">
                                            <fieldset>
                                                <label>FNSKU:</label>
                                                <InputText
                                                    type="text"
                                                    size="small"
                                                    fluid
                                                    v-model="item.FNSKU"
                                                    readonly
                                                    disabled
                                                />
                                            </fieldset>
                                        </div>
                                    </div>
                                </template>
                            </Card>
                            <div class="mt-4 bg-white border-0">
                                <div class="row">
                                    <div class="col-lg-3 mb-2">
                                        <Card>
                                            <template #title>
                                                <h6 class="text-primary">
                                                    Dates
                                                </h6>
                                                <Divider />
                                            </template>
                                            <template #content>
                                                <fieldset>
                                                    <label
                                                        ><span
                                                            >Order Date:</span
                                                        ></label
                                                    >
                                                    <InputText
                                                        type="date"
                                                        size="small"
                                                        fluid
                                                        v-model="item.orderdate"
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        ><span
                                                            >Payment Date:</span
                                                        ></label
                                                    >
                                                    <InputText
                                                        type="date"
                                                        size="small"
                                                        fluid
                                                        v-model="
                                                            item.paymentdate
                                                        "
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        ><span
                                                            >Shipped Date:</span
                                                        ></label
                                                    >
                                                    <InputText
                                                        type="date"
                                                        size="small"
                                                        fluid
                                                        v-model="item.shipdate"
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        ><span
                                                            >Delivered
                                                            Date:</span
                                                        ></label
                                                    >
                                                    <InputText
                                                        type="date"
                                                        size="small"
                                                        fluid
                                                        v-model="
                                                            item.datedelivered
                                                        "
                                                    />
                                                </fieldset>
                                            </template>
                                        </Card>
                                    </div>
                                    <div class="col-lg-3 mb-2">
                                        <Card>
                                            <template #title>
                                                <h6 class="text-primary">
                                                    Serial & Tracking
                                                </h6>
                                                <Divider />
                                            </template>
                                            <template #content>
                                                <template
                                                    v-if="serialKeys.length"
                                                >
                                                    <fieldset
                                                        v-for="(
                                                            key, index
                                                        ) in serialKeys"
                                                        :key="key"
                                                    >
                                                        <label
                                                            >Serial Number
                                                            {{
                                                                getLabel(index)
                                                            }}:</label
                                                        >
                                                        <InputText
                                                            type="text"
                                                            size="small"
                                                            fluid
                                                            v-model="item[key]"
                                                            @blur="
                                                                checkDuplicateSerial(
                                                                    item[key],
                                                                    key,
                                                                )
                                                            "
                                                            :class="{
                                                                'p-invalid':
                                                                    serialErrors[
                                                                        key
                                                                    ],
                                                            }"
                                                        />
                                                        <small
                                                            v-if="
                                                                serialErrors[
                                                                    key
                                                                ]
                                                            "
                                                            class="p-error"
                                                        >
                                                            {{
                                                                serialErrors[
                                                                    key
                                                                ]
                                                            }}
                                                        </small>
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
                                                            >Tracking Number
                                                            {{
                                                                index + 1
                                                            }}:</label
                                                        >
                                                        <InputText
                                                            type="text"
                                                            size="small"
                                                            fluid
                                                            v-model="item[key]"
                                                        />
                                                    </fieldset>
                                                </template>
                                            </template>
                                        </Card>
                                    </div>
                                    <div class="col-lg-3 mb-2">
                                        <Card>
                                            <template #title>
                                                <h6 class="text-primary">
                                                    Product Information
                                                </h6>
                                                <Divider />
                                            </template>
                                            <template #content>
                                                <fieldset>
                                                    <label
                                                        ><span
                                                            >Sub-variant:</span
                                                        ></label
                                                    >
                                                    <InputText
                                                        type="text"
                                                        size="small"
                                                        fluid
                                                        v-model="
                                                            item.itemnumber
                                                        "
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        ><span
                                                            >Order Number:</span
                                                        ></label
                                                    >
                                                    <InputText
                                                        type="text"
                                                        size="small"
                                                        fluid
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
                                                    <InputText
                                                        type="text"
                                                        size="small"
                                                        fluid
                                                        v-model="
                                                            item.itemnumber
                                                        "
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        ><span
                                                            >Supplier
                                                            ID/Name:</span
                                                        ></label
                                                    >
                                                    <InputText
                                                        type="text"
                                                        size="small"
                                                        fluid
                                                        v-model="item.seller"
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Material:</label>
                                                    <Select
                                                        v-model="
                                                            item.materialtype
                                                        "
                                                        :options="
                                                            materialOptions
                                                        "
                                                        optionLabel="label"
                                                        optionValue="value"
                                                        placeholder="Select material type"
                                                        size="small"
                                                        fluid
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Source Type:</label>
                                                    <Select
                                                        v-model="
                                                            item.sourceType
                                                        "
                                                        :options="
                                                            sourceTypeOptions
                                                        "
                                                        optionLabel="label"
                                                        optionValue="value"
                                                        placeholder="Select source type"
                                                        size="small"
                                                        fluid
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        >Carrier /
                                                        Courier:</label
                                                    >
                                                    <Select
                                                        v-model="item.carrier"
                                                        :options="
                                                            courierOptions
                                                        "
                                                        optionLabel="label"
                                                        optionValue="value"
                                                        placeholder="Select courier"
                                                        size="small"
                                                        fluid
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        >Listed
                                                        Condition:</label
                                                    >
                                                    <Select
                                                        v-model="
                                                            item.listedcondition
                                                        "
                                                        :options="
                                                            listedConditionOptions
                                                        "
                                                        optionLabel="label"
                                                        optionValue="value"
                                                        placeholder="Select condition"
                                                        size="small"
                                                        fluid
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        >Payment Method:</label
                                                    >
                                                    <Select
                                                        v-model="
                                                            item.paymentmethod
                                                        "
                                                        :options="
                                                            paymentMethodOptions
                                                        "
                                                        optionLabel="label"
                                                        optionValue="value"
                                                        placeholder="Select Payment Method"
                                                        size="small"
                                                        fluid
                                                    />
                                                </fieldset>
                                            </template>
                                        </Card>
                                    </div>
                                    <div class="col-lg-3 mb-2">
                                        <Card>
                                            <template #title>
                                                <h6 class="text-primary">
                                                    Other Info
                                                </h6>
                                                <Divider />
                                            </template>
                                            <template #content>
                                                <fieldset>
                                                    <label>Module:</label>
                                                    <InputText
                                                        type="text"
                                                        size="small"
                                                        fluid
                                                        v-model="
                                                            item.ProductModuleLoc
                                                        "
                                                        readonly
                                                        disabled
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Store Name:</label>
                                                    <Select
                                                        v-model="item.storename"
                                                        :options="
                                                            storenameOptions
                                                        "
                                                        optionLabel="label"
                                                        optionValue="value"
                                                        placeholder=" Select Store Name"
                                                        size="small"
                                                        fluid
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label>RPN:</label>
                                                    <InputText
                                                        type="text"
                                                        size="small"
                                                        fluid
                                                        v-model="item.RPN"
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label>PRD:</label>
                                                    <InputText
                                                        type="text"
                                                        size="small"
                                                        fluid
                                                        v-model="item.PRD"
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label>PCN:</label>
                                                    <InputText
                                                        type="text"
                                                        size="small"
                                                        fluid
                                                        v-model="item.PCN"
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        >Basket Number:</label
                                                    >
                                                    <InputText
                                                        type="text"
                                                        size="small"
                                                        fluid
                                                        v-model="
                                                            item.basketnumber
                                                        "
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        >Priority Rank:</label
                                                    >
                                                    <Select
                                                        v-model="
                                                            item.priorityrank
                                                        "
                                                        :options="
                                                            priorityRanksOptions
                                                        "
                                                        optionLabel="label"
                                                        optionValue="value"
                                                        placeholder=" Select Priority Rank"
                                                        size="small"
                                                        fluid
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        >Validation
                                                        Status:</label
                                                    >
                                                    <Select
                                                        v-model="
                                                            item.validation_status
                                                        "
                                                        :options="
                                                            validationStatusOptions
                                                        "
                                                        optionLabel="label"
                                                        optionValue="value"
                                                        placeholder="Select Validation Status"
                                                        size="small"
                                                        fluid
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        >Return Status:</label
                                                    >
                                                    <InputText
                                                        type="text"
                                                        size="small"
                                                        fluid
                                                        v-model="
                                                            item.returnstatus
                                                        "
                                                        readonly
                                                        disabled
                                                    />
                                                </fieldset>
                                            </template>
                                        </Card>
                                    </div>
                                </div>
                            </div>
                            <fieldset>
                                <label><span>Description:</span></label>
                                <Textarea
                                    ref="descriptionarea"
                                    class="no-resize"
                                    v-model="item.description"
                                    placeholder="Description"
                                    rows="3"
                                    fluid
                                    size="small"
                                    @input="autoResize"
                                ></Textarea>
                            </fieldset>

                            <fieldset>
                                <label><span>Supplier Notes:</span></label>
                                <Textarea
                                    ref="supplierNotesarea"
                                    class="no-resize"
                                    v-model="item.supplierNotes"
                                    placeholder="Supplier Notes"
                                    rows="3"
                                    fluid
                                    size="small"
                                    @input="autoResize"
                                ></Textarea>
                            </fieldset>

                            <fieldset>
                                <label><span>Employee Notes:</span></label>
                                <Textarea
                                    ref="employeeNotesarea"
                                    class="no-resize"
                                    v-model="item.employeeNotes"
                                    placeholder="Employee Notes"
                                    rows="3"
                                    fluid
                                    size="small"
                                    @input="autoResize"
                                ></Textarea>
                            </fieldset>

                            <fieldset>
                                <label><span>Sticker Notes:</span></label>
                                <Textarea
                                    ref="stickerNotesarea"
                                    class="no-resize"
                                    v-model="item.stickerNotes"
                                    placeholder="Employee Notes"
                                    rows="3"
                                    fluid
                                    size="small"
                                    @input="autoResize"
                                ></Textarea>
                            </fieldset>
                        </div>

                        <!-- RIGHT: PRICING -->
                        <div class="form-col-right" v-show="showPricingSection">
                            <Card class="shadow">
                                <template #title>
                                    <h4 class="text-primary">Pricing</h4>
                                    <Divider />
                                </template>
                                <template #content>
                                    <fieldset>
                                        <label><span>Quantity</span></label>
                                        <InputText
                                            type="number"
                                            size="small"
                                            fluid
                                            class="text-end"
                                            v-model="item.quantity"
                                        />
                                    </fieldset>

                                    <fieldset>
                                        <label><span>Total Price</span></label>
                                        <InputText
                                            type="number"
                                            size="small"
                                            fluid
                                            class="text-end"
                                            v-model="item.price"
                                        />
                                    </fieldset>

                                    <fieldset>
                                        <label><span>Discount</span></label>
                                        <InputText
                                            type="number"
                                            size="small"
                                            fluid
                                            class="text-end"
                                            v-model="item.Discount"
                                        />
                                    </fieldset>

                                    <fieldset>
                                        <label><span>Tax</span></label>
                                        <InputText
                                            type="number"
                                            size="small"
                                            fluid
                                            class="text-end"
                                            v-model="item.tax"
                                        />
                                    </fieldset>

                                    <fieldset>
                                        <label><span>Shipping</span></label>
                                        <InputText
                                            type="number"
                                            size="small"
                                            fluid
                                            class="text-end"
                                            v-model="item.priceshipping"
                                        />
                                    </fieldset>

                                    <fieldset>
                                        <label><span>Refund</span></label>
                                        <InputText
                                            type="number"
                                            size="small"
                                            fluid
                                            class="text-end"
                                            v-model="item.refund"
                                        />
                                    </fieldset>

                                    <!-- Divider -->
                                    <hr class="my-4" />

                                    <fieldset>
                                        <label><span>Unit Price</span></label>
                                        <InputText
                                            type="text"
                                            size="small"
                                            fluid
                                            class="text-end bg-light"
                                            :value="formattedUnitprice"
                                            readonly
                                        />
                                    </fieldset>
                                    <!-- Total Summary -->
                                    <fieldset>
                                        <label><span>Grand Total</span></label>
                                        <InputText
                                            type="text"
                                            size="small"
                                            fluid
                                            class="text-end bg-light fw-bold text-success"
                                            :value="grandTotal"
                                            readonly
                                        />
                                    </fieldset>
                                </template>
                            </Card>
                        </div>
                    </div>
                </form>
            </div>
            <template #footer>
                <div class="pt-2">
                    <Button type="button" @click="saveEditModal">
                        <i class="fas fa-save"></i> Save
                    </Button>
                </div>
            </template>
        </Dialog>

        <copyDetailsModal
            :show-modal="showCopyDetailsModal"
            :item-data="currentCopyItem"
            @close="closeCopyDetailsModal"
        />

        <!----DIALOGS FOR UPDATING CAPTURED IMAGES---->
        <Dialog
            v-model:visible="openCapturedImageDialog"
            modal
            header="Images"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <div class="image-list-container">
                <!-- Existing Images -->
                <div
                    v-for="(image, index) in selectedImageList"
                    :key="`dialog-img-${index}`"
                    class="image-wrapper"
                >
                    <div class="image-container-with-overlay">
                        <!-- Delete Button - MUST be before img -->
                        <button
                            v-if="
                                uploadingIndex !== index &&
                                deletingIndex !== index
                            "
                            class="delete-image-btn"
                            @click.stop="confirmDeleteImage(index, image)"
                            type="button"
                        >
                            <i class="pi pi-trash"></i>
                        </button>

                        <img
                            :src="image"
                            :alt="`Product image ${index + 1}`"
                            class="image-item"
                            :class="{
                                uploading: uploadingIndex === index,
                                deleting: deletingIndex === index,
                            }"
                            :key="`img-content-${image}`"
                        />

                        <!-- Loading Overlay for Upload -->
                        <div
                            v-if="uploadingIndex === index"
                            class="upload-overlay"
                        >
                            <div class="upload-spinner"></div>
                            <p class="upload-text">Uploading...</p>
                        </div>

                        <!-- Loading Overlay for Delete -->
                        <div
                            v-if="deletingIndex === index"
                            class="upload-overlay"
                        >
                            <div class="upload-spinner"></div>
                            <p class="upload-text">Deleting...</p>
                        </div>
                    </div>

                    <input
                        type="file"
                        ref="capturedProductImageRef"
                        accept="image/*"
                        style="display: none"
                        @change="handleFileChange($event, index)"
                    />

                    <Button
                        :label="
                            uploadingIndex === index ? 'Uploading...' : 'Update'
                        "
                        size="small"
                        icon="pi pi-upload"
                        class="upload-button"
                        :loading="uploadingIndex === index"
                        :disabled="uploadingIndex === index || deletingIndex === index"
                        @click="handleUploadClick(index, image)"
                    />
                </div>

                <!-- Add New Image Button -->
                <div
                    class="image-wrapper add-image-wrapper"
                    v-if="selectedImageList.length < imageLimitCount"
                >
                    <div
                        class="add-image-container"
                        @click="handleAddNewImageClick"
                    >
                        <i class="pi pi-plus add-icon"></i>
                        <p class="add-text">Add Image</p>
                        <p class="add-subtext">
                            {{ selectedImageList.length }}/
                            {{ imageLimitCount }}
                        </p>
                    </div>

                    <input
                        type="file"
                        ref="addNewImageInputRef"
                        accept="image/*"
                        style="display: none"
                        @change="handleAddImageChange"
                    />
                </div>
            </div>
        </Dialog>
        <ScrollTop />
    </div>
</template>

<script>
import {
    Button,
    Card,
    Dialog,
    Divider,
    InputText,
    ScrollTop,
    Select,
    Textarea,
} from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import Houseage from "./houseage.js";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
import { showPricingForPH } from "../../utils/helpers.js";
import axios from "axios";
import Swal from "sweetalert2";

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
        slot: "ProductTitle",
        style: { minWidth: "15rem", maxWidth: "20rem" },
    },
    {
        header: "Status",
        field: "validation_status",
        headerStyle: "font-size: 16px;",
        slot: "validationStatus",
        style: { width: "6rem", minWidth: "6rem" },
    },
    {
        field: "ASIN",
        header: "ASIN",
        headerStyle: "font-size: 16px;",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "FNSKU",
        header: "FNSKU",
        headerStyle: "font-size: 16px;",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "grading",
        header: "Grading",
        headerStyle: "font-size: 16px;",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "serialnumber",
        header: "Serial Number",
        headerStyle: "font-size: 16px;",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "quantity",
        header: "Quantity",
        headerStyle: "font-size: 16px;",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "fulfillment_status",
        header: "Fullfilment Status",
        headerStyle: "font-size: 16px;",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "ProductModuleLoc",
        header: "Module",
        headerStyle: "font-size: 16px;",
        bodyStyle: { fontSize: "14px" },
    },
    {
        field: "returnstatus",
        header: "Return Status",
        headerStyle: "font-size: 16px;",
        bodyStyle: { fontSize: "14px" },
    },
];
export default {
    mixins: [Houseage],
    components: {
        XDataTable,
        Dialog,
        Divider,
        Card,
        Select,
        InputText,
        TableGallery,
        Button,
        Textarea,
        ScrollTop,
        TitlePage,
        ViewImageGalleryModal,
        AnimateDiv,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            materialOptions: [
                { label: "Inventory", value: "Inventory" },
                { label: "Supplies", value: "Supplies" },
                { label: "Components", value: "Components" },
                { label: "Office Equipment", value: "Office Equipment" },
            ],
            sourceTypeOptions: [
                { label: "ES", value: "ES" },
                { label: "AS", value: "AS" },
                { label: "XS", value: "XS" },
                { label: "PS", value: "PS" },
                { label: "RS", value: "RS" },
                { label: "B&H", value: "B&H" },
            ],
            listedConditionOptions: [
                { label: "New", value: "New" },
                { label: "Open Box", value: "Open Box" },
                { label: "Used", value: "Used" },
                {
                    label: "For parts or not working",
                    value: "For parts or not working",
                },
            ],
            paymentMethodOptions: [
                { label: "PayPal", value: "PayPal" },
                { label: "Credit/Debit Card", value: "Credit/Debit Card" },
                { label: "Cash", value: "Cash" },
                { label: "Bank Transfer", value: "Bank Transfer" },
                { label: "Check", value: "Check" },
            ],
            rowsPerPage: ROWS_PER_PAGE,
            showPricingSection: showPricingForPH(),
            openCapturedImageDialog: false,
            uploadingIndex: null,
            deletingIndex: null,
            imageRenderKey: 0,
            selectedImageList: [],
            imageLimitCount: 0,
            imageType: '',
            imgNumber: 0,
        };
    },
    methods: {
        handleOpenProductImageDialog(type, limit) {
            this.imageLimitCount = limit;
            switch (type) {
                case "product":
                    this.selectedImageList = this.imageList;
                    this.imageType = "captured";
                    break;
                case "tracking":
                    this.selectedImageList = this.trackingImgList;
                    this.imageType = "tracking";
                    break;
                case "serial":
                    this.selectedImageList = this.serialImgList;
                    this.imageType = "serial";
                    break;
                default:
                    break;
            }

            this.openCapturedImageDialog = true;
        },

        handleUploadClick(index, currentImage) {
            const fileInput = this.$refs.capturedProductImageRef;
            if (fileInput && fileInput[index]) {
                fileInput[index].click();
            }
            this.imgNumber = currentImage.split('_').pop().match(/(\d+)/)?.[1];
        },

        extractImageNumbers(imageList) {
            if (!imageList || !Array.isArray(imageList)) {
                return [];
            }

            const numbers = [];
            
            imageList.forEach(imagePath => {
                if (!imagePath) return;
                
                const imageNumber = imagePath.split('_').pop().match(/(\d+)/)?.[1];
                
                if (imageNumber) {
                    const num = parseInt(imageNumber, 10);
                    if (num >= 1 && num <= 12 && !numbers.includes(num)) {
                        numbers.push(num);
                    }
                }
            });
            
            return numbers.sort((a, b) => a - b);
        },

        findNextAvailableImageNumber(imageList, maxCount = 12) {
            const usedNumbers = this.extractImageNumbers(imageList);
            
            for (let i = 1; i <= maxCount; i++) {
                if (!usedNumbers.includes(i)) {
                    return i;
                }
            }
            
            return null;
        },

        handleAddNewImageClick() {
            this.$refs.addNewImageInputRef.click();
        },

        addCacheBuster(url, bust = null) {
            if (!url) return url;

            const buster = bust || Date.now();
            const separator = url.includes("?") ? "&" : "?";
            const cleanUrl = url.replace(/[?&](t|v|_)=\d+/g, "");

            return `${cleanUrl}${separator}t=${buster}`;
        },

        removeCacheBuster(url) {
            if (!url) return url;
            return url.replace(/[?&](t|v|_)=\d+/g, "").replace(/\?$/, "");
        },

        /**
         * Build image list from item data with cache busters
         */
        buildImageList(item, imageType, maxCount, timestamp) {
            const images = [];
            const basePath = `/images/product_images/${item.company || 'Airstaffs'}/`;
            
            if (imageType === 'captured') {
                // Check capturedImages object first
                if (item.capturedImages) {
                    for (let i = 1; i <= maxCount; i++) {
                        const imgKey = `capturedimg${i}`;
                        if (item.capturedImages[imgKey]) {
                            const path = basePath + item.capturedImages[imgKey];
                            images.push(this.addCacheBuster(path, timestamp));
                        }
                    }
                }
                
                // Fallback to regular img properties if no captured images
                if (images.length === 0) {
                    for (let i = 1; i <= maxCount; i++) {
                        const imgKey = `img${i}`;
                        if (item[imgKey]) {
                            const path = this.basePath + item[imgKey];
                            images.push(this.addCacheBuster(path, timestamp));
                        }
                    }
                }
            } else {
                // For serial and tracking images
                for (let i = 1; i <= maxCount; i++) {
                    const imgKey = `${imageType}img${i}`;
                    if (item[imgKey]) {
                        const path = basePath + item[imgKey];
                        images.push(this.addCacheBuster(path, timestamp));
                    }
                }
            }
            
            return images;
        },

        /**
         * Refresh current item from inventory
         */
        async refreshCurrentItem() {
            const updatedItem = this.inventory.find(
                inv => inv.ProductID === this.item.ProductID
            );
            
            if (updatedItem) {
                Object.assign(this.item, updatedItem);
                
                console.log('Item refreshed:', {
                    productId: this.item.ProductID,
                    imageType: this.imageType,
                    capturedImages: this.item.capturedImages,
                    serialImages: {
                        serialimg1: this.item.serialimg1,
                        serialimg2: this.item.serialimg2,
                    },
                    trackingImages: {
                        trackingimg1: this.item.trackingimg1,
                        trackingimg2: this.item.trackingimg2,
                    }
                });
                
                this.imageRenderKey++;
                this.$forceUpdate();
            }
        },

        /**
         * Rebuild image lists from fresh item data
         */
        rebuildImageLists() {
            const timestamp = Date.now();
            
            switch (this.imageType) {
                case "captured":
                    this.imageList = this.buildImageList(this.item, 'captured', 12, timestamp);
                    this.selectedImageList = [...this.imageList];
                    this.activeIndex = Math.min(this.activeIndex, this.imageList.length - 1);
                    if (this.activeIndex < 0 && this.imageList.length > 0) this.activeIndex = 0;
                    break;
                    
                case "tracking":
                    this.trackingImgList = this.buildImageList(this.item, 'tracking', 2, timestamp);
                    this.selectedImageList = [...this.trackingImgList];
                    this.trackingActiveIndex = Math.min(this.trackingActiveIndex, this.trackingImgList.length - 1);
                    if (this.trackingActiveIndex < 0 && this.trackingImgList.length > 0) this.trackingActiveIndex = 0;
                    break;
                    
                case "serial":
                    this.serialImgList = this.buildImageList(this.item, 'serial', 2, timestamp);
                    this.selectedImageList = [...this.serialImgList];
                    this.serialActiveIndex = Math.min(this.serialActiveIndex, this.serialImgList.length - 1);
                    if (this.serialActiveIndex < 0 && this.serialImgList.length > 0) this.serialActiveIndex = 0;
                    break;
            }
            
            this.imageRenderKey++;
            
            this.$nextTick(() => {
                this.$forceUpdate();
            });
        },

        async handleFileChange(event, index) {
            try {
                const file = event.target.files[0];
                if (!file) return;

                this.uploadingIndex = index;

                const formData = new FormData();
                formData.append("image", file);
                formData.append("productId", this.item.ProductID);
                formData.append("capturedImgCount", this.imgNumber);
                formData.append("imageType", this.imageType);

                const response = await axios.post(
                    "api/houseage/upload-image",
                    formData,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                        withCredentials: true,
                    },
                );

                if (response.data.success) {
                    await this.fetchInventory();
                    await this.refreshCurrentItem();
                    this.rebuildImageLists();

                    await Swal.fire({
                        title: "Upload Success",
                        text: response.data.message || 'Image uploaded successfully',
                        icon: "success",
                        timer: 2000,
                        showConfirmButton: false,
                    });
                }
            } catch (error) {
                console.error("Error uploading product image:", error);
                await Swal.fire({
                    title: "Error",
                    text: error.response?.data?.message || "Failed to upload image",
                    icon: "error",
                    confirmButtonColor: "#ef4444",
                });
            } finally {
                event.target.value = "";
                this.uploadingIndex = null;
            }
        },

        async handleAddImageChange(event) {
            try {
                const file = event.target.files[0];
                if (!file) return;

                const nextImageNumber = this.findNextAvailableImageNumber(
                    this.selectedImageList,
                    this.imageLimitCount
                );

                if (nextImageNumber === null) {
                    await Swal.fire({
                        title: "Limit Reached",
                        text: `Maximum ${this.imageLimitCount} images allowed`,
                        icon: "warning",
                        confirmButtonColor: "#f59e0b",
                    });
                    return;
                }

                this.uploadingIndex = this.selectedImageList.length;

                const formData = new FormData();
                formData.append("image", file);
                formData.append("productId", this.item.ProductID);
                formData.append("capturedImgCount", nextImageNumber);
                formData.append("imageType", this.imageType);

                const response = await axios.post(
                    "api/houseage/upload-image",
                    formData,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                        withCredentials: true,
                    },
                );

                if (response.data.success) {
                    await this.fetchInventory();
                    await this.refreshCurrentItem();
                    this.rebuildImageLists();

                    await Swal.fire({
                        title: "Upload Success",
                        text: `Image added to slot ${nextImageNumber}`,
                        icon: "success",
                        timer: 2000,
                        showConfirmButton: false,
                    });
                }
            } catch (error) {
                console.error("Error adding image:", error);
                await Swal.fire({
                    title: "Error",
                    text: error.response?.data?.message || "Failed to add image",
                    icon: "error",
                    confirmButtonColor: "#ef4444",
                });
            } finally {
                event.target.value = "";
                this.uploadingIndex = null;
            }
        },

        async confirmDeleteImage(index, currentImage) {
            this.imgNumber = currentImage.split('_').pop().match(/(\d+)/)?.[1];

            const result = await Swal.fire({
                title: "Delete Image?",
                text: `Are you sure you want to delete image ${this.imgNumber}? This action cannot be undone.`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#ef4444",
                cancelButtonColor: "#6b7280",
                confirmButtonText: "Yes, delete it!",
                cancelButtonText: "Cancel",
                reverseButtons: true,
            });

            if (result.isConfirmed) {
                await this.handleDeleteImage(index);
            }
        },

        async handleDeleteImage(index) {
            try {
                this.deletingIndex = index;

                const response = await axios.post(
                    "api/houseage/delete-image",
                    {
                        productId: String(this.item.ProductID),
                        capturedImgCount: this.imgNumber,
                        imageType: this.imageType,
                    },
                    {
                        withCredentials: true,
                    },
                );

                if (response.data.success) {
                    await this.fetchInventory();
                    await this.refreshCurrentItem();
                    this.rebuildImageLists();

                    Swal.fire({
                        title: "Deleted!",
                        text: `Image ${this.imgNumber} has been deleted.`,
                        icon: "success",
                        timer: 2000,
                        showConfirmButton: false,
                    });
                }
            } catch (error) {
                console.error("Error deleting image:", error);
                Swal.fire({
                    title: "Error!",
                    text: error.response?.data?.message || "Failed to delete image",
                    icon: "error",
                    confirmButtonColor: "#ef4444",
                });
            } finally {
                this.deletingIndex = null;
            }
        },
    },
    computed: {
        courierOptions() {
            return this.carrierOptions.map((carrier) => ({
                value: carrier,
                label: carrier,
            }));
        },
        storenameOptions() {
            return this.storeNames.map((store) => ({
                value: store,
                label: store,
            }));
        },
        priorityRanksOptions() {
            return this.priorityRanks.map((type) => ({
                label: type,
                value: type,
            }));
        },
        validationStatusOptions() {
            return this.validationStatuses.map((status) => ({
                label: status,
                value: status,
            }));
        },
        uniqueModuleOptions() {
            return [
                { value: "", label: "All Modules" },
                ...this.uniqueModules.map((module) => ({
                    label: module,
                    value: module,
                })),
            ];
        },
        updatePricingView() {
            this.showPricingSection = showPricingForPH();
        },
    },
    mounted() {
        window.addEventListener("resize", this.updatePricingView);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.updatePricingView);
    },
};
</script>

<style>
/* ... (keep all your existing styles) ... */
.search-input-wrapper {
    position: relative;
    width: 100%;
}

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

.search-loading-text {
    text-align: center;
    color: #6c757d;
    font-size: 0.9em;
    margin-top: 8px;
    font-style: italic;
}

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

.loading-content {
    text-align: center;
    padding: 20px;
}

.loading-content p {
    margin-top: 15px;
    color: #6c757d;
    font-weight: 500;
}

.loading-spinner-large {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

.loading-blur {
    filter: blur(1px);
    opacity: 0.6;
    pointer-events: none;
    transition: all 0.3s ease;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.fnsku-search-input:disabled {
    background-color: #f8f9fa;
    cursor: not-allowed;
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

.fnsku-list-container,
.fnsku-card-container {
    position: relative;
}

.table.loading-blur {
    table-layout: fixed;
}

.fnsku-card-container.loading-blur .card {
    pointer-events: none;
}

.fnsku-list-container,
.fnsku-card-container,
.search-input-wrapper {
    transition: all 0.3s ease;
}

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

    .btn-copy-details {
        padding: 8px 12px;
        font-size: 0.8rem;
        margin: 1px;
    }
}

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

fieldset > .p-error {
    color: #a94442;
    font-size: 10px;
    line-height: 0;
}
</style>