<template>
    <div class="vue-container houseage-module">
         <div class="d-flex align-items-center justify-content-between flex-wrap mb-4">
            <TitlePage
                title="Houseage Module"
                subtitle="Manage all products in the internal processing flow, including grading, return status, and next module assignment."
            />

            <div class="d-flex justify-content-center gap-2 mx-4 flex-wrap">
                <Button severity="secondary" size="small" outlined @click="showInvoiceModal = true"
                    label="Generate Invoice" icon="pi pi-file" :disabled="selectedRows.length === 0" v-show="!isUSAccount"/>
                <Button severity="secondary" size="small" outlined @click="goToSuppliersList"
                    label="Suppliers List" icon="pi pi-list" />
            </div>
         </div>
        

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
                :onSelectionChange="onSelectionChange"
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

        <Paginator
            :first="first"
            :rows="perPage"
            :total-records="totalRecords"
            :rows-per-page-options="[10, 20, 50]"
            template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
            class="small-paginator"
            @page="onPageChange"
        />

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
                            <ProductImageGallery
                                label="Serial Images"
                                :imageList="serialImgList"
                                :imageType="'serial'"
                                :maxImages="5"
                                :productId="item.ProductID"
                                :company="item.company"
                                @request-refresh="fetchInventory()"
                            />
                            <ProductImageGallery
                                label="Product Images"
                                :imageList="imageList"
                                :imageType="'captured'"
                                :maxImages="12"
                                :productId="item.ProductID"
                                :company="item.company"
                                @request-refresh="fetchInventory()"
                            />

                            <ProductImageGallery
                                label="Tracking Images"
                                :imageList="trackingImgList"
                                :imageType="'tracking'"
                                :maxImages="2"
                                :productId="item.ProductID"
                                :company="item.company"
                                @request-refresh="fetchInventory()"
                            />
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
                                                    :value="item.rtcounter"
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
                                                <div>
                                                    <Tag
                                                        :value="timezoneLabel"
                                                        severity="info"
                                                        icon="pi pi-clock"
                                                        class="timezone-badge"
                                                    />
                                                </div>
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
                                                        v-model="localOrderDate"
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
                                                            localPaymentDate
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
                                                        v-model="localShipDate"
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
                                                            localDeliveredDate
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
                        :disabled="
                            uploadingIndex === index || deletingIndex === index
                        "
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
        <ProductInvoiceModal :productIds="selectedRows" v-model:visible="showInvoiceModal" />
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
    Paginator,
} from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import Houseage from "./houseage.js";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
import { showPricingForPH } from "../../utils/helpers.js";
import ProductImageGallery from "../../components/ProductImageGallery/ProductImageGallery.vue";
import ProductInvoiceModal from "./ProductInvoiceModal.vue";

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
        field: "FNSKUviewer",
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
        ProductImageGallery,
        Paginator,
        ProductInvoiceModal
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

            currentTimezone: "UTC",
            timezoneLabel: "Loading...",
        };
    },
    methods: {
        convertToLocalDate(dateString) {
            if (!dateString) return "";

            try {
                const userTimezone = this.currentTimezone;
                const isLATimezone =
                    userTimezone === "America/Los_Angeles" ||
                    userTimezone === "America/Pacific" ||
                    !userTimezone;

                // DB stores time in LA timezone — if user is already in LA, just extract date directly
                if (isLATimezone) {
                    return dateString.split(" ")[0].split("T")[0];
                }

                // User is in a different timezone — convert LA time to user's local timezone
                const isRawFormat =
                    !dateString.includes("T") &&
                    !dateString.includes("Z") &&
                    !dateString.includes("+");

                let date;
                if (isRawFormat) {
                    const isoLike = dateString.replace(" ", "T");
                    const tempDate = new Date(isoLike);
                    const laWallClock = new Date(
                        new Date(isoLike).toLocaleString("en-US", {
                            timeZone: "America/Los_Angeles",
                        }),
                    );
                    const diff = tempDate - laWallClock;
                    date = new Date(tempDate.getTime() + diff);
                } else {
                    date = new Date(dateString);
                }

                const formatter = new Intl.DateTimeFormat("en-CA", {
                    timeZone: userTimezone,
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                });

                return formatter.format(date);
            } catch (error) {
                return dateString;
            }
        },

        convertFromLocalDate(localDateString) {
            if (!localDateString) return null;

            try {
                // The input gives us YYYY-MM-DD in user's timezone
                // We need to convert it to a proper datetime for storage

                // Create a date object at noon in the user's timezone to avoid day boundary issues
                const [year, month, day] = localDateString.split("-");
                const dateInUserTz = new Date(
                    `${year}-${month}-${day}T12:00:00`,
                );

                // Format for database storage (ISO format)
                return dateInUserTz.toISOString().split("T")[0]; // Returns YYYY-MM-DD
            } catch (error) {
                console.error("Error converting from local date:", error);
                return localDateString;
            }
        },

        async loadUserTimezone() {
            try {
                const response = await axios.get("/api/timezone/current");

                if (response.data.success && response.data.usertimezone) {
                    this.currentTimezone = response.data.usertimezone;

                    // Format timezone for display
                    const timezoneParts = this.currentTimezone.split("/");
                    const location = timezoneParts[
                        timezoneParts.length - 1
                    ].replace("_", " ");

                    // ✅ FIXED: Calculate GMT offset for the SELECTED timezone, not browser's
                    const date = new Date();

                    // Get the date in UTC
                    const utcDate = new Date(
                        date.toLocaleString("en-US", { timeZone: "UTC" }),
                    );

                    // Get the date in user's selected timezone
                    const userTzDate = new Date(
                        date.toLocaleString("en-US", {
                            timeZone: this.currentTimezone,
                        }),
                    );

                    // Calculate offset in hours
                    const offsetMs = userTzDate - utcDate;
                    const offsetHours = Math.round(offsetMs / (1000 * 60 * 60));
                    const offsetSign = offsetHours >= 0 ? "+" : "-";
                    const gmtOffset = `GMT${offsetSign}${Math.abs(
                        offsetHours,
                    )}`;

                    this.timezoneLabel = `(${gmtOffset})`;
                } else {
                    // Fallback to browser timezone
                    const browserTz =
                        Intl.DateTimeFormat().resolvedOptions().timeZone;
                    this.currentTimezone = browserTz;
                    const location = browserTz
                        .split("/")
                        .pop()
                        .replace("_", " ");
                    this.timezoneLabel = location;
                }

                console.log("📍 Timezone loaded:", this.timezoneLabel);
            } catch (error) {
                console.error("Error loading timezone:", error);
                this.currentTimezone = "UTC";
                this.timezoneLabel = "UTC";
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

        // ✅ ADD THESE COMPUTED PROPERTIES FOR DATE CONVERSION
        localOrderDate() {
            return this.convertToLocalDate(this.item.orderdate);
        },
        localPaymentDate: {
            get() {
                return this.convertToLocalDate(this.item.paymentdate);
            },
            set(value) {
                this.item.paymentdate = this.convertFromLocalDate(value);
            },
        },
        localShipDate: {
            get() {
                return this.convertToLocalDate(this.item.shipdate);
            },
            set(value) {
                this.item.shipdate = this.convertFromLocalDate(value);
            },
        },
        localDeliveredDate: {
            get() {
                return this.convertToLocalDate(this.item.datedelivered);
            },
            set(value) {
                this.item.datedelivered = this.convertFromLocalDate(value);
            },
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
