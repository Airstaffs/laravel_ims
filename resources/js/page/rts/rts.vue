<template>
    <div class="vue-container houseage-module">

        <TitlePage title="RTS Module"
            subtitle="Manage products designated for return to the original seller or supplier. Finalize shipment details and confirm the return status." />

        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="px-4">
            <XDataTable :value="sortedInventory" :columns="column" :pagination="false" :loading="loading"
                tableClass="desktop-view" selectionMode="multiple" dataKey="ProductID">

                <template #gallery="{ data }">
                    <div class="d-flex justify-content-center align-items-center">
                        <TableGallery :data="data" :openImageModal="openImageModal" :handleImageError="handleImageError"
                            :countAdditionalImages="countAllImages" />
                    </div>
                </template>
                <template #ProductTitle="{ data }">
                    <div class="d-flex align-items-start gap-4">
                        <div style="word-break: break-word; white-space: normal; overflow-wrap: break-word; flex: 1;">
                            <p style="font-size: .8rem;">ID# {{ data.rtcounter }}</p>
                            <p class="fw-semibold">
                                {{ data.ProductTitle }}
                            </p>
                        </div>
                    </div>
                </template>
                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start">
                        <Button label="More Details" severity="contrast" variant="text" size="small"
                            icon="pi pi-info-circle" class="text-primary" @click="toggleMoreDetailsModal(data)" />
                        <Button label="Edit" severity="contrast" variant="text" size="small" icon="pi pi-pencil"
                            class="text-warning" @click="openEditModal(data)" />
                        <Button label="RTS Option" severity="contrast" variant="text" size="small" icon="pi pi-wrench"
                            class="text-success" @click="openRTSModal(data)" />
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
                <div v-else-if="sortedInventory.length === 0" class="no-data-mobile">
                    No data found
                </div>
                <div class="mobile-card" v-else v-for="(item, index) in sortedInventory" :key="item.id">
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <TableGallery :data="item" :openImageModal="openImageModal" :handleImageError="handleImageError"
                            :countAdditionalImages="countAllImages" />
                        <div class="mobile-product-info">
                            <h6 class="mobile-product-name clickable">
                                <p>RT# : {{ item.rtcounter }}</p>
                                <p>{{ item.ProductTitle }}</p>
                            </h6>
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detal-value">
                                {{ item.ASIN }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">FNSKU:</span>
                            <span class="mobile-detal-value">
                                {{ item.FNSKUviewer }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Grading:</span>
                            <span class="mobile-detal-value">
                                {{ item.grading }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Serial Number:</span>
                            <span class="mobile-detal-value">
                                {{ item.serialnumber }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Quantity:</span>
                            <span class="mobile-detal-value">
                                {{ item.quantity }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Fulfillment Status:</span>
                            <span class="mobile-detal-value">
                                {{ item.fulfillment_status }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Module:</span>
                            <span class="mobile-detal-value">
                                {{ item.ProductModuleLoc }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Return Status:</span>
                            <span class="mobile-detal-value">
                                {{ item.returnstatus }}</span>
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-actions">
                        <Button @click="toggleMoreDetailsModal(item)" label="Details" icon="pi pi-info-circle"
                            size="small" severity="info" />

                        <Button @click="EditItem(item)" label="Edit" icon="pi pi-pencil" size="small" severity="warn" />

                        <Button @click="openRTSModal(item)" label="RTS Option" icon="pi pi-wrench" size="small"
                            severity="success" />
                    </div>

                    <hr v-if="expandedRows[index]" />

                    <div v-if="expandedRows[index]" class="mobile-expanded-content">
                        <p>
                            <strong>External Title provided by Supplier:</strong>
                            {{ item.ProductTitle }}
                        </p>
                        <p><strong>Product Name:</strong> {{ item.AStitle }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!----More Details---->

        <Dialog v-model:visible="openMoreDetailModal" modal header="More Details">
            <div class="d-flex flex-column gap-2" style="font-size: 14px;">
                <div>
                    <span class="fw-bold">External Title provided by Supplier: </span>
                    <span>{{ detailData.ProductTitle }}</span>
                </div>
                <div>
                    <span class="fw-bold">Product Title: </span>
                    <span>{{ detailData.AStitle }}</span>
                </div>
            </div>
        </Dialog>

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
        <ViewImageGalleryModal :showImageModal="showImageModal" :closeImageModal="closeImageModal"
            :ProductTitle="ProductTitle" :regularImages="regularImages" :capturedImages="capturedImages"
            :handleImageError="handleImageError" />


        <!-- Edit Modal -->
        <Dialog v-model:visible="showEditModal" modal header="Edit Product" :style="{ width: '98%' }" :pt="{
            root: { class: 'mobile-fullscreen-dialog' }
        }">
            <div>
                <div class="edit-order-container">
                    <form method="POST" class="editOrderForm">
                        <div class="form-grid-wrapper">
                            <!-- LEFT: IMAGE + GENERAL INFO -->
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

                                <Card>
                                    <template #title>
                                        <h6 class="text-primary">Dates</h6>
                                        <Divider />
                                    </template>
                                    <template #content>
                                        <fieldset>
                                            <label><span>Order Date:</span></label>
                                            <InputText fluid size="small" type="date" v-model="item.orderdate" />
                                        </fieldset>
                                        <fieldset>
                                            <label><span>Payment Date:</span></label>
                                            <InputText fluid size="small" type="date" v-model="item.paymentdate" />
                                        </fieldset>
                                        <fieldset>
                                            <label><span>Shipped Date:</span></label>
                                            <InputText fluid size="small" type="date" v-model="item.shipdate" />
                                        </fieldset>
                                        <fieldset>
                                            <label><span>Delivered Date:</span></label>
                                            <InputText fluid size="small" type="date" v-model="item.datedelivered" />
                                        </fieldset>
                                    </template>
                                </Card>
                            </div>

                            <!-- CENTER: ALL OTHER INFO EXCEPT PRICING -->
                            <div class="form-col-center">
                                <!-- <Card>
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
                                                    <label>External Title:</label>
                                                    <Textarea ref="productTextarea" fluid size="small" class="no-resize"
                                                        v-model="item.ProductTitle" placeholder="Product Title" rows="2"
                                                        @input="autoResize"></Textarea>
                                                </fieldset>
                                            </div>
                                            <div class="col-md-6">
                                                <fieldset>
                                                    <label>Internal Title:</label>
                                                    <Textarea ref="productTextarea" fluid size="small" class="no-resize"
                                                        v-model="item.ProductTitle" placeholder="Product Title" rows="2"
                                                        @input="autoResize" readonly disabled></Textarea>
                                                </fieldset>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <fieldset>
                                                    <label>RT:</label>
                                                    <InputText fluid size="small" type="text" :value="item.rtcounter"
                                                        placeholder="RT Counter" />
                                                </fieldset>
                                            </div>
                                            <div class="col-md-4">
                                                <fieldset>
                                                    <label>ASIN:</label>
                                                    <InputText fluid size="small" type="text" v-model="item.ASIN"
                                                        readonly disabled />
                                                </fieldset>
                                            </div>
                                            <div class="col-md-4">
                                                <fieldset>
                                                    <label>FNSKU:</label>
                                                    <InputText fluid size="small" type="text" v-model="item.FNSKU"
                                                        readonly disabled />
                                                </fieldset>
                                            </div>
                                        </div>
                                    </template>
                                </Card> -->
                                <div class=" bg-white border-0">
                                    <!-- SECTION: Dates -->
                                    <div class="row">
                                        <div class="col-md-3">
                                            <Card>
                                                <template #title>
                                                    <div>
                                                        <h6 class="text-primary">General Information</h6>
                                                        <Divider />
                                                    </div>
                                                </template>
                                                <template #content>

                                                    <div>
                                                        <fieldset>
                                                            <label>External Title:</label>
                                                            <Textarea ref="productTextarea" fluid size="small"
                                                                class="no-resize" v-model="item.ProductTitle"
                                                                placeholder="Product Title" rows="2"
                                                                @input="autoResize"></Textarea>
                                                        </fieldset>
                                                    </div>
                                                    <div>
                                                        <fieldset>
                                                            <label>Internal Title:</label>
                                                            <Textarea ref="productTextarea" fluid size="small"
                                                                class="no-resize" v-model="item.ProductTitle"
                                                                placeholder="Product Title" rows="2" @input="autoResize"
                                                                readonly disabled></Textarea>
                                                        </fieldset>
                                                    </div>


                                                    <div>
                                                        <fieldset>
                                                            <label>RT:</label>
                                                            <InputText fluid size="small" type="text"
                                                                :value="item.rtcounter" placeholder="RT Counter" />
                                                        </fieldset>
                                                    </div>
                                                    <div>
                                                        <fieldset>
                                                            <label>ASIN:</label>
                                                            <InputText fluid size="small" type="text"
                                                                v-model="item.ASIN" readonly disabled />
                                                        </fieldset>
                                                    </div>
                                                    <div>
                                                        <fieldset>
                                                            <label>FNSKU:</label>
                                                            <InputText fluid size="small" type="text"
                                                                v-model="item.FNSKU" readonly disabled />
                                                        </fieldset>
                                                    </div>

                                                </template>
                                            </Card>

                                            <Card class="mt-2">
                                                <template #content>
                                                    <fieldset>
                                                        <label><span>Description:</span></label>
                                                        <Textarea ref="descriptionarea" class="no-resize"
                                                            v-model="item.description" placeholder="Description"
                                                            rows="6" fluid size="small" @input="autoResize"></Textarea>
                                                    </fieldset>
                                                </template>
                                            </Card>
                                        </div>
                                        <div class="col-md-3 mb-2">

                                            <!-- SECTION: Serial & Tracking -->
                                            <Card>
                                                <template #title>
                                                    <h6 class="text-primary">Serial and Tracking</h6>
                                                    <Divider />
                                                </template>
                                                <template #content>
                                                    <template v-if="serialKeys.length">
                                                        <fieldset v-for="(
key, index
                                                    ) in serialKeys" :key="key">
                                                            <label>Serial Number
                                                                {{
                                                                    getLabel(index)
                                                                }}:</label>
                                                            <InputText fluid size="small" type="text"
                                                                v-model="item[key]" />
                                                        </fieldset>
                                                    </template>

                                                    <template v-if="trackingKeys.length">
                                                        <fieldset v-for="(
key, index
                                                    ) in trackingKeys" :key="key">
                                                            <label>Tracking Number
                                                                {{
                                                                    index + 1
                                                                }}:</label>
                                                            <InputText fluid size="small" type="text"
                                                                v-model="item[key]" />
                                                        </fieldset>
                                                    </template>
                                                </template>


                                            </Card>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <!-- SECTION: Product Info -->
                                            <Card>
                                                <template #title>
                                                    <h6 class="text-primary">Product Info</h6>
                                                    <Divider />
                                                </template>
                                                <template #content>
                                                    <fieldset>
                                                        <label>Sub-variant:</label>
                                                        <InputText fluid size="small" type="text"
                                                            v-model="item.itemnumber" />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Order Number:</label>
                                                        <InputText fluid size="small" type="text" :value="item.rtid"
                                                            placeholder="Order Number" />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Item Number:</label>
                                                        <InputText fluid size="small" type="text"
                                                            v-model="item.itemnumber" />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Supplier ID/Name:</label>
                                                        <InputText fluid size="small" type="text"
                                                            v-model="item.seller" />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Material:</label>
                                                        <Select v-model="item.materialtype"
                                                            :options="materialTypesOptions" optionLabel="label"
                                                            optionValue="value" placeholder="Select material type"
                                                            size="small" fluid />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Source Type:</label>
                                                        <Select v-model="item.sourceType" :options="sourceTypeOptions"
                                                            optionLabel="label" optionValue="value"
                                                            placeholder="Select source type" size="small" fluid />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Carrier /
                                                            Courier:</label>
                                                        <Select v-model="item.carrier" :options="courierOptions"
                                                            optionLabel="label" optionValue="value"
                                                            placeholder="Select courier" size="small" fluid />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Listed Condition:</label>
                                                        <Select v-model="item.listedcondition"
                                                            :options="listedConditionOptions" optionLabel="label"
                                                            optionValue="value" placeholder="Select condition"
                                                            size="small" fluid />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Payment Method:</label>
                                                        <Select v-model="item.paymentmethod"
                                                            :options="paymentMethodOptions" optionLabel="label"
                                                            optionValue="value" placeholder="Select Payment Method"
                                                            size="small" fluid />
                                                    </fieldset>
                                                </template>
                                            </Card>
                                        </div>
                                        <div class="col-lg-3  mb-2">
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
                                                        <InputText type="text" size="small" fluid v-model="item.ProductModuleLoc
                                                            " readonly disabled />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Store Name:</label>
                                                        <Select v-model="item.storename" :options="storenameOptions"
                                                            optionLabel="label" optionValue="value"
                                                            placeholder=" Select Store Name" size="small" fluid />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>RPN:</label>
                                                        <InputText type="text" size="small" fluid v-model="item.RPN" />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>PRD:</label>
                                                        <InputText type="text" size="small" fluid v-model="item.PRD" />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>PCN:</label>
                                                        <InputText type="text" size="small" fluid v-model="item.PCN" />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Basket Number:</label>
                                                        <InputText type="text" size="small" fluid
                                                            v-model="item.basketnumber" />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Priority Rank:</label>
                                                        <Select v-model="item.priorityrank"
                                                            :options="priorityRanksOptions" optionLabel="label"
                                                            optionValue="value" placeholder=" Select Priority Rank"
                                                            size="small" fluid />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Validation
                                                            Status:</label>
                                                        <Select v-model="item.validation_status"
                                                            :options="validationStatusOptions" optionLabel="label"
                                                            optionValue="value" placeholder="Select Validation Status"
                                                            size="small" fluid />
                                                    </fieldset>
                                                    <fieldset>
                                                        <label>Return Status:</label>
                                                        <InputText type="text" size="small" fluid
                                                            v-model="item.returnstatus" readonly disabled />
                                                    </fieldset>
                                                </template>
                                            </Card>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mobile-view">
                                    <div class="col-md-4">
                                        <fieldset>
                                            <label><span>Supplier Notes:</span></label>
                                            <Textarea ref="supplierNotesarea" class="no-resize"
                                                v-model="item.supplierNotes" placeholder="Supplier Notes" rows="3" fluid
                                                size="small" @input="autoResize"></Textarea>
                                        </fieldset>
                                    </div>
                                    <div class="col-md-4">
                                        <fieldset>
                                            <label><span>Employee Notes:</span></label>
                                            <Textarea ref="employeeNotesarea" class="no-resize"
                                                v-model="item.employeeNotes" placeholder="Employee Notes" rows="3" fluid
                                                size="small" @input="autoResize"></Textarea>
                                        </fieldset>
                                    </div>
                                    <div class="col-md-4">
                                        <fieldset>
                                            <label><span>Sticker Notes:</span></label>
                                            <Textarea ref="stickerNotesarea" class="no-resize"
                                                v-model="item.stickerNotes" placeholder="Employee Notes" rows="3" fluid
                                                size="small" @input="autoResize"></Textarea>
                                        </fieldset>
                                    </div>
                                </div>

                            </div>

                            <!-- RIGHT: PRICING -->
                            <div class="form-col-right">
                                <Card class="shadow">
                                    <template #title>
                                        <h4 class="text-primary">
                                            Pricing
                                        </h4>
                                        <Divider />
                                    </template>
                                    <template #content>
                                        <fieldset>
                                            <label><span>Quantity</span></label>
                                            <InputText type="number" size="small" fluid class="text-end"
                                                v-model="item.quantity" />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Total Price</span></label>
                                            <InputText type="number" size="small" fluid class="text-end"
                                                v-model="item.price" />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Discount</span></label>
                                            <InputText type="number" size="small" fluid class="text-end"
                                                v-model="item.Discount" />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Tax</span></label>
                                            <InputText type="number" size="small" fluid class="text-end"
                                                v-model="item.tax" />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Shipping</span></label>
                                            <InputText type="number" size="small" fluid class="text-end"
                                                v-model="item.priceshipping" />
                                        </fieldset>

                                        <fieldset>
                                            <label><span>Refund</span></label>
                                            <InputText type="number" size="small" fluid class="text-end"
                                                v-model="item.refund" />
                                        </fieldset>

                                        <!-- Divider -->
                                        <hr class="my-4" />

                                        <fieldset>
                                            <label><span>Unit Price</span></label>
                                            <InputText type="text" size="small" fluid class="text-end bg-light"
                                                :value="unitPrice" readonly />
                                        </fieldset>
                                        <!-- Total Summary -->
                                        <fieldset>
                                            <label><span>Grand Total</span></label>
                                            <InputText type="text" size="small" fluid
                                                class="text-end bg-light fw-bold text-success" :value="grandTotal"
                                                readonly />
                                        </fieldset>
                                    </template>
                                </Card>
                            </div>



                        </div>
                        <div class="row desktop-view">
                            <div class="col-md-4">
                                <fieldset>
                                    <label><span>Supplier Notes:</span></label>
                                    <Textarea ref="supplierNotesarea" class="no-resize" v-model="item.supplierNotes"
                                        placeholder="Supplier Notes" rows="3" fluid size="small"
                                        @input="autoResize"></Textarea>
                                </fieldset>
                            </div>
                            <div class="col-md-4">
                                <fieldset>
                                    <label><span>Employee Notes:</span></label>
                                    <Textarea ref="employeeNotesarea" class="no-resize" v-model="item.employeeNotes"
                                        placeholder="Employee Notes" rows="3" fluid size="small"
                                        @input="autoResize"></Textarea>
                                </fieldset>
                            </div>
                            <div class="col-md-4">
                                <fieldset>
                                    <label><span>Sticker Notes:</span></label>
                                    <Textarea ref="stickerNotesarea" class="no-resize" v-model="item.stickerNotes"
                                        placeholder="Employee Notes" rows="3" fluid size="small"
                                        @input="autoResize"></Textarea>
                                </fieldset>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </Dialog>


        <!-- RTS Options Modal -->
        <Dialog v-model:visible="showRTSModal" modal :header="`RTS Options - RT# ${rtsCurrentItem?.rtcounter}`"
            :style="{ maxWidth: '200rem' }" :pt="{
                root: { class: 'mobile-fullscreen-dialog' }
            }">
            <div>
                <div class="rts-form-container">
                    <form @submit.prevent="saveRTSModal" class="rts-form">
                        <!-- Product Info Header -->
                        <div class="rts-product-info">
                            <div class="product-image-mini">
                                <img :src="'/images/thumbnails/' +
                                    (rtsCurrentItem?.img1 || '')
                                    " :alt="rtsCurrentItem?.ProductTitle ||
                                        'Product'
                                        " @error="handleImageError($event)" />
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
                                        <span class="label-text">Date Filed</span>
                                    </label>
                                    <InputText size="small" fluid type="date" class="rts-input"
                                        v-model="rtsForm.dateField" required />
                                </fieldset>

                                <!-- Filed IN Checkboxes -->
                                <fieldset class="rts-fieldset">
                                    <label class="rts-label">
                                        <span class="label-text">Filed IN:</span>
                                    </label>
                                    <div class="checkbox-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" v-model="rtsForm.filedInES" class="checkbox-input" />
                                            <span class="checkbox-text">ES</span>
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" v-model="rtsForm.filedInPPL"
                                                class="checkbox-input" />
                                            <span class="checkbox-text">PPL</span>
                                        </label>
                                    </div>
                                </fieldset>

                                <!-- Test Result -->
                                <fieldset class="rts-fieldset">
                                    <label class="rts-label">
                                        <span class="label-text">Test Result</span>
                                    </label>
                                    <Select :options="testResultOptions" optionLabel="label" optionValue="value" fluid
                                        size="small" v-model="rtsForm.testResult" placeholder="Select Test Result"
                                        required />
                                </fieldset>

                                <!-- Status -->
                                <fieldset class="rts-fieldset">
                                    <label class="rts-label">
                                        <span class="label-text">Status</span>
                                    </label>
                                    <Select :options="statusOptions" optionLabel="label" optionValue="value" fluid
                                        size="small" v-model="rtsForm.status" placeholder="Select Status" required />

                                </fieldset>

                                <!-- RTS Result -->
                                <fieldset class="rts-fieldset">
                                    <label class="rts-label">
                                        <span class="label-text">RTS Result</span>
                                    </label>
                                    <Select :options="rtsResultOptions" optionLabel="label" optionValue="value" fluid
                                        size="small" v-model="rtsForm.rtsResult" placeholder="Select Status" required />

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
                                            <span class="label-text">Amount:</span>
                                        </label>
                                        <InputText type="number" step="0.01" v-model="rtsForm.refundAmount"
                                            placeholder="0.00" size="small" fluid />
                                    </fieldset>

                                    <!-- Date of Refund -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text">Date of Refund</span>
                                        </label>
                                        <InputText size="small" fluid type="date" v-model="rtsForm.refundDate" />
                                    </fieldset>

                                    <!-- Reason of Return -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text">Reason of Return</span>
                                        </label>
                                        <Textarea size="small" fluid v-model="rtsForm.reasonOfReturn" rows="3"
                                            placeholder="Enter reason for return..."></Textarea>
                                    </fieldset>

                                    <!-- Return TN -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text">Return TN:</span>
                                        </label>
                                        <InputText size="small" fluid type="text" v-model="rtsForm.returnTN"
                                            placeholder="Enter tracking number" />
                                    </fieldset>

                                    <!-- Notes -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text">Notes</span>
                                        </label>
                                        <Textarea size="small" fluid v-model="rtsForm.notes" rows="4"
                                            placeholder="Additional notes..."></Textarea>
                                    </fieldset>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <template #footer>
                <Button @click="closeRTSModal" label="Cancel" severity="secondary" size=small icon="pi pi-times" />
                <Button @click="saveRTSModal" :disabled="loading" :label="loading ? 'Saving...' : 'Save'"
                    :loading="loading" icon="pi pi-save" />
            </template>
        </Dialog>

        <div v-if="false" class="modal rts-modal">
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
                                    <img :src="'/images/thumbnails/' +
                                        (rtsCurrentItem?.img1 || '')
                                        " :alt="rtsCurrentItem?.ProductTitle ||
                                            'Product'
                                            " @error="handleImageError($event)" />
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
                                            <span class="label-text">Date Filed</span>
                                        </label>
                                        <InputText size="small" fluid type="date" class="rts-input"
                                            v-model="rtsForm.dateField" required />
                                    </fieldset>

                                    <!-- Filed IN Checkboxes -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text">Filed IN:</span>
                                        </label>
                                        <div class="checkbox-group">
                                            <label class="checkbox-label">
                                                <input type="checkbox" v-model="rtsForm.filedInES"
                                                    class="checkbox-input" />
                                                <span class="checkbox-text">ES</span>
                                            </label>
                                            <label class="checkbox-label">
                                                <input type="checkbox" v-model="rtsForm.filedInPPL"
                                                    class="checkbox-input" />
                                                <span class="checkbox-text">PPL</span>
                                            </label>
                                        </div>
                                    </fieldset>

                                    <!-- Test Result -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text">Test Result</span>
                                        </label>
                                        <Select :options="testResultOptions" optionLabel="label" optionValue="value"
                                            fluid size="small" v-model="rtsForm.testResult"
                                            placeholder="Select Test Result" />
                                    </fieldset>

                                    <!-- Status -->
                                    <fieldset class="rts-fieldset">
                                        <label class="rts-label">
                                            <span class="label-text">Status</span>
                                        </label>
                                        <select class="form-control rts-select" v-model="rtsForm.status" required>
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
                                            <span class="label-text">RTS Result</span>
                                        </label>
                                        <select class="form-control rts-select" v-model="rtsForm.rtsResult" required>
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
                                                <span class="label-text">Amount:</span>
                                            </label>
                                            <input type="number" step="0.01" class="form-control rts-input"
                                                v-model="rtsForm.refundAmount" placeholder="0.00" />
                                        </fieldset>

                                        <!-- Date of Refund -->
                                        <fieldset class="rts-fieldset">
                                            <label class="rts-label">
                                                <span class="label-text">Date of Refund</span>
                                            </label>
                                            <input type="date" class="form-control rts-input"
                                                v-model="rtsForm.refundDate" />
                                        </fieldset>

                                        <!-- Reason of Return -->
                                        <fieldset class="rts-fieldset">
                                            <label class="rts-label">
                                                <span class="label-text">Reason of Return</span>
                                            </label>
                                            <textarea class="form-control rts-textarea" v-model="rtsForm.reasonOfReturn"
                                                rows="3" placeholder="Enter reason for return..."></textarea>
                                        </fieldset>

                                        <!-- Return TN -->
                                        <fieldset class="rts-fieldset">
                                            <label class="rts-label">
                                                <span class="label-text">Return TN:</span>
                                            </label>
                                            <input type="text" class="form-control rts-input" v-model="rtsForm.returnTN"
                                                placeholder="Enter tracking number" />
                                        </fieldset>

                                        <!-- Notes -->
                                        <fieldset class="rts-fieldset">
                                            <label class="rts-label">
                                                <span class="label-text">Notes</span>
                                            </label>
                                            <textarea class="form-control rts-textarea" v-model="rtsForm.notes" rows="4"
                                                placeholder="Additional notes..."></textarea>
                                        </fieldset>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeRTSModal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" @click="saveRTSModal" :disabled="loading">
                        <i class="fas fa-save me-2"></i>
                        {{ loading ? "Saving..." : "Save" }}
                    </button>
                </div>
            </div>
        </div>
        <ScrollTop />
    </div>
</template>

<script>
import { Button, Card, Dialog, Divider, InputText, ScrollTop, Select, Textarea } from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import RTS from "./rts.js";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";

const TABLE_COLUMNS = [
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
        field: "ASIN",
        header: "ASIN",
        bodyStyle: { fontSize: "14px" }
    },
    {
        field: "FNSKU",
        header: "FNSKU",
        bodyStyle: { fontSize: "14px" }
    },
    {
        field: "grading",
        header: "Grading",
        bodyStyle: { fontSize: "14px" }
    },
    {
        field: "serialnumber",
        header: "Serial Number",
        bodyStyle: { fontSize: "14px" }
    },
    {
        field: "quantity",
        header: "Quantity",
        bodyStyle: { fontSize: "14px" }
    },
    {
        field: "fulfillment_status",
        header: "Fulfillment Status",
        bodyStyle: { fontSize: "14px" }
    },
    {
        field: "ProductModuleLoc",
        header: "Module",
        bodyStyle: { fontSize: "14px" }
    },
    {
        field: "returnstatus",
        header: "Return Status",
        bodyStyle: { fontSize: "14px" }
    }
]
export default {
    mixins: [RTS],
    components: {
        XDataTable,
        Dialog, Button, TableGallery, Card, InputText, Select, Divider, Textarea, ScrollTop, TitlePage, AnimateDiv,
        ViewImageGalleryModal
    },
    data() {
        return {
            column: TABLE_COLUMNS,
            openMoreDetailModal: false,
            detailData: {},
            sourceTypeOptions: [
                { label: "ES", value: "ES" },
                { label: "AS", value: "AS" },
                { label: "XS", value: "XS" },
                { label: "PS", value: "PS" },
                { label: "RS", value: "RS" },
                { label: "B&H", value: "B&H" }
            ],
            listedConditionOptions: [
                { label: "New", value: "New" },
                { label: "Open Box", value: "Open Box" },
                { label: "Used", value: "Used" },
                { label: "For parts or not working", value: "For parts or not working" }
            ],
            paymentMethodOptions: [
                { label: "PayPal", value: "PayPal" },
                { label: "Credit/Debit Card", value: "Credit/Debit Card" },
                { label: "Cash", value: "Cash" },
                { label: "Bank Transfer", value: "Bank Transfer" },
                { label: "Check", value: "Check" }
            ],
            testResultOptions: [
                { label: "Passed", value: "Passed" },
                { label: "Failed", value: "Failed" }
            ],
            statusOptions: [
                { label: "RTS", value: "RTS" },
                { label: "Dismantle", value: "Dismantle" }
            ],
            rtsResultOptions: [
                { label: "PRNR", value: "PRNR" },
                { label: "FRNR", value: "FRNR" },
                { label: "Replacement", value: "Replacement" },
                { label: "Ship-Back", value: "Ship-Back" },
            ]
        }
    },
    computed: {
        materialTypesOptions() {
            return this.materialTypes.map((type) => ({
                value: type,
                label: type,
            }));
        },
        courierOptions() {
            return this.carrierOptions.map((carrier) => ({
                value: carrier,
                label: carrier,
            }))
        },
        storenameOptions() {
            return this.storeNames.map((store) => ({
                value: store,
                label: store,
            }))
        },
        priorityRanksOptions() {
            return this.priorityRanks.map((type) => ({ label: type, value: type }))
        },
        validationStatusOptions() {
            return this.validationStatuses.map((status) => ({ label: status, value: status }))
        },
        uniqueModuleOptions() {
            return this.uniqueModules.map((module) => ({ label: module, value: module }))
        },
    },
    methods: {
        toggleMoreDetailsModal(item) {
            console.log(item, "itemitemitemitem")
            this.openMoreDetailModal = true
            this.detailData = item
        }
    }
};
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
    width: 500px;
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
    width: 100% !important;
    /* Force same width */
    min-width: 120px !important;
    /* Match "More Details" button width */
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
    align-items: stretch;
    /* Changed from flex-start to stretch */
    width: 100%;
}

.action-buttons .btn {
    width: 100% !important;
    min-width: 120px !important;
    /* Set consistent minimum width */
    max-width: 120px !important;
    /* Set consistent maximum width */
    text-align: center !important;
    /* Center text */
    justify-content: center !important;
    /* Center content */
    padding: 6px 8px !important;
    /* Consistent padding */
    font-size: 12px !important;
    white-space: nowrap !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    /* Remove any margin */
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
        width: 100%;
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
    width: 120px;
    /* Fixed container width */
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
